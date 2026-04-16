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

            if ($teacher) {
                $_SESSION['teacher_id']   = $teacher['id'];
                $_SESSION['teacher_name'] = $teacher['name'];
            } else {
                // super_admin ที่ไม่มีเรคอร์ด att_teachers: ใช้ virtual id
                $_SESSION['teacher_id']   = 0;
                $_SESSION['teacher_name'] = trim($user['firstname'] . ' ' . $user['lastname']);
            }

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
 * ตรวจสอบการ Login (teacher/admin)
 */
function checkLogin() {
    global $base_path;
    if (!isset($_SESSION['teacher_id'])) {
        header("Location: " . $base_path . "/attendance_system/index.php"); 
        exit();
    }
}

/**
 * ตรวจ Super Admin สำหรับหน้า admin.php
 */
function checkAdmin() {
    global $base_path;
    if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
        header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
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
    list($hours, $minutes) = explode(':', $timeStr);
    return ($hours * 60) + $minutes;
}

/**
 * บันทึกการเช็คชื่อ
 */
function saveAttendance($date, $period, $subject_id, $teacher_id, $student_id, $status, $time_in, $start_time, $note, $pdo) {
    // คำนวณสถานะสายอัตโนมัติ (เฉพาะกรณีที่สถานะไม่ใช่ ขาด ลา โดด และมีเวลาเข้ามา)
    // ถ้าเวลาเข้า > เวลาเริ่มคาบ + 5 นาที -> สาย
    if ($status === 'มา' && !empty($time_in) && !empty($start_time)) {
        $timeInMinutes = timeToMinutes($time_in);
        $startTimeMinutes = timeToMinutes($start_time);
        
        if ($timeInMinutes > ($startTimeMinutes + 5)) {
            $status = 'สาย';
        }
    }

    try {
        // ตรวจสอบว่าเคยเช็คชื่อในคาบนี้ วิชาและวันนี้ไปแล้วหรือยัง
        $checkStmt = $pdo->prepare("SELECT id FROM att_attendance WHERE date = :date AND period = :period AND subject_id = :subject_id AND student_id = :student_id");
        $checkStmt->execute([
            'date' => $date,
            'period' => $period,
            'subject_id' => $subject_id,
            'student_id' => $student_id
        ]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // Update
            $stmt = $pdo->prepare("UPDATE att_attendance 
                                  SET status = :status, time_in = :time_in, note = :note, teacher_id = :teacher_id
                                  WHERE id = :id");
            return $stmt->execute([
                'status' => $status,
                'time_in' => empty($time_in) ? null : $time_in,
                'note' => $note,
                'teacher_id' => $teacher_id, // อัปเดต teacher_id ด้วย (สำหรับกรณี Super Admin)
                'id' => $existing['id']
            ]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO att_attendance (date, period, subject_id, teacher_id, student_id, status, time_in, note) 
                                  VALUES (:date, :period, :subject_id, :teacher_id, :student_id, :status, :time_in, :note)");
            return $stmt->execute([
                'date' => $date,
                'period' => $period,
                'subject_id' => $subject_id,
                'teacher_id' => $teacher_id,
                'student_id' => $student_id,
                'status' => $status,
                'time_in' => empty($time_in) ? null : $time_in,
                'note' => $note
            ]);
        }
    } catch (PDOException $e) {
        error_log("Save Attendance Error: " . $e->getMessage());
        return false;
    }
}
?>
