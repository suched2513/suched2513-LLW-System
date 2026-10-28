<?php
/**
 * includes/telegram_bot.php
 * ฟังก์ชันและ class กลางสำหรับ Telegram Bot API
 *
 * ครอบคลุม:
 *  - sendTelegramMessage()  — ส่งข้อความ (backward compat function)
 *  - TelegramBot            — class เดิม (backward compat)
 *  - Telegram               — class ใหม่ พร้อม static methods และระบบ Multi-Token / Multi-Chat Fallback
 */

// ─── Backward-compatible function (ใช้ cURL ผ่าน Telegram class เพื่อความเสถียร) ───
function sendTelegramMessage(string $token, string $chatId, string $message): bool
{
    if (empty(trim($token)) || empty(trim($chatId))) return false;

    Telegram::init(trim($token), trim($chatId));
    $res = Telegram::sendMessage($message, trim($chatId));
    return (bool)($res['ok'] ?? false);
}

// ─── Backward-compatible TelegramBot class ─────────────────────────────
class TelegramBot
{
    private string $token;
    private string $chatId;

    public function __construct(string $token, string $chatId)
    {
        $this->token  = trim($token);
        $this->chatId = trim($chatId);
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
 *   Telegram::sendAttendanceNotify($pdo, $text, $customChatId);
 *   Telegram::sendLeaveNotify($pdo, $text, $customChatId);
 *   Telegram::sendDutyNotify($pdo, $text, $customChatId);
 *   Telegram::sendDutyPhotos($pdo, $photoPaths, $caption, $customChatId);
 *   Telegram::sendWfhNotify($pdo, $text, $customChatId);
 */
class Telegram
{
    private static string $token    = '';
    private static string $chatId   = '';
    private static int    $timeout  = 15;   // วินาที

    // ─── ตั้งค่า token และ default chat_id ─────────────────────────
    public static function init(string $token, string $defaultChatId = ''): void
    {
        self::$token  = trim($token);
        self::$chatId = trim($defaultChatId);
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
        $token = trim(self::$token);
        if (empty($token)) {
            return ['ok' => false, 'description' => 'Telegram token not set'];
        }
        if (isset($params['chat_id']) && is_string($params['chat_id'])) {
            $params['chat_id'] = trim($params['chat_id']);
        }

        // ป้องกันข้อความยาวเกินลิมิตของ Telegram API (text <= 4096, caption <= 1024 ตัวอักษร)
        if (isset($params['text']) && is_string($params['text'])) {
            if (function_exists('mb_convert_encoding')) {
                $params['text'] = mb_convert_encoding($params['text'], 'UTF-8', 'UTF-8');
            }
            if (function_exists('mb_strlen') && mb_strlen($params['text'], 'UTF-8') > 3950) {
                $params['text'] = mb_substr($params['text'], 0, 3920, 'UTF-8') . "\n...(ข้อความถูกตัดทอนเนื่องจากยาวเกินกำหนด)";
                unset($params['parse_mode']);
                $params['text'] = strip_tags($params['text']);
            }
        }
        if (isset($params['caption']) && is_string($params['caption'])) {
            if (function_exists('mb_strlen') && mb_strlen($params['caption'], 'UTF-8') > 1000) {
                $params['caption'] = mb_substr($params['caption'], 0, 980, 'UTF-8') . '...';
                unset($params['parse_mode']);
                $params['caption'] = strip_tags($params['caption']);
            }
        }

        $url     = "https://api.telegram.org/bot" . $token . "/{$method}";
        $raw     = false;
        $curlErr = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::$timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_POST           => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);

            if ($multipart) {
                // multipart/form-data สำหรับส่งไฟล์
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            } else {
                // application/json
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
            }

            $raw     = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);
        }

        // Fallback ด้วย file_get_contents หาก cURL ล้มเหลว (เช่น ปัญหา SSL/Firewall บน Shared Host)
        if ($raw === false && !$multipart) {
            $formParams = $params;
            if (isset($formParams['reply_markup']) && is_array($formParams['reply_markup'])) {
                $formParams['reply_markup'] = json_encode($formParams['reply_markup'], JSON_UNESCAPED_UNICODE);
            }
            $opts = [
                'http' => [
                    'header'        => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'        => 'POST',
                    'content'       => http_build_query($formParams),
                    'timeout'       => self::$timeout,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ];
            $ctx = stream_context_create($opts);
            $raw = @file_get_contents($url, false, $ctx);
        }

        if ($raw === false) {
            error_log("[Telegram::call] Request error: {$curlErr}");
            return ['ok' => false, 'description' => "cURL error: {$curlErr}"];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'description' => 'Invalid JSON response'];
        }

        // กรณีกลุ่มธรรมดาถูกอัปเกรดเป็น Supergroup (Telegram คืนค่า migrate_to_chat_id) → ส่งซ้ำด้วย ID ใหม่อัตโนมัติ
        if (empty($decoded['ok']) && !empty($decoded['parameters']['migrate_to_chat_id']) && isset($params['chat_id'])) {
            $newChatId = (string)$decoded['parameters']['migrate_to_chat_id'];
            if ($newChatId !== (string)$params['chat_id']) {
                $params['chat_id']       = $newChatId;
                self::$chatId            = $newChatId;
                $retry                   = self::call($method, $params, $multipart);
                $retry['migrated_chat']  = $newChatId;
                return $retry;
            }
        }

        // กรณีข้อความมีอักขระพิเศษที่ทำให้ HTML parse พัง → ลองส่งใหม่แบบข้อความธรรมดาอัตโนมัติ
        if (empty($decoded['ok']) && !empty($params['parse_mode']) && preg_match('/parse|entity|entities|tag/i', (string)($decoded['description'] ?? ''))) {
            unset($params['parse_mode']);
            if (isset($params['text']) && is_string($params['text'])) {
                $params['text'] = html_entity_decode(strip_tags($params['text']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (isset($params['caption']) && is_string($params['caption'])) {
                $params['caption'] = html_entity_decode(strip_tags($params['caption']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            return self::call($method, $params, $multipart);
        }

        return $decoded;
    }

    /**
     * ตรวจสอบและรัน Migration ซ่อมแซมการตั้งค่า Telegram / สิทธิ์ครูที่ปรึกษาอัตโนมัติ (ครั้งเดียว)
     */
    private static bool $migrationChecked = false;
    private static function ensureTelegramMigration(PDO $pdo): void
    {
        if (self::$migrationChecked) {
            return;
        }
        self::$migrationChecked = true;
        try {
            $migNames = [
                '2026_10_29_000001_fix_roles_advisors_and_telegram_settings',
                '2026_10_29_000002_verify_llw20_and_relink_advisors_by_name',
            ];
            $chk = $pdo->prepare("SELECT 1 FROM _migrations WHERE migration = ? LIMIT 1");
            $ins = $pdo->prepare("INSERT IGNORE INTO _migrations (migration, batch) VALUES (?, 99)");
            foreach ($migNames as $migName) {
                $chk->execute([$migName]);
                if (!$chk->fetchColumn()) {
                    $migFile = __DIR__ . '/../database/migrations/' . $migName . '.php';
                    if (file_exists($migFile)) {
                        $migDef = require $migFile;
                        if (is_array($migDef) && isset($migDef['up'])) {
                            $migDef['up']($pdo);
                            $ins->execute([$migName]);
                        }
                    }
                }
            }
        } catch (\Throwable $ignore) {}
    }

    /**
     * ดึงรายการคู่ (token, chat_id, chat_col) ตามลำดับความสำคัญของแต่ละโมดูล พร้อมระบบ Fallback อัตโนมัติ
     *
     * @param  PDO         $pdo
     * @param  string      $module  'attendance' | 'leave' | 'duty' | 'wfh'
     * @param  string|null $customChatId
     * @return array       [['token' => string, 'chat_id' => string, 'col' => string], ...]
     */
    public static function resolveModuleTargets(PDO $pdo, string $module, ?string $customChatId = null): array
    {
        self::ensureTelegramMigration($pdo);

        $settings = [];
        try {
            $settings = $pdo->query("SELECT * FROM wfh_system_settings ORDER BY setting_id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $ignore) {}

        $mainToken  = trim((string)($settings['telegram_token'] ?? ''));
        $attToken   = trim((string)($settings['att_bot_token'] ?? ''));
        $leaveToken = trim((string)($settings['leave_bot_token'] ?? ''));
        $dutyToken  = trim((string)($settings['duty_bot_token'] ?? ''));

        $adminChat  = trim((string)($settings['admin_chat_id'] ?? ''));
        $attChat    = trim((string)($settings['att_chat_id'] ?? ''));
        $leaveChat  = trim((string)($settings['leave_chat_id'] ?? ''));
        $dutyChat   = trim((string)($settings['duty_chat_id'] ?? ''));
        $customChat = trim((string)($customChatId ?? ''));

        // สำหรับระบบครูเวร ตรวจสอบค่าจาก duty_settings เพิ่มเติมด้วย
        $dutyGroupChat = '';
        if ($module === 'duty') {
            try {
                $dsCols = $pdo->query("SHOW COLUMNS FROM `duty_settings`")->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('skey', $dsCols, true) && in_array('svalue', $dsCols, true)) {
                    $dutyGroupChat = trim((string)($pdo->query("SELECT svalue FROM duty_settings WHERE skey IN ('duty_group_chat_id','group_chat_id','chat_id') AND svalue != '' LIMIT 1")->fetchColumn() ?: ''));
                } elseif (in_array('setting_key', $dsCols, true) && in_array('setting_value', $dsCols, true)) {
                    $dutyGroupChat = trim((string)($pdo->query("SELECT setting_value FROM duty_settings WHERE setting_key IN ('duty_group_chat_id','group_chat_id','chat_id') AND setting_value != '' LIMIT 1")->fetchColumn() ?: ''));
                }
            } catch (\Throwable $ignore) {}
        }

        switch ($module) {
            case 'attendance':
                $tokens = [$attToken, $mainToken, $leaveToken, $dutyToken];
                $chats  = [
                    ['id' => $customChat, 'col' => 'custom'],
                    ['id' => $attChat,    'col' => 'att_chat_id'],
                    ['id' => $adminChat,  'col' => 'admin_chat_id'],
                ];
                break;
            case 'leave':
                $tokens = [$leaveToken, $mainToken, $attToken, $dutyToken];
                $chats  = [
                    ['id' => $customChat, 'col' => 'custom'],
                    ['id' => $leaveChat,  'col' => 'leave_chat_id'],
                    ['id' => $adminChat,  'col' => 'admin_chat_id'],
                    ['id' => $attChat,    'col' => 'att_chat_id'],
                ];
                break;
            case 'duty':
                $tokens = [$dutyToken, $mainToken, $attToken, $leaveToken];
                $chats  = [
                    ['id' => $customChat,    'col' => 'custom'],
                    ['id' => $dutyChat,      'col' => 'duty_chat_id'],
                    ['id' => $dutyGroupChat, 'col' => 'duty_group_chat_id'],
                    ['id' => $adminChat,     'col' => 'admin_chat_id'],
                    ['id' => $attChat,       'col' => 'att_chat_id'],
                ];
                break;
            case 'wfh':
            default:
                $tokens = [$mainToken, $leaveToken, $attToken, $dutyToken];
                $chats  = [
                    ['id' => $customChat, 'col' => 'custom'],
                    ['id' => $adminChat,  'col' => 'admin_chat_id'],
                    ['id' => $leaveChat,  'col' => 'leave_chat_id'],
                    ['id' => $attChat,    'col' => 'att_chat_id'],
                ];
                break;
        }

        $validTokens = array_values(array_unique(array_filter($tokens, fn($t) => $t !== '')));
        $validChats  = [];
        $seenChatIds = [];
        foreach ($chats as $c) {
            if ($c['id'] !== '' && !isset($seenChatIds[$c['id']])) {
                $seenChatIds[$c['id']] = true;
                $validChats[] = $c;
            }
        }

        // สร้างลำดับการลองส่ง: ลอง Chat หลักกับทุก Token ที่เกี่ยวข้องก่อน แล้วจึงขยับไป Chat สำรอง
        $targets = [];
        foreach ($validChats as $c) {
            foreach ($validTokens as $tk) {
                $targets[] = [
                    'token'   => $tk,
                    'chat_id' => $c['id'],
                    'col'     => $c['col'],
                ];
            }
        }
        return $targets;
    }

    /**
     * บันทึก Chat ID ใหม่ลงฐานข้อมูลอัตโนมัติเมื่อกลุ่ม Telegram ถูกอัปเกรดเป็น Supergroup
     */
    private static function persistMigratedChat(PDO $pdo, string $col, string $newChatId): void
    {
        if ($newChatId === '' || !in_array($col, ['att_chat_id', 'leave_chat_id', 'duty_chat_id', 'admin_chat_id', 'duty_group_chat_id'], true)) {
            return;
        }
        try {
            if ($col === 'duty_group_chat_id') {
                $dsCols = $pdo->query("SHOW COLUMNS FROM `duty_settings`")->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('skey', $dsCols, true)) {
                    $pdo->prepare("UPDATE duty_settings SET svalue = ? WHERE skey IN ('duty_group_chat_id','group_chat_id','chat_id')")->execute([$newChatId]);
                } elseif (in_array('setting_key', $dsCols, true)) {
                    $pdo->prepare("UPDATE duty_settings SET setting_value = ? WHERE setting_key IN ('duty_group_chat_id','group_chat_id','chat_id')")->execute([$newChatId]);
                }
                $pdo->prepare("UPDATE wfh_system_settings SET duty_chat_id = ?")->execute([$newChatId]);
            } else {
                $pdo->prepare("UPDATE wfh_system_settings SET `{$col}` = ?")->execute([$newChatId]);
            }
        } catch (\Throwable $ignore) {}
    }

    /**
     * ฟังก์ชันกลางสำหรับส่งข้อความแจ้งเตือนแยกตามโมดูล พร้อมระบบ Multi-Token & Multi-Chat Fallback
     */
    public static function sendModuleNotify(PDO $pdo, string $module, string $text, ?string $customChatId = null): array
    {
        try {
            $targets = self::resolveModuleTargets($pdo, $module, $customChatId);
            if (empty($targets)) {
                return ['ok' => false, 'description' => "ยังไม่ได้ตั้งค่า Telegram Token หรือ Chat ID สำหรับโมดูล {$module}"];
            }

            $lastRes = ['ok' => false, 'description' => 'Unknown error'];
            foreach ($targets as $t) {
                self::init($t['token'], $t['chat_id']);
                $res = self::sendMessage($text, $t['chat_id']);
                if (!empty($res['ok'])) {
                    if (!empty($res['migrated_chat'])) {
                        self::persistMigratedChat($pdo, $t['col'], (string)$res['migrated_chat']);
                    }
                    return $res;
                }
                $lastRes = $res;
            }

            error_log("[Telegram::sendModuleNotify:{$module}] Failed on all targets. Last error: " . ($lastRes['description'] ?? 'unknown'));
            return $lastRes;
        } catch (\Throwable $e) {
            error_log("[Telegram::sendModuleNotify:{$module}] Exception: " . $e->getMessage());
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    /**
     * ส่งแจ้งเตือนสำหรับระบบเช็คชื่อ/นักเรียน (เข้าแถว, เครื่องแต่งกาย, รายคาบ, โฮมรูม, พฤติกรรม, ใบลานักเรียน)
     */
    public static function sendAttendanceNotify(PDO $pdo, string $text, ?string $customChatId = null): array
    {
        return self::sendModuleNotify($pdo, 'attendance', $text, $customChatId);
    }

    /**
     * ส่งแจ้งเตือนสำหรับระบบการลา / ขออนุญาตออกนอกสถานที่ของครูและบุคลากร
     */
    public static function sendLeaveNotify(PDO $pdo, string $text, ?string $customChatId = null): array
    {
        return self::sendModuleNotify($pdo, 'leave', $text, $customChatId);
    }

    /**
     * ส่งแจ้งเตือนสำหรับระบบครูเวร (Duty)
     */
    public static function sendDutyNotify(PDO $pdo, string $text, ?string $customChatId = null): array
    {
        return self::sendModuleNotify($pdo, 'duty', $text, $customChatId);
    }

    /**
     * ส่งรูปภาพสำหรับระบบครูเวร (รองรับทั้งรูปเดี่ยวและอัลบั้ม พร้อม Fallback อัตโนมัติ)
     */
    public static function sendDutyPhotos(PDO $pdo, array $photoPaths, string $caption = '', ?string $customChatId = null): array
    {
        $validFiles = array_values(array_filter($photoPaths, fn($p) => is_string($p) && $p !== '' && file_exists($p)));
        if (empty($validFiles)) {
            return self::sendDutyNotify($pdo, $caption, $customChatId);
        }

        try {
            $targets = self::resolveModuleTargets($pdo, 'duty', $customChatId);
            if (empty($targets)) {
                return ['ok' => false, 'description' => 'ยังไม่ได้ตั้งค่า Telegram สำหรับระบบครูเวร'];
            }

            $lastRes = ['ok' => false, 'description' => 'Unknown error'];
            foreach ($targets as $t) {
                self::init($t['token'], $t['chat_id']);
                if (count($validFiles) === 1) {
                    $res = self::sendPhoto($validFiles[0], $caption, $t['chat_id']);
                } else {
                    $res = self::sendMediaGroup($validFiles, $caption, $t['chat_id']);
                }
                if (!empty($res['ok'])) {
                    if (!empty($res['migrated_chat'])) {
                        self::persistMigratedChat($pdo, $t['col'], (string)$res['migrated_chat']);
                    }
                    return $res;
                }
                $lastRes = $res;
            }
            return $lastRes;
        } catch (\Throwable $e) {
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    /**
     * ส่งแจ้งเตือนสำหรับระบบลงเวลาปฏิบัติงาน (WFH) และงานสารบรรณ (e-Document)
     */
    public static function sendWfhNotify(PDO $pdo, string $text, ?string $customChatId = null): array
    {
        return self::sendModuleNotify($pdo, 'wfh', $text, $customChatId);
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
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
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
    public static function setWebhook(
        string $url,
        string $secretToken    = '',
        array  $allowedUpdates = ['message', 'callback_query']
    ): array {
        $params = [
            'url'                  => $url,
            'allowed_updates'      => $allowedUpdates,
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
    public static function createThumbnail(string $sourcePath, string $destPath, int $maxSize = 400): bool
    {
        if (!extension_loaded('gd')) {
            return copy($sourcePath, $destPath);
        }

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

        if ($origW <= $maxSize && $origH <= $maxSize) {
            imagedestroy($src);
            return copy($sourcePath, $destPath);
        }

        $ratio = ($origW > $origH) ? ($maxSize / $origW) : ($maxSize / $origH);
        $newW  = (int)round($origW * $ratio);
        $newH  = (int)round($origH * $ratio);

        $thumb = imagecreatetruecolor($newW, $newH);

        if ($mime === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

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
