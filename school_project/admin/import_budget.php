<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin']);
$db = getDB();
$depts = $db->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();
$deptMap = []; foreach ($depts as $d) $deptMap[$d['name']] = $d['id'];
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action==='manual') {
        $deptId = (int)$_POST['department_id'];
        $fy = (int)$_POST['fiscal_year'];
        $s = $db->prepare("INSERT INTO budget_projects (department_id,fiscal_year,project_group,project_name,activity,budget_subsidy,budget_quality,budget_revenue,budget_operation,budget_reserve,owner_name) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $s->execute([$deptId,$fy,$_POST['project_group'],$_POST['project_name'],$_POST['activity'],$_POST['budget_subsidy']??0,$_POST['budget_quality']??0,$_POST['budget_revenue']??0,$_POST['budget_operation']??0,$_POST['budget_reserve']??0,$_POST['owner_name']]);
        flashMessage('success','เพิ่มโครงการเรียบร้อย');
        header('Location: /admin/import_budget.php'); exit;
    }
}
renderHead('Import งบประมาณ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('Import งบประมาณ'); echo '<div class="page-content">'; showFlash();
?>
<div class="row g-4">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-plus-circle me-2"></i>เพิ่มโครงการ (Manual)</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="action" value="manual">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">ฝ่าย <span class="text-danger">*</span></label>
              <select name="department_id" class="form-select" required>
                <?php foreach ($depts as $d): ?><option value="<?=$d['id']?>"><?=h($d['name'])?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-6"><label class="form-label">ปีงบประมาณ</label>
              <select name="fiscal_year" class="form-select">
                <?php for($y=2567;$y<=2572;$y++): ?><option value="<?=$y?>" <?=$y==FISCAL_YEAR?'selected':''?>><?=$y?></option><?php endfor; ?>
              </select></div>
            <div class="col-12"><label class="form-label">กลุ่มงาน</label><input type="text" name="project_group" class="form-control" placeholder="เช่น โอกาสทางการศึกษา"></div>
            <div class="col-12"><label class="form-label">ชื่อโครงการ <span class="text-danger">*</span></label><input type="text" name="project_name" class="form-control" required></div>
            <div class="col-12"><label class="form-label">กิจกรรม</label><textarea name="activity" class="form-control" rows="3"></textarea></div>
            <div class="col-12"><label class="form-label">ผู้รับผิดชอบ</label><input type="text" name="owner_name" class="form-control" placeholder="ชื่อต้องตรงกับ owner_name ของผู้ใช้"></div>
            <div class="col-md-4"><label class="form-label">งบอุดหนุน</label><input type="number" name="budget_subsidy" class="form-control" value="0" min="0" step="any"></div>
            <div class="col-md-4"><label class="form-label">งบพัฒนาคุณภาพ</label><input type="number" name="budget_quality" class="form-control" value="0" min="0" step="any"></div>
            <div class="col-md-4"><label class="form-label">เงินรายได้สถานศึกษา</label><input type="number" name="budget_revenue" class="form-control" value="0" min="0" step="any"></div>
          </div>
          <button type="submit" class="btn btn-primary mt-3 w-100"><i class="bi bi-plus-circle me-2"></i>เพิ่มโครงการ</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-info-circle me-2"></i>วิธี Import CSV</div>
      <div class="card-body">
        <p style="font-size:13px">สร้างไฟล์ CSV มีคอลัมน์ดังนี้:</p>
        <code style="font-size:11px;display:block;background:#f8fafc;padding:8px;border-radius:6px">department,fiscal_year,project_group,project_name,activity,budget_subsidy,budget_quality,budget_revenue,owner_name</code>
        <hr>
        <p style="font-size:12px;color:#64748b">หลังจากติดตั้ง PhpSpreadsheet ด้วย Composer สามารถ import ไฟล์ Excel ได้โดยตรง</p>
      </div>
    </div>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>