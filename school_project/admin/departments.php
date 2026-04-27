<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin']);
$db = getDB();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action==='create') {
        $s = $db->prepare("INSERT INTO departments (name,order_no) VALUES (?,?)");
        $s->execute([$_POST['name'],(int)$_POST['order_no']]);
        flashMessage('success','เพิ่มฝ่ายเรียบร้อย');
    } elseif ($action==='delete') {
        $db->prepare("UPDATE departments SET name=CONCAT(name,'_del_',id) WHERE id=?")->execute([$_POST['dept_id']]);
        flashMessage('success','ลบเรียบร้อย');
    }
    header('Location: /admin/departments.php'); exit;
}
$depts = $db->query("SELECT d.*,COUNT(u.id) AS user_count FROM departments d LEFT JOIN users u ON u.department_id=d.id GROUP BY d.id ORDER BY d.order_no")->fetchAll();
renderHead('จัดการฝ่าย');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('จัดการฝ่าย'); echo '<div class="page-content">'; showFlash();
?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card"><div class="card-header">เพิ่มฝ่าย</div><div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="action" value="create">
        <div class="mb-3"><label class="form-label">ชื่อฝ่าย</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">ลำดับ</label><input type="number" name="order_no" class="form-control" value="<?=count($depts)+1?>"></div>
        <button type="submit" class="btn btn-primary w-100">เพิ่มฝ่าย</button>
      </form>
    </div></div>
  </div>
  <div class="col-md-8">
    <div class="card"><div class="card-header">ฝ่ายทั้งหมด</div><div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead><tr><th class="ps-3">ลำดับ</th><th>ชื่อฝ่าย</th><th class="text-center">ผู้ใช้</th></tr></thead>
        <tbody>
        <?php foreach ($depts as $d): ?>
        <tr><td class="ps-3"><?=$d['order_no']?></td><td class="fw-semibold"><?=h($d['name'])?></td><td class="text-center"><span class="badge bg-secondary"><?=$d['user_count']?> คน</span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>