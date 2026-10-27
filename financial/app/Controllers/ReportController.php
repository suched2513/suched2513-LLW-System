<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\ReportService;
use App\Services\AuthService;
use PDO;

/**
 * Class ReportController
 * ศูนย์รวมเอกสารและรายงานทางการเงินและบัญชีสถานศึกษา 28 รายงาน
 */
class ReportController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('reports', 'view');
        $catalog = ReportService::getReportCatalog();

        $this->render('reports/index', [
            'pageTitle' => 'ศูนย์เอกสารและรายงานการเงินสถานศึกษา (28 รายงาน)',
            'catalog'   => $catalog
        ]);
    }

    public function show(string $code): void
    {
        $this->checkPermission('reports', 'view');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $catalog = ReportService::getReportCatalog();
        $reportMeta = null;
        foreach ($catalog as $item) {
            if ($item['code'] === $code) {
                $reportMeta = $item;
                break;
            }
        }

        if (!$reportMeta) {
            $this->setFlash('danger', 'ไม่พบรายงานที่ระบุ');
            $this->redirect('/reports');
        }

        // Fetch sample or live data according to report type
        $data = [];
        if ($code === 'REP-01' || $code === 'REP-02') {
            $stmt = $pdo->prepare("SELECT * FROM fin_receipts WHERE fiscal_year_id = ? ORDER BY receipt_date DESC LIMIT 100");
            $stmt->execute([$fyId]);
            $data['receipts'] = $stmt->fetchAll();
        } elseif ($code === 'REP-03') {
            $stmt = $pdo->prepare("SELECT * FROM fin_payments WHERE fiscal_year_id = ? ORDER BY payment_date DESC LIMIT 100");
            $stmt->execute([$fyId]);
            $data['payments'] = $stmt->fetchAll();
        } elseif ($code === 'ADV-03') {
            $stmt = $pdo->prepare("SELECT a.*, u.full_name as borrower_name FROM fin_advances a JOIN fin_users u ON a.borrower_id = u.id WHERE a.fiscal_year_id = ? ORDER BY a.id DESC");
            $stmt->execute([$fyId]);
            $data['advances'] = $stmt->fetchAll();
        } elseif ($code === 'AST-01') {
            $data['assets'] = \App\Services\AssetService::getAssets();
        }

        $this->render('reports/show', [
            'pageTitle'  => "{$reportMeta['code']} - {$reportMeta['name']}",
            'reportMeta' => $reportMeta,
            'data'       => $data
        ]);
    }
}
