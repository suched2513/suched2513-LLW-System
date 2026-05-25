<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$sel_class  = trim($_GET['class'] ?? '');

if (!$is_admin) {
    $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE teacher_id=? ORDER BY subject_name");
    $st->execute([$teacher_id]); $subjects = $st->fetchAll();
} else {
    $subjects = $pdo->query("SELECT * FROM lms_subjects ORDER BY subject_name")->fetchAll();
}

$subject = null; $classrooms = []; $students = []; $exercises = []; $grade_grid = [];

if ($subject_id) {
    foreach ($subjects as $s) { if ((int)$s['id'] === $subject_id) { $subject = $s; break; } }
    if ($subject) {
        $cs = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
        $cs->execute([$subject_id]); $classrooms = $cs->fetchAll(PDO::FETCH_COLUMN);
        if (!$sel_class && !empty($classrooms)) $sel_class = $classrooms[0];

        if ($sel_class && in_array($sel_class, $classrooms, true)) {
            $sq = $pdo->prepare("
                SELECT id, student_id, name FROM att_students
                WHERE classroom=? AND status='active'
                  AND student_id REGEXP '^[0-9]+$'
                  AND student_id NOT IN (SELECT subject_code FROM att_subjects)
                ORDER BY student_id
            ");
            $sq->execute([$sel_class]); $students = $sq->fetchAll();

            $eq = $pdo->prepare("
                SELECT e.id, e.exercise_title, e.max_score, u.unit_name, u.order_no
                FROM lms_unit_exercises e
                JOIN lms_units u ON u.id = e.unit_id
                WHERE u.subject_id = ? ORDER BY u.order_no, e.id
            ");
            $eq->execute([$subject_id]); $exercises = $eq->fetchAll();

            if (!empty($students) && !empty($exercises)) {
                $sids = array_column($students, 'id');
                $eids = array_column($exercises, 'id');
                $sph  = implode(',', array_fill(0, count($sids), '?'));
                $eph  = implode(',', array_fill(0, count($eids), '?'));
                $sq2  = $pdo->prepare("
                    SELECT student_uid, exercise_id, grade, reviewed_at, submitted_at
                    FROM lms_student_exercises
                    WHERE student_uid IN ($sph) AND exercise_id IN ($eph) AND subject_id=?
                ");
                $sq2->execute(array_merge($sids, $eids, [$subject_id]));
                foreach ($sq2->fetchAll() as $row) {
                    $grade_grid[$row['student_uid']][$row['exercise_id']] = $row;
                }
            }
        }
    }
}

// CSV Export
if (isset($_GET['export']) && $subject && $sel_class && !empty($students)) {
    header('Content-Type: text/csv; charset=utf-8');
    $fname = 'grades_' . preg_replace('/\s+/', '_', $sel_class) . '_' . date('Ymd') . '.csv';
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $total_possible = array_sum(array_column($exercises, 'max_score'));
    $header = ['เลขประจำตัว', 'ชื่อ-สกุล'];
    foreach ($exercises as $ex) {
        $header[] = $ex['exercise_title'] . ($ex['max_score'] ? ' (/' . $ex['max_score'] . ')' : '');
    }
    $header[] = 'รวม' . ($total_possible ? ' (/' . $total_possible . ')' : '');
    if ($total_possible) $header[] = '%';
    fputcsv($out, $header);
    foreach ($students as $s) {
        $row = [$s['student_id'], $s['name']];
        $earned = 0;
        foreach ($exercises as $ex) {
            $sub = $grade_grid[$s['id']][$ex['id']] ?? null;
            if ($sub && $sub['reviewed_at'] !== null && $sub['grade'] !== null) {
                $row[] = (float)$sub['grade']; $earned += (float)$sub['grade'];
            } elseif ($sub) { $row[] = 'ส่งแล้ว(รอตรวจ)';
            } else           { $row[] = ''; }
        }
        $row[] = $earned;
        if ($total_possible) $row[] = round($earned / $total_possible * 100) . '%';
        fputcsv($out, $row);
    }
    fclose($out); exit;
}

$pageTitle    = 'สมุดคะแนน';
$pageSubtitle = 'คะแนนชิ้นงานรายนักเรียน';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex flex-wrap items-center gap-3 mb-6">
  <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-violet-500 to-purple-600">
    <i class="bi bi-table text-white text-lg"></i>
  </div>
  <div>
    <h2 class="text-lg font-black text-slate-800">สมุดคะแนน</h2>
    <p class="text-xs text-slate-400">คะแนนชิ้นงานรายนักเรียน · เลือกวิชาและห้องเรียน</p>
  </div>
</div>

<!-- Controls -->
<div class="flex flex-wrap items-center gap-3 mb-6">
  <form method="GET" class="flex gap-2 items-center">
    <select name="subject_id" onchange="this.form.submit()"
      class="bg-white border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none shadow-sm">
      <option value="">— เลือกวิชา —</option>
      <?php foreach ($subjects as $s): ?>
      <option value="<?=$s['id']?>" <?=(int)$s['id']===$subject_id?'selected':''?>>
        <?=htmlspecialchars($s['subject_name'],ENT_QUOTES,'UTF-8')?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php if ($sel_class): ?><input type="hidden" name="class" value="<?=htmlspecialchars($sel_class,ENT_QUOTES,'UTF-8')?>"><?php endif; ?>
  </form>

  <?php if ($subject && !empty($classrooms)): ?>
  <div class="flex gap-1.5 flex-wrap">
    <?php foreach ($classrooms as $cl): ?>
    <a href="grade_book.php?subject_id=<?=$subject_id?>&class=<?=urlencode($cl)?>"
       class="px-4 py-2 rounded-2xl text-sm font-bold transition-all shadow-sm
              <?=$sel_class===$cl?'bg-violet-600 text-white shadow-violet-200':'bg-white border border-slate-200 text-slate-600 hover:bg-violet-50'?>">
      <?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($subject && $sel_class && !empty($students) && !empty($exercises)): ?>
  <a href="grade_book.php?subject_id=<?=$subject_id?>&class=<?=urlencode($sel_class)?>&export=1"
     class="ml-auto px-4 py-2 bg-emerald-600 text-white text-sm font-bold rounded-2xl shadow-lg shadow-emerald-200/60 hover:bg-emerald-700 transition-all flex items-center gap-2">
    <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
  </a>
  <?php endif; ?>
</div>

<?php if (!$subject_id): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
  <i class="bi bi-table text-5xl block mb-3 opacity-30"></i>
  <p class="font-bold">เลือกวิชาก่อน</p>
</div>

<?php elseif (empty($exercises)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
  <i class="bi bi-inbox text-5xl block mb-3 opacity-30"></i>
  <p class="font-bold text-sm">ยังไม่มีชิ้นงานในวิชานี้</p>
</div>

<?php elseif (empty($students)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
  <i class="bi bi-people text-5xl block mb-3 opacity-30"></i>
  <p class="font-bold text-sm">ไม่มีนักเรียนในห้อง <?=htmlspecialchars($sel_class,ENT_QUOTES,'UTF-8')?></p>
</div>

<?php else: ?>
<?php $total_max = array_sum(array_column($exercises, 'max_score')); $pending_cnt = 0; ?>

<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">

  <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h3 class="font-black text-slate-800 text-sm">
        <?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?> &middot; ห้อง <?=htmlspecialchars($sel_class,ENT_QUOTES,'UTF-8')?>
      </h3>
      <p class="text-xs text-slate-400 mt-0.5">
        <?=count($students)?> คน &middot; <?=count($exercises)?> ชิ้นงาน<?=$total_max?' &middot; เต็ม '.$total_max.' คะแนน':''?>
      </p>
    </div>
    <div class="flex gap-2 text-[10px] flex-wrap">
      <span class="px-2 py-1 bg-emerald-50 text-emerald-700 font-bold rounded-lg border border-emerald-100">
        <i class="bi bi-check-circle-fill mr-1"></i>ตัวเลข = ตรวจแล้ว
      </span>
      <span class="px-2 py-1 bg-amber-50 text-amber-700 font-bold rounded-lg border border-amber-100">
        <i class="bi bi-upload mr-1"></i>ส่งแล้ว รอตรวจ
      </span>
      <span class="px-2 py-1 bg-slate-50 text-slate-500 font-bold rounded-lg border border-slate-100">— ยังไม่ส่ง</span>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm border-collapse">
      <thead>
        <tr class="bg-slate-50 text-xs font-black text-slate-400 uppercase tracking-wider">
          <th class="px-4 py-3 text-left sticky left-0 bg-slate-50 z-10 border-r border-slate-100 min-w-[160px]">ชื่อ-สกุล</th>
          <?php $prev_unit = null; foreach ($exercises as $ex): ?>
          <th class="px-2 py-3 text-center min-w-[76px] border-l border-slate-100">
            <?php if ($ex['unit_name'] !== $prev_unit): ?>
            <div class="text-violet-400 text-[9px] font-black truncate max-w-[72px] mb-0.5"
                 title="<?=htmlspecialchars($ex['unit_name'],ENT_QUOTES,'UTF-8')?>">
              <?=htmlspecialchars(mb_substr($ex['unit_name'],0,10),ENT_QUOTES,'UTF-8')?>
            </div>
            <?php $prev_unit = $ex['unit_name']; else: ?><div class="mb-3.5"></div><?php endif; ?>
            <div class="text-slate-500 font-black truncate max-w-[72px]"
                 title="<?=htmlspecialchars($ex['exercise_title'],ENT_QUOTES,'UTF-8')?>">
              <?=htmlspecialchars(mb_substr($ex['exercise_title'],0,7),ENT_QUOTES,'UTF-8')?>
            </div>
            <?php if ($ex['max_score']): ?>
            <div class="text-[9px] text-slate-300 font-normal">/<?=$ex['max_score']?></div>
            <?php endif; ?>
          </th>
          <?php endforeach; ?>
          <?php if ($total_max > 0): ?>
          <th class="px-4 py-3 text-center text-violet-700 border-l-2 border-violet-200 bg-violet-50/80 min-w-[80px]">
            รวม<br><span class="text-[9px] font-normal text-slate-400">/<?=$total_max?></span>
          </th>
          <th class="px-3 py-3 text-center text-violet-700 bg-violet-50/80 min-w-[64px]">%</th>
          <?php else: ?>
          <th class="px-4 py-3 text-center text-slate-600 border-l-2 border-slate-200">ส่งแล้ว</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($students as $i => $s):
          $earned = 0; $graded = 0; $submitted = 0;
          foreach ($exercises as $ex) {
            $sub = $grade_grid[$s['id']][$ex['id']] ?? null;
            if ($sub) {
              $submitted++;
              if ($sub['reviewed_at'] !== null && $sub['grade'] !== null) {
                $earned += (float)$sub['grade']; $graded++;
              } else { $pending_cnt++; }
            }
          }
          $pct = $total_max > 0 ? min(100, round($earned / $total_max * 100)) : 0;
          $row_bg = $i % 2 === 0 ? 'bg-white' : 'bg-slate-50/40';
        ?>
        <tr class="<?=$row_bg?> hover:bg-violet-50/40 transition-colors">
          <td class="px-4 py-3 sticky left-0 <?=$row_bg?> border-r border-slate-100 z-10">
            <div class="font-bold text-slate-800 text-xs leading-tight"><?=htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8')?></div>
            <div class="text-[10px] text-slate-400"><?=htmlspecialchars($s['student_id'],ENT_QUOTES,'UTF-8')?></div>
          </td>
          <?php foreach ($exercises as $ex): $sub = $grade_grid[$s['id']][$ex['id']] ?? null; ?>
          <td class="px-2 py-3 text-center border-l border-slate-100">
            <?php if ($sub && $sub['reviewed_at'] !== null && $sub['grade'] !== null): ?>
            <span class="font-black text-sm text-emerald-600"><?=rtrim(rtrim(number_format((float)$sub['grade'],1),'0'),'.')?></span>
            <?php elseif ($sub): ?>
            <span class="inline-flex items-center justify-center w-6 h-6 bg-amber-100 rounded-full" title="ส่งแล้ว รอตรวจ">
              <i class="bi bi-upload text-amber-500 text-[10px]"></i>
            </span>
            <?php else: ?>
            <span class="text-slate-200 text-xs select-none">—</span>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
          <?php if ($total_max > 0): ?>
          <td class="px-4 py-3 text-center border-l-2 border-violet-100 bg-violet-50/50">
            <?php if ($graded > 0): ?>
            <span class="font-black text-sm <?=$pct>=80?'text-emerald-600':($pct>=60?'text-amber-600':'text-rose-500')?>">
              <?=rtrim(rtrim(number_format($earned,1),'0'),'.')?>
            </span>
            <?php else: ?><span class="text-slate-300 text-xs">—</span><?php endif; ?>
          </td>
          <td class="px-3 py-3 bg-violet-50/50">
            <?php if ($graded > 0): ?>
            <div class="flex items-center gap-1.5">
              <div class="flex-1 bg-slate-200 rounded-full h-1.5 min-w-[28px]">
                <div class="h-1.5 rounded-full transition-all
                  <?=$pct>=80?'bg-emerald-500':($pct>=60?'bg-amber-500':'bg-rose-500')?>"
                  style="width:<?=$pct?>%"></div>
              </div>
              <span class="text-[10px] font-black text-slate-600 w-7 text-right"><?=$pct?>%</span>
            </div>
            <?php else: ?><span class="text-slate-300 text-[10px]">—</span><?php endif; ?>
          </td>
          <?php else: ?>
          <td class="px-4 py-3 text-center text-xs font-bold text-slate-600 border-l-2 border-slate-200">
            <?=$submitted?>/<?=count($exercises)?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($total_max > 0): ?>
      <tfoot>
        <tr class="bg-slate-100 border-t-2 border-slate-200 text-xs font-black text-slate-500">
          <td class="px-4 py-3 sticky left-0 bg-slate-100 border-r border-slate-200 z-10">คะแนนเต็ม</td>
          <?php foreach ($exercises as $ex): ?>
          <td class="px-2 py-3 text-center border-l border-slate-200 text-slate-600"><?=$ex['max_score'] ?: '—'?></td>
          <?php endforeach; ?>
          <td class="px-4 py-3 text-center border-l-2 border-violet-200 bg-violet-100 text-violet-700 font-black"><?=$total_max?></td>
          <td class="bg-violet-100"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

  <?php if ($pending_cnt > 0): ?>
  <div class="px-5 py-3 border-t border-amber-100 bg-amber-50/80 flex items-center gap-2">
    <i class="bi bi-exclamation-circle-fill text-amber-500"></i>
    <p class="text-xs font-bold text-amber-700">มี <?=$pending_cnt?> ชิ้นงานที่ยังรอตรวจ
      <a href="grade_exercises.php?subject_id=<?=$subject_id?>&class=<?=urlencode($sel_class)?>"
         class="underline hover:text-amber-900 ml-1">ไปตรวจงาน →</a>
    </p>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
