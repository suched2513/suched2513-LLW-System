<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuthService;
use App\Services\AuditTrailService;
use PDO;

/**
 * Class FundingSourceController
 * จัดการประเภทการเงินและแหล่งเงินสถานศึกษา (Funding Sources / Financial Categories)
 */
class FundingSourceController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('budgets', 'view');
        $pdo = Database::getConnection();

        // 1. ดึงรายการประเภทการเงินทั้งหมด
        $sources = $pdo->query("
            SELECT fs.*,
                   (SELECT COUNT(*) FROM fin_budgets b WHERE b.funding_source_id = fs.id) AS budget_count,
                   (SELECT COALESCE(SUM(b.initial_amount), 0) FROM fin_budgets b WHERE b.funding_source_id = fs.id) AS total_budget,
                   (SELECT COUNT(*) FROM fin_receipts r WHERE r.funding_source_id = fs.id) AS receipt_count,
                   (SELECT COALESCE(SUM(r.total_amount), 0) FROM fin_receipts r WHERE r.funding_source_id = fs.id) AS total_received,
                   (SELECT COUNT(*) FROM fin_bank_accounts ba WHERE ba.funding_source_id = fs.id) AS bank_count
            FROM fin_funding_sources fs
            ORDER BY fs.id ASC
        ")->fetchAll();

        // 2. สรุปยอดตามประเภทหลัก
        $typeLabels = [
            'subsidy'     => 'เงินอุดหนุน (รัฐบาล/สพฐ./กสศ.)',
            'revenue'     => 'เงินรายได้สถานศึกษา',
            'donation'    => 'เงินบริจาค',
            'tuition_fee' => 'ค่าบำรุงการศึกษา',
            'other'       => 'เงินสนับสนุนอื่นๆ',
        ];

        $countsByType = [];
        foreach ($sources as $s) {
            $t = $s['source_type'];
            if (!isset($countsByType[$t])) {
                $countsByType[$t] = 0;
            }
            $countsByType[$t]++;
        }

        $this->render('funding_sources/index', [
            'pageTitle'    => 'ประเภทการเงินและแหล่งเงินสถานศึกษา',
            'sources'      => $sources,
            'typeLabels'   => $typeLabels,
            'countsByType' => $countsByType
        ]);
    }

    public function store(): void
    {
        $this->checkPermission('budgets', 'create');
        $pdo = Database::getConnection();

        $sourceCode = trim((string)$this->request->input('source_code'));
        $sourceName = trim((string)$this->request->input('source_name'));
        $sourceType = trim((string)$this->request->input('source_type'));

        if (empty($sourceCode) || empty($sourceName) || empty($sourceType)) {
            $this->setFlash('danger', 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง');
            $this->redirect('/funding-sources');
            return;
        }

        $validTypes = ['subsidy', 'revenue', 'donation', 'tuition_fee', 'other'];
        if (!in_array($sourceType, $validTypes, true)) {
            $this->setFlash('danger', 'หมวดหมู่ประเภทการเงินไม่ถูกต้อง');
            $this->redirect('/funding-sources');
            return;
        }

        // ตรวจสอบรหัสซ้ำ
        $checkStmt = $pdo->prepare("SELECT id FROM fin_funding_sources WHERE source_code = ? LIMIT 1");
        $checkStmt->execute([$sourceCode]);
        if ($checkStmt->fetch()) {
            $this->setFlash('danger', "รหัสประเภทการเงิน '{$sourceCode}' มีอยู่ในระบบแล้ว");
            $this->redirect('/funding-sources');
            return;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO fin_funding_sources (source_code, source_name, source_type, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$sourceCode, $sourceName, $sourceType]);
            $newId = (int)$pdo->lastInsertId();

            AuditTrailService::log(
                $this->currentUserId(),
                'create',
                'fin_funding_sources',
                $newId,
                null,
                ['source_code' => $sourceCode, 'source_name' => $sourceName, 'source_type' => $sourceType],
                "เพิ่มประเภทการเงินใหม่: {$sourceName} ({$sourceCode})"
            );

            $this->setFlash('success', "เพิ่มประเภทการเงิน '{$sourceName}' เรียบร้อยแล้ว");
        } catch (\Throwable $e) {
            $this->setFlash('danger', "เกิดข้อผิดพลาด: " . $e->getMessage());
        }

        $this->redirect('/funding-sources');
    }

    public function update(string $id): void
    {
        $this->checkPermission('budgets', 'edit');
        $pdo = Database::getConnection();
        $sourceId = (int)$id;

        $sourceName = trim((string)$this->request->input('source_name'));
        $sourceType = trim((string)$this->request->input('source_type'));

        if (empty($sourceName) || empty($sourceType)) {
            $this->setFlash('danger', 'กรุณากรอกข้อมูลให้ครบถ้วน');
            $this->redirect('/funding-sources');
            return;
        }

        $validTypes = ['subsidy', 'revenue', 'donation', 'tuition_fee', 'other'];
        if (!in_array($sourceType, $validTypes, true)) {
            $this->setFlash('danger', 'หมวดหมู่ประเภทการเงินไม่ถูกต้อง');
            $this->redirect('/funding-sources');
            return;
        }

        $old = $pdo->query("SELECT * FROM fin_funding_sources WHERE id = {$sourceId}")->fetch();
        if (!$old) {
            $this->setFlash('danger', 'ไม่พบข้อมูลประเภทการเงินที่ต้องการแก้ไข');
            $this->redirect('/funding-sources');
            return;
        }

        try {
            $stmt = $pdo->prepare("UPDATE fin_funding_sources SET source_name = ?, source_type = ? WHERE id = ?");
            $stmt->execute([$sourceName, $sourceType, $sourceId]);

            AuditTrailService::log(
                $this->currentUserId(),
                'edit',
                'fin_funding_sources',
                $sourceId,
                $old,
                ['source_name' => $sourceName, 'source_type' => $sourceType],
                "แก้ไขประเภทการเงิน: {$sourceName}"
            );

            $this->setFlash('success', "แก้ไขประเภทการเงิน '{$sourceName}' เรียบร้อยแล้ว");
        } catch (\Throwable $e) {
            $this->setFlash('danger', "เกิดข้อผิดพลาด: " . $e->getMessage());
        }

        $this->redirect('/funding-sources');
    }

    public function toggle(string $id): void
    {
        $this->checkPermission('budgets', 'edit');
        $pdo = Database::getConnection();
        $sourceId = (int)$id;

        $old = $pdo->query("SELECT * FROM fin_funding_sources WHERE id = {$sourceId}")->fetch();
        if (!$old) {
            $this->setFlash('danger', 'ไม่พบข้อมูลประเภทการเงิน');
            $this->redirect('/funding-sources');
            return;
        }

        $newActive = $old['is_active'] ? 0 : 1;
        $statusText = $newActive ? 'เปิดใช้งาน' : 'ระงับการใช้งาน';

        try {
            $stmt = $pdo->prepare("UPDATE fin_funding_sources SET is_active = ? WHERE id = ?");
            $stmt->execute([$newActive, $sourceId]);

            AuditTrailService::log(
                $this->currentUserId(),
                'edit',
                'fin_funding_sources',
                $sourceId,
                $old,
                ['is_active' => $newActive],
                "เปลี่ยนสถานะประเภทการเงิน {$old['source_name']} เป็น {$statusText}"
            );

            $this->setFlash('success', "เปลี่ยนสถานะประเภทการเงิน '{$old['source_name']}' เป็น {$statusText} เรียบร้อยแล้ว");
        } catch (\Throwable $e) {
            $this->setFlash('danger', "เกิดข้อผิดพลาด: " . $e->getMessage());
        }

        $this->redirect('/funding-sources');
    }
}
