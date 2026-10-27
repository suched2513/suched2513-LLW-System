<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AdvanceService;
use App\Services\AuthService;
use PDO;

/**
 * Class AdvanceController
 * จัดการสัญญายืมเงินราชการ (แบบ 8500) การจ่ายเงินยืม และการส่งหลักฐานล้างเงินยืม
 */
class AdvanceController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('advances', 'view');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $status = trim((string)$this->request->query('status', ''));
        $search = trim((string)$this->request->query('search', ''));

        // Check and mark overdue advances automatically
        $pdo->exec("UPDATE fin_advances SET status = 'overdue' WHERE status = 'disbursed' AND due_date < CURDATE()");

        $sql = "
            SELECT 
                a.*,
                u.full_name as borrower_name, u.email as borrower_email,
                b.budget_category,
                proj.project_name, proj.project_code
            FROM fin_advances a
            JOIN fin_users u ON a.borrower_id = u.id
            JOIN fin_budgets b ON a.budget_id = b.id
            JOIN fin_projects proj ON b.project_id = proj.id
            WHERE a.fiscal_year_id = ?
        ";
        $params = [$fyId];

        if ($status !== '') {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }
        if ($search !== '') {
            $sql .= " AND (a.contract_no LIKE ? OR u.full_name LIKE ? OR a.objective LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }

        $sql .= " ORDER BY a.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $advances = $stmt->fetchAll();

        $bankAccounts = $pdo->query("SELECT id, bank_name, account_no, account_no as account_number, account_name, current_balance FROM fin_bank_accounts WHERE is_active = 1")->fetchAll();

        $this->render('advances/index', [
            'pageTitle'     => 'ระบบเงินยืมราชการและล้างเงินยืม',
            'advances'      => $advances,
            'bankAccounts'  => $bankAccounts,
            'currentStatus' => $status,
            'search'        => $search
        ]);
    }

    public function create(): void
    {
        $this->checkPermission('advances', 'create');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        // Available project budgets
        $budgets = $pdo->prepare("
            SELECT b.id, b.budget_category, (b.net_amount - b.committed_amount - b.paid_amount) as remaining_amount,
                   p.project_code, p.project_name, fs.source_name
            FROM fin_budgets b
            JOIN fin_projects p ON b.project_id = p.id
            JOIN fin_funding_sources fs ON b.funding_source_id = fs.id
            WHERE b.fiscal_year_id = ? AND (b.net_amount - b.committed_amount - b.paid_amount) > 0
            ORDER BY p.project_code ASC
        ");
        $budgets->execute([$fyId]);
        $availableBudgets = $budgets->fetchAll();

        $users = $pdo->query("SELECT id, full_name FROM fin_users WHERE is_active = 1 ORDER BY full_name ASC")->fetchAll();

        $this->render('advances/form', [
            'pageTitle'        => 'เขียนคำขอยืมเงินและทำสัญญา (แบบ 8500)',
            'availableBudgets' => $availableBudgets,
            'users'            => $users
        ]);
    }

    public function store(): void
    {
        $this->checkPermission('advances', 'create');

        $activeFy = AuthService::getActiveFiscalYear();
        $user = $this->user();

        $data = [
            'fiscal_year_id' => (int)$activeFy['id'],
            'budget_id'      => (int)$this->request->input('budget_id'),
            'borrower_id'    => (int)$this->request->input('borrower_id', $user['id']),
            'contract_date'  => $this->request->input('contract_date', date('Y-m-d')),
            'objective'      => (string)$this->request->input('objective'),
            'amount'         => (float)$this->request->input('amount'),
            'due_date'       => $this->request->input('due_date', date('Y-m-d', strtotime('+30 days')))
        ];

        try {
            $advanceId = AdvanceService::createAdvance($data, (int)$user['id']);
            $this->setFlash('success', 'สร้างสัญญายืมเงินเรียบร้อยแล้ว');
            $this->redirect("/advances/show/{$advanceId}");
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
            $this->redirect('/advances/create');
        }
    }

    public function show(string $id): void
    {
        $this->checkPermission('advances', 'view');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                a.*,
                u.full_name as borrower_name, u.email as borrower_email,
                b.budget_category, b.net_amount as budget_net,
                proj.project_code, proj.project_name, proj.department,
                fs.source_name
            FROM fin_advances a
            JOIN fin_users u ON a.borrower_id = u.id
            JOIN fin_budgets b ON a.budget_id = b.id
            JOIN fin_projects proj ON b.project_id = proj.id
            JOIN fin_funding_sources fs ON b.funding_source_id = fs.id
            WHERE a.id = ?
        ");
        $stmt->execute([(int)$id]);
        $advance = $stmt->fetch();

        if (!$advance) {
            $this->setFlash('danger', 'ไม่พบสัญญายืมเงินที่ระบุ');
            $this->redirect('/advances');
        }

        // Clearings for this advance
        $stmtClr = $pdo->prepare("
            SELECT c.*, u.full_name as approver_name
            FROM fin_advance_clearings c
            LEFT JOIN fin_users u ON c.approved_by = u.id
            WHERE c.advance_id = ?
            ORDER BY c.id DESC
        ");
        $stmtClr->execute([(int)$id]);
        $clearings = $stmtClr->fetchAll();

        $bankAccounts = $pdo->query("SELECT id, bank_name, account_no, account_no as account_number, account_name, current_balance FROM fin_bank_accounts WHERE is_active = 1")->fetchAll();

        $this->render('advances/show', [
            'pageTitle'    => "สัญญายืมเงิน: {$advance['contract_no']}",
            'advance'      => $advance,
            'clearings'    => $clearings,
            'bankAccounts' => $bankAccounts
        ]);
    }

    public function approve(): void
    {
        $this->checkPermission('advances', 'approve');
        $id = (int)$this->request->input('advance_id');
        $user = $this->user();

        $res = AdvanceService::approveAdvance($id, (int)$user['id']);
        $this->setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $this->redirect("/advances/show/{$id}");
    }

    public function disburse(): void
    {
        $this->checkPermission('advances', 'disburse');
        $id = (int)$this->request->input('advance_id');
        $bankAccountId = (int)$this->request->input('bank_account_id');
        $user = $this->user();

        $res = AdvanceService::disburseAdvance($id, $bankAccountId, (int)$user['id']);
        $this->setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $this->redirect("/advances/show/{$id}");
    }

    public function submitClearing(): void
    {
        $this->checkPermission('advances', 'clear');
        $id = (int)$this->request->input('advance_id');
        $user = $this->user();

        $data = [
            'clearing_date'         => $this->request->input('clearing_date', date('Y-m-d')),
            'actual_expense_amount' => (float)$this->request->input('actual_expense_amount')
        ];

        $res = AdvanceService::submitClearing($id, $data, (int)$user['id']);
        $this->setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $this->redirect("/advances/show/{$id}");
    }

    public function approveClearing(): void
    {
        $this->checkPermission('advances', 'approve');
        $clearingId = (int)$this->request->input('clearing_id');
        $advanceId = (int)$this->request->input('advance_id');
        $user = $this->user();

        $res = AdvanceService::approveClearing($clearingId, (int)$user['id']);
        $this->setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $this->redirect("/advances/show/{$advanceId}");
    }
}
