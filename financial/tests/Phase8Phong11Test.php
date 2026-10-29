<?php

declare(strict_types=1);

/**
 * Phase 8: แบบ ผง 1/1 (บันทึกข้อความขออนุมัติใช้เงินงบประมาณตามแผนปฏิบัติราชการ โรงเรียนละลมวิทยา)
 */

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/../app/Helpers/functions.php';

echo ">>> [Phase 8] Testing Document Generation: แบบ ผง 1/1 (School Budget Approval Form)" . PHP_EOL;

$passed = 0;
$total = 0;

function assertTrue(bool $condition, string $message): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$message}" . PHP_EOL;
    } else {
        echo "  [FAIL] {$message}" . PHP_EOL;
    }
}

// 1. Verify Garuda emblem asset exists
$garudaPath = __DIR__ . '/../public/assets/garuda.png';
assertTrue(file_exists($garudaPath) && filesize($garudaPath) > 1000, "Garuda emblem asset exists and has valid size (> 1KB)");

// 2. Verify View files exist
$formView = __DIR__ . '/../app/Views/documents/form_phong11.php';
$printView = __DIR__ . '/../app/Views/documents/phong11.php';
assertTrue(file_exists($formView), "Form view 'documents/form_phong11.php' exists");
assertTrue(file_exists($printView), "Print view 'documents/phong11.php' exists");

// 3. Test rendering the print view with sample data
$sampleDoc = [
    'doc_no'               => '221/2569',
    'doc_date'             => '24 ธันวาคม 2568',
    'school_name'          => 'โรงเรียนละลมวิทยา',
    'affiliation'          => 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร',
    'fiscal_year_be'       => '2570',
    'department'           => 'ฝ่ายบริหารงานวิชาการ',
    'work_section'         => 'กลุ่มสาระการเรียนรู้คณิตศาสตร์',
    'requester_name'       => 'นายทดสอบ การสอน',
    'requester_position'   => 'ครู วิทยฐานะชำนาญการพิเศษ',
    'project_name'         => 'โครงการส่งเสริมความเป็นเลิศทางวิชาการ',
    'activity_name'        => 'กิจกรรมพัฒนาทักษะคณิตศาสตร์',
    'objective'            => 'เพื่อจัดซื้อวัสดุอุปกรณ์ในการจัดค่ายคณิตศาสตร์',
    'inspector_name'       => 'นางสมศรี ใจดี',
    'budget_allocated'     => 15000.00,
    'budget_spent_prev'    => 2000.00,
    'total_amount'         => 5000.00,
    'total_amount_text'    => baht_text(5000.00),
    'activity_leader_name' => 'นายทดสอบ การสอน',
    'budget_head_name'     => 'นางชลลัดดา มากนวล',
    'plan_head_name'       => 'นางชลลัดดา มากนวล',
    'procurement_head_name'=> 'นางบานเย็น ภูรักษา',
    'finance_head_name'    => 'นางรัตนา หงษ์โสภา',
    'director_name'        => 'นายสถาน ปรางมาศ',
    'items'                => [
        [
            'item_name'  => 'กระดาษคำตอบและเอกสารประกอบกิจกรรม',
            'quantity'   => 5,
            'unit'       => 'รีม',
            'unit_price' => 120.00,
            'amount'     => 600.00
        ],
        [
            'item_name'  => 'ชุดอุปกรณ์สื่อการเรียนการสอนคณิตศาสตร์',
            'quantity'   => 1,
            'unit'       => 'ชุด',
            'unit_price' => 4400.00,
            'amount'     => 4400.00
        ]
    ],
    'specs'                => [
        [
            'item_no'     => '1',
            'description' => 'กระดาษถ่ายเอกสาร A4 80 แกรม',
            'note'        => 'ใช้งานจัดสอบ'
        ]
    ]
];

ob_start();
$doc = $sampleDoc;
require $printView;
$renderedHtml = ob_get_clean();

assertTrue(!empty($renderedHtml), "Rendered แบบ ผง 1/1 HTML output is non-empty");
assertTrue(strpos($renderedHtml, 'แบบ ผง 1/1') !== false, "Contains form identifier 'แบบ ผง 1/1'");
assertTrue(strpos($renderedHtml, 'บันทึกข้อความ') !== false, "Contains title 'บันทึกข้อความ'");
assertTrue(strpos($renderedHtml, 'โรงเรียนละลมวิทยา') !== false, "Contains school name 'โรงเรียนละลมวิทยา'");
assertTrue(strpos($renderedHtml, 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร') !== false, "Contains affiliation");
assertTrue(strpos($renderedHtml, 'นางชลลัดดา มากนวล') !== false, "Contains budget head name 'นางชลลัดดา มากนวล'");
assertTrue(strpos($renderedHtml, 'นางบานเย็น ภูรักษา') !== false, "Contains procurement head name 'นางบานเย็น ภูรักษา'");
assertTrue(strpos($renderedHtml, 'นางรัตนา หงษ์โสภา') !== false, "Contains finance head name 'นางรัตนา หงษ์โสภา'");
assertTrue(strpos($renderedHtml, 'นายสถาน ปรางมาศ') !== false, "Contains director name 'นายสถาน ปรางมาศ'");
assertTrue(strpos($renderedHtml, 'ห้าพันบาทถ้วน') !== false, "Contains correct Thai Baht text: 'ห้าพันบาทถ้วน'");
assertTrue(strpos($renderedHtml, 'ชุดอุปกรณ์สื่อการเรียนการสอนคณิตศาสตร์') !== false, "Contains item breakdown table");
assertTrue(strpos($renderedHtml, 'กระดาษถ่ายเอกสาร A4 80 แกรม') !== false, "Contains specifications table");
assertTrue(strpos($renderedHtml, 'หัวหน้างานแผนงาน') !== false, "Contains Box 1: หัวหน้างานแผนงาน");
assertTrue(strpos($renderedHtml, 'งานพัสดุ') !== false, "Contains Box 2: งานพัสดุ");
assertTrue(strpos($renderedHtml, 'หัวหน้างานการเงิน') !== false, "Contains Box 3: หัวหน้างานการเงิน");
assertTrue(strpos($renderedHtml, 'ผู้อำนวยการโรงเรียน') !== false, "Contains Box 4: ผู้อำนวยการโรงเรียน");

// 4. Verify Controller methods and Routes in code
$budgetControllerCode = file_get_contents(__DIR__ . '/../app/Controllers/BudgetController.php');
assertTrue(strpos($budgetControllerCode, 'function phong11') !== false, "BudgetController has phong11() method");
assertTrue(strpos($budgetControllerCode, 'function previewPhong11') !== false, "BudgetController has previewPhong11() method");

$disburseControllerCode = file_get_contents(__DIR__ . '/../app/Controllers/DisbursementController.php');
assertTrue(strpos($disburseControllerCode, 'function printPhong11') !== false, "DisbursementController has printPhong11() method");

$indexRoutesCode = file_get_contents(__DIR__ . '/../public/index.php');
assertTrue(strpos($indexRoutesCode, '/budgets/phong11') !== false, "Route GET /budgets/phong11 registered");
assertTrue(strpos($indexRoutesCode, '/budgets/phong11/preview') !== false, "Route POST /budgets/phong11/preview registered");
assertTrue(strpos($indexRoutesCode, '/disbursements/phong11/') !== false, "Route GET /disbursements/phong11/{id} registered");

echo PHP_EOL . ">>> Phase 8 Test Summary: {$passed} / {$total} tests passed." . PHP_EOL;

if ($passed !== $total) {
    exit(1);
}
