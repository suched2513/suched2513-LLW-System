<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ระบบห้องเรียนพิเศษและค่าบำรุงการศึกษา</h4>
        <div class="text-muted small">บริหารจัดการโครงการพิเศษ (MEP, SMTE, ICT, ทวิศึกษา) อัตราค่าธรรมเนียม และการจัดเก็บรายได้</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/special-programs/rates" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-tag me-1"></i> กำหนดอัตราค่าบำรุง
        </a>
        <a href="<?= $baseUrl ?>/special-programs/create-billing" class="btn btn-primary-gov btn-sm">
            <i class="bi bi-person-plus-fill me-1"></i> ตั้งหนี้รายห้องเรียน
        </a>
        <a href="<?= $baseUrl ?>/special-programs/charges" class="btn btn-gold btn-sm">
            <i class="bi bi-cash-coin me-1"></i> ทะเบียนรับชำระเงิน
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label">นักเรียนที่ตั้งหนี้ทั้งหมด</div>
            <div class="stat-value text-primary"><?= number_format((float)($stats['total_students'] ?? 0)) ?> <span class="fs-6 fw-normal text-muted">คน</span></div>
            <div class="small text-muted mt-2">ทุกระดับชั้นและโครงการ</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-gold">
            <div class="stat-label">ยอดที่ต้องจัดเก็บสุทธิ</div>
            <div class="stat-value text-warning-emphasis"><?= money_format_th($stats['total_charged']) ?> <span class="fs-6 fw-normal text-muted">บาท</span></div>
            <div class="small text-muted mt-2">รวมค่าบำรุงการศึกษา</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-success">
            <div class="stat-label">จัดเก็บได้แล้วจริง</div>
            <div class="stat-value text-success"><?= money_format_th($stats['total_collected']) ?> <span class="fs-6 fw-normal text-muted">บาท</span></div>
            <div class="small text-muted mt-2">คิดเป็น <?= $collectionRate ?>%</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label">ยกเว้น / ทุนการศึกษา</div>
            <div class="stat-value text-muted"><?= money_format_th($stats['total_waived']) ?> <span class="fs-6 fw-normal text-muted">บาท</span></div>
            <div class="small text-muted mt-2">ช่วยเหลือนักเรียนยากจน</div>
        </div>
    </div>
</div>

<!-- Special Programs List -->
<h5 class="fw-bold mb-3 text-navy"><i class="bi bi-mortarboard me-2"></i> โครงการห้องเรียนพิเศษที่เปิดสอนในโรงเรียน</h5>
<div class="row g-3">
    <?php foreach ($programs as $prog): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card card-gov h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-primary fs-6 px-3 py-1"><?= e($prog['program_code']) ?></span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">เปิดสอนปกติ</span>
                    </div>
                    <h5 class="fw-bold mb-1 text-dark"><?= e($prog['program_name']) ?></h5>
                    <p class="text-muted small mb-3"><?= e($prog['description'] ?? 'โครงการส่งเสริมศักยภาพความเป็นเลิศของผู้เรียน') ?></p>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="<?= $baseUrl ?>/special-programs/rates" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-tag"></i> อัตราค่าบำรุง
                        </a>
                        <a href="<?= $baseUrl ?>/special-programs/charges" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-people"></i> รายชื่อนักเรียน
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
