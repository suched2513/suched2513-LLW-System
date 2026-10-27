<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$exportParams = http_build_query($filter);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ประวัติการใช้งานระบบ (Audit Trail)</h4>
        <div class="text-muted small">บันทึกทุกความเคลื่อนไหว การเข้าสู่ระบบ การเพิ่ม แก้ไข อนุมัติ และการลบข้อมูลเพื่อความโปร่งใสและตรวจสอบได้</div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/audit-logs/export?<?= $exportParams ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> ส่งออกเป็นไฟล์ CSV (Excel)
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card card-gov mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= $baseUrl ?>/audit-logs" class="row g-2 align-items-center">
            <div class="col-md-2">
                <select name="module" class="form-select form-select-sm">
                    <option value="">-- ทุกโมดูล --</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= e($m) ?>" <?= ($filter['module'] ?? '') === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="action" class="form-select form-select-sm">
                    <option value="">-- ทุกการกระทำ --</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= e($a) ?>" <?= ($filter['action'] ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="user_id" class="form-select form-select-sm">
                    <option value="0">-- ทุกผู้ใช้งาน --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ((int)($filter['user_id'] ?? 0)) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($filter['date_from'] ?? '') ?>" placeholder="ตั้งแต่วันที่">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($filter['date_to'] ?? '') ?>" placeholder="ถึงวันที่">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary-gov flex-grow-1"><i class="bi bi-search"></i> กรอง</button>
                <a href="<?= $baseUrl ?>/audit-logs" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-clock-history me-2"></i> บันทึกประวัติกิจกรรมล่าสุด (<?= count($logs) ?> รายการ)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0" style="font-size: 0.88rem;">
            <thead>
                <tr>
                    <th>วัน-เวลา</th>
                    <th>ผู้ใช้งาน</th>
                    <th>โมดูล</th>
                    <th>การกระทำ</th>
                    <th>ตาราง/รหัส</th>
                    <th>รายละเอียด / หมายเหตุ</th>
                    <th>IP Address</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">ไม่พบประวัติการใช้งานที่ตรงตามเงื่อนไข</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="text-nowrap small text-muted"><?= thai_date($l['created_at'], true, true) ?></td>
                            <td class="fw-semibold">
                                <?php if ($l['username']): ?>
                                    <?= e($l['full_name'] ?? $l['username']) ?>
                                    <div class="small text-muted">@<?= e($l['username']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">ระบบ / ผู้มาเยือน</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= e($l['module']) ?></span></td>
                            <td><code><?= e($l['action']) ?></code></td>
                            <td class="small text-muted"><?= e($l['table_name'] ?? '-') ?><?= $l['record_id'] ? " #{$l['record_id']}" : '' ?></td>
                            <td><?= e($l['notes'] ?? '-') ?></td>
                            <td class="small text-muted"><?= e($l['ip_address']) ?></td>
                            <td>
                                <?php if ($l['status'] === 'success'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">สำเร็จ</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= e($l['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
