<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
checkRole(['director', 'budget_officer', 'admin']);

$pageTitle = 'ความคืบหน้าโครงการ';
$pageSubtitle = 'ตารางสรุปจำนวนโครงการแยกตามสถานะรายฝ่าย';
require_once __DIR__ . '/../components/layout_start.php';

$pdo = getPdo();
$summary = $pdo->query("SELECT * FROM v_project_status_summary")->fetchAll();
?>

<div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left border-b border-slate-50">
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ฝ่าย / กลุ่มงาน</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">ทั้งหมด</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">ยังไม่ดำเนินการ</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">ฉบับร่าง</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">รออนุมัติ</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">อนุมัติแล้ว</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">ปฏิเสธ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($summary as $row): ?>
                <tr class="group hover:bg-slate-50/50 transition-all">
                    <td class="py-5 px-4 font-black text-slate-700"><?= htmlspecialchars($row['department_name']) ?></td>
                    <td class="py-5 px-4 text-center font-black text-slate-800"><?= $row['total_projects'] ?></td>
                    <td class="py-5 px-4 text-center font-bold text-slate-300"><?= $row['no_request'] ?></td>
                    <td class="py-5 px-4 text-center font-bold text-slate-400"><?= $row['draft'] ?></td>
                    <td class="py-5 px-4 text-center">
                        <span class="bg-amber-50 text-amber-600 px-2 py-1 rounded-lg font-black text-xs"><?= $row['submitted'] ?></span>
                    </td>
                    <td class="py-5 px-4 text-center">
                        <span class="bg-emerald-50 text-emerald-600 px-2 py-1 rounded-lg font-black text-xs"><?= $row['approved'] ?></span>
                    </td>
                    <td class="py-5 px-4 text-center font-bold text-rose-300"><?= $row['rejected'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
