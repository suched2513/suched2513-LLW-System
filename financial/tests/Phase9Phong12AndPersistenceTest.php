<?php

declare(strict_types=1);

/**
 * Phase 9: แบบ ผง 1/2 (รายงานการใช้เงินงบประมาณ) & Database Persistence System
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

echo ">>> [Phase 9] Testing Document Generation: แบบ ผง 1/2 & Persistence Registry" . PHP_EOL;

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

// 1. Verify View files exist
$formP12 = __DIR__ . '/../app/Views/documents/form_phong12.php';
$printP12 = __DIR__ . '/../app/Views/documents/phong12.php';
$regIndex = __DIR__ . '/../app/Views/documents/index.php';

assertTrue(file_exists($formP12), "Form view 'documents/form_phong12.php' exists");
assertTrue(file_exists($printP12), "Print view 'documents/phong12.php' exists");
assertTrue(file_exists($regIndex), "Registry view 'documents/index.php' exists");

// 2. Test rendering แบบ ผง 1/2 with sample data
$sampleDoc = [
    'doc_no'               => '21/2569',
    'doc_date'             => '11 กันยายน 2569',
    'school_name'          => 'โรงเรียนละลมวิทยา',
    'affiliation'          => 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร',
    'fiscal_year_be'       => '2570',
    'department'           => 'ฝ่ายบริหารงานทั่วไป',
    'work_section'         => 'งานอาคารสถานที่และสภาพแวดล้อม',
    'requester_name'       => 'นายวรวุฒิ โพธิ์ทิพย์',
    'requester_position'   => 'ครู',
    'project_name'         => 'โครงการพัฒนาส่งเสริมการบริหารงานงบประมาณ นโยบายและแผนงานให้เกิดประสิทธิภาพ',
    'activity_name'        => 'กิจกรรมซ่อมบำรุงพัสดุ',
    'objective'            => 'เพื่อเปลี่ยนไส้กรองเครื่องกรองน้ำดื่มสำหรับใช้ในโรงเรียน',
    'inspector_name'       => 'นายสิทธิโชค แว่นแก้ว',
    'budget_allocated'     => 20000.00,
    'budget_spent_prev'    => 0.00,
    'total_amount'         => 18000.00,
    'total_amount_text'    => baht_text(18000.00),
    'activity_leader_name' => 'นายวันเฉลิม มลิพันธ์',
    'budget_head_name'     => 'นางชลลัดดา มากนวล',
    'plan_head_name'       => 'นางชลลัดดา มากนวล',
    'procurement_head_name'=> 'นางบานเย็น ภูรักษา',
    'finance_head_name'    => 'นางรัตนา หงษ์โสภา',
    'director_name'        => 'นายสถาน ปรางมาศ',
    'items'                => [
        [
            'item_name'  => 'ไส้กรองน้ำดื่มระบบ RO ขนาด 20 นิ้ว',
            'quantity'   => 4,
            'unit'       => 'ชุด',
            'unit_price' => 4500.00,
            'amount'     => 18000.00
        ]
    ]
];

ob_start();
$doc = $sampleDoc;
require $printP12;
$renderedHtml = ob_get_clean();

assertTrue(!empty($renderedHtml), "Rendered แบบ ผง 1/2 HTML output is non-empty");
assertTrue(strpos($renderedHtml, 'แบบ ผง 1/2') !== false, "Contains form identifier 'แบบ ผง 1/2'");
assertTrue(strpos($renderedHtml, 'รายงานการใช้เงินงบประมาณตามแผนปฏิบัติราชการ') !== false, "Contains title 'รายงานการใช้เงินงบประมาณตามแผนปฏิบัติราชการ'");
assertTrue(strpos($renderedHtml, 'บัดนี้ ได้ดำเนินการเป็นที่เรียบร้อยแล้ว') !== false, "Contains execution completion statement");
assertTrue(strpos($renderedHtml, 'จึงเรียนมาเพื่อโปรดทราบ') !== false, "Contains closing 'จึงเรียนมาเพื่อโปรดทราบ'");
assertTrue(strpos($renderedHtml, 'หนึ่งหมื่นแปดพันบาทถ้วน') !== false, "Contains correct Thai Baht text: 'หนึ่งหมื่นแปดพันบาทถ้วน'");
assertTrue(strpos($renderedHtml, 'ได้ทำการตรวจสอบเอกสารหลักฐานแล้วถูกต้อง') !== false, "Contains plan head confirmation");
assertTrue(strpos($renderedHtml, 'ได้ทำเอกสาร จัดซื้อ/จัดจ้าง เรียบร้อยแล้ว') !== false, "Contains procurement confirmation");
assertTrue(strpos($renderedHtml, 'ได้ทำการตรวจสอบรายการแล้ว') !== false, "Contains director acknowledgement");

// 3. Verify Database Schema has fin_budget_requests
$schemaFile = __DIR__ . '/../database/schema.sql';
$schemaContent = file_get_contents($schemaFile);
assertTrue(strpos($schemaContent, 'fin_budget_requests') !== false, "Schema SQL contains table 'fin_budget_requests'");

// 4. Verify Controller methods and Routes in code
$budgetControllerCode = file_get_contents(__DIR__ . '/../app/Controllers/BudgetController.php');
assertTrue(strpos($budgetControllerCode, 'function documents') !== false, "BudgetController has documents() method");
assertTrue(strpos($budgetControllerCode, 'function submitPhong11') !== false, "BudgetController has submitPhong11() method");
assertTrue(strpos($budgetControllerCode, 'function phong12') !== false, "BudgetController has phong12() method");
assertTrue(strpos($budgetControllerCode, 'function submitPhong12') !== false, "BudgetController has submitPhong12() method");
assertTrue(strpos($budgetControllerCode, 'function printDocument') !== false, "BudgetController has printDocument() method");

$indexRoutesCode = file_get_contents(__DIR__ . '/../public/index.php');
assertTrue(strpos($indexRoutesCode, '/budgets/documents') !== false, "Route GET /budgets/documents registered");
assertTrue(strpos($indexRoutesCode, '/budgets/documents/print/{id}') !== false, "Route GET /budgets/documents/print/{id} registered");
assertTrue(strpos($indexRoutesCode, '/budgets/phong12') !== false, "Route GET /budgets/phong12 registered");
assertTrue(strpos($indexRoutesCode, '/budgets/phong12/submit') !== false, "Route POST /budgets/phong12/submit registered");

echo PHP_EOL . ">>> Phase 9 Test Summary: {$passed} / {$total} tests passed." . PHP_EOL;

if ($passed !== $total) {
    exit(1);
}
