<?php
/**
 * assembly/api/get_classroom_report.php
 * GET ?classroom=&date= — Data for printable classroom report
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
$date      = trim($_GET['date']      ?? date('Y-m-d'));

if (!$classroom) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุห้องเรียน']);
    exit;
}

try {
    $pdo = getPdo();

    // 1. Classroom Info
    $cStmt = $pdo->prepare("SELECT classroom, teacher_name FROM assembly_classrooms WHERE classroom = ?");
    $cStmt->execute([$classroom]);
    $classInfo = $cStmt->fetch();
    if (!$classInfo) {
        $classInfo = ['classroom' => $classroom, 'teacher_name' => '-'];
    }

    // 2. All Students in this room
    $sStmt = $pdo->prepare("SELECT student_id, name FROM assembly_students WHERE classroom = ? ORDER BY student_id");
    $sStmt->execute([$classroom]);
    $students = $sStmt->fetchAll();

    // 3. Today's Attendance
    $aStmt = $pdo->prepare("SELECT student_id, status FROM assembly_attendance WHERE classroom = ? AND date = ?");
    $aStmt->execute([$classroom, $date]);
    $todayRecords = $aStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 4. Cumulative Stats (Sum for semester/year)
    // We calculate counts for each student in this classroom across all recorded dates
    $hStmt = $pdo->prepare("
        SELECT 
            student_id,
            SUM(status = 'ม') AS total_present,
            SUM(status = 'ข') AS total_absent,
            SUM(status = 'ล') AS total_leave,
            SUM(status = 'ด') AS total_skip
        FROM assembly_attendance
        WHERE classroom = ?
        GROUP BY student_id
    ");
    $hStmt->execute([$classroom]);
    $histData = $hStmt->fetchAll(PDO::FETCH_UNIQUE);

    // 5. System Settings (for School Name)
    $settings = $pdo->query("SELECT * FROM wfh_system_settings WHERE setting_id = 1")->fetch();
    $schoolName = "โรงเรียนละลมวิทยา"; // Default

    // Combine Data
    $studentList = [];
    $summary = ['total' => count($students), 'present' => 0, 'absent' => 0, 'leave' => 0, 'skip' => 0];

    foreach ($students as $s) {
        $sid = $s['student_id'];
        $todayStatus = $todayRecords[$sid] ?? null;
        
        if ($todayStatus) {
            match($todayStatus) {
                'ม' => $summary['present']++,
                'ข' => $summary['absent']++,
                'ล' => $summary['leave']++,
                'ด' => $summary['skip']++,
                default => null
            };
        }

        $studentList[] = [
            'id'    => $sid,
            'name'  => $s['name'],
            'today' => $todayStatus,
            'stats' => [
                'm' => (int)($histData[$sid]['total_present'] ?? 0),
                'k' => (int)($histData[$sid]['total_absent']  ?? 0),
                'l' => (int)($histData[$sid]['total_leave']   ?? 0),
                'd' => (int)($histData[$sid]['total_skip']    ?? 0),
            ]
        ];
    }

    echo json_encode([
        'status'     => 'success',
        'school'     => $schoolName,
        'date'       => $date,
        'classroom'  => $classInfo,
        'summary'    => $summary,
        'students'   => $studentList
    ]);

} catch (Exception $e) {
    error_log('[Assembly] get_classroom_report: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
