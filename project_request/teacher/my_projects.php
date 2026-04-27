<?php
session_start();
$pageTitle = 'โครงการที่รับผิดชอบ';
$pageSubtitle = 'ตรวจสอบงบประมาณและสถานะการใช้จ่ายรายโครงการ';
require_once __DIR__ . '/../components/layout_start.php';

$user = currentUser();
$pdo = getPdo();

$stmt = $pdo->prepare("SELECT * FROM budget_projects WHERE owner_name = ? AND is_active = 1 ORDER BY project_name");
$stmt->execute([$user['full_name']]);
$projects = $stmt->fetchAll();
?>

<div class="space-y-6">
    <?php if (empty($projects)): ?>
        <div class="bg-white rounded-[2.5rem] p-12 text-center border border-slate-100 shadow-xl shadow-slate-200/50">
            <i class="bi bi-inbox text-5xl text-slate-200 mb-4 block"></i>
            <p class="text-slate-400 font-bold">ไม่พบโครงการที่คุณเป็นผู้รับผิดชอบ</p>
        </div>
    <?php endif; ?>

    <?php foreach ($projects as $p): 
        $total = $p['budget_subsidy'] + $p['budget_quality'] + $p['budget_revenue'] + $p['budget_operation'] + $p['budget_reserve'];
        
        // Fetch used budget
        $stmt = $pdo->prepare("SELECT SUM(amount_requested) as spent FROM project_requests WHERE budget_project_id = ? AND status = 'approved'");
        $stmt->execute([$p['id']]);
        $spent = $stmt->fetch()['spent'] ?? 0;
        $remaining = $total - $spent;
    ?>
    <div class="bg-white rounded-[2.5rem] overflow-hidden border border-slate-100 shadow-xl shadow-slate-200/50 flex flex-col lg:flex-row">
        <!-- Project Info -->
        <div class="lg:w-1/3 p-8 border-b lg:border-b-0 lg:border-r border-slate-50 bg-slate-50/30">
            <div class="flex items-center gap-3 mb-4">
                <span class="text-[10px] font-black bg-blue-600 text-white px-3 py-1 rounded-full uppercase tracking-widest">Project Info</span>
                <span class="text-[10px] font-black bg-slate-100 text-slate-400 px-3 py-1 rounded-full uppercase tracking-widest">FY <?= $p['fiscal_year'] ?></span>
            </div>
            <h4 class="text-xl font-black text-slate-800 mb-2"><?= htmlspecialchars($p['project_name']) ?></h4>
            <p class="text-sm text-slate-500 font-bold mb-6"><?= htmlspecialchars($p['activity']) ?></p>
            
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400">งบประมาณรวม</span>
                    <span class="font-black text-slate-700"><?= number_format($total, 2) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400">ใช้ไปแล้ว</span>
                    <span class="font-black text-emerald-600"><?= number_format($spent, 2) ?></span>
                </div>
                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <span class="text-xs font-bold text-slate-800">คงเหลือ</span>
                    <span class="text-lg font-black text-blue-600"><?= number_format($remaining, 2) ?></span>
                </div>
            </div>

            <a href="request_form.php?project_id=<?= $p['id'] ?>" class="mt-8 w-full bg-blue-600 text-white py-4 rounded-2xl font-black text-sm shadow-xl shadow-blue-100 flex items-center justify-center gap-3 hover:bg-blue-700 transition-all">
                <i class="bi bi-plus-circle"></i>
                ขอดำเนินโครงการ
            </a>
        </div>

        <!-- Budget Breakdown -->
        <div class="flex-1 p-8">
            <h5 class="text-sm font-black text-slate-400 uppercase tracking-[0.2em] mb-6">รายละเอียดงบประมาณแต่ละประเภท</h5>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php 
                $types = [
                    ['key' => 'budget_subsidy', 'label' => 'งบเงินอุดหนุน', 'color' => 'blue'],
                    ['key' => 'budget_quality', 'label' => 'งบพัฒนาคุณภาพผู้เรียน', 'color' => 'indigo'],
                    ['key' => 'budget_revenue', 'label' => 'เงินรายได้สถานศึกษา', 'color' => 'emerald'],
                    ['key' => 'budget_operation', 'label' => 'งบงานประจำ', 'color' => 'amber'],
                    ['key' => 'budget_reserve', 'label' => 'เงินสำรองจ่าย', 'color' => 'rose'],
                ];
                foreach ($types as $t):
                    $val = $p[$t['key']];
                ?>
                <div class="p-4 rounded-2xl bg-white border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2"><?= $t['label'] ?></p>
                    <p class="text-lg font-black text-<?= $t['color'] ?>-600"><?= number_format($val, 2) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Recent Activity for this project -->
            <div class="mt-8 pt-8 border-t border-slate-50">
                <h5 class="text-sm font-black text-slate-400 uppercase tracking-[0.2em] mb-4">ประวัติการขอใช้เงินล่าสุด</h5>
                <?php
                    $stmt = $pdo->prepare("SELECT * FROM project_requests WHERE budget_project_id = ? ORDER BY id DESC LIMIT 3");
                    $stmt->execute([$p['id']]);
                    $reqs = $stmt->fetchAll();
                ?>
                <div class="space-y-2">
                    <?php if (empty($reqs)): ?>
                        <p class="text-xs text-slate-300 font-medium italic">ยังไม่มีประวัติการขอใช้เงิน</p>
                    <?php endif; ?>
                    <?php foreach ($reqs as $r): ?>
                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                        <div class="flex items-center gap-3">
                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider <?= STATUS_COLORS[$r['status']] ?>"><?= STATUS_LABELS[$r['status']] ?></span>
                            <span class="text-xs font-bold text-slate-600"><?= htmlspecialchars($r['reason']) ?></span>
                        </div>
                        <span class="text-xs font-black text-slate-800"><?= number_format($r['amount_requested'], 2) ?> บาท</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
