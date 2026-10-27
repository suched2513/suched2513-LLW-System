<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ระบบพัสดุ ทะเบียนคุมครุภัณฑ์ และค่าเสื่อมราคา</h4>
        <div class="text-muted small">ทะเบียนครุภัณฑ์ การคำนวณค่าเสื่อมราคาแบบเส้นตรง (Straight-Line) และคลังวัสดุสิ้นเปลือง</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#stockMovementModal">
            <i class="bi bi-arrow-left-right me-1"></i> รับเข้า / เบิกจ่ายวัสดุ
        </button>
        <button type="button" class="btn btn-primary-gov btn-sm" data-bs-toggle="modal" data-bs-target="#newAssetModal">
            <i class="bi bi-laptop me-1"></i> ขึ้นทะเบียนครุภัณฑ์ใหม่
        </button>
    </div>
</div>

<!-- 1. Fixed Assets Registry -->
<div class="card card-gov mb-4">
    <div class="card-header">
        <span><i class="bi bi-laptop me-2"></i> ทะเบียนคุมครุภัณฑ์และค่าเสื่อมราคาสะสม (<?= count($assets) ?> รายการ)</span>
        <span class="small text-muted">สูตรเส้นตรง: (ราคาทุน - 1.00 บาท) / อายุการใช้งาน</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>เลขรหัสครุภัณฑ์</th>
                    <th>รายการครุภัณฑ์</th>
                    <th>วันที่ได้มา</th>
                    <th>อายุ (ปี)</th>
                    <th class="text-end">ราคาทุน (บาท)</th>
                    <th class="text-end">ค่าเสื่อม/ปี (บาท)</th>
                    <th class="text-end">ค่าเสื่อมสะสม (บาท)</th>
                    <th class="text-end">มูลค่าสุทธิ (บาท)</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assets)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">ยังไม่มีการขึ้นทะเบียนครุภัณฑ์ในระบบ</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assets as $ast): ?>
                        <tr>
                            <td class="fw-bold"><code><?= e($ast['asset_code']) ?></code></td>
                            <td>
                                <div class="fw-semibold"><?= e($ast['asset_name']) ?></div>
                                <small class="text-muted">สถานที่: <?= e($ast['current_location'] ?? '-') ?></small>
                            </td>
                            <td><?= thai_date($ast['acquired_date'], false, true) ?></td>
                            <td class="text-center"><?= $ast['useful_life_years'] ?></td>
                            <td class="text-end fw-semibold"><?= money_format_th($ast['cost_amount']) ?></td>
                            <td class="text-end text-muted"><?= money_format_th($ast['annual_depreciation']) ?></td>
                            <td class="text-end text-danger"><?= money_format_th($ast['accumulated_depreciation']) ?></td>
                            <td class="text-end fw-bold text-navy"><?= money_format_th($ast['net_book_value']) ?></td>
                            <td>
                                <?php if ($ast['is_fully_depreciated']): ?>
                                    <span class="badge bg-secondary">หมดอายุใช้งาน (ซาก 1 บ.)</span>
                                <?php else: ?>
                                    <span class="badge bg-success">ใช้งานปกติ</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 2. Consumable Materials Inventory -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-boxes me-2"></i> คลังพัสดุสิ้นเปลืองและวัสดุสำนักงาน (<?= count($inventory) ?> รายการ)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>รหัสพัสดุ</th>
                    <th>ชื่อรายการวัสดุ</th>
                    <th>หมวดหมู่</th>
                    <th>หน่วยนับ</th>
                    <th class="text-end">คงเหลือในคลัง</th>
                    <th class="text-end">เกณฑ์ขั้นต่ำ</th>
                    <th>สถานะสต็อก</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventory)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">ไม่พบรายการวัสดุสิ้นเปลือง</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventory as $inv): 
                        $isLow = (float)$inv['current_stock'] <= (float)$inv['minimum_stock'];
                    ?>
                        <tr>
                            <td class="fw-bold"><code><?= e($inv['item_code']) ?></code></td>
                            <td class="fw-semibold"><?= e($inv['item_name']) ?></td>
                            <td><?= e($inv['category'] ?? '-') ?></td>
                            <td><?= e($inv['unit']) ?></td>
                            <td class="text-end fw-bold fs-6 <?= $isLow ? 'text-danger' : 'text-success' ?>">
                                <?= number_format((float)$inv['current_stock'], 2) ?>
                            </td>
                            <td class="text-end text-muted"><?= number_format((float)$inv['minimum_stock'], 2) ?></td>
                            <td>
                                <?php if ($isLow): ?>
                                    <span class="badge bg-danger">สต็อกใกล้หมด!</span>
                                <?php else: ?>
                                    <span class="badge bg-success">ปกติ</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Asset -->
<div class="modal fade" id="newAssetModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/inventory/assets">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-laptop me-2"></i> ขึ้นทะเบียนครุภัณฑ์ใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลขรหัสครุภัณฑ์ราชการ <span class="text-danger">*</span></label>
                        <input type="text" name="asset_code" class="form-control" placeholder="เช่น 7440-001-0001/69" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อครุภัณฑ์ <span class="text-danger">*</span></label>
                        <input type="text" name="asset_name" class="form-control" placeholder="เช่น เครื่องคอมพิวเตอร์ประมวลผล All-in-One" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">หมวดหมู่ครุภัณฑ์</label>
                            <input type="text" name="category" class="form-control" placeholder="เช่น ครุภัณฑ์คอมพิวเตอร์">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">วันที่ได้มา</label>
                            <input type="date" name="acquired_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ราคาทุน (บาท) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="cost_amount" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">อายุการใช้งาน (ปี)</label>
                            <input type="number" name="useful_life_years" class="form-control" value="5" min="1" max="50">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">สถานที่ตั้ง / ห้องเรียน</label>
                        <input type="text" name="current_location" class="form-control" placeholder="เช่น ห้องปฏิบัติการคอมพิวเตอร์ 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ผู้รับผิดชอบดูแล</label>
                        <select name="custodian_user_id" class="form-select">
                            <option value="">-- ไม่ระบุ --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gov">บันทึกครุภัณฑ์</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Stock Movement -->
<div class="modal fade" id="stockMovementModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/inventory/movements">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i> รับเข้า / เบิกจ่ายวัสดุสิ้นเปลือง</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลือกรายการวัสดุ <span class="text-danger">*</span></label>
                        <select name="inventory_item_id" class="form-select" required>
                            <option value="">-- เลือกวัสดุ --</option>
                            <?php foreach ($inventory as $inv): ?>
                                <option value="<?= $inv['id'] ?>"><?= e($inv['item_code']) ?>: <?= e($inv['item_name']) ?> (คงเหลือ: <?= $inv['current_stock'] ?> <?= e($inv['unit']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ประเภทการเคลื่อนไหว <span class="text-danger">*</span></label>
                        <select name="movement_type" class="form-select" required>
                            <option value="in">รับเข้าคลัง (Stock In)</option>
                            <option value="out">เบิกจ่ายไปใช้งาน (Stock Out)</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">จำนวน <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ราคาต่อหน่วย (กรณีรับเข้า)</label>
                            <input type="number" step="0.01" min="0" name="unit_cost" class="form-control" value="0.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลขที่เอกสารอ้างอิง</label>
                        <input type="text" name="reference_doc" class="form-control" placeholder="เช่น ใบเบิกเลขที่ 01/2570, PO-2570-0001">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gov">บันทึกสต็อก</button>
                </div>
            </div>
        </form>
    </div>
</div>
