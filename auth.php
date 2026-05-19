<?php
/**
 * auth.php — WFH Login Processor (ใช้ตาราง llw_users)
 * Form action จาก login.php และ index_wfh.php
 */
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$username      = trim($_POST['username'] ?? '');
$password_plain = $_POST['password'] ?? '';

if (!$username || !$password_plain) {
    header('Location: login.php?error=1');
    exit();
}

// ─── 1. ดึงผู้ใช้จาก llw_users ────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM llw_users WHERE username = ? AND status = 'active' LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password_plain, $user['password'])) {
    header('Location: login.php?error=1');
    exit();
}

// ─── 2. ตั้งค่า Session ────────────────────────────────────────
$_SESSION['user_id']  = $user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['fullname'] = trim($user['firstname'] . ' ' . $user['lastname']);
$_SESSION['llw_role'] = $user['role'];

// Multi-role: ดึง role ทั้งหมดของผู้ใช้ (1:M) ใส่ใน session
// llw_role = primary role (จาก llw_users.role) — เก็บไว้เพื่อ backward-compat
// llw_roles = array ของทุก role สำหรับ fine-grained access check (llw_has_any_role)
$rolesAll = [$user['role']];
try {
    $rs = $conn->prepare("SELECT role FROM llw_user_roles WHERE user_id = ?");
    $rs->bind_param('i', $user['user_id']);
    $rs->execute();
    $rsResult = $rs->get_result();
    $tmp = [];
    while ($row = $rsResult->fetch_assoc()) $tmp[] = $row['role'];
    $rs->close();
    if (!empty($tmp)) $rolesAll = array_values(array_unique($tmp));
} catch (Throwable $e) { /* ตารางอาจยังไม่ถูก migrate — fallback ที่ role เดียว */ }
$_SESSION['llw_roles'] = $rolesAll;

// backward-compat: WFH admin pages ตรวจ role === 'admin'
$_SESSION['role'] = !empty(array_intersect($rolesAll, ['super_admin','wfh_admin'])) ? 'admin' : 'user';

// ─── 3. Attendance teacher: ดึง teacher_id จาก att_teachers ────
if (in_array($user['role'], ['att_teacher', 'super_admin', 'club_admin'])) {
    $t = $conn->prepare("SELECT id, name FROM att_teachers WHERE username = ? LIMIT 1");
    $t->bind_param('s', $username);
    $t->execute();
    $teacher = $t->get_result()->fetch_assoc();
    $t->close();
    if ($teacher) {
        $_SESSION['teacher_id']   = $teacher['id'];
        $_SESSION['teacher_name'] = $teacher['name'];
    }
}

// ─── 4. อัปเดต last_login ──────────────────────────────────────
$u = $conn->prepare("UPDATE llw_users SET last_login = NOW() WHERE user_id = ?");
$u->bind_param('i', $user['user_id']);
$u->execute();
$u->close();

// ─── 5. Redirect ตาม role (หรือ ?redirect= ถ้ามี) ──────────────
$roleMap = [
    'super_admin' => 'central_dashboard.php',
    'wfh_admin'   => 'admin/dashboard.php',
    'wfh_staff'   => 'user/dashboard.php',
    'cb_admin'    => 'chromebook/index.php',
    'att_teacher' => 'attendance_system/dashboard.php',
    'club_admin'  => 'club/index.php',
    'bus_admin'   => 'bus/admin/dashboard.php',
    'bus_finance' => 'bus/admin/dashboard.php',
];

// ถ้ามี redirect param ใน session (จาก login.php) และ path ปลอดภัย
$redirectTo = null;
if (!empty($_SESSION['login_redirect'])) {
    $rd = $_SESSION['login_redirect'];
    unset($_SESSION['login_redirect']);
    // อนุญาตเฉพาะ relative path ที่ขึ้นต้นด้วย / ป้องกัน open redirect
    if (str_starts_with($rd, '/') && !str_starts_with($rd, '//')) {
        $redirectTo = $rd;
    }
}

header('Location: ' . ($redirectTo ?? $roleMap[$user['role']] ?? 'index.php'));
exit();
