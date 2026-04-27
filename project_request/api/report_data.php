<?php
/**
 * api/report_data.php — JSON Data for Charts
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$type = $_GET['type'] ?? '';
$fiscal_year = $_GET['fiscal_year'] ?? FISCAL_YEAR;
$pdo = getPdo();

try {
    switch ($type) {
        case 'budget_usage':
            // Used vs Alloc per Dept
            $stmt = $pdo->prepare("SELECT department_name, alloc_total, used_total FROM v_budget_usage WHERE fiscal_year = ?");
            $stmt->execute([$fiscal_year]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'labels' => array_column($data, 'department_name'),
                'alloc' => array_column($data, 'alloc_total'),
                'used' => array_column($data, 'used_total')
            ]);
            break;

        case 'fund_types':
            // Distribution of fund types across all projects
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(budget_subsidy) as subsidy, 
                    SUM(budget_quality) as quality, 
                    SUM(budget_revenue) as revenue, 
                    SUM(budget_operation) as operation,
                    SUM(budget_reserve) as reserve
                FROM budget_projects 
                WHERE fiscal_year = ? AND is_active = 1
            ");
            $stmt->execute([$fiscal_year]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'labels' => ['งบอุดหนุน', 'พัฒนาคุณภาพ', 'รายได้', 'งานประจำ', 'สำรองจ่าย'],
                'values' => [
                    (float)$row['subsidy'], 
                    (float)$row['quality'], 
                    (float)$row['revenue'], 
                    (float)$row['operation'],
                    (float)$row['reserve']
                ]
            ]);
            break;

        case 'monthly_requests':
            // Requests over last 12 months
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(request_date, '%Y-%m') as month, 
                    COUNT(*) as count,
                    SUM(amount_requested) as total
                FROM project_requests 
                WHERE request_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY month
                ORDER BY month ASC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Map to Thai month names
            $thaiMonths = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
            $labels = [];
            foreach ($data as $d) {
                $m = (int)substr($d['month'], 5, 2);
                $y = (int)substr($d['month'], 0, 4) + 543;
                $labels[] = $thaiMonths[$m] . ' ' . substr($y, 2);
            }

            echo json_encode([
                'labels' => $labels,
                'counts' => array_column($data, 'count'),
                'totals' => array_column($data, 'total')
            ]);
            break;

        case 'dept_remaining':
            // Horizontal bar for remaining budget
            $stmt = $pdo->prepare("SELECT department_name, (alloc_total - used_total) as remaining FROM v_budget_usage WHERE fiscal_year = ?");
            $stmt->execute([$fiscal_year]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'labels' => array_column($data, 'department_name'),
                'values' => array_column($data, 'remaining')
            ]);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
