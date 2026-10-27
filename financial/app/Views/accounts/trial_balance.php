<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$isBalanced = $trialBalance['is_balanced'];
$grandDebit = $trialBalance['grand_debit'];
$grandCredit = $trialBalance['grand_credit'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">งบทดลอง (Trial Balance)</h4>
        <div class="text-muted small">สรุปยอดดุลเดบิต-เครดิต ประจำปีงบประมาณ พ.ศ. <?= e($activeFiscalYear['year_be']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> พิมพ์งบทดลอง
        </button>
    </div>
</div>

<!-- Balance Status & Filter -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card <?= $isBalanced ? 'stat-success' : 'stat-danger' ?>">
            <div class="stat-label">สถานะความสมดุลตามหลักบัญชีคู่</div>
            <div class="stat-value fs-5 <?= $isBalanced ? 'text-success' : 'text-danger' ?>">
                <i class="bi <?= $isBalanced ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-1"></i>
                <?= $isBalanced ? 'สมดุล (เดบิต = เครดิต)' : 'ไม่สมดุล (ยอดต่างกัน)' ?>
            </div>
            <div class="small text-muted mt-1">
                <?= $isBalanced ? 'ผลรวมสองข้างเท่ากันทุกประการ' : sprintf('ผลต่าง: %.2f บาท', abs($grandDebit - $grandCredit)) ?>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card card-gov h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <form method="GET" action="<?= $baseUrl ?>/accounts/trial-balance" class="row g-2 w-100 align-items-center">
                    <div class="col-md-8">
                        <label class="form-label small text-muted mb-1">กรองตามงวดบัญชี:</label>
                        <select name="period_id" class="form-select form-select-sm">
                            <option value="">-- รวมทั้งปีงบประมาณ --</option>
                            <?php foreach ($periods as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $periodId == $p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['period_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 pt-3">
                        <button type="submit" class="btn btn-sm btn-primary-gov w-100">
                            <i class="bi bi-filter me-1"></i> แสดงผล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Trial Balance Table -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-calculator me-2"></i> รายการยอดดุลบัญชี</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 140px;">รหัสบัญชี</th>
                    <th>ชื่อบัญชี</th>
                    <th>หมวดบัญชี</th>
                    <th class="text-end" style="width: 180px;">ยอดดุลเดบิต (บาท)</th>
                    <th class="text-end" style="width: 180px;">ยอดดุลเครดิต (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($trialBalance['accounts'])): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">ไม่พบข้อมูลบัญชี</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($trialBalance['accounts'] as $acc): ?>
                        <tr>
                            <td><code><?= e($acc['account_code']) ?></code></td>
                            <td>
                                <a href="<?= $baseUrl ?>/accounts/ledger/<?= $acc['id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= e($acc['account_name']) ?>
                                </a>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= e($acc['account_category']) ?></span></td>
                            <td class="text-end fw-semibold <?= $acc['balance_debit'] > 0 ? 'text-primary' : 'text-muted' ?>">
                                <?= $acc['balance_debit'] > 0 ? money_format_th($acc['balance_debit']) : '-' ?>
                            </td>
                            <td class="text-end fw-semibold <?= $acc['balance_credit'] > 0 ? 'text-success' : 'text-muted' ?>">
                                <?= $acc['balance_credit'] > 0 ? money_format_th($acc['balance_credit']) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="3" class="text-end fs-6">รวมทั้งสิ้น (Grand Total):</th>
                    <th class="text-end fw-bold text-primary fs-6"><?= money_format_th($grandDebit) ?></th>
                    <th class="text-end fw-bold text-success fs-6"><?= money_format_th($grandCredit) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
