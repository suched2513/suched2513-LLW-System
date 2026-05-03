<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_student']) || $_SESSION['is_student'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$club_id = isset($input['club_id']) ? (int)$input['club_id'] : 0;
$sid     = $_SESSION['student_code'] ?? '';

if ($club_id <= 0 || $sid === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // Get active settings
    $cfg = $pdo->query("SELECT * FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$cfg) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'ยังไม่เปิดระบบลงทะเบียนชุมนุม']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    if ($cfg['reg_open'] && $now < $cfg['reg_open']) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'ยังไม่ถึงเวลาเปิดลงทะเบียน']);
        exit;
    }
    if ($cfg['reg_close'] && $now > $cfg['reg_close']) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'หมดเวลาลงทะเบียนแล้ว']);
        exit;
    }

    $semester = (int)$cfg['semester'];
    $year     = (int)$cfg['year'];

    // Check existing registration
    $existing = $pdo->prepare("SELECT id, club_id FROM club_registrations WHERE student_id = ? AND semester = ? AND year = ?");
    $existing->execute([$sid, $semester, $year]);
    $reg = $existing->fetch(PDO::FETCH_ASSOC);

    if ($reg && $reg['club_id'] == $club_id) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'คุณลงทะเบียนชุมนุมนี้แล้ว']);
        exit;
    }
    if ($reg && !$cfg['allow_change']) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'ไม่อนุญาตให้เปลี่ยนชุมนุม']);
        exit;
    }

    // Get club info + check capacity
    $club = $pdo->prepare("SELECT cg.*, COUNT(cr.id) AS registered FROM club_groups cg LEFT JOIN club_registrations cr ON cr.club_id = cg.id AND cr.semester = ? AND cr.year = ? WHERE cg.id = ? AND cg.status = 'open' LIMIT 1");
    $club->execute([$semester, $year, $club_id]);
    $clubRow = $club->fetch(PDO::FETCH_ASSOC);

    if (!$clubRow) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบชุมนุม หรือชุมนุมปิดรับแล้ว']);
        exit;
    }
    if ((int)$clubRow['registered'] >= (int)$clubRow['max_capacity']) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'ชุมนุมเต็มแล้ว']);
        exit;
    }

    if ($reg) {
        // Change club
        $stmt = $pdo->prepare("UPDATE club_registrations SET club_id = ?, changed_at = NOW() WHERE id = ?");
        $stmt->execute([$club_id, $reg['id']]);
    } else {
        // New registration
        $stmt = $pdo->prepare("INSERT INTO club_registrations (student_id, club_id, semester, year) VALUES (?,?,?,?)");
        $stmt->execute([$sid, $club_id, $semester, $year]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'ลงทะเบียนชุมนุม "' . $clubRow['name'] . '" สำเร็จ', 'club_name' => $clubRow['name']]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[club register] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
