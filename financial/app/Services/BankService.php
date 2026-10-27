<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Class BankService
 * จัดการบัญชีธนาคาร การนำเข้าสเตทเมนต์ (CSV) และระบบกระทบยอดเงินฝากธนาคาร (Bank Reconciliation)
 */
class BankService
{
    public static function getAccounts(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT b.*, fs.source_name,
                   b.account_no as account_number
            FROM fin_bank_accounts b
            LEFT JOIN fin_funding_sources fs ON b.funding_source_id = fs.id
            ORDER BY b.id ASC
        ");
        return $stmt->fetchAll();
    }

    public static function createAccount(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO fin_bank_accounts (bank_name, branch_name, account_no, account_name, account_type, current_balance, funding_source_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            trim($data['bank_name']),
            trim($data['branch_name'] ?? ''),
            trim($data['account_no']),
            trim($data['account_name']),
            $data['account_type'] ?? 'savings',
            (float)($data['initial_balance'] ?? 0.00),
            !empty($data['funding_source_id']) ? (int)$data['funding_source_id'] : null
        ]);
        $accId = (int)$pdo->lastInsertId();

        AuditTrailService::log('banks', 'create_account', 'fin_bank_accounts', $accId, null, $data, "เพิ่มบัญชีธนาคาร {$data['account_no']}");
        return $accId;
    }

    public static function recordTransaction(
        int $bankAccountId,
        string $transDate,
        string $transType,
        float $amount,
        ?string $refType = null,
        ?int $refId = null
    ): int {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO fin_bank_transactions (bank_account_id, trans_date, trans_type, amount, reference_type, reference_id, is_reconciled, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$bankAccountId, $transDate, $transType, $amount, $refType, $refId]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * นำเข้า Bank Statement จากไฟล์ CSV
     * รูปแบบคอลัมน์มาตรฐาน: วันที่(Y-m-d), รายการ, ถอน/จ่าย(Withdrawal), ฝาก/รับ(Deposit), ยอดคงเหลือ(Balance)
     */
    public static function importStatementCsv(int $bankAccountId, string $month, array $file, int $userId): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $filename = basename($file['name']);
            $tmpPath = $file['tmp_name'];

            // ลบการนำเข้าเดิมของเดือนเดียวกันถ้ามี
            $stmtDel = $pdo->prepare("SELECT id FROM fin_statement_imports WHERE bank_account_id = ? AND statement_month = ?");
            $stmtDel->execute([$bankAccountId, $month]);
            if ($oldId = $stmtDel->fetchColumn()) {
                $pdo->prepare("DELETE FROM fin_statement_imports WHERE id = ?")->execute([$oldId]);
            }

            $stmtImport = $pdo->prepare("
                INSERT INTO fin_statement_imports (bank_account_id, statement_month, original_filename, uploaded_by, uploaded_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmtImport->execute([$bankAccountId, $month, $filename, $userId]);
            $importId = (int)$pdo->lastInsertId();

            $handle = fopen($tmpPath, 'r');
            if (!$handle) throw new Exception('ไม่สามารถเปิดไฟล์ CSV ได้');

            $stmtLine = $pdo->prepare("
                INSERT INTO fin_statement_lines (statement_import_id, line_date, description, withdrawal, deposit, balance, match_status)
                VALUES (?, ?, ?, ?, ?, ?, 'unmatched')
            ");

            $lineCount = 0;
            $header = fgetcsv($handle); // ข้ามแถว Header

            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row) || count($row) < 4) continue;

                $rawDate = trim($row[0]);
                $date = date('Y-m-d', strtotime($rawDate)) ?: date('Y-m-d');
                $desc = trim($row[1] ?? '');
                $withdrawal = (float)str_replace(',', '', $row[2] ?? '0');
                $deposit = (float)str_replace(',', '', $row[3] ?? '0');
                $balance = (float)str_replace(',', '', $row[4] ?? '0');

                $stmtLine->execute([$importId, $date, $desc, $withdrawal, $deposit, $balance]);
                $lineCount++;
            }
            fclose($handle);

            $pdo->commit();
            AuditTrailService::log('banks', 'import_statement', 'fin_statement_imports', $importId, null, ['month' => $month, 'lines' => $lineCount], "นำเข้าสเตทเมนต์ธนาคาร {$filename} ({$lineCount} รายการ)");

            return ['success' => true, 'import_id' => $importId, 'count' => $lineCount];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ดำเนินการกระทบยอดเงินฝากธนาคาร (Bank Reconciliation)
     * เปรียบเทียบยอดตามสมุดบัญชีโรงเรียน กับ สเตทเมนต์ธนาคาร
     */
    public static function reconcile(int $bankAccountId, string $asOfDate, float $statementEndingBalance, int $userId): array
    {
        $pdo = Database::getConnection();

        // 1. ยอดตามสมุดบัญชีโรงเรียน (Book Balance) ณ วันที่กระทบยอด
        $stmtAcc = $pdo->prepare("SELECT current_balance FROM fin_bank_accounts WHERE id = ?");
        $stmtAcc->execute([$bankAccountId]);
        $bookBalance = (float)$stmtAcc->fetchColumn();

        // 2. เช็คค้างจ่าย (Outstanding Checks: Payments that are disbursed but not yet cleared in bank statement)
        $stmtChecks = $pdo->prepare("
            SELECT p.voucher_no, p.payment_date, p.payee_name, p.net_amount, p.cheque_no
            FROM fin_payments p
            WHERE p.bank_account_id = ? 
              AND p.workflow_status IN ('disbursed', 'posted')
              AND p.payment_date <= ?
              AND p.id NOT IN (
                  SELECT reference_id FROM fin_bank_transactions WHERE reference_type = 'payment' AND is_reconciled = 1
              )
        ");
        $stmtChecks->execute([$bankAccountId, $asOfDate]);
        $outstandingChecks = $stmtChecks->fetchAll();
        $totalOutstandingChecks = array_sum(array_column($outstandingChecks, 'net_amount'));

        // 3. เงินฝากระหว่างทาง (Deposits in Transit: Cash/transfer received by school but not cleared in bank)
        $stmtDeposits = $pdo->prepare("
            SELECT r.receipt_no, r.receipt_date, r.payer_name, r.total_amount
            FROM fin_receipts r
            WHERE r.bank_account_id = ?
              AND r.status IN ('issued', 'deposited')
              AND r.receipt_date <= ?
              AND r.id NOT IN (
                  SELECT reference_id FROM fin_bank_transactions WHERE reference_type = 'receipt' AND is_reconciled = 1
              )
        ");
        $stmtDeposits->execute([$bankAccountId, $asOfDate]);
        $depositsInTransit = $stmtDeposits->fetchAll();
        $totalDepositsInTransit = array_sum(array_column($depositsInTransit, 'total_amount'));

        // 4. การคำนวณพิสูจน์ยอด (Adjusted Bank Balance Formula)
        // ยอดคงเหลือตามใบแจ้งยอดธนาคาร (Statement Balance)
        // บวก: เงินฝากระหว่างทาง (Deposits in Transit)
        // หัก: เช็คสั่งจ่ายที่ยังไม่ได้นำไปขึ้นเงิน (Outstanding Checks)
        // = ยอดคงเหลือที่ถูกต้อง (Adjusted Bank Balance)
        $adjustedBankBalance = $statementEndingBalance + $totalDepositsInTransit - $totalOutstandingChecks;
        $difference = $adjustedBankBalance - $bookBalance;

        // บันทึกผลการกระทบยอดลง fin_reconciliations
        $stmtRec = $pdo->prepare("
            INSERT INTO fin_reconciliations (bank_account_id, as_of_date, book_balance, bank_statement_balance, reconciled_difference, status, verified_by, verified_at)
            VALUES (?, ?, ?, ?, ?, 'verified', ?, NOW())
        ");
        $stmtRec->execute([$bankAccountId, $asOfDate, $bookBalance, $statementEndingBalance, $difference, $userId]);
        $recId = (int)$pdo->lastInsertId();

        AuditTrailService::log('banks', 'reconcile', 'fin_reconciliations', $recId, null, ['diff' => $difference], "กระทบยอดเงินฝากธนาคาร ณ วันที่ {$asOfDate}");

        return [
            'reconciliation_id'        => $recId,
            'as_of_date'               => $asOfDate,
            'book_balance'             => $bookBalance,
            'statement_balance'        => $statementEndingBalance,
            'deposits_in_transit'      => $depositsInTransit,
            'total_deposits_in_transit'=> $totalDepositsInTransit,
            'outstanding_checks'       => $outstandingChecks,
            'total_outstanding_checks' => $totalOutstandingChecks,
            'adjusted_bank_balance'    => $adjustedBankBalance,
            'difference'               => $difference,
            'is_matched'               => abs($difference) < 0.01
        ];
    }
}
