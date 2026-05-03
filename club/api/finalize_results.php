<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'att_teacher'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$club_id  = isset($input['club_id'])  ? (int)$input['club_id']  : 0;
$semester = isset($input['semester']) ? (int)$input['semester'] : 0;
$year     = isset($input['year'])     ? (int)$input['year']     : 0;
$overrides = $input['overrides'] ?? [];

if ($club_id <= 0 || $semester <= 0 || $year <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบ']);
    exit;
}

try {
    $pdo = getPdo();

    // Get club info
    $stmt = $pdo->prepare("SELECT id, teacher_id, pass_threshold FROM club_groups WHERE id = ?");
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$club) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบชุมนุม']);
        exit;
    }

    // Verify teacher ownership
    if ($_SESSION['llw_role'] === 'att_teacher') {
        $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
        if ((int)$club['teacher_id'] !== $teacherId) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่ใช่ครูที่ปรึกษาของชุมนุมนี้']);
            exit;
        }
    }

    $passThreshold = (int)$club['pass_threshold'];

    // Count total done sessions for this club
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_sessions WHERE club_id = ? AND status = 'done'");
    $stmt->execute([$club_id]);
    $totalSessions = (int)$stmt->fetchColumn();

    // Get all registered students for this club/semester/year
    $stmt = $pdo->prepare("SELECT student_id FROM club_registrations WHERE club_id = ? AND semester = ? AND year = ?");
    $stmt->execute([$club_id, $semester, $year]);
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($students)) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีนักเรียนในชุมนุมนี้']);
        exit;
    }

    // Build overrides map
    $overrideMap = [];
    foreach ($overrides as $ov) {
        $sid = trim($ov['student_id'] ?? '');
        if ($sid !== '') {
            $overrideMap[$sid] = [
                'result'  => $ov['result'] ?? null,
                'comment' => trim($ov['comment'] ?? ''),
            ];
        }
    }

    $finalizedBy = (int)($_SESSION['teacher_id'] ?? 0);

    $pdo->beginTransaction();

    $upsert = $pdo->prepare("INSERT INTO club_results
        (student_id, club_id, semester, year, total_sessions, attended_sessions, attendance_pct, result, teacher_comment, finalized_by, finalized_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE
            total_sessions=VALUES(total_sessions),
            attended_sessions=VALUES(attended_sessions),
            attendance_pct=VALUES(attendance_pct),
            result=VALUES(result),
            teacher_comment=VALUES(teacher_comment),
            finalized_by=VALUES(finalized_by),
            finalized_at=NOW()");

    $count = 0;
    foreach ($students as $studentId) {
        // Count attended sessions (present + late)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM club_attendance ca
            JOIN club_sessions cs ON cs.id = ca.session_id
            WHERE cs.club_id = ? AND ca.student_id = ? AND ca.status IN ('present','late') AND cs.status = 'done'
        ");
        $stmt->execute([$club_id, $studentId]);
        $attended = (int)$stmt->fetchColumn();

        $pct = $totalSessions > 0 ? round($attended / $totalSessions * 100, 2) : 0.00;
        $result = $pct >= $passThreshold ? 'pass' : 'fail';
        $comment = '';

        // Apply override if set
        if (isset($overrideMap[$studentId])) {
            $ov = $overrideMap[$studentId];
            if ($ov['result'] !== null && in_array($ov['result'], ['pass', 'fail', 'pending'])) {
                $result = $ov['result'];
            }
            $comment = $ov['comment'];
        }

        $upsert->execute([
            $studentId, $club_id, $semester, $year,
            $totalSessions, $attended, $pct, $result,
            $comment, $finalizedBy ?: null,
        ]);
        $count++;
    }

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => "ประเมินผลสำเร็จ $count คน", 'count' => $count]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[finalize_results] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
