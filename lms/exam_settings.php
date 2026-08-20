<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
require_once __DIR__ . '/_helpers.php';

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);
$msg = '';

$unit_id = (int)($_GET['unit_id'] ?? $_POST['unit_id'] ?? 0);
if (!$unit_id) { header('Location: subjects.php'); exit(); }
$unit = lms_get_owned_unit($pdo, $unit_id, $is_admin, $teacher_id);
if (!$unit) { header('Location: subjects.php'); exit(); }
$subject_id = (int)$unit['subject_id'];
$subject = $pdo->prepare("SELECT * FROM lms_subjects WHERE id=?"); $subject->execute([$subject_id]); $subject = $subject->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pre_pass     = max(1, (int)$_POST['pre_pass_score']);
    $post_pass    = max(1, (int)$_POST['post_pass_score']);
    $max_att      = max(1, (int)$_POST['post_max_attempts']);
    $show_answer  = isset($_POST['post_show_answer']) ? 1 : 0;

    $open_raw  = trim($_POST['post_exam_open_at']  ?? '');
    $close_raw = trim($_POST['post_exam_close_at'] ?? '');
    $open_at   = $open_raw  ? date('Y-m-d H:i:s', strtotime($open_raw))  : null;
    $close_at  = $close_raw ? date('Y-m-d H:i:s', strtotime($close_raw)) : null;

    if ($open_at && $close_at && $close_at <= $open_at) {
        $msg = 'error:เวลาปิดต้องหลังเวลาเปิดเสมอ';
        header('Location: exam_settings.php?unit_id='.$unit_id.'&msg='.urlencode($msg)); exit();
    }

    $pdo->prepare("
        INSERT INTO lms_exam_settings (subject_id, unit_id, pre_pass_score, post_pass_score, post_max_attempts, post_exam_open_at, post_exam_close_at, post_show_answer)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE pre_pass_score=?, post_pass_score=?, post_max_attempts=?, post_exam_open_at=?, post_exam_close_at=?, post_show_answer=?
    ")->execute([$subject_id, $unit_id, $pre_pass, $post_pass, $max_att, $open_at, $close_at, $show_answer, $pre_pass, $post_pass, $max_att, $open_at, $close_at, $show_answer]);
    $msg = 'success:บันทึกการตั้งค่าสำเร็จ';
    header('Location: exam_settings.php?unit_id='.$unit_id.'&msg='.urlencode($msg)); exit();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$st = $pdo->prepare("SELECT * FROM lms_exam_settings WHERE unit_id=?");
$st->execute([$unit_id]);
$s = $st->fetch();

$stpre = $pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions WHERE unit_id=?"); $stpre->execute([$unit_id]); $pre_total = (int)$stpre->fetchColumn();
$stpost = $pdo->prepare("SELECT COUNT(*) FROM lms_post_questions WHERE unit_id=?"); $stpost->execute([$unit_id]); $post_total = (int)$stpost->fetchColumn();

$pageTitle    = 'ตั้งค่าการสอบ';
$pageSubtitle = htmlspecialchars($unit['unit_name'],ENT_QUOTES,'UTF-8');
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
    <a href="<?=$base_path?>/lms/units.php?subject_id=<?=$subject_id?>" class="text-xs text-violet-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i>หน่วยการเรียนรู้</a>
    <h2 class="text-lg font-black text-slate-800">ตั้งค่าการสอบ</h2>
    <p class="text-xs text-slate-400">หน่วย: <?=htmlspecialchars($unit['unit_name'],ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($subject['subject_name'] ?? '',ENT_QUOTES,'UTF-8')?></p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-6">
    <form method="POST" class="space-y-5">
      <input type="hidden" name="unit_id" value="<?=$unit_id?>">
      <h3 class="font-black text-slate-700 text-sm mb-4">กำหนดค่า</h3>

      <div class="rounded-xl p-4 bg-blue-50 border border-blue-100">
        <div class="font-bold text-blue-800 text-xs mb-3"><i class="fas fa-play-circle mr-1"></i> แบบทดสอบก่อนเรียน (หน่วยนี้)</div>
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

      <div class="rounded-xl p-4 bg-rose-50 border border-rose-100 space-y-3">
        <div class="font-bold text-rose-800 text-xs mb-1"><i class="fas fa-flag-checkered mr-1"></i> แบบทดสอบหลังเรียน (หน่วยนี้)</div>
        <div>
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
        <div class="border-t border-rose-200 pt-3">
          <div class="font-bold text-rose-700 text-xs mb-2"><i class="fas fa-clock mr-1"></i> ช่วงเวลาเปิดสอบ <span class="font-normal text-rose-400">(ไม่กำหนด = เปิดตลอด)</span></div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-black text-slate-500 mb-1">เปิดสอบ</label>
              <input type="datetime-local" name="post_exam_open_at"
                value="<?=!empty($s['post_exam_open_at']) ? date('Y-m-d\TH:i', strtotime($s['post_exam_open_at'])) : ''?>"
                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-rose-400 outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-slate-500 mb-1">ปิดสอบ</label>
              <input type="datetime-local" name="post_exam_close_at"
                value="<?=!empty($s['post_exam_close_at']) ? date('Y-m-d\TH:i', strtotime($s['post_exam_close_at'])) : ''?>"
                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-rose-400 outline-none">
            </div>
          </div>
          <p class="text-xs text-rose-500 mt-2"><i class="fas fa-info-circle mr-1"></i>นักเรียนจะเข้าสอบได้เฉพาะในช่วงเวลาที่กำหนด ลบค่าออกทั้งสองช่องเพื่อเปิดตลอดเวลา</p>
        </div>
        <div class="border-t border-rose-200 pt-3">
          <label class="flex items-start gap-2 cursor-pointer">
            <input type="checkbox" name="post_show_answer" value="1" <?=($s['post_show_answer']??1)?'checked':''?>
              class="mt-0.5 accent-rose-600">
            <span class="text-xs"><span class="font-bold text-slate-700">แสดงเฉลยให้นักเรียนเห็นหลังสอบ</span><br>
            <span class="text-slate-400">ถ้าปิด นักเรียนจะเห็นแค่คะแนน ไม่เห็นว่าข้อไหนถูก/ผิด หรือคำตอบที่ถูกต้อง</span></span>
          </label>
        </div>
      </div>

      <button type="submit" class="w-full py-3 bg-violet-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all flex items-center justify-center gap-2">
        <i class="fas fa-save"></i> บันทึกการตั้งค่า
      </button>
    </form>
  </div>

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
      <?php
        $now = time();
        $open  = !empty($s['post_exam_open_at'])  ? strtotime($s['post_exam_open_at'])  : null;
        $close = !empty($s['post_exam_close_at']) ? strtotime($s['post_exam_close_at']) : null;
        if ($open || $close):
          $is_open = (!$open || $now >= $open) && (!$close || $now <= $close);
      ?>
      <div class="mt-2 flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full <?=$is_open?'bg-emerald-500':'bg-slate-300'?>"></span>
        <span class="text-xs font-bold <?=$is_open?'text-emerald-600':'text-slate-400'?>"><?=$is_open?'เปิดสอบอยู่':'ยังไม่ถึงเวลา / ปิดแล้ว'?></span>
      </div>
      <?php if ($open): ?>
      <div class="text-xs text-slate-400 mt-1">เปิด: <?=date('d/m/Y H:i', $open)?></div>
      <?php endif; ?>
      <?php if ($close): ?>
      <div class="text-xs text-slate-400">ปิด: <?=date('d/m/Y H:i', $close)?></div>
      <?php endif; ?>
      <?php else: ?>
      <div class="text-xs text-slate-400 mt-1">เปิดตลอดเวลา</div>
      <?php endif; ?>
    </div>
    <div class="bg-orange-50 rounded-2xl border border-orange-100 p-5">
      <div class="font-bold text-orange-700 text-xs mb-2"><i class="fas fa-exclamation-triangle mr-1"></i>เงื่อนไขรีเซ็ต</div>
      <p class="text-xs text-orange-600">หากนักเรียนสอบหลังเรียนของหน่วยนี้ครบจำนวนครั้งแล้วยังไม่ผ่าน ระบบจะรีเซ็ตประวัติสอบของหน่วยนี้ (ก่อนเรียน + หลังเรียน + แบบฝึกหัดของหน่วยนี้) เพื่อให้เริ่มต้นใหม่</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
