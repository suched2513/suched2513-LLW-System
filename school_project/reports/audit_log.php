<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/layout.php';
requireRole(['admin']);
$db = getDB();
$limit = 100;
$stmtLogs = $db->prepare("SELECT al.*,CONCAT(u.firstname,' ',u.lastname) AS full_name FROM audit_logs al LEFT JOIN llw_users u ON al.user_id=u.user_id ORDER BY al.created_at DESC LIMIT ?");
$stmtLogs->execute([$limit]); $logs = $stmtLogs->fetchAll();
renderHead('Log ระบบ');
echo '<div class="d-flex">'; renderSidebar(); echo '<div class="main-content flex-grow-1">'; renderTopbar('Log ระบบ'); echo '<div class="page-content">'; showFlash();
?>
<div class="card"><div class="card-header"><i class="bi bi-shield-check me-2"></i>Audit Log ล่าสุด <?=$limit?> รายการ</div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
<thead><tr><th class="ps-3">เวลา</th><th>ผู้ใช้</th><th>การกระทำ</th><th>เป้าหมาย</th><th>IP</th></tr></thead>
<tbody>
<?php foreach ($logs as $l): ?>
<tr>
  <td class="ps-3" style="font-size:12px;white-space:nowrap"><?=h($l['created_at'])?></td>
  <td style="font-size:13px"><?=h($l['full_name']??'ไม่ระบุ')?></td>
  <td><span class="badge bg-secondary"><?=h($l['action'])?></span></td>
  <td style="font-size:12px;color:#64748b"><?=h($l['target_type'])?> <?=h($l['target_id']??'')?></td>
  <td style="font-size:12px;color:#64748b"><?=h($l['ip_address']??'')?></td>
</tr>
<?php endforeach; if(empty($logs)): ?><tr><td colspan="5" class="text-center py-4 text-muted">ไม่มี Log</td></tr><?php endif; ?>
</tbody>
</table></div></div></div>
<?php echo '</div></div></div>'; renderFooter(); ?>