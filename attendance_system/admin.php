<?php
require_once 'functions.php';
checkAdmin();


$pageTitle = 'จัดการระบบ (Admin)';
$pageSubtitle = 'จัดการรายชื่อครู รายวิชา และข้อมูลพื้นฐาน';

$msg = ''; $msgType = 'success';
$action = $_POST['action'] ?? '';

if ($action) csrf_verify();

// ── AJAX: toggle is_elective (ต้อง early-return ก่อน HTML ──
if ($action === 'toggle_elective') {
    header('Content-Type: application/json; charset=utf-8');
    $sid = (int)($_POST['subject_id'] ?? 0);
    if (!$sid) { echo json_encode(['ok' => false, 'msg' => 'invalid id']); exit; }
    try {
        $cur = $pdo->prepare("SELECT is_elective FROM att_subjects WHERE id=? LIMIT 1");
        $cur->execute([$sid]);
        $row = $cur->fetch();
        if (!$row) { echo json_encode(['ok' => false, 'msg' => 'not found']); exit; }
        $newVal = $row['is_elective'] ? 0 : 1;
        $pdo->prepare("UPDATE att_subjects SET is_elective=? WHERE id=?")->execute([$newVal, $sid]);
        echo json_encode(['ok' => true, 'is_elective' => $newVal]);
    } catch (\Throwable $e) {
        error_log('[toggle_elective] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'db error']);
    }
    exit;
}

// --- CRUD: TEACHERS ---
if ($action === 'add_teacher') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if (!$uid) { $msg = 'กรุณาเลือกผู้ใช้งานจากระบบ'; $msgType = 'error'; } 
    else {
        // Fetch user info from central table
        $stmt = $pdo->prepare("SELECT * FROM llw_users WHERE user_id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        
        if (!$user) { $msg = 'ไม่พบผู้ใช้งานที่เลือก'; $msgType = 'error'; }
        else {
            // Check if already a teacher
            $chk = $pdo->prepare("SELECT id FROM att_teachers WHERE llw_user_id = ?");
            $chk->execute([$uid]);
            if ($chk->fetch()) { $msg = "ผู้ใช้งานนี้ถูกเพิ่มเป็นครูในระบบแล้ว"; $msgType = 'error'; } 
            else {
                $name = trim($user['firstname'] . ' ' . $user['lastname']);
                $pdo->beginTransaction();
                try {
                    // 1. Link to att_teachers
                    $t = $pdo->prepare("INSERT INTO att_teachers (name, username, password, llw_user_id) VALUES (?, ?, ?, ?)");
                    $t->execute([$name, $user['username'], $user['password'], $uid]);
                    
                    // 2. Update role if needed (only if not super_admin)
                    if (!in_array($user['role'], ['super_admin', 'att_teacher'])) {
                        $u = $pdo->prepare("UPDATE llw_users SET role = 'att_teacher' WHERE user_id = ?");
                        $u->execute([$uid]);
                    }
                    
                    $pdo->commit(); 
                    $msg = "เพิ่มครู '$name' เข้าสู่ระบบเรียบร้อย";
                } catch (Exception $e) { 
                    $pdo->rollBack(); 
                    $msg = 'เกิดข้อผิดพลาด: ' . $e->getMessage(); 
                    $msgType = 'error'; 
                }
            }
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
        $chk = $pdo->prepare("SELECT id, status FROM att_students WHERE student_id=? LIMIT 1");
        $chk->execute([$sid]);
        $exRow = $chk->fetch(PDO::FETCH_ASSOC);
        if ($exRow && ($exRow['status'] ?? 'active') === 'active') {
            $msg = "รหัส '$sid' มีอยู่ในระบบแล้ว"; $msgType = 'error';
        } elseif ($exRow) {
            $pdo->prepare("UPDATE att_students SET name=?, classroom=?, status='active', status_note=NULL WHERE id=?")->execute([$sname, $cls, $exRow['id']]);
            if (function_exists('llw_sync_student_to_modules')) {
                llw_sync_student_to_modules($pdo, $sid, $sname, $cls, 'active');
            }
            $msg = "เปิดใช้งานและอัปเดตนักเรียน '$sname' สำเร็จ";
        } else {
            $pdo->prepare("INSERT INTO att_students (student_id, name, classroom, status) VALUES (?,?,?,'active')")->execute([$sid, $sname, $cls]);
            if (function_exists('llw_sync_student_to_modules')) {
                llw_sync_student_to_modules($pdo, $sid, $sname, $cls, 'active');
            }
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
        $stCur = $pdo->prepare("SELECT status FROM att_students WHERE id=? LIMIT 1");
        $stCur->execute([$eid]);
        $curStatus = $stCur->fetchColumn() ?: 'active';
        if (function_exists('llw_sync_student_to_modules')) {
            llw_sync_student_to_modules($pdo, $sid, $sname, $cls, (string)$curStatus);
        }
        $msg = 'แก้ไขนักเรียนสำเร็จ';
    }
} elseif ($action === 'delete_student') {
    $eid    = (int)$_POST['student_db_id'];
    $status = $_POST['status'] ?? 'moved';
    $note   = trim($_POST['status_note'] ?? '');
    if ($eid) {
        $pdo->prepare("UPDATE att_students SET status=?, status_note=? WHERE id=?")->execute([$status, $note, $eid]);
        $stInfo = $pdo->prepare("SELECT student_id, name, classroom FROM att_students WHERE id=? LIMIT 1");
        $stInfo->execute([$eid]);
        if ($sRow = $stInfo->fetch(PDO::FETCH_ASSOC)) {
            if (function_exists('llw_sync_student_to_modules')) {
                llw_sync_student_to_modules($pdo, $sRow['student_id'], $sRow['name'], $sRow['classroom'], $status);
            }
        }
        $msg = 'ปรับสถานะนักเรียนเรียบร้อย';
    }
}

// --- CRUD: SUBJECTS ---
// --- CRUD: SUBJECTS & TERM SETTINGS ---
llw_ensure_semester_schema($pdo);
$filter_year = (int)($_GET['year'] ?? llw_current_academic_year());
$filter_sem  = isset($_GET['sem']) ? (int)$_GET['sem'] : llw_current_semester();

if ($action === 'save_term_settings') {
    $yr       = (int)($_POST['academic_year'] ?? llw_current_academic_year());
    $s1_start = trim($_POST['sem1_start'] ?? '');
    $s1_end   = trim($_POST['sem1_end']   ?? '');
    $s2_start = trim($_POST['sem2_start'] ?? '');
    $s2_end   = trim($_POST['sem2_end']   ?? '');
    $active_m = (int)($_POST['active_mode'] ?? 0); // 0=auto, 1=force sem1, 2=force sem2
    if ($s1_start && $s1_end && $s2_start && $s2_end) {
        $pdo->exec("UPDATE llw_academic_terms SET is_active = 0");
        $up = $pdo->prepare("
            INSERT INTO llw_academic_terms (academic_year, semester, start_date, end_date, is_active)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE start_date=VALUES(start_date), end_date=VALUES(end_date), is_active=VALUES(is_active)
        ");
        $up->execute([$yr, 1, $s1_start, $s1_end, $active_m === 1 ? 1 : 0]);
        $up->execute([$yr, 2, $s2_start, $s2_end, $active_m === 2 ? 1 : 0]);
        $msg = "บันทึกการตั้งค่าภาคเรียนปีการศึกษา $yr สำเร็จ";
    } else {
        $msg = 'กรุณาระบุวันเปิด–ปิดภาคเรียนให้ครบ'; $msgType = 'error';
    }
} elseif ($action === 'copy_subjects_to_sem2') {
    $yr        = (int)($_POST['academic_year'] ?? llw_current_academic_year());
    $auto_code = !empty($_POST['auto_increment_code']);
    try {
        $pdo->beginTransaction();
        $srcStmt = $pdo->prepare("SELECT * FROM att_subjects WHERE academic_year = ? AND semester = 1 ORDER BY id");
        $srcStmt->execute([$yr]);
        $sem1Subjects = $srcStmt->fetchAll(PDO::FETCH_ASSOC);

        $chkStmt = $pdo->prepare("SELECT id FROM att_subjects WHERE academic_year = ? AND semester = 2 AND subject_code = ? AND classroom = ? LIMIT 1");
        $insStmt = $pdo->prepare("INSERT INTO att_subjects (subject_code, subject_name, classroom, telegram_chat_id, is_elective, teacher_id, academic_year, semester) VALUES (?, ?, ?, ?, ?, ?, ?, 2)");
        $enrSrc  = $pdo->prepare("SELECT student_id FROM att_subject_students WHERE subject_id = ?");
        $enrIns  = $pdo->prepare("INSERT IGNORE INTO att_subject_students (subject_id, student_id) VALUES (?, ?)");

        $copied = 0; $skipped = 0;
        foreach ($sem1Subjects as $sub) {
            $newCode = $sub['subject_code'];
            $newName = $sub['subject_name'];
            if ($auto_code && preg_match('/^(.+\d{4})1$/u', $newCode, $m)) {
                $newCode = $m[1] . '2';
                $newName = preg_replace('/(\s*)1$/u', '${1}2', $newName);
            }
            $chkStmt->execute([$yr, $newCode, $sub['classroom']]);
            if ($chkStmt->fetchColumn()) {
                $skipped++;
                continue;
            }
            $insStmt->execute([
                $newCode,
                $newName,
                $sub['classroom'],
                $sub['telegram_chat_id'] ?? null,
                (int)($sub['is_elective'] ?? 0),
                (int)$sub['teacher_id'],
                $yr,
            ]);
            $newSubId = (int)$pdo->lastInsertId();
            if (!empty($sub['is_elective']) && $newSubId > 0) {
                $enrSrc->execute([$sub['id']]);
                foreach ($enrSrc->fetchAll(PDO::FETCH_COLUMN) as $stuId) {
                    $enrIns->execute([$newSubId, $stuId]);
                }
            }
            $copied++;
        }
        $pdo->commit();
        $filter_sem = 2;
        $msg = "คัดลอกรายวิชาสู่ภาคเรียนที่ 2 สำเร็จ $copied วิชา" . ($skipped ? " (ข้ามที่มีอยู่แล้ว $skipped วิชา)" : '');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = 'เกิดข้อผิดพลาดในการคัดลอกวิชา: ' . $e->getMessage();
        $msgType = 'error';
    }
} elseif ($action === 'add_subject') {
    $tid = (int)$_POST['teacher_id']; $code = trim($_POST['subject_code']); $name = trim($_POST['subject_name']); $cls = trim($_POST['classroom']);
    $is_elective = isset($_POST['is_elective']) ? 1 : 0;
    if (!$tid || !$code || !$name || !$cls) { $msg = 'ข้อมูลไม่ครบ'; $msgType = 'error'; } 
    else {
        try {
            $sem_in = max(1, min(2, (int)($_POST['semester'] ?? $filter_sem)));
            $yr_in  = (int)($_POST['academic_year'] ?? $filter_year);
            $pdo->prepare("INSERT INTO att_subjects (subject_code,subject_name,classroom,teacher_id,is_elective,academic_year,semester) VALUES (?,?,?,?,?,?,?)")->execute([$code, $name, $cls, $tid, $is_elective, $yr_in, $sem_in]);
            $filter_sem = $sem_in;
        } catch (PDOException $e) {
            // Fallback: column is_elective ยังไม่มี (ยังไม่ได้ run migration)
            $pdo->prepare("INSERT INTO att_subjects (subject_code,subject_name,classroom,teacher_id) VALUES (?,?,?,?)")->execute([$code, $name, $cls, $tid]);
        }
        $msg = "เพิ่มวิชาสำเร็จ" . ($is_elective ? ' (วิชาเลือก — กรุณา run migration ก่อน)' : '');
    }
} elseif ($action === 'edit_subject') {
    $sid  = (int)$_POST['subject_id'];
    $tid  = (int)$_POST['teacher_id'];
    $code = trim($_POST['subject_code'] ?? '');
    $name = trim($_POST['subject_name'] ?? '');
    $cls  = trim($_POST['classroom'] ?? '');
    if ($sid && $tid && $code && $name && $cls) {
        $_chk_tg = (bool)$pdo->query("SHOW COLUMNS FROM `att_subjects` LIKE 'telegram_chat_id'")->fetch();
        if ($_chk_tg) {
            $chat = trim($_POST['telegram_chat_id'] ?? '');
            $sem_ed = max(1, min(2, (int)($_POST['semester'] ?? $filter_sem)));
            $pdo->prepare("UPDATE att_subjects SET teacher_id=?, subject_code=?, subject_name=?, classroom=?, telegram_chat_id=?, semester=? WHERE id=?")->execute([$tid, $code, $name, $cls, $chat ?: null, $sem_ed, $sid]);
        } else {
            $pdo->prepare("UPDATE att_subjects SET teacher_id=?, subject_code=?, subject_name=?, classroom=? WHERE id=?")->execute([$tid, $code, $name, $cls, $sid]);
        }
        $msg = "แก้ไขวิชา '$code' สำเร็จ";
    } else {
        $msg = 'ข้อมูลไม่ครบ'; $msgType = 'error';
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
            // REMOVED: $pdo->prepare("DELETE FROM att_subject_students WHERE subject_id=?")->execute([$sid]);
            // This allows incremental saves without losing other grades/students
            
            if (!empty($enrolled)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO att_subject_students (subject_id, student_id) VALUES (?,?)");
                foreach ($enrolled as $std_id) {
                    if ($std_id > 0) $ins->execute([$sid, $std_id]);
                }
            }
            $pdo->commit();
            $msg = 'บันทึกการลงทะเบียน (สะสมรายชื่อ) สำเร็จ';
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

    // Available users for linking (only those not already in att_teachers)
    $available_users = $pdo->query("
        SELECT user_id, username, firstname, lastname
        FROM llw_users
        WHERE user_id NOT IN (SELECT llw_user_id FROM att_teachers WHERE llw_user_id IS NOT NULL)
        ORDER BY firstname
    ")->fetchAll();

    $sem1_subj_count = (int)$pdo->query("SELECT COUNT(*) FROM att_subjects WHERE academic_year = $filter_year AND semester = 1")->fetchColumn();
    $sem2_subj_count = (int)$pdo->query("SELECT COUNT(*) FROM att_subjects WHERE academic_year = $filter_year AND semester = 2")->fetchColumn();
    $sem_where = $filter_sem > 0 ? "WHERE s.academic_year = $filter_year AND s.semester = $filter_sem" : "WHERE s.academic_year = $filter_year";
    $subjects   = $pdo->query("SELECT s.*, t.name as t_name FROM att_subjects s JOIN att_teachers t ON t.id = s.teacher_id $sem_where ORDER BY s.semester, s.subject_code")->fetchAll();
    $term1_cfg  = llw_get_term_dates($filter_year, 1, $pdo);
    $term2_cfg  = llw_get_term_dates($filter_year, 2, $pdo);
    $classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students WHERE student_id REGEXP '^[0-9]+$' ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

    if (!$filter_cls) $filter_cls = $classrooms[0] ?? '';

    // Pre-fetch subject_codes once to avoid correlated subquery on every student row
    $subjectCodes = $pdo->query("SELECT subject_code FROM att_subjects")->fetchAll(PDO::FETCH_COLUMN);
    $excludeSql = empty($subjectCodes)
        ? "1=1"
        : "student_id NOT IN (" . implode(',', array_map(fn($c) => $pdo->quote($c), $subjectCodes)) . ")";

    $show_all = isset($_GET['show_all']) && $_GET['show_all'] == 1;
    $filter_major = $_GET['major'] ?? '';
    $status_filter = $show_all ? "" : " AND status='active'";
    $major_filter = $filter_major === 'none' ? " AND (major IS NULL OR major = '')" : ($filter_major ? " AND major = " . $pdo->quote($filter_major) : "");

    if ($filter_cls) {
        $sq = $pdo->prepare("SELECT * FROM att_students WHERE classroom=? AND student_id REGEXP '^[0-9]+$' AND $excludeSql $status_filter $major_filter ORDER BY student_id");
        $sq->execute([$filter_cls]);
        $all_students = $sq->fetchAll();
    } else {
        $all_students = $pdo->query("SELECT * FROM att_students WHERE student_id REGEXP '^[0-9]+$' AND $excludeSql $status_filter $major_filter ORDER BY classroom, student_id LIMIT 1000")->fetchAll();
    }
} catch (Exception $e) {
    $msg = "ระบบยังไม่พร้อมใช้งาน: กรุณารัน Migration ตาราง Attendance (" . $e->getMessage() . ")";
    $msgType = 'error';
}

// --- ส่วน Elective ---
$migration_ready    = false;
$elective_subjects  = [];
$enrollments        = [];
$all_students_by_class = [];
try {
    // Detect is_elective without SHOW COLUMNS: check the already-fetched subjects array,
    // fall back to a lightweight column probe only when subjects table is empty.
    if (!empty($subjects)) {
        $migration_ready = array_key_exists('is_elective', $subjects[0]);
    } else {
        $colCheck = $pdo->query("SHOW COLUMNS FROM att_subjects LIKE 'is_elective'")->fetch();
        $migration_ready = !empty($colCheck);
    }

    if ($migration_ready) {
        $elective_subjects = array_filter($subjects, function($s) {
            return !empty($s['is_elective']);
        });

        // Single query for all enrollments instead of N+1 loop
        if (!empty($elective_subjects)) {
            $eIds = array_values(array_column($elective_subjects, 'id'));
            $ph   = implode(',', array_fill(0, count($eIds), '?'));
            $eStmt = $pdo->prepare("SELECT subject_id, student_id FROM att_subject_students WHERE subject_id IN ($ph)");
            $eStmt->execute($eIds);
            foreach ($eStmt->fetchAll() as $row) {
                $enrollments[$row['subject_id']][] = $row['student_id'];
            }
        }

        // นักเรียน active จัดกลุ่มตามห้อง (reuse pre-fetched excludeSql)
        $excludeSqlE = $excludeSql ?? "1=1";
        $all_rows = $pdo->query("SELECT * FROM att_students WHERE student_id REGEXP '^[0-9]+$' AND $excludeSqlE AND status='active' ORDER BY classroom, student_id")->fetchAll();
        foreach ($all_rows as $row) {
            $all_students_by_class[$row['classroom']][] = $row;
        }
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
            <?php if (!empty($elective_subjects)): ?><span class="ml-1 px-1.5 py-0.5 bg-violet-100 text-violet-600 text-xs font-black rounded-lg"><?= count($elective_subjects) ?></span><?php endif; ?>
        </button>
    </div>

    <!-- ══ TEACHERS TAB ══ -->
    <div id="pane-teachers" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Form Add Teacher (Linked Mode) -->
        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 flex flex-col gap-6 h-fit">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl"><i class="bi bi-person-plus-fill"></i></div>
                <h3 class="font-bold text-slate-800">เพิ่มครูเข้าในระบบ</h3>
            </div>
            <p class="text-xs text-slate-400 -mt-2">เลือกจากบัญชีรายชื่อบุคลากรที่มีอยู่แล้วในระบบส่วนกลาง</p>
            <form method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_teacher">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">เลือกบุคลากร</label>
                    <select name="user_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-400 outline-none appearance-none font-bold">
                        <option value="">-- เลือกจากรายชื่อ --</option>
                        <?php foreach($available_users as $au): ?>
                            <option value="<?= $au['user_id'] ?>">
                                <?= htmlspecialchars($au['firstname'] . ' ' . $au['lastname']) ?> (@<?= $au['username'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bg-slate-50 rounded-2xl p-4 border border-dashed border-slate-200">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">คำแนะนำ</p>
                    <p class="text-sm text-slate-400 leading-relaxed">ระบบจะใช้ Username และ Password เดียวกับที่ใช้ลงเวลาปฏิบัติงาน (WFH) หากไม่พบชื่อในรายการ กรุณาไปเพิ่มที่ <a href="/manage_users.php" class="text-indigo-600 underline">เมนูจัดการผู้ใช้</a> ก่อน</p>
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition mt-2">ยืนยันการเพิ่มครู</button>
            </form>
        </div>

        <!-- Teacher List Table -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-50 flex items-center justify-between">
                <h3 class="font-bold text-slate-800">รายชื่อครูทั้งหมด (<?= count($teachers) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50 text-sm">
                    <thead class="bg-slate-50/50 text-xs font-black text-slate-400 uppercase tracking-widest">
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
                            <td class="px-6 py-4 text-left align-top max-w-xs">
                                <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 font-bold text-xs inline-block mb-1.5"><?= $t['s_count'] ?> วิชา</span>
                                <?php if ($t['s_count'] > 0): ?>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php 
                                    $t_subjs = array_filter($subjects, fn($s) => $s['teacher_id'] == $t['id']);
                                    foreach($t_subjs as $ts): 
                                        $is_elec = !empty($ts['is_elective']) ? '<i class="bi bi-star-fill text-amber-400 ms-1 text-[8px]"></i>' : '';
                                    ?>
                                        <span class="px-2 py-1 bg-slate-50 text-slate-600 border border-slate-200 rounded-md text-[10px] font-bold leading-tight" title="<?= htmlspecialchars($ts['subject_name']) ?>">
                                            <span class="text-blue-600"><?= htmlspecialchars($ts['subject_code']) ?></span> 
                                            <span class="text-slate-400 mx-0.5">|</span> 
                                            <?= htmlspecialchars($ts['classroom']) ?>
                                            <?= $is_elec ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-[10px] text-slate-400 mt-1">ยังไม่มีวิชาสอน</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 rounded-lg text-xs font-bold <?= $t['user_status']==='active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
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
                <form method="GET" class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase">กรองสถานะ:</label>
                        <select name="show_all" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold outline-none">
                            <option value="0" <?= !$show_all ? 'selected' : '' ?>>เฉพาะที่ยังอยู่ (Active)</option>
                            <option value="1" <?= $show_all ? 'selected' : '' ?>>ทั้งหมด (รวมที่ย้าย/ออก)</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase">สายการเรียน:</label>
                        <select name="major" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold outline-none">
                            <option value="">-- ทุกสาย --</option>
                            <option value="none" <?= $filter_major === 'none' ? 'selected' : '' ?>>ยังไม่มีสาย</option>
                            <?php 
                            $majors_list = $pdo->query("SELECT DISTINCT major FROM att_students WHERE major IS NOT NULL AND major != '' ORDER BY major")->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($majors_list as $ml): ?>
                            <option value="<?= htmlspecialchars($ml) ?>" <?= $filter_major === $ml ? 'selected' : '' ?>><?= htmlspecialchars($ml) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase">ห้อง:</label>
                        <select name="cls" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold outline-none">
                            <option value="">-- ทุกห้อง --</option>
                            <?php foreach ($classrooms as $cls): ?>
                            <option value="<?= htmlspecialchars($cls) ?>" <?= $filter_cls === $cls ? 'selected' : '' ?>><?= htmlspecialchars($cls) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50 text-sm">
                    <thead class="bg-slate-50/50 text-xs font-black text-slate-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-6 py-4 text-left">รหัส</th>
                            <th class="px-6 py-4 text-left">ชื่อ-สกุล</th>
                            <th class="px-6 py-4 text-center">ห้อง/สายการเรียน</th>
                            <th class="px-6 py-4 text-center">สถานะ</th>
                            <th class="px-6 py-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($all_students as $std): ?>
                        <tr class="hover:bg-slate-50 transition" id="row-<?= $std['id'] ?>">
                            <td class="px-6 py-3.5 font-mono font-bold text-blue-600 text-xs"><?= htmlspecialchars($std['student_id']) ?></td>
                            <td class="px-6 py-3.5 font-bold text-slate-700"><?= htmlspecialchars($std['name']) ?></td>
                            <td class="px-6 py-3.5 text-center">
                                <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold"><?= htmlspecialchars($std['classroom']) ?></span>
                                <?php if (!empty($std['major'])): ?>
                                <span class="px-2 py-0.5 rounded-lg bg-violet-50 text-violet-600 text-[10px] font-bold ms-1"><?= htmlspecialchars($std['major']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3.5 text-center">
                                <?php
                                $status = $std['status'] ?? 'active';
                                $badgeClass = 'bg-emerald-50 text-emerald-600';
                                $statusTh = 'ปกติ';
                                if ($status === 'moved') { $badgeClass = 'bg-amber-50 text-amber-600'; $statusTh = 'ย้าย'; }
                                elseif ($status === 'resigned') { $badgeClass = 'bg-rose-50 text-rose-600'; $statusTh = 'ออก'; }
                                elseif ($status === 'suspended') { $badgeClass = 'bg-slate-100 text-slate-500'; $statusTh = 'พักการเรียน'; }
                                elseif ($status === 'inactive') { $badgeClass = 'bg-slate-100 text-slate-500'; $statusTh = 'ปิดใช้งาน'; }
                                ?>
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase <?= $badgeClass ?>" title="<?= htmlspecialchars($std['status_note'] ?? '') ?>">
                                    <?= $statusTh ?>
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-right flex gap-1 justify-end">
                                <button onclick="editStudent(<?= $std['id'] ?>, '<?= addslashes($std['student_id']) ?>', '<?= addslashes($std['name']) ?>', '<?= addslashes($std['classroom']) ?>')"
                                        class="p-2 text-blue-500 hover:bg-blue-50 rounded-xl transition" title="แก้ไข"><i class="bi bi-pencil-fill"></i></button>
                                <button onclick="archiveStudent(<?= $std['id'] ?>, '<?= addslashes($std['name']) ?>')"
                                        class="p-2 text-amber-500 hover:bg-amber-50 rounded-xl transition" title="จำหน่ายออก/ปรับสถานะ"><i class="bi bi-person-x-fill"></i></button>
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
        <!-- ── แถบจัดการภาคเรียน 1/2 + คัดลอกรายวิชาในคลิกเดียว ── -->
        <div class="lg:col-span-3 bg-gradient-to-r from-indigo-50 via-blue-50 to-emerald-50 rounded-3xl p-6 border border-blue-100 shadow-sm flex flex-col gap-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 bg-blue-600 text-white rounded-2xl flex items-center justify-center text-xl shadow-md shadow-blue-200"><i class="bi bi-calendar2-range-fill"></i></div>
                    <div>
                        <h3 class="font-black text-slate-800 text-base">ตั้งค่าภาคเรียน & จัดการรายวิชาประจำเทอม (ปีการศึกษา <?= $filter_year ?>)</h3>
                        <p class="text-xs text-slate-500 font-bold">ภาคเรียนปัจจุบันของระบบ: <span class="text-blue-600">ภาคเรียนที่ <?= llw_current_semester() ?>/<?= llw_current_academic_year() ?></span></p>
                    </div>
                </div>
                <!-- สลับดูวิชาตามภาคเรียน -->
                <div class="flex items-center gap-1.5 bg-white p-1.5 rounded-2xl border border-slate-200 shadow-sm">
                    <a href="admin.php?tab=subjects&year=<?= $filter_year ?>&sem=1" class="px-4 py-2 rounded-xl text-xs font-black transition-all <?= $filter_sem === 1 ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' ?>">
                        เทอม 1 (<?= $sem1_subj_count ?? 0 ?> วิชา)
                    </a>
                    <a href="admin.php?tab=subjects&year=<?= $filter_year ?>&sem=2" class="px-4 py-2 rounded-xl text-xs font-black transition-all <?= $filter_sem === 2 ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' ?>">
                        เทอม 2 (<?= $sem2_subj_count ?? 0 ?> วิชา)
                    </a>
                    <a href="admin.php?tab=subjects&year=<?= $filter_year ?>&sem=0" class="px-4 py-2 rounded-xl text-xs font-black transition-all <?= $filter_sem === 0 ? 'bg-slate-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' ?>">
                        ทุกเทอม
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- ฟอร์มตั้งค่าวันเปิด-ปิดภาคเรียน -->
                <form method="POST" class="bg-white/90 rounded-2xl p-4 border border-slate-200/80 flex flex-col gap-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_term_settings">
                    <input type="hidden" name="academic_year" value="<?= $filter_year ?>">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black text-slate-700"><i class="bi bi-Sliders text-blue-600 me-1"></i> วันเปิด–ปิดภาคเรียน (ใช้คำนวณเวลาเรียน มส. 80% และเข้าแถว)</span>
                        <select name="active_mode" class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-bold text-slate-700 outline-none">
                            <option value="0" <?= empty($term1_cfg['is_active']) && empty($term2_cfg['is_active']) ? 'selected' : '' ?>>สลับเทอมอัตโนมัติตามวันที่</option>
                            <option value="1" <?= !empty($term1_cfg['is_active']) ? 'selected' : '' ?>>บังคับใช้ ภาคเรียนที่ 1</option>
                            <option value="2" <?= !empty($term2_cfg['is_active']) ? 'selected' : '' ?>>บังคับใช้ ภาคเรียนที่ 2</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <span class="font-black text-blue-700 block mb-1">ภาคเรียนที่ 1</span>
                            <div class="flex items-center gap-1">
                                <input type="date" name="sem1_start" value="<?= htmlspecialchars($term1_cfg['start_date'] ?? '2026-05-16') ?>" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold">
                                <span>–</span>
                                <input type="date" name="sem1_end" value="<?= htmlspecialchars($term1_cfg['end_date'] ?? '2026-10-31') ?>" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold">
                            </div>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <span class="font-black text-emerald-700 block mb-1">ภาคเรียนที่ 2</span>
                            <div class="flex items-center gap-1">
                                <input type="date" name="sem2_start" value="<?= htmlspecialchars($term2_cfg['start_date'] ?? '2026-11-01') ?>" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold">
                                <span>–</span>
                                <input type="date" name="sem2_end" value="<?= htmlspecialchars($term2_cfg['end_date'] ?? '2027-03-31') ?>" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="self-end bg-blue-600 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-blue-700 transition shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> บันทึกวันเปิด–ปิดเทอม
                    </button>
                </form>

                <!-- ฟอร์มคัดลอกรายวิชาจากเทอม 1 มาเทอม 2 ในคลิกเดียว -->
                <form method="POST" onsubmit="return confirm('ยืนยันการคัดลอกรายวิชาทั้งหมดจากเทอม 1 ไปยังเทอม 2 หรือไม่? (วิชาที่มีอยู่แล้วในเทอม 2 จะไม่ถูกทับ)');" class="bg-white/90 rounded-2xl p-4 border border-emerald-200/80 flex flex-col justify-between gap-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="copy_subjects_to_sem2">
                    <input type="hidden" name="academic_year" value="<?= $filter_year ?>">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-lg text-[10px] font-black uppercase">One-Click Rollover</span>
                            <h4 class="font-black text-slate-800 text-xs">คัดลอกรายวิชาและรายชื่อนักเรียนวิชาเลือกจาก เทอม 1 → เทอม 2</h4>
                        </div>
                        <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">ไม่ต้องกรอกวิชาใหม่ทีละห้อง! ระบบจะคัดลอกครูผู้สอน ห้องเรียน ตั้งค่า Telegram และรายชื่อนักเรียนวิชาเลือกจากเทอม 1 (<?= $sem1_subj_count ?? 0 ?> วิชา) มาสร้างเป็นวิชาของเทอม 2 ให้ทันที</p>
                        <label class="flex items-center gap-2 mt-2.5 text-xs font-bold text-slate-700 cursor-pointer">
                            <input type="checkbox" name="auto_increment_code" value="1" checked class="w-4 h-4 accent-emerald-600">
                            <span>เปลี่ยนเลขท้ายรหัสวิชาจาก <code class="bg-slate-100 px-1 rounded">...1</code> เป็น <code class="bg-emerald-50 text-emerald-700 px-1 rounded">...2</code> อัตโนมัติ (เช่น ท21101 → ท21102)</span>
                        </label>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <a href="import_subjects.php?semester=<?= $filter_sem ?: 2 ?>" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1">
                            <i class="bi bi-file-earmark-spreadsheet-fill"></i> หรือนำเข้าวิชาจาก CSV
                        </a>
                        <button type="submit" class="bg-emerald-600 text-white px-5 py-2 rounded-xl text-xs font-black hover:bg-emerald-700 transition shadow-md shadow-emerald-100 flex items-center gap-1.5">
                            <i class="bi bi-files"></i> คัดลอกวิชาไปเทอม 2 ทันที
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Add Subject Form -->
        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 flex flex-col gap-6 h-fit">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl"><i class="bi bi-journal-plus"></i></div>
                <h3 class="font-bold text-slate-800">เพิ่มรายวิชา</h3>
            </div>
            <form method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_subject">
                <input type="hidden" name="academic_year" value="<?= $filter_year ?>">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ภาคเรียน</label>
                    <select name="semester" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-blue-400 outline-none">
                        <option value="1" <?= ($filter_sem ?: llw_current_semester()) == 1 ? 'selected' : '' ?>>ภาคเรียนที่ 1/<?= $filter_year ?></option>
                        <option value="2" <?= ($filter_sem ?: llw_current_semester()) == 2 ? 'selected' : '' ?>>ภาคเรียนที่ 2/<?= $filter_year ?></option>
                    </select>
                </div>
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
                        <p class="text-xs text-violet-500 mt-0.5">นักเรียนแต่ละคนเลือกวิชาไม่เหมือนกัน ต้องกำหนดนักเรียนในแท็บลงทะเบียนด้วย</p>
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
                    <thead class="bg-slate-50/50 text-xs font-black text-slate-400 uppercase tracking-widest">
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
                                    <span class="text-xs font-mono text-blue-600 font-bold"><?= $s['subject_code'] ?> <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 text-[10px] ms-1">เทอม <?= (int)($s['semester'] ?? 1) ?></span></span>
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($s['subject_name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-600"><?= $s['t_name'] ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs"><?= $s['classroom'] ?></span>
                                <?php if (!empty($s['telegram_chat_id'] ?? '')): ?>
                                <span class="px-2 py-1 rounded-lg bg-blue-50 text-blue-500 font-bold text-xs ms-1" title="มี Telegram Chat ID ตั้งค่าไว้"><i class="bi bi-telegram"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if (!$migration_ready): ?>
                                <span class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-600 font-bold text-xs"><i class="bi bi-database-exclamation"></i> รอ migration</span>
                                <?php else: ?>
                                <button type="button"
                                    onclick="toggleElective(<?= $s['id'] ?>, this)"
                                    data-elective="<?= !empty($s['is_elective']) ? '1' : '0' ?>"
                                    class="elective-btn px-2.5 py-1 rounded-lg font-bold text-xs transition-all hover:scale-105 active:scale-95 <?= !empty($s['is_elective']) ? 'bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-500' ?>">
                                    <?php if (!empty($s['is_elective'])): ?>
                                        <i class="bi bi-star-fill me-1"></i>วิชาเลือก
                                    <?php else: ?>
                                        <i class="bi bi-lock-fill me-1"></i>บังคับ
                                    <?php endif; ?>
                                </button>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right text-nowrap">
                                <button onclick="editSubject(<?= $s['id'] ?>, '<?= addslashes($s['subject_code']) ?>', '<?= addslashes($s['subject_name']) ?>', '<?= addslashes($s['classroom']) ?>', <?= (int)$s['teacher_id'] ?>, '<?= addslashes($s['telegram_chat_id'] ?? '') ?>')"
                                        class="p-2 text-blue-500 hover:bg-blue-50 rounded-xl transition me-1" title="แก้ไข"><i class="bi bi-pencil-fill"></i></button>
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
                <a href="/database/migrate.php" target="_blank"
                   class="inline-flex items-center gap-2 mt-3 bg-amber-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-amber-600 transition shadow-lg shadow-amber-200">
                    <i class="bi bi-lightning-charge-fill"></i> เปิดระบบ Migration (/database/migrate.php)
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

        <!-- Teacher Filter -->
        <?php
            $teachersInElective = [];
            foreach ($elective_subjects as $es) {
                $tid = $es['teacher_id'] ?? 0;
                if ($tid && !isset($teachersInElective[$tid])) {
                    $teachersInElective[$tid] = $es['t_name'] ?? 'ไม่ระบุ';
                }
            }
        ?>
        <div class="bg-white border border-slate-200 rounded-2xl px-5 py-4 flex items-center gap-3 flex-wrap">
            <i class="bi bi-person-fill text-violet-500 text-lg flex-shrink-0"></i>
            <span class="text-xs font-black text-slate-500 uppercase tracking-widest flex-shrink-0">ครูผู้สอน:</span>
            <button type="button" onclick="filterTeacher('all', this)"
                class="teacher-filter-btn px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-violet-600 text-white shadow-sm">
                ทุกคน (<?= count($elective_subjects) ?> วิชา)
            </button>
            <?php foreach ($teachersInElective as $tid => $tname): ?>
            <?php $cnt = count(array_filter($elective_subjects, fn($e) => ($e['teacher_id'] ?? 0) == $tid)); ?>
            <button type="button" onclick="filterTeacher('<?= $tid ?>', this)"
                class="teacher-filter-btn px-4 py-1.5 rounded-full text-xs font-bold transition-all bg-slate-100 text-slate-600 hover:bg-violet-100 hover:text-violet-700">
                <?= htmlspecialchars($tname) ?> (<?= $cnt ?>)
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Export All Banner -->
        <div class="bg-violet-50 border border-violet-200 rounded-2xl px-5 py-4 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <i class="bi bi-star-fill text-violet-500 text-xl"></i>
                <div>
                    <p class="font-black text-violet-800 text-sm">วิชาเลือกทั้งหมด <?= count($elective_subjects) ?> วิชา</p>
                    <p class="text-xs text-violet-500 font-bold">จำนวนผู้ลงทะเบียนรวม <?= array_sum(array_map(fn($id) => count($enrollments[$id] ?? []), array_column($elective_subjects, 'id'))) ?> คน</p>
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
        <div class="subject-card bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden" data-teacher-id="<?= $es['teacher_id'] ?? 0 ?>">
            <div class="px-6 py-4 border-b border-slate-100 bg-violet-50/50 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2 py-0.5 bg-violet-100 text-violet-700 text-xs font-black rounded-lg">วิชาเลือก</span>
                        <span class="font-mono text-xs font-bold text-blue-600"><?= $es['subject_code'] ?></span>
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-500 text-xs font-bold rounded-lg">
                            <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($es['t_name'] ?? 'ไม่ระบุครู') ?>
                        </span>
                    </div>
                    <h3 class="font-black text-slate-800 mt-1"><?= htmlspecialchars($es['subject_name']) ?></h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">ลงทะเบียนแล้ว <?= count($enrolled_ids) ?> คน จากทั้งหมด <?= $total_students ?> คน</p>
                </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="export_elective.php?subject_id=<?= $es['id'] ?>" target="_blank"
                           class="inline-flex items-center gap-1.5 text-sm font-black text-emerald-600 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-xl transition border border-emerald-100">
                            <i class="bi bi-file-earmark-spreadsheet-fill"></i> Export CSV
                        </a>
                        <div class="flex items-center gap-1 bg-white border border-violet-200 rounded-xl px-2 py-1 shadow-sm">
                            <select onchange="if(this.value) selectByMajor(this, this.value); this.value='';" class="bg-transparent text-xs font-bold text-violet-600 outline-none cursor-pointer p-1">
                                <option value="">🪄 เลือกตามสาย...</option>
                                <?php 
                                $all_majors = [];
                                foreach ($all_rows as $ar) { if($ar['major']) $all_majors[] = $ar['major']; }
                                $all_majors = array_unique($all_majors);
                                sort($all_majors);
                                foreach ($all_majors as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
            </div>
            <form method="POST" class="p-6">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_enrollment">
                <input type="hidden" name="subject_id" value="<?= $es['id'] ?>">
                <?php if (empty($all_students_by_class)): ?>
                <p class="text-amber-600 text-sm font-bold py-4">ยังไม่มีนักเรียนในระบบ กรุณาเพิ่มนักเรียนที่แท็บ "จัดการนักเรียน" ก่อน</p>
                <?php else: ?>
                <!-- Grade Filter Bar -->
                <div class="grade-filter-bar flex items-center gap-2 flex-wrap mb-5 pb-4 border-b border-slate-100">
                    <span class="text-xs font-black text-slate-500 uppercase tracking-widest mr-1">กรองชั้น:</span>
                    <button type="button" onclick="filterGrade(this)" data-grade="all"
                        class="grade-btn px-3 py-1 rounded-full text-xs font-bold transition-all bg-violet-600 text-white shadow-sm">ทั้งหมด</button>
                    <?php
                        $grades = [];
                        foreach (array_keys($all_students_by_class) as $cn) {
                            if (preg_match('/^(ม\.\d+)/', $cn, $m) && !in_array($m[1], $grades)) $grades[] = $m[1];
                        }
                        sort($grades);
                        foreach ($grades as $g): ?>
                    <button type="button" onclick="filterGrade(this)" data-grade="<?= htmlspecialchars($g) ?>"
                        class="grade-btn px-3 py-1 rounded-full text-xs font-bold transition-all bg-slate-100 text-slate-600 hover:bg-violet-100 hover:text-violet-700"><?= htmlspecialchars($g) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($all_students_by_class as $cls_name => $cls_students): ?>
                <div class="mb-5 cls-group" data-classroom="<?= htmlspecialchars($cls_name) ?>">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-700 font-black text-xs rounded-lg"><?= htmlspecialchars($cls_name) ?></span>
                        <span class="text-xs text-slate-400 font-bold"><?= count($cls_students) ?> คน</span>
                        <button type="button" onclick="selectGroup(this)" class="text-xs font-bold text-violet-500 hover:text-violet-700 ml-auto transition">เลือกทั้งห้อง</button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                        <?php foreach ($cls_students as $std): ?>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all <?= in_array($std['id'], $enrolled_ids) ? 'bg-violet-50 border-violet-300' : 'bg-slate-50 border-slate-200 hover:border-violet-200' ?>">
                            <input type="checkbox" name="enrolled_students[]" value="<?= $std['id'] ?>"
                                   data-major="<?= htmlspecialchars($std['major'] ?? '') ?>"
                                   class="accent-violet-600 w-4 h-4 flex-shrink-0" <?= in_array($std['id'], $enrolled_ids) ? 'checked' : '' ?>>
                            <div class="min-w-0">
                                <p class="font-bold text-xs text-slate-700 truncate"><?= htmlspecialchars($std['name']) ?></p>
                                <p class="font-mono text-[10px] text-blue-500"><?= htmlspecialchars($std['student_id']) ?><?= $std['major'] ? ' | '.$std['major'] : '' ?></p>
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
function submitForm(fields) {
    const f = document.createElement('form'); f.method = 'POST';
    const csrf = document.querySelector('input[name="csrf_token"]')?.value ?? '';
    let html = `<input name="csrf_token" value="${csrf}">`;
    for (const [k, v] of Object.entries(fields)) html += `<input name="${k}" value="${String(v).replace(/"/g,'&quot;')}">`;
    f.innerHTML = html;
    document.body.appendChild(f); f.submit();
}

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

function filterTeacher(teacherId, btn) {
    document.querySelectorAll('.teacher-filter-btn').forEach(b => {
        b.classList.remove('bg-violet-600','text-white','shadow-sm');
        b.classList.add('bg-slate-100','text-slate-600');
    });
    btn.classList.remove('bg-slate-100','text-slate-600');
    btn.classList.add('bg-violet-600','text-white','shadow-sm');

    document.querySelectorAll('.subject-card').forEach(card => {
        card.style.display = (teacherId === 'all' || card.dataset.teacherId === String(teacherId)) ? '' : 'none';
    });
    // reset grade filters inside hidden cards
    document.querySelectorAll('.subject-card:not([style*="none"]) .grade-btn[data-grade="all"]').forEach(b => filterGrade(b));
}

function filterGrade(btn) {
    const grade = btn.dataset.grade;
    const form  = btn.closest('form');
    // update active button style
    form.querySelectorAll('.grade-btn').forEach(b => {
        b.classList.remove('bg-violet-600','text-white','shadow-sm');
        b.classList.add('bg-slate-100','text-slate-600');
    });
    btn.classList.remove('bg-slate-100','text-slate-600');
    btn.classList.add('bg-violet-600','text-white','shadow-sm');
    // show/hide classroom groups
    form.querySelectorAll('.cls-group').forEach(div => {
        const cls = div.dataset.classroom || '';
        div.style.display = (grade === 'all' || cls.startsWith(grade)) ? '' : 'none';
    });
}

function selectGroup(btn) {
    const group = btn.closest('.mb-5');
    const checks = group.querySelectorAll('input[type="checkbox"]');
    const allChecked = [...checks].every(c => c.checked);
    checks.forEach(c => {
        c.checked = !allChecked;
        updateLabelStyle(c);
    });
    btn.textContent = allChecked ? 'เลือกทั้งห้อง' : 'ยกเลิกทั้งห้อง';
}

function selectByMajor(select, major) {
    if (!major) return;
    const card = select.closest('.subject-card');
    
    // Function to normalize string: remove ALL spaces and symbols (keep only letters and numbers)
    const normalize = (str) => str.replace(/[^\u0E00-\u0E7Fa-zA-Z0-9]/g, '').trim();
    
    const target = normalize(major);
    const allCheckboxes = card.querySelectorAll('input[type="checkbox"][data-major]');
    let count = 0;
    
    allCheckboxes.forEach(cb => {
        const current = normalize(cb.getAttribute('data-major') || '');
        const group = cb.closest('.cls-group');
        
        // เช็คว่าระดับชั้นถูกแสดงอยู่ (ไม่โดนซ่อนจากการกรอง)
        if (current === target && target !== '' && (!group || group.style.display !== 'none')) {
            cb.checked = true;
            updateLabelStyle(cb);
            count++;
        }
    });

    if (count > 0) {
        Swal.fire({
            icon: 'success',
            title: 'เลือกสายการเรียนสำเร็จ',
            text: 'ระบบได้เลือกนักเรียนสาย ' + major + ' จำนวน ' + count + ' คนให้แล้วครับ',
            timer: 2000, showConfirmButton: false, toast: true, position: 'top-end'
        });
    } else {
        // DEBUG: Get sample majors found in the page
        let samples = [];
        allCheckboxes.forEach((cb, idx) => {
            let m = cb.getAttribute('data-major');
            if (m && idx < 5) samples.push("'" + m + "'");
        });
        
        Swal.fire({
            icon: 'warning',
            title: 'ไม่พบรายชื่อในหน้านี้',
            html: 'เป้าหมายที่หา: <b>' + major + '</b><br><br>ตัวอย่างสายที่ระบบเห็นในหน้านี้:<br>' + (samples.length > 0 ? samples.join(', ') : 'ไม่มีข้อมูลสายเลย'),
            confirmButtonText: 'รับทราบ'
        });
    }
}

function updateLabelStyle(cb) {
    const label = cb.closest('label');
    if (cb.checked) {
        label.classList.add('bg-violet-50', 'border-violet-300');
        label.classList.remove('bg-slate-50', 'border-slate-200');
    } else {
        label.classList.remove('bg-violet-50', 'border-violet-300');
        label.classList.add('bg-slate-50', 'border-slate-200');
    }
}

function deleteTeacher(id, name) {
    Swal.fire({
        title: 'ลบข้อมูลครู?', text: `ต้องการลบครู "${name}" หรือไม่? วิชาที่เกี่ยวข้องจะถูกลบด้วย`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#e11d48', confirmButtonText: 'ยืนยันลบข้อมูล', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if(r.isConfirmed){ submitForm({ action: 'delete_teacher', teacher_id: id }); }
    });
}

async function toggleElective(id, btn) {
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'toggle_elective');
    fd.append('subject_id', id);
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value ?? '');
    try {
        const res  = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'ผิดพลาด');
        const isElective = data.is_elective === 1;
        btn.dataset.elective = isElective ? '1' : '0';
        btn.className = 'elective-btn px-2.5 py-1 rounded-lg font-bold text-xs transition-all hover:scale-105 active:scale-95 '
            + (isElective ? 'bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-500');
        btn.innerHTML = isElective
            ? '<i class="bi bi-star-fill me-1"></i>วิชาเลือก'
            : '<i class="bi bi-lock-fill me-1"></i>บังคับ';
    } catch(e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message, confirmButtonColor: '#2563eb' });
    }
    btn.disabled = false;
}

function editSubject(id, code, name, classroom, teacherId, chatId) {
    const teacherOptions = <?= json_encode(array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name']], $teachers)) ?>;
    const opts = teacherOptions.map(t =>
        `<option value="${t.id}" ${t.id == teacherId ? 'selected' : ''}>${t.name.replace(/"/g,'&quot;')}</option>`
    ).join('');
    Swal.fire({
        title: 'แก้ไขวิชา',
        html: `
        <div class="text-left space-y-3 mt-2">
            <div>
                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">ครูผู้สอน</label>
                <select id="es_teacher" class="swal2-select mt-1" style="width:100%;display:block">${opts}</select>
            </div>
            <div class="flex gap-2">
                <div style="flex:1">
                    <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">รหัสวิชา</label>
                    <input id="es_code" class="swal2-input mt-1" value="${code}">
                </div>
                <div style="flex:1">
                    <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">ห้องเรียน</label>
                    <input id="es_classroom" class="swal2-input mt-1" value="${classroom}">
                </div>
            </div>
            <div>
                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">ชื่อวิชา</label>
                <input id="es_name" class="swal2-input mt-1" value="${name}">
            </div>
            <div>
                <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                    <i class="bi bi-telegram me-1 text-blue-400"></i>Telegram Chat ID (กลุ่มรับแจ้งเตือนเช็คชื่อ)
                </label>
                <input id="es_chat" class="swal2-input mt-1" value="${chatId || ''}" placeholder="-1001234567890 (ว่าง = ใช้ค่ากลางจากระบบ)">
            </div>
        </div>`,
        confirmButtonText: 'บันทึก',
        confirmButtonColor: '#2563eb',
        showCancelButton: true,
        cancelButtonText: 'ยกเลิก',
        focusConfirm: false,
        preConfirm: () => {
            const tid  = document.getElementById('es_teacher').value;
            const c    = document.getElementById('es_code').value.trim();
            const n    = document.getElementById('es_name').value.trim();
            const cls  = document.getElementById('es_classroom').value.trim();
            const chat = document.getElementById('es_chat').value.trim();
            if (!tid || !c || !n || !cls) { Swal.showValidationMessage('กรุณากรอกข้อมูลให้ครบ'); return false; }
            return { tid, c, n, cls, chat };
        }
    }).then(r => {
        if (r.isConfirmed) {
            submitForm({
                action: 'edit_subject', subject_id: id,
                teacher_id: r.value.tid, subject_code: r.value.c,
                subject_name: r.value.n, classroom: r.value.cls,
                telegram_chat_id: r.value.chat
            });
        }
    });
}

function deleteSubject(id, name) {
    Swal.fire({
        title: 'ลบวิชา?', text: `ต้องการลบวิชา "${name}" หรือไม่?`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#e11d48', confirmButtonText: 'ยืนยันลบ', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if(r.isConfirmed){ submitForm({ action: 'delete_subject', subject_id: id }); }
    });
}

function archiveStudent(id, name) {
    Swal.fire({
        title: 'จำหน่ายออก / ปรับสถานะ',
        html: `
        <div class="text-left mt-3">
            <p class="text-sm text-slate-500 mb-4">ปรับสถานะของนักเรียน <strong>${name}</strong> เพื่อให้ไม่ปรากฏในใบเช็คชื่อปัจจุบัน แต่ยังเก็บข้อมูลไว้ในรายงานย้อนหลัง</p>
            <div class="mb-3">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-wider">เลือกสถานะใหม่</label>
                <select id="archive_status" class="swal2-input mt-1" style="width:100%;margin:0;">
                    <option value="moved">ย้ายสถานศึกษา (Moved)</option>
                    <option value="resigned">ลาออก (Resigned)</option>
                    <option value="suspended">พักการเรียน (Suspended)</option>
                    <option value="inactive">ปิดใช้งาน / ไม่อยู่ในรายชื่อ (Inactive)</option>
                    <option value="active">ปกติ (Active)</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-wider">หมายเหตุ / เหตุผล</label>
                <input id="archive_note" class="swal2-input mt-1" style="width:100%;margin:0;" placeholder="เช่น ย้ายไปโรงเรียน... / ลาออกเนื่องจาก...">
            </div>
        </div>`,
        confirmButtonText: 'ยืนยันการปรับสถานะ',
        confirmButtonColor: '#d97706',
        showCancelButton: true,
        cancelButtonText: 'ยกเลิก',
        preConfirm: () => {
            return {
                status: document.getElementById('archive_status').value,
                note: document.getElementById('archive_note').value.trim()
            }
        }
    }).then(r => {
        if (r.isConfirmed) {
            submitForm({ action: 'delete_student', student_db_id: id, status: r.value.status, status_note: r.value.note });
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
            submitForm({ action: 'edit_student', edit_id: id, student_id_code: r.value.sid, student_name: r.value.nm, student_classroom: r.value.cls });
        }
    });
}
</script>

<?php
$swal_tab = 'teachers';
if ($msg && in_array($action, ['add_student','edit_student','delete_student'])) $swal_tab = 'students';
if ($msg && in_array($action, ['add_subject','edit_subject','delete_subject','save_term_settings','copy_subjects_to_sem2'])) $swal_tab = 'subjects';
if ($msg && $action === 'save_enrollment') $swal_tab = 'enrollment';

if ($msg) {
    $safeMsg = addslashes(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'));
    echo "<script>Swal.fire({ icon: '$msgType', title: 'แจ้งเตือน', text: '$safeMsg', timer: 2000, showConfirmButton: false }).then(()=>showTab('$swal_tab'));</script>";
} else {
    $initTab = $_GET['tab'] ?? (isset($_GET['sem']) ? 'subjects' : (isset($_GET['cls']) ? 'students' : 'teachers'));
    echo "<script>document.addEventListener('DOMContentLoaded',()=>showTab('$initTab'));</script>";
}
require_once '../components/layout_end.php';
?>
