<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$schoolName = \App\Services\SettingService::get('school_name', 'โรงเรียนละลมวิทยา');
$affiliation = \App\Services\SettingService::get('affiliation', 'สังกัดสำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร');
$schoolAddress = \App\Services\SettingService::get('address', 'หมู่ 2 ตำบลละลม อำเภอภูสิงห์ จังหวัดศรีสะเกษ 33140');
$schoolPhone = \App\Services\SettingService::get('phone', '045-810787');
$schoolTaxId = \App\Services\SettingService::get('tax_id', '0994000376228');
$principalName = \App\Services\SettingService::get('principal_name', 'นายสุรศักดิ์ สุขใจ');
$financeHeadName = \App\Services\SettingService::get('finance_head_name', 'นางกนกพร การเงินดี');
?>
<style>
@media print {
    #sidebar, .top-navbar, .gov-footer, .no-print {
        display: none !important;
    }
    .page-body {
        padding: 0 !important;
    }
    .receipt-box {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 1.5rem !important;
    }
}
.receipt-box {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 2.5rem;
    max-width: 850px;
    margin: 0 auto;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    position: relative;
}
.watermark-cancelled {
    position: absolute;
    top: 35%;
    left: 20%;
    font-size: 5rem;
    color: rgba(220, 53, 69, 0.25);
    transform: rotate(-25deg);
    font-weight: 900;
    pointer-events: none;
    border: 8px dashed rgba(220, 53, 69, 0.3);
    padding: 1rem 3rem;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ใบเสร็จรับเงิน (Official Receipt)</h4>
        <div class="text-muted small">เลขที่ <?= e($receipt['receipt_no']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/receipts" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
        <button type="button" class="btn btn-primary-gov btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> สั่งพิมพ์ใบเสร็จ
        </button>
    </div>
</div>

<div class="receipt-box">
    <?php if ($receipt['status'] === 'cancelled'): ?>
        <div class="watermark-cancelled">ยกเลิกแล้ว</div>
    <?php endif; ?>

    <!-- Header -->
    <div class="text-center mb-4 pb-2 border-bottom">
        <h4 class="fw-bold mb-1" style="color: #0B2545;"><?= e($schoolName) ?></h4>
        <div class="text-muted small mb-1"><?= e($affiliation) ?></div>
        <div class="text-muted small"><?= e($schoolAddress) ?> | โทร: <?= e($schoolPhone) ?> | เลขประจำตัวผู้เสียภาษี: <?= e($schoolTaxId) ?></div>
        <h5 class="fw-bold mt-3 mb-0" style="letter-spacing: 2px;">ใบเสร็จรับเงิน</h5>
    </div>

    <!-- Info Row -->
    <div class="row g-2 mb-4">
        <div class="col-sm-6">
            <div><span class="text-muted">ได้รับเงินจาก:</span> <strong class="fs-6"><?= e($receipt['payer_name']) ?></strong></div>
            <?php if ($receipt['payer_tax_id']): ?>
                <div class="small text-muted">เลขประจำตัวผู้เสียภาษี: <?= e($receipt['payer_tax_id']) ?></div>
            <?php endif; ?>
            <div class="small text-muted mt-1">แหล่งเงิน: <?= e($receipt['source_name']) ?></div>
        </div>
        <div class="col-sm-6 text-sm-end">
            <div><span class="text-muted">เลขที่ใบเสร็จ:</span> <strong class="fs-6 text-navy"><code><?= e($receipt['receipt_no']) ?></code></strong></div>
            <div><span class="text-muted">วันที่:</span> <strong><?= thai_date($receipt['receipt_date']) ?></strong></div>
            <div><span class="text-muted">ปีงบประมาณ:</span> <strong>พ.ศ. <?= e($activeFiscalYear['year_be']) ?></strong></div>
        </div>
    </div>

    <!-- Items Table -->
    <table class="table table-bordered mb-3">
        <thead class="table-light">
            <tr>
                <th class="text-center" style="width: 60px;">ลำดับ</th>
                <th>รายการรับชำระ</th>
                <th class="text-end" style="width: 180px;">จำนวนเงิน (บาท)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $idx => $it): ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td><?= e($it['item_description']) ?></td>
                    <td class="text-end"><?= money_format_th($it['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="table-light">
                <td colspan="2" class="fw-bold">
                    จำนวนเงินตัวอักษร: ( <?= baht_text($receipt['total_amount']) ?> )
                </td>
                <td class="text-end fw-bold fs-6 text-navy">
                    <?= money_format_th($receipt['total_amount']) ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Payment Method & Details -->
    <div class="mb-5 small text-muted">
        <?php 
        $methods = ['cash' => 'เงินสด', 'transfer' => 'โอนเงินเข้าบัญชีธนาคาร', 'cheque' => 'เช็คธนาคาร'];
        ?>
        <strong>วิธีรับชำระเงิน:</strong> <?= $methods[$receipt['payment_method']] ?? $receipt['payment_method'] ?>
        <?php if ($receipt['bank_name']): ?>
            (<?= e($receipt['bank_name']) ?> บัญชีเลขที่ <code><?= e($receipt['account_number']) ?></code>)
        <?php endif; ?>
        <?php if ($receipt['bank_ref_no']): ?>
            | เลขอ้างอิง: <?= e($receipt['bank_ref_no']) ?>
        <?php endif; ?>
    </div>

    <!-- Signatures -->
    <div class="row text-center mt-5">
        <div class="col-6">
            <div class="mb-4">..................................................................</div>
            <div class="fw-semibold">( <?= e($receipt['creator_name'] ?? $financeHeadName) ?> )</div>
            <div class="text-muted small">เจ้าหน้าที่การเงิน / ผู้รับเงิน</div>
        </div>
        <div class="col-6">
            <div class="mb-4">..................................................................</div>
            <div class="fw-semibold">( <?= e($principalName) ?> )</div>
            <div class="text-muted small">ผู้อำนวยการโรงเรียนละลมวิทยา</div>
        </div>
    </div>
</div>
