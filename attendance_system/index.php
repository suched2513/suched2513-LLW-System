<?php
require_once 'functions.php';

if (isset($_SESSION['teacher_id'])) {
    header("Location: dashboard.php"); exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if (loginTeacher($_POST['username'], $_POST['password'], $pdo)) {
        header("Location: dashboard.php"); exit();
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | LLW Attendance</title>
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
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-400/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-indigo-400/20 rounded-full blur-[120px]"></div>
    </div>

    <div class="w-full max-w-md z-10">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-600 rounded-3xl shadow-2xl shadow-blue-200 mb-6 text-white text-4xl">
                <i class="bi bi-person-check-fill"></i>
            </div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">LLW Attendance</h1>
            <p class="text-slate-400 font-medium mt-2">ระบบเช็คชื่อนักเรียน โรงเรียนละลมวิทยา</p>
        </div>

        <div class="glass p-10 rounded-[32px] shadow-2xl border border-white/50">
            <h2 class="text-xl font-bold text-slate-700 mb-8 flex items-center gap-2">
                <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
                เข้าสู่ระบบสำหรับครู
            </h2>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">ชื่อผู้ใช้งาน</label>
                    <div class="relative">
                        <i class="bi bi-person absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="username" required placeholder="User ID" 
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-12 pr-4 py-3.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">รหัสผ่าน</label>
                    <div class="relative">
                        <i class="bi bi-shield-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="password" name="password" required placeholder="Password" 
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-12 pr-4 py-3.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="login" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black text-lg shadow-xl shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] transition-all active:scale-95">
                        เข้าสู่ระบบ
                    </button>
                </div>
            </form>

            <div class="mt-8 pt-8 border-t border-slate-100 text-center">
                <a href="../central_dashboard.php" class="text-xs font-bold text-slate-400 hover:text-blue-600 transition tracking-wider uppercase">
                    <i class="bi bi-house-door mr-1"></i> กลับไปหน้าหลักของโรงเรียน
                </a>
            </div>
        </div>
        
        <p class="text-center mt-10 text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em]">
            © 2026 Lalom Wittaya School. All Rights Reserved.
        </p>
    </div>

    <?php if ($error): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'เข้าสู่ระบบไม่สำเร็จ',
            text: '<?= $error ?>',
            confirmButtonColor: '#2563eb',
            borderRadius: '24px'
        });
    </script>
    <?php endif; ?>
</body>
</html>
