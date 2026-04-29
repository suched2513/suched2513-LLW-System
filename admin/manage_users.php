<?php
session_start();
require_once '../config.php';

// Auth guard
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header("Location: ../login.php"); exit();
}

$msg = '';

// ===== เพิ่มผู้ใช้ =====
$allowed_roles = ['admin', 'user'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $un   = trim($_POST['username'] ?? '');
        $pw   = password_hash($_POST['password'] ?? '', PASSWORD_BCRYPT);
        $fn   = trim($_POST['firstname'] ?? '');
        $ln   = trim($_POST['lastname'] ?? '');
        $pos  = trim($_POST['position'] ?? '');
        $dept = (int)($_POST['dept_id'] ?? 0);
        $role = in_array($_POST['role'] ?? '', $allowed_roles) ? $_POST['role'] : 'user';
        $stmt = $conn->prepare("INSERT INTO wfh_users (username,password,firstname,lastname,position,dept_id,role) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('sssssis', $un, $pw, $fn, $ln, $pos, $dept, $role);
        $stmt->execute();
        $stmt->close();
        $msg = 'เพิ่มผู้ใช้สำเร็จ';

    } elseif ($_POST['action'] === 'delete') {
        $uid = (int)$_POST['user_id'];
        $stmt = $conn->prepare("DELETE FROM wfh_users WHERE user_id=? AND role!='admin'");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();
        $msg = 'warning:ลบผู้ใช้เรียบร้อยแล้ว';

    } elseif ($_POST['action'] === 'reset_pw') {
        $uid = (int)$_POST['user_id'];
        $pw  = password_hash('123456', PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE wfh_users SET password=? WHERE user_id=?");
        $stmt->bind_param('si', $pw, $uid);
        $stmt->execute();
        $stmt->close();
        $msg = 'info:รีเซ็ตรหัสผ่านเป็น 123456 แล้ว';
    }
}

$users = $conn->query("
    SELECT u.*, d.dept_name
    FROM wfh_users u
    LEFT JOIN wfh_departments d ON u.dept_id = d.dept_id
    ORDER BY u.role DESC, u.user_id ASC
")->fetch_all(MYSQLI_ASSOC);

$departments = $conn->query("SELECT * FROM wfh_departments")->fetch_all(MYSQLI_ASSOC);

// Layout variables
$pageTitle = 'จัดการบุคลากร';
$pageSubtitle = 'เพิ่ม ลบ และแก้ไขข้อมูลบุคลากรในระบบ';
$activeSystem = 'wfh';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Alert Handler -->
<?php if ($msg): ?>
<script>
    Swal.fire({
        icon: '<?= (strpos($msg, "error:") === 0) ? "error" : ((strpos($msg, "warning:") === 0) ? "warning" : "success") ?>',
        title: 'ดำเนินการสำเร็จ',
        text: '<?= str_replace(["error:", "warning:", "info:"], "", $msg) ?>',
        confirmButtonColor: '#4f46e5'
    });
</script>
<?php endif; ?>

<div class="space-y-8">
    
    <!-- Quick Add Form -->
    <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-xl shadow-indigo-100/40 p-8 border border-white/60">
        <div class="flex items-center gap-4 mb-8">
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <div>
                <h3 class="text-xl font-black text-slate-800">เพิ่มบุคลากรใหม่</h3>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Fast Registration</p>
            </div>
        </div>

        <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <input type="hidden" name="action" value="add">
            
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Username / ID</label>
                <input class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold" name="username" placeholder="ระบุชื่อผู้ใช้" required>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Password</label>
                <input class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold" name="password" type="password" placeholder="ระบุรหัสผ่าน" required>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">First Name</label>
                <input class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold" name="firstname" placeholder="ชื่อ" required>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Last Name</label>
                <input class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold" name="lastname" placeholder="นามสกุล" required>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Position</label>
                <input class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold" name="position" placeholder="ตำแหน่ง">
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Department</label>
                <select class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold appearance-none" name="dept_id">
                    <option value="">-- ฝ่าย --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Access Role</label>
                <select class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold appearance-none" name="role">
                    <option value="user">ครู/บุคลากร</option>
                    <option value="admin">ผู้ดูแลระบบ</option>
                </select>
            </div>

            <div class="flex items-end">
                <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-3 rounded-2xl shadow-lg shadow-indigo-100 transition-all hover:scale-[1.02] active:scale-[0.98]">
                    <i class="bi bi-plus-circle me-1"></i> เพิ่มบุคลากร
                </button>
            </div>
        </form>
    </div>

    <!-- User Table -->
    <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 overflow-hidden border border-slate-100">
        <div class="px-8 py-6 border-bottom border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-3">
                <i class="bi bi-person-lines-fill text-indigo-600 text-xl"></i>
                <h3 class="text-lg font-black text-slate-800">รายชื่อบุคลากรทั้งหมด</h3>
            </div>
            <span class="px-4 py-1 bg-indigo-100 text-indigo-600 rounded-full text-[10px] font-black uppercase tracking-widest">
                Total: <?= count($users) ?> Members
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 border-y border-slate-100">
                        <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">#</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Username</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ชื่อ-สกุล</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ตำแหน่ง / ฝ่าย</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">บทบาท</th>
                        <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $i => $u): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors group">
                        <td class="px-8 py-4 text-xs font-bold text-slate-400"><?= $i+1 ?></td>
                        <td class="px-6 py-4">
                            <code class="px-2 py-1 bg-slate-100 text-indigo-600 rounded text-[11px] font-bold">
                                <?= htmlspecialchars($u['username']) ?>
                            </code>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-sm font-black text-slate-800 tracking-tight"><?= htmlspecialchars($u['firstname'].' '.$u['lastname']) ?></p>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-slate-600 truncate"><?= htmlspecialchars($u['position'] ?? '-') ?></span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tight truncate"><?= htmlspecialchars($u['dept_name'] ?? 'ไม่ระบุฝ่าย') ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-black uppercase tracking-wider">Administrator</span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-slate-100 text-slate-500 rounded-full text-[10px] font-black uppercase tracking-wider">Staff</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-8 py-4 text-right">
                            <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <form method="POST" class="inline" onsubmit="return confirm('รีเซ็ตรหัสผ่านเป็น 123456?')">
                                    <input type="hidden" name="action" value="reset_pw">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <button class="w-8 h-8 flex items-center justify-center bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-600 hover:text-white transition-all shadow-sm">
                                        <i class="bi bi-key-fill text-sm"></i>
                                    </button>
                                </form>
                                <?php if ($u['role'] !== 'admin'): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('ยืนยันการลบ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <button class="w-8 h-8 flex items-center justify-center bg-rose-50 text-rose-500 rounded-lg hover:bg-rose-500 hover:text-white transition-all shadow-sm">
                                        <i class="bi bi-trash-fill text-sm"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
