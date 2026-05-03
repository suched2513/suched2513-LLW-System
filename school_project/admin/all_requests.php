<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','director','budget_officer','procurement_head','finance_head','deputy_director']);
$db = getDB();

$filter  = $_GET['status'] ?? '';
$dept    = (int)($_GET['dept'] ?? 0);
$q       = trim($_GET['q'] ?? '');
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));

$params = [];
$where  = "WHERE 1=1";
if ($filter) { $where .= " AND pr.status = ?";            $params[] = $filter; }
if ($dept)   { $where .= " AND d.id = ?";                 $params[] = $dept; }
if ($q)      { $where .= " AND (bp.project_name LIKE ? OR CONCAT(u.firstname,' ',u.lastname) LIKE ?)";
               $params[] = "%$q%"; $params[] = "%$q%"; }

$baseQuery = "FROM project_requests pr
              JOIN budget_projects bp ON pr.budget_project_id = bp.id
              JOIN llw_users u ON pr.user_id = u.user_id
              JOIN departments d ON bp.department_id = d.id
              $where";

$countStmt = $db->prepare("SELECT COUNT(*) $baseQuery");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$dataParams = array_merge($params, [$perPage, $offset]);
$s = $db->prepare("SELECT pr.*,bp.project_name,
                          CONCAT(u.firstname,' ',u.lastname) AS teacher_name,
                          d.name AS dept_name
                   $baseQuery
                   ORDER BY pr.created_at DESC
                   LIMIT ? OFFSET ?");
$s->execute($dataParams);
$requests = $s->fetchAll();

$depts = $db->query("SELECT * FROM departments ORDER BY order_no")->fetchAll();

// Build base URL for filter links (preserve all params except page)
function filterUrl(array $override = []): string {
    $p = array_merge([
        'status' => $_GET['status'] ?? '',
        'dept'   => $_GET['dept'] ?? 0,
        'q'      => $_GET['q'] ?? '',
        'page'   => 1,
    ], $override);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== 0 && $v !== '0'));
}

renderHead('คำขอทั้งหมด');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('คำขอทั้งหมด'); echo '<div class="page-content">'; showFlash();
?>

<!-- Filter bar -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
      <input type="hidden" name="status" value="<?= h($filter) ?>">
      <input type="hidden" name="dept"   value="<?= $dept ?>">
      <input class="form-control form-control-sm" style="width:220px" type="text"
             name="q" value="<?= h($q) ?>" placeholder="ค้นหาโครงการ / ผู้ขอ…">
      <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search me-1"></i>ค้นหา</button>
      <?php if ($q): ?>
      <a href="<?= filterUrl(['q' => '']) ?>" class="btn btn-sm btn-outline-secondary">ล้าง</a>
      <?php endif; ?>
    </form>
  </div>
</div>
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= filterUrl(['status' => '']) ?>" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline-secondary' ?>">ทั้งหมด</a>
      <?php foreach (['submitted' => 'รออนุมัติ', 'approved' => 'อนุมัติ', 'rejected' => 'ปฏิเสธ', 'draft' => 'Draft'] as $st => $l): ?>
      <a href="<?= filterUrl(['status' => $st]) ?>" class="btn btn-sm <?= $filter === $st ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
      <select class="form-select form-select-sm" style="width:auto"
              onchange="location='<?= filterUrl(['dept' => '__D__']) ?>'.replace('__D__',this.value)">
        <option value="0">-- ทุกฝ่าย --</option>
        <?php foreach ($depts as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $dept == $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-inbox me-2"></i>คำขอทั้งหมด (<?= $total ?> รายการ)</span>
    <?php if ($totalPages > 1): ?>
    <small class="text-muted">หน้า <?= $page ?>/<?= $totalPages ?></small>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr>
        <th class="ps-4">โครงการ</th>
        <th>ฝ่าย</th>
        <th>ผู้ขอ</th>
        <th>ประเภท</th>
        <th class="text-end">วงเงิน</th>
        <th class="text-center">สถานะ</th>
        <th class="text-center">เอกสาร</th>
      </tr></thead>
      <tbody>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td class="ps-4"><div style="font-weight:500"><?= h($r['project_name']) ?></div></td>
        <td><?= h($r['dept_name']) ?></td>
        <td><?= h($r['teacher_name']) ?></td>
        <td><?= $r['proc_type'] === 'hire' ? 'จัดจ้าง' : 'จัดซื้อ' ?></td>
        <td class="text-end fw-semibold"><?= formatMoney($r['amount_requested']) ?></td>
        <td class="text-center"><?= statusBadge($r['status']) ?></td>
        <td class="text-center">
          <?php if (in_array($r['status'], ['submitted', 'approved'])): ?>
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">เอกสาร</button>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/documents/gen_memo.php?id=<?= $r['id'] ?>" target="_blank">บันทึกขออนุมัติ</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/documents/gen_committee.php?id=<?= $r['id'] ?>" target="_blank">แต่งตั้งกรรมการ</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/documents/gen_delivery.php?id=<?= $r['id'] ?>" target="_blank">ใบส่งมอบงาน</a></li>
            </ul>
          </div>
          <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($requests)): ?>
      <tr><td colspan="7" class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>ไม่พบรายการที่ตรงกับเงื่อนไข
      </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted">แสดง <?= ($offset + 1) ?>–<?= min($offset + $perPage, $total) ?> จาก <?= $total ?> รายการ</small>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= filterUrl(['page' => $page - 1]) ?>">‹</a>
        </li>
        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        if ($start > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
        for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="<?= filterUrl(['page' => $i]) ?>"><?= $i ?></a>
        </li>
        <?php endfor;
        if ($end < $totalPages): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= filterUrl(['page' => $page + 1]) ?>">›</a>
        </li>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>
