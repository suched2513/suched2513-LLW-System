<?php
/**
 * config/academic.php — ปีการศึกษา / ภาคเรียนปัจจุบัน (ค่ากลางของทุกระบบ)
 *
 * ค่าเริ่มต้นคำนวณจากวันที่อัตโนมัติ:
 *   - ภาคเรียนที่ 1 : 16 พ.ค. – 31 ต.ค.
 *   - ภาคเรียนที่ 2 : 1 พ.ย. – 15 พ.ค. ของปีถัดไป
 *   - ปีการศึกษา (พ.ศ.) นับจากปีที่เริ่มภาคเรียนที่ 1
 *
 * ถ้าต้องการบังคับค่าเอง ให้กำหนดตัวเลขใน LLW_FORCE_ACADEMIC_YEAR / LLW_FORCE_SEMESTER
 * (ปล่อยเป็น null เพื่อใช้ค่าอัตโนมัติ)
 */

if (!defined('LLW_FORCE_ACADEMIC_YEAR')) define('LLW_FORCE_ACADEMIC_YEAR', null); // เช่น 2569
if (!defined('LLW_FORCE_SEMESTER'))      define('LLW_FORCE_SEMESTER', null);      // 1 หรือ 2

// วันเริ่มภาคเรียน (เดือน, วัน)
if (!defined('LLW_SEM1_START')) define('LLW_SEM1_START', [5, 16]);
if (!defined('LLW_SEM2_START')) define('LLW_SEM2_START', [11, 1]);

if (!function_exists('llw_current_semester')) {
    function llw_current_semester(?int $ts = null): int {
        if (LLW_FORCE_SEMESTER !== null) return (int)LLW_FORCE_SEMESTER;
        $ts = $ts ?? time();
        $md = (int)date('n', $ts) * 100 + (int)date('j', $ts);
        $s1 = LLW_SEM1_START[0] * 100 + LLW_SEM1_START[1];
        $s2 = LLW_SEM2_START[0] * 100 + LLW_SEM2_START[1];
        return ($md >= $s1 && $md < $s2) ? 1 : 2;
    }
}

if (!function_exists('llw_current_academic_year')) {
    function llw_current_academic_year(?int $ts = null): int {
        if (LLW_FORCE_ACADEMIC_YEAR !== null) return (int)LLW_FORCE_ACADEMIC_YEAR;
        $ts = $ts ?? time();
        $md = (int)date('n', $ts) * 100 + (int)date('j', $ts);
        $s1 = LLW_SEM1_START[0] * 100 + LLW_SEM1_START[1];
        $y  = (int)date('Y', $ts) + 543;
        return $md >= $s1 ? $y : $y - 1; // ม.ค.–พ.ค. ยังเป็นปีการศึกษาก่อนหน้า
    }
}
