<?php
namespace PP5\App\Services;

/**
 * CitizenIdCrypto — บริการเข้ารหัสและถอดรหัสเลขประจำตัวประชาชน 13 หลักตามมาตรฐาน PDPA
 *
 * ใช้ AES-256-CBC พร้อม HMAC-SHA256 ป้องกันการแก้ไขข้อมูล (Authenticated Encryption)
 * และสร้างเลขแบบปิดบังบางส่วน (Masked: 1-xxxx-xxxxx-xx-1) สำหรับแสดงผลให้ผู้ไม่มีสิทธิ์เต็ม
 */
class CitizenIdCrypto
{
    private const CIPHER = 'aes-256-cbc';

    /**
     * ดึงกุญแจเข้ารหัสระดับระบบ
     */
    private static function getSecretKey(): string
    {
        $raw = defined('DB_PASS') && DB_PASS !== ''
            ? 'LLW_PP5_PDPA_KEY_' . DB_NAME . '_' . DB_PASS
            : 'LLW_PP5_PDPA_DEFAULT_KEY_2569_LALOMWITTAYA';
        return hash('sha256', $raw, true);
    }

    /**
     * ตรวจสอบความถูกต้องของเลขบัตรประชาชนไทย 13 หลัก (รวม Check Digit)
     */
    public static function isValidCitizenId(string $citizenId): bool
    {
        $digits = preg_replace('/[^0-9]/', '', $citizenId);
        if (strlen($digits) !== 13) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int)$digits[$i]) * (13 - $i);
        }
        $check = (11 - ($sum % 11)) % 10;
        return $check === (int)$digits[12];
    }

    /**
     * เข้ารหัสเลขบัตรประชาชน 13 หลัก (Encrypted Cast)
     */
    public static function encrypt(string $plainCitizenId): ?string
    {
        $clean = preg_replace('/[^0-9]/', '', trim($plainCitizenId));
        if ($clean === '') {
            return null;
        }
        $key    = self::getSecretKey();
        $ivLen  = openssl_cipher_iv_length(self::CIPHER);
        $iv     = random_bytes($ivLen);
        $cipher = openssl_encrypt($clean, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return null;
        }
        $hmac = hash_hmac('sha256', $iv . $cipher, $key, true);
        return base64_encode($iv . $hmac . $cipher);
    }

    /**
     * ถอดรหัสเลขบัตรประชาชน (เฉพาะผู้มีสิทธิ์ตาม PDPA เช่น งานทะเบียนและวัดผล)
     */
    public static function decrypt(?string $encryptedPayload): ?string
    {
        if (!$encryptedPayload) {
            return null;
        }
        $raw = base64_decode($encryptedPayload, true);
        if ($raw === false) {
            return null;
        }
        $key   = self::getSecretKey();
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($raw) <= ($ivLen + 32)) {
            return null;
        }
        $iv         = substr($raw, 0, $ivLen);
        $hmac       = substr($raw, $ivLen, 32);
        $cipherText = substr($raw, $ivLen + 32);
        $calcHmac   = hash_hmac('sha256', $iv . $cipherText, $key, true);

        if (!hash_equals($hmac, $calcHmac)) {
            return null;
        }
        $plain = openssl_decrypt($cipherText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $plain !== false ? $plain : null;
    }

    /**
     * จัดรูปแบบเลขบัตรประชาชนแบบเต็ม (เช่น 1-3399-01213-74-1)
     */
    public static function formatFull(?string $digits): string
    {
        $clean = preg_replace('/[^0-9]/', '', (string)$digits);
        if (strlen($clean) !== 13) {
            return $clean ?: '-';
        }
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($clean, 0, 1),
            substr($clean, 1, 4),
            substr($clean, 5, 5),
            substr($clean, 10, 2),
            substr($clean, 12, 1)
        );
    }

    /**
     * จัดรูปแบบเลขบัตรประชาชนแบบปิดบัง PDPA (เช่น 1-3399-XXXXX-74-1)
     */
    public static function formatMasked(?string $digitsOrMasked): string
    {
        $clean = preg_replace('/[^0-9]/', '', (string)$digitsOrMasked);
        if (strlen($clean) === 13) {
            return sprintf(
                '%s-%s-XXXXX-%s-%s',
                substr($clean, 0, 1),
                substr($clean, 1, 4),
                substr($clean, 10, 2),
                substr($clean, 12, 1)
            );
        }
        return $digitsOrMasked ? htmlspecialchars($digitsOrMasked, ENT_QUOTES, 'UTF-8') : '-';
    }

    /**
     * Alias สำหรับ formatMasked
     */
    public static function mask(?string $digitsOrMasked): string
    {
        return self::formatMasked($digitsOrMasked);
    }
}
