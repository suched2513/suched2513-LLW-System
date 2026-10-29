<?php
/**
 * Public Front Controller
 * ระบบบริหารการเงินและบัญชีสถานศึกษา โรงเรียนละลมวิทยา
 */

declare(strict_types=1);

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo "<div style='font-family:sans-serif;padding:30px;background:#fff5f5;border:2px solid #e53e3e;border-radius:8px;max-width:800px;margin:50px auto;'>";
        echo "<h2 style='color:#c53030;margin-top:0;'>PHP Fatal Error</h2>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($err['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($err['file']) . "</p>";
        echo "<p><strong>Line:</strong> " . $err['line'] . "</p>";
        echo "</div>";
    }
});

set_exception_handler(function(\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo "<div style='font-family:sans-serif;padding:30px;background:#fff5f5;border:2px solid #e53e3e;border-radius:8px;max-width:800px;margin:50px auto;'>";
    echo "<h2 style='color:#c53030;margin-top:0;'>Uncaught Exception</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (Line " . $e->getLine() . ")</p>";
    echo "<pre style='background:#f7fafc;padding:15px;border:1px solid #e2e8f0;border-radius:4px;overflow-x:auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
});

// 1. ตรวจสอบ Error Reporting ตาม Environment
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 2. กำหนด Timezone
date_default_timezone_set('Asia/Bangkok');

// 3. เริ่ม Session ปลอดภัย
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// 4. โหลด Autoloader สำหรับ App namespace เสมอ
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

// โหลด Composer dependencies เพิ่มเติมถ้ามี
$autoloadFile = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
}

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RbacMiddleware;
use App\Middleware\CsrfMiddleware;

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\SettingController;
use App\Controllers\FiscalYearController;
use App\Controllers\UserController;
use App\Controllers\AuditLogController;
use App\Controllers\BudgetController;
use App\Controllers\FundingSourceController;
use App\Controllers\ReceiptController;
use App\Controllers\DisbursementController;
use App\Controllers\AdvanceController;
use App\Controllers\AccountController;
use App\Controllers\BankController;
use App\Controllers\SpecialProgramController;
use App\Controllers\ProcurementController;
use App\Controllers\InventoryController;
use App\Controllers\ReportController;

// 5. คำนวณ Base URL อัตโนมัติ
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (str_starts_with($requestUri, '/financial/public')) {
    $baseUrl = '/financial/public';
} elseif (str_starts_with($requestUri, '/financial')) {
    $baseUrl = '/financial';
} else {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $baseUrl = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
}
define('BASE_URL', $baseUrl);

// 6. กำหนดเส้นทาง URL (Routes)
$router = new Router();

// Register Middlewares
$router->registerMiddleware('auth', AuthMiddleware::class);
$router->registerMiddleware('rbac', RbacMiddleware::class);
$router->registerMiddleware('csrf', CsrfMiddleware::class);

// --- Public & Auth Routes ---
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/index.php', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], ['csrf']);
$router->get('/logout', [AuthController::class, 'logout']);

// --- Authenticated User Routes ---
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$router->get('/profile', [AuthController::class, 'profile'], ['auth']);
$router->post('/profile/password', [AuthController::class, 'updatePassword'], ['auth', 'csrf']);

// --- Settings Module ---
$router->get('/settings', [SettingController::class, 'index'], ['auth', 'rbac:settings,view']);
$router->post('/settings', [SettingController::class, 'update'], ['auth', 'csrf', 'rbac:settings,edit']);

// --- Fiscal Years & Accounting Periods ---
$router->get('/fiscal-years', [FiscalYearController::class, 'index'], ['auth', 'rbac:settings,view']);
$router->post('/fiscal-years/store', [FiscalYearController::class, 'store'], ['auth', 'csrf', 'rbac:settings,create']);
$router->post('/fiscal-years/set-current', [FiscalYearController::class, 'setCurrent'], ['auth', 'csrf', 'rbac:settings,edit']);
$router->post('/fiscal-years/toggle-period-lock', [FiscalYearController::class, 'togglePeriodLock'], ['auth', 'csrf', 'rbac:settings,edit']);

// --- Users & Roles Module ---
$router->get('/users', [UserController::class, 'index'], ['auth', 'rbac:users,view']);
$router->get('/users/create', [UserController::class, 'create'], ['auth', 'rbac:users,create']);
$router->post('/users/store', [UserController::class, 'store'], ['auth', 'csrf', 'rbac:users,create']);
$router->get('/users/edit/{id}', [UserController::class, 'edit'], ['auth', 'rbac:users,edit']);
$router->post('/users/update/{id}', [UserController::class, 'update'], ['auth', 'csrf', 'rbac:users,edit']);
$router->post('/users/toggle-status', [UserController::class, 'toggleStatus'], ['auth', 'csrf', 'rbac:users,edit']);

// --- Audit Logs Module ---
$router->get('/audit-logs', [AuditLogController::class, 'index'], ['auth', 'rbac:audit_logs,view']);
$router->get('/audit-logs/export', [AuditLogController::class, 'exportCsv'], ['auth', 'rbac:audit_logs,export']);

// --- Budgets Module ---
$router->get('/budgets', [BudgetController::class, 'index'], ['auth', 'rbac:budgets,view']);
$router->post('/budgets/projects', [BudgetController::class, 'createProject'], ['auth', 'csrf', 'rbac:budgets,create']);
$router->post('/budgets/allocate', [BudgetController::class, 'allocate'], ['auth', 'csrf', 'rbac:budgets,edit']);
$router->post('/budgets/transfer', [BudgetController::class, 'transfer'], ['auth', 'csrf', 'rbac:budgets,edit']);
$router->post('/budgets/import-plan', [BudgetController::class, 'importPlan'], ['auth', 'csrf', 'rbac:budgets,create']);

// --- Funding Sources (ประเภทการเงินและแหล่งเงินสถานศึกษา) ---
$router->get('/funding-sources', [FundingSourceController::class, 'index'], ['auth', 'rbac:budgets,view']);
$router->post('/funding-sources/store', [FundingSourceController::class, 'store'], ['auth', 'csrf', 'rbac:budgets,create']);
$router->post('/funding-sources/update/{id}', [FundingSourceController::class, 'update'], ['auth', 'csrf', 'rbac:budgets,edit']);
$router->post('/funding-sources/toggle/{id}', [FundingSourceController::class, 'toggle'], ['auth', 'csrf', 'rbac:budgets,edit']);

// --- Receipts Module ---
$router->get('/receipts', [ReceiptController::class, 'index'], ['auth', 'rbac:receipts,view']);
$router->get('/receipts/create', [ReceiptController::class, 'create'], ['auth', 'rbac:receipts,create']);
$router->post('/receipts/store', [ReceiptController::class, 'store'], ['auth', 'csrf', 'rbac:receipts,create']);
$router->get('/receipts/show/{id}', [ReceiptController::class, 'show'], ['auth', 'rbac:receipts,view']);
$router->post('/receipts/cancel', [ReceiptController::class, 'cancel'], ['auth', 'csrf', 'rbac:receipts,cancel']);
$router->post('/receipts/deposit', [ReceiptController::class, 'deposit'], ['auth', 'csrf', 'rbac:receipts,edit']);

// --- Disbursements Module ---
$router->get('/disbursements', [DisbursementController::class, 'index'], ['auth', 'rbac:disbursements,view']);
$router->get('/disbursements/create', [DisbursementController::class, 'create'], ['auth', 'rbac:disbursements,create']);
$router->post('/disbursements/store', [DisbursementController::class, 'store'], ['auth', 'csrf', 'rbac:disbursements,create']);
$router->get('/disbursements/show/{id}', [DisbursementController::class, 'show'], ['auth', 'rbac:disbursements,view']);
$router->post('/disbursements/submit', [DisbursementController::class, 'submit'], ['auth', 'csrf', 'rbac:disbursements,propose']);
$router->post('/disbursements/audit', [DisbursementController::class, 'audit'], ['auth', 'csrf', 'rbac:disbursements,audit']);
$router->post('/disbursements/approve', [DisbursementController::class, 'approve'], ['auth', 'csrf', 'rbac:disbursements,approve']);
$router->post('/disbursements/disburse', [DisbursementController::class, 'disburse'], ['auth', 'csrf', 'rbac:disbursements,disburse']);
$router->post('/disbursements/reject', [DisbursementController::class, 'reject'], ['auth', 'csrf', 'rbac:disbursements,reject']);

// --- Advances Module ---
$router->get('/advances', [AdvanceController::class, 'index'], ['auth', 'rbac:advances,view']);
$router->get('/advances/create', [AdvanceController::class, 'create'], ['auth', 'rbac:advances,create']);
$router->post('/advances/store', [AdvanceController::class, 'store'], ['auth', 'csrf', 'rbac:advances,create']);
$router->get('/advances/show/{id}', [AdvanceController::class, 'show'], ['auth', 'rbac:advances,view']);
$router->post('/advances/approve', [AdvanceController::class, 'approve'], ['auth', 'csrf', 'rbac:advances,approve']);
$router->post('/advances/disburse', [AdvanceController::class, 'disburse'], ['auth', 'csrf', 'rbac:advances,disburse']);
$router->post('/advances/clearing', [AdvanceController::class, 'submitClearing'], ['auth', 'csrf', 'rbac:advances,clear']);
$router->post('/advances/approve-clearing', [AdvanceController::class, 'approveClearing'], ['auth', 'csrf', 'rbac:advances,approve']);

// --- Accounts & General Ledger Module ---
$router->get('/accounts', [AccountController::class, 'index'], ['auth', 'rbac:accounts,view']);
$router->get('/accounts/entry/{id}', [AccountController::class, 'showEntry'], ['auth', 'rbac:accounts,view']);
$router->get('/accounts/chart', [AccountController::class, 'chart'], ['auth', 'rbac:accounts,view']);
$router->post('/accounts/chart', [AccountController::class, 'storeAccount'], ['auth', 'csrf', 'rbac:accounts,create']);
$router->get('/accounts/ledger/{id}', [AccountController::class, 'ledger'], ['auth', 'rbac:accounts,view']);
$router->get('/accounts/trial-balance', [AccountController::class, 'trialBalance'], ['auth', 'rbac:accounts,view']);

// --- Bank Accounts & Reconciliation Module ---
$router->get('/banks', [BankController::class, 'index'], ['auth', 'rbac:bank_reconciliation,view']);
$router->post('/banks/store', [BankController::class, 'store'], ['auth', 'csrf', 'rbac:bank_reconciliation,create']);
$router->get('/banks/ledger/{id}', [BankController::class, 'ledger'], ['auth', 'rbac:bank_reconciliation,view']);
$router->get('/banks/statement/{id}', [BankController::class, 'statement'], ['auth', 'rbac:bank_reconciliation,view']);
$router->post('/banks/statement/{id}', [BankController::class, 'importStatement'], ['auth', 'csrf', 'rbac:bank_reconciliation,edit']);
$router->get('/banks/reconcile/{id}', [BankController::class, 'reconcile'], ['auth', 'rbac:bank_reconciliation,view']);

// --- Special Programs & Tuition Fees Module ---
$router->get('/special-programs', [SpecialProgramController::class, 'index'], ['auth', 'rbac:special_programs,view']);
$router->get('/special-programs/rates', [SpecialProgramController::class, 'rates'], ['auth', 'rbac:special_programs,view']);
$router->post('/special-programs/rates', [SpecialProgramController::class, 'storeRate'], ['auth', 'csrf', 'rbac:special_programs,create']);
$router->get('/special-programs/charges', [SpecialProgramController::class, 'charges'], ['auth', 'rbac:special_programs,view']);
$router->get('/special-programs/create-billing', [SpecialProgramController::class, 'createBilling'], ['auth', 'rbac:special_programs,create']);
$router->post('/special-programs/create-billing', [SpecialProgramController::class, 'storeBilling'], ['auth', 'csrf', 'rbac:special_programs,create']);
$router->post('/special-programs/pay', [SpecialProgramController::class, 'pay'], ['auth', 'csrf', 'rbac:special_programs,create']);
$router->post('/special-programs/waive', [SpecialProgramController::class, 'waive'], ['auth', 'csrf', 'rbac:special_programs,edit']);

// --- Procurement & Purchasing Module ---
$router->get('/procurement', [ProcurementController::class, 'index'], ['auth', 'rbac:procurement,view']);
$router->post('/procurement/requests', [ProcurementController::class, 'storePR'], ['auth', 'csrf', 'rbac:procurement,create']);
$router->post('/procurement/approve-pr', [ProcurementController::class, 'approvePR'], ['auth', 'csrf', 'rbac:procurement,approve']);
$router->post('/procurement/orders', [ProcurementController::class, 'storePO'], ['auth', 'csrf', 'rbac:procurement,create']);
$router->post('/procurement/inspections', [ProcurementController::class, 'storeInspection'], ['auth', 'csrf', 'rbac:procurement,audit']);
$router->post('/procurement/suppliers', [ProcurementController::class, 'storeSupplier'], ['auth', 'csrf', 'rbac:procurement,create']);

// --- Inventory & Assets Module ---
$router->get('/inventory', [InventoryController::class, 'index'], ['auth', 'rbac:inventory,view']);
$router->post('/inventory/assets', [InventoryController::class, 'storeAsset'], ['auth', 'csrf', 'rbac:inventory,create']);
$router->post('/inventory/movements', [InventoryController::class, 'storeMovement'], ['auth', 'csrf', 'rbac:inventory,edit']);

// --- Reports & Documents Module ---
$router->get('/reports', [ReportController::class, 'index'], ['auth', 'rbac:reports,view']);
$router->get('/reports/show/{code}', [ReportController::class, 'show'], ['auth', 'rbac:reports,view']);

// 7. Dispatch Request
try {
    $request = new Request();
    $router->dispatch($request);
} catch (\Throwable $e) {
    http_response_code(500);
    Response::abort(500, "เกิดข้อผิดพลาด: " . $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")");
}
