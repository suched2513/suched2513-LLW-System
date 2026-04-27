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
    $keys = ['school_name','school_district','school_province','fiscal_year','overdue_days','line_notify_token','smtp_host','smtp_port','smtp_user','smtp_from','budget_warning_pct'];
    foreach ($keys as $k) {
        $val = $_POST[$k] ?? '';
        $s = $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
        $s->execute([$k,$val,$val]);
    }
    flashMessage('success','บันทึกการตั้งค่าเรียบร้อย');
    header('Location: /admin/settings.php'); exit;
}
$settings = $db->query("SELECT * FROM settings")->fetchAll();
$sv = []; foreach ($settings as $s) $sv[$s['setting_key']] = $s['setting_value'];
renderHead('ตั้งค่าระบบ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('ตั้งค่าระบบ'); echo '<div class="page-content">'; showFlash();
?>
<div class="card">
  <div class="card-header"><i class="bi bi-gear me-2"></i>ตั้งค่าระบบ</div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <div class="row g-4">
        <div class="col-md-6">
          <h6 class="fw-bold text-primary mb-3">ข้อมูลโรงเรียน</h6>
          <div class="mb-3"><label class="form-label">ชื่อโรงเรียน</label><input type="text" name="school_name" class="form-control" value="<?=h($sv['school_name']??'')?>"></div>
          <div class="mb-3"><label class="form-label">อำเภอ</label><input type="text" name="school_district" class="form-control" value="<?=h($sv['school_district']??'')?>"></div>
          <div class="mb-3"><label class="form-label">จังหวัด</label><input type="text" name="school_province" class="form-control" value="<?=h($sv['school_province']??'')?>"></div>
          <div class="mb-3"><label class="form-label">ปีงบประมาณ</label><input type="number" name="fiscal_year" class="form-control" value="<?=h($sv['fiscal_year']??'')?>"></div>
          <div class="mb-3"><label class="form-label">วันค้างโครงการ (วัน)</label><input type="number" name="overdue_days" class="form-control" value="<?=h($sv['overdue_days']??30)?>"></div>
          <div class="mb-3"><label class="form-label">% เตือนงบใกล้หมด</label><input type="number" name="budget_warning_pct" class="form-control" value="<?=h($sv['budget_warning_pct']??90)?>"></div>
        </div>
        <div class="col-md-6">
          <h6 class="fw-bold text-primary mb-3">LINE Notify</h6>
          <div class="mb-3"><label class="form-label">LINE Notify Token</label><input type="text" name="line_notify_token" class="form-control" value="<?=h($sv['line_notify_token']??'')?>" placeholder="Token จาก notify.line.me"></div>
          <h6 class="fw-bold text-primary mb-3 mt-3">Email SMTP</h6>
          <div class="mb-3"><label class="form-label">SMTP Host</label><input type="text" name="smtp_host" class="form-control" value="<?=h($sv['smtp_host']??'smtp.gmail.com')?>"></div>
          <div class="mb-3"><label class="form-label">SMTP Port</label><input type="number" name="smtp_port" class="form-control" value="<?=h($sv['smtp_port']??587)?>"></div>
          <div class="mb-3"><label class="form-label">Email ผู้ส่ง</label><input type="email" name="smtp_from" class="form-control" value="<?=h($sv['smtp_from']??'')?>"></div>
          <div class="mb-3"><label class="form-label">SMTP Username</label><input type="text" name="smtp_user" class="form-control" value="<?=h($sv['smtp_user']??'')?>"></div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>บันทึกการตั้งค่า</button>
    </form>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>