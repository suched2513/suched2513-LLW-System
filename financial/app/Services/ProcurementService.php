<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Class ProcurementService
 * จัดการกระบวนการจัดซื้อจัดจ้าง: ทะเบียนผู้ค้า, ขอซื้อขอจ้าง (PR), ใบสั่งซื้อ (PO), และการตรวจรับพัสดุ
 */
class ProcurementService
{
    public static function generatePrNo(?int $fyBe = null): string
    {
        $pdo = Database::getConnection();
        $fyBe = $fyBe ?? thai_fiscal_year();
        $pattern = "PR-{$fyBe}-%";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fin_purchase_requests WHERE pr_no LIKE ?");
        $stmt->execute([$pattern]);
        $seq = (int)$stmt->fetchColumn() + 1;

        return sprintf('PR-%d-%04d', $fyBe, $seq);
    }

    public static function generatePoNo(?int $fyBe = null): string
    {
        $pdo = Database::getConnection();
        $fyBe = $fyBe ?? thai_fiscal_year();
        $pattern = "PO-{$fyBe}-%";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fin_purchase_orders WHERE po_no LIKE ?");
        $stmt->execute([$pattern]);
        $seq = (int)$stmt->fetchColumn() + 1;

        return sprintf('PO-%d-%04d', $fyBe, $seq);
    }

    public static function generateInspectionNo(?int $fyBe = null): string
    {
        $pdo = Database::getConnection();
        $fyBe = $fyBe ?? thai_fiscal_year();
        $pattern = "INSP-{$fyBe}-%";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM fin_inspections WHERE inspection_no LIKE ?");
        $stmt->execute([$pattern]);
        $seq = (int)$stmt->fetchColumn() + 1;

        return sprintf('INSP-%d-%04d', $fyBe, $seq);
    }

    public static function getSuppliers(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM fin_suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    }

    public static function createSupplier(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO fin_suppliers (tax_id, name, contact_person, phone, address, bank_name, bank_account_no, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            trim($data['tax_id']),
            trim($data['name']),
            trim($data['contact_person'] ?? ''),
            trim($data['phone'] ?? ''),
            trim($data['address'] ?? ''),
            trim($data['bank_name'] ?? ''),
            trim($data['bank_account_no'] ?? '')
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function createPR(array $data, int $userId): int
    {
        $pdo = Database::getConnection();

        $fyId = (int)$data['fiscal_year_id'];
        $budgetId = (int)$data['budget_id'];
        $subject = trim($data['subject']);
        $estimatedAmount = (float)$data['estimated_amount'];
        $method = $data['procurement_method'] ?? 'specific';
        $requestDate = $data['request_date'] ?? date('Y-m-d');

        if (empty($subject) || $estimatedAmount <= 0) {
            throw new Exception('กรุณาระบุเรื่องขอซื้อขอจ้างและวงเงินงบประมาณ');
        }

        // ตรวจสอบงบประมาณ
        $avail = BudgetService::checkAvailability($budgetId, $estimatedAmount);
        if (!$avail['allowed']) {
            throw new Exception("ไม่สามารถขอซื้อขอจ้างได้: " . $avail['message']);
        }

        $prNo = self::generatePrNo();

        $stmt = $pdo->prepare("
            INSERT INTO fin_purchase_requests 
            (pr_no, request_date, fiscal_year_id, budget_id, procurement_method, subject, estimated_amount, status, requester_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?, NOW())
        ");
        $stmt->execute([$prNo, $requestDate, $fyId, $budgetId, $method, $subject, $estimatedAmount, $userId]);
        $prId = (int)$pdo->lastInsertId();

        AuditTrailService::log('procurement', 'create_pr', 'fin_purchase_requests', $prId, null, ['pr_no' => $prNo, 'amount' => $estimatedAmount], "สร้างใบขอซื้อขอจ้าง {$prNo}");
        return $prId;
    }

    public static function approvePR(int $prId, int $userId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_purchase_requests WHERE id = ?");
        $stmt->execute([$prId]);
        $pr = $stmt->fetch();

        if (!$pr) return false;

        // ผูกพันงบประมาณ
        BudgetService::holdCommitment((int)$pr['budget_id'], 'purchase_request', $prId, (float)$pr['estimated_amount']);

        $pdo->prepare("UPDATE fin_purchase_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
            ->execute([$userId, $prId]);

        AuditTrailService::log('procurement', 'approve_pr', 'fin_purchase_requests', $prId, ['status' => 'draft'], ['status' => 'approved'], "อนุมัติใบขอซื้อขอจ้าง {$pr['pr_no']}");
        return true;
    }

    public static function createPO(array $data, int $userId): int
    {
        $pdo = Database::getConnection();
        $prId = (int)$data['pr_id'];
        $supplierId = (int)$data['supplier_id'];
        $totalAmount = (float)$data['total_amount'];
        $deliveryDate = $data['delivery_due_date'] ?? date('Y-m-d', strtotime('+15 days'));
        $contractNo = trim($data['contract_no'] ?? '');

        $poNo = self::generatePoNo();

        $stmt = $pdo->prepare("
            INSERT INTO fin_purchase_orders (po_no, po_date, pr_id, supplier_id, contract_no, total_amount, delivery_due_date, status)
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$poNo, $prId, $supplierId, $contractNo, $totalAmount, $deliveryDate]);
        $poId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE fin_purchase_requests SET status = 'ordered' WHERE id = ?")->execute([$prId]);

        AuditTrailService::log('procurement', 'create_po', 'fin_purchase_orders', $poId, null, ['po_no' => $poNo, 'amount' => $totalAmount], "ออกใบสั่งซื้อ/สั่งจ้าง {$poNo}");
        return $poId;
    }

    public static function inspectPO(int $poId, string $result, string $remarks, int $userId): int
    {
        $pdo = Database::getConnection();
        $inspectionNo = self::generateInspectionNo();

        $stmt = $pdo->prepare("
            INSERT INTO fin_inspections (inspection_no, inspection_date, po_id, result, inspector_user_id, remarks)
            VALUES (?, CURDATE(), ?, ?, ?, ?)
        ");
        $stmt->execute([$inspectionNo, $poId, $result, $userId, $remarks]);
        $inspId = (int)$pdo->lastInsertId();

        if ($result === 'accepted') {
            $pdo->prepare("UPDATE fin_purchase_orders SET status = 'completed' WHERE id = ?")->execute([$poId]);
        }

        AuditTrailService::log('procurement', 'inspect', 'fin_inspections', $inspId, null, ['po_id' => $poId, 'result' => $result], "ตรวจรับพัสดุ {$inspectionNo}");
        return $inspId;
    }
}
