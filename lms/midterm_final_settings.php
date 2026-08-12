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

$subject_id = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
if (!$subject_id) { header('Location: subjects.php'); exit(); }
$subject = lms_get_owned_subject($pdo, $subject_id, $is_admin, $teacher_id);
if (!$subject) { header('Location: subjects.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $midterm_pass = max(1, (int)$_POST['midterm_pass_score']);
    $midterm_att  = max(1, (int)$_POST['midterm_max_attempts']);
    $final_pass   = max(1, (int)$_POST['final_pass_score']);
    $final_att    = max(1, (int)$_POST['final_max_attempts']);

    $m_open_raw  = trim($_POST['midterm_open_at']  ?? '');
    $m_close_raw = trim($_POST['midterm_close_at'] ?? '');
    $f_open_raw  = trim($_POST['final_open_at']    ?? '');
    $f_close_raw = trim($_POST['final_close_at']   ?? '');
    $m_open  = $m_open_raw  ? date('Y-m-d H:i:s', strtotime($m_open_raw))  : null;
    $m_close = $m_close_raw ? date('Y-m-d H:i:s', strtotime($m_close_raw)) : null;
    $f_open  = $f_open_raw  ? date('Y-m-d H:i:s', strtotime($f_open_raw))  : null;
    $f_close = $f_close_raw ? date('Y-m-d H:i:s', strtotime($f_close_raw)) : null;

    if (($m_open && $m_close && $m_close <= $m_open) || ($f_open && $f_close && $f_close <= $f_open)) {
        $msg = 'error:เวลาปิดต้องหลังเวลาเปิดเสมอ';
        header('Location: midterm_final_settings.php?subject_id='.$subject_id.'&msg='.urlencode($msg)); exit();
    }

    $pdo->prepare("
        INSERT INTO lms_subject_settings
            (subject_id, unlock_mode, midterm_pass_score, midterm_max_attempts, midterm_open_at, midterm_close_at,
             final_pass_score, final_max_attempts, final_open_at, final_close_at)
        VALUES (?, 'open_all', ?,?,?,?, ?,?,?,?)
        ON DUPLICATE KEY UPDATE
            midterm_pass_score=?, midterm_max_attempts=?, midterm_open_at=?, midterm_close_at=?,
            final_pass_score=?, final_max_attempts=?, final_open_at=?, final_close_at=?
    ")->execute([
        $subject_id, $midterm_pass, $midterm_att, $m_open, $m_close, $final_pass, $final_att, $f_open, $f_close,
        $midterm_pass, $midterm_att, $m_open, $m_close, $final_pass, $final_att, $f_open, $f_close,
    ]);
    $msg = 'success:บันทึกการตั้งค่าสำเร็จ';
    header('Location: midterm_final_settings.php?subject_id='.$subject_id.'&msg='.urlencode($msg)); exit();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$st = $pdo->prepare("SELECT * FROM lms_subject_settings WHERE subject_id=?");
$st->execute([$subject_id]);
$s = $st->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_midterm_questions WHERE subject_id=?"); $stmt->execute([$subject_id]); $midterm_total = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_final_questions WHERE subject_id=?");   $stmt->execute([$subject_id]); $final_total   = (int)$stmt->fetchColumn();

$pageTitle    = 'ตั้งค่าสอบกลางภาค-ปลายภาค';
$pageSubtitle = htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8');
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
    <a href="<?=$base_path?>/lms/subject_dashboard.php?subject_id=<?=$subject_id?>&tab=exam" class="text-xs text-violet-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i>ข้อสอบ</a>
    <h2 class="text-lg font-black text-slate-800">ตั้งค่าสอบกลางภาค-ปลายภาค</h2>
    <p class="text-xs text-slate-400"><?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?></p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-6">
    <form method="POST" class="space-y-5">
      <input type="hidden" name="subject_id" value="<?=$subject_id?>">

      <div class="rounded-xl p-4 bg-indigo-50 border border-indigo-100 space-y-3">
        <div class="font-bold text-indigo-800 text-xs mb-1"><i class="fas fa-clipboard-list mr-1"></i> สอบกลางภาค</div>
        <div>
          <label class="block text-xs font-black text-slate-500 mb-1">จำนวนข้อที่ผ่านขึ้นไป
            <span class="font-normal text-slate-400">(จาก <?=$midterm_total?> ข้อ)</span></label>
          <div class="flex items-center gap-3">
            <input type="number" name="midterm_pass_score" value="<?=$s['midterm_pass_score']??6?>"
              min="1" max="<?=$midterm_total?:100?>" required
              class="w-24 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-indigo-400 outline-none">
            <span class="text-xs text-slate-400">ข้อขึ้นไป</span>
          </div>
        </div>
        <div>
          <label class="block text-xs font-black text-slate-500 mb-1">สอบได้กี่ครั้ง</label>
          <div class="flex items-center gap-3">
            <input type="number" name="midterm_max_attempts" value="<?=$s['midterm_max_attempts']??1?>"
              min="1" max="99" required
              class="w-24 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-indigo-400 outline-none">
            <span class="text-xs text-slate-400">ครั้ง</span>
          </div>
        </div>
        <div class="border-t border-indigo-200 pt-3">
          <div class="font-bold text-indigo-700 text-xs mb-2"><i class="fas fa-clock mr-1"></i> ช่วงเวลาเปิดสอบ <span class="font-normal text-indigo-400">(ไม่กำหนด = เปิดตลอด)</span></div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-black text-slate-500 mb-1">เปิดสอบ</label>
              <input type="datetime-local" name="midterm_open_at"
                value="<?=!empty($s['midterm_open_at']) ? date('Y-m-d\TH:i', strtotime($s['midterm_open_at'])) : ''?>"
                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-400 outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-slate-500 mb-1">ปิดสอบ</label>
              <input type="datetime-local" name="midterm_close_at"
                value="<?=!empty($s['midterm_close_at']) ? date('Y-m-d\TH:i', strtotime($s['midterm_close_at'])) : ''?>"
                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-400 outline-none">
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-xl p-4 bg-amber-50 border border-amber-100 space-y-3">
        <div class="font-bold text-amber-800 text-xs mb-1"><i class="fas fa-flag-checkered mr-1"></i> สอบปลายภาค</div>
        <div>
          <label class="block text-xs font-black text-slate-500 mb-1">จำนวนข้อที่ผ่านขึ้นไป
            <span class="font-normal text-slate-400">(จาก <?=$final_total?> ข้อ)</span></label>
          <div class="flex items-center gap-3">
            <input type="number" name="final_pass_score" value="<?=$s['final_pass_score']??6?>"
              min="1" max="<?=$final_total?:100?>" required
              class="w-24 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-amber-400 outline-none">
            <span class="text-xs text-slate-400">ข้อขึ้นไป</span>
          </div>
        </div>
        <div>
          <label class="block text-xs font-black text-slate-500 mb-1">สอบได้กี่ครั้ง</label>
          <div class="flex items-center gap-3">
            <input type="number" name="final_max_attempts" value="<?=$s['final_max_attempts']??1?>"
              min="1" max="99" required
              class="w-24 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-amber-400 outline-none">
            <span class="text-xs text-slate-400">ครั้ง</span>
          </div>
        </div>
        <div class="border-t border-amber-200 pt-3">
          <div class="font-bold text-amber-700 text-xs mb-2"><i class="fas fa-clock mr-1"></i> ช่วงเวลาเปิดสอบ <span class="font-normal text-amber-500">(ไม่กำหนด = เปิดตลอด)</span></div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-black text-slate-500 mb-1">เปิดสอบ</label>
              <input type="datetime-local" name="final_open_at"
                value="<?=!empty($s['final_open_at']) ? date('Y-m-d\TH:i', strtotime($s['final_open_at'])) : ''?>"
                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-amber-400 outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-slate-500 mb-1">ปิดสอบ</label>
              <input type="datetime-local" name="final_close_at"
                value="<?=!empty($s['final_close_at']) ? date('Y-m-d\TH:i', strtotime($s['final_close_at'])) : ''?>"
                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-amber-400 outline-none">
            </div>
          </div>
        </div>
      </div>

      <p class="text-xs text-slate-400"><i class="fas fa-info-circle mr-1"></i>สอบกลางภาค/ปลายภาคเปิดตามช่วงเวลาที่กำหนดเท่านั้น ไม่ผูกกับความคืบหน้ารายหน่วยของนักเรียน</p>

      <button type="submit" class="w-full py-3 bg-violet-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all flex items-center justify-center gap-2">
        <i class="fas fa-save"></i> บันทึกการตั้งค่า
      </button>
    </form>
  </div>

  <div class="space-y-4">
    <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5" style="border-left:4px solid #4F46E5">
      <div class="text-xs text-slate-400 mb-1">การตั้งค่าปัจจุบัน — กลางภาค</div>
      <div class="text-3xl font-black text-indigo-600"><?=$s['midterm_pass_score']??'—'?><span class="text-base font-normal text-slate-400"> / <?=$midterm_total?> ข้อ</span></div>
      <div class="text-xs text-slate-400 mt-1">สอบได้ <strong><?=$s['midterm_max_attempts']??1?></strong> ครั้ง</div>
      <?php
        $now = time();
        $mo = !empty($s['midterm_open_at'])  ? strtotime($s['midterm_open_at'])  : null;
        $mc = !empty($s['midterm_close_at']) ? strtotime($s['midterm_close_at']) : null;
        if ($mo || $mc):
          $m_is_open = (!$mo || $now >= $mo) && (!$mc || $now <= $mc);
      ?>
      <div class="mt-2 flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full <?=$m_is_open?'bg-emerald-500':'bg-slate-300'?>"></span>
        <span class="text-xs font-bold <?=$m_is_open?'text-emerald-600':'text-slate-400'?>"><?=$m_is_open?'เปิดสอบอยู่':'ยังไม่ถึงเวลา / ปิดแล้ว'?></span>
      </div>
      <?php if ($mo): ?><div class="text-xs text-slate-400 mt-1">เปิด: <?=date('d/m/Y H:i', $mo)?></div><?php endif; ?>
      <?php if ($mc): ?><div class="text-xs text-slate-400">ปิด: <?=date('d/m/Y H:i', $mc)?></div><?php endif; ?>
      <?php else: ?>
      <div class="text-xs text-slate-400 mt-1">เปิดตลอดเวลา</div>
      <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5" style="border-left:4px solid #D97706">
      <div class="text-xs text-slate-400 mb-1">การตั้งค่าปัจจุบัน — ปลายภาค</div>
      <div class="text-3xl font-black text-amber-600"><?=$s['final_pass_score']??'—'?><span class="text-base font-normal text-slate-400"> / <?=$final_total?> ข้อ</span></div>
      <div class="text-xs text-slate-400 mt-1">สอบได้ <strong><?=$s['final_max_attempts']??1?></strong> ครั้ง</div>
      <?php
        $fo = !empty($s['final_open_at'])  ? strtotime($s['final_open_at'])  : null;
        $fc = !empty($s['final_close_at']) ? strtotime($s['final_close_at']) : null;
        if ($fo || $fc):
          $f_is_open = (!$fo || $now >= $fo) && (!$fc || $now <= $fc);
      ?>
      <div class="mt-2 flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full <?=$f_is_open?'bg-emerald-500':'bg-slate-300'?>"></span>
        <span class="text-xs font-bold <?=$f_is_open?'text-emerald-600':'text-slate-400'?>"><?=$f_is_open?'เปิดสอบอยู่':'ยังไม่ถึงเวลา / ปิดแล้ว'?></span>
      </div>
      <?php if ($fo): ?><div class="text-xs text-slate-400 mt-1">เปิด: <?=date('d/m/Y H:i', $fo)?></div><?php endif; ?>
      <?php if ($fc): ?><div class="text-xs text-slate-400">ปิด: <?=date('d/m/Y H:i', $fc)?></div><?php endif; ?>
      <?php else: ?>
      <div class="text-xs text-slate-400 mt-1">เปิดตลอดเวลา</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
