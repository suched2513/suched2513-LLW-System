<?php

declare(strict_types=1);

/**
 * Test Suite Runner for School Financial & Accounting System
 * รันการทดสอบระบบทุกขั้นตอน (Phases 1-9) แบบครบวงจร
 */

$testFiles = [
    'BahtTextTest.php'                  => 'Phase 1: BahtText & Thai Localization',
    'Phase2CoreTest.php'                => 'Phase 2: MVC Core, Router, RBAC & Security',
    'Phase3FinanceTest.php'             => 'Phase 3: Budget, Receipts, Payments & Double-Entry Accounting',
    'Phase4BankAndProgramsTest.php'     => 'Phase 4: Bank Accounts, Reconciliation & Special Programs',
    'Phase5ProcurementAndAssetTest.php' => 'Phase 5: Procurement, Inventory/Assets & 28 Reports',
    'Phase6FundingSourcesTest.php'      => 'Phase 6: Funding Sources & Financial Types Management',
    'Phase7SchoolPlanImportTest.php'    => 'Phase 7: School Action Plan & Budget 2570 Import',
    'Phase8Phong11Test.php'             => 'Phase 8: แบบ ผง 1/1 Budget Approval Document Generator',
    'Phase9Phong12AndPersistenceTest.php'=> 'Phase 9: แบบ ผง 1/2 Report Document & Persistence Registry',
];

echo "=====================================================================" . PHP_EOL;
echo "  โรงเรียนละลมวิทยา - ระบบบริหารการเงินและบัญชีสถานศึกษา" . PHP_EOL;
echo "  COMPREHENSIVE TEST SUITE EXECUTION" . PHP_EOL;
echo "=====================================================================" . PHP_EOL . PHP_EOL;

$allPassed = true;
$totalSuites = count($testFiles);
$passedSuites = 0;

foreach ($testFiles as $file => $description) {
    echo ">>> Running Suite: {$description} ({$file})" . PHP_EOL;
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . $file;
    
    $output = [];
    $returnCode = 0;
    exec("C:\\xampp\\php\\php.exe \"{$filePath}\"", $output, $returnCode);

    foreach ($output as $line) {
        echo "    " . $line . PHP_EOL;
    }
    echo PHP_EOL;

    if ($returnCode === 0) {
        $passedSuites++;
    } else {
        $allPassed = false;
    }
}

echo "=====================================================================" . PHP_EOL;
echo "สรุปผลการทดสอบทั้งหมด:" . PHP_EOL;
echo "  ชุดการทดสอบทั้งหมด: {$totalSuites}" . PHP_EOL;
echo "  ผ่านสมบูรณ์: {$passedSuites}" . PHP_EOL;
echo "  ล้มเหลว: " . ($totalSuites - $passedSuites) . PHP_EOL;
echo "=====================================================================" . PHP_EOL;

if ($allPassed) {
    echo "ผลลัพธ์: ทุกชุดการทดสอบผ่าน 100% (ALL SUITES PASSED - READY FOR PRODUCTION)" . PHP_EOL;
    exit(0);
} else {
    echo "ผลลัพธ์: มีข้อผิดพลาดในชุดการทดสอบ (SOME SUITES FAILED)" . PHP_EOL;
    exit(1);
}
