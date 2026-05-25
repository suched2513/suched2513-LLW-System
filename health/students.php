<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo       = getPdo();
$year      = (int)($_GET['year']     ?? 2569);
$sem       = (int)($_GET['semester'] ?? 1);
$classroom = trim($_GET['classroom'] ?? '');
$search    = trim($_GET['q'] ?? '');
if (!in_array($sem, [1, 2])) $sem = 1;

// Classrooms with at least one record
$classrooms = $pdo->prepare("
    SELECT DISTINCT s.classroom
    FROM att_students s
    WHERE s.status='active' AND s.student_id REGEXP '^[0-9]+$'
      AND s.student_id NOT IN (SELECT subject_code FROM att_subjects)
    ORDER BY s.classroom
");
$classrooms->execute();
$classrooms = $classrooms->fetchAll(PDO::FETCH_COLUMN);

// Build query
$where  = ['s.status=\'active\'', 's.student_id REGEXP \'^[0-9]+$\'', 's.student_id NOT IN (SELECT subject_code FROM att_subjects)'];
$params = [];

if ($classroom) { $where[] = 's.classroom=?'; $params[] = $classroom; }
if ($search)    { $where[] = 's.name LIKE ?';  $params[] = "%$search%"; }

$where_sql = implode(' AND ', $where);

$rows = $pdo->prepare("
    SELECT s.id, s.name, s.classroom, s.gender, s.birthdate,
           hr.id AS rec_id, hr.weight_kg, hr.height_cm, hr.bmi, hr.bfa_status, hr.hfa_status,
           hr.record_date, hr.semester, hr.academic_year
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
    ) hr ON hr.student_id = s.id
    WHERE $where_sql
    ORDER BY s.classroom, s.name
");
$rows->execute(array_merge([$year, $sem, $year, $sem], $params));
$students = $rows->fetchAll();

// Export CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="health_' . $year . '_' . $sem . '_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    echo "ชื่อ,ห้อง,เพศ,น้ำหนัก(kg),ส่วนสูง(cm),BMI,ภาวะโภชนาการ,ส่วนสูงตามวัย,วันที่วัด\n";
    foreach ($students as $s) {
        echo implode(',', [
            '"' . str_replace('"','""',$s['name']) . '"',
            '"' . $s['classroom'] . '"',
            $s['gender'] === 'male' ? 'ชาย' : 'หญิง',
            $s['weight_kg'] ?? '',
            $s['height_cm'] ?? '',
            $s['bmi'] ? number_format($s['bmi'],1) : '',
            '"' . ($s['bfa_status'] ?? '—') . '"',
            '"' . ($s['hfa_status'] ?? '—') . '"',
            $s['record_date'] ?? '',
        ]) . "\n";
    }
    exit;
}

$pageTitle    = 'รายชื่อนักเรียน (สุขภาวะ)';
$pageSubtitle = 'บันทึกน้ำหนักส่วนสูงรายบุคคล';
$activeSystem = 'health';
require_once __DIR__ . '/../components/layout_start.php';

$saved = isset($_GET['saved']);
?>

<?php if ($saved): ?>
<div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center gap-3 text-emerald-700 text-sm font-bold">
  <i class="fas fa-check-circle text-emerald-500 text-lg"></i> บันทึกข้อมูลเรียบร้อยแล้ว
</div>
<?php endif; ?>

<!-- Header -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div class="flex items-center gap-3">
    <a href="/health/dashboard.php" class="w-9 h-9 bg-slate-100 rounded-xl flex items-center justify-center hover:bg-slate-200 transition-all">
      <i class="fas fa-arrow-left text-slate-600 text-sm"></i>
    </a>
    <div>
      <h2 class="text-lg font-black text-slate-800">รายชื่อนักเรียน</h2>
      <p class="text-xs text-slate-400"><?=count($students)?> คน · ปี <?=$year?> ภาค <?=$sem?></p>
    </div>
  </div>
  <div class="flex gap-2">
    <a href="?year=<?=$year?>&semester=<?=$sem?>&classroom=<?=urlencode($classroom)?>&q=<?=urlencode($search)?>&export=1"
       class="px-3 py-2 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-xl hover:bg-emerald-100 transition-all">
      <i class="fas fa-file-csv mr-1"></i>Export CSV
    </a>
    <a href="/health/record.php" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all">
      <i class="fas fa-plus mr-1"></i>บันทึกใหม่
    </a>
  </div>
</div>

<!-- Filters -->
<form method="get" class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-4 mb-5 flex flex-wrap gap-3">
  <input type="hidden" name="year" value="<?=$year?>">
  <input type="hidden" name="semester" value="<?=$sem?>">
  <select name="classroom" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-slate-50 focus:ring-2 focus:ring-emerald-400 outline-none">
    <option value="">ทุกห้อง</option>
    <?php foreach ($classrooms as $cl): ?>
    <option value="<?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>" <?=$cl===$classroom?'selected':''?>><?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="q" value="<?=htmlspecialchars($search,ENT_QUOTES,'UTF-8')?>" placeholder="ค้นหาชื่อ..." class="flex-1 min-w-36 text-xs border border-slate-200 rounded-xl px-3 py-2 bg-slate-50 focus:ring-2 focus:ring-emerald-400 outline-none">
  <button type="submit" class="px-3 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition-all">
    <i class="fas fa-search"></i>
  </button>
  <a href="?year=<?=$year?>&semester=<?=$sem?>" class="px-3 py-2 bg-slate-100 text-slate-500 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all">รีเซ็ต</a>
</form>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 text-left">#</th>
          <th class="px-4 py-3 text-left">ชื่อ</th>
          <th class="px-4 py-3 text-left">ห้อง</th>
          <th class="px-4 py-3 text-center">น้ำหนัก</th>
          <th class="px-4 py-3 text-center">ส่วนสูง</th>
          <th class="px-4 py-3 text-center">BMI</th>
          <th class="px-4 py-3 text-center">ภาวะโภชนาการ</th>
          <th class="px-4 py-3 text-center">ส่วนสูงตามวัย</th>
          <th class="px-4 py-3 text-center">วันที่วัด</th>
          <th class="px-4 py-3 text-center">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php if (empty($students)): ?>
        <tr><td colspan="10" class="px-5 py-10 text-center text-slate-300">ไม่มีข้อมูล</td></tr>
        <?php endif; ?>
        <?php
        $bfa_colors = ['ผอมมาก'=>'rose','ผอม'=>'amber','สมส่วน'=>'emerald','น้ำหนักเกิน'=>'orange','อ้วน'=>'rose'];
        $hfa_colors = ['เตี้ยมาก'=>'rose','เตี้ย'=>'amber','ปกติ'=>'slate','สูง'=>'blue'];
        $n = 0;
        foreach ($students as $s):
            $n++;
            $bc = $bfa_colors[$s['bfa_status'] ?? ''] ?? 'slate';
            $hc = $hfa_colors[$s['hfa_status'] ?? ''] ?? 'slate';
            $no_record = !$s['rec_id'];
        ?>
        <tr class="hover:bg-slate-50/50 transition-colors <?=$no_record?'opacity-50':''?>">
          <td class="px-4 py-3 text-xs text-slate-400"><?=$n?></td>
          <td class="px-4 py-3 font-bold text-slate-700 text-xs"><?=htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8')?></td>
          <td class="px-4 py-3"><span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs font-bold rounded-full"><?=htmlspecialchars($s['classroom'],ENT_QUOTES,'UTF-8')?></span></td>
          <?php if ($no_record): ?>
          <td colspan="6" class="px-4 py-3 text-center text-xs text-slate-300">— ยังไม่มีข้อมูล —</td>
          <?php else: ?>
          <td class="px-4 py-3 text-center text-xs font-bold"><?=number_format($s['weight_kg'],1)?> kg</td>
          <td class="px-4 py-3 text-center text-xs font-bold"><?=number_format($s['height_cm'],1)?> cm</td>
          <td class="px-4 py-3 text-center text-xs font-bold text-slate-600"><?=number_format($s['bmi'],1)?></td>
          <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-<?=$bc?>-50 text-<?=$bc?>-600 text-xs font-bold rounded-full"><?=htmlspecialchars($s['bfa_status']??'—',ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-<?=$hc?>-50 text-<?=$hc?>-600 text-xs font-bold rounded-full"><?=htmlspecialchars($s['hfa_status']??'—',ENT_QUOTES,'UTF-8')?></span></td>
          <td class="px-4 py-3 text-center text-xs text-slate-400"><?=date('d/m/Y',strtotime($s['record_date']))?></td>
          <?php endif; ?>
          <td class="px-4 py-3 text-center">
            <?php if ($no_record): ?>
            <a href="/health/record.php?prefill_student=<?=$s['id']?>" class="px-2 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-lg hover:bg-emerald-100 transition-all">
              <i class="fas fa-plus mr-1"></i>บันทึก
            </a>
            <?php else: ?>
            <div class="flex gap-1 justify-center">
              <a href="/health/record.php?id=<?=$s['rec_id']?>" class="px-2 py-1 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-lg hover:bg-blue-100 transition-all">
                <i class="fas fa-edit"></i>
              </a>
              <a href="/health/profile.php?student_id=<?=$s['id']?>" class="px-2 py-1 bg-violet-50 text-violet-700 text-[10px] font-bold rounded-lg hover:bg-violet-100 transition-all">
                <i class="fas fa-chart-line"></i>
              </a>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
