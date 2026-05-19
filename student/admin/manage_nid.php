<?php
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin','wfh_admin'], true)) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();
$msg = ''; $msgType = 'success';

// ── POST: save single NID ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_nid') {
        $sid = (int)($_POST['student_db_id'] ?? 0);
        $nid = preg_replace('/[^a-zA-Z0-9]/', '', trim($_POST['national_id'] ?? ''));

        if ($sid <= 0) {
            $msg = 'ไม่พบข้อมูลนักเรียน'; $msgType = 'error';
        } elseif (strlen($nid) !== 13) {
            $msg = 'เลขบัตรประชาชนต้องมี 13 หลัก'; $msgType = 'error';
        } else {
            $hash   = password_hash($nid, PASSWORD_BCRYPT);
            $masked = substr($nid,0,1).'-'.substr($nid,1,4).'-'.substr($nid,5,5).'-'.substr($nid,10,2).'-'.substr($nid,12,1);
            $pdo->prepare("UPDATE att_students SET national_id_hash=?, national_id_masked=? WHERE id=?")
                ->execute([$hash, $masked, $sid]);
            $msg = 'บันทึกเลขบัตรประชาชนเรียบร้อยแล้ว';
        }

    } elseif ($action === 'clear_nid') {
        $sid = (int)($_POST['student_db_id'] ?? 0);
        if ($sid > 0) {
            $pdo->prepare("UPDATE att_students SET national_id_hash=NULL, national_id_masked=NULL WHERE id=?")
                ->execute([$sid]);
            $msg = 'ลบเลขบัตรประชาชนเรียบร้อยแล้ว';
        }

    } elseif ($action === 'import_csv') {
        // CSV format: student_id, national_id (no header)
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $msg = 'กรุณาเลือกไฟล์ CSV'; $msgType = 'error';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (!in_array($mime, ['text/plain','text/csv','application/csv','application/octet-stream'], true)) {
                $msg = 'ไฟล์ต้องเป็น CSV เท่านั้น'; $msgType = 'error';
            } else {
                $content = file_get_contents($file['tmp_name']);
                if (!mb_check_encoding($content, 'UTF-8') && function_exists('iconv')) {
                    $content = @iconv('WINDOWS-874','UTF-8//IGNORE', $content) ?: $content;
                }
                $lines   = array_filter(array_map('trim', explode("\n", $content)));
                $ok = $skip = $err = 0;
                $stmtGet = $pdo->prepare("SELECT id FROM att_students WHERE student_id = ? LIMIT 1");
                $stmtUpd = $pdo->prepare("UPDATE att_students SET national_id_hash=?, national_id_masked=? WHERE id=?");

                foreach ($lines as $line) {
                    if (strpos(strtolower($line), 'student') !== false) continue; // skip header
                    $cols = str_getcsv($line);
                    if (count($cols) < 2) { $skip++; continue; }

                    $rawSid = trim($cols[0]);
                    $nid    = preg_replace('/[^a-zA-Z0-9]/', '', trim($cols[1]));
                    // Normalize student_id: pad numeric to 5 digits
                    if (ctype_digit($rawSid)) $rawSid = str_pad($rawSid, 5, '0', STR_PAD_LEFT);

                    if (strlen($nid) !== 13) { $skip++; continue; }

                    $stmtGet->execute([$rawSid]);
                    $row = $stmtGet->fetch(PDO::FETCH_ASSOC);
                    if (!$row) { $skip++; continue; }

                    $hash   = password_hash($nid, PASSWORD_BCRYPT);
                    $masked = substr($nid,0,1).'-'.substr($nid,1,4).'-'.substr($nid,5,5).'-'.substr($nid,10,2).'-'.substr($nid,12,1);
                    $stmtUpd->execute([$hash, $masked, $row['id']]);
                    $ok++;
                }
                $msg = "นำเข้าสำเร็จ {$ok} คน" . ($skip > 0 ? " · ข้าม {$skip} แถว (รหัสไม่พบ/ข้อมูลไม่ถูกต้อง)" : '');
            }
        }

    } elseif ($action === 'sync_from_bus') {
        // Copy national_id_hash from bus_students → att_students
        // Only fills NULL records — never overwrites existing data
        try {
            $synced = $pdo->exec("
                UPDATE att_students a
                JOIN bus_students b
                    ON  b.student_id = a.student_id
                     OR b.student_id = LPAD(a.student_id, 5, '0')
                     OR LPAD(b.student_id, 5, '0') = a.student_id
                SET a.national_id_hash   = b.national_id_hash,
                    a.national_id_masked = b.national_id_masked
                WHERE b.national_id_hash IS NOT NULL
                  AND a.national_id_hash IS NULL
            ");
            $msg = "ซิงค์จากระบบรถสำเร็จ · เพิ่มข้อมูลให้ {$synced} คน · นักเรียนที่มีข้อมูลอยู่แล้วไม่ถูกเปลี่ยน";
        } catch (Exception $e) {
            error_log($e->getMessage());
            $msg = 'เกิดข้อผิดพลาด กรุณาลองใหม่'; $msgType = 'error';
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────
$filterClass  = trim($_GET['class'] ?? '');
$filterStatus = $_GET['status'] ?? 'all'; // all | set | missing

$classrooms = [];
try {
    $classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// ── Student list ──────────────────────────────────────────────────
$students = []; $total = 0; $withNid = 0;
try {
    $where = [];
    $params = [];
    if ($filterClass !== '') { $where[] = 'classroom = ?'; $params[] = $filterClass; }
    if ($filterStatus === 'set')     { $where[] = "national_id_hash IS NOT NULL AND national_id_masked != '[ชั่วคราว]'"; }
    if ($filterStatus === 'missing') { $where[] = "(national_id_hash IS NULL OR national_id_masked = '[ชั่วคราว]')"; }
    if ($filterStatus === 'temp')    { $where[] = "national_id_masked = '[ชั่วคราว]'"; }
    $sql = "SELECT id, student_id, name, classroom, national_id_masked,
                   (national_id_hash IS NOT NULL) as has_nid
            FROM att_students"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY classroom, student_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Overall counts
    $total   = (int)$pdo->query("SELECT COUNT(*) FROM att_students")->fetchColumn();
    $withNid = (int)$pdo->query("SELECT COUNT(*) FROM att_students WHERE national_id_hash IS NOT NULL")->fetchColumn();
    $withTemp = (int)$pdo->query("SELECT COUNT(*) FROM att_students WHERE national_id_masked = '[ชั่วคราว]'")->fetchColumn();
} catch (Exception $e) { error_log($e->getMessage()); }

$missing  = $total - $withNid;
$pct      = $total > 0 ? round($withNid / $total * 100) : 0;

// Count records in bus_students that can be synced (have NID but att_students doesn't)
$busReadyToSync = 0;
try {
    $busReadyToSync = (int)$pdo->query("
        SELECT COUNT(DISTINCT a.id)
        FROM att_students a
        JOIN bus_students b
            ON  b.student_id = a.student_id
             OR b.student_id = LPAD(a.student_id, 5, '0')
             OR LPAD(b.student_id, 5, '0') = a.student_id
        WHERE b.national_id_hash IS NOT NULL
          AND a.national_id_hash IS NULL
    ")->fetchColumn();
} catch (Exception $e) { error_log($e->getMessage()); }

$pageTitle    = 'จัดการเลขบัตรประชาชนนักเรียน';
$pageSubtitle = 'สำหรับระบบพอร์ทัลนักเรียน';
$activeSystem = 'attendance';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-<?= $msgType === 'error' ? 'exclamation-triangle-fill' : 'check-circle-fill' ?>"></i>
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="container-fluid">

<!-- ── Progress Overview ─────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="fw-black mb-0">ความคืบหน้าการป้อนข้อมูล</h6>
                        <small class="text-muted">นักเรียนที่มีเลขบัตรประชาชนในระบบ</small>
                    </div>
                    <span class="fs-4 fw-black text-primary"><?= $pct ?>%</span>
                </div>
                <div class="progress mb-2" style="height:12px;border-radius:99px">
                    <div class="progress-bar bg-primary" style="width:<?= $pct ?>%;border-radius:99px"></div>
                </div>
                <div class="d-flex flex-wrap gap-3 text-sm">
                    <span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i><?= $withNid - $withTemp ?> คน — เลขบัตรจริง</span>
                    <?php if ($withTemp > 0): ?>
                    <span class="text-warning fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i><?= $withTemp ?> คน — รหัสชั่วคราว (0000000000000)</span>
                    <?php endif; ?>
                    <?php if ($missing > 0): ?>
                    <span class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i><?= $missing ?> คน — ยังไม่มีข้อมูล</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 d-flex flex-column gap-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body d-flex flex-column justify-content-center">
                <p class="small fw-bold opacity-75 mb-1">Import ทีเดียว (CSV)</p>
                <p class="small mb-3 opacity-75">รูปแบบ: <code class="text-warning">รหัสนักเรียน,เลขบัตร13หลัก</code></p>
                <button class="btn btn-light btn-sm fw-bold" onclick="document.getElementById('modal-csv').classList.remove('hidden')">
                    <i class="bi bi-upload me-1"></i>นำเข้า CSV
                </button>
            </div>
        </div>
        <div class="card border-0 shadow-sm <?= $busReadyToSync > 0 ? 'bg-success' : 'bg-secondary' ?> text-white">
            <div class="card-body d-flex flex-column justify-content-center">
                <p class="small fw-bold opacity-75 mb-1">ซิงค์จากระบบรถ (bus_students)</p>
                <?php if ($busReadyToSync > 0): ?>
                    <p class="small mb-3 opacity-75">พร้อมซิงค์ <strong class="text-white"><?= $busReadyToSync ?> คน</strong> · ไม่แก้ไขข้อมูลเดิม</p>
                    <form method="POST" onsubmit="return confirm('ซิงค์เลขบัตรประชาชนจากระบบรถ?\n\nจะเติมเฉพาะนักเรียนที่ยังไม่มีข้อมูล (<?= $busReadyToSync ?> คน)\nข้อมูลที่มีอยู่แล้วจะไม่ถูกเปลี่ยน')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="sync_from_bus">
                        <button type="submit" class="btn btn-light btn-sm fw-bold">
                            <i class="bi bi-arrow-repeat me-1"></i>ซิงค์ <?= $busReadyToSync ?> คน
                        </button>
                    </form>
                <?php else: ?>
                    <p class="small mb-0 opacity-75">
                        <?= $missing > 0
                            ? 'bus_students ไม่มีข้อมูลเลขบัตรที่จะซิงค์ → ใช้ Import CSV แทน'
                            : 'ซิงค์ครบแล้ว ✓' ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Filter Bar ─────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
            <select name="class" class="form-select form-select-sm w-auto fw-bold" onchange="this.form.submit()">
                <option value="">ทุกห้องเรียน</option>
                <?php foreach ($classrooms as $cls): ?>
                <option value="<?= htmlspecialchars($cls) ?>" <?= $filterClass === $cls ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cls) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div class="btn-group btn-group-sm flex-wrap">
                <a href="?class=<?= urlencode($filterClass) ?>&status=all"
                   class="btn <?= $filterStatus === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?> fw-bold">ทั้งหมด</a>
                <a href="?class=<?= urlencode($filterClass) ?>&status=set"
                   class="btn <?= $filterStatus === 'set' ? 'btn-success' : 'btn-outline-secondary' ?> fw-bold">
                    เลขบัตรจริง
                </a>
                <?php if ($withTemp > 0): ?>
                <a href="?class=<?= urlencode($filterClass) ?>&status=temp"
                   class="btn <?= $filterStatus === 'temp' ? 'btn-warning' : 'btn-outline-secondary' ?> fw-bold">
                    รหัสชั่วคราว (<?= $withTemp ?>)
                </a>
                <?php endif; ?>
                <a href="?class=<?= urlencode($filterClass) ?>&status=missing"
                   class="btn <?= $filterStatus === 'missing' ? 'btn-danger' : 'btn-outline-secondary' ?> fw-bold">
                    ต้องอัพเดท <?= $filterStatus === 'all' ? '(' . ($missing + $withTemp) . ')' : '' ?>
                </a>
            </div>
            <span class="text-muted small ms-auto">แสดง <?= count($students) ?> รายการ</span>
        </form>
    </div>
</div>

<!-- ── Student Table ──────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
        <p class="text-center text-muted py-5">ไม่มีรายการ</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-xs fw-bold text-uppercase text-muted ps-4">#</th>
                        <th class="text-xs fw-bold text-uppercase text-muted">นักเรียน</th>
                        <th class="text-xs fw-bold text-uppercase text-muted">ห้อง</th>
                        <th class="text-xs fw-bold text-uppercase text-muted">เลขบัตร</th>
                        <th class="text-xs fw-bold text-uppercase text-muted text-end pe-4">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $i => $s): ?>
                <tr>
                    <td class="ps-4 text-muted small"><?= $i + 1 ?></td>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($s['student_id'], ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary fw-bold"><?= htmlspecialchars($s['classroom'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                        <?php if ($s['has_nid']): ?>
                            <span class="badge bg-success bg-opacity-10 text-success fw-bold">
                                <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($s['national_id_masked'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger fw-bold">
                                <i class="bi bi-x-circle me-1"></i>ยังไม่มีข้อมูล
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-outline-primary fw-bold"
                                onclick="openEdit(<?= (int)$s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name']), ENT_QUOTES, 'UTF-8') ?>', <?= $s['has_nid'] ? 'true' : 'false' ?>)">
                            <i class="bi bi-pencil-fill"></i> <?= $s['has_nid'] ? 'แก้ไข' : 'เพิ่ม' ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /container-fluid -->

<!-- ── Modal: Edit NID ───────────────────────────────────────────── -->
<div class="modal fade" id="modal-edit-nid" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_nid">
                <input type="hidden" name="student_db_id" id="editStudentId">
                <div class="modal-header border-0">
                    <h6 class="modal-title fw-black" id="editStudentName"></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold small">เลขบัตรประชาชน 13 หลัก</label>
                    <input type="text" name="national_id" id="editNidInput"
                           class="form-control fw-bold" maxlength="13" inputmode="numeric"
                           placeholder="x-xxxx-xxxxx-xx-x" required>
                    <div class="form-text">ไม่ต้องใส่เครื่องหมาย - (ขีด)</div>
                </div>
                <div class="modal-footer border-0 d-flex justify-content-between">
                    <button type="button" id="btnClearNid" class="btn btn-sm btn-outline-danger fw-bold d-none"
                            onclick="clearNid()">
                        <i class="bi bi-trash"></i> ลบออก
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-sm btn-primary fw-bold">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Modal: CSV Import ──────────────────────────────────────────── -->
<div class="modal fade" id="modal-csv" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import_csv">
                <div class="modal-header border-0">
                    <h6 class="modal-title fw-black"><i class="bi bi-upload me-2"></i>นำเข้าจาก CSV</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info border-0 small fw-bold">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        รูปแบบไฟล์ CSV (ไม่มีหัวตาราง):<br>
                        <code>รหัสนักเรียน,เลขบัตรประชาชน13หลัก</code><br>
                        <small class="text-muted">เช่น: <code>04853,1234567890123</code></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">เลือกไฟล์ CSV</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-exclamation-triangle me-1 text-warning"></i>
                        หากนักเรียนมีเลขบัตรอยู่แล้ว ข้อมูลใหม่จะ<strong>ทับ</strong>ข้อมูลเดิม
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="bi bi-upload me-1"></i>นำเข้า
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentStudentId = 0;
let currentHasNid    = false;

function openEdit(id, name, hasNid) {
    currentStudentId = id;
    currentHasNid    = hasNid;
    document.getElementById('editStudentId').value   = id;
    document.getElementById('editStudentName').textContent = name;
    document.getElementById('editNidInput').value    = '';
    document.getElementById('btnClearNid').classList.toggle('d-none', !hasNid);
    new bootstrap.Modal(document.getElementById('modal-edit-nid')).show();
}

function clearNid() {
    if (!confirm('ลบเลขบัตรประชาชนของนักเรียนคนนี้ออก?')) return;
    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `<?= csrf_field() ?>
        <input name="action" value="clear_nid">
        <input name="student_db_id" value="${currentStudentId}">`;
    document.body.appendChild(f);
    f.submit();
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
