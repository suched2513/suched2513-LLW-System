<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard — super_admin only
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}
if ($_SESSION['llw_role'] !== 'super_admin') {
    header('Location: /plc_system/dashboard.php'); exit();
}

$pdo = getPdo();

// System-wide stats
$stmt = $pdo->query("SELECT COUNT(*) FROM plc_groups");
$totalGroups = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM plc_groups WHERE status = 'active'");
$activeGroups = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM plc_logs");
$totalLogs = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM plc_members");
$totalParticipants = (int)$stmt->fetchColumn();

// All groups with stats
$stmt = $pdo->query("
    SELECT g.*,
           u.firstname AS creator_name,
           u.lastname  AS creator_lastname,
           (SELECT COUNT(*) FROM plc_members m WHERE m.group_id = g.id) AS member_count,
           (SELECT COUNT(*) FROM plc_logs l WHERE l.group_id = g.id) AS log_count,
           (SELECT COUNT(DISTINCT phase) FROM plc_logs l WHERE l.group_id = g.id) AS phase_count
    FROM plc_groups g
    JOIN llw_users u ON g.created_by = u.user_id
    ORDER BY g.created_at DESC
");
$allGroups = $stmt->fetchAll();

$pageTitle = 'PLC Admin Overview';
$pageSubtitle = 'จัดการระบบชุมชนแห่งการเรียนรู้ทางวิชาชีพ';
$activeSystem = 'plc';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="space-y-8 animate-in fade-in duration-700">

    <!-- Header Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-violet-600 to-purple-700 rounded-[2rem] p-8 text-white shadow-xl shadow-violet-200/50 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-32 h-32 bg-white/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
            <p class="text-xs font-black uppercase tracking-[0.2em] opacity-80">กลุ่ม PLC ทั้งหมด</p>
            <div class="flex items-end justify-between mt-4">
                <p class="text-5xl font-black italic"><?= $totalGroups ?></p>
                <i class="bi bi-collection-fill text-4xl opacity-30"></i>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-8 shadow-xl shadow-slate-100/50 border border-slate-100 group hover:border-emerald-200 transition-all">
            <p class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">กำลัง Active</p>
            <div class="flex items-end justify-between mt-4">
                <p class="text-5xl font-black text-slate-800 italic"><?= $activeGroups ?></p>
                <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500">
                    <i class="bi bi-activity text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-8 shadow-xl shadow-slate-100/50 border border-slate-100 group hover:border-violet-200 transition-all">
            <p class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">บันทึกกิจกรรมรวม</p>
            <div class="flex items-end justify-between mt-4">
                <p class="text-5xl font-black text-slate-800 italic"><?= $totalLogs ?></p>
                <div class="w-12 h-12 bg-violet-50 rounded-2xl flex items-center justify-center text-violet-500">
                    <i class="bi bi-journal-text text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-8 shadow-xl shadow-slate-100/50 border border-slate-100 group hover:border-blue-200 transition-all">
            <p class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">ครูที่เข้าร่วม</p>
            <div class="flex items-end justify-between mt-4">
                <p class="text-5xl font-black text-slate-800 italic"><?= $totalParticipants ?></p>
                <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-500">
                    <i class="bi bi-people-fill text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- All Groups Table -->
    <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
        <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100">
            <div>
                <h3 class="text-lg font-black text-slate-800">กลุ่ม PLC ทั้งหมด</h3>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">All PLC Groups System-wide</p>
            </div>
        </div>

        <?php if (empty($allGroups)): ?>
        <div class="p-16 text-center">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-300">
                <i class="bi bi-journal-x text-4xl"></i>
            </div>
            <p class="text-slate-400 font-bold">ยังไม่มีกลุ่ม PLC ในระบบ</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left text-xs font-black text-slate-400 uppercase tracking-widest px-6 py-4">#</th>
                        <th class="text-left text-xs font-black text-slate-400 uppercase tracking-widest px-6 py-4">ชื่อกลุ่ม</th>
                        <th class="text-left text-xs font-black text-slate-400 uppercase tracking-widest px-6 py-4">ผู้สร้าง</th>
                        <th class="text-left text-xs font-black text-slate-400 uppercase tracking-widest px-6 py-4">ปีการศึกษา</th>
                        <th class="text-center text-xs font-black text-slate-400 uppercase tracking-widest px-6 py-4">สมาชิก</th>
                        <th class="text-center text-xs font-black text-slate-400 uppercase tracking-widest px-6 py-4">PDCA Progress</th>
                        <th class="text-center text-xs font-black text-slate-400 uppercase tracking-widest px-6 py-4">บันทึก</th>
                        <th class="text-center text-xs font-black text-slate-400 uppercase tracking-widest px-6 py-4">สถานะ</th>
                        <th class="text-center text-xs font-black text-slate-400 uppercase tracking-widest px-6 py-4">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($allGroups as $i => $g):
                        $progress = ($g['phase_count'] / 4) * 100;
                        $statusColors = [
                            'active'    => 'bg-emerald-50 text-emerald-600',
                            'completed' => 'bg-blue-50 text-blue-600',
                            'archived'  => 'bg-slate-100 text-slate-500',
                        ];
                        $statusClass = $statusColors[$g['status']] ?? 'bg-slate-50 text-slate-500';
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="px-6 py-4 text-xs font-black text-slate-300"><?= $i + 1 ?></td>
                        <td class="px-6 py-4">
                            <a href="view_group.php?id=<?= $g['id'] ?>" class="font-black text-sm text-slate-800 hover:text-violet-600 transition-colors">
                                <?= htmlspecialchars($g['group_name']) ?>
                            </a>
                            <?php if ($g['target_group']): ?>
                            <p class="text-xs text-slate-400 font-bold mt-0.5 italic"><?= htmlspecialchars($g['target_group']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-xs font-bold text-slate-600"><?= htmlspecialchars($g['creator_name']) ?> <?= htmlspecialchars($g['creator_lastname']) ?></td>
                        <td class="px-6 py-4 text-xs font-bold text-slate-600 font-mono"><?= htmlspecialchars($g['academic_year']) ?>/<?= htmlspecialchars($g['semester']) ?></td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 bg-violet-50 text-violet-600 text-xs font-black rounded-full"><?= $g['member_count'] ?> คน</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-violet-500 to-purple-500 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                                </div>
                                <span class="text-xs font-black text-violet-600 w-10 text-right"><?= number_format($progress) ?>%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center text-xs font-black text-slate-600"><?= $g['log_count'] ?></td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider <?= $statusClass ?>"><?= $g['status'] ?></span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="view_group.php?id=<?= $g['id'] ?>" class="w-8 h-8 bg-violet-50 text-violet-600 rounded-xl flex items-center justify-center hover:bg-violet-600 hover:text-white transition-all" title="ดูรายละเอียด">
                                    <i class="bi bi-eye text-xs"></i>
                                </a>
                                <a href="report_print.php?id=<?= $g['id'] ?>" target="_blank" class="w-8 h-8 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all" title="พิมพ์รายงาน">
                                    <i class="bi bi-printer text-xs"></i>
                                </a>
                                <button onclick="deleteGroup(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['group_name'])) ?>')" class="w-8 h-8 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all opacity-0 group-hover:opacity-100" title="ลบกลุ่ม">
                                    <i class="bi bi-trash3 text-xs"></i>
                                </button>
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

<script>
async function deleteGroup(groupId, name) {
    const confirm = await Swal.fire({
        title: `ลบกลุ่ม "${name}"?`,
        html: '<p class="text-sm text-slate-500">บันทึกกิจกรรมและสมาชิกทั้งหมดจะถูกลบออกด้วย<br>การกระทำนี้ไม่สามารถย้อนกลับได้</p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบทั้งกลุ่มเลย',
        cancelButtonText: 'ยกเลิก'
    });
    if (!confirm.isConfirmed) return;

    try {
        const res = await fetch('api/plc_handler.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete_group', group_id: groupId }),
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await res.json();
        if (result.status === 'success') {
            Swal.fire({ icon: 'success', title: 'ลบกลุ่มสำเร็จ', timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
        } else throw new Error(result.message);
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message });
    }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
