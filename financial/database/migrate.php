<?php
/**
 * database/migrate.php
 * ตัวรันการสร้างตารางฐานข้อมูลระบบบริหารการเงินและบัญชีสถานศึกษา
 * สามารถรันผ่าน CLI: php database/migrate.php หรือเรียกผ่านเว็บตอนติดตั้ง
 */

require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

try {
    echo "--- เริ่มต้นการอัปเกรดฐานข้อมูล (Running Migrations) ---" . PHP_EOL;
    $pdo = Database::getConnection();

    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        die("ไม่พบไฟล์ schema.sql ที่ $schemaFile" . PHP_EOL);
    }

    $sql = file_get_contents($schemaFile);

    // แยกและรันคำสั่ง SQL ทีละคำสั่ง
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $count = 0;
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
            $count++;
        }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "✅ ดำเนินการสร้างตารางเรียบร้อยแล้ว ($count คำสั่ง)" . PHP_EOL;

    // รัน seeder ต่ออัตโนมัติ
    require_once __DIR__ . '/seed.php';

} catch (Throwable $e) {
    echo "❌ เกิดข้อผิดพลาดในการสร้างฐานข้อมูล: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
