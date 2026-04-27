<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
checkRole(['director', 'budget_officer', 'admin']);

$pageTitle = 'ภาพรวมการใช้งบประมาณ';
$pageSubtitle = 'ตารางสรุปการเบิกจ่ายแยกตามฝ่ายและกลุ่มงาน';
require_once __DIR__ . '/../components/layout_start.php';

$pdo = getPdo();
$fy = $_GET['fiscal_year'] ?? FISCAL_YEAR;
$dept_id = $_GET['department_id'] ?? '';

// Build Query
$sql = "SELECT * FROM v_budget_usage WHERE fiscal_year = ?";
$params = [$fy];

if ($dept_id) {
    $sql .= " AND department_id = ?";
    $params[] = $dept_id;
}

$sql .= " ORDER BY department_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Fetch departments for filter
$depts = $pdo->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();
?>

<div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100 mb-8">
    <!-- Filter Bar -->
    <form class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mb-8">
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">ปีงบประมาณ</label>
            <input type="text" name="fiscal_year" value="<?= htmlspecialchars($fy) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold outline-none">
        </div>
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">เลือกฝ่าย</label>
            <select name="department_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold outline-none">
                <option value="">ทั้งหมด</option>
                <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $dept_id == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-2 flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all flex-1">
                ค้นหาข้อมูล
            </button>
            <a href="export_excel.php?type=budget_overview&fiscal_year=<?= $fy ?>&department_id=<?= $dept_id ?>" class="bg-emerald-500 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-emerald-100 hover:bg-emerald-600 transition-all">
                <i class="bi bi-file-earmark-excel"></i>
            </a>
            <a href="export_pdf.php?type=budget_overview&fiscal_year=<?= $fy ?>&department_id=<?= $dept_id ?>" class="bg-rose-500 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-rose-100 hover:bg-rose-600 transition-all">
                <i class="bi bi-file-earmark-pdf"></i>
            </a>
        </div>
    </form>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left border-b border-slate-50">
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ฝ่าย / กลุ่มงาน</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">งบจัดสรร</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">ใช้ไป</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">คงเหลือ</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">ร้อยละ (%)</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">สถานะ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php 
                $sumAlloc = 0; $sumUsed = 0;
                foreach ($data as $row): 
                    $sumAlloc += $row['alloc_total'];
                    $sumUsed += $row['used_total'];
                    $remaining = $row['alloc_total'] - $row['used_total'];
                    $percent = ($row['alloc_total'] > 0) ? ($row['used_total'] / $row['alloc_total']) * 100 : 0;
                    
                    // Status Logic
                    $statusLabel = 'ปกติ'; $statusColor = 'bg-emerald-50 text-emerald-600';
                    if ($percent > 90) { $statusLabel = 'เกือบหมด'; $statusColor = 'bg-rose-50 text-rose-600'; }
                    elseif ($percent > 70) { $statusLabel = 'ระวัง'; $statusColor = 'bg-amber-50 text-amber-600'; }
                ?>
                <tr class="group hover:bg-slate-50/50 transition-all">
                    <td class="py-5 px-4">
                        <p class="font-black text-slate-700"><?= htmlspecialchars($row['department_name']) ?></p>
                    </td>
                    <td class="py-5 px-4 text-right font-bold text-slate-600"><?= number_format($row['alloc_total'], 2) ?></td>
                    <td class="py-5 px-4 text-right font-black text-blue-600"><?= number_format($row['used_total'], 2) ?></td>
                    <td class="py-5 px-4 text-right font-bold text-slate-400"><?= number_format($remaining, 2) ?></td>
                    <td class="py-5 px-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <span class="text-xs font-black"><?= number_format($percent, 1) ?>%</span>
                            <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="<?= str_replace('text-', 'bg-', explode(' ', $statusColor)[1]) ?> h-full" style="width: <?= $percent ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="py-5 px-4 text-center">
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $statusColor ?>">
                            <?= $statusLabel ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-slate-50 font-black text-slate-800">
                    <td class="py-6 px-4 rounded-l-[1.5rem]">รวมทั้งสิ้น</td>
                    <td class="py-6 px-4 text-right"><?= number_format($sumAlloc, 2) ?></td>
                    <td class="py-6 px-4 text-right text-blue-600"><?= number_format($sumUsed, 2) ?></td>
                    <td class="py-6 px-4 text-right"><?= number_format($sumAlloc - $sumUsed, 2) ?></td>
                    <td class="py-6 px-4 text-center"><?= $sumAlloc > 0 ? number_format(($sumUsed / $sumAlloc) * 100, 1) : 0 ?>%</td>
                    <td class="py-6 px-4 rounded-r-[1.5rem]"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
