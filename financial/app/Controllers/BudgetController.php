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

    public function phong11(): void
    {
        $this->checkPermission('budgets', 'view');
        $pdo = Database::getConnection();

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $selectedBudgetId = (int)$this->request->query('id', 0);

        // Fetch all budgets with project details
        $stmt = $pdo->prepare("
            SELECT 
                b.*,
                p.project_code, p.project_name, p.activity_name, p.department, p.responsible_person,
                fs.source_name, fs.source_type
            FROM fin_budgets b
            JOIN fin_projects p ON b.project_id = p.id
            JOIN fin_funding_sources fs ON b.funding_source_id = fs.id
            WHERE b.fiscal_year_id = ?
            ORDER BY p.project_code ASC, b.budget_category ASC
        ");
        $stmt->execute([$fyId]);
        $allBudgets = $stmt->fetchAll();

        $preBudget = null;
        if ($selectedBudgetId > 0) {
            foreach ($allBudgets as $b) {
                if ((int)$b['id'] === $selectedBudgetId) {
                    $preBudget = $b;
                    break;
                }
            }
        }

        $this->render('documents/form_phong11', [
            'pageTitle'  => 'จัดทำเอกสาร แบบ ผง 1/1',
            'allBudgets' => $allBudgets,
            'preBudget'  => $preBudget
        ]);
    }

    public function previewPhong11(): void
    {
        $this->checkPermission('budgets', 'view');

        $docNo = (string)$this->request->input('doc_no');
        $docDate = (string)$this->request->input('doc_date');
        $schoolName = (string)$this->request->input('school_name', 'โรงเรียนละลมวิทยา');
        $affiliation = (string)$this->request->input('affiliation', 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร');
        $fiscalYearBe = (string)$this->request->input('fiscal_year_be', '2570');
        $department = (string)$this->request->input('department');
        $workSection = (string)$this->request->input('work_section');
        $requesterName = (string)$this->request->input('requester_name');
        $requesterPosition = (string)$this->request->input('requester_position', 'ครู');
        $projectName = (string)$this->request->input('project_name');
        $activityName = (string)$this->request->input('activity_name');
        $objective = (string)$this->request->input('objective');
        $inspectorName = (string)$this->request->input('inspector_name');
        $budgetAllocated = (float)$this->request->input('budget_allocated', 0);
        $budgetSpentPrev = (float)$this->request->input('budget_spent_prev', 0);
        $totalAmount = (float)$this->request->input('total_amount', 0);

        $activityLeaderName = (string)$this->request->input('activity_leader_name', $requesterName);
        $budgetHeadName = (string)$this->request->input('budget_head_name', 'นางชลลัดดา มากนวล');
        $planHeadName = (string)$this->request->input('plan_head_name', 'นางชลลัดดา มากนวล');
        $procurementHeadName = (string)$this->request->input('procurement_head_name', 'นางบานเย็น ภูรักษา');
        $financeHeadName = (string)$this->request->input('finance_head_name', 'นางรัตนา หงษ์โสภา');
        $directorName = (string)$this->request->input('director_name', 'นายสถาน ปรางมาศ');

        // Items
        $itemNames = (array)$this->request->input('item_name', []);
        $itemQuantities = (array)$this->request->input('item_quantity', []);
        $itemUnits = (array)$this->request->input('item_unit', []);
        $itemUnitPrices = (array)$this->request->input('item_unit_price', []);
        $itemAmounts = (array)$this->request->input('item_amount', []);

        $items = [];
        $calculatedTotal = 0;
        for ($i = 0; $i < count($itemNames); $i++) {
            if (!empty(trim($itemNames[$i]))) {
                $qty = (float)($itemQuantities[$i] ?? 1);
                $unit = trim((string)($itemUnits[$i] ?? ''));
                $price = (float)($itemUnitPrices[$i] ?? 0);
                $amt = (float)($itemAmounts[$i] ?? ($qty * $price));
                $calculatedTotal += $amt;
                $items[] = [
                    'item_name'  => $itemNames[$i],
                    'quantity'   => $qty,
                    'unit'       => $unit,
                    'unit_price' => $price,
                    'amount'     => $amt
                ];
            }
        }
        if ($totalAmount <= 0 && $calculatedTotal > 0) {
            $totalAmount = $calculatedTotal;
        }

        // Specs
        $specItemNos = (array)$this->request->input('spec_item_no', []);
        $specDescriptions = (array)$this->request->input('spec_description', []);
        $specNotes = (array)$this->request->input('spec_note', []);
        $specs = [];
        for ($j = 0; $j < count($specItemNos); $j++) {
            if (!empty(trim($specItemNos[$j])) || !empty(trim($specDescriptions[$j] ?? ''))) {
                $specs[] = [
                    'item_no'     => $specItemNos[$j],
                    'description' => $specDescriptions[$j] ?? '',
                    'note'        => $specNotes[$j] ?? ''
                ];
            }
        }

        $doc = [
            'doc_no'               => $docNo,
            'doc_date'             => $docDate,
            'school_name'          => $schoolName,
            'affiliation'          => $affiliation,
            'fiscal_year_be'       => $fiscalYearBe,
            'department'           => $department,
            'work_section'         => $workSection,
            'requester_name'       => $requesterName,
            'requester_position'   => $requesterPosition,
            'project_name'         => $projectName,
            'activity_name'        => $activityName,
            'objective'            => $objective,
            'inspector_name'       => $inspectorName,
            'budget_allocated'     => $budgetAllocated,
            'budget_spent_prev'    => $budgetSpentPrev,
            'total_amount'         => $totalAmount,
            'total_amount_text'    => baht_text($totalAmount),
            'activity_leader_name' => $activityLeaderName,
            'budget_head_name'     => $budgetHeadName,
            'plan_head_name'       => $planHeadName,
            'procurement_head_name'=> $procurementHeadName,
            'finance_head_name'    => $financeHeadName,
            'director_name'        => $directorName,
            'items'                => $items,
            'specs'                => $specs
        ];

        $viewPath = __DIR__ . '/../../financial/app/Views/documents/phong11.php';
        if (!file_exists($viewPath)) {
            $viewPath = 'C:/xampp/htdocs/llw/financial/app/Views/documents/phong11.php';
        }
        extract(['doc' => $doc]);
        require $viewPath;
        exit;
    }
}
