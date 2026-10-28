<?php
require_once 'db.php';

// Auto-apply pending roster & stability migrations once if not yet applied
try {
    if (isset($pdo)) {
        $autoMigs = [
            '2026_10_28_000003_force_exact_548_roster',
            '2026_10_28_000004_add_stability_indexes_and_constraints',
            '2026_10_29_000001_fix_roles_advisors_and_telegram_settings',
            '2026_10_29_000002_verify_llw20_and_relink_advisors_by_name',
        ];
        $chkMig = $pdo->prepare("SELECT 1 FROM _migrations WHERE migration = ? LIMIT 1");
        $insMig = $pdo->prepare("INSERT IGNORE INTO _migrations (migration, batch) VALUES (?, 99)");
        foreach ($autoMigs as $migName) {
            $chkMig->execute([$migName]);
            if (!$chkMig->fetchColumn()) {
                $migFile = __DIR__ . '/../database/migrations/' . $migName . '.php';
                if (file_exists($migFile)) {
                    $migDef = require $migFile;
                    if (is_array($migDef) && isset($migDef['up'])) {
                        $migDef['up']($pdo);
                        $insMig->execute([$migName]);
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('[functions auto-migrate] ' . $e->getMessage());
}

// เปิดใช้งาน session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ฟังก์ชันสำหรับ Login
 * - ตรวจ llw_users ก่อน (ระบบรวม)
 * - Fallback ไปตรวจ att_teachers โดยตรง ถ้า llw_users ยังไม่มี/ยังไม่ได้ import SQL
 */
function loginTeacher($username, $password, $pdo) {
    // ── 1. ตรวจจาก llw_users (unified) ──────────────────────────────────
    try {
        $stmt = $pdo->prepare("SELECT * FROM llw_users WHERE username = :u AND role IN ('super_admin','att_teacher') AND status='active' LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // ดึง att_teachers.id เพื่อใช้เป็น FK ใน att_attendance
            $tStmt = $pdo->prepare("SELECT id, name FROM att_teachers WHERE username = :u LIMIT 1");
            $tStmt->execute([':u' => $username]);
            $teacher = $tStmt->fetch();

            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['llw_role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = trim($user['firstname'] . ' ' . $user['lastname']);

            // Ensure a record exists in att_teachers (especially for admins)
            $teacher = ensureTeacherRecord($user['username'], $_SESSION['fullname'], $user['user_id'], $pdo);
            
            $_SESSION['teacher_id']   = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'];

            // อัปเดต last_login
            $pdo->prepare("UPDATE llw_users SET last_login=NOW() WHERE user_id=?")->execute([$user['user_id']]);
            return true;
        }
    } catch (PDOException $e) {
        // llw_users ยังไม่มี → ใช้ Fallback
    }

    // ── 2. Fallback: att_teachers เดิม (legacy ก่อนนำเข้า llw_db.sql) ─────────────
    try {
        $stmt = $pdo->prepare("SELECT * FROM att_teachers WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $teacher = $stmt->fetch();

        if ($teacher && password_verify($password, $teacher['password'])) {
            $_SESSION['teacher_id']   = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'];
            $_SESSION['llw_role']     = 'att_teacher';
            return true;
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * ตรวจสอบการ Login (Standardized for Unified System)
 */
function checkLogin() {
    global $base_path, $pdo;
    
    // 1. ตรวจสอบ Role หลัก
    if (!isset($_SESSION['llw_role'])) {
        header("Location: " . $base_path . "/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }

    // 2. ถ้ามี Role แต่ไม่มี teacher_id (หรือเป็น 0)
    // ต้องทำให้แน่ใจว่าทุกคนที่เข้าหน้านี้มีเรคอร์ดใน att_teachers เพื่อป้องกัน FK Violation
    if (!isset($_SESSION['teacher_id']) || (int)$_SESSION['teacher_id'] === 0) {
        $username = $_SESSION['username'] ?? '';
        $fullname = $_SESSION['fullname'] ?? ($_SESSION['firstname'] ?? 'Admin');
        $user_id  = $_SESSION['user_id'] ?? 0;

        if ($user_id > 0) {
            $teacher = ensureTeacherRecord($username, $fullname, $user_id, $pdo);
            $_SESSION['teacher_id'] = $teacher['id'] ?? 0;
            $_SESSION['teacher_name'] = $teacher['name'] ?? $fullname;
        } else {
            $_SESSION['teacher_id'] = 0;
        }
    }
}

/**
 * ตรวจความอนุญาตเฉพาะ Admin (Super Admin / WFH Admin)
 */
function checkAdmin() {
    global $base_path;
    checkLogin();
    if ($_SESSION['llw_role'] !== 'super_admin') {
        header('Location: ' . $base_path . '/attendance_system/dashboard.php');
        exit();
    }
}

/**
 * ฟังก์ชันช่วยตรวจสอบและสร้างเรคอร์ดครู (แก้ปัญหา FK violation สำหรับ Admin)
 */
function ensureTeacherRecord($username, $fullname, $llw_user_id, $pdo) {
    // 1. ลองหาจาก llw_user_id ก่อน
    $stmt = $pdo->prepare("SELECT id, name FROM att_teachers WHERE llw_user_id = ? LIMIT 1");
    $stmt->execute([$llw_user_id]);
    $teacher = $stmt->fetch();
    if ($teacher) return $teacher;

    // 2. ลองหาจาก username
    $stmt = $pdo->prepare("SELECT id, name FROM att_teachers WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $teacher = $stmt->fetch();
    if ($teacher) {
        // แมพ llw_user_id ย้อนหลังให้
        $pdo->prepare("UPDATE att_teachers SET llw_user_id = ? WHERE id = ?")->execute([$llw_user_id, $teacher['id']]);
        return $teacher;
    }

    // 3. ไม่เจอเลย -> สร้างใหม่ (สำหรับ Admin ที่ไม่เคยใช้งานระบบเช็คชื่อ)
    $stmt = $pdo->prepare("INSERT INTO att_teachers (name, username, password, llw_user_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$fullname, $username, '', $llw_user_id]);
    $id = $pdo->lastInsertId();
    return ['id' => $id, 'name' => $fullname];
}

/**
 * ดึงรายวิชาที่ครูสอน
 * teacher_id=0 หมายถึง super_admin/wfh_admin → ดึงทั้งหมด
 */
function getTeacherSubjects($teacher_id, $pdo, $semester = null, $academic_year = null) {
    $hasSemCol = function_exists('llw_ensure_semester_schema') && llw_ensure_semester_schema($pdo);
    if ($hasSemCol) {
        $sem  = $semester !== null ? (int)$semester : llw_current_semester();
        $year = $academic_year !== null ? (int)$academic_year : llw_current_academic_year();
        if ($sem > 0 && $year > 0) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM att_subjects WHERE academic_year = ? AND semester = ?");
            $chk->execute([$year, $sem]);
            if ((int)$chk->fetchColumn() === 0 && $sem === 2) $sem = 1;
            if ((int)$teacher_id === 0) {
                $stmt = $pdo->prepare("SELECT s.*, COALESCE(t.name, 'ไม่ระบุครู') as teacher_name FROM att_subjects s LEFT JOIN att_teachers t ON t.id = s.teacher_id WHERE s.academic_year = :yr AND s.semester = :sem ORDER BY s.subject_code ASC");
                $stmt->execute(['yr' => $year, 'sem' => $sem]);
                return $stmt->fetchAll();
            }
            $stmt = $pdo->prepare("SELECT s.*, COALESCE(t.name, 'ไม่ระบุครู') as teacher_name FROM att_subjects s LEFT JOIN att_teachers t ON t.id = s.teacher_id WHERE s.teacher_id = :teacher_id AND s.academic_year = :yr AND s.semester = :sem ORDER BY s.subject_code ASC");
            $stmt->execute(['teacher_id' => $teacher_id, 'yr' => $year, 'sem' => $sem]);
            return $stmt->fetchAll();
        }
    }
    if ((int)$teacher_id === 0) {
        // admin ดูทุกวิชา (LEFT JOIN เผื่อวิชาที่ยังไม่มีครูผูก)
        return $pdo->query("SELECT s.*, COALESCE(t.name, 'ไม่ระบุครู') as teacher_name FROM att_subjects s LEFT JOIN att_teachers t ON t.id = s.teacher_id ORDER BY s.subject_code ASC")->fetchAll();
    }
    $stmt = $pdo->prepare("SELECT s.*, COALESCE(t.name, 'ไม่ระบุครู') as teacher_name FROM att_subjects s LEFT JOIN att_teachers t ON t.id = s.teacher_id WHERE s.teacher_id = :teacher_id ORDER BY s.subject_code ASC");
    $stmt->execute(['teacher_id' => $teacher_id]);
    return $stmt->fetchAll();
}

/**
 * ดึงข้อมูลรายวิชาจาก ID
 */
function getSubjectById($subject_id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM att_subjects WHERE id = :subject_id LIMIT 1");
    $stmt->execute(['subject_id' => $subject_id]);
    return $stmt->fetch();
}

/**
 * ดึงรายชื่อนักเรียนในห้อง
 */
function getStudentsByClassroom($classroom, $pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM att_students
        WHERE classroom = :classroom
          AND status = 'active'
          AND student_id REGEXP '^[0-9]+$'
          AND student_id NOT IN (SELECT subject_code FROM att_subjects)
        ORDER BY student_id ASC
    ");
    $stmt->execute(['classroom' => $classroom]);
    return $stmt->fetchAll();
}

/**
 * ดึงรายชื่อนักเรียนสำหรับวิชา (รองรับทั้งวิชาบังคับและวิชาเลือก)
 * - is_elective=0 → ดึงนักเรียนทั้งห้อง (เหมือนเดิม)
 * - is_elective=1 → ดึงเฉพาะคนที่ลงทะเบียนใน att_subject_students
 */
function getStudentsBySubject($subject_id, $pdo) {
    $sub = $pdo->prepare("SELECT * FROM att_subjects WHERE id=? LIMIT 1");
    $sub->execute([$subject_id]);
    $subject = $sub->fetch();
    if (!$subject) return [];

    if (!empty($subject['is_elective'])) {
        // วิชาเลือก: ดึงจาก enrollment table
        $stmt = $pdo->prepare("
            SELECT s.* FROM att_students s
            JOIN att_subject_students ss ON ss.student_id = s.id
            WHERE ss.subject_id = ?
              AND s.status = 'active'
            ORDER BY s.student_id ASC
        ");
        $stmt->execute([$subject_id]);
    } else {
        // วิชาบังคับ: ดึงทั้งห้อง
        // กรอง 3 ชั้น: (1) รหัสนักเรียนต้องเป็นตัวเลขล้วน (04742 ฯลฯ)
        //              (2) ห้ามซ้ำกับ subject_code ในตารางวิชา
        //              (3) ต้องมีสถานะเป็น 'active' เท่านั้น
        $stmt = $pdo->prepare("
            SELECT s.* FROM att_students s
            WHERE s.classroom = ?
              AND s.status = 'active'
              AND s.student_id REGEXP '^[0-9]+$'
              AND s.student_id NOT IN (SELECT subject_code FROM att_subjects)
            ORDER BY s.student_id ASC
        ");
        $stmt->execute([$subject['classroom']]);
    }
    return $stmt->fetchAll();
}

/**
 * แปลงเวลา (string) เป็นนาที เพื่อการคำนวณง่ายๆ (เช่น "08:40" -> 520)
 */
function timeToMinutes($timeStr) {
    if (empty($timeStr) || strpos($timeStr, ':') === false) return 0;
    list($hours, $minutes) = explode(':', $timeStr);
    return ((int)$hours * 60) + (int)$minutes;
}

/**
 * บันทึกการเช็คชื่อ (ใช้ att_students.id เป็น integer FK และป้องกันการกดบันทึกซ้ำ)
 */
function saveAttendance($date, $period, $subject_id, $teacher_id, $student_id, $status, $time_in, $start_time, $note, $pdo) {
    $student_db_id = (int)$student_id;
    if ($student_db_id <= 0) {
        return false;
    }

    // 1. Calculate Late Status automatically
    if ($status === 'มา' && !empty($time_in) && !empty($start_time)) {
        $timeInMinutes = timeToMinutes($time_in);
        $startTimeMinutes = timeToMinutes($start_time);
        if ($timeInMinutes > ($startTimeMinutes + 5)) {
            $status = 'สาย';
        }
    }

    // 2. Check for existing record
    $checkStmt = $pdo->prepare("SELECT id FROM att_attendance WHERE date = :date AND period = :period AND subject_id = :subject_id AND student_id = :student_id LIMIT 1");
    $checkStmt->execute([
        'date' => $date,
        'period' => $period,
        'subject_id' => $subject_id,
        'student_id' => $student_db_id
    ]);
    $existing = $checkStmt->fetch();

    $params = [
        'status'     => $status,
        'time_in'    => empty($time_in) ? null : $time_in,
        'note'       => $note,
        'teacher_id' => $teacher_id ?: 0
    ];

    if ($existing) {
        // Update
        $params['id'] = $existing['id'];
        $stmt = $pdo->prepare("UPDATE att_attendance SET status=:status, time_in=:time_in, note=:note, teacher_id=:teacher_id WHERE id=:id");
        return $stmt->execute($params);
    } else {
        // Insert
        $params['date']       = $date;
        $params['period']     = $period;
        $params['subject_id'] = $subject_id;
        $params['student_id'] = $student_db_id;
        $stmt = $pdo->prepare("INSERT INTO att_attendance (date, period, subject_id, teacher_id, student_id, status, time_in, note) 
                              VALUES (:date, :period, :subject_id, :teacher_id, :student_id, :status, :time_in, :note)
                              ON DUPLICATE KEY UPDATE status=VALUES(status), time_in=VALUES(time_in), note=VALUES(note), teacher_id=VALUES(teacher_id)");
        return $stmt->execute($params);
    }
}

/**
 * ซิงค์ข้อมูลนักเรียนรายบุคคลไปยังโมดูลอื่นๆ (Assembly, Behavior, Bus, Chromebook)
 * เพื่อให้ทุกโมดูลมีข้อมูลตรงกับ att_students เสมอ (Single Source of Truth)
 */
function llw_sync_student_to_modules(PDO $pdo, string $student_id, string $name, string $classroom, string $status = 'active'): void {
    $sid = preg_match('/^\d+$/', trim($student_id)) ? str_pad(trim($student_id), 5, '0', STR_PAD_LEFT) : trim($student_id);
    if ($sid === '') return;

    $isActive = ($status === 'active');

    // 1. assembly_students
    try {
        if ($isActive) {
            $st = $pdo->prepare("INSERT INTO assembly_students (student_id, name, classroom) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), classroom=VALUES(classroom)");
            $st->execute([$sid, $name, $classroom]);
        } else {
            $pdo->prepare("DELETE FROM assembly_students WHERE student_id = ?")->execute([$sid]);
        }
    } catch (Throwable $e) {}

    // 2. beh_students
    try {
        if ($isActive) {
            $level = $classroom;
            $room  = '';
            if (strpos($classroom, '/') !== false) {
                [$level, $room] = array_map('trim', explode('/', $classroom, 2));
            }
            $st = $pdo->prepare("INSERT INTO beh_students (student_id, name, level, room, status) VALUES (?, ?, ?, ?, 'active') ON DUPLICATE KEY UPDATE name=VALUES(name), level=VALUES(level), room=VALUES(room), status='active'");
            $st->execute([$sid, $name, $level, $room]);
        } else {
            $pdo->prepare("UPDATE beh_students SET status = 'inactive' WHERE student_id = ?")->execute([$sid]);
        }
    } catch (Throwable $e) {}

    // 3. bus_students
    try {
        if ($isActive) {
            $pdo->prepare("UPDATE bus_students SET name = ?, classroom = ? WHERE student_id = ?")->execute([$name, $classroom, $sid]);
        } else {
            $pdo->prepare("UPDATE bus_students SET is_active = 0 WHERE student_id = ?")->execute([$sid]);
        }
    } catch (Throwable $e) {}

    // 4. cb_students
    try {
        if ($isActive) {
            $st = $pdo->prepare("INSERT INTO cb_students (student_id, name, class_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), class_name=VALUES(class_name)");
            $st->execute([$sid, $name, $classroom]);
        }
    } catch (Throwable $e) {}
}

/**
 * ซิงค์รายชื่อนักเรียนที่ active ทั้งหมดจาก att_students ไปยังโมดูลอื่นๆ
 */
function llw_sync_all_active_students_to_modules(PDO $pdo): void {
    try {
        $rows = $pdo->query("
            SELECT student_id, name, classroom
            FROM att_students
            WHERE status = 'active'
              AND student_id REGEXP '^[0-9]+$'
              AND student_id NOT IN (SELECT subject_code FROM att_subjects)
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return;
    }

    if (empty($rows)) return;

    $activeSids = array_values(array_unique(array_column($rows, 'student_id')));
    $phSids = implode(',', array_fill(0, count($activeSids), '?'));

    // 1. assembly_students
    try {
        $pdo->prepare("DELETE FROM assembly_students WHERE student_id NOT IN ($phSids)")->execute($activeSids);
        $stAsm = $pdo->prepare("INSERT INTO assembly_students (student_id, name, classroom) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), classroom=VALUES(classroom)");
        foreach ($rows as $r) {
            $stAsm->execute([$r['student_id'], $r['name'], $r['classroom']]);
        }
    } catch (Throwable $e) {}

    // 2. beh_students
    try {
        $pdo->prepare("UPDATE beh_students SET status = 'inactive' WHERE student_id NOT IN ($phSids)")->execute($activeSids);
        $stBeh = $pdo->prepare("INSERT INTO beh_students (student_id, name, level, room, status) VALUES (?, ?, ?, ?, 'active') ON DUPLICATE KEY UPDATE name=VALUES(name), level=VALUES(level), room=VALUES(room), status='active'");
        foreach ($rows as $r) {
            $level = $r['classroom'];
            $room  = '';
            if (strpos($r['classroom'], '/') !== false) {
                [$level, $room] = array_map('trim', explode('/', $r['classroom'], 2));
            }
            $stBeh->execute([$r['student_id'], $r['name'], $level, $room]);
        }
    } catch (Throwable $e) {}

    // 3. bus_students
    try {
        $pdo->prepare("UPDATE bus_students SET is_active = 0 WHERE student_id NOT IN ($phSids)")->execute($activeSids);
        $stBus = $pdo->prepare("UPDATE bus_students SET name = ?, classroom = ? WHERE student_id = ?");
        foreach ($rows as $r) {
            $stBus->execute([$r['name'], $r['classroom'], $r['student_id']]);
        }
    } catch (Throwable $e) {}

    // 4. cb_students
    try {
        $stCb = $pdo->prepare("INSERT INTO cb_students (student_id, name, class_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), class_name=VALUES(class_name)");
        foreach ($rows as $r) {
            $stCb->execute([$r['student_id'], $r['name'], $r['classroom']]);
        }
    } catch (Throwable $e) {}
}

/**
 * ดึงรายชื่อนักเรียนกลุ่มเสี่ยง (มส.) ทั่วทั้งระบบหรือตามครู
 * เกณฑ์: มาเรียน < 80% (นับจากคาบที่เช็คชื่อไปแล้ว)
 */
function getStudentsAtRisk($teacher_id, $pdo, $limit = 5) {
    $where = $teacher_id > 0 ? "WHERE s.status = 'active' AND sj.teacher_id = :tid" : "WHERE s.status = 'active'";
    $sql = "
        SELECT 
            s.id, s.student_id, s.name, s.classroom,
            COUNT(a.id) as sessions_count,
            SUM(CASE WHEN a.status='มา' THEN 1 ELSE 0 END) as present_count,
            ROUND((SUM(CASE WHEN a.status='มา' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1) as presence_rate
        FROM att_students s
        JOIN att_attendance a ON a.student_id = s.id
        JOIN att_subjects sj ON sj.id = a.subject_id
        $where
        GROUP BY s.id
        HAVING sessions_count > 3 AND presence_rate < 80
        ORDER BY presence_rate ASC
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($sql);
    if ($teacher_id > 0) $stmt->bindValue(':tid', $teacher_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * ดึงรายชื่อนักเรียนดีเด่น (Diligent) - มาเรียน 100%
 */
function getStudentHighlights($teacher_id, $pdo, $limit = 5) {
    $where = $teacher_id > 0 ? "WHERE s.status = 'active' AND sj.teacher_id = :tid" : "WHERE s.status = 'active'";
    $sql = "
        SELECT 
            s.id, s.student_id, s.name, s.classroom,
            COUNT(a.id) as sessions_count,
            ROUND((SUM(CASE WHEN a.status='มา' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1) as presence_rate
        FROM att_students s
        JOIN att_attendance a ON a.student_id = s.id
        JOIN att_subjects sj ON sj.id = a.subject_id
        $where
        GROUP BY s.id
        HAVING sessions_count > 5 AND presence_rate >= 95
        ORDER BY sessions_count DESC, presence_rate DESC
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($sql);
    if ($teacher_id > 0) $stmt->bindValue(':tid', $teacher_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
?>
