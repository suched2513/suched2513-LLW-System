<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','super_admin','director','budget_officer','wfh_admin','procurement_head','finance_head','deputy_director']);
$db = getDB();
$fy = (int)($_GET['fy'] ?? FISCAL_YEAR);

try {
    $stmt = $db->prepare("
        SELECT 
            d.name AS department_name,
            COUNT(bp.id) AS total_projects,
            SUM(CASE WHEN pr.id IS NULL THEN 1 ELSE 0 END) AS no_request,
            SUM(CASE WHEN pr.status = 'draft' THEN 1 ELSE 0 END) AS cnt_draft,
            SUM(CASE WHEN pr.status = 'submitted' THEN 1 ELSE 0 END) AS cnt_submitted,
            SUM(CASE WHEN pr.status = 'approved' THEN 1 ELSE 0 END) AS cnt_approved,
            SUM(CASE WHEN pr.status = 'rejected' THEN 1 ELSE 0 END) AS cnt_rejected
        FROM departments d
        LEFT JOIN budget_projects bp ON bp.department_id = d.id AND bp.fiscal_year = ?
        LEFT JOIN project_requests pr ON pr.budget_project_id = bp.id
        GROUP BY d.id, d.name
        ORDER BY d.order_no, d.name
    ");
    $stmt->execute([$fy]);
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    $rows = [];
    error_log($e->getMessage());
}
renderHead('ความคืบหน้าโครงการ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('รายงานความคืบหน้าโครงการ'); echo '<div class="page-content">'; showFlash();
?>
<div class="card">
  <div class="card-header"><i class="bi bi-clipboard-data me-2"></i>ความคืบหน้ารายฝ่าย ปีงบประมาณ <?=$fy?></div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th class="ps-4">ฝ่าย</th><th class="text-center">ทั้งหมด</th><th class="text-center">ยังไม่ดำเนินการ</th><th class="text-center">Draft</th><th class="text-center">รออนุมัติ</th><th class="text-center">อนุมัติ</th><th class="text-center">ปฏิเสธ</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="ps-4 fw-semibold"><?=h($r['department_name'])?></td>
        <td class="text-center"><span class="badge bg-secondary"><?=$r['total_projects']?></span></td>
        <td class="text-center"><?=$r['no_request']>0?'<span class="badge bg-warning text-dark">'.h($r['no_request']).'</span>':'<span class="text-muted">0</span>'?></td>
        <td class="text-center"><?=$r['cnt_draft']>0?'<span class="badge bg-secondary">'.h($r['cnt_draft']).'</span>':'<span class="text-muted">0</span>'?></td>
        <td class="text-center"><?=$r['cnt_submitted']>0?'<span class="badge bg-warning text-dark">'.h($r['cnt_submitted']).'</span>':'<span class="text-muted">0</span>'?></td>
        <td class="text-center"><?=$r['cnt_approved']>0?'<span class="badge bg-success">'.h($r['cnt_approved']).'</span>':'<span class="text-muted">0</span>'?></td>
        <td class="text-center"><?=$r['cnt_rejected']>0?'<span class="badge bg-danger">'.h($r['cnt_rejected']).'</span>':'<span class="text-muted">0</span>'?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php echo '</div></div></div>'; renderFooter(); ?>