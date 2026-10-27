<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\SpecialProgramService;
use App\Services\AuthService;
use PDO;

/**
 * Class SpecialProgramController
 * จัดการห้องเรียนพิเศษ อัตราค่าบำรุงการศึกษา การตั้งหนี้รายห้องเรียน และการรับชำระเงิน
 */
class SpecialProgramController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('special_programs', 'view');
        $programs = SpecialProgramService::getPrograms();

        $pdo = Database::getConnection();

        // สรุปยอดการจัดเก็บค่าบำรุงการศึกษา
        $stmtStats = $pdo->query("
            SELECT 
                COUNT(*) as total_students,
                COALESCE(SUM(net_amount), 0) as total_charged,
                COALESCE(SUM(paid_amount), 0) as total_collected,
                COALESCE(SUM(waived_amount), 0) as total_waived
            FROM fin_student_charges
        ");
        $stats = $stmtStats->fetch();

        $totalCharged = (float)($stats['total_charged'] ?? 0);
        $totalCollected = (float)($stats['total_collected'] ?? 0);
        $collectionRate = $totalCharged > 0 ? ($totalCollected / $totalCharged) * 100 : 0.0;

        $this->render('special_programs/index', [
            'pageTitle'      => 'ระบบห้องเรียนพิเศษและค่าบำรุงการศึกษา',
            'programs'       => $programs,
            'stats'          => $stats,
            'collectionRate' => round($collectionRate, 1)
        ]);
    }

    public function rates(): void
    {
        $this->checkPermission('special_programs', 'view');
        $activeFy = AuthService::getActiveFiscalYear();
        $yearBe = (int)$this->request->query('year_be', $activeFy['year_be']);
        $semester = (int)$this->request->query('semester', 2);

        $rates = SpecialProgramService::getFeeRates($yearBe, $semester);
        $programs = SpecialProgramService::getPrograms();

        $this->render('special_programs/rates', [
            'pageTitle' => 'กำหนดอัตราค่าบำรุงการศึกษา',
            'rates'     => $rates,
            'programs'  => $programs,
            'yearBe'    => $yearBe,
            'semester'  => $semester
        ]);
    }

    public function storeRate(): void
    {
        $this->checkPermission('special_programs', 'create');

        $programId = (int)$this->request->input('special_program_id');
        $yearBe = (int)$this->request->input('academic_year_be');
        $semester = (int)$this->request->input('semester');
        $gradeLevel = (string)$this->request->input('grade_level');
        $amount = (float)$this->request->input('fee_amount');

        if ($amount <= 0) {
            $this->setFlash('danger', 'อัตราค่าบำรุงการศึกษาต้องมากกว่า 0 บาท');
            $this->redirect('/special-programs/rates');
        }

        try {
            SpecialProgramService::setFeeRate($programId, $yearBe, $semester, $gradeLevel, $amount);
            $this->setFlash('success', 'บันทึกอัตราค่าบำรุงการศึกษาเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        $this->redirect("/special-programs/rates?year_be={$yearBe}&semester={$semester}");
    }

    public function charges(): void
    {
        $this->checkPermission('special_programs', 'view');
        $pdo = Database::getConnection();

        $classroom = trim((string)$this->request->query('classroom', ''));
        $status = trim((string)$this->request->query('status', ''));
        $search = trim((string)$this->request->query('search', ''));

        $sql = "
            SELECT c.*, r.academic_year_be, r.semester, r.grade_level, p.program_name, p.program_code, rec.receipt_no
            FROM fin_student_charges c
            JOIN fin_fee_rates r ON c.fee_rate_id = r.id
            JOIN fin_special_programs p ON r.special_program_id = p.id
            LEFT JOIN fin_receipts rec ON c.receipt_id = rec.id
            WHERE 1=1
        ";
        $params = [];

        if ($classroom !== '') {
            $sql .= " AND c.classroom = ?";
            $params[] = $classroom;
        }
        if ($status !== '') {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }
        if ($search !== '') {
            $sql .= " AND (c.student_id LIKE ? OR c.student_name LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term; $params[] = $term;
        }

        $sql .= " ORDER BY c.classroom ASC, c.student_id ASC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $charges = $stmt->fetchAll();

        // Classrooms list for filter
        $classrooms = $pdo->query("SELECT DISTINCT classroom FROM fin_student_charges ORDER BY classroom ASC")->fetchAll(PDO::FETCH_COLUMN);

        // Bank Accounts for payment modal
        $bankAccounts = $pdo->query("SELECT id, bank_name, account_number, account_name FROM fin_bank_accounts WHERE is_active = 1")->fetchAll();

        $this->render('special_programs/charges', [
            'pageTitle'    => 'ทะเบียนคุมการจัดเก็บค่าบำรุงการศึกษา',
            'charges'      => $charges,
            'classrooms'   => $classrooms,
            'bankAccounts' => $bankAccounts,
            'filter'       => [
                'classroom' => $classroom,
                'status'    => $status,
                'search'    => $search
            ]
        ]);
    }

    public function createBilling(): void
    {
        $this->checkPermission('special_programs', 'create');
        $rates = SpecialProgramService::getFeeRates();

        $this->render('special_programs/create_billing', [
            'pageTitle' => 'ตั้งหนี้ค่าบำรุงการศึกษาแบบรายห้องเรียน',
            'rates'     => $rates
        ]);
    }

    public function storeBilling(): void
    {
        $this->checkPermission('special_programs', 'create');

        $feeRateId = (int)$this->request->input('fee_rate_id');
        $classroom = trim((string)$this->request->input('classroom'));
        $rawStudentList = trim((string)$this->request->input('student_list'));
        $user = $this->user();

        if (empty($feeRateId) || empty($classroom) || empty($rawStudentList)) {
            $this->setFlash('danger', 'กรุณากรอกข้อมูลและรายชื่อนักเรียนให้ครบถ้วน');
            $this->redirect('/special-programs/create-billing');
        }

        // Parse student list: each line format: "student_id, student_name" or "student_id student_name"
        $lines = explode("\n", str_replace("\r", "", $rawStudentList));
        $students = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = preg_split('/[\t,]+/', $line);
            if (count($parts) >= 2) {
                $students[] = [
                    'student_id'   => trim($parts[0]),
                    'student_name' => trim($parts[1]),
                    'classroom'    => $classroom
                ];
            }
        }

        if (empty($students)) {
            $this->setFlash('danger', 'ไม่พบรูปแบบข้อมูลนักเรียนที่ถูกต้อง (รหัสนักเรียน, ชื่อ-นามสกุล)');
            $this->redirect('/special-programs/create-billing');
        }

        try {
            $count = SpecialProgramService::generateClassroomCharges($feeRateId, $students, (int)$user['id']);
            $this->setFlash('success', "ตั้งหนี้ค่าบำรุงการศึกษาห้อง {$classroom} สำเร็จ รวม {$count} คน");
            $this->redirect("/special-programs/charges?classroom={$classroom}");
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
            $this->redirect('/special-programs/create-billing');
        }
    }

    public function pay(): void
    {
        $this->checkPermission('special_programs', 'create');

        $chargeId = (int)$this->request->input('charge_id');
        $paidAmount = (float)$this->request->input('paid_amount');
        $method = (string)$this->request->input('payment_method', 'cash');
        $bankAccountId = !empty($this->request->input('bank_account_id')) ? (int)$this->request->input('bank_account_id') : null;
        $user = $this->user();

        if ($paidAmount <= 0) {
            $this->setFlash('danger', 'จำนวนเงินชำระต้องมากกว่า 0 บาท');
            $this->redirect('/special-programs/charges');
        }

        $res = SpecialProgramService::recordPayment($chargeId, $paidAmount, $method, $bankAccountId, (int)$user['id']);

        if ($res['success']) {
            $this->setFlash('success', 'บันทึกรับชำระและออกใบเสร็จรับเงินเรียบร้อยแล้ว');
            $this->redirect("/receipts/show/{$res['receipt_id']}");
        } else {
            $this->setFlash('danger', 'ไม่สามารถบันทึกได้: ' . ($res['message'] ?? ''));
            $this->redirect('/special-programs/charges');
        }
    }

    public function waive(): void
    {
        $this->checkPermission('special_programs', 'edit');

        $chargeId = (int)$this->request->input('charge_id');
        $waivedAmount = (float)$this->request->input('waived_amount');
        $reason = trim((string)$this->request->input('waive_reason'));
        $user = $this->user();

        if ($waivedAmount <= 0 || empty($reason)) {
            $this->setFlash('danger', 'กรุณาระบุจำนวนเงินและเหตุผลการยกเว้น');
            $this->redirect('/special-programs/charges');
        }

        $res = SpecialProgramService::waiveCharge($chargeId, $waivedAmount, $reason, (int)$user['id']);
        $this->setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $this->redirect('/special-programs/charges');
    }
}
