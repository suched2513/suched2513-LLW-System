<?php
/**
 * pp5/activity_logs.php — หน้าตรวจสอบประวัติการแก้ไขข้อมูล คะแนน เกรด และการปลดล็อก ปพ.5 (Audit Trail)
 */

require_once __DIR__ . '/_bootstrap.php';
pp5_require_login();

use PP5\App\Policies\Pp5Policy;

$policy = new Pp5Policy($pdo);
$policy->authorizeAdmin();

$termCtx      = pp5_get_term_context($pdo);
$filterAction = trim((string)($_GET['action_type'] ?? ''));

$where  = ['1=1'];
$params = [];
if ($filterAction !== '') {
    $where[]  = '`action` LIKE ?';
    $params[] = $filterAction . '%';
}

$stmt = $pdo->prepare("
    SELECT * FROM `pp5_activity_logs`
    WHERE " . implode(' AND ', $where) . "
    ORDER BY `created_at` DESC, `id` DESC
    LIMIT 200
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle    = 'ประวัติการแก้ไขและใช้งานระบบ ปพ.5';
$pageSubtitle = 'บันทึก Audit Trail ทุกครั้งที่มีการแก้ไขข้อมูล คะแนน เกรด การปลดล็อก ปพ.5 และการนำเข้า Excel';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
?>

<?= pp5_render_top_bar($pdo, $termCtx, $policy, 'logs') ?>

<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 overflow-hidden border border-slate-100">
    <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="bi bi-shield-check text-indigo-600"></i> ประวัติการดำเนินการในระบบ (แสดง 200 รายการล่าสุด)
            </h3>
            <p class="text-xs text-slate-400">บันทึกผู้กระทำ วันเวลา ค่าเดิม-ค่าใหม่ และ IP Address ตามมาตรฐานความปลอดภัย</p>
        </div>

        <form method="GET" class="flex items-center gap-2">
            <select name="action_type" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700">
                <option value="">— ทุกประเภทกิจกรรม —</option>
                <option value="settings." <?= $filterAction === 'settings.' ? 'selected' : '' ?>>การตั้งค่าโรงเรียน</option>
                <option value="grade_criteria." <?= $filterAction === 'grade_criteria.' ? 'selected' : '' ?>>เกณฑ์การตัดเกรด</option>
                <option value="student." <?= $filterAction === 'student.' ? 'selected' : '' ?>>ข้อมูลนักเรียน</option>
                <option value="subject." <?= $filterAction === 'subject.' ? 'selected' : '' ?>>ข้อมูลรายวิชา</option>
                <option value="course." <?= $filterAction === 'course.' ? 'selected' : '' ?>>การจัดครูสอน / ปลดล็อก ปพ.5</option>
                <option value="excel." <?= $filterAction === 'excel.' ? 'selected' : '' ?>>การนำเข้า Excel</option>
                <option value="score." <?= $filterAction === 'score.' ? 'selected' : '' ?>>การแก้ไขคะแนน</option>
                <option value="grade." <?= $filterAction === 'grade.' ? 'selected' : '' ?>>การตัดเกรด / แก้ 0 ร มส</option>
            </select>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs sm:text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-xs font-bold text-slate-400 uppercase px-5 py-3.5">วัน-เวลา</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3.5">ผู้ดำเนินการ</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3.5">ประเภท</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3.5">รายละเอียด</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3.5">ค่าเดิม → ค่าใหม่</th>
                    <th class="text-xs font-bold text-slate-400 uppercase px-4 py-3.5">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-slate-400 font-bold">ยังไม่มีประวัติการแก้ไขข้อมูล</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $lg): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-5 py-3 whitespace-nowrap font-mono text-xs text-slate-500"><?= htmlspecialchars($lg['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($lg['user_name'] ?: 'System', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 font-mono font-bold text-xs">
                                    <?= htmlspecialchars($lg['action'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-700"><?= htmlspecialchars($lg['description'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 max-w-xs">
                                <?php if (!empty($lg['old_values']) || !empty($lg['new_values'])): ?>
                                    <details class="text-xs cursor-pointer text-blue-600 font-bold">
                                        <summary>ดูข้อมูล JSON</summary>
                                        <div class="mt-1 p-2 rounded-lg bg-slate-900 text-slate-200 font-mono text-[10px] overflow-x-auto">
                                            <?php if (!empty($lg['old_values'])): ?>
                                                <div class="text-rose-300">เดิม: <?= htmlspecialchars($lg['old_values'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($lg['new_values'])): ?>
                                                <div class="text-emerald-300">ใหม่: <?= htmlspecialchars($lg['new_values'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-400"><?= htmlspecialchars($lg['ip_address'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
