<?php
/**
 * homeroom/api/get_log_data.php
 * ดึงข้อมูลนักเรียน, สถิติการเช็คชื่อเข้าแถวหน้าเสาธง (assembly_attendance) เป็นค่าเริ่มต้น,
 * ข้อมูลการเช็คชื่อโฮมรูมแยกต่างหาก (hr_daily_attendance โดยไม่ทับข้อมูลเข้าแถว),
 * หัวข้อโฮมรูม และคำนวณสถานะรายสัปดาห์ (สัปดาห์ที่ 1-20) ตามภาคเรียนจริง
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$classroom     = trim($_GET['classroom'] ?? '');
$start_date    = trim($_GET['start_date'] ?? '');
$end_date      = trim($_GET['end_date'] ?? '');
$selected_date = trim($_GET['date'] ?? $start_date);
$semester      = isset($_GET['semester']) && in_array((int)$_GET['semester'], [1, 2], true)
    ? (int)$_GET['semester']
    : llw_current_semester();
$academic_year = isset($_GET['academic_year']) && (int)$_GET['academic_year'] > 2500
    ? (int)$_GET['academic_year']
    : llw_current_academic_year();

if ($classroom === '' || $start_date === '' || $end_date === '') {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo = getPdo();
    llw_ensure_semester_schema($pdo);

    $queryStart = min($start_date, $selected_date);
    $queryEnd   = max($end_date, $selected_date);

    // 1. ดึงรายชื่อนักเรียนจาก Central Table (att_students) พร้อม fallback ไป assembly_students
    $stmt = $pdo->prepare("
        SELECT student_id, name FROM att_students
        WHERE classroom = ?
          AND (academic_year = ? OR academic_year IS NULL)
          AND (status = 'active' OR status IS NULL)
          AND student_id REGEXP '^[0-9]+$'
          AND student_id NOT IN (SELECT subject_code FROM att_subjects)
        ORDER BY CAST(student_id AS UNSIGNED), student_id
    ");
    $stmt->execute([$classroom, $academic_year]);
    $rawStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rawStudents)) {
        $stmt = $pdo->prepare("SELECT student_id, name FROM assembly_students WHERE classroom = ? ORDER BY CAST(student_id AS UNSIGNED), student_id");
        $stmt->execute([$classroom]);
        $rawStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $students = [];
    $seenIds = [];
    foreach ($rawStudents as $st) {
        $normId = llw_normalize_student_id($st['student_id']);
        if ($normId === '' || isset($seenIds[$normId])) continue;
        $seenIds[$normId] = true;
        $students[] = [
            'student_id' => $normId,
            'name'       => $st['name'],
        ];
    }

    // 2. ดึงข้อมูลครูที่ปรึกษา (llw_class_advisors)
    $stmt = $pdo->prepare("
        SELECT u.firstname, u.lastname
        FROM llw_class_advisors a
        JOIN llw_users u ON a.user_id = u.user_id
        WHERE a.classroom = ?
        ORDER BY a.role_type ASC, a.id ASC
    ");
    $stmt->execute([$classroom]);
    $advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. ดึงหัวข้อโฮมรูมรายวัน (homeroom_logs)
    $stmt = $pdo->prepare("SELECT id, log_date, topic FROM homeroom_logs WHERE classroom = ? AND log_date BETWEEN ? AND ?");
    $stmt->execute([$classroom, $queryStart, $queryEnd]);
    $logsByDate = [];
    $logIdsByDate = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $logsByDate[$l['log_date']]   = $l['topic'];
        $logIdsByDate[$l['log_date']] = (int)$l['id'];
    }

    // 4. ดึงสถิติการเช็คชื่อเข้าแถวหน้าเสาธง (assembly_attendance) เป็นค่าตั้งต้น (Baseline)
    $stmt = $pdo->prepare("
        SELECT student_id, date, status, note, updated_at
        FROM assembly_attendance
        WHERE classroom = ? AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$classroom, $queryStart, $queryEnd]);
    $attRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $assemblyAttByStudentAndDate = [];
    $notesByStudentAndDate       = [];
    $checkedCountByDate          = [];
    $lastUpdatedByDate           = [];

    $statusMap = [
        'ม'   => 'มา',
        'มา'  => 'มา',
        'ข'   => 'ขาด',
        'ขาด' => 'ขาด',
        'ล'   => 'ลา',
        'ลา'  => 'ลา',
        'ด'   => 'ขาด', // โดดเข้าแถว -> ตั้งต้นเป็นขาด (และแสดงป้ายกำกับว่าโดดเข้าแถว)
        'โดด' => 'ขาด',
        'ส'   => 'สาย',
        'สาย' => 'สาย',
    ];

    foreach ($attRows as $a) {
        $normSid = llw_normalize_student_id($a['student_id']);
        $d       = $a['date'];
        $rawSt   = trim($a['status'] ?? 'ม');
        $longSt  = $statusMap[$rawSt] ?? 'มา';

        $assemblyAttByStudentAndDate[$normSid][$d] = ($rawSt === 'ด' || $rawSt === 'โดด') ? 'โดด' : $longSt;
        if (!empty($a['note'])) {
            $notesByStudentAndDate[$normSid][$d] = $a['note'];
        }
        $checkedCountByDate[$d] = ($checkedCountByDate[$d] ?? 0) + 1;
        if (!empty($a['updated_at'])) {
            if (!isset($lastUpdatedByDate[$d]) || $a['updated_at'] > $lastUpdatedByDate[$d]) {
                $lastUpdatedByDate[$d] = $a['updated_at'];
            }
        }
    }

    // 4.5 ดึงข้อมูลการเช็คชื่อโฮมรูม (hr_daily_attendance) ถ้าครูเคยบันทึกแยกไว้ในคาบโฮมรูม
    // ข้อมูลใน hr_daily_attendance จะใช้แสดงในโฮมรูมโดยไม่ไปทับ assembly_attendance
    $homeroomAttByStudentAndDate = [];
    $hasHrSavedByDate = [];
    $hasHrAttTable = (bool)$pdo->query("SHOW TABLES LIKE 'hr_daily_attendance'")->fetchColumn();
    if ($hasHrAttTable && !empty($logIdsByDate)) {
        $logIds = array_values($logIdsByDate);
        $dateByLogId = array_flip($logIdsByDate);
        $inPlaceholders = implode(',', array_fill(0, count($logIds), '?'));
        $stmtHrAtt = $pdo->prepare("SELECT log_id, student_id, status, note FROM hr_daily_attendance WHERE log_id IN ({$inPlaceholders})");
        $stmtHrAtt->execute($logIds);
        foreach ($stmtHrAtt->fetchAll(PDO::FETCH_ASSOC) as $hrRow) {
            $d = $dateByLogId[(int)$hrRow['log_id']] ?? null;
            if (!$d) continue;
            $normSid = llw_normalize_student_id($hrRow['student_id']);
            $st = $hrRow['status'];
            if ($st === 'โดด') $st = 'ขาด';
            $homeroomAttByStudentAndDate[$normSid][$d] = $st;
            $hasHrSavedByDate[$d] = ($hasHrSavedByDate[$d] ?? 0) + 1;
        }
    }

    // รวมสถานะที่จะแสดงในหน้าโฮมรูม:
    // - ถ้าวันนั้นมีบันทึกใน hr_daily_attendance แล้ว ให้ใช้ค่าจาก hr_daily_attendance
    // - ถ้ายังไม่มี ให้ดึงค่าจาก assembly_attendance มาใส่ให้อัตโนมัติ!
    $effectiveAttByStudentAndDate = [];
    foreach ($students as $st) {
        $sid = $st['student_id'];
        $asmSt = $assemblyAttByStudentAndDate[$sid][$selected_date] ?? null;
        $hrSt  = $homeroomAttByStudentAndDate[$sid][$selected_date] ?? null;

        if ($hrSt !== null) {
            $effectiveAttByStudentAndDate[$sid][$selected_date] = $hrSt;
        } elseif ($asmSt !== null) {
            // ถ้าตอนเข้าแถวเป็น 'โดด' ในโฮมรูมให้เริ่มต้นเป็น 'ขาด' (แต่ครูเปลี่ยนเป็น 'สาย' หรือ 'มา' ได้ถ้าเข้าโฮมรูม)
            $effectiveAttByStudentAndDate[$sid][$selected_date] = ($asmSt === 'โดด') ? 'ขาด' : $asmSt;
        } else {
            $effectiveAttByStudentAndDate[$sid][$selected_date] = 'มา';
        }
    }

    // 5. ดึงรูปภาพกิจกรรม (homeroom_photos)
    $stmt = $pdo->prepare("SELECT log_date, image_path FROM homeroom_photos WHERE classroom = ? AND log_date BETWEEN ? AND ?");
    $stmt->execute([$classroom, $queryStart, $queryEnd]);
    $photosByDate = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $photosByDate[$p['log_date']] = $p['image_path'];
    }

    // 6. คำนวณสถานะรายสัปดาห์ (สัปดาห์ที่ 1 - 20) ของภาคเรียนที่เลือกจริง
    $termInfo  = llw_get_term_dates($academic_year, $semester, $pdo);
    $termStart = $termInfo['start_date'];
    $termEnd   = $termInfo['end_date'];

    $tsStart  = strtotime($termStart);
    $dowStart = (int)date('N', $tsStart); // 1=Mon .. 7=Sun
    if ($dowStart <= 5) {
        $week1MondayTs = strtotime('-' . ($dowStart - 1) . ' days', $tsStart);
    } else {
        $week1MondayTs = strtotime('+' . (8 - $dowStart) . ' days', $tsStart);
    }

    $week20FridayTs = strtotime('+19 weeks +4 days', $week1MondayTs);
    $semRangeStart  = date('Y-m-d', $week1MondayTs);
    $semRangeEnd    = date('Y-m-d', $week20FridayTs);

    $stmtLogDates = $pdo->prepare("
        SELECT DISTINCT log_date FROM homeroom_logs
        WHERE classroom = ? AND log_date BETWEEN ? AND ?
    ");
    $stmtLogDates->execute([$classroom, $semRangeStart, $semRangeEnd]);
    $homeroomLoggedDates = array_flip($stmtLogDates->fetchAll(PDO::FETCH_COLUMN));

    $stmtAttDates = $pdo->prepare("
        SELECT DISTINCT date FROM assembly_attendance
        WHERE classroom = ? AND date BETWEEN ? AND ?
    ");
    $stmtAttDates->execute([$classroom, $semRangeStart, $semRangeEnd]);
    $assemblyCheckedDates = array_flip($stmtAttDates->fetchAll(PDO::FETCH_COLUMN));

    $weeks = [];
    $activeWeekNumber = 1;
    $selTs = strtotime($selected_date);

    for ($w = 1; $w <= 20; $w++) {
        $wMonTs = strtotime('+' . ($w - 1) . ' weeks', $week1MondayTs);
        $wFriTs = strtotime('+4 days', $wMonTs);
        $wSunTs = strtotime('+6 days', $wMonTs);

        $wMonStr = date('Y-m-d', $wMonTs);
        $wFriStr = date('Y-m-d', $wFriTs);

        $hrDaysCount  = 0;
        $asmDaysCount = 0;
        for ($dIdx = 0; $dIdx < 5; $dIdx++) {
            $dayStr = date('Y-m-d', strtotime("+{$dIdx} days", $wMonTs));
            if (isset($homeroomLoggedDates[$dayStr])) {
                $hrDaysCount++;
            }
            if (isset($assemblyCheckedDates[$dayStr])) {
                $asmDaysCount++;
            }
        }

        if ($selTs >= $wMonTs && $selTs <= $wSunTs) {
            $activeWeekNumber = $w;
        }

        $weeks[] = [
            'week'          => $w,
            'monday'        => $wMonStr,
            'friday'        => $wFriStr,
            'label_range'   => date('d/m', $wMonTs) . ' - ' . date('d/m', $wFriTs),
            'logged_days'   => $hrDaysCount,
            'assembly_days' => $asmDaysCount,
        ];
    }

    if ($selTs > $week20FridayTs) {
        $activeWeekNumber = 20;
    } elseif ($selTs < $week1MondayTs) {
        $activeWeekNumber = 1;
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'students'            => $students,
            'advisors'            => $advisors,
            'logs'                => $logsByDate,
            'attendance'          => $effectiveAttByStudentAndDate,
            'assembly_attendance' => $assemblyAttByStudentAndDate,
            'homeroom_saved_date' => $hasHrSavedByDate,
            'notes'               => $notesByStudentAndDate,
            'checked_by_date'     => $checkedCountByDate,
            'updated_by_date'     => $lastUpdatedByDate,
            'photos'              => $photosByDate,
            'weeks'               => $weeks,
            'active_week'         => $activeWeekNumber,
            'term_start'          => $termStart,
            'term_end'            => $termEnd,
            'semester'            => $semester,
            'academic_year'       => $academic_year,
        ]
    ]);

} catch (Throwable $e) {
    llw_log_error($e, 'homeroom/get_log_data');
    echo json_encode([
        'status'  => 'error',
        'message' => 'เกิดข้อผิดพลาดในการโหลดข้อมูลโฮมรูม'
    ]);
}
