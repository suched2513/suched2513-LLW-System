<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ออกใบเสร็จรับเงินใหม่</h4>
        <div class="text-muted small">บันทึกรายการรับเงิน ออกเลขที่ใบเสร็จ และส่งลงบัญชีคู่อัตโนมัติ</div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/receipts" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับไปทะเบียนคุม
        </a>
    </div>
</div>

<form method="POST" action="<?= $baseUrl ?>/receipts/store" id="receiptForm">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-person-lines-fill me-2"></i> ข้อมูลผู้ชำระเงินและการรับชำระ</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">วันที่รับเงิน <span class="text-danger">*</span></label>
                            <input type="date" name="receipt_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">แหล่งเงินงบประมาณ <span class="text-danger">*</span></label>
                            <select name="funding_source_id" class="form-select" required>
                                <?php foreach ($fundingSources as $fs): ?>
                                    <option value="<?= $fs['id'] ?>"><?= e($fs['source_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">ชื่อผู้ชำระเงิน / หน่วยงาน <span class="text-danger">*</span></label>
                            <input type="text" name="payer_name" class="form-control" required placeholder="เช่น สำนักงานคณะกรรมการการศึกษาขั้นพื้นฐาน, นายสมศักดิ์ สุขใจ">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">เลขประจำตัวผู้เสียภาษี / บัตรประชาชน</label>
                            <input type="text" name="payer_tax_id" class="form-control" placeholder="13 หลัก (ถ้ามี)">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">วิธีรับเงิน <span class="text-danger">*</span></label>
                            <select name="payment_method" id="paymentMethod" class="form-select" required>
                                <option value="cash">เงินสด</option>
                                <option value="transfer">เงินโอนเข้าบัญชีธนาคาร</option>
                                <option value="cheque">เช็คธนาคาร</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="bankAccountCol" style="display: none;">
                            <label class="form-label fw-semibold">บัญชีธนาคารที่รับโอน</label>
                            <select name="bank_account_id" class="form-select">
                                <option value="">-- เลือกบัญชีธนาคาร --</option>
                                <?php foreach ($bankAccounts as $ba): ?>
                                    <option value="<?= $ba['id'] ?>"><?= e($ba['bank_name']) ?> (<?= e($ba['account_number']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="bankRefCol" style="display: none;">
                            <label class="form-label fw-semibold">เลขอ้างอิงการโอน / เลขที่เช็ค</label>
                            <input type="text" name="bank_ref_no" class="form-control" placeholder="เช่น สลิปโอนเงิน, เลขที่เช็ค">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt Items -->
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-list-check me-2"></i> รายการรับเงิน</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่มแถวรายการ
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-gov mb-0" id="itemsTable">
                            <thead>
                                <tr>
                                    <th>รายการรับชำระ <span class="text-danger">*</span></th>
                                    <th style="width: 200px;">บัญชีรายได้</th>
                                    <th style="width: 180px;" class="text-end">จำนวนเงิน (บาท) <span class="text-danger">*</span></th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr>
                                    <td>
                                        <input type="text" name="item_description[]" class="form-control form-control-sm" placeholder="ระบุรายละเอียด เช่น ค่าบำรุงการศึกษา ภาคเรียนที่ 2" required>
                                    </td>
                                    <td>
                                        <select name="item_account_id[]" class="form-select form-select-sm">
                                            <option value="">-- อัตโนมัติ --</option>
                                            <?php foreach ($revenueAccounts as $ra): ?>
                                                <option value="<?= $ra['id'] ?>"><?= e($ra['account_code']) ?> <?= e($ra['account_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0.01" name="item_amount[]" class="form-control form-control-sm text-end item-amount" placeholder="0.00" required>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-link text-danger remove-line-btn"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2" class="text-end">ยอดรวมสุทธิ (บาท):</th>
                                    <th class="text-end fw-bold text-success fs-6" id="totalAmountDisplay">0.00</th>
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
                    <span><i class="bi bi-info-circle me-2"></i> สรุปการออกใบเสร็จ</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="text-muted d-block small">ปีงบประมาณ:</span>
                        <strong class="text-navy">พ.ศ. <?= e($activeFiscalYear['year_be']) ?></strong>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted d-block small">สถานะเริ่มต้น:</span>
                        <span class="badge bg-primary">ออกใบเสร็จ (Issued)</span>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted d-block small">การลงบัญชีคู่:</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i> ลงสมุดรายวันรับเงินทันที</span>
                    </div>
                    <hr>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary-gov btn-lg shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> บันทึกและออกใบเสร็จ
                        </button>
                        <a href="<?= $baseUrl ?>/receipts" class="btn btn-outline-secondary">ยกเลิก</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const methodSelect = document.getElementById('paymentMethod');
    const bankAccountCol = document.getElementById('bankAccountCol');
    const bankRefCol = document.getElementById('bankRefCol');

    function toggleBankCols() {
        if (methodSelect.value === 'transfer' || methodSelect.value === 'cheque') {
            bankAccountCol.style.display = 'block';
            bankRefCol.style.display = 'block';
        } else {
            bankAccountCol.style.display = 'none';
            bankRefCol.style.display = 'none';
        }
    }
    methodSelect.addEventListener('change', toggleBankCols);
    toggleBankCols();

    // Calculate total
    function calcTotal() {
        let sum = 0;
        document.querySelectorAll('.item-amount').forEach(inp => {
            const val = parseFloat(inp.value) || 0;
            sum += val;
        });
        document.getElementById('totalAmountDisplay').textContent = sum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    document.getElementById('itemsBody').addEventListener('input', function(e) {
        if (e.target.classList.contains('item-amount')) {
            calcTotal();
        }
    });

    // Add item row
    document.getElementById('addItemBtn').addEventListener('click', function() {
        const tbody = document.getElementById('itemsBody');
        const firstRow = tbody.querySelector('tr');
        const newRow = firstRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(i => i.value = '');
        newRow.querySelector('select').selectedIndex = 0;
        tbody.appendChild(newRow);
        calcTotal();
    });

    // Remove item row
    document.getElementById('itemsBody').addEventListener('click', function(e) {
        if (e.target.closest('.remove-line-btn')) {
            const rows = document.querySelectorAll('#itemsBody tr');
            if (rows.length > 1) {
                e.target.closest('tr').remove();
                calcTotal();
            } else {
                alert('ต้องมีรายการอย่างน้อย 1 รายการ');
            }
        }
    });
});
</script>
