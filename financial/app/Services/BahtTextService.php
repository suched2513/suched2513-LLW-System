<?php

namespace App\Services;

/**
 * Class BahtTextService
 * แปลงจำนวนเงินตัวเลข (DECIMAL) เป็นตัวอักษรภาษาไทยตามหลักเกณฑ์ราชการ
 *
 * ตัวอย่างการทดสอบ:
 *  - 0.00         => ศูนย์บาทถ้วน
 *  - 0.50         => ศูนย์บาทห้าสิบสตางค์
 *  - 1.00         => หนึ่งบาทถ้วน
 *  - 11.00        => สิบเอ็ดบาทถ้วน
 *  - 21.00        => ยี่สิบเอ็ดบาทถ้วน
 *  - 101.00       => หนึ่งร้อยเอ็ดบาทถ้วน
 *  - 1,000.25     => หนึ่งพันบาทยี่สิบห้าสตางค์
 *  - 1,000,001.00 => หนึ่งล้านเอ็ดบาทถ้วน
 */
class BahtTextService
{
    private static array $digits = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    private static array $positions = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน', 'ล้าน'];

    /**
     * แปลงจำนวนเงินเป็นตัวอักษรไทย
     *
     * @param float|int|string $amount จำนวนเงิน
     * @return string ข้อความจำนวนเงินภาษาไทย
     */
    public static function toBahtText(float|int|string $amount): string
    {
        // ทำความสะอาดและจัดรูปแบบตัวเลข ป้องกัน Floating Point Error
        $amountStr = is_string($amount) ? str_replace(',', '', trim($amount)) : (string)$amount;
        if (!is_numeric($amountStr)) {
            return 'ศูนย์บาทถ้วน';
        }

        $isNegative = false;
        if (str_starts_with($amountStr, '-')) {
            $isNegative = true;
            $amountStr = substr($amountStr, 1);
        }

        // ปัดเศษทศนิยม 2 ตำแหน่ง
        $amountStr = number_format((float)$amountStr, 2, '.', '');
        [$integerPart, $fractionPart] = explode('.', $amountStr);

        // ตรวจสอบยอด 0.00
        if ((int)$integerPart === 0 && (int)$fractionPart === 0) {
            return 'ศูนย์บาทถ้วน';
        }

        $result = '';

        // แปลงส่วนจำนวนเต็ม (บาท)
        if ((int)$integerPart > 0) {
            $result .= self::convertGroup($integerPart) . 'บาท';
        } else {
            $result .= 'ศูนย์บาท';
        }

        // แปลงส่วนทศนิยม (สตางค์)
        if ((int)$fractionPart === 0) {
            $result .= 'ถ้วน';
        } else {
            $result .= self::convertFraction($fractionPart) . 'สตางค์';
        }

        return ($isNegative ? 'ลบ' : '') . $result;
    }

    /**
     * แปลงตัวเลขจำนวนเต็ม รองรับค่าเกินล้าน
     */
    private static function convertGroup(string $numberStr): string
    {
        $len = strlen($numberStr);
        if ($len > 6) {
            $higherPart = substr($numberStr, 0, $len - 6);
            $lowerPart = substr($numberStr, $len - 6);
            return self::convertGroup($higherPart) . 'ล้าน' . self::convertSixDigits($lowerPart, true);
        }

        return self::convertSixDigits($numberStr, false);
    }

    /**
     * แปลงตัวเลขไม่เกิน 6 หลัก (0 - 999,999)
     */
    private static function convertSixDigits(string $digitsStr, bool $hasHigherMillion = false): string
    {
        $len = strlen($digitsStr);
        $text = '';
        $nonZeroCount = 0;

        for ($i = 0; $i < $len; $i++) {
            $digit = (int)$digitsStr[$i];
            $pos = $len - $i - 1; // 0=หน่วย, 1=สิบ, 2=ร้อย, ...

            if ($digit !== 0) {
                $nonZeroCount++;

                if ($pos === 0) {
                    // หลักหน่วย: ออกเสียง "เอ็ด" เมื่อมีค่ามากกว่า 1 หลัก หรือมีหลักล้านนำหน้า
                    if ($digit === 1 && (($len > 1 && $nonZeroCount > 1) || $hasHigherMillion)) {
                        $text .= 'เอ็ด';
                    } else {
                        $text .= self::$digits[$digit];
                    }
                } elseif ($pos === 1) {
                    // หลักสิบ
                    if ($digit === 1) {
                        $text .= 'สิบ';
                    } elseif ($digit === 2) {
                        $text .= 'ยี่สิบ';
                    } else {
                        $text .= self::$digits[$digit] . 'สิบ';
                    }
                } else {
                    // หลักร้อย พัน หมื่น แสน
                    $text .= self::$digits[$digit] . self::$positions[$pos];
                }
            }
        }

        return $text;
    }

    /**
     * แปลงส่วนสตางค์ (2 หลัก)
     */
    private static function convertFraction(string $satangStr): string
    {
        $d1 = (int)$satangStr[0];
        $d2 = (int)$satangStr[1];
        $text = '';

        if ($d1 > 0) {
            if ($d1 === 1) {
                $text .= 'สิบ';
            } elseif ($d1 === 2) {
                $text .= 'ยี่สิบ';
            } else {
                $text .= self::$digits[$d1] . 'สิบ';
            }
        }

        if ($d2 > 0) {
            if ($d2 === 1 && $d1 > 0) {
                $text .= 'เอ็ด';
            } else {
                $text .= self::$digits[$d2];
            }
        }

        return $text;
    }
}
