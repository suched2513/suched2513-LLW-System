<?php
/**
 * Public Front Controller
 * ระบบบริหารการเงินและบัญชีสถานศึกษา โรงเรียนละลมวิทยา
 */

declare(strict_types=1);

// 1. ตรวจสอบ Error Reporting ตาม Environment
ini_set('display_errors', '0');
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

// 4. โหลด Autoloader
$autoloadFile = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
} else {
    // Manual PSR-4 Fallback autoloader ถ้ายังไม่ได้รัน composer install
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
            require $file;
        }
    });

    require_once __DIR__ . '/../app/Helpers/functions.php';
}

use App\Core\Request;
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

// 5. คำนวณ Base URL อัตโนมัติ
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$baseUrl = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
define('BASE_URL', $baseUrl);

// 6. กำหนดเส้นทาง URL (Routes)
$router = new Router();

// Register Middlewares
$router->registerMiddleware('auth', AuthMiddleware::class);
$router->registerMiddleware('rbac', RbacMiddleware::class);
$router->registerMiddleware('csrf', CsrfMiddleware::class);

// --- Public & Auth Routes ---
$router->get('/', [AuthController::class, 'showLogin']);
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

// 7. Dispatch Request
$request = new Request();
$router->dispatch($request);
