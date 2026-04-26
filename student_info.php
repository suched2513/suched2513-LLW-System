<?php
/**
 * student_info.php — Detailed Student Information System (SIS)
 */
session_start();
require_once 'config/database.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) { header('Location: login.php'); exit(); }

$pdo = getPdo();

// --- Filters ---
$currentYear = (int)($_GET['year'] ?? 2569);
$currentSemester = (int)($_GET['semester'] ?? 1);
$currentClass = $_GET['classroom'] ?? '';
$search = $_GET['search'] ?? '';

// --- Check Demographics Support ---
$hasDemographics = false;
try {
    $pdo->query("SELECT gender FROM att_students LIMIT 1");
    $hasDemographics = true;
} catch (Exception $e) { $hasDemographics = false; }

// --- Fetch Classrooms ---
$stmtRooms = $pdo->prepare("SELECT DISTINCT classroom FROM att_students " . ($hasDemographics ? "WHERE academic_year = ? AND semester = ?" : "") . " ORDER BY classroom");
if ($hasDemographics) $stmtRooms->execute([$currentYear, $currentSemester]);
else $stmtRooms->execute();
$rooms = $stmtRooms->fetchAll(PDO::FETCH_COLUMN);

// --- Build Query ---
$params = [];
$sql = "SELECT * FROM att_students WHERE 1=1";

if ($hasDemographics) {
    $sql .= " AND academic_year = ? AND semester = ?";
    $params[] = $currentYear;
    $params[] = $currentSemester;
}

if ($currentClass) {
    $sql .= " AND classroom = ?";
    $params[] = $currentClass;
}

if ($search) {
    $sql .= " AND (name LIKE ? OR student_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY classroom, student_id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Helper: Gender Detection ---
function detectGender($name, $dbGender = null) {
    if ($dbGender === 'ชาย' || $dbGender === 'หญิง') return $dbGender;
    $name = trim($name);
    if (mb_strpos($name, 'เด็กชาย') === 0 || mb_strpos($name, 'นาย') === 0 || mb_strpos($name, 'ด.ช.') === 0 || mb_strpos($name, 'ดช.') === 0) return 'ชาย';
    if (mb_strpos($name, 'เด็กหญิง') === 0 || mb_strpos($name, 'นางสาว') === 0 || mb_strpos($name, 'นาง') === 0 || mb_strpos($name, 'ด.ญ.') === 0 || mb_strpos($name, 'ดญ.') === 0) return 'หญิง';
    return null;
}

// --- Calculate Statistics ---
$totalCount = count($students);
$maleCount = 0;
$femaleCount = 0;
foreach ($students as $s) {
    $g = detectGender($s['name'], $s['gender'] ?? null);
    if ($g === 'ชาย') $maleCount++;
    elseif ($g === 'หญิง') $femaleCount++;
}

$pageTitle = 'สารสนเทศนักเรียน';
$activeSystem = 'portal'; // Use portal context for now or create a new one
require_once 'components/layout_start.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column md:flex-row justify-content-between align-items-center gap-3">
                    <form class="row g-2 w-100">
                        <div class="col-md-2">
                            <select name="year" class="form-select border-slate-200 rounded-3 text-xs font-bold">
                                <?php for($y=2569; $y<=2573; $y++): ?>
                                    <option value="<?= $y ?>" <?= $currentYear == $y ? 'selected' : '' ?>>ปี <?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="semester" class="form-select border-slate-200 rounded-3 text-xs font-bold">
                                <option value="1" <?= $currentSemester == 1 ? 'selected' : '' ?>>เทอม 1</option>
                                <option value="2" <?= $currentSemester == 2 ? 'selected' : '' ?>>เทอม 2</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="classroom" class="form-select border-slate-200 rounded-3 text-xs font-bold">
                                <option value="">ทุกห้องเรียน</option>
                                <?php foreach($rooms as $r): ?>
                                    <option value="<?= htmlspecialchars($r) ?>" <?= $currentClass == $r ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-slate-200 text-slate-400"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นชื่อ หรือ เลขประจำตัว..." 
                                       class="form-control border-slate-200 text-xs font-bold">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 rounded-3 font-black text-xs shadow-sm">
                                <i class="fas fa-search me-1"></i> ค้นหา
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

            
            <!-- Statistics Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat-card flex items-center gap-6">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-500 text-white flex items-center justify-center text-3xl shadow-xl shadow-indigo-200"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <p class="text-4xl font-black text-slate-800 leading-none"><?= number_format($totalCount) ?></p>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">นักเรียนทั้งหมด</p>
                    </div>
                </div>
                <div class="stat-card flex items-center gap-6 border-l-8 border-l-blue-500">
                    <div class="w-16 h-16 rounded-2xl bg-blue-500 text-white flex items-center justify-center text-3xl shadow-xl shadow-blue-200"><i class="bi bi-gender-male"></i></div>
                    <div>
                        <p class="text-4xl font-black text-slate-800 leading-none"><?= number_format($maleCount) ?></p>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">ชาย (Male)</p>
                    </div>
                </div>
                <div class="stat-card flex items-center gap-6 border-l-8 border-l-pink-500">
                    <div class="w-16 h-16 rounded-2xl bg-pink-500 text-white flex items-center justify-center text-3xl shadow-xl shadow-pink-200"><i class="bi bi-gender-female"></i></div>
                    <div>
                        <p class="text-4xl font-black text-slate-800 leading-none"><?= number_format($femaleCount) ?></p>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">หญิง (Female)</p>
                    </div>
                </div>
            </div>

            <!-- Student Table -->
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-xl shadow-slate-200/50 overflow-hidden">
                <div class="p-6 border-b border-slate-50 flex justify-between items-center">
                    <h2 class="font-black text-slate-800 flex items-center gap-2">
                        <i class="bi bi-table text-indigo-600"></i>
                        รายชื่อนักเรียนแยกตามห้อง
                    </h2>
                    <span class="px-4 py-1.5 rounded-full bg-slate-100 text-slate-500 text-[10px] font-black uppercase">
                        แสดงทั้งหมด <?= count($students) ?> รายการ
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <tr>
                                <th class="px-8 py-5">ห้องเรียน</th>
                                <th class="px-6 py-5">เลขประจำตัว</th>
                                <th class="px-6 py-5">ชื่อ-นามสกุล</th>
                                <th class="px-6 py-5 text-center">เพศ</th>
                                <th class="px-6 py-5 text-center">ปี/เทอม</th>
                                <th class="px-8 py-5 text-center">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if(empty($students)): ?>
                            <tr>
                                <td colspan="6" class="px-8 py-20 text-center">
                                    <div class="flex flex-col items-center opacity-30">
                                        <i class="bi bi-inbox text-6xl mb-4"></i>
                                        <p class="font-black text-lg">ไม่พบข้อมูลนักเรียน</p>
                                        <p class="text-sm">กรุณาตรวจสอบการตั้งค่าตัวกรอง</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach($students as $s): ?>
                            <tr class="hover:bg-slate-50/50 transition-all group">
                                <td class="px-8 py-4 font-black text-indigo-600"><?= htmlspecialchars($s['classroom']) ?></td>
                                <td class="px-6 py-4 font-bold text-slate-500"><?= htmlspecialchars($s['student_id']) ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center font-black text-[10px]">
                                            <?= mb_substr($s['name'], 0, 1) ?>
                                        </div>
                                        <span class="font-bold text-slate-700"><?= htmlspecialchars($s['name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php 
                                    $g = detectGender($s['name'], $s['gender'] ?? null);
                                    if($g === 'ชาย'): ?>
                                        <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-600 text-[10px] font-black">♂ ชาย</span>
                                    <?php elseif($g === 'หญิง'): ?>
                                        <span class="px-3 py-1 rounded-full bg-pink-50 text-pink-600 text-[10px] font-black">♀ หญิง</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-400 text-[10px] font-black">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center font-bold text-slate-400 text-xs">
                                    <?= ($s['academic_year'] ?? '-') ?>/<?= ($s['semester'] ?? '-') ?>
                                </td>
                                <td class="px-8 py-4 text-center">
                                    <button class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 hover:bg-indigo-600 hover:text-white transition-all">
                                        <i class="bi bi-pencil-fill text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
<?php require_once 'components/layout_end.php'; ?>
