<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo  = getPdo();
$year = (int)($_GET['year']     ?? 2569);
$sem  = (int)($_GET['semester'] ?? 1);
if (!in_array($sem, [1, 2])) $sem = 1;

$health_ok = (bool)$pdo->query("SHOW TABLES LIKE 'health_records'")->fetch();

$school_stats = ['total_students'=>0,'has_record'=>0,'normal'=>0,'thin'=>0,'very_thin'=>0,'fat'=>0,'obese'=>0,'short'=>0,'very_short'=>0,'avg_bmi'=>null];
$cls_summary  = [];
$at_risk      = [];
$gender_stats = ['male'=>['normal'=>0,'thin'=>0,'fat'=>0,'short'=>0],'female'=>['normal'=>0,'thin'=>0,'fat'=>0,'short'=>0]];
$trend        = [];

if ($health_ok) {
    // School-wide KPI from latest record per student
    $kpi = $pdo->prepare("
        SELECT
            COUNT(DISTINCT hr.student_id)                                       AS has_record,
            SUM(hr.bfa_status = 'สมส่วน')                                       AS normal,
            SUM(hr.bfa_status = 'ผอม')                                          AS thin,
            SUM(hr.bfa_status = 'ผอมมาก')                                       AS very_thin,
            SUM(hr.bfa_status = 'น้ำหนักเกิน')                                  AS fat,
            SUM(hr.bfa_status = 'อ้วน')                                         AS obese,
            SUM(hr.hfa_status = 'เตี้ย')                                        AS short_st,
            SUM(hr.hfa_status = 'เตี้ยมาก')                                     AS very_short,
            ROUND(AVG(hr.bmi), 1)                                               AS avg_bmi
        FROM health_records hr
        JOIN (
            SELECT student_id, MAX(record_date) latest
            FROM health_records WHERE academic_year=? AND semester=?
            GROUP BY student_id
        ) lr ON lr.student_id=hr.student_id AND lr.latest=hr.record_date
        WHERE hr.academic_year=? AND hr.semester=?
    ");
    $kpi->execute([$year, $sem, $year, $sem]);
    $k = $kpi->fetch();

    // Total active students
    $school_stats['total_students'] = (int)$pdo->query("
        SELECT COUNT(*) FROM att_students
        WHERE status='active' AND student_id REGEXP '^[0-9]+$'
          AND student_id NOT IN (SELECT subject_code FROM att_subjects)
    ")->fetchColumn();
    $school_stats['has_record']  = (int)($k['has_record']  ?? 0);
    $school_stats['normal']      = (int)($k['normal']      ?? 0);
    $school_stats['thin']        = (int)($k['thin']        ?? 0);
    $school_stats['very_thin']   = (int)($k['very_thin']   ?? 0);
    $school_stats['fat']         = (int)($k['fat']         ?? 0);
    $school_stats['obese']       = (int)($k['obese']       ?? 0);
    $school_stats['short']       = (int)($k['short_st']    ?? 0);
    $school_stats['very_short']  = (int)($k['very_short']  ?? 0);
    $school_stats['avg_bmi']     = $k['avg_bmi'] ?? null;

    // Per-classroom breakdown
    $cr = $pdo->prepare("
        SELECT s.classroom,
               COUNT(DISTINCT s.id)                                             AS total,
               COUNT(DISTINCT hr.student_id)                                    AS has_record,
               SUM(hr.bfa_status = 'สมส่วน')                                   AS normal,
               SUM(hr.bfa_status IN ('ผอม','ผอมมาก'))                          AS thin,
               SUM(hr.bfa_status IN ('น้ำหนักเกิน','อ้วน'))                     AS fat,
               SUM(hr.hfa_status IN ('เตี้ย','เตี้ยมาก'))                       AS short_st,
               ROUND(AVG(hr.bmi), 1)                                            AS avg_bmi,
               ROUND(AVG(hr.weight_kg), 1)                                      AS avg_weight,
               ROUND(AVG(hr.height_cm), 1)                                      AS avg_height
        FROM att_students s
        LEFT JOIN (
            SELECT hr1.*
            FROM health_records hr1
            JOIN (
                SELECT student_id, MAX(record_date) latest
                FROM health_records WHERE academic_year=? AND semester=?
                GROUP BY student_id
            ) lr ON lr.student_id=hr1.student_id AND lr.latest=hr1.record_date
            WHERE hr1.academic_year=? AND hr1.semester=?
        ) hr ON hr.student_id=s.id
        WHERE s.status='active' AND s.student_id REGEXP '^[0-9]+$'
          AND s.student_id NOT IN (SELECT subject_code FROM att_subjects)
        GROUP BY s.classroom ORDER BY s.classroom
    ");
    $cr->execute([$year, $sem, $year, $sem]);
    $cls_summary = $cr->fetchAll();

    // At-risk students (ผอมมาก หรือ อ้วน หรือ เตี้ยมาก)
    $ar = $pdo->prepare("
        SELECT s.name, s.classroom, s.gender,
               hr.bmi, hr.weight_kg, hr.height_cm, hr.bfa_status, hr.hfa_status, hr.record_date,
               hr.student_id
        FROM health_records hr
        JOIN att_students s ON s.id=hr.student_id
        JOIN (
            SELECT student_id, MAX(record_date) latest
            FROM health_records WHERE academic_year=? AND semester=?
            GROUP BY student_id
        ) lr ON lr.student_id=hr.student_id AND lr.latest=hr.record_date
        WHERE hr.academic_year=? AND hr.semester=?
          AND (hr.bfa_status IN ('ผอมมาก','อ้วน') OR hr.hfa_status = 'เตี้ยมาก')
        ORDER BY s.classroom, hr.bfa_status, s.name
    ");
    $ar->execute([$year, $sem, $year, $sem]);
    $at_risk = $ar->fetchAll();

    // Gender breakdown
    $gr = $pdo->prepare("
        SELECT s.gender,
               SUM(hr.bfa_status = 'สมส่วน')                   AS normal,
               SUM(hr.bfa_status IN ('ผอม','ผอมมาก'))           AS thin,
               SUM(hr.bfa_status IN ('น้ำหนักเกิน','อ้วน'))      AS fat,
               SUM(hr.hfa_status IN ('เตี้ย','เตี้ยมาก'))        AS short_st
        FROM health_records hr
        JOIN att_students s ON s.id=hr.student_id
        JOIN (
            SELECT student_id, MAX(record_date) latest
            FROM health_records WHERE academic_year=? AND semester=?
            GROUP BY student_id
        ) lr ON lr.student_id=hr.student_id AND lr.latest=hr.record_date
        WHERE hr.academic_year=? AND hr.semester=?
        GROUP BY s.gender
    ");
    $gr->execute([$year, $sem, $year, $sem]);
    foreach ($gr->fetchAll() as $row) {
        $key = ($row['gender'] === 'หญิง') ? 'female' : 'male';
        $gender_stats[$key] = [
            'normal' => (int)$row['normal'],
            'thin'   => (int)$row['thin'],
            'fat'    => (int)$row['fat'],
            'short'  => (int)$row['short_st'],
        ];
    }

    // Multi-semester trend
    $trend = $pdo->query("
        SELECT academic_year, semester,
               COUNT(DISTINCT student_id) AS cnt,
               ROUND(AVG(bmi), 1) AS avg_bmi,
               ROUND(AVG(weight_kg), 1) AS avg_weight,
               ROUND(AVG(height_cm), 1) AS avg_height,
               SUM(bfa_status='สมส่วน') AS normal,
               SUM(bfa_status IN('ผอม','ผอมมาก')) AS thin,
               SUM(bfa_status IN('น้ำหนักเกิน','อ้วน')) AS fat
        FROM health_records
        GROUP BY academic_year, semester
        ORDER BY academic_year, semester
    ")->fetchAll();
}

// Export CSV
if (isset($_GET['export']) && $health_ok && !empty($cls_summary)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="health_school_' . $year . '_' . $sem . '.csv"');
    echo "\xEF\xBB\xBF";
    echo "ห้อง,นักเรียนทั้งหมด,มีข้อมูล,สมส่วน,ผอม/ผอมมาก,น้ำหนักเกิน/อ้วน,เตี้ย/เตี้ยมาก,BMI เฉลี่ย,น้ำหนักเฉลี่ย,ส่วนสูงเฉลี่ย\n";
    foreach ($cls_summary as $c) {
        echo implode(',', [$c['classroom'],$c['total'],$c['has_record'],$c['normal'],$c['thin'],$c['fat'],$c['short_st'],$c['avg_bmi'],$c['avg_weight'],$c['avg_height']]) . "\n";
    }
    exit;
}

$hr_total = max(1, $school_stats['has_record']);
$pct = fn($v) => round($v / $hr_total * 100, 1);

$pageTitle    = 'รายงานภาพรวมโรงเรียน';
$pageSubtitle = "ปีการศึกษา $year ภาคเรียนที่ $sem";
$activeSystem = 'health';
require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Header -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div class="flex items-center gap-3">
    <a href="/health/dashboard.php" class="w-9 h-9 bg-slate-100 rounded-xl flex items-center justify-center hover:bg-slate-200 transition-all">
      <i class="fas fa-arrow-left text-slate-600 text-sm"></i>
    </a>
    <div>
      <h2 class="text-lg font-black text-slate-800">รายงานภาพรวมโรงเรียน</h2>
      <p class="text-xs text-slate-400">สถิติโภชนาการและสุขภาวะนักเรียนทั้งโรงเรียน</p>
    </div>
  </div>
  <div class="flex gap-2 flex-wrap items-center">
    <form method="get" class="flex items-center gap-2">
      <select name="year" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:ring-2 focus:ring-emerald-400 outline-none">
        <?php foreach ([2569,2568,2567] as $y): ?>
        <option value="<?=$y?>" <?=$y===$year?'selected':''?>>ปี <?=$y?></option>
        <?php endforeach; ?>
      </select>
      <select name="semester" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-white focus:ring-2 focus:ring-emerald-400 outline-none">
        <option value="1" <?=$sem===1?'selected':''?>>ภาค 1</option>
        <option value="2" <?=$sem===2?'selected':''?>>ภาค 2</option>
      </select>
    </form>
    <?php if ($health_ok && !empty($cls_summary)): ?>
    <a href="?year=<?=$year?>&semester=<?=$sem?>&export=1"
       class="px-4 py-2 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-xl hover:bg-emerald-100 transition-all">
      <i class="fas fa-file-csv mr-1"></i>Export CSV
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$health_ok): ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center">
  <p class="text-amber-700 font-bold text-sm">ยังไม่ได้รัน Migration — รัน <code class="bg-amber-100 px-1 rounded">php database/migrate.php</code> ก่อน</p>
</div>
<?php else: ?>

<!-- KPI School-wide -->
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $coverage = $school_stats['total_students'] > 0
    ? round($school_stats['has_record'] / $school_stats['total_students'] * 100, 1) : 0;
  $skpi = [
    ['l'=>'นักเรียนที่มีข้อมูล', 'v'=>$school_stats['has_record'].'/'.$school_stats['total_students'], 'sub'=>"$coverage% ครอบคลุม", 'g'=>'from-slate-500 to-slate-700', 'sh'=>'shadow-slate-200/50'],
    ['l'=>'สมส่วน',              'v'=>$school_stats['normal'],     'sub'=>$pct($school_stats['normal']).'%',    'g'=>'from-emerald-500 to-teal-600',  'sh'=>'shadow-emerald-200/50'],
    ['l'=>'ผอม / ผอมมาก',       'v'=>$school_stats['thin']+$school_stats['very_thin'],   'sub'=>$pct($school_stats['thin']+$school_stats['very_thin']).'%', 'g'=>'from-amber-400 to-orange-500', 'sh'=>'shadow-amber-200/50'],
    ['l'=>'น้ำหนักเกิน / อ้วน',  'v'=>$school_stats['fat']+$school_stats['obese'],       'sub'=>$pct($school_stats['fat']+$school_stats['obese']).'%',      'g'=>'from-rose-500 to-pink-600',    'sh'=>'shadow-rose-200/50'],
  ];
  foreach ($skpi as $c): ?>
  <div class="bg-gradient-to-br <?=$c['g']?> rounded-2xl p-5 text-white shadow-xl <?=$c['sh']?>">
    <p class="text-xs font-bold opacity-80 mb-1"><?=$c['l']?></p>
    <p class="text-3xl font-black"><?=$c['v']?></p>
    <p class="text-xs opacity-70 mt-1"><?=$c['sub']?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts Row 1 -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

  <!-- Stacked bar by classroom -->
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 lg:col-span-2">
    <h3 class="font-black text-slate-700 text-sm mb-3 flex items-center gap-2">
      <i class="fas fa-chart-bar text-blue-500"></i> เปรียบเทียบภาวะโภชนาการแยกห้อง
    </h3>
    <?php if (empty($cls_summary)): ?>
    <div class="h-40 flex items-center justify-center text-slate-300 text-sm">ยังไม่มีข้อมูล</div>
    <?php else: ?>
    <canvas id="classBarChart" height="220"></canvas>
    <?php endif; ?>
  </div>

  <!-- Donut overall -->
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <h3 class="font-black text-slate-700 text-sm mb-3 flex items-center gap-2">
      <i class="fas fa-chart-pie text-emerald-500"></i> สัดส่วนภาพรวม
    </h3>
    <?php if ($school_stats['has_record'] === 0): ?>
    <div class="h-40 flex items-center justify-center text-slate-300 text-sm">ยังไม่มีข้อมูล</div>
    <?php else: ?>
    <canvas id="overallPie" height="220"></canvas>
    <?php endif; ?>
  </div>
</div>

<!-- Charts Row 2: Gender + Trend -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

  <!-- Gender comparison -->
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <h3 class="font-black text-slate-700 text-sm mb-3 flex items-center gap-2">
      <i class="fas fa-venus-mars text-violet-500"></i> เปรียบเทียบตามเพศ
    </h3>
    <canvas id="genderChart" height="220"></canvas>
  </div>

  <!-- Multi-semester trend -->
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5">
    <h3 class="font-black text-slate-700 text-sm mb-3 flex items-center gap-2">
      <i class="fas fa-chart-line text-teal-500"></i> แนวโน้ม BMI เฉลี่ย
    </h3>
    <?php if (empty($trend)): ?>
    <div class="h-40 flex items-center justify-center text-slate-300 text-sm">ยังไม่มีข้อมูลหลายภาคเรียน</div>
    <?php else: ?>
    <canvas id="trendChart" height="220"></canvas>
    <?php endif; ?>
  </div>
</div>

<!-- Classroom Summary Table -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden mb-5">
  <div class="px-5 py-4 border-b border-slate-100">
    <h3 class="font-black text-slate-700 text-sm">สรุปแยกห้องเรียน</h3>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 text-left">ห้อง</th>
          <th class="px-4 py-3 text-center">ทั้งหมด</th>
          <th class="px-4 py-3 text-center">มีข้อมูล</th>
          <th class="px-4 py-3 text-center">สมส่วน</th>
          <th class="px-4 py-3 text-center">ผอม/ผอมมาก</th>
          <th class="px-4 py-3 text-center">น้ำหนักเกิน/อ้วน</th>
          <th class="px-4 py-3 text-center">เตี้ย/เตี้ยมาก</th>
          <th class="px-4 py-3 text-center">BMI เฉลี่ย</th>
          <th class="px-4 py-3 text-center">น้ำหนักเฉลี่ย</th>
          <th class="px-4 py-3 text-center">ส่วนสูงเฉลี่ย</th>
          <th class="px-4 py-3 text-center">รายงาน</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php if (empty($cls_summary)): ?>
        <tr><td colspan="11" class="px-5 py-10 text-center text-slate-300">ยังไม่มีข้อมูล</td></tr>
        <?php endif; ?>
        <?php foreach ($cls_summary as $c):
          $hr = max(1,(int)$c['has_record']);
          $normal_pct = $c['has_record'] > 0 ? round((int)$c['normal'] / $hr * 100) : 0;
        ?>
        <tr class="hover:bg-slate-50/50 transition-colors">
          <td class="px-4 py-3 font-black text-slate-700 text-sm">
            <a href="/health/report_class.php?classroom=<?=urlencode($c['classroom'])?>&year=<?=$year?>&semester=<?=$sem?>" class="hover:text-emerald-600 transition-colors">
              <?=htmlspecialchars($c['classroom'],ENT_QUOTES,'UTF-8')?>
            </a>
          </td>
          <td class="px-4 py-3 text-center text-xs text-slate-500"><?=$c['total']?></td>
          <td class="px-4 py-3 text-center">
            <span class="text-xs font-bold <?=$c['has_record']>0?'text-blue-600':'text-slate-300'?>"><?=$c['has_record']?></span>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center gap-1.5 justify-center">
              <span class="text-xs font-bold text-emerald-600"><?=(int)$c['normal']?></span>
              <div class="w-12 bg-slate-100 rounded-full h-1.5 flex-shrink-0">
                <div class="h-1.5 rounded-full bg-emerald-500" style="width:<?=$normal_pct?>%"></div>
              </div>
            </div>
          </td>
          <td class="px-4 py-3 text-center"><span class="text-xs font-bold <?=(int)$c['thin']>0?'text-amber-600':'text-slate-300'?>"><?=(int)$c['thin']?></span></td>
          <td class="px-4 py-3 text-center"><span class="text-xs font-bold <?=(int)$c['fat']>0?'text-rose-500':'text-slate-300'?>"><?=(int)$c['fat']?></span></td>
          <td class="px-4 py-3 text-center"><span class="text-xs font-bold <?=(int)$c['short_st']>0?'text-violet-600':'text-slate-300'?>"><?=(int)$c['short_st']?></span></td>
          <td class="px-4 py-3 text-center text-xs font-bold text-slate-600"><?=$c['avg_bmi'] ?? '—'?></td>
          <td class="px-4 py-3 text-center text-xs text-slate-500"><?=$c['avg_weight'] ? $c['avg_weight'].' kg' : '—'?></td>
          <td class="px-4 py-3 text-center text-xs text-slate-500"><?=$c['avg_height'] ? $c['avg_height'].' cm' : '—'?></td>
          <td class="px-4 py-3 text-center">
            <a href="/health/report_class.php?classroom=<?=urlencode($c['classroom'])?>&year=<?=$year?>&semester=<?=$sem?>"
               class="px-2 py-1 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-lg hover:bg-blue-100 transition-all">
              <i class="fas fa-file-alt mr-1"></i>รายงาน
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- At-risk students -->
<?php if (!empty($at_risk)): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-rose-100/50 border border-rose-100 overflow-hidden">
  <div class="px-5 py-4 border-b border-rose-100 flex items-center gap-2">
    <i class="fas fa-exclamation-triangle text-rose-500"></i>
    <h3 class="font-black text-slate-700 text-sm">นักเรียนกลุ่มเสี่ยง</h3>
    <span class="px-2 py-0.5 bg-rose-100 text-rose-600 text-[10px] font-black rounded-full animate-pulse"><?=count($at_risk)?> คน</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-rose-50/50 text-xs text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 text-left">ชื่อ</th>
          <th class="px-4 py-3 text-left">ห้อง</th>
          <th class="px-4 py-3 text-center">เพศ</th>
          <th class="px-4 py-3 text-center">BMI</th>
          <th class="px-4 py-3 text-center">ภาวะโภชนาการ</th>
          <th class="px-4 py-3 text-center">ส่วนสูงตามวัย</th>
          <th class="px-4 py-3 text-center">วันที่วัด</th>
          <th class="px-4 py-3 text-center">ดูประวัติ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($at_risk as $r):
          $bc = ['ผอมมาก'=>'rose','อ้วน'=>'rose'][$r['bfa_status']] ?? 'amber';
        ?>
        <tr class="hover:bg-rose-50/30 transition-colors">
          <td class="px-4 py-3 font-bold text-slate-700 text-xs"><?=htmlspecialchars($r['name'],ENT_QUOTES,'UTF-8')?></td>
          <td class="px-4 py-3"><span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs font-bold rounded-full"><?=htmlspecialchars($r['classroom'],ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-4 py-3 text-center text-xs <?=$r['gender']==='หญิง'?'text-rose-500':'text-blue-500'?> font-bold"><?=htmlspecialchars($r['gender']??'—',ENT_QUOTES,'UTF-8')?></td>
          <td class="px-4 py-3 text-center text-xs font-black text-rose-600"><?=number_format($r['bmi'],1)?></td>
          <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-rose-100 text-rose-600 text-xs font-black rounded-full"><?=htmlspecialchars($r['bfa_status']??'—',ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-4 py-3 text-center">
            <?php if ($r['hfa_status'] === 'เตี้ยมาก'): ?>
            <span class="px-2 py-0.5 bg-rose-100 text-rose-600 text-xs font-black rounded-full">เตี้ยมาก</span>
            <?php else: ?>
            <span class="text-xs text-slate-400"><?=htmlspecialchars($r['hfa_status']??'—',ENT_QUOTES,'UTF-8')?></span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-center text-xs text-slate-400"><?=date('d/m/Y',strtotime($r['record_date']))?></td>
          <td class="px-4 py-3 text-center">
            <a href="/health/profile.php?student_id=<?=$r['student_id']?>" class="px-2 py-1 bg-violet-50 text-violet-700 text-[10px] font-bold rounded-lg hover:bg-violet-100 transition-all">
              <i class="fas fa-chart-line"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($school_stats['has_record'] > 0): ?>
<script>
// Classroom stacked bar
const clsLabels = <?=json_encode(array_column($cls_summary,'classroom'))?>;
new Chart(document.getElementById('classBarChart'), {
  type:'bar',
  data:{ labels:clsLabels, datasets:[
    { label:'สมส่วน',        data:<?=json_encode(array_map('intval',array_column($cls_summary,'normal')))?>,   backgroundColor:'#10B981', borderRadius:3, borderSkipped:false },
    { label:'ผอม/ผอมมาก',    data:<?=json_encode(array_map('intval',array_column($cls_summary,'thin')))?>,     backgroundColor:'#F59E0B', borderRadius:3, borderSkipped:false },
    { label:'น้ำหนักเกิน/อ้วน',data:<?=json_encode(array_map('intval',array_column($cls_summary,'fat')))?>,    backgroundColor:'#F43F5E', borderRadius:3, borderSkipped:false },
    { label:'เตี้ย/เตี้ยมาก', data:<?=json_encode(array_map('intval',array_column($cls_summary,'short_st')))?>, backgroundColor:'#8B5CF6', borderRadius:3, borderSkipped:false },
  ]},
  options:{ responsive:true,
    plugins:{ legend:{ position:'bottom', labels:{ font:{family:'Prompt',size:10}, padding:10 } } },
    scales:{ x:{ grid:{display:false} }, y:{ beginAtZero:true, grid:{color:'#f3f4f6'}, ticks:{stepSize:1} } }
  }
});

// Overall donut
new Chart(document.getElementById('overallPie'), {
  type:'doughnut',
  data:{
    labels:['สมส่วน','ผอม/ผอมมาก','น้ำหนักเกิน/อ้วน','เตี้ย/เตี้ยมาก'],
    datasets:[{ data:[
      <?=$school_stats['normal']?>,
      <?=$school_stats['thin']+$school_stats['very_thin']?>,
      <?=$school_stats['fat']+$school_stats['obese']?>,
      <?=$school_stats['short']+$school_stats['very_short']?>
    ], backgroundColor:['#10B981','#F59E0B','#F43F5E','#8B5CF6'], borderWidth:2, borderColor:'#fff' }]
  },
  options:{ responsive:true, cutout:'60%',
    plugins:{ legend:{ position:'bottom', labels:{ font:{family:'Prompt',size:10}, padding:10 } } }
  }
});

// Gender grouped bar
new Chart(document.getElementById('genderChart'), {
  type:'bar',
  data:{
    labels:['สมส่วน','ผอม/ผอมมาก','น้ำหนักเกิน/อ้วน','เตี้ย/เตี้ยมาก'],
    datasets:[
      { label:'ชาย',  data:[<?=$gender_stats['male']['normal']?>,<?=$gender_stats['male']['thin']?>,<?=$gender_stats['male']['fat']?>,<?=$gender_stats['male']['short']?>],   backgroundColor:'#3B82F6', borderRadius:4, borderSkipped:false },
      { label:'หญิง', data:[<?=$gender_stats['female']['normal']?>,<?=$gender_stats['female']['thin']?>,<?=$gender_stats['female']['fat']?>,<?=$gender_stats['female']['short']?>], backgroundColor:'#F43F5E', borderRadius:4, borderSkipped:false },
    ]
  },
  options:{ responsive:true,
    plugins:{ legend:{ position:'bottom', labels:{ font:{family:'Prompt',size:11}, padding:10 } } },
    scales:{ x:{ grid:{display:false} }, y:{ beginAtZero:true, grid:{color:'#f3f4f6'}, ticks:{stepSize:1} } }
  }
});

<?php if (!empty($trend)): ?>
// Trend chart
const trendLabels = <?=json_encode(array_map(fn($t)=>'ปี '.$t['academic_year'].' ภาค '.$t['semester'], $trend))?>;
new Chart(document.getElementById('trendChart'), {
  type:'line',
  data:{ labels:trendLabels, datasets:[{
    label:'BMI เฉลี่ย',
    data:<?=json_encode(array_map(fn($t)=>(float)$t['avg_bmi'], $trend))?>,
    borderColor:'#14B8A6', backgroundColor:'rgba(20,184,166,0.08)',
    fill:true, tension:0.4, pointBackgroundColor:'#14B8A6', pointRadius:5, borderWidth:2
  }]},
  options:{ responsive:true,
    plugins:{ legend:{display:false} },
    scales:{
      x:{ grid:{display:false}, ticks:{font:{family:'Prompt',size:10}} },
      y:{ beginAtZero:false, suggestedMin:14, suggestedMax:24, grid:{color:'#f3f4f6'}, ticks:{font:{family:'Prompt',size:10}} }
    }
  }
});
<?php endif; ?>
</script>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
