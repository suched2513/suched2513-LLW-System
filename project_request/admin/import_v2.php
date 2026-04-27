<?php
/**
 * import_v2.php - Simplified Budget Import (CSV Only)
 * To avoid 500 errors on Shared Hosting due to missing libraries.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

$pageTitle = 'นำเข้างบประมาณ (v2)';
$pageSubtitle = 'นำเข้าผ่านไฟล์ CSV เพื่อความเสถียรสูงสุด';
require_once __DIR__ . '/../components/layout_start.php';
require_once __DIR__ . '/../config/db.php';

$pdo = getPdo();
$departments = [];
try {
    $departments = $pdo->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();
    if (empty($departments)) {
        $departments = $pdo->query("SELECT dept_id as id, dept_name as name FROM wfh_departments")->fetchAll();
    }
} catch (Exception $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลฝ่าย: " . $e->getMessage();
}

$message = isset($_GET['msg']) ? $_GET['msg'] : '';
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$tempFile = isset($_SESSION['import_temp_file']) ? $_SESSION['import_temp_file'] : '';

// Step 2: Upload CSV and Read Headers
if ($step === 2 && !empty($_FILES['budget_file']['tmp_name'])) {
    $uploadDir = __DIR__ . '/../uploads/temp/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $fileName = time() . '_import.csv';
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['budget_file']['tmp_name'], $targetPath)) {
        $_SESSION['import_temp_file'] = $targetPath;
        $tempFile = $targetPath;
        
        $headers = [];
        if (($handle = fopen($targetPath, "r")) !== FALSE) {
            // Find first non-empty row as header
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (!empty(array_filter($data))) {
                    $headers = $data;
                    break;
                }
            }
            fclose($handle);
        }
        
        if (empty($headers)) {
            $message = "ไม่สามารถอ่านหัวข้อจากไฟล์ได้ กรุณาตรวจสอบว่าเป็นไฟล์ CSV ที่ถูกต้อง";
            $step = 1;
        }
    } else {
        $message = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
        $step = 1;
    }
}

// Step 3: Final Import
if ($step === 3 && isset($_POST['mapping'])) {
    $mapping = $_POST['mapping'];
    $dept_id = $_POST['department_id'];
    $fiscal_year = $_POST['fiscal_year'];
    
    $count = 0;
    $skipped = 0;
    $last_project_name = '';
    
    try {
        if (($handle = fopen($tempFile, "r")) !== FALSE) {
            // Skip rows until data starts (after header)
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (!empty(array_filter($data))) break; // Skip up to header row
            }
            
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Handle Merged Project Name
                $p_name_idx = $mapping['project_name'];
                if (empty($row[$p_name_idx]) && !empty($last_project_name)) {
                    $row[$p_name_idx] = $last_project_name;
                }
                if (!empty($row[$p_name_idx])) {
                    $last_project_name = $row[$p_name_idx];
                }

                // Insert to DB
                $activity = isset($row[$mapping['activity']]) ? $row[$mapping['activity']] : '';
                $p_name = isset($row[$mapping['project_name']]) ? $row[$mapping['project_name']] : '';
                
                if (empty($p_name) || empty($activity)) continue;

                // Simple insert logic
                $sql = "INSERT INTO budget_projects (department_id, project_name, activity, budget_subsidy, budget_quality, budget_revenue, budget_operation, budget_reserve, owner_name, fiscal_year) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE budget_subsidy = VALUES(budget_subsidy)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $dept_id,
                    $p_name,
                    $activity,
                    (float)(isset($row[$mapping['budget_subsidy']]) ? str_replace(',', '', $row[$mapping['budget_subsidy']]) : 0),
                    (float)(isset($row[$mapping['budget_quality']]) ? str_replace(',', '', $row[$mapping['budget_quality']]) : 0),
                    (float)(isset($row[$mapping['budget_revenue']]) ? str_replace(',', '', $row[$mapping['budget_revenue']]) : 0),
                    (float)(isset($row[$mapping['budget_operation']]) ? str_replace(',', '', $row[$mapping['budget_operation']]) : 0),
                    (float)(isset($row[$mapping['budget_reserve']]) ? str_replace(',', '', $row[$mapping['budget_reserve']]) : 0),
                    isset($row[$mapping['owner_name']]) ? $row[$mapping['owner_name']] : '',
                    $fiscal_year
                ]);
                $count++;
            }
            fclose($handle);
        }
        
        header("Location: import_v2.php?msg=" . urlencode("นำเข้าสำเร็จ $count รายการ"));
        exit();
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $step = 2;
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-[2.5rem] p-10 shadow-xl border border-slate-100">
        <h3 class="text-2xl font-black text-slate-800">นำเข้าข้อมูล (เวอร์ชันเสถียร)</h3>
        <p class="text-slate-400 font-medium mb-8">กรุณาใช้ไฟล์ .CSV ในการนำเข้าข้อมูล</p>

        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-blue-50 text-blue-600 rounded-2xl font-bold"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="step" value="2">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-2">ฝ่าย</label>
                    <select name="department_id" class="w-full bg-slate-50 p-4 rounded-xl border border-slate-100 font-bold" required>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-2">ปีงบประมาณ</label>
                    <input type="text" name="fiscal_year" value="2569" class="w-full bg-slate-50 p-4 rounded-xl border border-slate-100 font-bold">
                </div>
            </div>
            <div class="p-8 border-2 border-dashed border-slate-200 rounded-2xl text-center">
                <input type="file" name="budget_file" accept=".csv" required>
                <p class="text-xs text-slate-400 mt-2">คำแนะนำ: ใน Excel ให้ไปที่ File > Save As > CSV (Comma delimited)</p>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white p-4 rounded-2xl font-black shadow-lg">อัปโหลดไฟล์</button>
        </form>

        <?php elseif ($step === 2): ?>
        <form method="POST" class="space-y-6">
            <input type="hidden" name="step" value="3">
            <input type="hidden" name="department_id" value="<?= $_POST['department_id'] ?>">
            <input type="hidden" name="fiscal_year" value="<?= $_POST['fiscal_year'] ?>">
            
            <div class="grid grid-cols-2 gap-6">
                <?php 
                $fields = [
                    'project_name' => 'ชื่อโครงการ',
                    'activity'     => 'กิจกรรม',
                    'budget_subsidy' => 'งบเงินอุดหนุน',
                    'budget_quality' => 'งบพัฒนาคุณภาพ',
                    'budget_revenue' => 'เงินรายได้',
                    'budget_operation' => 'งบงานประจำ',
                    'budget_reserve' => 'เงินสำรองจ่าย',
                    'owner_name' => 'ผู้รับผิดชอบ'
                ];
                foreach ($fields as $key => $label): 
                ?>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-2"><?= $label ?></label>
                    <select name="mapping[<?= $key ?>]" class="w-full bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <option value="">-- เลือกคอลัมน์ --</option>
                        <?php foreach ($headers as $idx => $h): ?>
                            <option value="<?= $idx ?>" <?= (strpos($h, $label) !== false) ? 'selected' : '' ?>>คอลัมน์ <?= $idx+1 ?>: <?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="w-full bg-emerald-500 text-white p-4 rounded-2xl font-black shadow-lg">ยืนยันนำเข้า</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
