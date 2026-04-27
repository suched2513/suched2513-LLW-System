<?php
session_start();
$pageTitle = 'ประวัติคำขอ';
$pageSubtitle = 'รายการคำขอดำเนินโครงการทั้งหมดของคุณ';
require_once __DIR__ . '/../components/layout_start.php';

$user = currentUser();
$pdo = getPdo();

$stmt = $pdo->prepare("
    SELECT r.*, p.project_name, p.activity 
    FROM project_requests r
    JOIN budget_projects p ON r.budget_project_id = p.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$user['id']]);
$requests = $stmt->fetchAll();
?>

<?php if (isset($_GET['success'])): ?>
<script>
    showToast('success', 'บันทึกคำขอสำเร็จ');
</script>
<?php endif; ?>

<div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left border-b border-slate-50">
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">วันที่</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">โครงการ / กิจกรรม</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">เหตุผล</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">ยอดเงิน</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-center">สถานะ</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="6" class="py-12 text-center text-slate-400 font-medium italic">ไม่พบประวัติคำขอ</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($requests as $r): ?>
                <tr class="group hover:bg-slate-50/50 transition-all">
                    <td class="py-4 px-4 text-sm font-bold text-slate-500">
                        <?= date('d/m/Y', strtotime($r['request_date'])) ?>
                    </td>
                    <td class="py-4 px-4">
                        <p class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($r['project_name']) ?></p>
                        <p class="text-[10px] text-slate-400 font-bold uppercase"><?= htmlspecialchars($r['activity']) ?></p>
                    </td>
                    <td class="py-4 px-4">
                        <p class="text-xs text-slate-500 truncate max-w-[200px]"><?= htmlspecialchars($r['reason']) ?></p>
                    </td>
                    <td class="py-4 px-4 text-right">
                        <p class="font-black text-slate-800"><?= number_format($r['amount_requested'], 2) ?></p>
                    </td>
                    <td class="py-4 px-4 text-center">
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= STATUS_COLORS[$r['status']] ?>">
                            <?= STATUS_LABELS[$r['status']] ?>
                        </span>
                    </td>
                    <td class="py-4 px-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="request_view.php?id=<?= $r['id'] ?>" class="w-8 h-8 flex items-center justify-center bg-slate-50 text-slate-400 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                            <?php if ($r['status'] === 'approved' || $r['status'] === 'submitted'): ?>
                            <div class="relative group/doc">
                                <button class="w-8 h-8 flex items-center justify-center bg-slate-50 text-slate-400 rounded-lg hover:bg-emerald-50 hover:text-emerald-600 transition-all">
                                    <i class="bi bi-file-earmark-word-fill"></i>
                                </button>
                                <div class="absolute right-0 bottom-full mb-2 w-48 bg-white rounded-xl shadow-xl border border-slate-100 p-2 hidden group-hover/doc:block z-10 animate-fade-in">
                                    <a href="../documents/gen_memo.php?id=<?= $r['id'] ?>" class="block w-full text-left px-3 py-2 text-[10px] font-black text-slate-600 hover:bg-slate-50 rounded-lg uppercase tracking-wider">1. บันทึกขออนุมัติ</a>
                                    <a href="../documents/gen_committee.php?id=<?= $r['id'] ?>" class="block w-full text-left px-3 py-2 text-[10px] font-black text-slate-600 hover:bg-slate-50 rounded-lg uppercase tracking-wider">2. แต่งตั้งคณะกรรมการ</a>
                                    <a href="../documents/gen_order.php?id=<?= $r['id'] ?>" class="block w-full text-left px-3 py-2 text-[10px] font-black text-slate-600 hover:bg-slate-50 rounded-lg uppercase tracking-wider">3. คำสั่งแต่งตั้ง + TOR</a>
                                    <a href="../documents/gen_delivery.php?id=<?= $r['id'] ?>" class="block w-full text-left px-3 py-2 text-[10px] font-black text-slate-600 hover:bg-slate-50 rounded-lg uppercase tracking-wider">4. ใบส่งมอบงาน</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
