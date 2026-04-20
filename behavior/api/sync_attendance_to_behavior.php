<?php
/**
 * API: Sync Attendance to Behavior
 * แปลงข้อมูลการขาดเข้าแถว/แต่งกายผิดระเบียบ จากระบบ Assembly มาเป็นบันทึกคะแนนในระบบพฤติกรรม
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการนี้']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$date = $input['date'] ?? date('Y-m-d');
$teacherId = $_SESSION['user_id'] ?? 0;
$teacherName = ($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '');

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // 1. ดึงรายการขาดเข้าแถวจาก assembly_attendance
    // มองหาตัวย่อ 'ข' (ขาด) และ 'ด' (โดด)
    $stmt = $pdo->prepare("
        SELECT a.student_id, a.status, s.name as student_name
        FROM assembly_attendance a
        JOIN assembly_students s ON a.student_id = s.student_id
        WHERE a.date = ? AND a.status IN ('ข', 'ด')
    ");
    $stmt->execute([$date]);
    $absents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    $dupes = 0;

    foreach ($absents as $ab) {
        $sid = $ab['student_id'];
        $sName = $ab['student_name'];
        $actName = ($ab['status'] === 'ข') ? 'ขาดการเข้าแถว' : 'โดดเข้าแถว/ไม่เข้าแถว';
        
        // เช็คว่าเคยบันทึกพฤติกรรมประเภทนี้ในวันนี้หรือยัง (ป้องกันซ้ำ)
        $check = $pdo->prepare("SELECT id FROM beh_records WHERE student_id = ? AND record_date = ? AND activity = ?");
        $check->execute([$sid, $date, $actName]);
        
        if (!$check->fetch()) {
            // ดึงคะแนนจาก template
            $tpl = $pdo->prepare("SELECT score FROM beh_templates WHERE name = ? LIMIT 1");
            $tpl->execute([$actName]);
            $score = $tpl->fetchColumn() ?: 5;

            // บันทึก
            $ins = $pdo->prepare("
                INSERT INTO beh_records 
                (student_id, student_name, record_date, type, activity, score, teacher_name, teacher_user_id) 
                VALUES (?, ?, ?, 'ความผิด', ?, ?, ?, ?)
            ");
            $ins->execute([$sid, $sName, $date, $actName, $score, $teacherName, $teacherId]);
            $count++;
        } else {
            $dupes++;
        }
    }

    // 2. ดึงรายการแต่งกายผิดระเบียบ (ถ้ามี)
    $stmtDress = $pdo->prepare("
        SELECT a.student_id, s.name as student_name, nail, hair, shirt, pants, socks, shoes
        FROM assembly_attendance a
        JOIN assembly_students s ON a.student_id = s.student_id
        WHERE a.date = ? AND (nail='ผิด' OR hair='ผิด' OR shirt='ผิด' OR pants='ผิด' OR socks='ผิด' OR shoes='ผิด')
    ");
    $stmtDress->execute([$date]);
    $dressVios = $stmtDress->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dressVios as $dv) {
        $sid = $dv['student_id'];
        $sName = $dv['student_name'];
        $vios = [];
        if ($dv['nail'] === 'ผิด') $vios[] = 'เล็บ';
        if ($dv['hair'] === 'ผิด') $vios[] = 'ทรงผม';
        if ($dv['shirt'] === 'ผิด') $vios[] = 'เสื้อ';
        if ($dv['pants'] === 'ผิด') $vios[] = 'กางเกง/กระโปรง';
        if ($dv['socks'] === 'ผิด') $vios[] = 'ถุงเท้า';
        if ($dv['shoes'] === 'ผิด') $vios[] = 'รองเท้า';
        
        $actNameDress = "แต่งกายผิดระเบียบ (จากการเข้าแถว: " . implode(',', $vios) . ")";

        $checkDress = $pdo->prepare("SELECT id FROM beh_records WHERE student_id = ? AND record_date = ? AND activity LIKE 'แต่งกายผิดระเบียบ (จากการเข้าแถว%'");
        $checkDress->execute([$sid, $date]);

        if (!$checkDress->fetch()) {
             $insDress = $pdo->prepare("
                INSERT INTO beh_records 
                (student_id, student_name, record_date, type, activity, score, teacher_name, teacher_user_id) 
                VALUES (?, ?, ?, 'ความผิด', ?, 5, ?, ?)
            ");
            $insDress->execute([$sid, $sName, $date, $actNameDress, $teacherName, $teacherId]);
            $count++;
        }
    }

    // 3. ดึงรายการโดดเรียนรายวิชา (จากระบบเช็คชื่อรายคาบ)
    $stmtAtt = $pdo->prepare("
        SELECT a.student_id, s.name as student_name, sub.subject_name, a.period, a.date
        FROM att_attendance a
        JOIN att_students s ON a.student_id = s.id
        JOIN att_subjects sub ON sub.id = a.subject_id
        WHERE a.date = ? AND a.status = 'โดด'
    ");
    $stmtAtt->execute([$date]);
    $skips = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($skips as $sk) {
        $sid = $sk['student_id'];
        $sName = $sk['student_name'];
        $actNameSkip = "โดดเรียนวิชา " . $sk['subject_name'] . " (คาบที่ " . $sk['period'] . ")";

        $checkSkip = $pdo->prepare("SELECT id FROM beh_records WHERE student_id = ? AND record_date = ? AND activity = ?");
        $checkSkip->execute([$sid, $date, $actNameSkip]);

        if (!$checkSkip->fetch()) {
             $insSkip = $pdo->prepare("
                INSERT INTO beh_records 
                (student_id, student_name, record_date, type, activity, score, teacher_name, teacher_user_id) 
                VALUES (?, ?, ?, 'ความผิด', ?, 10, ?, ?)
            ");
            $insSkip->execute([$sid, $sName, $date, $actNameSkip, $teacherName, $teacherUserId]);
            $count++;
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => "บันทึกพฤติกรรมสำเร็จ $count รายการ (ข้ามรายการซ้ำ $dupes รายการ)"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
