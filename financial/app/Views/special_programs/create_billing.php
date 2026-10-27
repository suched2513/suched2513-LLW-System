<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ตั้งหนี้ค่าบำรุงการศึกษาแบบรายห้องเรียน</h4>
        <div class="text-muted small">สร้างรายการแจ้งหนี้ค่าบำรุงการศึกษาแบบกลุ่ม (Batch Billing) สำหรับนักเรียนทั้งห้องเรียน</div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/special-programs/charges" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับไปทะเบียนคุม
        </a>
    </div>
</div>

<form method="POST" action="<?= $baseUrl ?>/special-programs/create-billing">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <div class="row">
        <div class="col-lg-7">
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-person-lines-fill me-2"></i> ข้อมูลการตั้งหนี้</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลือกอัตราค่าบำรุงการศึกษา <span class="text-danger">*</span></label>
                        <select name="fee_rate_id" class="form-select" required>
                            <option value="">-- เลือกโครงการและระดับชั้น --</option>
                            <?php foreach ($rates as $r): ?>
                                <option value="<?= $r['id'] ?>">
                                    <?= e($r['program_code']) ?>: <?= e($r['program_name']) ?> (<?= e($r['grade_level']) ?>) - <?= money_format_th($r['fee_amount']) ?> บาท/คน [ภาค <?= e($r['semester']) ?>/<?= e($r['academic_year_be']) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อห้องเรียน <span class="text-danger">*</span></label>
                        <input type="text" name="classroom" class="form-control" placeholder="เช่น ม.1/1, ม.4/1 (MEP)" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">รายชื่อนักเรียน (วางข้อมูลรหัสนักเรียน และชื่อ-นามสกุล 1 คนต่อ 1 บรรทัด) <span class="text-danger">*</span></label>
                        <textarea name="student_list" class="form-control font-monospace" rows="10" required placeholder="10001, เด็กชายสมชาย เก่งเรียน&#10;10002, เด็กหญิงกานดา มุ่งมั่น&#10;10003, นายธนวัฒน์ ศรีสุข"></textarea>
                        <div class="form-text small">รูปแบบ: <code>รหัสนักเรียน, ชื่อ-นามสกุล</code> (คั่นด้วยเครื่องหมายจุลภาค หรือ Tab)</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-gov btn-lg shadow-sm">
                            <i class="bi bi-save me-1"></i> ยืนยันการตั้งหนี้ทั้งห้องเรียน
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-gov bg-light mb-4">
                <div class="card-header bg-transparent">
                    <span><i class="bi bi-info-circle me-2"></i> คำแนะนำการใช้งาน</span>
                </div>
                <div class="card-body small text-muted">
                    <p>ระบบจะสร้างรายการตั้งหนี้สำหรับนักเรียนทุกคนในห้องเรียนตามอัตราที่เลือก:</p>
                    <ul>
                        <li>ยอดหนี้สุทธิจะถูกคำนวณจากอัตราค่าบำรุงการศึกษาของโครงการพิเศษที่ผูกไว้</li>
                        <li>สามารถคัดลอก (Copy) คอลัมน์รหัสและชื่อนักเรียนจาก Excel มาวางได้ทันที</li>
                        <li>เมื่อนักเรียนมาชำระเงิน เจ้าหน้าที่การเงินสามารถกดปุ่ม <strong>"รับชำระ"</strong> ในหน้ารายชื่อเพื่อออกใบเสร็จรับเงินราชการได้ทันที</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>
