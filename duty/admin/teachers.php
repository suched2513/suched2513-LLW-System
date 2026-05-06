<?php
/**
 * duty/admin/teachers.php — จัดการครูเวร + เชื่อม Telegram
 */
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();

// ── ดึง bot username ──
$stmtBot = $pdo->query("SELECT svalue FROM duty_settings WHERE skey = 'duty_bot_username'");
$botUsername = (string)($stmtBot->fetchColumn() ?? '');

// ── Action handlers ──
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        // เพิ่มครูใหม่
        $prefix   = trim($_POST['prefix']    ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone']     ?? '');

        if ($fullName !== '') {
            $stmt = $pdo->prepare(
                "INSERT INTO duty_teachers (prefix, full_name, phone) VALUES (?, ?, ?)"
            );
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
            $stmt = $pdo->prepare(
                "UPDATE duty_teachers SET prefix=?, full_name=?, phone=?, status=? WHERE id=?"
            );
            $stmt->execute([$prefix, $fullName, $phone, $status, $id]);
            $msg = 'success:บันทึกข้อมูลเรียบร้อยแล้ว';
        }
    }

    if ($action === 'gen_link') {
        // สร้าง link token ใหม่
        $id    = (int)($_POST['id'] ?? 0);
        $token = bin2hex(random_bytes(20)); // 40 chars
        $stmt  = $pdo->prepare("UPDATE duty_teachers SET link_token=?, linked_at=NULL WHERE id=?");
        $stmt->execute([$token, $id]);
        header('Location: teachers.php?linked=1&id=' . $id);
        exit();
    }

    if ($action === 'unlink') {
        // ยกเลิกการผูกบัญชี
        $id   = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare(
            "UPDATE duty_teachers
             SET telegram_user_id=NULL, telegram_username=NULL, linked_at=NULL, link_token=NULL
             WHERE id=?"
        );
        $stmt->execute([$id]);
        $msg = 'success:ยกเลิกการเชื่อมบัญชี Telegram เรียบร้อย';
    }

    if ($action === 'delete') {
        $id   = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE duty_teachers SET status='inactive' WHERE id=?");
        $stmt->execute([$id]);
        $msg = 'success:ปิดการใช้งานครูเรียบร้อย';
    }
}

// ── ดึงรายชื่อครู ──
$teachers = $pdo->query(
    "SELECT * FROM duty_teachers ORDER BY status ASC, full_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// ── ถ้าเพิ่งสร้าง link ─ แสดง QR ──
$showQrFor = null;
if (isset($_GET['linked'], $_GET['id'])) {
    $qrId = (int)$_GET['id'];
    $stmtQ = $pdo->prepare("SELECT * FROM duty_teachers WHERE id = ?");
    $stmtQ->execute([$qrId]);
    $showQrFor = $stmtQ->fetch(PDO::FETCH_ASSOC);
}

$pageTitle   = 'จัดการครูเวร';
$pageSubtitle = 'ข้อมูลครูและการเชื่อมต่อ Telegram Bot';
$activeSystem = 'duty';

require_once __DIR__ . '/../../components/layout_start.php';

// ── แสดง alert ──
if ($msg):
    $isErr  = str_starts_with($msg, 'error:');
    $msgTxt = $isErr ? substr($msg, 6) : substr($msg, 8);
?>
<script>
Swal.fire({
    icon: '<?= $isErr ? 'error' : 'success' ?>',
    title: '<?= $isErr ? 'ข้อผิดพลาด' : 'สำเร็จ' ?>',
    text: '<?= htmlspecialchars($msgTxt, ENT_QUOTES) ?>',
    confirmButtonColor: '#2563eb'
});
</script>
<?php endif; ?>

<?php if ($showQrFor && $showQrFor['link_token']): ?>
<!-- Modal แสดง QR Code -->
<div class="modal fade show d-block" style="background:rgba(0,0,0,.5);" id="qrModal">
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
                $token   = $showQrFor['link_token'];
                $tgLink  = $botUsername
                    ? "https://t.me/{$botUsername}?start={$token}"
                    : "https://t.me/YOUR_BOT?start={$token}";
                // Google Charts API สร้าง QR (ไม่ต้อง install library)
                $qrUrl = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=' . urlencode($tgLink) . '&choe=UTF-8';
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

<!-- Header Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="fas fa-chalkboard-teacher me-2 text-primary"></i>จัดการครูเวร</h4>
        <small class="text-muted">เชื่อมบัญชี Telegram เพื่อรายงานเวรด้วยรูปถ่าย</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus me-1"></i> เพิ่มครูใหม่
    </button>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="teacherTable">
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
                        <td>
                            <strong><?= htmlspecialchars($t['prefix'] . $t['full_name']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($t['phone'] ?? '-') ?></td>
                        <td class="text-center">
                            <?php if ($t['linked_at']): ?>
                                <span class="badge bg-success">
                                    <i class="fab fa-telegram me-1"></i>
                                    <?= $t['telegram_username'] ? '@' . htmlspecialchars($t['telegram_username']) : 'เชื่อมแล้ว' ?>
                                </span>
                                <br><small class="text-muted"><?= date('d/m/Y', strtotime($t['linked_at'])) ?></small>
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
                                </span>
                                <br>
                                <a href="teachers.php?linked=1&id=<?= $t['id'] ?>" class="btn btn-outline-info btn-sm mt-1">
                                    <i class="fas fa-qrcode"></i> ดู QR อีกครั้ง
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">ยังไม่เชื่อม</span>
                                <br>
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
                    <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีข้อมูลครูเวร</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
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
                    <label class="form-label fw-bold">เบอร์โทรศัพท์</label>
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

<!-- Edit Modal -->
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
    for (let o of sel.options) { o.selected = (o.value === t.prefix); }
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
