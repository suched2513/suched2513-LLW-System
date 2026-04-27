<?php
/**
 * api/notify_send.php — Automated Notifications (Cron Job)
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdo = getPdo();
$today = date('Y-m-d');

try {
    // 1. Check for Overdue Projects (> 30 days in draft or no request)
    $stmt = $pdo->query("
        SELECT bp.*, u.username as email, u.full_name, d.name as dept_name
        FROM budget_projects bp
        JOIN departments d ON bp.department_id = d.id
        JOIN users u ON bp.owner_name = u.full_name
        LEFT JOIN project_requests pr ON pr.budget_project_id = bp.id
        WHERE bp.is_active = 1 
        AND (pr.id IS NULL OR (pr.status = 'draft' AND DATEDIFF(NOW(), pr.created_at) > 30))
    ");
    $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($overdue as $item) {
        // Log Notification in DB
        $log = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, 'project_overdue', ?, ?, ?, 'budget_project')");
        $log->execute([
            $item['id'], // Assuming owner has an ID we can find, else need a better join
            'โครงการค้างดำเนินการ!',
            "โครงการ {$item['project_name']} ของท่านยังไม่มีการดำเนินการเกิน 30 วัน กรุณาตรวจสอบ",
            $item['id']
        ]);
        
        // Send Email (Mocked call)
        // sendEmail($item['email'], 'เตือน: โครงการค้างดำเนินการ', "เรียนคุณ {$item['full_name']}...");
    }

    // 2. Check for Budget Warnings (> 90%)
    $stmt = $pdo->query("SELECT * FROM v_budget_usage WHERE (used_total / alloc_total) > 0.9");
    $warnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($warnings as $w) {
        $msg = "⚠️ [SBMS] งบประมาณฝ่าย {$w['department_name']} ใช้ไปแล้ว " . number_format(($w['used_total']/$w['alloc_total'])*100, 1) . "% กรุณาตรวจสอบการเบิกจ่าย";
        sendLineNotify($msg);
    }

    echo "Notifications processed successfully at " . date('Y-m-d H:i:s');

} catch (Exception $e) {
    error_log("Notification Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}

/**
 * LINE Notify Helper
 */
function sendLineNotify($message) {
    $token = "YOUR_LINE_NOTIFY_TOKEN"; // Should be moved to config
    if ($token === "YOUR_LINE_NOTIFY_TOKEN") return;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "message=" . $message);
    $headers = array('Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer ' . $token . '',);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
