<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ใบแจ้งยอดธนาคาร (Bank Statement)</h4>
        <div class="text-muted small"><?= e($account['bank_name']) ?> | เลขที่บัญชี: <code><?= e($account['account_no']) ?></code></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/banks" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
        <a href="<?= $baseUrl ?>/banks/reconcile/<?= $account['id'] ?>" class="btn btn-gold btn-sm">
            <i class="bi bi-check2-all me-1"></i> ไปหน้ากระทบยอด
        </a>
    </div>
</div>

<div class="row">
    <!-- Upload Box -->
    <div class="col-lg-4 mb-4">
        <div class="card card-gov mb-3">
            <div class="card-header">
                <span><i class="bi bi-upload me-2"></i> นำเข้าสเตทเมนต์ (CSV)</span>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= $baseUrl ?>/banks/statement/<?= $account['id'] ?>" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ระบุเดือนของสเตทเมนต์</label>
                        <input type="month" name="statement_month" class="form-control" value="<?= date('Y-m') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลือกไฟล์ CSV สเตทเมนต์</label>
                        <input type="file" name="statement_file" class="form-control" accept=".csv" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-gov">
                            <i class="bi bi-cloud-arrow-up me-1"></i> อัปโหลดและประมวลผล
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-gov bg-light">
            <div class="card-header bg-transparent">
                <span><i class="bi bi-file-earmark-spreadsheet me-2"></i> รูปแบบไฟล์ CSV ที่รองรับ</span>
            </div>
            <div class="card-body small text-muted">
                <p>ไฟล์ CSV ต้องมีคอลัมน์เรียงตามลำดับดังนี้:</p>
                <ol>
                    <li><strong>วันที่:</strong> เช่น <code>2026-10-25</code></li>
                    <li><strong>รายการ/คำอธิบาย:</strong> รายละเอียดเงินเข้าออก</li>
                    <li><strong>ถอนเงิน (Withdrawal):</strong> เช่น <code>1500.00</code></li>
                    <li><strong>ฝากเงิน (Deposit):</strong> เช่น <code>50000.00</code></li>
                    <li><strong>ยอดคงเหลือ (Balance):</strong> ยอดเงินตามธนาคาร</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Statement Lines Table -->
    <div class="col-lg-8 mb-4">
        <div class="card card-gov">
            <div class="card-header">
                <span><i class="bi bi-table me-2"></i> รายการในสเตทเมนต์ <?= $import ? "ประจำเดือน " . e($import['statement_month']) : '' ?> (<?= count($lines) ?> รายการ)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-gov table-hover mb-0">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>คำอธิบายรายการ</th>
                            <th class="text-end" style="width: 130px;">ถอนเงิน (บาท)</th>
                            <th class="text-end" style="width: 130px;">ฝากเงิน (บาท)</th>
                            <th class="text-end" style="width: 140px;">ยอดคงเหลือ (บาท)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lines)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">ยังไม่มีการนำเข้าสเตทเมนต์สำหรับบัญชีนี้</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lines as $l): ?>
                                <tr>
                                    <td><?= thai_date($l['line_date'], false, true) ?></td>
                                    <td><?= e($l['description'] ?? '-') ?></td>
                                    <td class="text-end text-danger"><?= (float)$l['withdrawal'] > 0 ? money_format_th($l['withdrawal']) : '-' ?></td>
                                    <td class="text-end text-success"><?= (float)$l['deposit'] > 0 ? money_format_th($l['deposit']) : '-' ?></td>
                                    <td class="text-end fw-bold"><?= money_format_th($l['balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
