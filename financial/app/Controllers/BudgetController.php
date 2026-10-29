<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\BudgetService;
use App\Services\AuthService;
use App\Services\AuditTrailService;
use PDO;

/**
 * Class BudgetController
 * บริหารจัดการงบประมาณ โครงการ จัดสรร และโอนเปลี่ยนแปลงงบ
 */
class BudgetController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('budgets', 'view');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $selectedDept = trim((string)$this->request->query('dept', ''));

        $sql = "
            SELECT 
                b.*,
                p.project_code, p.project_name, p.activity_name, p.department, p.responsible_person,
                fs.source_name, fs.source_type
            FROM fin_budgets b
            JOIN fin_projects p ON b.project_id = p.id
            JOIN fin_funding_sources fs ON b.funding_source_id = fs.id
            WHERE b.fiscal_year_id = ?
        ";
        $params = [$fyId];
        if (!empty($selectedDept)) {
            $sql .= " AND p.department = ?";
            $params[] = $selectedDept;
        }
        $sql .= " ORDER BY p.project_code ASC, b.budget_category ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $budgets = $stmt->fetchAll();

        // Department summaries
        $deptStmt = $pdo->prepare("
            SELECT p.department, 
                   COUNT(DISTINCT p.id) as project_count, 
                   COALESCE(SUM(b.initial_amount), 0) as total_initial, 
                   COALESCE(SUM(b.paid_amount), 0) as total_paid
            FROM fin_projects p
            LEFT JOIN fin_budgets b ON b.project_id = p.id AND b.fiscal_year_id = ?
            WHERE p.fiscal_year_id = ?
            GROUP BY p.department
            ORDER BY p.department ASC
        ");
        $deptStmt->execute([$fyId, $fyId]);
        $deptSummaries = $deptStmt->fetchAll();

        // Projects for allocation modal
        $projects = $pdo->prepare("SELECT * FROM fin_projects WHERE fiscal_year_id = ? ORDER BY project_code ASC");
        $projects->execute([$fyId]);
        $allProjects = $projects->fetchAll();

        // Funding sources
        $fundingSources = $pdo->query("SELECT * FROM fin_funding_sources WHERE is_active = 1")->fetchAll();

        // Transfers history
        $transfers = $pdo->prepare("
            SELECT t.*, u.full_name as creator_name,
                   p1.project_name as from_project, p2.project_name as to_project
            FROM fin_budget_transfers t
            JOIN fin_budgets b1 ON t.from_budget_id = b1.id
            JOIN fin_projects p1 ON b1.project_id = p1.id
            JOIN fin_budgets b2 ON t.to_budget_id = b2.id
            JOIN fin_projects p2 ON b2.project_id = p2.id
            LEFT JOIN fin_users u ON t.created_by = u.id
            WHERE b1.fiscal_year_id = ?
            ORDER BY t.id DESC LIMIT 50
        ");
        $transfers->execute([$fyId]);
        $allTransfers = $transfers->fetchAll();

        $this->render('budgets/index', [
            'pageTitle'      => 'บริหารจัดการงบประมาณและโครงการ',
            'budgets'        => $budgets,
            'projects'       => $allProjects,
            'fundingSources' => $fundingSources,
            'transfers'      => $allTransfers,
            'selectedDept'   => $selectedDept,
            'deptSummaries'  => $deptSummaries
        ]);
    }

    public function createProject(): void
    {
        $this->checkPermission('budgets', 'create');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $code = trim((string)$this->request->input('project_code'));
        $name = trim((string)$this->request->input('project_name'));
        $dept = trim((string)$this->request->input('department'));
        $owner = trim((string)$this->request->input('responsible_person'));
        $planAmt = (float)$this->request->input('planned_amount', 0);

        if (empty($code) || empty($name)) {
            $this->setFlash('danger', 'กรุณาระบุรหัสและชื่อโครงการ');
            $this->redirect('/budgets');
        }

        try {
            Database::patchDatabaseSchema($pdo);

            $stmtCols = $pdo->prepare("SHOW COLUMNS FROM fin_projects LIKE 'responsible_person'");
            $stmtCols->execute();
            $hasOwnerCol = $stmtCols->rowCount() > 0;

            if ($hasOwnerCol) {
                $stmt = $pdo->prepare("
                    INSERT INTO fin_projects (fiscal_year_id, project_code, project_name, department, responsible_person, planned_amount, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $stmt->execute([$fyId, $code, $name, $dept, $owner, $planAmt]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO fin_projects (fiscal_year_id, project_code, project_name, department, status, created_at)
                    VALUES (?, ?, ?, ?, 'active', NOW())
                ");
                $stmt->execute([$fyId, $code, $name, $dept]);
            }

            $this->setFlash('success', "เพิ่มโครงการ '{$name}' เรียบร้อยแล้ว");
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        $this->redirect('/budgets');
    }

    public function allocate(): void
    {
        $this->checkPermission('budgets', 'edit');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $projectId = (int)$this->request->input('project_id');
        $fundingSourceId = (int)$this->request->input('funding_source_id');
        $category = $this->request->input('budget_category');
        $initialAmount = (float)$this->request->input('initial_amount');

        if ($initialAmount <= 0) {
            $this->setFlash('danger', 'จำนวนเงินจัดสรรต้องมากกว่า 0 บาท');
            $this->redirect('/budgets');
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO fin_budgets (fiscal_year_id, project_id, funding_source_id, budget_category, initial_amount, committed_amount, paid_amount)
                VALUES (?, ?, ?, ?, ?, 0.00, 0.00)
                ON DUPLICATE KEY UPDATE initial_amount = initial_amount + VALUES(initial_amount)
            ");
            $stmt->execute([$fyId, $projectId, $fundingSourceId, $category, $initialAmount]);

            $this->setFlash('success', 'บันทึกการจัดสรรงบประมาณเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาดในการจัดสรร: ' . $e->getMessage());
        }

        $this->redirect('/budgets');
    }

    public function transfer(): void
    {
        $this->checkPermission('budgets', 'edit');

        $fromBudgetId = (int)$this->request->input('from_budget_id');
        $toBudgetId = (int)$this->request->input('to_budget_id');
        $amount = (float)$this->request->input('amount');
        $reason = trim((string)$this->request->input('reason'));
        $user = $this->user();

        $result = BudgetService::transfer($fromBudgetId, $toBudgetId, $amount, $reason, (int)$user['id']);

        if ($result['success']) {
            $this->setFlash('success', $result['message']);
        } else {
            $this->setFlash('danger', $result['message']);
        }

        $this->redirect('/budgets');
    }

    public function importPlan(): void
    {
        $this->checkPermission('budgets', 'create');
        $pdo = Database::getConnection();
        try {
            require_once __DIR__ . '/../../database/import_school_plan_2570.php';
            $res = runSchoolPlanImport($pdo);
            AuditTrailService::log(
                $this->currentUserId(),
                'create',
                'fin_budgets',
                0,
                null,
                $res,
                "นำเข้าแผนปฏิบัติการและงบประมาณปี 2570 ครบทั้ง 4 ฝ่าย ({$res['total_activities']} กิจกรรม) รวม " . number_format($res['grand_total'], 2) . " บาท"
            );
            $this->setFlash('success', "นำเข้าแผนงานโครงการปี 2570 ครบทุกฝ่ายสำเร็จ ({$res['total_activities']} กิจกรรม) วงเงินจัดสรรรวม " . number_format($res['grand_total'], 2) . " บาท");
        } catch (\Throwable $e) {
            $this->setFlash('danger', "เกิดข้อผิดพลาดในการนำเข้าข้อมูล: " . $e->getMessage());
        }
        $this->redirect('/budgets');
    }
}
