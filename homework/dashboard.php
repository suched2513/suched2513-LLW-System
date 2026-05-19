<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();

// KPI
$totalAssignments = (int)$pdo->query("SELECT COUNT(*) FROM hw_assignments WHERE status='published'")->fetchColumn();
$totalSubmissions = (int)$pdo->query("SELECT COUNT(*) FROM hw_submissions")->fetchColumn();
$reviewedCount    = (int)$pdo->query("SELECT COUNT(*) FROM hw_submissions WHERE score IS NOT NULL")->fetchColumn();
$pendingReview    = $totalSubmissions - $reviewedCount;

// Recent assignments with submission stats
$assignments = $pdo->query("
    SELECT a.*,
           COUNT(s.id) AS sub_count,
           SUM(s.score IS NOT NULL) AS reviewed_count
    FROM hw_assignments a
    LEFT JOIN hw_submissions s ON s.assignment_id = a.id
    WHERE a.status = 'published'
    GROUP BY a.id
    ORDER BY a.created_at DESC
    LIMIT 10
")->fetchAll();

$pageTitle    = 'ระบบการบ้าน';
$pageSubtitle = 'สั่ง ส่ง และตรวจงานนักเรียน';
$activeSystem = 'homework';
require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- KPI Strip -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-br from-indigo-500 to-indigo-700 text-white rounded-2xl p-5 shadow-lg shadow-indigo-200/50">
        <p class="text-xs font-black uppercase tracking-wider opacity-80">งานทั้งหมด</p>
        <p class="text-4xl font-black mt-1"><?= $totalAssignments ?></p>
    </div>
    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white rounded-2xl p-5 shadow-lg shadow-emerald-200/50">
        <p class="text-xs font-black uppercase tracking-wider opacity-80">ส่งแล้ว</p>
        <p class="text-4xl font-black mt-1"><?= $totalSubmissions ?></p>
    </div>
    <div class="bg-gradient-to-br from-amber-500 to-orange-500 text-white rounded-2xl p-5 shadow-lg shadow-amber-200/50">
        <p class="text-xs font-black uppercase tracking-wider opacity-80">รอตรวจ</p>
        <p class="text-4xl font-black mt-1"><?= $pendingReview ?></p>
    </div>
    <div class="bg-gradient-to-br from-slate-600 to-slate-800 text-white rounded-2xl p-5 shadow-lg shadow-slate-200/50">
        <p class="text-xs font-black uppercase tracking-wider opacity-80">ตรวจแล้ว</p>
        <p class="text-4xl font-black mt-1"><?= $reviewedCount ?></p>
    </div>
</div>

<!-- Header actions -->
<div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-black text-slate-800">รายการงานล่าสุด</h2>
    <a href="<?= $base_path ?>/homework/create.php"
       class="bg-indigo-600 text-white px-5 py-2.5 rounded-2xl font-bold text-sm shadow-lg shadow-indigo-200 hover:bg-indigo-700 hover:scale-[1.02] transition-all flex items-center gap-2">
        <i class="bi bi-plus-lg"></i> สั่งงานใหม่
    </a>
</div>

<!-- Assignment table -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="px-5 py-4 text-xs font-black text-slate-400 uppercase tracking-wider text-left">ชื่องาน</th>
                    <th class="px-4 py-4 text-xs font-black text-slate-400 uppercase tracking-wider text-left">ห้อง</th>
                    <th class="px-4 py-4 text-xs font-black text-slate-400 uppercase tracking-wider text-center">กำหนดส่ง</th>
                    <th class="px-4 py-4 text-xs font-black text-slate-400 uppercase tracking-wider text-center">ส่งแล้ว</th>
                    <th class="px-4 py-4 text-xs font-black text-slate-400 uppercase tracking-wider text-center">รอตรวจ</th>
                    <th class="px-4 py-4 text-xs font-black text-slate-400 uppercase tracking-wider text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($assignments)): ?>
                <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">
                    <i class="bi bi-inbox text-4xl block mb-2 opacity-30"></i>
                    ยังไม่มีงาน — <a href="<?= $base_path ?>/homework/create.php" class="text-indigo-500 font-bold">สั่งงานแรก</a>
                </td></tr>
                <?php else: ?>
                <?php foreach ($assignments as $a):
                    $isOverdue  = strtotime($a['due_date']) < time();
                    $pending    = (int)$a['sub_count'] - (int)$a['reviewed_count'];
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="px-5 py-4">
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($a['subject']): ?>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($a['subject'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4">
                        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-xs font-bold rounded-full">
                            <?= htmlspecialchars($a['classroom'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <span class="text-xs font-bold <?= $isOverdue ? 'text-rose-500' : 'text-slate-600' ?>">
                            <?= date('d/m/Y H:i', strtotime($a['due_date'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <span class="text-sm font-black text-emerald-600"><?= (int)$a['sub_count'] ?></span>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <?php if ($pending > 0): ?>
                        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-black rounded-full"><?= $pending ?></span>
                        <?php else: ?>
                        <span class="text-slate-300 text-xs font-bold">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <a href="<?= $base_path ?>/homework/review.php?id=<?= $a['id'] ?>"
                           class="text-indigo-500 hover:text-indigo-700 font-bold text-xs transition-colors">
                            <i class="bi bi-clipboard2-check"></i> ตรวจงาน
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
