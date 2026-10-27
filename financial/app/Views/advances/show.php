<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$schoolName = \App\Services\SettingService::get('school_name', 'โรงเรียนละลมวิทยา');
$principalName = \App\Services\SettingService::get('principal_name', 'นายสุรศักดิ์ สุขใจ');
$financeHeadName = \App\Services\SettingService::get('finance_head_name', 'นางกนกพร การเงินดี');
$currentUserId = $currentUser['id'] ?? 0;
$status = $advance['status'];
?>
<style>
@media print {
    #sidebar, .top-navbar, .gov-footer, .no-print {
        display: none !important;
    }
    .page-body { padding: 0 !important; }
    .contract-box {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        padding: 1.5rem !important;
    }
}
.contract-box {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 2.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">สัญญายืมเงินราชการ (แบบ 8500)</h4>
        <div class="text-muted small">สัญญาเลขที่ <?= e($advance['contract_no']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/advances" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> พิมพ์สัญญา (แบบ 8500)
        </button>

        <?php if ($status === 'draft'): ?>
            <?php if ($currentUserId !== (int)$advance['borrower_id']): ?>
                <form method="POST" action="<?= $baseUrl ?>/advances/approve" class="d-inline">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="advance_id" value="<?= $advance['id'] ?>">
                    <button type="submit" class="btn btn-primary-gov btn-sm" data-confirm="ยืนยันการอนุมัติสัญญายืมเงินนี้?">
                        <i class="bi bi-check2-circle me-1"></i> อนุมัติสัญญายืมเงิน
                    </button>
                </form>
            <?php else: ?>
                <button class="btn btn-secondary btn-sm" disabled title="ผู้ขอยืมไม่สามารถอนุมัติตนเองได้">
                    <i class="bi bi-lock me-1"></i> ผู้ขอยืมไม่สามารถอนุมัติตนเองได้
                </button>
            <?php endif; ?>
        <?php elseif ($status === 'approved'): ?>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#disburseAdvanceModal">
                <i class="bi bi-cash-stack me-1"></i> จ่ายเงินยืม
            </button>
        <?php elseif ($status === 'disbursed' || $status === 'overdue'): ?>
            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#clearingModal">
                <i class="bi bi-journal-arrow-down me-1"></i> ส่งหลักฐานล้างเงินยืม
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Contract Print View -->
<div class="contract-box mb-4">
    <div class="text-center mb-4 pb-2 border-bottom">
        <h4 class="fw-bold mb-1" style="color: #0B2545;"><?= e($schoolName) ?></h4>
        <h5 class="fw-bold mb-0">สัญญายืมเงิน (แบบ 8500)</h5>
    </div>

    <div class="row g-2 mb-4">
        <div class="col-sm-6">
            <div><span class="text-muted">ข้าพเจ้า:</span> <strong class="fs-6"><?= e($advance['borrower_name']) ?></strong></div>
            <div class="small text-muted mt-1">โครงการ: <?= e($advance['project_code']) ?> <?= e($advance['project_name']) ?></div>
            <div class="small text-muted">หมวดงบประมาณ: <?= e($advance['budget_category']) ?> | แหล่งเงิน: <?= e($advance['source_name']) ?></div>
        </div>
        <div class="col-sm-6 text-sm-end">
            <div><span class="text-muted">สัญญาเลขที่:</span> <strong class="fs-6 text-navy"><code><?= e($advance['contract_no']) ?></code></strong></div>
            <div><span class="text-muted">วันที่ทำสัญญา:</span> <strong><?= thai_date($advance['contract_date']) ?></strong></div>
            <div><span class="text-muted">วันครบกำหนดส่งใช้:</span> <strong class="text-danger"><?= thai_date($advance['due_date']) ?></strong></div>
        </div>
    </div>

    <div class="p-3 bg-light border rounded mb-4">
        <div class="fw-semibold mb-1">วัตถุประสงค์ในการยืมเงิน:</div>
        <p class="mb-0 text-dark"><?= nl2br(e($advance['objective'])) ?></p>
    </div>

    <div class="d-flex justify-content-between align-items-center p-3 border rounded mb-4 bg-light">
        <div>
            <div class="text-muted small">จำนวนเงินที่ขอยืม:</div>
            <div class="fw-bold fs-5 text-navy"><?= money_format_th($advance['amount']) ?> บาท</div>
        </div>
        <div class="text-end">
            <div class="text-muted small">จำนวนเงินตัวอักษร:</div>
            <div class="fw-bold">( <?= baht_text($advance['amount']) ?> )</div>
        </div>
    </div>

    <div class="small text-muted mb-5">
        ข้าพเจ้าสัญญาว่าจะปฏิบัติตามระเบียบของทางราชการทุกประการ และจะนำใบสำคัญคู่จ่ายที่ถูกต้อง พร้อมเงินเหลือจ่าย (ถ้ามี) ส่งใช้ภายใน 30 วัน นับแต่วันที่ได้รับเงินยืมนี้ หากข้าพเจ้ามิได้ส่งใช้ภายในกำหนด ข้าพเจ้ายินยอมให้ผู้บังคับบัญชาหักเงินเดือน ค่าจ้าง หรือเงินอื่นใดที่ข้าพเจ้าจะพึงได้รับจากทางราชการเพื่อชดใช้เงินยืมนี้จนครบถ้วน
    </div>

    <div class="row text-center mt-5">
        <div class="col-6">
            <div class="mb-4">..................................................................</div>
            <div class="fw-semibold">( <?= e($advance['borrower_name']) ?> )</div>
            <div class="text-muted small">ผู้ยืมเงิน</div>
        </div>
        <div class="col-6">
            <div class="mb-4">..................................................................</div>
            <div class="fw-semibold">( <?= e($principalName) ?> )</div>
            <div class="text-muted small">ผู้อนุมัติการยืมเงิน</div>
        </div>
    </div>
</div>

<!-- Clearings History -->
<?php if (!empty($clearings)): ?>
    <div class="card card-gov no-print">
        <div class="card-header">
            <span><i class="bi bi-receipt-cutoff me-2"></i> ประวัติการส่งหลักฐานล้างเงินยืม</span>
        </div>
        <div class="table-responsive">
            <table class="table table-gov table-hover mb-0">
                <thead>
                    <tr>
                        <th>เลขที่ใบรับล้าง</th>
                        <th>วันที่ส่งหลักฐาน</th>
                        <th class="text-end">ค่าใช้จ่ายจริง (บาท)</th>
                        <th class="text-end">คืนเงินเหลือ (บาท)</th>
                        <th class="text-end">เบิกเงินเพิ่ม (บาท)</th>
                        <th>สถานะ</th>
                        <th class="text-end">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clearings as $clr): ?>
                        <tr>
                            <td class="fw-bold"><code><?= e($clr['clearing_no']) ?></code></td>
                            <td><?= thai_date($clr['clearing_date'], false, true) ?></td>
                            <td class="text-end fw-bold"><?= money_format_th($clr['actual_expense_amount']) ?></td>
                            <td class="text-end text-success"><?= money_format_th($clr['refund_amount']) ?></td>
                            <td class="text-end text-danger"><?= money_format_th($clr['reimburse_amount']) ?></td>
                            <td>
                                <?php if ($clr['status'] === 'submitted'): ?>
                                    <span class="badge bg-warning text-dark">รอยืนยันการล้าง</span>
                                <?php else: ?>
                                    <span class="badge bg-success">อนุมัติล้างเรียบร้อย</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($clr['status'] === 'submitted'): ?>
                                    <form method="POST" action="<?= $baseUrl ?>/advances/approve-clearing" class="d-inline">
                                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="clearing_id" value="<?= $clr['id'] ?>">
                                        <input type="hidden" name="advance_id" value="<?= $advance['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" data-confirm="ยืนยันการอนุมัติล้างเงินยืมนี้? ระบบจะทำการตัดยอดงบประมาณจริงและลงบัญชีคู่">
                                            <i class="bi bi-check2"></i> อนุมัติล้างเงินยืม
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Disburse Advance -->
<div class="modal fade" id="disburseAdvanceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/advances/disburse">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="advance_id" value="<?= $advance['id'] ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i> จ่ายเงินยืมราชการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        จำนวนเงินที่ต้องจ่าย: <strong><?= money_format_th($advance['amount']) ?> บาท</strong> ให้แก่ <strong><?= e($advance['borrower_name']) ?></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ตัดจ่ายจากบัญชีธนาคาร <span class="text-danger">*</span></label>
                        <select name="bank_account_id" class="form-select" required>
                            <?php foreach ($bankAccounts as $ba): ?>
                                <option value="<?= $ba['id'] ?>">
                                    <?= e($ba['bank_name']) ?> (<?= e($ba['account_number']) ?>) - คงเหลือ: <?= money_format_th($ba['current_balance']) ?> บาท
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success">ยืนยันจ่ายเงินยืมและลงบัญชี</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Submit Clearing -->
<div class="modal fade" id="clearingModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/advances/clearing">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="advance_id" value="<?= $advance['id'] ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-journal-arrow-down me-2"></i> ส่งหลักฐานล้างเงินยืม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small">
                        ยอดเงินที่ยืมไป: <strong><?= money_format_th($advance['amount']) ?> บาท</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">วันที่ส่งหลักฐาน <span class="text-danger">*</span></label>
                        <input type="date" name="clearing_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ยอดค่าใช้จ่ายจริงตามใบเสร็จ/ใบสำคัญ (บาท) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="actual_expense_amount" id="actualExpenseInp" class="form-control" required placeholder="0.00">
                    </div>
                    <div id="clearingDiffSummary" class="alert alert-secondary small mb-0" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning">ส่งหลักฐานล้างเงินยืม</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const borrowed = <?= (float)$advance['amount'] ?>;
    const expenseInp = document.getElementById('actualExpenseInp');
    const diffDiv = document.getElementById('clearingDiffSummary');

    if (expenseInp && diffDiv) {
        expenseInp.addEventListener('input', function() {
            const exp = parseFloat(expenseInp.value) || 0;
            diffDiv.style.display = 'block';
            if (exp < borrowed) {
                const refund = borrowed - exp;
                diffDiv.className = 'alert alert-success small mb-0';
                diffDiv.innerHTML = `<strong>มีเงินเหลือจ่าย:</strong> ต้องนำส่งคืนโรงเรียนเป็นเงินสด <strong>${refund.toLocaleString('en-US', {minimumFractionDigits: 2})} บาท</strong>`;
            } else if (exp > borrowed) {
                const reimburse = exp - borrowed;
                diffDiv.className = 'alert alert-info small mb-0';
                diffDiv.innerHTML = `<strong>ใช้จ่ายเกินวงเงินยืม:</strong> โรงเรียนต้องเบิกจ่ายเงินส่วนต่างคืนผู้ยืม <strong>${reimburse.toLocaleString('en-US', {minimumFractionDigits: 2})} บาท</strong>`;
            } else {
                diffDiv.className = 'alert alert-primary small mb-0';
                diffDiv.innerHTML = `<strong>ยอดพอดี:</strong> ค่าใช้จ่ายเท่ากับวงเงินที่ยืมไปพอดี (ไม่มีเงินคืนหรือเบิกเพิ่ม)`;
            }
        });
    }
});
</script>
