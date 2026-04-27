<?php
/**
 * login.php — Project Request System Login
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';

if (isset($_SESSION['user_id'])) {
    _redirect_by_role($_SESSION['role']);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $pdo = getPdo();
            $stmt = $pdo->prepare("SELECT * FROM llw_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = trim($user['firstname'] . ' ' . $user['lastname']);
                $_SESSION['role']      = $user['role'];
                $_SESSION['department'] = $user['department'] ?? '';

                _redirect_by_role($user['role']);
            } else {
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
        }
    }
}

function _redirect_by_role(?string $role): void {
    // Map central system roles to this app's dashboards
    $map = [
        'admin'          => '/admin/dashboard.php',
        'super_admin'    => '/admin/dashboard.php',
        'wfh_admin'      => '/admin/dashboard.php',
        'teacher'        => '/teacher/dashboard.php',
        'att_teacher'    => '/teacher/dashboard.php',
        'wfh_staff'      => '/teacher/dashboard.php',
        'director'       => '/dashboard/director.php',
        'budget_officer' => '/dashboard/budget_officer.php',
    ];
    
    $target = $map[$role] ?? null;
    
    // If role is unknown, default based on role hierarchy or show error
    if (!$target) {
        // Clear session to prevent loop if role is truly invalid for this app
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?error=invalid_role');
        exit();
    }
    
    header('Location: ' . BASE_URL . $target);
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | <?= htmlspecialchars(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); }
        .inp {
            width: 100%; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 1rem;
            padding: 0.875rem 1rem 0.875rem 3rem; font-size: 0.875rem; font-weight: 600;
            outline: none; transition: all 0.2s; color: #1e293b;
        }
        .inp:focus { border-color: #2563eb; box-shadow: 0 0 0 3px #2563eb22; background: #fff; }
    </style>
</head>
<body class="min-h-screen bg-[#f1f5f9] flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Decorative background elements -->
    <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-100 rounded-full blur-[120px] opacity-60"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-indigo-100 rounded-full blur-[120px] opacity-60"></div>

    <div class="w-full max-w-[420px] glass rounded-[2.5rem] shadow-2xl p-8 sm:p-10 relative z-10">
        <div class="text-center mb-10">
            <div class="inline-flex w-20 h-20 bg-blue-600 rounded-3xl items-center justify-center text-white text-4xl mb-6 shadow-xl shadow-blue-200">
                <i class="bi bi-wallet2"></i>
            </div>
            <h1 class="text-2xl font-black text-slate-800 leading-tight mb-2"><?= htmlspecialchars(APP_NAME) ?></h1>
            <p class="text-slate-400 text-sm font-medium">โรงเรียนละลมวิทยา</p>
        </div>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">ชื่อผู้ใช้งาน</label>
                <div class="relative">
                    <i class="bi bi-person absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-lg"></i>
                    <input type="text" name="username" class="inp" placeholder="Username" required autocomplete="username">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">รหัสผ่าน</label>
                <div class="relative">
                    <i class="bi bi-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-lg"></i>
                    <input type="password" name="password" class="inp" placeholder="Password" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black text-sm shadow-xl shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-3">
                <i class="bi bi-box-arrow-in-right text-lg"></i>
                เข้าสู่ระบบ
            </button>
        </form>

        <div class="mt-8 pt-8 border-t border-slate-100 text-center">
            <p class="text-[10px] text-slate-300 font-black uppercase tracking-[0.2em]">© 2026 Lalom Wittaya School</p>
        </div>
    </div>

    <?php if ($error): ?>
    <script>
    Swal.fire({
        icon: 'error',
        title: 'ล้มเหลว',
        text: '<?= htmlspecialchars($error) ?>',
        confirmButtonColor: '#2563eb',
        customClass: { popup: 'rounded-[2rem]', confirmButton: 'rounded-xl px-8' }
    });
    </script>
    <?php endif; ?>
</body>
</html>
