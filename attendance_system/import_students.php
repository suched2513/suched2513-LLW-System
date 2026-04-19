<?php
require_once 'functions.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'];
$pageTitle = 'จัดการข้อมูลนักเรียน';
$pageSubtitle = 'นำเข้าและจัดการรายชื่อนักเรียน';

$msg = ''; $msgType = ''; $preview = []; $errors = []; $importCount = 0;

// -- Available classrooms for filter --
$classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

// -- Add single student --
if (($_POST['do'] ?? '') === 'add_student') {
    $sid = trim($_POST['student_id'] ?? '');
    // Standardize to 5 digits
    if (preg_match('/^\d+$/', $sid)) $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
    
    $nm = trim($_POST['name'] ?? '');
    $cls = trim($_POST['classroom'] ?? '');
    if (!$sid || !$nm || !$cls) { $msg = 'กรุณากรอกข้อมูลให้ครบถ้วน'; $msgType = 'error'; } 
    else {
        $st = $pdo->prepare("INSERT INTO att_students (student_id,name,classroom) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),classroom=VALUES(classroom)");
        $ok = $st->execute([$sid, $nm, $cls]);
        $msg = $ok ? "เพิ่ม/อัปเดตนักเรียน '$nm' สำเร็จ" : 'เกิดข้อผิดพลาด';
        $msgType = $ok ? 'success' : 'error';
    }
}
// -- Edit single student --
if (($_POST['do'] ?? '') === 'edit_student') {
    $id = (int)($_POST['student_db_id'] ?? 0);
    $nm = trim($_POST['edit_name'] ?? '');
    $cls = trim($_POST['edit_classroom'] ?? '');
    if ($id && $nm && $cls) {
        $pdo->prepare("UPDATE att_students SET name=?,classroom=? WHERE id=?")->execute([$nm,$cls,$id]);
        $msg = 'แก้ไขข้อมูลสำเร็จ'; $msgType = 'success';
    }
}
// -- Delete single student --
if (($_POST['do'] ?? '') === 'delete_student') {
    $delId = (int)($_POST['student_id'] ?? 0);
    if ($delId) { $pdo->prepare("DELETE FROM att_students WHERE id=?")->execute([$delId]); }
    header("Location: import_students.php"); exit();
}

// -- Handle CSV Upload / Import --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['csvfile']) || isset($_POST['json_data']))) {
    $classroom_filter = trim($_POST['classroom_fixed'] ?? '');
    $action = $_POST['do'] ?? 'preview';

    if ($action === 'import' && !empty($_POST['json_data'])) {
        // --- PROCESS IMPORT ---
        $data = json_decode($_POST['json_data'], true);
        if ($data) {
            $stmt = $pdo->prepare("INSERT INTO att_students (student_id, name, classroom) VALUES (:sid, :name, :cls) ON DUPLICATE KEY UPDATE name=VALUES(name), classroom=VALUES(classroom)");
            $pdo->beginTransaction();
            try {
                foreach ($data as $p) { 
                    $stmt->execute([':sid'=>$p['student_id'],':name'=>$p['name'],':cls'=>$p['classroom']]); 
                    $importCount++; 
                }
                $pdo->commit(); 
                $msg = "นำเข้าสำเร็จ $importCount รายการ"; 
                $msgType = 'success';
            } catch (Exception $e) { 
                if ($pdo->inTransaction()) $pdo->rollBack(); 
                $msg = 'ผิดพลาด: ' . $e->getMessage(); 
                $msgType = 'error'; 
            }
        }
    } elseif (isset($_FILES['csvfile'])) {
        // --- PROCESS PREVIEW ---
        $file = $_FILES['csvfile'];
        if ($file['error'] !== UPLOAD_ERR_OK) { 
            $msg = 'อัปโหลดไฟล์ล้มเหลว'; $msgType = 'error'; 
        } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') { 
            $msg = 'กรุณาเลือกไฟล์ .csv'; $msgType = 'error'; 
        } else {
            $content = file_get_contents($file['tmp_name']);
            
            // Detect Encoding (Excel Thai usually Windows-874)
            $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-874', 'TIS-620'], true);
            if ($encoding !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'Windows-874');
            }

            // Detect Delimiter
            $delimiter = ',';
            if (strpos($content, ';') !== false && strpos($content, ',') === false) $delimiter = ';';

            $lines = explode("\n", str_replace("\r", "", $content));
            $is_first = true;
            $rowNum = 0;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                $rowNum++;
                
                if ($is_first) { $is_first = false; continue; } // Skip header
                if (count($row) < 2) continue; // Need at least ID and Name

                $sid = trim($row[0] ?? '');
                // Standardize to 5 digits
                if (preg_match('/^\d+$/', $sid)) $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
                
                $name = trim($row[1] ?? '');
                $cls = trim($row[2] ?? '');

                if (!$sid || !$name) continue;
                if ($classroom_filter) $cls = $classroom_filter;

                $preview[] = ['student_id'=>$sid, 'name'=>$name, 'classroom'=>$cls, 'row'=>$rowNum];
            }
            
            if (empty($preview)) {
                $msg = 'ไม่พบข้อมูลที่สามารถนำเข้าได้ในไฟล์นี้';
                $msgType = 'warning';
            }
        }
    }
}

// -- Current students --
$filterCls = $_GET['cls'] ?? '';
$whereQ = $filterCls ? "WHERE classroom = " . $pdo->quote($filterCls) : '';
$students = $pdo->query("SELECT * FROM att_students $whereQ ORDER BY classroom, student_id")->fetchAll();

require_once 'components/layout_start.php';
?>

<div class="flex flex-col gap-8">

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- ── Add Single Student ── -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 flex flex-col gap-6 h-fit">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <h3 class="font-bold text-slate-800">เพิ่มนักเรียนรายบุคคล</h3>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="do" value="add_student">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">รหัสนักเรียน</label>
                    <input type="text" name="student_id" required placeholder="66001" 
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ชื่อ-สกุล</label>
                    <input type="text" name="name" required placeholder="สมชาย ใจดี" 
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ห้องเรียน</label>
                    <input type="text" name="classroom" required placeholder="ม.4/1" list="cls-list-add"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-100">
                    เพิ่มนักเรียน
                </button>
            </form>
        </div>

        <!-- ── Import CSV ── -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 flex flex-col gap-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl">
                    <i class="bi bi-cloud-arrow-up-fill"></i>
                </div>
                <h3 class="font-bold text-slate-800">นำเข้าด้วยไฟล์ CSV</h3>
            </div>
            
            <div class="p-4 bg-blue-50/50 rounded-2xl border border-blue-100/50">
                <p class="text-[10px] text-blue-600 font-bold uppercase tracking-wider mb-2">รูปแบบ CSV (ต้องเรียงคอลัมน์ตามนี้)</p>
                <div class="flex items-center justify-between text-[11px] text-blue-500 font-medium">
                    <span>student_id, name, classroom</span>
                    <a href="data:text/csv;charset=utf-8,%EF%BB%BFstudent_id%2Cname%2Cclassroom%0A66001%2C%E0%B8%AA%E0%B8%A1%E0%B8%8A%E0%B8%B2%E0%B8%A2%20%E0%B9%83%E0%B8%88%E0%B8%94%E0%B8%B5%2C%E0%B8%A1.4%2F1" download="template.csv" class="underline hover:text-blue-700">ดาวน์โหลดไฟล์ตัวอย่าง</a>
                </div>
                <p class="text-[9px] text-slate-400 mt-2">* รองรับไฟล์จาก Excel (ภาษาไทย) และ Google Sheets (UTF-8)</p>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">เลือกไฟล์ CSV</label>
                    <input type="file" name="csvfile" accept=".csv" required 
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-xs file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:bg-blue-600 file:text-white hover:file:bg-blue-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ระบุห้องเรียน (กรณีต้องการทับค่าในไฟล์)</label>
                    <input type="text" name="classroom_fixed" placeholder="เว้นว่างไว้หากต้องการใช้ค่าจากไฟล์"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <button type="submit" name="do" value="preview" class="w-full bg-slate-800 text-white py-3 rounded-xl font-bold hover:bg-slate-900 transition shadow-lg">
                    ตรวจสอบข้อมูล
                </button>
            </form>
        </div>
    </div>

    <!-- ── PREVIEW SECTION ── -->
    <?php if (!empty($preview)): ?>
    <div class="bg-white rounded-3xl shadow-xl shadow-blue-100/50 border border-slate-100 overflow-hidden ring-4 ring-blue-500/10">
        <div class="px-6 py-5 bg-blue-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <i class="bi bi-eye-fill text-xl"></i>
                <div>
                    <h3 class="font-bold">ตรวจสอบข้อมูลก่อนบันทึก</h3>
                    <p class="text-[10px] opacity-80 uppercase font-bold tracking-widest">พบข้อมูลทั้งหมด <?= count($preview) ?> รายการ</p>
                </div>
            </div>
            <form method="POST" class="flex gap-2">
                <input type="hidden" name="do" value="import">
                <input type="hidden" name="json_data" value='<?= json_encode($preview, JSON_UNESCAPED_UNICODE) ?>'>
                <button type="submit" class="bg-white text-blue-600 px-6 py-2 rounded-xl text-sm font-black hover:bg-blue-50 transition shadow-lg">
                    ยืนยันการนำเข้า
                </button>
            </form>
        </div>
        <div class="overflow-x-auto max-h-[400px]">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50 sticky top-0">
                    <tr>
                        <th class="px-6 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">รหัส</th>
                        <th class="px-6 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">ชื่อ-สกุล</th>
                        <th class="px-6 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">ห้องเรียน</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-sm">
                    <?php foreach($preview as $p): ?>
                    <tr class="hover:bg-blue-50/30 transition">
                        <td class="px-6 py-3 font-mono font-bold text-blue-600"><?= htmlspecialchars($p['student_id']) ?></td>
                        <td class="px-6 py-3 font-semibold text-slate-700"><?= htmlspecialchars($p['name']) ?></td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-black"><?= htmlspecialchars($p['classroom']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── LIST ── -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-50 flex items-center justify-between">
            <h3 class="font-bold text-slate-800">รายชื่อนักเรียนในระบบ</h3>
            <form method="GET" class="flex gap-2">
                <input type="text" name="cls" value="<?= htmlspecialchars($filterCls) ?>" placeholder="กรองห้อง..." 
                       class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-1.5 text-xs outline-none focus:ring-1 focus:ring-blue-400">
                <button class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-blue-100 transition">กรอง</button>
            </form>
        </div>

        <?php if (empty($students)): ?>
            <div class="p-16 text-center text-slate-300">
                <i class="bi bi-person-x text-4xl mb-3 block"></i>
                <p class="text-xs">ไม่พบข้อมูลนักเรียน</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">รหัส</th>
                            <th class="px-6 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">ชื่อ-สกุล</th>
                            <th class="px-6 py-3 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest">ห้องเรียน</th>
                            <th class="px-6 py-3 text-right text-[10px] font-bold text-slate-400 uppercase tracking-widest">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-sm">
                        <?php foreach($students as $s): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-3 text-blue-600 font-mono font-bold text-xs"><?= $s['student_id'] ?></td>
                            <td class="px-6 py-3 font-bold text-slate-700"><?= htmlspecialchars($s['name']) ?></td>
                            <td class="px-6 py-3 text-center">
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-[10px]"><?= $s['classroom'] ?></span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button onclick='openEditModal(<?= json_encode($s) ?>)' class="p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg"><i class="bi bi-pencil-square"></i></button>
                                    <button onclick="deleteStudent(<?= $s['id'] ?>, '<?= addslashes($s['name']) ?>')" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg"><i class="bi bi-trash-fill"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Edit -->
<div id="edit-modal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-sm p-8 shadow-2xl border border-slate-100 flex flex-col gap-6">
        <h3 class="font-bold text-xl text-slate-800">แก้ไขข้อมูลนักเรียน</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="do" value="edit_student">
            <input type="hidden" name="student_db_id" id="edit-id">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ชื่อ-สกุล</label>
                <input type="text" name="edit_name" id="edit-name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ห้องเรียน</label>
                <input type="text" name="edit_classroom" id="edit-cls" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeEditModal()" class="flex-1 px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-50 transition">ยกเลิก</button>
                <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(s) {
    document.getElementById('edit-id').value = s.id;
    document.getElementById('edit-name').value = s.name;
    document.getElementById('edit-cls').value = s.classroom;
    document.getElementById('edit-modal').classList.remove('hidden');
}
function closeEditModal() { document.getElementById('edit-modal').classList.add('hidden'); }
function deleteStudent(id, name) {
    Swal.fire({
        title: 'ลบข้อมูล?', text: `ต้องการลบรายชื่อ "${name}" หรือไม่?`, icon: 'warning',
        showCancelButton: true, confirmButtonText: 'ลบออก', cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#e11d48', borderRadius: '20px'
    }).then(r => {
        if(r.isConfirmed){
            const f = document.createElement('form'); f.method='POST';
            f.innerHTML = `<input name="do" value="delete_student"><input name="student_id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }
    });
}
</script>

<?php
if ($msg) echo "<script>Swal.fire({ icon: '$msgType', title: 'แจ้งเตือน', text: '$msg', timer: 2500, showConfirmButton: false });</script>";
require_once 'components/layout_end.php';
?>
