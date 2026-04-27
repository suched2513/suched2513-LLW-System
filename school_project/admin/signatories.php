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
        $s = $db->prepare("INSERT INTO signatories (role_label,full_name,position,order_no) VALUES (?,?,?,?)");
        $s->execute([$_POST['role_label'],$_POST['full_name'],$_POST['position'],(int)$_POST['order_no']]);
        flashMessage('success','เพิ่มผู้ลงนามเรียบร้อย');
    } elseif ($action==='update') {
        $s = $db->prepare("UPDATE signatories SET role_label=?,full_name=?,position=?,is_active=? WHERE id=?");
        $s->execute([$_POST['role_label'],$_POST['full_name'],$_POST['position'],(int)$_POST['is_active'],(int)$_POST['sig_id']]);
        flashMessage('success','อัปเดตเรียบร้อย');
    }
    header('Location: /admin/signatories.php'); exit;
}
$sigs = $db->query("SELECT * FROM signatories ORDER BY order_no")->fetchAll();
renderHead('ผู้ลงนาม');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('จัดการผู้ลงนาม'); echo '<div class="page-content">'; showFlash();
?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card"><div class="card-header">เพิ่มผู้ลงนาม</div><div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="action" value="create">
        <div class="mb-2"><label class="form-label">บทบาท</label><input type="text" name="role_label" class="form-control" required placeholder="เช่น ผู้อำนวยการโรงเรียน"></div>
        <div class="mb-2"><label class="form-label">ชื่อ-สกุล</label><input type="text" name="full_name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">ตำแหน่ง</label><input type="text" name="position" class="form-control"></div>
        <div class="mb-3"><label class="form-label">ลำดับ</label><input type="number" name="order_no" class="form-control" value="<?=count($sigs)+1?>"></div>
        <button type="submit" class="btn btn-primary w-100">เพิ่ม</button>
      </form>
    </div></div>
  </div>
  <div class="col-md-8">
    <div class="card"><div class="card-header">รายชื่อผู้ลงนาม</div><div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead><tr><th class="ps-3">บทบาท</th><th>ชื่อ</th><th>ตำแหน่ง</th><th class="text-center">สถานะ</th></tr></thead>
        <tbody>
        <?php foreach ($sigs as $s): ?>
        <tr><td class="ps-3 fw-semibold"><?=h($s['role_label'])?></td><td><?=h($s['full_name'])?></td><td style="font-size:13px;color:#64748b"><?=h($s['position'])?></td><td class="text-center"><?=$s['is_active']?'<span class="badge bg-success">ใช้งาน</span>':'<span class="badge bg-secondary">ปิด</span>'?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>