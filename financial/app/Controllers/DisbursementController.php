<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\DisbursementService;
use App\Services\AuthService;
use PDO;

/**
 * Class DisbursementController
 * จัดการกระบวนการเบิกจ่ายเงิน ใบสำคัญจ่าย (PV) และเวิร์กโฟลว์การอนุมัติ 6 ขั้นตอน
 */
class DisbursementController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('disbursements', 'view');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $status = trim((string)$this->request->query('status', ''));
        $search = trim((string)$this->request->query('search', ''));

        $sql = "
            SELECT 
                p.*,
                u.full_name as requester_name,
                b.budget_category,
                proj.project_name, proj.project_code,
                aud.full_name as auditor_name,
                app.full_name as approver_name
            FROM fin_payments p
            JOIN fin_users u ON p.requester_id = u.id
            JOIN fin_budgets b ON p.budget_id = b.id
            JOIN fin_projects proj ON b.project_id = proj.id
            LEFT JOIN fin_users aud ON p.audited_by = aud.id
            LEFT JOIN fin_users app ON p.approved_by = app.id
            WHERE p.fiscal_year_id = ?
        ";
        $params = [$fyId];

        if ($status !== '') {
            $sql .= " AND p.workflow_status = ?";
            $params[] = $status;
        }
        if ($search !== '') {
            $sql .= " AND (p.voucher_no LIKE ? OR p.payee_name LIKE ? OR proj.project_name LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }

        $sql .= " ORDER BY p.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();

        // Bank Accounts for payment modal
        $bankAccounts = $pdo->query("SELECT id, bank_name, account_no, account_no as account_number, account_name FROM fin_bank_accounts WHERE is_active = 1")->fetchAll();
        foreach ($bankAccounts as &$ba) {
            $ba['current_balance'] = $ba['current_balance'] ?? 0.00;
        }

        $this->render('disbursements/index', [
            'pageTitle'    => 'ระบบเบิกจ่ายเงินและใบสำคัญจ่าย',
            'payments'     => $payments,
            'bankAccounts' => $bankAccounts,
            'currentStatus'=> $status,
            'search'       => $search
        ]);
    }

    public function create(): void
    {
        $this->checkPermission('disbursements', 'create');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        // Available project budgets with remaining funds
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

        $bankAccounts = $pdo->query("SELECT * FROM fin_bank_accounts WHERE is_active = 1")->fetchAll();
        $expenseAccounts = $pdo->query("SELECT id, account_code, account_name FROM fin_chart_of_accounts WHERE account_category = 'expense' AND is_active = 1 ORDER BY account_code ASC")->fetchAll();

        $this->render('disbursements/form', [
            'pageTitle'        => 'สร้างคำขอเบิกจ่ายเงินใหม่',
            'availableBudgets' => $availableBudgets,
            'bankAccounts'     => $bankAccounts,
            'expenseAccounts'  => $expenseAccounts
        ]);
    }

    public function store(): void
    {
        $this->checkPermission('disbursements', 'create');

        $activeFy = AuthService::getActiveFiscalYear();
        $user = $this->user();

        $data = [
            'fiscal_year_id'          => (int)$activeFy['id'],
            'budget_id'               => (int)$this->request->input('budget_id'),
            'payment_date'            => $this->request->input('payment_date', date('Y-m-d')),
            'payee_name'              => (string)$this->request->input('payee_name'),
            'payee_tax_id'            => (string)$this->request->input('payee_tax_id'),
            'payment_method'          => (string)$this->request->input('payment_method', 'transfer'),
            'bank_account_id'         => $this->request->input('bank_account_id'),
            'cheque_no'               => (string)$this->request->input('cheque_no'),
            'withholding_tax_percent' => (float)$this->request->input('withholding_tax_percent', 0.00)
        ];

        $descriptions = (array)$this->request->input('item_description', []);
        $quantities = (array)$this->request->input('item_quantity', []);
        $unitPrices = (array)$this->request->input('item_unit_price', []);
        $accounts = (array)$this->request->input('item_account_id', []);

        $items = [];
        for ($i = 0; $i < count($descriptions); $i++) {
            if (!empty($descriptions[$i])) {
                $qty = (float)($quantities[$i] ?? 1);
                $price = (float)($unitPrices[$i] ?? 0);
                if ($price > 0) {
                    $items[] = [
                        'description' => $descriptions[$i],
                        'quantity'    => $qty,
                        'unit_price'  => $price,
                        'amount'      => $qty * $price,
                        'account_id'  => !empty($accounts[$i]) ? (int)$accounts[$i] : null
                    ];
                }
            }
        }

        if (empty($items)) {
            $this->setFlash('danger', 'กรุณาระบุรายการขอเบิกจ่ายอย่างน้อย 1 รายการ');
            $this->redirect('/disbursements/create');
        }

        try {
            $paymentId = DisbursementService::createDraft($data, $items, (int)$user['id']);
            $this->setFlash('success', 'บันทึกคำขอเบิกจ่ายร่างเรียบร้อยแล้ว');
            $this->redirect("/disbursements/show/{$paymentId}");
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
            $this->redirect('/disbursements/create');
        }
    }

    public function show(string $id): void
    {
        $this->checkPermission('disbursements', 'view');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                u.full_name as requester_name, u.email as requester_email,
                b.budget_category, b.net_amount as budget_net,
                proj.project_code, proj.project_name, proj.department,
                fs.source_name,
                aud.full_name as auditor_name,
                app.full_name as approver_name,
                dis.full_name as disburser_name,
                ba.bank_name, ba.account_no, ba.account_no as account_number, ba.account_name
            FROM fin_payments p
            JOIN fin_users u ON p.requester_id = u.id
            JOIN fin_budgets b ON p.budget_id = b.id
            JOIN fin_projects proj ON b.project_id = proj.id
            JOIN fin_funding_sources fs ON b.funding_source_id = fs.id
            LEFT JOIN fin_users aud ON p.audited_by = aud.id
            LEFT JOIN fin_users app ON p.approved_by = app.id
            LEFT JOIN fin_users dis ON p.disbursed_by = dis.id
            LEFT JOIN fin_bank_accounts ba ON p.bank_account_id = ba.id
            WHERE p.id = ?
        ");
        $stmt->execute([(int)$id]);
        $payment = $stmt->fetch();

        if (!$payment) {
            $this->setFlash('danger', 'ไม่พบใบสำคัญจ่ายที่ระบุ');
            $this->redirect('/disbursements');
        }

        $stmtItems = $pdo->prepare("SELECT * FROM fin_payment_items WHERE payment_id = ?");
        $stmtItems->execute([(int)$id]);
        $items = $stmtItems->fetchAll();

        // Bank Accounts for disburse modal
        $bankAccounts = $pdo->query("SELECT id, bank_name, account_no, account_no as account_number, account_name FROM fin_bank_accounts WHERE is_active = 1")->fetchAll();
        foreach ($bankAccounts as &$ba) {
            $ba['current_balance'] = $ba['current_balance'] ?? 0.00;
        }

        $this->render('disbursements/show', [
            'pageTitle'    => "ใบสำคัญจ่าย: {$payment['voucher_no']}",
            'payment'      => $payment,
            'items'        => $items,
            'bankAccounts' => $bankAccounts
        ]);
    }

    public function submit(): void
    {
        $this->checkPermission('disbursements', 'propose');
        $id = (int)$this->request->input('payment_id');
        $user = $this->user();

        $res = DisbursementService::submit($id, (int)$user['id']);
        $this->setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $this->redirect("/disbursements/show/{$id}");
    }

    public function audit(): void
    {
        $this->checkPermission('disbursements', 'audit');
        $id = (int)$this->request->input('payment_id');
        $user = $this->user();

        $res = DisbursementService::audit($id, (int)$user['id']);
        $this->setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $this->redirect("/disbursements/show/{$id}");
    }

    public function approve(): void
    {
        $this->checkPermission('disbursements', 'approve');
        $id = (int)$this->request->input('payment_id');
        $user = $this->user();

        $res = DisbursementService::approve($id, (int)$user['id']);
        $this->setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $this->redirect("/disbursements/show/{$id}");
    }

    public function disburse(): void
    {
        $this->checkPermission('disbursements', 'disburse');
        $id = (int)$this->request->input('payment_id');
        $user = $this->user();

        $payData = [
            'payment_method'  => (string)$this->request->input('payment_method'),
            'bank_account_id' => (int)$this->request->input('bank_account_id'),
            'cheque_no'       => (string)$this->request->input('cheque_no')
        ];

        $res = DisbursementService::disburse($id, $payData, (int)$user['id']);
        $this->setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $this->redirect("/disbursements/show/{$id}");
    }

    public function reject(): void
    {
        $this->checkPermission('disbursements', 'reject');
        $id = (int)$this->request->input('payment_id');
        $reason = trim((string)$this->request->input('rejection_reason'));
        $user = $this->user();

        if (empty($reason)) {
            $this->setFlash('danger', 'กรุณาระบุเหตุผลในการส่งกลับ/ปฏิเสธคำขอ');
            $this->redirect("/disbursements/show/{$id}");
        }

        $res = DisbursementService::reject($id, $reason, (int)$user['id']);
        $this->setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $this->redirect("/disbursements/show/{$id}");
    }

    public function printPhong11(string $id): void
    {
        $this->checkPermission('disbursements', 'view');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                u.full_name as requester_name, u.email as requester_email,
                b.budget_category, b.net_amount as budget_net, b.paid_amount as budget_paid, b.committed_amount as budget_committed,
                proj.project_code, proj.project_name, proj.activity_name, proj.department, proj.responsible_person,
                fs.source_name,
                aud.full_name as auditor_name,
                app.full_name as approver_name,
                dis.full_name as disburser_name
            FROM fin_payments p
            JOIN fin_users u ON p.requester_id = u.id
            JOIN fin_budgets b ON p.budget_id = b.id
            JOIN fin_projects proj ON b.project_id = proj.id
            JOIN fin_funding_sources fs ON b.funding_source_id = fs.id
            LEFT JOIN fin_users aud ON p.audited_by = aud.id
            LEFT JOIN fin_users app ON p.approved_by = app.id
            LEFT JOIN fin_users dis ON p.disbursed_by = dis.id
            WHERE p.id = ?
        ");
        $stmt->execute([(int)$id]);
        $payment = $stmt->fetch();

        if (!$payment) {
            $this->setFlash('danger', 'ไม่พบใบสำคัญจ่ายที่ระบุ');
            $this->redirect('/disbursements');
        }

        $stmtItems = $pdo->prepare("SELECT * FROM fin_payment_items WHERE payment_id = ?");
        $stmtItems->execute([(int)$id]);
        $rawItems = $stmtItems->fetchAll();

        $items = [];
        foreach ($rawItems as $it) {
            $items[] = [
                'item_name'  => $it['description'],
                'quantity'   => (float)$it['quantity'],
                'unit'       => 'รายการ',
                'unit_price' => (float)$it['unit_price'],
                'amount'     => (float)$it['amount']
            ];
        }

        $spentPrev = (float)($payment['budget_paid'] ?? 0) - (float)($payment['net_amount'] ?? 0);
        if ($spentPrev < 0) {
            $spentPrev = 0;
        }

        $doc = [
            'doc_no'               => $payment['voucher_no'] ?? '     /2570',
            'doc_date'             => thai_date($payment['payment_date'] ?? date('Y-m-d'), false, true),
            'school_name'          => 'โรงเรียนละลมวิทยา',
            'affiliation'          => 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร',
            'fiscal_year_be'       => '2570',
            'department'           => $payment['department'] ?? 'ฝ่ายบริหารงานวิชาการ',
            'work_section'         => $payment['department'] ?? 'งานตามแผนปฏิบัติราชการ',
            'requester_name'       => $payment['requester_name'] ?? '...................................................',
            'requester_position'   => 'ครู',
            'project_name'         => $payment['project_name'] ?? '',
            'activity_name'        => !empty($payment['activity_name']) ? $payment['activity_name'] : $payment['project_name'],
            'objective'            => $payment['description'] ?: 'เพื่อใช้ในการดำเนินกิจกรรมตามแผนปฏิบัติราชการ',
            'inspector_name'       => $payment['auditor_name'] ?? 'นางชลลัดดา มากนวล',
            'budget_allocated'     => (float)$payment['budget_net'],
            'budget_spent_prev'    => $spentPrev,
            'total_amount'         => (float)$payment['net_amount'],
            'total_amount_text'    => baht_text((float)$payment['net_amount']),
            'activity_leader_name' => $payment['responsible_person'] ?? $payment['requester_name'],
            'budget_head_name'     => 'นางชลลัดดา มากนวล',
            'plan_head_name'       => 'นางชลลัดดา มากนวล',
            'procurement_head_name'=> 'นางบานเย็น ภูรักษา',
            'finance_head_name'    => 'นางรัตนา หงษ์โสภา',
            'director_name'        => 'นายสถาน ปรางมาศ',
            'items'                => $items,
            'specs'                => []
        ];

        $viewPath = 'C:/xampp/htdocs/llw/financial/app/Views/documents/phong11.php';
        extract(['doc' => $doc]);
        require $viewPath;
        exit;
    }
}
