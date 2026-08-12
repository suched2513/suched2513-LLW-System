<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
require_once __DIR__ . '/_helpers.php';

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

$sel_subject = (int)($_GET['subject_id'] ?? 0);
$sel_class   = trim($_GET['class'] ?? '');
$sel_unit    = (int)($_GET['unit_id'] ?? 0);

if ($is_admin) {
    $subjects = $pdo->query("SELECT * FROM lms_subjects ORDER BY subject_name")->fetchAll();
} else {
    $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE teacher_id=? OR teacher_id IS NULL ORDER BY subject_name");
    $st->execute([$teacher_id]); $subjects = $st->fetchAll();
}

$subject = $sel_subject ? lms_get_owned_subject($pdo, $sel_subject, $is_admin, $teacher_id) : null;
$classes = []; $units = []; $unit = null;
if ($subject) {
    $cs = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom");
    $cs->execute([$sel_subject]); $classes = $cs->fetchAll(PDO::FETCH_COLUMN);
    $us = $pdo->prepare("SELECT * FROM lms_units WHERE subject_id=? AND deleted_at IS NULL ORDER BY order_no");
    $us->execute([$sel_subject]); $units = $us->fetchAll();
    if ($sel_unit) {
        foreach ($units as $u) { if ($u['id'] === $sel_unit) { $unit = $u; break; } }
    }
}

$students = [];
if ($subject && $sel_class && $unit) {
    $q = $pdo->prepare("
        SELECT id, student_id, name AS student_name
        FROM att_students
        WHERE classroom=? AND status='active'
          AND student_id REGEXP '^[0-9]+$'
          AND student_id NOT IN (SELECT subject_code FROM att_subjects)
        ORDER BY student_id
    ");
    $q->execute([$sel_class]); $students = $q->fetchAll();
}

$rows = []; $item_analysis = ['pre' => [], 'post' => []];
$kpi = ['total'=>0,'not_started'=>0,'in_progress'=>0,'passed'=>0,'pre_sum'=>0,'pre_cnt'=>0,'post_sum'=>0,'post_cnt'=>0];
$pending_exercise = 0; $pending_essay = 0;

if ($subject && $sel_class && $unit && !empty($students)) {
    $kpi['total'] = count($students);
    foreach ($students as $s) {
        $uid = $s['id'];
        $pre  = $pdo->prepare("SELECT score,total FROM lms_student_pre_exam  WHERE student_uid=? AND unit_id=? ORDER BY taken_at DESC LIMIT 1"); $pre->execute([$uid,$sel_unit]); $pre=$pre->fetch();
        $post = $pdo->prepare("SELECT score,total,passed FROM lms_student_post_exam WHERE student_uid=? AND unit_id=? ORDER BY taken_at DESC LIMIT 1"); $post->execute([$uid,$sel_unit]); $post=$post->fetch();

        if (!$pre && !$post) { $kpi['not_started']++; }
        elseif ($post && $post['passed']) { $kpi['passed']++; }
        else { $kpi['in_progress']++; }

        if ($pre && $pre['total'] > 0)  { $kpi['pre_sum']  += $pre['score'];  $kpi['pre_cnt']++; }
        if ($post && $post['total'] > 0) { $kpi['post_sum'] += $post['score']; $kpi['post_cnt']++; }

        $rows[] = [
            'student_id' => $s['student_id'], 'name' => $s['student_name'],
            'pre_score' => $pre['score'] ?? null, 'pre_total' => $pre['total'] ?? null,
            'post_score' => $post['score'] ?? null, 'post_total' => $post['total'] ?? null,
            'post_passed' => $post['passed'] ?? 0,
            'status' => (!$pre && !$post) ? 'not_started' : (($post && $post['passed']) ? 'passed' : 'in_progress'),
        ];
    }

    $ec = $pdo->prepare("
        SELECT COUNT(*) FROM lms_student_exercises se
        WHERE se.unit_id=? AND se.reviewed_at IS NULL
          AND se.student_uid IN (SELECT id FROM att_students WHERE classroom=?)
    ");
    $ec->execute([$sel_unit, $sel_class]); $pending_exercise = (int)$ec->fetchColumn();

    $pending_essay = 0;
    foreach (['pre' => 'lms_pre_questions', 'post' => 'lms_post_questions'] as $etype => $qtbl) {
        $es = $pdo->prepare("
            SELECT COUNT(*) FROM lms_student_exam_answers a
            JOIN `{$qtbl}` q ON q.id = a.question_id
            WHERE a.exam_type=? AND q.unit_id=? AND a.reviewed_at IS NULL
              AND a.student_uid IN (SELECT id FROM att_students WHERE classroom=?)
        ");
        $es->execute([$etype, $sel_unit, $sel_class]); $pending_essay += (int)$es->fetchColumn();
    }

    // Item analysis: per question, correct rate across this classroom's students
    foreach (['pre','post'] as $etype) {
        $qtbl = $etype === 'pre' ? 'lms_pre_questions' : 'lms_post_questions';
        $ir = $pdo->prepare("
            SELECT q.id, q.question_text, q.question_type,
                   COUNT(r.id) AS attempts, SUM(r.is_correct) AS correct_cnt
            FROM `{$qtbl}` q
            LEFT JOIN lms_exam_item_results r ON r.question_id = q.id AND r.exam_type = ?
                AND r.student_uid IN (SELECT id FROM att_students WHERE classroom = ?)
            WHERE q.unit_id = ?
            GROUP BY q.id
            ORDER BY (SUM(r.is_correct) / NULLIF(COUNT(r.id),0)) ASC, attempts DESC
        ");
        $ir->execute([$etype, $sel_class, $sel_unit]);
        $item_analysis[$etype] = $ir->fetchAll();
    }
}

$question_types_labels = array_map(fn($t) => $t['label'], lms_question_types());

// ── CSV export ──────────────────────────────────────────────────
if (isset($_GET['export']) && $subject && $sel_class && $unit) {
    $type = $_GET['export'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lms_report_'.$type.'_'.$sel_unit.'_'.date('YmdHis').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    if ($type === 'scores') {
        fputcsv($out, ['เลขประจำตัว','ชื่อ-สกุล','คะแนนก่อนเรียน','เต็ม','คะแนนหลังเรียน','เต็ม','สถานะ']);
        foreach ($rows as $r) {
            $statusLabel = ['not_started'=>'ยังไม่เริ่ม','in_progress'=>'กำลังเรียน/ไม่ผ่าน','passed'=>'ผ่านแล้ว'][$r['status']];
            fputcsv($out, [$r['student_id'],$r['name'],$r['pre_score']??'-',$r['pre_total']??'-',$r['post_score']??'-',$r['post_total']??'-',$statusLabel]);
        }
    } elseif (in_array($type, ['itemanalysis_pre','itemanalysis_post'], true)) {
        $etype = $type === 'itemanalysis_pre' ? 'pre' : 'post';
        fputcsv($out, ['คำถาม','ประเภท','จำนวนครั้งที่ตอบ','ตอบถูก','ร้อยละที่ตอบถูก']);
        foreach ($item_analysis[$etype] as $it) {
            $rate = $it['attempts'] > 0 ? round($it['correct_cnt'] / $it['attempts'] * 100, 1) : null;
            fputcsv($out, [$it['question_text'], $question_types_labels[$it['question_type']] ?? $it['question_type'], $it['attempts'], $it['correct_cnt'], $rate !== null ? $rate.'%' : '—']);
        }
    }
    fclose($out); exit();
}

$pageTitle    = 'รายงานผล';
$pageSubtitle = 'รายงานผลการเรียนและวิเคราะห์ข้อสอบ';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex items-center gap-3 mb-6">
  <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-indigo-500 to-blue-600">
    <i class="fas fa-chart-pie text-white"></i>
  </div>
  <div>
    <h2 class="text-lg font-black text-slate-800">รายงานผล</h2>
    <p class="text-xs text-slate-400">คะแนนก่อน–หลังเรียน และวิเคราะห์ข้อสอบ</p>
  </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 mb-5">
  <form method="GET" class="flex gap-3 items-center flex-wrap">
    <label class="text-xs font-black text-slate-500">วิชา:</label>
    <select name="subject_id" onchange="this.form.submit()"
      class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
      <option value="">-- เลือกวิชา --</option>
      <?php foreach ($subjects as $subj): ?>
      <option value="<?=$subj['id']?>" <?=$sel_subject===$subj['id']?'selected':''?>><?=htmlspecialchars($subj['subject_name'],ENT_QUOTES,'UTF-8')?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($subject && !empty($classes)): ?>
    <label class="text-xs font-black text-slate-500">ห้อง:</label>
    <select name="class" onchange="this.form.submit()"
      class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
      <option value="">-- เลือกห้อง --</option>
      <?php foreach ($classes as $cl): ?>
      <option value="<?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>" <?=$sel_class===$cl?'selected':''?>><?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <?php if ($subject && !empty($units)): ?>
    <label class="text-xs font-black text-slate-500">หน่วย:</label>
    <select name="unit_id" onchange="this.form.submit()"
      class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
      <option value="">-- เลือกหน่วย --</option>
      <?php foreach ($units as $u): ?>
      <option value="<?=$u['id']?>" <?=$sel_unit===$u['id']?'selected':''?>><?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
  </form>
</div>

<?php if (!$subject): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <i class="fas fa-hand-point-up text-5xl mb-3 block opacity-30"></i><p>โปรดเลือกวิชาก่อน</p>
</div>
<?php elseif (!$sel_class): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <i class="fas fa-users text-5xl mb-3 block opacity-30"></i>
  <p>โปรดเลือกห้องเรียน<?=empty($classes)?' (วิชานี้ยังไม่ได้กำหนดห้อง)':''?></p>
</div>
<?php elseif (!$unit): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <i class="fas fa-layer-group text-5xl mb-3 block opacity-30"></i>
  <p>โปรดเลือกหน่วย<?=empty($units)?' (วิชานี้ยังไม่มีหน่วย)':''?></p>
</div>
<?php elseif (empty($students)): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center text-slate-300">
  <p>ไม่มีนักเรียนในห้องนี้</p>
</div>
<?php else: ?>

<!-- KPI -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">นักเรียนทั้งหมด</p>
    <p class="text-3xl font-black text-slate-700"><?=$kpi['total']?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">ยังไม่เริ่ม</p>
    <p class="text-3xl font-black text-rose-500"><?=$kpi['not_started']?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">กำลังเรียน / ไม่ผ่าน</p>
    <p class="text-3xl font-black text-amber-500"><?=$kpi['in_progress']?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">ผ่านแล้ว</p>
    <p class="text-3xl font-black text-emerald-600"><?=$kpi['passed']?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">คะแนนเฉลี่ยก่อนเรียน</p>
    <p class="text-3xl font-black text-blue-600"><?=$kpi['pre_cnt']?round($kpi['pre_sum']/$kpi['pre_cnt'],1):'—'?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">คะแนนเฉลี่ยหลังเรียน</p>
    <p class="text-3xl font-black text-rose-500"><?=$kpi['post_cnt']?round($kpi['post_sum']/$kpi['post_cnt'],1):'—'?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">รอตรวจใบงาน</p>
    <p class="text-3xl font-black text-orange-500"><?=$pending_exercise?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <p class="text-xs text-slate-400 font-bold mb-1">รอตรวจอัตนัย</p>
    <p class="text-3xl font-black text-violet-500"><?=$pending_essay?></p>
  </div>
</div>

<!-- Score comparison -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden mb-5">
  <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
    <h3 class="font-black text-slate-700 text-sm"><i class="fas fa-chart-line text-blue-500 mr-1"></i>เปรียบเทียบคะแนนก่อน–หลังเรียน</h3>
    <a href="reports.php?subject_id=<?=$sel_subject?>&class=<?=urlencode($sel_class)?>&unit_id=<?=$sel_unit?>&export=scores" class="px-3 py-1.5 bg-emerald-500 text-white text-xs font-bold rounded-lg hover:bg-emerald-600 transition-all">
      <i class="fas fa-file-csv mr-1"></i>ส่งออก CSV
    </a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-xs">
      <thead class="bg-slate-50 text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 text-left">ชื่อ–สกุล</th>
          <th class="px-4 py-3 text-center">ก่อนเรียน</th>
          <th class="px-4 py-3 text-center">หลังเรียน</th>
          <th class="px-4 py-3 text-center">สถานะ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($rows as $r): ?>
        <tr class="hover:bg-slate-50/50 transition-colors">
          <td class="px-4 py-3">
            <div class="font-bold text-slate-700"><?=htmlspecialchars($r['name'],ENT_QUOTES,'UTF-8')?></div>
            <div class="text-[10px] text-slate-400"><?=htmlspecialchars($r['student_id'],ENT_QUOTES,'UTF-8')?></div>
          </td>
          <td class="px-4 py-3 text-center"><?=$r['pre_score']!==null?$r['pre_score'].'/'.$r['pre_total']:'—'?></td>
          <td class="px-4 py-3 text-center"><?=$r['post_score']!==null?$r['post_score'].'/'.$r['post_total']:'—'?></td>
          <td class="px-4 py-3 text-center">
            <?php if ($r['status']==='passed'): ?><span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 font-bold rounded-full">ผ่านแล้ว</span>
            <?php elseif ($r['status']==='not_started'): ?><span class="px-2 py-0.5 bg-rose-50 text-rose-500 font-bold rounded-full">ยังไม่เริ่ม</span>
            <?php else: ?><span class="px-2 py-0.5 bg-amber-50 text-amber-600 font-bold rounded-full">กำลังเรียน/ไม่ผ่าน</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Item analysis -->
<?php foreach (['pre'=>'ก่อนเรียน','post'=>'หลังเรียน'] as $etype => $elabel): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden mb-5">
  <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
    <h3 class="font-black text-slate-700 text-sm"><i class="fas fa-microscope text-rose-500 mr-1"></i>วิเคราะห์ข้อสอบ<?=$elabel?> <span class="text-slate-300 font-normal">(เรียงจากตอบผิดมากสุด)</span></h3>
    <a href="reports.php?subject_id=<?=$sel_subject?>&class=<?=urlencode($sel_class)?>&unit_id=<?=$sel_unit?>&export=itemanalysis_<?=$etype?>" class="px-3 py-1.5 bg-emerald-500 text-white text-xs font-bold rounded-lg hover:bg-emerald-600 transition-all">
      <i class="fas fa-file-csv mr-1"></i>ส่งออก CSV
    </a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-xs">
      <thead class="bg-slate-50 text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 text-left">คำถาม</th>
          <th class="px-4 py-3 text-center w-28">ประเภท</th>
          <th class="px-4 py-3 text-center w-24">ตอบแล้ว</th>
          <th class="px-4 py-3 text-center w-32">ร้อยละที่ถูก</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php if (empty($item_analysis[$etype])): ?>
        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-300">ยังไม่มีข้อสอบ</td></tr>
        <?php endif; ?>
        <?php foreach ($item_analysis[$etype] as $it):
          $rate = $it['attempts'] > 0 ? round($it['correct_cnt'] / $it['attempts'] * 100, 1) : null;
          $meta = $question_types_labels[$it['question_type']] ?? $it['question_type'];
        ?>
        <tr class="hover:bg-slate-50/50 transition-colors">
          <td class="px-4 py-3 text-slate-700"><?=htmlspecialchars(mb_substr($it['question_text'],0,80),ENT_QUOTES,'UTF-8')?></td>
          <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-slate-100 text-slate-500 font-bold rounded-full"><?=htmlspecialchars($meta,ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-4 py-3 text-center text-slate-400"><?=$it['attempts']?></td>
          <td class="px-4 py-3 text-center">
            <?php if ($rate === null): ?><span class="text-slate-300">—</span>
            <?php else: ?>
            <span class="px-2 py-0.5 font-black rounded-full <?=$rate < 50 ? 'bg-rose-50 text-rose-600' : ($rate < 80 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600')?>"><?=$rate?>%</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
