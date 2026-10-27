<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$schoolName = \App\Services\SettingService::get('school_name', 'โรงเรียนละลมวิทยา');
$principalName = \App\Services\SettingService::get('principal_name', 'นายสุรศักดิ์ สุขใจ');
$financeHeadName = \App\Services\SettingService::get('finance_head_name', 'นางกนกพร การเงินดี');
$isMatched = $reconciliation['is_matched'];
?>
<style>
@media print {
    #sidebar, .top-navbar, .gov-footer, .no-print { display: none !important; }
    .page-body { padding: 0 !important; }
    .reconcile-box {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        padding: 1.5rem !important;
    }
}
.reconcile-box {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 2.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">งบพิสูจน์ยอดเงินฝากธนาคาร (Bank Reconciliation Statement)</h4>
        <div class="text-muted small"><?= e($account['bank_name']) ?> | เลขที่บัญชี: <code><?= e($account['account_no']) ?></code></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/banks" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
        <button type="button" class="btn btn-primary-gov btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> พิมพ์งบพิสูจน์ยอด
        </button>
    </div>
</div>

<!-- Parameters Bar -->
<div class="card card-gov mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" action="<?= $baseUrl ?>/banks/reconcile/<?= $account['id'] ?>" class="row g-2 align-items-center">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">พิสูจน์ยอด ณ วันที่:</label>
                <input type="date" name="as_of_date" class="form-control form-control-sm" value="<?= e($asOfDate) ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">ยอดเงินตามสเตทเมนต์ธนาคาร (Statement Balance):</label>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.01" name="statement_balance" class="form-control" value="<?= e($statementBal) ?>">
                    <span class="input-group-text">บาท</span>
                </div>
            </div>
            <div class="col-md-3 pt-3">
                <button type="submit" class="btn btn-sm btn-primary-gov w-100">
                    <i class="bi bi-calculator me-1"></i> คำนวณกระทบยอด
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reconcile Report Box -->
<div class="reconcile-box">
    <!-- Header -->
    <div class="text-center mb-4 pb-2 border-bottom">
        <h4 class="fw-bold mb-1" style="color: #0B2545;"><?= e($schoolName) ?></h4>
        <h5 class="fw-bold mb-1">งบพิสูจน์ยอดเงินฝากธนาคาร (Bank Reconciliation)</h5>
        <div class="text-muted small">
            ธนาคาร: <?= e($account['bank_name']) ?> <?= e($account['branch_name'] ? "สาขา {$account['branch_name']}" : '') ?> 
            เลขที่บัญชี: <code><?= e($account['account_no']) ?></code>
        </div>
        <div class="fw-semibold mt-1">ณ วันที่ <?= thai_date($asOfDate) ?></div>
    </div>

    <!-- Reconciliation Formula Table -->
    <table class="table table-bordered mb-4">
        <tbody>
            <tr class="table-light">
                <td class="fw-bold">ยอดคงเหลือตามใบแจ้งยอดธนาคาร (Bank Statement Ending Balance):</td>
                <td class="text-end fw-bold fs-6 text-navy" style="width: 220px;"><?= money_format_th($reconciliation['statement_balance']) ?></td>
            </tr>

            <!-- Plus: Deposits in Transit -->
            <tr>
                <td colspan="2" class="fw-semibold text-primary pt-3">
                    <i class="bi bi-plus-circle me-1"></i> บวก: เงินฝากระหว่างทาง (โรงเรียนบันทึกรับแล้ว แต่ยังไม่ปรากฏในสเตทเมนต์):
                </td>
            </tr>
            <?php if (empty($reconciliation['deposits_in_transit'])): ?>
                <tr>
                    <td class="ps-4 text-muted small">- ไม่มีรายการเงินฝากระหว่างทาง -</td>
                    <td class="text-end text-muted">0.00</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reconciliation['deposits_in_transit'] as $dit): ?>
                    <tr>
                        <td class="ps-4 small">
                            ใบเสร็จเลขที่ <code><?= e($dit['receipt_no']) ?></code> วันที่ <?= thai_date($dit['receipt_date'], false, true) ?> จาก <?= e($dit['payer_name']) ?>
                        </td>
                        <td class="text-end text-success small"><?= money_format_th($dit['total_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="table-light">
                <td class="ps-4 fw-semibold text-end">รวมเงินฝากระหว่างทาง:</td>
                <td class="text-end fw-bold text-success">+<?= money_format_th($reconciliation['total_deposits_in_transit']) ?></td>
            </tr>

            <!-- Less: Outstanding Checks -->
            <tr>
                <td colspan="2" class="fw-semibold text-danger pt-3">
                    <i class="bi bi-dash-circle me-1"></i> หัก: เช็คสั่งจ่ายที่ยังไม่ได้นำไปขึ้นเงิน (โรงเรียนจ่ายแล้ว แต่ผู้รับยังไม่ขึ้นเงิน):
                </td>
            </tr>
            <?php if (empty($reconciliation['outstanding_checks'])): ?>
                <tr>
                    <td class="ps-4 text-muted small">- ไม่มีรายการเช็คค้างจ่าย -</td>
                    <td class="text-end text-muted">0.00</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reconciliation['outstanding_checks'] as $oc): ?>
                    <tr>
                        <td class="ps-4 small">
                            ใบสำคัญจ่าย <code><?= e($oc['voucher_no']) ?></code> เช็คเลขที่ <?= e($oc['cheque_no'] ?? '-') ?> จ่ายให้ <?= e($oc['payee_name']) ?>
                        </td>
                        <td class="text-end text-danger small"><?= money_format_th($oc['net_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="table-light">
                <td class="ps-4 fw-semibold text-end">รวมเช็คสั่งจ่ายที่ยังไม่ได้นำไปขึ้นเงิน:</td>
                <td class="text-end fw-bold text-danger">-<?= money_format_th($reconciliation['total_outstanding_checks']) ?></td>
            </tr>

            <!-- Adjusted Balance -->
            <tr class="table-primary">
                <td class="fw-bold fs-6">ยอดคงเหลือที่ถูกต้องตามธนาคาร (Adjusted Bank Balance):</td>
                <td class="text-end fw-bold fs-6 text-primary"><?= money_format_th($reconciliation['adjusted_bank_balance']) ?></td>
            </tr>

            <!-- Comparison to Book Balance -->
            <tr class="table-light">
                <td class="fw-bold">ยอดคงเหลือตามสมุดบัญชีเงินฝากธนาคารของโรงเรียน (Book Balance):</td>
                <td class="text-end fw-bold fs-6 text-navy"><?= money_format_th($reconciliation['book_balance']) ?></td>
            </tr>

            <!-- Difference -->
            <tr class="<?= $isMatched ? 'table-success' : 'table-danger' ?>">
                <td class="fw-bold">
                    <i class="bi <?= $isMatched ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-1"></i>
                    ผลต่าง (Difference): <?= $isMatched ? 'ยอดตรงกันสมบูรณ์ 100%' : 'พบผลต่างที่ต้องตรวจสอบ' ?>
                </td>
                <td class="text-end fw-bold fs-6 <?= $isMatched ? 'text-success' : 'text-danger' ?>">
                    <?= money_format_th($reconciliation['difference']) ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Signatures -->
    <div class="row text-center mt-5 pt-3">
        <div class="col-6">
            <div class="mb-4">..................................................................</div>
            <div class="fw-semibold">( <?= e($financeHeadName) ?> )</div>
            <div class="text-muted small">ผู้จัดทำ / เจ้าหน้าที่การเงิน</div>
        </div>
        <div class="col-6">
            <div class="mb-4">..................................................................</div>
            <div class="fw-semibold">( <?= e($principalName) ?> )</div>
            <div class="text-muted small">ผู้อำนวยการโรงเรียนละลมวิทยา</div>
        </div>
    </div>
</div>
