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

// ตรวจสิทธิ์ att_teacher → อนุญาตให้เช็คชื่อเฉพาะห้องที่ตนเป็นที่ปรึกษา
if ($_SESSION['llw_role'] === 'att_teacher') {
    try {
        $pdo    = getPdo();
        $userId = $_SESSION['user_id'] ?? 0;
        
        $check = $pdo->prepare("SELECT 1 FROM llw_class_advisors WHERE classroom = ? AND user_id = ?");
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

    // ดึง teacher name จาก llw_class_advisors (ตารางที่ถูกต้อง)
    $tcStmt = $pdo->prepare("
        SELECT CONCAT(u.firstname, ' ', u.lastname)
        FROM llw_class_advisors ca
        JOIN llw_users u ON u.user_id = ca.user_id
        WHERE ca.classroom = ?
        ORDER BY u.firstname
    ");
    $tcStmt->execute([$classroom]);
    $advisors = $tcStmt->fetchAll(PDO::FETCH_COLUMN);
    $teacherName = !empty($advisors) ? implode(' / ', $advisors) : '-';

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

        // ตรวจว่ามีอยู่แล้วไหม (ต้อง filter classroom ด้วย)
        $existCheck = $pdo->prepare("SELECT id FROM assembly_attendance WHERE date = ? AND student_id = ? AND classroom = ?");
        $existCheck->execute([$date, $studentId, $classroom]);
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
    $tgDebug = null;
    try {
        $total      = count($records);
        $present    = count(array_filter($records, fn($r) => ($r['status'] ?? '') === 'ม'));
        $absentList = array_filter($records, fn($r) => ($r['status'] ?? 'ม') !== 'ม');

        // ดึงชื่อนักเรียนจาก att_students (ตารางหลักเดียวกับ get_students.php) + fallback assembly_students
        $studentIds = array_values(array_filter(array_map(
            fn($r) => trim($r['student_id'] ?? $r['studentID'] ?? ''),
            $records
        )));
        $nameMap = [];
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            try {
                $nameStmt = $pdo->prepare("SELECT student_id, name FROM att_students WHERE student_id IN ($placeholders)");
                $nameStmt->execute($studentIds);
                foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $nr) {
                    $nameMap[$nr['student_id']] = $nr['name'];
                    $nameMap[str_pad($nr['student_id'], 5, '0', STR_PAD_LEFT)] = $nr['name'];
                }
            } catch (\Throwable $ignore) {}

            if (empty($nameMap)) {
                try {
                    $nameStmt = $pdo->prepare("SELECT student_id, name FROM assembly_students WHERE student_id IN ($placeholders)");
                    $nameStmt->execute($studentIds);
                    foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $nr) {
                        $nameMap[$nr['student_id']] = $nr['name'];
                        $nameMap[str_pad($nr['student_id'], 5, '0', STR_PAD_LEFT)] = $nr['name'];
                    }
                } catch (\Throwable $ignore) {}
            }
        }

        // ตรวจรายการเครื่องแต่งกายผิดระเบียบ (เฉพาะนักเรียนที่มาเรียน)
        $uniformMap = [
            'nail'  => 'เล็บ',
            'hair'  => 'ผม',
            'shirt' => 'เสื้อ',
            'pants' => 'กางเกง',
            'socks' => 'ถุงเท้า',
            'shoes' => 'รองเท้า',
        ];
        $uniformIssues = [];
        foreach ($records as $r) {
            if (($r['status'] ?? 'ม') !== 'ม') continue;
            $badItems = [];
            foreach ($uniformMap as $col => $label) {
                if (($r[$col] ?? 'ถูก') === 'ผิด') {
                    $badItems[] = $label;
                }
            }
            if (!empty($badItems)) {
                $sid    = trim($r['student_id'] ?? $r['studentID'] ?? '');
                $padSid = preg_match('/^\d+$/', $sid) ? str_pad($sid, 5, '0', STR_PAD_LEFT) : $sid;
                $sName  = htmlspecialchars($nameMap[$sid] ?? ($nameMap[$padSid] ?? $sid), ENT_QUOTES, 'UTF-8');
                $uniformIssues[] = "• {$sName} (" . implode(', ', $badItems) . ")";
            }
        }

        $thDate = date('j', strtotime($date));
        $monthNames = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                       'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        $month = (int)date('n', strtotime($date));
        $thDateStr = "$thDate {$monthNames[$month]} " . (date('Y', strtotime($date)) + 543);

        $safeRoom    = htmlspecialchars($classroom, ENT_QUOTES, 'UTF-8');
        $safeTeacher = htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8');

        $msg  = "🌅 <b>เช็คชื่อเข้าแถวเช้า & เครื่องแต่งกาย</b>\n";
        $msg .= "📅 วันที่: $thDateStr\n";
        $msg .= "🏫 ห้อง: $safeRoom\n";
        $msg .= "👩‍🏫 ครูที่ปรึกษา: $safeTeacher\n\n";
        $msg .= "👥 นักเรียนทั้งหมด: $total คน\n";
        $msg .= "✅ มาเข้าแถว: $present คน\n";

        $statusMap = ['ข' => 'ขาด', 'ล' => 'ลา', 'ด' => 'โดด'];
        if (count($absentList) > 0) {
            $msg .= "❌ ไม่มาเข้าแถว:\n";
            foreach ($absentList as $a) {
                $sid        = trim($a['student_id'] ?? $a['studentID'] ?? '');
                $padSid     = preg_match('/^\d+$/', $sid) ? str_pad($sid, 5, '0', STR_PAD_LEFT) : $sid;
                $name       = htmlspecialchars($nameMap[$sid] ?? ($nameMap[$padSid] ?? $sid), ENT_QUOTES, 'UTF-8');
                $stKey      = $a['status'] ?? '';
                $statusText = htmlspecialchars($statusMap[$stKey] ?? $stKey, ENT_QUOTES, 'UTF-8');
                $noteStr    = !empty($a['note']) ? ' [' . htmlspecialchars($a['note'], ENT_QUOTES, 'UTF-8') . ']' : '';
                $msg .= "• $name ($statusText)$noteStr\n";
            }
        } else {
            $msg .= "🎉 ทุกคนมาเข้าแถวครบ!\n";
        }

        if (!empty($uniformIssues)) {
            $msg .= "\n⚠️ <b>เครื่องแต่งกายผิดระเบียบ (" . count($uniformIssues) . " คน):</b>\n";
            $msg .= implode("\n", $uniformIssues) . "\n";
        } else {
            $msg .= "👔 เครื่องแต่งกาย: ถูกระเบียบทุกคน\n";
        }

        $msg .= "\n🕒 บันทึกเวลา: " . date('H:i:s');

        $tgRes = Telegram::sendAttendanceNotify($pdo, $msg);
        $tgDebug = [
            'sent' => (bool)($tgRes['ok'] ?? false),
            'desc' => $tgRes['description'] ?? null,
        ];
        if (!($tgRes['ok'] ?? false)) {
            error_log('[Assembly] Telegram send failed: ' . ($tgRes['description'] ?? 'unknown'));
        }
    } catch (\Throwable $tgErr) {
        $tgDebug = ['sent' => false, 'desc' => $tgErr->getMessage()];
        error_log('[Assembly] Telegram error: ' . $tgErr->getMessage());
    }

    echo json_encode([
        'status'   => 'success',
        'message'  => "เพิ่มใหม่ $added รายการ, อัพเดต $updated รายการ",
        'added'    => $added,
        'updated'  => $updated,
        'telegram' => $tgDebug,
    ]);
} catch (Exception $e) {
    error_log('[Assembly] save_all: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
