<?php
/**
 * login.php — LLW Platinum Login (Context-aware, Premium Design)
 */
session_start();
require_once 'config.php';

// เก็บ ?redirect= ลง session เพื่อใช้หลัง login สำเร็จ
if (!empty($_GET['redirect'])) {
    $rd = $_GET['redirect'];
    if (str_starts_with($rd, '/') && !str_starts_with($rd, '//')) {
        $_SESSION['login_redirect'] = $rd;
    }
}

if (isset($_SESSION['llw_role'])) {
    _redirect_by_role($_SESSION['llw_role']);
}

// ── Detect context from redirect ──────────────────────────────────
$redirect = $_SESSION['login_redirect'] ?? $_GET['redirect'] ?? '';
$ctx = 'default';
if (str_contains($redirect, 'chromebook'))       $ctx = 'chromebook';
elseif (str_contains($redirect, 'attendance'))   $ctx = 'attendance';
elseif (str_contains($redirect, 'admin') || str_contains($redirect, 'user/')) $ctx = 'wfh';
elseif (str_contains($redirect, 'leave'))        $ctx = 'leave';

$ctxMap = [
    'chromebook' => [
        'title'    => 'ระบบจัดการ Chromebook',
        'subtitle' => 'ยืม-คืนและตรวจสอบสภาพอุปกรณ์',
        'icon'     => 'bi-laptop',
        'grad'     => 'from-cyan-500 to-blue-600',
        'shadow'   => 'shadow-cyan-300/40',
        'ring'     => 'focus:ring-cyan-400',
        'btn'      => 'from-cyan-500 to-blue-600',
        'btnShadow'=> 'shadow-cyan-200',
        'accent'   => '#06b6d4',
        'badge'    => 'CHROMEBOOK SYSTEM',
    ],
    'attendance' => [
        'title'    => 'ระบบเช็คชื่อนักเรียน',
        'subtitle' => 'บันทึกเวลาเรียนรายวิชาแบบ Real-time',
        'icon'     => 'bi-person-check-fill',
        'grad'     => 'from-indigo-500 to-blue-600',
        'shadow'   => 'shadow-indigo-300/40',
        'ring'     => 'focus:ring-indigo-400',
        'btn'      => 'from-indigo-500 to-blue-600',
        'btnShadow'=> 'shadow-indigo-200',
        'accent'   => '#6366f1',
        'badge'    => 'ATTENDANCE SYSTEM',
    ],
    'wfh' => [
        'title'    => 'ระบบลงเวลาบุคลากร',
        'subtitle' => 'เข้า-ออกงานด้วย GPS ยืนยันตัวตน',
        'icon'     => 'bi-geo-alt-fill',
        'grad'     => 'from-emerald-500 to-teal-600',
        'shadow'   => 'shadow-emerald-300/40',
        'ring'     => 'focus:ring-emerald-400',
        'btn'      => 'from-emerald-500 to-teal-600',
        'btnShadow'=> 'shadow-emerald-200',
        'accent'   => '#10b981',
        'badge'    => 'WFH SYSTEM',
    ],
    'leave' => [
        'title'    => 'ระบบขออนุญาตออกนอก',
        'subtitle' => 'ยื่นคำขอลาออนไลน์ผ่าน Telegram',
        'icon'     => 'bi-door-open-fill',
        'grad'     => 'from-rose-500 to-pink-600',
        'shadow'   => 'shadow-rose-300/40',
        'ring'     => 'focus:ring-rose-400',
        'btn'      => 'from-rose-500 to-pink-600',
        'btnShadow'=> 'shadow-rose-200',
        'accent'   => '#f43f5e',
        'badge'    => 'LEAVE SYSTEM',
    ],
    'default' => [
        'title'    => 'LLW Platinum Portal',
        'subtitle' => 'ศูนย์กลางระบบบริหารจัดการโรงเรียน',
        'icon'     => 'bi-shield-lock-fill',
        'grad'     => 'from-blue-600 to-indigo-700',
        'shadow'   => 'shadow-blue-300/40',
        'ring'     => 'focus:ring-blue-400',
        'btn'      => 'from-blue-600 to-indigo-700',
        'btnShadow'=> 'shadow-blue-200',
        'accent'   => '#3b82f6',
        'badge'    => 'UNIFIED SYSTEM',
    ],
];

$c = $ctxMap[$ctx];

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
                $_SESSION['teacher_id']   = $teacher['id'] ?? 0;
                $_SESSION['teacher_name'] = $teacher['name'] ?? trim($user['firstname'] . ' ' . $user['lastname']);
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
    global $base_path;
    
    if (!empty($_SESSION['login_redirect'])) {
        $rd = $_SESSION['login_redirect'];
        unset($_SESSION['login_redirect']);
        
        // Ensure redirect target includes base_path if it's missing
        if (str_starts_with($rd, '/') && $base_path !== '' && !str_starts_with($rd, $base_path)) {
            $rd = $base_path . $rd;
        }
        
        header('Location: ' . $rd); exit();
    }
    
    $map = [
        'super_admin' => '/central_dashboard.php',
        'wfh_admin'   => '/admin/dashboard.php',
        'wfh_staff'   => '/user/dashboard.php',
        'cb_admin'    => '/chromebook/index.php',
        'att_teacher' => '/attendance_system/dashboard.php',
    ];
    
    $target = $map[$role] ?? '/index.php';
    header('Location: ' . $base_path . $target); exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($c['title']) ?> | LLW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .inp {
            width: 100%;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 1rem;
            padding: 0.875rem 1rem 0.875rem 3rem;
            font-size: 0.875rem;
            font-weight: 600;
            outline: none;
            transition: all 0.2s;
            color: #1e293b;
        }
        .inp:focus {
            border-color: <?= htmlspecialchars($c['accent']) ?>;
            box-shadow: 0 0 0 3px <?= htmlspecialchars($c['accent']) ?>22;
            background: #fff;
        }
        .inp::placeholder { color: #cbd5e1; font-weight: 500; }
        @keyframes float {
            0%,100% { transform: translateY(0) rotate(0deg); }
            33% { transform: translateY(-16px) rotate(3deg); }
            66% { transform: translateY(-8px) rotate(-2deg); }
        }
        @keyframes fadeSlide {
            from { opacity:0; transform:translateX(30px); }
            to   { opacity:1; transform:translateX(0); }
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .anim-float { animation: float 6s ease-in-out infinite; }
        .anim-fade-slide { animation: fadeSlide 0.6s cubic-bezier(.22,1,.36,1) both; }
        .anim-fade-up { animation: fadeUp 0.5s ease-out both; }
        .anim-delay-1 { animation-delay: 0.1s; }
        .anim-delay-2 { animation-delay: 0.2s; }
        .anim-delay-3 { animation-delay: 0.3s; }
        .anim-delay-4 { animation-delay: 0.4s; }
        .btn-login {
            background: linear-gradient(135deg, var(--accent-from), var(--accent-to));
            transition: all 0.3s cubic-bezier(.34,1.56,.64,1);
        }
        .btn-login:hover { transform: translateY(-2px) scale(1.01); }
        .btn-login:active { transform: scale(0.98); }
        :root {
            --accent-from: <?= htmlspecialchars(str_contains($c['btn'],'from-') ? explode(' ', $c['btn'])[0] : '#06b6d4') ?>;
            --accent-to: <?= htmlspecialchars(str_contains($c['btn'],'to-') ? explode(' ', $c['btn'])[1] : '#2563eb') ?>;
        }
        .pattern-dots {
            background-image: radial-gradient(circle, rgba(255,255,255,0.2) 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 flex items-stretch overflow-hidden">

<!-- ══════════════════════════════════════════════════════════
     LEFT PANEL — Module Branding
══════════════════════════════════════════════════════════ -->
<div class="hidden lg:flex lg:w-[45%] bg-gradient-to-br <?= $c['grad'] ?> relative flex-col items-center justify-center p-12 overflow-hidden">

    <!-- Dot pattern overlay -->
    <div class="absolute inset-0 pattern-dots opacity-30"></div>

    <!-- Floating circles decoration -->
    <div class="absolute -top-20 -left-20 w-72 h-72 bg-white/10 rounded-full blur-2xl"></div>
    <div class="absolute -bottom-20 -right-20 w-96 h-96 bg-white/10 rounded-full blur-3xl"></div>
    <div class="absolute top-1/4 right-0 w-48 h-48 bg-white/5 rounded-full blur-xl"></div>

    <!-- Content -->
    <div class="relative z-10 text-center text-white">

        <!-- LLW Logo -->
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur rounded-2xl text-[10px] font-black uppercase tracking-[0.3em] mb-10">
            <div class="w-5 h-5 bg-white rounded-lg flex items-center justify-center text-xs font-black" style="color:<?= htmlspecialchars($c['accent']) ?>">L</div>
            <?= htmlspecialchars($c['badge']) ?>
        </div>

        <!-- Module Icon -->
        <div class="anim-float inline-flex w-28 h-28 bg-white/20 backdrop-blur-sm rounded-[2rem] items-center justify-center text-6xl mb-8 shadow-2xl ring-4 ring-white/20">
            <i class="bi <?= $c['icon'] ?>"></i>
        </div>

        <!-- Title -->
        <h1 class="text-3xl font-black leading-tight mb-3">
            <?= htmlspecialchars($c['title']) ?>
        </h1>
        <p class="text-white/70 font-medium text-sm leading-relaxed max-w-xs mx-auto">
            <?= htmlspecialchars($c['subtitle']) ?>
        </p>

        <!-- Feature list -->
        <div class="mt-10 space-y-3 text-left max-w-xs">
            <?php
            $features = [
                'chromebook' => ['ยืม-คืนอุปกรณ์ดิจิทัล', 'ตรวจสอบสภาพเครื่อง', 'นำเข้าข้อมูล CSV'],
                'attendance'  => ['บันทึกการเข้าเรียน', 'รายงานรายวิชา', 'ส่งออกข้อมูล Excel'],
                'wfh'         => ['ลงเวลาด้วย GPS', 'ยืนยันตัวตนด้วยรูปถ่าย', 'แจ้งเตือน Telegram'],
                'leave'       => ['ยื่นคำขอออนไลน์', 'ระบบอนุมัติหลายชั้น', 'แจ้งเตือนอัตโนมัติ'],
                'default'     => ['ระบบเช็คชื่อนักเรียน', 'จัดการ Chromebook', 'ลงเวลาบุคลากร'],
            ];
            foreach ($features[$ctx] ?? $features['default'] as $f):
            ?>
            <div class="flex items-center gap-3 text-sm font-bold text-white/80">
                <div class="w-5 h-5 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-check-lg text-white text-xs"></i>
                </div>
                <?= htmlspecialchars($f) ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bottom school name -->
        <div class="mt-16 pt-6 border-t border-white/20 text-white/50 text-[10px] font-black uppercase tracking-[0.2em]">
            โรงเรียนละลมวิทยา • 2026
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     RIGHT PANEL — Login Form
══════════════════════════════════════════════════════════ -->
<div class="flex-1 flex items-center justify-center p-6 sm:p-10 lg:p-14 bg-white relative overflow-hidden">

    <!-- Background orb -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br <?= $c['grad'] ?> opacity-5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>

    <div class="w-full max-w-sm anim-fade-slide">

        <!-- Mobile-only: module badge -->
        <div class="lg:hidden flex items-center gap-3 mb-8 anim-fade-up">
            <div class="w-12 h-12 bg-gradient-to-br <?= $c['grad'] ?> rounded-2xl flex items-center justify-center text-white text-xl shadow-lg <?= $c['shadow'] ?>">
                <i class="bi <?= $c['icon'] ?>"></i>
            </div>
            <div>
                <p class="font-black text-slate-800 text-base leading-tight"><?= htmlspecialchars($c['title']) ?></p>
                <p class="text-xs text-slate-400 font-medium"><?= htmlspecialchars($c['subtitle']) ?></p>
            </div>
        </div>

        <!-- Heading -->
        <div class="mb-8 anim-fade-up anim-delay-1">
            <p class="text-[10px] font-black uppercase tracking-[0.25em] mb-2" style="color:<?= htmlspecialchars($c['accent']) ?>"><?= htmlspecialchars($c['badge']) ?></p>
            <h2 class="text-2xl font-black text-slate-800 leading-tight">ยินดีต้อนรับ</h2>
            <p class="text-slate-400 text-sm font-medium mt-1">กรอกข้อมูลเพื่อเข้าสู่ระบบ</p>
        </div>

        <!-- Form -->
        <form method="POST" class="space-y-5 anim-fade-up anim-delay-2">

            <!-- Username -->
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] mb-2">ชื่อผู้ใช้งาน</label>
                <div class="relative">
                    <i class="bi bi-person absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-base"></i>
                    <input type="text" name="username" class="inp" placeholder="กรอก Username" required autocomplete="username">
                </div>
            </div>

            <!-- Password -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">รหัสผ่าน</label>
                    <a href="#" class="text-[10px] font-bold hover:underline" style="color:<?= htmlspecialchars($c['accent']) ?>">ลืมรหัสผ่าน?</a>
                </div>
                <div class="relative">
                    <i class="bi bi-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-base"></i>
                    <input type="password" name="password" id="pwd" class="inp pr-12" placeholder="กรอกรหัสผ่าน" required autocomplete="current-password">
                    <button type="button" id="pwd-toggle" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-500 transition-colors">
                        <i class="bi bi-eye" id="pwd-icon"></i>
                    </button>
                </div>
            </div>

            <!-- Submit -->
            <div class="pt-2 anim-fade-up anim-delay-3">
                <button type="submit" class="btn-login w-full bg-gradient-to-r <?= $c['btn'] ?> text-white py-4 rounded-2xl font-black text-sm shadow-xl <?= $c['btnShadow'] ?> flex items-center justify-center gap-2.5">
                    <i class="bi bi-box-arrow-in-right text-lg"></i>
                    เข้าสู่ระบบ
                </button>
            </div>
        </form>

        <!-- Divider -->
        <div class="flex items-center gap-3 my-6 anim-fade-up anim-delay-4">
            <div class="flex-1 h-px bg-slate-100"></div>
            <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">หรือ</span>
            <div class="flex-1 h-px bg-slate-100"></div>
        </div>

        <!-- Back to home -->
        <div class="text-center anim-fade-up anim-delay-4">
            <a href="index.php" class="inline-flex items-center gap-2 text-xs font-bold text-slate-400 hover:text-slate-600 transition-colors">
                <i class="bi bi-house-fill"></i>
                กลับหน้าหลัก
            </a>
        </div>

        <!-- Footer -->
        <p class="text-center text-[9px] text-slate-300 font-black uppercase tracking-[0.2em] mt-12">
            © 2026 โรงเรียนละลมวิทยา • LLW Platform
        </p>
    </div>
</div>

<?php if ($error): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
        icon: 'error',
        title: 'เข้าสู่ระบบไม่สำเร็จ',
        text: '<?= htmlspecialchars($error, ENT_QUOTES) ?>',
        confirmButtonColor: '<?= htmlspecialchars($c['accent']) ?>',
        background: '#ffffff',
        customClass: { popup: 'rounded-[32px]', confirmButton: 'rounded-xl px-10 py-3 font-bold' }
    });
});
</script>
<?php endif; ?>

<script>
// Password toggle
const pwd = document.getElementById('pwd');
const icon = document.getElementById('pwd-icon');
document.getElementById('pwd-toggle').addEventListener('click', () => {
    const show = pwd.type === 'text';
    pwd.type = show ? 'password' : 'text';
    icon.className = show ? 'bi bi-eye' : 'bi bi-eye-slash';
});

// Auto focus
document.querySelector('input[name="username"]')?.focus();
</script>
</body>
</html>
