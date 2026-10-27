<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Class SpecialProgramService
 * จัดการห้องเรียนพิเศษ (EP, MEP, SMTE, ICT), อัตราค่าบำรุงการศึกษา, การตั้งหนี้รายห้องเรียน และการออกใบเสร็จรับเงิน
 */
class SpecialProgramService
{
    public static function getPrograms(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM fin_special_programs ORDER BY id ASC")->fetchAll();
    }

    public static function getFeeRates(?int $yearBe = null, ?int $semester = null): array
    {
        $pdo = Database::getConnection();
        $yearBe = $yearBe ?? thai_fiscal_year();
        $semester = $semester ?? 2;

        $stmt = $pdo->prepare("
            SELECT r.*, p.program_name, p.program_code
            FROM fin_fee_rates r
            JOIN fin_special_programs p ON r.special_program_id = p.id
            WHERE r.academic_year_be = ? AND r.semester = ?
            ORDER BY r.grade_level ASC, p.program_code ASC
        ");
        $stmt->execute([$yearBe, $semester]);
        return $stmt->fetchAll();
    }

    public static function setFeeRate(int $programId, int $yearBe, int $semester, string $gradeLevel, float $feeAmount): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO fin_fee_rates (academic_year_be, semester, special_program_id, grade_level, fee_amount)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE fee_amount = VALUES(fee_amount)
        ");
        $stmt->execute([$yearBe, $semester, $programId, $gradeLevel, $feeAmount]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * ตั้งหนี้ค่าบำรุงการศึกษาสำหรับนักเรียนทั้งห้องเรียน (Batch Billing)
     */
    public static function generateClassroomCharges(int $feeRateId, array $students, int $userId): int
    {
        $pdo = Database::getConnection();
        $stmtRate = $pdo->prepare("SELECT * FROM fin_fee_rates WHERE id = ?");
        $stmtRate->execute([$feeRateId]);
        $rate = $stmtRate->fetch();

        if (!$rate) throw new Exception('ไม่พบอัตราค่าบำรุงการศึกษาที่ระบุ');

        $feeAmount = (float)$rate['fee_amount'];
        $count = 0;

        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO fin_student_charges 
                (student_id, student_name, classroom, fee_rate_id, original_amount, discount_amount, waived_amount, net_amount, paid_amount, status)
                VALUES (?, ?, ?, ?, ?, 0.00, 0.00, ?, 0.00, 'unpaid')
            ");

            foreach ($students as $stu) {
                if (empty($stu['student_id']) || empty($stu['student_name'])) continue;

                $stmtInsert->execute([
                    trim($stu['student_id']),
                    trim($stu['student_name']),
                    trim($stu['classroom']),
                    $feeRateId,
                    $feeAmount,
                    $feeAmount
                ]);
                $count++;
            }

            $pdo->commit();
            AuditTrailService::log('special_programs', 'generate_charges', 'fin_student_charges', null, null, ['count' => $count, 'rate_id' => $feeRateId], "ตั้งหนี้ค่าบำรุงการศึกษา ({$count} คน)");
            return $count;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * บันทึกรับชำระค่าบำรุงการศึกษา และออกใบเสร็จรับเงินราชการอัตโนมัติ
     */
    public static function recordPayment(int $chargeId, float $paidAmount, string $paymentMethod, ?int $bankAccountId, int $userId): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT c.*, r.academic_year_be, r.semester, p.program_name
                FROM fin_student_charges c
                JOIN fin_fee_rates r ON c.fee_rate_id = r.id
                JOIN fin_special_programs p ON r.special_program_id = p.id
                WHERE c.id = ? FOR UPDATE
            ");
            $stmt->execute([$chargeId]);
            $charge = $stmt->fetch();

            if (!$charge) throw new Exception('ไม่พบรายการแจ้งหนี้ค่าบำรุงการศึกษา');
            if ($charge['status'] === 'paid') throw new Exception('รายการนี้ชำระเงินครบถ้วนแล้ว');

            $newTotalPaid = (float)$charge['paid_amount'] + $paidAmount;
            $netAmount = (float)$charge['net_amount'];
            $newStatus = ($newTotalPaid >= $netAmount) ? 'paid' : 'partial';

            // 1. ออกใบเสร็จรับเงินผ่าน ReceiptService (แหล่งเงิน = 2 เงินรายได้สถานศึกษา/ค่าบำรุงการศึกษา)
            $activeFy = AuthService::getActiveFiscalYear();
            $receiptData = [
                'fiscal_year_id'    => (int)$activeFy['id'],
                'receipt_date'      => date('Y-m-d'),
                'funding_source_id' => 2, // เงินรายได้สถานศึกษา
                'payer_name'        => "{$charge['student_name']} (รหัสนักเรียน: {$charge['student_id']}, ห้อง: {$charge['classroom']})",
                'payer_tax_id'      => null,
                'payment_method'    => $paymentMethod,
                'bank_account_id'   => $bankAccountId,
                'bank_ref_no'       => "ค่าบำรุงการศึกษา {$charge['program_name']} ภาคเรียนที่ {$charge['semester']}/{$charge['academic_year_be']}"
            ];

            $receiptItems = [
                [
                    'description' => "ค่าบำรุงการศึกษา {$charge['program_name']} ภาคเรียนที่ {$charge['semester']}/{$charge['academic_year_be']}",
                    'amount'      => $paidAmount,
                    'account_id'  => AccountingService::getAccountIdByCode('4220') // รายได้ค่าบำรุงการศึกษา
                ]
            ];

            $receiptId = ReceiptService::createReceipt($receiptData, $receiptItems, $userId);

            // 2. อัปเดต fin_student_charges
            $stmtUp = $pdo->prepare("
                UPDATE fin_student_charges 
                SET paid_amount = ?, status = ?, receipt_id = ? 
                WHERE id = ?
            ");
            $stmtUp->execute([$newTotalPaid, $newStatus, $receiptId, $chargeId]);

            $pdo->commit();
            AuditTrailService::log('special_programs', 'pay_fee', 'fin_student_charges', $chargeId, null, ['paid' => $paidAmount, 'receipt_id' => $receiptId], "รับชำระค่าบำรุงการศึกษา รหัสนักเรียน {$charge['student_id']}");

            return [
                'success'    => true,
                'receipt_id' => $receiptId,
                'status'     => $newStatus,
                'paid_total' => $newTotalPaid
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ยกเว้นหรือให้ทุนส่วนลดค่าบำรุงการศึกษาแก่นักเรียน
     */
    public static function waiveCharge(int $chargeId, float $waivedAmount, string $reason, int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_student_charges WHERE id = ?");
        $stmt->execute([$chargeId]);
        $c = $stmt->fetch();

        if (!$c) return ['success' => false, 'message' => 'ไม่พบรายการแจ้งหนี้'];

        $newWaived = (float)$c['waived_amount'] + $waivedAmount;
        $newNet = max(0.00, (float)$c['original_amount'] - $newWaived);
        $newStatus = ($newNet <= (float)$c['paid_amount']) ? 'waived' : $c['status'];

        $stmtUp = $pdo->prepare("UPDATE fin_student_charges SET waived_amount = ?, net_amount = ?, status = ? WHERE id = ?");
        $stmtUp->execute([$newWaived, $newNet, $newStatus, $chargeId]);

        AuditTrailService::log('special_programs', 'waive_fee', 'fin_student_charges', $chargeId, null, ['waived' => $waivedAmount, 'reason' => $reason], "ยกเว้นค่าบำรุงการศึกษา รหัสนักเรียน {$c['student_id']}");
        return ['success' => true, 'message' => "ยกเว้นค่าบำรุงการศึกษา {$waivedAmount} บาท สำเร็จ"];
    }
}
