<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Class DisbursementService
 * จัดการกระบวนการเบิกจ่ายเงิน 6 ขั้นตอน (Draft -> Proposed -> Audited -> Approved -> Disbursed -> Posted)
 */
class DisbursementService
{
    public static function generateVoucherNo(?int $fyBe = null): string
    {
        $pdo = Database::getConnection();
        $fyBe = $fyBe ?? thai_fiscal_year();
        $pattern = "PV-{$fyBe}-%";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fin_payments WHERE voucher_no LIKE ?");
        $stmt->execute([$pattern]);
        $count = (int)$stmt->fetchColumn() + 1;

        return sprintf('PV-%d-%04d', $fyBe, $count);
    }

    public static function createDraft(array $data, array $items, int $userId): int
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $fyId = (int)$data['fiscal_year_id'];
            $budgetId = (int)$data['budget_id'];
            $paymentDate = $data['payment_date'] ?? date('Y-m-d');
            $payeeName = trim($data['payee_name']);
            $payeeTaxId = !empty($data['payee_tax_id']) ? trim($data['payee_tax_id']) : null;
            $paymentMethod = $data['payment_method'] ?? 'transfer';
            $bankAccountId = !empty($data['bank_account_id']) ? (int)$data['bank_account_id'] : null;
            $chequeNo = !empty($data['cheque_no']) ? trim($data['cheque_no']) : null;
            $withholdingTaxPercent = (float)($data['withholding_tax_percent'] ?? 0.00);

            if (empty($payeeName)) {
                throw new Exception('กรุณาระบุชื่อผู้รับเงิน');
            }

            $grossAmount = 0.00;
            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 1);
                $price = (float)($item['unit_price'] ?? 0);
                $lineAmt = (float)($item['amount'] ?? ($qty * $price));
                $grossAmount += $lineAmt;
            }

            if ($grossAmount <= 0.00) {
                throw new Exception('ยอดเงินขอเบิกจ่ายรวมต้องมากกว่า 0 บาท');
            }

            $taxAmount = round($grossAmount * ($withholdingTaxPercent / 100), 2);
            $netAmount = $grossAmount - $taxAmount;

            $voucherNo = self::generateVoucherNo();

            $stmt = $pdo->prepare("
                INSERT INTO fin_payments 
                (voucher_no, payment_date, fiscal_year_id, budget_id, requester_id, payee_name, payee_tax_id, payment_method, bank_account_id, cheque_no, gross_amount, withholding_tax_percent, withholding_tax_amount, net_amount, workflow_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
            ");
            $stmt->execute([
                $voucherNo, $paymentDate, $fyId, $budgetId, $userId, $payeeName, $payeeTaxId, $paymentMethod, $bankAccountId, $chequeNo, $grossAmount, $withholdingTaxPercent, $taxAmount, $netAmount
            ]);
            $paymentId = (int)$pdo->lastInsertId();

            $stmtItem = $pdo->prepare("
                INSERT INTO fin_payment_items (payment_id, item_description, quantity, unit_price, amount, account_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 1);
                $price = (float)($item['unit_price'] ?? 0);
                $lineAmt = (float)($item['amount'] ?? ($qty * $price));

                $stmtItem->execute([
                    $paymentId,
                    trim($item['description']),
                    $qty,
                    $price,
                    $lineAmt,
                    !empty($item['account_id']) ? (int)$item['account_id'] : null
                ]);
            }

            $pdo->commit();
            AuditTrailService::log('disbursements', 'create_draft', 'fin_payments', $paymentId, null, ['voucher_no' => $voucherNo, 'net_amount' => $netAmount], "สร้างคำขอเบิกจ่ายร่าง {$voucherNo}");
            return $paymentId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function submit(int $paymentId, int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $p = $stmt->fetch();

        if (!$p) return ['success' => false, 'message' => 'ไม่พบคำขอเบิกจ่าย'];
        if ($p['workflow_status'] !== 'draft') return ['success' => false, 'message' => 'คำขอนี้ไม่ได้อยู่ในสถานะร่าง'];

        // ตรวจสอบงบประมาณคงเหลือและตัดยอดผูกพัน (Budget Commitment Hold)
        $avail = BudgetService::checkAvailability((int)$p['budget_id'], (float)$p['gross_amount']);
        if (!$avail['allowed']) {
            return ['success' => false, 'message' => 'ไม่สามารถเสนอขอเบิกได้: ' . $avail['message']];
        }

        $holdSuccess = BudgetService::holdCommitment((int)$p['budget_id'], 'payment', $paymentId, (float)$p['gross_amount']);
        if (!$holdSuccess) {
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการผูกพันงบประมาณ'];
        }

        $pdo->prepare("UPDATE fin_payments SET workflow_status = 'proposed' WHERE id = ?")->execute([$paymentId]);
        AuditTrailService::log('disbursements', 'submit', 'fin_payments', $paymentId, ['status' => 'draft'], ['status' => 'proposed'], "เสนอคำขอเบิกจ่าย {$p['voucher_no']}");

        return ['success' => true, 'message' => "เสนอคำขอเบิกจ่าย {$p['voucher_no']} เรียบร้อยแล้ว"];
    }

    public static function audit(int $paymentId, int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $p = $stmt->fetch();

        if (!$p) return ['success' => false, 'message' => 'ไม่พบคำขอเบิกจ่าย'];
        if ($p['workflow_status'] !== 'proposed') return ['success' => false, 'message' => 'สถานะไม่ถูกต้องสำหรับการตรวจสอบ'];

        $pdo->prepare("UPDATE fin_payments SET workflow_status = 'audited', audited_by = ?, audited_at = NOW() WHERE id = ?")
            ->execute([$userId, $paymentId]);

        AuditTrailService::log('disbursements', 'audit', 'fin_payments', $paymentId, ['status' => 'proposed'], ['status' => 'audited'], "ตรวจสอบเอกสารเบิกจ่าย {$p['voucher_no']} ผ่านแล้ว");
        return ['success' => true, 'message' => "ตรวจสอบเอกสารเบิกจ่าย {$p['voucher_no']} เรียบร้อย พร้อมเสนออนุมัติ"];
    }

    public static function approve(int $paymentId, int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $p = $stmt->fetch();

        if (!$p) return ['success' => false, 'message' => 'ไม่พบคำขอเบิกจ่าย'];
        if ($p['workflow_status'] !== 'audited') return ['success' => false, 'message' => 'คำขอนี้ยังไม่ผ่านการตรวจสอบ'];

        // Strict Separation of Duties: Requester cannot approve their own disbursement!
        if ((int)$p['requester_id'] === $userId) {
            return ['success' => false, 'message' => 'หลักการควบคุมภายใน: ผู้ขอเบิกไม่สามารถอนุมัติรายการของตนเองได้'];
        }

        $pdo->prepare("UPDATE fin_payments SET workflow_status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
            ->execute([$userId, $paymentId]);

        AuditTrailService::log('disbursements', 'approve', 'fin_payments', $paymentId, ['status' => 'audited'], ['status' => 'approved'], "อนุมัติการเบิกจ่าย {$p['voucher_no']}");
        return ['success' => true, 'message' => "อนุมัติการเบิกจ่าย {$p['voucher_no']} เรียบร้อยแล้ว"];
    }

    public static function disburse(int $paymentId, array $payData, int $userId): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM fin_payments WHERE id = ? FOR UPDATE");
            $stmt->execute([$paymentId]);
            $p = $stmt->fetch();

            if (!$p) throw new Exception('ไม่พบคำขอเบิกจ่าย');
            if ($p['workflow_status'] !== 'approved') throw new Exception('รายการยังไม่ได้รับการอนุมัติ');

            $method = $payData['payment_method'] ?? $p['payment_method'];
            $bankAccId = !empty($payData['bank_account_id']) ? (int)$payData['bank_account_id'] : $p['bank_account_id'];
            $chequeNo = !empty($payData['cheque_no']) ? trim($payData['cheque_no']) : $p['cheque_no'];
            $netAmount = (float)$p['net_amount'];

            // หักยอดเงินจากบัญชีธนาคาร (หากจ่ายผ่านธนาคาร)
            if ($method !== 'cash' && $bankAccId) {
                $stmtBank = $pdo->prepare("SELECT current_balance FROM fin_bank_accounts WHERE id = ? FOR UPDATE");
                $stmtBank->execute([$bankAccId]);
                $bal = (float)$stmtBank->fetchColumn();

                if ($netAmount > $bal) {
                    throw new Exception("ยอดเงินในบัญชีธนาคารไม่พอจ่าย (คงเหลือ: {$bal} บาท, ต้องจ่าย: {$netAmount} บาท)");
                }

                $pdo->prepare("UPDATE fin_bank_accounts SET current_balance = current_balance - ? WHERE id = ?")
                    ->execute([$netAmount, $bankAccId]);
            }

            // ตัดยอดผูกพันเป็นเบิกจ่ายจริงในงบประมาณ
            BudgetService::disburseCommitment((int)$p['budget_id'], 'payment', $paymentId, (float)$p['gross_amount']);

            // อัปเดตสถานะเป็น disbursed
            $stmtUp = $pdo->prepare("
                UPDATE fin_payments 
                SET workflow_status = 'disbursed', payment_method = ?, bank_account_id = ?, cheque_no = ?, disbursed_by = ?, disbursed_at = NOW() 
                WHERE id = ?
            ");
            $stmtUp->execute([$method, $bankAccId, $chequeNo, $userId, $paymentId]);

            $pdo->commit();

            // ลงบัญชีคู่ทั่วไปอัตโนมัติ
            try {
                AccountingService::postPayment($paymentId, $userId);
                $pdo->prepare("UPDATE fin_payments SET workflow_status = 'posted', posted_at = NOW() WHERE id = ?")->execute([$paymentId]);
            } catch (\Throwable $ae) {
                error_log('[Disbursement Auto-Post Warning] ' . $ae->getMessage());
            }

            AuditTrailService::log('disbursements', 'disburse', 'fin_payments', $paymentId, ['status' => 'approved'], ['status' => 'disbursed'], "จ่ายเงินใบสำคัญจ่าย {$p['voucher_no']} จำนวน {$netAmount} บาท");
            return ['success' => true, 'message' => "จ่ายเงินตามใบสำคัญจ่าย {$p['voucher_no']} สำเร็จแล้ว"];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function reject(int $paymentId, string $reason, int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $p = $stmt->fetch();

        if (!$p) return ['success' => false, 'message' => 'ไม่พบคำขอเบิกจ่าย'];

        // คืนยอดผูกพันงบประมาณ
        BudgetService::releaseCommitment('payment', $paymentId);

        $pdo->prepare("UPDATE fin_payments SET workflow_status = 'rejected', rejection_reason = ? WHERE id = ?")
            ->execute([$reason, $paymentId]);

        AuditTrailService::log('disbursements', 'reject', 'fin_payments', $paymentId, ['status' => $p['workflow_status']], ['status' => 'rejected', 'reason' => $reason], "ปฏิเสธคำขอเบิกจ่าย {$p['voucher_no']}");
        return ['success' => true, 'message' => "ปฏิเสธคำขอเบิกจ่ายเรียบร้อยแล้ว"];
    }
}
