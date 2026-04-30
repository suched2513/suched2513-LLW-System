<?php
/**
 * import_students.php — หน้า import นักเรียนแบบ standalone
 * ใช้ layout กลาง (components/) ไม่ผ่าน attendance_system
 */
session_start();
require_once __DIR__ . '/config.php';
$pdo = getPdo();

// Auth guard — super_admin only
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'att_teacher'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();
$msg = ''; $msgType = ''; $preview = []; $importCount = 0;

// ── Add single student ──
if (($_POST['do'] ?? '') === 'add_student') {
    $sid = trim($_POST['student_id'] ?? '');
    if (preg_match('/^\d+$/', $sid)) $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
    $nm  = trim($_POST['name'] ?? '');
    $cls = trim($_POST['classroom'] ?? '');
    if (!$sid || !$nm || !$cls) {
        $msg = 'กรุณากรอกข้อมูลให้ครบถ้วน'; $msgType = 'error';
    } else {
        try {
            $st = $pdo->prepare("INSERT INTO att_students (student_id, name, classroom) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), classroom=VALUES(classroom)");
            $st->execute([$sid, $nm, $cls]);
            $msg = "เพิ่ม/อัปเดต '$nm' สำเร็จ"; $msgType = 'success';
        } catch (Exception $e) {
            error_log($e->getMessage());
            $msg = 'เกิดข้อผิดพลาด'; $msgType = 'error';
        }
    }
}

// ── Import CSV ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'import' && !empty($_POST['json_data'])) {
    $data = json_decode($_POST['json_data'], true);
    if ($data) {
        try {
            $stmt = $pdo->prepare("INSERT INTO att_students (student_id, name, classroom) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), classroom=VALUES(classroom)");
            $pdo->beginTransaction();
            foreach ($data as $p) {
                $stmt->execute([$p['student_id'], $p['name'], $p['classroom']]);
                $importCount++;
            }
            $pdo->commit();
            $msg = "นำเข้าสำเร็จ $importCount รายการ"; $msgType = 'success';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            $msg = 'เกิดข้อผิดพลาดระหว่างนำเข้า'; $msgType = 'error';
        }
    }
}

// ── Preview CSV ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvfile']) && ($_POST['do'] ?? '') === 'preview') {
    $file = $_FILES['csvfile'];
    if ($file['error'] === UPLOAD_ERR_OK && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'csv') {
        $content = file_get_contents($file['tmp_name']);
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-874', 'TIS-620'], true);
        if ($encoding !== 'UTF-8') $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'Windows-874');
        // Remove BOM
        $content = ltrim($content, "\xEF\xBB\xBF");
        $delimiter = (strpos($content, ';') !== false && strpos($content, ',') === false) ? ';' : ',';
        $lines = explode("\n", str_replace("\r", "", $content));
        $first = true;
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line, $delimiter);
            if ($first) { $first = false; continue; }
            if (count($row) < 2) continue;
            $sid  = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');
            $cls  = trim($row[2] ?? '');
            if (!$sid || !$name) continue;
            if (preg_match('/^\d+$/', $sid)) $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
            $classroom_fixed = trim($_POST['classroom_fixed'] ?? '');
            if ($classroom_fixed) $cls = $classroom_fixed;
            $preview[] = ['student_id' => $sid, 'name' => $name, 'classroom' => $cls];
        }
        if (empty($preview)) { $msg = 'ไม่พบข้อมูลในไฟล์'; $msgType = 'warning'; }
    } else {
        $msg = 'กรุณาเลือกไฟล์ .csv ที่ถูกต้อง'; $msgType = 'error';
    }
}

// ── Delete student ──
if (($_POST['do'] ?? '') === 'delete_student') {
    $delId = (int)($_POST['student_db_id'] ?? 0);
    if ($delId) {
        $pdo->prepare("DELETE FROM att_students WHERE id=?")->execute([$delId]);
    }
    header('Location: /import_students.php'); exit();
}

// ── List students ──
$filterCls  = trim($_GET['cls'] ?? '');
$whereClause = $filterCls ? "WHERE classroom = ?" : '';
$stmt       = $filterCls
    ? $pdo->prepare("SELECT * FROM att_students $whereClause ORDER BY classroom, student_id")
    : $pdo->prepare("SELECT * FROM att_students ORDER BY classroom, student_id");
if ($filterCls) $stmt->execute([$filterCls]);
else $stmt->execute();
$students = $stmt->fetchAll();

$classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students WHERE classroom != '' ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);
$totalCount = $pdo->query("SELECT COUNT(*) FROM att_students")->fetchColumn();

$pageTitle    = 'จัดการข้อมูลนักเรียน';
$pageSubtitle = 'นำเข้าและจัดการรายชื่อนักเรียนทั้งโรงเรียน';
$activeSystem = 'attendance';
require_once __DIR__ . '/components/layout_start.php';
?>

<div class="flex flex-col gap-8">

    <?php if ($msg): ?>
    <div class="p-4 rounded-2xl font-bold text-sm flex items-center gap-3
        <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($msgType === 'error' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200') ?>">
        <i class="bi bi-<?= $msgType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> text-xl"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- KPI -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-indigo-500 to-blue-600 rounded-2xl p-5 text-white shadow-xl shadow-indigo-200/50 col-span-2 sm:col-span-1">
            <p class="text-xs font-bold opacity-80 uppercase tracking-wider">นักเรียนทั้งหมด</p>
            <p class="text-4xl font-black mt-1"><?= number_format($totalCount) ?></p>
            <p class="text-xs opacity-70 mt-1">คนในระบบ</p>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">ห้องเรียน</p>
            <p class="text-3xl font-black text-slate-800 mt-1"><?= count($classrooms) ?></p>
            <p class="text-xs text-slate-400 mt-1">ห้อง</p>
        </div>
        <div class="col-span-2 bg-blue-50 rounded-2xl p-5 border border-blue-100 flex items-center gap-4">
            <i class="bi bi-info-circle-fill text-blue-500 text-2xl"></i>
            <div>
                <p class="text-xs font-bold text-blue-700">รูปแบบ CSV ที่รองรับ</p>
                <p class="text-xs text-blue-600 font-mono mt-1">student_id, name, classroom</p>
                <a href="data:text/csv;charset=utf-8,%EF%BB%BFstudent_id%2Cname%2Cclassroom%0A01001%2C%E0%B9%80%E0%B8%94%E0%B9%87%E0%B8%81%E0%B8%8A%E0%B8%B2%E0%B8%A2%E0%B8%AA%E0%B8%A1%E0%B8%8A%E0%B8%B2%E0%B8%A2%20%E0%B9%83%E0%B8%88%E0%B8%94%E0%B8%B5%2C%E0%B8%A1.1%2F1"
                   download="template_students.csv"
                   class="text-[10px] font-bold text-blue-500 underline hover:text-blue-700">
                    ⬇ ดาวน์โหลด Template
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Add Single -->
        <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 p-6 border border-slate-100">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <h3 class="font-black text-slate-800">เพิ่มนักเรียนรายบุคคล</h3>
            </div>
            <form method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="add_student">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">รหัสนักเรียน</label>
                    <input type="text" name="student_id" required placeholder="01001"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">ชื่อ-สกุล</label>
                    <input type="text" name="name" required placeholder="เด็กชายสมชาย ใจดี"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">ห้องเรียน</label>
                    <input type="text" name="classroom" required placeholder="ม.1/1" list="cls-list"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                    <datalist id="cls-list">
                        <?php foreach($classrooms as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-2xl font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 hover:scale-[1.02] transition-all">
                    <i class="bi bi-plus-circle-fill mr-2"></i>เพิ่มนักเรียน
                </button>
            </form>
        </div>

        <!-- Import CSV -->
        <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 p-6 border border-slate-100">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl">
                    <i class="bi bi-cloud-arrow-up-fill"></i>
                </div>
                <h3 class="font-black text-slate-800">นำเข้าด้วยไฟล์ CSV</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="preview">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">เลือกไฟล์ CSV</label>
                    <input type="file" name="csvfile" accept=".csv" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-3 text-xs file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">
                        กำหนดห้องเรียน <span class="text-slate-300 font-normal">(เว้นว่างเพื่อใช้จากไฟล์)</span>
                    </label>
                    <input type="text" name="classroom_fixed" placeholder="เช่น ม.2/2 (ถ้าต้องการกำหนดทั้งหมดให้ห้องเดียว)"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
                <button type="submit" class="w-full bg-slate-800 text-white py-3 rounded-2xl font-bold shadow-lg hover:bg-slate-900 hover:scale-[1.02] transition-all">
                    <i class="bi bi-eye-fill mr-2"></i>ตรวจสอบข้อมูลก่อนนำเข้า
                </button>
            </form>
        </div>
    </div>

    <!-- Preview -->
    <?php if (!empty($preview)): ?>
    <div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 overflow-hidden ring-2 ring-blue-500/20">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <i class="bi bi-eye-fill text-xl"></i>
                <div>
                    <h3 class="font-bold">ตรวจสอบข้อมูลก่อนบันทึก</h3>
                    <p class="text-xs opacity-80">พบ <?= count($preview) ?> รายการ — กด "ยืนยัน" เพื่อนำเข้า</p>
                </div>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="import">
                <input type="hidden" name="json_data" value='<?= htmlspecialchars(json_encode($preview, JSON_UNESCAPED_UNICODE)) ?>'>
                <button type="submit" class="bg-white text-blue-600 px-6 py-2.5 rounded-2xl text-sm font-black hover:bg-blue-50 transition shadow-lg">
                    <i class="bi bi-check-circle-fill mr-2"></i>ยืนยันนำเข้า <?= count($preview) ?> คน
                </button>
            </form>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50 sticky top-0">
                    <tr>
                        <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-wider">รหัส</th>
                        <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-wider">ชื่อ-สกุล</th>
                        <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-wider">ห้อง</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-sm">
                    <?php foreach($preview as $i => $p): ?>
                    <tr class="hover:bg-blue-50/30 transition">
                        <td class="px-6 py-3 text-slate-400 text-xs"><?= $i+1 ?></td>
                        <td class="px-6 py-3 font-mono font-bold text-indigo-600"><?= htmlspecialchars($p['student_id']) ?></td>
                        <td class="px-6 py-3 font-semibold text-slate-700"><?= htmlspecialchars($p['name']) ?></td>
                        <td class="px-6 py-3">
                            <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600 text-xs font-bold"><?= htmlspecialchars($p['classroom']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Student List -->
    <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between flex-wrap gap-3">
            <div>
                <h3 class="font-black text-slate-800">รายชื่อนักเรียนในระบบ</h3>
                <p class="text-xs text-slate-400 mt-0.5">ทั้งหมด <?= number_format($totalCount) ?> คน <?= $filterCls ? "| กรอง: $filterCls" : '' ?></p>
            </div>
            <form method="GET" class="flex gap-2">
                <input type="text" name="cls" value="<?= htmlspecialchars($filterCls) ?>" placeholder="กรองห้องเรียน..."
                       list="filter-cls-list"
                       class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 text-xs outline-none focus:ring-2 focus:ring-indigo-400 w-40">
                <datalist id="filter-cls-list">
                    <?php foreach($classrooms as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>">
                    <?php endforeach; ?>
                </datalist>
                <button class="bg-indigo-600 text-white px-4 py-2 rounded-2xl text-xs font-bold hover:bg-indigo-700 transition">กรอง</button>
                <?php if($filterCls): ?>
                <a href="/import_students.php" class="bg-slate-100 text-slate-600 px-4 py-2 rounded-2xl text-xs font-bold hover:bg-slate-200 transition">ล้าง</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if(empty($students)): ?>
        <div class="p-16 text-center text-slate-300">
            <i class="bi bi-person-x text-5xl block mb-3"></i>
            <p class="font-bold">ไม่พบข้อมูลนักเรียน</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-wider">รหัส</th>
                        <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-wider">ชื่อ-สกุล</th>
                        <th class="px-6 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-wider">ห้องเรียน</th>
                        <th class="px-6 py-3 text-right text-[10px] font-black text-slate-400 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-sm">
                    <?php foreach($students as $s): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-3 font-mono font-bold text-indigo-600 text-xs"><?= htmlspecialchars($s['student_id']) ?></td>
                        <td class="px-6 py-3 font-semibold text-slate-700"><?= htmlspecialchars($s['name']) ?></td>
                        <td class="px-6 py-3 text-center">
                            <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 font-bold text-xs"><?= htmlspecialchars($s['classroom']) ?></span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <button onclick="deleteStudent(<?= $s['id'] ?>, '<?= addslashes($s['name']) ?>')"
                                    class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-xl transition">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form id="delete-form" method="POST" class="hidden">
    <input type="hidden" name="do" value="delete_student">
    <input type="hidden" name="student_db_id" id="delete-id">
</form>

<script>
function deleteStudent(id, name) {
    Swal.fire({
        title: 'ลบข้อมูล?',
        text: `ต้องการลบ "${name}" หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบออก',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#e11d48'
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-form').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
