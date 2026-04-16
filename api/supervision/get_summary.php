<?php
// api/supervision/get_summary.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

try {
    $pdo = getPdo();

    // Check Evaluator Status
    $stmtC = $pdo->prepare("SELECT role, is_evaluator FROM llw_users WHERE user_id = ?");
    $stmtC->execute([$_SESSION['user_id']]);
    $currUser = $stmtC->fetch();

    $isAdmin = in_array($currUser['role'] ?? '', ['super_admin', 'wfh_admin']);
    $isEval  = ($currUser['is_evaluator'] ?? 0) == 1;

    if (!$isAdmin && !$isEval) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลส่วนนี้']);
        exit;
    }
    
    // 1. Interpretation Stats (Pie Chart)
    $stmtPie = $pdo->query("
        SELECT interpretation, COUNT(*) as count 
        FROM sup_records 
        GROUP BY interpretation
    ");
    $pieData = $stmtPie->fetchAll();

    // 2. Global Radar Data (Avg of all teachers)
    $stmtGlobal = $pdo->query("
        SELECT s.item_idx, AVG(s.score) as avg_score
        FROM sup_scores s
        GROUP BY s.item_idx
    ");
    $globalScoresRaw = $stmtGlobal->fetchAll();
    
    $globalItemScores = array_fill(0, 27, 0);
    foreach ($globalScoresRaw as $s) {
        $globalItemScores[$s['item_idx']] = (float)$s['avg_score'];
    }

    $radarData = [
        'group1' => count($globalItemScores) > 0 ? array_sum(array_slice($globalItemScores, 0, 13)) / 13 : 0,
        'group2' => count($globalItemScores) > 0 ? array_sum(array_slice($globalItemScores, 13, 5)) / 5 : 0,
        'group3' => count($globalItemScores) > 0 ? array_sum(array_slice($globalItemScores, 18, 5)) / 5 : 0,
        'group4' => count($globalItemScores) > 0 ? array_sum(array_slice($globalItemScores, 23, 4)) / 4 : 0,
    ];

    // 3. Recent 10 Records
    $stmtRecent = $pdo->query("
        SELECT r.*, CONCAT(u.firstname, ' ', u.lastname) as teacher_name
        FROM sup_records r
        JOIN llw_users u ON r.teacher_id = u.user_id
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $recent = $stmtRecent->fetchAll();

    // 4. Totals & Special KPIs
    $totalRecords = $pdo->query("SELECT COUNT(*) FROM sup_records")->fetchColumn();
    $totalTeachers = $pdo->query("SELECT COUNT(DISTINCT teacher_id) FROM sup_records")->fetchColumn();

    // KPI: Total Teachers (Excluding Dir/Deputy)
    $stmtTotalStaff = $pdo->query("
        SELECT COUNT(*) 
        FROM llw_users 
        WHERE status = 'active'
          AND position NOT LIKE '%ผู้อำนวยการ%'
          AND role IN ('att_teacher', 'wfh_staff', 'cb_admin')
    ");
    $kpiTotalStaff = $stmtTotalStaff->fetchColumn();

    // KPI: Self-Evaluated
    $stmtSelfDone = $pdo->query("
        SELECT COUNT(DISTINCT teacher_id) 
        FROM sup_records 
        WHERE observer_position = 'ประเมินตนเอง'
    ");
    $kpiSelfDone = $stmtSelfDone->fetchColumn();

    // KPI: Completed 3 Peer Evaluations
    $stmtPeerDone = $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT teacher_id 
            FROM sup_records 
            WHERE observer_id != teacher_id 
              AND observer_position != 'ประเมินตนเอง' 
            GROUP BY teacher_id 
            HAVING COUNT(DISTINCT observer_id) >= 3
        ) as sub
    ");
    $kpiPeerDone = $stmtPeerDone->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'stats' => [
            'total_records' => $totalRecords,
            'total_teachers_supervised' => $totalTeachers,
            'kpi_total_staff' => $kpiTotalStaff,
            'kpi_self_done' => $kpiSelfDone,
            'kpi_peer_done' => $kpiPeerDone
        ],
        'pie' => $pieData,
        'radar' => $radarData,
        'recent' => $recent
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลสรุป']);
    error_log($e->getMessage());
}
