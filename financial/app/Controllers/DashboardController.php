<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuthService;

/**
 * Class DashboardController
 * แดชบอร์ดสรุปภาพรวมการเงิน บัญชี งบประมาณ และรายการรออนุมัติ
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        $pdo = Database::getConnection();
        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = $activeFy['id'] ?? null;

        // 1. สรุปภาพรวมงบประมาณปีปัจจุบัน
        $budgetSummary = [
            'total_allocated' => 0.00,
            'total_committed' => 0.00,
            'total_spent'     => 0.00,
            'total_remaining' => 0.00,
            'percent_spent'   => 0.0
        ];

        if ($fyId) {
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(current_allocation), 0) as allocated,
                    COALESCE(SUM(committed_amount), 0) as committed,
                    COALESCE(SUM(actual_spent), 0) as spent
                FROM fin_budgets
                WHERE fiscal_year_id = ?
            ");
            $stmt->execute([$fyId]);
            $bRow = $stmt->fetch();
            if ($bRow) {
                $allocated = (float)$bRow['allocated'];
                $committed = (float)$bRow['committed'];
                $spent     = (float)$bRow['spent'];
                $remaining = $allocated - ($committed + $spent);
                $percent   = $allocated > 0 ? (($committed + $spent) / $allocated) * 100 : 0.0;

                $budgetSummary = [
                    'total_allocated' => $allocated,
                    'total_committed' => $committed,
                    'total_spent'     => $spent,
                    'total_remaining' => $remaining,
                    'percent_spent'   => round($percent, 2)
                ];
            }
        }

        // 2. ยอดเงินในบัญชีธนาคารและเงินสดคงเหลือ
        $bankAccounts = [];
        $totalBankBalance = 0.00;
        try {
            $stmtBank = $pdo->query("SELECT bank_name, account_no, account_no as account_number, account_name, current_balance FROM fin_bank_accounts WHERE is_active = 1");
            $bankAccounts = $stmtBank->fetchAll();
            foreach ($bankAccounts as $ba) {
                $totalBankBalance += (float)$ba['current_balance'];
            }
        } catch (\Throwable $e) {}

        // 3. รายการรอตรวจสอบ / รออนุมัติ
        $pendingChecks = 0;
        $pendingApprovals = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM fin_disbursements WHERE status = 'submitted'");
            $pendingChecks = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM fin_disbursements WHERE status = 'verified'");
            $pendingApprovals = (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {}

        // 4. สัญญายืมเงินค้างชำระ / เกินกำหนด
        $outstandingAdvancesCount = 0;
        $outstandingAdvancesAmount = 0.00;
        try {
            $stmtAdv = $pdo->query("SELECT COUNT(*), COALESCE(SUM(remaining_amount), 0) FROM fin_advances WHERE status IN ('disbursed', 'overdue')");
            $advRow = $stmtAdv->fetch(PDO::FETCH_NUM);
            if ($advRow) {
                $outstandingAdvancesCount = (int)$advRow[0];
                $outstandingAdvancesAmount = (float)$advRow[1];
            }
        } catch (\Throwable $e) {}

        // 5. รายรับและรายจ่ายประจำเดือนปัจจุบัน
        $monthlyIncome = 0.00;
        $monthlyExpense = 0.00;
        try {
            $stmtInc = $pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) 
                FROM fin_receipts 
                WHERE status = 'confirmed' 
                  AND receipt_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            $stmtInc->execute();
            $monthlyIncome = (float)$stmtInc->fetchColumn();

            $stmtExp = $pdo->prepare("
                SELECT COALESCE(SUM(net_amount), 0) 
                FROM fin_disbursements 
                WHERE status = 'paid' 
                  AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            $stmtExp->execute();
            $monthlyExpense = (float)$stmtExp->fetchColumn();
        } catch (\Throwable $e) {}

        $this->render('dashboard/index', [
            'pageTitle'                 => 'แดชบอร์ดบริหารการเงินและงบประมาณ',
            'budgetSummary'             => $budgetSummary,
            'bankAccounts'              => $bankAccounts,
            'totalBankBalance'          => $totalBankBalance,
            'pendingChecks'             => $pendingChecks,
            'pendingApprovals'          => $pendingApprovals,
            'outstandingAdvancesCount'  => $outstandingAdvancesCount,
            'outstandingAdvancesAmount' => $outstandingAdvancesAmount,
            'monthlyIncome'             => $monthlyIncome,
            'monthlyExpense'            => $monthlyExpense,
        ]);
    }
}
