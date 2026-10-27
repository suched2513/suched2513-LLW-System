<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ระบบจัดซื้อจัดจ้างและการตรวจรับพัสดุ</h4>
        <div class="text-muted small">ขอซื้อขอจ้าง (PR) ใบสั่งซื้อสั่งจ้าง (PO) การตรวจรับพัสดุ และทะเบียนผู้ค้า</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newSupplierModal">
            <i class="bi bi-shop me-1"></i> เพิ่มผู้ค้า/คู่สัญญา
        </button>
        <button type="button" class="btn btn-primary-gov btn-sm" data-bs-toggle="modal" data-bs-target="#newPrModal">
            <i class="bi bi-file-earmark-plus me-1"></i> สร้างใบขอซื้อขอจ้าง (PR)
        </button>
    </div>
</div>

<div class="row">
    <!-- Purchase Requests (PR) -->
    <div class="col-lg-7 mb-4">
        <div class="card card-gov h-100">
            <div class="card-header">
                <span><i class="bi bi-file-earmark-text me-2"></i> รายการขอซื้อขอจ้าง (PR)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-gov table-hover mb-0">
                    <thead>
                        <tr>
                            <th>เลขที่ PR</th>
                            <th>เรื่อง / โครงการ</th>
                            <th class="text-end">วงเงินงบประมาณ (บาท)</th>
                            <th>สถานะ</th>
                            <th class="text-end">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">ไม่พบรายการขอซื้อขอจ้าง</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $pr): ?>
                                <tr>
                                    <td class="fw-bold"><code><?= e($pr['pr_no']) ?></code></td>
                                    <td>
                                        <div class="fw-semibold text-truncate" style="max-width: 250px;"><?= e($pr['subject']) ?></div>
                                        <small class="text-muted"><?= e($pr['project_name']) ?></small>
                                    </td>
                                    <td class="text-end fw-bold text-navy"><?= money_format_th($pr['estimated_amount']) ?></td>
                                    <td>
                                        <?php if ($pr['status'] === 'draft'): ?>
                                            <span class="badge bg-secondary">ร่างคำขอ</span>
                                        <?php elseif ($pr['status'] === 'approved'): ?>
                                            <span class="badge bg-info text-dark">อนุมัติแล้ว (รอออก PO)</span>
                                        <?php elseif ($pr['status'] === 'ordered'): ?>
                                            <span class="badge bg-success">ออก PO แล้ว</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($pr['status'] === 'draft'): ?>
                                            <form method="POST" action="<?= $baseUrl ?>/procurement/approve-pr" class="d-inline">
                                                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="pr_id" value="<?= $pr['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" data-confirm="ยืนยันการอนุมัติและผูกพันงบประมาณสำหรับ PR นี้?">
                                                    <i class="bi bi-check2"></i> อนุมัติ
                                                </button>
                                            </form>
                                        <?php elseif ($pr['status'] === 'approved'): ?>
                                            <button type="button" class="btn btn-sm btn-primary-gov" data-bs-toggle="modal" data-bs-target="#newPoModal_<?= $pr['id'] ?>">
                                                <i class="bi bi-cart-plus me-1"></i> ออก PO
                                            </button>

                                            <!-- Modal: Create PO -->
                                            <div class="modal fade" id="newPoModal_<?= $pr['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <form method="POST" action="<?= $baseUrl ?>/procurement/orders">
                                                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                                        <input type="hidden" name="pr_id" value="<?= $pr['id'] ?>">
                                                        <div class="modal-content text-start">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title fw-bold"><i class="bi bi-cart-plus me-2"></i> ออกใบสั่งซื้อ/สั่งจ้าง (PO)</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-semibold">อ้างอิง PR</label>
                                                                    <input type="text" class="form-control" value="<?= e($pr['pr_no']) ?>: <?= e($pr['subject']) ?>" readonly>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-semibold">เลือกผู้ค้า / ร้านค้าคู่สัญญา <span class="text-danger">*</span></label>
                                                                    <select name="supplier_id" class="form-select" required>
                                                                        <option value="">-- เลือกผู้ค้า --</option>
                                                                        <?php foreach ($suppliers as $sup): ?>
                                                                            <option value="<?= $sup['id'] ?>"><?= e($sup['name']) ?> (Tax ID: <?= e($sup['tax_id']) ?>)</option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-semibold">ยอดเงินตามสัญญา/ใบสั่งซื้อ (บาท) <span class="text-danger">*</span></label>
                                                                    <input type="number" step="0.01" min="1" name="total_amount" class="form-control" value="<?= $pr['estimated_amount'] ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-semibold">กำหนดวันส่งมอบพัสดุ</label>
                                                                    <input type="date" name="delivery_due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+15 days')) ?>">
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                                <button type="submit" class="btn btn-primary-gov">ออกใบสั่งซื้อ (PO)</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Purchase Orders & Inspections -->
    <div class="col-lg-5 mb-4">
        <div class="card card-gov h-100">
            <div class="card-header">
                <span><i class="bi bi-cart-check me-2"></i> ใบสั่งซื้อ/สั่งจ้าง (PO) และการตรวจรับ</span>
            </div>
            <div class="table-responsive">
                <table class="table table-gov table-hover mb-0">
                    <thead>
                        <tr>
                            <th>เลขที่ PO</th>
                            <th>ผู้ค้า</th>
                            <th class="text-end">จำนวนเงิน</th>
                            <th>การตรวจรับ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">ไม่พบใบสั่งซื้อสั่งจ้าง</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $po): ?>
                                <tr>
                                    <td class="fw-bold"><code><?= e($po['po_no']) ?></code></td>
                                    <td><?= e($po['supplier_name']) ?></td>
                                    <td class="text-end fw-bold text-success"><?= money_format_th($po['total_amount']) ?></td>
                                    <td>
                                        <?php if ($po['status'] === 'completed'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> ตรวจรับแล้ว</span>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#inspectModal_<?= $po['id'] ?>">
                                                <i class="bi bi-clipboard-check me-1"></i> ตรวจรับ
                                            </button>

                                            <!-- Modal: Inspection -->
                                            <div class="modal fade" id="inspectModal_<?= $po['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <form method="POST" action="<?= $baseUrl ?>/procurement/inspections">
                                                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                                        <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                                                        <div class="modal-content text-start">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title fw-bold"><i class="bi bi-clipboard-check me-2"></i> บันทึกผลการตรวจรับพัสดุ</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-semibold">ผลการตรวจรับพัสดุ <span class="text-danger">*</span></label>
                                                                    <select name="result" class="form-select" required>
                                                                        <option value="accepted">ผ่านการตรวจรับ (ครบถ้วนถูกต้องตามสัญญา)</option>
                                                                        <option value="rejected">ไม่ผ่านการตรวจรับ (ชำรุด/ไม่ตรงสเปก)</option>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-semibold">ความเห็นคณะกรรมการตรวจรับ</label>
                                                                    <textarea name="remarks" class="form-control" rows="3" placeholder="ระบุผลการตรวจนับ จำนวน และคุณภาพ"></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                                <button type="submit" class="btn btn-success">บันทึกตรวจรับพัสดุ</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
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

<!-- Modal: New PR -->
<div class="modal fade" id="newPrModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/procurement/requests">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus me-2"></i> สร้างใบขอซื้อขอจ้าง (PR)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เรื่องขอซื้อขอจ้าง <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" placeholder="เช่น ขอซื้อวัสดุอุปกรณ์สำหรับการจัดการเรียนการสอนคอมพิวเตอร์" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">หมวดงบประมาณตามโครงการ <span class="text-danger">*</span></label>
                        <select name="budget_id" class="form-select" required>
                            <option value="">-- เลือกโครงการ --</option>
                            <?php foreach ($availableBudgets as $ab): ?>
                                <option value="<?= $ab['id'] ?>">
                                    <?= e($ab['project_code']) ?>: <?= e($ab['project_name']) ?> (คงเหลือ: <?= money_format_th($ab['remaining']) ?> บาท)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">วิธีจัดซื้อจัดจ้าง</label>
                            <select name="procurement_method" class="form-select">
                                <option value="specific">เฉพาะเจาะจง (มาตรา 56)</option>
                                <option value="price_agreement">ตกลงราคา</option>
                                <option value="bidding">e-Bidding</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">วงเงินประมาณการ (บาท) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="estimated_amount" class="form-control" required placeholder="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gov">บันทึกใบขอซื้อขอจ้าง</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: New Supplier -->
<div class="modal fade" id="newSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/procurement/suppliers">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-shop me-2"></i> เพิ่มผู้ค้า / ร้านค้าคู่สัญญา</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อร้านค้า / บริษัท / ผู้ประกอบการ <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="เช่น หจก. ศรีสะเกษวิศวกรรม">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลขประจำตัวผู้เสียภาษี (13 หลัก) <span class="text-danger">*</span></label>
                        <input type="text" name="tax_id" class="form-control" required placeholder="0994000xxxxxx">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ผู้ติดต่อ</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ที่อยู่</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gov">บันทึกผู้ค้า</button>
                </div>
            </div>
        </form>
    </div>
</div>
