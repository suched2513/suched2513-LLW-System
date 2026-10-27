<?php
$isEdit = !empty($user);
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$actionUrl = $isEdit ? "{$baseUrl}/users/update/{$user['id']}" : "{$baseUrl}/users/store";
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;"><?= e($pageTitle) ?></h4>
        <div class="text-muted small"><?= $isEdit ? 'แก้ไขข้อมูลส่วนบุคคลและกำหนดบทบาทสิทธิ์' : 'กรอกข้อมูลเพื่อสร้างบัญชีผู้ใช้งานใหม่' ?></div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/users" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับไปรายชื่อ
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="<?= $actionUrl ?>">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-person me-2"></i> ข้อมูลบัญชีผู้ใช้งาน</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อผู้ใช้งาน (Username) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" value="<?= e($user['username'] ?? '') ?>" <?= $isEdit ? 'readonly' : 'required' ?> placeholder="เช่น somchai_k">
                            <?php if ($isEdit): ?>
                                <div class="form-text small">ไม่สามารถแก้ไขชื่อผู้ใช้งานได้</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?= $isEdit ? 'เปลี่ยนรหัสผ่านใหม่ (เว้นว่างหากไม่เปลี่ยน)' : 'รหัสผ่านเริ่มต้น <span class="text-danger">*</span>' ?></label>
                            <input type="password" class="form-control" name="password" <?= $isEdit ? '' : 'required' ?> placeholder="ความยาวอย่างน้อย 8 ตัวอักษร">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อ - นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" value="<?= e($user['full_name'] ?? '') ?>" required placeholder="เช่น นายสมชาย เก่งการเงิน">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">อีเมลติดต่อ</label>
                            <input type="email" class="form-control" name="email" value="<?= e($user['email'] ?? '') ?>" placeholder="name@llw.ac.th">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Role Assignment -->
            <div class="card card-gov mb-4">
                <div class="card-header">
                    <span><i class="bi bi-shield-check me-2"></i> กำหนดบทบาทในระบบ (Roles)</span>
                    <span class="small text-muted">สามารถเลือกได้มากกว่า 1 บทบาท</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($roles as $r): ?>
                            <div class="col-md-6">
                                <div class="form-check p-3 border rounded h-100 bg-light">
                                    <input class="form-check-input" type="checkbox" name="roles[]" value="<?= $r['id'] ?>" id="role_<?= $r['id'] ?>"
                                        <?= in_array($r['id'], $userRoles) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold d-block" for="role_<?= $r['id'] ?>">
                                        <?= e($r['role_name']) ?>
                                        <div class="small fw-normal text-muted"><?= e($r['description'] ?? '') ?></div>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-4">
                <a href="<?= $baseUrl ?>/users" class="btn btn-outline-secondary">ยกเลิก</a>
                <button type="submit" class="btn btn-primary-gov">
                    <i class="bi bi-save me-1"></i> <?= $isEdit ? 'บันทึกการเปลี่ยนแปลง' : 'สร้างบัญชีผู้ใช้งาน' ?>
                </button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card card-gov bg-light">
            <div class="card-header bg-transparent">
                <span><i class="bi bi-info-circle me-2"></i> หลักการแบ่งแยกหน้าที่ (SoD)</span>
            </div>
            <div class="card-body small text-muted">
                <p>ระบบกำหนดหลักการควบคุมภายในของสถานศึกษา ดังนี้:</p>
                <ul>
                    <li><strong>ผู้ขอเบิก (Requester):</strong> ไม่สามารถอนุมัติรายการของตนเองได้</li>
                    <li><strong>ผู้ดูแลระบบ (Admin):</strong> มีสิทธิ์ตั้งค่าระบบและผู้ใช้งาน แต่ไม่ได้รับสิทธิ์อนุมัติการเงินโดยอัตโนมัติ</li>
                    <li><strong>ผู้อนุมัติ (Approver):</strong> ผู้อำนวยการโรงเรียนหรือผู้ได้รับมอบอำนาจ</li>
                    <li><strong>เจ้าหน้าที่การเงินและบัญชี:</strong> แยกหน้าที่ระหว่างผู้รับ/จ่ายเงิน กับผู้บันทึกบัญชีคู่</li>
                </ul>
            </div>
        </div>
    </div>
</div>
