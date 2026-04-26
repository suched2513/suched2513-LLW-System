<?php
/**
 * teacher_info.php — Teacher Information System (Full Personnel)
 */
session_start();
require_once 'config/database.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) { header('Location: login.php'); exit(); }

$pdo = getPdo();

// --- Fetch Stats (All Roles) ---
$totalUsers = $pdo->query("SELECT COUNT(*) FROM llw_users WHERE status = 'active'")->fetchColumn() ?: 0;
$adminCount = $pdo->query("SELECT COUNT(*) FROM llw_users WHERE role IN ('super_admin', 'wfh_admin') AND status = 'active'")->fetchColumn() ?: 0;
$staffCount = $pdo->query("SELECT COUNT(*) FROM llw_users WHERE role NOT IN ('super_admin', 'wfh_admin') AND status = 'active'")->fetchColumn() ?: 0;

// --- Fetch Detailed Personnel List ---
$sql = "
    SELECT u.user_id, u.firstname, u.lastname, u.role, u.status,
           (SELECT COUNT(*) FROM att_subjects WHERE teacher_id = t.id) as subject_count,
           t.id as att_teacher_id
    FROM llw_users u
    LEFT JOIN att_teachers t ON u.user_id = t.llw_user_id
    WHERE u.status = 'active'
    ORDER BY u.role, u.firstname
";
$teachers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$rolesThai = [
    'super_admin' => 'ผู้ดูแลระบบ',
    'wfh_admin'   => 'ผู้บริหาร',
    'att_teacher' => 'ครูผู้สอน',
    'wfh_staff'   => 'บุคลากรทั่วไป',
    'cb_admin'    => 'เจ้าหน้าที่ Chromebook'
];

$pageTitle = 'สารสนเทศครูและบุคลากร';
?>
require_once 'components/layout_start.php';
?>

            
            <!-- Statistics Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat-card flex items-center gap-6">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-500 text-white flex items-center justify-center text-3xl shadow-xl shadow-indigo-200"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <p class="text-4xl font-black text-slate-800 leading-none"><?= number_format($totalUsers) ?></p>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">บุคลากรรวม</p>
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
                    <div class="w-16 h-16 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-3xl shadow-xl shadow-emerald-200"><i class="bi bi-person-vcard"></i></div>
                    <div>
                        <p class="text-4xl font-black text-slate-800 leading-none"><?= number_format($staffCount) ?></p>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">ครูและเจ้าหน้าที่</p>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-xl shadow-slate-200/50 overflow-hidden">
                <div class="p-8 border-b border-slate-50">
                    <h2 class="font-black text-slate-800 flex items-center gap-2">
                        <i class="bi bi-person-badge text-indigo-600"></i>
                        รายชื่อบุคลากรจำแนกตามตำแหน่ง (Active)
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
                                <td class="px-6 py-5 text-center text-emerald-500">
                                    <span class="text-[10px] font-black uppercase tracking-widest">Active</span>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <button class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 hover:bg-amber-500 hover:text-white transition-all">
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
