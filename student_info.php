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

// --- Calculate Statistics ---
$totalCount = count($students);
$maleCount = 0;
$femaleCount = 0;
foreach ($students as $s) {
    if (($s['gender'] ?? '') === 'ชาย') $maleCount++;
    elseif (($s['gender'] ?? '') === 'หญิง') $femaleCount++;
}

$pageTitle = 'สารสนเทศนักเรียน';
$activeSystem = 'portal'; // Use portal context for now or create a new one
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | LLW Platinum</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f0f4f8; }
        .glass-header { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.5); }
        .stat-card { background: white; border-radius: 1.5rem; padding: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="flex min-h-screen">
    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <!-- Header -->
        <header class="glass-header px-8 py-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 sticky top-0 z-10">
            <div>
                <h1 class="text-2xl font-black text-slate-800">📊 สารสนเทศนักเรียน</h1>
                <p class="text-xs text-slate-400 font-bold mt-1 uppercase tracking-widest">Student Information & Statistics System</p>
            </div>
            
            <form class="flex flex-wrap gap-2">
                <select name="year" class="bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php for($y=2569; $y>=2565; $y--): ?>
                        <option value="<?= $y ?>" <?= $currentYear == $y ? 'selected' : '' ?>>ปี <?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <select name="semester" class="bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="1" <?= $currentSemester == 1 ? 'selected' : '' ?>>เทอม 1</option>
                    <option value="2" <?= $currentSemester == 2 ? 'selected' : '' ?>>เทอม 2</option>
                </select>
                <select name="classroom" class="bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">ทุกห้องเรียน</option>
                    <?php foreach($rooms as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= $currentClass == $r ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="relative">
                    <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นชื่อ หรือ เลขประจำตัว..." 
                           class="bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500 w-48 lg:w-64">
                </div>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-black text-xs shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all">
                    ค้นหา
                </button>
            </form>
        </header>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-8">
            
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
                                    <?php if(($s['gender'] ?? '') === 'ชาย'): ?>
                                        <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-600 text-[10px] font-black">♂ ชาย</span>
                                    <?php elseif(($s['gender'] ?? '') === 'หญิง'): ?>
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
        </div>
    </main>
</body>
</html>
