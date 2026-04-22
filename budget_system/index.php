<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'ระบบบริหารงบประมาณ (SBMS)';
$pageSubtitle = 'จัดการงบประมาณ โครงการ และกิจกรรมโรงเรียน';
$activeSystem = 'budget';

try {
    $pdo = getPdo();
    
    // Get active fiscal year
    $stmt = $pdo->query("SELECT id, year_name FROM sbms_fiscal_years WHERE is_active = 1 LIMIT 1");
    $activeYear = $stmt->fetch();
    $yearId = $activeYear['id'] ?? 0;

    // KPI 1: Total Budget
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM sbms_budgets WHERE fiscal_year_id = ?");
    $stmt->execute([$yearId]);
    $totalBudget = (float)($stmt->fetchColumn() ?: 0);

    // KPI 2: Total Used
    $stmt = $pdo->prepare("SELECT SUM(used_amount) FROM sbms_projects WHERE fiscal_year_id = ?");
    $stmt->execute([$yearId]);
    $totalUsed = (float)($stmt->fetchColumn() ?: 0);

    $usedPercent = $totalBudget > 0 ? round(($totalUsed / $totalBudget) * 100, 1) : 0;

    // KPI 3: Total Activities
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sbms_activities a JOIN sbms_projects p ON a.project_id = p.id WHERE p.fiscal_year_id = ?");
    $stmt->execute([$yearId]);
    $totalActivities = $stmt->fetchColumn() ?: 0;

    // Get Project Activities for Table
    $stmt = $pdo->prepare("
        SELECT a.*, p.project_name 
        FROM sbms_activities a
        JOIN sbms_projects p ON a.project_id = p.id
        WHERE p.fiscal_year_id = ?
        ORDER BY a.activity_name
    ");
    $stmt->execute([$yearId]);
    $activities = $stmt->fetchAll();

    // Get Recent Disbursements (Requests)
    $stmt = $pdo->prepare("
        SELECT d.*, a.activity_name 
        FROM sbms_disbursements d
        JOIN sbms_activities a ON d.activity_id = a.id
        ORDER BY d.created_at DESC LIMIT 10
    ");
    $stmt->execute();
    $disbursements = $stmt->fetchAll();

} catch (Exception $e) {
    error_log($e->getMessage());
}

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Alerts -->
<?php if (isset($_GET['success'])): ?>
<script>Swal.fire({ icon: 'success', title: 'บันทึกคำขอสำเร็จ', showConfirmButton: false, timer: 2000 });</script>
<?php endif; ?>
<?php if (isset($_GET['summary_success'])): ?>
<script>Swal.fire({ icon: 'success', title: 'สรุปโครงการเรียบร้อยแล้ว', showConfirmButton: false, timer: 2000 });</script>
<?php endif; ?>

<div class="space-y-8">
    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-emerald-500 to-teal-700 rounded-[2.5rem] p-8 text-white shadow-2xl shadow-emerald-200/50 relative overflow-hidden group">
            <i class="bi bi-wallet2 absolute -right-4 -bottom-4 text-9xl opacity-10 group-hover:scale-110 transition-transform"></i>
            <p class="text-[10px] font-black opacity-80 uppercase tracking-[0.2em]">งบประมาณที่ได้รับ</p>
            <p class="text-4xl font-black mt-3"><?= number_format($totalBudget) ?> <span class="text-lg opacity-60">฿</span></p>
            <div class="mt-4 flex items-center gap-2 text-xs font-bold text-emerald-100">
                <i class="bi bi-calendar-event"></i> ปีงบประมาณ <?= htmlspecialchars($activeYear['year_name'] ?? '-') ?>
            </div>
        </div>

        <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] p-8 border border-white shadow-xl shadow-slate-200/50 relative overflow-hidden group">
            <i class="bi bi-cash-stack absolute -right-4 -bottom-4 text-9xl text-emerald-500/10 group-hover:scale-110 transition-transform"></i>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">ใช้จ่ายไปแล้ว</p>
            <p class="text-4xl font-black mt-3 text-slate-800"><?= number_format($totalUsed) ?> <span class="text-lg text-slate-400">฿</span></p>
            <div class="mt-4 flex items-center gap-2 text-xs font-bold text-emerald-600">
                <i class="bi bi-graph-up-arrow"></i> <?= $usedPercent ?>% ของงบประมาณ
            </div>
        </div>

        <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] p-8 border border-white shadow-xl shadow-slate-200/50 relative overflow-hidden group">
            <i class="bi bi-check2-circle absolute -right-4 -bottom-4 text-9xl text-emerald-500/10 group-hover:scale-110 transition-transform"></i>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">ใช้จ่ายไปแล้วร้อยละ</p>
            <p class="text-4xl font-black mt-3 text-emerald-600"><?= $usedPercent ?> <span class="text-lg text-slate-400">%</span></p>
            <div class="mt-4 w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                <div class="bg-emerald-500 h-full" style="width: <?= $usedPercent ?>%"></div>
            </div>
        </div>

        <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] p-8 border border-white shadow-xl shadow-slate-200/50 relative overflow-hidden group">
            <i class="bi bi-activity absolute -right-4 -bottom-4 text-9xl text-emerald-500/10 group-hover:scale-110 transition-transform"></i>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">จำนวนกิจกรรมทั้งหมด</p>
            <p class="text-4xl font-black mt-3 text-slate-800"><?= number_format($totalActivities) ?> <span class="text-lg text-slate-400">กิจกรรม</span></p>
            <div class="mt-4 flex items-center gap-2 text-xs font-bold text-slate-500">
                <i class="bi bi-list-task"></i> รวมทุกโครงการในปีนี้
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Activity Table -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-xl border border-white p-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h3 class="text-xl font-black text-slate-800">รายการกิจกรรมโครงการ</h3>
                        <p class="text-xs text-slate-400 font-bold mt-1 uppercase tracking-widest">Project Activity Tracking</p>
                    </div>
                    <a href="request_form.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl font-black text-sm shadow-lg shadow-emerald-200 hover:scale-105 transition-all flex items-center gap-2">
                        <i class="bi bi-plus-lg"></i> ขออนุญาตดำเนินงาน
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-separate border-spacing-y-3">
                        <thead>
                            <tr class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                <th class="pb-4 px-4">กิจกรรม / โครงการ</th>
                                <th class="pb-4 px-4">งบจัดสรร</th>
                                <th class="pb-4 px-4">เบิกจ่าย</th>
                                <th class="pb-4 px-4">คงเหลือ</th>
                                <th class="pb-4 px-4 text-right">สัดส่วน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $act): 
                                $remain = (float)$act['budget_allocated'] - (float)$act['budget_used'];
                                $pct = (float)$act['budget_allocated'] > 0 ? round(((float)$act['budget_used'] / (float)$act['budget_allocated']) * 100, 1) : 0;
                            ?>
                            <tr class="bg-slate-50/50 hover:bg-emerald-50/50 transition-colors rounded-2xl group">
                                <td class="py-4 px-4 rounded-l-2xl">
                                    <p class="text-sm font-black text-slate-700"><?= htmlspecialchars($act['activity_name']) ?></p>
                                    <p class="text-[10px] font-bold text-slate-400 mt-1"><?= htmlspecialchars($act['project_name']) ?></p>
                                </td>
                                <td class="py-4 px-4 font-bold text-sm text-slate-600">
                                    <?= number_format($act['budget_allocated']) ?>
                                </td>
                                <td class="py-4 px-4 font-bold text-sm text-emerald-600">
                                    <?= number_format($act['budget_used']) ?>
                                </td>
                                <td class="py-4 px-4 font-bold text-sm <?= $remain < 0 ? 'text-rose-500' : 'text-slate-500' ?>">
                                    <?= number_format($remain) ?>
                                </td>
                                <td class="py-4 px-4 rounded-r-2xl text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="text-[10px] font-black text-slate-400"><?= $pct ?>%</span>
                                        <div class="w-12 bg-slate-200 h-1.5 rounded-full overflow-hidden">
                                            <div class="bg-emerald-500 h-full" style="width: <?= $pct ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Requests -->
            <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-xl border border-white p-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h3 class="text-xl font-black text-slate-800">รายการขอเบิกจ่ายล่าสุด</h3>
                        <p class="text-xs text-slate-400 font-bold mt-1 uppercase tracking-widest">Recent Disbursement Requests</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-separate border-spacing-y-3">
                        <thead>
                            <tr class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                <th class="pb-4 px-4">เลขที่ / วันที่</th>
                                <th class="pb-4 px-4">รายการ</th>
                                <th class="pb-4 px-4 text-center">สถานะ</th>
                                <th class="pb-4 px-4 text-right">เครื่องมือ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disbursements as $d): ?>
                            <tr class="bg-white hover:bg-slate-50 transition-colors rounded-2xl">
                                <td class="py-4 px-4 rounded-l-2xl">
                                    <p class="text-xs font-black text-slate-700"><?= htmlspecialchars($d['doc_no']) ?></p>
                                    <p class="text-[10px] font-bold text-slate-400"><?= date('d/m/Y', strtotime($d['created_at'])) ?></p>
                                </td>
                                <td class="py-4 px-4">
                                    <p class="text-sm font-bold text-slate-600"><?= htmlspecialchars($d['activity_name']) ?></p>
                                    <p class="text-[10px] font-black text-emerald-600">฿ <?= number_format($d['amount'], 2) ?></p>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <?php if ($d['status'] === 'pending'): ?>
                                        <span class="px-3 py-1 rounded-full bg-amber-50 text-amber-600 text-[10px] font-black uppercase">ยังไม่สรุป</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase">สรุปแล้ว</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4 rounded-r-2xl text-right flex items-center justify-end gap-2">
                                    <a href="print_request.php?id=<?= $d['id'] ?>" target="_blank" title="พิมพ์ใบขออนุญาต" class="w-8 h-8 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php if ($d['status'] === 'pending'): ?>
                                    <a href="summary_form.php?id=<?= $d['id'] ?>" title="กรอกสรุปโครงการ" class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Charts / Sidebar info -->
        <div class="space-y-8">
            <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-xl border border-white p-8">
                <h3 class="text-lg font-black text-slate-800 mb-6">สัดส่วนการใช้งบประมาณ</h3>
                <canvas id="budgetChart" class="max-h-[300px]"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('budgetChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['ใช้ไปแล้ว', 'คงเหลือ'],
            datasets: [{
                data: [<?= $totalUsed ?>, <?= max(0, $totalBudget - $totalUsed) ?>],
                backgroundColor: ['#10b981', '#f1f5f9'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            cutout: '75%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: 'Prompt', weight: 'bold' } } }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
