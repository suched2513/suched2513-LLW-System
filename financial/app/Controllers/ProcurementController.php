<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\ProcurementService;
use App\Services\AuthService;
use PDO;

/**
 * Class ProcurementController
 * จัดการจัดซื้อจัดจ้าง ขอซื้อขอจ้าง (PR), สั่งซื้อสั่งจ้าง (PO), ตรวจรับพัสดุ และทะเบียนผู้ค้า
 */
class ProcurementController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('procurement', 'view');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        // Purchase Requests
        $stmtPr = $pdo->prepare("
            SELECT pr.*, u.full_name as requester_name, p.project_name, p.project_code
            FROM fin_purchase_requests pr
            JOIN fin_users u ON pr.requester_id = u.id
            JOIN fin_budgets b ON pr.budget_id = b.id
            JOIN fin_projects p ON b.project_id = p.id
            WHERE pr.fiscal_year_id = ?
            ORDER BY pr.id DESC
        ");
        $stmtPr->execute([$fyId]);
        $requests = $stmtPr->fetchAll();

        // Purchase Orders
        $stmtPo = $pdo->prepare("
            SELECT po.*, pr.pr_no, pr.subject, s.name as supplier_name, insp.result as inspection_result
            FROM fin_purchase_orders po
            JOIN fin_purchase_requests pr ON po.pr_id = pr.id
            JOIN fin_suppliers s ON po.supplier_id = s.id
            LEFT JOIN fin_inspections insp ON po.id = insp.po_id
            WHERE pr.fiscal_year_id = ?
            ORDER BY po.id DESC
        ");
        $stmtPo->execute([$fyId]);
        $orders = $stmtPo->fetchAll();

        // Suppliers
        $suppliers = ProcurementService::getSuppliers();

        // Budgets for PR creation
        $budgets = $pdo->prepare("
            SELECT b.id, (b.net_amount - b.committed_amount - b.paid_amount) as remaining,
                   p.project_code, p.project_name, b.budget_category
            FROM fin_budgets b
            JOIN fin_projects p ON b.project_id = p.id
            WHERE b.fiscal_year_id = ?
        ");
        $budgets->execute([$fyId]);
        $availableBudgets = $budgets->fetchAll();

        $this->render('procurement/index', [
            'pageTitle'        => 'ระบบจัดซื้อจัดจ้างและการตรวจรับพัสดุ',
            'requests'         => $requests,
            'orders'           => $orders,
            'suppliers'        => $suppliers,
            'availableBudgets' => $availableBudgets
        ]);
    }

    public function storePR(): void
    {
        $this->checkPermission('procurement', 'create');

        $activeFy = AuthService::getActiveFiscalYear();
        $user = $this->user();

        $data = [
            'fiscal_year_id'     => (int)$activeFy['id'],
            'budget_id'          => (int)$this->request->input('budget_id'),
            'subject'            => (string)$this->request->input('subject'),
            'estimated_amount'   => (float)$this->request->input('estimated_amount'),
            'procurement_method' => (string)$this->request->input('procurement_method', 'specific'),
            'request_date'       => $this->request->input('request_date', date('Y-m-d'))
        ];

        try {
            $prId = ProcurementService::createPR($data, (int)$user['id']);
            $this->setFlash('success', 'บันทึกใบขอซื้อขอจ้างเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        $this->redirect('/procurement');
    }

    public function approvePR(): void
    {
        $this->checkPermission('procurement', 'approve');
        $prId = (int)$this->request->input('pr_id');
        $user = $this->user();

        $success = ProcurementService::approvePR($prId, (int)$user['id']);
        if ($success) {
            $this->setFlash('success', 'อนุมัติใบขอซื้อขอจ้างและผูกพันงบประมาณเรียบร้อยแล้ว');
        } else {
            $this->setFlash('danger', 'เกิดข้อผิดพลาดในการอนุมัติ');
        }

        $this->redirect('/procurement');
    }

    public function storePO(): void
    {
        $this->checkPermission('procurement', 'create');
        $user = $this->user();

        $data = [
            'pr_id'             => (int)$this->request->input('pr_id'),
            'supplier_id'       => (int)$this->request->input('supplier_id'),
            'total_amount'      => (float)$this->request->input('total_amount'),
            'contract_no'       => (string)$this->request->input('contract_no'),
            'delivery_due_date' => $this->request->input('delivery_due_date')
        ];

        try {
            $poId = ProcurementService::createPO($data, (int)$user['id']);
            $this->setFlash('success', 'ออกใบสั่งซื้อ/สั่งจ้าง (PO) เรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        $this->redirect('/procurement');
    }

    public function storeInspection(): void
    {
        $this->checkPermission('procurement', 'audit');
        $user = $this->user();

        $poId = (int)$this->request->input('po_id');
        $result = (string)$this->request->input('result', 'accepted');
        $remarks = trim((string)$this->request->input('remarks', ''));

        try {
            ProcurementService::inspectPO($poId, $result, $remarks, (int)$user['id']);
            $this->setFlash('success', 'บันทึกรายงานผลการตรวจรับพัสดุเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        $this->redirect('/procurement');
    }

    public function storeSupplier(): void
    {
        $this->checkPermission('procurement', 'create');

        $data = [
            'tax_id'          => $this->request->input('tax_id'),
            'name'            => $this->request->input('name'),
            'contact_person'  => $this->request->input('contact_person'),
            'phone'           => $this->request->input('phone'),
            'address'         => $this->request->input('address'),
            'bank_name'       => $this->request->input('bank_name'),
            'bank_account_no' => $this->request->input('bank_account_no')
        ];

        try {
            ProcurementService::createSupplier($data);
            $this->setFlash('success', "เพิ่มผู้ค้า/คู่สัญญา '{$data['name']}' เรียบร้อยแล้ว");
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        $this->redirect('/procurement');
    }
}
