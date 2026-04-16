<?php
/**
 * Migration: update_telegram_settings
 * อัปเดต Token และ Chat ID สำหรับการแจ้งเตือน Telegram ตามที่ผู้ใช้ระบุ
 */
return [
    'up' => function (PDO $pdo) {
        $token = '7884146306:AAE6JUc18cNovfrVTGh0myTaRG0eJrYIVxE';
        $chatId = '-4643875655';

        // ตรวจสอบว่ามีแถวในตารางหรือไม่
        $stmt = $pdo->query("SELECT COUNT(*) FROM wfh_system_settings");
        if ($stmt->fetchColumn() == 0) {
            // ถ้าไม่มี ให้ insert แถวเริ่มต้น
            $pdo->exec("
                INSERT INTO wfh_system_settings 
                (regular_time_in, late_time, geofence_radius, telegram_token, admin_chat_id) 
                VALUES ('08:00', '08:15', 200, '$token', '$chatId')
            ");
        } else {
            // ถ้ามี ให้ update
            $stmt = $pdo->prepare("UPDATE wfh_system_settings SET telegram_token = ?, admin_chat_id = ?");
            $stmt->execute([$token, $chatId]);
        }
    },
    'down' => function (PDO $pdo) {
        // ในกรณี rollback เราไม่ได้ลบ token แต่อาจจะปล่อยไว้หรือกลับไปใช้ค่าว่าง
    },
];
