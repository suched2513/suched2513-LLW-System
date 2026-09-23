<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'cb_admin'])) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit('ไม่พบรายการ'); }

$pdo = getPdo();

$hasImgCol = !empty($pdo->query("SHOW COLUMNS FROM cb_repairs LIKE 'images'")->fetchAll());
$imgSql    = $hasImgCol ? 'r.images,' : "'' AS images,";

$stmt = $pdo->prepare("
    SELECT r.id, r.borrow_log_id, r.chromebook_id, r.chromebook_serial,
           r.description, {$imgSql} r.status, r.repair_notes, r.reported_by,
           r.created_at, r.updated_at,
           b.borrower_type, b.borrower_id, b.class_name, b.date_borrowed,
           COALESCE(t.name, s.name, b.borrower_id) AS borrower_name,
           c.model, c.brand,
           s.student_id
    FROM cb_repairs r
    LEFT JOIN cb_borrow_logs b ON b.entry_id = r.borrow_log_id
    LEFT JOIN cb_teachers t ON b.borrower_type='Teacher' AND t.teacher_id = b.borrower_id
    LEFT JOIN cb_students s ON b.borrower_type='Student' AND s.student_id = b.borrower_id
    LEFT JOIN cb_chromebooks c ON c.chromebook_id = r.chromebook_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$rep = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rep) { http_response_code(404); exit('ไม่พบรายการ'); }

// ดึงข้อมูลนักเรียนเพิ่มเติมจาก att_students
$studentExtra = null;
if ($rep['borrower_type'] === 'Student' && $rep['borrower_id']) {
    $stmtS = $pdo->prepare("
        SELECT name, classroom, phone, parent_name
        FROM att_students
        WHERE student_id = LPAD(?, 5, '0') AND academic_year = 2569
        LIMIT 1
    ");
    $stmtS->execute([$rep['borrower_id']]);
    $studentExtra = $stmtS->fetch(PDO::FETCH_ASSOC);
}

// ดึงข้อมูลครูประจำชั้น (homeroom teacher) จากห้องเรียน
$homeroomTeacher = null;
if ($rep['class_name']) {
    $stmtH = $pdo->prepare("
        SELECT t.name
        FROM cb_teachers t
        WHERE t.teacher_id IN (
            SELECT DISTINCT borrower_id FROM cb_borrow_logs
            WHERE class_name = ? AND borrower_type = 'Teacher'
            LIMIT 1
        )
        LIMIT 1
    ");
    $stmtH->execute([$rep['class_name']]);
    $homeroomTeacher = $stmtH->fetchColumn();
}

$printDate  = date('d/m/Y H:i');
$repairDate = (new DateTime($rep['created_at']))->format('d/m/Y');
$docNo      = str_pad($rep['id'], 4, '0', STR_PAD_LEFT);
$borrowerName = $studentExtra['name'] ?? $rep['borrower_name'] ?? '—';
$className    = $studentExtra['classroom'] ?? $rep['class_name'] ?? '—';
$parentName   = $studentExtra['parent_name'] ?? '';
$deviceBrand  = trim(($rep['brand'] ?? '') . ' ' . ($rep['model'] ?? '')) ?: '—';
$isStudent    = $rep['borrower_type'] === 'Student';
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>หนังสือรับรองรับผิดชอบความเสียหาย #<?= $docNo ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Sarabun', sans-serif;
    font-size: 14pt;
    color: #1a1a1a;
    background: #f1f5f9;
}

/* ── Screen toolbar ── */
.screen-bar {
    background: linear-gradient(135deg, #0e7490, #1d4ed8);
    color: #fff; padding: 12px 24px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; position: sticky; top: 0; z-index: 10;
    box-shadow: 0 2px 12px rgba(0,0,0,0.15);
}
.screen-bar .title-area { display: flex; flex-direction: column; gap: 2px; }
.screen-bar .title-area strong { font-size: 14pt; font-weight: 800; }
.screen-bar .title-area span   { font-size: 10pt; opacity: .75; }
.screen-bar .actions { display: flex; gap: 8px; }
.btn-print {
    background: #fff; color: #0e7490; border: none; cursor: pointer;
    padding: 9px 22px; border-radius: 10px;
    font-family: 'Sarabun',sans-serif; font-size: 12pt; font-weight: 700;
    display: flex; align-items: center; gap: 6px; transition: background .2s;
}
.btn-print:hover { background: #e0f7fa; }
.btn-close {
    background: transparent; color: #fff;
    border: 1.5px solid rgba(255,255,255,.4); cursor: pointer;
    padding: 9px 18px; border-radius: 10px;
    font-family: 'Sarabun',sans-serif; font-size: 11pt; font-weight: 600;
}
.btn-close:hover { background: rgba(255,255,255,.1); }

/* ── Page wrap ── */
.page-wrap { max-width: 780px; margin: 32px auto 60px; padding: 0 16px; }

/* ── Document card ── */
.doc {
    background: #fff;
    border: 1.5px solid #cbd5e1;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 24px rgba(0,0,0,0.07);
    padding: 40px 48px;
}

/* ── Header ── */
.doc-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 14px;
    border-bottom: 2px solid #1e293b;
}
.school-logo-area { display: flex; align-items: center; gap: 12px; }
.school-logo-box {
    width: 68px; height: 68px;
    border: 2.5px solid #1e293b;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 8.5pt; font-weight: 700; color: #334155; text-align: center;
    padding: 4px; line-height: 1.3; letter-spacing: .03em;
}
.school-name-block { display: flex; flex-direction: column; gap: 2px; }
.school-name-block .school-main  { font-size: 12.5pt; font-weight: 800; color: #0f172a; }
.school-name-block .school-sub   { font-size: 9pt; color: #64748b; font-weight: 600; }
.doc-title-block { text-align: center; }
.doc-title-block h1 { font-size: 17pt; font-weight: 800; color: #1e293b; letter-spacing: .01em; line-height: 1.25; }
.doc-title-block .doc-date-line  { font-size: 11pt; color: #475569; margin-top: 6px; }
.doc-title-block .doc-no         { font-size: 9.5pt; color: #94a3b8; margin-top: 3px; font-weight: 600; }

/* ── Section headers ── */
.section {
    margin-top: 20px;
}
.section-title {
    font-size: 12.5pt;
    font-weight: 800;
    color: #fff;
    background: #1e293b;
    padding: 5px 14px;
    border-radius: 6px;
    display: inline-block;
    margin-bottom: 12px;
}

/* ── Field row ── */
.field-row {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 4px 8px;
    margin-bottom: 9px;
    font-size: 13pt;
}
.field-label {
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    flex-shrink: 0;
}
.field-value {
    flex: 1;
    border-bottom: 1.5px solid #334155;
    min-width: 80px;
    padding-bottom: 2px;
    min-height: 22px;
    color: #0f172a;
    font-weight: 600;
}
.field-value.blank { color: transparent; } /* blank field */
.field-fixed {
    /* Pre-filled from DB */
    color: #1e40af;
    font-weight: 700;
}
.field-unit {
    white-space: nowrap;
    font-weight: 700;
    color: #1e293b;
    flex-shrink: 0;
}

/* ── Checkbox row ── */
.checkbox-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 28px;
    align-items: center;
    margin-bottom: 9px;
    font-size: 13pt;
    font-weight: 600;
}
.cb-item { display: flex; align-items: center; gap: 6px; cursor: default; }
.cb-box {
    width: 16px; height: 16px;
    border: 2px solid #334155;
    border-radius: 3px;
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.cb-box.checked { background: #1e293b; }
.cb-box.checked::after { content: '✓'; color: #fff; font-size: 11px; font-weight: 900; line-height: 1; }

/* ── Description box ── */
.desc-area {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    min-height: 52px;
    font-size: 12.5pt;
    color: #334155;
    line-height: 1.6;
    margin-bottom: 9px;
}
.desc-area.blank-lines {
    color: transparent;
    background: repeating-linear-gradient(
        to bottom, transparent, transparent 27px, #e2e8f0 27px, #e2e8f0 28.5px
    );
    min-height: 72px;
}

/* ── Signature grid ── */
.sign-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1.5px solid #cbd5e1;
}
.sign-box { text-align: center; }
.sign-label { font-size: 10.5pt; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
.sign-line  { height: 56px; border-bottom: 1.5px dashed #94a3b8; margin: 6px 16px; }
.sign-name  { font-size: 12pt; color: #1e293b; font-weight: 600; }
.sign-role  { font-size: 10pt; color: #64748b; margin-top: 2px; }
.sign-date  { font-size: 10.5pt; color: #94a3b8; margin-top: 2px; }

/* ── Divider ── */
.section-divider {
    border: none;
    border-top: 1px dashed #cbd5e1;
    margin: 16px 0;
}

/* ── Note (italic small text) ── */
.note-text { font-size: 10pt; color: #94a3b8; font-style: italic; margin-top: 6px; }

/* ── Print styles ── */
@media print {
    .screen-bar { display: none !important; }
    body { background: #fff; }
    .page-wrap { margin: 0; padding: 0; max-width: 100%; }
    .doc {
        border: none; border-radius: 0; box-shadow: none;
        padding: 12mm 14mm;
        page-break-inside: avoid;
    }
    @page { size: A4 portrait; margin: 8mm 6mm; }
}
</style>
</head>
<body>

<!-- Screen toolbar -->
<div class="screen-bar">
    <div class="title-area">
        <strong>หนังสือรับรองรับผิดชอบความเสียหาย</strong>
        <span>เลขที่อ้างอิง #<?= $docNo ?> · พิมพ์ ณ <?= $printDate ?></span>
    </div>
    <div class="actions">
        <button class="btn-print" onclick="window.print()">🖨️ พิมพ์</button>
        <button class="btn-close" onclick="window.close()">✕ ปิด</button>
    </div>
</div>

<div class="page-wrap">
<div class="doc">

    <!-- ── Document Header ── -->
    <div class="doc-header">
        <div class="school-logo-area">
            <div class="school-logo-box">
                SVQA<br>—<br>โรงเรียน<br>ละลมวิทยา
            </div>
            <div class="school-name-block">
                <span class="school-main">โรงเรียนละลมวิทยา</span>
                <span class="school-sub">ระบบจัดการ Chromebook — LLW System</span>
            </div>
        </div>
        <div class="doc-title-block">
            <h1>หนังสือรับรองรับผิดชอบ<br>ความเสียหาย</h1>
            <div class="doc-date-line">
                วันที่บันทึกรายการ&nbsp;&nbsp;<?= $repairDate ?>
            </div>
            <div class="doc-no">เลขที่เอกสาร #<?= $docNo ?></div>
        </div>
    </div>

    <!-- ── Section 1: ผู้ทำเสียหาย / ลูกหนาย ── -->
    <div class="section">
        <div class="section-title">1. ผู้ทำเสียหาย / ลูกหนาย</div>

        <!-- 1.1 ผู้ปกครอง -->
        <div class="field-row">
            <span class="field-label">1.1 ชื่อผู้ปกครอง</span>
            <span class="field-value"><?= htmlspecialchars($parentName ?: '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="field-label">นักศึกษาประจำตัว</span>
            <span class="field-value" style="max-width:120px"><?= htmlspecialchars($rep['borrower_id'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="field-label">ตำแหน่ง</span>
            <div class="checkbox-row" style="margin-bottom:0;">
                <div class="cb-item">
                    <div class="cb-box <?= $isStudent ? '' : 'checked' ?>"></div>
                    <span>ครู</span>
                </div>
                <div class="cb-item">
                    <div class="cb-box <?= $isStudent ? 'checked' : '' ?>"></div>
                    <span>นักเรียน</span>
                </div>
            </div>
        </div>
        <div class="field-row">
            <span class="field-label">โรงเรียน</span>
            <span class="field-value field-fixed">โรงเรียนละลมวิทยา</span>
            <span class="field-label">ระดับชั้น</span>
            <span class="field-value field-fixed" style="max-width:90px"><?= htmlspecialchars($className, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="field-label">โทร</span>
            <span class="field-value" style="max-width:160px">&nbsp;</span>
        </div>

        <!-- 1.2 ชื่อผู้ปกครอง contact -->
        <div class="field-row">
            <span class="field-label">1.2 ชื่อผู้ปกครอง</span>
            <span class="field-value">&nbsp;</span>
            <span class="field-label">เบอร์โทรติดต่อ</span>
            <span class="field-value" style="max-width:180px">&nbsp;</span>
            <span class="field-label">Line ID</span>
            <span class="field-value" style="max-width:160px">&nbsp;</span>
        </div>

        <!-- 1.3 ครูประจำชั้น -->
        <div class="field-row">
            <span class="field-label">1.3 ชื่อครูประจำชั้น</span>
            <span class="field-value field-fixed"><?= htmlspecialchars($homeroomTeacher ?: '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="field-label">เบอร์โทรติดต่อ</span>
            <span class="field-value" style="max-width:180px">&nbsp;</span>
            <span class="field-label">Line ID</span>
            <span class="field-value" style="max-width:160px">&nbsp;</span>
        </div>
    </div>

    <!-- ── Section 2: อุปกรณ์เสียหาย / ลูกหนาย ── -->
    <div class="section">
        <div class="section-title">2. อุปกรณ์เสียหาย / ลูกหนาย</div>

        <div class="field-row">
            <span class="field-label">ชื่ออุปกรณ์</span>
            <span class="field-value field-fixed">Chromebook</span>
            <span class="field-label">ยี่ห้อ</span>
            <span class="field-value field-fixed"><?= htmlspecialchars($rep['brand'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="field-label">รุ่น</span>
            <span class="field-value field-fixed"><?= htmlspecialchars($rep['model'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="field-row">
            <span class="field-label">หมายเลขเครื่อง</span>
            <span class="field-value field-fixed" style="font-family:monospace;font-size:12.5pt;letter-spacing:.04em;">
                <?= htmlspecialchars($rep['chromebook_id'], ENT_QUOTES, 'UTF-8') ?>
                <?= $rep['chromebook_serial'] ? ' / ' . htmlspecialchars($rep['chromebook_serial'], ENT_QUOTES, 'UTF-8') : '' ?>
            </span>
        </div>
    </div>

    <!-- ── Section 3: จำนวนเงินชดเชย/สาเหตุ ── -->
    <div class="section">
        <div class="section-title">3. จำนวนเงินชดเชยและสาเหตุ</div>

        <div class="field-row">
            <span class="field-label">เลขรับวันที่</span>
            <span class="field-value field-fixed"><?= $repairDate ?></span>
            <span class="field-label">เวลา</span>
            <span class="field-value field-fixed"><?= (new DateTime($rep['created_at']))->format('H:i') ?></span>
            <span class="field-label">น. สถานะความเสียหาย</span>
            <span class="field-value field-fixed"><?= htmlspecialchars($rep['status'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="checkbox-row">
            <div class="cb-item">
                <div class="cb-box<?= ($rep['status'] !== 'รับกลับ') ? ' checked' : '' ?>"></div>
                <span>ซ่อมหาย</span>
            </div>
            <div class="cb-item">
                <div class="cb-box<?= ($rep['status'] === 'รับกลับ') ? ' checked' : '' ?>"></div>
                <span>เสียหาย (สภาพความเสียหาย)</span>
            </div>
            <span class="field-value" style="max-width:300px"><?= htmlspecialchars(mb_substr($rep['description'] ?? '', 0, 60), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="field-label" style="margin-bottom:6px;">รายละเอียดของอุปกรณ์</div>
        <div class="desc-area"><?= nl2br(htmlspecialchars($rep['description'] ?? '—', ENT_QUOTES, 'UTF-8')) ?></div>

        <?php if ($rep['repair_notes']): ?>
        <div class="field-label" style="margin-bottom:6px;">หมายเหตุการซ่อม / ผลการซ่อม</div>
        <div class="desc-area"><?= nl2br(htmlspecialchars($rep['repair_notes'], ENT_QUOTES, 'UTF-8')) ?></div>
        <?php endif; ?>

        <div class="field-row" style="margin-top:10px;">
            <span class="field-label">จำนวนเงินที่ต้องชำระ</span>
            <span class="field-value" style="max-width:200px">&nbsp;</span>
            <span class="field-label">บาท</span>
        </div>
    </div>

    <!-- ── Section 4: ผู้รับผิดชอบความเสียหาย ── -->
    <div class="section">
        <div class="section-title">4. ผู้รับผิดชอบความเสียหาย</div>

        <div class="checkbox-row" style="margin-bottom:10px;">
            <div class="cb-item">
                <div class="cb-box"></div>
                <span>รับผิดชอบโดยแจ้งผ่านแอ็คเคาท์ผู้ปกครอง</span>
                <span class="field-value" style="max-width:220px">&nbsp;</span>
            </div>
        </div>
        <div class="checkbox-row" style="margin-bottom:10px;">
            <div class="cb-item">
                <div class="cb-box"></div>
                <span>รับผิดชอบโดยบุคคล</span>
            </div>
        </div>

        <div class="field-row">
            <span class="field-label">ชื่อ</span>
            <span class="field-value field-fixed"><?= htmlspecialchars($borrowerName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="field-label">นามสกุล</span>
            <span class="field-value">&nbsp;</span>
            <span class="field-label">ตำแหน่ง</span>
            <div class="checkbox-row" style="margin-bottom:0;">
                <div class="cb-item">
                    <div class="cb-box <?= $isStudent ? '' : 'checked' ?>"></div>
                    <span>ครู</span>
                </div>
                <div class="cb-item">
                    <div class="cb-box <?= $isStudent ? 'checked' : '' ?>"></div>
                    <span>นักเรียน</span>
                </div>
            </div>
        </div>
        <div class="field-row">
            <span class="field-label">เบอร์โทรติดต่อ</span>
            <span class="field-value" style="max-width:200px">&nbsp;</span>
            <span class="field-label">Line ID</span>
            <span class="field-value" style="max-width:180px">&nbsp;</span>
            <span class="field-label">Email</span>
            <span class="field-value">&nbsp;</span>
        </div>

        <p class="note-text">* ผู้รับผิดชอบนี้มีหน้าที่ดำเนินการในส่วนของแต่ละโดยลำดับดังต่อไปนี้ตามที่ระบุ</p>
    </div>

    <!-- ── Section 5: ลงนามรับทราบ ── -->
    <div class="section">
        <div class="section-title">5. ลงนามรับทราบความเสียหายและตรวจรับผิดชอบ</div>

        <div class="sign-grid">
            <!-- ลายเซ็นซ้าย -->
            <div class="sign-box">
                <div class="sign-label">ผู้รับผิดชอบความเสียหาย</div>
                <div class="sign-line"></div>
                <div class="sign-name">(<?= htmlspecialchars($borrowerName, ENT_QUOTES, 'UTF-8') ?>)</div>
                <div class="sign-role">
                    <?= $isStudent
                        ? htmlspecialchars('นักเรียน ชั้น ' . ($className ?: ''), ENT_QUOTES, 'UTF-8')
                        : 'ครู/บุคลากรทางการศึกษา' ?>
                </div>
                <div class="sign-date">วันที่ ......./......./......</div>
            </div>
            <!-- ลายเซ็นขวา -->
            <div class="sign-box">
                <div class="sign-label">ครูประจำชั้น / ผู้ดูแลระบบ</div>
                <div class="sign-line"></div>
                <div class="sign-name">(<?= htmlspecialchars($homeroomTeacher ?: '...........................', ENT_QUOTES, 'UTF-8') ?>)</div>
                <div class="sign-role">ครูประจำชั้น / ครูผู้รับผิดชอบระบบ</div>
                <div class="sign-date">วันที่ ......./......./......</div>
            </div>
        </div>
    </div>

</div><!-- /.doc -->
</div><!-- /.page-wrap -->

<script>
window.addEventListener('load', () => {
    setTimeout(() => window.print(), 600);
});
</script>
</body>
</html>
