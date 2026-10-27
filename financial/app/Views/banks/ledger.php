<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ความเคลื่อนไหวบัญชีเงินฝากธนาคาร</h4>
        <div class="text-muted small"><?= e($account['bank_name']) ?> | เลขที่บัญชี: <code><?= e($account['account_no']) ?></code></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/banks" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับไปบัญชีธนาคาร
        </a>
        <a href="<?= $baseUrl ?>/banks/reconcile/<?= $account['id'] ?>" class="btn btn-gold btn-sm">
            <i class="bi bi-check2-all me-1"></i> กระทบยอดเงินฝาก
        </a>
    </div>
</div>

<div class="card card-gov mb-4">
    <div class="card-header">
        <span><i class="bi bi-list-ul me-2"></i> รายการเงินเข้า-ออกในบัญชี</span>
        <span class="badge bg-light text-primary border">ยอดคงเหลือปัจจุบัน: <?= money_format_th($account['current_balance']) ?> บาท</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>วันที่</th>
                    <th>ประเภท</th>
                    <th>เลขที่อ้างอิง</th>
                    <th>ผู้ชำระ / ผู้รับเงิน</th>
                    <th class="text-end" style="width: 180px;">ฝากเข้า (บาท)</th>
                    <th class="text-end" style="width: 180px;">ถอน/จ่ายออก (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">ยังไม่มีรายการเงินเข้า-ออกในบัญชีนี้</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?= thai_date($t['trans_date'], false, true) ?></td>
                            <td>
                                <?php if ($t['trans_type'] === 'receipt'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">รับเงินตามใบเสร็จ</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">จ่ายตามใบสำคัญ</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold"><code><?= e($t['ref_no']) ?></code></td>
                            <td><?= e($t['party_name']) ?></td>
                            <td class="text-end fw-bold text-success">
                                <?= $t['direction'] === 'deposit' ? money_format_th($t['amount']) : '-' ?>
                            </td>
                            <td class="text-end fw-bold text-danger">
                                <?= $t['direction'] === 'withdrawal' ? money_format_th($t['amount']) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="4" class="text-end">ยอดคงเหลือปัจจุบันในสมุดบัญชี:</th>
                    <th colspan="2" class="text-end fw-bold fs-6 text-navy"><?= money_format_th($account['current_balance']) ?> บาท</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
