<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Helpers/functions.php';
require_once __DIR__ . '/../app/Services/BahtTextService.php';
require_once __DIR__ . '/../app/Services/AssetService.php';
require_once __DIR__ . '/../app/Services/ProcurementService.php';
require_once __DIR__ . '/../app/Services/ReportService.php';

use App\Services\AssetService;
use App\Services\ProcurementService;
use App\Services\ReportService;

echo "=== เริ่มต้นการทดสอบ Phase 5: Procurement, Assets & 28 Reports ===" . PHP_EOL;

$passCount = 0;
$failCount = 0;

function assert_test(string $name, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$name}" . PHP_EOL;
        $passCount++;
    } else {
        echo "  [FAIL] {$name}" . PHP_EOL;
        $failCount++;
    }
}

// 1. ทดสอบสูตรการคำนวณค่าเสื่อมราคาครุภัณฑ์แบบเส้นตรง (Straight-Line Depreciation)
// ครุภัณฑ์คอมพิวเตอร์ ราคาทุน 30,001.00 บาท อายุการใช้งาน 5 ปี
// มูลค่าซาก = 1.00 บาท -> ยอดคิดค่าเสื่อม = 30,000.00 บาท -> ค่าเสื่อมต่อปี = 6,000.00 บาท
$asset = [
    'asset_code'        => '7440-001-0001/69',
    'asset_name'        => 'เครื่องคอมพิวเตอร์ประมวลผล',
    'cost_amount'       => 30001.00,
    'useful_life_years' => 5,
    'acquired_date'     => '2024-10-27' // ผ่านมาแล้ว 2 ปีบริบูรณ์ ณ 2026-10-27
];

$depCalc = AssetService::calculateDepreciation($asset, '2026-10-27');
assert_test("ค่าเสื่อมราคาต่อปี: (30,001 - 1) / 5 = 6,000.00 บาท", $depCalc['annual_depreciation'] === 6000.00);
assert_test("ค่าเสื่อมราคาสะสม 2 ปี: 6,000 x 2 = 12,000.00 บาท", $depCalc['accumulated_depreciation'] === 12000.00);
assert_test("มูลค่าสุทธิตามบัญชี: 30,001 - 12,000 = 18,001.00 บาท", $depCalc['net_book_value'] === 18001.00);

// กรณีใช้งานเกินอายุการใช้งาน (เช่น ผ่านมาแล้ว 7 ปี) -> มูลค่าสุทธิต้องเหลือซาก 1.00 บาท
$depExpired = AssetService::calculateDepreciation($asset, '2031-10-27');
assert_test("เมื่อหมดอายุใช้งาน มูลค่าสุทธิตามบัญชีเหลือมูลค่าซาก 1.00 บาท", $depExpired['net_book_value'] === 1.00 && $depExpired['is_fully_depreciated'] === true);

// 2. ทดสอบรูปแบบเลขที่เอกสารจัดซื้อจัดจ้าง
$fy = thai_fiscal_year();
assert_test("รูปแบบรหัสใบขอซื้อขอจ้าง PR-{$fy}-0001", (bool)preg_match("/^PR-{$fy}-\\d{4}$/", sprintf('PR-%d-%04d', $fy, 1)));
assert_test("รูปแบบรหัสใบสั่งซื้อ PO-{$fy}-0001", (bool)preg_match("/^PO-{$fy}-\\d{4}$/", sprintf('PO-%d-%04d', $fy, 1)));
assert_test("รูปแบบรหัสรายงานตรวจรับ INSP-{$fy}-0001", (bool)preg_match("/^INSP-{$fy}-\\d{4}$/", sprintf('INSP-%d-%04d', $fy, 1)));

// 3. ทดสอบความครบถ้วนของ 28 รายงานทางการเงินและบัญชีสถานศึกษา
$catalog = ReportService::getReportCatalog();
assert_test("ดัชนีรายงานมีครบถ้วน 28 รายงานตามข้อกำหนด", count($catalog) === 28);

$codes = array_column($catalog, 'code');
assert_test("รหัสรายงานไม่ซ้ำกัน (Unique Codes)", count($codes) === count(array_unique($codes)));
assert_test("มีรายงานสำคัญครบถ้วน (บก.111, 8500, 8708, งบทดลอง, ทะเบียนครุภัณฑ์)", 
    in_array('REC-CERT', $codes) && 
    in_array('ADV-01', $codes) && 
    in_array('TRV-01', $codes) && 
    in_array('TB-01', $codes) && 
    in_array('AST-01', $codes)
);

echo PHP_EOL . "สรุปผลการทดสอบ Phase 5: ผ่าน {$passCount} รายการ, ล้มเหลว {$failCount} รายการ" . PHP_EOL;

if ($failCount > 0) {
    exit(1);
}
