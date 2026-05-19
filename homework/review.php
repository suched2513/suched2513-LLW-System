<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . $base_path . '/homework/dashboard.php'); exit(); }

$assignment = $pdo->prepare("SELECT * FROM hw_assignments WHERE id = ? LIMIT 1");
$assignment->execute([$id]);
$assignment = $assignment->fetch();
if (!$assignment) { header('Location: ' . $base_path . '/homework/dashboard.php'); exit(); }

// Students in target classrooms
$classrooms = array_map('trim', explode(',', $assignment['classroom']));
$placeholders = implode(',', array_fill(0, count($classrooms), '?'));

$students = $pdo->prepare("
    SELECT id, student_id, name, classroom
    FROM att_students
    WHERE classroom IN ($placeholders) AND status='active'
      AND student_id REGEXP '^[0-9]+$'
      AND student_id NOT IN (SELECT subject_code FROM att_subjects)
    ORDER BY classroom, student_id
");
$students->execute($classrooms);
$students = $students->fetchAll();

// Submissions map: student_uid → submission
$subs = $pdo->prepare("SELECT * FROM hw_submissions WHERE assignment_id = ?");
$subs->execute([$id]);
$subsMap = [];
foreach ($subs->fetchAll() as $s) {
    $subsMap[$s['student_uid']] = $s;
}

$totalStudents = count($students);
$submitted     = count($subsMap);
$reviewed      = count(array_filter($subsMap, fn($s) => $s['score'] !== null));

$pageTitle    = 'ตรวจงาน';
$pageSubtitle = htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8');
$activeSystem = 'homework';
require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Assignment header -->
<div class="bg-white rounded-2xl shadow-xl shadow-indigo-100/50 border border-indigo-100/30 p-6 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-black text-slate-800"><?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="flex flex-wrap gap-3 mt-2">
                <?php if ($assignment['subject']): ?>
                <span class="px-3 py-1 bg-indigo-50 text-indigo-600 text-xs font-bold rounded-full"><?= htmlspecialchars($assignment['subject'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-bold rounded-full"><?= htmlspecialchars($assignment['classroom'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="px-3 py-1 bg-amber-50 text-amber-700 text-xs font-bold rounded-full">
                    ส่งภายใน <?= date('d/m/Y H:i', strtotime($assignment['due_date'])) ?>
                </span>
                <span class="px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full">
                    คะแนนเต็ม <?= $assignment['max_score'] ?>
                </span>
            </div>
        </div>
        <div class="flex gap-4 text-center">
            <div><p class="text-2xl font-black text-indigo-600"><?= $submitted ?>/<?= $totalStudents ?></p><p class="text-xs text-slate-400 font-bold">ส่งแล้ว</p></div>
            <div><p class="text-2xl font-black text-emerald-600"><?= $reviewed ?></p><p class="text-xs text-slate-400 font-bold">ตรวจแล้ว</p></div>
            <div><p class="text-2xl font-black text-amber-600"><?= $submitted - $reviewed ?></p><p class="text-xs text-slate-400 font-bold">รอตรวจ</p></div>
        </div>
    </div>
    <?php if ($assignment['description']): ?>
    <div class="mt-4 p-4 bg-slate-50 rounded-xl text-sm text-slate-600 leading-relaxed whitespace-pre-wrap">
        <?= htmlspecialchars($assignment['description'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>
</div>

<!-- Student list -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center gap-3">
        <span class="text-sm font-black text-slate-700">รายชื่อนักเรียนทั้งหมด</span>
        <div class="flex gap-2 ml-auto">
            <button onclick="filterStudents('all')" id="btn-all" class="filter-btn active px-3 py-1 rounded-xl text-xs font-bold transition-all">ทั้งหมด</button>
            <button onclick="filterStudents('submitted')" id="btn-submitted" class="filter-btn px-3 py-1 rounded-xl text-xs font-bold transition-all">ส่งแล้ว</button>
            <button onclick="filterStudents('pending')" id="btn-pending" class="filter-btn px-3 py-1 rounded-xl text-xs font-bold transition-all">ยังไม่ส่ง</button>
        </div>
    </div>
    <div class="divide-y divide-slate-50" id="studentList">
        <?php foreach ($students as $st):
            $sub = $subsMap[$st['id']] ?? null;
            $hasSubmit = $sub !== null;
            $isReviewed = $hasSubmit && $sub['score'] !== null;
        ?>
        <div class="student-row p-5 hover:bg-slate-50/50 transition-colors" data-state="<?= $hasSubmit ? 'submitted' : 'pending' ?>">
            <div class="flex items-start gap-4">
                <!-- Status icon -->
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5
                    <?= $isReviewed ? 'bg-emerald-100' : ($hasSubmit ? 'bg-amber-100' : 'bg-slate-100') ?>">
                    <i class="bi <?= $isReviewed ? 'bi-check-circle-fill text-emerald-500' : ($hasSubmit ? 'bi-hourglass-split text-amber-500' : 'bi-x-circle text-slate-300') ?>"></i>
                </div>

                <!-- Student info -->
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($st['name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="text-xs text-slate-400"><?= htmlspecialchars($st['student_id'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-xs font-bold rounded-full"><?= htmlspecialchars($st['classroom'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($hasSubmit): ?>
                        <span class="px-2 py-0.5 <?= $sub['status']==='late' ? 'bg-rose-50 text-rose-500' : 'bg-emerald-50 text-emerald-600' ?> text-xs font-bold rounded-full">
                            <?= $sub['status']==='late' ? 'สายเกินกำหนด' : 'ส่งตรงเวลา' ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasSubmit && $sub['content']): ?>
                    <div class="bg-slate-50 rounded-xl p-3 text-sm text-slate-600 leading-relaxed whitespace-pre-wrap mt-2 max-h-32 overflow-auto">
                        <?= htmlspecialchars($sub['content'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php elseif (!$hasSubmit): ?>
                    <p class="text-xs text-slate-400 mt-1">ยังไม่ได้ส่งงาน</p>
                    <?php endif; ?>

                    <?php
                    $attachedFiles = [];
                    if ($hasSubmit && !empty($sub['file_path'])) {
                        $decoded = json_decode($sub['file_path'], true);
                        if (is_array($decoded)) $attachedFiles = $decoded;
                    }
                    ?>
                    <?php if (!empty($attachedFiles)): ?>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <?php foreach ($attachedFiles as $fp):
                            $ext = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
                            $icon = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'bi-image' : (($ext === 'pdf') ? 'bi-file-earmark-pdf' : 'bi-file-earmark-word');
                            $iconColor = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'text-emerald-500' : (($ext === 'pdf') ? 'text-rose-500' : 'text-blue-500');
                        ?>
                        <a href="<?= $base_path ?>/<?= htmlspecialchars($fp, ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                           class="flex items-center gap-1.5 px-2.5 py-1 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:border-indigo-300 hover:text-indigo-600 transition-all shadow-sm">
                            <i class="bi <?= $icon ?> <?= $iconColor ?>"></i>
                            <?= htmlspecialchars(basename($fp), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Score panel -->
                <?php if ($hasSubmit): ?>
                <div class="flex-shrink-0 w-52">
                    <div class="flex items-center gap-2 mb-2">
                        <input type="number" min="0" max="<?= $assignment['max_score'] ?>"
                               class="score-input w-16 bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-sm font-bold text-center focus:ring-2 focus:ring-indigo-400 outline-none"
                               value="<?= $isReviewed ? $sub['score'] : '' ?>"
                               placeholder="—"
                               data-sub-id="<?= $sub['id'] ?>">
                        <span class="text-xs text-slate-400 font-bold">/ <?= $assignment['max_score'] ?></span>
                    </div>
                    <textarea class="comment-input w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-400 outline-none resize-none transition-all"
                              rows="2" placeholder="ความเห็น (ไม่บังคับ)"
                              data-sub-id="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['teacher_comment'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <button onclick="saveReview(<?= $sub['id'] ?>, this)"
                            class="mt-1.5 w-full bg-indigo-600 text-white py-1.5 rounded-xl text-xs font-bold hover:bg-indigo-700 transition-all">
                        <i class="bi bi-check2"></i> บันทึก
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.filter-btn { background: #f1f5f9; color: #64748b; }
.filter-btn.active { background: #4f46e5; color: #fff; }
</style>

<script>
function filterStudents(state) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('btn-' + state).classList.add('active');
    document.querySelectorAll('.student-row').forEach(row => {
        row.style.display = (state === 'all' || row.dataset.state === state) ? '' : 'none';
    });
}

async function saveReview(subId, btn) {
    const scoreInput   = document.querySelector(`.score-input[data-sub-id="${subId}"]`);
    const commentInput = document.querySelector(`.comment-input[data-sub-id="${subId}"]`);
    const score = scoreInput.value;
    if (score === '') { Swal.fire({ icon:'warning', title:'กรุณากรอกคะแนน', confirmButtonColor:'#4f46e5' }); return; }

    btn.disabled = true; btn.textContent = 'กำลังบันทึก...';

    const res = await fetch('<?= $base_path ?>/homework/api/save_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sub_id: subId, score: parseInt(score), comment: commentInput.value.trim() })
    }).then(r => r.json());

    btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2"></i> บันทึก';

    if (res.status === 'success') {
        btn.textContent = '✓ บันทึกแล้ว';
        btn.classList.replace('bg-indigo-600', 'bg-emerald-500');
        setTimeout(() => { btn.innerHTML = '<i class="bi bi-check2"></i> บันทึก'; btn.classList.replace('bg-emerald-500','bg-indigo-600'); }, 2000);
    } else {
        Swal.fire({ icon:'error', title:'ผิดพลาด', text: res.message, confirmButtonColor:'#4f46e5' });
    }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
