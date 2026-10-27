<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ตั้งค่าระบบและข้อมูลหน่วยงาน</h4>
        <div class="text-muted small">กำหนดข้อมูลสถานศึกษา ตราสัญลักษณ์ ลายมือชื่อผู้บริหาร และนโยบายควบคุมงบประมาณ</div>
    </div>
</div>

<form method="POST" action="<?= $baseUrl ?>/settings" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <div class="row">
        <!-- 1. School Information -->
        <div class="col-lg-8">
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-building me-2"></i> ข้อมูลสถานศึกษา</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">ชื่อสถานศึกษา <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="school_name" value="<?= e($settings['school_name'] ?? 'โรงเรียนละลมวิทยา') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">รหัสสถานศึกษา (10 หลัก)</label>
                            <input type="text" class="form-control" name="school_code" value="<?= e($settings['school_code'] ?? '33042006') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">สังกัดหน่วยงาน</label>
                            <input type="text" class="form-control" name="affiliation" value="<?= e($settings['affiliation'] ?? 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร') ?>">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">ที่อยู่</label>
                            <input type="text" class="form-control" name="address" value="<?= e($settings['address'] ?? 'หมู่ 2 ตำบลละลม') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">ตำบล/แขวง</label>
                            <input type="text" class="form-control" name="subdistrict" value="<?= e($settings['subdistrict'] ?? 'ละลม') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">อำเภอ/เขต</label>
                            <input type="text" class="form-control" name="district" value="<?= e($settings['district'] ?? 'ภูสิงห์') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">จังหวัด</label>
                            <input type="text" class="form-control" name="province" value="<?= e($settings['province'] ?? 'ศรีสะเกษ') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">รหัสไปรษณีย์</label>
                            <input type="text" class="form-control" name="postal_code" value="<?= e($settings['postal_code'] ?? '33140') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">โทรศัพท์</label>
                            <input type="text" class="form-control" name="phone" value="<?= e($settings['phone'] ?? '045-810787') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">อีเมลหน่วยงาน</label>
                            <input type="email" class="form-control" name="email" value="<?= e($settings['email'] ?? 'lalomwittaya@gmail.com') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">เลขประจำตัวผู้เสียภาษี (Tax ID)</label>
                            <input type="text" class="form-control" name="tax_id" value="<?= e($settings['tax_id'] ?? '0994000376228') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Officers & Signatures -->
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-pen me-2"></i> ผู้มีอำนาจลงนามในเอกสารและรายงานทางการเงิน</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อผู้อำนวยการสถานศึกษา</label>
                            <input type="text" class="form-control" name="principal_name" value="<?= e($settings['principal_name'] ?? 'นายสุรศักดิ์ สุขใจ') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ตำแหน่งผู้อำนวยการ</label>
                            <input type="text" class="form-control" name="principal_position" value="<?= e($settings['principal_position'] ?? 'ผู้อำนวยการโรงเรียนละลมวิทยา') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อรองผู้อำนวยการฝ่ายบริหารงบประมาณ</label>
                            <input type="text" class="form-control" name="budget_deputy_name" value="<?= e($settings['budget_deputy_name'] ?? 'นางสาวกานดา วิชางาม') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อหัวหน้าเจ้าหน้าที่การเงิน</label>
                            <input type="text" class="form-control" name="finance_head_name" value="<?= e($settings['finance_head_name'] ?? 'นางกนกพร การเงินดี') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อหัวหน้าเจ้าหน้าที่บัญชี</label>
                            <input type="text" class="form-control" name="accountant_head_name" value="<?= e($settings['accountant_head_name'] ?? 'นายบัญชา ลงบัญชีแม่น') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อหัวหน้าเจ้าหน้าที่พัสดุ</label>
                            <input type="text" class="form-control" name="inventory_head_name" value="<?= e($settings['inventory_head_name'] ?? 'นายพัสกร ดูแลพัสดุ') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Logos & Policies -->
        <div class="col-lg-4">
            <!-- Logos -->
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-image me-2"></i> ตราสัญลักษณ์สถานศึกษา</span>
                </div>
                <div class="card-body">
                    <div class="mb-3 text-center">
                        <?php if (!empty($settings['school_logo_path'])): ?>
                            <img src="<?= $baseUrl ?>/<?= e($settings['school_logo_path']) ?>" alt="Logo" class="img-thumbnail mb-2" style="max-height: 100px;">
                        <?php else: ?>
                            <div class="p-3 border rounded bg-light text-muted mb-2"><i class="bi bi-shield text-secondary fs-1"></i><br>ยังไม่มีตราโรงเรียน</div>
                        <?php endif; ?>
                        <label class="form-label fw-semibold d-block text-start">อัปโหลดตราสัญลักษณ์โรงเรียน (PNG/JPG)</label>
                        <input type="file" class="form-control form-control-sm" name="school_logo" accept="image/*">
                    </div>

                    <hr>

                    <div class="mb-3 text-center">
                        <?php if (!empty($settings['garuda_logo_path'])): ?>
                            <img src="<?= $baseUrl ?>/<?= e($settings['garuda_logo_path']) ?>" alt="Garuda" class="img-thumbnail mb-2" style="max-height: 100px;">
                        <?php else: ?>
                            <div class="p-3 border rounded bg-light text-muted mb-2"><i class="bi bi-award text-secondary fs-1"></i><br>ยังไม่มีตราครุฑ</div>
                        <?php endif; ?>
                        <label class="form-label fw-semibold d-block text-start">อัปโหลดตราครุฑสำหรับเอกสารราชการ</label>
                        <input type="file" class="form-control form-control-sm" name="garuda_logo" accept="image/*">
                    </div>
                </div>
            </div>

            <!-- Policies -->
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-shield-lock me-2"></i> นโยบายควบคุมงบประมาณ</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">แจ้งเตือนงบใกล้หมดเมื่อใช้ถึง (%)</label>
                        <div class="input-group">
                            <input type="number" step="1" min="1" max="100" class="form-control" name="budget_warning_percent" value="<?= e($settings['budget_warning_percent'] ?? '80') ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ระงับการเบิกจ่ายเมื่อใช้ถึง (%)</label>
                        <div class="input-group">
                            <input type="number" step="1" min="1" max="150" class="form-control" name="budget_block_percent" value="<?= e($settings['budget_block_percent'] ?? '100') ?>">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text text-danger small">ห้ามเบิกเกิน 100% ตามระเบียบกระทรวงการคลัง</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">อัตราภาษีมูลค่าเพิ่ม (VAT %)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" name="vat_rate" value="<?= e($settings['vat_rate'] ?? '7.00') ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ภาษีหัก ณ ที่จ่าย ค่าจ้าง/บริการ (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" name="withholding_tax_service_rate" value="<?= e($settings['withholding_tax_service_rate'] ?? '1.00') ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid mb-4">
                <button type="submit" class="btn btn-gold btn-lg shadow-sm">
                    <i class="bi bi-save me-2"></i> บันทึกการตั้งค่าระบบ
                </button>
            </div>
        </div>
    </div>
</form>
