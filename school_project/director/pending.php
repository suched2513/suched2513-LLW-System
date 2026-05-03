<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['director','admin','budget_officer','procurement_head','finance_head','deputy_director']);
$u = getCurrentUser();
$db = getDB();

$roleStepMap = [
    'budget_officer'   => 'submitted',
    'wfh_admin'        => 'submitted',
    'procurement_head' => 'budget_approved',
    'finance_head'     => 'procurement_approved',
    'deputy_director'  => 'finance_approved',
    'director'         => 'deputy_approved',
    'admin'            => 'all',
    'super_admin'      => 'all'
];
$myStep = $roleStepMap[$u['role']] ?? '';

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$params = [];
$where  = "WHERE pr.status NOT IN ('approved','rejected','draft')";
if ($myStep !== 'all' && $myStep !== '') {
    $where    .= " AND pr.current_step = ?";
    $params[]  = $myStep;
}

$baseQuery = "FROM project_requests pr
              JOIN budget_projects bp ON pr.budget_project_id = bp.id
              JOIN llw_users u ON pr.user_id = u.user_id
              JOIN departments d ON bp.department_id = d.id
              $where";

$countStmt = $db->prepare("SELECT COUNT(*) $baseQuery");
$countStmt->execute($params);
$total     = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT pr.*,bp.project_name,bp.activity,
                             CONCAT(u.firstname,' ',u.lastname) AS teacher_name,
                             d.name AS dept_name
                      $baseQuery
                      ORDER BY pr.created_at ASC
                      LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$requests = $stmt->fetchAll();

renderHead('รออนุมัติ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('คำขอรออนุมัติ'); echo '<div class="page-content">'; showFlash();
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-hourglass-split me-2"></i>คำขอรออนุมัติ (<?= $total ?> รายการ)</span>
    <?php if ($totalPages > 1): ?>
    <small class="text-muted">หน้า <?= $page ?>/<?= $totalPages ?></small>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th class="ps-4">โครงการ</th>
          <th>ฝ่าย</th>
          <th>ผู้ขอ</th>
          <th>ประเภท</th>
          <th class="text-end">วงเงิน</th>
          <th>วันที่ยื่น</th>
          <th class="text-center">ดำเนินการ</th>
        </tr></thead>
        <tbody>
<?php foreach ($requests as $r): ?>
        <tr>
          <td class="ps-4">
            <div style="font-weight:500"><?= h($r['project_name']) ?></div>
            <div style="font-size:12px;color:#64748b"><?= h(mb_substr($r['activity'] ?? '', 0, 60)) ?></div>
          </td>
          <td><?= h($r['dept_name']) ?></td>
          <td><?= h($r['teacher_name']) ?></td>
          <td><?= $r['proc_type'] === 'hire' ? 'จัดจ้าง' : 'จัดซื้อ' ?></td>
          <td class="text-end fw-semibold text-primary"><?= formatMoney($r['amount_requested']) ?></td>
          <td><?= formatDate($r['created_at']) ?></td>
          <td class="text-center">
            <a href="<?= BASE_URL ?>/director/approve.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary">
              <i class="bi bi-pencil-square me-1"></i>พิจารณาและลงนาม
            </a>
          </td>
        </tr>
<?php endforeach; ?>
<?php if (empty($requests)): ?>
        <tr><td colspan="7" class="text-center py-5 text-muted">
          <i class="bi bi-check-all fs-2 d-block mb-2"></i>ไม่มีคำขอรออนุมัติในขณะนี้
        </td></tr>
<?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php if ($totalPages > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted">แสดง <?= ($offset + 1) ?>–<?= min($offset + $perPage, $total) ?> จาก <?= $total ?> รายการ</small>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= $page - 1 ?>">‹</a>
        </li>
        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        if ($start > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
        for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor;
        if ($end < $totalPages): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= $page + 1 ?>">›</a>
        </li>
      </ul>
    </nav>
  </div>
<?php endif; ?>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>
