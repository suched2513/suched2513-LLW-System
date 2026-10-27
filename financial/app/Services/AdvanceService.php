<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Class AdvanceService
 * จัดการสัญญายืมเงินราชการ (แบบ 8500), การจ่ายเงินยืม, การส่งหลักฐานล้างเงินยืม และการคืนเงิน
 */
class AdvanceService
{
    public static function generateContractNo(?int $fyBe = null): string
    {
        $pdo = Database::getConnection();
        $fyBe = $fyBe ?? thai_fiscal_year();
        $pattern = "ADV-{$fyBe}-%";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fin_advances WHERE contract_no LIKE ?");
        $stmt->execute([$pattern]);
        $count = (int)$stmt->fetchColumn() + 1;

        return sprintf('ADV-%d-%04d', $fyBe, $count);
    }

    public static function createAdvance(array $data, int $userId): int
    {
        $pdo = Database::getConnection();

        $fyId = (int)$data['fiscal_year_id'];
        $budgetId = (int)$data['budget_id'];
        $borrowerId = (int)($data['borrower_id'] ?? $userId);
        $contractDate = $data['contract_date'] ?? date('Y-m-d');
        $objective = trim($data['objective']);
        $amount = (float)$data['amount'];
        $dueDate = $data['due_date'] ?? date('Y-m-d', strtotime('+30 days'));

        if (empty($objective)) {
            throw new Exception('กรุณาระบุวัตถุประสงค์ในการขอยืมเงิน');
        }
        if ($amount <= 0) {
            throw new Exception('จำนวนเงินที่ขอยืมต้องมากกว่า 0 บาท');
        }

        $contractNo = self::generateContractNo();

        $stmt = $pdo->prepare("
            INSERT INTO fin_advances 
            (contract_no, contract_date, fiscal_year_id, budget_id, borrower_id, objective, amount, due_date, status, cleared_amount, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', 0.00, NOW())
        ");
        $stmt->execute([
            $contractNo, $contractDate, $fyId, $budgetId, $borrowerId, $objective, $amount, $dueDate
        ]);
        $advanceId = (int)$pdo->lastInsertId();

        AuditTrailService::log('advances', 'create_contract', 'fin_advances', $advanceId, null, ['contract_no' => $contractNo, 'amount' => $amount], "สร้างสัญญายืมเงิน {$contractNo}");
        return $advanceId;
    }

    public static function approveAdvance(int $advanceId, int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_advances WHERE id = ?");
        $stmt->execute([$advanceId]);
        $adv = $stmt->fetch();

        if (!$adv) return ['success' => false, 'message' => 'ไม่พบสัญญายืมเงิน'];
        if ($adv['status'] !== 'draft') return ['success' => false, 'message' => 'สถานะไม่ถูกต้องสำหรับการอนุมัติ'];

        // Strict Separation of Duties: Borrower cannot approve their own advance!
        if ((int)$adv['borrower_id'] === $userId) {
            return ['success' => false, 'message' => 'ผู้ขอยืมเงินไม่สามารถอนุมัติสัญญายืมเงินของตนเองได้'];
        }

        // ตรวจสอบงบประมาณและผูกพันงบ
        $avail = BudgetService::checkAvailability((int)$adv['budget_id'], (float)$adv['amount']);
        if (!$avail['allowed']) {
            return ['success' => false, 'message' => 'ไม่สามารถอนุมัติได้: ' . $avail['message']];
        }

        $holdSuccess = BudgetService::holdCommitment((int)$adv['budget_id'], 'advance', $advanceId, (float)$adv['amount']);
        if (!$holdSuccess) {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการผูกพันงบประมาณ'];
        }

        $pdo->prepare("UPDATE fin_advances SET status = 'approved' WHERE id = ?")->execute([$advanceId]);
        AuditTrailService::log('advances', 'approve', 'fin_advances', $advanceId, ['status' => 'draft'], ['status' => 'approved'], "อนุมัติสัญญายืมเงิน {$adv['contract_no']}");

        return ['success' => true, 'message' => "อนุมัติสัญญายืมเงิน {$adv['contract_no']} เรียบร้อยแล้ว"];
    }

    public static function disburseAdvance(int $advanceId, int $bankAccountId, int $userId): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM fin_advances WHERE id = ? FOR UPDATE");
            $stmt->execute([$advanceId]);
            $adv = $stmt->fetch();

            if (!$adv) throw new Exception('ไม่พบสัญญายืมเงิน');
            if ($adv['status'] !== 'approved') throw new Exception('สัญญาต้องได้รับการอนุมัติก่อนจ่ายเงินยืม');

            $amount = (float)$adv['amount'];

            // หักยอดเงินธนาคาร
            $stmtBank = $pdo->prepare("SELECT current_balance FROM fin_bank_accounts WHERE id = ? FOR UPDATE");
            $stmtBank->execute([$bankAccountId]);
            $bal = (float)$stmtBank->fetchColumn();

            if ($amount > $bal) {
                throw new Exception("ยอดเงินในบัญชีธนาคารไม่พอจ่ายเงินยืม (คงเหลือ: {$bal} บาท)");
            }

            $pdo->prepare("UPDATE fin_bank_accounts SET current_balance = current_balance - ? WHERE id = ?")
                ->execute([$amount, $bankAccountId]);

            // อัปเดตสถานะเป็น disbursed
            $pdo->prepare("UPDATE fin_advances SET status = 'disbursed' WHERE id = ?")->execute([$advanceId]);

            // ลงบัญชีคู่: Dr. ลูกหนี้เงินยืมในงบประมาณ (1130) / Cr. เงินฝากธนาคาร (1113)
            $advReceivableAccId = AccountingService::getAccountIdByCode('1130');
            $bankAccId = AccountingService::getAccountIdByCode('1113');

            $lines = [
                [
                    'account_id' => $advReceivableAccId,
                    'debit' => $amount,
                    'credit' => 0.00,
                    'description' => "ลูกหนี้เงินยืมตามสัญญายืมเงิน {$adv['contract_no']}"
                ],
                [
                    'account_id' => $bankAccId,
                    'debit' => 0.00,
                    'credit' => $amount,
                    'description' => "จ่ายเงินยืมสัญญายืมเงิน {$adv['contract_no']}"
                ]
            ];

            AccountingService::createJournalEntry(
                date('Y-m-d'),
                (int)$adv['fiscal_year_id'],
                'advance',
                'advance',
                $advanceId,
                "บันทึกจ่ายเงินยืมตามสัญญายืมเงินเลขที่ {$adv['contract_no']}",
                $lines,
                $userId,
                'AV' // Advance Voucher
            );

            $pdo->commit();
            AuditTrailService::log('advances', 'disburse', 'fin_advances', $advanceId, ['status' => 'approved'], ['status' => 'disbursed'], "จ่ายเงินยืม {$adv['contract_no']} จำนวน {$amount} บาท");
            return ['success' => true, 'message' => "จ่ายเงินยืมตามสัญญา {$adv['contract_no']} เรียบร้อยแล้ว"];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function submitClearing(int $advanceId, array $data, int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_advances WHERE id = ?");
        $stmt->execute([$advanceId]);
        $adv = $stmt->fetch();

        if (!$adv) return ['success' => false, 'message' => 'ไม่พบสัญญายืมเงิน'];
        if ($adv['status'] !== 'disbursed' && $adv['status'] !== 'overdue') {
            return ['success' => false, 'message' => 'สถานะไม่ถูกต้องสำหรับการส่งหลักฐานล้างเงินยืม'];
        }

        $clearingDate = $data['clearing_date'] ?? date('Y-m-d');
        $expenseAmount = (float)$data['actual_expense_amount'];
        $advanceAmount = (float)$adv['amount'];

        $refundAmount = 0.00;
        $reimburseAmount = 0.00;

        if ($expenseAmount < $advanceAmount) {
            $refundAmount = $advanceAmount - $expenseAmount; // คืนเงินเหลือ
        } elseif ($expenseAmount > $advanceAmount) {
            $reimburseAmount = $expenseAmount - $advanceAmount; // จ่ายเงินส่วนต่างเพิ่มให้ผู้ยืม
        }

        $stmtCount = $pdo->query("SELECT COUNT(*) FROM fin_advance_clearings");
        $clearingSeq = (int)$stmtCount->fetchColumn() + 1;
        $clearingNo = sprintf('CLR-%d-%04d', thai_fiscal_year(), $clearingSeq);

        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO fin_advance_clearings 
                (clearing_no, clearing_date, advance_id, actual_expense_amount, refund_amount, reimburse_amount, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'submitted', NOW())
            ");
            $stmtInsert->execute([$clearingNo, $clearingDate, $advanceId, $expenseAmount, $refundAmount, $reimburseAmount]);
            $clearingId = (int)$pdo->lastInsertId();

            $pdo->prepare("UPDATE fin_advances SET status = 'clearing_pending' WHERE id = ?")->execute([$advanceId]);

            $pdo->commit();
            AuditTrailService::log('advances', 'submit_clearing', 'fin_advance_clearings', $clearingId, null, ['clearing_no' => $clearingNo, 'expense' => $expenseAmount], "ยื่นขอส่งหลักฐานล้างเงินยืม {$clearingNo}");

            return ['success' => true, 'clearing_id' => $clearingId, 'message' => "ส่งหลักฐานล้างเงินยืม {$clearingNo} เรียบร้อยแล้ว"];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function approveClearing(int $clearingId, int $userId): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT c.*, a.contract_no, a.budget_id, a.fiscal_year_id, a.amount as advance_amount FROM fin_advance_clearings c JOIN fin_advances a ON c.advance_id = a.id WHERE c.id = ? FOR UPDATE");
            $stmt->execute([$clearingId]);
            $clr = $stmt->fetch();

            if (!$clr) throw new Exception('ไม่พบรายการล้างเงินยืม');
            if ($clr['status'] !== 'submitted') throw new Exception('รายการนี้ได้รับการอนุมัติไปแล้ว');

            $advanceId = (int)$clr['advance_id'];
            $expenseAmt = (float)$clr['actual_expense_amount'];
            $advanceAmt = (float)$clr['advance_amount'];
            $refundAmt = (float)$clr['refund_amount'];
            $reimburseAmt = (float)$clr['reimburse_amount'];

            // ปรับยอดผูกพันงบประมาณเป็นเบิกจ่ายจริง
            BudgetService::disburseCommitment((int)$clr['budget_id'], 'advance', $advanceId, $expenseAmt);

            // อัปเดตสถานะการล้างเงินยืม
            $pdo->prepare("UPDATE fin_advance_clearings SET status = 'approved', approved_by = ? WHERE id = ?")
                ->execute([$userId, $clearingId]);

            // อัปเดตสถานะสัญญาเป็น settled (ล้างเรียบร้อย)
            $pdo->prepare("UPDATE fin_advances SET status = 'settled', cleared_amount = ? WHERE id = ?")
                ->execute([$expenseAmt, $advanceId]);

            // บันทึกบัญชีคู่:
            // Dr. ค่าใช้จ่ายดำเนินงาน (5120)  [ตามยอดใช้จริง]
            // Dr. เงินสด (1111)              [ยอดเงินที่ผู้ยืมนำมาคืน ถ้ามี]
            //   Cr. ลูกหนี้เงินยืม (1130)        [ตามยอดที่ยืมไปเดิม]
            //   Cr. เงินฝากธนาคาร/เงินสด        [ถ้าโรงเรียนจ่ายส่วนต่างเพิ่มให้ผู้ยืม]
            $expenseAccId = AccountingService::getAccountIdByCode('5120');
            $advReceivableAccId = AccountingService::getAccountIdByCode('1130');
            $cashAccId = AccountingService::getAccountIdByCode('1111');
            $bankAccId = AccountingService::getAccountIdByCode('1113');

            $lines = [
                [
                    'account_id' => $expenseAccId,
                    'debit' => $expenseAmt,
                    'credit' => 0.00,
                    'description' => "ค่าใช้จ่ายตามหลักฐานล้างเงินยืม {$clr['clearing_no']}"
                ]
            ];

            if ($refundAmt > 0.00) {
                $lines[] = [
                    'account_id' => $cashAccId,
                    'debit' => $refundAmt,
                    'credit' => 0.00,
                    'description' => "รับคืนเงินยืมคงเหลือ {$clr['clearing_no']}"
                ];
            }

            $lines[] = [
                'account_id' => $advReceivableAccId,
                'debit' => 0.00,
                'credit' => $advanceAmt,
                'description' => "ล้างลูกหนี้เงินยืมสัญญายืมเงิน {$clr['contract_no']}"
            ];

            if ($reimburseAmt > 0.00) {
                $lines[] = [
                    'account_id' => $bankAccId,
                    'debit' => 0.00,
                    'credit' => $reimburseAmt,
                    'description' => "จ่ายเงินส่วนต่างเพิ่มให้ผู้ยืม {$clr['clearing_no']}"
                ];
            }

            AccountingService::createJournalEntry(
                date('Y-m-d'),
                (int)$clr['fiscal_year_id'],
                'clearing',
                'clearing',
                $clearingId,
                "บันทึกหลักฐานล้างเงินยืมตามใบรับล้างเลขที่ {$clr['clearing_no']}",
                $lines,
                $userId,
                'CV' // Clearing Voucher
            );

            $pdo->commit();
            AuditTrailService::log('advances', 'approve_clearing', 'fin_advance_clearings', $clearingId, ['status' => 'submitted'], ['status' => 'approved'], "อนุมัติการล้างเงินยืม {$clr['clearing_no']}");
            return ['success' => true, 'message' => "อนุมัติการล้างเงินยืม {$clr['clearing_no']} สำเร็จ สัญญายืมเงินถูกล้างเรียบร้อยแล้ว"];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
