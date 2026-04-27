<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin','director','budget_officer']);
$db = getDB();
$fy = (int)($_GET['fy'] ?? FISCAL_YEAR);
$s = $db->prepare("SELECT * FROM v_project_status_summary WHERE fiscal_year=? ORDER BY department_name");
$s->execute([$fy]); $rows = $s->fetchAll();
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