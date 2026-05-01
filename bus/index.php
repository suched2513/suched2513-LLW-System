<?php
/**
 * bus/index.php — Student Login Portal
 */
session_start();
require_once __DIR__ . '/config.php';

// Already logged in → straight to dashboard
if (isset($_SESSION['bus_student_id'])) {
    header('Location: /bus/dashboard.php'); exit();
}

$error = '';
$redirect = $_GET['redirect'] ?? '/bus/dashboard.php';
$bye = isset($_GET['bye']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $sid = trim($_POST['student_id'] ?? '');
    $nid = trim($_POST['national_id'] ?? '');
    $nid = preg_replace('/\D/', '', $nid); // digits only
    // Normalize student ID: if purely numeric, pad to 5 digits (e.g. 4853 → 04853)
    if ($sid !== '' && ctype_digit($sid)) {
        $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
    }

    if ($sid === '' || strlen($nid) !== 13) {
        $error = 'กรุณากรอกรหัสนักเรียนและเลขบัตรประชาชน 13 หลักให้ถูกต้อง';
    } else {
        try {
            $pdo = getPdo();
            $stmt = $pdo->prepare("SELECT * FROM bus_students WHERE student_id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$sid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && password_verify($nid, $row['national_id_hash'])) {
                $_SESSION['bus_student_id']    = $row['id'];
                $_SESSION['bus_student_sid']   = $row['student_id'];
                $_SESSION['bus_student_name']  = $row['fullname'];
                $_SESSION['bus_student_class'] = $row['classroom'];
                header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
                exit();
            } else {
                $error = 'รหัสนักเรียนหรือเลขบัตรประชาชนไม่ถูกต้อง หรือบัญชีถูกปิดใช้งาน';
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            $error = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
        }
    }
}
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>เข้าสู่ระบบ | รถรับส่งนักเรียน LLW</title>
<meta name="theme-color" content="#f97316">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family:'Prompt',sans-serif; }
@keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
.animate-fade-up { animation:fadeUp .5s ease-out both; }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-orange-400 via-amber-400 to-yellow-300 flex items-center justify-center p-4">

<!-- Decorative circles -->
<div class="fixed top-0 left-0 w-64 h-64 bg-white/10 rounded-full -translate-x-1/2 -translate-y-1/2 pointer-events-none"></div>
<div class="fixed bottom-0 right-0 w-96 h-96 bg-white/10 rounded-full translate-x-1/3 translate-y-1/3 pointer-events-none"></div>

<div class="w-full max-w-sm animate-fade-up">

    <!-- School Header -->
    <div class="text-center mb-6">
        <div class="w-20 h-20 bg-white rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-2xl shadow-orange-600/30">
            <i class="bi bi-bus-front-fill text-4xl text-orange-500"></i>
        </div>
        <h1 class="text-2xl font-black text-white drop-shadow">ระบบรถรับส่งนักเรียน</h1>
        <p class="text-orange-100 text-sm font-medium mt-1">โรงเรียนละลมวิทยา</p>
    </div>

    <!-- Login Card -->
    <div class="bg-white rounded-[28px] shadow-2xl shadow-orange-600/20 overflow-hidden">
        <div class="bg-gradient-to-r from-orange-500 to-amber-500 px-6 py-4">
            <p class="text-white font-black text-sm">เข้าสู่ระบบ</p>
            <p class="text-orange-100 text-[11px]">สำหรับนักเรียนเท่านั้น</p>
        </div>
        <div class="p-6 space-y-4">

            <?php if ($bye): ?>
            <div class="bg-emerald-50 text-emerald-700 rounded-2xl px-4 py-3 text-sm font-bold flex items-center gap-2">
                <i class="bi bi-check-circle-fill"></i> ออกจากระบบเรียบร้อยแล้ว
            </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
            <div class="bg-rose-50 text-rose-700 rounded-2xl px-4 py-3 text-sm font-bold flex items-center gap-2 border border-rose-100">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">รหัสนักเรียน</label>
                    <div class="relative">
                        <i class="bi bi-person-badge absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="student_id" required autocomplete="username" inputmode="numeric"
                               placeholder="เช่น 04849"
                               class="w-full pl-10 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold focus:ring-2 focus:ring-orange-400 outline-none transition-all"
                               value="<?= htmlspecialchars($_POST['student_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">เลขบัตรประชาชน 13 หลัก</label>
                    <div class="relative">
                        <i class="bi bi-credit-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="password" name="national_id" required autocomplete="current-password" inputmode="numeric"
                               maxlength="13" placeholder="• • • • • • • • • • • • •"
                               class="w-full pl-10 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold focus:ring-2 focus:ring-orange-400 outline-none transition-all">
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 pl-1">ใช้เป็นรหัสผ่านในการเข้าระบบ</p>
                </div>

                <button type="submit"
                    class="w-full py-4 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-2xl font-black text-sm shadow-lg shadow-orange-200 hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-box-arrow-in-right text-lg"></i> เข้าสู่ระบบ
                </button>
            </form>
        </div>
    </div>

    <!-- Back link -->
    <div class="text-center mt-5 space-y-2">
        <a href="/index.php" class="text-white/80 text-xs font-bold hover:text-white transition-colors flex items-center justify-center gap-1">
            <i class="bi bi-arrow-left"></i> กลับหน้าหลัก LLW
        </a>
        <p class="text-orange-100/70 text-[10px]">หากเข้าสู่ระบบไม่ได้ กรุณาติดต่อเจ้าหน้าที่การเงิน</p>
    </div>

</div>
</body>
</html>
