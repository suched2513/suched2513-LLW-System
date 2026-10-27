<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">กำหนดอัตราค่าบำรุงการศึกษา</h4>
        <div class="text-muted small">กำหนดอัตราค่าบำรุงการศึกษาแยกตามโครงการและระดับชั้น ประจำปีการศึกษา <?= e($yearBe) ?> ภาคเรียนที่ <?= e($semester) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/special-programs" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
        <button type="button" class="btn btn-primary-gov btn-sm" data-bs-toggle="modal" data-bs-target="#newRateModal">
            <i class="bi bi-plus-circle me-1"></i> เพิ่ม / แก้ไขอัตราค่าบำรุง
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card card-gov mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= $baseUrl ?>/special-programs/rates" class="row g-2 align-items-center">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">ปีการศึกษา (พ.ศ.):</label>
                <input type="number" name="year_be" class="form-control form-control-sm" value="<?= e($yearBe) ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">ภาคเรียนที่:</label>
                <select name="semester" class="form-select form-select-sm">
                    <option value="1" <?= $semester == 1 ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                    <option value="2" <?= $semester == 2 ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                </select>
            </div>
            <div class="col-md-2 pt-3">
                <button type="submit" class="btn btn-sm btn-primary-gov w-100">
                    <i class="bi bi-search me-1"></i> แสดงผล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Rates Table -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-tag me-2"></i> บัญชีอัตราค่าบำรุงการศึกษา (<?= count($rates) ?> รายการ)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>รหัสโครงการ</th>
                    <th>ชื่อโครงการพิเศษ</th>
                    <th>ระดับชั้น</th>
                    <th>ปีการศึกษา / ภาคเรียน</th>
                    <th class="text-end" style="width: 200px;">อัตราค่าบำรุง (บาท/คน)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rates)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">ยังไม่ได้กำหนดอัตราค่าบำรุงการศึกษาสำหรับภาคเรียนนี้</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rates as $r): ?>
                        <tr>
                            <td class="fw-bold"><span class="badge bg-primary"><?= e($r['program_code']) ?></span></td>
                            <td class="fw-semibold"><?= e($r['program_name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($r['grade_level']) ?></span></td>
                            <td><?= e($r['academic_year_be']) ?> / <?= e($r['semester']) ?></td>
                            <td class="text-end fw-bold text-navy fs-6"><?= money_format_th($r['fee_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Rate -->
<div class="modal fade" id="newRateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/special-programs/rates">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-tag me-2"></i> กำหนดอัตราค่าบำรุงการศึกษา</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">โครงการพิเศษ <span class="text-danger">*</span></label>
                        <select name="special_program_id" class="form-select" required>
                            <?php foreach ($programs as $prog): ?>
                                <option value="<?= $prog['id'] ?>"><?= e($prog['program_code']) ?> - <?= e($prog['program_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ปีการศึกษา (พ.ศ.)</label>
                            <input type="number" name="academic_year_be" class="form-control" value="<?= e($yearBe) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ภาคเรียนที่</label>
                            <select name="semester" class="form-select">
                                <option value="1" <?= $semester == 1 ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                                <option value="2" <?= $semester == 2 ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ระดับชั้น <span class="text-danger">*</span></label>
                        <select name="grade_level" class="form-select" required>
                            <option value="ม.1">มัธยมศึกษาปีที่ 1 (ม.1)</option>
                            <option value="ม.2">มัธยมศึกษาปีที่ 2 (ม.2)</option>
                            <option value="ม.3">มัธยมศึกษาปีที่ 3 (ม.3)</option>
                            <option value="ม.4">มัธยมศึกษาปีที่ 4 (ม.4)</option>
                            <option value="ม.5">มัธยมศึกษาปีที่ 5 (ม.5)</option>
                            <option value="ม.6">มัธยมศึกษาปีที่ 6 (ม.6)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">อัตราค่าบำรุงการศึกษา (บาท/คน) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="fee_amount" class="form-control" placeholder="เช่น 3500.00" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gov">บันทึกอัตรา</button>
                </div>
            </div>
        </form>
    </div>
</div>
