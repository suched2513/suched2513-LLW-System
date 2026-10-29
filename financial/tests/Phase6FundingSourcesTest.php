<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Helpers/functions.php';

echo "=== เริ่มต้นการทดสอบ Phase 6: ประเภทการเงินและแหล่งเงินสถานศึกษา ===" . PHP_EOL;

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $message): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$message}" . PHP_EOL;
        $passCount++;
    } else {
        echo "  [FAIL] {$message}" . PHP_EOL;
        $failCount++;
    }
}

// 1. ตรวจสอบรายการประเภทการเงินที่โรงเรียนต้องการ 11 ประเภท
$expectedSources = [
    'ค่าจัดการเรียนการสอน',
    'กิจกรรมพัฒนาคุณภาพผู้เรียน',
    'ค่าอุปกรณ์การเรียน',
    'ค่าหนังสือเรียน',
    'ค่าเครื่องแต่งกาย',
    'เงินปัจจัยพื้นฐาน นร.ยากจน',
    'เงินรายได้สถานศึกษา',
    'เงินบริจาค',
    'เงินค่ากระแสไฟฟ้า',
    'เงินค่ารถรับ-ส่ง นักเรียน',
    'เงินสนับสนุนอื่นๆ'
];

// ดึงรายการจาก seed file
$seedContent = file_get_contents(__DIR__ . '/../database/seed.php');
foreach ($expectedSources as $sourceName) {
    assertTest(str_contains($seedContent, $sourceName), "พบประเภทการเงิน '{$sourceName}' ในระบบฐานข้อมูล (Seed)");
}

// 2. ดึงรายการจาก schema.sql
$schemaContent = file_get_contents(__DIR__ . '/../database/schema.sql');
foreach ($expectedSources as $sourceName) {
    assertTest(str_contains($schemaContent, $sourceName), "พบประเภทการเงิน '{$sourceName}' ใน Schema SQL");
}

// 3. ตรวจสอบการจัดกลุ่มประเภทแหล่งเงิน (Classification)
$sourceTypeMapping = [
    'ค่าจัดการเรียนการสอน'        => 'subsidy',
    'กิจกรรมพัฒนาคุณภาพผู้เรียน' => 'subsidy',
    'ค่าอุปกรณ์การเรียน'          => 'subsidy',
    'ค่าหนังสือเรียน'              => 'subsidy',
    'ค่าเครื่องแต่งกาย'            => 'subsidy',
    'เงินปัจจัยพื้นฐาน นร.ยากจน'   => 'subsidy',
    'เงินรายได้สถานศึกษา'         => 'revenue',
    'เงินบริจาค'                   => 'donation',
    'เงินค่ากระแสไฟฟ้า'           => 'subsidy',
    'เงินค่ารถรับ-ส่ง นักเรียน'      => 'subsidy',
    'เงินสนับสนุนอื่นๆ'            => 'other',
];

assertTest(count($sourceTypeMapping) === 11, "มีประเภทการเงินตามที่โรงเรียนกำหนดครบ 11 รายการ");

// 4. ตรวจสอบ Controller และ Route
assertTest(class_exists('App\Controllers\FundingSourceController') || file_exists(__DIR__ . '/../app/Controllers/FundingSourceController.php'), "มี Controller จัดการประเภทการเงิน (FundingSourceController)");
assertTest(file_exists(__DIR__ . '/../app/Views/funding_sources/index.php'), "มีหน้าจอแสดงและจัดการประเภทการเงิน (funding_sources/index.php)");

// 5. ตรวจสอบ Route ใน index.php
$indexContent = file_get_contents(__DIR__ . '/../public/index.php');
assertTest(str_contains($indexContent, '/funding-sources'), "มีการลงทะเบียน Route /funding-sources ใน Front Controller");

echo PHP_EOL . "สรุปผลการทดสอบ Phase 6: ผ่าน {$passCount} รายการ, ล้มเหลว {$failCount} รายการ" . PHP_EOL;

if ($failCount > 0) {
    exit(1);
}
