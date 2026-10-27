<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$fyYearBe = $activeFiscalYear['year_be'] ?? thai_fiscal_year();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">แดชบอร์ดบริหารการเงินและงบประมาณ</h4>
        <div class="text-muted small">ภาพรวมข้อมูลการเงิน บัญชี และงบประมาณประจำปี พ.ศ. <?= e($fyYearBe) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/reports" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-bar-graph me-1"></i> รายงานการเงิน
        </a>
        <a href="<?= $baseUrl ?>/disbursements" class="btn btn-primary-gov btn-sm">
            <i class="bi bi-plus-circle me-1"></i> สร้างคำขอเบิกจ่าย
        </a>
    </div>
</div>

<!-- Key Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label"><i class="bi bi-wallet2 text-primary me-1"></i> งบประมาณที่ได้รับจัดสรร</div>
            <div class="stat-value text-primary"><?= money_format_th($budgetSummary['total_allocated']) ?> <span class="fs-6 fw-normal text-muted">บาท</span></div>
            <div class="small text-muted mt-2">ปีงบประมาณ พ.ศ. <?= e($fyYearBe) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-gold">
            <div class="stat-label"><i class="bi bi-cart-check text-warning me-1"></i> ยอดผูกพันรอจ่าย</div>
            <div class="stat-value text-warning-emphasis"><?= money_format_th($budgetSummary['total_committed']) ?> <span class="fs-6 fw-normal text-muted">บาท</span></div>
            <div class="small text-muted mt-2">ใบสั่งซื้อ/สัญญาผูกพัน</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-danger">
            <div class="stat-label"><i class="bi bi-cash-coin text-danger me-1"></i> ยอดเบิกจ่ายจริงแล้ว</div>
            <div class="stat-value text-danger"><?= money_format_th($budgetSummary['total_spent']) ?> <span class="fs-6 fw-normal text-muted">บาท</span></div>
            <div class="small text-muted mt-2">ใช้ไปแล้ว <?= $budgetSummary['percent_spent'] ?>%</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-success">
            <div class="stat-label"><i class="bi bi-check2-circle text-success me-1"></i> งบประมาณคงเหลือสุทธิ</div>
            <div class="stat-value text-success"><?= money_format_th($budgetSummary['total_remaining']) ?> <span class="fs-6 fw-normal text-muted">บาท</span></div>
            <div class="small text-muted mt-2">พร้อมสำหรับการเบิกจ่าย</div>
        </div>
    </div>
</div>

<!-- Pending Action Badges & Bank Accounts -->
<div class="row g-3 mb-4">
    <!-- Pending Workflow Actions -->
    <div class="col-lg-5">
        <div class="card card-gov h-100">
            <div class="card-header">
                <span><i class="bi bi-bell me-2"></i> รายการที่ต้องดำเนินการ</span>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <div>
                            <i class="bi bi-clock-history text-warning me-2 fs-5"></i>
                            <span class="fw-semibold">คำขอเบิกจ่ายรอตรวจสอบ</span>
                        </div>
                        <div>
                            <span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill"><?= $pendingChecks ?></span>
                            <a href="<?= $baseUrl ?>/disbursements" class="btn btn-sm btn-outline-secondary ms-2">ตรวจ</a>
                        </div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <div>
                            <i class="bi bi-check-circle text-primary me-2 fs-5"></i>
                            <span class="fw-semibold">คำขอเบิกจ่ายรออนุมัติ</span>
                        </div>
                        <div>
                            <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill"><?= $pendingApprovals ?></span>
                            <a href="<?= $baseUrl ?>/disbursements" class="btn btn-sm btn-outline-secondary ms-2">อนุมัติ</a>
                        </div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <div>
                            <i class="bi bi-exclamation-circle text-danger me-2 fs-5"></i>
                            <span class="fw-semibold">สัญญายืมเงินค้างล้าง</span>
                        </div>
                        <div>
                            <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill"><?= $outstandingAdvancesCount ?></span>
                            <a href="<?= $baseUrl ?>/advances" class="btn btn-sm btn-outline-secondary ms-2">ดู</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Bank Accounts Summary -->
    <div class="col-lg-7">
        <div class="card card-gov h-100">
            <div class="card-header">
                <span><i class="bi bi-bank me-2"></i> บัญชีเงินฝากธนาคารของสถานศึกษา</span>
                <span class="badge bg-light text-primary border">ยอดรวม: <?= money_format_th($totalBankBalance) ?> บาท</span>
            </div>
            <div class="table-responsive">
                <table class="table table-gov table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ชื่อธนาคาร</th>
                            <th>เลขที่บัญชี</th>
                            <th>ชื่อบัญชี</th>
                            <th class="text-end">ยอดเงินคงเหลือ (บาท)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bankAccounts)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">ยังไม่มีข้อมูลบัญชีธนาคารในระบบ</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bankAccounts as $acc): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($acc['bank_name']) ?></td>
                                    <td><code><?= e($acc['account_number']) ?></code></td>
                                    <td><?= e($acc['account_name']) ?></td>
                                    <td class="text-end fw-bold text-success"><?= money_format_th($acc['current_balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Quick Navigation to 13 Modules -->
<h5 class="fw-bold mb-3" style="color: #0B2545;"><i class="bi bi-grid-fill me-2"></i> โมดูลระบบบริหารงานการเงินและบัญชี</h5>
<div class="row g-3">
    <div class="col-sm-6 col-md-4 col-xl-3">
        <a href="<?= $baseUrl ?>/receipts" class="card card-gov text-decoration-none h-100 p-3 hover-shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded bg-primary-subtle text-primary fs-3"><i class="bi bi-wallet2"></i></div>
                <div>
                    <div class="fw-bold text-dark">ระบบรับเงิน</div>
                    <small class="text-muted">ออกใบเสร็จ นำฝาก รายรับ</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-3">
        <a href="<?= $baseUrl ?>/disbursements" class="card card-gov text-decoration-none h-100 p-3 hover-shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded bg-success-subtle text-success fs-3"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="fw-bold text-dark">เบิกจ่ายเงิน</div>
                    <small class="text-muted">เสนอ ตรวจ อนุมัติ จ่าย</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-3">
        <a href="<?= $baseUrl ?>/advances" class="card card-gov text-decoration-none h-100 p-3 hover-shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded bg-warning-subtle text-warning-emphasis fs-3"><i class="bi bi-journal-arrow-up"></i></div>
                <div>
                    <div class="fw-bold text-dark">เงินยืมและล้างยืม</div>
                    <small class="text-muted">สัญญา ทวงถาม คืนเงิน</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-3">
        <a href="<?= $baseUrl ?>/budgets" class="card card-gov text-decoration-none h-100 p-3 hover-shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded bg-info-subtle text-info-emphasis fs-3"><i class="bi bi-pie-chart"></i></div>
                <div>
                    <div class="fw-bold text-dark">บริหารงบประมาณ</div>
                    <small class="text-muted">จัดสรร โอนเปลี่ยนแปลง ผูกพัน</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-3">
        <a href="<?= $baseUrl ?>/accounts" class="card card-gov text-decoration-none h-100 p-3 hover-shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded bg-secondary-subtle text-secondary fs-3"><i class="bi bi-book"></i></div>
                <div>
                    <div class="fw-bold text-dark">บัญชีคู่และสมุดรายวัน</div>
                    <small class="text-muted">เดบิต/เครดิต แยกประเภท งบทดลอง</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-3">
        <a href="<?= $baseUrl ?>/procurement" class="card card-gov text-decoration-none h-100 p-3 hover-shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded bg-primary-subtle text-primary fs-3"><i class="bi bi-cart-check"></i></div>
                <div>
                    <div class="fw-bold text-dark">จัดซื้อจัดจ้าง</div>
                    <small class="text-muted">ขอซื้อ ขอจ้าง ใบสั่งซื้อ ตรวจรับ</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-3">
        <a href="<?= $baseUrl ?>/inventory" class="card card-gov text-decoration-none h-100 p-3 hover-shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded bg-success-subtle text-success fs-3"><i class="bi bi-boxes"></i></div>
                <div>
                    <div class="fw-bold text-dark">พัสดุและครุภัณฑ์</div>
                    <small class="text-muted">ทะเบียนคุม เบิกจ่าย ค่าเสื่อม</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-3">
        <a href="<?= $baseUrl ?>/reports" class="card card-gov text-decoration-none h-100 p-3 hover-shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded bg-danger-subtle text-danger fs-3"><i class="bi bi-file-earmark-bar-graph"></i></div>
                <div>
                    <div class="fw-bold text-dark">เอกสารและ 28 รายงาน</div>
                    <small class="text-muted">PDF ฟอนต์ไทย & Excel</small>
                </div>
            </div>
        </a>
    </div>
</div>
