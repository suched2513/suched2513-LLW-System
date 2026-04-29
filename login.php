<?php
/**
 * login.php — LLW Platinum Login (Modern Dark Theme, Sarabun Font)
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
        'subtitle' => 'ยืม-คืนและตรวจสอบสภาพอุปกรณ์ดิจิทัล',
        'icon'     => 'bi-laptop',
        'grad'     => 'from-cyan-500 to-blue-600',
        'shadow'   => 'shadow-cyan-500/20',
        'accent'   => '#06b6d4',
        'badge'    => 'CHROMEBOOK SYSTEM',
    ],
    'attendance' => [
        'title'    => 'ระบบเช็คชื่อนักเรียน',
        'subtitle' => 'บันทึกเวลาเรียนรายวิชาแบบ Real-time',
        'icon'     => 'bi-person-check-fill',
        'grad'     => 'from-indigo-500 to-blue-600',
        'shadow'   => 'shadow-indigo-500/20',
        'accent'   => '#6366f1',
        'badge'    => 'ATTENDANCE SYSTEM',
    ],
    'wfh' => [
        'title'    => 'ระบบลงเวลาบุคลากร',
        'subtitle' => 'เข้า-ออกงานด้วย GPS ยืนยันตัวตน',
        'icon'     => 'bi-geo-alt-fill',
        'grad'     => 'from-emerald-500 to-teal-600',
        'shadow'   => 'shadow-emerald-500/20',
        'accent'   => '#10b981',
        'badge'    => 'WFH SYSTEM',
    ],
    'leave' => [
        'title'    => 'ระบบขออนุญาตออกนอก',
        'subtitle' => 'ยื่นคำขอลาออนไลน์ผ่านระบบอัตโนมัติ',
        'icon'     => 'bi-door-open-fill',
        'grad'     => 'from-rose-500 to-pink-600',
        'shadow'   => 'shadow-rose-500/20',
        'accent'   => '#f43f5e',
        'badge'    => 'LEAVE SYSTEM',
    ],
    'default' => [
        'title'    => 'LLW Platinum Portal',
        'subtitle' => 'ศูนย์กลางระบบบริหารจัดการโรงเรียนละลมวิทยา',
        'icon'     => 'bi-shield-lock-fill',
        'grad'     => 'from-blue-600 to-indigo-700',
        'shadow'   => 'shadow-blue-500/20',
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
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@100;200;300;400;500;600;700;800&family=Prompt:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --accent: <?= htmlspecialchars($c['accent']) ?>;
            --accent-grad: linear-gradient(135deg, <?= htmlspecialchars($c['accent']) ?>, #4f46e5);
        }
        body { font-family: 'Sarabun', sans-serif; }
        .font-header { font-family: 'Prompt', sans-serif; }
        
        .glass {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .inp {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 0.875rem 1rem 0.875rem 3rem;
            font-size: 0.875rem;
            color: white;
            outline: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .inp:focus {
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 4px rgba(<?= hexToRgb($c['accent']) ?>, 0.15);
        }
        
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob { animation: blob 7s infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }

        .btn-login {
            background: var(--accent-grad);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            filter: brightness(1.1);
        }
    </style>
</head>
<?php
function hexToRgb($hex) {
    $hex = str_replace("#", "", $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return "$r, $g, $b";
}
?>
<body class="min-h-screen bg-slate-950 flex items-stretch overflow-hidden selection:bg-indigo-500/30 selection:text-indigo-200">

<!-- ══════════════════════════════════════════════════════════
     LEFT PANEL — Immersive Branding
     ══════════════════════════════════════════════════════════ -->
<div class="hidden lg:flex lg:w-[60%] relative overflow-hidden flex-col justify-between p-16">
    
    <!-- Animated Background Decor -->
    <div class="absolute top-0 -left-4 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
    <div class="absolute top-0 -right-4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
    <div class="absolute -bottom-8 left-20 w-72 h-72 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
    
    <!-- Top Branding -->
    <div class="relative z-10 flex items-center gap-4">
        <div class="w-12 h-12 bg-white/10 backdrop-blur rounded-2xl flex items-center justify-center border border-white/10">
            <span class="font-header text-2xl font-black text-white">L</span>
        </div>
        <div>
            <h3 class="font-header text-white font-black tracking-wider text-xl">LLW PLATFORM</h3>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-[0.2em]">Lalom Wittaya School</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="relative z-10 max-w-xl">
        <div class="inline-flex items-center gap-2 px-3 py-1 bg-<?= explode('-',$c['grad'])[1] ?>/10 border border-<?= explode('-',$c['grad'])[1] ?>/20 rounded-full mb-6">
            <span class="w-2 h-2 rounded-full bg-<?= explode('-',$c['grad'])[1] ?> animate-pulse"></span>
            <span class="text-[10px] font-black text-white tracking-[0.2em] uppercase"><?= htmlspecialchars($c['badge']) ?></span>
        </div>
        
        <h1 class="text-6xl font-header font-black text-white leading-[1.1] mb-6">
            ยกระดับการศึกษา<br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r <?= $c['grad'] ?>">ด้วยระบบอัจฉริยะ</span>
        </h1>
        
        <p class="text-slate-400 text-lg font-medium leading-relaxed mb-10 max-w-md">
            <?= htmlspecialchars($c['subtitle']) ?> มุ่งมั่นพัฒนาศักยภาพผู้เรียนและบุคลากรผ่านนวัตกรรมดิจิทัลที่ทันสมัย
        </p>

        <!-- Feature Grid -->
        <div class="grid grid-cols-2 gap-6">
            <?php
            $features = [
                'chromebook' => [
                    ['icon' => 'bi-laptop', 'title' => 'ยืม-คืนเครื่อง', 'desc' => 'บันทึกผ่าน QR Code'],
                    ['icon' => 'bi-shield-check', 'title' => 'ตรวจสภาพ', 'desc' => 'รายงานความเสียหาย'],
                ],
                'attendance' => [
                    ['icon' => 'bi-person-check', 'title' => 'เช็คชื่อ', 'desc' => 'Real-time บันทึกทันใจ'],
                    ['icon' => 'bi-graph-up', 'title' => 'รายงาน', 'desc' => 'สรุปผลอัตโนมัติ'],
                ],
                'wfh' => [
                    ['icon' => 'bi-geo-alt', 'title' => 'ลงเวลา GPS', 'desc' => 'แม่นยำทุกพิกัด'],
                    ['icon' => 'bi-camera', 'title' => 'ถ่ายภาพ', 'desc' => 'ยืนยันตัวตนชัดเจน'],
                ],
                'leave' => [
                    ['icon' => 'bi-file-earmark-text', 'title' => 'ออนไลน์', 'desc' => 'ไม่ต้องใช้กระดาษ'],
                    ['icon' => 'bi-lightning', 'title' => 'อนุมัติเร็ว', 'desc' => 'แจ้งผ่าน Telegram'],
                ],
                'default' => [
                    ['icon' => 'bi-grid-1x2', 'title' => 'Multi-system', 'desc' => 'รวมทุกระบบในหนึ่งเดียว'],
                    ['icon' => 'bi-lock', 'title' => 'Secure Auth', 'desc' => 'ความปลอดภัยสูงสุด'],
                ],
            ];
            foreach ($features[$ctx] ?? $features['default'] as $feat):
            ?>
            <div class="glass p-5 rounded-[2rem] group hover:bg-white/5 transition-all duration-500">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br <?= $c['grad'] ?> flex items-center justify-center text-white text-xl mb-4 shadow-lg <?= $c['shadow'] ?>">
                    <i class="<?= $feat['icon'] ?>"></i>
                </div>
                <h4 class="text-white font-bold text-sm mb-1"><?= $feat['title'] ?></h4>
                <p class="text-slate-500 text-xs"><?= $feat['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Bottom Footer -->
    <div class="relative z-10 flex items-center justify-between text-slate-500 text-[10px] font-bold tracking-[0.2em] uppercase">
        <span>© 2026 Lalom Wittaya School</span>
        <div class="flex gap-6">
            <a href="#" class="hover:text-white transition-colors">Privacy Policy</a>
            <a href="#" class="hover:text-white transition-colors">Terms of Service</a>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     RIGHT PANEL — Login Form
     ══════════════════════════════════════════════════════════ -->
<div class="flex-1 flex items-center justify-center p-8 bg-slate-950 border-l border-white/5">
    
    <div class="w-full max-w-sm">
        
        <!-- Mobile Logo -->
        <div class="lg:hidden flex justify-center mb-10">
            <div class="w-16 h-16 bg-gradient-to-br <?= $c['grad'] ?> rounded-3xl flex items-center justify-center text-white text-3xl shadow-2xl <?= $c['shadow'] ?>">
                <i class="bi <?= $c['icon'] ?>"></i>
            </div>
        </div>

        <div class="mb-10 text-center lg:text-left">
            <h2 class="text-3xl font-header font-black text-white mb-2">ยินดีต้อนรับกลับมา</h2>
            <p class="text-slate-400 font-medium">กรุณาเข้าสู่ระบบเพื่อจัดการข้อมูลของคุณ</p>
        </div>

        <!-- Form -->
        <form method="POST" class="space-y-6">
            
            <div>
                <label class="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2 px-1">Username / ชื่อผู้ใช้งาน</label>
                <div class="relative group">
                    <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-white transition-colors">
                        <i class="bi bi-person text-lg"></i>
                    </div>
                    <input type="text" name="username" class="inp" placeholder="กรอกชื่อผู้ใช้งาน" required autocomplete="username">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2 px-1">
                    <label class="block text-[11px] font-black text-slate-500 uppercase tracking-widest">Password / รหัสผ่าน</label>
                    <a href="#" class="text-[10px] font-bold text-indigo-400 hover:text-indigo-300 transition-colors">ลืมรหัสผ่าน?</a>
                </div>
                <div class="relative group">
                    <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-white transition-colors">
                        <i class="bi bi-lock text-lg"></i>
                    </div>
                    <input type="password" name="password" id="pwd" class="inp pr-12" placeholder="กรอกรหัสผ่าน" required autocomplete="current-password">
                    <button type="button" id="pwd-toggle" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white transition-colors">
                        <i class="bi bi-eye" id="pwd-icon"></i>
                    </button>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-login w-full text-white py-4 rounded-2xl font-header font-black text-sm uppercase tracking-widest flex items-center justify-center gap-3">
                    เข้าสู่ระบบ
                    <i class="bi bi-arrow-right text-lg"></i>
                </button>
            </div>
        </form>

        <!-- Divider -->
        <div class="flex items-center gap-4 my-8">
            <div class="flex-1 h-px bg-white/5"></div>
            <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest">OR</span>
            <div class="flex-1 h-px bg-white/5"></div>
        </div>

        <div class="text-center">
            <a href="index.php" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-white transition-colors">
                <i class="bi bi-house"></i>
                กลับสู่หน้าหลักระบบ
            </a>
        </div>

        <!-- Footer -->
        <div class="mt-16 text-center">
            <p class="text-[10px] font-black text-slate-700 uppercase tracking-[0.2em]">
                Secure Portal • Version 2.0.4
            </p>
        </div>
    </div>
</div>

<?php if ($error): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
        icon: 'error',
        title: 'การเข้าสู่ระบบล้มเหลว',
        text: '<?= htmlspecialchars($error, ENT_QUOTES) ?>',
        confirmButtonColor: '<?= htmlspecialchars($c['accent']) ?>',
        background: '#0f172a',
        color: '#f8fafc',
        customClass: {
            popup: 'rounded-[2rem] border border-white/10 backdrop-blur-xl',
            confirmButton: 'rounded-xl px-8 py-3 font-bold uppercase tracking-widest text-xs'
        }
    });
});
</script>
<?php endif; ?>

<script>
// Password visibility toggle
const pwdInput = document.getElementById('pwd');
const pwdIcon = document.getElementById('pwd-icon');
document.getElementById('pwd-toggle').addEventListener('click', () => {
    const isText = pwdInput.type === 'text';
    pwdInput.type = isText ? 'password' : 'text';
    pwdIcon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
});

// Auto-focus username
document.querySelector('input[name="username"]')?.focus();
</script>

</body>
</html>
