<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth Guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'att_teacher'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();
$quiz_id = (int)($_GET['quiz_id'] ?? 0);
if (!$quiz_id) {
    header('Location: manage_units.php'); exit();
}

// Fetch quiz details
$stmt = $pdo->prepare("
    SELECT q.*, u.unit_number, u.unit_name, u.subject_id, s.subject_name, s.subject_code, s.classroom, s.teacher_id
    FROM lms_quizzes q
    JOIN lms_units u ON q.unit_id = u.id
    JOIN att_subjects s ON u.subject_id = s.id
    WHERE q.id = ?
");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: manage_units.php'); exit();
}

// Ensure teacher owns this subject
if ($_SESSION['llw_role'] !== 'super_admin' && $quiz['teacher_id'] != ($_SESSION['teacher_id'] ?? 0)) {
    header('Location: manage_units.php'); exit();
}

// Fetch all students in this classroom
$stmt = $pdo->prepare("
    SELECT * FROM att_students 
    WHERE classroom = ? AND status = 'active'
    ORDER BY student_id ASC
");
$stmt->execute([$quiz['classroom']]);
$students = $stmt->fetchAll();

// Fetch attempts for this quiz
$stmt = $pdo->prepare("
    SELECT a.*, s.name as student_name, s.student_id as student_code
    FROM lms_quiz_attempts a
    JOIN att_students s ON a.student_id = s.id
    WHERE a.quiz_id = ?
    ORDER BY a.id DESC
");
$stmt->execute([$quiz_id]);
$attempts = $stmt->fetchAll();

// Group attempts by student_id (since students can have multiple attempts, we can show their best attempt or count attempts)
$student_attempts = [];
foreach ($attempts as $attempt) {
    $student_attempts[$attempt['student_id']][] = $attempt;
}

// Calculate Stats
$total_students = count($students);
$participating_students_count = 0;
$scores = [];
$max_points = 0;

// Find max points for this quiz based on questions
$stmt = $pdo->prepare("SELECT SUM(points) FROM lms_questions WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$max_points = (int)($stmt->fetchColumn() ?: 0);

foreach ($students as $student) {
    $s_id = $student['id'];
    if (isset($student_attempts[$s_id])) {
        $participating_students_count++;
        // Get best score
        $best_score = 0;
        foreach ($student_attempts[$s_id] as $att) {
            if ($att['score'] > $best_score) {
                $best_score = $att['score'];
            }
        }
        $scores[] = $best_score;
    }
}

$highest_score = !empty($scores) ? max($scores) : 0;
$lowest_score = !empty($scores) ? min($scores) : 0;
$avg_score = !empty($scores) ? (array_sum($scores) / count($scores)) : 0;

$pageTitle = 'รายงานผลคะแนนสอบ';
$pageSubtitle = htmlspecialchars($quiz['title']) . ' (' . ($quiz['quiz_type'] === 'pre' ? 'ก่อนเรียน' : 'หลังเรียน') . ')';
$activeSystem = 'lms';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Context / Header Navigation -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <a href="manage_units.php" class="inline-flex items-center justify-center w-10 h-10 bg-white hover:bg-slate-50 text-slate-600 rounded-xl border border-slate-200 shadow-sm transition-all">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-sm font-semibold text-slate-500">
                <?= htmlspecialchars($quiz['subject_code']) ?> • <?= htmlspecialchars($quiz['subject_name']) ?> (<?= htmlspecialchars($quiz['classroom']) ?>)
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">หน่วยเรียนที่ <?= $quiz['unit_number'] ?>: <?= htmlspecialchars($quiz['unit_name']) ?></p>
        </div>
    </div>
    
    <div class="flex items-center gap-2">
        <button onclick="window.print()" class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-xs font-bold transition-all border border-slate-250 no-print">
            <i class="bi bi-printer"></i> พิมพ์รายงาน
        </button>
    </div>
</div>

<!-- Stats and Analytics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 text-white">
    <div class="bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-2xl p-6 shadow-xl shadow-indigo-100/50">
        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">การเข้าสอบของนักเรียน</p>
        <p class="text-4xl font-black mt-2"><?= $participating_students_count ?> / <?= $total_students ?></p>
        <div class="w-full bg-white/20 h-1.5 rounded-full mt-3 overflow-hidden">
            <div class="bg-white h-full" style="width: <?= $total_students > 0 ? ($participating_students_count / $total_students * 100) : 0 ?>%"></div>
        </div>
    </div>
    
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl p-6 shadow-xl shadow-emerald-100/50">
        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">คะแนนสูงสุด (Max)</p>
        <p class="text-4xl font-black mt-2"><?= number_format($highest_score, 1) ?> <span class="text-sm font-semibold opacity-75">/ <?= $max_points ?></span></p>
        <span class="text-xs font-medium opacity-70 mt-1 block">จากคะแนนที่ดีที่สุดของนักเรียน</span>
    </div>

    <div class="bg-gradient-to-br from-indigo-550 to-purple-700 rounded-2xl p-6 shadow-xl shadow-indigo-100/50">
        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">คะแนนเฉลี่ย (Mean)</p>
        <p class="text-4xl font-black mt-2"><?= number_format($avg_score, 1) ?> <span class="text-sm font-semibold opacity-75">/ <?= $max_points ?></span></p>
        <span class="text-xs font-medium opacity-70 mt-1 block">ส่วนเบี่ยงเบนเฉลี่ยห้องเรียน</span>
    </div>

    <div class="bg-gradient-to-br from-pink-500 to-pink-700 rounded-2xl p-6 shadow-xl shadow-pink-100/50">
        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">คะแนนต่ำสุด (Min)</p>
        <p class="text-4xl font-black mt-2"><?= number_format($lowest_score, 1) ?> <span class="text-sm font-semibold opacity-75">/ <?= $max_points ?></span></p>
        <span class="text-xs font-medium opacity-70 mt-1 block">จากคะแนนที่มีการส่งผลแล้ว</span>
    </div>
</div>

<!-- Detailed Students Table -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100 header_table font-black text-slate-800 flex justify-between items-center bg-slate-50/50">
        <span class="text-sm font-black text-slate-800">รายชื่อนักเรียนและผลคะแนนการทำแบบทดสอบ</span>
        <span class="text-xs text-slate-500 font-normal">ทั้งหมด <?= $total_students ?> คน</span>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider">รหัสประจำตัว</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider">ชื่อ-นามสกุล</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider text-center">สถานะ</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider text-center">จำนวนครั้งที่ทำ</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider text-center">คะแนนดีที่สุด</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider text-center">ส่งข้อสอบล่าสุด</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider text-center no-print">ตอบกลับ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            ไม่พบรายชื่อคู่นักเรียนในชั้นเรียนนี้
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $stu): ?>
                        <?php 
                        $s_id = $stu['id'];
                        $stu_att = $student_attempts[$s_id] ?? [];
                        $has_attempt = !empty($stu_att);
                        
                        $best_s = 0;
                        $latest_time = '—';
                        $status_badge = '<span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-500">ไม่ได้ทำสอบ</span>';
                        
                        if ($has_attempt) {
                            $latest_att = $stu_att[0]; // Latest is first due to query order
                            $latest_time = date('d/m/Y H:i', strtotime($latest_att['completed_at'] ?: $latest_att['started_at']));
                            
                            $in_prog = false;
                            foreach ($stu_att as $att) {
                                if ($att['score'] > $best_s) {
                                    $best_s = $att['score'];
                                }
                                if ($att['status'] === 'in_progress') {
                                    $in_prog = true;
                                }
                            }
                            
                            if ($in_prog) {
                                $status_badge = '<span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-amber-50 text-amber-600">กำลังทำ</span>';
                            } else {
                                $status_badge = '<span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-emerald-50 text-emerald-600">ส่งแล้ว</span>';
                            }
                        }
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 font-bold text-xs text-slate-500"><?= htmlspecialchars($stu['student_id']) ?></td>
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-800"><?= htmlspecialchars($stu['name']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-center"><?= $status_badge ?></td>
                            <td class="px-6 py-4 text-center font-bold text-slate-600"><?= count($stu_att) ?></td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($has_attempt): ?>
                                    <span class="text-sm font-black text-indigo-650"><?= number_format($best_s, 1) ?></span> / <?= $max_points ?>
                                <?php else: ?>
                                    <span class="text-slate-350">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center text-xs text-slate-400"><?= $latest_time ?></td>
                            <td class="px-6 py-4 text-center no-print">
                                <?php if ($has_attempt): ?>
                                    <button onclick="viewStudentResponses(<?= $stu['id'] ?>, '<?= htmlspecialchars($stu['name'], ENT_QUOTES) ?>')" class="inline-flex items-center gap-1 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-1 rounded shadow-sm transition-all">
                                        <i class="bi bi-eye-fill"></i> ตรวจคำตอบ
                                    </button>
                                <?php else: ?>
                                    <span class="text-slate-300 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= RESPONSE MODAL ================= -->

<div id="responseModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-2xl shadow-2xl border border-slate-100 overflow-hidden transform transition-all duration-300">
        <div class="px-6 py-5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-base font-black text-slate-800">กระดาษคำตอบของนักเรียน</h3>
                <p id="response_student_name" class="text-xs text-slate-500 font-bold mt-0.5"></p>
            </div>
            <button onclick="closeResponseModal()" class="w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full flex items-center justify-center transition-all">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div id="modal_responses_container" class="p-6 space-y-6 max-h-[60vh] overflow-y-auto no-scrollbar font-prompt">
            <!-- Loading or Responses will list here dynamic via JS fetch -> API -->
        </div>

        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
            <button type="button" onclick="closeResponseModal()" class="px-5 py-2 bg-indigo-650 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition-all">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>

<script>
    function viewStudentResponses(studentId, name) {
        document.getElementById('response_student_name').textContent = name;
        const container = document.getElementById('modal_responses_container');
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                <div class="animate-spin rounded-full h-8 w-8 border-4 border-slate-200 border-t-indigo-500 mb-3"></div>
                <p class="text-xs font-bold">กำลังดึงข้อมูลใบคำตอบ...</p>
            </div>
        `;
        document.getElementById('responseModal').classList.remove('hidden');

        // Fetch student answers via AJAX
        fetch(`get_student_responses.php?quiz_id=<?= $quiz_id ?>&student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                displayResponses(data.data);
            } else {
                container.innerHTML = `<p class="text-xs text-center font-bold text-rose-500">${data.message || 'เกิดข้อผิดพลาดในการโหลดผลสอบ'}</p>`;
            }
        })
        .catch(err => {
            container.innerHTML = `<p class="text-xs text-center font-bold text-rose-500">เกิดข้อผิดพลาดในการติดต่อระบบ</p>`;
        });
    }

    function displayResponses(data) {
        const container = document.getElementById('modal_responses_container');
        if (!data.length) {
            container.innerHTML = '<p class="text-xs text-center text-slate-500 font-bold py-6">ไม่มีประวัติคำตอบของนักเรียน</p>';
            return;
        }

        let html = '';
        data.forEach((q, idx) => {
            let choiceHtml = '';
            q.choices.forEach((c, cIdx) => {
                let choiceCls = 'border-slate-120 text-slate-600 bg-slate-50/50';
                let icon = '';

                // If this choice is selected by student
                const is_selected = (c.id == q.selected_choice_id);

                if (is_selected) {
                    if (q.is_correct) {
                        choiceCls = 'bg-emerald-50 border-emerald-300 text-emerald-800 font-bold';
                        icon = '<i class="bi bi-check-circle-fill text-emerald-600 text-xs ml-1"></i>';
                    } else {
                        choiceCls = 'bg-rose-50 border-rose-200 text-rose-800 font-bold';
                        icon = '<i class="bi bi-x-circle-fill text-rose-600 text-xs ml-1"></i>';
                    }
                } else if (c.is_correct) {
                    // Show correct answer if student got it wrong
                    choiceCls = 'border-emerald-200 bg-emerald-50/10 text-emerald-800';
                    icon = '<span class="text-[9px] font-black text-emerald-600 ml-1.5">(คำตอบที่ถูก)</span>';
                }

                choiceHtml += `
                    <div class="flex items-start gap-2 p-2.5 rounded-xl border ${choiceCls}">
                        <span class="w-5 h-5 rounded text-[10px] font-black flex items-center justify-center flex-shrink-0 mt-0.5 border ${c.is_correct ? 'bg-emerald-100 border-emerald-300 text-emerald-800' : 'bg-slate-100 border-slate-200 text-slate-500'}">
                            ${String.fromCharCode(65 + cIdx)}
                        </span>
                        <div class="text-xs select-none flex-1">
                            ${c.choice_text} ${icon}
                        </div>
                    </div>
                `;
            });

            html += `
                <div class="p-4 rounded-xl border border-slate-120 space-y-3 bg-white">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400">คำถามข้อที่ ${idx + 1}</span>
                        <span class="px-2 py-0.5 text-[10px] font-black rounded ${q.is_correct ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}">
                            ${q.is_correct ? 'ถูกต้อง' : 'ผิด'} (${q.points} คะแนน)
                        </span>
                    </div>
                    <h4 class="text-sm font-bold text-slate-800 whitespace-pre-line">${q.question_text}</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                        ${choiceHtml}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }
    
    function closeResponseModal() {
        document.getElementById('responseModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
