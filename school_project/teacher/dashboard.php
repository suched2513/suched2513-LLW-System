<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();
$u  = currentUser();
$db = getDB();

// สถิติ
$stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM project_requests WHERE user_id=? GROUP BY status");
$stmt->execute([$u['id']]);
$stats = array_column($stmt->fetchAll(), 'cnt', 'status');

// โครงการของตัวเอง
$stmt = $db->prepare("
  SELECT bp.*, d.name AS dept_name,
    pr.id AS req_id, pr.status AS req_status, pr.amount_requested, pr.updated_at AS req_updated
  FROM budget_projects bp
  JOIN departments d ON bp.department_id = d.id
  LEFT JOIN project_requests pr ON pr.budget_project_id = bp.id
    AND pr.id = (SELECT id FROM project_requests WHERE budget_project_id=bp.id ORDER BY created_at DESC LIMIT 1)
  WHERE bp.owner_name LIKE ? AND bp.is_active=1
  ORDER BY bp.department_id, bp.id
");
$stmt->execute(['%' . $u['name'] . '%']);
$projects = $stmt->fetchAll();

// แจ้งเตือนล่าสุด
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$u['id']]);
$notifs = $stmt->fetchAll();

renderHead('Dashboard ของฉัน');
?>
<div class="d-flex">
<?php renderSidebar(); ?>
<div class="main">
<?php renderTopbar('Dashboard ของฉัน'); ?>
<div class="content">

  <!-- Metric cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="metric-card blue">
        <div class="metric-label">โครงการทั้งหมด</div>
        <div class="metric-value"><?= count($projects) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="metric-card yellow">
        <div class="metric-label">รออนุมัติ</div>
        <div class="metric-value"><?= ($stats['submitted'] ?? 0) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="metric-card green">
        <div class="metric-label">อนุมัติแล้ว</div>
        <div class="metric-value"><?= ($stats['approved'] ?? 0) ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="metric-card">
        <div class="metric-label">Draft / ยังไม่ส่ง</div>
        <div class="metric-value"><?= ($stats['draft'] ?? 0) ?></div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- โครงการ -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          โครงการของฉัน
          <a href="<?= APP_URL ?>/teacher/my_projects.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>โครงการ</th><th>งบที่ได้รับ</th><th>สถานะคำขอ</th><th></th></tr></thead>
              <tbody>
              <?php if (empty($projects)): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">ยังไม่มีโครงการที่รับผิดชอบ</td></tr>
              <?php else: foreach ($projects as $p): ?>
              <tr>
                <td>
                  <div class="fw-500" style="font-size:14px"><?= h($p['project_name']) ?></div>
                  <div class="text-muted" style="font-size:12px"><?= h($p['dept_name']) ?></div>
                </td>
                <td style="font-size:13px">
                  <?php $total = $p['budget_subsidy']+$p['budget_quality']+$p['budget_revenue']+$p['budget_operation']+$p['budget_reserve']; ?>
                  <?= number_format($total, 0) ?> บาท
                </td>
                <td>
                  <?php if ($p['req_id']): ?>
                  <span class="badge-status badge-<?= h($p['req_status']) ?>"><?= h($p['req_status']) ?></span>
                  <?php else: ?>
                  <span class="badge-status badge-draft">ยังไม่ดำเนินการ</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!$p['req_id'] || $p['req_status'] === 'draft'): ?>
                  <a href="<?= APP_URL ?>/teacher/request_form.php?bp_id=<?= $p['id'] ?><?= $p['req_id'] ? '&req_id='.$p['req_id'] : '' ?>" class="btn btn-sm btn-primary">ขอดำเนินการ</a>
                  <?php else: ?>
                  <a href="<?= APP_URL ?>/teacher/request_view.php?id=<?= $p['req_id'] ?>" class="btn btn-sm btn-outline-secondary">ดู</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- แจ้งเตือน -->
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">การแจ้งเตือน</div>
        <div class="card-body p-0">
          <?php if (empty($notifs)): ?>
          <p class="text-center text-muted py-4" style="font-size:13px">ไม่มีการแจ้งเตือน</p>
          <?php else: foreach ($notifs as $n): ?>
          <div class="d-flex gap-2 p-3 border-bottom <?= $n['is_read'] ? '' : 'bg-light' ?>">
            <div class="flex-grow-1">
              <div style="font-size:13px;font-weight:500"><?= h($n['title']) ?></div>
              <div style="font-size:12px;color:#6b7280"><?= h($n['message']) ?></div>
              <div style="font-size:11px;color:#9ca3af"><?= thDate($n['created_at']) ?></div>
            </div>
            <?php if (!$n['is_read']): ?><span class="badge bg-primary" style="height:fit-content;font-size:10px">ใหม่</span><?php endif; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>
</div>
</div>
<?php renderFoot(); ?>
