<?php
session_start();
require_once __DIR__ . '/../../config.php';

// Staff only
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin','wfh_admin','att_teacher','bus_admin'], true)) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();

if (!function_exists('busGetSemester')) {
    function busGetSemester(): string {
        $m = (int)date('n'); $y = (int)date('Y') + 543;
        return $y . '-' . ($m >= 5 && $m <= 10 ? 1 : 2);
    }
}
$semester     = $_GET['semester'] ?? busGetSemester();
$filterType   = $_GET['type'] ?? 'all';
$canConfirm   = in_array($_SESSION['llw_role'], ['super_admin','wfh_admin','bus_admin'], true);

// Handle confirm/unconfirm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canConfirm) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $tid    = (int)($_POST['transport_id'] ?? 0);
    if ($tid > 0 && in_array($action, ['confirm','unconfirm'], true)) {
        try {
            if ($action === 'confirm') {
                $pdo->prepare("UPDATE student_transport SET status='confirmed', confirmed_by=?, confirmed_at=NOW() WHERE id=?")
                    ->execute([$_SESSION['user_id'], $tid]);
            } else {
                $pdo->prepare("UPDATE student_transport SET status='submitted', confirmed_by=NULL, confirmed_at=NULL WHERE id=?")
                    ->execute([$tid]);
            }
        } catch (Exception $e) { error_log($e->getMessage()); }
    }
    header('Location: ?semester=' . urlencode($semester) . '&type=' . urlencode($filterType));
    exit();
}

// Summary counts
$summary = [];
try {
    $stmt = $pdo->prepare("
        SELECT transport_type, COUNT(*) as cnt
        FROM student_transport WHERE semester = ?
        GROUP BY transport_type ORDER BY cnt DESC
    ");
    $stmt->execute([$semester]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $summary[$r['transport_type']] = (int)$r['cnt'];
    }
} catch (Exception $e) { error_log($e->getMessage()); }

$totalSurveyed = array_sum($summary);

// Total students
$totalStudents = 0;
try {
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM att_students WHERE national_id_hash IS NOT NULL")->fetchColumn();
} catch (Exception $e) {}

// Detail list
$rows = [];
try {
    $where  = $filterType !== 'all' ? 'AND st.transport_type = :type' : '';
    $stmt   = $pdo->prepare("
        SELECT st.id, st.transport_type, st.route_id, st.home_village, st.note,
               st.status, st.created_at, st.updated_at,
               a.student_id, a.name, a.classroom,
               br.route_code, br.route_name
        FROM student_transport st
        JOIN att_students a ON a.id = st.att_student_id
        LEFT JOIN bus_routes br ON br.id = st.route_id
        WHERE st.semester = :sem $where
        ORDER BY a.classroom, a.student_id
    ");
    $params = [':sem' => $semester];
    if ($filterType !== 'all') $params[':type'] = $filterType;
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); }

$typeInfo = [
    'school_bus'  => ['label'=>'ใช้รถรับส่งโรงเรียน',     'icon'=>'bi-bus-front-fill',    'color'=>'orange'],
    'motorcycle'  => ['label'=>'ขี่มอเตอร์ไซค์',           'icon'=>'bi-bicycle',           'color'=>'amber'],
    'bicycle'     => ['label'=>'ขี่จักรยาน',                'icon'=>'bi-bicycle',           'color'=>'lime'],
    'walk'        => ['label'=>'เดินมาโรงเรียน',            'icon'=>'bi-person-walking',    'color'=>'emerald'],
    'private_car' => ['label'=>'รถส่วนตัว/ผู้ปกครอง',      'icon'=>'bi-car-front-fill',    'color'=>'blue'],
    'other'       => ['label'=>'อื่นๆ',                     'icon'=>'bi-three-dots-vertical','color'=>'slate'],
];

$pageTitle    = 'สรุปแบบสำรวจการเดินทาง';
$pageSubtitle = 'ภาคเรียน ' . $semester;
$activeSystem = 'portal';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<div class="container-fluid">

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="display-6 fw-black text-primary"><?= $totalSurveyed ?></div>
            <div class="small text-muted fw-bold">กรอกแบบสำรวจแล้ว</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="display-6 fw-black text-danger"><?= max(0, $totalStudents - $totalSurveyed) ?></div>
            <div class="small text-muted fw-bold">ยังไม่กรอก</div>
        </div>
    </div>
    <?php $busCnt = $summary['school_bus'] ?? 0; ?>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="display-6 fw-black text-warning"><?= $busCnt ?></div>
            <div class="small text-muted fw-bold">ใช้รถโรงเรียน</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="display-6 fw-black text-success"><?= $totalSurveyed > 0 ? round($totalSurveyed/$totalStudents*100) : 0 ?>%</div>
            <div class="small text-muted fw-bold">ตอบกลับแล้ว</div>
        </div>
    </div>
</div>

<!-- Type breakdown -->
<div class="row g-3 mb-4">
    <?php foreach ($typeInfo as $key => $info): $cnt = $summary[$key] ?? 0; ?>
    <div class="col-6 col-md-2">
        <a href="?semester=<?= urlencode($semester) ?>&type=<?= $key ?>"
           class="card border-0 shadow-sm text-decoration-none h-100 <?= $filterType === $key ? 'border-primary border' : '' ?>">
            <div class="card-body text-center py-3">
                <i class="bi <?= $info['icon'] ?> text-<?= $info['color'] === 'lime' ? 'success' : $info['color'] ?>-600 fs-4"></i>
                <div class="fw-black fs-5 mt-1"><?= $cnt ?></div>
                <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($info['label']) ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        <a href="?semester=<?= urlencode($semester) ?>&type=all"
           class="btn btn-sm <?= $filterType === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?> fw-bold">ทั้งหมด</a>
        <?php foreach ($typeInfo as $key => $info): ?>
        <a href="?semester=<?= urlencode($semester) ?>&type=<?= $key ?>"
           class="btn btn-sm <?= $filterType === $key ? 'btn-primary' : 'btn-outline-secondary' ?> fw-bold">
            <?= htmlspecialchars($info['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Data table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-black mb-0"><i class="bi bi-table me-2"></i>รายชื่อ (<?= count($rows) ?> คน)</h6>
        <?php if ($canConfirm && count($rows) > 0): ?>
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="confirm_all">
            <small class="text-muted me-2">เลือก Confirm รายบุคคลในตาราง</small>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
        <p class="text-center text-muted py-5">ไม่มีข้อมูล</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-xs fw-bold text-uppercase text-muted ps-4">นักเรียน</th>
                        <th class="text-xs fw-bold text-uppercase text-muted">การเดินทาง</th>
                        <th class="text-xs fw-bold text-uppercase text-muted">พื้นที่</th>
                        <th class="text-xs fw-bold text-uppercase text-muted text-center">สถานะ</th>
                        <?php if ($canConfirm): ?><th class="text-xs fw-bold text-uppercase text-muted text-end pe-4">จัดการ</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r):
                        $ti = $typeInfo[$r['transport_type']] ?? ['label'=>$r['transport_type'],'icon'=>'bi-question','color'=>'slate'];
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold"><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($r['student_id'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($r['classroom'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td>
                            <div class="fw-bold small"><i class="bi <?= $ti['icon'] ?> me-1"></i><?= htmlspecialchars($ti['label']) ?></div>
                            <?php if ($r['route_name']): ?>
                            <div class="small text-muted">สาย <?= htmlspecialchars($r['route_code'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($r['route_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= htmlspecialchars($r['home_village'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-center">
                            <span class="badge <?= $r['status'] === 'confirmed' ? 'bg-success' : 'bg-warning text-dark' ?> bg-opacity-10
                                          text-<?= $r['status'] === 'confirmed' ? 'success' : 'warning' ?> fw-bold">
                                <?= $r['status'] === 'confirmed' ? 'ยืนยันแล้ว' : 'รอยืนยัน' ?>
                            </span>
                        </td>
                        <?php if ($canConfirm): ?>
                        <td class="text-end pe-4">
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="transport_id" value="<?= (int)$r['id'] ?>">
                                <?php if ($r['status'] === 'submitted'): ?>
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn btn-sm btn-success fw-bold">
                                    <i class="bi bi-check-lg"></i> ยืนยัน
                                </button>
                                <?php else: ?>
                                <input type="hidden" name="action" value="unconfirm">
                                <button type="submit" class="btn btn-sm btn-outline-secondary btn-sm">ยกเลิก</button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

</div>
<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
