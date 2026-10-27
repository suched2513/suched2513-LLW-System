<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\ReceiptService;
use App\Services\AuthService;
use PDO;

/**
 * Class ReceiptController
 * จัดการระบบรับเงิน ออกใบเสร็จ นำฝากธนาคาร และพิมพ์ใบเสร็จรับเงิน
 */
class ReceiptController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('receipts', 'view');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $search = trim((string)$this->request->query('search', ''));
        $status = trim((string)$this->request->query('status', ''));
        $method = trim((string)$this->request->query('payment_method', ''));
        $dateFrom = trim((string)$this->request->query('date_from', ''));
        $dateTo = trim((string)$this->request->query('date_to', ''));

        $sql = "
            SELECT r.*, fs.source_name, u.full_name as creator_name, ba.bank_name, ba.account_no, ba.account_no as account_number
            FROM fin_receipts r
            JOIN fin_funding_sources fs ON r.funding_source_id = fs.id
            LEFT JOIN fin_users u ON r.created_by = u.id
            LEFT JOIN fin_bank_accounts ba ON r.bank_account_id = ba.id
            WHERE r.fiscal_year_id = ?
        ";
        $params = [$fyId];

        if ($search !== '') {
            $sql .= " AND (r.receipt_no LIKE ? OR r.payer_name LIKE ? OR r.bank_ref_no LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if ($status !== '') {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        if ($method !== '') {
            $sql .= " AND r.payment_method = ?";
            $params[] = $method;
        }
        if ($dateFrom !== '') {
            $sql .= " AND r.receipt_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= " AND r.receipt_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY r.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $receipts = $stmt->fetchAll();

        // Cash receipts available for deposit
        $cashForDeposit = $pdo->prepare("SELECT * FROM fin_receipts WHERE fiscal_year_id = ? AND payment_method = 'cash' AND status = 'issued'");
        $cashForDeposit->execute([$fyId]);
        $undepositedCash = $cashForDeposit->fetchAll();

        $bankAccounts = $pdo->query("SELECT id, bank_name, account_no, account_no as account_number, account_name FROM fin_bank_accounts WHERE is_active = 1")->fetchAll();

        $this->render('receipts/index', [
            'pageTitle'       => 'ระบบรับเงินและออกใบเสร็จ',
            'receipts'        => $receipts,
            'undepositedCash' => $undepositedCash,
            'bankAccounts'    => $bankAccounts,
            'filter'          => [
                'search'         => $search,
                'status'         => $status,
                'payment_method' => $method,
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo
            ]
        ]);
    }

    public function create(): void
    {
        $this->checkPermission('receipts', 'create');
        $pdo = Database::getConnection();

        $fundingSources = $pdo->query("SELECT * FROM fin_funding_sources WHERE is_active = 1")->fetchAll();
        $bankAccounts = $pdo->query("SELECT * FROM fin_bank_accounts WHERE is_active = 1")->fetchAll();
        $revenueAccounts = $pdo->query("SELECT id, account_code, account_name FROM fin_chart_of_accounts WHERE account_category = 'revenue' AND is_active = 1 ORDER BY account_code ASC")->fetchAll();

        $this->render('receipts/form', [
            'pageTitle'       => 'ออกใบเสร็จรับเงินใหม่',
            'fundingSources'  => $fundingSources,
            'bankAccounts'    => $bankAccounts,
            'revenueAccounts' => $revenueAccounts
        ]);
    }

    public function store(): void
    {
        $this->checkPermission('receipts', 'create');

        $activeFy = AuthService::getActiveFiscalYear();
        $user = $this->user();

        $data = [
            'fiscal_year_id'    => (int)$activeFy['id'],
            'receipt_date'      => $this->request->input('receipt_date', date('Y-m-d')),
            'funding_source_id' => (int)$this->request->input('funding_source_id'),
            'payer_name'        => (string)$this->request->input('payer_name'),
            'payer_tax_id'      => (string)$this->request->input('payer_tax_id'),
            'payment_method'    => (string)$this->request->input('payment_method'),
            'bank_account_id'   => $this->request->input('bank_account_id'),
            'bank_ref_no'       => (string)$this->request->input('bank_ref_no')
        ];

        // Format items from POST arrays
        $descriptions = (array)$this->request->input('item_description', []);
        $amounts = (array)$this->request->input('item_amount', []);
        $accounts = (array)$this->request->input('item_account_id', []);

        $items = [];
        for ($i = 0; $i < count($descriptions); $i++) {
            if (!empty($descriptions[$i]) && (float)$amounts[$i] > 0) {
                $items[] = [
                    'description' => $descriptions[$i],
                    'amount'      => (float)$amounts[$i],
                    'account_id'  => !empty($accounts[$i]) ? (int)$accounts[$i] : null
                ];
            }
        }

        if (empty($items)) {
            $this->setFlash('danger', 'กรุณาระบุรายการและจำนวนเงินอย่างน้อย 1 รายการ');
            $this->redirect('/receipts/create');
        }

        try {
            $receiptId = ReceiptService::createReceipt($data, $items, (int)$user['id']);
            $this->setFlash('success', 'ออกใบเสร็จรับเงินเรียบร้อยแล้ว');
            $this->redirect("/receipts/show/{$receiptId}");
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
            $this->redirect('/receipts/create');
        }
    }

    public function show(string $id): void
    {
        $this->checkPermission('receipts', 'view');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT r.*, fs.source_name, u.full_name as creator_name, ba.bank_name, ba.account_no, ba.account_no as account_number, ba.account_name
            FROM fin_receipts r
            JOIN fin_funding_sources fs ON r.funding_source_id = fs.id
            LEFT JOIN fin_users u ON r.created_by = u.id
            LEFT JOIN fin_bank_accounts ba ON r.bank_account_id = ba.id
            WHERE r.id = ?
        ");
        $stmt->execute([(int)$id]);
        $receipt = $stmt->fetch();

        if (!$receipt) {
            $this->setFlash('danger', 'ไม่พบใบเสร็จรับเงิน');
            $this->redirect('/receipts');
        }

        $stmtItems = $pdo->prepare("SELECT * FROM fin_receipt_items WHERE receipt_id = ?");
        $stmtItems->execute([(int)$id]);
        $items = $stmtItems->fetchAll();

        $this->render('receipts/show', [
            'pageTitle' => "ใบเสร็จรับเงิน: {$receipt['receipt_no']}",
            'receipt'   => $receipt,
            'items'     => $items
        ]);
    }

    public function cancel(): void
    {
        $this->checkPermission('receipts', 'cancel');

        $receiptId = (int)$this->request->input('receipt_id');
        $reason = trim((string)$this->request->input('cancellation_reason'));
        $user = $this->user();

        if (empty($reason)) {
            $this->setFlash('danger', 'กรุณาระบุเหตุผลในการยกเลิกใบเสร็จ');
            $this->redirect('/receipts');
        }

        $success = ReceiptService::cancelReceipt($receiptId, $reason, (int)$user['id']);
        if ($success) {
            $this->setFlash('success', 'ยกเลิกใบเสร็จรับเงินเรียบร้อยแล้ว');
        } else {
            $this->setFlash('danger', 'เกิดข้อผิดพลาดในการยกเลิกใบเสร็จ');
        }

        $this->redirect('/receipts');
    }

    public function deposit(): void
    {
        $this->checkPermission('receipts', 'edit');

        $receiptIds = (array)$this->request->input('receipt_ids', []);
        $bankAccountId = (int)$this->request->input('bank_account_id');
        $depositDate = $this->request->input('deposit_date', date('Y-m-d'));
        $user = $this->user();

        if (empty($receiptIds) || empty($bankAccountId)) {
            $this->setFlash('danger', 'กรุณาเลือกรายการใบเสร็จและบัญชีธนาคารปลายทาง');
            $this->redirect('/receipts');
        }

        $res = ReceiptService::depositCash($receiptIds, $bankAccountId, $depositDate, (int)$user['id']);
        if ($res['success']) {
            $this->setFlash('success', "นำฝากเงินสดเข้าธนาคารสำเร็จ รวม {$res['count']} ใบเสร็จ เป็นเงิน " . money_format_th($res['total_deposited']) . " บาท");
        } else {
            $this->setFlash('danger', 'ไม่สามารถนำฝากได้: ' . ($res['message'] ?? ''));
        }

        $this->redirect('/receipts');
    }
}
