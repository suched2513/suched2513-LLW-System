<?php
// api/supervision/get_triple_summary.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$teacher_id = $_GET['teacher_id'] ?? null;
if (!$teacher_id) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่ระบุรหัสครู']);
    exit;
}

try {
    $pdo = getPdo();
    
    // 1. Get Teacher Profile
    $stmtUser = $pdo->prepare("SELECT user_id, firstname, lastname, position, academic_status, subject_group FROM llw_users WHERE user_id = ?");
    $stmtUser->execute([$teacher_id]);
    $teacher = $stmtUser->fetch();
    
    if (!$teacher) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลครู']);
        exit;
    }

    // 2. Get 3 Latest Peer Evaluations (Unique observers, not self)
    $stmtRecords = $pdo->prepare("
        SELECT * FROM (
            SELECT r.*, 
                   ROW_NUMBER() OVER(PARTITION BY observer_id ORDER BY created_at DESC) as rn
            FROM sup_records r
            WHERE r.teacher_id = ? 
              AND r.observer_id != r.teacher_id
              AND r.observer_position != 'ประเมินตนเอง'
        ) AS sub
        WHERE rn = 1
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $stmtRecords->execute([$teacher_id]);
    $records = $stmtRecords->fetchAll();

    if (count($records) < 1) {
        echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้รับการนิเทศจากผู้นิเทศ (ต้องการอย่างน้อย 1 รายการเพื่อดูรายงาน)']);
        exit;
    }

    // 3. Get detailed scores for each of these 3 records
    $fullData = [];
    foreach ($records as $r) {
        $stmtS = $pdo->prepare("SELECT item_idx, score FROM sup_scores WHERE record_id = ? ORDER BY item_idx ASC");
        $stmtS->execute([$r['id']]);
        $scores = $stmtS->fetchAll();
        
        $r['scores'] = $scores;
        $fullData[] = $r;
    }

    echo json_encode([
        'status' => 'success',
        'teacher' => $teacher,
        'evaluations' => $fullData
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลสรุป 3 ท่าน']);
    error_log($e->getMessage());
}
