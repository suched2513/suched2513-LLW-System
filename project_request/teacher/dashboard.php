<?php
session_start();
$pageTitle = 'Dashboard';
$pageSubtitle = 'ภาพรวมโครงการที่รับผิดชอบ';
require_once __DIR__ . '/../components/layout_start.php';

$user = currentUser();
$pdo = getPdo();

try {
    // 1. Fetch Projects assigned to this teacher
    $stmt = $pdo->prepare("SELECT * FROM budget_projects WHERE owner_name = ? AND is_active = 1");
    $stmt->execute([$user['full_name']]);
    $projects = $stmt->fetchAll();
    
    // Calculate Stats
    $totalAssigned = 0;
    foreach ($projects as $p) {
        $totalAssigned += ($p['budget_subsidy'] + $p['budget_quality'] + $p['budget_revenue'] + $p['budget_operation'] + $p['budget_reserve']);
    }
    
    // 2. Fetch total spent/requested
    $stmt = $pdo->prepare("SELECT SUM(amount_requested) as spent FROM project_requests WHERE user_id = ? AND status != 'rejected'");
    $stmt->execute([$user['id']]);
    $spentAmount = $stmt->fetch()['spent'] ?? 0;
    
    // 3. Pending requests count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM project_requests WHERE user_id = ? AND status = 'submitted'");
    $stmt->execute([$user['id']]);
    $pendingCount = $stmt->fetch()['count'] ?? 0;

} catch (Exception $e) {
    error_log($e->getMessage());
}
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
    <!-- Total Budget Assigned -->
    <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-[2rem] p-8 text-white shadow-xl shadow-blue-200">
        <p class="text-[11px] font-bold text-indigo-100 uppercase tracking-widest mb-1">งบประมาณที่ได้รับจัดสรร</p>
        <h3 class="text-3xl font-black"><?= number_format($totalAssigned, 2) ?></h3>
        <p class="text-xs text-indigo-100/60 mt-4 font-bold uppercase tracking-widest">จาก <?= count($projects) ?> โครงการ/กิจกรรม</p>
    </div>

    <!-- Spent/Requested -->
    <div class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">งบที่ขอใช้ไปแล้ว</p>
        <h3 class="text-3xl font-black text-slate-800"><?= number_format($spentAmount, 2) ?></h3>
        <div class="w-full bg-slate-100 h-2 rounded-full mt-4 overflow-hidden">
            <?php $percent = $totalAssigned > 0 ? ($spentAmount / $totalAssigned) * 100 : 0; ?>
            <div class="bg-blue-500 h-full" style="width: <?= $percent ?>%"></div>
        </div>
    </div>

    <!-- Pending Requests -->
    <div class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">คำขอที่รออนุมัติ</p>
        <h3 class="text-3xl font-black text-slate-800"><?= number_format($pendingCount) ?></h3>
        <p class="text-xs text-slate-400 mt-4 font-bold">รอการพิจารณาจากผู้อำนวยการ</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Project List Table -->
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <div class="flex items-center justify-between mb-6">
            <h4 class="text-lg font-black text-slate-800">โครงการปัจจุบัน</h4>
            <a href="my_projects.php" class="text-xs font-bold text-blue-600 hover:underline">ดูทั้งหมด</a>
        </div>
        <div class="space-y-4">
            <?php if (empty($projects)): ?>
                <p class="text-center py-8 text-slate-400 font-medium">ไม่พบโครงการที่รับผิดชอบ</p>
            <?php endif; ?>
            <?php foreach (array_slice($projects, 0, 5) as $p): ?>
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-between group hover:border-blue-200 transition-all">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-blue-600 shadow-sm">
                        <i class="bi bi-folder2-open"></i>
                    </div>
                    <div>
                        <p class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($p['project_name']) ?></p>
                        <p class="text-[10px] text-slate-400 font-bold uppercase"><?= htmlspecialchars($p['activity']) ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-black text-slate-800 text-sm"><?= number_format($p['budget_subsidy'] + $p['budget_quality'] + $p['budget_revenue'] + $p['budget_operation'] + $p['budget_reserve'], 2) ?></p>
                    <p class="text-[9px] text-slate-400 font-bold uppercase">Budget</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50">
        <h4 class="text-lg font-black text-slate-800 mb-6">ดำเนินการ</h4>
        <div class="grid grid-cols-1 gap-4">
            <a href="request_form.php" class="flex items-center gap-4 p-6 bg-blue-600 rounded-[2rem] text-white shadow-xl shadow-blue-200 hover:scale-[1.02] transition-all group">
                <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
                    <i class="bi bi-plus-lg"></i>
                </div>
                <div>
                    <p class="font-black text-lg">ขอดำเนินโครงการ</p>
                    <p class="text-xs text-blue-100 font-bold uppercase">New Project Request</p>
                </div>
            </a>
            <a href="request_list.php" class="flex items-center gap-4 p-6 bg-slate-50 rounded-[2rem] text-slate-700 border border-slate-100 hover:bg-slate-100 transition-all group">
                <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center text-2xl text-slate-400 shadow-sm">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <p class="font-black text-lg">ประวัติคำขอ</p>
                    <p class="text-xs text-slate-400 font-bold uppercase">Request History</p>
                </div>
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
