<?php
/**
 * login.php — Unified Login for LLW Platform
 */
session_start();
require_once 'config.php';

if (isset($_SESSION['llw_role'])) {
    _redirect_by_role($_SESSION['llw_role']);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT * FROM llw_users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['fullname']  = trim($user['firstname'] . ' ' . $user['lastname']);
            $_SESSION['llw_role']  = $user['role'];
            $_SESSION['role']      = in_array($user['role'], ['super_admin','wfh_admin']) ? 'admin' : 'user';

            if (in_array($user['role'], ['att_teacher','super_admin'])) {
                $t = $conn->prepare("SELECT id, name FROM att_teachers WHERE username = ? LIMIT 1");
                $t->bind_param('s', $username); $t->execute();
                $teacher = $t->get_result()->fetch_assoc(); $t->close();
                if ($teacher) { $_SESSION['teacher_id'] = $teacher['id']; $_SESSION['teacher_name'] = $teacher['name']; }
            }

            $u = $conn->prepare("UPDATE llw_users SET last_login = NOW() WHERE user_id = ?");
            $u->bind_param('i', $user['user_id']); $u->execute(); $u->close();

            _redirect_by_role($user['role']);
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}

function _redirect_by_role(string $role): void {
    $map = [
        'super_admin' => 'central_dashboard.php',
        'wfh_admin'   => 'admin/dashboard.php',
        'wfh_staff'   => 'user/dashboard.php',
        'cb_admin'    => 'chromebook/index.php',
        'att_teacher' => 'attendance_system/dashboard.php',
    ];
    header('Location: ' . ($map[$role] ?? 'index.php')); exit();
}
?>
<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | LLW Premium</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="h-full bg-slate-100 flex items-center justify-center p-4">
    <!-- Blobs -->
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-400/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-emerald-400/20 rounded-full blur-[120px]"></div>
    </div>

    <div class="w-full max-w-md z-10">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-600 rounded-3xl shadow-2xl shadow-blue-200 mb-6 text-white text-4xl">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">LLW Premium</h1>
            <p class="text-slate-400 font-medium mt-2">โรงเรียนละลมวิทยา — ระบบบริหารจัดการสถานศึกษา</p>
        </div>

        <div class="glass p-10 rounded-[32px] shadow-2xl border border-white/50">
            <h2 class="text-xl font-bold text-slate-700 mb-8 flex items-center gap-2">
                <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
                เข้าสู่ระบบรวมศูนย์
            </h2>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">ชื่อผู้ใช้งาน (User ID)</label>
                    <div class="relative">
                        <i class="bi bi-person absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="username" required placeholder="User ID" 
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-12 pr-4 py-3.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">รหัสผ่าน (Password)</label>
                    <div class="relative">
                        <i class="bi bi-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="password" name="password" required placeholder="••••••••" 
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-12 pr-4 py-3.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black text-lg shadow-xl shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] transition-all">
                        ยืนยันการเข้าใช้
                    </button>
                </div>
            </form>

            <div class="mt-8 pt-8 border-t border-slate-100 flex flex-wrap justify-center gap-2">
                <span class="px-2 py-0.5 rounded-lg bg-blue-50 text-blue-600 text-[9px] font-black uppercase tracking-wider">Super Admin</span>
                <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-600 text-[9px] font-black uppercase tracking-wider">WFH Admin</span>
                <span class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-600 text-[9px] font-black uppercase tracking-wider">CB Manager</span>
                <span class="px-2 py-0.5 rounded-lg bg-rose-50 text-rose-600 text-[9px] font-black uppercase tracking-wider">Academic Teacher</span>
            </div>
        </div>
        
        <div class="mt-10 flex items-center justify-center gap-4">
            <a href="index.php" class="text-xs font-bold text-slate-500 hover:text-blue-600 transition tracking-widest uppercase">หน้าแรก</a>
            <span class="text-slate-300">|</span>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em]">© 2026 Lalom Wittaya School.</p>
        </div>
    </div>

    <?php if ($error): ?>
    <script>
        Swal.fire({ icon: 'error', title: 'เข้าสู่ระบบไม่สำเร็จ', text: '<?= $error ?>', confirmButtonColor: '#2563eb' });
    </script>
    <?php endif; ?>
</body>
</html>
