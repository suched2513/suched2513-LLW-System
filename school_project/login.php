<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/constants.php';

if (isset($_SESSION['user_id'])) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $db = getDB();
        $s = $db->prepare("SELECT * FROM llw_users WHERE username=? AND status='active'");
        $s->execute([$username]);
        $user = $s->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['firstname'] . ' ' . $user['lastname'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['dept_id']   = $user['department_id'];
            $_SESSION['owner_name']= $user['owner_name'];
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            auditLog('login');
            $redirects = ['admin'=>BASE_URL.'/admin/dashboard.php','director'=>BASE_URL.'/dashboard/director.php','budget_officer'=>BASE_URL.'/dashboard/budget_officer.php','teacher'=>BASE_URL.'/teacher/my_projects.php','head'=>BASE_URL.'/teacher/my_projects.php'];
            header('Location: ' . ($redirects[$user['role']] ?? BASE_URL . '/index.php'));
            exit;
        } else { $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'; }
    } else { $error = 'กรุณากรอกข้อมูลให้ครบ'; }
}
?><!DOCTYPE html>
<html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>เข้าสู่ระบบ | <?= SCHOOL_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<style>
body{background:linear-gradient(135deg,#1e293b 0%,#1a56db 100%);min-height:100vh;display:flex;align-items:center;}
.login-card{width:100%;max-width:420px;margin:auto;}
.login-logo{width:80px;height:80px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:36px;}
</style>
</head><body>
<div class="login-card p-3">
  <div class="card shadow-lg border-0" style="border-radius:16px">
    <div class="card-body p-4">
      <div class="text-center mb-4">
        <div class="login-logo"><i class="bi bi-mortarboard-fill text-primary"></i></div>
        <h5 class="fw-bold mb-1"><?= SCHOOL_NAME ?></h5>
        <p class="text-muted small"><?= SCHOOL_DISTRICT ?> <?= SCHOOL_PROVINCE ?></p>
        <h6 class="text-primary fw-semibold">ระบบขอดำเนินโครงการ</h6>
      </div>
      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-circle"></i> <?= h($error) ?>
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-warning">Session หมดอายุ กรุณาเข้าสู่ระบบใหม่</div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="mb-3">
          <label class="form-label fw-semibold">ชื่อผู้ใช้</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="username" class="form-control" placeholder="username" value="<?= h($_POST['username']??'') ?>" required autofocus>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">รหัสผ่าน</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" class="form-control" placeholder="password" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
        </button>
      </form>
      <hr class="my-3">
      <div class="bg-light rounded p-3" style="font-size:12px">
        <strong>บัญชีทดสอบ:</strong><br>
        admin / password123 | director / password123<br>
        teacher1 / password123 | budget1 / password123
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
