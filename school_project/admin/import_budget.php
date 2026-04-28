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
        header('Location: ' . BASE_URL . '/admin/import_budget.php'); exit;
    }
    if ($action==='csv' && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file']['tmp_name'];
        $count = 0; $errors = []; $line = 1;
        
        if (($handle = fopen($file, "r")) !== FALSE) {
            // Detect delimiter
            $firstLine = fgets($handle);
            $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
            rewind($handle);
            
            fgetcsv($handle, 1000, $delimiter); // Skip header
            $db->beginTransaction();
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                $line++;
                if (empty(array_filter($data))) continue; // Skip empty rows
                if (count($data) < 9) {
                    $errors[] = "บรรทัดที่ $line: ข้อมูลไม่ครบ (มี ".count($data)." คอลัมน์ จากที่ต้องการ 9)";
                    continue;
                }
                
                $deptName = trim($data[0]);
                $deptId = $deptMap[$deptName] ?? null;
                
                if (!$deptId) {
                    $errors[] = "บรรทัดที่ $line: ไม่พบฝ่ายชื่อ '$deptName' ในระบบ (กรุณาเช็คตัวสะกด)";
                    continue;
                }
                
                try {
                    $s = $db->prepare("INSERT INTO budget_projects (department_id,fiscal_year,project_group,project_name,activity,budget_subsidy,budget_quality,budget_revenue,owner_name) VALUES (?,?,?,?,?,?,?,?,?)");
                    $s->execute([
                        $deptId, (int)$data[1], $data[2], $data[3], $data[4],
                        (float)$data[5], (float)$data[6], (float)$data[7], $data[8]
                    ]);
                    $count++;
                } catch (Exception $e) {
                    $errors[] = "บรรทัดที่ $line: เกิดข้อผิดพลาด SQL - " . $e->getMessage();
                }
            }
            $db->commit();
            fclose($handle);
            
            if ($count > 0) flashMessage('success', "นำเข้าสำเร็จ $count รายการ");
            if (!empty($errors)) {
                $_SESSION['import_errors'] = $errors;
                if ($count === 0) flashMessage('danger', "ไม่สามารถนำเข้าข้อมูลได้ กรุณาตรวจสอบข้อผิดพลาดด้านล่าง");
            }
        } else {
            flashMessage('danger', "ไม่สามารถเปิดไฟล์ได้");
        }
        header('Location: ' . BASE_URL . '/admin/import_budget.php'); exit;
    }
}
$recentProjects = $db->query("SELECT bp.*, d.name AS dept_name FROM budget_projects bp JOIN departments d ON bp.department_id=d.id ORDER BY bp.id DESC LIMIT 5")->fetchAll();
renderHead('Import งบประมาณ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('Import งบประมาณ'); echo '<div class="page-content">'; 
showFlash();
if (!empty($_SESSION['import_errors'])) {
    echo '<div class="alert alert-danger shadow-sm">';
    echo '<h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>พบข้อผิดพลาดในการนำเข้า:</h6><ul class="mb-0 small">';
    foreach ($_SESSION['import_errors'] as $err) echo '<li>'.h($err).'</li>';
    echo '</ul><div class="mt-2 text-dark small"><strong>คำแนะนำ:</strong> ตรวจสอบว่าชื่อฝ่ายสะกดตรงกันเป๊ะ และไฟล์เซฟเป็น UTF-8 (แนะนำ Google Sheets)</div></div>';
    unset($_SESSION['import_errors']);
}
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
    <div class="card">
      <div class="card-header"><i class="bi bi-upload me-2"></i>อัปโหลดไฟล์ CSV</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="action" value="csv">
          <div class="mb-3">
            <label class="form-label">เลือกไฟล์ .csv</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
          </div>
          <button type="submit" class="btn btn-success w-100"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import CSV</button>
        </form>
        <hr>
        <p style="font-size:13px" class="fw-bold mb-1 text-primary"><i class="bi bi-info-circle me-1"></i>วิธีเตรียมไฟล์ CSV</p>
        <p style="font-size:12px" class="mb-2">เรียงลำดับคอลัมน์ดังนี้ (คั่นด้วยคอมม่า):</p>
        <code style="font-size:10px;display:block;background:#f8fafc;padding:8px;border-radius:6px;word-break:break-all">department,fiscal_year,project_group,project_name,activity,budget_subsidy,budget_quality,budget_revenue,owner_name</code>
        <hr>
        <div class="d-grid">
          <a href="<?=BASE_URL?>/assets/sample_projects.csv" class="btn btn-sm btn-outline-info" download>
            <i class="bi bi-download me-1"></i>ดาวน์โหลดไฟล์ตัวอย่าง CSV
          </a>
        </div>
        <div class="mt-2 small text-muted">
          * ชื่อฝ่าย (department) ต้องตรงกับที่มีในระบบ<br>
          * ไฟล์ต้องเป็น UTF-8 (แนะนำใช้ Google Sheets แล้ว Download as CSV)
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-clock-history me-2"></i>โครงการที่เพิ่มล่าสุด 5 รายการ</span>
    <a href="<?=BASE_URL?>/admin/budget_list.php" class="btn btn-sm btn-link text-decoration-none">ดูทั้งหมด →</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0" style="font-size:13px">
        <thead class="table-light"><tr><th class="ps-3">ปีงบ</th><th>ชื่อโครงการ</th><th>ฝ่าย</th><th>ผู้รับผิดชอบ</th><th class="text-end pe-3">รวมงบประมาณ</th></tr></thead>
        <tbody>
          <?php if (empty($recentProjects)): ?>
            <tr><td colspan="5" class="text-center py-3 text-muted">ยังไม่มีข้อมูลโครงการในระบบ</td></tr>
          <?php endif; ?>
          <?php foreach ($recentProjects as $p): ?>
          <?php $total = $p['budget_subsidy']+$p['budget_quality']+$p['budget_revenue']+$p['budget_operation']+$p['budget_reserve']; ?>
          <tr>
            <td class="ps-3"><?=$p['fiscal_year']?></td>
            <td><div class="fw-semibold"><?=h($p['project_name'])?></div></td>
            <td><?=h($p['dept_name'])?></td>
            <td><?=h($p['owner_name'])?></td>
            <td class="text-end pe-3 fw-bold text-primary"><?=formatMoney($total)?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>