<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">จัดการผู้ใช้งานและกำหนดสิทธิ์</h4>
        <div class="text-muted small">กำหนดบัญชีผู้ใช้งาน มอบหมายบทบาท และควบคุมการเข้าถึงระบบตามหลักการแบ่งแยกหน้าที่ (Segregation of Duties)</div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/users/create" class="btn btn-primary-gov btn-sm">
            <i class="bi bi-person-plus-fill me-1"></i> เพิ่มผู้ใช้งานใหม่
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card card-gov mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= $baseUrl ?>/users" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" name="search" placeholder="ค้นหาชื่อผู้ใช้, ชื่อ-นามสกุล, อีเมล..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select form-select-sm">
                    <option value="">-- กรองตามบทบาททั้งหมด --</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= e($r['role_code']) ?>" <?= $roleFilter === $r['role_code'] ? 'selected' : '' ?>>
                            <?= e($r['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- สถานะทั้งหมด --</option>
                    <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>เปิดใช้งานปกติ</option>
                    <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>ระงับการใช้งาน</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary-gov flex-grow-1"><i class="bi bi-search"></i> ค้นหา</button>
                <a href="<?= $baseUrl ?>/users" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-people me-2"></i> รายชื่อผู้ใช้งานในระบบ (<?= count($users) ?> บัญชี)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>ชื่อผู้ใช้งาน (Username)</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th>อีเมล</th>
                    <th>บทบาทในระบบ</th>
                    <th>สถานะ</th>
                    <th>สร้างเมื่อ</th>
                    <th class="text-end">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">ไม่พบข้อมูลผู้ใช้งานที่ตรงตามเงื่อนไข</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $idx => $u): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td class="fw-bold">
                                <code><?= e($u['username']) ?></code>
                            </td>
                            <td><?= e($u['full_name']) ?></td>
                            <td><?= e($u['email'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($u['role_names'])): ?>
                                    <?php foreach (explode(', ', $u['role_names']) as $rName): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1 mb-1">
                                            <?= e($rName) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted small">ยังไม่มีบทบาท</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge bg-success">เปิดใช้งาน</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">ระงับการใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= thai_date($u['created_at'], false, true) ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= $baseUrl ?>/users/edit/<?= $u['id'] ?>" class="btn btn-outline-secondary" title="แก้ไข">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="<?= $baseUrl ?>/users/toggle-status" class="d-inline">
                                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-outline-<?= $u['is_active'] ? 'danger' : 'success' ?>" 
                                                title="<?= $u['is_active'] ? 'ระงับบัญชี' : 'เปิดใช้งานบัญชี' ?>"
                                                data-confirm="ยืนยันการ<?= $u['is_active'] ? 'ระงับ' : 'เปิดใช้งาน' ?> บัญชี '<?= e($u['username']) ?>' ใช่หรือไม่?">
                                            <i class="bi bi-<?= $u['is_active'] ? 'slash-circle' : 'check-circle' ?>"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
