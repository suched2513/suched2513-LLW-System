<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$schoolName = \App\Services\SettingService::get('school_name', 'โรงเรียนละลมวิทยา');
$affiliation = \App\Services\SettingService::get('affiliation', 'สังกัดสำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร');
$principalName = \App\Services\SettingService::get('principal_name', 'นายสุรศักดิ์ สุขใจ');
$financeHeadName = \App\Services\SettingService::get('finance_head_name', 'นางกนกพร การเงินดี');
$code = $reportMeta['code'];
?>
<style>
@media print {
    #sidebar, .top-navbar, .gov-footer, .no-print { display: none !important; }
    .page-body { padding: 0 !important; }
    .report-sheet {
        border: none !important;
        box-shadow: none !important;
        padding: 1rem !important;
    }
}
.report-sheet {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 2.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    margin: 0 auto;
    max-width: 950px;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;"><?= e($reportMeta['name']) ?></h4>
        <div class="text-muted small">รหัสรายงาน: <code><?= e($reportMeta['code']) ?></code> | หมวด: <?= e($reportMeta['category']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/reports" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับไปศูนย์รายงาน
        </a>
        <button type="button" class="btn btn-primary-gov btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> พิมพ์รายงานราชการ
        </button>
    </div>
</div>

<div class="report-sheet">
    <!-- Header -->
    <div class="text-center mb-4 pb-2 border-bottom">
        <h4 class="fw-bold mb-1" style="color: #0B2545;"><?= e($schoolName) ?></h4>
        <div class="text-muted small mb-1"><?= e($affiliation) ?></div>
        <h5 class="fw-bold mt-2 mb-1"><?= e($reportMeta['name']) ?></h5>
        <div class="small fw-semibold text-muted">ประจำปีงบประมาณ พ.ศ. <?= e($activeFiscalYear['year_be']) ?> (ข้อมูล ณ วันที่ <?= thai_date(date('Y-m-d')) ?>)</div>
    </div>

    <!-- Report Body Table -->
    <?php if ($code === 'REP-01' || $code === 'REP-02'): ?>
        <table class="table table-bordered table-hover mb-4">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;" class="text-center">ลำดับ</th>
                    <th>เลขที่ใบเสร็จ</th>
                    <th>วันที่รับเงิน</th>
                    <th>ผู้ชำระเงิน</th>
                    <th>วิธีชำระ</th>
                    <th class="text-end" style="width: 180px;">จำนวนเงิน (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalRec = 0;
                $receipts = $data['receipts'] ?? [];
                if (empty($receipts)): 
                ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">ไม่พบข้อมูลรายรับในงวดนี้</td></tr>
                <?php else: ?>
                    <?php foreach ($receipts as $idx => $r): $totalRec += (float)$r['total_amount']; ?>
                        <tr>
                            <td class="text-center"><?= $idx + 1 ?></td>
                            <td><code><?= e($r['receipt_no']) ?></code></td>
                            <td><?= thai_date($r['receipt_date'], false, true) ?></td>
                            <td><?= e($r['payer_name']) ?></td>
                            <td><?= e($r['payment_method']) ?></td>
                            <td class="text-end fw-semibold"><?= money_format_th($r['total_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="5" class="text-end">ยอดรวมรายรับทั้งสิ้น (บาท):</th>
                    <th class="text-end fw-bold text-success fs-6"><?= money_format_th($totalRec) ?></th>
                </tr>
            </tfoot>
        </table>
    <?php elseif ($code === 'REP-03'): ?>
        <table class="table table-bordered table-hover mb-4">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;" class="text-center">ลำดับ</th>
                    <th>เลขที่ใบสำคัญ (PV)</th>
                    <th>วันที่จ่าย</th>
                    <th>ผู้รับเงิน</th>
                    <th>วิธีจ่าย</th>
                    <th class="text-end" style="width: 180px;">ยอดจ่ายสุทธิ (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalPay = 0;
                $payments = $data['payments'] ?? [];
                if (empty($payments)): 
                ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">ไม่พบข้อมูลรายจ่ายในงวดนี้</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $idx => $p): $totalPay += (float)$p['net_amount']; ?>
                        <tr>
                            <td class="text-center"><?= $idx + 1 ?></td>
                            <td><code><?= e($p['voucher_no']) ?></code></td>
                            <td><?= thai_date($p['payment_date'], false, true) ?></td>
                            <td><?= e($p['payee_name']) ?></td>
                            <td><?= e($p['payment_method']) ?></td>
                            <td class="text-end fw-semibold"><?= money_format_th($p['net_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="5" class="text-end">ยอดรวมรายจ่ายทั้งสิ้น (บาท):</th>
                    <th class="text-end fw-bold text-danger fs-6"><?= money_format_th($totalPay) ?></th>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <div class="alert alert-light border text-center p-4 mb-4">
            <i class="bi bi-file-earmark-check text-primary fs-1 mb-2 d-block"></i>
            <h6 class="fw-bold text-navy"><?= e($reportMeta['name']) ?> (แบบฟอร์มทางการ)</h6>
            <p class="text-muted small mb-0">ระบบเชื่อมโยงฐานข้อมูลโรงเรียนละลมวิทยาเพื่อออกเอกสารและรายงานราชการฉบับสมบูรณ์</p>
        </div>
    <?php endif; ?>

    <!-- Signatures Block -->
    <div class="row text-center mt-5 pt-3">
        <div class="col-6">
            <div class="mb-4">..................................................................</div>
            <div class="fw-semibold">( <?= e($financeHeadName) ?> )</div>
            <div class="text-muted small">เจ้าหน้าที่การเงินและบัญชี</div>
        </div>
        <div class="col-6">
            <div class="mb-4">..................................................................</div>
            <div class="fw-semibold">( <?= e($principalName) ?> )</div>
            <div class="text-muted small">ผู้อำนวยการโรงเรียนละลมวิทยา</div>
        </div>
    </div>
</div>
