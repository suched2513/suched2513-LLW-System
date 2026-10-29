<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ประเภทการเงินและแหล่งเงินสถานศึกษา</h4>
        <div class="text-muted small">กำหนดและจัดการประเภทเงิน งบประมาณ เงินอุดหนุน เงินรายได้สถานศึกษา และเงินบริจาค</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/budgets" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> กลับไปหน้างบประมาณ
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSourceModal">
            <i class="bi bi-plus-lg me-1"></i> เพิ่มประเภทการเงิน
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-gov h-100 border-start border-4 border-primary">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-primary-subtle text-primary rounded-3 p-3 me-3">
                        <i class="bi bi-tags fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">ประเภทการเงินทั้งหมด</div>
                        <div class="fs-4 fw-bold text-dark"><?= count($sources) ?> <span class="fs-6 fw-normal text-muted">รายการ</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-gov h-100 border-start border-4 border-info">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-info-subtle text-info rounded-3 p-3 me-3">
                        <i class="bi bi-mortarboard fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">หมวดเงินอุดหนุน (รัฐบาล/สพฐ.)</div>
                        <div class="fs-4 fw-bold text-info"><?= $countsByType['subsidy'] ?? 0 ?> <span class="fs-6 fw-normal text-muted">ประเภท</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-gov h-100 border-start border-4 border-success">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-success-subtle text-success rounded-3 p-3 me-3">
                        <i class="bi bi-cash-coin fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">รายได้สถานศึกษา & บริจาค</div>
                        <div class="fs-4 fw-bold text-success"><?= ($countsByType['revenue'] ?? 0) + ($countsByType['donation'] ?? 0) ?> <span class="fs-6 fw-normal text-muted">ประเภท</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-gov h-100 border-start border-4 border-warning">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-warning-subtle text-warning rounded-3 p-3 me-3">
                        <i class="bi bi-wallet fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">สนับสนุนอื่นๆ & ค่าบำรุง</div>
                        <div class="fs-4 fw-bold text-warning"><?= ($countsByType['other'] ?? 0) + ($countsByType['tuition_fee'] ?? 0) ?> <span class="fs-6 fw-normal text-muted">ประเภท</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Table Card -->
<div class="card card-gov shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-check me-2"></i> ทะเบียนประเภทการเงินและแหล่งเงิน</span>
        <span class="badge bg-light text-dark border"><?= count($sources) ?> รายการ</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;" class="text-center">#</th>
                    <th style="width: 120px;">รหัสประเภท</th>
                    <th>ชื่อประเภทการเงิน / แหล่งเงิน</th>
                    <th style="width: 220px;">หมวดหมู่หลัก</th>
                    <th style="width: 150px;" class="text-end">งบประมาณผูกพัน</th>
                    <th style="width: 150px;" class="text-end">ยอดรับเงินสะสม</th>
                    <th style="width: 100px;" class="text-center">สถานะ</th>
                    <th style="width: 140px;" class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sources)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            ยังไม่มีข้อมูลประเภทการเงินในระบบ
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sources as $idx => $s): ?>
                        <tr>
                            <td class="text-center text-muted"><?= $idx + 1 ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= e($s['source_code']) ?></span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark"><?= e($s['source_name']) ?></span>
                                <?php if ($s['bank_count'] > 0): ?>
                                    <span class="badge bg-info-subtle text-info ms-1" title="มีบัญชีธนาคารผูกอยู่ <?= $s['bank_count'] ?> บัญชี">
                                        <i class="bi bi-bank me-1"></i><?= $s['bank_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $badgeClass = match($s['source_type']) {
                                    'subsidy'     => 'bg-primary-subtle text-primary border border-primary-subtle',
                                    'revenue'     => 'bg-success-subtle text-success border border-success-subtle',
                                    'donation'    => 'bg-info-subtle text-info border border-info-subtle',
                                    'tuition_fee' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                    default       => 'bg-secondary-subtle text-secondary border'
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?> px-2 py-1">
                                    <?= e($typeLabels[$s['source_type']] ?? $s['source_type']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if ($s['budget_count'] > 0): ?>
                                    <div class="fw-semibold text-dark"><?= money_format_th((float)$s['total_budget']) ?> ฿</div>
                                    <small class="text-muted"><?= $s['budget_count'] ?> โครงการ</small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($s['receipt_count'] > 0): ?>
                                    <div class="fw-semibold text-success"><?= money_format_th((float)$s['total_received']) ?> ฿</div>
                                    <small class="text-muted"><?= $s['receipt_count'] ?> ใบเสร็จ</small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($s['is_active']): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>ใช้งาน</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>ระงับ</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal<?= $s['id'] ?>" 
                                            title="แก้ไขข้อมูล">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="<?= $baseUrl ?>/funding-sources/toggle/<?= $s['id'] ?>" class="d-inline" onsubmit="return confirm('ยืนยันเปลี่ยนสถานะการใช้งานรายการนี้?');">
                                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                        <button type="submit" class="btn <?= $s['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $s['is_active'] ? 'ระงับการใช้งาน' : 'เปิดใช้งาน' ?>">
                                            <i class="bi <?= $s['is_active'] ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content text-start">
                                            <form method="POST" action="<?= $baseUrl ?>/funding-sources/update/<?= $s['id'] ?>">
                                                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">แก้ไขประเภทการเงิน</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">รหัสประเภทการเงิน</label>
                                                        <input type="text" class="form-control bg-light" value="<?= e($s['source_code']) ?>" readonly>
                                                        <small class="text-muted">รหัสประเภทการเงินไม่สามารถเปลี่ยนแปลงได้</small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">ชื่อประเภทการเงิน <span class="text-danger">*</span></label>
                                                        <input type="text" name="source_name" class="form-control" value="<?= e($s['source_name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">หมวดหมู่หลัก <span class="text-danger">*</span></label>
                                                        <select name="source_type" class="form-select" required>
                                                            <?php foreach ($typeLabels as $val => $lbl): ?>
                                                                <option value="<?= $val ?>" <?= $s['source_type'] === $val ? 'selected' : '' ?>>
                                                                    <?= e($lbl) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> บันทึกการแก้ไข</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create Funding Source -->
<div class="modal fade" id="createSourceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $baseUrl ?>/funding-sources/store">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>เพิ่มประเภทการเงินใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">รหัสประเภทการเงิน (Code) <span class="text-danger">*</span></label>
                        <input type="text" name="source_code" class="form-control" placeholder="เช่น SUB-09, REV-02, OTH-02" required>
                        <small class="text-muted">ใช้ตัวอักษรภาษาอังกฤษ ตัวเลข และเครื่องหมายขีด</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อประเภทการเงิน <span class="text-danger">*</span></label>
                        <input type="text" name="source_name" class="form-control" placeholder="เช่น เงินอุดหนุนโครงการพิเศษ..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">หมวดหมู่หลัก <span class="text-danger">*</span></label>
                        <select name="source_type" class="form-select" required>
                            <option value="">-- เลือกหมวดหมู่ --</option>
                            <?php foreach ($typeLabels as $val => $lbl): ?>
                                <option value="<?= $val ?>"><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> เพิ่มประเภทการเงิน</button>
                </div>
            </form>
        </div>
    </div>
</div>
