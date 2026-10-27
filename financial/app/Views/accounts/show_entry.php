<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ใบสำคัญลงบัญชีรายวัน: <?= e($entry['entry_no']) ?></h4>
        <div class="text-muted small">ประเภท: <?= e($entry['entry_type']) ?> | งวดบัญชี: <?= e($entry['period_name']) ?></div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/accounts" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับไปสมุดรายวัน
        </a>
    </div>
</div>

<div class="card card-gov mb-4">
    <div class="card-header">
        <span><i class="bi bi-journal-check me-2"></i> ข้อมูลใบสำคัญลงบัญชี</span>
        <span>
            <?php if ($entry['is_reversed']): ?>
                <span class="badge bg-danger">กลับรายการแล้ว</span>
            <?php else: ?>
                <span class="badge bg-success">ลงบัญชีสมบูรณ์ (Posted)</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <span class="text-muted d-block small">เลขที่ใบสำคัญ:</span>
                <strong class="fs-6"><code><?= e($entry['entry_no']) ?></code></strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block small">วันที่ลงบัญชี:</span>
                <strong><?= thai_date($entry['entry_date']) ?></strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block small">ผู้บันทึกรายการ:</span>
                <strong><?= e($entry['creator_name'] ?? 'ระบบอัตโนมัติ') ?></strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block small">เอกสารอ้างอิง:</span>
                <strong><?= e($entry['reference_type'] ?? '-') ?> #<?= e($entry['reference_id'] ?? '-') ?></strong>
            </div>
            <div class="col-12">
                <span class="text-muted d-block small">คำอธิบายรายการ:</span>
                <div class="p-2 bg-light border rounded"><?= e($entry['description']) ?></div>
            </div>
        </div>

        <!-- Lines Table -->
        <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-list-nested me-1"></i> รายการเดบิต / เครดิต</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 120px;">รหัสบัญชี</th>
                        <th>ชื่อบัญชี</th>
                        <th>คำอธิบายรายการย่อย</th>
                        <th class="text-end" style="width: 180px;">เดบิต (บาท)</th>
                        <th class="text-end" style="width: 180px;">เครดิต (บาท)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $l): ?>
                        <tr>
                            <td><code><?= e($l['account_code']) ?></code></td>
                            <td>
                                <a href="<?= $baseUrl ?>/accounts/ledger/<?= $l['account_id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= e($l['account_name']) ?>
                                </a>
                            </td>
                            <td><?= e($l['line_description'] ?? '-') ?></td>
                            <td class="text-end fw-bold <?= (float)$l['debit_amount'] > 0 ? 'text-primary' : 'text-muted' ?>">
                                <?= (float)$l['debit_amount'] > 0 ? money_format_th($l['debit_amount']) : '-' ?>
                            </td>
                            <td class="text-end fw-bold <?= (float)$l['credit_amount'] > 0 ? 'text-success' : 'text-muted' ?>">
                                <?= (float)$l['credit_amount'] > 0 ? money_format_th($l['credit_amount']) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">ยอดรวมทั้งสิ้น (บาท):</th>
                        <th class="text-end fw-bold text-primary fs-6"><?= money_format_th($entry['total_debit']) ?></th>
                        <th class="text-end fw-bold text-success fs-6"><?= money_format_th($entry['total_credit']) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
