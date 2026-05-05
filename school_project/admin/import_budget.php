<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin']);
$db = getDB();
$depts    = $db->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();
$deptMap  = [];
foreach ($depts as $d) $deptMap[$d['name']] = $d['id'];

// แปลงประเภทเงิน → ชื่อ column ใน budget_projects
function mapBudgetType(string $type): string {
    $t = trim($type);
    $map = [
        'งบประมาณเงินอุดหนุน' => 'budget_subsidy',
        'งบอุดหนุน'           => 'budget_subsidy',
        'งบพัฒนาคุณภาพผู้เรียน' => 'budget_quality',
        'งบพัฒนาคุณภาพ'      => 'budget_quality',
        'เงินรายได้สถานศึกษา' => 'budget_revenue',
        'เงินรายได้'          => 'budget_revenue',
        'งบดำเนินการ'         => 'budget_operation',
        'งบสำรอง'            => 'budget_reserve',
    ];
    foreach ($map as $k => $v) {
        if (mb_stripos($t, mb_substr($k, 0, 6)) !== false) return $v;
    }
    return 'budget_subsidy'; // default
}

// ตัดตัวเลขนำหน้า เช่น "1. โครงการ..." → "โครงการ..."
function stripLeadingNumber(string $s): string {
    return preg_replace('/^\d+\.\s*/', '', trim($s));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ─── Manual ───────────────────────────────────────────────
    if ($action === 'manual') {
        $deptId = (int)$_POST['department_id'];
        $fy     = (int)$_POST['fiscal_year'];
        $budgetCol = mapBudgetType($_POST['budget_type'] ?? '');
        $amount    = (float)str_replace(',', '', $_POST['amount'] ?? 0);
        $s = $db->prepare("INSERT INTO budget_projects
            (department_id,fiscal_year,project_name,activity,{$budgetCol},owner_name)
            VALUES (?,?,?,?,?,?)");
        $s->execute([$deptId, $fy,
            stripLeadingNumber($_POST['project_name']),
            stripLeadingNumber($_POST['activity'] ?? ''),
            $amount, $_POST['owner_name'] ?? '']);
        flashMessage('success', 'เพิ่มโครงการเรียบร้อย');
        header('Location: ' . BASE_URL . '/admin/import_budget.php'); exit;
    }

    // ─── CSV ──────────────────────────────────────────────────
    if ($action === 'csv' && isset($_FILES['csv_file'])) {
        $fy   = (int)($_POST['fiscal_year'] ?? FISCAL_YEAR);
        $file = $_FILES['csv_file']['tmp_name'];
        $count = 0; $errors = []; $line = 1;

        if (($handle = fopen($file, 'r')) !== false) {
            // Detect UTF-8 BOM
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);

            $firstLine = fgets($handle);
            $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
            rewind($handle);
            if ($bom === "\xEF\xBB\xBF") fread($handle, 3);

            fgetcsv($handle, 2000, $delimiter); // skip header
            $db->beginTransaction();
            while (($data = fgetcsv($handle, 2000, $delimiter)) !== false) {
                $line++;
                if (empty(array_filter($data))) continue;
                if (count($data) < 5) {
                    $errors[] = "บรรทัดที่ $line: ข้อมูลไม่ครบ (ต้องการอย่างน้อย 5 คอลัมน์)";
                    continue;
                }

                $deptName   = trim($data[0]);
                $projectName = stripLeadingNumber($data[1]);
                $activity   = stripLeadingNumber($data[2]);
                $budgetType = trim($data[3]);
                $amount     = (float)str_replace(',', '', $data[4]);
                $ownerName  = trim($data[5] ?? '');

                $deptId = $deptMap[$deptName] ?? null;
                if (!$deptId) {
                    $errors[] = "บรรทัดที่ $line: ไม่พบฝ่าย '$deptName' ในระบบ";
                    continue;
                }

                $budgetCol = mapBudgetType($budgetType);

                try {
                    $s = $db->prepare("INSERT INTO budget_projects
                        (department_id,fiscal_year,project_name,activity,{$budgetCol},owner_name)
                        VALUES (?,?,?,?,?,?)");
                    $s->execute([$deptId, $fy, $projectName, $activity, $amount, $ownerName]);
                    $count++;
                } catch (Exception $e) {
                    $errors[] = "บรรทัดที่ $line: " . $e->getMessage();
                }
            }
            $db->commit();
            fclose($handle);

            if ($count > 0) flashMessage('success', "นำเข้าสำเร็จ $count รายการ (ปี $fy)");
            if (!empty($errors)) $_SESSION['import_errors'] = $errors;
            if ($count === 0 && empty($errors)) flashMessage('warning', 'ไม่พบข้อมูลในไฟล์');
        } else {
            flashMessage('danger', 'ไม่สามารถเปิดไฟล์ได้');
        }
        header('Location: ' . BASE_URL . '/admin/import_budget.php'); exit;
    }

    // ─── Download sample CSV ──────────────────────────────────
    if ($action === 'download_sample') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sample_budget.csv"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM สำหรับ Excel
        $rows = [
            ['ฝ่าย','โครงการ','กิจกรรม','ประเภทเงิน','จำนวนเงิน','ผู้รับผิดชอบ'],
            ['ฝ่ายบริหารงานวิชาการ','1. โครงการสร้างโอกาสทางการศึกษา','1. กิจกรรมประชาสัมพันธ์และรับสมัครนักเรียน','งบประมาณเงินอุดหนุน','20000','นางอัมพร แพงมา'],
            ['ฝ่ายบริหารงานวิชาการ','1. โครงการสร้างโอกาสทางการศึกษา','2. กิจกรรมค่ายปฐมนิเทศนักเรียนใหม่ ม.1 และ งบพัฒนาคุณภาพผู้เรียน','งบพัฒนาคุณภาพผู้เรียน','20000','นางอัมพร แพงมา'],
            ['ฝ่ายบริหารงานวิชาการ','1. โครงการสร้างโอกาสทางการศึกษา','3. กิจกรรมแก้ปัญหาการออกเรียนกลางคัน','งบพัฒนาคุณภาพผู้เรียน','5000','นางชลัดดา มากนวล'],
            ['ฝ่ายบริหารงานวิชาการ','2. โครงการพัฒนาผลสัมฤทธิ์ทางการเรียน','1. กิจกรรมสอนเสริมนักเรียนอ่านไม่ออกเขียนไม่ได้','งบอุดหนุน','15000','นายสมชาย ใจดี'],
            ['ฝ่ายบริหารงานบุคคล','1. โครงการพัฒนาครูและบุคลากร','1. กิจกรรมอบรมพัฒนาทักษะการจัดการเรียนรู้','เงินรายได้สถานศึกษา','30000','นางสาวมาลี สุขใจ'],
        ];
        $out = fopen('php://output', 'w');
        foreach ($rows as $r) fputcsv($out, $r);
        fclose($out);
        exit;
    }
}

$recentProjects = $db->query("SELECT bp.*, d.name AS dept_name FROM budget_projects bp JOIN departments d ON bp.department_id=d.id ORDER BY bp.id DESC LIMIT 10")->fetchAll();

renderHead('Import งบประมาณ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('Import งบประมาณ'); echo '<div class="page-content">';
showFlash();
if (!empty($_SESSION['import_errors'])) {
    echo '<div class="alert alert-danger shadow-sm"><h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>พบข้อผิดพลาด:</h6><ul class="mb-0 small">';
    foreach ($_SESSION['import_errors'] as $e) echo '<li>' . h($e) . '</li>';
    echo '</ul></div>';
    unset($_SESSION['import_errors']);
}
?>

<div class="row g-4">

  <!-- CSV Import -->
  <div class="col-md-5">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="bi bi-upload me-2 text-success"></i>นำเข้าจากไฟล์ CSV</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="csv">
          <div class="mb-3">
            <label class="form-label fw-semibold">ปีงบประมาณ <span class="text-danger">*</span></label>
            <select name="fiscal_year" class="form-select">
              <?php for ($y = 2567; $y <= 2572; $y++): ?>
              <option value="<?= $y ?>" <?= $y == FISCAL_YEAR ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">ไฟล์ CSV</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
          </div>
          <button type="submit" class="btn btn-success w-100 mb-2">
            <i class="bi bi-file-earmark-arrow-up me-1"></i>Import CSV
          </button>
        </form>

        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="download_sample">
          <button type="submit" class="btn btn-outline-info w-100">
            <i class="bi bi-download me-1"></i>ดาวน์โหลด CSV ตัวอย่าง
          </button>
        </form>

        <hr>
        <p class="fw-semibold small mb-1 text-primary"><i class="bi bi-info-circle me-1"></i>รูปแบบไฟล์ CSV</p>
        <div class="border rounded p-2 bg-light" style="font-size:11px;font-family:monospace">
          ฝ่าย, โครงการ, กิจกรรม, ประเภทเงิน, จำนวนเงิน, ผู้รับผิดชอบ
        </div>
        <div class="mt-2 small text-muted">
          <strong>ประเภทเงินที่ใช้ได้:</strong><br>
          • งบประมาณเงินอุดหนุน<br>
          • งบพัฒนาคุณภาพผู้เรียน<br>
          • เงินรายได้สถานศึกษา<br>
          • งบดำเนินการ<br>
          • งบสำรอง<br>
          <br>
          * ชื่อฝ่ายต้องตรงกับในระบบ<br>
          * บันทึกไฟล์เป็น UTF-8 (แนะนำ Google Sheets)
        </div>
      </div>
    </div>
  </div>

  <!-- Manual -->
  <div class="col-md-7">
    <div class="card h-100">
      <div class="card-header fw-semibold"><i class="bi bi-plus-circle me-2 text-primary"></i>เพิ่มโครงการ (Manual)</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="manual">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">ฝ่าย <span class="text-danger">*</span></label>
              <select name="department_id" class="form-select" required>
                <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>"><?= h($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">ปีงบประมาณ</label>
              <select name="fiscal_year" class="form-select">
                <?php for ($y = 2567; $y <= 2572; $y++): ?>
                <option value="<?= $y ?>" <?= $y == FISCAL_YEAR ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">ชื่อโครงการ <span class="text-danger">*</span></label>
              <input type="text" name="project_name" class="form-control" required placeholder="เช่น โครงการสร้างโอกาสทางการศึกษา">
            </div>
            <div class="col-12">
              <label class="form-label">กิจกรรม</label>
              <input type="text" name="activity" class="form-control" placeholder="เช่น กิจกรรมประชาสัมพันธ์และรับสมัครนักเรียน">
            </div>
            <div class="col-md-6">
              <label class="form-label">ประเภทเงิน</label>
              <select name="budget_type" class="form-select">
                <option value="งบประมาณเงินอุดหนุน">งบประมาณเงินอุดหนุน</option>
                <option value="งบพัฒนาคุณภาพผู้เรียน">งบพัฒนาคุณภาพผู้เรียน</option>
                <option value="เงินรายได้สถานศึกษา">เงินรายได้สถานศึกษา</option>
                <option value="งบดำเนินการ">งบดำเนินการ</option>
                <option value="งบสำรอง">งบสำรอง</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">จำนวนเงิน (บาท)</label>
              <input type="number" name="amount" class="form-control" value="0" min="0" step="any">
            </div>
            <div class="col-12">
              <label class="form-label">ผู้รับผิดชอบ</label>
              <input type="text" name="owner_name" class="form-control" placeholder="ชื่อ-สกุล ผู้รับผิดชอบโครงการ">
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-3 w-100">
            <i class="bi bi-plus-circle me-2"></i>เพิ่มโครงการ
          </button>
        </form>
      </div>
    </div>
  </div>

</div>

<!-- ชื่อฝ่ายในระบบ -->
<div class="card mt-3">
  <div class="card-header fw-semibold"><i class="bi bi-building me-1"></i>ฝ่ายที่มีในระบบ (ใช้ชื่อให้ตรงกันเป๊ะในไฟล์ CSV)</div>
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($depts as $d): ?>
      <span class="badge bg-light text-dark border px-3 py-2" style="font-size:13px"><?= h($d['name']) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- โครงการล่าสุด -->
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-clock-history me-2"></i>โครงการที่เพิ่มล่าสุด 10 รายการ</span>
    <a href="<?= BASE_URL ?>/admin/budget_list.php" class="btn btn-sm btn-link text-decoration-none">ดูทั้งหมด →</a>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm mb-0" style="font-size:13px">
      <thead class="table-light">
        <tr>
          <th class="ps-3">ปีงบ</th>
          <th>ชื่อโครงการ</th>
          <th>กิจกรรม</th>
          <th>ฝ่าย</th>
          <th>ผู้รับผิดชอบ</th>
          <th class="text-end pe-3">งบประมาณ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentProjects)): ?>
        <tr><td colspan="6" class="text-center py-3 text-muted">ยังไม่มีข้อมูล</td></tr>
        <?php endif; ?>
        <?php foreach ($recentProjects as $p):
          $total = $p['budget_subsidy'] + $p['budget_quality'] + $p['budget_revenue'] + $p['budget_operation'] + $p['budget_reserve'];
        ?>
        <tr>
          <td class="ps-3"><?= $p['fiscal_year'] ?></td>
          <td class="fw-semibold"><?= h($p['project_name']) ?></td>
          <td class="text-muted"><?= h(mb_strimwidth($p['activity'] ?? '', 0, 40, '…')) ?></td>
          <td><?= h($p['dept_name']) ?></td>
          <td><?= h($p['owner_name']) ?></td>
          <td class="text-end pe-3 fw-bold text-primary"><?= formatMoney($total) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php echo '</div></div></div>'; renderFooter(); ?>
