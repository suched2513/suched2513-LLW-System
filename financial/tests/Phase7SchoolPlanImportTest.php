<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Helpers/functions.php';

echo "=== เริ่มต้นการทดสอบ Phase 7: การนำเข้าแผนปฏิบัติการและงบประมาณปี 2570 ===" . PHP_EOL;

$passCount = 0;
$failCount = 0;

function assertPlanTest(bool $condition, string $message): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$message}" . PHP_EOL;
        $passCount++;
    } else {
        echo "  [FAIL] {$message}" . PHP_EOL;
        $failCount++;
    }
}

// 1. ตรวจสอบไฟล์ CSV ของทั้ง 4 ฝ่าย
$files = [
    'วิชาการ' => __DIR__ . '/../storage/sheets/2_academic_397508199.csv',
    'ทั่วไป'  => __DIR__ . '/../storage/sheets/3_general_1711717387.csv',
    'บุคคล'  => __DIR__ . '/../storage/sheets/4_personnel_914416083.csv',
    'แผนงาน' => __DIR__ . '/../storage/sheets/5_budget_plan_1078076210.csv'
];

foreach ($files as $name => $f) {
    assertPlanTest(file_exists($f), "พบไฟล์ข้อมูลแผนงาน {$name} ({$f})");
}

// 2. ตรวจสอบการ Parse และยอดงบประมาณ
$cleanNum = function($v): float {
    if (!$v) return 0.0;
    $v = preg_replace('/[^\d\.\-]/', '', (string)$v);
    return is_numeric($v) ? (float)$v : 0.0;
};

$totalActivities = 0;
$totalSubsidy = 0.0;
$totalDevelop = 0.0;
$totalRevenue = 0.0;

foreach ($files as $k => $filepath) {
    $fh = fopen($filepath, 'r');
    $rowIdx = 0;
    while (($row = fgetcsv($fh)) !== false) {
        $rowIdx++;
        if ($rowIdx <= 4) continue;
        $pName = trim($row[0] ?? '');
        $aName = trim($row[1] ?? '');
        $reqBudget = trim($row[2] ?? '');
        $appSubsidy = trim($row[3] ?? '');
        $appDevelop = trim($row[4] ?? '');
        $appRevenue = ($k === 'ทั่วไป') ? trim($row[5] ?? '') : '';

        if (str_starts_with($pName, 'รวม') || str_starts_with($aName, 'รวม') || $pName === 'ย' || $aName === 'ย') continue;
        if (empty($pName) && empty($aName) && empty($reqBudget) && empty($appSubsidy)) continue;

        if (!empty($aName) && $aName !== 'กิจกรรม') {
            $totalActivities++;
            $totalSubsidy += $cleanNum($appSubsidy);
            $totalDevelop += $cleanNum($appDevelop);
            $totalRevenue += $cleanNum($appRevenue);
        }
    }
    fclose($fh);
}

assertPlanTest($totalActivities === 148, "จำนวนกิจกรรมรวมทั้ง 4 ฝ่ายเท่ากับ 148 กิจกรรม (พบ {$totalActivities})");
assertPlanTest(abs($totalSubsidy - 1742700.00) < 0.01, "ยอดเงินอุดหนุนที่อนุมัติ (SUB-01) เท่ากับ 1,742,700.00 บาท (พบ " . number_format($totalSubsidy, 2) . ")");
assertPlanTest(abs($totalDevelop - 881480.00) < 0.01, "ยอดงบพัฒนาคุณภาพผู้เรียน (SUB-02) เท่ากับ 881,480.00 บาท (พบ " . number_format($totalDevelop, 2) . ")");
assertPlanTest(abs($totalRevenue - 676000.00) < 0.01, "ยอดเงินรายได้สถานศึกษา (REV-01) เท่ากับ 676,000.00 บาท (พบ " . number_format($totalRevenue, 2) . ")");
assertPlanTest(abs(($totalSubsidy + $totalDevelop + $totalRevenue) - 3300180.00) < 0.01, "ยอดรวมงบประมาณที่อนุมัติทั้งสิ้น 3,300,180.00 บาท ตรงกับเอกสารงบหน้า 100%");

// 3. ตรวจสอบ Script และ Route การนำเข้า
assertPlanTest(file_exists(__DIR__ . '/../database/import_school_plan_2570.php'), "มีสคริปต์นำเข้า import_school_plan_2570.php");
$indexContent = file_get_contents(__DIR__ . '/../public/index.php');
assertPlanTest(str_contains($indexContent, '/budgets/import-plan'), "มีการลงทะเบียน Route /budgets/import-plan ใน Front Controller");

echo PHP_EOL . "สรุปผลการทดสอบ Phase 7: ผ่าน {$passCount} รายการ, ล้มเหลว {$failCount} รายการ" . PHP_EOL;

if ($failCount > 0) {
    exit(1);
}
