<?php
/**
 * duty/install_webhook.php — ติดตั้ง Telegram Webhook (รันครั้งเดียว)
 *
 * วิธีใช้:
 *   php duty/install_webhook.php
 * หรือเปิดผ่าน browser (ต้อง login ก่อน):
 *   https://llw.krusuched.com/duty/install_webhook.php
 *
 * ลบไฟล์นี้ออกหลังใช้งานแล้วเพื่อความปลอดภัย
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/telegram_bot.php';

$isCli = php_sapi_name() === 'cli';

// ── Auth (web only) ──
if (!$isCli) {
    if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
        http_response_code(403);
        die('Super admin only. หรือใช้ CLI: php duty/install_webhook.php');
    }
}

$pdo = getPdo();

// ── อ่าน settings ──
$stmtS = $pdo->query("SELECT telegram_token, admin_chat_id FROM wfh_system_settings LIMIT 1");
$sys = $stmtS->fetch(PDO::FETCH_ASSOC);
$botToken = $sys['telegram_token'] ?? '';

if (empty($botToken)) {
    die("❌ ยังไม่ได้ตั้งค่า telegram_token ใน wfh_system_settings\n");
}

Telegram::init($botToken);

// ── สร้าง secret token ใหม่ (ถ้ายังไม่มี) ──
$stmtSec = $pdo->prepare("SELECT svalue FROM duty_settings WHERE skey = 'telegram_webhook_secret'");
$stmtSec->execute();
$existingSecret = (string)($stmtSec->fetchColumn() ?? '');

if (empty($existingSecret)) {
    $newSecret = bin2hex(random_bytes(32)); // 64 chars
    $pdo->prepare("UPDATE duty_settings SET svalue=? WHERE skey='telegram_webhook_secret'")
        ->execute([$newSecret]);
    $webhookSecret = $newSecret;
    echo "✅ สร้าง webhook secret ใหม่\n";
} else {
    $webhookSecret = $existingSecret;
    echo "ℹ️  ใช้ webhook secret เดิม\n";
}

// ── กำหนด Webhook URL ──
if ($isCli) {
    // CLI: ให้ผู้ใช้กรอก site URL
    echo "กรอก Site URL (เช่น https://llw.krusuched.com): ";
    $siteUrl = rtrim(trim(fgets(STDIN)), '/');
} else {
    // Web: ตรวจ URL อัตโนมัติ
    $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $siteUrl = $proto . '://' . $host;
}

$webhookUrl = $siteUrl . '/api/telegram_webhook.php';

echo "📡 กำลังตั้งค่า Webhook: {$webhookUrl}\n";

// ── ตรวจสอบ Webhook ปัจจุบัน ──
$currentInfo = Telegram::getWebhookInfo();
if (!empty($currentInfo['result']['url'])) {
    echo "ℹ️  Webhook ปัจจุบัน: " . $currentInfo['result']['url'] . "\n";
}

// ── ติดตั้ง Webhook ──
$res = Telegram::setWebhook($webhookUrl, $webhookSecret, ['message', 'callback_query']);

if ($res['ok'] ?? false) {
    echo "✅ ติดตั้ง Webhook สำเร็จ!\n";
    echo "   URL: {$webhookUrl}\n";
    echo "   Secret: " . substr($webhookSecret, 0, 8) . "...\n\n";

    // อัปเดต bot username ถ้าทำได้
    $meRes = Telegram::call_public('getMe', []);
    if (!empty($meRes['result']['username'])) {
        $botUser = $meRes['result']['username'];
        $pdo->prepare("UPDATE duty_settings SET svalue=? WHERE skey='duty_bot_username'")
            ->execute([$botUser]);
        echo "✅ บันทึก Bot Username: @{$botUser}\n";
    }

    echo "\n⚠️  กรุณาลบไฟล์นี้ทิ้งหลังใช้งาน:\n   rm duty/install_webhook.php\n";
} else {
    echo "❌ ติดตั้ง Webhook ล้มเหลว:\n";
    echo "   " . ($res['description'] ?? json_encode($res)) . "\n";
    echo "\nตรวจสอบ:\n";
    echo "  1. URL ต้องเป็น HTTPS\n";
    echo "  2. Bot token ถูกต้อง\n";
    echo "  3. Server เข้าถึงได้จากอินเทอร์เน็ต\n";
}

// ── Web output ──
if (!$isCli): ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Install Webhook</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:600px">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">🤖 ติดตั้ง Telegram Webhook</h5>
        </div>
        <div class="card-body">
            <?php if ($res['ok'] ?? false): ?>
            <div class="alert alert-success">
                <strong>✅ สำเร็จ!</strong><br>
                Webhook URL: <code><?= htmlspecialchars($webhookUrl) ?></code>
            </div>
            <div class="alert alert-warning">
                <strong>⚠️ กรุณาลบไฟล์นี้ทิ้ง</strong> หลังจากใช้งานแล้ว<br>
                <code>rm duty/install_webhook.php</code>
            </div>
            <?php else: ?>
            <div class="alert alert-danger">
                <strong>❌ ล้มเหลว:</strong><br>
                <?= htmlspecialchars($res['description'] ?? 'Unknown error') ?>
            </div>
            <?php endif; ?>
            <a href="/duty/admin/reports.php" class="btn btn-primary">ไปหน้า Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>
<?php
endif;
