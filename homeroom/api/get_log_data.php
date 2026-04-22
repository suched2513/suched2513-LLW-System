<?php
/**
 * homeroom/api/get_log_data.php
 * ดึงข้อมูลนักเรียน, สถิติการเช็คชื่อหน้าเสาธง, และหัวข้อโฮมรูม
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$classroom = $_GET['classroom'] ?? null;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

if (!$classroom || !$start_date || !$end_date) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo = getPdo();
    
    // 1. ดึงรายชื่อนักเรียนจากฐานข้อมูลกลาง (att_students)
    $stmt = $pdo->prepare("SELECT student_id, name FROM att_students WHERE classroom = ? ORDER BY student_id");
    $stmt->execute([$classroom]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. ดึงข้อมูลครูที่ปรึกษาจากฐานข้อมูลกลาง (llw_class_advisors)
    $stmt = $pdo->prepare("
        SELECT u.firstname, u.lastname 
        FROM llw_class_advisors a
        JOIN llw_users u ON a.user_id = u.user_id
        WHERE a.classroom = ?
    ");
    $stmt->execute([$classroom]);
    $advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. ดึงหัวข้อโฮมรูมรายวัน (homeroom_logs)
    $stmt = $pdo->prepare("SELECT log_date, topic FROM homeroom_logs WHERE classroom = ? AND log_date BETWEEN ? AND ?");
    $stmt->execute([$classroom, $start_date, $end_date]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $logsByDate = [];
    foreach ($logs as $l) $logsByDate[$l['log_date']] = $l['topic'];

    // 4. ดึงสถิติการเช็คชื่อเข้าแถวหน้าเสาธง (assembly_attendance)
    $stmt = $pdo->prepare("
        SELECT student_id, date, status 
        FROM assembly_attendance 
        WHERE classroom = ? AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$classroom, $start_date, $end_date]);
    $att = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $attByStudentAndDate = [];
    foreach ($att as $a) {
        $attByStudentAndDate[$a['student_id']][$a['date']] = $a['status'];
    }

    // 5. ดึงรูปภาพกิจกรรม (homeroom_photos)
    $stmt = $pdo->prepare("SELECT log_date, image_path FROM homeroom_photos WHERE classroom = ? AND log_date BETWEEN ? AND ?");
    $stmt->execute([$classroom, $start_date, $end_date]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $photosByDate = [];
    foreach ($photos as $p) $photosByDate[$p['log_date']] = $p['image_path'];

    echo json_encode([
        'status' => 'success',
        'data' => [
            'students' => $students,
            'advisors' => $advisors,
            'logs' => $logsByDate,
            'attendance' => $attByStudentAndDate,
            'photos' => $photosByDate
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
        'debug' => 'โปรดตรวจสอบว่าได้รัน /homeroom/api/init.php หรือยัง'
    ]);
}
