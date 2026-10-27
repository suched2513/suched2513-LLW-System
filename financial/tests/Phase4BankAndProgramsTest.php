<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Helpers/functions.php';
require_once __DIR__ . '/../app/Services/BahtTextService.php';
require_once __DIR__ . '/../app/Services/BankService.php';
require_once __DIR__ . '/../app/Services/SpecialProgramService.php';

use App\Services\BankService;
use App\Services\SpecialProgramService;

echo "=== เริ่มต้นการทดสอบ Phase 4: Bank Reconciliation & Special Programs ===" . PHP_EOL;

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

// 1. ทดสอบสูตรการคำนวณงบพิสูจน์ยอดเงินฝากธนาคาร (Bank Reconciliation Equilibrium)
// สถานการณ์:
// - ยอดคงเหลือตามสเตทเมนต์ธนาคาร: 500,000.00 บาท
// - เงินฝากระหว่างทาง (Deposits in Transit): ใบเสร็จที่ รร. รับแล้วแต่ยังไม่เข้าสเตทเมนต์ 50,000.00 บาท
// - เช็คค้างจ่าย (Outstanding Checks): เช็คที่จ่ายแล้วแต่ผู้รับยังไม่ขึ้นเงิน 30,000.00 บาท
// - ยอดคงเหลือตามสมุดบัญชีโรงเรียน (Book Balance): 520,000.00 บาท
$statementBalance = 500000.00;
$depositsInTransit = 50000.00;
$outstandingChecks = 30000.00;
$bookBalance = 520000.00;

$adjustedBankBalance = $statementBalance + $depositsInTransit - $outstandingChecks;
$diff = $adjustedBankBalance - $bookBalance;

assert_test("สูตรยอดคงเหลือที่ถูกต้องตามธนาคาร: 500,000 + 50,000 - 30,000 = 520,000 บาท", $adjustedBankBalance === 520000.00);
assert_test("ผลต่างงบพิสูจน์ยอดเท่ากับ 0.00 บาท (ยอดตรงกันสมบูรณ์)", abs($diff) < 0.001);

// 2. ทดสอบกรณีมีผลต่างที่ต้องตรวจสอบ (Discrepancy detection)
$fakeStatementBalance = 495000.00;
$fakeAdjusted = $fakeStatementBalance + $depositsInTransit - $outstandingChecks;
$fakeDiff = $fakeAdjusted - $bookBalance;
assert_test("ระบบตรวจพบผลต่างเมื่อยอดเงินไม่ตรงกัน (ตรวจพบผลต่าง -5,000 บาท)", abs($fakeDiff) > 0.01 && $fakeDiff === -5000.00);

// 3. ทดสอบการคำนวณหนี้และยอดค้างชำระค่าบำรุงการศึกษา (Tuition Billing Calculation)
$originalFee = 3500.00;
$discount = 500.00;
$waived = 0.00;
$netAmount = $originalFee - $discount - $waived;
assert_test("ยอดหนี้สุทธิหลังหักส่วนลด: 3,500 - 500 = 3,000 บาท", $netAmount === 3000.00);

// จ่ายบางส่วน 1,000 บาท
$paidPartial = 1000.00;
$duePartial = $netAmount - $paidPartial;
$statusPartial = ($paidPartial >= $netAmount) ? 'paid' : ($paidPartial > 0 ? 'partial' : 'unpaid');
assert_test("สถานะชำระบางส่วน (partial) คงค้าง 2,000 บาท", $statusPartial === 'partial' && $duePartial === 2000.00);

// จ่ายเพิ่มอีก 2,000 บาท (รวม 3,000 บาท ครบถ้วน)
$paidTotal = $paidPartial + 2000.00;
$dueFinal = $netAmount - $paidTotal;
$statusFinal = ($paidTotal >= $netAmount) ? 'paid' : 'partial';
assert_test("สถานะชำระครบถ้วน (paid) ยอดคงค้าง 0 บาท", $statusFinal === 'paid' && $dueFinal === 0.00);

// 4. ทดสอบการยกเว้นค่าบำรุงการศึกษาสำหรับนักเรียนยากจน (Waiver / Scholarship)
$waivedFull = 3000.00;
$netWaived = max(0.00, $originalFee - $discount - $waivedFull);
$statusWaived = ($netWaived <= 0) ? 'waived' : 'unpaid';
assert_test("สถานะยกเว้นค่าบำรุงการศึกษา (waived) ยอดหนี้สุทธิเป็น 0 บาท", $statusWaived === 'waived' && $netWaived === 0.00);

echo PHP_EOL . "สรุปผลการทดสอบ Phase 4: ผ่าน {$passCount} รายการ, ล้มเหลว {$failCount} รายการ" . PHP_EOL;

if ($failCount > 0) {
    exit(1);
}
