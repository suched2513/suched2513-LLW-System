<?php
session_start();
require_once 'config.php';

// Auth: super_admin only
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    header('Location: login.php'); exit();
}

$pageTitle = 'แดชบอร์ดกลาง';
$pageSubtitle = 'ระบบบริหารจัดการฐานข้อมูลและผู้ใช้งานส่วนกลาง';
$today = date('Y-m-d');
$msg = ''; $msgType = 'success';

// --- CRUD ---
$action = $_POST['action'] ?? '';
if ($action === 'add') {
    $un = $conn->real_escape_string(trim($_POST['username'])); $fn = $conn->real_escape_string(trim($_POST['firstname'])); $ln = $conn->real_escape_string(trim($_POST['lastname']));
    $rl = $conn->real_escape_string($_POST['role']); $st = $conn->real_escape_string($_POST['status']); $pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $sql = "INSERT INTO llw_users (username,password,firstname,lastname,role,status) VALUES ('$un','$pw','$fn','$ln','$rl','$st')";
    if ($conn->query($sql)) {
        if ($rl === 'att_teacher') {
            $name = $fn . ' ' . $ln; $uid = $conn->insert_id;
            $conn->query("INSERT IGNORE INTO att_teachers (name,username,password,llw_user_id) VALUES ('$name','$un','$pw',$uid)");
        }
        $msg = 'เพิ่มผู้ใช้สำเร็จ';
    } else { $msg = 'เกิดข้อผิดพลาด: ' . $conn->error; $msgType = 'error'; }
} elseif ($action === 'delete') {
    $id = (int)$_POST['user_id'];
    if ($id !== (int)$_SESSION['user_id']) { $conn->query("DELETE FROM llw_users WHERE user_id=$id"); $msg = 'ลบผู้ใช้สำเร็จ'; }
}

// Data Fetching
$users = $conn->query("SELECT u.* FROM llw_users u ORDER BY u.role, u.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// KPIs
$kpi = [
    'total_users'   => $conn->query("SELECT COUNT(*) FROM llw_users")->fetch_row()[0],
    'active_users'  => $conn->query("SELECT COUNT(*) FROM llw_users WHERE status='active'")->fetch_row()[0],
    'checkin_today' => $conn->query("SELECT COUNT(*) FROM wfh_timelogs WHERE log_date='$today'")->fetch_row()[0],
    'borrowed_cb'   => $conn->query("SELECT COUNT(*) FROM cb_borrow_logs WHERE status='Borrowed'")->fetch_row()[0],
    'att_today'     => $conn->query("SELECT COUNT(*) FROM att_attendance WHERE date='$today'")->fetch_row()[0],
];

require_once 'components/layout_start.php';
?>

<div class="flex flex-col gap-8">
    <!-- KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 flex items-center gap-6">
            <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl"><i class="bi bi-people-fill"></i></div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Users Overall</p>
                <h3 class="text-3xl font-black text-slate-800 tracking-tight"><?= $kpi['total_users'] ?></h3>
            </div>
        </div>
        <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 flex items-center gap-6">
            <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Active Now</p>
                <h3 class="text-3xl font-black text-slate-800 tracking-tight"><?= $kpi['active_users'] ?></h3>
            </div>
        </div>
        <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 flex items-center gap-6">
            <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-2xl"><i class="bi bi-laptop-fill"></i></div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">CB Borrowed</p>
                <h3 class="text-3xl font-black text-slate-800 tracking-tight"><?= $kpi['borrowed_cb'] ?></h3>
            </div>
        </div>
        <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 flex items-center gap-6">
            <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-2xl"><i class="bi bi-journal-check"></i></div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Att. Logs (Today)</p>
                <h3 class="text-3xl font-black text-slate-800 tracking-tight"><?= $kpi['att_today'] ?></h3>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- User Management Form (Left Sidebar style) -->
        <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 h-fit sticky top-28">
            <h3 class="text-lg font-black text-slate-800 mb-6 flex items-center gap-3">
                <i class="bi bi-person-plus-fill text-blue-600"></i> เพิ่มผู้ใช้งานใหม่
            </h3>
            <form method="POST" class="space-y-4 text-xs font-bold">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-400 block mb-2 px-1 uppercase tracking-wider">ชื่อจริง</label>
                        <input type="text" name="firstname" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-400 transition-all">
                    </div>
                    <div>
                        <label class="text-slate-400 block mb-2 px-1 uppercase tracking-wider">นามสกุล</label>
                        <input type="text" name="lastname" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-400 transition-all">
                    </div>
                </div>
                <div>
                    <label class="text-slate-400 block mb-2 px-1 uppercase tracking-wider">Username</label>
                    <input type="text" name="username" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 font-mono outline-none focus:ring-2 focus:ring-blue-400 transition-all">
                </div>
                <div>
                    <label class="text-slate-400 block mb-2 px-1 uppercase tracking-wider">Password</label>
                    <input type="password" name="password" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-400 transition-all">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-slate-400 block mb-2 px-1 uppercase tracking-wider">สิทธิ์การใช้งาน (Role)</label>
                        <select name="role" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-400 transition-all cursor-pointer">
                            <option value="super_admin">Super Admin</option>
                            <option value="wfh_admin">WFH Admin</option>
                            <option value="cb_admin">CB Admin</option>
                            <option value="att_teacher">Academic Teacher</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-slate-400 block mb-2 px-1 uppercase tracking-wider">สถานะ</label>
                        <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-400 transition-all cursor-pointer">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black text-[13px] shadow-xl shadow-blue-100 hover:bg-blue-700 hover:scale-[1.01] transition-all mt-4">
                    สร้างบัญชีผู้ใช้ใหม่
                </button>
            </form>
        </div>

        <!-- User List Table (Right Main) -->
        <div class="lg:col-span-2 bg-white rounded-[32px] shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-10 py-6 border-b border-slate-50 flex items-center justify-between">
                <h3 class="font-black text-slate-800">รายชื่อผู้ใช้งานทั้งหมด (<?= count($users) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">
                        <tr>
                            <th class="px-10 py-5 text-left">ผู้ใช้งาน</th>
                            <th class="px-6 py-5 text-left">สิทธิ์ / กลุ่ม</th>
                            <th class="px-6 py-5 text-center">สถานะ</th>
                            <th class="px-6 py-5 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($users as $u): 
                            $badgeClass = [
                                'super_admin' => 'bg-blue-600 text-white',
                                'wfh_admin'   => 'bg-emerald-50 text-emerald-600',
                                'cb_admin'    => 'bg-amber-50 text-amber-600',
                                'att_teacher' => 'bg-rose-50 text-rose-600',
                            ][$u['role']] ?? 'bg-slate-100 text-slate-500';
                        ?>
                        <tr class="hover:bg-slate-50 transition-all">
                            <td class="px-10 py-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center font-black text-sm"><?= mb_substr($u['firstname'],0,1) ?></div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-700"><?= htmlspecialchars($u['firstname'].' '.$u['lastname']) ?></span>
                                        <span class="text-[10px] font-mono font-bold text-slate-400"><?= $u['username'] ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-6">
                                <span class="px-3 py-1 rounded-xl font-black text-[9px] uppercase tracking-wider <?= $badgeClass ?>">
                                    <?= str_replace('_', ' ', $u['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-6 text-center">
                                <span class="px-2 py-0.5 rounded-lg text-[9px] font-black uppercase <?= $u['status']==='active' ? 'text-emerald-500' : 'text-rose-500' ?>">
                                    <?= $u['status'] ?>
                                </span>
                            </td>
                            <td class="px-10 py-6 text-right">
                                <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                <button onclick="deleteUser(<?= $u['user_id'] ?>, '<?= addslashes($u['username']) ?>')" class="p-2.5 text-rose-500 hover:bg-rose-50 rounded-xl transition-all"><i class="bi bi-trash3-fill"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function deleteUser(id, name) {
    Swal.fire({
        title: 'ยืนยันการลบ?', text: `คุณกำลังจะลบผู้ใช้ "${name}" ข้อมูลที่เกี่ยวข้องอาจได้รับผลกระทบ`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#e11d48', confirmButtonText: 'ลบทิ้งทันที', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if(r.isConfirmed){
            const f = document.createElement('form'); f.method='POST';
            f.innerHTML = `<input name="action" value="delete"><input name="user_id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }
    });
}
</script>

<?php 
if ($msg) echo "<script>Swal.fire({ icon: '$msgType', title: 'แจ้งเตือน', text: '$msg', timer: 2000, showConfirmButton: false });</script>";
require_once 'components/layout_end.php'; 
?>
