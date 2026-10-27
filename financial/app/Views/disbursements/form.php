<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">สร้างคำขอเบิกจ่ายเงินใหม่</h4>
        <div class="text-muted small">บันทึกคำขอเบิกจ่ายเงินตามโครงการและหมวดงบประมาณ</div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/disbursements" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
    </div>
</div>

<form method="POST" action="<?= $baseUrl ?>/disbursements/store" id="disburseForm">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <div class="row">
        <!-- Main Info -->
        <div class="col-lg-8">
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-wallet2 me-2"></i> ข้อมูลโครงการและงบประมาณที่ขอเบิก</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">เลือกหมวดงบประมาณตามโครงการ <span class="text-danger">*</span></label>
                            <select name="budget_id" class="form-select" required>
                                <option value="">-- เลือกโครงการและหมวดงบประมาณ --</option>
                                <?php foreach ($availableBudgets as $ab): ?>
                                    <option value="<?= $ab['id'] ?>">
                                        <?= e($ab['project_code']) ?>: <?= e($ab['project_name']) ?> [<?= e($ab['budget_category']) ?> | <?= e($ab['source_name']) ?>] (คงเหลือ: <?= money_format_th($ab['remaining_amount']) ?> บาท)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">วันที่ขอเบิกจ่าย <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">อัตราภาษีหัก ณ ที่จ่าย</label>
                            <select name="withholding_tax_percent" id="taxPercent" class="form-select">
                                <option value="0.00">ไม่หักภาษี (0%)</option>
                                <option value="1.00">หักภาษีค่าจ้าง / บริการ (1%)</option>
                                <option value="3.00">หักภาษีค่าจ้างทำของนิติบุคคล (3%)</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">ชื่อผู้รับเงิน / ร้านค้า / เจ้าหนี้ <span class="text-danger">*</span></label>
                            <input type="text" name="payee_name" class="form-control" required placeholder="เช่น ห้างหุ้นส่วนจำกัด ศรีสะเกษการค้า, นายสมเกียรติ มุ่งมั่น">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">เลขประจำตัวผู้เสียภาษี (ถ้ามี)</label>
                            <input type="text" name="payee_tax_id" class="form-control" placeholder="13 หลัก">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">วิธีจ่ายเงิน</label>
                            <select name="payment_method" class="form-select">
                                <option value="transfer">โอนเงินผ่านระบบธนาคาร</option>
                                <option value="cheque">จ่ายด้วยเช็ค</option>
                                <option value="cash">จ่ายด้วยเงินสด</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">บัญชีธนาคารของสถานศึกษา (ผู้จ่าย)</label>
                            <select name="bank_account_id" class="form-select">
                                <option value="">-- เลือกบัญชีธนาคาร --</option>
                                <?php foreach ($bankAccounts as $ba): ?>
                                    <option value="<?= $ba['id'] ?>"><?= e($ba['bank_name']) ?> (<?= e($ba['account_number']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-list-check me-2"></i> รายการพัสดุ / ค่าใช้จ่ายที่ขอเบิก</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addDisburseItemBtn">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่มรายการ
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-gov mb-0" id="disburseItemsTable">
                            <thead>
                                <tr>
                                    <th>รายการ / ค่าใช้จ่าย <span class="text-danger">*</span></th>
                                    <th style="width: 100px;" class="text-center">จำนวน</th>
                                    <th style="width: 130px;" class="text-end">ราคา/หน่วย</th>
                                    <th style="width: 140px;" class="text-end">รวมเป็นเงิน</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="disburseItemsBody">
                                <tr>
                                    <td>
                                        <input type="text" name="item_description[]" class="form-control form-control-sm" placeholder="เช่น ค่าวัสดุอุปกรณ์การสอน" required>
                                    </td>
                                    <td>
                                        <input type="number" step="1" min="1" name="item_quantity[]" class="form-control form-control-sm text-center item-qty" value="1" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="item_unit_price[]" class="form-control form-control-sm text-end item-price" placeholder="0.00" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="item_amount[]" class="form-control form-control-sm text-end item-amt" readonly>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-link text-danger remove-item-btn"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end">ยอดรวมก่อนหักภาษี (บาท):</th>
                                    <th class="text-end fw-bold" id="grossTotal">0.00</th>
                                    <th></th>
                                </tr>
                                <tr>
                                    <th colspan="3" class="text-end text-danger">ภาษีหัก ณ ที่จ่าย (บาท):</th>
                                    <th class="text-end fw-bold text-danger" id="taxTotal">0.00</th>
                                    <th></th>
                                </tr>
                                <tr>
                                    <th colspan="3" class="text-end text-navy fs-6">ยอดเบิกจ่ายสุทธิ (บาท):</th>
                                    <th class="text-end fw-bold text-navy fs-6" id="netTotal">0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Summary -->
        <div class="col-lg-4">
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-shield-check me-2"></i> เวิร์กโฟลว์การอนุมัติ</span>
                </div>
                <div class="card-body">
                    <ol class="small text-muted mb-4 ps-3">
                        <li class="fw-bold text-dark">ร่างคำขอเบิกจ่าย (คุณ)</li>
                        <li>เสนอคำขอ (ระบบผูกพันงบประมาณ)</li>
                        <li>เจ้าหน้าที่ตรวจสอบเอกสารและใบสำคัญ</li>
                        <li>ผู้อำนวยการอนุมัติเบิกจ่าย</li>
                        <li>จ่ายเงินตามวิธีชำระ (หักธนาคาร)</li>
                        <li>ลงบัญชีคู่อัตโนมัติ (General Ledger)</li>
                    </ol>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary-gov btn-lg shadow-sm">
                            <i class="bi bi-save me-1"></i> บันทึกร่างคำขอเบิกจ่าย
                        </button>
                        <a href="<?= $baseUrl ?>/disbursements" class="btn btn-outline-secondary">ยกเลิก</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function recalc() {
        let gross = 0;
        document.querySelectorAll('#disburseItemsBody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const lineAmt = qty * price;
            row.querySelector('.item-amt').value = lineAmt.toFixed(2);
            gross += lineAmt;
        });

        const taxRate = parseFloat(document.getElementById('taxPercent').value) || 0;
        const tax = gross * (taxRate / 100);
        const net = gross - tax;

        document.getElementById('grossTotal').textContent = gross.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('taxTotal').textContent = tax.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('netTotal').textContent = net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    document.getElementById('disburseItemsBody').addEventListener('input', recalc);
    document.getElementById('taxPercent').addEventListener('change', recalc);

    document.getElementById('addDisburseItemBtn').addEventListener('click', function() {
        const tbody = document.getElementById('disburseItemsBody');
        const firstRow = tbody.querySelector('tr');
        const newRow = firstRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(i => i.value = (i.classList.contains('item-qty') ? '1' : ''));
        tbody.appendChild(newRow);
        recalc();
    });

    document.getElementById('disburseItemsBody').addEventListener('click', function(e) {
        if (e.target.closest('.remove-item-btn')) {
            const rows = document.querySelectorAll('#disburseItemsBody tr');
            if (rows.length > 1) {
                e.target.closest('tr').remove();
                recalc();
            } else {
                alert('ต้องมีรายการอย่างน้อย 1 รายการ');
            }
        }
    });
});
</script>
