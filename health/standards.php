<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();

// Count loaded standards
$cnt = $pdo->query("SELECT COUNT(*) FROM health_growth_standards")->fetchColumn();
$male_cnt   = $pdo->query("SELECT COUNT(*) FROM health_growth_standards WHERE gender='male'")->fetchColumn();
$female_cnt = $pdo->query("SELECT COUNT(*) FROM health_growth_standards WHERE gender='female'")->fetchColumn();

// Sample rows
$samples = $pdo->query("
    SELECT * FROM health_growth_standards
    ORDER BY gender, age_month LIMIT 40
")->fetchAll();

// Handle CSV import
$import_msg = '';
$import_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        $file = $_FILES['csv_file']['tmp_name'];
        if (!is_uploaded_file($file)) throw new Exception('ไม่พบไฟล์');

        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') throw new Exception('กรุณาอัปโหลดไฟล์ .csv เท่านั้น');

        // Detect encoding
        $content = file_get_contents($file);
        if (mb_detect_encoding($content, 'UTF-16LE', true)) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
            $content = ltrim($content, "\xFF\xFE");
        } elseif (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        file_put_contents($file, $content);

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        // Normalize header
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        $required = ['gender','age_month'];
        foreach ($required as $req) {
            if (!in_array($req, $header)) throw new Exception("ไม่พบคอลัมน์: $req");
        }

        $stmt = $pdo->prepare("
            INSERT INTO health_growth_standards
                (gender,age_month,hfa_neg3,hfa_neg2,hfa_neg1,hfa_median,hfa_pos1,hfa_pos2,hfa_pos3,
                 wfa_neg3,wfa_neg2,wfa_neg1,wfa_median,wfa_pos1,wfa_pos2,wfa_pos3,
                 bfa_neg3,bfa_neg2,bfa_neg1,bfa_median,bfa_pos1,bfa_pos2,bfa_pos3)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                hfa_neg3=VALUES(hfa_neg3),hfa_neg2=VALUES(hfa_neg2),hfa_neg1=VALUES(hfa_neg1),
                hfa_median=VALUES(hfa_median),hfa_pos1=VALUES(hfa_pos1),hfa_pos2=VALUES(hfa_pos2),hfa_pos3=VALUES(hfa_pos3),
                wfa_neg3=VALUES(wfa_neg3),wfa_neg2=VALUES(wfa_neg2),wfa_neg1=VALUES(wfa_neg1),
                wfa_median=VALUES(wfa_median),wfa_pos1=VALUES(wfa_pos1),wfa_pos2=VALUES(wfa_pos2),wfa_pos3=VALUES(wfa_pos3),
                bfa_neg3=VALUES(bfa_neg3),bfa_neg2=VALUES(bfa_neg2),bfa_neg1=VALUES(bfa_neg1),
                bfa_median=VALUES(bfa_median),bfa_pos1=VALUES(bfa_pos1),bfa_pos2=VALUES(bfa_pos2),bfa_pos3=VALUES(bfa_pos3)
        ");

        $col = array_flip($header);
        $g  = fn($k) => isset($col[$k]) ? (strlen(trim($row[$col[$k]]??'')) ? (float)$row[$col[$k]] : null) : null;

        $count = 0;
        $pdo->beginTransaction();
        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) continue;
            $gender    = strtolower(trim($row[$col['gender']] ?? ''));
            $age_month = (int)($row[$col['age_month']] ?? 0);
            if (!in_array($gender, ['male','female']) || $age_month < 0) continue;
            $stmt->execute([
                $gender, $age_month,
                $g('hfa_neg3'),$g('hfa_neg2'),$g('hfa_neg1'),$g('hfa_median'),$g('hfa_pos1'),$g('hfa_pos2'),$g('hfa_pos3'),
                $g('wfa_neg3'),$g('wfa_neg2'),$g('wfa_neg1'),$g('wfa_median'),$g('wfa_pos1'),$g('wfa_pos2'),$g('wfa_pos3'),
                $g('bfa_neg3'),$g('bfa_neg2'),$g('bfa_neg1'),$g('bfa_median'),$g('bfa_pos1'),$g('bfa_pos2'),$g('bfa_pos3'),
            ]);
            $count++;
        }
        fclose($handle);
        $pdo->commit();
        $import_msg = "นำเข้าสำเร็จ {$count} แถว";
        // Re-fetch counts
        $cnt        = $pdo->query("SELECT COUNT(*) FROM health_growth_standards")->fetchColumn();
        $male_cnt   = $pdo->query("SELECT COUNT(*) FROM health_growth_standards WHERE gender='male'")->fetchColumn();
        $female_cnt = $pdo->query("SELECT COUNT(*) FROM health_growth_standards WHERE gender='female'")->fetchColumn();
        $samples    = $pdo->query("SELECT * FROM health_growth_standards ORDER BY gender,age_month LIMIT 40")->fetchAll();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $import_err = $e->getMessage();
        error_log($e->getMessage());
    }
}

$pageTitle    = 'เกณฑ์มาตรฐานการเจริญเติบโต';
$pageSubtitle = 'กรมอนามัย — WHO Growth Standards';
$activeSystem = 'health';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/health/dashboard.php" class="w-9 h-9 bg-slate-100 rounded-xl flex items-center justify-center hover:bg-slate-200 transition-all">
    <i class="fas fa-arrow-left text-slate-600 text-sm"></i>
  </a>
  <div>
    <h2 class="text-lg font-black text-slate-800">เกณฑ์มาตรฐาน กรมอนามัย</h2>
    <p class="text-xs text-slate-400">WHO Growth Standards สำหรับประเมินภาวะโภชนาการนักเรียนไทย</p>
  </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 text-center">
    <p class="text-3xl font-black text-slate-700"><?=(int)$cnt?></p>
    <p class="text-xs text-slate-400 mt-1">แถวทั้งหมด</p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 text-center">
    <p class="text-3xl font-black text-blue-600"><?=(int)$male_cnt?></p>
    <p class="text-xs text-slate-400 mt-1">เพศชาย</p>
  </div>
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-5 text-center">
    <p class="text-3xl font-black text-rose-500"><?=(int)$female_cnt?></p>
    <p class="text-xs text-slate-400 mt-1">เพศหญิง</p>
  </div>
</div>

<!-- Import card -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-6 mb-6">
  <h3 class="font-black text-slate-700 text-sm mb-1">นำเข้าข้อมูลมาตรฐาน (CSV)</h3>
  <p class="text-xs text-slate-400 mb-4">ไฟล์ CSV ต้องมีคอลัมน์: <code class="bg-slate-100 px-1 rounded">gender</code>, <code class="bg-slate-100 px-1 rounded">age_month</code>, <code class="bg-slate-100 px-1 rounded">hfa_neg3</code> ... <code class="bg-slate-100 px-1 rounded">bfa_pos3</code></p>

  <?php if ($import_msg): ?>
  <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 text-xs font-bold">
    <i class="fas fa-check-circle mr-1"></i><?=htmlspecialchars($import_msg,ENT_QUOTES,'UTF-8')?>
  </div>
  <?php endif; ?>
  <?php if ($import_err): ?>
  <div class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-600 text-xs font-bold">
    <i class="fas fa-exclamation-circle mr-1"></i><?=htmlspecialchars($import_err,ENT_QUOTES,'UTF-8')?>
  </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="flex items-center gap-3 flex-wrap">
    <input type="file" name="csv_file" accept=".csv" required
      class="text-xs border border-slate-200 rounded-xl px-3 py-2 bg-slate-50 file:mr-2 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 file:border-0 file:rounded-lg file:px-3 file:py-1">
    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all">
      <i class="fas fa-upload mr-1"></i>นำเข้า
    </button>
  </form>

  <details class="mt-4">
    <summary class="text-xs text-slate-500 cursor-pointer font-bold hover:text-slate-700">ดูตัวอย่าง format CSV</summary>
    <pre class="mt-2 text-[10px] bg-slate-50 border border-slate-200 rounded-xl p-3 overflow-x-auto text-slate-600">gender,age_month,hfa_neg3,hfa_neg2,hfa_neg1,hfa_median,hfa_pos1,hfa_pos2,hfa_pos3,wfa_neg3,wfa_neg2,wfa_neg1,wfa_median,wfa_pos1,wfa_pos2,wfa_pos3,bfa_neg3,bfa_neg2,bfa_neg1,bfa_median,bfa_pos1,bfa_pos2,bfa_pos3
male,60,99.9,103.0,106.1,109.4,112.8,116.2,119.7,13.0,14.2,15.7,17.3,19.2,21.4,23.7,12.1,13.6,15.2,16.9,18.9,21.1,23.5
female,60,98.7,101.9,105.1,108.4,111.8,115.2,118.7,12.4,13.7,15.2,16.8,18.7,21.0,23.5,12.0,13.6,15.3,17.1,19.2,21.6,24.3</pre>
  </details>
</div>

<!-- Sample data table -->
<?php if (!empty($samples)): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-100">
    <h3 class="font-black text-slate-700 text-sm">ตัวอย่างข้อมูลที่โหลดแล้ว (40 แถวแรก)</h3>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-[11px]">
      <thead class="bg-slate-50 text-[10px] text-slate-400 font-black uppercase tracking-wider">
        <tr>
          <th class="px-3 py-2">เพศ</th>
          <th class="px-3 py-2">อายุ(เดือน)</th>
          <th class="px-3 py-2 bg-blue-50" colspan="4">ส่วนสูงตามวัย HFA</th>
          <th class="px-3 py-2 bg-amber-50" colspan="4">น้ำหนักตามวัย WFA</th>
          <th class="px-3 py-2 bg-violet-50" colspan="4">BMI ตามวัย BFA</th>
        </tr>
        <tr>
          <th class="px-3 py-2"></th><th class="px-3 py-2"></th>
          <th class="px-3 py-2 bg-blue-50">-2SD</th><th class="px-3 py-2 bg-blue-50">median</th><th class="px-3 py-2 bg-blue-50">+1SD</th><th class="px-3 py-2 bg-blue-50">+2SD</th>
          <th class="px-3 py-2 bg-amber-50">-2SD</th><th class="px-3 py-2 bg-amber-50">median</th><th class="px-3 py-2 bg-amber-50">+1SD</th><th class="px-3 py-2 bg-amber-50">+2SD</th>
          <th class="px-3 py-2 bg-violet-50">-2SD</th><th class="px-3 py-2 bg-violet-50">median</th><th class="px-3 py-2 bg-violet-50">+1SD</th><th class="px-3 py-2 bg-violet-50">+2SD</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($samples as $s): ?>
        <tr class="hover:bg-slate-50/50">
          <td class="px-3 py-2 font-bold <?=$s['gender']==='male'?'text-blue-600':'text-rose-500'?>">
            <?=$s['gender']==='male'?'ชาย':'หญิง'?>
          </td>
          <td class="px-3 py-2 text-center text-slate-600"><?=$s['age_month']?></td>
          <td class="px-3 py-2 text-center"><?=$s['hfa_neg2']?></td>
          <td class="px-3 py-2 text-center font-bold"><?=$s['hfa_median']?></td>
          <td class="px-3 py-2 text-center"><?=$s['hfa_pos1']?></td>
          <td class="px-3 py-2 text-center"><?=$s['hfa_pos2']?></td>
          <td class="px-3 py-2 text-center"><?=$s['wfa_neg2']?></td>
          <td class="px-3 py-2 text-center font-bold"><?=$s['wfa_median']?></td>
          <td class="px-3 py-2 text-center"><?=$s['wfa_pos1']?></td>
          <td class="px-3 py-2 text-center"><?=$s['wfa_pos2']?></td>
          <td class="px-3 py-2 text-center"><?=$s['bfa_neg2']?></td>
          <td class="px-3 py-2 text-center font-bold"><?=$s['bfa_median']?></td>
          <td class="px-3 py-2 text-center"><?=$s['bfa_pos1']?></td>
          <td class="px-3 py-2 text-center"><?=$s['bfa_pos2']?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center">
  <i class="fas fa-table text-amber-400 text-3xl mb-3 block"></i>
  <p class="text-amber-700 font-bold text-sm mb-1">ยังไม่มีข้อมูลมาตรฐาน</p>
  <p class="text-amber-600 text-xs">ระบบจะใช้เกณฑ์โดยประมาณจนกว่าจะนำเข้าข้อมูลกรมอนามัย</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
