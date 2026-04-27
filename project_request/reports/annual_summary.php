<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
checkRole(['director', 'budget_officer', 'admin']);

$pageTitle = 'สรุปปีงบประมาณ';
$pageSubtitle = 'รายงานสรุปภาพรวมการเบิกจ่ายโครงการประจำปีงบประมาณ';
require_once __DIR__ . '/../components/layout_start.php';

$pdo = getPdo();
$fy = $_GET['fiscal_year'] ?? FISCAL_YEAR;

// Summary Query
$stmt = $pdo->prepare("
    SELECT 
        d.name as dept_name,
        COUNT(bp.id) as total_projects,
        SUM(bp.budget_subsidy + bp.budget_quality + bp.budget_revenue + bp.budget_operation + bp.budget_reserve) as alloc_total,
        COALESCE(SUM(pr_all.total_req), 0) as req_total,
        COALESCE(SUM(pr_app.total_app), 0) as app_total,
        COUNT(pr_app.id) as app_count
    FROM departments d
    JOIN budget_projects bp ON bp.department_id = d.id
    LEFT JOIN (
        SELECT budget_project_id, SUM(amount_requested) as total_req FROM project_requests GROUP BY budget_project_id
    ) pr_all ON pr_all.budget_project_id = bp.id
    LEFT JOIN (
        SELECT id, budget_project_id, SUM(amount_requested) as total_app FROM project_requests WHERE status = 'approved' GROUP BY budget_project_id
    ) pr_app ON pr_app.budget_project_id = bp.id
    WHERE bp.fiscal_year = ?
    GROUP BY d.id
    ORDER BY d.order_no
");
$stmt->execute([$fy]);
$summary = $stmt->fetchAll();
?>

<div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
        <form class="flex items-center gap-4">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 px-1">เลือกปีงบประมาณ</label>
                <div class="flex gap-2">
                    <input type="text" name="fiscal_year" value="<?= htmlspecialchars($fy) ?>" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold outline-none w-32">
                    <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-slate-900 transition-all">แสดงผล</button>
                </div>
            </div>
        </form>
        <div class="flex gap-2">
            <button class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-black text-xs shadow-xl shadow-blue-100 flex items-center gap-2">
                <i class="bi bi-printer"></i>
                พิมพ์รายงานสรุป
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left border-b border-slate-50">
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ฝ่าย</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">โครงการ</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">งบจัดสรรรวม</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">ยอดที่ขอใช้</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">ยอดอนุมัติจริง</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">อนุมัติ (โครงการ)</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">คงเหลือ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php 
                $gAlloc = 0; $gReq = 0; $gApp = 0;
                foreach ($summary as $row): 
                    $gAlloc += $row['alloc_total'];
                    $gReq += $row['req_total'];
                    $gApp += $row['app_total'];
                    $rem = $row['alloc_total'] - $row['app_total'];
                ?>
                <tr class="group hover:bg-slate-50/50 transition-all">
                    <td class="py-5 px-4 font-black text-slate-700"><?= htmlspecialchars($row['dept_name']) ?></td>
                    <td class="py-5 px-4 text-center font-bold text-slate-500"><?= $row['total_projects'] ?></td>
                    <td class="py-5 px-4 text-right font-bold text-slate-600"><?= number_format($row['alloc_total'], 2) ?></td>
                    <td class="py-5 px-4 text-right font-bold text-amber-600"><?= number_format($row['req_total'], 2) ?></td>
                    <td class="py-5 px-4 text-right font-black text-emerald-600"><?= number_format($row['app_total'], 2) ?></td>
                    <td class="py-5 px-4 text-center font-bold text-slate-500"><?= $row['app_count'] ?></td>
                    <td class="py-5 px-4 text-right font-bold text-slate-400"><?= number_format($rem, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-slate-800 text-white rounded-b-2xl">
                <tr>
                    <td class="py-6 px-4 rounded-bl-[1.5rem] font-black uppercase">รวมทั่วโรงเรียน</td>
                    <td colspan="1"></td>
                    <td class="py-6 px-4 text-right font-black"><?= number_format($gAlloc, 2) ?></td>
                    <td class="py-6 px-4 text-right font-black"><?= number_format($gReq, 2) ?></td>
                    <td class="py-6 px-4 text-right font-black text-emerald-400"><?= number_format($gApp, 2) ?></td>
                    <td></td>
                    <td class="py-6 px-4 text-right rounded-br-[1.5rem] font-black"><?= number_format($gAlloc - $gApp, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
