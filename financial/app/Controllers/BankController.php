<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\BankService;
use App\Services\AuthService;
use PDO;

/**
 * Class BankController
 * จัดการบัญชีเงินฝากธนาคาร สเตทเมนต์ และการทำงบพิสูจน์ยอดกระทบยอดเงินฝากธนาคาร (Bank Reconciliation)
 */
class BankController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('bank_reconciliation', 'view');
        $accounts = BankService::getAccounts();

        $pdo = Database::getConnection();
        $fundingSources = $pdo->query("SELECT * FROM fin_funding_sources WHERE is_active = 1")->fetchAll();

        $this->render('banks/index', [
            'pageTitle'      => 'บัญชีเงินฝากธนาคารและการกระทบยอด',
            'accounts'       => $accounts,
            'fundingSources' => $fundingSources
        ]);
    }

    public function store(): void
    {
        $this->checkPermission('bank_reconciliation', 'create');

        $data = [
            'bank_name'         => $this->request->input('bank_name'),
            'branch_name'       => $this->request->input('branch_name'),
            'account_no'        => $this->request->input('account_no'),
            'account_name'      => $this->request->input('account_name'),
            'account_type'      => $this->request->input('account_type', 'savings'),
            'initial_balance'   => (float)$this->request->input('initial_balance', 0),
            'funding_source_id' => $this->request->input('funding_source_id')
        ];

        if (empty($data['bank_name']) || empty($data['account_no']) || empty($data['account_name'])) {
            $this->setFlash('danger', 'กรุณากรอกข้อมูลบัญชีธนาคารให้ครบถ้วน');
            $this->redirect('/banks');
        }

        try {
            BankService::createAccount($data);
            $this->setFlash('success', 'เพิ่มบัญชีเงินฝากธนาคารเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        $this->redirect('/banks');
    }

    public function ledger(string $id): void
    {
        $this->checkPermission('bank_reconciliation', 'view');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM fin_bank_accounts WHERE id = ?");
        $stmt->execute([(int)$id]);
        $account = $stmt->fetch();

        if (!$account) {
            $this->setFlash('danger', 'ไม่พบบัญชีธนาคาร');
            $this->redirect('/banks');
        }

        // Transactions related to this bank account (Receipts and Payments)
        $stmtTrans = $pdo->prepare("
            SELECT 'receipt' as trans_type, r.receipt_date as trans_date, r.receipt_no as ref_no, r.payer_name as party_name, r.total_amount as amount, 'deposit' as direction
            FROM fin_receipts r
            WHERE r.bank_account_id = ? AND r.status IN ('issued', 'deposited')
            UNION ALL
            SELECT 'payment' as trans_type, p.payment_date as trans_date, p.voucher_no as ref_no, p.payee_name as party_name, p.net_amount as amount, 'withdrawal' as direction
            FROM fin_payments p
            WHERE p.bank_account_id = ? AND p.workflow_status IN ('disbursed', 'posted')
            ORDER BY trans_date DESC LIMIT 200
        ");
        $stmtTrans->execute([(int)$id, (int)$id]);
        $transactions = $stmtTrans->fetchAll();

        $this->render('banks/ledger', [
            'pageTitle'    => "ความเคลื่อนไหวบัญชี: {$account['bank_name']} ({$account['account_no']})",
            'account'      => $account,
            'transactions' => $transactions
        ]);
    }

    public function statement(string $id): void
    {
        $this->checkPermission('bank_reconciliation', 'view');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM fin_bank_accounts WHERE id = ?");
        $stmt->execute([(int)$id]);
        $account = $stmt->fetch();

        if (!$account) {
            $this->setFlash('danger', 'ไม่พบบัญชีธนาคาร');
            $this->redirect('/banks');
        }

        // Latest statement import
        $stmtImp = $pdo->prepare("SELECT * FROM fin_statement_imports WHERE bank_account_id = ? ORDER BY id DESC LIMIT 1");
        $stmtImp->execute([(int)$id]);
        $import = $stmtImp->fetch();

        $lines = [];
        if ($import) {
            $stmtLines = $pdo->prepare("SELECT * FROM fin_statement_lines WHERE statement_import_id = ? ORDER BY line_date ASC, id ASC");
            $stmtLines->execute([$import['id']]);
            $lines = $stmtLines->fetchAll();
        }

        $this->render('banks/statement', [
            'pageTitle' => "สเตทเมนต์ธนาคาร: {$account['bank_name']}",
            'account'   => $account,
            'import'    => $import,
            'lines'     => $lines
        ]);
    }

    public function importStatement(string $id): void
    {
        $this->checkPermission('bank_reconciliation', 'edit');

        $month = $this->request->input('statement_month', date('Y-m'));
        $file = $this->request->file('statement_file');
        $user = $this->user();

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->setFlash('danger', 'กรุณาเลือกไฟล์สเตทเมนต์ CSV ที่ถูกต้อง');
            $this->redirect("/banks/statement/{$id}");
        }

        $res = BankService::importStatementCsv((int)$id, $month, $file, (int)$user['id']);
        if ($res['success']) {
            $this->setFlash('success', "นำเข้าสเตทเมนต์สำเร็จ ({$res['count']} รายการ)");
        } else {
            $this->setFlash('danger', 'ไม่สามารถนำเข้าได้: ' . ($res['message'] ?? ''));
        }

        $this->redirect("/banks/statement/{$id}");
    }

    public function reconcile(string $id): void
    {
        $this->checkPermission('bank_reconciliation', 'view');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM fin_bank_accounts WHERE id = ?");
        $stmt->execute([(int)$id]);
        $account = $stmt->fetch();

        if (!$account) {
            $this->setFlash('danger', 'ไม่พบบัญชีธนาคาร');
            $this->redirect('/banks');
        }

        $asOfDate = $this->request->query('as_of_date', date('Y-m-d'));
        $statementBalance = (float)$this->request->query('statement_balance', $account['current_balance']);
        $user = $this->user();

        $reconciliation = BankService::reconcile((int)$id, $asOfDate, $statementBalance, (int)$user['id']);

        $this->render('banks/reconcile', [
            'pageTitle'      => "งบพิสูจน์ยอดเงินฝากธนาคาร: {$account['bank_name']}",
            'account'        => $account,
            'reconciliation' => $reconciliation,
            'asOfDate'       => $asOfDate,
            'statementBal'   => $statementBalance
        ]);
    }
}
