<?php
require_once 'functions.php';
checkAdmin();


$pageTitle = 'จัดการระบบ (Admin)';
$pageSubtitle = 'จัดการรายชื่อครู รายวิชา และข้อมูลพื้นฐาน';

$msg = ''; $msgType = 'success';
$action = $_POST['action'] ?? '';

if ($action) csrf_verify();

// --- CRUD: TEACHERS ---
if ($action === 'add_teacher') {
    $name = trim($_POST['name']); $username = trim($_POST['username']); $password = $_POST['password'];
    if (!$name || !$username || !$password) { $msg = 'กรุณากรอกข้อมูลให้ครบถ้วน'; $msgType = 'error'; } 
    else {
        $chk = $pdo->prepare("SELECT user_id FROM llw_users WHERE username=?");
        $chk->execute([$username]);
        if ($chk->fetch()) { $msg = "Username '$username' ถูกใช้งานแล้ว"; $msgType = 'error'; } 
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->beginTransaction();
            try {
                $u = $pdo->prepare("INSERT INTO llw_users (username,password,firstname,lastname,role,status) VALUES (?,?,?,?,'att_teacher','active')");
                $parts = explode(' ', $name, 2);
                $u->execute([$username, $hash, $parts[0], $parts[1] ?? '']);
                $uid = $pdo->lastInsertId();
                $t = $pdo->prepare("INSERT INTO att_teachers (name,username,password,llw_user_id) VALUES (?,?,?,?)");
                $t->execute([$name, $username, $hash, $uid]);
                $pdo->commit(); $msg = "เพิ่มครู '$name' สำเร็จ";
            } catch (Exception $e) { $pdo->rollBack(); $msg = 'เกิดข้อผิดพลาด: ' . $e->getMessage(); $msgType = 'error'; }
        }
    }
} elseif ($action === 'delete_teacher') {
    $tid = (int)$_POST['teacher_id'];
    if ($tid) {
        $link = $pdo->prepare("SELECT llw_user_id FROM att_teachers WHERE id=?");
        $link->execute([$tid]);
        $uid = $link->fetchColumn();
        $pdo->prepare("DELETE FROM att_subjects WHERE teacher_id=?")->execute([$tid]);
        $pdo->prepare("DELETE FROM att_teachers WHERE id=?")->execute([$tid]);
        if ($uid) $pdo->prepare("DELETE FROM llw_users WHERE user_id=?")->execute([$uid]);
        $msg = 'ลบข้อมูลสำเร็จ';
    }
}

// --- CRUD: STUDENTS ---
if ($action === 'add_student') {
    $sname = trim($_POST['student_name']);
    $sid   = trim($_POST['student_id_code']);
    // Standardize to 5 digits for consistency
    if (preg_match('/^\d+$/', $sid)) $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
    
    $cls   = trim($_POST['student_classroom']);
    if (!$sname || !$sid || !$cls) { $msg = 'กรุณากรอกข้อมูลให้ครบถ้วน'; $msgType = 'error'; }
    else {
        $chk = $pdo->prepare("SELECT id FROM att_students WHERE student_id=? AND classroom=?");
        $chk->execute([$sid, $cls]);
        if ($chk->fetch()) { $msg = "รหัส '$sid' มีอยู่ในห้องนี้แล้ว"; $msgType = 'error'; }
        else {
            $pdo->prepare("INSERT INTO att_students (student_id, name, classroom) VALUES (?,?,?)")->execute([$sid, $sname, $cls]);
            $msg = "เพิ่มนักเรียน '$sname' สำเร็จ";
        }
    }
} elseif ($action === 'edit_student') {
    $eid   = (int)$_POST['edit_id'];
    $sname = trim($_POST['student_name']);
    $sid   = trim($_POST['student_id_code']);
    // Standardize to 5 digits for consistency
    if (preg_match('/^\d+$/', $sid)) $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
    
    $cls   = trim($_POST['student_classroom']);
    if ($eid && $sname && $sid && $cls) {
        $pdo->prepare("UPDATE att_students SET name=?, student_id=?, classroom=? WHERE id=?")->execute([$sname, $sid, $cls, $eid]);
        $msg = 'แก้ไขนักเรียนสำเร็จ';
    }
} elseif ($action === 'delete_student') {
    $eid = (int)$_POST['student_db_id'];
    if ($eid) {
        $pdo->prepare("DELETE FROM att_students WHERE id=?")->execute([$eid]);
        $msg = 'ลบนักเรียนสำเร็จ';
    }
}

// --- CRUD: SUBJECTS ---
if ($action === 'add_subject') {
    $tid = (int)$_POST['teacher_id']; $code = trim($_POST['subject_code']); $name = trim($_POST['subject_name']); $cls = trim($_POST['classroom']);
    $is_elective = isset($_POST['is_elective']) ? 1 : 0;
    if (!$tid || !$code || !$name || !$cls) { $msg = 'ข้อมูลไม่ครบ'; $msgType = 'error'; } 
    else {
        try {
            $pdo->prepare("INSERT INTO att_subjects (subject_code,subject_name,classroom,teacher_id,is_elective) VALUES (?,?,?,?,?)")->execute([$code, $name, $cls, $tid, $is_elective]);
        } catch (PDOException $e) {
            // Fallback: column is_elective ยังไม่มี (ยังไม่ได้ run migration)
            $pdo->prepare("INSERT INTO att_subjects (subject_code,subject_name,classroom,teacher_id) VALUES (?,?,?,?)")->execute([$code, $name, $cls, $tid]);
        }
        $msg = "เพิ่มวิชาสำเร็จ" . ($is_elective ? ' (วิชาเลือก — กรุณา run migration ก่อน)' : '');
    }
} elseif ($action === 'delete_subject') {
    $sid = (int)$_POST['subject_id'];
    if ($sid) { $pdo->prepare("DELETE FROM att_subjects WHERE id=?")->execute([$sid]); $msg = 'ลบวิชาสำเร็จ'; }

// --- CRUD: ENROLLMENT (วิชาเลือก) ---
} elseif ($action === 'save_enrollment') {
    $sid = (int)$_POST['subject_id'];
    $enrolled = array_map('intval', $_POST['enrolled_students'] ?? []);
    if ($sid) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM att_subject_students WHERE subject_id=?")->execute([$sid]);
            if (!empty($enrolled)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO att_subject_students (subject_id, student_id) VALUES (?,?)");
                foreach ($enrolled as $std_id) {
                    if ($std_id > 0) $ins->execute([$sid, $std_id]);
                }
            }
            $pdo->commit();
            $msg = 'บันทึกการลงทะเบียนวิชาเลือกสำเร็จ';
        } catch (Exception $e) {
            $pdo->rollBack(); $msg = 'เกิดข้อผิดพลาด'; $msgType = 'error';
        }
    }
}

// Data Fetching (Safe Mode)
$teachers   = [];
$subjects   = [];
$classrooms = [];
$all_students = [];
$filter_cls = $_GET['cls'] ?? '';

try {
    $teachers   = $pdo->query("SELECT t.*, lu.username, lu.status as user_status, lu.last_login, COUNT(DISTINCT s.id) as s_count FROM att_teachers t LEFT JOIN llw_users lu ON lu.user_id = t.llw_user_id LEFT JOIN att_subjects s ON s.teacher_id = t.id GROUP BY t.id ORDER BY t.name")->fetchAll();
    $subjects   = $pdo->query("SELECT s.*, t.name as t_name FROM att_subjects s JOIN att_teachers t ON t.id = s.teacher_id ORDER BY s.subject_code")->fetchAll();
    $classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

    if (!$filter_cls) $filter_cls = $classrooms[0] ?? '';

    // Students (filter by classroom if selected)
    if ($filter_cls) {
        $sq = $pdo->prepare("SELECT * FROM att_students WHERE classroom=? ORDER BY student_id");
        $sq->execute([$filter_cls]);
        $all_students = $sq->fetchAll();
    } else {
        $all_students = $pdo->query("SELECT * FROM att_students ORDER BY classroom, student_id LIMIT 100")->fetchAll();
    }
} catch (Exception $e) {
    $msg = "ระบบยังไม่พร้อมใช้งาน: กรุณารัน Migration ตาราง Attendance (" . $e->getMessage() . ")";
    $msgType = 'error';
}

// --- ส่วน Elective (ต้องการ migration ก่อน) ---
$migration_ready    = false;
$elective_subjects  = [];
$enrollments        = [];
$all_students_by_class = [];
try {
    // ทดสอบว่า column is_elective มีอยู่แล้วไหม
    $check_col = $pdo->query("SHOW COLUMNS FROM att_subjects LIKE 'is_elective'")->fetch();
    if ($check_col) {
        $migration_ready = true;
        $elective_subjects = array_filter($subjects, function($s) {
            return !empty($s['is_elective']);
        });

        // Enrollment per elective subject
        foreach ($elective_subjects as $es) {
            $e = $pdo->prepare("SELECT student_id FROM att_subject_students WHERE subject_id=?");
            $e->execute([$es['id']]);
            $enrollments[$es['id']] = $e->fetchAll(PDO::FETCH_COLUMN);
        }

        // นักเรียนทุกคน จัดกลุ่มตามห้อง
        $all_rows = $pdo->query("SELECT * FROM att_students ORDER BY classroom, student_id")->fetchAll();
        foreach ($all_rows as $row) {
            $all_students_by_class[$row['classroom']][] = $row;
        }
    } else {
        $migration_ready = false;
    }
} catch (Exception $e) {
    $migration_ready = false;
}


require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-8">
    
    <!-- ── Tab Navigation ── -->
    <div class="flex p-1 bg-slate-200/50 rounded-2xl w-fit no-print flex-wrap gap-1">
        <button onclick="showTab('teachers')" id="tab-teachers" class="tab-btn active px-6 py-2.5 rounded-xl text-sm font-bold transition-all">
            <i class="bi bi-people-fill mr-2"></i> จัดการครู
        </button>
        <button onclick="showTab('students')" id="tab-students" class="tab-btn px-6 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-700 transition-all">
            <i class="bi bi-mortarboard-fill mr-2"></i> จัดการนักเรียน
        </button>
        <button onclick="showTab('subjects')" id="tab-subjects" class="tab-btn px-6 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-700 transition-all">
            <i class="bi bi-book-half mr-2"></i> จัดการวิชา
        </button>
        <button onclick="showTab('enrollment')" id="tab-enrollment" class="tab-btn px-6 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-700 transition-all">
            <i class="bi bi-person-check-fill mr-2"></i> ลงทะเบียนวิชาเลือก
            <?php if (!empty($elective_subjects)): ?><span class="ml-1 px-1.5 py-0.5 bg-violet-100 text-violet-600 text-[9px] font-black rounded-lg"><?= count($elective_subjects) ?></span><?php endif; ?>
        </button>
    </div>

    <!-- ══ TEACHERS TAB ══ -->
    <div id="pane-teachers" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Form Add Teacher -->
        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 flex flex-col gap-6 h-fit">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl"><i class="bi bi-person-plus-fill"></i></div>
                <h3 class="font-bold text-slate-800">เพิ่มครูเข้าในระบบ</h3>
            </div>
            <form method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_teacher">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ชื่อ-สกุล</label>
                    <input type="text" name="name" required placeholder="นายสมชาย ใจดี" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Username</label>
                    <input type="text" name="username" required placeholder="teacher01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition mt-2">ยืนยันเพิ่มครู</button>
            </form>
        </div>

        <!-- Teacher List Table -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-50 flex items-center justify-between">
                <h3 class="font-bold text-slate-800">รายชื่อครูทั้งหมด (<?= count($teachers) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50 text-sm">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-6 py-4 text-left">ครูผู้สอน</th>
                            <th class="px-6 py-4 text-left">Username</th>
                            <th class="px-6 py-4 text-center">วิชา</th>
                            <th class="px-6 py-4 text-center">สถานะ</th>
                            <th class="px-6 py-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($teachers as $t): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs"><?= mb_substr($t['name'],0,1) ?></div>
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($t['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-blue-600"><?= $t['username'] ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 font-bold text-[10px]"><?= $t['s_count'] ?> วิชา</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold <?= $t['user_status']==='active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
                                    <?= strtoupper($t['user_status'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="deleteTeacher(<?= $t['id'] ?>, '<?= addslashes($t['name']) ?>')" class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition"><i class="bi bi-trash3-fill"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══ STUDENTS TAB ══ -->
    <div id="pane-students" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Add Student Form -->
        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 flex flex-col gap-5 h-fit">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl"><i class="bi bi-person-plus-fill"></i></div>
                <h3 class="font-bold text-slate-800">เพิ่มนักเรียน</h3>
            </div>
            <form method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_student">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">รหัสนักเรียน</label>
                    <input type="text" name="student_id_code" required placeholder="เช่น 4100, 4102" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-emerald-400 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ชื่อ-สกุล</label>
                    <input type="text" name="student_name" required placeholder="นายสมชาย ใจดี" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ห้องเรียน</label>
                    <input type="text" name="student_classroom" required placeholder="เช่น ม.4/1"
                           value="<?= htmlspecialchars($filter_cls) ?>"
                           list="cls-list" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
                    <datalist id="cls-list">
                        <?php foreach ($classrooms as $cls): ?>
                        <option value="<?= htmlspecialchars($cls) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <button type="submit" class="w-full bg-emerald-600 text-white py-3 rounded-xl font-bold shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition mt-2">ยืนยันเพิ่มนักเรียน</button>
            </form>
            <div class="border-t border-slate-100 pt-4">
                <a href="import_students.php" class="flex items-center gap-2 text-sm font-bold text-blue-600 hover:text-blue-700 transition">
                    <i class="bi bi-file-earmark-spreadsheet-fill"></i> นำเข้าจาก CSV (หลายคนพร้อมกัน)
                </a>
            </div>
        </div>

        <!-- Student List -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h3 class="font-bold text-slate-800">รายชื่อนักเรียน (<?= count($all_students) ?>)</h3>
                </div>
                <!-- Classroom filter -->
                <form method="GET" class="flex items-center gap-2">
                    <select name="cls" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-emerald-400 outline-none">
                        <option value="">-- ทุกห้อง --</option>
                        <?php foreach ($classrooms as $cls): ?>
                        <option value="<?= htmlspecialchars($cls) ?>" <?= $filter_cls === $cls ? 'selected' : '' ?>><?= htmlspecialchars($cls) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50 text-sm">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-6 py-4 text-left">รหัส</th>
                            <th class="px-6 py-4 text-left">ชื่อ-สกุล</th>
                            <th class="px-6 py-4 text-center">ห้อง</th>
                            <th class="px-6 py-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($all_students as $std): ?>
                        <tr class="hover:bg-slate-50 transition" id="row-<?= $std['id'] ?>">
                            <td class="px-6 py-3.5 font-mono font-bold text-blue-600 text-xs"><?= htmlspecialchars($std['student_id']) ?></td>
                            <td class="px-6 py-3.5 font-bold text-slate-700"><?= htmlspecialchars($std['name']) ?></td>
                            <td class="px-6 py-3.5 text-center">
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-[10px]"><?= $std['classroom'] ?></span>
                            </td>
                            <td class="px-6 py-3.5 text-right flex gap-1 justify-end">
                                <button onclick="editStudent(<?= $std['id'] ?>, '<?= addslashes($std['student_id']) ?>', '<?= addslashes($std['name']) ?>', '<?= addslashes($std['classroom']) ?>')"
                                        class="p-2 text-blue-500 hover:bg-blue-50 rounded-xl transition" title="แก้ไข"><i class="bi bi-pencil-fill"></i></button>
                                <button onclick="deleteStudent(<?= $std['id'] ?>, '<?= addslashes($std['name']) ?>')"
                                        class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition" title="ลบ"><i class="bi bi-trash3-fill"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($all_students)): ?>
                        <tr><td colspan="4" class="px-6 py-10 text-center text-slate-300 font-bold">ยังไม่มีนักเรียน<?= $filter_cls ? " ในห้อง $filter_cls" : '' ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══ SUBJECTS TAB ══ -->
    <div id="pane-subjects" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add Subject Form -->
        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 flex flex-col gap-6 h-fit">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl"><i class="bi bi-journal-plus"></i></div>
                <h3 class="font-bold text-slate-800">เพิ่มรายวิชา</h3>
            </div>
            <form method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_subject">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ครูผู้สอน</label>
                    <select name="teacher_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                        <option value="">-- เลือกครู --</option>
                        <?php foreach($teachers as $t): ?> <option value="<?= $t['id'] ?>"><?= $t['name'] ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                   <div>
                       <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">รหัสวิชา</label>
                       <input type="text" name="subject_code" required placeholder="ท31101" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-mono outline-none">
                   </div>
                   <div>
                       <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ห้อง/ชั้น</label>
                       <input type="text" name="classroom" required placeholder="ม.4/1" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs outline-none">
                   </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ชื่อรายวิชา</label>
                    <input type="text" name="subject_name" required placeholder="ภาษาไทย" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold outline-none">
                </div>
                <div class="flex items-center gap-3 p-4 bg-violet-50 border border-violet-200 rounded-xl">
                    <input type="checkbox" name="is_elective" id="is_elective" value="1" class="w-4 h-4 accent-violet-600">
                    <div>
                        <label for="is_elective" class="font-bold text-violet-700 text-sm cursor-pointer">วิชาเลือก (Elective)</label>
                        <p class="text-[10px] text-violet-500 mt-0.5">นักเรียนแต่ละคนเลือกวิชาไม่เหมือนกัน ต้องกำหนดนักเรียนในแท็บลงทะเบียนด้วย</p>
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold shadow-xl shadow-blue-100 hover:bg-blue-700 transition mt-2">ยืนยันเพิ่มวิชา</button>
            </form>
        </div>

        <!-- Subject List Table -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
             <div class="px-8 py-5 border-b border-slate-50">
                <h3 class="font-bold text-slate-800">รายวิชาทั้งหมด (<?= count($subjects) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50 text-sm">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-6 py-4 text-left">รหัส / วิชา</th>
                            <th class="px-6 py-4 text-left">ครูผู้รับผิดชอบ</th>
                            <th class="px-6 py-4 text-center">ห้องเรียน</th>
                            <th class="px-6 py-4 text-center">ประเภท</th>
                            <th class="px-6 py-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($subjects as $s): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="text-xs font-mono text-blue-600 font-bold"><?= $s['subject_code'] ?></span>
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($s['subject_name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-600"><?= $s['t_name'] ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-[10px]"><?= $s['classroom'] ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if (!$migration_ready): ?>
                                <span class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-600 font-bold text-[10px]"><i class="bi bi-database-exclamation"></i> รอ migration</span>
                                <?php elseif (!empty($s['is_elective'])): ?>
                                <span class="px-2.5 py-1 rounded-lg bg-violet-100 text-violet-700 font-black text-[10px]"><i class="bi bi-star-fill"></i> วิชาเลือก</span>
                                <?php else: ?>
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-500 font-bold text-[10px]">บังคับ</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="deleteSubject(<?= $s['id'] ?>, '<?= addslashes($s['subject_name']) ?>')" class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition"><i class="bi bi-trash3"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══ ENROLLMENT TAB ══ -->
    <div id="pane-enrollment" class="hidden flex flex-col gap-6">

        <?php if (!$migration_ready): ?>
        <div class="bg-amber-50 border-2 border-amber-300 rounded-2xl p-6 flex items-start gap-4">
            <div class="w-12 h-12 bg-amber-400 rounded-xl flex items-center justify-center text-white text-xl flex-shrink-0"><i class="bi bi-database-exclamation"></i></div>
            <div>
                <p class="font-black text-amber-800 text-base">ต้อง Run Migration ก่อนใช้งานฟีเจอร์นี้!</p>
                <p class="text-sm text-amber-700 mt-1">ยังไม่มี column <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono text-xs">is_elective</code> ในฐานข้อมูล</p>
                <a href="/database/run_pending.php" target="_blank"
                   class="inline-flex items-center gap-2 mt-3 bg-amber-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-amber-600 transition shadow-lg shadow-amber-200">
                    <i class="bi bi-lightning-charge-fill"></i> เปิด run_pending.php
                </a>
                <p class="text-xs text-amber-500 mt-2">เปิด URL นี้ใน tab ใหม่ แล้ว reload หน้านี้</p>
            </div>
        </div>

        <?php elseif (empty($elective_subjects)): ?>
        <div class="bg-violet-50 border border-violet-200 rounded-2xl p-10 text-center text-violet-600">
            <i class="bi bi-star text-4xl block mb-3 opacity-50"></i>
            <p class="font-black">ยังไม่มีวิชาเลือกในระบบ</p>
            <p class="text-sm mt-1">ไปเพิ่มวิชา และติ๊ก &#34;วิชาเลือก&#34; ที่ฟอร์มเพิ่มวิชาก่อนครับ</p>
        </div>
        <?php else: ?>
        <!-- Export All Banner -->
        <div class="bg-violet-50 border border-violet-200 rounded-2xl px-5 py-4 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <i class="bi bi-star-fill text-violet-500 text-xl"></i>
                <div>
                    <p class="font-black text-violet-800 text-sm">วิชาเลือกทั้งหมด <?= count($elective_subjects) ?> วิชา</p>
                    <p class="text-[10px] text-violet-500 font-bold">จำนวนผู้ลงทะเบียนรวม <?= array_sum(array_map(fn($id) => count($enrollments[$id] ?? []), array_column($elective_subjects, 'id'))) ?> คน</p>
                </div>
            </div>
            <a href="export_elective.php" target="_blank"
               class="inline-flex items-center gap-2 bg-emerald-600 text-white px-5 py-2 rounded-xl font-black text-sm shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i> Export ทุกวิชาเลือก
            </a>
        </div>

        <?php foreach ($elective_subjects as $es):
            $enrolled_ids = $enrollments[$es['id']] ?? [];
            $total_students = array_sum(array_map('count', $all_students_by_class));
        ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-violet-50/50 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 bg-violet-100 text-violet-700 text-[10px] font-black rounded-lg">วิชาเลือก</span>
                        <span class="font-mono text-xs font-bold text-blue-600"><?= $es['subject_code'] ?></span>
                    </div>
                    <h3 class="font-black text-slate-800 mt-1"><?= htmlspecialchars($es['subject_name']) ?></h3>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">ลงทะเบียนแล้ว <?= count($enrolled_ids) ?> คน จากทั้งหมด <?= $total_students ?> คน</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="export_elective.php?subject_id=<?= $es['id'] ?>" target="_blank"
                       class="inline-flex items-center gap-1.5 text-[11px] font-black text-emerald-600 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-xl transition border border-emerald-100">
                        <i class="bi bi-file-earmark-spreadsheet-fill"></i> Export CSV
                    </a>
                    <span class="text-[10px] font-bold text-violet-500"><i class="bi bi-info-circle"></i> เลือกได้ทุกห้อง</span>
                </div>
            </div>
            <form method="POST" class="p-6">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_enrollment">
                <input type="hidden" name="subject_id" value="<?= $es['id'] ?>">
                <?php if (empty($all_students_by_class)): ?>
                <p class="text-amber-600 text-sm font-bold py-4">ยังไม่มีนักเรียนในระบบ กรุณาเพิ่มนักเรียนที่แท็บ "จัดการนักเรียน" ก่อน</p>
                <?php else: ?>
                <?php foreach ($all_students_by_class as $cls_name => $cls_students): ?>
                <div class="mb-5">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-700 font-black text-xs rounded-lg"><?= htmlspecialchars($cls_name) ?></span>
                        <span class="text-[10px] text-slate-400 font-bold"><?= count($cls_students) ?> คน</span>
                        <button type="button" onclick="selectGroup(this)" class="text-[10px] font-bold text-violet-500 hover:text-violet-700 ml-auto transition">เลือกทั้งห้อง</button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                        <?php foreach ($cls_students as $std): ?>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all <?= in_array($std['id'], $enrolled_ids) ? 'bg-violet-50 border-violet-300' : 'bg-slate-50 border-slate-200 hover:border-violet-200' ?>">
                            <input type="checkbox" name="enrolled_students[]" value="<?= $std['id'] ?>"
                                   class="accent-violet-600 w-4 h-4 flex-shrink-0" <?= in_array($std['id'], $enrolled_ids) ? 'checked' : '' ?>>
                            <div class="min-w-0">
                                <p class="font-bold text-xs text-slate-700 truncate"><?= htmlspecialchars($std['name']) ?></p>
                                <p class="font-mono text-[9px] text-blue-500"><?= htmlspecialchars($std['student_id']) ?></p>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="flex items-center justify-end pt-4 border-t border-slate-100">
                    <button type="submit" class="bg-violet-600 text-white px-8 py-2.5 rounded-xl font-bold shadow-lg shadow-violet-100 hover:bg-violet-700 transition flex items-center gap-2">
                        <i class="bi bi-check2-all"></i> บันทึกการลงทะเบียน
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>


<script>
function showTab(tab) {
    ['teachers','students','subjects','enrollment'].forEach(t => {
        const pane = document.getElementById('pane-'+t);
        const btn  = document.getElementById('tab-'+t);
        if (!pane || !btn) return;
        pane.classList.toggle('hidden', t !== tab);
        if (t === tab) {
            btn.classList.add('active','bg-white','text-blue-700','shadow-sm');
            btn.classList.remove('text-slate-500');
        } else {
            btn.classList.remove('active','bg-white','text-blue-700','shadow-sm');
            btn.classList.add('text-slate-500');
        }
    });
}

function selectAll(btn) {
    const form = btn.closest('form');
    const checks = form.querySelectorAll('input[type="checkbox"]');
    const allChecked = [...checks].every(c => c.checked);
    checks.forEach(c => {
        c.checked = !allChecked;
        c.closest('label').className = c.closest('label').className
            .replace(/bg-\S+|border-\S+/g, '')
            .trim() + (c.checked ? ' bg-violet-50 border-violet-300' : ' bg-slate-50 border-slate-200');
    });
    btn.textContent = allChecked ? 'เลือกทั้งหมด' : 'ยกเลิกทั้งหมด';
}

function selectGroup(btn) {
    const group = btn.closest('.mb-5');
    const checks = group.querySelectorAll('input[type="checkbox"]');
    const allChecked = [...checks].every(c => c.checked);
    checks.forEach(c => {
        c.checked = !allChecked;
        c.closest('label').className = c.closest('label').className
            .replace(/bg-\S+|border-\S+/g, '')
            .trim() + (c.checked ? ' bg-violet-50 border-violet-300' : ' bg-slate-50 border-slate-200');
    });
    btn.textContent = allChecked ? 'เลือกทั้งห้อง' : 'ยกเลิกทั้งห้อง';
}

function deleteTeacher(id, name) {
    Swal.fire({
        title: 'ลบข้อมูลครู?', text: `ต้องการลบครู "${name}" หรือไม่? วิชาที่เกี่ยวข้องจะถูกลบด้วย`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#e11d48', confirmButtonText: 'ยืนยันลบข้อมูล', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if(r.isConfirmed){
            const f = document.createElement('form'); f.method='POST';
            f.innerHTML = `<input name="action" value="delete_teacher"><input name="teacher_id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }
    });
}

function deleteSubject(id, name) {
    Swal.fire({
        title: 'ลบวิชา?', text: `ต้องการลบวิชา "${name}" หรือไม่?`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#e11d48', confirmButtonText: 'ยืนยันลบ', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if(r.isConfirmed){
            const f = document.createElement('form'); f.method='POST';
            f.innerHTML = `<input name="action" value="delete_subject"><input name="subject_id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }
    });
}

function deleteStudent(id, name) {
    Swal.fire({
        title: 'ลบนักเรียน?', text: `ต้องการลบ "${name}" หรือไม่?`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#e11d48', confirmButtonText: 'ยืนยันลบ', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if (r.isConfirmed) {
            const f = document.createElement('form'); f.method = 'POST';
            f.innerHTML = `<input name="action" value="delete_student"><input name="student_db_id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }
    });
}

function editStudent(id, sidCode, name, classroom) {
    Swal.fire({
        title: 'แก้ไขนักเรียน',
        html: `
        <div class="text-left space-y-3 mt-2">
            <div>
                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">รหัสนักเรียน</label>
                <input id="edit_sid" class="swal2-input mt-1" value="${sidCode}" placeholder="รหัส">
            </div>
            <div>
                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">ชื่อ-สกุล</label>
                <input id="edit_name" class="swal2-input mt-1" value="${name}" placeholder="ชื่อ-สกุล">
            </div>
            <div>
                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">ห้องเรียน</label>
                <input id="edit_classroom" class="swal2-input mt-1" value="${classroom}" placeholder="เช่น ม.4/1">
            </div>
        </div>`,
        confirmButtonText: 'บันทึก',
        confirmButtonColor: '#2563eb',
        showCancelButton: true,
        cancelButtonText: 'ยกเลิก',
        focusConfirm: false,
        preConfirm: () => {
            const sid = document.getElementById('edit_sid').value.trim();
            const nm  = document.getElementById('edit_name').value.trim();
            const cls = document.getElementById('edit_classroom').value.trim();
            if (!sid || !nm || !cls) {
                Swal.showValidationMessage('กรุณากรอกข้อมูลให้ครบ');
                return false;
            }
            return { sid, nm, cls };
        }
    }).then(r => {
        if (r.isConfirmed) {
            const f = document.createElement('form'); f.method = 'POST';
            f.innerHTML = `
                <input name="action" value="edit_student">
                <input name="edit_id" value="${id}">
                <input name="student_id_code" value="${r.value.sid}">
                <input name="student_name" value="${r.value.nm}">
                <input name="student_classroom" value="${r.value.cls}">`;
            document.body.appendChild(f); f.submit();
        }
    });
}
</script>

<?php
$swal_tab = 'teachers';
if ($msg && in_array($action, ['add_student','edit_student','delete_student'])) $swal_tab = 'students';
if ($msg && in_array($action, ['add_subject','delete_subject'])) $swal_tab = 'subjects';
if ($msg && $action === 'save_enrollment') $swal_tab = 'enrollment';

if ($msg) {
    $safeMsg = addslashes(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'));
    echo "<script>Swal.fire({ icon: '$msgType', title: 'แจ้งเตือน', text: '$safeMsg', timer: 2000, showConfirmButton: false }).then(()=>showTab('$swal_tab'));</script>";
} else {
    $initTab = isset($_GET['cls']) ? 'students' : 'teachers';
    echo "<script>document.addEventListener('DOMContentLoaded',()=>showTab('$initTab'));</script>";
}
require_once '../components/layout_end.php';
?>
