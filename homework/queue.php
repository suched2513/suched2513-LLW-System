<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();

$filter = $_GET['filter'] ?? 'pending'; // pending | all

$where = $filter === 'all' ? '' : 'WHERE s.score IS NULL';

$pending = $pdo->query("
    SELECT s.*,
           a.title     AS assignment_title,
           a.max_score AS max_score,
           a.subject   AS subject,
           a.due_date  AS due_date
    FROM hw_submissions s
    JOIN hw_assignments a ON a.id = s.assignment_id
    $where
    ORDER BY s.submitted_at ASC
")->fetchAll();

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM hw_submissions WHERE score IS NULL")->fetchColumn();

$pageTitle    = 'คิวตรวจงาน';
$pageSubtitle = 'งานที่ส่งและรอการตรวจ';
$activeSystem = 'homework';
require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Top bar -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-amber-200/50">
            <i class="bi bi-hourglass-split text-white"></i>
        </div>
        <div>
            <h2 class="text-lg font-black text-slate-800">คิวตรวจงาน</h2>
            <p class="text-xs text-slate-400">
                <?php if ($filter === 'pending'): ?>
                    รอตรวจ <span class="font-black text-amber-600"><?= $pendingCount ?></span> รายการ
                <?php else: ?>
                    แสดงทั้งหมด <span class="font-black text-slate-600"><?= count($pending) ?></span> รายการ
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="?filter=pending" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $filter === 'pending' ? 'bg-amber-500 text-white shadow-lg shadow-amber-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">
            <i class="bi bi-hourglass-split mr-1"></i> รอตรวจ (<?= $pendingCount ?>)
        </a>
        <a href="?filter=all" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?= $filter === 'all' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">
            <i class="bi bi-list-ul mr-1"></i> ทั้งหมด
        </a>
        <a href="<?= $base_path ?>/homework/dashboard.php" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 text-slate-500 hover:bg-slate-200 transition-all">
            <i class="bi bi-arrow-left mr-1"></i> กลับ
        </a>
    </div>
</div>

<?php if (empty($pending)): ?>
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-16 text-center">
    <i class="bi bi-patch-check text-5xl text-emerald-400 block mb-3 opacity-60"></i>
    <p class="font-black text-slate-600 text-lg">ตรวจงานครบแล้ว!</p>
    <p class="text-slate-400 text-sm mt-1">ไม่มีงานที่รอตรวจในขณะนี้</p>
</div>
<?php else: ?>

<div class="space-y-4" id="submissionList">
<?php foreach ($pending as $s):
    $isLate     = $s['status'] === 'late';
    $isReviewed = $s['score'] !== null;
    $files      = [];
    if (!empty($s['file_path'])) {
        $decoded = json_decode($s['file_path'], true);
        if (is_array($decoded)) $files = $decoded;
    }
?>
<div class="bg-white rounded-2xl shadow-lg shadow-slate-100/50 border border-slate-100 overflow-hidden submission-card"
     id="card-<?= $s['id'] ?>"
     data-reviewed="<?= $isReviewed ? '1' : '0' ?>">

    <!-- Card header -->
    <div class="px-6 py-4 border-b border-slate-50 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 flex-wrap">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0
                <?= $isReviewed ? 'bg-emerald-100' : ($isLate ? 'bg-rose-100' : 'bg-amber-100') ?>">
                <i class="bi <?= $isReviewed ? 'bi-check-circle-fill text-emerald-500' : ($isLate ? 'bi-alarm-fill text-rose-500' : 'bi-hourglass-split text-amber-500') ?>"></i>
            </div>
            <div>
                <p class="font-black text-slate-800 text-sm"><?= htmlspecialchars($s['student_name'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-slate-400"><?= htmlspecialchars($s['student_code'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <span class="px-2.5 py-0.5 bg-indigo-50 text-indigo-600 text-xs font-bold rounded-full">
                <?= htmlspecialchars($s['classroom'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php if ($isLate): ?>
            <span class="px-2.5 py-0.5 bg-rose-50 text-rose-500 text-xs font-bold rounded-full">ส่งช้า</span>
            <?php endif; ?>
            <?php if ($isReviewed): ?>
            <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-600 text-xs font-bold rounded-full">ตรวจแล้ว <?= $s['score'] ?>/<?= $s['max_score'] ?></span>
            <?php endif; ?>
        </div>
        <div class="text-right flex-shrink-0">
            <p class="text-xs font-black text-slate-700"><?= htmlspecialchars($s['assignment_title'], ENT_QUOTES, 'UTF-8') ?></p>
            <div class="flex gap-2 justify-end mt-1">
                <?php if ($s['subject']): ?>
                <span class="px-2 py-0.5 bg-violet-50 text-violet-600 text-xs font-bold rounded-full"><?= htmlspecialchars($s['subject'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span class="text-xs text-slate-400"><?= date('d/m H:i', strtotime($s['submitted_at'])) ?></span>
            </div>
        </div>
    </div>

    <!-- Content + Score panel -->
    <div class="px-6 py-4 flex flex-wrap gap-6">

        <!-- Submission content -->
        <div class="flex-1 min-w-0">
            <?php if ($s['content']): ?>
            <div class="bg-slate-50 rounded-xl p-4 text-sm text-slate-700 leading-relaxed whitespace-pre-wrap max-h-40 overflow-auto mb-3">
                <?= htmlspecialchars($s['content'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($files)): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($files as $fp):
                    $ext = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
                    $icon = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'bi-image' : (($ext === 'pdf') ? 'bi-file-earmark-pdf' : 'bi-file-earmark-word');
                    $iconColor = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'text-emerald-500' : (($ext === 'pdf') ? 'text-rose-500' : 'text-blue-500');
                ?>
                <a href="<?= $base_path ?>/<?= htmlspecialchars($fp, ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                   class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:border-indigo-300 hover:text-indigo-600 transition-all shadow-sm">
                    <i class="bi <?= $icon ?> <?= $iconColor ?>"></i>
                    <?= htmlspecialchars(basename($fp), ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!$s['content'] && empty($files)): ?>
            <p class="text-xs text-slate-400 italic">ไม่มีเนื้อหา</p>
            <?php endif; ?>
        </div>

        <!-- Score panel -->
        <div class="w-52 flex-shrink-0">
            <label class="block text-xs font-black text-slate-400 uppercase tracking-wider mb-1.5">คะแนน (เต็ม <?= $s['max_score'] ?>)</label>
            <div class="flex items-center gap-2 mb-2">
                <input type="number" min="0" max="<?= $s['max_score'] ?>"
                       class="score-input w-16 bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-sm font-bold text-center focus:ring-2 focus:ring-indigo-400 outline-none"
                       value="<?= $isReviewed ? htmlspecialchars($s['score'], ENT_QUOTES, 'UTF-8') : '' ?>"
                       placeholder="—"
                       data-sub-id="<?= $s['id'] ?>">
                <span class="text-xs text-slate-400 font-bold">/ <?= $s['max_score'] ?></span>
            </div>
            <textarea class="comment-input w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-400 outline-none resize-none"
                      rows="2" placeholder="ความเห็น (ไม่บังคับ)"
                      data-sub-id="<?= $s['id'] ?>"><?= htmlspecialchars($s['teacher_comment'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            <button onclick="saveReview(<?= $s['id'] ?>, <?= $s['max_score'] ?>, this)"
                    class="save-btn mt-1.5 w-full bg-indigo-600 text-white py-1.5 rounded-xl text-xs font-bold hover:bg-indigo-700 transition-all">
                <i class="bi bi-check2"></i> บันทึกคะแนน
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<script>
async function saveReview(subId, maxScore, btn) {
    const scoreInput   = document.querySelector(`.score-input[data-sub-id="${subId}"]`);
    const commentInput = document.querySelector(`.comment-input[data-sub-id="${subId}"]`);
    const score = scoreInput.value;

    if (score === '') {
        Swal.fire({ icon: 'warning', title: 'กรุณากรอกคะแนน', confirmButtonColor: '#4f46e5' });
        return;
    }
    const s = parseInt(score);
    if (isNaN(s) || s < 0 || s > maxScore) {
        Swal.fire({ icon: 'warning', title: `คะแนนต้องอยู่ระหว่าง 0 - ${maxScore}`, confirmButtonColor: '#4f46e5' });
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> กำลังบันทึก...';

    const res = await fetch('<?= $base_path ?>/homework/api/save_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sub_id: subId, score: s, comment: commentInput.value.trim() })
    }).then(r => r.json());

    btn.disabled = false;

    if (res.status === 'success') {
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> บันทึกแล้ว';
        btn.classList.replace('bg-indigo-600', 'bg-emerald-500');
        btn.classList.replace('hover:bg-indigo-700', 'hover:bg-emerald-600');

        const card = document.getElementById('card-' + subId);
        card.dataset.reviewed = '1';

        // If in pending filter: fade card out after 1.5s
        <?php if ($filter === 'pending'): ?>
        setTimeout(() => {
            card.style.transition = 'opacity 0.5s, max-height 0.5s';
            card.style.opacity = '0';
            card.style.maxHeight = '0';
            card.style.overflow = 'hidden';
            setTimeout(() => card.remove(), 500);
        }, 1500);
        <?php endif; ?>
    } else {
        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message, confirmButtonColor: '#4f46e5' });
        btn.innerHTML = '<i class="bi bi-check2"></i> บันทึกคะแนน';
    }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
