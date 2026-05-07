<?php
/**
 * duty/admin/teachers.php — จัดการครูเวร + เชื่อม Telegram
 * ดึงรายชื่อครูจาก att_teachers (ฐานข้อมูลกลาง)
 */
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();

// ── Auto-run migration: เพิ่ม att_teacher_id ถ้ายังไม่มี (MySQL 5.7 compatible) ──
try {
    // ตรวจว่า column มีอยู่แล้วหรือยัง
    $colCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'duty_teachers'
           AND COLUMN_NAME  = 'att_teacher_id'"
    );
    $colCheck->execute();
    if ((int)$colCheck->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE duty_teachers ADD COLUMN att_teacher_id INT NULL COMMENT 'FK att_teachers.id'");
    }
    // ตรวจว่า unique key มีหรือยัง
    $keyCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'duty_teachers'
           AND INDEX_NAME   = 'uk_att_teacher_id'"
    );
    $keyCheck->execute();
    if ((int)$keyCheck->fetchColumn() === 0) {
        try {
            $pdo->exec("ALTER TABLE duty_teachers ADD UNIQUE KEY uk_att_teacher_id (att_teacher_id)");
        } catch (Exception $e) { /* ignore duplicate key error */ }
    }
} catch (Exception $e) {
    error_log('duty/teachers migration: ' . $e->getMessage());
}

// ── ดึง bot username ──
$stmtBot = $pdo->query("SELECT svalue FROM duty_settings WHERE skey = 'duty_bot_username'");
$botUsername = (string)($stmtBot->fetchColumn() ?? '');

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ── นำเข้าครูจาก att_teachers ──
    if ($action === 'import_from_att') {
        $ids = $_POST['att_ids'] ?? [];
        $imported = 0;

        // ดึงรายชื่อที่เลือก
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtAtt = $pdo->prepare(
                "SELECT at.id, at.name, at.username,
                        lu.firstname, lu.lastname
                 FROM att_teachers at
                 LEFT JOIN llw_users lu ON lu.user_id = at.llw_user_id
                 WHERE at.id IN ({$placeholders})"
            );
            $stmtAtt->execute($ids);
            $attTeachers = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

            // INSERT โดยใช้ att_teacher_id เป็น unique key (ไม่ block ครูชื่อซ้ำ)
            $stmtIns = $pdo->prepare(
                "INSERT INTO duty_teachers (full_name, att_teacher_id, status)
                 VALUES (?, ?, 'active')
                 ON DUPLICATE KEY UPDATE full_name = VALUES(full_name)"
            );

            foreach ($attTeachers as $at) {
                // fallback chain: llw_users fullname → at.name → at.username → ครู#id
                $fullName = trim(($at['firstname'] ?? '') . ' ' . ($at['lastname'] ?? ''));
                if ($fullName === '') $fullName = trim($at['name'] ?? '');
                if ($fullName === '') $fullName = trim($at['username'] ?? '');
                if ($fullName === '') $fullName = 'ครู#' . $at['id'];

                $stmtIns->execute([$fullName, (int)$at['id']]);
                if ($stmtIns->rowCount() > 0) $imported++;
            }
        }
        $msg = $imported > 0
            ? "success:นำเข้าครู {$imported} คนเรียบร้อยแล้ว"
            : "warning:ไม่มีรายชื่อใหม่ (ครูเหล่านี้อยู่ในระบบแล้วทั้งหมด)";
    }

    if ($action === 'add') {
        $prefix   = trim($_POST['prefix']    ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone']     ?? '');
        if ($fullName !== '') {
            $stmt = $pdo->prepare("INSERT INTO duty_teachers (prefix, full_name, phone) VALUES (?, ?, ?)");
            $stmt->execute([$prefix, $fullName, $phone]);
            $msg = 'success:เพิ่มครูเรียบร้อยแล้ว';
        } else {
            $msg = 'error:กรุณากรอกชื่อ-สกุล';
        }
    }

    if ($action === 'edit') {
        $id       = (int)($_POST['id']        ?? 0);
        $prefix   = trim($_POST['prefix']     ?? '');
        $fullName = trim($_POST['full_name']  ?? '');
        $phone    = trim($_POST['phone']      ?? '');
        $status   = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
        if ($id && $fullName !== '') {
            $pdo->prepare("UPDATE duty_teachers SET prefix=?, full_name=?, phone=?, status=? WHERE id=?")
                ->execute([$prefix, $fullName, $phone, $status, $id]);
            $msg = 'success:บันทึกข้อมูลเรียบร้อยแล้ว';
        }
    }

    if ($action === 'gen_link') {
        $id    = (int)($_POST['id'] ?? 0);
        $token = bin2hex(random_bytes(20));
        $pdo->prepare("UPDATE duty_teachers SET link_token=?, linked_at=NULL WHERE id=?")->execute([$token, $id]);
        header('Location: teachers.php?linked=1&id=' . $id);
        exit();
    }

    if ($action === 'unlink') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE duty_teachers SET telegram_user_id=NULL, telegram_username=NULL, linked_at=NULL, link_token=NULL WHERE id=?")
            ->execute([$id]);
        $msg = 'success:ยกเลิกการเชื่อมบัญชี Telegram เรียบร้อย';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE duty_teachers SET status='inactive' WHERE id=?")->execute([$id]);
        $msg = 'success:ปิดการใช้งานครูเรียบร้อย';
    }
}

// ── Auto-fix: อัปเดตชื่อว่างใน duty_teachers จาก att_teachers/llw_users ──
// กรณีครูถูก import ไปก่อนหน้าแต่ชื่อว่าง จะถูก re-sync อัตโนมัติ
try {
    $pdo->exec("
        UPDATE duty_teachers dt
        JOIN (
            SELECT
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(lu.firstname,''),' ',COALESCE(lu.lastname,''))), ''),
                    NULLIF(TRIM(at.name), ''),
                    NULLIF(TRIM(at.username), '')
                ) AS resolved_name
            FROM att_teachers at
            LEFT JOIN llw_users lu ON lu.user_id = at.llw_user_id
        ) src ON src.resolved_name = dt.full_name OR dt.full_name = ''
        SET dt.full_name = src.resolved_name
        WHERE (dt.full_name IS NULL OR TRIM(dt.full_name) = '')
          AND src.resolved_name IS NOT NULL
          AND src.resolved_name != ''
    ");
} catch (Exception $e) {
    // ไม่ block ถ้า sync ล้มเหลว
}

// ── ดึงรายชื่อครูเวร ──
$teachers = $pdo->query(
    "SELECT * FROM duty_teachers ORDER BY status ASC, full_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// ── ดึง att_teachers ทั้งหมดสำหรับ modal นำเข้า ──
// fallback chain: llw_users fullname → at.name → at.username (กรณีชื่อว่างทุก field)
$attTeachers = $pdo->query(
    "SELECT at.id, at.name, at.username,
            COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(lu.firstname,''),' ',COALESCE(lu.lastname,''))), ''),
                NULLIF(TRIM(at.name), ''),
                NULLIF(TRIM(at.username), ''),
                CONCAT('ครู#', at.id)
            ) AS display_name
     FROM att_teachers at
     LEFT JOIN llw_users lu ON lu.user_id = at.llw_user_id
     ORDER BY display_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// ── ดึง att_teacher_id ที่ import ไปแล้ว (ป้องกัน import ซ้ำโดยใช้ ID) ──
try {
    $existingAttIds = $pdo->query("SELECT att_teacher_id FROM duty_teachers WHERE att_teacher_id IS NOT NULL")
        ->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $existingAttIds = [];
}

// ── ถ้าเพิ่งสร้าง link → แสดง QR ──
$showQrFor = null;
if (isset($_GET['linked'], $_GET['id'])) {
    $stmtQ = $pdo->prepare("SELECT * FROM duty_teachers WHERE id = ?");
    $stmtQ->execute([(int)$_GET['id']]);
    $showQrFor = $stmtQ->fetch(PDO::FETCH_ASSOC);
}

$pageTitle    = 'จัดการครูเวร';
$pageSubtitle = 'ข้อมูลครูและการเชื่อมต่อ Telegram Bot';
$activeSystem = 'duty';

require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if ($msg):
    $isErr  = str_starts_with($msg, 'error:');
    $isWarn = str_starts_with($msg, 'warning:');
    $icon   = $isErr ? 'error' : ($isWarn ? 'warning' : 'success');
    $msgTxt = substr($msg, strpos($msg, ':') + 1);
?>
<script>
Swal.fire({icon:'<?= $icon ?>',title:'<?= $isErr?'ข้อผิดพลาด':($isWarn?'แจ้งเตือน':'สำเร็จ') ?>',
    text:'<?= htmlspecialchars($msgTxt, ENT_QUOTES) ?>',confirmButtonColor:'#2563eb'});
</script>
<?php endif; ?>

<?php if ($showQrFor && $showQrFor['link_token']): ?>
<div class="modal fade show d-block" style="background:rgba(0,0,0,.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-qrcode me-2 text-primary"></i>
                    ลิงก์เชื่อมบัญชี — <?= htmlspecialchars($showQrFor['full_name']) ?>
                </h5>
                <a href="teachers.php" class="btn-close"></a>
            </div>
            <div class="modal-body text-center">
                <?php
                $token  = $showQrFor['link_token'];
                $tgLink = $botUsername
                    ? "https://t.me/{$botUsername}?start={$token}"
                    : "https://t.me/YOUR_BOT?start={$token}";
                $qrUrl  = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=' . urlencode($tgLink) . '&choe=UTF-8';
                ?>
                <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code" class="img-fluid rounded mb-3" style="max-width:220px;">
                <p class="small text-muted mb-2">ให้ครูสแกน QR หรือกดลิงก์ด้านล่าง</p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($tgLink) ?>" id="tgLink" readonly>
                    <button class="btn btn-outline-primary btn-sm"
                            onclick="navigator.clipboard.writeText(document.getElementById('tgLink').value);
                                     Swal.fire({icon:'success',title:'คัดลอกแล้ว',timer:1200,showConfirmButton:false})">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <div class="alert alert-warning text-start small mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    ลิงก์ใช้ได้ครั้งเดียว จะหมดอายุเมื่อครูกด Start แล้ว
                </div>
            </div>
            <div class="modal-footer">
                <a href="teachers.php" class="btn btn-secondary">ปิด</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="fas fa-chalkboard-teacher me-2 text-primary"></i>จัดการครูเวร</h4>
        <small class="text-muted">เชื่อมบัญชี Telegram เพื่อรายงานเวรด้วยรูปถ่าย</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-file-import me-1"></i> นำเข้าจากระบบ
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> เพิ่มครูใหม่
        </button>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>ชื่อ-สกุล</th>
                        <th>เบอร์โทร</th>
                        <th class="text-center">Telegram</th>
                        <th class="text-center">สถานะ</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($teachers as $i => $t): ?>
                    <tr class="<?= $t['status'] === 'inactive' ? 'table-secondary opacity-50' : '' ?>">
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($t['prefix'] . $t['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($t['phone'] ?? '-') ?></td>
                        <td class="text-center">
                            <?php if ($t['linked_at']): ?>
                                <span class="badge bg-success">
                                    <i class="fab fa-telegram me-1"></i>
                                    <?= $t['telegram_username'] ? '@' . htmlspecialchars($t['telegram_username']) : 'เชื่อมแล้ว' ?>
                                </span><br>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($t['linked_at'])) ?></small>
                                <form method="POST" class="d-inline mt-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="unlink">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm mt-1"
                                            onclick="return confirm('ยืนยันยกเลิกการผูกบัญชี?')">
                                        <i class="fas fa-unlink"></i> ยกเลิก
                                    </button>
                                </form>
                            <?php elseif ($t['link_token']): ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-clock me-1"></i>รอการเชื่อม
                                </span><br>
                                <a href="teachers.php?linked=1&id=<?= $t['id'] ?>" class="btn btn-outline-info btn-sm mt-1">
                                    <i class="fas fa-qrcode"></i> ดู QR อีกครั้ง
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">ยังไม่เชื่อม</span><br>
                                <form method="POST" class="d-inline mt-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="gen_link">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-outline-primary btn-sm mt-1">
                                        <i class="fas fa-link"></i> สร้าง Link
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $t['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $t['status'] === 'active' ? 'ใช้งาน' : 'ปิดการใช้งาน' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-outline-secondary btn-sm"
                                    onclick='openEdit(<?= json_encode($t, JSON_UNESCAPED_UNICODE) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($teachers)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">
                        <i class="fas fa-users fa-2x mb-2 d-block opacity-25"></i>
                        ยังไม่มีข้อมูลครูเวร<br>
                        <button class="btn btn-success btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="fas fa-file-import me-1"></i> นำเข้าจากระบบเลย
                        </button>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ════ Modal: นำเข้าจาก att_teachers ════ -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_from_att">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-import me-2 text-success"></i>
                    นำเข้าครูจากฐานข้อมูลกลาง
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    เลือกครูจากระบบเช็คชื่อ (att_teachers) ที่ต้องการเพิ่มเข้าระบบเวร<br>
                    ชื่อที่มีเครื่องหมาย <span class="badge bg-secondary">มีแล้ว</span> อยู่ในระบบแล้ว
                </p>

                <!-- Search -->
                <div class="mb-3">
                    <input type="text" id="searchAtt" class="form-control"
                           placeholder="🔍 ค้นหาชื่อครู..." oninput="filterAttList()">
                </div>

                <!-- Select All -->
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="selectAll"
                           onchange="document.querySelectorAll('.att-check:not(:disabled)').forEach(c=>c.checked=this.checked)">
                    <label class="form-check-label fw-bold" for="selectAll">เลือกทั้งหมด</label>
                </div>

                <div id="attList" style="max-height:350px;overflow-y:auto;" class="border rounded p-2">
                    <?php foreach ($attTeachers as $at):
                        $alreadyIn = in_array($at['id'], $existingAttIds);
                    ?>
                    <div class="att-item d-flex align-items-center py-1 border-bottom"
                         data-name="<?= htmlspecialchars(mb_strtolower($at['display_name'])) ?>">
                        <div class="form-check flex-fill mb-0">
                            <input class="form-check-input att-check"
                                   type="checkbox"
                                   name="att_ids[]"
                                   value="<?= $at['id'] ?>"
                                   id="att_<?= $at['id'] ?>"
                                   <?= $alreadyIn ? 'disabled checked' : '' ?>>
                            <label class="form-check-label w-100" for="att_<?= $at['id'] ?>">
                                <?= htmlspecialchars($at['display_name']) ?>
                            </label>
                        </div>
                        <?php if ($alreadyIn): ?>
                        <span class="badge bg-secondary ms-2 flex-shrink-0">มีแล้ว</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($attTeachers)): ?>
                    <p class="text-muted text-center py-3">ไม่พบข้อมูลครูในระบบ att_teachers</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-file-import me-1"></i> นำเข้าที่เลือก
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: เพิ่มครูใหม่ด้วยตนเอง -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>เพิ่มครูเวรใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">คำนำหน้า</label>
                    <select name="prefix" class="form-select">
                        <option value="">— ไม่มี —</option>
                        <option value="นาย">นาย</option>
                        <option value="นาง">นาง</option>
                        <option value="นางสาว">นางสาว</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">ชื่อ-สกุล <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required placeholder="เช่น สมชาย ใจดี">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">เบอร์โทร</label>
                    <input type="text" name="phone" class="form-control" placeholder="0812345678">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: แก้ไข -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลครู</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">คำนำหน้า</label>
                    <select name="prefix" id="editPrefix" class="form-select">
                        <option value="">— ไม่มี —</option>
                        <option value="นาย">นาย</option>
                        <option value="นาง">นาง</option>
                        <option value="นางสาว">นางสาว</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">ชื่อ-สกุล <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="editName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">เบอร์โทร</label>
                    <input type="text" name="phone" id="editPhone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">สถานะ</label>
                    <select name="status" id="editStatus" class="form-select">
                        <option value="active">ใช้งาน</option>
                        <option value="inactive">ปิดการใช้งาน</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(t) {
    document.getElementById('editId').value     = t.id;
    document.getElementById('editName').value   = t.full_name;
    document.getElementById('editPhone').value  = t.phone || '';
    document.getElementById('editStatus').value = t.status;
    const sel = document.getElementById('editPrefix');
    for (let o of sel.options) o.selected = (o.value === t.prefix);
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function filterAttList() {
    const q = document.getElementById('searchAtt').value.toLowerCase();
    document.querySelectorAll('.att-item').forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
