<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pre_pass  = max(1, (int)$_POST['pre_pass_score']);
    $post_pass = max(1, (int)$_POST['post_pass_score']);
    $max_att   = max(1, (int)$_POST['post_max_attempts']);
    $pdo->prepare("UPDATE lms_exam_settings SET pre_pass_score=?, post_pass_score=?, post_max_attempts=? WHERE id=1")
        ->execute([$pre_pass, $post_pass, $max_att]);
    $msg = 'success:บันทึกการตั้งค่าสำเร็จ';
    header('Location: exam_settings.php?msg=' . urlencode($msg)); exit();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$s          = $pdo->query("SELECT * FROM lms_exam_settings LIMIT 1")->fetch();
$pre_total  = (int)$pdo->query("SELECT COUNT(*) FROM lms_pre_questions")->fetchColumn();
$post_total = (int)$pdo->query("SELECT COUNT(*) FROM lms_post_questions")->fetchColumn();

$pageTitle    = 'ตั้งค่าการสอบ';
$pageSubtitle = 'กำหนดเกณฑ์ผ่านและจำนวนครั้ง';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{
  const p='<?=addslashes($msg)?>'.split(':');
  Swal.fire({icon:p[0]==='success'?'success':'error',title:p[0]==='success'?'สำเร็จ':'ผิดพลาด',text:p[1],confirmButtonColor:'#7C3AED',timer:2000,showConfirmButton:false});
});</script>
<?php endif; ?>

<div class="flex items-center gap-3 mb-6">
  <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-slate-500 to-slate-700">
    <i class="fas fa-cog text-white"></i>
  </div>
  <div>
    <h2 class="text-lg font-black text-slate-800">ตั้งค่าการสอบ</h2>
    <p class="text-xs text-slate-400">กำหนดเกณฑ์การผ่านและจำนวนครั้งที่สอบได้</p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
  <!-- Form -->
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-6">
    <form method="POST" class="space-y-5">
      <h3 class="font-black text-slate-700 text-sm mb-4">กำหนดค่า</h3>

      <div class="rounded-xl p-4 bg-blue-50 border border-blue-100">
        <div class="font-bold text-blue-800 text-xs mb-3"><i class="fas fa-play-circle mr-1"></i> แบบทดสอบก่อนเรียน</div>
        <label class="block text-xs font-black text-slate-500 mb-1">จำนวนข้อที่ผ่านขึ้นไป
          <span class="font-normal text-slate-400">(จาก <?=$pre_total?> ข้อ)</span></label>
        <div class="flex items-center gap-3">
          <input type="number" name="pre_pass_score" value="<?=$s['pre_pass_score']??6?>"
            min="1" max="<?=$pre_total?:100?>" required
            class="w-24 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-blue-400 outline-none">
          <span class="text-xs text-slate-400">ข้อขึ้นไป</span>
        </div>
        <p class="text-xs text-blue-600 mt-2"><i class="fas fa-info-circle mr-1"></i>สอบได้ไม่จำกัดครั้ง จนกว่าจะผ่าน</p>
      </div>

      <div class="rounded-xl p-4 bg-rose-50 border border-rose-100">
        <div class="font-bold text-rose-800 text-xs mb-3"><i class="fas fa-flag-checkered mr-1"></i> แบบทดสอบหลังเรียน</div>
        <div class="mb-3">
          <label class="block text-xs font-black text-slate-500 mb-1">จำนวนข้อที่ผ่านขึ้นไป
            <span class="font-normal text-slate-400">(จาก <?=$post_total?> ข้อ)</span></label>
          <div class="flex items-center gap-3">
            <input type="number" name="post_pass_score" value="<?=$s['post_pass_score']??6?>"
              min="1" max="<?=$post_total?:100?>" required
              class="w-24 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-rose-400 outline-none">
            <span class="text-xs text-slate-400">ข้อขึ้นไป</span>
          </div>
        </div>
        <div>
          <label class="block text-xs font-black text-slate-500 mb-1">สอบได้กี่ครั้ง</label>
          <div class="flex items-center gap-3">
            <input type="number" name="post_max_attempts" value="<?=$s['post_max_attempts']??3?>"
              min="1" max="99" required
              class="w-24 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-rose-400 outline-none">
            <span class="text-xs text-slate-400">ครั้ง</span>
          </div>
        </div>
      </div>

      <button type="submit" class="w-full py-3 bg-violet-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all flex items-center justify-center gap-2">
        <i class="fas fa-save"></i> บันทึกการตั้งค่า
      </button>
    </form>
  </div>

  <!-- Summary -->
  <div class="space-y-4">
    <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5" style="border-left:4px solid #0288D1">
      <div class="text-xs text-slate-400 mb-1">การตั้งค่าปัจจุบัน — ก่อนเรียน</div>
      <div class="text-3xl font-black text-blue-600"><?=$s['pre_pass_score']??'—'?><span class="text-base font-normal text-slate-400"> / <?=$pre_total?> ข้อ</span></div>
      <div class="text-xs text-slate-400 mt-1">สอบได้ไม่จำกัดครั้ง</div>
    </div>
    <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5" style="border-left:4px solid #EF5350">
      <div class="text-xs text-slate-400 mb-1">การตั้งค่าปัจจุบัน — หลังเรียน</div>
      <div class="text-3xl font-black text-rose-500"><?=$s['post_pass_score']??'—'?><span class="text-base font-normal text-slate-400"> / <?=$post_total?> ข้อ</span></div>
      <div class="text-xs text-slate-400 mt-1">สอบได้ <strong><?=$s['post_max_attempts']??3?></strong> ครั้ง</div>
    </div>
    <div class="bg-orange-50 rounded-2xl border border-orange-100 p-5">
      <div class="font-bold text-orange-700 text-xs mb-2"><i class="fas fa-exclamation-triangle mr-1"></i>เงื่อนไขรีเซ็ต</div>
      <p class="text-xs text-orange-600">หากนักเรียนสอบหลังเรียนครบจำนวนครั้งแล้วยังไม่ผ่าน ระบบจะรีเซ็ตประวัติสอบทั้งหมด (ก่อนเรียน + หลังเรียน + แบบฝึกหัด) เพื่อให้เริ่มต้นใหม่</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
