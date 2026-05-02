<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pdo = getPdo();
$userId = $_SESSION['user_id'];
$groupId = $_GET['id'] ?? 0;

// Fetch group details
$stmt = $pdo->prepare("SELECT * FROM plc_groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: dashboard.php');
    exit();
}

// Fetch members
$stmt = $pdo->prepare("
    SELECT m.*, u.firstname, u.lastname, u.role as system_role
    FROM plc_members m
    JOIN llw_users u ON m.user_id = u.user_id
    WHERE m.group_id = ?
");
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();

// Check if current user is member
$isMember = false;
$myRole = '';
foreach ($members as $m) {
    if ($m['user_id'] == $userId) {
        $isMember = true;
        $myRole = $m['role'];
        break;
    }
}

if (!$isMember && $_SESSION['llw_role'] !== 'super_admin') {
    header('Location: dashboard.php');
    exit();
}

// Determine if current user is model_teacher or super_admin (for elevated actions)
$canManage = ($myRole === 'model_teacher') || ($_SESSION['llw_role'] === 'super_admin');


// Fetch logs grouped by phase
$stmt = $pdo->prepare("
    SELECT l.*, u.firstname, u.lastname
    FROM plc_logs l
    JOIN llw_users u ON l.user_id = u.user_id
    WHERE l.group_id = ?
    ORDER BY l.created_at ASC
");
$stmt->execute([$groupId]);
$logs = $stmt->fetchAll();

$phaseLogs = [
    'Plan'  => [],
    'Do'    => [],
    'Check' => [],
    'Act'   => []
];
foreach ($logs as $l) {
    $phaseLogs[$l['phase']][] = $l;
}

$pageTitle = htmlspecialchars($group['group_name']);
$pageSubtitle = "ปีการศึกษา {$group['academic_year']} / {$group['semester']}";
$activeSystem = 'plc';

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="space-y-10 animate-in slide-in-from-bottom-5 duration-700">
    
    <!-- Group Header & Progress -->
    <div class="flex flex-col lg:flex-row gap-8">
        <div class="flex-1 bg-white/70 backdrop-blur-xl rounded-[2.5rem] p-8 sm:p-10 shadow-xl shadow-slate-100/50 border border-white/50 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-8">
                <div class="w-16 h-16 bg-violet-50 rounded-2xl flex items-center justify-center text-violet-500 text-3xl group-hover:rotate-12 transition-transform">
                    <i class="bi bi-person-workspace"></i>
                </div>
            </div>

            <div class="relative z-10">
                <span class="px-4 py-1.5 rounded-full bg-violet-600 text-white text-xs font-black uppercase tracking-[0.2em] shadow-lg shadow-violet-100">
                    <?= htmlspecialchars($group['status']) ?>
                </span>
                <h2 class="text-3xl sm:text-4xl font-black text-slate-800 tracking-tight mt-6 leading-tight">
                    <?= htmlspecialchars($group['group_name']) ?>
                </h2>
                <div class="flex items-center gap-6 mt-4 text-slate-400">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-calendar4-week text-violet-500"></i>
                        <span class="text-xs font-bold font-mono tracking-wider">ปี <?= htmlspecialchars($group['academic_year']) ?>/<?= htmlspecialchars($group['semester']) ?></span>
                    </div>
                    <?php if ($group['target_group']): ?>
                    <div class="flex items-center gap-2">
                        <i class="bi bi-bullseye text-rose-500"></i>
                        <span class="text-xs font-bold"><?= htmlspecialchars($group['target_group']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Global Progress Bar -->
                <?php
                    $phasesDone = 0;
                    foreach ($phaseLogs as $p => $l) if (!empty($l)) $phasesDone++;
                    $progressPercent = ($phasesDone / 4) * 100;
                ?>
                <div class="mt-10 pt-10 border-t border-slate-100">
                    <div class="flex flex-wrap justify-between items-end mb-4 gap-4">
                        <p class="text-xs font-black text-slate-400 uppercase tracking-[0.3em]">PDCA Master Progress</p>
                        <div class="flex items-center gap-3">
                            <p class="text-3xl font-black italic text-violet-600"><?= number_format($progressPercent) ?>%</p>
                            <?php if ($canManage): ?>
                            <select id="groupStatusSelect" onchange="updateGroupStatus(this.value)" class="text-xs font-black uppercase tracking-wider border border-slate-200 rounded-xl px-3 py-1.5 outline-none focus:ring-2 focus:ring-violet-500 cursor-pointer transition-all <?php
                                echo match($group['status']) {
                                    'active' => 'bg-emerald-50 text-emerald-600',
                                    'completed' => 'bg-blue-50 text-blue-600',
                                    'archived' => 'bg-slate-100 text-slate-500',
                                    default => 'bg-slate-50 text-slate-500'
                                };
                            ?>">
                                <option value="active" <?= $group['status'] === 'active' ? 'selected' : '' ?>>● Active</option>
                                <option value="completed" <?= $group['status'] === 'completed' ? 'selected' : '' ?>>✓ Completed</option>
                                <option value="archived" <?= $group['status'] === 'archived' ? 'selected' : '' ?>>▸ Archived</option>
                            </select>
                            <?php else: ?>
                            <span class="text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-xl <?php
                                echo match($group['status']) {
                                    'active' => 'bg-emerald-50 text-emerald-600',
                                    'completed' => 'bg-blue-50 text-blue-600',
                                    'archived' => 'bg-slate-100 text-slate-500',
                                    default => 'bg-slate-50 text-slate-500'
                                };
                            ?>"><?= htmlspecialchars($group['status']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="h-4 bg-slate-50 rounded-full overflow-hidden p-1 shadow-inner">
                        <div class="h-full bg-gradient-to-r from-violet-500 via-purple-500 to-indigo-600 rounded-full shadow-lg shadow-violet-200 transition-all duration-1000" style="width: <?= $progressPercent ?>%"></div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Team Members -->
        <div class="w-full lg:w-96 bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-100/50 border border-slate-100">
        <div class="flex items-center justify-between mb-8">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-[0.2em]">ทีมวิชาชีพ (PLC TEAM)</h3>
                <?php if ($canManage): ?>
                <button onclick="openAddMemberModal()" class="w-8 h-8 rounded-full bg-violet-50 flex items-center justify-center text-violet-500 hover:bg-violet-600 hover:text-white transition-all" title="เพิ่มสมาชิก">
                    <i class="bi bi-person-plus-fill"></i>
                </button>
                <?php else: ?>
                <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-300">
                    <i class="bi bi-people"></i>
                </div>
                <?php endif; ?>
            </div>
            <div class="space-y-4">
                <?php foreach ($members as $member): 
                    $roleColors = [
                        'model_teacher' => 'from-blue-500 to-indigo-600',
                        'mentor'        => 'from-emerald-500 to-teal-600',
                        'expert'        => 'from-amber-500 to-orange-600',
                        'member'        => 'from-slate-400 to-slate-500'
                    ];
                    $roleLabels = [
                        'model_teacher' => 'Model Teacher',
                        'mentor'        => 'Mentor',
                        'expert'        => 'Expert',
                        'member'        => 'Member'
                    ];
                ?>
                <div class="flex items-center gap-4 p-3 rounded-2xl hover:bg-slate-50 transition-colors group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br <?= $roleColors[$member['role']] ?> text-white flex items-center justify-center font-black text-xs shadow-lg group-hover:scale-110 transition-transform">
                        <?= mb_substr($member['firstname'], 0, 1) ?>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-black text-slate-800"><?= htmlspecialchars($member['firstname']) ?> <?= htmlspecialchars($member['lastname']) ?></p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-0.5 italic">
                            <?= $roleLabels[$member['role']] ?>
                        </p>
                    </div>
                    <?php if ($member['user_id'] == $userId): ?>
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <?php elseif ($canManage && $member['role'] !== 'model_teacher'): ?>
                    <button onclick="removeMember(<?= $member['user_id'] ?>, '<?= htmlspecialchars(addslashes($member['firstname'])) ?> <?= htmlspecialchars(addslashes($member['lastname'])) ?>')" class="w-7 h-7 rounded-full bg-rose-50 flex items-center justify-center text-rose-400 hover:bg-rose-500 hover:text-white transition-all opacity-0 group-hover:opacity-100" title="ลบออกจากกลุ่ม">
                        <i class="bi bi-x-lg text-xs"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <!-- PDCA Timeline -->
    <div class="relative space-y-12 pb-20">
        <!-- Connecting Line -->
        <div class="absolute left-8 top-10 bottom-20 w-1 bg-slate-100 hidden sm:block"></div>

        <?php 
        $steps = [
            ['key' => 'Plan',  'title' => 'PLAN (วิเคราะห์และวางแผน)', 'color' => 'blue',    'icon' => 'bi-pencil-square'],
            ['key' => 'Do',    'title' => 'DO (ปฏิบัติและสังเกต)',     'color' => 'emerald', 'icon' => 'bi-play-circle-fill'],
            ['key' => 'Check', 'title' => 'CHECK (สะท้อนผล)',        'color' => 'amber',   'icon' => 'bi-search'],
            ['key' => 'Act',   'title' => 'ACT (สรุปผลและขยายผล)',     'color' => 'rose',    'icon' => 'bi-award-fill'],
        ];

        foreach ($steps as $step):
            $isDone = !empty($phaseLogs[$step['key']]);
            $color = $step['color'];
        ?>
        <div class="relative pl-0 sm:pl-20 group">
            <!-- Phase Indicator -->
            <div class="absolute left-4 sm:left-4 top-0 w-8 h-8 rounded-full border-4 border-white shadow-lg z-10 hidden sm:flex items-center justify-center transition-transform hover:scale-125 duration-500 <?= $isDone ? "bg-$color-500 text-white" : "bg-slate-100 text-slate-400" ?>">
                <i class="bi <?= $isDone ? 'bi-check-lg' : 'bi-circle' ?> text-xs"></i>
            </div>

            <div class="bg-white rounded-[2rem] p-8 sm:p-10 shadow-xl shadow-slate-100/50 border border-slate-100 transition-all duration-500 hover:shadow-2xl hover:shadow-violet-200/20 active:scale-[0.995]">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-<?= $color ?>-50 text-<?= $color ?>-500 flex items-center justify-center text-xl shadow-inner group-hover:rotate-6 transition-transform">
                            <i class="bi <?= $step['icon'] ?>"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-slate-800 tracking-tight italic"><?= $step['title'] ?></h3>
                            <p class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mt-1">Phase <?= $step['key'] ?></p>
                        </div>
                    </div>
                    <?php if ($isMember || $_SESSION['llw_role'] === 'super_admin'): ?>
                    <a href="add_log.php?group_id=<?= $groupId ?>&phase=<?= $step['key'] ?>" class="flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-<?= $color ?>-500 text-white font-bold text-xs shadow-lg shadow-<?= $color ?>-200 hover:bg-<?= $color ?>-600 hover:scale-105 transition-all">
                        <i class="bi bi-plus-lg"></i> บันทึกกิจกรรม
                    </a>
                    <?php endif; ?>
                </div>

                <div class="space-y-6">
                    <?php if (!$isDone): ?>
                    <div class="p-8 text-center bg-slate-50/50 rounded-2xl border-2 border-dashed border-slate-100">
                        <p class="text-slate-400 text-xs font-bold font-mono tracking-widest uppercase italic">--- NO RECORDS YET ---</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($phaseLogs[$step['key']] as $log): ?>
                        <div class="p-6 sm:p-8 bg-gradient-to-br from-slate-50/50 to-white rounded-3xl border border-slate-100 hover:border-violet-200 transition-all group/card relative">
                            <?php 
                            $canDeleteLog = ($log['user_id'] == $userId) || $canManage;
                            if ($canDeleteLog): ?>
                            <button onclick="deleteLog(<?= $log['id'] ?>)" class="absolute top-4 right-4 w-8 h-8 rounded-xl bg-rose-50 flex items-center justify-center text-rose-400 hover:bg-rose-500 hover:text-white transition-all opacity-0 group/card-hover:opacity-100 group/card:hover:opacity-100" title="ลบบันทึก">
                                <i class="bi bi-trash3 text-xs"></i>
                            </button>
                            <?php endif; ?>
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-slate-400 text-xs font-black shadow-sm group-hover/card:text-violet-500 transition-colors">
                                    <?= mb_substr($log['firstname'], 0, 1) ?>
                                </div>
                                <div>
                                    <p class="text-xs font-black text-slate-800"><?= htmlspecialchars($log['firstname']) ?> <?= htmlspecialchars($log['lastname']) ?></p>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest italic"><?= date('d M Y', strtotime($log['log_date'])) ?></p>
                                </div>
                            </div>
                            
                            <h4 class="text-lg font-black text-slate-800 mb-3 tracking-tight"><?= htmlspecialchars($log['topic']) ?></h4>
                            <div class="text-sm text-slate-500 leading-relaxed space-y-4">
                                <?php if ($log['details']): ?>
                                <div>
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1 italic">รายละเอียดกิจกรรม</p>
                                    <div class="whitespace-pre-line"><?= htmlspecialchars($log['details']) ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($log['reflection']): ?>
                                <div class="bg-violet-50/50 p-4 rounded-2xl border border-violet-100/50 italic">
                                    <p class="text-xs font-black text-violet-400 uppercase tracking-widest mb-1 italic">การสะท้อนผลและแนวทางพัฒนา</p>
                                    <div class="text-violet-900"><?= htmlspecialchars($log['reflection']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Evidence Section -->
                            <?php if ($log['evidence_path']): ?>
                            <div class="mt-6 flex items-center gap-3">
                                <?php 
                                    $files = explode(',', $log['evidence_path']);
                                    foreach ($files as $file): if (empty($file)) continue;
                                ?>
                                <a href="<?= htmlspecialchars($file) ?>" target="_blank" class="flex items-center gap-2 px-3 py-1.5 bg-white rounded-lg border border-slate-100 text-xs font-bold text-slate-600 hover:bg-violet-50 hover:text-violet-600 hover:border-violet-200 transition-all">
                                    <i class="bi bi-file-earmark-image"></i> หลักฐานแนบ
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Member Modal -->
<?php if ($canManage): ?>
<div id="addMemberModal" class="fixed inset-0 z-50 flex items-center justify-center hidden p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md" onclick="closeAddMemberModal()"></div>
    <div class="bg-white/90 backdrop-blur-xl w-full max-w-lg rounded-[2.5rem] shadow-2xl relative z-10 overflow-hidden">
        <div class="p-8 sm:p-10">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h3 class="text-2xl font-black text-slate-800 italic">เพิ่มสมาชิกกลุ่ม</h3>
                    <p class="text-slate-400 text-xs font-black uppercase tracking-[0.2em] mt-1">Add Team Member</p>
                </div>
                <button onclick="closeAddMemberModal()" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-all">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest pl-2 mb-2 block">ค้นหาชื่อครู/บุคลากร</label>
                    <input type="text" id="memberSearch" oninput="searchUsers(this.value)" placeholder="พิมพ์ชื่อเพื่อค้นหา..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition-all font-bold">
                    <div id="searchResults" class="mt-3 space-y-2 max-h-60 overflow-y-auto"></div>
                </div>
                <div>
                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest pl-2 mb-2 block">บทบาทในกลุ่ม</label>
                    <select id="memberRoleSelect" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-4 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition-all font-bold">
                        <option value="member">Member (สมาชิก)</option>
                        <option value="mentor">Mentor (ครูพี่เลี้ยง)</option>
                        <option value="expert">Expert (ผู้เชี่ยวชาญ)</option>
                    </select>
                </div>
                <input type="hidden" id="selectedUserId" value="">
                <button onclick="submitAddMember()" id="confirmAddBtn" disabled class="w-full bg-gradient-to-r from-violet-600 to-purple-600 text-white rounded-2xl py-4 font-black text-sm shadow-xl shadow-violet-200 hover:shadow-2xl disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                    เพิ่มสมาชิก <i class="bi bi-person-check"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Floating Print Button -->
<a href="report_print.php?id=<?= $groupId ?>" target="_blank" class="fixed bottom-8 right-8 w-16 h-16 bg-gradient-to-br from-indigo-600 to-blue-600 text-white rounded-full flex items-center justify-center shadow-2xl shadow-blue-300 hover:scale-110 active:scale-90 transition-all z-40 group">
    <i class="bi bi-printer-fill text-2xl group-hover:animate-bounce"></i>
    <div class="absolute right-full mr-4 bg-slate-800 text-white text-xs font-black uppercase tracking-widest px-4 py-2 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
        พิมพ์รายงานสรุป
    </div>
</a>


<script>
const GROUP_ID = <?= $groupId ?>;
const API_URL = 'api/plc_handler.php';

async function callApi(data) {
    const res = await fetch(API_URL, {
        method: 'POST',
        body: JSON.stringify(data),
        headers: { 'Content-Type': 'application/json' }
    });
    return res.json();
}

// ── Delete Log ─────────────────────────────────────────
async function deleteLog(logId) {
    const confirm = await Swal.fire({
        title: 'ลบบันทึกนี้?',
        text: 'การลบไม่สามารถย้อนกลับได้',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบเลย',
        cancelButtonText: 'ยกเลิก'
    });
    if (!confirm.isConfirmed) return;

    try {
        const result = await callApi({ action: 'delete_log', log_id: logId });
        if (result.status === 'success') {
            Swal.fire({ icon: 'success', title: 'ลบสำเร็จ', timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
        } else throw new Error(result.message);
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message });
    }
}

// ── Update Group Status ────────────────────────────────
async function updateGroupStatus(newStatus) {
    const labels = { active: 'Active', completed: 'Completed', archived: 'Archived' };
    const confirm = await Swal.fire({
        title: `เปลี่ยนสถานะเป็น "${labels[newStatus]}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7c3aed',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    });
    if (!confirm.isConfirmed) {
        // Revert select
        document.getElementById('groupStatusSelect').value = '<?= $group['status'] ?>';
        return;
    }
    try {
        const result = await callApi({ action: 'update_group_status', group_id: GROUP_ID, status: newStatus });
        if (result.status === 'success') {
            Swal.fire({ icon: 'success', title: 'อัปเดตสถานะสำเร็จ', timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
        } else throw new Error(result.message);
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message });
    }
}

// ── Remove Member ──────────────────────────────────────
async function removeMember(targetUserId, name) {
    const confirm = await Swal.fire({
        title: `ลบ "${name}" ออกจากกลุ่ม?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบออก',
        cancelButtonText: 'ยกเลิก'
    });
    if (!confirm.isConfirmed) return;
    try {
        const result = await callApi({ action: 'remove_member', group_id: GROUP_ID, target_user_id: targetUserId });
        if (result.status === 'success') {
            Swal.fire({ icon: 'success', title: 'ลบสมาชิกสำเร็จ', timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
        } else throw new Error(result.message);
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message });
    }
}

<?php if ($canManage): ?>
// ── Add Member Modal ───────────────────────────────────
function openAddMemberModal() {
    document.getElementById('addMemberModal').classList.remove('hidden');
    document.getElementById('memberSearch').focus();
    document.body.style.overflow = 'hidden';
}
function closeAddMemberModal() {
    document.getElementById('addMemberModal').classList.add('hidden');
    document.body.style.overflow = '';
    document.getElementById('memberSearch').value = '';
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('selectedUserId').value = '';
    document.getElementById('confirmAddBtn').disabled = true;
}

let searchTimer;
function searchUsers(query) {
    clearTimeout(searchTimer);
    if (query.length < 2) {
        document.getElementById('searchResults').innerHTML = '';
        return;
    }
    searchTimer = setTimeout(async () => {
        try {
            const res = await callApi({ action: 'get_users', q: query });
            const results = document.getElementById('searchResults');
            if (res.status !== 'success' || !res.data.length) {
                results.innerHTML = '<p class="text-slate-400 text-xs text-center py-4 font-bold">ไม่พบข้อมูล</p>';
                return;
            }
            results.innerHTML = res.data.map(u => `
                <div onclick="selectUser(${u.user_id}, '${u.firstname} ${u.lastname}')"
                     class="flex items-center gap-3 p-3 rounded-2xl cursor-pointer hover:bg-violet-50 transition-all border border-transparent hover:border-violet-200">
                    <div class="w-9 h-9 bg-gradient-to-br from-violet-500 to-purple-600 rounded-xl text-white text-xs font-black flex items-center justify-center shadow-lg">${u.firstname.charAt(0)}</div>
                    <div>
                        <p class="text-xs font-black text-slate-800">${u.firstname} ${u.lastname}</p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">${u.role}</p>
                    </div>
                </div>
            `).join('');
        } catch(e) {}
    }, 300);
}

function selectUser(userId, name) {
    document.getElementById('selectedUserId').value = userId;
    document.getElementById('memberSearch').value = name;
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('confirmAddBtn').disabled = false;
}

async function submitAddMember() {
    const targetUserId = document.getElementById('selectedUserId').value;
    const memberRole   = document.getElementById('memberRoleSelect').value;
    if (!targetUserId) return;
    try {
        const result = await callApi({ action: 'add_member', group_id: GROUP_ID, target_user_id: parseInt(targetUserId), member_role: memberRole });
        if (result.status === 'success') {
            Swal.fire({ icon: 'success', title: 'เพิ่มสมาชิกสำเร็จ', text: result.message, confirmButtonColor: '#7c3aed' })
                .then(() => { closeAddMemberModal(); location.reload(); });
        } else throw new Error(result.message);
    } catch(e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message });
    }
}
<?php endif; ?>
</script>

