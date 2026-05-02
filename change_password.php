<?php
/**
 * change_password.php — หน้าเปลี่ยนรหัสผ่านส่วนตัวสำหรับผู้ใช้งานทุกคน
 */
session_start();
require_once __DIR__ . '/config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: login.php');
    exit();
}

$pdo = getPdo();
$msg = '';
$msgType = 'success';

// Forced mode: ถูก redirect มาจาก login เพราะยังไม่ได้เปลี่ยนรหัสผ่าน
$forcedMode = !empty($_SESSION['force_password_change']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    $user_id      = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("SELECT password FROM llw_users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            $msg = 'ไม่พบข้อมูลผู้ใช้งาน';
            $msgType = 'error';
        } elseif (!$forcedMode && !password_verify($current_pass, $user['password'])) {
            $msg = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            $msgType = 'error';
        } elseif (strlen($new_pass) < 6) {
            $msg = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
            $msgType = 'error';
        } elseif ($new_pass === '123456') {
            $msg = 'กรุณาตั้งรหัสผ่านใหม่ที่ไม่ใช่ 123456';
            $msgType = 'error';
        } elseif ($new_pass !== $confirm_pass) {
            $msg = 'การยืนยันรหัสผ่านไม่ตรงกัน';
            $msgType = 'error';
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $update = $pdo->prepare("UPDATE llw_users SET password = ?, force_password_change = 0 WHERE user_id = ?");
            $update->execute([$new_hash, $user_id]);
            $_SESSION['force_password_change'] = 0;
            $msg = 'เปลี่ยนรหัสผ่านสำเร็จแล้ว';
            $msgType = 'success';

            // Forced mode: redirect ไปหน้างานทันที
            if ($forcedMode) {
                $map = [
                    'super_admin' => '/central_dashboard.php',
                    'wfh_admin'   => '/admin/dashboard.php',
                    'wfh_staff'   => '/user/dashboard.php',
                    'cb_admin'    => '/chromebook/index.php',
                    'att_teacher' => '/attendance_system/dashboard.php',
                    'bus_admin'   => '/bus/admin/dashboard.php',
                    'bus_finance' => '/bus/admin/dashboard.php',
                ];
                header('Location: ' . $base_path . ($map[$_SESSION['llw_role']] ?? '/index.php')); exit();
            }
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        $msg = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
        $msgType = 'error';
    }
}

$pageTitle = $forcedMode ? 'ตั้งรหัสผ่านครั้งแรก' : 'เปลี่ยนรหัสผ่าน';
$pageSubtitle = $forcedMode ? 'กรุณาตั้งรหัสผ่านส่วนตัวก่อนใช้งานระบบ' : 'ความปลอดภัยของบัญชีผู้ใช้งาน';
$activeSystem = 'portal';

require_once __DIR__ . '/components/layout_start.php';
?>

<div class="max-w-2xl mx-auto">
    <!-- Hero Card -->
    <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-[2.5rem] p-8 sm:p-10 text-white shadow-2xl shadow-indigo-200/50 mb-8 relative overflow-hidden">
        <div class="relative z-10">
            <h2 class="text-3xl font-black mb-2 flex items-center gap-3">
                <i class="bi bi-shield-lock-fill"></i> ความปลอดภัย
            </h2>
            <p class="text-indigo-100 text-sm font-medium opacity-80 leading-relaxed max-w-md">
                รหัสผ่านเป็นสิ่งสำคัญ โปรดรักษาเป็นความลับและเปลี่ยนสม่ำเสมอเพื่อความปลอดภัยของข้อมูลในระบบ
            </p>
        </div>
        <i class="bi bi-key-fill absolute -right-10 -bottom-10 text-[10rem] text-white/10 rotate-12"></i>
    </div>

    <!-- Change Password Form -->
    <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8 sm:p-10">

        <?php if ($forcedMode): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-start gap-3">
            <i class="bi bi-exclamation-triangle-fill text-amber-500 text-lg flex-shrink-0 mt-0.5"></i>
            <div>
                <p class="text-amber-800 font-black text-sm">กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งาน</p>
                <p class="text-amber-600 text-xs mt-0.5">รหัสผ่านใหม่จะใช้กับ<strong>ทุกระบบงาน</strong>ของโรงเรียน กรุณาจดจำไว้และอย่าแชร์ให้ผู้อื่น</p>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <?= csrf_field() ?>
            <?php if (!$forcedMode): ?>
            <div>
                <label class="block text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-2.5 ml-1">รหัสผ่านปัจจุบัน (Current Password)</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                        <i class="bi bi-lock"></i>
                    </div>
                    <input type="password" name="current_password" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-4 py-4 text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all"
                           placeholder="ระบุรหัสผ่านที่คุณใช้อยู่ขณะนี้">
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-2.5 ml-1">รหัสผ่านใหม่ (New Password)</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                            <i class="bi bi-key"></i>
                        </div>
                        <input type="password" name="new_password" required minlength="6"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-4 py-4 text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all"
                               placeholder="อย่างน้อย 6 ตัวอักษร">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-2.5 ml-1">ยืนยันรหัสผ่านใหม่ (Confirm)</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <input type="password" name="confirm_password" required minlength="6"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-4 py-4 text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all"
                               placeholder="พิมพ์รหัสผ่านใหม่อีกครั้ง">
                    </div>
                </div>
            </div>

            <div class="pt-4 flex flex-col sm:flex-row gap-4">
                <button type="submit" 
                        class="flex-[2] bg-gradient-to-r from-indigo-600 to-blue-600 text-white px-8 py-4 rounded-[1.25rem] font-black text-xs uppercase tracking-widest shadow-xl shadow-indigo-100 hover:scale-[1.02] active:scale-95 transition-all">
                    บันทึกการเปลี่ยนแปลง
                </button>
                <a href="index.php" 
                   class="flex-1 bg-slate-100 text-slate-500 px-8 py-4 rounded-[1.25rem] font-black text-xs uppercase tracking-widest text-center hover:bg-slate-200 transition-all">
                    ยกเลิก
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($msg): ?>
<script>
    Swal.fire({
        icon: '<?= $msgType ?>',
        title: '<?= $msgType === "success" ? "สำเร็จ" : "ผิดพลาด" ?>',
        text: '<?= $msg ?>',
        confirmButtonColor: '#4f46e5',
        customClass: {
            popup: 'rounded-[2rem]',
            confirmButton: 'rounded-xl'
        }
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
