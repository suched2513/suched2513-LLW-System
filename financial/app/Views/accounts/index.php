<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">สมุดรายวันทั่วไป (General Journal)</h4>
        <div class="text-muted small">บันทึกรายการบัญชีคู่ เดบิต-เครดิต ประจำปีงบประมาณ พ.ศ. <?= e($activeFiscalYear['year_be']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/accounts/chart" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-diagram-3 me-1"></i> ผังบัญชี (5 หมวด)
        </a>
        <a href="<?= $baseUrl ?>/accounts/trial-balance" class="btn btn-gold btn-sm">
            <i class="bi bi-calculator me-1"></i> งบทดลอง (Trial Balance)
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card card-gov mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= $baseUrl ?>/accounts" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="entry_type" class="form-select form-select-sm">
                    <option value="">-- ทุกประเภทสมุดรายวัน --</option>
                    <option value="receipt" <?= ($filter['entry_type'] ?? '') === 'receipt' ? 'selected' : '' ?>>สมุดเงินสดรับ (Receipts)</option>
                    <option value="payment" <?= ($filter['entry_type'] ?? '') === 'payment' ? 'selected' : '' ?>>สมุดเงินสดจ่าย (Payments)</option>
                    <option value="advance" <?= ($filter['entry_type'] ?? '') === 'advance' ? 'selected' : '' ?>>สมุดจ่ายเงินยืม (Advances)</option>
                    <option value="clearing" <?= ($filter['entry_type'] ?? '') === 'clearing' ? 'selected' : '' ?>>สมุดล้างเงินยืม (Clearings)</option>
                    <option value="general" <?= ($filter['entry_type'] ?? '') === 'general' ? 'selected' : '' ?>>สมุดรายวันทั่วไป (General)</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($filter['date_from'] ?? '') ?>" placeholder="ตั้งแต่วันที่">
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($filter['date_to'] ?? '') ?>" placeholder="ถึงวันที่">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary-gov flex-grow-1"><i class="bi bi-search"></i> กรองรายการ</button>
                <a href="<?= $baseUrl ?>/accounts" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Journal Entries Table -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-journal-text me-2"></i> รายการบันทึกบัญชี (<?= count($entries) ?> รายการ)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>เลขที่ใบสำคัญ (JV)</th>
                    <th>วันที่ลงบัญชี</th>
                    <th>งวดบัญชี</th>
                    <th>ประเภท</th>
                    <th>คำอธิบายรายการ</th>
                    <th class="text-end">เดบิต (บาท)</th>
                    <th class="text-end">เครดิต (บาท)</th>
                    <th class="text-end">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">ไม่พบรายการบันทึกบัญชีในระบบ</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                        <tr class="<?= $e['is_reversed'] ? 'table-danger text-muted text-decoration-line-through' : '' ?>">
                            <td class="fw-bold">
                                <a href="<?= $baseUrl ?>/accounts/entry/<?= $e['id'] ?>" class="text-decoration-none">
                                    <code><?= e($e['entry_no']) ?></code>
                                </a>
                            </td>
                            <td><?= thai_date($e['entry_date'], false, true) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($e['period_name']) ?></span></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?= e($e['entry_type']) ?>
                                </span>
                            </td>
                            <td>
                                <div><?= e($e['description']) ?></div>
                                <?php if ($e['is_reversed']): ?>
                                    <small class="badge bg-danger">กลับรายการแล้ว</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold text-navy"><?= money_format_th($e['total_debit']) ?></td>
                            <td class="text-end fw-bold text-navy"><?= money_format_th($e['total_credit']) ?></td>
                            <td class="text-end">
                                <a href="<?= $baseUrl ?>/accounts/entry/<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary" title="ดูรายการคู่บัญชี">
                                    <i class="bi bi-eye"></i> ดู
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
