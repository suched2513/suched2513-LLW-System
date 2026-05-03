<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin']);
$db = getDB();

// Check if step_key column exists (migration may not have run yet)
$hasStepKey = false;
try {
    $cols = $db->query("SHOW COLUMNS FROM `signatories` LIKE 'step_key'")->fetchAll();
    $hasStepKey = !empty($cols);
} catch (Exception $e) { $hasStepKey = false; }

// Load active users for linked_user_id dropdown
$users = $db->query("SELECT user_id, CONCAT(firstname,' ',lastname) AS full_name, role FROM llw_users WHERE status='active' ORDER BY full_name")->fetchAll();

$stepOptions = [
    '' => '— ไม่ระบุขั้นตอน —',
    'submitted'            => 'ขั้นที่ 1: ตรวจสอบงบประมาณ (แผนงาน)',
    'budget_approved'      => 'ขั้นที่ 2: ตรวจสอบพัสดุ',
    'procurement_approved' => 'ขั้นที่ 3: ตรวจสอบการเงิน',
    'finance_approved'     => 'ขั้นที่ 4: รองผู้อำนวยการ',
    'deputy_approved'      => 'ขั้นที่ 5: ผู้อำนวยการโรงเรียน',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action       = $_POST['action'] ?? '';
    $stepKey      = $hasStepKey ? (trim($_POST['step_key'] ?? '') ?: null) : null;
    $linkedUserId = $hasStepKey ? ((int)($_POST['linked_user_id'] ?? 0) ?: null) : null;

    if ($action === 'create') {
        if ($hasStepKey) {
            $s = $db->prepare("INSERT INTO signatories (role_label,full_name,position,order_no,step_key,linked_user_id) VALUES (?,?,?,?,?,?)");
            $s->execute([$_POST['role_label'], $_POST['full_name'], $_POST['position'], (int)$_POST['order_no'], $stepKey, $linkedUserId]);
        } else {
            $s = $db->prepare("INSERT INTO signatories (role_label,full_name,position,order_no) VALUES (?,?,?,?)");
            $s->execute([$_POST['role_label'], $_POST['full_name'], $_POST['position'], (int)$_POST['order_no']]);
        }
        flashMessage('success', 'เพิ่มผู้ลงนามเรียบร้อย');
    } elseif ($action === 'update') {
        if ($hasStepKey) {
            $s = $db->prepare("UPDATE signatories SET role_label=?,full_name=?,position=?,order_no=?,is_active=?,step_key=?,linked_user_id=? WHERE id=?");
            $s->execute([$_POST['role_label'], $_POST['full_name'], $_POST['position'], (int)$_POST['order_no'], (int)$_POST['is_active'], $stepKey, $linkedUserId, (int)$_POST['sig_id']]);
        } else {
            $s = $db->prepare("UPDATE signatories SET role_label=?,full_name=?,position=?,order_no=?,is_active=? WHERE id=?");
            $s->execute([$_POST['role_label'], $_POST['full_name'], $_POST['position'], (int)$_POST['order_no'], (int)$_POST['is_active'], (int)$_POST['sig_id']]);
        }
        flashMessage('success', 'อัปเดตเรียบร้อย');
    }
    header('Location: /admin/signatories.php'); exit;
}

// Load signatories with linked user name
if ($hasStepKey) {
    $sigs = $db->query("
        SELECT s.*, CONCAT(u.firstname,' ',u.lastname) AS linked_name
        FROM signatories s
        LEFT JOIN llw_users u ON u.user_id = s.linked_user_id
        ORDER BY s.order_no
    ")->fetchAll();
} else {
    $sigs = $db->query("SELECT * FROM signatories ORDER BY order_no")->fetchAll();
}

renderHead('ผู้ลงนาม');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('จัดการผู้ลงนาม'); echo '<div class="page-content">'; showFlash();
?>

<?php if (!$hasStepKey): ?>
<div class="alert alert-warning mb-3">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <strong>กรุณา run migration ก่อน</strong> เพื่อเปิดใช้การล็อคขั้นตอนการลงนาม:
  <code>php database/migrate.php</code>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- Add form -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header">เพิ่มผู้ลงนาม</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="create">
          <div class="mb-2">
            <label class="form-label">บทบาท / ตำแหน่งทางการ</label>
            <input type="text" name="role_label" class="form-control" required placeholder="เช่น หัวหน้าแผนงาน">
          </div>
          <div class="mb-2">
            <label class="form-label">ชื่อ-สกุล</label>
            <input type="text" name="full_name" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">ตำแหน่ง (รายละเอียด)</label>
            <input type="text" name="position" class="form-control" placeholder="เช่น ครูชำนาญการพิเศษ">
          </div>
          <div class="mb-2">
            <label class="form-label">ลำดับ</label>
            <input type="number" name="order_no" class="form-control" value="<?= count($sigs) + 1 ?>">
          </div>
          <?php if ($hasStepKey): ?>
          <div class="mb-2">
            <label class="form-label">ล็อคขั้นตอนการลงนาม</label>
            <select name="step_key" class="form-select">
              <?php foreach ($stepOptions as $k => $v): ?>
                <option value="<?= h($k) ?>"><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">หากกำหนด เฉพาะผู้ใช้ที่ระบุเท่านั้นจะลงนามขั้นตอนนี้ได้</div>
          </div>
          <div class="mb-3">
            <label class="form-label">ผูกบัญชีผู้ใช้ (ผู้ลงนามเฉพาะ)</label>
            <select name="linked_user_id" class="form-select">
              <option value="">— ไม่ระบุ (ใครก็ได้ที่มี role) —</option>
              <?php foreach ($users as $usr): ?>
                <option value="<?= $usr['user_id'] ?>"><?= h($usr['full_name']) ?> (<?= h($usr['role']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-plus-circle me-1"></i>เพิ่ม
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>รายชื่อผู้ลงนาม</span>
        <span class="badge bg-secondary"><?= count($sigs) ?> คน</span>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0" style="font-size:14px">
          <thead>
            <tr>
              <th class="ps-3">#</th>
              <th>บทบาท / ชื่อ</th>
              <?php if ($hasStepKey): ?><th>ล็อคขั้นตอน</th><?php endif; ?>
              <th class="text-center">สถานะ</th>
              <th class="text-center">แก้ไข</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sigs as $s): ?>
            <tr>
              <td class="ps-3 text-muted"><?= (int)$s['order_no'] ?></td>
              <td>
                <div class="fw-semibold"><?= h($s['full_name']) ?></div>
                <small class="text-muted"><?= h($s['role_label']) ?><?= $s['position'] ? ' · ' . h($s['position']) : '' ?></small>
              </td>
              <?php if ($hasStepKey): ?>
              <td>
                <?php if (!empty($s['step_key'])): ?>
                  <span class="badge bg-primary"><?= h($stepOptions[$s['step_key']] ?? $s['step_key']) ?></span>
                  <?php if (!empty($s['linked_user_id'])): ?>
                    <div class="mt-1"><small class="text-success"><i class="bi bi-person-check me-1"></i><?= h($s['linked_name'] ?? '—') ?></small></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
              <td class="text-center">
                <?= $s['is_active'] ? '<span class="badge bg-success">ใช้งาน</span>' : '<span class="badge bg-secondary">ปิด</span>' ?>
              </td>
              <td class="text-center">
                <button class="btn btn-sm btn-outline-secondary"
                  onclick="openEdit(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($sigs)): ?>
            <tr><td colspan="<?= $hasStepKey ? 5 : 4 ?>" class="text-center text-muted py-4">ยังไม่มีข้อมูลผู้ลงนาม</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($hasStepKey): ?>
    <!-- Step lock summary -->
    <div class="card mt-3">
      <div class="card-header"><i class="bi bi-diagram-3 me-2"></i>สรุปการล็อคขั้นตอน</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:13px">
          <thead><tr><th class="ps-3">ขั้นตอน</th><th>ผู้ลงนามที่กำหนด</th><th>สถานะ</th></tr></thead>
          <tbody>
          <?php
          // Build step → signatory map
          $sigByStep = [];
          foreach ($sigs as $s) {
              if (!empty($s['step_key'])) $sigByStep[$s['step_key']] = $s;
          }
          foreach ($stepOptions as $k => $v):
              if ($k === '') continue;
              $mapped = $sigByStep[$k] ?? null;
          ?>
          <tr>
            <td class="ps-3"><?= h($v) ?></td>
            <td>
              <?php if ($mapped): ?>
                <strong><?= h($mapped['full_name']) ?></strong>
                <span class="text-muted"> — <?= h($mapped['role_label']) ?></span>
              <?php else: ?>
                <span class="text-muted">ไม่ได้ล็อค</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($mapped && !empty($mapped['linked_user_id'])): ?>
                <span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i>ล็อคแล้ว</span>
              <?php elseif ($mapped): ?>
                <span class="badge bg-warning text-dark">มีชื่อแต่ไม่ผูก account</span>
              <?php else: ?>
                <span class="badge bg-light text-secondary border">ใช้ role ปกติ</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="sig_id" id="edit_sig_id">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>แก้ไขผู้ลงนาม</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">บทบาท / ตำแหน่งทางการ</label>
            <input type="text" name="role_label" id="edit_role_label" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">ชื่อ-สกุล</label>
            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">ตำแหน่ง (รายละเอียด)</label>
            <input type="text" name="position" id="edit_position" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">ลำดับ</label>
            <input type="number" name="order_no" id="edit_order_no" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">สถานะ</label>
            <select name="is_active" id="edit_is_active" class="form-select">
              <option value="1">ใช้งาน</option>
              <option value="0">ปิดการใช้งาน</option>
            </select>
          </div>
          <?php if ($hasStepKey): ?>
          <hr>
          <div class="mb-2">
            <label class="form-label">ล็อคขั้นตอนการลงนาม</label>
            <select name="step_key" id="edit_step_key" class="form-select">
              <?php foreach ($stepOptions as $k => $v): ?>
                <option value="<?= h($k) ?>"><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">ผูกบัญชีผู้ใช้ (ผู้ลงนามเฉพาะ)</label>
            <select name="linked_user_id" id="edit_linked_user_id" class="form-select">
              <option value="">— ไม่ระบุ —</option>
              <?php foreach ($users as $usr): ?>
                <option value="<?= $usr['user_id'] ?>"><?= h($usr['full_name']) ?> (<?= h($usr['role']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary">บันทึก</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(sig) {
    document.getElementById('edit_sig_id').value = sig.id;
    document.getElementById('edit_role_label').value = sig.role_label || '';
    document.getElementById('edit_full_name').value = sig.full_name || '';
    document.getElementById('edit_position').value = sig.position || '';
    document.getElementById('edit_order_no').value = sig.order_no || 1;
    document.getElementById('edit_is_active').value = sig.is_active ?? 1;
    <?php if ($hasStepKey): ?>
    document.getElementById('edit_step_key').value = sig.step_key || '';
    document.getElementById('edit_linked_user_id').value = sig.linked_user_id || '';
    <?php endif; ?>
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php echo '</div></div></div>'; renderFooter(); ?>
