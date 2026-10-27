<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">บัญชีเงินฝากธนาคารและการกระทบยอด</h4>
        <div class="text-muted small">ทะเบียนบัญชีธนาคารโรงเรียนละลมวิทยา สเตทเมนต์ และการจัดทำงบพิสูจน์ยอดเงินฝากธนาคาร</div>
    </div>
    <div>
        <button type="button" class="btn btn-primary-gov btn-sm" data-bs-toggle="modal" data-bs-target="#newAccountModal">
            <i class="bi bi-bank me-1"></i> เพิ่มบัญชีธนาคารใหม่
        </button>
    </div>
</div>

<!-- Bank Accounts Cards -->
<div class="row g-3 mb-4">
    <?php foreach ($accounts as $acc): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card card-gov h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="p-2 bg-primary-subtle text-primary rounded fs-4">
                            <i class="bi bi-bank2"></i>
                        </div>
                        <span class="badge bg-light text-dark border">
                            <?= $acc['account_type'] === 'current' ? 'กระแสรายวัน' : 'ออมทรัพย์' ?>
                        </span>
                    </div>

                    <h5 class="fw-bold mb-1 text-navy"><?= e($acc['bank_name']) ?></h5>
                    <div class="text-muted small mb-2"><?= e($acc['branch_name'] ? "สาขา {$acc['branch_name']}" : '-') ?></div>
                    <div class="small fw-semibold mb-3">
                        เลขที่บัญชี: <code><?= e($acc['account_no']) ?></code>
                    </div>

                    <div class="p-2 bg-light rounded border mb-3">
                        <div class="text-muted small">ยอดเงินคงเหลือตามสมุดบัญชี:</div>
                        <div class="fs-4 fw-bold text-success"><?= money_format_th($acc['current_balance']) ?> <span class="fs-6 fw-normal text-muted">บาท</span></div>
                    </div>

                    <div class="d-flex gap-1 justify-content-end">
                        <a href="<?= $baseUrl ?>/banks/ledger/<?= $acc['id'] ?>" class="btn btn-sm btn-outline-secondary" title="ความเคลื่อนไหว">
                            <i class="bi bi-journal-text me-1"></i> ความเคลื่อนไหว
                        </a>
                        <a href="<?= $baseUrl ?>/banks/statement/<?= $acc['id'] ?>" class="btn btn-sm btn-outline-primary" title="สเตทเมนต์">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> สเตทเมนต์
                        </a>
                        <a href="<?= $baseUrl ?>/banks/reconcile/<?= $acc['id'] ?>" class="btn btn-sm btn-gold" title="กระทบยอด">
                            <i class="bi bi-check2-all me-1"></i> กระทบยอด
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal: New Bank Account -->
<div class="modal fade" id="newAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/banks/store">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-bank me-2"></i> เพิ่มบัญชีเงินฝากธนาคาร</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อธนาคาร <span class="text-danger">*</span></label>
                        <input type="text" name="bank_name" class="form-control" placeholder="เช่น ธนาคารกรุงไทย, ธ.ก.ส." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">สาขา</label>
                        <input type="text" name="branch_name" class="form-control" placeholder="เช่น สาขาขุขันธ์, สาขาละลม">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลขที่บัญชี <span class="text-danger">*</span></label>
                        <input type="text" name="account_no" class="form-control" placeholder="เช่น 328-0-12345-6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อบัญชี <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control" placeholder="เช่น โรงเรียนละลมวิทยา (เงินรายได้สถานศึกษา)" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ประเภทบัญชี</label>
                            <select name="account_type" class="form-select">
                                <option value="savings">ออมทรัพย์ (Savings)</option>
                                <option value="current">กระแสรายวัน (Current/Cheque)</option>
                                <option value="fixed">ประจำ (Fixed)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ยอดเงินยกมาเริ่มต้น (บาท)</label>
                            <input type="number" step="0.01" min="0" name="initial_balance" class="form-control" value="0.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">แหล่งเงินงบประมาณที่ผูกกับบัญชี</label>
                        <select name="funding_source_id" class="form-select">
                            <option value="">-- ไม่ระบุ / ทั่วไป --</option>
                            <?php foreach ($fundingSources as $fs): ?>
                                <option value="<?= $fs['id'] ?>"><?= e($fs['source_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gov">บันทึกบัญชีธนาคาร</button>
                </div>
            </div>
        </form>
    </div>
</div>
