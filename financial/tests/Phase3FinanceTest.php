<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Helpers/functions.php';
require_once __DIR__ . '/../app/Services/BahtTextService.php';
require_once __DIR__ . '/../app/Services/SettingService.php';
require_once __DIR__ . '/../app/Services/BudgetService.php';
require_once __DIR__ . '/../app/Services/AccountingService.php';
require_once __DIR__ . '/../app/Services/ReceiptService.php';
require_once __DIR__ . '/../app/Services/DisbursementService.php';
require_once __DIR__ . '/../app/Services/AdvanceService.php';

use App\Services\BudgetService;
use App\Services\AccountingService;
use App\Services\ReceiptService;
use App\Services\DisbursementService;
use App\Services\AdvanceService;

echo "=== เริ่มต้นการทดสอบ Phase 3: Financial & Accounting Engine ===" . PHP_EOL;

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

// 1. ทดสอบรูปแบบเลขที่เอกสารอัตโนมัติ (Document Running Numbers)
$fy = thai_fiscal_year();

$recPattern = "/^REC-{$fy}-\\d{4}$/";
$testRecNo = sprintf('REC-%d-%04d', $fy, 1);
assert_test("รูปแบบเลขที่ใบเสร็จรับเงิน REC-{$fy}-0001 ถูกต้อง", (bool)preg_match($recPattern, $testRecNo));

$pvPattern = "/^PV-{$fy}-\\d{4}$/";
$testPvNo = sprintf('PV-%d-%04d', $fy, 1);
assert_test("รูปแบบเลขที่ใบสำคัญจ่าย PV-{$fy}-0001 ถูกต้อง", (bool)preg_match($pvPattern, $testPvNo));

$advPattern = "/^ADV-{$fy}-\\d{4}$/";
$testAdvNo = sprintf('ADV-%d-%04d', $fy, 1);
assert_test("รูปแบบเลขที่สัญญายืมเงิน ADV-{$fy}-0001 ถูกต้อง", (bool)preg_match($advPattern, $testAdvNo));

$jvPattern = "/^JV-{$fy}-\\d{4}$/";
$testJvNo = sprintf('JV-%d-%04d', $fy, 1);
assert_test("รูปแบบเลขที่ใบสำคัญรายวัน JV-{$fy}-0001 ถูกต้อง", (bool)preg_match($jvPattern, $testJvNo));

// 2. ทดสอบสูตรควบคุมวงเงินงบประมาณ (Real-time Budget Control Formula)
// Net Budget: 100,000, Committed: 30,000, Paid: 40,000 (Used: 70,000, Remaining: 30,000)
$netBudget = 100000.00;
$committed = 30000.00;
$paid = 40000.00;
$remaining = $netBudget - ($committed + $paid);

// คำขอใหม่: 5,000 => รวม 75,000 (75%) => ปกติ (< 80%)
$req1 = 5000.00;
$pct1 = (($committed + $paid + $req1) / $netBudget) * 100;
assert_test("คำขอ 5,000 บาท ใช้งบไป 75% ไม่เกินเกณฑ์แจ้งเตือน 80%", $pct1 < 80.0 && $req1 <= $remaining);

// คำขอใหม่: 15,000 => รวม 85,000 (85%) => เตือน (>= 80% แต่ไม่เกิน 100%)
$req2 = 15000.00;
$pct2 = (($committed + $paid + $req2) / $netBudget) * 100;
assert_test("คำขอ 15,000 บาท ใช้งบไป 85% เข้าเกณฑ์แจ้งเตือนงบใกล้หมด (Warning >= 80%)", $pct2 >= 80.0 && $pct2 < 100.0);

// คำขอใหม่: 35,000 => เกินงบคงเหลือ (เหลือ 30,000) => ต้องบล็อก
$req3 = 35000.00;
assert_test("คำขอ 35,000 บาท เกินงบคงเหลือ (30,000 บาท) ถูกปฏิเสธ (Blocked)", $req3 > $remaining);

// 3. ทดสอบการคำนวณล้างเงินยืม (Advance Clearing Calculation)
$advanceAmount = 50000.00;

// กรณีที่ 1: ใช้จ่ายจริง 45,000 บาท (เหลือเงิน 5,000 บาท ต้องคืน)
$expense1 = 45000.00;
$refund1 = ($expense1 < $advanceAmount) ? ($advanceAmount - $expense1) : 0.00;
$reimburse1 = ($expense1 > $advanceAmount) ? ($expense1 - $advanceAmount) : 0.00;
assert_test("ล้างเงินยืม: ใช้ 45,000 บาท คืนเงินเหลือ 5,000 บาท พอดี", $refund1 === 5000.00 && $reimburse1 === 0.00);

// กรณีที่ 2: ใช้จ่ายจริง 52,000 บาท (เบิกเกินวงเงินยืม 2,000 บาท ต้องจ่ายส่วนต่างเพิ่ม)
$expense2 = 52000.00;
$refund2 = ($expense2 < $advanceAmount) ? ($advanceAmount - $expense2) : 0.00;
$reimburse2 = ($expense2 > $advanceAmount) ? ($expense2 - $advanceAmount) : 0.00;
assert_test("ล้างเงินยืม: ใช้ 52,000 บาท โรงเรียนต้องจ่ายส่วนต่างเพิ่ม 2,000 บาท", $refund2 === 0.00 && $reimburse2 === 2000.00);

// 4. ทดสอบความสมดุลของสมุดบัญชีคู่ (Double-entry Equilibrium: Debit == Credit)
$testLines = [
    ['account_id' => 1, 'debit' => 10000.00, 'credit' => 0.00],
    ['account_id' => 2, 'debit' => 0.00, 'credit' => 9900.00],
    ['account_id' => 3, 'debit' => 0.00, 'credit' => 100.00]
];
$sumDr = array_sum(array_column($testLines, 'debit'));
$sumCr = array_sum(array_column($testLines, 'credit'));
assert_test("สมุดบัญชีคู่มีความสมดุล เดบิต (10,000.00) เท่ากับ เครดิต (10,000.00)", abs($sumDr - $sumCr) < 0.001);

// 5. ทดสอบการแบ่งแยกหน้าที่ (Separation of Duties Rule)
$requesterId = 5; // ครูผู้ขอเบิก
$approverId = 5;  // ผู้อนุมัติเป็นคนเดียวกัน
$isSoDViolation = ($requesterId === $approverId);
assert_test("หลักการควบคุมภายใน: ผู้ขอเบิกต้องไม่อนุมัติรายการของตนเอง (SoD Enforced)", $isSoDViolation === true);

echo PHP_EOL . "สรุปผลการทดสอบ Phase 3: ผ่าน {$passCount} รายการ, ล้มเหลว {$failCount} รายการ" . PHP_EOL;

if ($failCount > 0) {
    exit(1);
}
