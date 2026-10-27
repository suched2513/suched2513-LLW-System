<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ทำสัญญายืมเงินราชการ (แบบ 8500)</h4>
        <div class="text-muted small">ยื่นความประสงค์ขอยืมเงินทดรองจ่ายเพื่อปฏิบัติราชการตามโครงการ</div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/advances" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
    </div>
</div>

<form method="POST" action="<?= $baseUrl ?>/advances/store">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <div class="row">
        <div class="col-lg-8">
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-file-earmark-text me-2"></i> สัญญายืมเงิน</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">วันที่ทำสัญญา <span class="text-danger">*</span></label>
                            <input type="date" name="contract_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">กำหนดวันส่งใช้เงินยืม (ไม่เกิน 30 วัน) <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">ผู้ยืมเงิน <span class="text-danger">*</span></label>
                            <select name="borrower_id" class="form-select" required>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($currentUser['id'] == $u['id']) ? 'selected' : '' ?>>
                                        <?= e($u['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">หมวดงบประมาณตามโครงการที่ใช้ยืม <span class="text-danger">*</span></label>
                            <select name="budget_id" class="form-select" required>
                                <option value="">-- เลือกโครงการและหมวดงบประมาณ --</option>
                                <?php foreach ($availableBudgets as $ab): ?>
                                    <option value="<?= $ab['id'] ?>">
                                        <?= e($ab['project_code']) ?>: <?= e($ab['project_name']) ?> [<?= e($ab['budget_category']) ?> | <?= e($ab['source_name']) ?>] (คงเหลือ: <?= money_format_th($ab['remaining_amount']) ?> บาท)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">วัตถุประสงค์ในการยืมเงิน <span class="text-danger">*</span></label>
                            <textarea name="objective" class="form-control" rows="3" required placeholder="ระบุเหตุผลความจำเป็นและกิจกรรมที่ต้องใช้เงินยืม"></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">จำนวนเงินที่ขอยืม (บาท) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="amount" class="form-control form-control-lg text-end fw-bold" required placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-gov mb-4 bg-light">
                <div class="card-header bg-transparent">
                    <span><i class="bi bi-info-circle me-2"></i> ระเบียบการยืมเงินราชการ</span>
                </div>
                <div class="card-body small text-muted">
                    <p>ตามระเบียบกระทรวงการคลังว่าด้วยการเบิกจ่ายเงินจากคลัง:</p>
                    <ul>
                        <li>ผู้ยืมจะต้องส่งหลักฐานการใช้จ่ายเงินและเงินเหลือจ่าย (ถ้ามี) ภายใน <strong>30 วัน</strong> นับจากวันที่ได้รับเงินยืม</li>
                        <li>หากผู้ยืมยังค้างชำระเงินยืมสัญญาเดิม จะ<strong>ไม่สามารถขอยืมเงินสัญญาใหม่ได้</strong></li>
                        <li>ผู้ยืมเงินไม่สามารถเป็นผู้อนุมัติสัญญายืมเงินของตนเองได้</li>
                    </ul>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary-gov btn-lg shadow-sm">
                    <i class="bi bi-save me-1"></i> บันทึกและสร้างสัญญา (แบบ 8500)
                </button>
                <a href="<?= $baseUrl ?>/advances" class="btn btn-outline-secondary">ยกเลิก</a>
            </div>
        </div>
    </div>
</form>
