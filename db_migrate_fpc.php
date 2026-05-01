<?php
/**
 * One-time migration runner: add force_password_change to llw_users
 * Self-deletes after successful run.
 * Access: /db_migrate_fpc.php?token=llwMigrate2026
 */
if (($_GET['token'] ?? '') !== 'llwMigrate2026') {
    http_response_code(403); die('Forbidden');
}

require_once __DIR__ . '/config/database.php';
$pdo = getPdo();

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family:monospace;font-size:14px;padding:20px'>";
echo "=== LLW Migration: add force_password_change ===\n\n";

try {
    $cols = $pdo->query("SHOW COLUMNS FROM llw_users LIKE 'force_password_change'")->fetchAll();

    if (!empty($cols)) {
        echo "✅ Column 'force_password_change' มีอยู่แล้ว — ไม่ต้องทำอะไร\n";
    } else {
        $pdo->exec("ALTER TABLE llw_users ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        echo "✅ เพิ่ม column 'force_password_change' สำเร็จ!\n";
    }

    $count = $pdo->query("SELECT COUNT(*) FROM llw_users")->fetchColumn();
    echo "   ผู้ใช้ทั้งหมด: {$count} คน\n\n";
    echo "✅ Migration เสร็จสมบูรณ์!\n";
    echo "   กลับไปที่ manage_users.php แล้วกดปุ่ม 'Reset รหัสทั้งหมด' ได้เลยครับ\n\n";
    echo "<a href='/manage_users.php' style='color:#3b82f6;font-weight:bold'>→ ไปที่ manage_users.php</a>\n";

    // Self-delete after success
    unlink(__FILE__);
    echo "\n(ไฟล์นี้ถูกลบออกอัตโนมัติแล้ว)\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
