<?php
/**
 * includes/telegram_bot.php
 * ฟังก์ชันกลางสำหรับส่งข้อความ Telegram
 */

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
?>
