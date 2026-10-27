<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuthService;
use App\Services\AuditTrailService;
use PDO;

/**
 * Class FiscalYearController
 * จัดการปีงบประมาณไทย (พ.ศ.) และรอบระยะเวลาบัญชี 12 งวด (ตุลาคม - กันยายน)
 */
class FiscalYearController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('settings', 'view');
        $pdo = Database::getConnection();

        // ดึงรายการปีงบประมาณทั้งหมด
        $stmtYears = $pdo->query("SELECT * FROM fin_fiscal_years ORDER BY year_be DESC");
        $years = $stmtYears->fetchAll();

        // ดึงงวดบัญชีของปีปัจจุบัน
        $activeFy = AuthService::getActiveFiscalYear();
        $selectedYearId = (int)$this->request->query('year_id', $activeFy['id'] ?? ($years[0]['id'] ?? 0));

        $stmtPeriods = $pdo->prepare("SELECT * FROM fin_accounting_periods WHERE fiscal_year_id = ? ORDER BY period_number ASC");
        $stmtPeriods->execute([$selectedYearId]);
        $periods = $stmtPeriods->fetchAll();

        $this->render('fiscal_years/index', [
            'pageTitle'      => 'จัดการปีงบประมาณและรอบระยะเวลาบัญชี',
            'years'          => $years,
            'selectedYearId' => $selectedYearId,
            'periods'        => $periods
        ]);
    }

    public function store(): void
    {
        $this->checkPermission('settings', 'create');
        $pdo = Database::getConnection();

        $yearBe = (int)$this->request->input('year_be');
        if ($yearBe < 2500 || $yearBe > 2700) {
            $this->setFlash('danger', 'ปีงบประมาณ พ.ศ. ไม่ถูกต้อง');
            $this->redirect('/fiscal-years');
        }

        // Check duplicate
        $stmtCheck = $pdo->prepare("SELECT id FROM fin_fiscal_years WHERE year_be = ?");
        $stmtCheck->execute([$yearBe]);
        if ($stmtCheck->fetch()) {
            $this->setFlash('warning', "ปีงบประมาณ {$yearBe} มีอยู่ในระบบแล้ว");
            $this->redirect('/fiscal-years');
        }

        $yearCe = $yearBe - 543;
        $prevCe = $yearCe - 1;
        $startDate = "{$prevCe}-10-01";
        $endDate = "{$yearCe}-09-30";

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO fin_fiscal_years (year_be, year_ce, start_date, end_date, is_current, is_closed) VALUES (?, ?, ?, ?, 0, 0)");
            $stmt->execute([$yearBe, $yearCe, $startDate, $endDate]);
            $fyId = (int)$pdo->lastInsertId();

            // Auto-generate 12 accounting periods (Period 1 = October to Period 12 = September)
            $periodsConfig = [
                1  => ["งวดที่ 1 (ตุลาคม $yearBe)", "$prevCe-10-01", "$prevCe-10-31"],
                2  => ["งวดที่ 2 (พฤศจิกายน $yearBe)", "$prevCe-11-01", "$prevCe-11-30"],
                3  => ["งวดที่ 3 (ธันวาคม $yearBe)", "$prevCe-12-01", "$prevCe-12-31"],
                4  => ["งวดที่ 4 (มกราคม $yearBe)", "$yearCe-01-01", "$yearCe-01-31"],
                5  => ["งวดที่ 5 (กุมภาพันธ์ $yearBe)", "$yearCe-02-01", date('Y-m-t', strtotime("$yearCe-02-01"))],
                6  => ["งวดที่ 6 (มีนาคม $yearBe)", "$yearCe-03-01", "$yearCe-03-31"],
                7  => ["งวดที่ 7 (เมษายน $yearBe)", "$yearCe-04-01", "$yearCe-04-30"],
                8  => ["งวดที่ 8 (พฤษภาคม $yearBe)", "$yearCe-05-01", "$yearCe-05-31"],
                9  => ["งวดที่ 9 (มิถุนายน $yearBe)", "$yearCe-06-01", "$yearCe-06-30"],
                10 => ["งวดที่ 10 (กรกฎาคม $yearBe)", "$yearCe-07-01", "$yearCe-07-31"],
                11 => ["งวดที่ 11 (สิงหาคม $yearBe)", "$yearCe-08-01", "$yearCe-08-31"],
                12 => ["งวดที่ 12 (กันยายน $yearBe)", "$yearCe-09-01", "$yearCe-09-30"],
            ];

            $stmtPeriod = $pdo->prepare("INSERT INTO fin_accounting_periods (fiscal_year_id, period_number, period_name, start_date, end_date, is_closed) VALUES (?, ?, ?, ?, ?, 0)");
            foreach ($periodsConfig as $num => [$name, $start, $end]) {
                $stmtPeriod->execute([$fyId, $num, $name, $start, $end]);
            }

            $pdo->commit();
            AuditTrailService::log('fiscal_year', 'create', 'fin_fiscal_years', $fyId, null, ['year_be' => $yearBe], "เพิ่มปีงบประมาณ {$yearBe}");

            $this->setFlash('success', "เพิ่มปีงบประมาณ {$yearBe} และสร้าง 12 งวดบัญชีเรียบร้อยแล้ว");
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->setFlash('danger', 'เกิดข้อผิดพลาดในการสร้างปีงบประมาณ: ' . $e->getMessage());
        }

        $this->redirect('/fiscal-years');
    }

    public function setCurrent(): void
    {
        $this->checkPermission('settings', 'edit');
        $id = (int)$this->request->input('year_id');
        $pdo = Database::getConnection();

        $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE fin_fiscal_years SET is_current = 0");
            $stmt = $pdo->prepare("UPDATE fin_fiscal_years SET is_current = 1 WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->commit();

            AuthService::switchFiscalYear($id);
            AuditTrailService::log('fiscal_year', 'set_current', 'fin_fiscal_years', $id, null, null, "กำหนดปีงบประมาณปัจจุบัน");

            $this->setFlash('success', 'เปลี่ยนปีงบประมาณหลักเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->setFlash('danger', 'ไม่สามารถเปลี่ยนปีงบประมาณหลักได้: ' . $e->getMessage());
        }

        $this->redirect('/fiscal-years');
    }

    public function togglePeriodLock(): void
    {
        $this->checkPermission('settings', 'edit');
        $periodId = (int)$this->request->input('period_id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT is_closed, period_name FROM fin_accounting_periods WHERE id = ?");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch();

        if ($period) {
            $newStatus = $period['is_closed'] ? 0 : 1;
            $stmtUpdate = $pdo->prepare("UPDATE fin_accounting_periods SET is_closed = ?, closed_at = ? WHERE id = ?");
            $closedAt = $newStatus ? date('Y-m-d H:i:s') : null;
            $stmtUpdate->execute([$newStatus, $closedAt, $periodId]);

            $statusText = $newStatus ? 'ปิดงวดบัญชี (ห้ามลงบัญชีเพิ่มเติม)' : 'เปิดงวดบัญชี';
            AuditTrailService::log('accounting_period', 'toggle_lock', 'fin_accounting_periods', $periodId, ['is_closed' => $period['is_closed']], ['is_closed' => $newStatus], "{$statusText} {$period['period_name']}");

            $this->setFlash('success', "{$statusText} {$period['period_name']} เรียบร้อยแล้ว");
        }

        $this->redirect('/fiscal-years?year_id=' . (int)$this->request->input('year_id'));
    }
}
