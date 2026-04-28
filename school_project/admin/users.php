<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin']);
$db = getDB();
$depts = $db->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();

$roleMap = [
    'teacher' => 'att_teacher',
    'head' => 'att_teacher',
    'budget_officer' => 'wfh_admin',
    'procurement_head' => 'procurement_head',
    'finance_head' => 'finance_head',
    'deputy_director' => 'deputy_director',
    'director' => 'super_admin',
    'admin' => 'super_admin'
];

if ($_SERVER['REQUEST_METHOD']=== 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action==='create') {
        $pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $fullName = trim($_POST['full_name'] ?? '');
        $parts = preg_split('/\s+/', $fullName, 2);
        $firstname = $parts[0] ?? '';
        $lastname  = $parts[1] ?? '';
        $llwRole = $roleMap[$_POST['role']] ?? 'att_teacher';
        
        try {
            $s = $db->prepare("INSERT INTO llw_users (username,password,firstname,lastname,role,department_id,owner_name,status) VALUES (?,?,?,?,?,?,?, 'active')");
            $s->execute([$_POST['username'],$pw,$firstname,$lastname,$llwRole,$_POST['dept_id']?:null,$_POST['owner_name']]);
            flashMessage('success','สร้างผู้ใช้เรียบร้อย');
        } catch (Exception $e) {
            flashMessage('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    } elseif ($action==='update') {
        $uid = (int)$_POST['user_id'];
        $fullName = trim($_POST['full_name'] ?? '');
        $parts = preg_split('/\s+/', $fullName, 2);
        $firstname = $parts[0] ?? '';
        $lastname  = $parts[1] ?? '';
        $llwRole = $roleMap[$_POST['role']] ?? 'att_teacher';

        try {
            $sql = "UPDATE llw_users SET firstname=?, lastname=?, role=?, department_id=?, owner_name=?";
            $params = [$firstname, $lastname, $llwRole, $_POST['dept_id'] ?: null, $_POST['owner_name']];
            if (!empty($_POST['password'])) {
                $sql .= ", password=?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            $sql .= " WHERE user_id=?";
            $params[] = $uid;
            $db->prepare($sql)->execute($params);
            flashMessage('success','อัปเดตข้อมูลผู้ใช้เรียบร้อย');
        } catch (Exception $e) {
            flashMessage('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
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
<div class="card shadow-sm"><div class="card-header bg-white fw-bold">เพิ่มผู้ใช้ใหม่</div><div class="card-body">
<form method="POST">
<input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
<input type="hidden" name="action" value="create">
<div class="mb-2"><label class="form-label small">ชื่อผู้ใช้ (Username)</label><input type="text" name="username" class="form-control" required></div>
<div class="mb-2"><label class="form-label small">รหัสผ่าน</label><input type="password" name="password" class="form-control" required></div>
<div class="mb-2"><label class="form-label small">ชื่อ-นามสกุล</label><input type="text" name="full_name" class="form-control" required></div>
<div class="mb-2"><label class="form-label small">ชื่อในระบบงบ (owner_name)</label><input type="text" name="owner_name" class="form-control" placeholder="ต้องตรงกับชื่อในงบประมาณ"></div>
<div class="mb-2"><label class="form-label small">สิทธิ์/บทบาท</label>
<select name="role" class="form-select">
    <?php foreach($roleMap as $k => $v): ?>
    <option value="<?= $k ?>"><?= roleLabel($k) ?></option>
    <?php endforeach; ?>
</select></div>
<div class="mb-3"><label class="form-label small">ฝ่าย/กลุ่มสาระ</label>
<select name="dept_id" class="form-select"><option value="">-- ไม่ระบุ --</option>
<?php foreach ($depts as $d): ?><option value="<?=$d['id']?>"><?=h($d['name'])?></option><?php endforeach; ?>
</select></div>
<button type="submit" class="btn btn-primary w-100 shadow-sm"><i class="bi bi-person-plus me-1"></i>เพิ่มผู้ใช้</button>
</form>
</div></div>
</div>

<div class="col-md-8">
<div class="card shadow-sm"><div class="card-header bg-white fw-bold">ผู้ใช้ทั้งหมด (<?=count($users)?> คน)</div><div class="card-body p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead class="table-light"><tr><th class="ps-3">ชื่อ-นามสกุล</th><th>Username</th><th>สิทธิ์</th><th>ฝ่าย</th><th class="text-center">สถานะ</th><th class="text-center">จัดการ</th></tr></thead>
<tbody>
<?php foreach ($users as $u): 
    // Find reverse map for select
    $rKey = array_search($u['role'], $roleMap);
    if ($u['role'] === 'att_teacher') $rKey = 'teacher';
    if ($u['role'] === 'super_admin') $rKey = 'director';
?>
<tr>
<td class="ps-3"><div class="fw-bold"><?=h($u['full_name'])?></div><div class="text-muted small"><?=h($u['owner_name']??'')?></div></td>
<td><code><?=h($u['username'])?></code></td>
<td><span class="badge bg-soft-info text-info border border-info-subtle px-2"><?=roleLabel($u['role'])?></span></td>
<td class="small"><?=h($u['dept_name']??'ไม่ระบุ')?></td>
<td class="text-center"><?=$u['status']==='active'?'<span class="badge bg-success">ใช้งาน</span>':'<span class="badge bg-secondary">ปิดใช้</span>'?></td>
<td class="text-center">
  <div class="btn-group">
    <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode([
        "id" => $u['user_id'],
        "full_name" => $u['full_name'],
        "username" => $u['username'],
        "owner_name" => $u['owner_name'],
        "role" => $rKey,
        "dept_id" => $u['department_id']
    ]) ?>)'>แก้ไข</button>
    <form method="POST" class="d-inline">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="user_id" value="<?=$u['user_id']?>">
      <button type="submit" class="btn btn-sm btn-outline-<?=$u['status']==='active'?'warning':'success'?>"><?=$u['status']==='active'?'ปิด':'เปิด'?></button>
    </form>
  </div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div></div>
</div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">แก้ไขข้อมูลผู้ใช้</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="user_id" id="edit_user_id">
          
          <div class="mb-3"><label class="form-label">Username</label><input type="text" id="edit_username" class="form-control" readonly></div>
          <div class="mb-3"><label class="form-label">ชื่อ-นามสกุล</label><input type="text" name="full_name" id="edit_full_name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">ชื่อในระบบงบ (owner_name)</label><input type="text" name="owner_name" id="edit_owner_name" class="form-control"></div>
          <div class="mb-3"><label class="form-label">เปลี่ยนรหัสผ่าน (เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)</label><input type="password" name="password" class="form-control"></div>
          
          <div class="mb-3"><label class="form-label">สิทธิ์/บทบาท</label>
          <select name="role" id="edit_role" class="form-select">
              <?php foreach($roleMap as $k => $v): ?>
              <option value="<?= $k ?>"><?= roleLabel($k) ?></option>
              <?php endforeach; ?>
          </select></div>
          
          <div class="mb-3"><label class="form-label">ฝ่าย/กลุ่มสาระ</label>
          <select name="dept_id" id="edit_dept_id" class="form-select"><option value="">-- ไม่ระบุ --</option>
          <?php foreach ($depts as $d): ?><option value="<?=$d['id']?>"><?=h($d['name'])?></option><?php endforeach; ?>
          </select></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const editModal = new bootstrap.Modal(document.getElementById('editModal'));
function editUser(u) {
    document.getElementById('edit_user_id').value = u.id;
    document.getElementById('edit_username').value = u.username;
    document.getElementById('edit_full_name').value = u.full_name;
    document.getElementById('edit_owner_name').value = u.owner_name;
    document.getElementById('edit_role').value = u.role;
    document.getElementById('edit_dept_id').value = u.dept_id || '';
    editModal.show();
}
</script>

<style>
.bg-soft-info { background-color: rgba(13, 202, 240, 0.1); }
</style>

<?php echo '</div></div></div>'; renderFooter(); ?>