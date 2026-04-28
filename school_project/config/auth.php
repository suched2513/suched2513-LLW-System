<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/constants.php';

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) $roles = [$roles];
    $currentRole = $_SESSION['llw_role'] ?? $_SESSION['role'] ?? '';
    
    // Always allow super_admin
    if ($currentRole === 'super_admin' || $currentRole === 'admin') return;
    
    // Map platform roles to local roles for comparison
    $map = [
        'att_teacher'=>'teacher',
        'wfh_admin'=>'budget_officer',
        'wfh_staff'=>'teacher',
        'procurement_head'=>'procurement_head',
        'finance_head'=>'finance_head',
        'deputy_director'=>'deputy_director'
    ];
    $mappedRole = $map[$currentRole] ?? $currentRole;
    
    if (!in_array($currentRole, $roles) && !in_array($mappedRole, $roles)) {
        http_response_code(403);
        die('<div style="text-align:center;padding:50px;font-family:sans-serif"><h2>ไม่มีสิทธิ์เข้าถึง</h2><p>บทบาทของคุณคือ: ' . htmlspecialchars($currentRole ?: 'guest') . '</p><a href="' . BASE_URL . '/index.php">กลับหน้าหลัก</a></div>');
    }
}

function getCurrentUser() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $fullName = $_SESSION['full_name'] ?? '';
    if (!$fullName && isset($_SESSION['firstname'])) {
        $fullName = $_SESSION['firstname'] . ' ' . ($_SESSION['lastname'] ?? '');
    }
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? null,
        'full_name' => $fullName,
        'role'      => $_SESSION['llw_role'] ?? $_SESSION['role'] ?? null,
        'dept_id'   => $_SESSION['dept_id'] ?? null,
        'owner_name'=> $_SESSION['owner_name'] ?? null,
    ];
}

function csrfToken() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        die('CSRF token mismatch');
    }
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function roleLabel($r) {
    $map = [
        'admin'=>'ผู้ดูแลระบบ','super_admin'=>'ผู้ดูแลระบบสูงสุด',
        'teacher'=>'ครู','att_teacher'=>'ครู',
        'head'=>'หัวหน้าฝ่าย',
        'budget_officer'=>'เจ้าหน้าที่งบประมาณ (แผนงาน)','wfh_admin'=>'เจ้าหน้าที่งบประมาณ',
        'procurement_head'=>'หัวหน้าพัสดุ',
        'finance_head'=>'หัวหน้าการเงิน',
        'deputy_director'=>'รองผู้อำนวยการ',
        'director'=>'ผู้อำนวยการ'
    ];
    return $map[$r] ?? $r;
}
function statusLabel($s) {
    $map = [
        'draft'=>'ร่าง',
        'submitted'=>'รอฝ่ายงบประมาณ',
        'budget_approved'=>'รอฝ่ายพัสดุ',
        'procurement_approved'=>'รอฝ่ายการเงิน',
        'finance_approved'=>'รอ รอง ผอ.',
        'deputy_approved'=>'รอ ผอ. อนุมัติ',
        'approved'=>'อนุมัติแล้ว',
        'rejected'=>'ปฏิเสธ'
    ];
    return $map[$s] ?? $s;
}
function statusBadge($s) {
    $colors = ['draft'=>'secondary','submitted'=>'warning','approved'=>'success','rejected'=>'danger'];
    $c = $colors[$s] ?? 'secondary';
    return '<span class="badge bg-'.$c.'">'.statusLabel($s).'</span>';
}
function formatMoney($n) { return number_format((float)$n, 2); }
function formatDate($d) {
    if (!$d) return '-';
    $months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $t = strtotime($d);
    return date('j', $t) . ' ' . $months[(int)date('n', $t)] . ' ' . (date('Y', $t) + 543);
}
