<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ระบบเงินยืมราชการและล้างเงินยืม (แบบ 8500)</h4>
        <div class="text-muted small">สัญญายืมเงินราชการ การจ่ายเงินยืม การติดตามครบกำหนด 30 วัน และการส่งหลักฐานล้างเงินยืม</div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/advances/create" class="btn btn-primary-gov btn-sm">
            <i class="bi bi-file-earmark-plus me-1"></i> ทำสัญญายืมเงินใหม่
        </a>
    </div>
</div>

<!-- Status Filters -->
<ul class="nav nav-pills mb-3 gap-1">
    <li class="nav-item">
        <a class="nav-link <?= empty($currentStatus) ? 'active' : '' ?>" href="<?= $baseUrl ?>/advances">ทั้งหมด</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'draft' ? 'active' : '' ?>" href="<?= $baseUrl ?>/advances?status=draft">ร่างสัญญา</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'approved' ? 'active' : '' ?>" href="<?= $baseUrl ?>/advances?status=approved">อนุมัติแล้ว (รอรับเงิน)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'disbursed' ? 'active' : '' ?>" href="<?= $baseUrl ?>/advances?status=disbursed">จ่ายเงินแล้ว (ระหว่างดำเนินงาน)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'clearing_pending' ? 'active' : '' ?>" href="<?= $baseUrl ?>/advances?status=clearing_pending">รอล้างสัญญา</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'settled' ? 'active' : '' ?>" href="<?= $baseUrl ?>/advances?status=settled">ล้างเรียบร้อย</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-danger <?= $currentStatus === 'overdue' ? 'active' : '' ?>" href="<?= $baseUrl ?>/advances?status=overdue">
            <i class="bi bi-exclamation-circle-fill me-1"></i> เกินกำหนดชำระ
        </a>
    </li>
</ul>

<!-- Advances Table -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-journal-arrow-up me-2"></i> ทะเบียนคุมสัญญายืมเงินราชการ (<?= count($advances) ?> สัญญา)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>เลขที่สัญญา (แบบ 8500)</th>
                    <th>วันที่ทำสัญญา</th>
                    <th>ผู้ยืมเงิน</th>
                    <th>โครงการ / วัตถุประสงค์</th>
                    <th class="text-end">วงเงินที่ยืม (บาท)</th>
                    <th>วันครบกำหนดส่งใช้</th>
                    <th>สถานะสัญญา</th>
                    <th class="text-end">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($advances)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">ไม่พบสัญญายืมเงินในระบบ</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $statusBadges = [
                        'draft'            => ['bg-secondary', 'ร่างสัญญา'],
                        'approved'         => ['bg-info text-dark', 'อนุมัติแล้ว'],
                        'disbursed'        => ['bg-warning text-dark', 'จ่ายเงินแล้ว'],
                        'clearing_pending' => ['bg-primary', 'ส่งหลักฐานรอล้าง'],
                        'settled'          => ['bg-success', 'ล้างสัญญาแล้ว'],
                        'overdue'          => ['bg-danger', 'เกินกำหนดชำระ']
                    ];
                    foreach ($advances as $a): 
                        [$badgeClass, $badgeText] = $statusBadges[$a['status']] ?? ['bg-secondary', $a['status']];
                        $isOverdue = ($a['status'] === 'disbursed' || $a['status'] === 'overdue') && strtotime($a['due_date']) < time();
                    ?>
                        <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                            <td class="fw-bold">
                                <a href="<?= $baseUrl ?>/advances/show/<?= $a['id'] ?>" class="text-decoration-none">
                                    <code><?= e($a['contract_no']) ?></code>
                                </a>
                            </td>
                            <td><?= thai_date($a['contract_date'], false, true) ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($a['borrower_name']) ?></div>
                                <small class="text-muted"><?= e($a['borrower_email'] ?? '-') ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($a['project_name']) ?></div>
                                <small class="text-muted text-truncate d-block" style="max-width: 250px;"><?= e($a['objective']) ?></small>
                            </td>
                            <td class="text-end fw-bold text-navy"><?= money_format_th($a['amount']) ?></td>
                            <td>
                                <div><?= thai_date($a['due_date'], false, true) ?></div>
                                <?php if ($isOverdue): ?>
                                    <small class="badge bg-danger">เกินกำหนด!</small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span></td>
                            <td class="text-end">
                                <a href="<?= $baseUrl ?>/advances/show/<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="ดูสัญญา / ล้างเงินยืม">
                                    <i class="bi bi-file-earmark-text me-1"></i> รายละเอียด
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
