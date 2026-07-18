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
$msg = '';
$msgType = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_unit') {
            $subject_id = (int)$_POST['subject_id'];
            $unit_number = (int)$_POST['unit_number'];
            $unit_name = trim($_POST['unit_name']);
            $description = trim($_POST['description']);
            
            if (!$subject_id || !$unit_number || !$unit_name) {
                throw new Exception('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
            }
            
            $stmt = $pdo->prepare("INSERT INTO lms_units (subject_id, unit_number, order_no, unit_name, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$subject_id, $unit_number, $unit_number, $unit_name, $description]);
            $msg = 'เพิ่มหน่วยการเรียนรู้สำเร็จ';
            
        } elseif ($action === 'edit_unit') {
            $unit_id = (int)$_POST['unit_id'];
            $unit_number = (int)$_POST['unit_number'];
            $unit_name = trim($_POST['unit_name']);
            $description = trim($_POST['description']);
            
            if (!$unit_id || !$unit_number || !$unit_name) {
                throw new Exception('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
            }
            
            $stmt = $pdo->prepare("UPDATE lms_units SET unit_number = ?, order_no = ?, unit_name = ?, description = ? WHERE id = ?");
            $stmt->execute([$unit_number, $unit_number, $unit_name, $description, $unit_id]);
            $msg = 'แก้ไขหน่วยการเรียนรู้สำเร็จ';
            
        } elseif ($action === 'delete_unit') {
            $unit_id = (int)$_POST['unit_id'];
            if (!$unit_id) throw new Exception('ไม่พบรหัสหน่วยการเรียนรู้');
            
            $stmt = $pdo->prepare("DELETE FROM lms_units WHERE id = ?");
            $stmt->execute([$unit_id]);
            $msg = 'ลบหน่วยการเรียนรู้สำเร็จ';
            
        } elseif ($action === 'create_quiz') {
            $unit_id = (int)$_POST['unit_id'];
            $quiz_type = $_POST['quiz_type'] === 'post' ? 'post' : 'pre';
            $title = trim($_POST['title']);
            $time_limit = (int)$_POST['time_limit'];
            
            if (!$unit_id || !$title) {
                throw new Exception('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
            }
            
            // Check if quiz already exists of this type
            $stmt = $pdo->prepare("SELECT id FROM lms_quizzes WHERE unit_id = ? AND quiz_type = ?");
            $stmt->execute([$unit_id, $quiz_type]);
            if ($stmt->fetch()) {
                throw new Exception('มีแบบทดสอบประเภทนี้ในหน่วยการเรียนรู้นี้แล้ว');
            }
            
            $stmt = $pdo->prepare("INSERT INTO lms_quizzes (unit_id, quiz_type, title, time_limit, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$unit_id, $quiz_type, $title, $time_limit]);
            $msg = 'สร้างแบบทดสอบสำเร็จ';
            
        } elseif ($action === 'update_quiz_settings') {
            $quiz_id = (int)$_POST['quiz_id'];
            $title = trim($_POST['title']);
            $time_limit = (int)$_POST['time_limit'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (!$quiz_id || !$title) {
                throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
            }
            
            $stmt = $pdo->prepare("UPDATE lms_quizzes SET title = ?, time_limit = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$title, $time_limit, $is_active, $quiz_id]);
            $msg = 'บันทึกการตั้งค่าแบบทดสอบสำเร็จ';
            
        } elseif ($action === 'toggle_quiz') {
            header('Content-Type: application/json');
            $quiz_id = (int)$_POST['quiz_id'];
            $is_active = (int)$_POST['is_active'];
            
            $stmt = $pdo->prepare("UPDATE lms_quizzes SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $quiz_id]);
            
            echo json_encode(['status' => 'success']);
            exit;
            
        } elseif ($action === 'delete_quiz') {
            $quiz_id = (int)$_POST['quiz_id'];
            if (!$quiz_id) throw new Exception('ไม่พบรหัสแบบทดสอบ');
            
            $stmt = $pdo->prepare("DELETE FROM lms_quizzes WHERE id = ?");
            $stmt->execute([$quiz_id]);
            $msg = 'ลบแบบทดสอบสำเร็จ';
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = 'error';
    }
}

// Fetch subjects
if ($_SESSION['llw_role'] === 'super_admin') {
    $stmt = $pdo->prepare("
        SELECT s.*, t.name as teacher_name 
        FROM att_subjects s 
        LEFT JOIN att_teachers t ON s.teacher_id = t.id 
        ORDER BY s.subject_code ASC, s.classroom ASC
    ");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, t.name as teacher_name 
        FROM att_subjects s 
        LEFT JOIN att_teachers t ON s.teacher_id = t.id 
        WHERE s.teacher_id = ? 
        ORDER BY s.subject_code ASC, s.classroom ASC
    ");
    $stmt->execute([$_SESSION['teacher_id'] ?? 0]);
}
$subjects = $stmt->fetchAll();

// Group units by subject_id
$subjectIds = array_column($subjects, 'id');
$units = [];
$statsTotalUnits = 0;
$statsTotalQuizzes = 0;
$statsTotalAttempts = 0;

if (!empty($subjectIds)) {
    $inQuery = implode(',', array_fill(0, count($subjectIds), '?'));
    
    // Fetch units and quizzes
    $stmt = $pdo->prepare("
        SELECT u.*, 
               q_pre.id as pre_quiz_id, q_pre.is_active as pre_quiz_active, q_pre.time_limit as pre_time_limit, q_pre.title as pre_quiz_title,
               q_post.id as post_quiz_id, q_post.is_active as post_quiz_active, q_post.time_limit as post_time_limit, q_post.title as post_quiz_title,
               (SELECT COUNT(*) FROM lms_questions WHERE quiz_id = q_pre.id) as pre_question_count,
               (SELECT COUNT(*) FROM lms_questions WHERE quiz_id = q_post.id) as post_question_count
        FROM lms_units u
        LEFT JOIN lms_quizzes q_pre ON q_pre.unit_id = u.id AND q_pre.quiz_type = 'pre'
        LEFT JOIN lms_quizzes q_post ON q_post.unit_id = u.id AND q_post.quiz_type = 'post'
        WHERE u.subject_id IN ($inQuery)
        ORDER BY u.unit_number ASC
    ");
    $stmt->execute($subjectIds);
    $unitsList = $stmt->fetchAll();
    
    foreach ($unitsList as $unit) {
        $units[$unit['subject_id']][] = $unit;
        $statsTotalUnits++;
        if ($unit['pre_quiz_id']) $statsTotalQuizzes++;
        if ($unit['post_quiz_id']) $statsTotalQuizzes++;
    }
    
    // Fetch attempts count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM lms_quiz_attempts a
        JOIN lms_quizzes q ON a.quiz_id = q.id
        JOIN lms_units u ON q.unit_id = u.id
        WHERE u.subject_id IN ($inQuery)
    ");
    $stmt->execute($subjectIds);
    $statsTotalAttempts = (int)$stmt->fetchColumn();
}

$pageTitle = 'จัดการหน่วยเรียนรู้และแบบทดสอบ';
$pageSubtitle = 'สร้างและจัดการแผนการเรียนรู้ แบบทดสอบวัดความรู้ก่อน-หลังเรียน';
$activeSystem = 'lms';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Action Message Toast standard check -->
<?php if ($msg): ?>
<script>
    Swal.fire({
        icon: '<?= $msgType ?>',
        title: '<?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>',
        confirmButtonColor: '#4f46e5'
    });
</script>
<?php endif; ?>

<!-- Stats Overview Section -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-2xl p-6 text-white shadow-xl shadow-indigo-100/50">
        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">รายวิชาทั้งหมด</p>
        <p class="text-4xl font-black mt-2"><?= count($subjects) ?></p>
        <span class="text-xs font-medium opacity-70 mt-1 block">วิชาที่คุณรับผิดชอบ</span>
    </div>
    
    <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl p-6 text-white shadow-xl shadow-blue-100/50">
        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">หน่วยเรียนรู้สะสม</p>
        <p class="text-4xl font-black mt-2"><?= $statsTotalUnits ?></p>
        <span class="text-xs font-medium opacity-70 mt-1 block">หน่วยการเรียนรู้ทั้งหมด</span>
    </div>

    <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl p-6 text-white shadow-xl shadow-emerald-100/50">
        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">แบบทดสอบที่สร้าง</p>
        <p class="text-4xl font-black mt-2"><?= $statsTotalQuizzes ?></p>
        <span class="text-xs font-medium opacity-70 mt-1 block">แบบทดสอบก่อน-หลังเรียน</span>
    </div>

    <div class="bg-gradient-to-br from-pink-500 to-pink-700 rounded-2xl p-6 text-white shadow-xl shadow-pink-100/50">
        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">จำนวนครั้งที่ทำข้อสอบ</p>
        <p class="text-4xl font-black mt-2"><?= $statsTotalAttempts ?></p>
        <span class="text-xs font-medium opacity-70 mt-1 block">จำนวนนักเรียนที่ทำแบบทดสอบ</span>
    </div>
</div>

<!-- Subjects and Units List -->
<div class="space-y-8">
    <?php if (empty($subjects)): ?>
        <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-12 text-center">
            <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="bi bi-journal-x text-3xl"></i>
            </div>
            <h3 class="text-lg font-black text-slate-800">ไม่พบข้อมูลรายวิชา</h3>
            <p class="text-sm text-slate-500 mt-1">คุณไม่มีรายวิชาที่สอนลงทะเบียนในระบบ หรือข้อมูลผู้สอนไม่ได้เชื่อมโยง</p>
        </div>
    <?php else: ?>
        <?php foreach ($subjects as $subj): ?>
            <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
                <!-- Subject Header -->
                <div class="bg-slate-50 px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="px-3 py-1 bg-indigo-50 text-indigo-700 font-black rounded-lg text-sm">
                            <?= htmlspecialchars($subj['subject_code']) ?>
                        </span>
                        <div>
                            <h3 class="text-base sm:text-lg font-black text-slate-800 leading-tight">
                                <?= htmlspecialchars($subj['subject_name']) ?>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                <i class="bi bi-people-fill text-slate-400"></i> ชั้นเรียน: <?= htmlspecialchars($subj['classroom']) ?>
                                <?php if ($_SESSION['llw_role'] === 'super_admin'): ?>
                                    | ผู้สอน: <?= htmlspecialchars($subj['teacher_name'] ?? 'ไม่มีผู้สอน') ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div>
                        <button onclick="openAddUnitModal(<?= $subj['id'] ?>, '<?= htmlspecialchars($subj['subject_name'], ENT_QUOTES) ?>')" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition-all hover:scale-[1.01]">
                            <i class="bi bi-plus-lg"></i> เพิ่มหน่วยการเรียนรู้
                        </button>
                    </div>
                </div>

                <!-- Units inside Subject -->
                <div class="p-6">
                    <?php if (empty($units[$subj['id']])): ?>
                        <div class="text-center py-10 text-slate-400 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                            <i class="bi bi-folder2-open text-3xl"></i>
                            <p class="text-xs font-bold mt-2">ยังไม่มีหน่วยการเรียนรู้ในรายวิชานี้</p>
                            <p class="text-[10px] opacity-75 mt-0.5">กดปุ่ม "เพิ่มหน่วยการเรียนรู้" ด้านขวาบนเพื่อเริ่มต้นสร้างแผนการเรียนรู้</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($units[$subj['id']] as $unit): ?>
                                <div class="bg-slate-50/20 border border-slate-100 rounded-2xl p-5 hover:border-slate-200/80 transition-all">
                                    
                                    <!-- Unit Header Line -->
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="px-2.5 py-0.5 bg-slate-200 text-slate-700 font-bold rounded text-xs">
                                                    หน่วยที่ <?= htmlspecialchars($unit['unit_number']) ?>
                                                </span>
                                                <h4 class="text-sm sm:text-base font-black text-slate-800 leading-tight">
                                                    <?= htmlspecialchars($unit['unit_name']) ?>
                                                </h4>
                                            </div>
                                            <?php if ($unit['description']): ?>
                                                <p class="text-xs text-slate-500 mt-1 pl-1 leading-relaxed">
                                                    <?= htmlspecialchars($unit['description']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex items-center gap-1.5 self-end md:self-auto">
                                            <button onclick="openEditUnitModal(<?= $unit['id'] ?>, <?= $unit['unit_number'] ?>, '<?= htmlspecialchars($unit['unit_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($unit['description'] ?? '', ENT_QUOTES) ?>')" class="w-8 h-8 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 border border-transparent hover:border-indigo-100 flex items-center justify-center transition-all" title="แก้ไขหน่วย">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button onclick="confirmDeleteUnit(<?= $unit['id'] ?>)" class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-100 flex items-center justify-center transition-all" title="ลบหน่วย">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Quizzes Cards Grid -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                        <!-- Pre-Test Quiz Module -->
                                        <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-sm flex flex-col justify-between">
                                            <div>
                                                <div class="flex items-center justify-between mb-3">
                                                    <span class="text-xs font-black text-slate-400 uppercase tracking-wider">
                                                        <i class="bi bi-clipboard2-check text-indigo-500"></i> แบบทดสอบก่อนเรียน
                                                    </span>
                                                    <?php if ($unit['pre_quiz_id']): ?>
                                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full <?= $unit['pre_quiz_active'] ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' ?>">
                                                            <?= $unit['pre_quiz_active'] ? 'เปิดใช้งาน' : 'ปิดการใช้งาน' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($unit['pre_quiz_id']): ?>
                                                    <h5 class="text-xs font-black text-slate-700 leading-tight">
                                                        <?= htmlspecialchars($unit['pre_quiz_title']) ?>
                                                    </h5>
                                                    <div class="grid grid-cols-2 gap-2 mt-3 mb-4 text-[10px] font-bold text-slate-500">
                                                        <span class="flex items-center gap-1"><i class="bi bi-question-circle"></i> ข้อสอบ: <?= $unit['pre_question_count'] ?> ข้อ</span>
                                                        <span class="flex items-center gap-1"><i class="bi bi-clock"></i> เวลา: <?= $unit['pre_time_limit'] > 0 ? $unit['pre_time_limit'] . ' นาที' : 'ไม่จำกัด' ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center py-6 text-slate-400 bg-slate-50/50 rounded-xl mb-4 border border-dashed border-slate-100">
                                                        <span class="text-[10px] font-bold block mb-1.5">ยังไม่ได้สร้างแบบทดสอบก่อนเรียน</span>
                                                        <button onclick="openCreateQuizModal(<?= $unit['id'] ?>, 'pre', 'แบบทดสอบก่อนเรียน หน่วยที่ <?= $unit['unit_number'] ?>')" class="inline-flex items-center gap-1 border border-indigo-200 text-indigo-600 hover:bg-indigo-50/50 text-[10px] font-bold px-3 py-1.5 rounded-lg transition-all">
                                                            <i class="bi bi-plus-lg"></i> สร้างแบบทดสอบ
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($unit['pre_quiz_id']): ?>
                                                <div class="border-t border-slate-50 pt-3 flex flex-wrap gap-1.5 justify-between items-center">
                                                    <label class="inline-flex items-center cursor-pointer">
                                                        <input type="checkbox" onchange="toggleQuizActive(<?= $unit['pre_quiz_id'] ?>, this)" class="sr-only peer" <?= $unit['pre_quiz_active'] ? 'checked' : '' ?>>
                                                        <div class="relative w-7 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-500"></div>
                                                        <span class="ms-1.5 text-[9px] font-bold text-slate-500">เปิดระบบ</span>
                                                    </label>

                                                    <div class="flex items-center gap-1">
                                                        <a href="edit_quiz.php?quiz_id=<?= $unit['pre_quiz_id'] ?>" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-[10px] font-bold px-2 py-1.5 rounded-lg transition-all inline-flex items-center gap-1">
                                                            <i class="bi bi-pencil-square"></i> ข้อสอบ
                                                        </a>
                                                        <button onclick="openQuizSettingsModal(<?= $unit['pre_quiz_id'] ?>, '<?= htmlspecialchars($unit['pre_quiz_title'], ENT_QUOTES) ?>', <?= $unit['pre_time_limit'] ?>, <?= $unit['pre_quiz_active'] ?>)" class="bg-slate-50 hover:bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-1.5 rounded-lg border border-slate-100 transition-all" title="ตั้งค่าเวลา">
                                                            <i class="bi bi-gear-fill"></i>
                                                        </button>
                                                        <a href="quiz_reports.php?quiz_id=<?= $unit['pre_quiz_id'] ?>" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-1.5 rounded-lg transition-all" title="ดูผลคะแนน">
                                                            <i class="bi bi-bar-chart-fill"></i>
                                                        </a>
                                                        <button onclick="confirmDeleteQuiz(<?= $unit['pre_quiz_id'] ?>)" class="bg-rose-50 hover:bg-rose-100 text-rose-600 text-[10px] font-bold px-2 py-1.5 rounded-lg transition-all" title="ลบแบบทดสอบ">
                                                            <i class="bi bi-trash3-fill"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Post-Test Quiz Module -->
                                        <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-sm flex flex-col justify-between">
                                            <div>
                                                <div class="flex items-center justify-between mb-3">
                                                    <span class="text-xs font-black text-slate-400 tracking-wider">
                                                        <i class="bi bi-award text-rose-500"></i> แบบทดสอบหลังเรียน
                                                    </span>
                                                    <?php if ($unit['post_quiz_id']): ?>
                                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full <?= $unit['post_quiz_active'] ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' ?>">
                                                            <?= $unit['post_quiz_active'] ? 'เปิดใช้งาน' : 'ปิดการใช้งาน' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($unit['post_quiz_id']): ?>
                                                    <h5 class="text-xs font-black text-slate-700 leading-tight">
                                                        <?= htmlspecialchars($unit['post_quiz_title']) ?>
                                                    </h5>
                                                    <div class="grid grid-cols-2 gap-2 mt-3 mb-4 text-[10px] font-bold text-slate-500">
                                                        <span class="flex items-center gap-1"><i class="bi bi-question-circle"></i> ข้อสอบ: <?= $unit['post_question_count'] ?> ข้อ</span>
                                                        <span class="flex items-center gap-1"><i class="bi bi-clock"></i> เวลา: <?= $unit['post_time_limit'] > 0 ? $unit['post_time_limit'] . ' นาที' : 'ไม่จำกัด' ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center py-6 text-slate-400 bg-slate-50/50 rounded-xl mb-4 border border-dashed border-slate-100">
                                                        <span class="text-[10px] font-bold block mb-1.5">ยังไม่ได้สร้างแบบทดสอบหลังเรียน</span>
                                                        <button onclick="openCreateQuizModal(<?= $unit['id'] ?>, 'post', 'แบบทดสอบหลังเรียน หน่วยที่ <?= $unit['unit_number'] ?>')" class="inline-flex items-center gap-1 border border-indigo-200 text-indigo-600 hover:bg-indigo-50/50 text-[10px] font-bold px-3 py-1.5 rounded-lg transition-all">
                                                            <i class="bi bi-plus-lg"></i> สร้างแบบทดสอบ
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($unit['post_quiz_id']): ?>
                                                <div class="border-t border-slate-50 pt-3 flex flex-wrap gap-1.5 justify-between items-center">
                                                    <label class="inline-flex items-center cursor-pointer">
                                                        <input type="checkbox" onchange="toggleQuizActive(<?= $unit['post_quiz_id'] ?>, this)" class="sr-only peer" <?= $unit['post_quiz_active'] ? 'checked' : '' ?>>
                                                        <div class="relative w-7 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-500"></div>
                                                        <span class="ms-1.5 text-[9px] font-bold text-slate-500">เปิดระบบ</span>
                                                    </label>

                                                    <div class="flex items-center gap-1">
                                                        <a href="edit_quiz.php?quiz_id=<?= $unit['post_quiz_id'] ?>" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-[10px] font-bold px-2 py-1.5 rounded-lg transition-all inline-flex items-center gap-1">
                                                            <i class="bi bi-pencil-square"></i> ข้อสอบ
                                                        </a>
                                                        <button onclick="openQuizSettingsModal(<?= $unit['post_quiz_id'] ?>, '<?= htmlspecialchars($unit['post_quiz_title'], ENT_QUOTES) ?>', <?= $unit['post_time_limit'] ?>, <?= $unit['post_quiz_active'] ?>)" class="bg-slate-50 hover:bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-1.5 rounded-lg border border-slate-100 transition-all font-bold" title="ตั้งค่าเวลา">
                                                            <i class="bi bi-gear-fill"></i>
                                                        </button>
                                                        <a href="quiz_reports.php?quiz_id=<?= $unit['post_quiz_id'] ?>" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-1.5 rounded-lg transition-all" title="ดูผลคะแนน">
                                                            <i class="bi bi-bar-chart-fill"></i>
                                                        </a>
                                                        <button onclick="confirmDeleteQuiz(<?= $unit['post_quiz_id'] ?>)" class="bg-rose-50 hover:bg-rose-100 text-rose-600 text-[10px] font-bold px-2 py-1.5 rounded-lg transition-all" title="ลบแบบทดสอบ">
                                                            <i class="bi bi-trash3-fill"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
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

<!-- ================= MODALS ================= -->

<!-- Add Unit Modal -->
<div id="addUnitModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl border border-slate-100 overflow-hidden transform transition-all duration-300">
        <div class="px-6 py-5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-black text-slate-800">เพิ่มหน่วยการเรียนรู้</h3>
            <button onclick="closeAddUnitModal()" class="w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full flex items-center justify-center transition-all">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_unit">
            <input type="hidden" id="add_subj_id" name="subject_id">
            <div class="p-6 space-y-4">
                <div class="bg-indigo-50/50 px-4 py-3 rounded-2xl border border-indigo-100/50">
                    <p class="text-[10px] font-black text-indigo-500 uppercase tracking-wider">รายวิชา</p>
                    <p id="add_subj_name" class="text-xs font-bold text-indigo-900 mt-0.5">—</p>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">ลำดับหน่วยเรียน (เลขจำนวนเต็ม เช่น 1, 2, ...)</label>
                    <input type="number" name="unit_number" min="1" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">ชื่อหน่วยการเรียนรู้</label>
                    <input type="text" name="unit_name" required placeholder="เช่น บทนำเคมีพื้นฐาน" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">คำอธิบายรายละเอียด (เพิ่มเติม)</label>
                    <textarea name="description" rows="3" placeholder="รายละเอียดหรือหัวข้อย่อย..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeAddUnitModal()" class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 text-xs font-bold hover:bg-slate-100 transition-all">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition-all">ยืนยัน</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Unit Modal -->
<div id="editUnitModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl border border-slate-100 overflow-hidden transform transition-all duration-300">
        <div class="px-6 py-5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-black text-slate-800">แก้ไขหน่วยการเรียนรู้</h3>
            <button onclick="closeEditUnitModal()" class="w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full flex items-center justify-center transition-all">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_unit">
            <input type="hidden" id="edit_unit_id" name="unit_id">
            <div class="p-6 space-y-4">
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">ลำดับหน่วยเรียน (เลขจำนวนเต็ม)</label>
                    <input type="number" id="edit_unit_number" name="unit_number" min="1" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">ชื่อหน่วยการเรียนรู้</label>
                    <input type="text" id="edit_unit_name" name="unit_name" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">คำอธิบายรายละเอียด</label>
                    <textarea id="edit_unit_description" name="description" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeEditUnitModal()" class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 text-xs font-bold hover:bg-slate-100 transition-all">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition-all">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Quiz Modal -->
<div id="createQuizModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl border border-slate-100 overflow-hidden transform transition-all duration-300">
        <div class="px-6 py-5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-black text-slate-800">สร้างแบบทดสอบ</h3>
            <button onclick="closeCreateQuizModal()" class="w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full flex items-center justify-center transition-all">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_quiz">
            <input type="hidden" id="create_quiz_unit_id" name="unit_id">
            <input type="hidden" id="create_quiz_type" name="quiz_type">
            <div class="p-6 space-y-4">
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">ชื่อแบบทดสอบ</label>
                    <input type="text" id="create_quiz_title" name="title" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">เวลาในการทำข้อสอบ (นาที, 0 คือไม่จำกัดเวลา)</label>
                    <input type="number" name="time_limit" min="0" value="30" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeCreateQuizModal()" class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 text-xs font-bold hover:bg-slate-100 transition-all">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition-all">สร้าง</button>
            </div>
        </form>
    </div>
</div>

<!-- Quiz Settings Modal -->
<div id="quizSettingsModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl border border-slate-100 overflow-hidden transform transition-all duration-300">
        <div class="px-6 py-5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-black text-slate-800">ตั้งค่าแบบทดสอบ</h3>
            <button onclick="closeQuizSettingsModal()" class="w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full flex items-center justify-center transition-all">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_quiz_settings">
            <input type="hidden" id="setting_quiz_id" name="quiz_id">
            <div class="p-6 space-y-4">
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">ชื่อแบบทดสอบ</label>
                    <input type="text" id="setting_quiz_title" name="title" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">เวลาเรียนทำข้อสอบ (นาที, 0 คือไม่จำกัดเวลา)</label>
                    <input type="number" id="setting_quiz_time_limit" name="time_limit" min="0" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div class="flex items-center gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <input type="checkbox" id="setting_quiz_active" name="is_active" class="w-4 h-4 text-indigo-600 bg-slate-100 border-slate-300 rounded focus:ring-indigo-500 focus:ring-2">
                    <label for="setting_quiz_active" class="text-xs font-bold text-slate-700">เปิดให้นักเรียนเข้าทำแบบทดสอบ (Active)</label>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeQuizSettingsModal()" class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 text-xs font-bold hover:bg-slate-100 transition-all">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition-all">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Forms for Deletes -->
<form id="hiddenDeleteUnitForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete_unit">
    <input type="hidden" id="delete_unit_target_id" name="unit_id">
</form>

<form id="hiddenDeleteQuizForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete_quiz">
    <input type="hidden" id="delete_quiz_target_id" name="quiz_id">
</form>

<!-- JS Controller -->
<script>
    // Unit Actions
    function openAddUnitModal(subjId, subjName) {
        document.getElementById('add_subj_id').value = subjId;
        document.getElementById('add_subj_name').textContent = subjName;
        document.getElementById('addUnitModal').classList.remove('hidden');
    }
    
    function closeAddUnitModal() {
        document.getElementById('addUnitModal').classList.add('hidden');
    }
    
    function openEditUnitModal(unitId, unitNum, unitName, desc) {
        document.getElementById('edit_unit_id').value = unitId;
        document.getElementById('edit_unit_number').value = unitNum;
        document.getElementById('edit_unit_name').value = unitName;
        document.getElementById('edit_unit_description').value = desc;
        document.getElementById('editUnitModal').classList.remove('hidden');
    }
    
    function closeEditUnitModal() {
        document.getElementById('editUnitModal').classList.add('hidden');
    }
    
    function confirmDeleteUnit(unitId) {
        Swal.fire({
            title: 'ต้องการลบหน่วยการเรียนรู้?',
            text: 'การทำเช่นนี้จะลบแบบทดสอบและคำถามทั้งหมดที่เชื่อมโยงกับหน่วยนี้ รวมถึงประวัติคะแนนสอบของนักเรียนด้วย!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ใช่, ฉันต้องการลบ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete_unit_target_id').value = unitId;
                document.getElementById('hiddenDeleteUnitForm').submit();
            }
        });
    }

    // Quiz Actions
    function openCreateQuizModal(unitId, quizType, defaultTitle) {
        document.getElementById('create_quiz_unit_id').value = unitId;
        document.getElementById('create_quiz_type').value = quizType;
        document.getElementById('create_quiz_title').value = defaultTitle;
        document.getElementById('createQuizModal').classList.remove('hidden');
    }
    
    function closeCreateQuizModal() {
        document.getElementById('createQuizModal').classList.add('hidden');
    }

    function openQuizSettingsModal(quizId, title, timeLimit, isActive) {
        document.getElementById('setting_quiz_id').value = quizId;
        document.getElementById('setting_quiz_title').value = title;
        document.getElementById('setting_quiz_time_limit').value = timeLimit;
        document.getElementById('setting_quiz_active').checked = isActive === 1;
        document.getElementById('quizSettingsModal').classList.remove('hidden');
    }
    
    function closeQuizSettingsModal() {
        document.getElementById('quizSettingsModal').classList.add('hidden');
    }

    function toggleQuizActive(quizId, checkbox) {
        const isActive = checkbox.checked ? 1 : 0;
        const formData = new FormData();
        formData.append('action', 'toggle_quiz');
        formData.append('quiz_id', quizId);
        formData.append('is_active', isActive);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                checkbox.checked = !checkbox.checked;
                Swal.fire({ icon: 'error', title: 'ไม่สามารถเปลี่ยนสถานะได้' });
            }
        })
        .catch(err => {
            checkbox.checked = !checkbox.checked;
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ' });
        });
    }

    function confirmDeleteQuiz(quizId) {
        Swal.fire({
            title: 'ต้องการลบแบบทดสอบ?',
            text: 'คำถาม ตัวเลือก และคะแนนของนักเรียนทั้งหมดในแบบทดสอบนี้จะถูกลบถาวร!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ยืนยัน, ต้องการลบ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete_quiz_target_id').value = quizId;
                document.getElementById('hiddenDeleteQuizForm').submit();
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
