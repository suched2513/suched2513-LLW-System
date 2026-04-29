<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header("Location: ../login.php"); exit();
}

// ฟิลเตอร์
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_dept  = (int)($_GET['dept'] ?? 0);

$base_sql = "
    SELECT u.firstname, u.lastname, u.position, d.dept_name,
           t.log_date, t.check_in_time, t.check_out_time, t.check_in_status,
           t.check_in_lat, t.check_in_lng
    FROM wfh_timelogs t
    JOIN wfh_users u ON t.user_id = u.user_id
    LEFT JOIN wfh_departments d ON u.dept_id = d.dept_id
    WHERE DATE_FORMAT(t.log_date,'%Y-%m') = ?";

if ($filter_dept) {
    $stmt = $conn->prepare($base_sql . " AND u.dept_id = ? ORDER BY t.log_date DESC, t.check_in_time ASC");
    $stmt->bind_param('si', $filter_month, $filter_dept);
} else {
    $stmt = $conn->prepare($base_sql . " ORDER BY t.log_date DESC, t.check_in_time ASC");
    $stmt->bind_param('s', $filter_month);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$departments = $conn->query("SELECT * FROM wfh_departments")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน - WFH:LLW</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { font-family: 'Sarabun', sans-serif; }
        body { background: #f0f4f8; }
        .sidebar { width:230px; min-height:100vh; position:fixed; top:0; left:0; background:linear-gradient(180deg,#198754,#0d6e44); z-index:100; box-shadow:2px 0 10px rgba(0,0,0,0.1); }
        .sidebar-brand { padding:20px 16px 16px; border-bottom:1px solid rgba(255,255,255,0.15); }
        .sidebar-brand h5 { color:#fff; font-weight:700; margin:0; }
        .sidebar-nav a { display:flex; align-items:center; gap:10px; color:rgba(255,255,255,0.8); padding:11px 20px; text-decoration:none; font-size:.92rem; transition:all .2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background:rgba(255,255,255,0.15); color:#fff; }
        .sidebar-nav a i { font-size:1.1rem; width:20px; text-align:center; }
        .main-content { margin-left:230px; padding:24px; }
        .card-custom { border:none; border-radius:1rem; box-shadow:0 4px 15px rgba(0,0,0,0.07); }
        .table-hover tbody tr:hover { background:#f0f9f4; }
        @media print {
            .sidebar, .no-print { display: none !important; }
            .main-content { margin: 0; padding: 10px; }
        }
        @media(max-width:768px) { .sidebar{position:relative;width:100%;min-height:auto;} .main-content{margin-left:0;} }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-brand"><h5><i class="bi bi-shield-check me-2"></i>WFH:LLW</h5><small class="text-white-50">ผู้ดูแลระบบ</small></div>
    <nav class="sidebar-nav mt-2">
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> ภาพรวม</a>
        <a href="manage_users.php"><i class="bi bi-people-fill"></i> จัดการบุคลากร</a>
        <a href="reports.php" class="active"><i class="bi bi-file-earmark-bar-graph"></i> รายงาน</a>
        <a href="settings.php"><i class="bi bi-gear-fill"></i> ตั้งค่าระบบ</a>
        <a href="../logout.php" style="border-top:1px solid rgba(255,255,255,0.1)"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </nav>
</div>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-bar-graph text-success me-2"></i>รายงานการลงเวลา</h4>
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์</button>
    </div>

    <!-- Filter -->
    <div class="card card-custom p-3 mb-3 no-print">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">เดือน</label>
                <input type="month" class="form-control" name="month" value="<?= htmlspecialchars($filter_month, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">ฝ่าย/กลุ่มงาน</label>
                <select class="form-select" name="dept">
                    <option value="">-- ทั้งหมด --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['dept_id'] ?>" <?= $filter_dept == $d['dept_id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['dept_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-success w-100"><i class="bi bi-search me-1"></i>ค้นหา</button>
            </div>
        </form>
    </div>

    <!-- Title for print -->
    <div class="text-center mb-3" style="display:none;" id="print-title">
        <h5>รายงานการลงเวลาปฏิบัติงาน WFH - โรงเรียนละลมวิทยา</h5>
        <p class="text-muted small">เดือน <?= htmlspecialchars($filter_month, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="card card-custom p-3">
        <div class="fw-semibold mb-3">พบทั้งหมด <?= count($logs) ?> รายการ</div>
        <?php if (empty($logs)): ?>
            <div class="text-center text-muted py-5"><i class="bi bi-search fs-2"></i><p class="mt-2">ไม่พบข้อมูล</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle">
                <thead class="table-success">
                    <tr>
                        <th>#</th><th>วันที่</th><th>ชื่อ-สกุล</th><th>ฝ่าย</th>
                        <th>เข้างาน</th><th>ออกงาน</th><th>สถานะ</th><th class="no-print">GPS</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $i => $r): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= date('d/m/Y', strtotime($r['log_date'])) ?></td>
                        <td><?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?><br><small class="text-muted"><?= htmlspecialchars($r['position'] ?? '') ?></small></td>
                        <td><small><?= htmlspecialchars($r['dept_name'] ?? '') ?></small></td>
                        <td class="text-success fw-semibold"><?= $r['check_in_time'] ?? '-' ?></td>
                        <td class="text-danger fw-semibold"><?= $r['check_out_time'] ?? '-' ?></td>
                        <td>
                            <?php if ($r['check_in_status'] === 'มาสาย'): ?>
                                <span class="badge bg-warning text-dark">มาสาย</span>
                            <?php elseif ($r['check_in_time']): ?>
                                <span class="badge bg-success">ปกติ</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">ไม่มีข้อมูล</span>
                            <?php endif; ?>
                        </td>
                        <td class="no-print">
                            <?php if ($r['check_in_lat']): ?>
                                <a href="https://maps.google.com/?q=<?= htmlspecialchars($r['check_in_lat'], ENT_QUOTES, 'UTF-8') ?>,<?= htmlspecialchars($r['check_in_lng'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </a>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.addEventListener('beforeprint', () => { document.getElementById('print-title').style.display = 'block'; });
window.addEventListener('afterprint',  () => { document.getElementById('print-title').style.display = 'none'; });
</script>
</body>
</html>
