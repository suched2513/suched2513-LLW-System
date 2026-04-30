<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: /login.php'); exit(); }

try {
    $pdo = getPdo();
} catch(Throwable $e) {
    die('<pre style="color:red;font-size:14px;padding:20px">DB ERROR: '.$e->getMessage().'</pre>');
}
$msg = ''; $type = ''; $count = 0; $preview = [];

// ── IMPORT ──
if (($_POST['action'] ?? '') === 'import' && !empty($_POST['json_data'])) {
    csrf_verify();
    $rows = json_decode($_POST['json_data'], true) ?? [];
    try {
        $stmt = $pdo->prepare("INSERT INTO att_students (student_id,name,classroom) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),classroom=VALUES(classroom)");
        $pdo->beginTransaction();
        foreach ($rows as $r) { $stmt->execute([$r[0],$r[1],$r[2]]); $count++; }
        $pdo->commit();
        $msg = "✅ นำเข้าสำเร็จ {$count} รายการ"; $type = 'ok';
    } catch(Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        $msg = '❌ เกิดข้อผิดพลาด: '.$e->getMessage(); $type = 'err';
    }
}

// ── SYNC ALL MODULES ──
if (($_POST['action'] ?? '') === 'sync_all') {
    try {
        $students = $pdo->query("
            SELECT student_id, name, classroom FROM att_students
            WHERE student_id IS NOT NULL AND TRIM(student_id) != ''
            GROUP BY student_id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtAsm = $pdo->prepare("INSERT INTO assembly_students (student_id,name,classroom) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),classroom=VALUES(classroom)");
        $stmtBeh = $pdo->prepare("INSERT INTO beh_students (student_id,name,level,room,status) VALUES(?,?,?,?,'active') ON DUPLICATE KEY UPDATE name=VALUES(name),level=VALUES(level),room=VALUES(room),status='active'");
        $stmtCb  = $pdo->prepare("INSERT INTO cb_students (student_id,name,class_name) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),class_name=VALUES(class_name)");

        $pdo->beginTransaction();
        $n = 0;
        foreach ($students as $s) {
            $sid=$s['student_id']; $name=$s['name']; $cls=$s['classroom'];
            $level=$cls; $room='';
            if(strpos($cls,'/')!==false){[$level,$room]=array_map('trim',explode('/',$cls,2));}
            $stmtAsm->execute([$sid,$name,$cls]);
            $stmtBeh->execute([$sid,$name,$level,$room]);
            $stmtCb->execute([$sid,$name,$cls]);
            $n++;
        }
        $pdo->commit();
        $msg = "✅ Sync สำเร็จ! อัพเดท {$n} คน → Assembly, Behavior, Chromebook"; $type = 'ok';
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $msg = "Sync Error: " . $e->getMessage(); $type = 'err';
    }
}


if (($_POST['action'] ?? '') === 'preview' && isset($_FILES['csv'])) {
    try {
        $f = $_FILES['csv'];
        if ($f['error'] !== 0) {
            $msg = "Upload error code: " . $f['error']; $type = 'err';
        } else {
            $raw = file_get_contents($f['tmp_name']);
            // Strip BOM and normalize encoding
            $raw = ltrim($raw, "\xEF\xBB\xBF");
            // If not valid UTF-8, try iconv from TIS-620 (Thai Excel)
            if (!mb_check_encoding($raw, 'UTF-8')) {
                $converted = @iconv('TIS-620', 'UTF-8//IGNORE', $raw);
                if ($converted !== false && strlen($converted) > 10) $raw = $converted;
            }
            $delim = (substr_count($raw,';') > substr_count($raw,',')) ? ';' : ',';
            $lines = explode("\n", str_replace("\r","",$raw));
            $skip = true;
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line) continue;
                if ($skip) { $skip = false; continue; }
                $cols = str_getcsv($line, $delim);
                $sid = trim($cols[0] ?? '');
                $name = trim($cols[1] ?? '');
                $cls = trim($cols[2] ?? '');
                if (!$sid || !$name) continue;
                if (preg_match('/^\d+$/', $sid) && strlen($sid) < 5) $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
                $preview[] = [$sid, $name, $cls];
            }
            $msg = count($preview) > 0 ? "พบ ".count($preview)." รายการ" : "ไม่พบข้อมูล";
            $type = count($preview) > 0 ? 'preview' : 'err';
        }
    } catch(Throwable $e) {
        $msg = "ERROR: " . $e->getMessage() . " [" . basename($e->getFile()) . ":" . $e->getLine() . "]";
        $type = 'err';
    }
}

try {
    $total = $pdo->query("SELECT COUNT(*) FROM att_students")->fetchColumn();
} catch(Throwable $e) {
    die('<pre style="color:red;padding:20px">QUERY ERROR: '.$e->getMessage().'</pre>');
}
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>อัพโหลดรายชื่อนักเรียน | LLW</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>body{font-family:'Prompt',sans-serif}</style>
</head>
<body class="bg-slate-50 min-h-screen p-4 sm:p-8">
<div class="max-w-3xl mx-auto space-y-6">

  <!-- Header -->
  <div class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-3xl p-6 text-white shadow-xl shadow-indigo-200">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black">📤 อัพโหลดรายชื่อนักเรียน</h1>
        <p class="text-indigo-100 text-sm mt-1">นำเข้าข้อมูลนักเรียนทั้งโรงเรียน — ระบบจะอัพเดทข้อมูลที่มีอยู่โดยอัตโนมัติ</p>
      </div>
      <div class="text-right">
        <p class="text-4xl font-black"><?= number_format($total) ?></p>
        <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider">คนในระบบ</p>
      </div>
    </div>
  </div>

  <!-- Message -->
  <?php if ($msg): ?>
  <div class="p-4 rounded-2xl font-bold text-sm <?= $type==='ok' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($type==='err' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-blue-50 text-blue-700 border border-blue-200') ?>">
    <?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <!-- Format Info -->
  <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
    <p class="font-black text-slate-700 mb-2">รูปแบบ CSV ที่รองรับ</p>
    <code class="bg-slate-100 rounded-xl px-4 py-2 text-sm text-indigo-600 font-mono block">student_id, name, classroom</code>
    <p class="text-xs text-slate-400 mt-2">ตัวอย่าง: <span class="font-mono">04849, เด็กชายกฤษณะ ยกจำนวน, ม.1/1</span></p>
    <p class="text-xs text-slate-400 mt-1">✓ รองรับ Excel (Thai) และ Google Sheets (UTF-8) ✓ รหัส 4 หลักจะเติม 0 อัตโนมัติเป็น 5 หลัก</p>
  </div>

  <?php if ($type === 'preview' && count($preview) > 0): ?>
  <!-- Preview Table -->
  <div class="bg-white rounded-2xl shadow-xl shadow-blue-100 border border-blue-100 overflow-hidden">
    <div class="bg-blue-600 px-6 py-4 text-white flex items-center justify-between">
      <div>
        <h2 class="font-black">ตรวจสอบข้อมูล <?= count($preview) ?> รายการ</h2>
        <p class="text-xs text-blue-200 mt-0.5">กด "ยืนยันนำเข้า" เพื่อบันทึกลงฐานข้อมูล</p>
      </div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import">
        <input type="hidden" name="json_data" value="<?= htmlspecialchars(json_encode($preview, JSON_UNESCAPED_UNICODE)) ?>">
        <button class="bg-white text-blue-600 px-6 py-2 rounded-2xl font-black text-sm hover:bg-blue-50 transition shadow-lg">
          ✅ ยืนยันนำเข้า <?= count($preview) ?> คน
        </button>
      </form>
    </div>
    <div class="overflow-auto max-h-96">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 sticky top-0">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-black text-slate-400 uppercase">#</th>
            <th class="px-4 py-2 text-left text-xs font-black text-slate-400 uppercase">รหัส</th>
            <th class="px-4 py-2 text-left text-xs font-black text-slate-400 uppercase">ชื่อ-สกุล</th>
            <th class="px-4 py-2 text-left text-xs font-black text-slate-400 uppercase">ห้อง</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach($preview as $i=>$r): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-2 text-slate-400 text-xs"><?= $i+1 ?></td>
            <td class="px-4 py-2 font-mono font-bold text-indigo-600"><?= htmlspecialchars($r[0]) ?></td>
            <td class="px-4 py-2 font-semibold text-slate-700"><?= htmlspecialchars($r[1]) ?></td>
            <td class="px-4 py-2"><span class="bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-lg text-xs font-bold"><?= htmlspecialchars($r[2]) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <!-- Upload Form -->
  <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
    <h2 class="font-black text-slate-700 mb-4">เลือกไฟล์ CSV</h2>
    <form method="POST" enctype="multipart/form-data" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="preview">
      <div class="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center hover:border-indigo-400 transition-colors">
        <p class="text-slate-400 text-sm mb-3">เลือกไฟล์ CSV จากเครื่องของคุณ</p>
        <input type="file" name="csv" accept=".csv" required class="block mx-auto text-sm text-slate-500 file:mr-4 file:py-2 file:px-6 file:rounded-xl file:border-0 file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
      </div>
      <button type="submit" class="w-full bg-indigo-600 text-white font-black py-3.5 rounded-2xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 text-lg">
        🔍 ตรวจสอบข้อมูลก่อนนำเข้า
      </button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Sync All Modules -->
  <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl p-5 border border-emerald-200">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h3 class="font-black text-emerald-800">🔄 Sync ข้อมูลไปทุกระบบ</h3>
        <p class="text-xs text-emerald-600 mt-1">อัพเดทรายชื่อนักเรียนใน Assembly, Behavior, Chromebook ให้ตรงกับ att_students</p>
      </div>
      <form method="POST" onsubmit="return confirm('ยืนยัน Sync รายชื่อไปทุกระบบ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="sync_all">
        <button type="submit" class="bg-emerald-600 text-white px-6 py-3 rounded-2xl font-black text-sm hover:bg-emerald-700 transition shadow-lg shadow-emerald-200 whitespace-nowrap">
          🔄 Sync ทุกระบบเดี๋ยวนี้
        </button>
      </form>
    </div>
  </div>

  <div class="text-center">
    <a href="/central_dashboard.php" class="text-slate-400 text-sm hover:text-indigo-600">← กลับหน้าหลัก</a>
  </div>
</div>
</body>
</html>
