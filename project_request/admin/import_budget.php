<?php
session_start();
$pageTitle = 'นำเข้างบประมาณ';
$pageSubtitle = 'อัปโหลดไฟล์ Excel/CSV เพื่อนำข้อมูลโครงการลงฐานข้อมูล';
require_once __DIR__ . '/../components/layout_start.php';

// Check if vendor autoload exists
$autoload = __DIR__ . '/../vendor/autoload.php';
$hasSpreadsheet = file_exists($autoload);

if ($hasSpreadsheet) {
    require_once $autoload;
}

$pdo = getPdo();
$departments = $pdo->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();

$message = '';
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$tempFile = $_SESSION['import_temp_file'] ?? '';

if ($step === 2 && !empty($_FILES['budget_file']['tmp_name'])) {
    // Save file to a temp location
    $uploadDir = __DIR__ . '/../uploads/temp/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $fileName = time() . '_' . $_FILES['budget_file']['name'];
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['budget_file']['tmp_name'], $targetPath)) {
        $_SESSION['import_temp_file'] = $targetPath;
        $tempFile = $targetPath;
        
        // Read headers
        $headers = [];
        if (strpos($fileName, '.csv') !== false) {
            if (($handle = fopen($targetPath, "r")) !== FALSE) {
                $headers = fgetcsv($handle, 1000, ",");
                fclose($handle);
            }
        } elseif ($hasSpreadsheet) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($targetPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $headers = $worksheet->rangeToArray('A1:' . $worksheet->getHighestColumn() . '1', NULL, TRUE, FALSE)[0];
        } else {
            $message = 'ระบบไม่รองรับไฟล์ Excel กรุณาติดตั้ง PhpSpreadsheet หรือใช้ไฟล์ CSV';
            $step = 1;
        }
    } else {
        $message = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
        $step = 1;
    }
}

// Step 3: Process Data
if ($step === 3 && isset($_POST['mapping'])) {
    $mapping = $_POST['mapping'];
    $dept_id = $_POST['department_id'];
    $fiscal_year = $_POST['fiscal_year'];
    
    $count = 0;
    $skipped = 0;

    if (strpos($tempFile, '.csv') !== false) {
        if (($handle = fopen($tempFile, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ","); // Skip header
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (insertBudget($pdo, $row, $mapping, $dept_id, $fiscal_year)) {
                    $count++;
                } else {
                    $skipped++;
                }
            }
            fclose($handle);
        }
    } elseif ($hasSpreadsheet) {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFile);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        array_shift($rows); // Skip header

        foreach ($rows as $row) {
            if (insertBudget($pdo, $row, $mapping, $dept_id, $fiscal_year)) {
                $count++;
            } else {
                $skipped++;
            }
        }
    }

    $message = "นำเข้าสำเร็จ $count รายการ (ข้าม $skipped รายการที่ซ้ำ)";
    $step = 1;
    if (file_exists($tempFile)) unlink($tempFile);
    unset($_SESSION['import_temp_file']);
}

function insertBudget($pdo, $row, $mapping, $dept_id, $fiscal_year) {
    // Map indices
    $p_name = $row[$mapping['project_name']] ?? '';
    $activity = $row[$mapping['activity']] ?? '';
    
    if (empty($p_name) || empty($activity)) return false;

    // Check for duplicate
    $check = $pdo->prepare("SELECT id FROM budget_projects WHERE project_name = ? AND activity = ? AND fiscal_year = ?");
    $check->execute([$p_name, $activity, $fiscal_year]);
    if ($check->fetch()) return false;

    // Insert
    $sql = "INSERT INTO budget_projects (department_id, project_group, project_name, activity, budget_subsidy, budget_quality, budget_revenue, budget_operation, budget_reserve, owner_name, fiscal_year) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $dept_id,
        $row[$mapping['project_group']] ?? '',
        $p_name,
        $activity,
        (float)($row[$mapping['budget_subsidy']] ?? 0),
        (float)($row[$mapping['budget_quality']] ?? 0),
        (float)($row[$mapping['budget_revenue']] ?? 0),
        (float)($row[$mapping['budget_operation']] ?? 0),
        (float)($row[$mapping['budget_reserve']] ?? 0),
        $row[$mapping['owner_name']] ?? '',
        $fiscal_year
    ]);
}
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="bg-white rounded-[2.5rem] p-10 shadow-xl shadow-slate-200/50 mb-8 border border-slate-100">
        <div class="flex items-center gap-6 mb-8">
            <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-[1.5rem] flex items-center justify-center text-3xl">
                <i class="bi bi-cloud-arrow-up-fill"></i>
            </div>
            <div>
                <h3 class="text-2xl font-black text-slate-800">นำเข้างบประมาณรายโครงการ</h3>
                <p class="text-slate-400 font-medium mt-1">อัปโหลดไฟล์ Excel/CSV และจับคู่คอลัมน์เพื่อนำเข้าข้อมูล</p>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="mb-8 p-4 bg-emerald-50 border border-emerald-100 text-emerald-600 rounded-2xl font-bold flex items-center gap-3">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <!-- Step 1: Upload -->
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="step" value="2">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-3 px-1">ฝ่าย / กลุ่มงาน</label>
                    <select name="department_id" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all" required>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-3 px-1">ปีงบประมาณ</label>
                    <input type="text" name="fiscal_year" value="<?= FISCAL_YEAR ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all" required>
                </div>
            </div>

            <div class="border-2 border-dashed border-slate-200 rounded-[2rem] p-12 text-center hover:border-blue-400 transition-all bg-slate-50 group">
                <i class="bi bi-file-earmark-excel-fill text-5xl text-slate-300 group-hover:text-blue-500 transition-all"></i>
                <p class="mt-4 text-slate-500 font-bold">ลากไฟล์มาวาง หรือคลิกเพื่อเลือกไฟล์</p>
                <p class="text-[10px] text-slate-400 uppercase tracking-widest mt-1">รองรับ .XLSX และ .CSV</p>
                <input type="file" name="budget_file" class="hidden" id="fileInput" accept=".csv, .xlsx">
                <button type="button" onclick="document.getElementById('fileInput').click()" class="mt-6 bg-white border border-slate-200 px-8 py-3 rounded-xl font-black text-xs shadow-sm hover:bg-slate-100 transition-all">
                    เลือกไฟล์จากคอมพิวเตอร์
                </button>
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black text-sm shadow-xl shadow-blue-200 hover:bg-blue-700 transition-all flex items-center justify-center gap-3">
                ดำเนินการขั้นตอนถัดไป
                <i class="bi bi-arrow-right"></i>
            </button>
        </form>

        <?php elseif ($step === 2): ?>
        <!-- Step 2: Mapping -->
        <form method="POST" class="space-y-8">
            <input type="hidden" name="step" value="3">
            <input type="hidden" name="department_id" value="<?= $_POST['department_id'] ?>">
            <input type="hidden" name="fiscal_year" value="<?= $_POST['fiscal_year'] ?>">

            <div class="bg-blue-50 rounded-2xl p-6 border border-blue-100 mb-8">
                <h4 class="text-sm font-black text-blue-900 mb-2">คำแนะนำการจับคู่ (Mapping)</h4>
                <p class="text-xs text-blue-600 font-medium">ระบบพบคอลัมน์จากไฟล์ของคุณ กรุณาเลือกคอลัมน์ที่ตรงกับฟิลด์ในฐานข้อมูล</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                <?php 
                $fields = [
                    'project_group'   => 'หมวด / กลุ่มโครงการ',
                    'project_name'    => 'ชื่อโครงการ (สำคัญ)',
                    'activity'        => 'กิจกรรม (สำคัญ)',
                    'budget_subsidy'  => 'งบเงินอุดหนุน',
                    'budget_quality'  => 'งบพัฒนาคุณภาพผู้เรียน',
                    'budget_revenue'  => 'เงินรายได้สถานศึกษา',
                    'budget_operation'=> 'งบงานประจำ',
                    'budget_reserve'  => 'เงินสำรองจ่าย',
                    'owner_name'      => 'ผู้รับผิดชอบ (ชื่อครู)'
                ];
                foreach ($fields as $key => $label): 
                ?>
                <div class="flex flex-col gap-2">
                    <label class="text-[11px] font-black text-slate-500 uppercase tracking-widest px-1"><?= $label ?></label>
                    <select name="mapping[<?= $key ?>]" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all">
                        <option value="">-- ไม่เลือก --</option>
                        <?php foreach ($headers as $idx => $h): ?>
                        <option value="<?= $idx ?>" <?= (strpos($h, $label) !== false || strpos($h, $key) !== false) ? 'selected' : '' ?>>
                            คอลัมน์ <?= $idx + 1 ?>: <?= htmlspecialchars($h) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="pt-6 flex gap-4">
                <button type="button" onclick="window.location.reload()" class="flex-1 bg-slate-100 text-slate-600 py-4 rounded-2xl font-black text-sm hover:bg-slate-200 transition-all">
                    ยกเลิกและกลับไปใหม่
                </button>
                <button type="submit" class="flex-[2] bg-blue-600 text-white py-4 rounded-2xl font-black text-sm shadow-xl shadow-blue-200 hover:bg-blue-700 transition-all flex items-center justify-center gap-3">
                    <i class="bi bi-check-lg text-lg"></i>
                    ยืนยันการนำเข้าข้อมูล
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    if (fileName) {
        this.parentElement.querySelector('p').innerText = 'ไฟล์ที่เลือก: ' + fileName;
    }
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
