<?php
class TelegramBot {
    private $token;
    private $chat_id;

    public function __construct($token, $chat_id) {
        $this->token = $token;
        $this->chat_id = $chat_id;
    }

    public function sendMessage($message) {
        if (empty($this->token) || empty($this->chat_id)) return false;

        $url = "https://api.telegram.org/bot" . $this->token . "/sendMessage";
        $data = [
            'chat_id' => $this->chat_id,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ],
        ];

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }
}
?>
