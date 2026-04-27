<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
$user = currentUser();

$pdo = getPdo();
$dept_id = $_GET['department_id'] ?? '';

// Role Guard: Teacher can only see their own dept (if we want to restrict)
// But the prompt says head (เฉพาะฝ่ายตัวเอง) for budget_by_dept
// For now, let's allow Director and Budget Officer to see all, others see their own.
if (!in_array($user['role'], ['director', 'budget_officer', 'admin'])) {
    // Try to find the dept ID of the teacher
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
    $stmt->execute([$user['dept']]);
    $ownDept = $stmt->fetch();
    $dept_id = $ownDept['id'] ?? die('Unauthorized');
}

$pageTitle = 'งบประมาณรายฝ่าย';
$pageSubtitle = 'รายละเอียดโครงการและการเบิกจ่ายภายในฝ่ายงาน';
require_once __DIR__ . '/../components/layout_start.php';

$depts = $pdo->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();

if ($dept_id) {
    // Fetch Projects for this dept
    $stmt = $pdo->prepare("
        SELECT bp.*, 
               COALESCE(spent.total_spent, 0) as used_total
        FROM budget_projects bp
        LEFT JOIN (
            SELECT budget_project_id, SUM(amount_requested) as total_spent 
            FROM project_requests WHERE status = 'approved' GROUP BY budget_project_id
        ) spent ON spent.budget_project_id = bp.id
        WHERE bp.department_id = ? AND bp.fiscal_year = ?
    ");
    $stmt->execute([$dept_id, FISCAL_YEAR]);
    $projects = $stmt->fetchAll();
    
    $selectedDept = array_filter($depts, fn($d) => $d['id'] == $dept_id);
    $deptName = !empty($selectedDept) ? reset($selectedDept)['name'] : 'ไม่พบข้อมูล';
}
?>

<div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100 mb-8">
    <form class="flex items-end gap-4 mb-10">
        <div class="w-64">
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">เลือกฝ่าย/กลุ่มงาน</label>
            <select name="department_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold outline-none" onchange="this.form.submit()">
                <option value="">-- เลือกฝ่าย --</option>
                <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $dept_id == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($dept_id): ?>
    <div class="mb-8">
        <h4 class="text-xl font-black text-slate-800">ฝ่าย: <?= htmlspecialchars($deptName) ?></h4>
        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Project Budget Breakdown (FY <?= FISCAL_YEAR ?>)</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left border-b border-slate-50">
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">โครงการ</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">งบจัดสรร</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">ใช้ไป</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">คงเหลือ</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">ร้อยละ (%)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($projects as $p): 
                    $totalAlloc = $p['budget_subsidy'] + $p['budget_quality'] + $p['budget_revenue'] + $p['budget_operation'] + $p['budget_reserve'];
                    $remaining = $totalAlloc - $p['used_total'];
                    $percent = $totalAlloc > 0 ? ($p['used_total'] / $totalAlloc) * 100 : 0;
                ?>
                <tr class="group hover:bg-slate-50/50 transition-all">
                    <td class="py-5 px-4">
                        <p class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($p['project_name']) ?></p>
                        <p class="text-[10px] text-slate-400 font-bold uppercase"><?= htmlspecialchars($p['activity']) ?></p>
                    </td>
                    <td class="py-5 px-4 text-right font-bold text-slate-500"><?= number_format($totalAlloc, 2) ?></td>
                    <td class="py-5 px-4 text-right font-black text-blue-600"><?= number_format($p['used_total'], 2) ?></td>
                    <td class="py-5 px-4 text-right font-bold text-slate-400"><?= number_format($remaining, 2) ?></td>
                    <td class="py-5 px-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <span class="text-xs font-black"><?= number_format($percent, 1) ?>%</span>
                            <div class="w-12 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="bg-blue-600 h-full" style="width: <?= $percent ?>%"></div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="py-20 text-center text-slate-300">
        <i class="bi bi-search text-5xl mb-4 block"></i>
        <p class="font-bold">กรุณาเลือกฝ่ายเพื่อแสดงข้อมูล</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
