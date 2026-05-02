<?php
require_once 'functions.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'] ?? 0;
$pageTitle = 'จัดการข้อมูลนักเรียน';
$pageSubtitle = 'นำเข้าและจัดการรายชื่อนักเรียนผ่านระบบ Hybrid';
$activeSystem = 'attendance';

$msg = ''; $msgType = ''; $preview = []; $errors = []; $importCount = 0;

$classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

function makeNidMasked(string $nid): string {
    return substr($nid,0,1).'-'.substr($nid,1,4).'-'.substr($nid,5,5).'-'.substr($nid,10,2).'-'.substr($nid,12,1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';

    // -- Add single student --
    if ($do === 'add_student') {
        $sid = trim($_POST['student_id'] ?? '');
        if (preg_match('/^\d+$/', $sid)) $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
        $nm  = trim($_POST['name'] ?? '');
        $cls = trim($_POST['classroom'] ?? '');
        $nid = preg_replace('/\D/', '', trim($_POST['national_id'] ?? ''));

        if (!$sid || !$nm || !$cls || strlen($nid) !== 13) {
            $msg = !$sid || !$nm || !$cls ? 'กรุณากรอกข้อมูลให้ครบถ้วน' : 'เลขบัตรประชาชนต้องมี 13 หลัก';
            $msgType = 'error';
        } else {
            try {
                $hash   = password_hash($nid, PASSWORD_BCRYPT);
                $masked = makeNidMasked($nid);
                $st = $pdo->prepare("INSERT INTO att_students (student_id, name, classroom, academic_year, national_id_hash, national_id_masked)
                    VALUES (?,?,?,2569,?,?)
                    ON DUPLICATE KEY UPDATE name=VALUES(name), classroom=VALUES(classroom), academic_year=2569,
                    national_id_hash=VALUES(national_id_hash), national_id_masked=VALUES(national_id_masked)");
                $ok = $st->execute([$sid, $nm, $cls, $hash, $masked]);
                $msg = $ok ? "บันทึกข้อมูล '$nm' สำเร็จ" : 'เกิดข้อผิดพลาด';
                $msgType = $ok ? 'success' : 'error';
            } catch (Exception $e) { error_log($e->getMessage()); $msg = 'เกิดข้อผิดพลาด'; $msgType = 'error'; }
        }
    }

    // -- Edit student --
    if ($do === 'edit_student') {
        $dbid = (int)($_POST['student_db_id'] ?? 0);
        $nm   = trim($_POST['edit_name'] ?? '');
        $cls  = trim($_POST['edit_classroom'] ?? '');
        $nid  = preg_replace('/\D/', '', trim($_POST['edit_national_id'] ?? ''));

        if (!$dbid || !$nm || !$cls) {
            $msg = 'กรุณากรอกข้อมูลให้ครบ'; $msgType = 'error';
        } else {
            try {
                if (strlen($nid) === 13) {
                    $hash   = password_hash($nid, PASSWORD_BCRYPT);
                    $masked = makeNidMasked($nid);
                    $pdo->prepare("UPDATE att_students SET name=?, classroom=?, national_id_hash=?, national_id_masked=? WHERE id=?")
                        ->execute([$nm, $cls, $hash, $masked, $dbid]);
                } else {
                    $pdo->prepare("UPDATE att_students SET name=?, classroom=? WHERE id=?")
                        ->execute([$nm, $cls, $dbid]);
                }
                $msg = 'อัปเดตข้อมูลสำเร็จ'; $msgType = 'success';
            } catch (Exception $e) { error_log($e->getMessage()); $msg = 'เกิดข้อผิดพลาด'; $msgType = 'error'; }
        }
    }

    // -- Delete student --
    if ($do === 'delete_student') {
        $dbid = (int)($_POST['student_db_id'] ?? 0);
        if ($dbid > 0) {
            try {
                $pdo->prepare("DELETE FROM att_students WHERE id=?")->execute([$dbid]);
                $msg = 'ลบข้อมูลสำเร็จ'; $msgType = 'success';
            } catch (Exception $e) { error_log($e->getMessage()); $msg = 'เกิดข้อผิดพลาด'; $msgType = 'error'; }
        }
    }

    // -- CSV Preview --
    if ($do === 'preview' && isset($_FILES['csvfile'])) {
        $file = $_FILES['csvfile'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $content = file_get_contents($file['tmp_name']);
            $content = ltrim($content, "\xEF\xBB\xBF");
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = @iconv('TIS-620', 'UTF-8//IGNORE', $content);
            }
            $delimiter = (strpos($content, ';') !== false && strpos($content, ',') === false) ? ';' : ',';
            $lines = explode("\n", str_replace("\r", "", $content));
            $is_first = true;
            foreach ($lines as $idx => $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                if ($is_first) { $is_first = false; continue; }
                if (count($row) < 2) continue;

                $sid = trim($row[0] ?? '');
                if (preg_match('/^\d+$/', $sid)) $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
                $name = trim($row[1] ?? '');
                $cls  = trim($row[2] ?? $_POST['classroom_fixed'] ?? '');
                $nid  = preg_replace('/\D/', '', trim($row[3] ?? ''));
                $nid_valid  = strlen($nid) === 13;
                $nid_masked = $nid_valid ? makeNidMasked($nid) : null;

                if ($sid && $name) {
                    $preview[] = [
                        'student_id'      => $sid,
                        'name'            => $name,
                        'classroom'       => $cls,
                        'row'             => $idx + 1,
                        'national_id'     => $nid_valid ? $nid : null,
                        'national_id_masked' => $nid_masked,
                        'nid_valid'       => $nid_valid,
                    ];
                }
            }
        }
    }

    // -- Import (hash NID here to avoid timeout during preview) --
    if ($do === 'import' && !empty($_POST['json_data'])) {
        set_time_limit(300);
        $data = json_decode($_POST['json_data'], true);
        if ($data) {
            $stmt = $pdo->prepare("INSERT INTO att_students (student_id, name, classroom, academic_year, national_id_hash, national_id_masked)
                VALUES (:sid, :name, :cls, 2569, :nid_hash, :nid_masked)
                ON DUPLICATE KEY UPDATE name=VALUES(name), classroom=VALUES(classroom), academic_year=2569,
                national_id_hash=IF(VALUES(national_id_hash) IS NOT NULL, VALUES(national_id_hash), national_id_hash),
                national_id_masked=IF(VALUES(national_id_masked) IS NOT NULL, VALUES(national_id_masked), national_id_masked)");
            $pdo->beginTransaction();
            try {
                foreach ($data as $p) {
                    $nid_hash = null;
                    if (!empty($p['national_id'])) {
                        $nid_hash = password_hash($p['national_id'], PASSWORD_BCRYPT);
                    }
                    $stmt->execute([
                        ':sid'        => $p['student_id'],
                        ':name'       => $p['name'],
                        ':cls'        => $p['classroom'],
                        ':nid_hash'   => $nid_hash,
                        ':nid_masked' => $p['national_id_masked'] ?? null,
                    ]);
                    $importCount++;
                }
                $pdo->commit();
                $msg = "นำเข้าและตั้งรหัสผ่านสำเร็จ $importCount รายการ";
                $msgType = 'success';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($e->getMessage());
                $msg = 'เกิดข้อผิดพลาดระหว่างนำเข้า'; $msgType = 'error';
            }
        }
    }
}

$filterCls = $_GET['cls'] ?? '';
$whereQ = $filterCls ? "WHERE classroom = " . $pdo->quote($filterCls) : '';
$students = $pdo->query("SELECT * FROM att_students $whereQ ORDER BY classroom, student_id LIMIT 500")->fetchAll();

require_once __DIR__ . '/components/layout_start.php';
?>

<style>
    .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); }
    .drop-zone { border: 2px dashed #cbd5e1; transition: all 0.3s; }
    .drop-zone:hover, .drop-zone.active { border-color: #4f46e5; background: #f5f3ff; }
</style>

<div class="space-y-10 mb-20">

    <!-- Top Tools -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- Import Controls -->
        <div class="lg:col-span-8 flex flex-col gap-6">
            <div class="glass-card rounded-[40px] p-8 shadow-xl shadow-indigo-100/30">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-14 h-14 bg-gradient-to-br from-indigo-600 to-blue-600 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-indigo-200/50">
                        <i class="bi bi-cloud-arrow-up-fill"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-slate-800">นำเข้าข้อมูลนักเรียนแบบกลุ่ม</h2>
                        <p class="text-sm text-slate-500">อัปเดตห้องเรียน รายชื่อ และรหัสผ่าน (เลขบัตร) ผ่านไฟล์ CSV</p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="importForm" class="space-y-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="preview">

                    <div id="dropZone" class="drop-zone rounded-[32px] p-10 flex flex-col items-center justify-center cursor-pointer group">
                        <i class="bi bi-file-earmark-spreadsheet text-5xl text-slate-300 group-hover:text-indigo-500 mb-4 transition-colors"></i>
                        <p class="text-slate-600 font-bold">ลากไฟล์ CSV มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</p>
                        <p class="text-[10px] text-slate-400 uppercase tracking-widest mt-2">รองรับ .CSV เท่านั้น (Thai/TIS-620 หรือ UTF-8)</p>
                        <input type="file" name="csvfile" id="fileInput" accept=".csv" class="hidden">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">บังคับห้องเรียน (ถ้ามี)</label>
                            <input type="text" name="classroom_fixed" placeholder="เช่น ม.1/1 (เว้นว่างไว้เพื่อใช้ค่าจากไฟล์)"
                                   class="w-full bg-white border border-slate-200 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-slate-900 text-white rounded-2xl px-8 py-4 font-black text-sm hover:bg-black hover:-translate-y-1 transition-all shadow-xl shadow-slate-200">
                                <i class="bi bi-search mr-2"></i> ตรวจสอบข้อมูลในไฟล์
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Preview Results -->
            <?php if (!empty($preview)):
                $missingNid = array_filter($preview, fn($p) => !$p['nid_valid']);
            ?>
            <div class="glass-card rounded-[40px] overflow-hidden shadow-2xl border-2 border-indigo-500/20">
                <div class="px-8 py-6 bg-gradient-to-r from-indigo-600 to-blue-600 text-white flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-xl">
                            <i class="bi bi-ui-checks"></i>
                        </div>
                        <div>
                            <h3 class="font-black text-lg">ตรวจสอบความถูกต้อง</h3>
                            <p class="text-xs opacity-80">
                                พบ <span class="font-black"><?= count($preview) ?></span> รายการ
                                <?php if (count($missingNid) > 0): ?>
                                · <span class="text-amber-300 font-black"><?= count($missingNid) ?> รายการไม่มีเลขบัตร</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="import">
                        <input type="hidden" name="json_data" value='<?= htmlspecialchars(json_encode($preview, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
                        <button type="submit" class="bg-white text-indigo-600 px-8 py-3 rounded-2xl font-black text-sm hover:scale-105 active:scale-95 transition-all shadow-xl shadow-indigo-900/20"
                                onclick="return confirmImport(<?= count($missingNid) ?>)">
                            <i class="bi bi-check2-circle mr-2"></i> ยืนยันการนำเข้า
                        </button>
                    </form>
                </div>
                <div class="max-h-[500px] overflow-y-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 sticky top-0 z-10">
                            <tr class="text-left">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ลำดับ</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">รหัส</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ชื่อ-นามสกุล</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ห้อง</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">เลขบัตร (รหัสผ่าน)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($preview as $p): ?>
                            <tr class="hover:bg-indigo-50/50 transition-colors <?= !$p['nid_valid'] ? 'bg-amber-50/50' : '' ?>">
                                <td class="px-6 py-4 text-xs font-bold text-slate-400">#<?= $p['row'] ?></td>
                                <td class="px-6 py-4 font-mono font-black text-indigo-600 text-xs"><?= htmlspecialchars($p['student_id']) ?></td>
                                <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($p['name']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-black border border-emerald-100">
                                        <?= htmlspecialchars($p['classroom']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($p['nid_valid']): ?>
                                    <span class="font-mono text-xs text-slate-600"><?= htmlspecialchars($p['national_id_masked']) ?></span>
                                    <?php else: ?>
                                    <span class="px-2 py-1 rounded-lg bg-amber-100 text-amber-700 text-[10px] font-black">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>ไม่มีเลขบัตร
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right column -->
        <div class="lg:col-span-4 flex flex-col gap-6">

            <!-- Single Add Card -->
            <div class="glass-card rounded-[40px] p-8 shadow-xl shadow-slate-200/50 border border-white">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <h3 class="font-black text-slate-800">เพิ่มรายบุคคล</h3>
                </div>
                <form method="POST" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="add_student">
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">เลขประจำตัว</label>
                        <input type="text" name="student_id" required placeholder="เช่น 04684"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-3.5 text-sm font-bold focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" required placeholder="ชื่อ-สกุล"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-3.5 text-sm font-bold focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">ห้องเรียน</label>
                        <input type="text" name="classroom" required placeholder="ม.1/1"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-3.5 text-sm font-bold focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">เลขบัตรประชาชน 13 หลัก</label>
                        <input type="text" name="national_id" required maxlength="13" inputmode="numeric"
                               placeholder="1234567890123"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-3.5 text-sm font-bold font-mono focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all">
                        <p class="text-[10px] text-slate-400 ml-4">ใช้เป็นรหัสผ่านเข้าระบบนักเรียน</p>
                    </div>
                    <button type="submit" class="w-full bg-emerald-500 text-white rounded-2xl py-4 font-black text-sm hover:bg-emerald-600 hover:-translate-y-1 transition-all shadow-xl shadow-emerald-100">
                        บันทึกนักเรียน
                    </button>
                </form>
            </div>

            <!-- Help Card -->
            <div class="bg-indigo-600 rounded-[40px] p-8 text-white shadow-xl shadow-indigo-200 relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 text-9xl text-white/10 rotate-12"><i class="bi bi-info-circle"></i></div>
                <h3 class="font-black text-lg mb-4">รูปแบบไฟล์ CSV</h3>
                <ul class="text-xs space-y-3 font-medium opacity-90">
                    <li class="flex gap-2"><i class="bi bi-check-circle"></i> คอลัมน์ 1: เลขประจำตัว (4-5 หลัก)</li>
                    <li class="flex gap-2"><i class="bi bi-check-circle"></i> คอลัมน์ 2: ชื่อ-นามสกุล</li>
                    <li class="flex gap-2"><i class="bi bi-check-circle"></i> คอลัมน์ 3: ห้องเรียน (เช่น ม.1/1)</li>
                    <li class="flex gap-2"><i class="bi bi-shield-lock-fill text-amber-300"></i> <span><strong>คอลัมน์ 4: เลขบัตรประชาชน 13 หลัก</strong><br>ใช้เป็นรหัสผ่านเข้าระบบนักเรียน</span></li>
                    <li class="flex gap-2"><i class="bi bi-exclamation-circle text-amber-300"></i> หากมีชื่อเดิมอยู่แล้ว ระบบจะอัปเดตห้องและรหัสผ่านให้</li>
                </ul>
                <a href="data:text/csv;charset=utf-8,%EF%BB%BFstudent_id%2Cname%2Cclassroom%2Cnational_id%0A04684%2C%E0%B9%80%E0%B8%94%E0%B9%87%E0%B8%81%E0%B8%82%E0%B8%B2%E0%B8%A2%E0%B8%81%E0%B8%B4%E0%B8%95%E0%B8%95%E0%B8%B4%E0%B8%A8%E0%B8%B1%E0%B8%81%E0%B8%94%E0%B8%B4%E0%B9%8C%2C%E0%B8%A1.1%2F1%2C1234567890123"
                   download="student_template.csv"
                   class="mt-6 inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                    <i class="bi bi-download"></i> โหลดไฟล์ตัวอย่าง
                </a>
            </div>
        </div>
    </div>

    <!-- Main List Table -->
    <div class="glass-card rounded-[40px] overflow-hidden shadow-xl shadow-slate-200/50">
        <div class="px-8 py-8 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-6">
            <h3 class="text-xl font-black text-slate-800 flex items-center gap-3">
                <i class="bi bi-people-fill text-indigo-500"></i> รายชื่อนักเรียนปัจจุบัน
            </h3>
            <form method="GET" class="flex gap-2 w-full md:w-auto">
                <input type="text" name="cls" value="<?= htmlspecialchars($filterCls) ?>" placeholder="กรองตามห้อง..."
                       class="flex-1 md:w-48 bg-slate-50 border border-slate-200 rounded-2xl px-5 py-2 text-xs font-bold outline-none focus:ring-4 focus:ring-indigo-500/10">
                <button class="bg-slate-900 text-white px-6 py-2 rounded-2xl text-xs font-black hover:bg-black transition-all shadow-lg">กรอง</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50/50">
                    <tr class="text-left">
                        <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">เลขประจำตัว</th>
                        <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ชื่อ-นามสกุล</th>
                        <th class="px-8 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">ห้องเรียน</th>
                        <th class="px-8 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">รหัสผ่าน (เลขบัตร)</th>
                        <th class="px-8 py-4 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach($students as $s): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors group">
                        <td class="px-8 py-4 font-mono font-black text-indigo-600 text-xs tracking-tighter"><?= htmlspecialchars($s['student_id']) ?></td>
                        <td class="px-8 py-4 font-bold text-slate-700"><?= htmlspecialchars($s['name']) ?></td>
                        <td class="px-8 py-4 text-center">
                            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 font-black text-[10px]"><?= htmlspecialchars($s['classroom']) ?></span>
                        </td>
                        <td class="px-8 py-4 text-center">
                            <?php if (!empty($s['national_id_masked'])): ?>
                            <span class="font-mono text-xs text-emerald-600 font-bold">
                                <i class="bi bi-shield-lock-fill me-1"></i><?= htmlspecialchars($s['national_id_masked']) ?>
                            </span>
                            <?php else: ?>
                            <span class="px-2 py-1 rounded-lg bg-rose-50 text-rose-500 text-[10px] font-black">
                                <i class="bi bi-x-circle-fill me-1"></i>ยังไม่มี
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-8 py-4 text-right">
                            <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick='openEditModal(<?= htmlspecialchars(json_encode($s, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)'
                                        class="p-2 text-amber-500 hover:bg-amber-50 rounded-xl transition-all">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button onclick="deleteStudent(<?= (int)$s['id'] ?>, '<?= addslashes(htmlspecialchars($s['name'])) ?>')"
                                        class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition-all">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="5" class="px-8 py-20 text-center">
                            <i class="bi bi-inbox text-5xl text-slate-200 mb-4 block"></i>
                            <p class="text-slate-400 font-bold">ไม่พบข้อมูลนักเรียน</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[100] flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-[40px] w-full max-w-sm p-10 shadow-2xl border border-white/50">
        <div class="flex items-center gap-4 mb-8">
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-2xl">
                <i class="bi bi-pencil-fill"></i>
            </div>
            <h3 class="font-black text-xl text-slate-800">แก้ไขข้อมูล</h3>
        </div>
        <form method="POST" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="edit_student">
            <input type="hidden" name="student_db_id" id="edit-id">
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">ชื่อ-นามสกุล</label>
                <input type="text" name="edit_name" id="edit-name" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">ห้องเรียน</label>
                <input type="text" name="edit_classroom" id="edit-cls" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">เลขบัตรประชาชน (เว้นว่าง = ไม่เปลี่ยน)</label>
                <input type="text" name="edit_national_id" id="edit-nid" maxlength="13" inputmode="numeric"
                       placeholder="กรอกเพื่อเปลี่ยนรหัสผ่าน"
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm font-bold font-mono focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all">
                <p id="edit-nid-current" class="text-[10px] text-slate-400 ml-4 mt-1"></p>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeEditModal()" class="flex-1 px-6 py-4 rounded-2xl text-sm font-black text-slate-400 hover:bg-slate-50 transition-all">ยกเลิก</button>
                <button type="submit" class="flex-1 bg-slate-900 text-white px-6 py-4 rounded-2xl text-sm font-black shadow-xl shadow-slate-200 hover:bg-black hover:-translate-y-1 transition-all">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete form (hidden) -->
<form id="deleteForm" method="POST" class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="delete_student">
    <input type="hidden" name="student_db_id" id="delete-id">
</form>

<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');

    dropZone.onclick = () => fileInput.click();
    fileInput.onchange = () => { if(fileInput.files.length) document.getElementById('importForm').submit(); };
    dropZone.ondragover = (e) => { e.preventDefault(); dropZone.classList.add('active'); };
    dropZone.ondragleave = () => dropZone.classList.remove('active');
    dropZone.ondrop = (e) => {
        e.preventDefault(); dropZone.classList.remove('active');
        fileInput.files = e.dataTransfer.files;
        document.getElementById('importForm').submit();
    };

    function confirmImport(missingCount) {
        if (missingCount > 0) {
            return confirm(`มีนักเรียน ${missingCount} คนที่ไม่มีเลขบัตรประชาชน จะไม่สามารถเข้าระบบนักเรียนได้\nยืนยันการนำเข้าต่อไปหรือไม่?`);
        }
        return true;
    }

    function openEditModal(s) {
        document.getElementById('edit-id').value = s.id;
        document.getElementById('edit-name').value = s.name;
        document.getElementById('edit-cls').value = s.classroom;
        document.getElementById('edit-nid').value = '';
        document.getElementById('edit-nid-current').textContent =
            s.national_id_masked ? 'รหัสปัจจุบัน: ' + s.national_id_masked : 'ยังไม่มีรหัสผ่าน';
        document.getElementById('edit-modal').classList.remove('hidden');
    }
    function closeEditModal() { document.getElementById('edit-modal').classList.add('hidden'); }

    function deleteStudent(id, name) {
        Swal.fire({
            title: 'ลบข้อมูล?', text: `ต้องการลบรายชื่อ "${name}" ออกจากระบบหรือไม่?`, icon: 'warning',
            showCancelButton: true, confirmButtonText: 'ใช่, ลบออก', cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#e11d48', cancelButtonColor: '#94a3b8',
            customClass: { popup: 'rounded-[32px]', confirmButton: 'rounded-xl px-6 py-3', cancelButton: 'rounded-xl px-6 py-3' }
        }).then(r => {
            if (r.isConfirmed) {
                document.getElementById('delete-id').value = id;
                document.getElementById('deleteForm').submit();
            }
        });
    }
</script>

<?php
$escapedMsg = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
if ($msg) echo "<script>Swal.fire({ icon: '$msgType', title: 'แจ้งเตือน', text: '$escapedMsg', timer: 4000, showConfirmButton: false, customClass: { popup: 'rounded-[32px]' } });</script>";
require_once 'components/layout_end.php';
?>
