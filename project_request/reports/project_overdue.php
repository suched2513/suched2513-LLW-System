<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
checkRole(['director', 'budget_officer', 'admin']);

$pageTitle = 'โครงการค้างดำเนินการ';
$pageSubtitle = 'รายการโครงการที่ยังไม่มีการขอใช้เงิน หรือค้างอยู่ในสถานะร่างเกิน 30 วัน';
require_once __DIR__ . '/../components/layout_start.php';

$pdo = getPdo();
$days = $_GET['days'] ?? 30;

// Logic: Projects with no requests OR (Draft AND > X days)
$stmt = $pdo->prepare("
    SELECT bp.*, d.name as dept_name, pr.status, pr.created_at as req_date, pr.id as request_id
    FROM budget_projects bp
    JOIN departments d ON bp.department_id = d.id
    LEFT JOIN project_requests pr ON pr.budget_project_id = bp.id
    WHERE bp.is_active = 1 
    AND (
        pr.id IS NULL 
        OR (pr.status = 'draft' AND DATEDIFF(NOW(), pr.created_at) > ?)
    )
    ORDER BY d.id, bp.project_name
");
$stmt->execute([$days]);
$overdue = $stmt->fetchAll();
?>

<div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h4 class="text-lg font-black text-slate-800">รายการโครงการที่ล่าช้า</h4>
            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Project Execution Delay (Threshold: <?= $days ?> Days)</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left border-b border-slate-50">
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ฝ่าย</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ชื่อโครงการ / กิจกรรม</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ผู้รับผิดชอบ</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">สถานะล่าสุด</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ค้างมาแล้ว</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">ดำเนินการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($overdue)): ?>
                <tr>
                    <td colspan="6" class="py-12 text-center text-slate-300 font-bold italic">ไม่พบโครงการที่ค้างดำเนินการ</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($overdue as $row): 
                    $diff = $row['req_date'] ? (new DateTime($row['req_date']))->diff(new DateTime())->days : '-';
                ?>
                <tr class="group hover:bg-slate-50/50 transition-all">
                    <td class="py-5 px-4">
                        <span class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-0.5 rounded uppercase"><?= htmlspecialchars($row['dept_name']) ?></span>
                    </td>
                    <td class="py-5 px-4">
                        <p class="font-black text-slate-700"><?= htmlspecialchars($row['project_name']) ?></p>
                        <p class="text-[10px] text-slate-400 font-bold uppercase"><?= htmlspecialchars($row['activity']) ?></p>
                    </td>
                    <td class="py-5 px-4 font-bold text-slate-500"><?= htmlspecialchars($row['owner_name']) ?></td>
                    <td class="py-5 px-4">
                        <?php if (!$row['status']): ?>
                            <span class="text-[10px] font-black text-rose-400 uppercase">ยังไม่มีการขอใช้</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider <?= STATUS_COLORS[$row['status']] ?>">
                                <?= STATUS_LABELS[$row['status']] ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="py-5 px-4">
                        <?php if ($diff !== '-'): ?>
                        <span class="text-sm font-black text-rose-500"><?= $diff ?> วัน</span>
                        <?php else: ?>
                            <span class="text-xs text-slate-300">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-5 px-4 text-right">
                        <button onclick="sendNudge('<?= $row['owner_name'] ?>', '<?= htmlspecialchars($row['project_name']) ?>')" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all">
                            ส่งแจ้งเตือน
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function sendNudge(teacher, project) {
    Swal.fire({
        title: 'ส่งการแจ้งเตือน?',
        text: `ระบบจะส่งข้อความแจ้งเตือนครู ${teacher} ให้รีบดำเนินการโครงการ ${project}`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันส่ง',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#2563eb'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('สำเร็จ!', 'ส่งการแจ้งเตือนเรียบร้อยแล้ว', 'success');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
