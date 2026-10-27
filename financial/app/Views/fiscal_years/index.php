<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">จัดการปีงบประมาณและรอบระยะเวลาบัญชี</h4>
        <div class="text-muted small">กำหนดปีงบประมาณไทย (1 ตุลาคม - 30 กันยายน) และควบคุมการเปิด-ปิดงวดบัญชี 12 งวด</div>
    </div>
    <div>
        <button type="button" class="btn btn-primary-gov btn-sm" data-bs-toggle="modal" data-bs-target="#newYearModal">
            <i class="bi bi-calendar-plus me-1"></i> เพิ่มปีงบประมาณใหม่
        </button>
    </div>
</div>

<div class="row">
    <!-- Fiscal Years List -->
    <div class="col-lg-5 mb-4">
        <div class="card card-gov h-100">
            <div class="card-header">
                <span><i class="bi bi-calendar-range me-2"></i> รายการปีงบประมาณ</span>
            </div>
            <div class="table-responsive">
                <table class="table table-gov table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ปีงบประมาณ</th>
                            <th>ช่วงวันที่</th>
                            <th>สถานะ</th>
                            <th class="text-end">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($years as $y): ?>
                            <tr class="<?= $y['id'] == $selectedYearId ? 'table-primary' : '' ?>">
                                <td class="fw-bold">
                                    <a href="<?= $baseUrl ?>/fiscal-years?year_id=<?= $y['id'] ?>" class="text-decoration-none">
                                        พ.ศ. <?= e($y['year_be']) ?> (<?= e($y['year_ce']) ?>)
                                    </a>
                                </td>
                                <td class="small text-muted">
                                    <?= thai_date($y['start_date'], false, true) ?> - <br>
                                    <?= thai_date($y['end_date'], false, true) ?>
                                </td>
                                <td>
                                    <?php if ($y['is_current']): ?>
                                        <span class="badge bg-success">ปีปัจจุบัน</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ทั่วไป</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (!$y['is_current']): ?>
                                        <form method="POST" action="<?= $baseUrl ?>/fiscal-years/set-current" class="d-inline">
                                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="year_id" value="<?= $y['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary" data-confirm="ต้องการกำหนดให้ปี พ.ศ. <?= e($y['year_be']) ?> เป็นปีงบประมาณปัจจุบันหรือไม่?">
                                                เลือกใช้
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-success disabled"><i class="bi bi-check"></i> กำลังใช้</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 12 Accounting Periods of Selected Fiscal Year -->
    <div class="col-lg-7 mb-4">
        <div class="card card-gov h-100">
            <div class="card-header">
                <span><i class="bi bi-calendar3 me-2"></i> รอบระยะเวลาบัญชี 12 งวด (ปี พ.ศ. <?= e($activeFiscalYear['year_be']) ?>)</span>
                <span class="small text-muted">ปิดงวดเพื่อป้องกันการแก้ไขข้อมูลย้อนหลัง</span>
            </div>
            <div class="table-responsive">
                <table class="table table-gov table-hover mb-0">
                    <thead>
                        <tr>
                            <th>งวดที่</th>
                            <th>ชื่องวดบัญชี</th>
                            <th>ระยะเวลา</th>
                            <th>สถานะงวด</th>
                            <th class="text-end">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($periods)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">ไม่พบข้อมูลรอบระยะเวลาบัญชี</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($periods as $p): ?>
                                <tr>
                                    <td class="fw-bold text-center"><?= $p['period_number'] ?></td>
                                    <td><?= e($p['period_name']) ?></td>
                                    <td class="small text-muted">
                                        <?= thai_date($p['start_date'], false, true) ?> - <?= thai_date($p['end_date'], false, true) ?>
                                    </td>
                                    <td>
                                        <?php if ($p['is_closed']): ?>
                                            <span class="badge bg-danger"><i class="bi bi-lock-fill me-1"></i> ปิดงวดแล้ว</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="bi bi-unlock-fill me-1"></i> เปิดรับรายการ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= $baseUrl ?>/fiscal-years/toggle-period-lock" class="d-inline">
                                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="period_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="year_id" value="<?= $selectedYearId ?>">
                                            <button type="submit" class="btn btn-sm <?= $p['is_closed'] ? 'btn-outline-success' : 'btn-outline-danger' ?>"
                                                    data-confirm="ต้องการ<?= $p['is_closed'] ? 'เปิดงวดบัญชี' : 'ปิดงวดบัญชี (ห้ามบันทึกรายการเพิ่ม)' ?> ใช่หรือไม่?">
                                                <i class="bi bi-<?= $p['is_closed'] ? 'unlock' : 'lock' ?>"></i> <?= $p['is_closed'] ? 'เปิดงวด' : 'ปิดงวด' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add New Fiscal Year -->
<div class="modal fade" id="newYearModal" tabindex="-1" aria-labelledby="newYearModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/fiscal-years/store">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="newYearModalLabel"><i class="bi bi-calendar-plus me-2"></i> เพิ่มปีงบประมาณใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ระบุปีงบประมาณ (พ.ศ.)</label>
                        <input type="number" name="year_be" class="form-control" value="<?= thai_fiscal_year() + 1 ?>" min="2560" max="2650" required>
                        <div class="form-text small">ระบบจะสร้างรอบระยะเวลาบัญชี 12 งวด (1 ต.ค. - 30 ก.ย.) ให้โดยอัตโนมัติ</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gov">บันทึกและสร้างงวดบัญชี</button>
                </div>
            </div>
        </form>
    </div>
</div>
