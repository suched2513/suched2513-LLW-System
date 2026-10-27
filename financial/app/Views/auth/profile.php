<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card card-gov h-100">
            <div class="card-header">
                <span><i class="bi bi-person-badge me-2"></i> ข้อมูลบัญชีผู้ใช้งาน</span>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar-circle bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 70px; height: 70px; font-size: 1.8rem; font-weight: 700;">
                        <?= mb_substr($profile['full_name'] ?? 'U', 0, 1) ?>
                    </div>
                    <h5 class="fw-bold mb-0"><?= e($profile['full_name']) ?></h5>
                    <div class="text-muted small">@<?= e($profile['username']) ?></div>
                </div>

                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">อีเมล:</span>
                        <span class="fw-semibold"><?= e($profile['email'] ?? '-') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">สถานะ:</span>
                        <span class="badge bg-success">เปิดใช้งานปกติ</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">วันที่สร้างบัญชี:</span>
                        <span><?= thai_date($profile['created_at'], true) ?></span>
                    </li>
                </ul>

                <h6 class="fw-bold text-navy mb-2"><i class="bi bi-shield-check me-1"></i> บทบาทที่ได้รับมอบหมาย</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($roles as $r): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                            <i class="bi bi-person-fill-gear me-1"></i> <?= e($r['role_name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-4">
        <div class="card card-gov">
            <div class="card-header">
                <span><i class="bi bi-key me-2"></i> เปลี่ยนรหัสผ่าน</span>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= $baseUrl ?>/profile/password">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-semibold">รหัสผ่านปัจจุบัน</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required placeholder="กรอกรหัสผ่านปัจจุบัน">
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required placeholder="กรอกรหัสผ่านใหม่">
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required placeholder="กรอกรหัสผ่านใหม่อีกครั้ง">
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary-gov">
                            <i class="bi bi-save me-1"></i> บันทึกรหัสผ่านใหม่
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
