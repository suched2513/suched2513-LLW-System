<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
$allowedRoles = ['super_admin', 'club_admin', 'att_teacher'];
if (!in_array($_SESSION['llw_role'], $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการนี้']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$fromSem  = isset($input['from_semester']) ? (int)$input['from_semester'] : 1;
$fromYear = isset($input['from_year'])     ? (int)$input['from_year']     : 2569;
$toSem    = isset($input['to_semester'])   ? (int)$input['to_semester']   : 2;
$toYear   = isset($input['to_year'])       ? (int)$input['to_year']       : 2569;

if ($fromSem === $toSem && $fromYear === $toYear) {
    echo json_encode(['status' => 'error', 'message' => 'ภาคเรียนต้นทางและปลายทางต้องไม่เป็นเทอมเดียวกัน']);
    exit;
}

try {
    $pdo = getPdo();
    $userRole  = $_SESSION['llw_role'];
    $teacherId = (int)($_SESSION['teacher_id'] ?? 0);

    $where = ["cg.semester = ?", "cg.year = ?", "cg.status != 'archived'"];
    $params = [$fromSem, $fromYear];

    // Teachers can clone only clubs where they are an advisor
    if ($userRole === 'att_teacher' && $teacherId > 0) {
        $where[] = "(cg.teacher_id = ? OR cg.teacher_id_2 = ? OR cg.teacher_id_3 = ?)";
        $params[] = $teacherId;
        $params[] = $teacherId;
        $params[] = $teacherId;
    }

    $sql = "SELECT * FROM club_groups cg WHERE " . implode(' AND ', $where) . " ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sourceClubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($sourceClubs)) {
        echo json_encode(['status' => 'error', 'message' => "ไม่พบชุมนุมในภาคเรียนที่ $fromSem/$fromYear ที่สามารถคัดลอกได้"]);
        exit;
    }

    // Existing clubs in target term
    $stmtDest = $pdo->prepare("SELECT name FROM club_groups WHERE semester = ? AND year = ?");
    $stmtDest->execute([$toSem, $toYear]);
    $destNames = $stmtDest->fetchAll(PDO::FETCH_COLUMN);
    $destNamesMap = array_flip(array_map('trim', $destNames));

    $insertStmt = $pdo->prepare("
        INSERT INTO club_groups (name, description, objectives, obstacles, teacher_id, teacher_id_2, teacher_id_3, room, max_capacity, semester, year, status, pass_threshold)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?)
    ");

    $clonedCount = 0;
    $skippedCount = 0;

    $pdo->beginTransaction();
    foreach ($sourceClubs as $c) {
        $nameTrim = trim($c['name']);
        if (isset($destNamesMap[$nameTrim])) {
            $skippedCount++;
            continue;
        }
        $insertStmt->execute([
            $c['name'],
            $c['description'] ?? '',
            $c['objectives'] ?? '',
            '', // Reset obstacles for the new term
            $c['teacher_id'] ?: null,
            $c['teacher_id_2'] ?: null,
            $c['teacher_id_3'] ?: null,
            $c['room'] ?? '',
            (int)($c['max_capacity'] ?: 30),
            $toSem,
            $toYear,
            (int)($c['pass_threshold'] ?: 80),
        ]);
        $destNamesMap[$nameTrim] = true;
        $clonedCount++;
    }
    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "คัดลอกชุมนุมสู่ภาคเรียนที่ $toSem/$toYear สำเร็จ $clonedCount ชุมนุม" . ($skippedCount > 0 ? " (ข้ามที่ชื่อซ้ำ $skippedCount ชุมนุม)" : ""),
        'cloned_count' => $clonedCount,
        'skipped_count' => $skippedCount,
    ]);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[clone_clubs] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการคัดลอก: ' . $e->getMessage()]);
}
