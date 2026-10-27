<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AccountingService;
use App\Services\AuthService;
use PDO;

/**
 * Class AccountController
 * จัดการผังบัญชี 5 หมวด, สมุดรายวันทั่วไป, สมุดแยกประเภท และงบทดลอง
 */
class AccountController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('accounts', 'view');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $entryType = trim((string)$this->request->query('entry_type', ''));
        $dateFrom = trim((string)$this->request->query('date_from', ''));
        $dateTo = trim((string)$this->request->query('date_to', ''));

        $sql = "
            SELECT e.*, p.period_name, u.full_name as creator_name
            FROM fin_journal_entries e
            JOIN fin_accounting_periods p ON e.accounting_period_id = p.id
            LEFT JOIN fin_users u ON e.created_by = u.id
            WHERE e.fiscal_year_id = ?
        ";
        $params = [$fyId];

        if ($entryType !== '') {
            $sql .= " AND e.entry_type = ?";
            $params[] = $entryType;
        }
        if ($dateFrom !== '') {
            $sql .= " AND e.entry_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= " AND e.entry_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY e.entry_date DESC, e.id DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll();

        $this->render('accounts/index', [
            'pageTitle' => 'สมุดรายวันทั่วไป (General Journal)',
            'entries'   => $entries,
            'filter'    => [
                'entry_type' => $entryType,
                'date_from'  => $dateFrom,
                'date_to'    => $dateTo
            ]
        ]);
    }

    public function showEntry(string $id): void
    {
        $this->checkPermission('accounts', 'view');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT e.*, p.period_name, u.full_name as creator_name
            FROM fin_journal_entries e
            JOIN fin_accounting_periods p ON e.accounting_period_id = p.id
            LEFT JOIN fin_users u ON e.created_by = u.id
            WHERE e.id = ?
        ");
        $stmt->execute([(int)$id]);
        $entry = $stmt->fetch();

        if (!$entry) {
            $this->setFlash('danger', 'ไม่พบรายการบันทึกบัญชีที่ระบุ');
            $this->redirect('/accounts');
        }

        $stmtLines = $pdo->prepare("
            SELECT l.*, a.account_code, a.account_name, a.account_category
            FROM fin_journal_lines l
            JOIN fin_chart_of_accounts a ON l.account_id = a.id
            WHERE l.journal_entry_id = ?
            ORDER BY l.debit_amount DESC, l.credit_amount ASC, l.id ASC
        ");
        $stmtLines->execute([(int)$id]);
        $lines = $stmtLines->fetchAll();

        $this->render('accounts/show_entry', [
            'pageTitle' => "ใบสำคัญรายวัน: {$entry['entry_no']}",
            'entry'     => $entry,
            'lines'     => $lines
        ]);
    }

    public function chart(): void
    {
        $this->checkPermission('accounts', 'view');
        $pdo = Database::getConnection();

        $category = trim((string)$this->request->query('category', ''));
        $sql = "SELECT * FROM fin_chart_of_accounts WHERE 1=1";
        $params = [];

        if ($category !== '') {
            $sql .= " AND account_category = ?";
            $params[] = $category;
        }
        $sql .= " ORDER BY account_code ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $accounts = $stmt->fetchAll();

        $this->render('accounts/chart', [
            'pageTitle' => 'ผังบัญชีมาตรฐานสถานศึกษา (Chart of Accounts)',
            'accounts'  => $accounts,
            'category'  => $category
        ]);
    }

    public function storeAccount(): void
    {
        $this->checkPermission('accounts', 'create');
        $pdo = Database::getConnection();

        $code = trim((string)$this->request->input('account_code'));
        $name = trim((string)$this->request->input('account_name'));
        $cat = (string)$this->request->input('account_category');
        $normal = (string)$this->request->input('normal_balance');

        if (empty($code) || empty($name)) {
            $this->setFlash('danger', 'กรุณาระบุรหัสบัญชีและชื่อบัญชี');
            $this->redirect('/accounts/chart');
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO fin_chart_of_accounts (account_code, account_name, account_category, normal_balance, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$code, $name, $cat, $normal]);

            $this->setFlash('success', "เพิ่มผังบัญชี '{$code} - {$name}' เรียบร้อยแล้ว");
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        $this->redirect('/accounts/chart');
    }

    public function ledger(string $accountId): void
    {
        $this->checkPermission('accounts', 'view');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $stmtAcc = $pdo->prepare("SELECT * FROM fin_chart_of_accounts WHERE id = ?");
        $stmtAcc->execute([(int)$accountId]);
        $account = $stmtAcc->fetch();

        if (!$account) {
            $this->setFlash('danger', 'ไม่พบบัญชีที่ระบุ');
            $this->redirect('/accounts/chart');
        }

        $stmtLines = $pdo->prepare("
            SELECT l.*, e.entry_no, e.entry_date, e.description as entry_desc
            FROM fin_journal_lines l
            JOIN fin_journal_entries e ON l.journal_entry_id = e.id
            WHERE l.account_id = ? AND e.fiscal_year_id = ? AND e.is_posted = 1 AND e.is_reversed = 0
            ORDER BY e.entry_date ASC, e.id ASC
        ");
        $stmtLines->execute([(int)$accountId, $fyId]);
        $lines = $stmtLines->fetchAll();

        // Calculate running balance
        $runningBalance = 0.00;
        $ledgerRows = [];
        foreach ($lines as $line) {
            $debit = (float)$line['debit_amount'];
            $credit = (float)$line['credit_amount'];

            if ($account['normal_balance'] === 'debit') {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $ledgerRows[] = array_merge($line, ['balance' => $runningBalance]);
        }

        $this->render('accounts/ledger', [
            'pageTitle' => "สมุดแยกประเภท: {$account['account_code']} {$account['account_name']}",
            'account'   => $account,
            'lines'     => $ledgerRows,
            'balance'   => $runningBalance
        ]);
    }

    public function trialBalance(): void
    {
        $this->checkPermission('accounts', 'view');
        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $periodId = $this->request->query('period_id') ? (int)$this->request->query('period_id') : null;
        $result = AccountingService::getTrialBalance($fyId, $periodId);

        $pdo = Database::getConnection();
        $periods = $pdo->prepare("SELECT id, period_name, period_number FROM fin_accounting_periods WHERE fiscal_year_id = ? ORDER BY period_number ASC");
        $periods->execute([$fyId]);
        $allPeriods = $periods->fetchAll();

        $this->render('accounts/trial_balance', [
            'pageTitle'    => 'งบทดลอง (Trial Balance)',
            'trialBalance' => $result,
            'periods'      => $allPeriods,
            'periodId'     => $periodId
        ]);
    }
}
