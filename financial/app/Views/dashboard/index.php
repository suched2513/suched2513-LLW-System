<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$fyYearBe = $activeFiscalYear['year_be'] ?? thai_fiscal_year();

// คำนวณยอดเตือนทั้งหมด
$overdueCount = $overdueAdvancesCount ?? 0;
$clearingCount = $pendingClearingCount ?? 0;
$totalAlerts = $pendingChecks + $pendingApprovals + $overdueCount + $clearingCount;

// สัดส่วนกราฟงบประมาณ
$spentPct = (float)($budgetSummary['percent_spent'] ?? 0);
$alloc = (float)($budgetSummary['total_allocated'] ?? 0);
$spent = (float)($budgetSummary['total_spent'] ?? 0);
$comm = (float)($budgetSummary['total_committed'] ?? 0);
$rem = max(0, (float)($budgetSummary['total_remaining'] ?? 0));
?>

<!-- Google Fonts: Prompt -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .fin-dashboard {
        font-family: 'Prompt', -apple-system, BlinkMacSystemFont, sans-serif;
        color: #1e293b;
    }

    /* Top Header */
    .fin-header-card {
        background: #ffffff;
        border-radius: 1.25rem;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 4px 18px 0 rgba(226, 232, 240, 0.6);
        border: 1px solid #f1f5f9;
        margin-bottom: 1.5rem;
    }

    .fin-brand-bubble {
        width: 52px;
        height: 52px;
        border-radius: 1rem;
        background: linear-gradient(135deg, #0b2545 0%, #1e3a8a 50%, #c59b27 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 16px -4px rgba(11, 37, 69, 0.25);
        flex-shrink: 0;
    }

    .live-clock-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #334155;
        padding: 0.45rem 0.9rem;
        border-radius: 0.85rem;
        font-size: 0.82rem;
        font-weight: 600;
    }

    /* Pulsing Alert System (กระพริบเตือน) */
    @keyframes pulse-glow-danger {
        0%, 100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.45);
            border-color: #f87171;
        }
        50% {
            box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            border-color: #ef4444;
        }
    }

    @keyframes pulse-beacon {
        0%, 100% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.2);
            opacity: 0.4;
        }
    }

    @keyframes blink-badge-fast {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.25;
        }
    }

    .pulse-alert-box {
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #fff1f2 0%, #fef2f2 100%);
        border: 2px solid #f87171;
        animation: pulse-glow-danger 2.2s infinite;
        padding: 1rem 1.35rem;
        margin-bottom: 1.5rem;
    }

    .pulse-alert-ok {
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
        border: 1px solid #bbf7d0;
        padding: 0.85rem 1.35rem;
        margin-bottom: 1.5rem;
    }

    .beacon-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: #ef4444;
        display: inline-block;
        animation: pulse-beacon 1.3s infinite ease-in-out;
        box-shadow: 0 0 10px #ef4444;
    }

    .badge-blink-danger {
        background-color: #ef4444 !important;
        color: #ffffff !important;
        animation: blink-badge-fast 1.2s infinite ease-in-out;
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    }

    .badge-blink-warning {
        background-color: #f59e0b !important;
        color: #ffffff !important;
        animation: blink-badge-fast 1.6s infinite ease-in-out;
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
    }

    /* 6 Vibrant KPI Gradient Cards (สไตล์คลังสื่อ) */
    .fin-kpi-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    @media (min-width: 768px) {
        .fin-kpi-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (min-width: 1200px) {
        .fin-kpi-grid { grid-template-columns: repeat(6, 1fr); }
    }

    .kpi-card {
        border-radius: 1.25rem;
        padding: 1.15rem;
        color: #ffffff;
        position: relative;
        overflow: hidden;
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 155px;
    }
    .kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 28px -6px rgba(0, 0, 0, 0.22);
    }

    .kpi-bubble {
        width: 42px;
        height: 42px;
        border-radius: 0.85rem;
        background: rgba(255, 255, 255, 0.22);
        backdrop-filter: blur(6px);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .kpi-card-1 { background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%); box-shadow: 0 10px 20px -5px rgba(29, 78, 216, 0.35); }
    .kpi-card-2 { background: linear-gradient(135deg, #e11d48 0%, #be123c 100%); box-shadow: 0 10px 20px -5px rgba(225, 29, 72, 0.35); }
    .kpi-card-3 { background: linear-gradient(135deg, #d97706 0%, #c2410c 100%); box-shadow: 0 10px 20px -5px rgba(217, 119, 6, 0.35); }
    .kpi-card-4 { background: linear-gradient(135deg, #059669 0%, #0f766e 100%); box-shadow: 0 10px 20px -5px rgba(5, 150, 105, 0.35); }
    .kpi-card-5 { background: linear-gradient(135deg, #7c3aed 0%, #4338ca 100%); box-shadow: 0 10px 20px -5px rgba(124, 58, 237, 0.35); }
    .kpi-card-6 { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); box-shadow: 0 10px 20px -5px rgba(2, 132, 199, 0.35); }

    .kpi-num {
        font-size: 1.45rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        line-height: 1.2;
    }
    @media (max-width: 576px) {
        .kpi-num { font-size: 1.2rem; }
    }

    .kpi-progress-bg {
        width: 100%;
        height: 5px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 9999px;
        overflow: hidden;
        margin-top: 0.5rem;
    }
    .kpi-progress-bar {
        height: 100%;
        background: #ffffff;
        border-radius: 9999px;
    }

    /* Content Cards */
    .fin-content-card {
        background: #ffffff;
        border-radius: 1.25rem;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 18px 0 rgba(226, 232, 240, 0.6);
        padding: 1.25rem 1.5rem;
        height: 100%;
    }

    /* Quick Module Navigation Cards */
    .fin-mod-card {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 1.15rem;
        padding: 1rem 1.15rem;
        display: flex;
        align-items: center;
        gap: 0.9rem;
        text-decoration: none !important;
        transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        height: 100%;
    }
    .fin-mod-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px -4px rgba(11, 37, 69, 0.12);
        border-color: #cbd5e1;
    }

    .fin-mod-icon {
        width: 44px;
        height: 44px;
        border-radius: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }
</style>

<div class="fin-dashboard">

    <!-- 1. Top Header & Real-time Clock Bar (เหมือนคลังสื่อ) -->
    <div class="fin-header-card d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="fin-brand-bubble">
                <i class="bi bi-bank2 fs-3"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h4 class="fw-bold mb-0 text-slate-800" style="color: #0b2545;">แดชบอร์ดบริหารการเงินและงบประมาณ</h4>
                    <span class="badge rounded-pill text-bg-primary px-3 py-1 fw-bold" style="background-color: #0b2545 !important;">
                        ปีงบประมาณ พ.ศ. <?= e($fyYearBe) ?>
                    </span>
                </div>
                <div class="text-muted small mt-1">ศูนย์บริหารจัดการงบประมาณ การเงิน บัญชี และพัสดุ โรงเรียนละลมวิทยา</div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap w-100 w-lg-auto justify-content-between justify-content-lg-end">
            <!-- Live Date & Clock -->
            <div class="live-clock-badge">
                <i class="bi bi-calendar-event text-primary"></i>
                <span id="live_fin_date" class="d-none d-sm-inline">กำลังโหลด...</span>
                <span class="text-muted d-none d-sm-inline">|</span>
                <i class="bi bi-clock-fill text-primary"></i>
                <span id="live_fin_clock" class="font-monospace fw-bold text-dark">--:--:--</span>
            </div>

            <!-- Action Buttons -->
            <a href="<?= $baseUrl ?>/manual" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold shadow-sm text-dark">
                <i class="bi bi-journal-bookmark-fill me-1"></i> คู่มือการใช้งาน
            </a>
            <a href="<?= $baseUrl ?>/reports" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> รายงานการเงิน
            </a>
            <a href="<?= $baseUrl ?>/disbursements" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm" style="background: linear-gradient(135deg, #0b2545, #1e3a8a); border: none;">
                <i class="bi bi-plus-circle me-1"></i> เบิกจ่ายเงิน
            </a>
        </div>
    </div>

    <!-- 2. Pulsing Alert Banner (กระพริบเตือนรายการด่วน) -->
    <?php if ($totalAlerts > 0): ?>
        <div class="pulse-alert-box d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="beacon-dot"></div>
                <div>
                    <div class="fw-bold text-danger d-flex align-items-center gap-2">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <span>มีรายการเร่งด่วนที่ต้องดำเนินการ (รวม <?= number_format($totalAlerts) ?> รายการ)</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-1">
                        <?php if ($pendingChecks > 0): ?>
                            <a href="<?= $baseUrl ?>/disbursements" class="badge badge-blink-warning rounded-pill text-decoration-none px-2.5 py-1">
                                <i class="bi bi-clock-history me-1"></i> คำขอเบิกจ่ายรอตรวจ <?= $pendingChecks ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($pendingApprovals > 0): ?>
                            <a href="<?= $baseUrl ?>/disbursements" class="badge badge-blink-danger rounded-pill text-decoration-none px-2.5 py-1">
                                <i class="bi bi-check-circle me-1"></i> คำขอเบิกจ่ายรออนุมัติ <?= $pendingApprovals ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($overdueCount > 0): ?>
                            <a href="<?= $baseUrl ?>/advances" class="badge badge-blink-danger rounded-pill text-decoration-none px-2.5 py-1">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> สัญญายืมเงินเกินกำหนด <?= $overdueCount ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($clearingCount > 0): ?>
                            <a href="<?= $baseUrl ?>/advances" class="badge bg-secondary rounded-pill text-decoration-none px-2.5 py-1">
                                <i class="bi bi-journal-check me-1"></i> สัญญายืมรอตรวจล้าง <?= $clearingCount ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= $baseUrl ?>/disbursements" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold shadow-sm">
                    จัดการคำขอเบิกจ่าย <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="pulse-alert-ok d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 text-success small fw-semibold">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <span>สถานะการเงินเป็นปกติ ไม่มีคำขอเบิกจ่ายค้างตรวจสอบหรือสัญญายืมเงินเกินกำหนด</span>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">เรียบร้อย 100%</span>
        </div>
    <?php endif; ?>

    <!-- 3. 6 Vibrant Gradient KPI Stat Cards (สไตล์คลังสื่อเป๊ะๆ) -->
    <div class="fin-kpi-grid">
        <!-- 1. งบประมาณจัดสรร -->
        <div class="kpi-card kpi-card-1">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-bubble">
                    <i class="bi bi-wallet2"></i>
                </div>
                <span class="badge bg-white/20 text-white rounded-pill small fw-bold" style="background: rgba(255,255,255,0.2);">จัดสรรรวม</span>
            </div>
            <div class="mt-2">
                <div class="text-white-50 small fw-semibold">งบประมาณที่ได้รับ</div>
                <div class="kpi-num mt-1"><?= money_format_th($budgetSummary['total_allocated']) ?></div>
            </div>
            <div>
                <div class="d-flex justify-content-between text-white-50" style="font-size: 0.72rem;">
                    <span>เป้าหมายปี <?= e($fyYearBe) ?></span>
                    <span class="text-white fw-bold">100%</span>
                </div>
                <div class="kpi-progress-bg">
                    <div class="kpi-progress-bar" style="width: 100%;"></div>
                </div>
            </div>
        </div>

        <!-- 2. เบิกจ่ายจริงแล้ว -->
        <div class="kpi-card kpi-card-2">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-bubble">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <span class="badge bg-white/20 text-white rounded-pill small fw-bold" style="background: rgba(255,255,255,0.2);">เบิกจ่าย</span>
            </div>
            <div class="mt-2">
                <div class="text-white-50 small fw-semibold">เบิกจ่ายจริงแล้ว</div>
                <div class="kpi-num mt-1"><?= money_format_th($budgetSummary['total_spent']) ?></div>
            </div>
            <div>
                <div class="d-flex justify-content-between text-white-50" style="font-size: 0.72rem;">
                    <span>ใช้ไปแล้ว</span>
                    <span class="text-white fw-bold"><?= $budgetSummary['percent_spent'] ?>%</span>
                </div>
                <div class="kpi-progress-bg">
                    <div class="kpi-progress-bar" style="width: <?= min(100, $spentPct) ?>%;"></div>
                </div>
            </div>
        </div>

        <!-- 3. ผูกพันรอจ่าย -->
        <div class="kpi-card kpi-card-3">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-bubble">
                    <i class="bi bi-cart-check"></i>
                </div>
                <span class="badge bg-white/20 text-white rounded-pill small fw-bold" style="background: rgba(255,255,255,0.2);">PO/ผูกพัน</span>
            </div>
            <div class="mt-2">
                <div class="text-white-50 small fw-semibold">ยอดผูกพันรอจ่าย</div>
                <div class="kpi-num mt-1"><?= money_format_th($budgetSummary['total_committed']) ?></div>
            </div>
            <div>
                <div class="d-flex justify-content-between text-white-50" style="font-size: 0.72rem;">
                    <span>สัญญา/ใบสั่งซื้อ</span>
                    <span class="text-white fw-bold"><?= $alloc > 0 ? round(($comm / $alloc) * 100, 1) : 0 ?>%</span>
                </div>
                <div class="kpi-progress-bg">
                    <div class="kpi-progress-bar" style="width: <?= $alloc > 0 ? min(100, round(($comm / $alloc) * 100, 1)) : 0 ?>%;"></div>
                </div>
            </div>
        </div>

        <!-- 4. งบประมาณคงเหลือสุทธิ -->
        <div class="kpi-card kpi-card-4">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-bubble">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <span class="badge bg-white/20 text-white rounded-pill small fw-bold" style="background: rgba(255,255,255,0.2);">คงเหลือ</span>
            </div>
            <div class="mt-2">
                <div class="text-white-50 small fw-semibold">งบคงเหลือสุทธิ</div>
                <div class="kpi-num mt-1"><?= money_format_th($budgetSummary['total_remaining']) ?></div>
            </div>
            <div>
                <div class="d-flex justify-content-between text-white-50" style="font-size: 0.72rem;">
                    <span>พร้อมใช้จ่าย</span>
                    <span class="text-white fw-bold"><?= $alloc > 0 ? round(($rem / $alloc) * 100, 1) : 0 ?>%</span>
                </div>
                <div class="kpi-progress-bg">
                    <div class="kpi-progress-bar" style="width: <?= $alloc > 0 ? min(100, round(($rem / $alloc) * 100, 1)) : 0 ?>%;"></div>
                </div>
            </div>
        </div>

        <!-- 5. บัญชีธนาคารรวม -->
        <div class="kpi-card kpi-card-5">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-bubble">
                    <i class="bi bi-bank"></i>
                </div>
                <span class="badge bg-white/20 text-white rounded-pill small fw-bold" style="background: rgba(255,255,255,0.2);">ธนาคาร</span>
            </div>
            <div class="mt-2">
                <div class="text-white-50 small fw-semibold">เงินฝากธนาคารรวม</div>
                <div class="kpi-num mt-1"><?= money_format_th($totalBankBalance) ?></div>
            </div>
            <div>
                <div class="d-flex justify-content-between text-white-50" style="font-size: 0.72rem;">
                    <span>บัญชีทั้งหมด</span>
                    <span class="text-white fw-bold"><?= count($bankAccounts) ?> บัญชี</span>
                </div>
                <div class="kpi-progress-bg">
                    <div class="kpi-progress-bar" style="width: 100%;"></div>
                </div>
            </div>
        </div>

        <!-- 6. เงินยืมทดรองค้างชำระ -->
        <div class="kpi-card kpi-card-6">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-bubble">
                    <i class="bi bi-journal-arrow-up"></i>
                </div>
                <?php if ($overdueCount > 0): ?>
                    <span class="badge badge-blink-danger rounded-pill small">เกินกำหนด <?= $overdueCount ?></span>
                <?php else: ?>
                    <span class="badge bg-white/20 text-white rounded-pill small fw-bold" style="background: rgba(255,255,255,0.2);">เงินยืม</span>
                <?php endif; ?>
            </div>
            <div class="mt-2">
                <div class="text-white-50 small fw-semibold">เงินยืมค้างล้าง</div>
                <div class="kpi-num mt-1"><?= money_format_th($outstandingAdvancesAmount) ?></div>
            </div>
            <div>
                <div class="d-flex justify-content-between text-white-50" style="font-size: 0.72rem;">
                    <span>สัญญายืมที่ยังไม่ปิด</span>
                    <span class="text-white fw-bold"><?= number_format($outstandingAdvancesCount) ?> สัญญา</span>
                </div>
                <div class="kpi-progress-bg">
                    <div class="kpi-progress-bar" style="width: 100%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Middle Section: Budget Donut Chart & Bank Accounts Table -->
    <div class="row g-3 mb-4">
        <!-- 4.1 Donut Chart สัดส่วนงบประมาณ -->
        <div class="col-lg-5">
            <div class="fin-content-card d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-bold" style="color: #0b2545;">
                        <i class="bi bi-pie-chart-fill text-primary me-2"></i> สัดส่วนการใช้จ่ายงบประมาณ
                    </div>
                    <span class="badge bg-light text-muted border">ปี พ.ศ. <?= e($fyYearBe) ?></span>
                </div>

                <div class="position-relative my-auto" style="min-height: 220px; max-height: 240px;">
                    <canvas id="budgetDonutChart"></canvas>
                </div>

                <div class="d-flex justify-content-around text-center pt-3 mt-2 border-top">
                    <div>
                        <div class="small text-muted">เบิกจ่ายแล้ว</div>
                        <div class="fw-bold text-danger"><?= money_format_th($spent) ?></div>
                    </div>
                    <div>
                        <div class="small text-muted">ผูกพันรอจ่าย</div>
                        <div class="fw-bold text-warning-emphasis"><?= money_format_th($comm) ?></div>
                    </div>
                    <div>
                        <div class="small text-muted">คงเหลือสุทธิ</div>
                        <div class="fw-bold text-success"><?= money_format_th($rem) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4.2 บัญชีเงินฝากธนาคารของสถานศึกษา -->
        <div class="col-lg-7">
            <div class="fin-content-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-bold" style="color: #0b2545;">
                        <i class="bi bi-bank2 text-primary me-2"></i> บัญชีเงินฝากธนาคารของสถานศึกษา
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-bold rounded-pill">
                        รวม <?= money_format_th($totalBankBalance) ?> บาท
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="border-0">ชื่อธนาคาร</th>
                                <th class="border-0">เลขที่บัญชี</th>
                                <th class="border-0">ชื่อบัญชี</th>
                                <th class="border-0 text-end">ยอดคงเหลือ (บาท)</th>
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
                                        <td class="fw-semibold text-dark">
                                            <i class="bi bi-credit-card-2-front text-primary me-1"></i>
                                            <?= e($acc['bank_name']) ?>
                                        </td>
                                        <td><code class="text-dark bg-light px-2 py-1 rounded"><?= e($acc['account_number']) ?></code></td>
                                        <td class="text-secondary"><?= e($acc['account_name']) ?></td>
                                        <td class="text-end fw-bold text-success fs-6"><?= money_format_th($acc['current_balance']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Recent Disbursements Activity (ถ้ามี) -->
    <?php if (!empty($recentDisbursements)): ?>
        <div class="fin-content-card mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="fw-bold" style="color: #0b2545;">
                    <i class="bi bi-clock-history text-primary me-2"></i> คำขอเบิกจ่ายเงินล่าสุด
                </div>
                <a href="<?= $baseUrl ?>/disbursements" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    ดูทั้งหมด <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="border-0">เลขที่เอกสาร</th>
                            <th class="border-0">รายการ / เรื่อง</th>
                            <th class="border-0">ผู้ขอเบิก</th>
                            <th class="border-0 text-end">ยอดเงินสุทธิ</th>
                            <th class="border-0 text-center">สถานะ</th>
                            <th class="border-0 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDisbursements as $item): 
                            $statusBadge = match($item['status']) {
                                'submitted' => '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>รอตรวจสอบ</span>',
                                'verified'  => '<span class="badge bg-info text-dark"><i class="bi bi-check me-1"></i>รออนุมัติ</span>',
                                'approved'  => '<span class="badge bg-primary"><i class="bi bi-check2-all me-1"></i>อนุมัติแล้ว</span>',
                                'paid'      => '<span class="badge bg-success"><i class="bi bi-cash me-1"></i>จ่ายเงินแล้ว</span>',
                                'rejected'  => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>ไม่อนุมัติ</span>',
                                default     => '<span class="badge bg-secondary">' . e($item['status']) . '</span>'
                            };
                        ?>
                            <tr>
                                <td class="fw-semibold"><code><?= e($item['disbursement_no']) ?></code></td>
                                <td class="text-dark fw-medium"><?= e($item['title']) ?></td>
                                <td class="text-secondary"><?= e($item['requester_name'] ?? '-') ?></td>
                                <td class="text-end fw-bold text-dark"><?= money_format_th($item['net_amount']) ?></td>
                                <td class="text-center"><?= $statusBadge ?></td>
                                <td class="text-center">
                                    <a href="<?= $baseUrl ?>/disbursements/<?= $item['id'] ?>" class="btn btn-sm btn-light border rounded-pill px-2.5">
                                        เปิดดู
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- 6. 12 Quick Module Navigation Cards (มิติใหม่ สไตล์คลังสื่อ) -->
    <div class="d-flex align-items-center justify-content-between mb-3 mt-4">
        <h5 class="fw-bold mb-0" style="color: #0b2545;">
            <i class="bi bi-grid-fill text-primary me-2"></i> โมดูลระบบบริหารงานการเงินและบัญชีสถานศึกษา
        </h5>
        <span class="text-muted small">12 โมดูลงานมาตรฐาน</span>
    </div>

    <div class="row g-3">
        <!-- 1. ระบบรับเงิน -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/receipts" class="fin-mod-card">
                <div class="fin-mod-icon bg-primary-subtle text-primary">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">ระบบรับเงิน</div>
                    <small class="text-muted">ออกใบเสร็จ นำฝาก รายรับ</small>
                </div>
            </a>
        </div>

        <!-- 2. เบิกจ่ายเงิน -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/disbursements" class="fin-mod-card">
                <div class="fin-mod-icon bg-success-subtle text-success">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">เบิกจ่ายเงิน</div>
                    <small class="text-muted">เสนอ ตรวจ อนุมัติ จ่าย</small>
                </div>
            </a>
        </div>

        <!-- 3. เงินยืมและล้างยืม -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/advances" class="fin-mod-card">
                <div class="fin-mod-icon bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-journal-arrow-up"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">เงินยืมและล้างยืม</div>
                    <small class="text-muted">สัญญา ทวงถาม คืนเงิน</small>
                </div>
            </a>
        </div>

        <!-- 4. บริหารงบประมาณ -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/budgets" class="fin-mod-card">
                <div class="fin-mod-icon bg-info-subtle text-info-emphasis">
                    <i class="bi bi-pie-chart"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">บริหารงบประมาณ</div>
                    <small class="text-muted">จัดสรร โอนเปลี่ยนแปลง ผูกพัน</small>
                </div>
            </a>
        </div>

        <!-- 5. ทะเบียนเอกสาร ผง. -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/budgets/documents" class="fin-mod-card">
                <div class="fin-mod-icon text-white" style="background: linear-gradient(135deg, #0b2545, #1e3a8a);">
                    <i class="bi bi-file-earmark-ruled"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">ทะเบียนเอกสาร ผง.</div>
                    <small class="text-muted">แบบ ผง. 1/1, 1/2 ราชการ</small>
                </div>
            </a>
        </div>

        <!-- 6. ประเภทการเงิน -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/funding-sources" class="fin-mod-card">
                <div class="fin-mod-icon bg-secondary-subtle text-secondary">
                    <i class="bi bi-tags"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">ประเภทการเงิน</div>
                    <small class="text-muted">งบอุดหนุน เงินรายได้สถานศึกษา</small>
                </div>
            </a>
        </div>

        <!-- 7. ธนาคารและกระทบยอด -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/banks" class="fin-mod-card">
                <div class="fin-mod-icon bg-purple-subtle text-purple" style="background-color: #f3e8ff; color: #7e22ce;">
                    <i class="bi bi-credit-card-2-front"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">ธนาคาร & กระทบยอด</div>
                    <small class="text-muted">Statement ตรวจสอบยอดคงเหลือ</small>
                </div>
            </a>
        </div>

        <!-- 8. บัญชีและสมุดรายวัน -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/accounts" class="fin-mod-card">
                <div class="fin-mod-icon bg-secondary-subtle text-dark">
                    <i class="bi bi-book"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">บัญชีคู่และสมุดรายวัน</div>
                    <small class="text-muted">เดบิต/เครดิต แยกประเภท งบทดลอง</small>
                </div>
            </a>
        </div>

        <!-- 9. จัดซื้อจัดจ้าง -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/procurement" class="fin-mod-card">
                <div class="fin-mod-icon bg-primary-subtle text-primary">
                    <i class="bi bi-cart-check"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">จัดซื้อจัดจ้าง</div>
                    <small class="text-muted">ขอซื้อ ขอจ้าง ใบสั่งซื้อ ตรวจรับ</small>
                </div>
            </a>
        </div>

        <!-- 10. พัสดุและครุภัณฑ์ -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/inventory" class="fin-mod-card">
                <div class="fin-mod-icon bg-success-subtle text-success">
                    <i class="bi bi-boxes"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">พัสดุและครุภัณฑ์</div>
                    <small class="text-muted">ทะเบียนคุม เบิกจ่าย ค่าเสื่อม</small>
                </div>
            </a>
        </div>

        <!-- 11. ค่าบำรุงการศึกษา -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/special-programs" class="fin-mod-card">
                <div class="fin-mod-icon text-warning" style="background-color: #fef3c7; color: #b45309;">
                    <i class="bi bi-mortarboard"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">ค่าบำรุงการศึกษา</div>
                    <small class="text-muted">ห้องเรียนพิเศษ อัตราเรียกเก็บ</small>
                </div>
            </a>
        </div>

        <!-- 12. เอกสารและ 28 รายงาน -->
        <div class="col-sm-6 col-md-4 col-xl-3">
            <a href="<?= $baseUrl ?>/reports" class="fin-mod-card">
                <div class="fin-mod-icon bg-danger-subtle text-danger">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">เอกสารและ 28 รายงาน</div>
                    <small class="text-muted">รายงานการเงิน สตง. & สพม.</small>
                </div>
            </a>
        </div>
    </div>
</div>

<script>
// Live Date & Clock ticking
function updateFinClock() {
    const now = new Date();
    const thaiDays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    const thaiMonths = [
        'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
        'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
    ];

    const dayName = thaiDays[now.getDay()];
    const date = now.getDate();
    const monthName = thaiMonths[now.getMonth()];
    const yearBe = now.getFullYear() + 543;

    const dateStr = `วัน${dayName}ที่ ${date} ${monthName} ${yearBe}`;
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const timeStr = `${hours}:${minutes}:${seconds}`;

    const dateEl = document.getElementById('live_fin_date');
    const clockEl = document.getElementById('live_fin_clock');
    if (dateEl) dateEl.textContent = dateStr;
    if (clockEl) clockEl.textContent = timeStr;
}

setInterval(updateFinClock, 1000);
updateFinClock();

// Chart.js: Budget Donut Chart
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('budgetDonutChart');
    if (!ctx) return;

    const spent = <?= json_encode($spent) ?>;
    const committed = <?= json_encode($comm) ?>;
    const remaining = <?= json_encode($rem) ?>;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['เบิกจ่ายแล้ว', 'ผูกพันรอจ่าย', 'คงเหลือสุทธิ'],
            datasets: [{
                data: [spent, committed, remaining],
                backgroundColor: [
                    '#e11d48', // Rose / Red
                    '#d97706', // Amber / Orange
                    '#059669'  // Emerald / Green
                ],
                borderWidth: 3,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: { family: 'Prompt', size: 12 },
                        padding: 14
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const val = context.raw || 0;
                            return ' ' + context.label + ': ' + Number(val).toLocaleString('th-TH', { minimumFractionDigits: 2 }) + ' บาท';
                        }
                    }
                }
            },
            cutout: '68%'
        }
    });
});
</script>
