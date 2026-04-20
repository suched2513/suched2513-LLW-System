<?php
require_once 'db.php';

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

    // 2. ถ้ามี Role แต่ไม่มี teacher_id (กรณีย้ายเครื่อง หรือ Session บางส่วนหาย)
    // พยายามดึง teacher_id จาก username หรือกำหนดเป็น 0 สำหรับ admin
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
    if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
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
    $stmt = $pdo->prepare("INSERT INTO att_teachers (name, username, llw_user_id) VALUES (?, ?, ?)");
    $stmt->execute([$fullname, $username, $llw_user_id]);
    $id = $pdo->lastInsertId();
    return ['id' => $id, 'name' => $fullname];
}

/**
 * ดึงรายวิชาที่ครูสอน
 * teacher_id=0 หมายถึง super_admin → ดึงทั้งหมด
 */
function getTeacherSubjects($teacher_id, $pdo) {
    if ((int)$teacher_id === 0) {
        // super_admin ดูทุกวิชา
        return $pdo->query("SELECT s.*, t.name as teacher_name FROM att_subjects s JOIN att_teachers t ON t.id=s.teacher_id ORDER BY t.name,s.subject_code")->fetchAll();
    }
    $stmt = $pdo->prepare("SELECT * FROM att_subjects WHERE teacher_id = :teacher_id ORDER BY subject_code ASC");
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
    $stmt = $pdo->prepare("SELECT * FROM att_students WHERE classroom = :classroom ORDER BY student_id ASC");
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
            ORDER BY s.student_id ASC
        ");
        $stmt->execute([$subject_id]);
    } else {
        // วิชาบังคับ: ดึงทั้งห้อง
        $stmt = $pdo->prepare("SELECT * FROM att_students WHERE classroom=? ORDER BY student_id ASC");
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
 * บันทึกการเช็คชื่อ
 */
function saveAttendance($date, $period, $subject_id, $teacher_id, $student_id, $status, $time_in, $start_time, $note, $pdo) {
    // 0. Normalize student ID
    if (preg_match('/^\d+$/', $student_id)) {
        $student_id = str_pad($student_id, 5, '0', STR_PAD_LEFT);
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
    $checkStmt = $pdo->prepare("SELECT id FROM att_attendance WHERE date = :date AND period = :period AND subject_id = :subject_id AND student_id = :student_id");
    $checkStmt->execute([
        'date' => $date,
        'period' => $period,
        'subject_id' => $subject_id,
        'student_id' => $student_id
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
        $params['student_id'] = $student_id;
        $stmt = $pdo->prepare("INSERT INTO att_attendance (date, period, subject_id, teacher_id, student_id, status, time_in, note) 
                              VALUES (:date, :period, :subject_id, :teacher_id, :student_id, :status, :time_in, :note)");
        return $stmt->execute($params);
    }
}

/**
 * ดึงรายชื่อนักเรียนกลุ่มเสี่ยง (มส.) ทั่วทั้งระบบหรือตามครู
 * เกณฑ์: มาเรียน < 80% (นับจากคาบที่เช็คชื่อไปแล้ว)
 */
function getStudentsAtRisk($teacher_id, $pdo, $limit = 5) {
    $where = $teacher_id > 0 ? "WHERE sj.teacher_id = :tid" : "";
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
    $where = $teacher_id > 0 ? "WHERE sj.teacher_id = :tid" : "";
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
