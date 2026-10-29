<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Class AccountingService
 * สมุดบัญชีและเครื่องมือลงบัญชีคู่แบบอัตโนมัติ (Automated Double-Entry Ledger)
 */
class AccountingService
{
    /**
     * ดึงรหัสบัญชี (Account ID) จาก Account Code (เช่น '1111', '1113', '4110', '5120')
     */
    public static function getAccountIdByCode(string $code): ?int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM fin_chart_of_accounts WHERE account_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    /**
     * ค้นหารอบระยะเวลาบัญชี (Accounting Period) ที่ครอบคลุมวันที่ระบุ
     */
    public static function getPeriodByDate(string $date, int $fiscalYearId): ?int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT id, is_closed 
            FROM fin_accounting_periods 
            WHERE fiscal_year_id = ? AND ? BETWEEN start_date AND end_date 
            LIMIT 1
        ");
        $stmt->execute([$fiscalYearId, $date]);
        $period = $stmt->fetch();

        if ($period && $period['is_closed']) {
            throw new Exception("รอบระยะเวลาบัญชีของวันที่ {$date} ถูกปิดงวดแล้ว ไม่สามารถบันทึกรายการได้");
        }

        return $period ? (int)$period['id'] : null;
    }

    /**
     * สร้างเลขที่ใบสำคัญลงบัญชี (Entry Number) e.g. JV-2570-0001
     */
    public static function generateEntryNo(string $prefix = 'JV', ?int $fyBe = null): string
    {
        $pdo = Database::getConnection();
        $fyBe = $fyBe ?? thai_fiscal_year();
        $prefixPattern = "{$prefix}-{$fyBe}-%";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fin_journal_entries WHERE entry_no LIKE ?");
        $stmt->execute([$prefixPattern]);
        $seq = (int)$stmt->fetchColumn() + 1;

        return sprintf('%s-%d-%04d', $prefix, $fyBe, $seq);
    }

    /**
     * บันทึกรายการลงสมุดรายวันทั่วไป (Post Double-Entry Journal)
     * มีการตรวจสอบความสมดุล เดบิต = เครดิต อย่างเข้มงวด
     */
    public static function createJournalEntry(
        string $entryDate,
        int $fiscalYearId,
        string $entryType,
        ?string $refType,
        ?int $refId,
        string $description,
        array $lines,
        int $userId,
        string $prefix = 'JV'
    ): int {
        $totalDebit = 0.00;
        $totalCredit = 0.00;

        foreach ($lines as $line) {
            $totalDebit += (float)($line['debit'] ?? 0.00);
            $totalCredit += (float)($line['credit'] ?? 0.00);
        }

        // ตรวจสอบหลักบัญชีคู่: เดบิตต้องเท่ากับเครดิต
        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new Exception(sprintf(
                'รายการบัญชีไม่สมดุลตามหลักบัญชีคู่ (เดบิต: %.2f, เครดิต: %.2f ต่างกัน %.2f)',
                $totalDebit,
                $totalCredit,
                abs($totalDebit - $totalCredit)
            ));
        }

        if ($totalDebit <= 0.00) {
            throw new Exception('จำนวนเงินในรายการบัญชีต้องมากกว่า 0 บาท');
        }

        $periodId = self::getPeriodByDate($entryDate, $fiscalYearId);
        if (!$periodId) {
            throw new Exception("ไม่พบงวดบัญชีสำหรับวันที่ {$entryDate}");
        }

        $entryNo = self::generateEntryNo($prefix);
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmtEntry = $pdo->prepare("
                INSERT INTO fin_journal_entries 
                (entry_no, entry_date, fiscal_year_id, accounting_period_id, entry_type, reference_type, reference_id, description, total_debit, total_credit, is_posted, is_reversed, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, NOW())
            ");
            $stmtEntry->execute([
                $entryNo, $entryDate, $fiscalYearId, $periodId, $entryType, $refType, $refId, $description, $totalDebit, $totalCredit, $userId
            ]);
            $entryId = (int)$pdo->lastInsertId();

            $stmtLine = $pdo->prepare("
                INSERT INTO fin_journal_lines (journal_entry_id, account_id, debit_amount, credit_amount, line_description)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($lines as $line) {
                $stmtLine->execute([
                    $entryId,
                    $line['account_id'],
                    (float)($line['debit'] ?? 0.00),
                    (float)($line['credit'] ?? 0.00),
                    $line['description'] ?? $description
                ]);
            }

            $pdo->commit();
            AuditTrailService::log('accounting', 'post_journal', 'fin_journal_entries', $entryId, null, ['entry_no' => $entryNo, 'amount' => $totalDebit], "บันทึกบัญชี {$entryNo}: {$description}");

            return $entryId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * ลงบัญชีอัตโนมัติ: เมื่อมีการรับเงิน (Receipt Posting)
     * Dr. เงินสด (1111) หรือ เงินฝากธนาคาร (1113)
     *    Cr. รายได้ตามแหล่งเงิน (4110 / 4210 / 4220 / 4230)
     */
    public static function postReceipt(int $receiptId, int $userId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_receipts WHERE id = ?");
        $stmt->execute([$receiptId]);
        $receipt = $stmt->fetch();

        if (!$receipt) throw new Exception('ไม่พบใบเสร็จรับเงิน');

        // Check if already posted
        $stmtCheck = $pdo->prepare("SELECT id FROM fin_journal_entries WHERE reference_type = 'receipt' AND reference_id = ? AND is_reversed = 0");
        $stmtCheck->execute([$receiptId]);
        if ($existingId = $stmtCheck->fetchColumn()) {
            return (int)$existingId;
        }

        $isBank = ($receipt['payment_method'] === 'transfer' || $receipt['payment_method'] === 'cheque');
        $debitAccCode = $isBank ? '1113' : '1111'; // 1113=เงินฝากธนาคาร, 1111=เงินสด
        $debitAccId = self::getAccountIdByCode($debitAccCode);

        // หาบัญชีรายได้ตามประเภทแหล่งเงิน (Funding Source Type)
        $fsType = 'revenue';
        if (!empty($receipt['funding_source_id'])) {
            $stmtFs = $pdo->prepare("SELECT source_type FROM fin_funding_sources WHERE id = ?");
            $stmtFs->execute([(int)$receipt['funding_source_id']]);
            $fsType = $stmtFs->fetchColumn() ?: 'revenue';
        }

        if ($fsType === 'subsidy') {
            $creditAccId = self::getAccountIdByCode('410101') ?? self::getAccountIdByCode('4110'); // เงินอุดหนุน
        } elseif ($fsType === 'donation') {
            $creditAccId = self::getAccountIdByCode('410103') ?? self::getAccountIdByCode('4130'); // เงินบริจาค
        } elseif ($fsType === 'tuition_fee') {
            $creditAccId = self::getAccountIdByCode('410104') ?? self::getAccountIdByCode('4220'); // ค่าบำรุงการศึกษา
        } else {
            $creditAccId = self::getAccountIdByCode('410102') ?? self::getAccountIdByCode('4210'); // เงินรายได้สถานศึกษา/อื่นๆ
        }

        $lines = [
            [
                'account_id' => $debitAccId,
                'debit' => (float)$receipt['total_amount'],
                'credit' => 0.00,
                'description' => "รับเงินตามใบเสร็จ {$receipt['receipt_no']} จาก {$receipt['payer_name']}"
            ],
            [
                'account_id' => $creditAccId,
                'debit' => 0.00,
                'credit' => (float)$receipt['total_amount'],
                'description' => "รายรับตามใบเสร็จ {$receipt['receipt_no']}"
            ]
        ];

        return self::createJournalEntry(
            $receipt['receipt_date'],
            (int)$receipt['fiscal_year_id'],
            'receipt',
            'receipt',
            $receiptId,
            "บันทึกรับเงินตามใบเสร็จเลขที่ {$receipt['receipt_no']}",
            $lines,
            $userId,
            'RV' // Receipt Voucher
        );
    }

    /**
     * ลงบัญชีอัตโนมัติ: เมื่อมีการเบิกจ่ายเงิน (Payment Posting)
     * Dr. ค่าใช้จ่ายดำเนินงาน / ค่าตอบแทน (5120)
     *    Cr. เงินฝากธนาคาร (1113) หรือ เงินสด (1111)
     *    Cr. ภาษีหัก ณ ที่จ่ายรอนำส่ง (2130) (ถ้ามี)
     */
    public static function postPayment(int $paymentId, int $userId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();

        if (!$payment) throw new Exception('ไม่พบรายการเบิกจ่ายเงิน');

        // Check if already posted
        $stmtCheck = $pdo->prepare("SELECT id FROM fin_journal_entries WHERE reference_type = 'payment' AND reference_id = ? AND is_reversed = 0");
        $stmtCheck->execute([$paymentId]);
        if ($existingId = $stmtCheck->fetchColumn()) {
            return (int)$existingId;
        }

        $expenseAccId = self::getAccountIdByCode('5120'); // ค่าใช้จ่าย
        $bankAccId = ($payment['payment_method'] === 'cash') ? self::getAccountIdByCode('1111') : self::getAccountIdByCode('1113');
        $taxAccId = self::getAccountIdByCode('2130'); // ภาษีหัก ณ ที่จ่าย

        $gross = (float)$payment['gross_amount'];
        $tax = (float)$payment['withholding_tax_amount'];
        $net = (float)$payment['net_amount'];

        $lines = [
            [
                'account_id' => $expenseAccId,
                'debit' => $gross,
                'credit' => 0.00,
                'description' => "เบิกจ่ายตามใบสำคัญจ่าย {$payment['voucher_no']} จ่ายให้ {$payment['payee_name']}"
            ],
            [
                'account_id' => $bankAccId,
                'debit' => 0.00,
                'credit' => $net,
                'description' => "จ่ายเงินสุทธิ ใบสำคัญจ่าย {$payment['voucher_no']}"
            ]
        ];

        if ($tax > 0.00) {
            $lines[] = [
                'account_id' => $taxAccId,
                'debit' => 0.00,
                'credit' => $tax,
                'description' => "ภาษีหัก ณ ที่จ่าย {$payment['withholding_tax_percent']}% ใบสำคัญจ่าย {$payment['voucher_no']}"
            ];
        }

        return self::createJournalEntry(
            $payment['payment_date'],
            (int)$payment['fiscal_year_id'],
            'payment',
            'payment',
            $paymentId,
            "บันทึกจ่ายเงินตามใบสำคัญจ่ายเลขที่ {$payment['voucher_no']}",
            $lines,
            $userId,
            'PV' // Payment Voucher
        );
    }

    /**
     * คำนวณงบทดลอง (Trial Balance) ตามปีงบประมาณและงวดบัญชี
     */
    public static function getTrialBalance(int $fiscalYearId, ?int $periodId = null): array
    {
        $pdo = Database::getConnection();

        $sql = "
            SELECT 
                a.id, a.account_code, a.account_name, a.account_category, a.normal_balance,
                COALESCE(SUM(l.debit_amount), 0) as total_debit,
                COALESCE(SUM(l.credit_amount), 0) as total_credit
            FROM fin_chart_of_accounts a
            LEFT JOIN fin_journal_lines l ON a.id = l.account_id
            LEFT JOIN fin_journal_entries e ON l.journal_entry_id = e.id AND e.fiscal_year_id = ? AND e.is_posted = 1 AND e.is_reversed = 0
        ";
        $params = [$fiscalYearId];

        if ($periodId) {
            $sql .= " AND e.accounting_period_id = ?";
            $params[] = $periodId;
        }

        $sql .= " GROUP BY a.id ORDER BY a.account_code ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $grandDebit = 0.00;
        $grandCredit = 0.00;
        $accounts = [];

        foreach ($rows as $r) {
            $debit = (float)$r['total_debit'];
            $credit = (float)$r['total_credit'];

            $balanceDebit = 0.00;
            $balanceCredit = 0.00;

            if ($r['normal_balance'] === 'debit') {
                $net = $debit - $credit;
                if ($net >= 0) {
                    $balanceDebit = $net;
                } else {
                    $balanceCredit = abs($net);
                }
            } else {
                $net = $credit - $debit;
                if ($net >= 0) {
                    $balanceCredit = $net;
                } else {
                    $balanceDebit = abs($net);
                }
            }

            $grandDebit += $balanceDebit;
            $grandCredit += $balanceCredit;

            $accounts[] = array_merge($r, [
                'balance_debit' => $balanceDebit,
                'balance_credit' => $balanceCredit
            ]);
        }

        return [
            'accounts' => $accounts,
            'grand_debit' => $grandDebit,
            'grand_credit' => $grandCredit,
            'is_balanced' => abs($grandDebit - $grandCredit) < 0.01
        ];
    }
}
