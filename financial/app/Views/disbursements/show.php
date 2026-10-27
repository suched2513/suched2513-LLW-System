<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$schoolName = \App\Services\SettingService::get('school_name', 'โรงเรียนละลมวิทยา');
$principalName = \App\Services\SettingService::get('principal_name', 'นายสุรศักดิ์ สุขใจ');
$financeHeadName = \App\Services\SettingService::get('finance_head_name', 'นางกนกพร การเงินดี');
$currentUserId = $currentUser['id'] ?? 0;
$status = $payment['workflow_status'];
?>
<style>
@media print {
    #sidebar, .top-navbar, .gov-footer, .no-print {
        display: none !important;
    }
    .page-body { padding: 0 !important; }
    .voucher-box {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        padding: 1.5rem !important;
    }
}
.voucher-box {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 2.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ใบสำคัญจ่ายเงิน (Payment Voucher)</h4>
        <div class="text-muted small">เลขที่ <?= e($payment['voucher_no']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/disbursements" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> พิมพ์ใบสำคัญจ่าย
        </button>

        <!-- Workflow Action Buttons -->
        <?php if ($status === 'draft'): ?>
            <form method="POST" action="<?= $baseUrl ?>/disbursements/submit" class="d-inline">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                <button type="submit" class="btn btn-warning btn-sm" data-confirm="ยืนยันการเสนอคำขอเบิกจ่ายนี้ใช่หรือไม่? ระบบจะทำการตัดยอดผูกพันงบประมาณ">
                    <i class="bi bi-send me-1"></i> เสนอคำขอเบิกจ่าย
                </button>
            </form>
        <?php elseif ($status === 'proposed'): ?>
            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="bi bi-x-circle me-1"></i> ปฏิเสธ/ส่งกลับ
            </button>
            <form method="POST" action="<?= $baseUrl ?>/disbursements/audit" class="d-inline">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                <button type="submit" class="btn btn-info btn-sm text-dark" data-confirm="ยืนยันการตรวจสอบเอกสารและใบสำคัญถูกต้องครบถ้วน">
                    <i class="bi bi-check2-circle me-1"></i> ผ่านการตรวจสอบเอกสาร
                </button>
            </form>
        <?php elseif ($status === 'audited'): ?>
            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="bi bi-x-circle me-1"></i> ปฏิเสธ
            </button>
            <?php if ($currentUserId !== (int)$payment['requester_id']): ?>
                <form method="POST" action="<?= $baseUrl ?>/disbursements/approve" class="d-inline">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                    <button type="submit" class="btn btn-primary-gov btn-sm" data-confirm="ยืนยันการอนุมัติเบิกจ่ายเงินตามใบสำคัญจ่ายนี้">
                        <i class="bi bi-award me-1"></i> อนุมัติการเบิกจ่าย
                    </button>
                </form>
            <?php else: ?>
                <button class="btn btn-secondary btn-sm" disabled title="ผู้ขอเบิกไม่สามารถอนุมัติตนเองได้">
                    <i class="bi bi-lock me-1"></i> ผู้ขอเบิกไม่สามารถอนุมัติตนเองได้
                </button>
            <?php endif; ?>
        <?php elseif ($status === 'approved'): ?>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#disburseModal">
                <i class="bi bi-cash me-1"></i> จ่ายเงินและลงบัญชี
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Workflow Status Tracker -->
<div class="card card-gov mb-4 no-print">
    <div class="card-body p-3">
        <div class="row text-center g-2 small">
            <div class="col">
                <div class="fw-bold <?= in_array($status, ['draft','proposed','audited','approved','disbursed','posted']) ? 'text-primary' : 'text-muted' ?>">
                    <i class="bi bi-pencil-square"></i> ร่างคำขอ
                </div>
                <small class="text-muted"><?= e($payment['requester_name']) ?></small>
            </div>
            <div class="col">
                <div class="fw-bold <?= in_array($status, ['proposed','audited','approved','disbursed','posted']) ? 'text-primary' : 'text-muted' ?>">
                    <i class="bi bi-send"></i> เสนอขอเบิก
                </div>
                <small class="text-muted"><?= in_array($status, ['proposed','audited','approved','disbursed','posted']) ? 'ผูกพันงบแล้ว' : '-' ?></small>
            </div>
            <div class="col">
                <div class="fw-bold <?= in_array($status, ['audited','approved','disbursed','posted']) ? 'text-primary' : 'text-muted' ?>">
                    <i class="bi bi-search"></i> ตรวจสอบเอกสาร
                </div>
                <small class="text-muted"><?= e($payment['auditor_name'] ?? '-') ?></small>
            </div>
            <div class="col">
                <div class="fw-bold <?= in_array($status, ['approved','disbursed','posted']) ? 'text-primary' : 'text-muted' ?>">
                    <i class="bi bi-award"></i> อนุมัติจ่าย
                </div>
                <small class="text-muted"><?= e($payment['approver_name'] ?? '-') ?></small>
            </div>
            <div class="col">
                <div class="fw-bold <?= in_array($status, ['disbursed','posted']) ? 'text-success' : 'text-muted' ?>">
                    <i class="bi bi-cash-stack"></i> จ่ายเงินเรียบร้อย
                </div>
                <small class="text-muted"><?= e($payment['disburser_name'] ?? '-') ?></small>
            </div>
            <div class="col">
                <div class="fw-bold <?= ($status === 'posted') ? 'text-success' : 'text-muted' ?>">
                    <i class="bi bi-book"></i> ลงบัญชีคู่แล้ว
                </div>
                <small class="text-muted"><?= $payment['posted_at'] ? thai_date($payment['posted_at'], true, true) : '-' ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Official Payment Voucher Printable Box -->
<div class="voucher-box">
    <!-- Header -->
    <div class="text-center mb-4 pb-2 border-bottom">
        <h4 class="fw-bold mb-1" style="color: #0B2545;"><?= e($schoolName) ?></h4>
        <h5 class="fw-bold mb-0">ใบสำคัญจ่าย (Payment Voucher)</h5>
    </div>

    <!-- Metadata Row -->
    <div class="row g-2 mb-4">
        <div class="col-sm-7">
            <div><span class="text-muted">จ่ายให้:</span> <strong class="fs-6"><?= e($payment['payee_name']) ?></strong></div>
            <?php if ($payment['payee_tax_id']): ?>
                <div class="small text-muted">เลขประจำตัวผู้เสียภาษี: <?= e($payment['payee_tax_id']) ?></div>
            <?php endif; ?>
            <div class="mt-1"><span class="text-muted">โครงการ:</span> <strong><?= e($payment['project_code']) ?> <?= e($payment['project_name']) ?></strong></div>
            <div class="small text-muted">หมวดงบประมาณ: <?= e($payment['budget_category']) ?> | แหล่งเงิน: <?= e($payment['source_name']) ?></div>
        </div>
        <div class="col-sm-5 text-sm-end">
            <div><span class="text-muted">เลขที่ใบสำคัญ:</span> <strong class="fs-6 text-navy"><code><?= e($payment['voucher_no']) ?></code></strong></div>
            <div><span class="text-muted">วันที่:</span> <strong><?= thai_date($payment['payment_date']) ?></strong></div>
            <div><span class="text-muted">ผู้ขอเบิก:</span> <strong><?= e($payment['requester_name']) ?></strong></div>
            <?php if ($payment['bank_name']): ?>
                <div class="small text-muted">จ่ายจาก: <?= e($payment['bank_name']) ?> (<?= e($payment['account_number']) ?>)</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items Table -->
    <table class="table table-bordered mb-3">
        <thead class="table-light">
            <tr>
                <th class="text-center" style="width: 50px;">ลำดับ</th>
                <th>รายการที่ขอเบิกจ่าย</th>
                <th class="text-center" style="width: 90px;">จำนวน</th>
                <th class="text-end" style="width: 140px;">ราคา/หน่วย</th>
                <th class="text-end" style="width: 150px;">จำนวนเงิน (บาท)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $idx => $it): ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td><?= e($it['item_description']) ?></td>
                    <td class="text-center"><?= (float)$it['quantity'] ?></td>
                    <td class="text-end"><?= money_format_th($it['unit_price']) ?></td>
                    <td class="text-end"><?= money_format_th($it['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-end fw-semibold">รวมเป็นเงินทั้งสิ้น:</td>
                <td class="text-end fw-semibold"><?= money_format_th($payment['gross_amount']) ?></td>
            </tr>
            <?php if ((float)$payment['withholding_tax_amount'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-end text-danger fw-semibold">หัก ภาษี ณ ที่จ่าย <?= $payment['withholding_tax_percent'] ?>%:</td>
                    <td class="text-end text-danger fw-semibold">-<?= money_format_th($payment['withholding_tax_amount']) ?></td>
                </tr>
            <?php endif; ?>
            <tr class="table-light">
                <td colspan="3" class="fw-bold">
                    จำนวนเงินสุทธิตัวอักษร: ( <?= baht_text($payment['net_amount']) ?> )
                </td>
                <td class="text-end fw-bold text-navy fs-6">ยอดจ่ายสุทธิ:</td>
                <td class="text-end fw-bold text-navy fs-6"><?= money_format_th($payment['net_amount']) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Signatures -->
    <div class="row text-center mt-5 pt-3">
        <div class="col-4">
            <div class="mb-4">...................................................</div>
            <div class="fw-semibold">( <?= e($payment['requester_name']) ?> )</div>
            <div class="text-muted small">ผู้ขอเบิกจ่าย</div>
        </div>
        <div class="col-4">
            <div class="mb-4">...................................................</div>
            <div class="fw-semibold">( <?= e($payment['auditor_name'] ?? $financeHeadName) ?> )</div>
            <div class="text-muted small">ผู้ตรวจสอบ / เจ้าหน้าที่การเงิน</div>
        </div>
        <div class="col-4">
            <div class="mb-4">...................................................</div>
            <div class="fw-semibold">( <?= e($payment['approver_name'] ?? $principalName) ?> )</div>
            <div class="text-muted small">ผู้อนุมัติการเบิกจ่าย</div>
        </div>
    </div>
</div>

<!-- Modal: Disburse Payment -->
<div class="modal fade" id="disburseModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/disbursements/disburse">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cash me-2"></i> บันทึกการจ่ายเงิน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        ยอดที่ต้องจ่ายสุทธิ: <strong><?= money_format_th($payment['net_amount']) ?> บาท</strong> ให้แก่ <strong><?= e($payment['payee_name']) ?></strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">วิธีจ่ายเงิน <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select" required>
                            <option value="transfer">โอนเงินเข้าบัญชีธนาคาร</option>
                            <option value="cheque">จ่ายด้วยเช็คธนาคาร</option>
                            <option value="cash">จ่ายเงินสด</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ตัดจ่ายจากบัญชีธนาคาร</label>
                        <select name="bank_account_id" class="form-select">
                            <option value="">-- ไม่ผ่านบัญชีธนาคาร (เงินสด) --</option>
                            <?php foreach ($bankAccounts as $ba): ?>
                                <option value="<?= $ba['id'] ?>" <?= $payment['bank_account_id'] == $ba['id'] ? 'selected' : '' ?>>
                                    <?= e($ba['bank_name']) ?> (<?= e($ba['account_number']) ?>) - คงเหลือ: <?= money_format_th($ba['current_balance']) ?> บาท
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลขที่เช็ค (กรณีจ่ายด้วยเช็ค)</label>
                        <input type="text" name="cheque_no" class="form-control" placeholder="เช่น 10234567">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success">ยืนยันการจ่ายเงินและลงบัญชี</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reject Payment -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/disbursements/reject">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-x-circle me-2"></i> ปฏิเสธ / ส่งกลับแก้ไข</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ระบุเหตุผลในการส่งกลับแก้ไข / ปฏิเสธ <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="เช่น เอกสารใบเสร็จไม่ครบถ้วน, วงเงินไม่สอดคล้องกับแผนงาน"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" class="btn btn-danger">ยืนยันการปฏิเสธ</button>
                </div>
            </div>
        </form>
    </div>
</div>
