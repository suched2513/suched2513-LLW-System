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

$msg = '';
$msgType = 'success';

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_question') {
            $question_text = trim($_POST['question_text']);
            $points = (int)($_POST['points'] ?? 1);
            $choices = $_POST['choices'] ?? [];
            $correct_index = (int)($_POST['correct_index'] ?? 0); // 0 to 3
            
            if (!$question_text || count($choices) < 4) {
                throw new Exception('กรุณากรอกระบุคำถามและตัวเลือกให้ครบถ้วน');
            }
            
            $pdo->beginTransaction();
            
            // Insert Question
            $stmt = $pdo->prepare("INSERT INTO lms_questions (quiz_id, question_text, points) VALUES (?, ?, ?)");
            $stmt->execute([$quiz_id, $question_text, $points]);
            $question_id = $pdo->lastInsertId();
            
            // Insert Choices
            for ($i = 0; $i < 4; $i++) {
                $choice_text = trim($choices[$i]);
                $is_correct = ($i === $correct_index) ? 1 : 0;
                
                $stmt = $pdo->prepare("INSERT INTO lms_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                $stmt->execute([$question_id, $choice_text, $is_correct]);
            }
            
            $pdo->commit();
            $msg = 'เพิ่มคำถามสำเร็จ';
            
        } elseif ($action === 'edit_question') {
            $question_id = (int)$_POST['question_id'];
            $question_text = trim($_POST['question_text']);
            $points = (int)($_POST['points'] ?? 1);
            $choices = $_POST['choices'] ?? [];
            $correct_index = (int)($_POST['correct_index'] ?? 0);
            
            if (!$question_id || !$question_text || count($choices) < 4) {
                throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
            }
            
            $pdo->beginTransaction();
            
            // Update Question
            $stmt = $pdo->prepare("UPDATE lms_questions SET question_text = ?, points = ? WHERE id = ? AND quiz_id = ?");
            $stmt->execute([$question_text, $points, $question_id, $quiz_id]);
            
            // Get choice IDs for this question to update in-place
            $c_stmt = $pdo->prepare("SELECT id FROM lms_choices WHERE question_id = ? ORDER BY id");
            $c_stmt->execute([$question_id]);
            $choice_records = $c_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($choice_records) >= 4) {
                for ($i = 0; $i < 4; $i++) {
                    $choice_id = $choice_records[$i];
                    $choice_text = trim($choices[$i]);
                    $is_correct = ($i === $correct_index) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("UPDATE lms_choices SET choice_text = ?, is_correct = ? WHERE id = ?");
                    $stmt->execute([$choice_text, $is_correct, $choice_id]);
                }
            } else {
                // If structure mismatch exists, recreate them
                $stmt = $pdo->prepare("DELETE FROM lms_choices WHERE question_id = ?");
                $stmt->execute([$question_id]);
                for ($i = 0; $i < 4; $i++) {
                    $choice_text = trim($choices[$i]);
                    $is_correct = ($i === $correct_index) ? 1 : 0;
                    $stmt = $pdo->prepare("INSERT INTO lms_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$question_id, $choice_text, $is_correct]);
                }
            }
            
            $pdo->commit();
            $msg = 'แก้ไขคำถามสำเร็จ';
            
        } elseif ($action === 'delete_question') {
            $question_id = (int)$_POST['question_id'];
            if (!$question_id) throw new Exception('ไม่พบรหัสคำถาม');
            
            $stmt = $pdo->prepare("DELETE FROM lms_questions WHERE id = ? AND quiz_id = ?");
            $stmt->execute([$question_id, $quiz_id]);
            $msg = 'ลบคำถามตัวเลือกสำเร็จ';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = $e->getMessage();
        $msgType = 'error';
    }
}

// Fetch all questions and choices
$stmt = $pdo->prepare("
    SELECT * FROM lms_questions 
    WHERE quiz_id = ? 
    ORDER BY id ASC
");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

$questions_data = [];
foreach ($questions as $q) {
    $c_stmt = $pdo->prepare("SELECT * FROM lms_choices WHERE question_id = ? ORDER BY id ASC");
    $c_stmt->execute([$q['id']]);
    $choices = $c_stmt->fetchAll();
    
    $correct_idx = 0;
    foreach ($choices as $idx => $choice) {
        if ($choice['is_correct']) {
            $correct_idx = $idx;
        }
    }
    
    $q['choices'] = $choices;
    $q['correct_index'] = $correct_idx;
    $questions_data[] = $q;
}

$pageTitle = 'จัดการคลังข้อสอบ';
$pageSubtitle = htmlspecialchars($quiz['title']) . ' (' . ($quiz['quiz_type'] === 'pre' ? 'ก่อนเรียน' : 'หลังเรียน') . ')';
$activeSystem = 'lms';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Alert/Success Toast -->
<?php if ($msg): ?>
<script>
    Swal.fire({
        icon: '<?= $msgType ?>',
        title: '<?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>',
        confirmButtonColor: '#4f46e5'
    });
</script>
<?php endif; ?>

<!-- Navigation & Context Header -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <a href="manage_units.php" class="inline-flex items-center justify-center w-10 h-10 bg-white hover:bg-slate-50 text-slate-600 rounded-xl border border-slate-200 shadow-sm transition-all">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-sm font-semibold text-slate-500">
                <?= htmlspecialchars($quiz['subject_code']) ?> • <?= htmlspecialchars($quiz['subject_name']) ?> (<?= htmlspecialchars($quiz['classroom']) ?>)
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">หน่วยการเรียนรู้ที่ <?= $quiz['unit_number'] ?>: <?= htmlspecialchars($quiz['unit_name']) ?></p>
        </div>
    </div>
    
    <button onclick="openAddQuestionModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition-all hover:scale-[1.01]">
        <i class="bi bi-plus-lg"></i> เพิ่มคำถามข้อสอบ
    </button>
</div>

<!-- Questions List -->
<div class="space-y-6">
    <?php if (empty($questions_data)): ?>
        <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-12 text-center">
            <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="bi bi-file-earmark-plus text-3xl"></i>
            </div>
            <h3 class="text-lg font-black text-slate-800">ไม่มีคำถามในคลัง</h3>
            <p class="text-sm text-slate-500 mt-1">แบบทดสอบนี้ยังไม่มีคำถามสอบเพิ่มลงระบบ</p>
            <button onclick="openAddQuestionModal()" class="mt-4 inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all">
                <i class="bi bi-plus-lg"></i> เริ่มสร้างข้อแรก
            </button>
        </div>
    <?php else: ?>
        <?php foreach ($questions_data as $index => $q): ?>
            <div class="bg-white rounded-2xl shadow-md border border-slate-100 overflow-hidden hover:shadow-lg transition-all">
                <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100 flex items-center justify-between">
                    <span class="inline-flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-black">
                            <?= $index + 1 ?>
                        </span>
                        <span class="text-xs font-black text-slate-500">ข้อสอบ (<?= $q['points'] ?> คะแนน)</span>
                    </span>
                    
                    <div class="flex items-center gap-1">
                        <button onclick='openEditQuestionModal(<?= json_encode($q, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="w-8 h-8 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 border border-transparent hover:border-indigo-100 flex items-center justify-center transition-all" title="แก้ไข">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button onclick="confirmDeleteQuestion(<?= $q['id'] ?>)" class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-100 flex items-center justify-center transition-all" title="ลบ">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6 space-y-4">
                    <h4 class="text-slate-800 text-sm sm:text-base font-bold whitespace-pre-line leading-relaxed">
                        <?= htmlspecialchars($q['question_text']) ?>
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pl-3">
                        <?php foreach ($q['choices'] as $idx => $choice): ?>
                            <div class="flex items-start gap-2.5 p-3 rounded-xl border <?= $choice['is_correct'] ? 'bg-emerald-50/30 border-emerald-250 text-emerald-900 font-bold' : 'border-slate-100/80 text-slate-600' ?>">
                                <span class="w-5 h-5 rounded bg-slate-100 border border-slate-200 text-[10px] font-black text-slate-500 flex items-center justify-center flex-shrink-0 mt-0.5 <?= $choice['is_correct'] ? 'bg-emerald-100 border-emerald-300 text-emerald-800' : '' ?>">
                                    <?= chr(65 + $idx) ?>
                                </span>
                                <div class="flex-1 text-xs sm:text-sm">
                                    <?= htmlspecialchars($choice['choice_text']) ?>
                                    <?php if ($choice['is_correct']): ?>
                                        <i class="bi bi-check-circle-fill text-emerald-600 ml-1"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ================= MODALS ================= -->

<!-- Add/Edit Question Modal Container -->
<div id="questionModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-2xl shadow-2xl border border-slate-100 overflow-hidden transform transition-all duration-300">
        <div class="px-6 py-5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <h3 id="modalTitle" class="text-base font-black text-slate-800">สร้างคำถามข้อสอบ</h3>
            <button onclick="closeQuestionModal()" class="w-8 h-8 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full flex items-center justify-center transition-all">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form id="questionForm" method="POST">
            <input type="hidden" id="actionField" name="action" value="add_question">
            <input type="hidden" id="questionIdField" name="question_id">
            
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto no-scrollbar">
                <!-- Question Text -->
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">คำถาม /โจทย์ปัญหา</label>
                    <textarea name="question_text" id="form_question_text" rows="3" required placeholder="พิมพ์ข้อความคำถามที่นี่..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all"></textarea>
                </div>
                
                <!-- Score Points -->
                <div>
                    <label class="text-xs font-bold text-slate-600 block mb-1">คะแนนข้อสอบ</label>
                    <input type="number" name="points" id="form_points" min="1" value="1" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                
                <!-- Choices Section -->
                <div class="space-y-3">
                    <label class="text-xs font-bold text-slate-600 block mb-1">ตัวเลือกคำตอบ (Choices) และเลือกคำตอบที่ถูกต้อง</label>
                    
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-slate-150 border border-slate-200 rounded-xl text-xs font-black text-slate-700">
                                <?= chr(65 + $i) ?>
                            </div>
                            <input type="text" name="choices[]" id="form_choice_<?= $i ?>" required placeholder="พิมพ์ตัเลือกข้อมูลคำตอบที่ <?= $i + 1 ?>" class="flex-1 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                            
                            <label class="flex items-center justify-center p-3 border border-slate-200 rounded-2xl cursor-pointer hover:bg-slate-50 transition-all select-none">
                                <input type="radio" name="correct_index" value="<?= $i ?>" id="form_correct_<?= $i ?>" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500 border-slate-300" <?= $i === 0 ? 'checked' : '' ?>>
                                <span class="text-[10px] font-bold text-slate-600 ml-1.5 hidden sm:inline">ถูกต้อง</span>
                            </label>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeQuestionModal()" class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 text-xs font-bold hover:bg-slate-100 transition-all">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition-all">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="hiddenDeleteQuestionForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete_question">
    <input type="hidden" id="delete_question_id_field" name="question_id">
</form>

<!-- JS Controller -->
<script>
    function openAddQuestionModal() {
        document.getElementById('modalTitle').textContent = 'เพิ่มคำถามใหม่';
        document.getElementById('actionField').value = 'add_question';
        document.getElementById('questionIdField').value = '';
        document.getElementById('form_question_text').value = '';
        document.getElementById('form_points').value = '1';
        
        for (let i = 0; i < 4; i++) {
            document.getElementById('form_choice_' + i).value = '';
            document.getElementById('form_correct_' + i).checked = (i === 0);
        }
        
        document.getElementById('questionModal').classList.remove('hidden');
    }

    function openEditQuestionModal(qData) {
        document.getElementById('modalTitle').textContent = 'แก้ไขคำถามข้อนี้';
        document.getElementById('actionField').value = 'edit_question';
        document.getElementById('questionIdField').value = qData.id;
        document.getElementById('form_question_text').value = qData.question_text;
        document.getElementById('form_points').value = qData.points;
        
        for (let i = 0; i < 4; i++) {
            const choice = qData.choices[i];
            document.getElementById('form_choice_' + i).value = choice ? choice.choice_text : '';
            document.getElementById('form_correct_' + i).checked = (i === qData.correct_index);
        }
        
        document.getElementById('questionModal').classList.remove('hidden');
    }
    
    function closeQuestionModal() {
        document.getElementById('questionModal').classList.add('hidden');
    }
    
    function confirmDeleteQuestion(questionId) {
        Swal.fire({
            title: 'ต้องการลบคำถามข้อนี้?',
            text: 'การทำเช่นนี้ไม่สามารถเรียกคืนได้ถาวร',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ลบ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete_question_id_field').value = questionId;
                document.getElementById('hiddenDeleteQuestionForm').submit();
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
