<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ทะเบียนคุมการจัดเก็บค่าบำรุงการศึกษา</h4>
        <div class="text-muted small">ติดตามสถานะการชำระเงินของนักเรียน บันทึกรับเงิน และออกใบเสร็จรับเงินราชการ</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/special-programs" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
        <a href="<?= $baseUrl ?>/special-programs/create-billing" class="btn btn-primary-gov btn-sm">
            <i class="bi bi-person-plus-fill me-1"></i> ตั้งหนี้ห้องเรียนใหม่
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card card-gov mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= $baseUrl ?>/special-programs/charges" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm" name="search" placeholder="ค้นหารหัสนักเรียน, ชื่อ-นามสกุล..." value="<?= e($filter['search'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <select name="classroom" class="form-select form-select-sm">
                    <option value="">-- ทุกห้องเรียน --</option>
                    <?php foreach ($classrooms as $c): ?>
                        <option value="<?= e($c) ?>" <?= ($filter['classroom'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- ทุกสถานะการชำระ --</option>
                    <option value="unpaid" <?= ($filter['status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>ยังไม่ชำระ (ค้างจ่าย)</option>
                    <option value="partial" <?= ($filter['status'] ?? '') === 'partial' ? 'selected' : '' ?>>ชำระบางส่วน</option>
                    <option value="paid" <?= ($filter['status'] ?? '') === 'paid' ? 'selected' : '' ?>>ชำระครบถ้วนแล้ว</option>
                    <option value="waived" <?= ($filter['status'] ?? '') === 'waived' ? 'selected' : '' ?>>ยกเว้น / ได้รับทุน</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary-gov flex-grow-1"><i class="bi bi-search"></i> กรองข้อมูล</button>
                <a href="<?= $baseUrl ?>/special-programs/charges" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Charges Table -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-people me-2"></i> รายชื่อนักเรียนและยอดชำระ (<?= count($charges) ?> คน)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>รหัสนักเรียน</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th>ห้องเรียน</th>
                    <th>โครงการ</th>
                    <th class="text-end">ยอดสุทธิ (บาท)</th>
                    <th class="text-end">ชำระแล้ว (บาท)</th>
                    <th class="text-end">คงค้าง (บาท)</th>
                    <th>สถานะ</th>
                    <th class="text-end">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($charges)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">ไม่พบข้อมูลการตั้งหนี้ค่าบำรุงการศึกษา</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $statusBadges = [
                        'unpaid'  => ['bg-danger', 'ค้างชำระ'],
                        'partial' => ['bg-warning text-dark', 'ชำระบางส่วน'],
                        'paid'    => ['bg-success', 'ชำระครบแล้ว'],
                        'waived'  => ['bg-secondary', 'ยกเว้น/ทุนการศึกษา']
                    ];
                    foreach ($charges as $c): 
                        [$badgeClass, $badgeText] = $statusBadges[$c['status']] ?? ['bg-secondary', $c['status']];
                        $due = max(0.00, (float)$c['net_amount'] - (float)$c['paid_amount']);
                    ?>
                        <tr>
                            <td><code><?= e($c['student_id']) ?></code></td>
                            <td class="fw-semibold"><?= e($c['student_name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($c['classroom']) ?></span></td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($c['program_code']) ?></span></td>
                            <td class="text-end fw-semibold"><?= money_format_th($c['net_amount']) ?></td>
                            <td class="text-end text-success fw-semibold"><?= money_format_th($c['paid_amount']) ?></td>
                            <td class="text-end fw-bold <?= $due > 0 ? 'text-danger' : 'text-muted' ?>"><?= money_format_th($due) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span></td>
                            <td class="text-end">
                                <?php if ($c['status'] !== 'paid' && $c['status'] !== 'waived'): ?>
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payModal_<?= $c['id'] ?>" title="รับชำระเงิน">
                                        <i class="bi bi-cash me-1"></i> รับชำระ
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#waiveModal_<?= $c['id'] ?>" title="ยกเว้น/ทุน">
                                        <i class="bi bi-gift"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($c['receipt_id']): ?>
                                    <a href="<?= $baseUrl ?>/receipts/show/<?= $c['receipt_id'] ?>" class="btn btn-sm btn-outline-primary" title="ดูใบเสร็จรับเงิน">
                                        <i class="bi bi-printer"></i> ใบเสร็จ
                                    </a>
                                <?php endif; ?>

                                <!-- Modal: Pay Fee -->
                                <div class="modal fade" id="payModal_<?= $c['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST" action="<?= $baseUrl ?>/special-programs/pay">
                                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="charge_id" value="<?= $c['id'] ?>">
                                            <div class="modal-content text-start">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold text-success"><i class="bi bi-cash me-2"></i> รับชำระค่าบำรุงการศึกษา</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-light border small">
                                                        นักเรียน: <strong><?= e($c['student_name']) ?></strong> (ห้อง <?= e($c['classroom']) ?>)<br>
                                                        ยอดค้างชำระ: <strong class="text-danger"><?= money_format_th($due) ?> บาท</strong>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">จำนวนเงินที่ชำระ (บาท) <span class="text-danger">*</span></label>
                                                        <input type="number" step="0.01" min="1" max="<?= $due ?>" name="paid_amount" class="form-control" value="<?= $due ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">วิธีรับชำระ</label>
                                                        <select name="payment_method" class="form-select">
                                                            <option value="cash">เงินสด</option>
                                                            <option value="transfer">เงินโอนเข้าบัญชีธนาคาร</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">เข้าบัญชีธนาคาร (กรณีโอนเงิน)</label>
                                                        <select name="bank_account_id" class="form-select">
                                                            <option value="">-- ไม่ระบุ (เงินสด) --</option>
                                                            <?php foreach ($bankAccounts as $ba): ?>
                                                                <option value="<?= $ba['id'] ?>"><?= e($ba['bank_name']) ?> (<?= e($ba['account_number']) ?>)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                                    <button type="submit" class="btn btn-success">ยืนยันรับชำระและออกใบเสร็จ</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Modal: Waive Fee -->
                                <div class="modal fade" id="waiveModal_<?= $c['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST" action="<?= $baseUrl ?>/special-programs/waive">
                                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="charge_id" value="<?= $c['id'] ?>">
                                            <div class="modal-content text-start">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold"><i class="bi bi-gift me-2"></i> ยกเว้น / มอบทุนการศึกษา</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-light border small">
                                                        นักเรียน: <strong><?= e($c['student_name']) ?></strong> (ห้อง <?= e($c['classroom']) ?>)
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">จำนวนเงินที่ยกเว้น (บาท) <span class="text-danger">*</span></label>
                                                        <input type="number" step="0.01" min="1" max="<?= $due ?>" name="waived_amount" class="form-control" value="<?= $due ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">เหตุผลการยกเว้น <span class="text-danger">*</span></label>
                                                        <input type="text" name="waive_reason" class="form-control" placeholder="เช่น ได้รับทุนนักเรียนยากจนพิเศษ, บุตรผู้เสียสละ" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                                    <button type="submit" class="btn btn-primary-gov">บันทึกการยกเว้น</button>
                                                </div>
                                            </div>
                                        </form>
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
