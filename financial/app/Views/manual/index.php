<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>

<!-- Google Fonts: Prompt -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
    .fin-manual {
        font-family: 'Prompt', -apple-system, BlinkMacSystemFont, sans-serif;
        color: #1e293b;
    }

    .manual-hero {
        background: linear-gradient(135deg, #0b2545 0%, #1e3a8a 60%, #c59b27 100%);
        border-radius: 1.5rem;
        padding: 2rem 2.25rem;
        color: #ffffff;
        box-shadow: 0 10px 25px -5px rgba(11, 37, 69, 0.3);
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .manual-hero::after {
        content: '\F425';
        font-family: 'bootstrap-icons';
        position: absolute;
        right: -15px;
        bottom: -25px;
        font-size: 11rem;
        opacity: 0.08;
        pointer-events: none;
    }

    .step-card {
        background: #ffffff;
        border-radius: 1.25rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 18px 0 rgba(226, 232, 240, 0.6);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .step-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
    }

    .step-badge-num {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.15rem;
        color: #ffffff;
        flex-shrink: 0;
    }

    .flow-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.85rem;
        border-radius: 9999px;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .wf-arrow {
        color: #94a3b8;
        font-size: 1.25rem;
        margin: 0 0.5rem;
    }

    .quick-table th {
        background-color: #f8fafc;
        font-weight: 700;
        color: #475569;
    }

    @media print {
        #sidebar, .top-navbar, .no-print {
            display: none !important;
        }
        .content-area, .page-body {
            margin: 0 !important;
            padding: 0 !important;
        }
        .manual-hero {
            background: #0b2545 !important;
            color: #ffffff !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<div class="fin-manual">

    <!-- Hero Header -->
    <div class="manual-hero d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
        <div>
            <div class="d-inline-flex align-items-center gap-2 bg-white/20 backdrop-blur-sm px-3 py-1 rounded-pill text-xs fw-bold text-white mb-2" style="background: rgba(255,255,255,0.2);">
                <i class="bi bi-book-half"></i> คู่มือปฏิบัติการทางการเงินและบัญชีสถานศึกษา
            </div>
            <h2 class="fw-black mb-1 text-white">ขั้นตอนการใช้งานระบบบริหารการเงินและบัญชี</h2>
            <div class="text-white-50 small">โรงเรียนละลมวิทยา | เริ่มจากเมนูไหนก่อน เมื่อทำเสร็จแล้วต้องส่งต่อไปที่เมนูใด</div>
        </div>

        <div class="d-flex gap-2 no-print">
            <button type="button" onclick="window.print()" class="btn btn-light btn-sm rounded-pill px-3.5 py-2 fw-bold shadow-sm">
                <i class="bi bi-printer me-1"></i> พิมพ์คู่มือ
            </button>
            <a href="<?= $baseUrl ?>/dashboard" class="btn btn-outline-light btn-sm rounded-pill px-3.5 py-2 fw-semibold">
                <i class="bi bi-speedometer2 me-1"></i> แดชบอร์ด
            </a>
        </div>
    </div>

    <!-- Quick Jump Navigation Tabs -->
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 no-print bg-white">
        <div class="small fw-bold text-muted text-uppercase mb-2">
            <i class="bi bi-compass me-1"></i> นำทางด่วนไปยังหัวข้อ:
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="#section-workflow" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold">
                1. แผนผังภาพรวมวงจรการเงิน
            </a>
            <a href="#section-setup" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold">
                2. การตั้งค่าต้นปี (เริ่มจากไหน)
            </a>
            <a href="#section-ops" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold">
                3. วงจรงานประจำวัน (3 วงจรหลัก)
            </a>
            <a href="#section-assets" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold">
                4. งานพัสดุ & บัญชีคู่อัตโนมัติ
            </a>
            <a href="#section-closing" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold">
                5. งานสิ้นเดือน & 28 รายงาน
            </a>
            <a href="#section-table" class="btn btn-sm btn-warning rounded-pill fw-bold text-dark">
                ★ ตารางสรุป "ทำเมนูนี้... ไปไหนต่อ"
            </a>
        </div>
    </div>

    <!-- 1. แผนผังภาพรวมวงจรการเงิน -->
    <div id="section-workflow" class="step-card">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="step-badge-num bg-primary">1</div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">แผนผังภาพรวมวงจรการเงินสถานศึกษา (Workflow)</h4>
                <div class="text-muted small">ภาพรวมการไหลของข้อมูลจากต้นปีจนถึงการปิดงวดและรายงานผล</div>
            </div>
        </div>

        <div class="row g-3 text-center my-2">
            <div class="col-md-3">
                <div class="p-3 rounded-4 bg-primary-subtle text-primary h-100 border border-primary-subtle">
                    <div class="fs-3 mb-1"><i class="bi bi-sliders"></i></div>
                    <div class="fw-bold">ระยะที่ 1: ตั้งค่าต้นปี</div>
                    <div class="small text-muted mt-1">ปีงบประมาณ ➔ แหล่งเงิน ➔ ธนาคาร ➔ งบโครงการ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 rounded-4 bg-success-subtle text-success h-100 border border-success-subtle">
                    <div class="fs-3 mb-1"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="fw-bold">ระยะที่ 2: ปฏิบัติงานระหว่างปี</div>
                    <div class="small text-muted mt-1">รับเงิน ➔ จัดซื้อ ➔ ขอเบิก ➔ ยืมเงินราชการ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 rounded-4 bg-warning-subtle text-warning-emphasis h-100 border border-warning-subtle">
                    <div class="fs-3 mb-1"><i class="bi bi-boxes"></i></div>
                    <div class="fw-bold">ระยะที่ 3: พัสดุ & ลงบัญชี</div>
                    <div class="small text-muted mt-1">ทะเบียนคุมวัสดุ-ครุภัณฑ์ ➔ บัญชีคู่ (ระบบลงให้อัตโนมัติ)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 rounded-4 bg-info-subtle text-info-emphasis h-100 border border-info-subtle">
                    <div class="fs-3 mb-1"><i class="bi bi-file-earmark-check"></i></div>
                    <div class="fw-bold">ระยะที่ 4: สิ้นเดือน / สิ้นปี</div>
                    <div class="small text-muted mt-1">กระทบยอดธนาคาร ➔ พิมพ์ 28 รายงาน ➔ ปิดงวด</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. การตั้งค่าต้นปี (เริ่มต้นที่เมนูไหนก่อน) -->
    <div id="section-setup" class="step-card">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="step-badge-num bg-indigo" style="background-color: #4f46e5;">2</div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">เริ่มต้นที่เมนูไหนก่อน? (การเตรียมระบบต้นปีงบประมาณ)</h4>
                <div class="text-muted small">ต้องดำเนินการตามลำดับ 1.1 ถึง 1.4 ก่อนที่ครูจะสามารถเบิกเงินได้</div>
            </div>
        </div>

        <div class="list-group list-group-flush">
            <!-- 1.1 -->
            <div class="list-group-item px-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary px-2.5 py-1 rounded-pill">ขั้นตอนที่ 1.1</span>
                        <span class="fw-bold text-dark fs-6">เมนู: ปีงบประมาณ/งวดบัญชี</span>
                        <code>/fiscal-years</code>
                    </div>
                    <p class="text-muted small mt-1 mb-0">
                        สร้างปีงบประมาณใหม่ (เช่น พ.ศ. 2570) กำหนดสถานะเป็น <strong>"ปีปัจจุบัน (Active)"</strong> ระบบจะสร้างงวดบัญชี 12 เดือนให้โดยอัตโนมัติ
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2 no-print flex-shrink-0">
                    <span class="text-muted small">เสร็จแล้วไปต่อที่ ➔</span>
                    <a href="<?= $baseUrl ?>/fiscal-years" class="btn btn-sm btn-outline-primary rounded-pill">
                        เปิดเมนูปีงบประมาณ <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- 1.2 -->
            <div class="list-group-item px-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary px-2.5 py-1 rounded-pill">ขั้นตอนที่ 1.2</span>
                        <span class="fw-bold text-dark fs-6">เมนู: ประเภทการเงิน</span>
                        <code>/funding-sources</code>
                    </div>
                    <p class="text-muted small mt-1 mb-0">
                        ตรวจสอบหรือเพิ่มแหล่งเงิน เช่น เงินอุดหนุนรายหัว, เงินรายได้สถานศึกษา, เงินเรียนฟรี 15 ปี, เงินอาหารกลางวัน
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2 no-print flex-shrink-0">
                    <span class="text-muted small">เสร็จแล้วไปต่อที่ ➔</span>
                    <a href="<?= $baseUrl ?>/funding-sources" class="btn btn-sm btn-outline-primary rounded-pill">
                        เปิดเมนูประเภทการเงิน <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- 1.3 -->
            <div class="list-group-item px-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary px-2.5 py-1 rounded-pill">ขั้นตอนที่ 1.3</span>
                        <span class="fw-bold text-dark fs-6">เมนู: ธนาคารและกระทบยอด</span>
                        <code>/banks</code>
                    </div>
                    <p class="text-muted small mt-1 mb-0">
                        บันทึกบัญชีเงินฝากธนาคารของโรงเรียน (เช่น กรุงไทย, ออมสิน, ธ.ก.ส.) และกำหนดยอดเงินคงเหลือยกมาจากปีก่อนหน้า
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2 no-print flex-shrink-0">
                    <span class="text-muted small">เสร็จแล้วไปต่อที่ ➔</span>
                    <a href="<?= $baseUrl ?>/banks" class="btn btn-sm btn-outline-primary rounded-pill">
                        เปิดเมนูธนาคาร <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- 1.4 -->
            <div class="list-group-item px-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success px-2.5 py-1 rounded-pill">ขั้นตอนที่ 1.4</span>
                        <span class="fw-bold text-dark fs-6">เมนู: บริหารงบประมาณ</span>
                        <code>/budgets</code>
                    </div>
                    <p class="text-muted small mt-1 mb-0">
                        นำแผนปฏิบัติการประจำปีมากรอกจัดสรรวงเงินงบประมาณตามโครงการและกิจกรรม <strong>(เมื่อจัดสรรเสร็จแล้ว ระบบจะพร้อมเปิดให้ครูยื่นขอเบิกจ่ายได้ทันที)</strong>
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2 no-print flex-shrink-0">
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">พร้อมเปิดใช้งาน</span>
                    <a href="<?= $baseUrl ?>/budgets" class="btn btn-sm btn-success rounded-pill">
                        เปิดเมนูงบประมาณ <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. วงจรงานประจำวัน (3 วงจรหลัก) -->
    <div id="section-ops" class="step-card">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="step-badge-num bg-success">3</div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">วงจรการปฏิบัติงานประจำวัน (3 วงจรหลัก)</h4>
                <div class="text-muted small">เลือกทำตามกรณีที่เกิดขึ้นจริง: รับเงิน, เบิกจ่ายงบปกติ, หรือยืมเงินราชการ</div>
            </div>
        </div>

        <!-- วงจร A -->
        <div class="card border border-primary-subtle rounded-4 p-3 mb-3 bg-light">
            <div class="fw-bold text-primary fs-6 mb-2">
                <i class="bi bi-wallet2 me-1"></i> วงจร A: เมื่อโรงเรียนได้รับเงินเข้ามา (การรับเงิน)
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 text-sm text-dark mb-2">
                <span class="flow-badge bg-primary text-white">1. ระบบรับเงิน (/receipts)</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="small text-muted">กด "ออกใบเสร็จรับเงิน" ระบุประเภทเงิน & ยอดเงิน</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="flow-badge bg-info text-dark">2. ธนาคารและกระทบยอด (/banks)</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="small text-muted">กด "จัดทำใบนำฝากเงิน" เข้าบัญชีโรงเรียน</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="flow-badge bg-success text-white">3. ระบบลงบัญชีอัตโนมัติ</span>
            </div>
            <div class="text-muted small">
                * เมื่อนำฝากเงินแล้ว ยอดเงินในบัญชีธนาคารจะเพิ่มขึ้น และระบบจะเดบิตเงินฝากธนาคาร / เครดิตรายได้ในสมุดรายวันทั่วไปให้โดยอัตโนมัติ
            </div>
        </div>

        <!-- วงจร B -->
        <div class="card border border-warning-subtle rounded-4 p-3 mb-3 bg-light">
            <div class="fw-bold text-warning-emphasis fs-6 mb-2">
                <i class="bi bi-cash-stack me-1"></i> วงจร B: การเบิกจ่ายเงินงบประมาณปกติ (ซื้อของ/จ้างงาน/จัดกิจกรรม)
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 text-sm text-dark mb-2">
                <span class="flow-badge bg-secondary text-white">1. จัดซื้อจัดจ้าง (/procurement)</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="flow-badge bg-dark text-white">2. ทะเบียนเอกสาร ผง. (/budgets/documents)</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="flow-badge bg-primary text-white">3. เบิกจ่ายเงิน (/disbursements)</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="flow-badge bg-success text-white">4. ตรวจ ➔ อนุมัติ ➔ จ่ายเงิน</span>
            </div>
            <div class="text-muted small">
                * ผู้ขอเบิกพิมพ์ <strong>แบบ ผง. 1/1</strong> เสนอ ผอ. ➔ ยื่นขอเบิกในระบบ ➔ การเงินตรวจ (Verified) ➔ ผอ. อนุมัติ (Approved) ➔ การเงินออกใบสำคัญจ่าย (PV) และจ่ายเงิน (Paid) ➔ ระบบตัดงบประมาณและบัญชีธนาคารอัตโนมัติ
            </div>
        </div>

        <!-- วงจร C -->
        <div class="card border border-danger-subtle rounded-4 p-3 mb-2 bg-light">
            <div class="fw-bold text-danger fs-6 mb-2">
                <i class="bi bi-journal-arrow-up me-1"></i> วงจร C: การขอยืมเงินราชการไปจัดกิจกรรม (เงินยืมและล้างยืม)
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 text-sm text-dark mb-2">
                <span class="flow-badge bg-danger text-white">1. เงินยืมและล้างยืม (/advances)</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="small text-muted">ยื่นสัญญายืมเงิน แบบ 8500</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="flow-badge bg-warning text-dark">2. ผอ. อนุมัติ & รับเงิน</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="small text-muted">จัดกิจกรรม (ไม่เกิน 30 วัน)</span>
                <i class="bi bi-arrow-right wf-arrow"></i>
                <span class="flow-badge bg-success text-white">3. ส่งใช้เงินยืมและล้างสัญญา</span>
            </div>
            <div class="text-muted small">
                * หากเกิน 30 วัน สัญญาจะกลายเป็น <strong>"เกินกำหนด (Overdue)"</strong> และมีสัญญาณไฟกระพริบเตือนสีแดงบนหน้าแดชบอร์ดทันที เมื่อเสร็จกิจกรรมให้ครูกลับมากด <strong>"ส่งใช้เงินยืม"</strong> เพื่อแนบใบเสร็จและคืนเงินเหลือ
            </div>
        </div>
    </div>

    <!-- 4. งานพัสดุ ครุภัณฑ์ และบัญชีคู่ -->
    <div id="section-assets" class="step-card">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="step-badge-num bg-warning" style="background-color: #f59e0b;">4</div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">งานพัสดุ ครุภัณฑ์ และบัญชีคู่ (Asset & Ledger)</h4>
                <div class="text-muted small">เมื่อมีการจ่ายเงินซื้อของ ให้ส่งต่อข้อมูลมายังโมดูลนี้</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="border rounded-4 p-3 h-100 bg-white">
                    <div class="fw-bold text-dark mb-2">
                        <i class="bi bi-boxes text-success me-1"></i> พัสดุและครุภัณฑ์ <code>/inventory</code>
                    </div>
                    <ul class="text-muted small mb-0 ps-3">
                        <li class="mb-1"><strong>วัสดุสิ้นเปลือง:</strong> นำรายการของที่ซื้อมาลง <strong>บัญชีคุมวัสดุ (Stock Card)</strong> เพื่อคุมสต็อกและการเบิกจ่ายของครู</li>
                        <li><strong>ครุภัณฑ์:</strong> ลงทะเบียนคุมทรัพย์สิน (แบบ พณ.1) ระบบจะคำนวณ <strong>ค่าเสื่อมราคาประจำปี</strong> ให้อัตโนมัติทุกสิ้นปีงบประมาณ</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded-4 p-3 h-100 bg-white">
                    <div class="fw-bold text-dark mb-2">
                        <i class="bi bi-book text-primary me-1"></i> บัญชีและสมุดรายวัน <code>/accounts</code>
                    </div>
                    <ul class="text-muted small mb-0 ps-3">
                        <li class="mb-1">ระบบบันทึกบัญชีคู่ให้อัตโนมัติเมื่อเกิดการรับเงิน จ่ายเงิน หรือส่งใช้เงินยืม</li>
                        <li class="mb-1">เจ้าหน้าที่บัญชีสามารถตรวจสอบ <strong>สมุดรายวันทั่วไป (General Journal)</strong> และ <strong>บัญชีแยกประเภท (General Ledger)</strong></li>
                        <li>ตรวจสอบ <strong>งบทดลอง (Trial Balance)</strong> ต้องมียอดเดบิต = เครดิตเสมอ</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. งานสิ้นเดือนและสิ้นปีงบประมาณ -->
    <div id="section-closing" class="step-card">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="step-badge-num bg-info" style="background-color: #0284c7;">5</div>
            <div>
                <h4 class="fw-bold mb-0 text-dark">งานสิ้นเดือนและสิ้นปีงบประมาณ (Reconciliation & 28 รายงาน)</h4>
                <div class="text-muted small">ขั้นตอนการตรวจสอบยอดเงินให้ตรงกับธนาคาร และการออกรายงานมาตรฐาน</div>
            </div>
        </div>

        <div class="d-flex flex-column gap-2 text-sm">
            <div class="p-2.5 rounded-3 bg-light d-flex align-items-center justify-content-between">
                <div>
                    <strong>1. งบพิสูจน์ยอดเงินฝากธนาคาร (`/banks` ➔ เมนูย่อยกระทบยอด):</strong>
                    <span class="text-muted">นำ Statement จากธนาคารจริงมากระทบยอดกับสมุดบัญชีโรงเรียน เพื่อตรวจเช็คค้างจ่าย</span>
                </div>
                <a href="<?= $baseUrl ?>/banks" class="btn btn-sm btn-light border no-print rounded-pill">ไปกระทบยอด</a>
            </div>

            <div class="p-2.5 rounded-3 bg-light d-flex align-items-center justify-content-between">
                <div>
                    <strong>2. เอกสารและ 28 รายงาน (`/reports`):</strong>
                    <span class="text-muted">พิมพ์รายงานเงินคงเหลือประจำวัน (บก.111), ทะเบียนคุมเบิกจ่าย, รายงานลูกหนี้เงินยืม, งบทดลอง ส่ง สพม./สพฐ. หรือ สตง.</span>
                </div>
                <a href="<?= $baseUrl ?>/reports" class="btn btn-sm btn-light border no-print rounded-pill">ไปหน้า 28 รายงาน</a>
            </div>

            <div class="p-2.5 rounded-3 bg-light d-flex align-items-center justify-content-between">
                <div>
                    <strong>3. ปิดงวดบัญชีสิ้นปี (`/fiscal-years`):</strong>
                    <span class="text-muted">กดปิดงวดบัญชีประจำปี เพื่อส่งยอดคงเหลือยกไปเป็นยอดยกมาของปีงบประมาณถัดไป</span>
                </div>
                <a href="<?= $baseUrl ?>/fiscal-years" class="btn btn-sm btn-light border no-print rounded-pill">ไปหน้าปิดงวด</a>
            </div>
        </div>
    </div>

    <!-- 6. ตารางสรุป "ทำเมนูนี้... ไปไหนต่อ" -->
    <div id="section-table" class="step-card">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="step-badge-num bg-danger">★</div>
                <div>
                    <h4 class="fw-bold mb-0 text-dark">ตารางสรุป "ถ้าทำเมนูนี้... ต้องไปทำเมนูไหนต่อ?"</h4>
                    <div class="text-muted small">แผ่นนำทางด่วนสำหรับเจ้าหน้าที่และครูทุกคน</div>
                </div>
            </div>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1.5 rounded-pill fw-bold">
                Quick Guide
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle quick-table mb-0" style="font-size: 0.9rem;">
                <thead>
                    <tr>
                        <th style="width: 28%;">เมนูที่ท่านกำลังทำอยู่</th>
                        <th style="width: 32%;">สถานการณ์ / ผลลัพธ์ที่ได้</th>
                        <th style="width: 40%;">เมนูถัดไปที่ต้องไปทำต่อ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><i class="bi bi-calendar-range text-primary me-1"></i> ปีงบประมาณ</div>
                            <code>/fiscal-years</code>
                        </td>
                        <td>เปิดปีงบประมาณใหม่แล้ว</td>
                        <td>
                            <div class="fw-bold text-primary">➔ ไปที่: ประเภทการเงิน <code>/funding-sources</code></div>
                            <div class="small text-muted">เพื่อตรวจสอบหมวดหมู่แหล่งเงิน</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><i class="bi bi-tags text-primary me-1"></i> ประเภทการเงิน</div>
                            <code>/funding-sources</code>
                        </td>
                        <td>กำหนดหมวดแหล่งเงินเรียบร้อย</td>
                        <td>
                            <div class="fw-bold text-primary">➔ ไปที่: ธนาคารและกระทบยอด <code>/banks</code></div>
                            <div class="small text-muted">เพื่อใส่ยอดยกมาต้นงวดของแต่ละบัญชี</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><i class="bi bi-credit-card-2-front text-primary me-1"></i> ธนาคารและกระทบยอด</div>
                            <code>/banks</code>
                        </td>
                        <td>ตั้งค่าบัญชีธนาคารพร้อมแล้ว</td>
                        <td>
                            <div class="fw-bold text-success">➔ ไปที่: บริหารงบประมาณ <code>/budgets</code></div>
                            <div class="small text-muted">เพื่อจัดสรรเงินตามแผนปฏิบัติการ (พร้อมเปิดใช้งานระบบ)</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><i class="bi bi-wallet2 text-success me-1"></i> ระบบรับเงิน</div>
                            <code>/receipts</code>
                        </td>
                        <td>ออกใบเสร็จรับเงินสำเร็จ</td>
                        <td>
                            <div class="fw-bold text-primary">➔ ไปที่: ธนาคารและกระทบยอด <code>/banks</code></div>
                            <div class="small text-muted">กดทำ "ใบนำฝากเงิน" เข้าบัญชีโรงเรียน เพื่อตัดยอดเงินสดเข้าธนาคาร</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><i class="bi bi-cart-check text-warning me-1"></i> จัดซื้อจัดจ้าง</div>
                            <code>/procurement</code>
                        </td>
                        <td>ตรวจรับพัสดุเรียบร้อย</td>
                        <td>
                            <div class="fw-bold text-danger">➔ ไปที่: เบิกจ่ายเงิน <code>/disbursements</code></div>
                            <div class="small text-muted">เพื่อทำเรื่องขออนุมัติจ่ายเงินให้ผู้ขาย</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><i class="bi bi-file-earmark-ruled text-dark me-1"></i> ทะเบียนเอกสาร ผง.</div>
                            <code>/budgets/documents</code>
                        </td>
                        <td>พิมพ์ ผง. 1/1 ผอ. ลงนามแล้ว</td>
                        <td>
                            <div class="fw-bold text-danger">➔ ไปที่: เบิกจ่ายเงิน <code>/disbursements</code></div>
                            <div class="small text-muted">เพื่อยื่นคำขอเบิกเงินจริงในระบบ</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><i class="bi bi-cash-stack text-success me-1"></i> เบิกจ่ายเงิน</div>
                            <code>/disbursements</code>
                        </td>
                        <td>บันทึกจ่ายเงิน (Paid) สำเร็จ</td>
                        <td>
                            <div class="fw-bold text-dark">➔ ไปที่: พัสดุและครุภัณฑ์ <code>/inventory</code> (ถ้าซื้อของ)</div>
                            <div class="small text-muted">เพื่อลงทะเบียนคุมวัสดุ (Stock Card) หรือทะเบียนครุภัณฑ์ (พณ.1)</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><i class="bi bi-journal-arrow-up text-danger me-1"></i> เงินยืมและล้างยืม</div>
                            <code>/advances</code>
                        </td>
                        <td>อนุมัติจ่ายเงินยืมให้ครูแล้ว</td>
                        <td>
                            <div class="fw-bold text-warning-emphasis">➔ นำเงินไปจัดกิจกรรม (ไม่เกิน 30 วัน)</div>
                            <div class="small text-muted">เมื่อเสร็จแล้ว <strong>กลับมาที่เมนูเงินยืม</strong> เพื่อกด "ส่งใช้เงินยืมและล้างสัญญา"</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><i class="bi bi-journal-arrow-up text-danger me-1"></i> เงินยืมและล้างยืม</div>
                            <code>/advances</code>
                        </td>
                        <td>ล้างสัญญายืมเงินเรียบร้อย</td>
                        <td>
                            <div class="fw-bold text-primary">➔ ไปที่: บัญชีและสมุดรายวัน <code>/accounts</code></div>
                            <div class="small text-muted">เพื่อตรวจสอบยอดเดบิต/เครดิตการปิดบัญชีลูกหนี้เงินยืม</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><i class="bi bi-calendar-check text-info me-1"></i> สิ้นเดือน / สิ้นงวด</div>
                            <code>งานประจำเดือน</code>
                        </td>
                        <td>สรุปยอดประจำเดือน</td>
                        <td>
                            <div class="fw-bold text-primary">➔ ไปที่: ธนาคาร (งบพิสูจน์ยอด) & 28 รายงาน <code>/reports</code></div>
                            <div class="small text-muted">พิมพ์รายงาน บก.111, ทะเบียนคุมการเบิกจ่าย, รายงานลูกหนี้เงินยืม เสนอ ผอ.</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
