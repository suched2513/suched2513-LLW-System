<?php
/**
 * login.php — Enhanced Premium Login for LLW Platform
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
    <title>เข้าสู่ระบบ | LLW Modern System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .glass { 
            background: rgba(255, 255, 255, 0.7); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .bg-mesh {
            background-color: #eef2ff;
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            background-image: radial-gradient(circle at 10% 20%, rgb(226, 240, 254) 0%, rgb(255, 255, 255) 90.2%);
        }
        .animated-blob {
            position: absolute;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            filter: blur(80px);
            opacity: 0.15;
            z-index: 0;
            border-radius: 50%;
            animation: move 25s infinite alternate;
        }
        @keyframes move {
            from { transform: translate(0, 0); }
            to { transform: translate(100px, 100px); }
        }
    </style>
</head>
<body class="h-full bg-mesh flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Animated Background Blobs -->
    <div class="animated-blob w-[500px] h-[500px] -top-20 -left-20"></div>
    <div class="animated-blob w-[400px] h-[400px] -bottom-20 -right-20" style="animation-delay: -5s;"></div>

    <div class="w-full max-w-[450px] z-10">
        <div class="text-center mb-12">
            <div class="inline-block p-4 rounded-[40px] bg-white shadow-2xl mb-6">
                <div class="w-16 h-16 bg-blue-600 rounded-[28px] flex items-center justify-center text-white text-3xl shadow-lg">
                    <i class="bi bi-shield-check"></i>
                </div>
            </div>
            <h1 class="text-3xl font-black text-blue-900 tracking-tight leading-tight">ระบบบริหารจัดการการเรียน<br>การสอนออนไลน์</h1>
            <p class="text-slate-500 font-medium mt-3">กรอกข้อมูลเพื่อเข้าสู่ระบบ</p>
        </div>

        <div class="glass p-10 rounded-[48px] shadow-[0_20px_50px_rgba(0,0,0,0.05)] relative overflow-hidden">
            <!-- Role Tabs -->
            <div class="flex items-center justify-between gap-1 p-1.5 bg-slate-100/80 rounded-2xl mb-8">
                <button type="button" class="flex-1 py-2.5 px-4 text-xs font-bold rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-200 transition-all">นักเรียน</button>
                <button type="button" class="flex-1 py-2.5 px-4 text-xs font-bold rounded-xl text-slate-500 hover:bg-white/50 transition-all">ครู</button>
                <button type="button" class="flex-1 py-2.5 px-4 text-xs font-bold rounded-xl text-slate-500 hover:bg-white/50 transition-all">แอดมิน</button>
                <button type="button" class="flex-1 py-2.5 px-4 text-xs font-bold rounded-xl text-slate-500 hover:bg-white/50 transition-all">ซุปเปอร์</button>
            </div>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2.5 ml-1">ชื่อผู้ใช้</label>
                    <div class="relative group">
                        <i class="bi bi-person absolute left-5 group-focus-within:text-blue-500 transition-colors top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="text" name="username" required placeholder="User ID" 
                               class="w-full bg-white border-0 rounded-2xl pl-14 pr-5 py-4 text-sm font-medium shadow-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all placeholder:text-slate-300">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2.5 ml-1">
                        <label class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">รหัสผ่าน</label>
                        <a href="#" class="text-[10px] font-bold text-blue-500 hover:underline">ลืมรหัสผ่าน?</a>
                    </div>
                    <div class="relative group">
                        <i class="bi bi-lock absolute left-5 group-focus-within:text-blue-500 transition-colors top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="password" name="password" required placeholder="••••••••" 
                               class="w-full bg-white border-0 rounded-2xl pl-14 pr-14 py-4 text-sm font-medium shadow-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all placeholder:text-slate-300">
                        <button type="button" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-500">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black text-md shadow-2xl shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] transition-all flex items-center justify-center gap-3">
                        เข้าสู่ระบบ <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </form>

            <div class="mt-10 flex flex-col items-center gap-4">
                <div class="w-full h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent"></div>
                <p class="text-xs font-bold text-slate-400">ยังไม่มีบัญชี? <a href="#" class="text-blue-600 font-black">ลงทะเบียนโรงเรียน</a></p>
            </div>
        </div>
        
        <div class="mt-12 flex items-center justify-center gap-6">
            <a href="#" class="text-[10px] font-black text-slate-400 hover:text-blue-600 transition tracking-widest uppercase">นโยบายความเป็นส่วนตัว</a>
            <a href="#" class="text-[10px] font-black text-slate-400 hover:text-blue-600 transition tracking-widest uppercase">เงื่อนไขการใช้งาน</a>
            <a href="index.php" class="text-[10px] font-black text-slate-400 hover:text-blue-600 transition tracking-widest uppercase">หน้าหลัก</a>
        </div>
    </div>

    <?php if ($error): ?>
    <script>
        Swal.fire({ 
            icon: 'error', 
            title: 'เข้าสู่ระบบไม่สำเร็จ', 
            text: '<?= $error ?>', 
            confirmButtonColor: '#2563eb',
            background: '#ffffff',
            customClass: {
                popup: 'rounded-[32px]',
                confirmButton: 'rounded-xl px-10 py-3 font-bold'
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
