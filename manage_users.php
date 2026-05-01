<?php
/**
 * manage_users.php — จัดการผู้ใช้งานระบบ LLW (llw_users)
 * เข้าถึงได้: super_admin เท่านั้น
 */
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: login.php'); exit();
}

$pdo = getPdo();
$msg = '';
$msgType = 'success';

// ─── POST Actions ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username  = trim($_POST['username']);
        $firstname = trim($_POST['firstname']);
        $lastname  = trim($_POST['lastname']);
        $role      = $_POST['role'];
        $password  = $_POST['password'];

        $allowed_roles = ['super_admin','wfh_admin','wfh_staff','cb_admin','att_teacher','bus_admin','bus_finance'];
        if (!in_array($role, $allowed_roles)) {
            $msg = 'Role ไม่ถูกต้อง'; $msgType = 'error';
        } elseif (strlen($password) < 6) {
            $msg = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'; $msgType = 'error';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO llw_users (username,firstname,lastname,password,role,status) VALUES (?,?,?,?,?, 'active')");
                $stmt->execute([$username, $firstname, $lastname, $hash, $role]);
                $msg = "เพิ่มผู้ใช้ {$firstname} {$lastname} สำเร็จแล้ว";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $msg = 'Username นี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น'; $msgType = 'error';
                } else {
                    error_log($e->getMessage());
                    $msg = 'เกิดข้อผิดพลาด'; $msgType = 'error';
                }
            }
        }
    }

    if ($action === 'toggle_status') {
        $uid = (int)$_POST['user_id'];
        $newStatus = $_POST['new_status'];
        if (in_array($newStatus, ['active','inactive'])) {
            $stmt = $pdo->prepare("UPDATE llw_users SET status = ? WHERE user_id = ?");
            $stmt->execute([$newStatus, $uid]);
            $msg = 'อัปเดตสถานะเรียบร้อย';
        }
    }

    if ($action === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $newp = trim($_POST['new_password']);
        if (strlen($newp) < 6) {
            $msg = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'; $msgType = 'error';
        } else {
            $hash = password_hash($newp, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE llw_users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hash, $uid]);
            $msg = 'รีเซ็ตรหัสผ่านเรียบร้อย';
        }
    }

    if ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        if ($uid === (int)$_SESSION['user_id']) {
            $msg = 'ไม่สามารถลบบัญชีของตัวเองได้'; $msgType = 'error';
        } else {
            $stmt = $pdo->prepare("DELETE FROM llw_users WHERE user_id = ?");
            $stmt->execute([$uid]);
            $msg = 'ลบผู้ใช้เรียบร้อยแล้ว';
        }
    }

    if ($action === 'clear_users') {
        $myId = (int)$_SESSION['user_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM llw_users WHERE user_id != ?");
            $stmt->execute([$myId]);
            $msg = 'ล้างข้อมูลผู้ใช้อื่นทั้งหมดเรียบร้อยแล้ว (ยกเว้นคุณ)';
        } catch (Exception $e) {
            $msg = 'ไม่สามารถล้างข้อมูลได้'; $msgType = 'error';
        }
    }
}

// ─── Fetch Users ────────────────────────────────────────────────
$users = $pdo->query("SELECT * FROM llw_users ORDER BY role, firstname")->fetchAll(PDO::FETCH_ASSOC);
$totalUsers   = count($users);
$activeUsers  = count(array_filter($users, fn($u) => $u['status'] === 'active'));

$roleLabel = [
    'super_admin' => ['label' => 'Super Admin', 'color' => 'bg-purple-100 text-purple-700'],
    'wfh_admin'   => ['label' => 'WFH Admin',   'color' => 'bg-blue-100 text-blue-700'],
    'wfh_staff'   => ['label' => 'WFH Staff',   'color' => 'bg-emerald-100 text-emerald-700'],
    'cb_admin'    => ['label' => 'CB Admin',     'color' => 'bg-cyan-100 text-cyan-700'],
    'att_teacher' => ['label' => 'ครูผู้สอน',   'color' => 'bg-rose-100 text-rose-700'],
    'bus_admin'   => ['label' => 'Bus Admin',    'color' => 'bg-orange-100 text-orange-700'],
    'bus_finance' => ['label' => 'Bus Finance',  'color' => 'bg-amber-100 text-amber-700'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการผู้ใช้ | LLW Platinum</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
    .sidebar-item-active { background-color: #1d4ed8; color: white; box-shadow: 0 10px 15px -3px rgba(37,99,235,0.2); }
</style>
</head>
<body class="flex min-h-screen">

<!-- Sidebar (เหมือนกับ central_dashboard.php) -->
<aside class="w-72 bg-white border-r border-slate-100 flex flex-col fixed h-full z-20">
    <div class="p-8 flex items-center gap-4">
        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xl">
            <i class="bi bi-rocket-takeoff-fill"></i>
        </div>
        <span class="text-xl font-black text-slate-800 tracking-tight">LLW Platinum</span>
    </div>
    <nav class="flex-1 px-6 space-y-2">
        <a href="central_dashboard.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
            <i class="bi bi-grid-fill text-lg"></i> แดชบอร์ด
        </a>
        <a href="manage_users.php" class="sidebar-item-active flex items-center gap-4 px-5 py-4 rounded-2xl font-bold transition-all">
            <i class="bi bi-people text-lg"></i> จัดการผู้ใช้
        </a>
        <a href="attendance_system/admin.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
            <i class="bi bi-building text-lg"></i> ห้องเรียน / นักเรียน
        </a>
        <a href="attendance_system/admin.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
            <i class="bi bi-book text-lg"></i> รายวิชา
        </a>
        <a href="admin/reports.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-800 transition-all">
            <i class="bi bi-file-earmark-bar-graph text-lg"></i> รายงาน
        </a>
    </nav>
    <div class="p-8 mt-auto space-y-4">
        <a href="change_password.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-indigo-500 hover:bg-indigo-50 transition-all">
            <i class="bi bi-key-fill text-lg"></i> เปลี่ยนรหัสผ่าน
        </a>
        <a href="logout.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl font-bold text-rose-500 hover:bg-rose-50 transition-all">
            <i class="bi bi-box-arrow-left text-lg"></i> ออกจากระบบ
        </a>
    </div>
</aside>

<!-- Main Content -->
<main class="ml-72 flex-1 p-10">

    <!-- Header -->
    <header class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight flex items-center gap-3">
                <i class="bi bi-people-fill text-blue-600"></i> จัดการผู้ใช้งาน
            </h1>
            <p class="text-slate-400 font-medium mt-1">เพิ่ม / แก้ไข / ปิดใช้งานบัญชีผู้ใช้ทั้งหมด</p>
        </div>
        <div class="flex items-center gap-4">
            <?php if ($msg): ?>
            <div class="px-5 py-3 rounded-2xl text-sm font-bold <?= $msgType === 'error' ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600' ?>">
                <i class="bi bi-<?= $msgType === 'error' ? 'exclamation-triangle' : 'check-circle' ?>-fill mr-1"></i>
                <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            <button onclick="confirmClear()"
                class="flex items-center gap-2 bg-rose-100 text-rose-500 px-6 py-3 rounded-2xl font-bold hover:bg-rose-500 hover:text-white transition-all">
                <i class="bi bi-trash3 text-lg"></i> ล้างทั้งหมด
            </button>
            <button onclick="document.getElementById('modal-import').classList.remove('hidden')"
                class="flex items-center gap-2 bg-emerald-500 text-white px-6 py-3 rounded-2xl font-bold shadow-lg shadow-emerald-100 hover:bg-emerald-600 hover:scale-[1.02] transition-all">
                <i class="bi bi-file-earmark-spreadsheet text-lg"></i> นำเข้า CSV
            </button>
            <button onclick="document.getElementById('modal-add').classList.remove('hidden')"
                class="flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] transition-all">
                <i class="bi bi-plus-lg text-lg"></i> เพิ่มผู้ใช้ใหม่
            </button>
        </div>
    </header>

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-6 mb-8 max-w-md">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ผู้ใช้ทั้งหมด</p>
            <p class="text-4xl font-black text-slate-800 mt-1"><?= $totalUsers ?></p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ใช้งานอยู่</p>
            <p class="text-4xl font-black text-emerald-600 mt-1"><?= $activeUsers ?></p>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100 flex items-center gap-3">
            <i class="bi bi-person-lines-fill text-blue-600"></i>
            <span class="font-black text-slate-700">รายชื่อผู้ใช้งานทั้งหมด</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">#</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">ชื่อ-สกุล / Username</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Role</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">สถานะ</th>
                        <th class="px-6 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">เข้าสู่ระบบล่าสุด</th>
                        <th class="px-6 py-5 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php foreach ($users as $i => $u):
                    $r = $roleLabel[$u['role']] ?? ['label' => $u['role'], 'color' => 'bg-slate-100 text-slate-600'];
                    $isMe = ((int)$u['user_id'] === (int)$_SESSION['user_id']);
                ?>
                <tr class="hover:bg-slate-50/50 transition-all group">
                    <td class="px-6 py-5 text-xs font-bold text-slate-400"><?= $i + 1 ?></td>
                    <td class="px-6 py-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center font-black text-sm">
                                <?= mb_substr($u['firstname'], 0, 1) ?>
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($u['firstname'].' '.$u['lastname'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-xs text-slate-400 font-medium">@<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-5">
                        <span class="px-3 py-1 rounded-full text-[10px] font-black <?= $r['color'] ?>">
                            <?= $r['label'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-5">
                        <?php if ($u['status'] === 'active'): ?>
                        <span class="flex items-center gap-1.5 text-emerald-600 text-xs font-bold">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span> ใช้งาน
                        </span>
                        <?php else: ?>
                        <span class="flex items-center gap-1.5 text-slate-400 text-xs font-bold">
                            <span class="w-2 h-2 bg-slate-300 rounded-full"></span> ปิดใช้งาน
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-5 text-xs text-slate-500 font-medium">
                        <?= $u['last_login'] ? date('d/m/').((int)date('Y')+543).' '.date('H:i', strtotime($u['last_login'])) : '—' ?>
                    </td>
                    <td class="px-6 py-5 text-center">
                        <div class="flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-all">
                            <!-- Reset Password -->
                            <button onclick="openReset(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['firstname'], ENT_QUOTES) ?>')"
                                class="w-8 h-8 bg-amber-50 text-amber-500 rounded-xl flex items-center justify-center hover:bg-amber-500 hover:text-white transition-all" title="รีเซ็ตรหัสผ่าน">
                                <i class="bi bi-key-fill text-xs"></i>
                            </button>
                            <!-- Toggle Status -->
                            <?php if (!$isMe): ?>
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit"
                                    class="w-8 h-8 rounded-xl flex items-center justify-center transition-all <?= $u['status'] === 'active' ? 'bg-slate-100 text-slate-400 hover:bg-slate-500 hover:text-white' : 'bg-emerald-50 text-emerald-500 hover:bg-emerald-500 hover:text-white' ?>"
                                    title="<?= $u['status'] === 'active' ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>">
                                    <i class="bi bi-<?= $u['status'] === 'active' ? 'pause-fill' : 'play-fill' ?> text-xs"></i>
                                </button>
                            </form>
                            <!-- Delete -->
                            <button onclick="confirmDelete(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['firstname'].' '.$u['lastname'], ENT_QUOTES) ?>')"
                                class="w-8 h-8 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all" title="ลบผู้ใช้">
                                <i class="bi bi-trash3-fill text-xs"></i>
                            </button>
                            <?php else: ?>
                            <span class="text-[10px] text-slate-300 font-bold">บัญชีของคุณ</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal: เพิ่มผู้ใช้ -->
<div id="modal-add" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-white/20 rounded-2xl flex items-center justify-center text-xl">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <div>
                    <h5 class="font-black text-lg">เพิ่มผู้ใช้งานใหม่</h5>
                    <p class="text-[10px] font-bold text-blue-100 uppercase tracking-widest">กรอกข้อมูลให้ครบถ้วน</p>
                </div>
            </div>
        </div>
        <form method="POST" class="p-8 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">ชื่อ</label>
                    <input type="text" name="firstname" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="ชื่อจริง">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">นามสกุล</label>
                    <input type="text" name="lastname" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="นามสกุล">
                </div>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">Username</label>
                <input type="text" name="username" required autocomplete="off" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="ตัวอักษรภาษาอังกฤษ ไม่มีช่องว่าง">
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">Role</label>
                <select name="role" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    <option value="wfh_staff">WFH Staff (บุคลากรทั่วไป)</option>
                    <option value="att_teacher">ครูผู้สอน (Attendance)</option>
                    <option value="wfh_admin">WFH Admin / ผอ.</option>
                    <option value="cb_admin">CB Admin (Chromebook)</option>
                    <option value="bus_admin">Bus Admin (รถรับส่ง)</option>
                    <option value="bus_finance">Bus Finance (การเงินรถรับส่ง)</option>
                    <option value="super_admin">Super Admin</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">รหัสผ่าน</label>
                <input type="password" name="password" required autocomplete="new-password" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="อย่างน้อย 6 ตัวอักษร">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-add').classList.add('hidden')"
                    class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-2xl font-black text-sm hover:bg-slate-200 transition-all">ยกเลิก</button>
                <button type="submit"
                    class="flex-[2] py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl font-black text-sm shadow-lg shadow-blue-200 hover:opacity-90 transition-all">
                    <i class="bi bi-person-plus-fill mr-1"></i> สร้างบัญชี
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset Password -->
<div id="modal-reset" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 p-6 text-white">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-white/20 rounded-2xl flex items-center justify-center text-xl">
                    <i class="bi bi-key-fill"></i>
                </div>
                <div>
                    <h5 class="font-black text-lg">รีเซ็ตรหัสผ่าน</h5>
                    <p id="reset-name" class="text-[10px] font-bold text-amber-100 uppercase tracking-widest"></p>
                </div>
            </div>
        </div>
        <form method="POST" class="p-8 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset-uid">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">รหัสผ่านใหม่</label>
                <input type="password" name="new_password" required autocomplete="new-password"
                    class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-400 outline-none transition-all"
                    placeholder="อย่างน้อย 6 ตัวอักษร">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('modal-reset').classList.add('hidden')"
                    class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-2xl font-black text-sm hover:bg-slate-200 transition-all">ยกเลิก</button>
                <button type="submit"
                    class="flex-[2] py-3 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-2xl font-black text-sm shadow-lg shadow-amber-200 hover:opacity-90 transition-all">
                    <i class="bi bi-key-fill mr-1"></i> รีเซ็ตรหัสผ่าน
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: นำเข้า CSV -->
<div id="modal-import" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-6 text-white">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-white/20 rounded-2xl flex items-center justify-center text-xl">
                    <i class="bi bi-file-earmark-arrow-up"></i>
                </div>
                <div>
                    <h5 class="font-black text-lg">นำเข้าผู้ใช้งานจาก CSV</h5>
                    <p class="text-[10px] font-bold text-emerald-100 uppercase tracking-widest">รองรับไฟล์ .csv (UTF-8)</p>
                </div>
            </div>
        </div>
        <div class="p-8 space-y-6">
            <div class="bg-emerald-50 rounded-2xl p-5 border border-emerald-100">
                <p class="text-xs font-bold text-emerald-700 mb-2"><i class="bi bi-info-circle-fill mr-1"></i>รูปแบบไฟล์ CSV (ไม่มีหัวตาราง):</p>
                <code class="text-[11px] text-emerald-600 bg-emerald-100/50 px-2 py-1 rounded-lg block leading-relaxed">
                    username, firstname, lastname, password, role
                </code>
                <p class="text-[10px] text-emerald-500 mt-2 italic">* Role: super_admin, wfh_admin, wfh_staff, cb_admin, att_teacher</p>
                <p class="text-[11px] text-emerald-600 mt-3">
                    <a href="api/download_user_template.php" class="inline-flex items-center gap-1 font-black underline decoration-emerald-200 hover:text-emerald-800 transition-colors">
                        <i class="bi bi-download"></i> ดาวน์โหลดไฟล์ตัวอย่าง (.csv)
                    </a>
                </p>
            </div>
            
            <div id="drop-zone" class="border-2 border-dashed border-emerald-200 rounded-2xl p-10 text-center hover:border-emerald-400 hover:bg-emerald-50/50 transition-all cursor-pointer"
                 onclick="document.getElementById('csv-file').click()">
                <i class="bi bi-cloud-upload text-4xl text-emerald-400 block mb-2"></i>
                <p class="text-sm font-bold text-slate-500">คลิกเพื่อเลือกไฟล์ CSV หรือลากมาวาง</p>
                <p class="text-xs text-slate-400 mt-1" id="file-name">ยังไม่ได้เลือกไฟล์</p>
            </div>
            <input type="file" id="csv-file" accept=".csv" class="hidden" onchange="document.getElementById('file-name').textContent = this.files[0].name">

            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('modal-import').classList.add('hidden')"
                    class="flex-1 py-3 bg-slate-100 text-slate-500 rounded-2xl font-black text-sm hover:bg-slate-200 transition-all">ยกเลิก</button>
                <button type="button" onclick="handleImport()"
                    class="flex-[2] py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-2xl font-black text-sm shadow-lg shadow-emerald-200/50 hover:opacity-90 transition-all">
                    <i class="bi bi-check-circle-fill mr-1"></i> เริ่มนำเข้าข้อมูล
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete form (hidden) -->
<form id="delete-form" method="POST" class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="delete-uid">
</form>

<script>
function confirmClear() {
    Swal.fire({
        title: 'ล้างข้อมูลผู้ใช้ทั้งหมด?',
        html: 'ระบบจะลบข้อมูลผู้ใช้อื่น <b>"ทั้งหมด"</b> ยกเว้นคุณ<br><small class="text-rose-500 font-bold">การดำเนินการนี้ไม่สามารถย้อนกลับได้!</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ยืนยันลบทั้งหมด',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-[2rem]', confirmButton: 'rounded-xl', cancelButton: 'rounded-xl' }
    }).then(r => {
        if (r.isConfirmed) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.innerHTML = '<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="clear_users">';
            document.body.appendChild(f);
            f.submit();
        }
    });
}

function openReset(uid, name) {
    document.getElementById('reset-uid').value = uid;
    document.getElementById('reset-name').textContent = name;
    document.getElementById('modal-reset').classList.remove('hidden');
}

function confirmDelete(uid, name) {
    Swal.fire({
        title: 'ลบผู้ใช้?',
        html: `ต้องการลบบัญชี <b>${name}</b> ออกจากระบบ?<br><small class="text-slate-400">การดำเนินการนี้ไม่สามารถย้อนกลับได้</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ลบเลย',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-[2rem]', confirmButton: 'rounded-xl', cancelButton: 'rounded-xl' }
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('delete-uid').value = uid;
            document.getElementById('delete-form').submit();
        }
    });
}

async function handleImport() {
    const fileInput = document.getElementById('csv-file');
    if (!fileInput.files.length) {
        Swal.fire({ icon: 'warning', title: 'กรุณาเลือกไฟล์', text: 'กรุณาเลือกไฟล์ CSV ก่อนดำเนินการ' });
        return;
    }

    const formData = new FormData();
    formData.append('csv_file', fileInput.files[0]);

    Swal.fire({
        title: 'กำลังนำเข้าข้อมูล...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch('api/import_users.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        let res;
        try {
            res = JSON.parse(text);
        } catch (e) {
            console.error('Raw Response:', text);
            Swal.fire({
                icon: 'error',
                title: 'API Error',
                html: `<div class="text-left text-xs bg-slate-50 p-4 rounded-xl overflow-auto max-h-40"><code>${text.substring(0, 500)}</code></div>`,
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        if (res.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: res.message,
                confirmButtonColor: '#059669'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ผิดพลาด',
                text: res.message
            });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้: ' + e.message });
    }
}
</script>

</body>
</html>
