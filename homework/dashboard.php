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

// Distinct subjects for filter tabs
$subjects = $pdo->query("
    SELECT DISTINCT subject FROM hw_assignments
    WHERE status = 'published' AND subject != '' AND subject IS NOT NULL
    ORDER BY subject
")->fetchAll(PDO::FETCH_COLUMN);

// Assignments with submission stats
$assignments = $pdo->query("
    SELECT a.*,
           COUNT(s.id)                    AS sub_count,
           SUM(s.score IS NOT NULL)        AS reviewed_count
    FROM hw_assignments a
    LEFT JOIN hw_submissions s ON s.assignment_id = a.id
    WHERE a.status = 'published'
    GROUP BY a.id
    ORDER BY a.created_at DESC
    LIMIT 50
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
    <!-- Pending card links to queue -->
    <a href="<?= $base_path ?>/homework/queue.php" class="block bg-gradient-to-br from-amber-500 to-orange-500 text-white rounded-2xl p-5 shadow-lg shadow-amber-200/50 hover:scale-[1.02] transition-transform">
        <p class="text-xs font-black uppercase tracking-wider opacity-80">รอตรวจ</p>
        <p class="text-4xl font-black mt-1"><?= $pendingReview ?></p>
        <?php if ($pendingReview > 0): ?>
        <p class="text-xs opacity-80 mt-1 font-bold">คลิกเพื่อตรวจ →</p>
        <?php endif; ?>
    </a>
    <div class="bg-gradient-to-br from-slate-600 to-slate-800 text-white rounded-2xl p-5 shadow-lg shadow-slate-200/50">
        <p class="text-xs font-black uppercase tracking-wider opacity-80">ตรวจแล้ว</p>
        <p class="text-4xl font-black mt-1"><?= $reviewedCount ?></p>
    </div>
</div>

<!-- Header actions -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h2 class="text-lg font-black text-slate-800">รายการงานทั้งหมด</h2>
    <div class="flex gap-2">
        <a href="<?= $base_path ?>/homework/queue.php"
           class="<?= $pendingReview > 0 ? 'bg-amber-500 text-white shadow-lg shadow-amber-200 hover:bg-amber-600' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?> px-4 py-2.5 rounded-2xl font-bold text-sm transition-all flex items-center gap-2">
            <i class="bi bi-hourglass-split"></i> ตรวจคิวงาน
            <?php if ($pendingReview > 0): ?>
            <span class="bg-white/30 text-white text-xs font-black px-1.5 py-0.5 rounded-full"><?= $pendingReview ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $base_path ?>/homework/create.php"
           class="bg-indigo-600 text-white px-5 py-2.5 rounded-2xl font-bold text-sm shadow-lg shadow-indigo-200 hover:bg-indigo-700 hover:scale-[1.02] transition-all flex items-center gap-2">
            <i class="bi bi-plus-lg"></i> สั่งงานใหม่
        </a>
    </div>
</div>

<!-- Subject filter tabs -->
<?php if (!empty($subjects)): ?>
<div class="flex gap-2 overflow-x-auto pb-2 mb-4 scrollbar-hide">
    <button onclick="filterBySubject('')" id="tab-all"
            class="subject-tab active flex-shrink-0 px-4 py-1.5 rounded-xl text-xs font-bold transition-all">
        ทั้งหมด
    </button>
    <?php foreach ($subjects as $i => $sub): ?>
    <button onclick="filterBySubject(<?= json_encode($sub) ?>)" id="tab-<?= $i ?>"
            class="subject-tab flex-shrink-0 px-4 py-1.5 rounded-xl text-xs font-bold transition-all">
        <?= htmlspecialchars($sub, ENT_QUOTES, 'UTF-8') ?>
    </button>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Assignment table -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm" id="assignmentTable">
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
            <tbody class="divide-y divide-slate-50" id="assignmentTbody">
                <?php if (empty($assignments)): ?>
                <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">
                    <i class="bi bi-inbox text-4xl block mb-2 opacity-30"></i>
                    ยังไม่มีงาน — <a href="<?= $base_path ?>/homework/create.php" class="text-indigo-500 font-bold">สั่งงานแรก</a>
                </td></tr>
                <?php else: ?>
                <?php foreach ($assignments as $a):
                    $isOverdue = strtotime($a['due_date']) < time();
                    $pending   = (int)$a['sub_count'] - (int)$a['reviewed_count'];
                    $subject   = $a['subject'] ?? '';
                ?>
                <tr class="assignment-row hover:bg-slate-50/80 transition-colors"
                    data-subject="<?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>">
                    <td class="px-5 py-4">
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($subject): ?>
                        <p class="text-xs text-violet-500 font-bold mt-0.5"><?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></p>
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
                        <a href="<?= $base_path ?>/homework/queue.php" class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-black rounded-full hover:bg-amber-200 transition-colors"><?= $pending ?></a>
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

<style>
.subject-tab { background: #f1f5f9; color: #64748b; }
.subject-tab.active { background: #4f46e5; color: #fff; }
.subject-tab:hover:not(.active) { background: #e2e8f0; }
</style>

<script>
let currentSubject = '';

function filterBySubject(name) {
    currentSubject = name;

    // Update tab styles
    document.querySelectorAll('.subject-tab').forEach(t => t.classList.remove('active'));
    if (!name) {
        document.getElementById('tab-all').classList.add('active');
    } else {
        document.querySelectorAll('.subject-tab').forEach(t => {
            if (t.textContent.trim() === name) t.classList.add('active');
        });
    }

    // Filter rows
    document.querySelectorAll('.assignment-row').forEach(row => {
        const rowSubject = row.dataset.subject || '';
        row.style.display = (!name || rowSubject === name) ? '' : 'none';
    });

    // Show empty state if all hidden
    const visible = [...document.querySelectorAll('.assignment-row')].filter(r => r.style.display !== 'none');
    const emptyRow = document.getElementById('empty-filter-row');
    if (visible.length === 0 && name) {
        if (!emptyRow) {
            const tbody = document.getElementById('assignmentTbody');
            tbody.insertAdjacentHTML('beforeend',
                `<tr id="empty-filter-row"><td colspan="6" class="px-5 py-8 text-center text-slate-400 text-sm">ไม่มีงานในวิชา "${name}"</td></tr>`);
        }
    } else {
        emptyRow?.remove();
    }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
