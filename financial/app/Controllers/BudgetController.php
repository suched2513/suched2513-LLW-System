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
        $itemQuantities = (array)($this->request->input('quantity') ?: $this->request->input('item_quantity', []));
        $itemUnits = (array)($this->request->input('unit') ?: $this->request->input('item_unit', []));
        $itemUnitPrices = (array)($this->request->input('unit_price') ?: $this->request->input('item_unit_price', []));
        $itemAmounts = (array)$this->request->input('item_amount', []);

        $items = [];
        $calculatedTotal = 0;
        for ($i = 0; $i < count($itemNames); $i++) {
            $name = trim((string)($itemNames[$i] ?? ''));
            if ($name !== '') {
                $qty = (float)($itemQuantities[$i] ?? 1);
                $unit = trim((string)($itemUnits[$i] ?? ''));
                $price = (float)($itemUnitPrices[$i] ?? 0);
                $amt = (float)($itemAmounts[$i] ?? ($qty * $price));
                if ($amt <= 0 && $price > 0) {
                    $amt = $qty * $price;
                }
                $calculatedTotal += $amt;
                $items[] = [
                    'item_name'  => $name,
                    'quantity'   => $qty,
                    'unit'       => $unit ?: 'หน่วย',
                    'unit_price' => $price,
                    'amount'     => $amt
                ];
            }
        }
        if ($totalAmount <= 0 && $calculatedTotal > 0) {
            $totalAmount = $calculatedTotal;
        } elseif ($totalAmount > 0 && empty($items)) {
            $items[] = [
                'item_name'  => $objective ?: 'เพื่อใช้ในการดำเนินกิจกรรมตามแผนปฏิบัติราชการ',
                'quantity'   => 1,
                'unit'       => 'งาน',
                'unit_price' => $totalAmount,
                'amount'     => $totalAmount
            ];
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

                $actionType = (string)$this->request->input('action_type', 'save_and_print');
        $budgetId = (int)$this->request->input('budget_id', 0);

        if ($actionType === 'save_and_print' && $totalAmount > 0) {
            $pdo = Database::getConnection();
            Database::ensureBudgetRequestsTable($pdo);
            $user = $this->user();
            $userId = (int)($user['id'] ?? 1);
            $activeFy = AuthService::getActiveFiscalYear();
            $fyId = (int)$activeFy['id'];

            $stmtIns = $pdo->prepare("
                INSERT INTO fin_budget_requests (
                    doc_type, doc_no, doc_date, fiscal_year_id, budget_id, requester_id,
                    requester_name, requester_position, department, work_section,
                    project_name, activity_name, objective, inspector_name,
                    total_amount, budget_allocated, budget_spent_prev, budget_remaining_before, budget_remaining_after,
                    activity_leader_name, budget_head_name, plan_head_name, procurement_head_name,
                    finance_head_name, director_name, items_json, specs_json, status, created_by
                ) VALUES (
                    'phong11', ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, 'approved', ?
                )
            ");
            $stmtIns->execute([
                $docNo, $docDate, $fyId, $budgetId, $userId,
                $requesterName, $requesterPosition, $department, $workSection,
                $projectName, $activityName, $objective, $inspectorName,
                $totalAmount, $budgetAllocated, $budgetSpentPrev, ($budgetAllocated - $budgetSpentPrev), ($budgetAllocated - $budgetSpentPrev - $totalAmount),
                $activityLeaderName, $budgetHeadName, $planHeadName, $procurementHeadName,
                $financeHeadName, $directorName, json_encode($items, JSON_UNESCAPED_UNICODE), json_encode($specs, JSON_UNESCAPED_UNICODE), $userId
            ]);
            $requestId = (int)$pdo->lastInsertId();

            // Update committed amount in budget
            if ($budgetId > 0) {
                $pdo->prepare("UPDATE fin_budgets SET committed_amount = committed_amount + ? WHERE id = ?")
                    ->execute([$totalAmount, $budgetId]);

                // Record commitment
                try {
                    $pdo->prepare("INSERT INTO fin_budget_commitments (budget_id, reference_type, reference_id, amount, status) VALUES (?, 'purchase_request', ?, ?, 'active')")
                        ->execute([$budgetId, $requestId, $totalAmount]);
                } catch (\Throwable $e) {}
            }

            AuditTrailService::log(
                $userId,
                'create',
                'fin_budget_requests',
                $requestId,
                null,
                ['doc_no' => $docNo, 'total_amount' => $totalAmount],
                "สร้างและบันทึกเอกสารขออนุมัติใช้เงินงบประมาณ (แบบ ผง 1/1) เลขที่ {$docNo} จำนวน " . number_format($totalAmount, 2) . " บาท"
            );
        }

        $this->render('documents/phong11', ['doc' => $doc], null);
    }

    public function submitPhong11(): void
    {
        $this->previewPhong11();
    }

    public function documents(): void
    {
        $this->checkPermission('budgets', 'view');
        $pdo = Database::getConnection();
        Database::ensureBudgetRequestsTable($pdo);

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $type = (string)$this->request->query('type', 'all');

        $sql = "
            SELECT r.*, p.project_code, b.budget_category
            FROM fin_budget_requests r
            LEFT JOIN fin_budgets b ON r.budget_id = b.id
            LEFT JOIN fin_projects p ON b.project_id = p.id
            WHERE r.fiscal_year_id = ?
        ";
        $params = [$fyId];
        if ($type === 'phong11' || $type === 'phong12') {
            $sql .= " AND r.doc_type = ?";
            $params[] = $type;
        }
        $sql .= " ORDER BY r.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        // Stats
        $statStmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN doc_type = 'phong11' THEN 1 END) as total_phong11,
                COALESCE(SUM(CASE WHEN doc_type = 'phong11' THEN total_amount ELSE 0 END), 0) as amount_phong11,
                COUNT(CASE WHEN doc_type = 'phong12' THEN 1 END) as total_phong12,
                COALESCE(SUM(CASE WHEN doc_type = 'phong12' THEN total_amount ELSE 0 END), 0) as amount_phong12
            FROM fin_budget_requests
            WHERE fiscal_year_id = ?
        ");
        $statStmt->execute([$fyId]);
        $stats = $statStmt->fetch() ?: [];

        $this->render('documents/index', [
            'pageTitle'    => 'ทะเบียนเอกสารงบประมาณ (แบบ ผง 1/1 - ผง 1/2)',
            'requests'     => $requests,
            'currentType'  => $type,
            'stats'        => $stats
        ]);
    }

    public function phong12(): void
    {
        $this->checkPermission('budgets', 'view');
        $pdo = Database::getConnection();
        Database::ensureBudgetRequestsTable($pdo);

        $activeFy = AuthService::getActiveFiscalYear();
        $fyId = (int)$activeFy['id'];

        $fromId = (int)$this->request->query('from_id', 0);
        $fromPhong11 = null;
        if ($fromId > 0) {
            $stmtF = $pdo->prepare("SELECT * FROM fin_budget_requests WHERE id = ?");
            $stmtF->execute([$fromId]);
            $fromPhong11 = $stmtF->fetch() ?: null;
        }

        // Fetch all budgets
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

        $this->render('documents/form_phong12', [
            'pageTitle'    => 'จัดทำเอกสาร แบบ ผง 1/2',
            'allBudgets'   => $allBudgets,
            'fromPhong11'  => $fromPhong11
        ]);
    }

    public function submitPhong12(): void
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
        $relatedRequestId = (int)$this->request->input('related_request_id', 0);

        $activityLeaderName = (string)$this->request->input('activity_leader_name', $requesterName);
        $budgetHeadName = (string)$this->request->input('plan_head_name', 'นางชลลัดดา มากนวล');
        $planHeadName = (string)$this->request->input('plan_head_name', 'นางชลลัดดา มากนวล');
        $procurementHeadName = (string)$this->request->input('procurement_head_name', 'นางบานเย็น ภูรักษา');
        $financeHeadName = (string)$this->request->input('finance_head_name', 'นางรัตนา หงษ์โสภา');
        $directorName = (string)$this->request->input('director_name', 'นายสถาน ปรางมาศ');

        // Items
        $itemNames = (array)$this->request->input('item_name', []);
        $itemQuantities = (array)($this->request->input('quantity') ?: $this->request->input('item_quantity', []));
        $itemUnits = (array)($this->request->input('unit') ?: $this->request->input('item_unit', []));
        $itemUnitPrices = (array)($this->request->input('unit_price') ?: $this->request->input('item_unit_price', []));
        $itemAmounts = (array)$this->request->input('item_amount', []);

        $items = [];
        $calculatedTotal = 0;
        for ($i = 0; $i < count($itemNames); $i++) {
            $name = trim((string)($itemNames[$i] ?? ''));
            if ($name !== '') {
                $qty = (float)($itemQuantities[$i] ?? 1);
                $unit = trim((string)($itemUnits[$i] ?? ''));
                $price = (float)($itemUnitPrices[$i] ?? 0);
                $amt = (float)($itemAmounts[$i] ?? ($qty * $price));
                if ($amt <= 0 && $price > 0) {
                    $amt = $qty * $price;
                }
                $calculatedTotal += $amt;
                $items[] = [
                    'item_name'  => $name,
                    'quantity'   => $qty,
                    'unit'       => $unit ?: 'หน่วย',
                    'unit_price' => $price,
                    'amount'     => $amt
                ];
            }
        }
        if ($totalAmount <= 0 && $calculatedTotal > 0) {
            $totalAmount = $calculatedTotal;
        } elseif ($totalAmount > 0 && empty($items)) {
            $items[] = [
                'item_name'  => $objective ?: 'เพื่อใช้ในการดำเนินกิจกรรมตามแผนปฏิบัติราชการ',
                'quantity'   => 1,
                'unit'       => 'งาน',
                'unit_price' => $totalAmount,
                'amount'     => $totalAmount
            ];
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
            'specs'                => []
        ];

        $actionType = (string)$this->request->input('action_type', 'save_and_print');
        $budgetId = (int)$this->request->input('budget_id', 0);

        if ($actionType === 'save_and_print' && $totalAmount > 0) {
            $pdo = Database::getConnection();
            Database::ensureBudgetRequestsTable($pdo);
            $user = $this->user();
            $userId = (int)($user['id'] ?? 1);
            $activeFy = AuthService::getActiveFiscalYear();
            $fyId = (int)$activeFy['id'];

            $stmtIns = $pdo->prepare("
                INSERT INTO fin_budget_requests (
                    doc_type, doc_no, doc_date, fiscal_year_id, budget_id, requester_id,
                    requester_name, requester_position, department, work_section,
                    project_name, activity_name, objective, inspector_name,
                    total_amount, budget_allocated, budget_spent_prev, budget_remaining_before, budget_remaining_after,
                    activity_leader_name, budget_head_name, plan_head_name, procurement_head_name,
                    finance_head_name, director_name, items_json, specs_json, status, related_request_id, created_by
                ) VALUES (
                    'phong12', ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, 'reported', ?, ?
                )
            ");
            $stmtIns->execute([
                $docNo, $docDate, $fyId, $budgetId, $userId,
                $requesterName, $requesterPosition, $department, $workSection,
                $projectName, $activityName, $objective, $inspectorName,
                $totalAmount, $budgetAllocated, $budgetSpentPrev, ($budgetAllocated - $budgetSpentPrev), ($budgetAllocated - $budgetSpentPrev - $totalAmount),
                $activityLeaderName, $budgetHeadName, $planHeadName, $procurementHeadName,
                $financeHeadName, $directorName, json_encode($items, JSON_UNESCAPED_UNICODE), json_encode([], JSON_UNESCAPED_UNICODE),
                $relatedRequestId > 0 ? $relatedRequestId : null, $userId
            ]);
            $requestId = (int)$pdo->lastInsertId();

            if ($relatedRequestId > 0) {
                $pdo->prepare("UPDATE fin_budget_requests SET status = 'reported' WHERE id = ?")
                    ->execute([$relatedRequestId]);
            }

            AuditTrailService::log(
                $userId,
                'create',
                'fin_budget_requests',
                $requestId,
                null,
                ['doc_no' => $docNo, 'total_amount' => $totalAmount],
                "สร้างและบันทึกเอกสารรายงานการใช้เงินงบประมาณ (แบบ ผง 1/2) เลขที่ {$docNo} จำนวน " . number_format($totalAmount, 2) . " บาท"
            );
        }

        $this->render('documents/phong12', ['doc' => $doc], null);
    }

    public function printDocument(string $id): void
    {
        $this->checkPermission('budgets', 'view');
        $pdo = Database::getConnection();
        Database::ensureBudgetRequestsTable($pdo);

        $stmt = $pdo->prepare("SELECT * FROM fin_budget_requests WHERE id = ?");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->setFlash('danger', 'ไม่พบเอกสารงบประมาณที่ระบุ');
            $this->redirect('/budgets/documents');
        }

        $items = !empty($row['items_json']) ? json_decode($row['items_json'], true) : [];
        $specs = !empty($row['specs_json']) ? json_decode($row['specs_json'], true) : [];

        $doc = [
            'doc_no'               => $row['doc_no'],
            'doc_date'             => $row['doc_date'],
            'school_name'          => 'โรงเรียนละลมวิทยา',
            'affiliation'          => 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร',
            'fiscal_year_be'       => '2570',
            'department'           => $row['department'],
            'work_section'         => $row['work_section'],
            'requester_name'       => $row['requester_name'],
            'requester_position'   => $row['requester_position'],
            'project_name'         => $row['project_name'],
            'activity_name'        => $row['activity_name'],
            'objective'            => $row['objective'],
            'inspector_name'       => $row['inspector_name'],
            'budget_allocated'     => (float)$row['budget_allocated'],
            'budget_spent_prev'    => (float)$row['budget_spent_prev'],
            'total_amount'         => (float)$row['total_amount'],
            'total_amount_text'    => baht_text((float)$row['total_amount']),
            'activity_leader_name' => $row['activity_leader_name'],
            'budget_head_name'     => $row['budget_head_name'],
            'plan_head_name'       => $row['plan_head_name'],
            'procurement_head_name'=> $row['procurement_head_name'],
            'finance_head_name'    => $row['finance_head_name'],
            'director_name'        => $row['director_name'],
            'items'                => $items,
            'specs'                => $specs
        ];

        $viewName = ($row['doc_type'] === 'phong12') ? 'documents/phong12' : 'documents/phong11';
        $this->render($viewName, ['doc' => $doc], null);
    }
}
