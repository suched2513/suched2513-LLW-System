<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin']);
$db = getDB();
$depts = $db->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();
$msg = '';
if ($_SERVER['REQUEST_METHOD']=== 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action==='create') {
        $pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $fullName = trim($_POST['full_name'] ?? '');
        $parts = preg_split('/\s+/', $fullName, 2);
        $firstname = $parts[0] ?? '';
        $lastname  = $parts[1] ?? '';
        
        // Map budget role to llw_role
        $roleMap = ['teacher'=>'att_teacher', 'head'=>'att_teacher', 'budget_officer'=>'wfh_admin', 'director'=>'super_admin', 'admin'=>'super_admin'];
        $llwRole = $roleMap[$_POST['role']] ?? 'att_teacher';
        
        $s = $db->prepare("INSERT INTO llw_users (username,password,firstname,lastname,role,department_id,owner_name,status) VALUES (?,?,?,?,?,?,?, 'active')");
        $s->execute([$_POST['username'],$pw,$firstname,$lastname,$llwRole,$_POST['dept_id']?:null,$_POST['owner_name']]);
        flashMessage('success','สร้างผู้ใช้เรียบร้อย');
    } elseif ($action==='toggle') {
        $uid = (int)$_POST['user_id'];
        $db->prepare("UPDATE llw_users SET status=CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE user_id=?")->execute([$uid]);
        flashMessage('success','อัปเดตสถานะเรียบร้อย');
    }
    header('Location: ' . BASE_URL . '/admin/users.php'); exit;
}
$users = $db->query("SELECT u.*, user_id AS id, CONCAT(firstname, ' ', lastname) AS full_name, d.name AS dept_name FROM llw_users u LEFT JOIN departments d ON u.department_id=d.id ORDER BY u.role, u.firstname")->fetchAll();
renderHead('จัดการผู้ใช้');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('จัดการผู้ใช้'); echo '<div class="page-content">'; showFlash();
?>
<div class="row g-3">
<div class="col-md-4">
<div class="card"><div class="card-header">เพิ่มผู้ใช้ใหม่</div><div class="card-body">
<form method="POST">
<input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
<input type="hidden" name="action" value="create">
<div class="mb-2"><label class="form-label">ชื่อผู้ใช้</label><input type="text" name="username" class="form-control" required></div>
<div class="mb-2"><label class="form-label">รหัสผ่าน</label><input type="password" name="password" class="form-control" required></div>
<div class="mb-2"><label class="form-label">ชื่อ-สกุล</label><input type="text" name="full_name" class="form-control" required></div>
<div class="mb-2"><label class="form-label">ชื่อในระบบงบ (owner_name)</label><input type="text" name="owner_name" class="form-control" placeholder="ต้องตรงกับชื่อในงบประมาณ"></div>
<div class="mb-2"><label class="form-label">สิทธิ์</label>
<select name="role" class="form-select"><option value="teacher">ครู</option><option value="head">หัวหน้าฝ่าย</option><option value="budget_officer">เจ้าหน้าที่งบ</option><option value="director">ผู้อำนวยการ</option><option value="admin">Admin</option></select></div>
<div class="mb-3"><label class="form-label">ฝ่าย</label>
<select name="dept_id" class="form-select"><option value="">-- ไม่ระบุ --</option>
<?php foreach ($depts as $d): ?><option value="<?=$d['id']?>"><?=h($d['name'])?></option><?php endforeach; ?>
</select></div>
<button type="submit" class="btn btn-primary w-100">เพิ่มผู้ใช้</button>
</form>
</div></div>
</div>
<div class="col-md-8">
<div class="card"><div class="card-header">ผู้ใช้ทั้งหมด (<?=count($users)?> คน)</div><div class="card-body p-0">
<table class="table table-hover mb-0">
<thead><tr><th class="ps-3">ชื่อ-สกุล</th><th>Username</th><th>สิทธิ์</th><th>ฝ่าย</th><th class="text-center">สถานะ</th><th class="text-center">จัดการ</th></tr></thead>
<tbody>
<?php foreach ($users as $u): ?>
<tr>
<td class="ps-3"><div style="font-weight:500"><?=h($u['full_name'])?></div><div style="font-size:12px;color:#64748b"><?=h($u['owner_name']??'')?></div></td>
<td><?=h($u['username'])?></td>
<td><span class="badge bg-info text-dark"><?=roleLabel($u['role'])?></span></td>
<td><?=h($u['dept_name']??'ไม่ระบุ')?></td>
<td class="text-center"><?=$u['status']==='active'?'<span class="badge bg-success">ใช้งาน</span>':'<span class="badge bg-secondary">ปิดใช้</span>'?></td>
<td class="text-center">
<form method="POST" class="d-inline">
<input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
<input type="hidden" name="action" value="toggle">
<input type="hidden" name="user_id" value="<?=$u['user_id']?>">
<button type="submit" class="btn btn-sm btn-outline-<?=$u['status']==='active'?'warning':'success'?>"><?=$u['status']==='active'?'ปิด':'เปิด'?></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>
</div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>