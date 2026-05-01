<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../config.php';
busRequireStaff(['bus_admin', 'super_admin']);

$pdo     = getPdo();
$msg     = '';
$err     = '';
$search  = trim($_GET['q'] ?? '');
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $sid      = trim($_POST['student_id'] ?? '');
            $fullname = trim($_POST['fullname'] ?? '');
            $class    = trim($_POST['classroom'] ?? '');
            $village  = trim($_POST['village'] ?? '') ?: null;
            $nid      = preg_replace('/\D/', '', $_POST['national_id'] ?? '');

            if ($sid === '' || $fullname === '' || strlen($nid) !== 13) {
                $err = 'กรุณากรอกข้อมูลให้ครบถ้วน (รหัสนักเรียน ชื่อ-นามสกุล เลขบัตรประชาชน 13 หลัก)';
            } else {
                $hash   = password_hash($nid, PASSWORD_BCRYPT);
                $masked = busMaskNid($nid);
                $stmt   = $pdo->prepare("INSERT INTO bus_students (student_id, fullname, classroom, village, national_id_hash, national_id_masked) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$sid, $fullname, $class, $village, $hash, $masked]);
                $msg = 'เพิ่มนักเรียนเรียบร้อยแล้ว';
            }
        } elseif ($action === 'edit') {
            $id       = (int)($_POST['stu_id'] ?? 0);
            $fullname = trim($_POST['fullname'] ?? '');
            $class    = trim($_POST['classroom'] ?? '');
            $village  = trim($_POST['village'] ?? '') ?: null;
            $nidRaw   = preg_replace('/\D/', '', $_POST['national_id'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($fullname === '') {
                $err = 'กรุณากรอกชื่อ-นามสกุล';
            } else {
                if (strlen($nidRaw) === 13) {
                    $hash   = password_hash($nidRaw, PASSWORD_BCRYPT);
                    $masked = busMaskNid($nidRaw);
                    $pdo->prepare("UPDATE bus_students SET fullname=?, classroom=?, village=?, national_id_hash=?, national_id_masked=?, is_active=? WHERE id=?")
                        ->execute([$fullname, $class, $village, $hash, $masked, $isActive, $id]);
                } else {
                    $pdo->prepare("UPDATE bus_students SET fullname=?, classroom=?, village=?, is_active=? WHERE id=?")
                        ->execute([$fullname, $class, $village, $isActive, $id]);
                }
                $msg = 'แก้ไขข้อมูลเรียบร้อยแล้ว';
            }
        } elseif ($action === 'import_csv') {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $err = 'กรุณาเลือกไฟล์ CSV';
            } else {
                $mime = mime_content_type($_FILES['csv_file']['tmp_name']);
                $ext  = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['csv', 'txt']) || !in_array($mime, ['text/plain','text/csv','application/csv','application/octet-stream'])) {
                    $err = 'กรุณาอัพโหลดไฟล์ .csv เท่านั้น';
                } else {
                    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
                    // Detect and strip BOM (UTF-8 BOM from Excel)
                    $bom = fread($handle, 3);
                    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

                    $stmtInsert = $pdo->prepare("INSERT INTO bus_students (student_id, fullname, classroom, village, national_id_hash, national_id_masked, is_active) VALUES (?,?,?,?,?,?,1)");
                    $stmtUpdate = $pdo->prepare("UPDATE bus_students SET fullname=?, classroom=?, national_id_hash=?, national_id_masked=?, is_active=1 WHERE student_id=?");
                    $stmtCheck  = $pdo->prepare("SELECT id, fullname, classroom FROM bus_students WHERE student_id=?");

                    $countNew = $countUpd = $countSkip = 0;
                    $rowNum   = 0;
                    $skipHead = false;

                    while (($row = fgetcsv($handle)) !== false) {
                        $rowNum++;
                        // Auto-skip header row if first cell looks like a label
                        if ($rowNum === 1 && !is_numeric(preg_replace('/\D/', '', $row[0] ?? ''))) {
                            $skipHead = true;
                            continue;
                        }
                        $sid = trim($row[0] ?? '');
                        // Normalize: pad purely numeric student IDs to 5 digits (4853 → 04853)
                        if ($sid !== '' && ctype_digit($sid)) {
                            $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
                        }
                        $nid = preg_replace('/\D/', '', $row[1] ?? '');

                        if ($sid === '' || strlen($nid) !== 13) {
                            $countSkip++;
                            continue;
                        }

                        $hash    = password_hash($nid, PASSWORD_BCRYPT);
                        $masked  = busMaskNid($nid);
                        $name    = trim($row[2] ?? '');
                        $class   = trim($row[3] ?? '');
                        $village = trim($row[4] ?? '') ?: null;

                        $stmtCheck->execute([$sid]);
                        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                        if ($existing) {
                            // Use name/classroom from CSV if provided, else keep existing
                            $updName  = $name  !== '' ? $name  : $existing['fullname'];
                            $updClass = $class !== '' ? $class : $existing['classroom'];
                            $stmtUpdate->execute([$updName, $updClass, $hash, $masked, $sid]);
                            $countUpd++;
                        } elseif ($name !== '') {
                            $stmtInsert->execute([$sid, $name, $class, $village, $hash, $masked]);
                            $countNew++;
                        } else {
                            $countSkip++;
                        }
                    }
                    fclose($handle);
                    $msg = "นำเข้า CSV สำเร็จ: เพิ่มใหม่ {$countNew} คน · อัพเดทรหัส {$countUpd} คน · ข้ามไป {$countSkip} แถว";
                }
            }
        } elseif ($action === 'import_att') {
            // Import from att_students (only those not yet in bus_students)
            $rows = $pdo->query("
                SELECT a.student_id, a.name as fullname, a.classroom
                FROM att_students a
                WHERE a.student_id NOT IN (SELECT student_id FROM bus_students)
                  AND LPAD(a.student_id, 5, '0') NOT IN (SELECT student_id FROM bus_students)
                ORDER BY a.classroom, a.student_id
            ")->fetchAll(PDO::FETCH_ASSOC);

            $defaultNid = '0000000000000';
            $hash       = password_hash($defaultNid, PASSWORD_BCRYPT);
            $masked     = '0-0000-xxxxx-xx-0';

            $stmt = $pdo->prepare("INSERT INTO bus_students (student_id, fullname, classroom, national_id_hash, national_id_masked, is_active) VALUES (?,?,?,?,?,0)");
            $count = 0;
            foreach ($rows as $r) {
                $stmt->execute([str_pad(preg_replace('/\D/', '', $r['student_id']), 5, '0', STR_PAD_LEFT) ?: $r['student_id'], $r['fullname'], $r['classroom'], $hash, $masked]);
                $count++;
            }
            $msg = "นำเข้าข้อมูล {$count} คนเรียบร้อยแล้ว (ต้องอัพเดทเลขบัตรประชาชนก่อนเปิดใช้งาน)";

        } elseif ($action === 'delete_garbled') {
            $pdo->beginTransaction();

            // Detect garbled: names containing chars outside Thai (U+0E00–U+0E7F) and printable ASCII
            $rows = $pdo->query("SELECT id, fullname FROM bus_students")->fetchAll(PDO::FETCH_ASSOC);
            $toDelete = [];
            foreach ($rows as $r) {
                $invalid = preg_replace('/[\x{0020}-\x{007E}\x{0E00}-\x{0E7F}]/u', '', $r['fullname']);
                if (mb_strlen($invalid, 'UTF-8') > 0) {
                    $toDelete[] = (int)$r['id'];
                }
            }

            $deleted = $skipped = 0;
            if (!empty($toDelete)) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM bus_registrations WHERE student_id = ?");
                $del = $pdo->prepare("DELETE FROM bus_students WHERE id = ?");
                foreach ($toDelete as $id) {
                    $chk->execute([$id]);
                    if ((int)$chk->fetchColumn() === 0) {
                        $del->execute([$id]);
                        $deleted++;
                    } else {
                        $skipped++;
                    }
                }
            }

            $pdo->commit();
            $msg = "ลบรายชื่อภาษาต่างดาว {$deleted} คน";
            if ($skipped > 0) $msg .= " · ข้าม {$skipped} คน (มีการลงทะเบียนแล้ว — ใช้ปุ่ม 'ซิงค์ชื่อ' แทน)";

        } elseif ($action === 'activate_all') {
            $activated = $pdo->exec("UPDATE bus_students SET is_active = 1 WHERE is_active = 0");
            $msg = "เปิดใช้งาน {$activated} คนเรียบร้อยแล้ว (รหัสผ่านชั่วคราว: 0000000000000)";

        } elseif ($action === 'sync_and_fix') {
            $pdo->beginTransaction();

            // Update fullname + classroom from att_students (match on raw or padded student_id)
            $synced = $pdo->exec(
                "UPDATE bus_students b
                 JOIN att_students a ON a.student_id = b.student_id
                                    OR LPAD(a.student_id, 5, '0') = b.student_id
                 SET b.fullname = a.name, b.classroom = a.classroom"
            );

            // Pad purely-numeric student IDs to 5 digits
            $padded = $pdo->exec(
                "UPDATE bus_students
                 SET student_id = LPAD(student_id, 5, '0')
                 WHERE student_id REGEXP '^[0-9]+\$' AND CHAR_LENGTH(student_id) < 5"
            );

            $pdo->commit();
            $msg = "ซิงค์ชื่อ-ห้อง {$synced} คน · เติม 0 นำหน้ารหัส {$padded} คน";
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        $err = 'เกิดข้อผิดพลาด กรุณาลองใหม่' . (strpos($e->getMessage(), 'Duplicate') !== false ? ': รหัสนักเรียนนี้มีอยู่แล้ว' : '');
    }
}

// Fetch students
try {
    $where   = $search !== '' ? 'WHERE (student_id LIKE ? OR fullname LIKE ? OR classroom LIKE ? OR village LIKE ?)' : '';
    $params  = $search !== '' ? ["%$search%", "%$search%", "%$search%", "%$search%"] : [];

    $total   = (int)$pdo->prepare("SELECT COUNT(*) FROM bus_students $where")->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM bus_students $where")->execute($params) : 0;

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM bus_students $where");
    $cntStmt->execute($params);
    $total   = (int)$cntStmt->fetchColumn();
    $pages   = max(1, (int)ceil($total / $perPage));

    $listStmt = $pdo->prepare("SELECT * FROM bus_students $where ORDER BY classroom, student_id LIMIT $perPage OFFSET $offset");
    $listStmt->execute($params);
    $students = $listStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $students = [];
    $total    = 0;
    $pages    = 1;
}

$pageTitle    = 'รายชื่อนักเรียน';
$pageSubtitle = 'จัดการบัญชีนักเรียนผู้ใช้งานระบบ';
$activeSystem = 'bus';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="container-fluid">
  <div class="row g-4">

    <!-- Add Student -->
    <div class="col-12 col-xl-4">
      <div class="card border-0 shadow-sm" id="stuFormCard">
        <div class="card-header bg-white border-0">
          <h6 class="fw-black mb-0" id="stuFormTitle"><i class="fas fa-user-plus me-2 text-primary"></i>เพิ่มนักเรียน</h6>
        </div>
        <div class="card-body">
          <form method="POST" id="stuForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add" id="stuAction">
            <input type="hidden" name="stu_id" value="" id="stuId">
            <div class="mb-3">
              <label class="form-label fw-bold small">รหัสนักเรียน <span class="text-danger">*</span></label>
              <input type="text" name="student_id" id="fSid" class="form-control" required maxlength="20">
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
              <input type="text" name="fullname" id="fFullname" class="form-control" required maxlength="200">
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">ห้องเรียน</label>
              <input type="text" name="classroom" id="fClass" class="form-control" maxlength="50" placeholder="เช่น ม.1/1">
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">บ้าน / หมู่บ้าน</label>
              <input type="text" name="village" id="fVillage" class="form-control" maxlength="200" placeholder="เช่น บ้านประชาพัฒนา">
              <div class="form-text">ใช้สำหรับรายงานสรุปตามพื้นที่</div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">เลขบัตรประชาชน 13 หลัก <span class="text-danger">*</span></label>
              <input type="text" name="national_id" id="fNid" class="form-control" maxlength="13" inputmode="numeric" placeholder="ใส่เฉพาะตัวเลข 13 หลัก">
              <div class="form-text" id="nidHint">ใช้เป็นรหัสผ่านในการเข้าระบบ</div>
            </div>
            <div class="mb-3 form-check" id="activeRow" style="display:none">
              <input type="checkbox" name="is_active" class="form-check-input" id="fActive">
              <label class="form-check-label fw-bold small" for="fActive">เปิดใช้งาน</label>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary fw-bold flex-fill"><i class="fas fa-save me-1"></i><span id="stuBtnLabel">เพิ่มนักเรียน</span></button>
              <button type="button" class="btn btn-light fw-bold" onclick="resetStuForm()">ยกเลิก</button>
            </div>
          </form>

          <hr>

          <!-- Activate all inactive students -->
          <form method="POST" onsubmit="return confirm('เปิดใช้งานนักเรียนทุกคนที่ยังปิดอยู่?\n\nรหัสผ่านชั่วคราวคือ: 0000000000000')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="activate_all">
            <button type="submit" class="btn btn-success w-100 fw-bold small">
              <i class="fas fa-user-check me-1"></i> เปิดใช้งานทุกคน
            </button>
            <div class="form-text mt-1">
              เปิดเฉพาะที่ยังปิดอยู่ · password ชั่วคราว: <code>0000000000000</code>
            </div>
          </form>

          <hr>

          <!-- Import CSV (primary) -->
          <div class="mb-2">
            <p class="fw-black small mb-2"><i class="fas fa-file-csv me-1 text-success"></i>นำเข้าจากไฟล์ CSV</p>
            <p class="text-muted small mb-2">คอลัมน์: <code>รหัสนักเรียน, เลขบัตรประชาชน, ชื่อ-นามสกุล, ห้อง</code></p>
            <a href="/bus/admin/students_template.php" class="btn btn-outline-success btn-sm w-100 mb-2 fw-bold">
              <i class="fas fa-download me-1"></i> ดาวน์โหลด Template CSV
            </a>
            <form method="POST" enctype="multipart/form-data">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="import_csv">
              <input type="file" name="csv_file" accept=".csv,.txt" class="form-control form-control-sm mb-2" required>
              <button type="submit" class="btn btn-success w-100 fw-bold small">
                <i class="fas fa-upload me-1"></i> อัพโหลด CSV
              </button>
            </form>
          </div>

          <hr>

          <!-- Import from att_students (fallback) -->
          <form method="POST" onsubmit="return confirm('นำเข้าข้อมูลนักเรียนจากระบบเช็คชื่อ? (เฉพาะที่ยังไม่มีในระบบรถ)')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_att">
            <button type="submit" class="btn btn-outline-secondary w-100 fw-bold small">
              <i class="fas fa-file-import me-1"></i> นำเข้าจากระบบเช็คชื่อ (att_students)
            </button>
          </form>

          <hr>

          <!-- Delete garbled names -->
          <form method="POST" onsubmit="return confirm('ลบรายชื่อนักเรียนที่ชื่อเป็นภาษาต่างดาวทั้งหมด?\n\nนักเรียนที่มีการลงทะเบียนแล้วจะไม่ถูกลบ')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_garbled">
            <button type="submit" class="btn btn-danger w-100 fw-bold small">
              <i class="fas fa-trash-alt me-1"></i> ลบรายชื่อภาษาต่างดาวทั้งหมด
            </button>
            <div class="form-text text-danger mt-1">
              ลบเฉพาะที่ยังไม่มีการลงทะเบียน
            </div>
          </form>

          <hr>

          <!-- Fix garbled names + pad student IDs -->
          <form method="POST" onsubmit="return confirm('อัปเดตชื่อ-ห้องทุกคนจาก att_students และเติม 0 นำหน้ารหัสนักเรียน?\n\nหมายเหตุ: ชื่อที่ไม่มีในระบบเช็คชื่อจะไม่ถูกแก้ไข')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="sync_and_fix">
            <button type="submit" class="btn btn-warning w-100 fw-bold small">
              <i class="fas fa-sync-alt me-1"></i> ซิงค์ชื่อ + เติม 0 รหัสนักเรียน
            </button>
            <div class="form-text text-warning-emphasis mt-1">
              แก้ชื่อภาษาต่างดาว + รหัส เช่น 4853 → 04853
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Student List -->
    <div class="col-12 col-xl-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
          <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อ รหัส หรือห้อง..." class="form-control form-control-sm">
            <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">ค้นหา</button>
            <?php if ($search): ?><a href="/bus/admin/students.php" class="btn btn-sm btn-light fw-bold">ล้าง</a><?php endif; ?>
          </form>
        </div>
        <div class="card-body p-0">
          <div class="px-4 py-2 border-bottom small text-muted">แสดง <?= count($students) ?> จาก <?= number_format($total) ?> คน</div>
          <?php if (empty($students)): ?>
          <p class="text-center text-muted py-5">ไม่พบข้อมูล</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="text-xs fw-bold text-uppercase text-muted ps-4">นักเรียน</th>
                  <th class="text-xs fw-bold text-uppercase text-muted">บ้าน</th>
                  <th class="text-xs fw-bold text-uppercase text-muted">บัตรประชาชน</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-center">สถานะ</th>
                  <th class="text-xs fw-bold text-uppercase text-muted text-end pe-4">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $s): ?>
                <tr>
                  <td class="ps-4">
                    <div class="fw-bold"><?= htmlspecialchars($s['fullname']) ?></div>
                    <div class="small text-muted">รหัส <?= htmlspecialchars($s['student_id']) ?><?= $s['classroom'] ? ' · ' . htmlspecialchars($s['classroom']) : '' ?></div>
                  </td>
                  <td class="small <?= $s['village'] ? 'text-dark' : 'text-muted' ?>">
                    <?= $s['village'] ? htmlspecialchars($s['village']) : '<span class="text-muted fst-italic">ไม่ระบุ</span>' ?>
                  </td>
                  <td class="small text-muted font-monospace"><?= htmlspecialchars($s['national_id_masked']) ?></td>
                  <td class="text-center">
                    <span class="badge bg-<?= $s['is_active'] ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $s['is_active'] ? 'success' : 'secondary' ?> fw-bold">
                      <?= $s['is_active'] ? 'ใช้งาน' : 'ปิด' ?>
                    </span>
                  </td>
                  <td class="text-end pe-4">
                    <button type="button"
                      onclick="editStu(<?= htmlspecialchars(json_encode(['id'=>$s['id'],'student_id'=>$s['student_id'],'fullname'=>$s['fullname'],'classroom'=>$s['classroom'],'village'=>$s['village'] ?? '','is_active'=>$s['is_active']]), ENT_QUOTES) ?>)"
                      class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($pages > 1): ?>
          <div class="d-flex justify-content-center py-3">
            <nav><ul class="pagination pagination-sm mb-0">
              <?php for ($i = 1; $i <= $pages; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
              </li>
              <?php endfor; ?>
            </ul></nav>
          </div>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
function editStu(s) {
    document.getElementById('stuFormTitle').innerHTML = '<i class="fas fa-user-edit me-2 text-warning"></i>แก้ไขข้อมูลนักเรียน';
    document.getElementById('stuBtnLabel').textContent = 'บันทึก';
    document.getElementById('stuAction').value = 'edit';
    document.getElementById('stuId').value = s.id;
    document.getElementById('fSid').value = s.student_id;
    document.getElementById('fSid').readOnly = true;
    document.getElementById('fFullname').value = s.fullname;
    document.getElementById('fClass').value = s.classroom ?? '';
    document.getElementById('fVillage').value = s.village ?? '';
    document.getElementById('fNid').placeholder = 'เว้นว่างถ้าไม่ต้องการเปลี่ยนรหัสผ่าน';
    document.getElementById('nidHint').textContent = 'ถ้าไม่กรอก รหัสผ่านเดิมจะยังคงอยู่';
    document.getElementById('fActive').checked = s.is_active == 1;
    document.getElementById('activeRow').style.display = '';
    document.getElementById('stuFormCard').scrollIntoView({behavior:'smooth'});
}
function resetStuForm() {
    document.getElementById('stuFormTitle').innerHTML = '<i class="fas fa-user-plus me-2 text-primary"></i>เพิ่มนักเรียน';
    document.getElementById('stuBtnLabel').textContent = 'เพิ่มนักเรียน';
    document.getElementById('stuAction').value = 'add';
    document.getElementById('stuId').value = '';
    document.getElementById('fSid').readOnly = false;
    document.getElementById('fNid').placeholder = 'ใส่เฉพาะตัวเลข 13 หลัก';
    document.getElementById('nidHint').textContent = 'ใช้เป็นรหัสผ่านในการเข้าระบบ';
    document.getElementById('activeRow').style.display = 'none';
    document.getElementById('stuForm').reset();
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
