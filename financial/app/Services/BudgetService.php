<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Class BudgetService
 * จัดการการควบคุมวงเงินงบประมาณแบบเรียลไทม์ การตัดยอดผูกพัน และการโอนเปลี่ยนแปลงงบ
 */
class BudgetService
{
    /**
     * ตรวจสอบความพร้อมของวงเงินงบประมาณ (Real-time Budget Check)
     * สูตร: (ยอดเบิกจ่ายจริง + ยอดผูกพันรอจ่าย + ยอดคำขอใหม่) / งบประมาณสุทธิ * 100
     */
    public static function checkAvailability(int $budgetId, float $requestedAmount = 0.00): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT b.*, p.project_name, p.project_code, fs.source_name
            FROM fin_budgets b
            JOIN fin_projects p ON b.project_id = p.id
            JOIN fin_funding_sources fs ON b.funding_source_id = fs.id
            WHERE b.id = ?
        ");
        $stmt->execute([$budgetId]);
        $budget = $stmt->fetch();

        if (!$budget) {
            return [
                'allowed' => false,
                'status' => 'error',
                'message' => 'ไม่พบข้อมูลหมวดงบประมาณที่ระบุ'
            ];
        }

        $netAmount = (float)$budget['net_amount'];
        $committed = (float)$budget['committed_amount'];
        $paid = (float)$budget['paid_amount'];
        $currentUsed = $committed + $paid;
        $remaining = $netAmount - $currentUsed;

        $projectedTotal = $currentUsed + $requestedAmount;
        $projectedPercent = $netAmount > 0 ? ($projectedTotal / $netAmount) * 100 : 100.0;

        $warningThreshold = (float)SettingService::get('budget_warning_percent', 80.0);
        $blockThreshold = (float)SettingService::get('budget_block_percent', 100.0);

        if ($requestedAmount > $remaining) {
            return [
                'allowed' => false,
                'status' => 'blocked',
                'message' => sprintf(
                    'ยอดเงินคงเหลือไม่เพียงพอ (ต้องการ %.2f บาท คงเหลือ %.2f บาท)',
                    $requestedAmount,
                    $remaining
                ),
                'remaining' => $remaining,
                'percent' => round($projectedPercent, 2),
                'budget' => $budget
            ];
        }

        if ($projectedPercent >= $blockThreshold) {
            return [
                'allowed' => false,
                'status' => 'blocked',
                'message' => sprintf(
                    'งบประมาณจะเกินเกณฑ์ที่กำหนด (%.2f%% จากเกณฑ์ระงับ %.2f%%)',
                    $projectedPercent,
                    $blockThreshold
                ),
                'remaining' => $remaining,
                'percent' => round($projectedPercent, 2),
                'budget' => $budget
            ];
        }

        $isWarning = $projectedPercent >= $warningThreshold;
        return [
            'allowed' => true,
            'status' => $isWarning ? 'warning' : 'ok',
            'message' => $isWarning
                ? sprintf('งบประมาณใกล้เต็มแล้ว (ใช้วงเงินไปแล้ว %.2f%%)', $projectedPercent)
                : 'งบประมาณพร้อมสำหรับการเบิกจ่าย',
            'remaining' => $remaining - $requestedAmount,
            'percent' => round($projectedPercent, 2),
            'budget' => $budget
        ];
    }

    /**
     * ตัดยอดผูกพันงบประมาณ (Commitment Hold) พร้อมล็อกแถวข้อมูลป้องกัน Concurrency
     */
    public static function holdCommitment(int $budgetId, string $refType, int $refId, float $amount): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            // Row-level lock
            $stmtLock = $pdo->prepare("SELECT * FROM fin_budgets WHERE id = ? FOR UPDATE");
            $stmtLock->execute([$budgetId]);
            $budget = $stmtLock->fetch();

            if (!$budget) {
                throw new Exception('ไม่พบงบประมาณที่ระบุ');
            }

            $netAmount = (float)$budget['net_amount'];
            $committed = (float)$budget['committed_amount'];
            $paid = (float)$budget['paid_amount'];
            $remaining = $netAmount - ($committed + $paid);

            if ($amount > $remaining) {
                throw new Exception("งบประมาณคงเหลือไม่พอผูกพัน (คงเหลือ: {$remaining}, ขอผูกพัน: {$amount})");
            }

            // บันทึกตาราง fin_budget_commitments
            $stmtCommit = $pdo->prepare("
                INSERT INTO fin_budget_commitments (budget_id, reference_type, reference_id, amount, status, created_at)
                VALUES (?, ?, ?, ?, 'active', NOW())
            ");
            $stmtCommit->execute([$budgetId, $refType, $refId, $amount]);

            // อัปเดตยอด committed_amount ใน fin_budgets
            $stmtUp = $pdo->prepare("UPDATE fin_budgets SET committed_amount = committed_amount + ? WHERE id = ?");
            $stmtUp->execute([$amount, $budgetId]);

            $pdo->commit();
            AuditTrailService::log('budgets', 'hold_commitment', 'fin_budgets', $budgetId, null, ['amount' => $amount, 'ref_type' => $refType, 'ref_id' => $refId], 'ผูกพันงบประมาณ');
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[BudgetService::holdCommitment Error] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * คืนยอดผูกพัน (Release Commitment) เช่น เมื่อยกเลิกใบขอซื้อหรือใบเบิกจ่าย
     */
    public static function releaseCommitment(string $refType, int $refId): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM fin_budget_commitments WHERE reference_type = ? AND reference_id = ? AND status = 'active' FOR UPDATE");
            $stmt->execute([$refType, $refId]);
            $commitment = $stmt->fetch();

            if ($commitment) {
                $amount = (float)$commitment['amount'];
                $budgetId = (int)$commitment['budget_id'];

                $stmtUpdateCommit = $pdo->prepare("UPDATE fin_budget_commitments SET status = 'cancelled' WHERE id = ?");
                $stmtUpdateCommit->execute([$commitment['id']]);

                $stmtUpdateBudget = $pdo->prepare("UPDATE fin_budgets SET committed_amount = GREATEST(0, committed_amount - ?) WHERE id = ?");
                $stmtUpdateBudget->execute([$amount, $budgetId]);
            }

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[BudgetService::releaseCommitment Error] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * เปลี่ยนยอดผูกพันเป็นการเบิกจ่ายจริง (Liquidate Commitment to Actual Paid)
     */
    public static function disburseCommitment(int $budgetId, string $refType, int $refId, float $actualAmount): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmtLock = $pdo->prepare("SELECT * FROM fin_budgets WHERE id = ? FOR UPDATE");
            $stmtLock->execute([$budgetId]);

            $stmtCommit = $pdo->prepare("SELECT * FROM fin_budget_commitments WHERE reference_type = ? AND reference_id = ? AND status = 'active' FOR UPDATE");
            $stmtCommit->execute([$refType, $refId]);
            $commitment = $stmtCommit->fetch();

            $committedToRelease = $commitment ? (float)$commitment['amount'] : 0.00;

            if ($commitment) {
                $pdo->prepare("UPDATE fin_budget_commitments SET status = 'liquidated' WHERE id = ?")->execute([$commitment['id']]);
            }

            $stmtUpdateBudget = $pdo->prepare("
                UPDATE fin_budgets 
                SET committed_amount = GREATEST(0, committed_amount - ?),
                    paid_amount = paid_amount + ?
                WHERE id = ?
            ");
            $stmtUpdateBudget->execute([$committedToRelease, $actualAmount, $budgetId]);

            $pdo->commit();
            AuditTrailService::log('budgets', 'disburse', 'fin_budgets', $budgetId, null, ['paid' => $actualAmount], 'เบิกจ่ายงบประมาณจริง');
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[BudgetService::disburseCommitment Error] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * โอนเปลี่ยนแปลงงบประมาณระหว่างหมวด/โครงการ (Budget Transfer)
     */
    public static function transfer(int $fromBudgetId, int $toBudgetId, float $amount, string $reason, int $userId): array
    {
        if ($fromBudgetId === $toBudgetId) {
            return ['success' => false, 'message' => 'หมวดงบประมาณต้นทางและปลายทางต้องไม่ซ้ำกัน'];
        }
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'จำนวนเงินที่โอนต้องมากกว่า 0 บาท'];
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmtFrom = $pdo->prepare("SELECT * FROM fin_budgets WHERE id = ? FOR UPDATE");
            $stmtFrom->execute([$fromBudgetId]);
            $fromBudget = $stmtFrom->fetch();

            $stmtTo = $pdo->prepare("SELECT * FROM fin_budgets WHERE id = ? FOR UPDATE");
            $stmtTo->execute([$toBudgetId]);
            $toBudget = $stmtTo->fetch();

            if (!$fromBudget || !$toBudget) {
                throw new Exception('ไม่พบข้อมูลงบประมาณต้นทางหรือปลายทาง');
            }

            $remainingFrom = (float)$fromBudget['net_amount'] - ((float)$fromBudget['committed_amount'] + (float)$fromBudget['paid_amount']);
            if ($amount > $remainingFrom) {
                throw new Exception(sprintf('งบประมาณต้นทางคงเหลือไม่พอโอน (คงเหลือ: %.2f บาท)', $remainingFrom));
            }

            // Running transfer no
            $yearBe = thai_fiscal_year();
            $stmtCount = $pdo->query("SELECT COUNT(*) FROM fin_budget_transfers");
            $count = (int)$stmtCount->fetchColumn() + 1;
            $transferNo = sprintf('TRF-%d-%04d', $yearBe, $count);

            // บันทึกรายการโอน
            $stmtTrf = $pdo->prepare("
                INSERT INTO fin_budget_transfers (transfer_no, transfer_date, from_budget_id, to_budget_id, amount, reason, status, created_by, approved_by, approved_at, created_at)
                VALUES (?, CURDATE(), ?, ?, ?, ?, 'approved', ?, ?, NOW(), NOW())
            ");
            $stmtTrf->execute([$transferNo, $fromBudgetId, $toBudgetId, $amount, $reason, $userId, $userId]);
            $transferId = (int)$pdo->lastInsertId();

            // ตัดยอดต้นทาง และเพิ่มยอดปลายทาง
            $pdo->prepare("UPDATE fin_budgets SET transfer_out_amount = transfer_out_amount + ? WHERE id = ?")->execute([$amount, $fromBudgetId]);
            $pdo->prepare("UPDATE fin_budgets SET transfer_in_amount = transfer_in_amount + ? WHERE id = ?")->execute([$amount, $toBudgetId]);

            $pdo->commit();
            AuditTrailService::log('budgets', 'transfer', 'fin_budget_transfers', $transferId, null, ['amount' => $amount, 'from' => $fromBudgetId, 'to' => $toBudgetId], "โอนงบประมาณ {$transferNo}");

            return ['success' => true, 'message' => "โอนเปลี่ยนแปลงงบประมาณสำเร็จ ({$transferNo})"];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }
}
