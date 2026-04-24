<?php
/**
 * assembly/api/get_students.php
 * GET ?classroom=ม.1/1 — ดึงรายชื่อนักเรียนในห้อง + existing attendance
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$classroom = trim($_GET['classroom'] ?? '');
$date      = trim($_GET['date'] ?? date('Y-m-d'));

if ($classroom === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุห้องเรียน']);
    exit;
}

try {
    $pdo = getPdo();

    // ตรวจสิทธิ์ att_teacher → ต้องเป็นห้องตัวเอง (Central Table: llw_class_advisors)
    if ($_SESSION['llw_role'] === 'att_teacher') {
        $userId = $_SESSION['user_id'] ?? 0;
        $check  = $pdo->prepare("SELECT id FROM llw_class_advisors WHERE classroom = ? AND user_id = ?");
        $check->execute([$classroom, $userId]);
        if (!$check->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงห้องนี้']);
            exit;
        }
    }

    // ดึงนักเรียนจาก Central Table (att_students)
    if ($classroom === 'all') {
        $stmt = $pdo->query("SELECT student_id, name, classroom FROM att_students WHERE academic_year = 2569 ORDER BY classroom, student_id");
        $students = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT student_id, name, classroom
            FROM att_students
            WHERE classroom = ? AND academic_year = 2569
            ORDER BY student_id
        ");
        $stmt->execute([$classroom]);
        $students = $stmt->fetchAll();
    }

    // ดึง attendance (Morning)
    $existingMorning = [];
    $existingEvening = [];
    $teacherName = '';
    
    if ($classroom !== 'all') {
        // Morning
        $attStmt = $pdo->prepare("
            SELECT student_id, status, nail, hair, shirt, pants, socks, shoes, note
            FROM assembly_attendance
            WHERE classroom = ? AND date = ?
        ");
        $attStmt->execute([$classroom, $date]);
        foreach ($attStmt->fetchAll() as $row) {
            $existingMorning[$row['student_id']] = $row;
        }

        // Evening (Checkout)
        $chkStmt = $pdo->prepare("
            SELECT student_id, status, note
            FROM assembly_checkout
            WHERE classroom = ? AND date = ?
        ");
        $chkStmt->execute([$classroom, $date]);
        foreach ($chkStmt->fetchAll() as $row) {
            $existingEvening[$row['student_id']] = $row;
        }

        // Get Advisors from Central Table
        $teacherQuery = $pdo->prepare("
            SELECT CONCAT(u.firstname, ' ', u.lastname) as full_name 
            FROM llw_class_advisors a
            JOIN llw_users u ON a.user_id = u.user_id
            WHERE a.classroom = ?
            ORDER BY a.role_type ASC
        ");
        $teacherQuery->execute([$classroom]);
        $teacherName = implode(', ', $teacherQuery->fetchAll(PDO::FETCH_COLUMN));
    }

    // Merge
    $result = [];
    foreach ($students as $s) {
        $sid = $s['student_id'];
        $mAtt = $existingMorning[$sid] ?? null;
        $eAtt = $existingEvening[$sid] ?? null;

        $result[] = [
            'student_id'     => $sid,
            'name'           => $s['name'],
            'classroom'      => $s['classroom'],
            'teacher'        => $teacherName,
            'status'         => $mAtt['status'] ?? null,  // Keep 'status' for back-compat in first tab
            'morning_status' => $mAtt['status'] ?? null,
            'evening_status' => $eAtt['status'] ?? null,
            'nail'           => $mAtt['nail']   ?? null,
            'hair'           => $mAtt['hair']   ?? null,
            'shirt'          => $mAtt['shirt']  ?? null,
            'pants'          => $mAtt['pants']  ?? null,
            'socks'          => $mAtt['socks']  ?? null,
            'shoes'          => $mAtt['shoes']  ?? null,
            // Use evening note if morning note is empty, but usually we handle them separately.
            // For back-compat, we'll keep 'note' pointing to morning for now as requested.
            'note'           => $mAtt['note']   ?? '',
            'evening_note'   => $eAtt['note']   ?? '',
        ];
    }

    echo json_encode(['status' => 'success', 'students' => $result, 'teacher' => $teacherName]);
} catch (Exception $e) {
    error_log('[Assembly] get_students: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
