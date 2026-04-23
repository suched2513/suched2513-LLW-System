<?php
/**
 * teacher_info.php — Teacher Information System
 */
session_start();
require_once 'config/database.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) { header('Location: login.php'); exit(); }

$pdo = getPdo();

// --- Fetch Teacher Stats ---
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM llw_users WHERE role IN ('att_teacher', 'super_admin', 'wfh_admin')")->fetchColumn() ?: 0;
$adminCount = $pdo->query("SELECT COUNT(*) FROM llw_users WHERE role IN ('super_admin', 'wfh_admin')")->fetchColumn() ?: 0;
$teacherCount = $pdo->query("SELECT COUNT(*) FROM llw_users WHERE role = 'att_teacher'")->fetchColumn() ?: 0;

// --- Fetch Detailed Teacher List ---
$sql = "
    SELECT u.user_id, u.firstname, u.lastname, u.role, u.status,
           (SELECT COUNT(*) FROM att_subjects WHERE teacher_id = t.id) as subject_count,
           t.id as att_teacher_id
    FROM llw_users u
    LEFT JOIN att_teachers t ON u.user_id = t.llw_user_id
    WHERE u.role IN ('super_admin', 'wfh_admin', 'att_teacher')
    ORDER BY u.role, u.firstname
";
$teachers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$rolesThai = [
    'super_admin' => 'ผู้ดูแลระบบ',
    'wfh_admin'   => 'ผู้บริหาร',
    'att_teacher' => 'ครูผู้สอน'
];

$pageTitle = 'สารสนเทศครูและบุคลากร';
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
        <header class="glass-header px-8 py-6 flex justify-between items-center sticky top-0 z-10">
            <div>
                <h1 class="text-2xl font-black text-slate-800">👨‍🏫 สารสนเทศครูและบุคลากร</h1>
                <p class="text-xs text-slate-400 font-bold mt-1 uppercase tracking-widest">Teacher & Human Resource Information System</p>
            </div>
            <div class="flex gap-2">
                <button class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-black text-xs shadow-lg shadow-indigo-200">
                    <i class="bi bi-person-plus-fill mr-2"></i> เพิ่มครูใหม่
                </button>
            </div>
        </header>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-8">
            
            <!-- Statistics Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat-card flex items-center gap-6">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-500 text-white flex items-center justify-center text-3xl shadow-xl shadow-indigo-200"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <p class="text-4xl font-black text-slate-800 leading-none"><?= number_format($totalTeachers) ?></p>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">บุคลากรทั้งหมด</p>
                    </div>
                </div>
                <div class="stat-card flex items-center gap-6 border-l-8 border-l-blue-600">
                    <div class="w-16 h-16 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-3xl shadow-xl shadow-blue-200"><i class="bi bi-shield-lock-fill"></i></div>
                    <div>
                        <p class="text-4xl font-black text-slate-800 leading-none"><?= number_format($adminCount) ?></p>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">ฝ่ายบริหาร/แอดมิน</p>
                    </div>
                </div>
                <div class="stat-card flex items-center gap-6 border-l-8 border-l-emerald-500">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-3xl shadow-xl shadow-emerald-200"><i class="bi bi-person-video3"></i></div>
                    <div>
                        <p class="text-4xl font-black text-slate-800 leading-none"><?= number_format($teacherCount) ?></p>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">ครูผู้สอน</p>
                    </div>
                </div>
            </div>

            <!-- Teacher Table -->
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-xl shadow-slate-200/50 overflow-hidden">
                <div class="p-8 border-b border-slate-50 flex justify-between items-center">
                    <h2 class="font-black text-slate-800 flex items-center gap-2">
                        <i class="bi bi-person-badge text-indigo-600"></i>
                        รายชื่อบุคลากรจำแนกตามตำแหน่ง
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <tr>
                                <th class="px-8 py-5">ชื่อ-นามสกุล</th>
                                <th class="px-6 py-5">ตำแหน่ง</th>
                                <th class="px-6 py-5 text-center">วิชาที่สอน</th>
                                <th class="px-6 py-5 text-center">สถานะ</th>
                                <th class="px-8 py-5 text-center">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($teachers as $t): ?>
                            <tr class="hover:bg-slate-50/50 transition-all group">
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-[14px] bg-gradient-to-br from-indigo-50 to-blue-50 text-indigo-600 flex items-center justify-center font-black text-xs group-hover:from-indigo-600 group-hover:to-indigo-700 group-hover:text-white transition-all duration-300">
                                            <?= mb_substr($t['firstname'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <p class="font-black text-slate-800"><?= htmlspecialchars($t['firstname'] . ' ' . $t['lastname']) ?></p>
                                            <p class="text-[10px] text-slate-400 font-bold">USER ID: #<?= $t['user_id'] ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black uppercase">
                                        <?= $rolesThai[$t['role']] ?? $t['role'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="text-sm font-black text-slate-700"><?= $t['subject_count'] ?></span>
                                    <span class="text-[10px] text-slate-400 font-bold ml-1">วิชา</span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <div class="flex items-center justify-center gap-1.5 text-emerald-500">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span class="text-[10px] font-black uppercase tracking-widest">Active</span>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 hover:bg-indigo-600 hover:text-white transition-all">
                                            <i class="bi bi-eye-fill text-xs"></i>
                                        </button>
                                        <button class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 hover:bg-amber-500 hover:text-white transition-all">
                                            <i class="bi bi-pencil-fill text-xs"></i>
                                        </button>
                                    </div>
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
