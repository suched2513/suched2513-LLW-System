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
           c.model
    FROM cb_repairs r
    LEFT JOIN cb_borrow_logs b ON b.entry_id = r.borrow_log_id
    LEFT JOIN cb_teachers t   ON b.borrower_type='Teacher' AND t.teacher_id = b.borrower_id
    LEFT JOIN cb_students s   ON b.borrower_type='Student' AND s.student_id = b.borrower_id
    LEFT JOIN cb_chromebooks c ON c.chromebook_id = r.chromebook_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$rep = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rep) { http_response_code(404); exit('ไม่พบรายการ'); }

$attStudentName = null;
$attClassroom   = null;
if ($rep['borrower_type'] === 'Student' && $rep['borrower_id']) {
    $stmtS = $pdo->prepare("
        SELECT name, classroom FROM att_students
        WHERE student_id = LPAD(?, 5, '0') AND academic_year = 2569 LIMIT 1
    ");
    $stmtS->execute([$rep['borrower_id']]);
    $attRow = $stmtS->fetch(PDO::FETCH_ASSOC);
    if ($attRow) { $attStudentName = $attRow['name']; $attClassroom = $attRow['classroom']; }
}

$printDate    = date('d/m/Y H:i');
$repairDate   = (new DateTime($rep['created_at']))->format('d/m/Y');
$repairTime   = (new DateTime($rep['created_at']))->format('H:i');
$docNo        = str_pad($rep['id'], 4, '0', STR_PAD_LEFT);
$borrowerName = $attStudentName ?? $rep['borrower_name'] ?? '—';
$className    = $attClassroom   ?? $rep['class_name']    ?? '—';
$isStudent    = $rep['borrower_type'] === 'Student';
$deviceModel  = $rep['model'] ?: '—';
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>หนังสือรับรองรับผิดชอบความเสียหาย #<?= $docNo ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
/* ════ BASE ════ */
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sarabun', sans-serif; font-size: 13pt; color: #1a1a1a; background: #f1f5f9; }

/* ════ SCREEN TOOLBAR ════ */
.screen-bar {
    background: linear-gradient(135deg,#0e7490,#1d4ed8); color:#fff;
    padding:10px 22px; display:flex; align-items:center;
    justify-content:space-between; gap:10px;
    position:sticky; top:0; z-index:10; box-shadow:0 2px 10px rgba(0,0,0,.15);
}
.screen-bar strong { font-size:13pt; font-weight:800; }
.screen-bar small  { font-size:9.5pt; opacity:.75; display:block; }
.screen-bar .acts  { display:flex; gap:8px; }
.btn-print {
    background:#fff; color:#0e7490; border:none; cursor:pointer;
    padding:7px 18px; border-radius:9px;
    font-family:'Sarabun',sans-serif; font-size:11.5pt; font-weight:700;
    display:flex; align-items:center; gap:5px;
}
.btn-close {
    background:transparent; color:#fff; border:1.5px solid rgba(255,255,255,.4);
    cursor:pointer; padding:7px 14px; border-radius:9px;
    font-family:'Sarabun',sans-serif; font-size:10.5pt; font-weight:600;
}

/* ════ SCREEN WRAPPER ════ */
.page-wrap { max-width:760px; margin:28px auto 50px; padding:0 14px; }
.doc {
    background:#fff; border:1.5px solid #cbd5e1; border-radius:10px;
    box-shadow:0 5px 20px rgba(0,0,0,.07); padding:34px 42px;
}

/* ════ HEADER ════ */
.doc-header {
    display:flex; align-items:flex-start; justify-content:space-between;
    padding-bottom:10px; margin-bottom:6px; border-bottom:2.5px solid #1e293b;
}
.logo-wrap { display:flex; align-items:center; gap:10px; }
.logo-box {
    width:58px; height:58px; border:2px solid #1e293b; border-radius:6px;
    display:flex; align-items:center; justify-content:center;
    font-size:7pt; font-weight:800; color:#1e293b; text-align:center;
    padding:3px; line-height:1.3;
}
.school-block .s-name { font-size:11.5pt; font-weight:800; color:#0f172a; }
.school-block .s-sub  { font-size:8.5pt; color:#64748b; font-weight:600; margin-top:1px; }
.title-block { text-align:center; }
.title-block h1 { font-size:15.5pt; font-weight:800; color:#1e293b; line-height:1.22; }
.title-block .t-date { font-size:9.5pt; color:#475569; margin-top:5px; }
.title-block .t-no   { font-size:8.5pt; color:#94a3b8; margin-top:2px; font-weight:600; }

/* ════ SECTION ════ */
.sec { margin-top:11px; }
.sec-title {
    font-size:10.5pt; font-weight:800; color:#fff;
    background:#1e293b; padding:3px 11px; border-radius:5px;
    display:inline-block; margin-bottom:7px;
}

/* ════ FIELD ROW ════ */
.frow {
    display:flex; align-items:baseline; flex-wrap:wrap;
    gap:2px 6px; margin-bottom:6px; font-size:11pt; line-height:1.5;
}
.lbl { font-weight:700; color:#1e293b; white-space:nowrap; flex-shrink:0; }
.val {
    flex:1; border-bottom:1px solid #475569;
    min-width:55px; padding-bottom:1px; min-height:17px;
    color:#0f172a; font-weight:600;
}
.val.pre { color:#1d4ed8; font-weight:700; }

/* ════ CHECKBOX ROW ════ */
.crow { display:flex; flex-wrap:wrap; gap:3px 16px; align-items:center; margin-bottom:6px; font-size:11pt; font-weight:600; }
.ci   { display:flex; align-items:center; gap:5px; }
.cb   { width:13px; height:13px; border:1.5px solid #334155; border-radius:2px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
.cb.on { background:#1e293b; }
.cb.on::after { content:'✓'; color:#fff; font-size:9px; font-weight:900; line-height:1; }

/* ════ DESC BOX ════ */
.dbox {
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;
    padding:6px 11px; min-height:34px; font-size:10.5pt;
    color:#334155; line-height:1.5; margin-bottom:6px;
}

/* ════ AMOUNT ════ */
.arow { display:flex; align-items:baseline; gap:5px; margin-top:6px; font-size:11pt; font-weight:700; }
.aline { flex:1; border-bottom:1px solid #475569; min-height:16px; min-width:60px; }

/* ════ SIGNATURES ════ */
.sgrid {
    display:grid; grid-template-columns:1fr 1fr; gap:14px;
    margin-top:12px; padding-top:10px; border-top:1.5px solid #cbd5e1;
}
.sbox { text-align:center; }
.slbl { font-size:8.5pt; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.04em; margin-bottom:2px; }
.sline { height:40px; border-bottom:1px dashed #94a3b8; margin:3px 10px; }
.sname { font-size:10.5pt; color:#1e293b; font-weight:600; }
.srole { font-size:9pt; color:#64748b; margin-top:1px; }
.sdate { font-size:9.5pt; color:#94a3b8; margin-top:1px; }

.note { font-size:8.5pt; color:#94a3b8; font-style:italic; margin-top:3px; }

/* ════════════════════════════════
   PRINT — 1 หน้า A4 พอดี
   ════════════════════════════════ */
@media print {
    .screen-bar { display:none !important; }

    @page {
        size: A4 portrait;
        margin: 9mm 11mm 8mm 11mm;
    }

    html, body {
        font-size: 9pt !important;
        background: #fff !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .page-wrap { margin:0 !important; padding:0 !important; max-width:100% !important; }

    .doc {
        border:none !important; border-radius:0 !important;
        box-shadow:none !important; padding:0 !important;
        page-break-inside:avoid; break-inside:avoid;
    }

    /* Header */
    .doc-header { padding-bottom:5px !important; margin-bottom:3px !important; }
    .logo-box   { width:46px !important; height:46px !important; font-size:6pt !important; }
    .school-block .s-name { font-size:9.5pt !important; }
    .school-block .s-sub  { font-size:7.5pt !important; }
    .title-block h1       { font-size:12pt !important; }
    .title-block .t-date  { font-size:8pt !important; margin-top:2px !important; }
    .title-block .t-no    { font-size:7.5pt !important; }

    /* Sections */
    .sec       { margin-top:6px !important; }
    .sec-title { font-size:8.5pt !important; padding:2px 9px !important; margin-bottom:4px !important; }

    /* Fields */
    .frow { margin-bottom:3px !important; font-size:8.5pt !important; gap:1px 4px !important; line-height:1.4 !important; }
    .lbl  { font-size:8.5pt !important; }
    .val  { min-height:13px !important; font-size:8.5pt !important; }

    /* Checkboxes */
    .crow { margin-bottom:3px !important; font-size:8.5pt !important; gap:2px 12px !important; }
    .cb   { width:10px !important; height:10px !important; }
    .cb.on::after { font-size:7px !important; }

    /* Desc box */
    .dbox {
        font-size:8pt !important; padding:4px 7px !important;
        min-height:24px !important; margin-bottom:3px !important;
    }

    /* Amount */
    .arow  { margin-top:3px !important; font-size:8.5pt !important; }
    .aline { min-height:13px !important; }

    /* Signatures */
    .sgrid { margin-top:7px !important; padding-top:6px !important; gap:8px !important; }
    .sline { height:28px !important; margin:2px 8px !important; }
    .slbl  { font-size:7.5pt !important; }
    .sname { font-size:8.5pt !important; }
    .srole { font-size:8pt !important; }
    .sdate { font-size:8.5pt !important; }

    .note  { font-size:7.5pt !important; margin-top:1px !important; }
}
</style>
</head>
<body>

<!-- toolbar (screen only) -->
<div class="screen-bar">
    <div>
        <strong>หนังสือรับรองรับผิดชอบความเสียหาย</strong>
        <small>เลขที่ #<?= $docNo ?> &middot; พิมพ์ ณ <?= $printDate ?></small>
    </div>
    <div class="acts">
        <button class="btn-print" onclick="window.print()">🖨️ พิมพ์</button>
        <button class="btn-close" onclick="window.close()">✕ ปิด</button>
    </div>
</div>

<div class="page-wrap">
<div class="doc">

    <!-- HEADER -->
    <div class="doc-header">
        <div class="logo-wrap">
            <div class="logo-box">SVQA<br>—<br>โรงเรียน<br>ละลมวิทยา</div>
            <div class="school-block">
                <div class="s-name">โรงเรียนละลมวิทยา</div>
                <div class="s-sub">ระบบจัดการ Chromebook — LLW System</div>
            </div>
        </div>
        <div class="title-block">
            <h1>หนังสือรับรองรับผิดชอบ<br>ความเสียหาย</h1>
            <div class="t-date">วันที่บันทึก <?= $repairDate ?></div>
            <div class="t-no">เลขที่เอกสาร #<?= $docNo ?></div>
        </div>
    </div>

    <!-- 1. ผู้ทำเสียหาย -->
    <div class="sec">
        <div class="sec-title">1. ผู้ทำเสียหาย / ลูกหนาย</div>

        <div class="frow">
            <span class="lbl">1.1 ชื่อผู้ปกครอง</span>
            <span class="val">&nbsp;</span>
            <span class="lbl">นักศึกษาประจำตัว</span>
            <span class="val pre" style="max-width:120px"><?= htmlspecialchars($rep['borrower_id'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="lbl">ตำแหน่ง</span>
            <span class="crow" style="margin-bottom:0">
                <span class="ci"><span class="cb <?= $isStudent ? '' : 'on' ?>"></span><span>ครู</span></span>
                <span class="ci"><span class="cb <?= $isStudent ? 'on' : '' ?>"></span><span>นักเรียน</span></span>
            </span>
        </div>
        <div class="frow">
            <span class="lbl">โรงเรียน</span>
            <span class="val pre">โรงเรียนละลมวิทยา</span>
            <span class="lbl">ระดับชั้น</span>
            <span class="val pre" style="max-width:80px"><?= htmlspecialchars($className, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="lbl">โทร</span>
            <span class="val" style="max-width:150px">&nbsp;</span>
        </div>
        <div class="frow">
            <span class="lbl">1.2 ชื่อผู้ปกครอง</span>
            <span class="val">&nbsp;</span>
            <span class="lbl">เบอร์โทรติดต่อ</span>
            <span class="val" style="max-width:160px">&nbsp;</span>
            <span class="lbl">Line ID</span>
            <span class="val" style="max-width:140px">&nbsp;</span>
        </div>
        <div class="frow">
            <span class="lbl">1.3 ชื่อครูประจำชั้น</span>
            <span class="val">&nbsp;</span>
            <span class="lbl">เบอร์โทรติดต่อ</span>
            <span class="val" style="max-width:160px">&nbsp;</span>
            <span class="lbl">Line ID</span>
            <span class="val" style="max-width:140px">&nbsp;</span>
        </div>
    </div>

    <!-- 2. อุปกรณ์เสียหาย -->
    <div class="sec">
        <div class="sec-title">2. อุปกรณ์เสียหาย / ลูกหนาย</div>

        <div class="frow">
            <span class="lbl">ชื่ออุปกรณ์</span>
            <span class="val pre">Chromebook</span>
            <span class="lbl">ยี่ห้อ</span>
            <span class="val" style="max-width:120px">&nbsp;</span>
            <span class="lbl">รุ่น</span>
            <span class="val pre"><?= htmlspecialchars($deviceModel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="frow">
            <span class="lbl">หมายเลขเครื่อง</span>
            <span class="val pre" style="font-family:monospace;font-size:10.5pt;letter-spacing:.04em">
                <?= htmlspecialchars($rep['chromebook_id'], ENT_QUOTES, 'UTF-8') ?>
                <?= $rep['chromebook_serial'] ? '&nbsp;/&nbsp;' . htmlspecialchars($rep['chromebook_serial'], ENT_QUOTES, 'UTF-8') : '' ?>
            </span>
        </div>
    </div>

    <!-- 3. จำนวนเงินและสาเหตุ -->
    <div class="sec">
        <div class="sec-title">3. จำนวนเงินชดเชยและสาเหตุ</div>

        <div class="frow">
            <span class="lbl">เลขรับวันที่</span>
            <span class="val pre" style="max-width:100px"><?= $repairDate ?></span>
            <span class="lbl">เวลา</span>
            <span class="val pre" style="max-width:60px"><?= $repairTime ?></span>
            <span class="lbl">น. สถานะ</span>
            <span class="val pre" style="max-width:120px"><?= htmlspecialchars($rep['status'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="crow">
            <span class="ci">
                <span class="cb <?= ($rep['status'] === 'รับกลับ') ? '' : 'on' ?>"></span>
                <span>ซ่อมหาย</span>
            </span>
            <span class="ci">
                <span class="cb <?= ($rep['status'] !== 'รับกลับ') ? '' : 'on' ?>"></span>
                <span>เสียหาย (สภาพความเสียหาย)</span>
            </span>
            <span class="val" style="max-width:240px">&nbsp;</span>
        </div>

        <div class="lbl" style="margin-bottom:4px">รายละเอียดของอุปกรณ์</div>
        <div class="dbox"><?= $rep['description'] ? nl2br(htmlspecialchars($rep['description'], ENT_QUOTES, 'UTF-8')) : '&nbsp;' ?></div>

        <?php if ($rep['repair_notes']): ?>
        <div class="lbl" style="margin-bottom:4px">หมายเหตุการซ่อม / ผลการซ่อม</div>
        <div class="dbox"><?= nl2br(htmlspecialchars($rep['repair_notes'], ENT_QUOTES, 'UTF-8')) ?></div>
        <?php endif; ?>

        <div class="arow">
            <span class="lbl">จำนวนเงินที่ต้องชำระ</span>
            <span class="aline">&nbsp;</span>
            <span class="lbl">บาท</span>
        </div>
    </div>

    <!-- 4. ผู้รับผิดชอบ -->
    <div class="sec">
        <div class="sec-title">4. ผู้รับผิดชอบความเสียหาย</div>

        <div class="crow">
            <span class="ci"><span class="cb"></span><span>รับผิดชอบโดยแจ้งผ่านแอ็คเคาท์ผู้ปกครอง</span></span>
            <span class="val" style="max-width:180px">&nbsp;</span>
        </div>
        <div class="crow">
            <span class="ci"><span class="cb"></span><span>รับผิดชอบโดยบุคคล</span></span>
        </div>

        <div class="frow">
            <span class="lbl">ชื่อ</span>
            <span class="val pre"><?= htmlspecialchars($borrowerName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="lbl">ตำแหน่ง</span>
            <span class="crow" style="margin-bottom:0">
                <span class="ci"><span class="cb <?= $isStudent ? '' : 'on' ?>"></span><span>ครู</span></span>
                <span class="ci"><span class="cb <?= $isStudent ? 'on' : '' ?>"></span><span>นักเรียน</span></span>
            </span>
        </div>
        <div class="frow">
            <span class="lbl">เบอร์โทรติดต่อ</span>
            <span class="val" style="max-width:180px">&nbsp;</span>
            <span class="lbl">Line ID</span>
            <span class="val" style="max-width:160px">&nbsp;</span>
            <span class="lbl">Email</span>
            <span class="val">&nbsp;</span>
        </div>
        <p class="note">* ผู้รับผิดชอบนี้มีหน้าที่ดำเนินการตามที่ระบุในเอกสารฉบับนี้</p>
    </div>

    <!-- 5. ลงนาม -->
    <div class="sec">
        <div class="sec-title">5. ลงนามรับทราบความเสียหายและตรวจรับผิดชอบ</div>

        <div class="sgrid">
            <div class="sbox">
                <div class="slbl">ผู้รับผิดชอบความเสียหาย</div>
                <div class="sline"></div>
                <div class="sname">(<?= htmlspecialchars($borrowerName, ENT_QUOTES, 'UTF-8') ?>)</div>
                <div class="srole"><?= $isStudent ? 'นักเรียน ชั้น '.htmlspecialchars($className,ENT_QUOTES,'UTF-8') : 'ครู/บุคลากรทางการศึกษา' ?></div>
                <div class="sdate">วันที่ ......./......./......</div>
            </div>
            <div class="sbox">
                <div class="slbl">ครูประจำชั้น / ผู้ดูแลระบบ</div>
                <div class="sline"></div>
                <div class="sname">(....................................)</div>
                <div class="srole">ครูประจำชั้น / ครูผู้รับผิดชอบระบบ</div>
                <div class="sdate">วันที่ ......./......./......</div>
            </div>
        </div>
    </div>

</div><!-- /.doc -->
</div><!-- /.page-wrap -->

<script>
window.addEventListener('load', () => { setTimeout(() => window.print(), 600); });
</script>
</body>
</html>
