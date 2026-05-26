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
           b.borrower_type, b.class_name,
           COALESCE(t.name, s.name, b.borrower_id) AS borrower_name,
           c.model, c.brand
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

$imgs       = array_filter(explode(',', $rep['images'] ?? ''));
$isReturned = in_array($rep['status'], ['ซ่อมเสร็จ', 'รับกลับ']);
$printDate  = date('d/m/Y H:i');
$repairDate = (new DateTime($rep['created_at']))->format('d/m/Y');
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ใบแจ้งซ่อม #<?= $rep['id'] ?> — โรงเรียนละลมวิทยา</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sarabun', sans-serif; font-size: 13pt; color: #1e293b; background: #f8fafc; }

/* Screen UI */
.screen-bar {
    background: #0e7490; color: #fff; padding: 12px 20px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; position: sticky; top: 0; z-index: 10;
}
.screen-bar .btn-print {
    background: #fff; color: #0e7490; border: none; cursor: pointer;
    padding: 8px 20px; border-radius: 10px; font-family: 'Sarabun',sans-serif;
    font-size: 13pt; font-weight: 700; display: flex; align-items: center; gap: 6px;
}
.screen-bar .btn-print:hover { background: #e0f7fa; }
.screen-bar .btn-close {
    background: transparent; color: #fff; border: 1.5px solid rgba(255,255,255,.4); cursor: pointer;
    padding: 8px 16px; border-radius: 10px; font-family: 'Sarabun',sans-serif;
    font-size: 12pt; font-weight: 600;
}
.page-wrap { max-width: 720px; margin: 30px auto 60px; padding: 0 16px; }

/* Slip styles */
.slip {
    background: #fff; border: 1.5px solid #cbd5e1;
    border-radius: 12px; overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.slip + .slip { margin-top: 24px; }

.slip-header {
    padding: 18px 24px 14px;
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    border-bottom: 1.5px solid #e2e8f0;
}
.slip-header .school-info { flex: 1; }
.slip-header .school-name { font-size: 15pt; font-weight: 800; color: #0e7490; line-height: 1.2; }
.slip-header .school-sub  { font-size: 10pt; color: #64748b; margin-top: 2px; }
.slip-header .slip-title  { text-align: right; }
.slip-header .slip-title h2 { font-size: 16pt; font-weight: 800; color: #1e293b; }
.slip-header .slip-title .slip-no { font-size: 11pt; color: #64748b; margin-top: 2px; }

.slip-body { padding: 18px 24px; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 24px; margin-bottom: 16px; }
.info-grid.cols3 { grid-template-columns: 1fr 1fr 1fr; }
.info-item label { display: block; font-size: 9pt; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
.info-item .value { font-size: 13pt; font-weight: 700; color: #1e293b; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 4px; min-height: 26px; }
.info-item .value.mono { font-family: 'Courier New', monospace; font-size: 12pt; color: #0e7490; }

.desc-box { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; min-height: 56px; }
.desc-box label { font-size: 9pt; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 4px; }
.desc-box p { font-size: 13pt; color: #334155; line-height: 1.5; }

.status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 14px; border-radius: 20px; font-weight: 800; font-size: 12pt;
}
.status-รับแจ้ง   { background: #fef3c7; color: #b45309; }
.status-ส่งซ่อม   { background: #dbeafe; color: #1d4ed8; }
.status-ซ่อมเสร็จ { background: #d1fae5; color: #065f46; }
.status-รับกลับ   { background: #f1f5f9; color: #475569; }

.photos-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.photos-grid img { width: 90px; height: 90px; object-fit: cover; border-radius: 8px; border: 1.5px solid #e2e8f0; }

.sign-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 20px; padding-top: 16px; border-top: 1.5px solid #e2e8f0; }
.sign-box { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; }
.sign-box .sign-line { height: 60px; border-bottom: 1.5px dashed #cbd5e1; margin: 4px 0 6px; }
.sign-box label { font-size: 9pt; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }
.sign-box .sign-name { font-size: 11pt; color: #64748b; text-align: center; }
.sign-box .sign-date { font-size: 10pt; color: #94a3b8; text-align: center; margin-top: 2px; }

.cut-line { text-align: center; color: #94a3b8; font-size: 10pt; letter-spacing: .2em; margin: 16px 0; position: relative; }
.cut-line::before, .cut-line::after { content: ''; position: absolute; top: 50%; width: 42%; height: 0; border-top: 1.5px dashed #cbd5e1; }
.cut-line::before { left: 0; }
.cut-line::after  { right: 0; }

/* Print */
@media print {
    .screen-bar { display: none; }
    body { background: #fff; }
    .page-wrap { margin: 0; padding: 8px; max-width: 100%; }
    .slip { box-shadow: none; border-radius: 0; page-break-inside: avoid; }
    .slip + .slip { margin-top: 16px; }
    .cut-line { margin: 8px 0; }
}
</style>
</head>
<body>

<!-- Screen toolbar -->
<div class="screen-bar">
    <div>
        <div style="font-size:14pt;font-weight:800;">ใบแจ้งซ่อม Chromebook #<?= $rep['id'] ?></div>
        <div style="font-size:10pt;opacity:.8;">โรงเรียนละลมวิทยา · พิมพ์ ณ <?= $printDate ?></div>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="btn-print" onclick="window.print()">🖨️ พิมพ์</button>
        <button class="btn-close" onclick="window.close()">✕ ปิด</button>
    </div>
</div>

<div class="page-wrap">

<!-- ── ใบแจ้งซ่อม ─────────────────────────────────────────────── -->
<div class="slip">
    <div class="slip-header">
        <div class="school-info">
            <div class="school-name">โรงเรียนละลมวิทยา</div>
            <div class="school-sub">ระบบจัดการ Chromebook — LLW System</div>
        </div>
        <div class="slip-title">
            <h2>ใบแจ้งซ่อม</h2>
            <div class="slip-no">เลขที่ #<?= str_pad($rep['id'], 4, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>

    <div class="slip-body">

        <!-- Device info -->
        <div class="info-grid cols3" style="margin-bottom:14px;">
            <div class="info-item">
                <label>รหัสเครื่อง</label>
                <div class="value mono"><?= htmlspecialchars($rep['chromebook_id'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="info-item">
                <label>Serial Number</label>
                <div class="value mono"><?= htmlspecialchars($rep['chromebook_serial'] ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="info-item">
                <label>รุ่น</label>
                <div class="value"><?= htmlspecialchars(trim(($rep['brand'] ?? '') . ' ' . ($rep['model'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <!-- Borrower info -->
        <div class="info-grid" style="margin-bottom:14px;">
            <div class="info-item">
                <label>ผู้ยืม / ผู้แจ้ง</label>
                <div class="value"><?= htmlspecialchars($rep['borrower_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="info-item">
                <label>ห้องเรียน / ประเภท</label>
                <div class="value">
                    <?= htmlspecialchars($rep['class_name'] ?: ($rep['borrower_type'] === 'Teacher' ? 'ครู/บุคลากร' : '—'), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div class="info-item">
                <label>วันที่แจ้ง</label>
                <div class="value"><?= $repairDate ?></div>
            </div>
            <div class="info-item">
                <label>สถานะปัจจุบัน</label>
                <div class="value" style="border:none;padding:0;">
                    <span class="status-badge status-<?= htmlspecialchars($rep['status'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($rep['status'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="desc-box">
            <label>รายละเอียดความเสียหาย / อาการ</label>
            <p><?= nl2br(htmlspecialchars($rep['description'] ?? '—', ENT_QUOTES, 'UTF-8')) ?></p>
        </div>

        <!-- Images -->
        <?php if (!empty($imgs)): ?>
        <div style="margin-bottom:16px;">
            <div style="font-size:9pt;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">รูปภาพประกอบ</div>
            <div class="photos-grid">
                <?php foreach ($imgs as $img): ?>
                <img src="uploads/<?= htmlspecialchars(basename($img), ENT_QUOTES, 'UTF-8') ?>" alt="รูปซ่อม">
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($rep['repair_notes']): ?>
        <div class="desc-box" style="margin-bottom:16px;">
            <label>หมายเหตุการซ่อม / ผลการซ่อม</label>
            <p><?= nl2br(htmlspecialchars($rep['repair_notes'], ENT_QUOTES, 'UTF-8')) ?></p>
        </div>
        <?php endif; ?>

        <!-- Signatures — ใบแจ้งซ่อม -->
        <div class="sign-grid">
            <div class="sign-box">
                <label>ผู้แจ้งซ่อม</label>
                <div class="sign-line"></div>
                <div class="sign-name">(<?= htmlspecialchars($rep['borrower_name'] ?? '.....................', ENT_QUOTES, 'UTF-8') ?>)</div>
                <div class="sign-date">วันที่ <?= $repairDate ?></div>
            </div>
            <div class="sign-box">
                <label>ผู้รับแจ้ง / ผู้ดูแลระบบ</label>
                <div class="sign-line"></div>
                <div class="sign-name">(.....................)</div>
                <div class="sign-date">วันที่ ....../....../......</div>
            </div>
        </div>

    </div>
</div>

<?php if ($isReturned): ?>

<div class="cut-line">✂ ตัดตรงนี้</div>

<!-- ── ใบเซ็นรับเครื่องคืน ────────────────────────────────────── -->
<div class="slip">
    <div class="slip-header">
        <div class="school-info">
            <div class="school-name">โรงเรียนละลมวิทยา</div>
            <div class="school-sub">ระบบจัดการ Chromebook — LLW System</div>
        </div>
        <div class="slip-title">
            <h2>ใบเซ็นรับเครื่องคืน</h2>
            <div class="slip-no">อ้างอิงใบแจ้งซ่อม #<?= str_pad($rep['id'], 4, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>

    <div class="slip-body">

        <div class="info-grid cols3" style="margin-bottom:14px;">
            <div class="info-item">
                <label>รหัสเครื่อง</label>
                <div class="value mono"><?= htmlspecialchars($rep['chromebook_id'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="info-item">
                <label>Serial Number</label>
                <div class="value mono"><?= htmlspecialchars($rep['chromebook_serial'] ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="info-item">
                <label>รุ่น</label>
                <div class="value"><?= htmlspecialchars(trim(($rep['brand'] ?? '') . ' ' . ($rep['model'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <div class="info-grid" style="margin-bottom:14px;">
            <div class="info-item">
                <label>ผู้รับเครื่องคืน</label>
                <div class="value"><?= htmlspecialchars($rep['borrower_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="info-item">
                <label>ห้องเรียน / ประเภท</label>
                <div class="value">
                    <?= htmlspecialchars($rep['class_name'] ?: ($rep['borrower_type'] === 'Teacher' ? 'ครู/บุคลากร' : '—'), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>

        <?php if ($rep['repair_notes']): ?>
        <div class="desc-box" style="margin-bottom:14px;">
            <label>ผลการซ่อม / หมายเหตุ</label>
            <p><?= nl2br(htmlspecialchars($rep['repair_notes'], ENT_QUOTES, 'UTF-8')) ?></p>
        </div>
        <?php endif; ?>

        <!-- สภาพเครื่องตอนรับคืน -->
        <div style="margin-bottom:14px;">
            <div style="font-size:9pt;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">สภาพเครื่องตอนรับคืน</div>
            <div style="display:flex;gap:24px;font-size:12pt;font-weight:600;color:#334155;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" style="width:16px;height:16px;"> ปกติ / ซ่อมสำเร็จ
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" style="width:16px;height:16px;"> ยังมีปัญหา (ระบุ)
                </label>
            </div>
            <div style="border-bottom:1.5px solid #e2e8f0;margin-top:10px;min-height:30px;"></div>
        </div>

        <div class="sign-grid">
            <div class="sign-box">
                <label>ผู้รับเครื่องคืน</label>
                <div class="sign-line"></div>
                <div class="sign-name">(<?= htmlspecialchars($rep['borrower_name'] ?? '.....................', ENT_QUOTES, 'UTF-8') ?>)</div>
                <div class="sign-date">วันที่ ....../....../......</div>
            </div>
            <div class="sign-box">
                <label>ผู้ส่งมอบ / ผู้ดูแลระบบ</label>
                <div class="sign-line"></div>
                <div class="sign-name">(.....................)</div>
                <div class="sign-date">วันที่ ....../....../......</div>
            </div>
        </div>

    </div>
</div>

<?php endif; ?>

</div><!-- /page-wrap -->

<script>
// Auto-open print dialog when opened in new tab
window.addEventListener('load', () => {
    // Small delay so images can load
    setTimeout(() => window.print(), 600);
});
</script>
</body>
</html>
