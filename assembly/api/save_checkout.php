<?php
/**
 * assembly/api/save_checkout.php
 * POST JSON — บันทึกข้อมูลกลับบ้าน (evening checkout)
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

$input     = json_decode(file_get_contents('php://input'), true);
$records   = $input['records']   ?? [];
$date      = trim($input['date'] ?? date('Y-m-d')) ?: date('Y-m-d');
$classroom = preg_replace('/^ม\.+/', 'ม.', trim($input['classroom'] ?? ''));

if (empty($classroom) || empty($records)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo       = getPdo();
    $createdBy = $_SESSION['user_id'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO assembly_checkout (date, classroom, student_id, name, status, note, created_by)
        VALUES (:date, :classroom, :student_id, :name, :status, :note, :created_by)
        ON DUPLICATE KEY UPDATE
            status     = VALUES(status),
            note       = VALUES(note),
            created_by = VALUES(created_by)
    ");

    $count = 0;
    foreach ($records as $r) {
        $studentId = trim($r['student_id'] ?? $r['id'] ?? '');
        if ($studentId === '') continue;

        if (preg_match('/^\d+$/', $studentId)) {
            $studentId = str_pad($studentId, 5, '0', STR_PAD_LEFT);
        }
        $stmt->execute([
            ':date'       => $date,
            ':classroom'  => $classroom,
            ':student_id' => $studentId,
            ':name'       => $r['name']   ?? '',
            ':status'     => $r['status'] ?? 'มา',
            ':note'       => $r['note']   ?? '',
            ':created_by' => $createdBy,
        ]);
        $count++;
    }

    // ── แจ้งเตือน Telegram เช็คชื่อกลับบ้าน (ใช้ sendAttendanceNotify) ──
    $tgDebug = null;
    try {
        $advisors = [];
        try {
            $tcStmt = $pdo->prepare("
                SELECT TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')))
                FROM llw_class_advisors ca
                JOIN llw_users u ON u.user_id = ca.user_id
                WHERE ca.classroom = ?
                ORDER BY ca.id ASC
            ");
            $tcStmt->execute([$classroom]);
            $advisors = array_values(array_filter(array_unique($tcStmt->fetchAll(PDO::FETCH_COLUMN))));
        } catch (\Throwable $ignore) {}

        $checkerName = trim((string)($_SESSION['fullname'] ?? ($_SESSION['teacher_name'] ?? ($_SESSION['username'] ?? ''))));
        $teacherName = !empty($advisors) ? implode(' / ', $advisors) : ($checkerName !== '' ? $checkerName : '-');

        $total      = count($records);
        $present    = count(array_filter($records, fn($r) => ($r['status'] ?? 'มา') === 'มา'));
        $absentList = array_filter($records, fn($r) => ($r['status'] ?? 'มา') !== 'มา');

        $thDate = date('j', strtotime($date));
        $monthNames = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                       'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        $month = (int)date('n', strtotime($date));
        $thDateStr = "$thDate {$monthNames[$month]} " . (date('Y', strtotime($date)) + 543);

        $safeRoom    = htmlspecialchars($classroom, ENT_QUOTES, 'UTF-8');
        $safeTeacher = htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8');
        $safeChecker = htmlspecialchars($checkerName, ENT_QUOTES, 'UTF-8');

        $msg  = "🌇 <b>เช็คชื่อกลับบ้าน (ตอนเย็น)</b>\n";
        $msg .= "📅 วันที่: $thDateStr\n";
        $msg .= "🏫 ห้อง: $safeRoom\n";
        $msg .= "👩‍🏫 ครูที่ปรึกษา: $safeTeacher\n";
        if ($safeChecker !== '' && strpos($safeTeacher, $safeChecker) === false) {
            $msg .= "✍️ ผู้บันทึก: $safeChecker\n";
        }
        $msg .= "\n👥 นักเรียนทั้งหมด: $total คน\n";
        $msg .= "✅ อยู่ครบ: $present คน\n";

        if (count($absentList) > 0) {
            $msg .= "❌ ไม่อยู่/โดด:\n";
            foreach ($absentList as $a) {
                $name = htmlspecialchars(trim($a['name'] ?? ($a['student_id'] ?? '')), ENT_QUOTES, 'UTF-8');
                $st   = htmlspecialchars($a['status'] ?? '', ENT_QUOTES, 'UTF-8');
                $msg .= "• $name ($st)\n";
            }
        } else {
            $msg .= "🎉 นักเรียนอยู่ครบทุกคน!\n";
        }
        $msg .= "\n🕒 บันทึกเวลา: " . date('H:i:s');

        $tgRes = Telegram::sendAttendanceNotify($pdo, $msg);
        $tgDebug = [
            'sent' => (bool)($tgRes['ok'] ?? false),
            'desc' => $tgRes['description'] ?? null,
        ];
    } catch (\Throwable $tgErr) {
        $tgDebug = ['sent' => false, 'desc' => $tgErr->getMessage()];
        error_log('[Assembly] save_checkout Telegram error: ' . $tgErr->getMessage());
    }

    echo json_encode(['status' => 'success', 'saved' => $count, 'telegram' => $tgDebug]);
} catch (Exception $e) {
    error_log('[Assembly] save_checkout: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
