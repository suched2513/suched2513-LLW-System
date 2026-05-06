<?php
/**
 * includes/telegram_bot.php
 * ฟังก์ชันและ class กลางสำหรับ Telegram Bot API
 *
 * ครอบคลุม:
 *  - sendTelegramMessage()  — ส่งข้อความ (backward compat function)
 *  - TelegramBot            — class เดิม (backward compat)
 *  - Telegram               — class ใหม่ พร้อม static methods ทั้งหมด
 */

// ─── Backward-compatible function ──────────────────────────────────────
function sendTelegramMessage(string $token, string $chatId, string $message): bool
{
    if (empty($token) || empty($chatId)) return false;

    $url  = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id'    => $chatId,
        'text'       => $message,
        'parse_mode' => 'HTML',
    ];
    $opts = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5,
        ],
    ];
    $ctx = stream_context_create($opts);
    $result = @file_get_contents($url, false, $ctx);
    return $result !== false;
}

// ─── Backward-compatible TelegramBot class ─────────────────────────────
class TelegramBot
{
    private string $token;
    private string $chatId;

    public function __construct(string $token, string $chatId)
    {
        $this->token  = $token;
        $this->chatId = $chatId;
    }

    public function sendMessage(string $message): bool
    {
        return sendTelegramMessage($this->token, $this->chatId, $message);
    }
}

// ─── Telegram Static Class — เมธอดทั้งหมด ─────────────────────────────
/**
 * Class Telegram
 *
 * ใช้งาน:
 *   Telegram::init($token, $defaultChatId);  // ตั้งค่า 1 ครั้ง
 *   Telegram::sendMessage('สวัสดี', $chatId);
 *   Telegram::sendPhoto('/path/to/file.jpg', 'caption', $chatId);
 *   ...
 */
class Telegram
{
    private static string $token    = '';
    private static string $chatId   = '';
    private static int    $timeout  = 15;   // วินาที

    // ─── ตั้งค่า token และ default chat_id ─────────────────────────
    public static function init(string $token, string $defaultChatId = ''): void
    {
        self::$token  = $token;
        self::$chatId = $defaultChatId;
    }

    // ─── Helper: เรียก Telegram Bot API ────────────────────────────
    /**
     * เรียก Telegram API ด้วย cURL
     *
     * @param  string $method   ชื่อ method เช่น sendMessage, getFile
     * @param  array  $params   พารามิเตอร์
     * @param  bool   $multipart  true ถ้าต้องส่งไฟล์ (multipart/form-data)
     * @return array  ['ok' => bool, 'result' => mixed, 'description' => string]
     */
    private static function call(string $method, array $params = [], bool $multipart = false): array
    {
        if (empty(self::$token)) {
            return ['ok' => false, 'description' => 'Telegram token not set'];
        }

        $url = "https://api.telegram.org/bot" . self::$token . "/{$method}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::$timeout,
            CURLOPT_POST           => true,
        ]);

        if ($multipart) {
            // multipart/form-data สำหรับส่งไฟล์
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } else {
            // application/json
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
        }

        $raw    = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            error_log("[Telegram::call] cURL error: {$curlErr}");
            return ['ok' => false, 'description' => "cURL error: {$curlErr}"];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'description' => 'Invalid JSON response'];
        }
        return $decoded;
    }

    // ────────────────────────────────────────────────────────────────
    // 3.1 sendMessage — ส่งข้อความธรรมดา
    // ────────────────────────────────────────────────────────────────
    /**
     * ส่งข้อความ HTML ไปยัง chat_id
     *
     * @param  string      $text    ข้อความ (รองรับ HTML tags)
     * @param  string|null $chatId  ถ้า null ใช้ default chat_id
     * @return array
     */
    public static function sendMessage(string $text, ?string $chatId = null): array
    {
        return self::call('sendMessage', [
            'chat_id'    => $chatId ?? self::$chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // 3.1 sendPhoto — ส่งรูปภาพ 1 รูป
    // ────────────────────────────────────────────────────────────────
    /**
     * ส่งรูปภาพผ่าน multipart/form-data
     *
     * @param  string      $photoPath  path ไฟล์รูป (เต็ม)
     * @param  string      $caption    คำอธิบายรูป (HTML)
     * @param  string|null $chatId
     * @return array ['ok'=>bool, 'result'=>..., 'description'=>...]
     */
    public static function sendPhoto(string $photoPath, string $caption = '', ?string $chatId = null): array
    {
        if (!file_exists($photoPath)) {
            return ['ok' => false, 'description' => "File not found: {$photoPath}"];
        }

        $params = [
            'chat_id'    => $chatId ?? self::$chatId,
            'photo'      => new CURLFile($photoPath, mime_content_type($photoPath), basename($photoPath)),
            'parse_mode' => 'HTML',
        ];
        if ($caption !== '') {
            $params['caption'] = $caption;
        }

        return self::call('sendPhoto', $params, true);
    }

    // ────────────────────────────────────────────────────────────────
    // 3.2 sendMediaGroup — ส่งหลายรูปในครั้งเดียว (album)
    // ────────────────────────────────────────────────────────────────
    /**
     * ส่งรูปหลายรูปเป็น album (สูงสุด 10 รูป)
     *
     * @param  string[]    $photoPaths  array ของ path ไฟล์รูป
     * @param  string      $firstCaption  คำอธิบายของรูปแรก
     * @param  string|null $chatId
     * @return array
     */
    public static function sendMediaGroup(array $photoPaths, string $firstCaption = '', ?string $chatId = null): array
    {
        if (empty($photoPaths)) {
            return ['ok' => false, 'description' => 'No photos provided'];
        }

        // Telegram รองรับ album สูงสุด 10 รูป
        $photoPaths = array_slice($photoPaths, 0, 10);

        // สร้าง media array และ attach ไฟล์
        $media   = [];
        $params  = ['chat_id' => $chatId ?? self::$chatId];

        foreach ($photoPaths as $i => $path) {
            if (!file_exists($path)) continue;

            $attachKey = "photo_{$i}";
            $params[$attachKey] = new CURLFile($path, mime_content_type($path), basename($path));

            $item = [
                'type'  => 'photo',
                'media' => "attach://{$attachKey}",
            ];

            // caption เฉพาะรูปแรก
            if ($i === 0 && $firstCaption !== '') {
                $item['caption']    = $firstCaption;
                $item['parse_mode'] = 'HTML';
            }

            $media[] = $item;
        }

        if (empty($media)) {
            return ['ok' => false, 'description' => 'No valid photos'];
        }

        // media ต้อง encode เป็น JSON string ใน multipart
        $params['media'] = json_encode($media, JSON_UNESCAPED_UNICODE);

        return self::call('sendMediaGroup', $params, true);
    }

    // ────────────────────────────────────────────────────────────────
    // 3.3 sendMessageWithKeyboard — ส่งข้อความพร้อม Inline Keyboard
    // ────────────────────────────────────────────────────────────────
    /**
     * ส่งข้อความพร้อม Inline Keyboard
     *
     * @param  string      $text
     * @param  array       $inlineKeyboard  [[['text'=>'...','callback_data'=>'...'], ...], ...]
     * @param  string|null $chatId
     * @return array
     *
     * ตัวอย่าง $inlineKeyboard:
     *   [
     *     [['text' => 'จุดที่ 1', 'callback_data' => 'rpt:123']],
     *     [['text' => 'จุดที่ 2', 'callback_data' => 'rpt:124']],
     *   ]
     */
    public static function sendMessageWithKeyboard(string $text, array $inlineKeyboard, ?string $chatId = null): array
    {
        return self::call('sendMessage', [
            'chat_id'      => $chatId ?? self::$chatId,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => [
                'inline_keyboard' => $inlineKeyboard,
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // 3.4 downloadFile — ดาวน์โหลดไฟล์จาก Telegram
    // ────────────────────────────────────────────────────────────────
    /**
     * ดาวน์โหลดไฟล์จาก Telegram API แล้วบันทึกลงดิสก์
     *
     * @param  string $fileId    Telegram file_id
     * @param  string $saveTo    path ที่ต้องการบันทึก (เต็ม)
     * @return array  ['ok'=>bool, 'file_path'=>string, 'description'=>string]
     */
    public static function downloadFile(string $fileId, string $saveTo): array
    {
        // ขั้นที่ 1: getFile API → ได้ file_path (ชั่วคราว)
        $res = self::call('getFile', ['file_id' => $fileId]);
        if (empty($res['ok']) || empty($res['result']['file_path'])) {
            return ['ok' => false, 'description' => 'getFile failed: ' . ($res['description'] ?? 'unknown')];
        }

        $filePath = $res['result']['file_path'];
        $downloadUrl = "https://api.telegram.org/file/bot" . self::$token . "/{$filePath}";

        // ขั้นที่ 2: สร้างโฟลเดอร์ปลายทาง
        $dir = dirname($saveTo);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return ['ok' => false, 'description' => "Cannot create directory: {$dir}"];
        }

        // ขั้นที่ 3: ดาวน์โหลดด้วย cURL
        $fp = fopen($saveTo, 'wb');
        if (!$fp) {
            return ['ok' => false, 'description' => "Cannot open file for writing: {$saveTo}"];
        }

        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $success  = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            @unlink($saveTo); // ลบไฟล์ที่โหลดไม่สำเร็จ
            return ['ok' => false, 'description' => "Download failed (HTTP {$httpCode}): {$curlErr}"];
        }

        return ['ok' => true, 'file_path' => $saveTo];
    }

    // ────────────────────────────────────────────────────────────────
    // 3.5 answerCallbackQuery — ตอบ callback query (กดปุ่ม Inline)
    // ────────────────────────────────────────────────────────────────
    /**
     * ตอบ callback_query เพื่อหยุด loading spinner ใน Telegram
     *
     * @param  string      $callbackQueryId
     * @param  string|null $text  ข้อความ toast ที่แสดงชั่วคราว (ถ้ามี)
     * @param  bool        $showAlert  true=แสดงเป็น alert modal
     * @return array
     */
    public static function answerCallbackQuery(
        string  $callbackQueryId,
        ?string $text      = null,
        bool    $showAlert = false
    ): array {
        $params = ['callback_query_id' => $callbackQueryId];
        if ($text !== null) {
            $params['text']       = $text;
            $params['show_alert'] = $showAlert;
        }
        return self::call('answerCallbackQuery', $params);
    }

    // ────────────────────────────────────────────────────────────────
    // 3.6 setWebhook — ติดตั้ง Webhook
    // ────────────────────────────────────────────────────────────────
    /**
     * ติดตั้ง Telegram Webhook
     *
     * @param  string   $url           URL endpoint ของ webhook
     * @param  string   $secretToken   Secret ตรวจ header X-Telegram-Bot-Api-Secret-Token
     * @param  string[] $allowedUpdates  เช่น ['message','callback_query']
     * @return array
     */
    public static function setWebhook(
        string $url,
        string $secretToken    = '',
        array  $allowedUpdates = ['message', 'callback_query']
    ): array {
        $params = [
            'url'             => $url,
            'allowed_updates' => $allowedUpdates,
            'drop_pending_updates' => true,
        ];
        if ($secretToken !== '') {
            $params['secret_token'] = $secretToken;
        }
        return self::call('setWebhook', $params);
    }

    // ────────────────────────────────────────────────────────────────
    // 3.7 deleteWebhook — ลบ Webhook
    // ────────────────────────────────────────────────────────────────
    public static function deleteWebhook(): array
    {
        return self::call('deleteWebhook', ['drop_pending_updates' => true]);
    }

    // ────────────────────────────────────────────────────────────────
    // 3.8 getWebhookInfo — ดูสถานะ Webhook
    // ────────────────────────────────────────────────────────────────
    public static function getWebhookInfo(): array
    {
        return self::call('getWebhookInfo');
    }

    // ────────────────────────────────────────────────────────────────
    // Public alias สำหรับ call() — ใช้เมื่อต้องการเรียก API อื่นโดยตรง
    // ────────────────────────────────────────────────────────────────
    public static function call_public(string $method, array $params = []): array
    {
        return self::call($method, $params);
    }

    // ────────────────────────────────────────────────────────────────
    // Helper: สร้าง Thumbnail ด้วย GD
    // ────────────────────────────────────────────────────────────────
    /**
     * สร้าง thumbnail (max ด้านละ $maxSize px) จากไฟล์ภาพ
     *
     * @param  string $sourcePath  path ต้นฉบับ
     * @param  string $destPath    path thumbnail ที่จะบันทึก
     * @param  int    $maxSize     ขนาดสูงสุดด้านใดด้านหนึ่ง (px)
     * @return bool
     */
    public static function createThumbnail(string $sourcePath, string $destPath, int $maxSize = 400): bool
    {
        if (!extension_loaded('gd')) {
            // ถ้าไม่มี GD ให้ copy ไฟล์เต็มเป็น thumbnail แทน
            return copy($sourcePath, $destPath);
        }

        // ตรวจ MIME
        $mime = @mime_content_type($sourcePath);
        switch ($mime) {
            case 'image/jpeg':
                $src = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $src = @imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                $src = @imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        if (!$src) return false;

        $origW = imagesx($src);
        $origH = imagesy($src);

        // คำนวณ scale ให้ด้านที่ยาวกว่าไม่เกิน $maxSize
        if ($origW <= $maxSize && $origH <= $maxSize) {
            // รูปเล็กกว่า maxSize อยู่แล้ว ไม่ต้อง resize
            imagedestroy($src);
            return copy($sourcePath, $destPath);
        }

        $ratio = ($origW > $origH) ? ($maxSize / $origW) : ($maxSize / $origH);
        $newW  = (int)round($origW * $ratio);
        $newH  = (int)round($origH * $ratio);

        $thumb = imagecreatetruecolor($newW, $newH);

        // PNG transparency
        if ($mime === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        // บันทึก thumbnail (ใช้ JPEG เสมอเพื่อประหยัดพื้นที่)
        $dir = dirname($destPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $result = imagejpeg($thumb, $destPath, 85);

        imagedestroy($src);
        imagedestroy($thumb);

        return $result;
    }

    // ────────────────────────────────────────────────────────────────
    // Helper: Extract EXIF GPS
    // ────────────────────────────────────────────────────────────────
    /**
     * ดึง GPS coordinate จาก EXIF ของไฟล์ภาพ
     *
     * @param  string $filePath
     * @return array|null  ['lat'=>float, 'lng'=>float] หรือ null ถ้าไม่มี GPS
     */
    public static function extractExifGps(string $filePath): ?array
    {
        if (!function_exists('exif_read_data')) return null;

        $exif = @exif_read_data($filePath, 'GPS');
        if (empty($exif['GPS']['GPSLatitude']) || empty($exif['GPS']['GPSLongitude'])) {
            return null;
        }

        $lat = self::gpsToDecimal(
            $exif['GPS']['GPSLatitude'],
            $exif['GPS']['GPSLatitudeRef']
        );
        $lng = self::gpsToDecimal(
            $exif['GPS']['GPSLongitude'],
            $exif['GPS']['GPSLongitudeRef']
        );

        return ['lat' => $lat, 'lng' => $lng];
    }

    /** แปลง GPS DMS array → Decimal Degrees */
    private static function gpsToDecimal(array $gps, string $ref): float
    {
        [$deg, $min, $sec] = $gps;
        $d = self::fracToDecimal($deg);
        $m = self::fracToDecimal($min);
        $s = self::fracToDecimal($sec);
        $decimal = $d + ($m / 60) + ($s / 3600);
        return (strtoupper($ref) === 'S' || strtoupper($ref) === 'W') ? -$decimal : $decimal;
    }

    /** แปลง "numerator/denominator" string → float */
    private static function fracToDecimal(string $frac): float
    {
        if (str_contains($frac, '/')) {
            [$n, $d] = explode('/', $frac);
            return $d == 0 ? 0.0 : (float)$n / (float)$d;
        }
        return (float)$frac;
    }
}
