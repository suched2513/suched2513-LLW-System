<?php
/**
 * assembly/api/save_all.php
 * POST JSON — บันทึกข้อมูลทั้งห้อง (batch upsert + Telegram notify)
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/telegram_bot.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$records   = $input['records']   ?? [];
$date      = $input['date']      ?? date('Y-m-d');
$classroom = $input['classroom'] ?? '';

if (empty($classroom) || empty($records)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// ตรวจสิทธิ์ att_teacher → ต้องเป็นห้องตัวเอง
if ($_SESSION['llw_role'] === 'att_teacher') {
    try {
        $pdo    = getPdo();
        $userId = $_SESSION['user_id'] ?? 0;
        $check  = $pdo->prepare("SELECT id FROM assembly_classrooms WHERE classroom = ? AND llw_user_id = ?");
        $check->execute([$classroom, $userId]);
        if (!$check->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงห้องนี้']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
        exit;
    }
}

try {
    $pdo       = getPdo();
    $createdBy = $_SESSION['user_id'] ?? null;
    $added     = 0;
    $updated   = 0;

    // ดึง teacher name สำหรับ Telegram
    $tcStmt = $pdo->prepare("SELECT teacher_name FROM assembly_classrooms WHERE classroom = ?");
    $tcStmt->execute([$classroom]);
    $teacherName = $tcStmt->fetchColumn() ?: '-';

    $upsert = $pdo->prepare("
        INSERT INTO assembly_attendance
            (date, classroom, student_id, status, nail, hair, shirt, pants, socks, shoes, note, created_by)
        VALUES
            (:date, :classroom, :student_id, :status, :nail, :hair, :shirt, :pants, :socks, :shoes, :note, :created_by)
        ON DUPLICATE KEY UPDATE
            status     = VALUES(status),
            nail       = VALUES(nail),
            hair       = VALUES(hair),
            shirt      = VALUES(shirt),
            pants      = VALUES(pants),
            socks      = VALUES(socks),
            shoes      = VALUES(shoes),
            note       = VALUES(note),
            updated_at = CURRENT_TIMESTAMP
    ");

    foreach ($records as $r) {
        $studentId = trim($r['student_id'] ?? $r['studentID'] ?? '');
        if ($studentId === '') continue;

        // Normalize: pad to 5 digits
        if (preg_match('/^\d+$/', $studentId)) {
            $studentId = str_pad($studentId, 5, '0', STR_PAD_LEFT);
        }

        // ตรวจว่ามีอยู่แล้วไหม
        $existCheck = $pdo->prepare("SELECT id FROM assembly_attendance WHERE date = ? AND student_id = ?");
        $existCheck->execute([$date, $studentId]);
        $isUpdate = (bool)$existCheck->fetchColumn();

        $upsert->execute([
            ':date'       => $date,
            ':classroom'  => $classroom,
            ':student_id' => $studentId,
            ':status'     => $r['status'] ?? 'ม',
            ':nail'       => $r['nail']   ?? 'ถูก',
            ':hair'       => $r['hair']   ?? 'ถูก',
            ':shirt'      => $r['shirt']  ?? 'ถูก',
            ':pants'      => $r['pants']  ?? 'ถูก',
            ':socks'      => $r['socks']  ?? 'ถูก',
            ':shoes'      => $r['shoes']  ?? 'ถูก',
            ':note'       => $r['note']   ?? '',
            ':created_by' => $createdBy,
        ]);

        $isUpdate ? $updated++ : $added++;
    }

    // ─── Telegram Notify ───────────────────────────────────────────
    try {
        // ดึง Telegram config จาก wfh_system_settings
        $settings = $pdo->query("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1")->fetch();
        if ($settings && $settings['telegram_token'] && $settings['admin_chat_id']) {
            $total   = count($records);
            $present = count(array_filter($records, fn($r) => ($r['status'] ?? '') === 'ม'));
            $absentList = array_filter($records, fn($r) => ($r['status'] ?? 'ม') !== 'ม');

            // ดึงชื่อนักเรียน
            $studentIds = array_column($records, 'student_id');
            if (empty($studentIds)) $studentIds = array_column($records, 'studentID');
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $nameStmt = $pdo->prepare("SELECT student_id, name FROM assembly_students WHERE student_id IN ($placeholders)");
            $nameStmt->execute($studentIds);
            $nameMap = array_column($nameStmt->fetchAll(), 'name', 'student_id');

            $thDate = date('j', strtotime($date));
            $monthNames = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                           'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
            $month = (int)date('n', strtotime($date));
            $thDateStr = "$thDate {$monthNames[$month]} " . (date('Y', strtotime($date)) + 543);

            $msg  = "📅 วันที่: $thDateStr\n";
            $msg .= "🏫 ห้อง: $classroom\n";
            $msg .= "👩🏫 ครูที่ปรึกษา: $teacherName\n\n";
            $msg .= "👥 นักเรียนทั้งหมด: $total คน\n";
            $msg .= "✅ มาเข้าแถว: $present คน\n";

            if (count($absentList) > 0) {
                $msg .= "❌ ไม่มาเข้าแถว:\n\n";
                foreach ($absentList as $a) {
                    $sid  = $a['student_id'] ?? $a['studentID'] ?? '';
                    $name = $nameMap[$sid] ?? $sid;
                    $statusText = match($a['status'] ?? '') {
                        'ข' => 'ขาด', 'ล' => 'ลา', 'ด' => 'โดด', default => $a['status']
                    };
                    $msg .= "• $name ($statusText)\n";
                }
            } else {
                $msg .= "🎉 ทุกคนมาเข้าแถวครบ!\n";
            }
            $msg .= "\n🕒 บันทึกเวลา: " . date('H:i:s');

            $bot = new TelegramBot($settings['telegram_token'], $settings['admin_chat_id']);
            $bot->sendMessage($msg);
        }
    } catch (Exception $tgErr) {
        error_log('[Assembly] Telegram error: ' . $tgErr->getMessage());
    }

    echo json_encode([
        'status'  => 'success',
        'message' => "เพิ่มใหม่ $added รายการ, อัพเดต $updated รายการ",
        'added'   => $added,
        'updated' => $updated,
    ]);
} catch (Exception $e) {
    error_log('[Assembly] save_all: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
