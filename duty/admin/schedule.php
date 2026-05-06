<?php
/**
 * duty/admin/schedule.php — จัดตารางเวรประจำวัน
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();
$msg = '';

// ── Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $date      = $_POST['duty_date'] ?? '';
        $shift     = in_array($_POST['shift'] ?? '', ['day','night']) ? $_POST['shift'] : 'day';
        $pointNo   = (int)($_POST['point_no'] ?? 0);
        $role      = trim($_POST['role'] ?? '');
        $teacherId = (int)($_POST['teacher_id'] ?? 0) ?: null;

        if ($date && $pointNo) {
            $stmt = $pdo->prepare(
                "INSERT INTO duty_schedule (duty_date, shift, point_no, role, teacher_id)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$date, $shift, $pointNo, $role ?: null, $teacherId]);
            $msg = 'success:เพิ่มรายการเวรเรียบร้อย';
        } else {
            $msg = 'error:กรุณากรอกวันที่และหมายเลขจุด';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM duty_schedule WHERE id=?")->execute([$id]);
        $msg = 'success:ลบรายการเวรเรียบร้อย';
    }
}

// ── Filter ──
$filterDate = $_GET['date'] ?? date('Y-m-d');

// ── ดึงตารางเวร ──
$stmtSch = $pdo->prepare(
    "SELECT ds.*, dt.full_name AS teacher_name
     FROM duty_schedule ds
     LEFT JOIN duty_teachers dt ON dt.id = ds.teacher_id
     WHERE ds.duty_date = ?
     ORDER BY ds.shift, ds.point_no, ds.teacher_seq"
);
$stmtSch->execute([$filterDate]);
$schedules = $stmtSch->fetchAll(PDO::FETCH_ASSOC);

// ── ดึงรายชื่อครูสำหรับ dropdown ──
$teachers = $pdo->query(
    "SELECT id, prefix, full_name FROM duty_teachers WHERE status='active' ORDER BY full_name"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle    = 'ตารางเวร';
$pageSubtitle = 'จัดการตารางเวรประจำวัน';
$activeSystem = 'duty';

require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if ($msg): $isErr = str_starts_with($msg,'error:'); ?>
<script>
Swal.fire({icon:'<?= $isErr?'error':'success' ?>',title:'<?= $isErr?'ข้อผิดพลาด':'สำเร็จ' ?>',
    text:'<?= htmlspecialchars(substr($msg,strpos($msg,':')+1),ENT_QUOTES) ?>',confirmButtonColor:'#2563eb'});
</script>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="fas fa-calendar-alt me-2 text-primary"></i>ตารางเวร</h4>
    </div>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
            <button class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
        </form>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i>เพิ่มเวร
        </button>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>กะ</th>
                    <th class="text-center">จุดที่</th>
                    <th>ครู</th>
                    <th>หน้าที่</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($schedules as $s): ?>
                <tr>
                    <td>
                        <?= $s['shift'] === 'day' ? '<span class="badge bg-warning text-dark">☀️ กลางวัน</span>' : '<span class="badge bg-dark">🌙 กลางคืน</span>' ?>
                    </td>
                    <td class="text-center fw-bold"><?= $s['point_no'] ?></td>
                    <td><?= htmlspecialchars($s['teacher_name'] ?? '— ยังไม่ระบุ —') ?></td>
                    <td><?= htmlspecialchars($s['role'] ?? '—') ?></td>
                    <td class="text-center">
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('ลบรายการเวรนี้?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($schedules)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">
                    ยังไม่มีตารางเวรสำหรับวันนี้
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>เพิ่มรายการเวร</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">วันที่</label>
                    <input type="date" name="duty_date" class="form-control"
                           value="<?= htmlspecialchars($filterDate) ?>" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold">กะ</label>
                        <select name="shift" class="form-select" required>
                            <option value="day">☀️ กลางวัน</option>
                            <option value="night">🌙 กลางคืน</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">จุดที่</label>
                        <input type="number" name="point_no" class="form-control" min="1" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">ครูผู้รับผิดชอบ</label>
                    <select name="teacher_id" class="form-select">
                        <option value="">— ยังไม่ระบุ —</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>">
                            <?= htmlspecialchars($t['prefix'] . $t['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">หน้าที่/บทบาท</label>
                    <input type="text" name="role" class="form-control" placeholder="เช่น ตรวจหน้าประตู">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>บันทึก</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
