<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth Guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();

// Identify Student Profile
$student_id = $_SESSION['student_id'] ?? 0;
$student_classroom = $_SESSION['student_classroom'] ?? '';
$student_name = $_SESSION['student_name'] ?? '';

// Fallback logic for testing
if (!$student_id && in_array($_SESSION['llw_role'], ['super_admin', 'att_teacher'])) {
    $s_stmt = $pdo->query("SELECT id, name, classroom FROM att_students WHERE status='active' LIMIT 1");
    $mock_student = $s_stmt->fetch();
    if ($mock_student) {
        $student_id = $mock_student['id'];
        $student_classroom = $mock_student['classroom'];
        $student_name = $mock_student['name'] . ' (สิทธิ์ครูทดสอบ)';
    }
}

if (!$student_id) {
    die("ไม่พบข้อมูลบัญชีนักเรียนของคุณในระบบ");
}

$quiz_id = (int)($_GET['quiz_id'] ?? 0);
if (!$quiz_id) {
    header('Location: student_quizzes.php'); exit();
}

// Fetch active quiz with unit details
$stmt = $pdo->prepare("
    SELECT q.*, u.unit_number, u.unit_name, s.subject_code, s.subject_name
    FROM lms_quizzes q
    JOIN lms_units u ON q.unit_id = u.id
    JOIN att_subjects s ON u.subject_id = s.id
    WHERE q.id = ? AND q.is_active = 1
");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    die("ยังไม่เปิดแบบทดสอบนี้ในระบ หรือระบบยังไม่เปิดให้คนเข้าเข้าทดสอบ");
}

// Fetch all questions for this quiz
$stmt = $pdo->prepare("SELECT * FROM lms_questions WHERE quiz_id = ? ORDER BY id ASC");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

if (empty($questions)) {
    die("แบบทดสอบนี้ยังไม่มีคำถามข้อสอบ กรุณาติดต่อคุณครูเพื่อตรวจสอบ");
}

// Calculate total points
$total_points = 0;
foreach ($questions as $q) {
    $total_points += $q['points'];
}

// Handle attempt initiation/retrieval
$new_attempt_requested = isset($_GET['new_attempt']) && (int)$_GET['new_attempt'] === 1;
$attempt = null;

if (!$new_attempt_requested) {
    // Check if there is an in-progress attempt
    $stmt = $pdo->prepare("
        SELECT * FROM lms_quiz_attempts 
        WHERE quiz_id = ? AND student_id = ? AND status = 'in_progress' 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$quiz_id, $student_id]);
    $attempt = $stmt->fetch();
}

if (!$attempt) {
    // Create new attempt
    $stmt = $pdo->prepare("
        INSERT INTO lms_quiz_attempts (quiz_id, student_id, started_at, status, total_points, score) 
        VALUES (?, ?, NOW(), 'in_progress', ?, 0)
    ");
    $stmt->execute([$quiz_id, $student_id, $total_points]);
    
    // Retrieve newly created attempt
    $stmt = $pdo->prepare("SELECT * FROM lms_quiz_attempts WHERE id = ?");
    $stmt->execute([$pdo->lastInsertId()]);
    $attempt = $stmt->fetch();
}

// Calculate remaining time
$time_limit_sec = $quiz['time_limit'] * 60;
$remaining_sec = 0;
if ($quiz['time_limit'] > 0) {
    $elapsed_sec = time() - strtotime($attempt['started_at']);
    $remaining_sec = $time_limit_sec - $elapsed_sec;
    
    // If time exceeded, auto submit handler is processed below
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit') {
    $answers = $_POST['answers'] ?? []; // Array mapping question_id -> choice_id
    
    try {
        $pdo->beginTransaction();
        
        $score = 0;
        
        // Loop through questions to grade answers
        foreach ($questions as $q) {
            $question_id = $q['id'];
            $selected_choice_id = isset($answers[$question_id]) ? (int)$answers[$question_id] : null;
            $is_correct = 0;
            $points_earned = 0;
            
            if ($selected_choice_id) {
                // Check if correct choice
                $c_stmt = $pdo->prepare("SELECT is_correct FROM lms_choices WHERE id = ? AND question_id = ?");
                $c_stmt->execute([$selected_choice_id, $question_id]);
                $choice_correct = (int)$c_stmt->fetchColumn();
                
                if ($choice_correct === 1) {
                    $is_correct = 1;
                    $points_earned = $q['points'];
                    $score += $q['points'];
                }
            }
            
            // Check if answer record already exists (to prevent duplicate queries on double submits)
            $chk_stmt = $pdo->prepare("SELECT id FROM lms_quiz_answers WHERE attempt_id = ? AND question_id = ?");
            $chk_stmt->execute([$attempt['id'], $question_id]);
            $existing_ans = $chk_stmt->fetchColumn();
            
            if ($existing_ans) {
                $up_stmt = $pdo->prepare("
                    UPDATE lms_quiz_answers 
                    SET choice_id = ?, is_correct = ?, points_earned = ? 
                    WHERE id = ?
                ");
                $up_stmt->execute([$selected_choice_id, $is_correct, $points_earned, $existing_ans]);
            } else {
                $ins_stmt = $pdo->prepare("
                    INSERT INTO lms_quiz_answers (attempt_id, question_id, choice_id, is_correct, points_earned) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $ins_stmt->execute([$attempt['id'], $question_id, $selected_choice_id, $is_correct, $points_earned]);
            }
        }
        
        // Finalize Attempt
        $stmt = $pdo->prepare("
            UPDATE lms_quiz_attempts 
            SET score = ?, status = 'completed', completed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$score, $attempt['id']]);
        
        $pdo->commit();
        
        // Show success and redirect
        $_SESSION['quiz_submit_msg'] = "ส่งแบบทดสอบสำเร็จ! คุณทำคะแนนได้ " . number_format($score, 1) . " / " . $total_points . " คะแนน";
        header("Location: student_quizzes.php");
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("เกิดข้อผิดพลาดในการบันทึกข้อสอบ: " . $e->getMessage());
    }
}

// Fetch other choices to display in cards
$questions_with_choices = [];
foreach ($questions as $q) {
    // Fetch choices
    $stmt = $pdo->prepare("SELECT * FROM lms_choices WHERE question_id = ? ORDER BY id ASC");
    $stmt->execute([$q['id']]);
    $q['choices'] = $stmt->fetchAll();
    
    // Fetch saved answer if page was refreshed
    $stmt = $pdo->prepare("SELECT choice_id FROM lms_quiz_answers WHERE attempt_id = ? AND question_id = ? LIMIT 1");
    $stmt->execute([$attempt['id'], $q['id']]);
    $q['saved_choice_id'] = $stmt->fetchColumn() ?: null;
    
    $questions_with_choices[] = $q;
}

$pageTitle = 'ห้องวิเคราะห์ข้อสอบ';
$pageSubtitle = htmlspecialchars($quiz['title']);
$activeSystem = 'lms';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Timer & Header Sticky Banner -->
<div class="sticky top-16 sm:top-20 z-40 bg-white border border-slate-200 rounded-3xl p-5 shadow-lg shadow-slate-100/50 mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4 select-none no-print">
    <div>
        <h2 class="text-sm font-black text-slate-800 leading-tight">
            <?= htmlspecialchars($quiz['subject_code']) ?> • <?= htmlspecialchars($quiz['subject_name']) ?> • หน่วยที่ <?= $quiz['unit_number'] ?>
        </h2>
        <p class="text-xs text-slate-400 mt-1">ผู้ทำแบบทดสอบ: <?= htmlspecialchars($student_name) ?></p>
    </div>
    
    <div class="flex items-center gap-4">
        <!-- Countdown Timer -->
        <?php if ($quiz['time_limit'] > 0): ?>
            <div id="quizTimerWrapper" class="flex items-center gap-2 bg-rose-50 border border-rose-100 text-rose-700 px-4 py-2 rounded-2xl">
                <i class="bi bi-clock-history text-lg animate-pulse"></i>
                <div class="flex flex-col text-right">
                    <span class="text-[9px] font-black uppercase tracking-wider opacity-85">เวลาที่เหลือ</span>
                    <span id="quizTimeDisplay" class="font-black text-sm tracking-widest">00:00:00</span>
                </div>
            </div>
        <?php else: ?>
            <div class="flex items-center gap-2 bg-indigo-50 border border-indigo-150 text-indigo-750 px-4 py-2 rounded-2xl">
                <i class="bi bi-infinity text-lg"></i>
                <span class="text-xs font-black">ไม่จำกัดเวลาในการสอบ</span>
            </div>
        <?php endif; ?>

        <button onclick="confirmSubmit()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-5 py-3 rounded-2xl shadow-lg shadow-emerald-100 transition-all hover:scale-[1.01]">
            <i class="bi bi-send-fill"></i> ส่งคำตอบข้อสอบ
        </button>
    </div>
</div>

<!-- Questions Form -->
<form id="quizForm" method="POST">
    <input type="hidden" name="action" value="submit">
    
    <div class="space-y-6 max-w-4xl mx-auto">
        <?php foreach ($questions_with_choices as $index => $q): ?>
            <div class="bg-white rounded-2xl shadow-md border border-slate-100 p-6 space-y-4 hover:shadow-lg transition-all" id="q_card_<?= $q['id'] ?>">
                <div class="flex items-center justify-between pb-3 border-b border-slate-50">
                    <span class="inline-flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs font-black">
                            <?= $index + 1 ?>
                        </span>
                        <span class="text-xs font-bold text-slate-400">คำถามสอบ (<?= $q['points'] ?> คะแนน)</span>
                    </span>
                    
                    <!-- Skip / Answer Indicator -->
                    <span id="status_q_<?= $q['id'] ?>" class="text-[10px] font-black rounded-lg px-2 py-0.5 <?= $q['saved_choice_id'] ? 'bg-indigo-50 text-indigo-650' : 'bg-slate-100 text-slate-400' ?>">
                        <?= $q['saved_choice_id'] ? 'ตอบแล้ว' : 'ยังไม่ได้ตอบ' ?>
                    </span>
                </div>
                
                <h4 class="text-slate-800 text-sm sm:text-base font-bold whitespace-pre-line leading-relaxed pl-1">
                    <?= htmlspecialchars($q['question_text']) ?>
                </h4>
                
                <!-- Choices Radio Buttons -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pl-1">
                    <?php foreach ($q['choices'] as $cIndex => $choice): ?>
                        <?php 
                        $isChecked = $q['saved_choice_id'] == $choice['id'];
                        ?>
                        <label class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-100 hover:border-indigo-150 hover:bg-slate-50/50 cursor-pointer select-none transition-all relative q-option-label" id="label_choice_<?= $choice['id'] ?>">
                            <input type="radio" 
                                   name="answers[<?= $q['id'] ?>]" 
                                   value="<?= $choice['id'] ?>" 
                                   class="w-4 h-4 text-indigo-600 border-slate-350 focus:ring-indigo-500 mt-0.5 peer"
                                   id="radio_choice_<?= $choice['id'] ?>"
                                   onchange="markAnswered(<?= $q['id'] ?>, <?= $choice['id'] ?>)"
                                   <?= $isChecked ? 'checked' : '' ?>>
                            
                            <span class="w-5 h-5 rounded-lg bg-slate-100 border border-slate-200 text-[10px] font-black text-slate-500 flex items-center justify-center flex-shrink-0 peer-checked:bg-indigo-100 peer-checked:border-indigo-300 peer-checked:text-indigo-800">
                                <?= chr(65 + $cIndex) ?>
                            </span>
                            
                            <span class="text-xs sm:text-sm text-slate-600 peer-checked:text-indigo-900 peer-checked:font-bold">
                                <?= htmlspecialchars($choice['choice_text']) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</form>

<!-- JS Controller for take_quiz -->
<script>
    // Countdown Timer logic
    const isTimed = <?= $quiz['time_limit'] > 0 ? 'true' : 'false' ?>;
    let secondsLeft = <?= $remaining_sec ?>;
    
    if (isTimed) {
        if (secondsLeft <= 0) {
            // Already expired, force submit automatically!
            Swal.fire({
                icon: 'warning',
                title: 'หมดเวลาขอกำหนดเวลาส่งสอบ!',
                text: 'เวลาทำเครื่องข้อสอบหมด การทำสอบจะถูกส่งทันที',
                confirmButtonColor: '#4f46e5',
                allowOutsideClick: false
            }).then(() => {
                document.getElementById('quizForm').submit();
            });
        } else {
            const display = document.getElementById('quizTimeDisplay');
            const interval = setInterval(() => {
                secondsLeft--;
                
                if (secondsLeft <= 0) {
                    clearInterval(interval);
                    // Disable timer alerts
                    window.onbeforeunload = null;
                    Swal.fire({
                        icon: 'warning',
                        title: 'หมดเวลาในการทำสอบ!',
                        text: 'ระบบกำลังนำส่งผลสอบอัติโนมัติ',
                        confirmButtonColor: '#4f46e5',
                        allowOutsideClick: false
                    }).then(() => {
                        document.getElementById('quizForm').submit();
                    });
                } else {
                    const hrs = Math.floor(secondsLeft / 3600);
                    const mins = Math.floor((secondsLeft % 3600) / 60);
                    const secs = secondsLeft % 60;
                    
                    display.textContent = 
                        String(hrs).padStart(2, '0') + ':' + 
                        String(mins).padStart(2, '0') + ':' + 
                        String(secs).padStart(2, '0');
                }
            }, 1000);
        }
    }

    // Auto save status indicators
    function markAnswered(questionId, choiceId) {
        const badge = document.getElementById('status_q_' + questionId);
        if (badge) {
            badge.textContent = 'ตอบแล้ว';
            badge.className = 'text-[10px] font-black rounded-lg px-2 py-0.5 bg-indigo-50 text-indigo-650';
        }
    }

    // Submit warnings
    function confirmSubmit() {
        Swal.fire({
            title: 'ต้องการส่งกระดาษคำตอบ?',
            text: 'กรุณาตรวจสอบความถูกต้องของตัวเลือกสอบว่าตอบถูกต้องครบถ้วนแล้วหรือยัง?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ยืนยันการส่งข้อสอบ',
            cancelButtonText: 'ย้อนกลับ'
        }).then((result) => {
            if (result.isConfirmed) {
                window.onbeforeunload = null;
                document.getElementById('quizForm').submit();
            }
        });
    }

    // Protect exit
    window.onbeforeunload = function(e) {
        e.preventDefault();
        e.returnValue = 'คุณกำลังทำข้อสอบค้างอยู่ ต้องการออกจากหน้านี้ใช่หรือไม่?';
        return 'คุณกำลังทำข้อสอบค้างอยู่ ต้องการออกจากหน้านี้ใช่หรือไม่?';
    };
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
