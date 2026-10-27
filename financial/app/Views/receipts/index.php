<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ระบบรับเงินและออกใบเสร็จ</h4>
        <div class="text-muted small">บันทึกรับเงินอุดหนุน เงินรายได้สถานศึกษา ค่าบำรุงการศึกษา และนำฝากธนาคาร</div>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($undepositedCash)): ?>
            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#depositModal">
                <i class="bi bi-bank me-1"></i> นำฝากเงินสด (<?= count($undepositedCash) ?> รายการ)
            </button>
        <?php endif; ?>
        <a href="<?= $baseUrl ?>/receipts/create" class="btn btn-primary-gov btn-sm">
            <i class="bi bi-plus-circle me-1"></i> ออกใบเสร็จรับเงินใหม่
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card card-gov mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= $baseUrl ?>/receipts" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm" name="search" placeholder="ค้นหาเลขที่, ผู้ชำระ, เลขอ้างอิง..." value="<?= e($filter['search'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- ทุกสถานะ --</option>
                    <option value="issued" <?= ($filter['status'] ?? '') === 'issued' ? 'selected' : '' ?>>ออกใบเสร็จแล้ว</option>
                    <option value="deposited" <?= ($filter['status'] ?? '') === 'deposited' ? 'selected' : '' ?>>นำฝากธนาคารแล้ว</option>
                    <option value="cancelled" <?= ($filter['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>ยกเลิกแล้ว</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="payment_method" class="form-select form-select-sm">
                    <option value="">-- ทุกวิธีชำระ --</option>
                    <option value="cash" <?= ($filter['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>เงินสด</option>
                    <option value="transfer" <?= ($filter['payment_method'] ?? '') === 'transfer' ? 'selected' : '' ?>>เงินโอน</option>
                    <option value="cheque" <?= ($filter['payment_method'] ?? '') === 'cheque' ? 'selected' : '' ?>>เช็ค</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($filter['date_from'] ?? '') ?>" placeholder="จากวันที่">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($filter['date_to'] ?? '') ?>" placeholder="ถึงวันที่">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary-gov flex-grow-1"><i class="bi bi-search"></i></button>
                <a href="<?= $baseUrl ?>/receipts" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Receipts Table -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-receipt me-2"></i> ทะเบียนคุมใบเสร็จรับเงิน (<?= count($receipts) ?> รายการ)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>เลขที่ใบเสร็จ</th>
                    <th>วันที่รับเงิน</th>
                    <th>ผู้ชำระเงิน</th>
                    <th>แหล่งเงิน</th>
                    <th>วิธีชำระ</th>
                    <th class="text-end">จำนวนเงิน (บาท)</th>
                    <th>สถานะ</th>
                    <th class="text-end">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($receipts)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">ไม่พบรายการใบเสร็จรับเงิน</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $methodsTh = ['cash' => 'เงินสด', 'transfer' => 'เงินโอน', 'cheque' => 'เช็ค'];
                    foreach ($receipts as $r): 
                    ?>
                        <tr class="<?= $r['status'] === 'cancelled' ? 'text-muted table-light' : '' ?>">
                            <td class="fw-bold">
                                <a href="<?= $baseUrl ?>/receipts/show/<?= $r['id'] ?>" class="text-decoration-none">
                                    <code><?= e($r['receipt_no']) ?></code>
                                </a>
                            </td>
                            <td><?= thai_date($r['receipt_date'], false, true) ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($r['payer_name']) ?></div>
                                <?php if ($r['payer_tax_id']): ?>
                                    <small class="text-muted">Tax ID: <?= e($r['payer_tax_id']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= e($r['source_name']) ?></span></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?= $methodsTh[$r['payment_method']] ?? $r['payment_method'] ?>
                                </span>
                                <?php if ($r['bank_name']): ?>
                                    <div class="small text-muted"><?= e($r['bank_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold <?= $r['status'] === 'cancelled' ? 'text-decoration-line-through text-muted' : 'text-success' ?>">
                                <?= money_format_th($r['total_amount']) ?>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'issued'): ?>
                                    <span class="badge bg-primary">ออกใบเสร็จ</span>
                                <?php elseif ($r['status'] === 'deposited'): ?>
                                    <span class="badge bg-success"><i class="bi bi-bank"></i> นำฝากแล้ว</span>
                                <?php elseif ($r['status'] === 'cancelled'): ?>
                                    <span class="badge bg-danger">ยกเลิกแล้ว</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= $baseUrl ?>/receipts/show/<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary" title="ดู / พิมพ์">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <?php if ($r['status'] === 'issued'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal_<?= $r['id'] ?>" title="ยกเลิกใบเสร็จ">
                                        <i class="bi bi-x-circle"></i>
                                    </button>

                                    <!-- Modal: Cancel Receipt -->
                                    <div class="modal fade" id="cancelModal_<?= $r['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" action="<?= $baseUrl ?>/receipts/cancel">
                                                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="receipt_id" value="<?= $r['id'] ?>">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i> ยกเลิกใบเสร็จรับเงิน</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <p>ยืนยันการยกเลิกใบเสร็จรับเงินเลขที่ <strong><?= e($r['receipt_no']) ?></strong> จำนวนเงิน <strong><?= money_format_th($r['total_amount']) ?> บาท</strong></p>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">ระบุเหตุผลในการยกเลิก <span class="text-danger">*</span></label>
                                                            <textarea name="cancellation_reason" class="form-control" rows="3" required placeholder="เช่น ออกผิดพลาด, ผู้ชำระเงินขอยกเลิก"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                                        <button type="submit" class="btn btn-danger">ยืนยันยกเลิกใบเสร็จ</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Deposit Cash Receipts -->
<?php if (!empty($undepositedCash)): ?>
<div class="modal fade" id="depositModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="<?= $baseUrl ?>/receipts/deposit">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-bank me-2"></i> นำฝากเงินสดเข้าบัญชีธนาคาร</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">บัญชีธนาคารปลายทาง <span class="text-danger">*</span></label>
                            <select name="bank_account_id" class="form-select" required>
                                <option value="">-- เลือกบัญชีธนาคาร --</option>
                                <?php foreach ($bankAccounts as $ba): ?>
                                    <option value="<?= $ba['id'] ?>"><?= e($ba['bank_name']) ?> (<?= e($ba['account_number']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">วันที่นำฝากธนาคาร <span class="text-danger">*</span></label>
                            <input type="date" name="deposit_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <label class="form-label fw-semibold">เลือกรายการใบเสร็จเงินสดที่ต้องการนำฝาก:</label>
                    <div class="table-responsive border rounded" style="max-height: 250px;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="selectAllCash" checked></th>
                                    <th>เลขที่</th>
                                    <th>วันที่</th>
                                    <th>ผู้ชำระ</th>
                                    <th class="text-end">จำนวนเงิน (บาท)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($undepositedCash as $c): ?>
                                    <tr>
                                        <td><input type="checkbox" name="receipt_ids[]" value="<?= $c['id'] ?>" class="cash-checkbox" checked></td>
                                        <td><?= e($c['receipt_no']) ?></td>
                                        <td><?= thai_date($c['receipt_date'], false, true) ?></td>
                                        <td><?= e($c['payer_name']) ?></td>
                                        <td class="text-end fw-bold"><?= money_format_th($c['total_amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gov">บันทึกการนำฝากธนาคาร</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllCash');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.cash-checkbox').forEach(cb => cb.checked = selectAll.checked);
        });
    }
});
</script>
<?php endif; ?>
