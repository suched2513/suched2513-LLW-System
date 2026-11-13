<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['teacher','head','admin','budget_officer']);
$u = getCurrentUser();
$db = getDB();

// ดึงโครงการที่ตัวเองรับผิดชอบ - ใช้ user_id จาก llw_users
$ownerName = $u['owner_name'] ?? '';
$sql = "SELECT bp.*, d.name AS dept_name,
        pr.id AS req_id, pr.status AS req_status, pr.amount_requested, pr.proc_type
        FROM budget_projects bp
        JOIN departments d ON bp.department_id=d.id
        LEFT JOIN project_requests pr ON pr.budget_project_id=bp.id AND pr.user_id=?
        WHERE bp.is_active=1 AND bp.fiscal_year=?";
$params = [$u['id'], FISCAL_YEAR];
if ($ownerName) {
    $sql .= " AND bp.owner_name LIKE ?";
    $params[] = '%'.$ownerName.'%';
}
$sql .= " ORDER BY bp.department_id, bp.id";
$s = $db->prepare($sql);
$s->execute($params);
$projects = $s->fetchAll();

// ตัวเลือกฝ่ายที่มีอยู่ในรายการ และพารามิเตอร์ตัวกรอง
$departments = [];
foreach ($projects as $p) {
    $departments[(int)$p['department_id']] = $p['dept_name'];
}

$filterQ         = trim((string)($_GET['q'] ?? ''));
$filterDept      = (int)($_GET['dept_id'] ?? 0);
$filterStatus    = trim((string)($_GET['status'] ?? ''));
$filterHasBudget = (string)($_GET['has_budget'] ?? '');

$filteredProjects = array_values(array_filter($projects, function ($p) use ($filterQ, $filterDept, $filterStatus, $filterHasBudget) {
    $total = (float)$p['budget_subsidy'] + (float)$p['budget_quality'] + (float)$p['budget_revenue'] + (float)$p['budget_operation'] + (float)$p['budget_reserve'];
    if ($filterQ !== '') {
        $haystack = ($p['project_name'] ?? '') . ' ' . ($p['activity'] ?? '');
        if (mb_stripos($haystack, $filterQ) === false) {
            return false;
        }
    }
    if ($filterDept > 0 && (int)$p['department_id'] !== $filterDept) {
        return false;
    }
    if ($filterStatus !== '') {
        if ($filterStatus === 'not_started') {
            if (!empty($p['req_id'])) return false;
        } else {
            if (empty($p['req_id']) || ($p['req_status'] ?? '') !== $filterStatus) return false;
        }
    }
    if ($filterHasBudget === '1' && $total <= 0) {
        return false;
    }
    return true;
}));
$hasFilter = ($filterQ !== '' || $filterDept > 0 || $filterStatus !== '' || $filterHasBudget !== '');

renderHead('โครงการของฉัน');
echo '<div class="d-flex">';
renderSidebar();
echo '<div class="main-content flex-grow-1">';
renderTopbar('โครงการของฉัน');
echo '<div class="page-content">';
showFlash();
?>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#1a56db,#3b82f6)">
    <div class="stat-value"><?= count($projects) ?></div><div class="stat-label">โครงการทั้งหมด</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)">
    <div class="stat-value"><?= count(array_filter($projects, fn($p) => $p['req_status']==='submitted')) ?></div>
    <div class="stat-label">รออนุมัติ</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#10b981,#34d399)">
    <div class="stat-value"><?= count(array_filter($projects, fn($p) => $p['req_status']==='approved')) ?></div>
    <div class="stat-label">อนุมัติแล้ว</div></div></div>
  <div class="col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
    <div class="stat-value"><?= count(array_filter($projects, fn($p) => !$p['req_id'])) ?></div>
    <div class="stat-label">ยังไม่ดำเนินการ</div></div></div>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>
      <i class="bi bi-folder2-open me-2"></i>รายการโครงการ ปีงบประมาณ <?= FISCAL_YEAR ?>
      <span class="badge bg-light text-dark border ms-2">
        แสดง <?= count($filteredProjects) ?> จาก <?= count($projects) ?> รายการ
      </span>
    </span>
  </div>
  <div class="card-body border-bottom bg-light py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small text-muted mb-1">ค้นหาโครงการ / กิจกรรม</label>
        <input type="text" name="q" class="form-control form-control-sm" value="<?= h($filterQ) ?>" placeholder="พิมพ์คำค้นหา...">
      </div>
      <div class="col-md-3">
        <label class="form-label small text-muted mb-1">ฝ่าย</label>
        <select name="dept_id" class="form-select form-select-sm">
          <option value="">-- ทุกฝ่าย --</option>
          <?php foreach ($departments as $dId => $dName): ?>
          <option value="<?= $dId ?>" <?= $filterDept === $dId ? 'selected' : '' ?>><?= h($dName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">สถานะ</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">-- ทุกสถานะ --</option>
          <option value="not_started" <?= $filterStatus === 'not_started' ? 'selected' : '' ?>>ยังไม่ดำเนินการ</option>
          <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>ร่าง</option>
          <option value="submitted" <?= $filterStatus === 'submitted' ? 'selected' : '' ?>>รออนุมัติ</option>
          <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
          <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>ไม่อนุมัติ</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">งบประมาณ</label>
        <select name="has_budget" class="form-select form-select-sm">
          <option value="">ทั้งหมด</option>
          <option value="1" <?= $filterHasBudget === '1' ? 'selected' : '' ?>>เฉพาะมีงบ (> 0)</option>
        </select>
      </div>
      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary w-100" title="กรองข้อมูล">
          <i class="bi bi-funnel"></i>
        </button>
        <?php if ($hasFilter): ?>
        <a href="<?= BASE_URL ?>/teacher/my_projects.php" class="btn btn-sm btn-outline-secondary" title="ล้างตัวกรอง">
          <i class="bi bi-x-lg"></i>
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th class="ps-4">โครงการ / กิจกรรม</th>
          <th>ฝ่าย</th>
          <th class="text-end">งบจัดสรร (บาท)</th>
          <th class="text-center">สถานะ</th>
          <th class="text-center">ดำเนินการ</th>
        </tr></thead>
        <tbody>
<?php foreach ($filteredProjects as $p): ?>
        <tr>
          <td class="ps-4">
            <div style="font-weight:500;font-size:14px"><?= h($p['project_name']) ?></div>
            <?php if ($p['activity']): ?>
            <div style="font-size:12px;color:#64748b;margin-top:2px"><?= h(mb_substr($p['activity'],0,80)) ?>...</div>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-light text-dark"><?= h($p['dept_name']) ?></span></td>
          <td class="text-end">
            <?php $total = $p['budget_subsidy']+$p['budget_quality']+$p['budget_revenue']+$p['budget_operation']+$p['budget_reserve']; ?>
            <div style="font-weight:600;color:#1a56db"><?= formatMoney($total) ?></div>
            <?php if ($p['budget_quality']>0): ?><div style="font-size:11px;color:#64748b">คุณภาพ: <?= formatMoney($p['budget_quality']) ?></div><?php endif; ?>
            <?php if ($p['budget_revenue']>0): ?><div style="font-size:11px;color:#64748b">รายได้: <?= formatMoney($p['budget_revenue']) ?></div><?php endif; ?>
            <?php if ($p['budget_subsidy']>0): ?><div style="font-size:11px;color:#64748b">อุดหนุน: <?= formatMoney($p['budget_subsidy']) ?></div><?php endif; ?>
          </td>
          <td class="text-center">
            <?php if (!$p['req_id']): ?>
              <span class="badge bg-secondary">ยังไม่ดำเนินการ</span>
            <?php else: echo statusBadge($p['req_status']); endif; ?>
          </td>
          <td class="text-center">
            <?php if (!$p['req_id']): ?>
              <?php if ($total <= 0): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="ไม่สามารถขอดำเนินการได้ เนื่องจากงบจัดสรรเป็น 0.00 บาท">
                  <i class="bi bi-lock me-1"></i>ไม่มีงบจัดสรร
                </button>
              <?php else: ?>
                <a href="<?= BASE_URL ?>/teacher/request_form.php?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">
                  <i class="bi bi-plus-circle me-1"></i>ขอดำเนินการ
                </a>
              <?php endif; ?>
            <?php elseif ($p['req_status']==='draft'): ?>
              <a href="<?= BASE_URL ?>/teacher/request_form.php?req_id=<?= $p['req_id'] ?>" class="btn btn-sm btn-warning">
                <i class="bi bi-pencil me-1"></i>แก้ไข
              </a>
            <?php else: ?>
              <a href="<?= BASE_URL ?>/teacher/request_view.php?id=<?= $p['req_id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye me-1"></i>ดูรายละเอียด
              </a>
            <?php endif; ?>
          </td>
        </tr>
<?php endforeach; ?>
        <?php if (empty($filteredProjects)): ?>
        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i><?= $hasFilter ? 'ไม่พบโครงการที่ตรงกับเงื่อนไขตัวกรอง' : 'ไม่พบโครงการที่รับผิดชอบ' ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
echo '</div></div></div>';
renderFooter();
