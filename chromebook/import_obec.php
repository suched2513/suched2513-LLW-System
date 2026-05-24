<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'cb_admin'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}

$pdo = getPdo();

// ── SHOW COLUMNS guards ──
$cbCols    = $pdo->query("SHOW COLUMNS FROM cb_chromebooks")->fetchAll(PDO::FETCH_COLUMN);
$hasMac    = in_array('mac_address',   $cbCols);
$hasImei   = in_array('imei',          $cbCols);
$hasKbBox  = in_array('keyboard_box',  $cbCols);
$hasKbSn   = in_array('keyboard_sn',   $cbCols);
$hasSim    = in_array('sim_type',      $cbCols);
$hasMobile = in_array('mobile_no',     $cbCols);
$hasBrand  = in_array('brand',         $cbCols);
$hasBorrower = in_array('borrower_type', $cbCols);

$migrationReady = $hasMac && $hasImei;

// ── CSV column mapping (positions in our normalised mapped row) ──
define('COL_TYPE',    0);
define('COL_BOX',     1);
define('COL_SERIAL',  2);
define('COL_MAC',     3);
define('COL_IMEI',    4);
define('COL_KBBOX',   5);
define('COL_KBSN',    6);
define('COL_SIM',     7);
define('COL_MOBILE',  8);
define('COL_BRAND',   9);
define('COL_MODEL',   10);
define('COL_FN',      11);
define('COL_LN',      12);

$result = null;
$preview = [];
$errors  = [];
$step    = 'upload'; // upload | preview | done

// ── POST handler ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Step 1: parse & preview ──
    if ($action === 'preview') {
        if (empty($_FILES['csv_file']['tmp_name'])) {
            $errors[] = 'กรุณาเลือกไฟล์ CSV';
        } else {
            $content = file_get_contents($_FILES['csv_file']['tmp_name']);

            // Detect BOM and convert encoding (PowerShell 5.1 often writes UTF-16 LE)
            // UTF-16 LE: Thai char ย (U+0E22) → \x22\x0E → fgetcsv sees \x22 as quote → wrong field count
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3); // strip UTF-8 BOM
            } elseif (substr($content, 0, 2) === "\xFF\xFE") {
                $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
            } elseif (substr($content, 0, 2) === "\xFE\xFF") {
                $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
            }

            $lines = preg_split('/\r\n|\r|\n/', $content);
            $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));

            if (count($lines) < 2) {
                $errors[] = 'ไฟล์ต้องมีอย่างน้อย 1 แถวข้อมูล';
            } else {
                // Map headers by name (tolerates different column order)
                $rawHeader = str_getcsv(array_shift($lines));
                $colMap = [];
                foreach ($rawHeader as $i => $h) {
                    $colMap[trim(strtolower($h))] = $i;
                }

                $reqCols = ['type', 'box_no', 'serial_no'];
                foreach ($reqCols as $req) {
                    if (!array_key_exists($req, $colMap)) {
                        $found = implode(', ', array_keys($colMap));
                        $errors[] = "ไม่พบคอลัมน์ '{$req}' ในไฟล์ (คอลัมน์ที่พบ: {$found})";
                    }
                }

                if (empty($errors)) {
                    $get = fn($row, $name) => isset($colMap[$name]) ? trim($row[$colMap[$name]] ?? '') : '';
                    foreach ($lines as $line) {
                        $row = str_getcsv($line);
                        $preview[] = [
                            $get($row, 'type'),
                            $get($row, 'box_no'),
                            $get($row, 'serial_no'),
                            $get($row, 'mac_address'),
                            $get($row, 'imei'),
                            $get($row, 'keyboard_box'),
                            $get($row, 'keyboard_sn'),
                            $get($row, 'sim_type'),
                            $get($row, 'mobile_no'),
                            $get($row, 'brand'),
                            $get($row, 'model'),
                            $get($row, 'firstname'),
                            $get($row, 'lastname'),
                        ];
                    }

                    if (!empty($preview)) {
                        $_SESSION['obec_import_data'] = $preview;
                        $step = 'preview';
                    } else {
                        $errors[] = 'ไม่พบข้อมูลในไฟล์';
                    }
                }
            }
        }
    }

    // ── Step 2: import ──
    if ($action === 'import') {
        $data = $_SESSION['obec_import_data'] ?? [];
        if (empty($data)) {
            $errors[] = 'ข้อมูลหมดอายุ กรุณาอัปโหลดใหม่';
        } else {
            $countDev = 0; $countTeacher = 0; $countStudent = 0; $countLog = 0;
            $skipped  = 0;

            try {
                $pdo->beginTransaction();

                // ── prepare statements ──
                // cb_chromebooks — build dynamically depending on which columns exist
                $devCols  = ['chromebook_id', 'model', 'serial_number'];
                $devPlaceholders = ['?','?','?'];
                if ($hasBrand)    { $devCols[] = 'brand';        $devPlaceholders[] = '?'; }
                if ($hasMac)      { $devCols[] = 'mac_address';  $devPlaceholders[] = '?'; }
                if ($hasImei)     { $devCols[] = 'imei';         $devPlaceholders[] = '?'; }
                if ($hasKbBox)    { $devCols[] = 'keyboard_box'; $devPlaceholders[] = '?'; }
                if ($hasKbSn)     { $devCols[] = 'keyboard_sn';  $devPlaceholders[] = '?'; }
                if ($hasSim)      { $devCols[] = 'sim_type';     $devPlaceholders[] = '?'; }
                if ($hasMobile)   { $devCols[] = 'mobile_no';    $devPlaceholders[] = '?'; }
                if ($hasBorrower) { $devCols[] = 'borrower_type'; $devPlaceholders[] = '?'; }
                if ($hasBorrower) { $devCols[] = 'borrower_name'; $devPlaceholders[] = '?'; }

                $updateParts = array_map(fn($c) => "$c=VALUES($c)", array_slice($devCols, 1));
                $devSql = "INSERT INTO cb_chromebooks (" . implode(',', $devCols) . ")
                           VALUES (" . implode(',', $devPlaceholders) . ")
                           ON DUPLICATE KEY UPDATE " . implode(',', $updateParts);
                $stmtDev = $pdo->prepare($devSql);

                $stmtTeacher = $pdo->prepare("
                    INSERT INTO cb_teachers (teacher_id, name)
                    VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)
                ");
                $stmtStudent = $pdo->prepare("
                    INSERT INTO cb_students (student_id, name, class_name)
                    VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)
                ");
                $stmtLog = $pdo->prepare("
                    INSERT IGNORE INTO cb_borrow_logs
                        (borrower_type, borrower_id, class_name, chromebook_id, chromebook_serial, status, date_borrowed)
                    VALUES (?,?,?,?,?,'Borrowed',NOW())
                ");

                foreach ($data as $row) {
                    $type   = trim($row[COL_TYPE]);
                    $box    = trim($row[COL_BOX]);
                    $serial = trim($row[COL_SERIAL]);
                    $mac    = trim($row[COL_MAC]);
                    $imei   = trim($row[COL_IMEI]);
                    $kbbox  = trim($row[COL_KBBOX]);
                    $kbsn   = trim($row[COL_KBSN]);
                    $sim    = trim($row[COL_SIM]);
                    $mobile = trim($row[COL_MOBILE]);
                    $brand  = trim($row[COL_BRAND]);
                    $model  = trim($row[COL_MODEL]);
                    $fn     = trim($row[COL_FN]);
                    $ln     = trim($row[COL_LN]);
                    $name   = trim($fn . ' ' . $ln);

                    if (!$box || !$serial) { $skipped++; continue; }

                    $isTeacher  = ($type === 'ครู');
                    $bType      = $isTeacher ? 'Teacher' : 'Student';
                    $personId   = $box; // use box_no as unique person ID

                    // Insert device
                    $devParams = [$box, $brand . ' ' . $model, $serial];
                    if ($hasBrand)    $devParams[] = $brand;
                    if ($hasMac)      $devParams[] = $mac;
                    if ($hasImei)     $devParams[] = $imei ?: null;
                    if ($hasKbBox)    $devParams[] = $kbbox ?: null;
                    if ($hasKbSn)     $devParams[] = $kbsn ?: null;
                    if ($hasSim)      $devParams[] = $sim ?: null;
                    if ($hasMobile)   $devParams[] = $mobile ?: null;
                    if ($hasBorrower) $devParams[] = $bType;
                    if ($hasBorrower) $devParams[] = $name;
                    $stmtDev->execute($devParams);
                    $countDev++;

                    // Insert person
                    if ($isTeacher) {
                        $stmtTeacher->execute([$personId, $name]);
                        $countTeacher++;
                    } else {
                        $stmtStudent->execute([$personId, $name, '']);
                        $countStudent++;
                    }

                    // Insert borrow log
                    $stmtLog->execute([$bType, $personId, '', $box, $serial]);
                    $countLog++;
                }

                $pdo->commit();
                unset($_SESSION['obec_import_data']);
                $result = compact('countDev','countTeacher','countStudent','countLog','skipped');
                $step = 'done';

            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[OBEC import] ' . $e->getMessage());
                $errors[] = 'เกิดข้อผิดพลาดระหว่างนำเข้า: กรุณาตรวจสอบว่า run migration แล้ว';
            }
        }
    }

    // load preview from session if step=preview
    if ($step === 'preview' && empty($preview)) {
        $preview = $_SESSION['obec_import_data'] ?? [];
    }
}

// summary counts for existing data
$existDev     = (int)$pdo->query("SELECT COUNT(*) FROM cb_chromebooks")->fetchColumn();
$existTeacher = (int)$pdo->query("SELECT COUNT(*) FROM cb_teachers")->fetchColumn();
$existStudent = (int)$pdo->query("SELECT COUNT(*) FROM cb_students")->fetchColumn();

$pageTitle    = 'นำเข้าข้อมูลอุปกรณ์ OBEC';
$pageSubtitle = 'นำเข้าข้อมูล Chromebook จากไฟล์ สพม.ศรีสะเกษ ยโสธร';
$activeSystem = 'chromebook';
require_once '../components/layout_start.php';
?>

<!-- Page Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div class="flex items-center gap-3">
    <div class="w-11 h-11 rounded-2xl flex items-center justify-center shadow-lg"
         style="background:linear-gradient(135deg,#0891b2,#0284c7)">
      <i class="fas fa-file-import text-white"></i>
    </div>
    <div>
      <h2 class="text-lg font-black text-slate-800">นำเข้าข้อมูลอุปกรณ์ OBEC</h2>
      <p class="text-xs text-slate-400">Samsung Galaxy Tab S10 FE 5G จาก สพม.ศรีสะเกษ ยโสธร</p>
    </div>
  </div>
  <a href="index.php" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all flex items-center gap-2">
    <i class="fas fa-arrow-left"></i> กลับ
  </a>
</div>

<?php if (!$migrationReady): ?>
<!-- Migration warning -->
<div class="bg-amber-50 border-2 border-amber-300 rounded-2xl p-6 flex items-start gap-4 mb-6">
  <div class="w-10 h-10 bg-amber-400 rounded-xl flex items-center justify-center text-white flex-shrink-0">
    <i class="fas fa-exclamation-triangle"></i>
  </div>
  <div>
    <p class="font-black text-amber-800">ต้อง Run Migration ก่อน</p>
    <p class="text-sm text-amber-700 mt-1">ตาราง <code>cb_chromebooks</code> ยังไม่มีคอลัมน์ MAC, IMEI, Keyboard ฯลฯ</p>
    <code class="block mt-2 bg-amber-100 text-amber-800 rounded-lg px-3 py-2 text-xs font-mono">php database/migrate.php</code>
    <p class="text-xs text-amber-600 mt-1">หลัง run แล้ว reload หน้านี้ใหม่ก็สามารถนำเข้าได้เลย</p>
  </div>
</div>
<?php endif; ?>

<!-- Existing data summary -->
<div class="grid grid-cols-3 gap-4 mb-6">
  <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm text-center">
    <p class="text-2xl font-black text-cyan-600"><?= $existDev ?></p>
    <p class="text-xs text-slate-400 font-bold mt-0.5">อุปกรณ์ในระบบ</p>
  </div>
  <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm text-center">
    <p class="text-2xl font-black text-indigo-600"><?= $existTeacher ?></p>
    <p class="text-xs text-slate-400 font-bold mt-0.5">ครูในระบบ</p>
  </div>
  <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm text-center">
    <p class="text-2xl font-black text-violet-600"><?= $existStudent ?></p>
    <p class="text-xs text-slate-400 font-bold mt-0.5">นักเรียนในระบบ</p>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 mb-6">
  <?php foreach ($errors as $e): ?>
  <p class="text-sm text-rose-700 font-bold flex items-center gap-2"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($step === 'done' && $result): ?>
<!-- ── Import Result ── -->
<div class="bg-emerald-50 border-2 border-emerald-200 rounded-2xl p-8 mb-6">
  <div class="flex items-center gap-4 mb-4">
    <div class="w-12 h-12 bg-emerald-500 rounded-2xl flex items-center justify-center text-white text-xl">
      <i class="fas fa-check"></i>
    </div>
    <div>
      <p class="font-black text-emerald-800 text-lg">นำเข้าสำเร็จ!</p>
      <p class="text-sm text-emerald-600">ข้อมูลถูกบันทึกลงฐานข้อมูลแล้ว</p>
    </div>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl p-4 text-center border border-emerald-100">
      <p class="text-3xl font-black text-cyan-600"><?= $result['countDev'] ?></p>
      <p class="text-xs text-slate-500 font-bold">อุปกรณ์</p>
    </div>
    <div class="bg-white rounded-xl p-4 text-center border border-emerald-100">
      <p class="text-3xl font-black text-indigo-600"><?= $result['countTeacher'] ?></p>
      <p class="text-xs text-slate-500 font-bold">ครู</p>
    </div>
    <div class="bg-white rounded-xl p-4 text-center border border-emerald-100">
      <p class="text-3xl font-black text-violet-600"><?= $result['countStudent'] ?></p>
      <p class="text-xs text-slate-500 font-bold">นักเรียน</p>
    </div>
    <div class="bg-white rounded-xl p-4 text-center border border-emerald-100">
      <p class="text-3xl font-black text-emerald-600"><?= $result['countLog'] ?></p>
      <p class="text-xs text-slate-500 font-bold">บันทึกการรับ</p>
    </div>
  </div>
  <?php if ($result['skipped'] > 0): ?>
  <p class="text-xs text-amber-600 mt-3 font-bold"><i class="fas fa-exclamation-circle mr-1"></i>ข้ามไป <?= $result['skipped'] ?> แถว (ข้อมูลไม่สมบูรณ์)</p>
  <?php endif; ?>
  <div class="flex gap-3 mt-4">
    <a href="index.php" class="px-4 py-2 bg-cyan-600 text-white text-sm font-black rounded-xl hover:bg-cyan-700 transition-all shadow-md shadow-cyan-100">
      <i class="fas fa-tablet-alt mr-1"></i>ดูรายการอุปกรณ์
    </a>
    <a href="import_obec.php" class="px-4 py-2 bg-white text-slate-600 text-sm font-bold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all">
      นำเข้าเพิ่มเติม
    </a>
  </div>
</div>

<?php elseif ($step === 'preview' && !empty($preview)): ?>
<!-- ── Preview Table ── -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
  <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-cyan-50/40">
    <h3 class="font-black text-slate-800 flex items-center gap-2">
      <i class="fas fa-table text-cyan-600"></i>
      ตัวอย่างข้อมูล (<?= count($preview) ?> แถว)
    </h3>
    <div class="flex gap-2 text-xs font-bold">
      <span class="px-2 py-1 bg-indigo-50 text-indigo-700 rounded-lg">
        ครู: <?= count(array_filter($preview, fn($r) => $r[COL_TYPE] === 'ครู')) ?> คน
      </span>
      <span class="px-2 py-1 bg-violet-50 text-violet-700 rounded-lg">
        นักเรียน: <?= count(array_filter($preview, fn($r) => $r[COL_TYPE] !== 'ครู')) ?> คน
      </span>
    </div>
  </div>
  <div class="overflow-x-auto" style="max-height:420px;overflow-y:auto">
    <table class="min-w-full text-xs">
      <thead class="bg-slate-50 sticky top-0 z-10">
        <tr>
          <th class="px-4 py-3 text-left font-black text-slate-400 uppercase tracking-wider">#</th>
          <th class="px-4 py-3 text-left font-black text-slate-400 uppercase tracking-wider">ประเภท</th>
          <th class="px-4 py-3 text-left font-black text-slate-400 uppercase tracking-wider">BOX No.</th>
          <th class="px-4 py-3 text-left font-black text-slate-400 uppercase tracking-wider">Serial</th>
          <th class="px-4 py-3 text-left font-black text-slate-400 uppercase tracking-wider">MAC Address</th>
          <th class="px-4 py-3 text-left font-black text-slate-400 uppercase tracking-wider">Keyboard SN</th>
          <th class="px-4 py-3 text-left font-black text-slate-400 uppercase tracking-wider">SIM / Mobile</th>
          <th class="px-4 py-3 text-left font-black text-slate-400 uppercase tracking-wider">ชื่อผู้รับ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($preview as $i => $row): ?>
        <tr class="hover:bg-slate-50/50 transition-all">
          <td class="px-4 py-2.5 text-slate-400"><?= $i + 1 ?></td>
          <td class="px-4 py-2.5">
            <?php if ($row[COL_TYPE] === 'ครู'): ?>
            <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 font-black rounded-full">ครู</span>
            <?php else: ?>
            <span class="px-2 py-0.5 bg-violet-50 text-violet-700 font-black rounded-full">นักเรียน</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5 font-mono font-bold text-cyan-700"><?= htmlspecialchars($row[COL_BOX], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="px-4 py-2.5 font-mono text-slate-600"><?= htmlspecialchars($row[COL_SERIAL], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="px-4 py-2.5 font-mono text-slate-500"><?= htmlspecialchars($row[COL_MAC], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="px-4 py-2.5 font-mono text-slate-500 text-[10px]"><?= htmlspecialchars($row[COL_KBSN], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="px-4 py-2.5 text-slate-500">
            <?= htmlspecialchars($row[COL_SIM] ?: '-', ENT_QUOTES, 'UTF-8') ?>
            <?php if ($row[COL_MOBILE]): ?>
            <br><span class="text-[10px]"><?= htmlspecialchars($row[COL_MOBILE], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-2.5 font-bold text-slate-700">
            <?= htmlspecialchars(trim($row[COL_FN] . ' ' . $row[COL_LN]), ENT_QUOTES, 'UTF-8') ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Confirm import button -->
<?php if ($migrationReady): ?>
<div class="bg-cyan-50 border border-cyan-200 rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4">
  <div>
    <p class="font-black text-cyan-800">พร้อมนำเข้า <?= count($preview) ?> รายการ</p>
    <p class="text-xs text-cyan-600 mt-1">ระบบจะบันทึกอุปกรณ์, ครู/นักเรียน และบันทึกการรับอุปกรณ์</p>
  </div>
  <div class="flex gap-3">
    <a href="import_obec.php" class="px-4 py-2 bg-white text-slate-500 text-sm font-bold rounded-xl border border-slate-200 hover:bg-slate-50 transition-all">
      <i class="fas fa-times mr-1"></i>ยกเลิก
    </a>
    <form method="POST">
      <input type="hidden" name="action" value="import">
      <button type="submit" class="px-6 py-2 bg-cyan-600 text-white text-sm font-black rounded-xl hover:bg-cyan-700 transition-all shadow-lg shadow-cyan-100 flex items-center gap-2">
        <i class="fas fa-database"></i>นำเข้าจริง
      </button>
    </form>
  </div>
</div>
<?php else: ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-700 font-bold">
  <i class="fas fa-lock mr-1"></i>กรุณา run migration ก่อนจึงจะนำเข้าได้
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── Upload Form ── -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

  <!-- Upload card -->
  <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 bg-cyan-50/30">
      <h3 class="font-black text-slate-800 flex items-center gap-2">
        <i class="fas fa-upload text-cyan-600"></i>อัปโหลดไฟล์ CSV
      </h3>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
      <input type="hidden" name="action" value="preview">
      <div class="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center hover:border-cyan-300 transition-all cursor-pointer"
           onclick="document.getElementById('csv_file').click()">
        <i class="fas fa-file-csv text-4xl text-slate-300 mb-3 block"></i>
        <p class="font-black text-slate-500">คลิกเพื่อเลือกไฟล์ CSV</p>
        <p class="text-xs text-slate-400 mt-1" id="file-label">รองรับไฟล์ .csv เข้ารหัส UTF-8</p>
        <input type="file" id="csv_file" name="csv_file" accept=".csv" class="hidden"
               onchange="document.getElementById('file-label').textContent = this.files[0]?.name || 'ยังไม่ได้เลือก'">
      </div>
      <button type="submit"
              class="w-full py-3 bg-cyan-600 text-white font-black rounded-2xl hover:bg-cyan-700 transition-all shadow-lg shadow-cyan-100 flex items-center justify-center gap-2">
        <i class="fas fa-search"></i>ตรวจสอบข้อมูล
      </button>
    </form>
  </div>

  <!-- Instructions card -->
  <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
      <h3 class="font-black text-slate-800 flex items-center gap-2">
        <i class="fas fa-info-circle text-blue-500"></i>วิธีเตรียมไฟล์ CSV
      </h3>
    </div>
    <div class="p-6 space-y-4">
      <div class="flex gap-3">
        <div class="w-7 h-7 bg-cyan-100 text-cyan-700 rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">1</div>
        <div>
          <p class="font-black text-slate-700 text-sm">เปิดไฟล์ Excel จาก สพม.</p>
          <p class="text-xs text-slate-400 mt-0.5">DATA สพม.ศรีสะเกษ ยโสธร ละลมวิทยา.xlsx</p>
        </div>
      </div>
      <div class="flex gap-3">
        <div class="w-7 h-7 bg-cyan-100 text-cyan-700 rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">2</div>
        <div>
          <p class="font-black text-slate-700 text-sm">รัน PowerShell script เพื่อ export CSV</p>
          <div class="mt-1 bg-slate-800 text-green-300 rounded-xl p-3 text-[10px] font-mono leading-relaxed">
            ไฟล์ CSV อยู่ที่:<br>
            <span class="text-white">Downloads\lalomwittaya_devices.csv</span>
          </div>
        </div>
      </div>
      <div class="flex gap-3">
        <div class="w-7 h-7 bg-cyan-100 text-cyan-700 rounded-xl flex items-center justify-center text-sm font-black flex-shrink-0">3</div>
        <div>
          <p class="font-black text-slate-700 text-sm">อัปโหลดไฟล์ด้านซ้าย</p>
          <p class="text-xs text-slate-400 mt-0.5">ระบบจะแสดงตัวอย่างก่อนนำเข้าจริง</p>
        </div>
      </div>

      <div class="mt-4 bg-slate-50 rounded-xl p-4 border border-slate-100">
        <p class="text-[10px] font-black text-slate-500 uppercase tracking-wider mb-2">คอลัมน์ที่ต้องมีในไฟล์ CSV</p>
        <div class="flex flex-wrap gap-1">
          <?php foreach (['type','box_no','serial_no','mac_address','imei','keyboard_box','keyboard_sn','sim_type','mobile_no','brand','model','firstname','lastname'] as $col): ?>
          <span class="px-1.5 py-0.5 bg-cyan-50 text-cyan-700 text-[10px] font-mono rounded"><?= $col ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="bg-emerald-50 rounded-xl p-3 border border-emerald-100">
        <p class="text-xs font-black text-emerald-700"><i class="fas fa-check-circle mr-1"></i>ข้อมูลที่จะถูกนำเข้า</p>
        <ul class="text-xs text-emerald-600 mt-1 space-y-0.5 list-disc list-inside">
          <li>อุปกรณ์ 318 เครื่อง (Serial, MAC, IMEI, Keyboard)</li>
          <li>ครู 33 คน → <code>cb_teachers</code></li>
          <li>นักเรียน 285 คน → <code>cb_students</code></li>
          <li>บันทึกการรับอุปกรณ์ → <code>cb_borrow_logs</code></li>
        </ul>
      </div>
    </div>
  </div>

</div>
<?php endif; ?>

<?php require_once '../components/layout_end.php'; ?>
