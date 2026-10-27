<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">สมุดบัญชีแยกประเภท (General Ledger)</h4>
        <div class="text-muted small"><code><?= e($account['account_code']) ?></code> - <?= e($account['account_name']) ?> | ประจำปี พ.ศ. <?= e($activeFiscalYear['year_be']) ?></div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/accounts/chart" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับไปผังบัญชี
        </a>
    </div>
</div>

<div class="card card-gov mb-4">
    <div class="card-header">
        <span><i class="bi bi-journal-album me-2"></i> ความเคลื่อนไหวทางบัญชี</span>
        <span class="badge bg-light text-primary border">ยอดคงเหลือสุทธิ: <?= money_format_th($balance) ?> บาท</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>วันที่</th>
                    <th>เลขที่ใบสำคัญ (JV)</th>
                    <th>คำอธิบายรายการ</th>
                    <th class="text-end" style="width: 150px;">เดบิต (บาท)</th>
                    <th class="text-end" style="width: 150px;">เครดิต (บาท)</th>
                    <th class="text-end" style="width: 180px;">ยอดดุลคงเหลือ (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lines)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">ยังไม่มีรายการเคลื่อนไหวในบัญชีนี้</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lines as $l): ?>
                        <tr>
                            <td><?= thai_date($l['entry_date'], false, true) ?></td>
                            <td class="fw-bold">
                                <a href="<?= $baseUrl ?>/accounts/entry/<?= $l['journal_entry_id'] ?>" class="text-decoration-none">
                                    <code><?= e($l['entry_no']) ?></code>
                                </a>
                            </td>
                            <td><?= e($l['line_description'] ?? $l['entry_desc']) ?></td>
                            <td class="text-end fw-semibold <?= (float)$l['debit_amount'] > 0 ? 'text-primary' : 'text-muted' ?>">
                                <?= (float)$l['debit_amount'] > 0 ? money_format_th($l['debit_amount']) : '-' ?>
                            </td>
                            <td class="text-end fw-semibold <?= (float)$l['credit_amount'] > 0 ? 'text-success' : 'text-muted' ?>">
                                <?= (float)$l['credit_amount'] > 0 ? money_format_th($l['credit_amount']) : '-' ?>
                            </td>
                            <td class="text-end fw-bold text-navy">
                                <?= money_format_th($l['balance']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="5" class="text-end">ยอดคงเหลือสุทธิยกไป:</th>
                    <th class="text-end fw-bold text-navy fs-6"><?= money_format_th($balance) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
