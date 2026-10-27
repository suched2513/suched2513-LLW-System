<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ระบบเบิกจ่ายเงินและใบสำคัญจ่าย</h4>
        <div class="text-muted small">เวิร์กโฟลว์ 6 ขั้นตอน: ร่าง → เสนอ → ตรวจสอบ → อนุมัติ → จ่ายเงิน → ลงบัญชีคู่</div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/disbursements/create" class="btn btn-primary-gov btn-sm">
            <i class="bi bi-plus-circle me-1"></i> สร้างคำขอเบิกจ่ายใหม่
        </a>
    </div>
</div>

<!-- Status Filter Tabs -->
<ul class="nav nav-pills mb-3 gap-1">
    <li class="nav-item">
        <a class="nav-link <?= empty($currentStatus) ? 'active' : '' ?>" href="<?= $baseUrl ?>/disbursements">ทั้งหมด</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'draft' ? 'active' : '' ?>" href="<?= $baseUrl ?>/disbursements?status=draft">ร่าง</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'proposed' ? 'active' : '' ?>" href="<?= $baseUrl ?>/disbursements?status=proposed">รอตรวจสอบ</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'audited' ? 'active' : '' ?>" href="<?= $baseUrl ?>/disbursements?status=audited">รออนุมัติ</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'approved' ? 'active' : '' ?>" href="<?= $baseUrl ?>/disbursements?status=approved">รอจ่ายเงิน</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'disbursed' ? 'active' : '' ?>" href="<?= $baseUrl ?>/disbursements?status=disbursed">จ่ายแล้ว</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentStatus === 'posted' ? 'active' : '' ?>" href="<?= $baseUrl ?>/disbursements?status=posted">ลงบัญชีแล้ว</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-danger <?= $currentStatus === 'rejected' ? 'active' : '' ?>" href="<?= $baseUrl ?>/disbursements?status=rejected">ส่งกลับ/ปฏิเสธ</a>
    </li>
</ul>

<!-- Disbursements Table -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-cash-stack me-2"></i> รายการคำขอเบิกจ่าย (<?= count($payments) ?> รายการ)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>เลขที่ใบสำคัญ (PV)</th>
                    <th>วันที่</th>
                    <th>โครงการ / หมวดงบประมาณ</th>
                    <th>ผู้รับเงิน</th>
                    <th>ผู้ขอเบิก</th>
                    <th class="text-end">ยอดเบิกสุทธิ (บาท)</th>
                    <th>สถานะ</th>
                    <th class="text-end">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">ไม่พบรายการเบิกจ่ายในสถานะนี้</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $statusBadges = [
                        'draft'     => ['bg-secondary', 'ร่างคำขอ'],
                        'proposed'  => ['bg-warning text-dark', 'เสนอแล้ว (รอตรวจ)'],
                        'audited'   => ['bg-info text-dark', 'ตรวจแล้ว (รออนุมัติ)'],
                        'approved'  => ['bg-primary', 'อนุมัติแล้ว (รอจ่าย)'],
                        'disbursed' => ['bg-success', 'จ่ายเงินแล้ว'],
                        'posted'    => ['bg-success-subtle text-success border border-success-subtle', 'ลงบัญชีแล้ว'],
                        'rejected'  => ['bg-danger', 'ปฏิเสธ/ส่งกลับ'],
                        'cancelled' => ['bg-dark', 'ยกเลิก']
                    ];
                    foreach ($payments as $p): 
                        [$badgeClass, $badgeText] = $statusBadges[$p['workflow_status']] ?? ['bg-secondary', $p['workflow_status']];
                    ?>
                        <tr>
                            <td class="fw-bold">
                                <a href="<?= $baseUrl ?>/disbursements/show/<?= $p['id'] ?>" class="text-decoration-none">
                                    <code><?= e($p['voucher_no']) ?></code>
                                </a>
                            </td>
                            <td><?= thai_date($p['payment_date'], false, true) ?></td>
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width: 200px;"><?= e($p['project_name']) ?></div>
                                <small class="text-muted"><?= e($p['project_code']) ?> (<?= e($p['budget_category']) ?>)</small>
                            </td>
                            <td><?= e($p['payee_name']) ?></td>
                            <td><?= e($p['requester_name']) ?></td>
                            <td class="text-end fw-bold text-navy"><?= money_format_th($p['net_amount']) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span></td>
                            <td class="text-end">
                                <a href="<?= $baseUrl ?>/disbursements/show/<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="ดูรายละเอียดและดำเนินการ">
                                    <i class="bi bi-arrow-right-circle me-1"></i> ตรวจสอบ
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
