<?php
session_start();
require_once __DIR__ . '/config/database.php';

// ป้องกัน — เฉพาะ super_admin เท่านั้น
if (($_SESSION['llw_role'] ?? '') !== 'super_admin') {
    http_response_code(403); die('Forbidden');
}

$pdo = getPdo();
$file = __DIR__ . '/database/migrations/2026_05_11_000003_fix_student_duplicates_and_test_data.php';

echo '<pre style="font-family:monospace;font-size:14px;padding:20px">';
echo "Running: 2026_05_11_000003_fix_student_duplicates_and_test_data\n\n";

try {
    $migration = require $file;
    $migration['up']($pdo);
    echo "\n✅ Migration สำเร็จ\n";
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}

echo '</pre>';
echo '<p style="color:red;font-weight:bold">⚠️ ลบไฟล์นี้ออกทันทีหลังใช้งาน</p>';
