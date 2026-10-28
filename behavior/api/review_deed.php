<?php
/**
 * API: Approve or Reject a student good deed
 * POST JSON: { recordId, action: 'approve'|'reject', note }
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] === 'student') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบสำหรับครู/บุคลากร']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$recordId = (int)($input['recordId'] ?? 0);
$action   = $input['action'] ?? ''; // 'approve' or 'reject'
$note     = trim($input['note'] ?? '');

if ($recordId === 0 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $pdo = getPdo();
    $teacherName = trim((string)($_SESSION['fullname'] ?? ($_SESSION['teacher_name'] ?? 'ครูที่ปรึกษา')));
    $teacherId   = (int)($_SESSION['user_id'] ?? 0);
    $isSuper     = in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin', 'director', 'deputy_director'], true);

    // Verify record exists and is pending
    $stmt = $pdo->prepare("SELECT * FROM beh_records WHERE id = ? AND status = 'pending'");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการที่รอการตรวจสอบ']);
        exit;
    }

    // Permission check: check both llw_class_advisors and beh_advisors
    if (!$isSuper) {
        $hasPerm = false;
        try {
            $chk1 = $pdo->prepare("
                SELECT 1
                FROM llw_class_advisors ca
                JOIN att_students s ON s.classroom = ca.classroom
                WHERE ca.user_id = ? AND s.student_id = ?
                LIMIT 1
            ");
            $chk1->execute([$teacherId, $record['student_id']]);
            if ($chk1->fetchColumn()) $hasPerm = true;
        } catch (\Throwable $ignore) {}

        if (!$hasPerm) {
            try {
                $chk2 = $pdo->prepare("
                    SELECT 1
                    FROM beh_advisors a
                    JOIN beh_students s ON (a.level = s.level OR CONCAT('ม.', a.level) = s.level) AND a.room = s.room
                    WHERE a.user_id = ? AND s.student_id = ?
                    LIMIT 1
                ");
                $chk2->execute([$teacherId, $record['student_id']]);
                if ($chk2->fetchColumn()) $hasPerm = true;
            } catch (\Throwable $ignore) {}
        }

        if (!$hasPerm) {
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์อนุมัติให้นักเรียนคนนี้']);
            exit;
        }
    }

    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    $activity  = $record['activity'];
    if ($action === 'reject' && $note !== '') {
        $activity .= " (ปฏิเสธ: $note)";
    }

    $stmt = $pdo->prepare("
        UPDATE beh_records
        SET status = ?,
            teacher_name = ?,
            teacher_user_id = ?,
            activity = ?
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $teacherName, $teacherId, $activity, $recordId]);

    // แจ้งเตือนผลการอนุมัติความดีเข้า Telegram
    try {
        $icon      = ($action === 'approve') ? '✅' : '❌';
        $stLabel   = ($action === 'approve') ? 'อนุมัติความดีแล้ว' : 'ปฏิเสธบันทึกความดี';
        $safeSName = htmlspecialchars($record['student_name'] ?? $record['student_id'], ENT_QUOTES, 'UTF-8');
        $safeAct   = htmlspecialchars($record['activity'] ?? '-', ENT_QUOTES, 'UTF-8');
        $safeTName = htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8');
        $scoreVal  = (int)($record['score'] ?? 0);

        $tgMsg  = "{$icon} <b>{$stLabel}</b>\n";
        $tgMsg .= "👤 นักเรียน: {$safeSName}\n";
        $tgMsg .= "📝 กิจกรรม: {$safeAct}\n";
        if ($action === 'approve') {
            $tgMsg .= "💎 คะแนน: +{$scoreVal}\n";
        } elseif ($note !== '') {
            $tgMsg .= "💬 เหตุผล: " . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . "\n";
        }
        $tgMsg .= "👨‍🏫 ผู้ตรวจสอบ: {$safeTName}";

        Telegram::sendAttendanceNotify($pdo, $tgMsg);
    } catch (\Throwable $ignore) {}

    $msg = ($action === 'approve') ? 'อนุมัติความดีเรียบร้อยแล้ว' : 'ปฏิเสธคำขอเรียบร้อยแล้ว';
    echo json_encode(['status' => 'success', 'message' => $msg]);

} catch (Exception $e) {
    error_log('[behavior] review_deed error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
