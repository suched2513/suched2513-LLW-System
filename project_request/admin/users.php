<?php
session_start();
$pageTitle = 'จัดการผู้ใช้งาน';
$pageSubtitle = 'เพิ่ม แก้ไข และลบบัญชีผู้ใช้งานในระบบ';
require_once __DIR__ . '/../components/layout_start.php';

$pdo = getPdo();
$message = '';

// Handle CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        $dept = $_POST['department'];
        $password = $_POST['password'] ?? '';

        try {
            if ($id) {
                // Update
                if (!empty($password)) {
                    $sql = "UPDATE users SET username=?, password=?, full_name=?, role=?, department=? WHERE id=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT), $full_name, $role, $dept, $id]);
                } else {
                    $sql = "UPDATE users SET username=?, full_name=?, role=?, department=? WHERE id=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$username, $full_name, $role, $dept, $id]);
                }
                $message = 'อัปเดตข้อมูลสำเร็จ';
            } else {
                // Insert
                $sql = "INSERT INTO users (username, password, full_name, role, department) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT), $full_name, $role, $dept]);
                $message = 'เพิ่มผู้ใช้สำเร็จ';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $message = 'ลบผู้ใช้สำเร็จ';
    }
}

// Fetch Users
$stmt = $pdo->query("SELECT * FROM users ORDER BY role, full_name");
$users = $stmt->fetchAll();
?>

<div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h3 class="text-xl font-black text-slate-800">รายชื่อผู้ใช้งาน</h3>
            <p class="text-sm text-slate-400 font-medium">จัดการสิทธิ์การเข้าถึงระบบ</p>
        </div>
        <button onclick="openModal()" class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all flex items-center gap-2">
            <i class="bi bi-person-plus-fill"></i>
            เพิ่มผู้ใช้งานใหม่
        </button>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-4 bg-blue-50 text-blue-600 rounded-2xl font-bold text-sm">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left border-b border-slate-50">
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ชื่อ-นามสกุล</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ชื่อผู้ใช้</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">บทบาท</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ฝ่าย/กลุ่มงาน</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4 text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($users as $u): ?>
                <tr class="group hover:bg-slate-50/50 transition-all">
                    <td class="py-4 px-4">
                        <p class="font-bold text-slate-700"><?= htmlspecialchars($u['full_name']) ?></p>
                    </td>
                    <td class="py-4 px-4">
                        <code class="bg-slate-100 px-2 py-1 rounded text-xs text-slate-500"><?= htmlspecialchars($u['username']) ?></code>
                    </td>
                    <td class="py-4 px-4">
                        <?php 
                            $roleColors = [
                                'admin' => 'bg-rose-50 text-rose-600',
                                'teacher' => 'bg-blue-50 text-blue-600',
                                'director' => 'bg-emerald-50 text-emerald-600'
                            ];
                        ?>
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $roleColors[$u['role']] ?>">
                            <?= $u['role'] ?>
                        </span>
                    </td>
                    <td class="py-4 px-4">
                        <p class="text-sm text-slate-500 font-medium"><?= htmlspecialchars($u['department']) ?></p>
                    </td>
                    <td class="py-4 px-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick='editUser(<?= json_encode($u) ?>)' class="w-8 h-8 flex items-center justify-center bg-slate-50 text-slate-400 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('ยืนยันการลบผู้ใช้งาน?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="w-8 h-8 flex items-center justify-center bg-slate-50 text-slate-400 rounded-lg hover:bg-rose-50 hover:text-rose-600 transition-all">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="userModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-lg rounded-[2.5rem] shadow-2xl p-8 sm:p-10 animate-fade-in-up">
        <div class="flex items-center justify-between mb-8">
            <h4 id="modalTitle" class="text-xl font-black text-slate-800">เพิ่มผู้ใช้งาน</h4>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600"><i class="bi bi-x-lg"></i></button>
        </div>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="userId">

            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" id="username" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all" required>
            </div>

            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">รหัสผ่าน <span id="pwdLabel" class="text-slate-300 font-normal lowercase">(ปล่อยว่างหากไม่ต้องการเปลี่ยน)</span></label>
                <input type="password" name="password" id="password" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all">
            </div>

            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">ชื่อ-นามสกุล</label>
                <input type="text" name="full_name" id="fullName" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all" required>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">บทบาท</label>
                    <select name="role" id="role" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all">
                        <option value="teacher">Teacher</option>
                        <option value="admin">Admin</option>
                        <option value="director">Director</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">ฝ่าย</label>
                    <input type="text" name="department" id="dept" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all">
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black text-sm shadow-xl shadow-blue-200 hover:bg-blue-700 transition-all mt-4">
                บันทึกข้อมูล
            </button>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = 'เพิ่มผู้ใช้งาน';
    document.getElementById('userId').value = '';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('fullName').value = '';
    document.getElementById('role').value = 'teacher';
    document.getElementById('dept').value = '';
    document.getElementById('password').required = true;
    document.getElementById('pwdLabel').style.display = 'none';
    document.getElementById('userModal').classList.remove('hidden');
    document.getElementById('userModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('userModal').classList.add('hidden');
    document.getElementById('userModal').classList.remove('flex');
}

function editUser(user) {
    document.getElementById('modalTitle').innerText = 'แก้ไขผู้ใช้งาน';
    document.getElementById('userId').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('pwdLabel').style.display = 'inline';
    document.getElementById('fullName').value = user.full_name;
    document.getElementById('role').value = user.role;
    document.getElementById('dept').value = user.department;
    document.getElementById('userModal').classList.remove('hidden');
    document.getElementById('userModal').classList.add('flex');
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
