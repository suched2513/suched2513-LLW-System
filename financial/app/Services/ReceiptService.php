<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Class ReceiptService
 * จัดการระบบรับเงิน ออกใบเสร็จ นำฝากธนาคาร และยกเลิกใบเสร็จ
 */
class ReceiptService
{
    public static function generateReceiptNo(?int $fyBe = null): string
    {
        $pdo = Database::getConnection();
        $fyBe = $fyBe ?? thai_fiscal_year();
        $pattern = "REC-{$fyBe}-%";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fin_receipts WHERE receipt_no LIKE ?");
        $stmt->execute([$pattern]);
        $count = (int)$stmt->fetchColumn() + 1;

        return sprintf('REC-%d-%04d', $fyBe, $count);
    }

    public static function createReceipt(array $data, array $items, int $userId): int
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $fyId = (int)$data['fiscal_year_id'];
            $receiptDate = $data['receipt_date'] ?? date('Y-m-d');
            $fundingSourceId = (int)$data['funding_source_id'];
            $payerName = trim($data['payer_name']);
            $payerTaxId = !empty($data['payer_tax_id']) ? trim($data['payer_tax_id']) : null;
            $paymentMethod = $data['payment_method']; // 'cash', 'transfer', 'cheque'
            $bankAccountId = !empty($data['bank_account_id']) ? (int)$data['bank_account_id'] : null;
            $bankRefNo = !empty($data['bank_ref_no']) ? trim($data['bank_ref_no']) : null;

            if (empty($payerName)) {
                throw new Exception('กรุณาระบุชื่อผู้ชำระเงิน');
            }

            $totalAmount = 0.00;
            foreach ($items as $item) {
                $totalAmount += (float)$item['amount'];
            }

            if ($totalAmount <= 0) {
                throw new Exception('ยอดเงินรวมในใบเสร็จต้องมากกว่า 0 บาท');
            }

            $receiptNo = self::generateReceiptNo();

            $stmt = $pdo->prepare("
                INSERT INTO fin_receipts 
                (receipt_no, receipt_date, fiscal_year_id, funding_source_id, payer_name, payer_tax_id, payment_method, bank_account_id, bank_ref_no, total_amount, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'issued', ?, NOW())
            ");
            $stmt->execute([
                $receiptNo, $receiptDate, $fyId, $fundingSourceId, $payerName, $payerTaxId, $paymentMethod, $bankAccountId, $bankRefNo, $totalAmount, $userId
            ]);
            $receiptId = (int)$pdo->lastInsertId();

            $stmtItem = $pdo->prepare("
                INSERT INTO fin_receipt_items (receipt_id, item_description, amount, account_id)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $stmtItem->execute([
                    $receiptId,
                    trim($item['description']),
                    (float)$item['amount'],
                    !empty($item['account_id']) ? (int)$item['account_id'] : null
                ]);
            }

            // ถ้าเป็นการโอนเข้าบัญชีธนาคาร ให้ปรับยอดเงินในบัญชีธนาคารทันที
            if ($paymentMethod === 'transfer' && $bankAccountId) {
                $stmtBank = $pdo->prepare("UPDATE fin_bank_accounts SET current_balance = current_balance + ? WHERE id = ?");
                $stmtBank->execute([$totalAmount, $bankAccountId]);
            }

            $pdo->commit();

            // ลงบัญชีคู่อัตโนมัติ (Receipt Posting)
            try {
                AccountingService::postReceipt($receiptId, $userId);
            } catch (\Throwable $ae) {
                error_log('[Receipt Auto-Post Warning] ' . $ae->getMessage());
            }

            AuditTrailService::log('receipts', 'create', 'fin_receipts', $receiptId, null, ['receipt_no' => $receiptNo, 'total' => $totalAmount], "ออกใบเสร็จรับเงิน {$receiptNo}");
            return $receiptId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function cancelReceipt(int $receiptId, string $reason, int $userId): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM fin_receipts WHERE id = ? FOR UPDATE");
            $stmt->execute([$receiptId]);
            $receipt = $stmt->fetch();

            if (!$receipt) throw new Exception('ไม่พบใบเสร็จรับเงิน');
            if ($receipt['status'] === 'cancelled') throw new Exception('ใบเสร็จนี้ถูกยกเลิกไปแล้ว');

            $stmtUp = $pdo->prepare("
                UPDATE fin_receipts 
                SET status = 'cancelled', cancellation_reason = ?, cancelled_by = ?, cancelled_at = NOW() 
                WHERE id = ?
            ");
            $stmtUp->execute([$reason, $userId, $receiptId]);

            // คืนยอดเงินธนาคารหากเคยโอนเข้า
            if ($receipt['payment_method'] === 'transfer' && !empty($receipt['bank_account_id'])) {
                $pdo->prepare("UPDATE fin_bank_accounts SET current_balance = current_balance - ? WHERE id = ?")
                    ->execute([(float)$receipt['total_amount'], $receipt['bank_account_id']]);
            }

            // กลับรายการบัญชี (Reverse Journal Entry)
            $stmtJe = $pdo->prepare("SELECT id FROM fin_journal_entries WHERE reference_type = 'receipt' AND reference_id = ? AND is_reversed = 0");
            $stmtJe->execute([$receiptId]);
            $jeId = $stmtJe->fetchColumn();
            if ($jeId) {
                $pdo->prepare("UPDATE fin_journal_entries SET is_reversed = 1 WHERE id = ?")->execute([$jeId]);
            }

            $pdo->commit();
            AuditTrailService::log('receipts', 'cancel', 'fin_receipts', $receiptId, null, ['reason' => $reason], "ยกเลิกใบเสร็จรับเงิน {$receipt['receipt_no']}");
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[ReceiptService::cancelReceipt Error] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * นำฝากเงินสดเข้าบัญชีธนาคาร (Deposit cash receipts to bank)
     * ไม่บันทึกรายได้ซ้ำ แต่บันทึก โอนจากเงินสด (1111) เข้าเงินฝากธนาคาร (1113)
     */
    public static function depositCash(array $receiptIds, int $bankAccountId, string $depositDate, int $userId): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $inClause = implode(',', array_map('intval', $receiptIds));
            $stmt = $pdo->query("SELECT id, receipt_no, total_amount FROM fin_receipts WHERE id IN ({$inClause}) AND payment_method = 'cash' AND status = 'issued' FOR UPDATE");
            $receipts = $stmt->fetchAll();

            if (empty($receipts)) {
                throw new Exception('ไม่พบรายการเงินสดที่พร้อมนำฝากธนาคาร');
            }

            $totalDeposit = 0.00;
            foreach ($receipts as $r) {
                $totalDeposit += (float)$r['total_amount'];
            }

            // อัปเดตสถานะใบเสร็จเป็น deposited
            $pdo->exec("UPDATE fin_receipts SET status = 'deposited' WHERE id IN ({$inClause})");

            // เพิ่มยอดเงินในบัญชีธนาคาร
            $pdo->prepare("UPDATE fin_bank_accounts SET current_balance = current_balance + ? WHERE id = ?")
                ->execute([$totalDeposit, $bankAccountId]);

            // บันทึกบัญชีคู่: Dr. เงินฝากธนาคาร (1113) / Cr. เงินสด (1111)
            $bankAccCodeId = AccountingService::getAccountIdByCode('1113');
            $cashAccCodeId = AccountingService::getAccountIdByCode('1111');

            $activeFy = AuthService::getActiveFiscalYear();
            $fyId = (int)$activeFy['id'];

            $lines = [
                [
                    'account_id' => $bankAccCodeId,
                    'debit' => $totalDeposit,
                    'credit' => 0.00,
                    'description' => "นำฝากเงินสดเข้าบัญชีธนาคาร รวม " . count($receipts) . " ใบเสร็จ"
                ],
                [
                    'account_id' => $cashAccCodeId,
                    'debit' => 0.00,
                    'credit' => $totalDeposit,
                    'description' => "ส่งฝากเงินสดธนาคาร รวม " . count($receipts) . " ใบเสร็จ"
                ]
            ];

            AccountingService::createJournalEntry(
                $depositDate,
                $fyId,
                'general',
                'bank_deposit',
                $bankAccountId,
                "บันทึกนำฝากเงินสดเข้าบัญชีธนาคาร จำนวน " . money_format_th($totalDeposit) . " บาท",
                $lines,
                $userId,
                'DP' // Deposit Voucher
            );

            $pdo->commit();
            AuditTrailService::log('receipts', 'deposit', 'fin_bank_accounts', $bankAccountId, null, ['amount' => $totalDeposit, 'receipt_count' => count($receipts)], "นำฝากเงินสดเข้าธนาคาร");

            return ['success' => true, 'total_deposited' => $totalDeposit, 'count' => count($receipts)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
