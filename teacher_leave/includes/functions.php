<?php
/**
 * teacher_leave/includes/functions.php
 * Logic for calculating leave days and fiscal years
 */

/**
 * คำนวณจำนวนวันลา โดยหักวันหยุดเสาร์-อาทิตย์ และวันหยุดราชการ
 * @param string $start วันที่เริ่ม (YYYY-MM-DD)
 * @param string $end วันที่สิ้นสุด (YYYY-MM-DD)
 * @param PDO $pdo 
 * @param bool $includeWeekends ถ้าเป็น TRUE จะไม่หักเสาร์-อาทิตย์ (ปกติมักจะหัก)
 * @return float จำนวนวันลาที่ใช้จริง
 */
function calculateLeaveDays($start, $end, $pdo, $includeWeekends = false) {
    if (!$start || !$end) return 0;
    
    $startDate = new DateTime($start);
    $endDate   = new DateTime($end);
    $endDate->modify('+1 day'); // นับรวมวันสุดท้ายด้วย

    // ดึงวันหยุดราชการจาก DB
    $stmt = $pdo->prepare("SELECT holiday_date FROM tl_holidays WHERE holiday_date BETWEEN ? AND ?");
    $stmt->execute([$start, $end]);
    $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $days = 0;
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($startDate, $interval, $endDate);

    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        $dayOfWeek = $date->format('N'); // 1 (Mon) - 7 (Sun)

        // ตรวจสอบวันเสาร์ (6) และอาทิตย์ (7)
        $isWeekend = ($dayOfWeek == 6 || $dayOfWeek == 7);
        $isHoliday = in_array($dateStr, $holidays);

        if ($includeWeekends) {
            $days++;
        } else {
            // หักออกถ้าเป็นวันเสาร์-อาทิตย์ หรือวันหยุด
            if (!$isWeekend && !$isHoliday) {
                $days++;
            }
        }
    }

    return (float)$days;
}

/**
 * หาปีงบประมาณไทย (เริ่ม 1 ต.ค. ของปีก่อนหน้า - 30 ก.ย. ของปีปัจจุบัน)
 * @param string $dateStr วันที่ (YYYY-MM-DD) หรือค่าว่างเพื่อเอาปัจจุบัน
 * @return int ปีพุทธศักราช (BE)
 */
function getThaiFiscalYear($dateStr = '') {
    $date = $dateStr ? new DateTime($dateStr) : new DateTime();
    $month = (int)$date->format('m');
    $year = (int)$date->format('Y');

    // ถ้าเดือน >= 10 แสดงว่าเป็นปีงบประมาณของปีถัดไป
    $fiscalYear = ($month >= 10) ? $year + 1 : $year;
    
    // แปลงเป็นปี พ.ศ. (BE)
    return $fiscalYear + 543;
}

/**
 * ดึงสถิติการลาปัจจุบันของผู้ใช้
 */
function getUserLeaveStats($userId, $fiscalYear, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM tl_stats WHERE user_id = ? AND fiscal_year = ?");
    $stmt->execute([$userId, $fiscalYear]);
    $stats = $stmt->fetch();

    if (!$stats) {
        // สร้างข้อมูลเริ่มต้นถ้ายังไม่มี
        $stmtCreate = $pdo->prepare("INSERT INTO tl_stats (user_id, fiscal_year) VALUES (?, ?)");
        $stmtCreate->execute([$userId, $fiscalYear]);
        
        return [
            'sick_taken' => 0.0,
            'personal_taken' => 0.0,
            'vacation_taken' => 0.0,
            'vacation_quota' => 10.0, // ค่าเริ่มต้น 10 วัน
            'other_taken' => 0.0
        ];
    }
    
    return $stats;
}
