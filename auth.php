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

// backward-compat: WFH admin pages ตรวจ role === 'admin'
$_SESSION['role'] = in_array($user['role'], ['super_admin','wfh_admin']) ? 'admin' : 'user';

// ─── 3. Attendance teacher: ดึง teacher_id จาก att_teachers ────
if (in_array($user['role'], ['att_teacher','super_admin'])) {
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
