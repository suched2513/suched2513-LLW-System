<?php
session_start();
require_once __DIR__ . '/../config.php';

// Already logged in
if (!empty($_SESSION['is_student'])) {
    header('Location: /student/dashboard.php'); exit();
}

$redirect = $_GET['redirect'] ?? '/student/dashboard.php';
$bye      = isset($_GET['bye']);
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $sid = trim($_POST['student_id'] ?? '');
    $nid = preg_replace('/\D/', '', trim($_POST['national_id'] ?? ''));

    // Normalize: purely numeric → pad to 5 digits (e.g. 4853 → 04853)
    if ($sid !== '' && ctype_digit($sid)) {
        $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
    }

    if ($sid === '' || strlen($nid) !== 13) {
        $error = 'กรุณากรอกรหัสนักเรียนและเลขบัตรประชาชน 13 หลักให้ถูกต้อง';
    } else {
        try {
            $pdo  = getPdo();
            $stmt = $pdo->prepare("SELECT * FROM att_students WHERE student_id = ? AND national_id_hash IS NOT NULL LIMIT 1");
            $stmt->execute([$sid]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student && password_verify($nid, $student['national_id_hash'])) {
                session_regenerate_id(true);

                $_SESSION['is_student']    = true;
                $_SESSION['student_uid']   = $student['id'];
                $_SESSION['student_code']  = $student['student_id'];
                $_SESSION['student_name']  = $student['name'];
                $_SESSION['student_class'] = $student['classroom'];

                $pdo->prepare("UPDATE att_students SET last_login = NOW() WHERE id = ?")
                    ->execute([$student['id']]);

                // Bridge bus session so student can access bus pages without re-login
                $busStmt = $pdo->prepare("SELECT * FROM bus_students WHERE student_id = ? AND is_active = 1 LIMIT 1");
                $busStmt->execute([$sid]);
                $busRow = $busStmt->fetch(PDO::FETCH_ASSOC);
                if ($busRow && password_verify($nid, $busRow['national_id_hash'])) {
                    $_SESSION['bus_student_id']    = $busRow['id'];
                    $_SESSION['bus_student_sid']   = $busRow['student_id'];
                    $_SESSION['bus_student_name']  = $busRow['fullname'];
                    $_SESSION['bus_student_class'] = $busRow['classroom'];
                }

                $rd = filter_var($redirect, FILTER_SANITIZE_URL);
                header('Location: ' . (str_starts_with($rd, '/') ? $rd : '/student/dashboard.php'));
                exit();
            } else {
                $error = 'รหัสนักเรียนหรือเลขบัตรประชาชนไม่ถูกต้อง';
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            $error = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่';
        }
    }
}
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>เข้าสู่ระบบนักเรียน | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#0d9488">
<meta name="apple-mobile-web-app-capable" content="yes">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family:'Prompt',sans-serif; }
@keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
.fade-up { animation:fadeUp .45s ease-out both; }
.fade-up-2 { animation:fadeUp .45s ease-out .1s both; }
.fade-up-3 { animation:fadeUp .45s ease-out .2s both; }
</style>
</head>
<body class="min-h-screen flex flex-col" style="background:linear-gradient(160deg,#0f766e 0%,#0d9488 40%,#0891b2 100%);padding-top:env(safe-area-inset-top);padding-bottom:env(safe-area-inset-bottom)">

<!-- Header -->
<div class="flex items-center gap-3 px-5 pt-5 pb-2">
    <a href="/index.php" class="w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/25">
        <i class="bi bi-arrow-left text-white"></i>
    </a>
    <div>
        <div class="text-white font-black text-sm leading-tight">พอร์ทัลนักเรียน</div>
        <div class="text-teal-200 text-[10px] font-bold">โรงเรียนละลมวิทยา</div>
    </div>
</div>

<!-- Card -->
<div class="flex-1 flex items-center justify-center px-5 py-6">
<div class="w-full max-w-sm">

    <!-- Logo -->
    <div class="text-center mb-8 fade-up">
        <div class="w-24 h-24 bg-white/15 rounded-[2rem] flex items-center justify-center border border-white/25 shadow-2xl mx-auto mb-4">
            <i class="bi bi-mortarboard-fill text-white text-5xl"></i>
        </div>
        <h1 class="text-white font-black text-2xl leading-tight">เข้าสู่ระบบ</h1>
        <p class="text-teal-200 text-sm font-medium mt-1">ใช้รหัสนักเรียน + เลขบัตรประชาชน</p>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-3xl shadow-2xl p-6 fade-up-2">
        <?php if ($bye): ?>
        <div class="mb-4 px-4 py-3 bg-teal-50 border border-teal-100 rounded-2xl text-teal-700 text-xs font-bold text-center">
            <i class="bi bi-check-circle-fill mr-1"></i>ออกจากระบบเรียบร้อยแล้ว
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">
                    รหัสนักเรียน
                </label>
                <div class="relative">
                    <i class="bi bi-person-badge absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="student_id" required inputmode="numeric"
                           placeholder="เช่น 04853"
                           value="<?= htmlspecialchars($_POST['student_id'] ?? '', ENT_QUOTES) ?>"
                           class="w-full pl-10 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none transition-all">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">
                    เลขบัตรประชาชน 13 หลัก
                </label>
                <div class="relative">
                    <i class="bi bi-credit-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="password" name="national_id" id="nidInput" required inputmode="numeric"
                           maxlength="13" placeholder="x-xxxx-xxxxx-xx-x"
                           class="w-full pl-10 pr-12 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none transition-all">
                    <button type="button" onclick="toggleNid()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="bi bi-eye" id="nidEye"></i>
                    </button>
                </div>
                <p class="text-[10px] text-slate-400 mt-1.5 ml-1">ไม่ต้องใส่เครื่องหมาย - (ขีด)</p>
            </div>

            <button type="submit"
                    class="w-full py-4 bg-gradient-to-r from-teal-500 to-cyan-500 text-white rounded-2xl font-black text-sm shadow-xl shadow-teal-200/60 active:scale-95 transition-transform flex items-center justify-center gap-2 mt-2">
                <i class="bi bi-box-arrow-in-right text-base"></i> เข้าสู่ระบบ
            </button>
        </form>
    </div>

    <!-- Info -->
    <div class="mt-5 px-2 fade-up-3">
        <div class="bg-white/10 border border-white/15 rounded-2xl px-4 py-3.5 text-teal-100 text-xs space-y-1">
            <p class="font-black text-white flex items-center gap-1.5"><i class="bi bi-info-circle-fill"></i> วิธีเข้าสู่ระบบ</p>
            <p>• รหัสนักเรียน: เลขประจำตัว เช่น <span class="font-black text-white">04853</span></p>
            <p>• รหัสผ่าน: เลขบัตรประชาชน 13 หลัก</p>
            <p>• หากเข้าไม่ได้ ติดต่อครูประจำชั้น</p>
        </div>
    </div>

</div>
</div>

<?php if ($error): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
        icon: 'error',
        title: 'เข้าสู่ระบบไม่สำเร็จ',
        text: <?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>,
        confirmButtonColor: '#0d9488',
        customClass: { popup: 'rounded-[2rem]', confirmButton: 'rounded-xl' }
    });
});
</script>
<?php endif; ?>
<script>
function toggleNid() {
    const inp = document.getElementById('nidInput');
    const ico = document.getElementById('nidEye');
    const isHidden = inp.type === 'password';
    inp.type = isHidden ? 'text' : 'password';
    ico.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
}
</script>
</body>
</html>
