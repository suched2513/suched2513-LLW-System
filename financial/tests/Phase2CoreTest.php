<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Helpers/functions.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Services/AuditTrailService.php';
require_once __DIR__ . '/../app/Core/Request.php';
require_once __DIR__ . '/../app/Core/Response.php';
require_once __DIR__ . '/../app/Core/Router.php';

use App\Services\AuthService;
use App\Services\AuditTrailService;
use App\Core\Request;
use App\Core\Router;

echo "=== เริ่มต้นการทดสอบ Phase 2: Core, Auth, RBAC & Router ===" . PHP_EOL;

$passCount = 0;
$failCount = 0;

function assert_test(string $name, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$name}" . PHP_EOL;
        $passCount++;
    } else {
        echo "  [FAIL] {$name}" . PHP_EOL;
        $failCount++;
    }
}

// 1. ทดสอบ CSRF Token
$token = AuthService::getCsrfToken();
assert_test("CSRF Token ไม่เป็นค่าว่างและมีความยาว 64 hex characters", !empty($token) && strlen($token) === 64);
assert_test("CSRF Token ผ่านการตรวจสอบความถูกต้อง", AuthService::validateCsrfToken($token));
assert_test("CSRF Token ผิดพลาดเมื่อส่งค่าปลอม", !AuthService::validateCsrfToken('fake-token-123'));

// 2. ทดสอบ Thai Fiscal Year Boundary
assert_test("วันที่ 2026-09-30 อยู่ในปีงบประมาณ 2569", thai_fiscal_year('2026-09-30') === 2569);
assert_test("วันที่ 2026-10-01 อยู่ในปีงบประมาณ 2570", thai_fiscal_year('2026-10-01') === 2570);
assert_test("วันที่ 2026-10-27 อยู่ในปีงบประมาณ 2570", thai_fiscal_year('2026-10-27') === 2570);

// 3. ทดสอบ Thai Date Formatting
$dateFormatted = thai_date('2026-10-27');
assert_test("แปลงวันที่ ค.ศ. 2026-10-27 เป็น 27 ตุลาคม 2569", $dateFormatted === '27 ตุลาคม 2569');

$dateTimeFormatted = thai_date('2026-10-27 14:30:00', true);
assert_test("แปลงวันและเวลาภาษาไทย", str_contains($dateTimeFormatted, '27 ตุลาคม 2569 14:30 น.'));

// 4. ทดสอบ Money Format
assert_test("จัดรูปแบบจำนวนเงิน money_format_th", money_format_th(1250000.5) === '1,250,000.50');
assert_test("จัดรูปแบบจำนวนเงิน 0", money_format_th(0) === '0.00');

// 5. ทดสอบ AuditTrail Sensitive Data Masking ผ่าน Reflection
$reflection = new ReflectionClass(AuditTrailService::class);
$maskMethod = $reflection->getMethod('maskSensitive');
$maskMethod->setAccessible(true);

$rawPayload = [
    'username' => 'somchai',
    'password' => 'Secret1234!',
    'password_hash' => '$2y$10$abcdef',
    'email' => 'somchai@llw.ac.th',
    'nested' => [
        'token' => 'jwt_secret_token',
        'safe_val' => '100'
    ]
];

$masked = $maskMethod->invoke(null, $rawPayload);
assert_test("AuditTrail บดบังรหัสผ่าน password เป็น ********", $masked['password'] === '********');
assert_test("AuditTrail บดบัง password_hash เป็น ********", $masked['password_hash'] === '********');
assert_test("AuditTrail บดบัง nested token เป็น ********", $masked['nested']['token'] === '********');
assert_test("AuditTrail คงค่าข้อมูลทั่วไปไว้ถูกต้อง", $masked['username'] === 'somchai' && $masked['nested']['safe_val'] === '100');

// 6. ทดสอบ Router Regex Matching
$router = new Router();
$matched = false;
$receivedId = null;

$fakeController = new class {
    public function showUser($id) {
        global $matched, $receivedId;
        $matched = true;
        $receivedId = $id;
    }
};

$router->get('/users/edit/{id}', [get_class($fakeController), 'showUser']);

// จำลอง Request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users/edit/42';
$req = new Request();
$router->dispatch($req);

assert_test("Router จับคู่พารามิเตอร์ URL {id} สำเร็จ", $matched && $receivedId === '42');

echo PHP_EOL . "สรุปผลการทดสอบ: ผ่าน {$passCount} รายการ, ล้มเหลว {$failCount} รายการ" . PHP_EOL;

if ($failCount > 0) {
    exit(1);
}
