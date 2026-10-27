<?php

use App\Services\BahtTextService;

if (!function_exists('baht_text')) {
    /**
     * แปลงจำนวนเงินเป็นตัวอักษรภาษาไทย
     */
    function baht_text(float|int|string $amount): string
    {
        return BahtTextService::toBahtText($amount);
    }
}

if (!function_exists('thai_fiscal_year')) {
    /**
     * คำนวณปีงบประมาณไทย (พ.ศ.) จากวันที่ที่กำหนด
     * ปีงบประมาณเริ่ม 1 ตุลาคม - 30 กันยายน ของปีถัดไป
     * เช่น 30 ก.ย. 2569 => 2569, 1 ต.ค. 2569 => 2570
     */
    function thai_fiscal_year(?string $dateStr = null): int
    {
        $timestamp = $dateStr ? strtotime($dateStr) : time();
        $month = (int)date('n', $timestamp);
        $yearCe = (int)date('Y', $timestamp);
        $yearBe = $yearCe + 543;

        return ($month >= 10) ? ($yearBe + 1) : $yearBe;
    }
}

if (!function_exists('thai_date')) {
    /**
     * แปลงวันที่ ค.ศ. เป็นรูปแบบวันที่ภาษาไทย (พ.ศ.)
     *
     * @param string|null $dateStr วันที่ Y-m-d หรือ datetime
     * @param bool $includeTime แสดงเวลาด้วยหรือไม่
     * @param bool $shortMonth ใช้ชื่อเดือนแบบย่อหรือไม่
     */
    function thai_date(?string $dateStr, bool $includeTime = false, bool $shortMonth = false): string
    {
        if (!$dateStr || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($dateStr);
        if (!$timestamp) return $dateStr;

        $thaiMonthsFull = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
            5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
            9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
        ];

        $thaiMonthsShort = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
            5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
            9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];

        $day = (int)date('j', $timestamp);
        $month = (int)date('n', $timestamp);
        $yearBe = (int)date('Y', $timestamp) + 543;
        $monthName = $shortMonth ? $thaiMonthsShort[$month] : $thaiMonthsFull[$month];

        $formatted = "{$day} {$monthName} {$yearBe}";
        if ($includeTime) {
            $formatted .= ' ' . date('H:i', $timestamp) . ' น.';
        }

        return $formatted;
    }
}

if (!function_exists('money_format_th')) {
    /**
     * จัดรูปแบบจำนวนเงินเป็นตัวเลขทศนิยม 2 ตำแหน่ง
     */
    function money_format_th(float|int|string|null $amount): string
    {
        if ($amount === null || $amount === '') return '0.00';
        return number_format((float)$amount, 2, '.', ',');
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML entities ป้องกัน XSS
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
