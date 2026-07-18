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

// Fallback logic for testing (allows Teachers/Admins to view student panel)
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
    die("ไม่พบข้อมูลบัญชีนักเรียนของคุณในระบบ กรุณาเข้าสู่ระบบด้วยบัญชีสิทธิ์นักเรียน หรือขอความช่วยเหลือจากอาจารย์ประจำชั้น");
}

// Fetch subjects matching student classroom
$stmt = $pdo->prepare("
    SELECT s.*, t.name as teacher_name 
    FROM att_subjects s 
    LEFT JOIN att_teachers t ON s.teacher_id = t.id 
    WHERE s.classroom = ?
    ORDER BY s.subject_code ASC
");
$stmt->execute([$student_classroom]);
$subjects = $stmt->fetchAll();

// Group units and quizzes by subject
$units_with_quizzes = [];
if (!empty($subjects)) {
    foreach ($subjects as $subj) {
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   q_pre.id as pre_quiz_id, q_pre.title as pre_quiz_title, q_pre.time_limit as pre_time_limit,
                   q_post.id as post_quiz_id, q_post.title as post_quiz_title, q_post.time_limit as post_time_limit,
                   (SELECT COUNT(*) FROM lms_questions WHERE quiz_id = q_pre.id) as pre_question_count,
                   (SELECT COUNT(*) FROM lms_questions WHERE quiz_id = q_post.id) as post_question_count
            FROM lms_units u
            LEFT JOIN lms_quizzes q_pre ON q_pre.unit_id = u.id AND q_pre.quiz_type = 'pre' AND q_pre.is_active = 1
            LEFT JOIN lms_quizzes q_post ON q_post.unit_id = u.id AND q_post.quiz_type = 'post' AND q_post.is_active = 1
            WHERE u.subject_id = ?
            ORDER BY u.unit_number ASC
        ");
        $stmt->execute([$subj['id']]);
        $units_with_quizzes[$subj['id']] = $stmt->fetchAll();
    }
}

// Fetch all attempts for this student
$stmt = $pdo->prepare("SELECT * FROM lms_quiz_attempts WHERE student_id = ?");
$stmt->execute([$student_id]);
$attempts_list = $stmt->fetchAll();
$student_attempts = [];
foreach ($attempts_list as $att) {
    $student_attempts[$att['quiz_id']][] = $att;
}

$pageTitle = 'แบบทดสอบและประเมินผล';
$pageSubtitle = 'ทำแบบทดสอบวัดความรู้ก่อนเรียนและหลังเรียนในรายวิชาของคุณ';
$activeSystem = 'lms';

// Let's adapt base layout start
require_once __DIR__ . '/../components/layout_start.php';

// Display alert if student just submitted a quiz
if (isset($_SESSION['quiz_submit_msg'])) {
    $submit_msg = $_SESSION['quiz_submit_msg'];
    unset($_SESSION['quiz_submit_msg']);
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'ส่งข้อสอบสำเร็จ',
            text: '" . htmlspecialchars($submit_msg, ENT_QUOTES, 'UTF-8') . "',
            confirmButtonColor: '#4f46e5'
        });
    </script>";
}
?>

<!-- Student Profile Widget -->
<div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-3xl p-6 text-white shadow-xl shadow-blue-100/50 mb-8 flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center text-white text-2xl font-black">
            <i class="bi bi-person-fill"></i>
        </div>
        <div>
            <h2 class="text-lg font-black leading-tight"><?= htmlspecialchars($student_name) ?></h2>
            <p class="text-xs opacity-80 mt-1">
                <i class="bi bi-tag-fill"></i> ชั้นบรรยาย: <?= htmlspecialchars($student_classroom) ?> | รหัสนักเรียน: <?= htmlspecialchars($_SESSION['username'] ?? '—') ?>
            </p>
        </div>
    </div>
    <div class="px-4 py-2 bg-white/25 rounded-2xl border border-white/20 text-xs font-bold text-center">
        สะสมแล้ว <?= count($attempts_list) ?> บทเรียนที่มีสถิติทำข้อสอบ
    </div>
</div>

<!-- Subjects and Quizzes Grid -->
<div class="space-y-8">
    <?php if (empty($subjects)): ?>
        <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-12 text-center">
            <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="bi bi-emoji-neutral text-3xl"></i>
            </div>
            <h3 class="text-lg font-black text-slate-800">ไม่พบรายวิชาที่ต้องเรียน</h3>
            <p class="text-sm text-slate-500 mt-1">ไม่มีข้อมูลวิชาเรียนที่ลงทะเบียนสำหรับห้องเรียน <?= htmlspecialchars($student_classroom) ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($subjects as $subj): ?>
            <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
                <!-- Subject Header line -->
                <div class="bg-slate-50 px-6 py-4.5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="px-2.5 py-1 bg-blue-50 text-blue-700 font-black rounded-lg text-xs">
                            <?= htmlspecialchars($subj['subject_code']) ?>
                        </span>
                        <div>
                            <h3 class="text-sm sm:text-base font-black text-slate-800 leading-tight">
                                <?= htmlspecialchars($subj['subject_name']) ?>
                            </h3>
                            <p class="text-[10px] text-slate-500 mt-0.5">
                                ครูผู้สอน: <?= htmlspecialchars($subj['teacher_name'] ?? 'ไม่มีผู้สอน') ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Units and Assessments list -->
                <div class="p-6">
                    <?php if (empty($units_with_quizzes[$subj['id']])): ?>
                        <div class="text-center py-6 text-slate-400">
                            <span class="text-xs font-bold">ยังไม่เปิดหน้ารายละเอียดการประเมิน หรือแบบทดสอบในวิชานี้</span>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($units_with_quizzes[$subj['id']] as $unit): ?>
                                <div class="bg-slate-50/30 border border-slate-100/70 rounded-2xl p-4">
                                    <div class="flex items-center gap-2 mb-4">
                                        <span class="px-2 py-0.5 bg-slate-200 text-slate-700 font-bold rounded text-[10px]">
                                            หน่วยที่ <?= htmlspecialchars($unit['unit_number']) ?>
                                        </span>
                                        <h4 class="text-xs sm:text-sm font-black text-slate-700 leading-tight">
                                            <?= htmlspecialchars($unit['unit_name']) ?>
                                        </h4>
                                    </div>

                                    <!-- Grid modules representing pre and post tests -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- Pre-test Quiz -->
                                        <?php if ($unit['pre_quiz_id'] && $unit['pre_question_count'] > 0): ?>
                                            <?php 
                                            $quiz_id = $unit['pre_quiz_id'];
                                            $q_attempts = $student_attempts[$quiz_id] ?? [];
                                            $has_attempt = !empty($q_attempts);
                                            
                                            $best_score = 0;
                                            $in_progress = false;
                                            $attempt_record = null;
                                            
                                            foreach ($q_attempts as $att) {
                                                if ($att['status'] === 'in_progress') {
                                                    $in_progress = true;
                                                    $attempt_record = $att;
                                                }
                                                if ($att['score'] > $best_score) {
                                                    $best_score = $att['score'];
                                                    if (!$in_progress) $attempt_record = $att;
                                                }
                                            }
                                            ?>
                                            <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-sm flex items-center justify-between gap-3">
                                                <div>
                                                    <span class="text-[10px] font-black text-indigo-500 block mb-1 uppercase tracking-wider">
                                                        <i class="bi bi-clipboard-check"></i> แบบทดสอบก่อนเรียน
                                                    </span>
                                                    <h5 class="text-xs font-black text-slate-705 leading-normal">
                                                        <?= htmlspecialchars($unit['pre_quiz_title']) ?>
                                                    </h5>
                                                    <p class="text-[10px] font-bold text-slate-400 mt-1">
                                                        ข้อสอบ: <?= $unit['pre_question_count'] ?> ข้อ | เวลา: <?= $unit['pre_time_limit'] > 0 ? $unit['pre_time_limit'] . ' นาที' : 'ไม่จำกัด' ?>
                                                    </p>
                                                    
                                                    <?php if ($has_attempt && !$in_progress): ?>
                                                        <div class="mt-2 inline-flex items-center gap-1 bg-emerald-50 text-emerald-600 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                                            เสร็จชีวิต: ได้ดีสุด <?= number_format($best_score, 1) ?> คะแนน
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div>
                                                    <?php if ($in_progress): ?>
                                                        <a href="take_quiz.php?quiz_id=<?= $quiz_id ?>" class="inline-flex items-center gap-1 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-lg shadow-amber-100 transition-all hover:scale-[1.02]">
                                                            ทำต่อ
                                                        </a>
                                                    <?php elseif ($has_attempt): ?>
                                                        <button onclick="confirmRetake(<?= $quiz_id ?>, 'ก่อนเรียน')" class="inline-flex items-center gap-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3 py-2 rounded-xl transition-all border border-slate-200">
                                                            ทำใหม่
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="take_quiz.php?quiz_id=<?= $quiz_id ?>" class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-lg shadow-indigo-100 transition-all hover:scale-[1.02]">
                                                            ทำข้อสอบ
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-white/40 rounded-xl border border-dashed border-slate-200 p-4 text-center">
                                                <span class="text-[10px] font-bold text-slate-400 block py-3">ไม่มีแบบทดสอบก่อนเรียน</span>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Post-test Quiz -->
                                        <?php if ($unit['post_quiz_id'] && $unit['post_question_count'] > 0): ?>
                                            <?php 
                                            $quiz_id = $unit['post_quiz_id'];
                                            $q_attempts = $student_attempts[$quiz_id] ?? [];
                                            $has_attempt = !empty($q_attempts);
                                            
                                            $best_score = 0;
                                            $in_progress = false;
                                            $attempt_record = null;
                                            
                                            foreach ($q_attempts as $att) {
                                                if ($att['status'] === 'in_progress') {
                                                    $in_progress = true;
                                                    $attempt_record = $att;
                                                }
                                                if ($att['score'] > $best_score) {
                                                    $best_score = $att['score'];
                                                    if (!$in_progress) $attempt_record = $att;
                                                }
                                            }
                                            ?>
                                            <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-sm flex items-center justify-between gap-3">
                                                <div>
                                                    <span class="text-[10px] font-black text-pink-500 block mb-1 uppercase tracking-wider">
                                                        <i class="bi bi-award-fill"></i> แบบทดสอบหลังเรียน
                                                    </span>
                                                    <h5 class="text-xs font-black text-slate-705 leading-normal">
                                                        <?= htmlspecialchars($unit['post_quiz_title']) ?>
                                                    </h5>
                                                    <p class="text-[10px] font-bold text-slate-400 mt-1">
                                                        ข้อสอบ: <?= $unit['post_question_count'] ?> ข้อ | เวลา: <?= $unit['post_time_limit'] > 0 ? $unit['post_time_limit'] . ' นาที' : 'ไม่จำกัด' ?>
                                                    </p>
                                                    
                                                    <?php if ($has_attempt && !$in_progress): ?>
                                                        <div class="mt-2 inline-flex items-center gap-1 bg-emerald-50 text-emerald-600 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                                            ทำแล้ว: ได้ดีสุด <?= number_format($best_score, 1) ?> คะแนน
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div>
                                                    <?php if ($in_progress): ?>
                                                        <a href="take_quiz.php?quiz_id=<?= $quiz_id ?>" class="inline-flex items-center gap-1 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-lg shadow-amber-100 transition-all hover:scale-[1.02]">
                                                            ทำต่อ
                                                        </a>
                                                    <?php elseif ($has_attempt): ?>
                                                        <button onclick="confirmRetake(<?= $quiz_id ?>, 'หลังเรียน')" class="inline-flex items-center gap-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-3 py-2 rounded-xl transition-all border border-slate-200">
                                                            ทำใหม่
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="take_quiz.php?quiz_id=<?= $quiz_id ?>" class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-lg shadow-indigo-100 transition-all hover:scale-[1.02]">
                                                            ทำข้อสอบ
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-white/40 rounded-xl border border-dashed border-slate-200 p-4 text-center">
                                                <span class="text-[10px] font-bold text-slate-400 block py-3">ไม่มีแบบทดสอบหลังเรียน</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    function confirmRetake(quizId, textType) {
        Swal.fire({
            title: `เริ่มทำแบบทดสอบ${textType}ใหม่?`,
            text: 'การทำแบบทดสอบใหม่จะบันทึกเป็นคะแนนครั้งใหม่เข้ามาในระบบ',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ยืนยัน, เริ่มต้นทำข้อสอบ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect to take_quiz with a forcing parameter
                window.location.href = `take_quiz.php?quiz_id=${quizId}&new_attempt=1`;
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
