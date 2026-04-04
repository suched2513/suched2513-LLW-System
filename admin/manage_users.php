<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit();
}

$msg = '';

// ===== เพิ่มผู้ใช้ =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $un   = $conn->real_escape_string($_POST['username']);
        $pw   = md5($_POST['password']);
        $fn   = $conn->real_escape_string($_POST['firstname']);
        $ln   = $conn->real_escape_string($_POST['lastname']);
        $pos  = $conn->real_escape_string($_POST['position']);
        $dept = (int)$_POST['dept_id'];
        $role = $_POST['role'];
        $conn->query("INSERT INTO wfh_users (username,password,firstname,lastname,position,dept_id,role) VALUES ('$un','$pw','$fn','$ln','$pos',$dept,'$role')");
        $msg = '<div class="alert alert-success">เพิ่มผู้ใช้สำเร็จ</div>';

    } elseif ($_POST['action'] === 'delete') {
        $uid = (int)$_POST['user_id'];
        $conn->query("DELETE FROM wfh_users WHERE user_id=$uid AND role!='admin'");
        $msg = '<div class="alert alert-warning">ลบผู้ใช้เรียบร้อยแล้ว</div>';

    } elseif ($_POST['action'] === 'reset_pw') {
        $uid = (int)$_POST['user_id'];
        $pw  = md5('123456');
        $conn->query("UPDATE wfh_users SET password='$pw' WHERE user_id=$uid");
        $msg = '<div class="alert alert-info">รีเซ็ตรหัสผ่านเป็น 123456 แล้ว</div>';
    }
}

$users = $conn->query("
    SELECT u.*, d.dept_name
    FROM wfh_users u
    LEFT JOIN wfh_departments d ON u.dept_id = d.dept_id
    ORDER BY u.role DESC, u.user_id ASC
")->fetch_all(MYSQLI_ASSOC);

$departments = $conn->query("SELECT * FROM wfh_departments")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบุคลากร - WFH:LLW</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { font-family: 'Sarabun', sans-serif; }
        body { background: #f0f4f8; }
        .sidebar { width:230px; min-height:100vh; position:fixed; top:0; left:0; background:linear-gradient(180deg,#198754,#0d6e44); padding:0; z-index:100; box-shadow:2px 0 10px rgba(0,0,0,0.1); }
        .sidebar-brand { padding:20px 16px 16px; border-bottom:1px solid rgba(255,255,255,0.15); }
        .sidebar-brand h5 { color:#fff; font-weight:700; margin:0; }
        .sidebar-nav a { display:flex; align-items:center; gap:10px; color:rgba(255,255,255,0.8); padding:11px 20px; text-decoration:none; font-size:.92rem; transition:all .2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background:rgba(255,255,255,0.15); color:#fff; }
        .sidebar-nav a i { font-size:1.1rem; width:20px; text-align:center; }
        .main-content { margin-left:230px; padding:24px; }
        .card-custom { border:none; border-radius:1rem; box-shadow:0 4px 15px rgba(0,0,0,0.07); }
        .table-hover tbody tr:hover { background:#f0f9f4; }
        @media(max-width:768px) { .sidebar{position:relative;width:100%;min-height:auto;} .main-content{margin-left:0;} }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-brand"><h5><i class="bi bi-shield-check me-2"></i>WFH:LLW</h5><small class="text-white-50">ผู้ดูแลระบบ</small></div>
    <nav class="sidebar-nav mt-2">
        <a href="dashboard.php"><i class="bi bi-speedometer2"></i> ภาพรวม</a>
        <a href="manage_users.php" class="active"><i class="bi bi-people-fill"></i> จัดการบุคลากร</a>
        <a href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i> รายงาน</a>
        <a href="settings.php"><i class="bi bi-gear-fill"></i> ตั้งค่าระบบ</a>
        <a href="../logout.php" style="border-top:1px solid rgba(255,255,255,0.1)"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </nav>
</div>
<div class="main-content">
    <h4 class="fw-bold mb-4"><i class="bi bi-people-fill text-success me-2"></i>จัดการบุคลากร</h4>
    <?= $msg ?>

    <!-- ฟอร์มเพิ่มผู้ใช้ -->
    <div class="card card-custom p-3 mb-4">
        <div class="fw-semibold mb-3"><i class="bi bi-person-plus-fill text-success me-1"></i> เพิ่มบุคลากรใหม่</div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="row g-2">
                <div class="col-md-3"><input class="form-control" name="username" placeholder="Username / รหัส" required></div>
                <div class="col-md-2"><input class="form-control" name="password" type="password" placeholder="รหัสผ่าน" required></div>
                <div class="col-md-2"><input class="form-control" name="firstname" placeholder="ชื่อ" required></div>
                <div class="col-md-2"><input class="form-control" name="lastname" placeholder="นามสกุล" required></div>
                <div class="col-md-2"><input class="form-control" name="position" placeholder="ตำแหน่ง"></div>
                <div class="col-md-2">
                    <select class="form-select" name="dept_id">
                        <option value="">-- ฝ่าย --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="role">
                        <option value="user">ครู/บุคลากร</option>
                        <option value="admin">ผู้ดูแลระบบ</option>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-success w-100"><i class="bi bi-plus-circle me-1"></i>เพิ่ม</button></div>
            </div>
        </form>
    </div>

    <!-- ตารางผู้ใช้ -->
    <div class="card card-custom p-3">
        <div class="fw-semibold mb-3"><i class="bi bi-table text-success me-1"></i> รายชื่อบุคลากรทั้งหมด (<?= count($users) ?> คน)</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>#</th><th>Username</th><th>ชื่อ-สกุล</th><th>ตำแหน่ง</th><th>ฝ่าย</th><th>บทบาท</th><th>จัดการ</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                        <td><?= htmlspecialchars($u['firstname'].' '.$u['lastname']) ?></td>
                        <td><small><?= htmlspecialchars($u['position'] ?? '') ?></small></td>
                        <td><small><?= htmlspecialchars($u['dept_name'] ?? '') ?></small></td>
                        <td>
                            <?= $u['role'] === 'admin'
                                ? '<span class="badge bg-success">Admin</span>'
                                : '<span class="badge bg-secondary">ครู/บุคลากร</span>' ?>
                        </td>
                        <td>
                            <form method="POST" class="d-inline" onsubmit="return confirm('รีเซ็ตรหัสผ่านเป็น 123456?')">
                                <input type="hidden" name="action" value="reset_pw">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <button class="btn btn-sm btn-outline-warning"><i class="bi bi-key-fill"></i></button>
                            </form>
                            <?php if ($u['role'] !== 'admin'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
