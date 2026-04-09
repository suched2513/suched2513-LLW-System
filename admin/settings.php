<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header("Location: ../login.php"); exit();
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['regular_time_in'])) {
        // ตั้งค่าเวลา
        $time_in   = $_POST['regular_time_in'];
        $time_late = $_POST['late_time'];
        $conn->query("UPDATE wfh_system_settings SET regular_time_in='$time_in', late_time='$time_late' WHERE setting_id=1");
        $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i>บันทึกการตั้งค่าเรียบร้อย</div>';
    } elseif (isset($_POST['boss_pin'])) {
        // ตั้ง PIN ผอ.
        $pin = trim($_POST['boss_pin']);
        if (strlen($pin) >= 4 && strlen($pin) <= 6 && ctype_digit($pin)) {
            $hashed = password_hash($pin, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE wfh_system_settings SET boss_pin = ? WHERE setting_id = 1");
            $stmt->bind_param('s', $hashed);
            $stmt->execute();
            $msg = '<div class="alert alert-success"><i class="bi bi-shield-check-fill me-1"></i>ตั้ง PIN เรียบร้อยแล้ว</div>';
        } else {
            $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>PIN ต้องเป็นตัวเลข 4-6 หลักเท่านั้น</div>';
        }
    }
}

$settings = $conn->query("SELECT * FROM wfh_system_settings LIMIT 1")->fetch_assoc();
$hasPIN = !empty($settings['boss_pin']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - WFH:LLW</title>
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
        @media(max-width:768px) { .sidebar{position:relative;width:100%;min-height:auto;} .main-content{margin-left:0;} }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-brand"><h5><i class="bi bi-shield-check me-2"></i>WFH:LLW</h5><small class="text-white-50">ผู้ดูแลระบบ</small></div>
    <nav class="sidebar-nav mt-2">
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> ภาพรวม</a>
        <a href="manage_users.php"><i class="bi bi-people-fill"></i> จัดการบุคลากร</a>
        <a href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i> รายงาน</a>
        <a href="settings.php" class="active"><i class="bi bi-gear-fill"></i> ตั้งค่าระบบ</a>
        <a href="../logout.php" style="border-top:1px solid rgba(255,255,255,0.1)"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </nav>
</div>
<div class="main-content">
    <h4 class="fw-bold mb-4"><i class="bi bi-gear-fill text-success me-2"></i>ตั้งค่าระบบ</h4>
    <?= $msg ?>

    <div class="card card-custom p-4" style="max-width:500px;">
        <div class="fw-semibold mb-3"><i class="bi bi-clock-fill text-success me-1"></i> กำหนดเวลาทำงาน</div>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold">เวลาเริ่มงานปกติ</label>
                <input type="time" class="form-control" name="regular_time_in" value="<?= $settings['regular_time_in'] ?>" required>
                <div class="form-text">เวลามาตรฐานที่บุคลากรควรเข้างาน</div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">เวลาที่เริ่มนับว่า "มาสาย"</label>
                <input type="time" class="form-control" name="late_time" value="<?= $settings['late_time'] ?>" required>
                <div class="form-text">หากลงเวลาหลังจากเวลานี้ จะถูกบันทึกว่า "มาสาย"</div>
            </div>
            <button type="submit" class="btn btn-success px-4"><i class="bi bi-save-fill me-1"></i>บันทึกการตั้งค่า</button>
        </form>
    </div>

    <!-- PIN ผอ. -->
    <div class="card card-custom p-4 mt-3" style="max-width:500px;">
        <div class="fw-semibold mb-3"><i class="bi bi-shield-lock-fill text-danger me-1"></i> PIN สำหรับผู้อำนวยการอนุมัติคำขอออกนอกบริเวณ</div>
        <?php if ($hasPIN): ?>
        <div class="alert alert-success py-2 small"><i class="bi bi-shield-check-fill me-1"></i>ตั้ง PIN แล้ว — ผอ.ต้องกรอก PIN ทุกครั้งที่อนุมัติ</div>
        <?php else: ?>
        <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i>ยังไม่ได้ตั้ง PIN — ระบบจะอนุมัติเลยโดยไม่ต้องใช้ PIN</div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold"><?= $hasPIN ? 'เปลี่ยน PIN ใหม่' : 'ตั้ง PIN ใหม่' ?></label>
                <input type="password" class="form-control" name="boss_pin" maxlength="6"
                    placeholder="กรอกตัวเลข 4-6 หลัก" required autocomplete="new-password">
                <div class="form-text">ใช้ตัวเลขเท่านั้น 4-6 หลัก — เก็บแบบ Hashed เดิมจะผู้ใดดูไม่ออก</div>
            </div>
            <button type="submit" class="btn btn-danger px-4"><i class="bi bi-shield-lock-fill me-1"></i><?= $hasPIN ? 'เปลี่ยน PIN' : 'บันทึก PIN' ?></button>
        </form>
    </div>

    <!-- ข้อมูลระบบ -->
    <div class="card card-custom p-4 mt-3" style="max-width:500px;">
        <div class="fw-semibold mb-3"><i class="bi bi-info-circle-fill text-primary me-1"></i> ข้อมูลระบบ</div>
        <table class="table table-sm">
            <tr><td class="text-muted">ชื่อระบบ</td><td>WFH:LLW ระบบลงเวลาปฏิบัติงาน</td></tr>
            <tr><td class="text-muted">โรงเรียน</td><td>โรงเรียนละลมวิทยา</td></tr>
            <tr><td class="text-muted">เวอร์ชั่น</td><td>1.0.0</td></tr>
            <tr><td class="text-muted">วันที่ระบบ</td><td><?= date('d/m/') . (date('Y')+543) ?></td></tr>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
