<?php
/**
 * teacher_leave/print_leave.php
 * Official Leave Report (Print View) — แบบฟอร์มราชการแนวตั้ง A4
 */
session_start();
require_once '../config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}

$requestId = (int)($_GET['id'] ?? 0);
if (!$requestId) die('ไม่พบรหัสใบลา');

try {
    $pdo = getPdo();

    $stmt = $pdo->prepare("
        SELECT r.*, u.firstname, u.lastname, u.position, u.subject_group
        FROM tl_requests r
        JOIN llw_users u ON r.user_id = u.user_id
        WHERE r.id = ?
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    if (!$request) die('ไม่พบข้อมูลใบลา');

    $stats = getUserLeaveStats($request['user_id'], $request['fiscal_year'], $pdo);

    $stmtApp = $pdo->prepare("
        SELECT a.*, u.firstname, u.lastname
        FROM tl_approvals a
        LEFT JOIN llw_users u ON a.approver_id = u.user_id
        WHERE a.request_id = ?
        ORDER BY a.level ASC
    ");
    $stmtApp->execute([$requestId]);
    $approvals = $stmtApp->fetchAll();

    $approvalMap = [];
    foreach ($approvals as $a) $approvalMap[$a['level']] = $a;

} catch (Exception $e) {
    die('เกิดข้อผิดพลาด');
}

// แปลงวันที่เป็น พ.ศ.
function thaiDateFull($dateStr) {
    if (!$dateStr) return '...............';
    $ts = strtotime($dateStr);
    $months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

$fullName = $request['firstname'] . ' ' . $request['lastname'];
$leaveType = $request['leave_type'];

// สถิติ: ลามาแล้วก่อนครั้งนี้
function statBefore($stats, $type, $currentDays) {
    $map = ['sick'=>'sick_taken','personal'=>'personal_taken','vacation'=>'vacation_taken','other'=>'other_taken'];
    $key = $map[$type] ?? 'other_taken';
    return max(0, (float)($stats[$key] ?? 0) - (float)$currentDays);
}

$statBefore = statBefore($stats, $leaveType, $request['days_count']);
$statTotal  = $statBefore + $request['days_count'];

// quota ลาพักผ่อน
$vacQuota = $stats['vacation_quota'] ?? 10;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบลา - <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 15mm 18mm 15mm 18mm; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page { box-shadow: none; margin: 0; padding: 0; width: 100%; }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
            font-size: 15pt;
            line-height: 1.7;
            color: #000;
            background: #e5e7eb;
        }

        /* ── Print Button ── */
        .btn-print {
            position: fixed; bottom: 24px; right: 24px;
            padding: 14px 28px;
            background: #1d4ed8; color: white; border: none;
            border-radius: 50px; font-family: 'Sarabun', sans-serif;
            font-size: 15pt; font-weight: 700;
            box-shadow: 0 8px 24px rgba(29,78,216,.35);
            cursor: pointer; z-index: 999;
            display: flex; align-items: center; gap: 8px;
            transition: background .2s;
        }
        .btn-print:hover { background: #1e40af; }

        /* ── Page ── */
        .page {
            width: 210mm; min-height: 297mm;
            padding: 14mm 18mm 14mm 18mm;
            margin: 12mm auto;
            background: white;
            box-shadow: 0 4px 24px rgba(0,0,0,.13);
            position: relative;
        }

        /* ── Header ── */
        .form-title {
            text-align: center;
            font-size: 18pt;
            font-weight: 800;
            margin-bottom: 10pt;
            letter-spacing: .5px;
        }

        /* ── Meta (เขียนที่ / วันที่) ── */
        .meta-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10pt;
            font-size: 14.5pt;
        }
        .meta-row .field { display: inline-block; border-bottom: 1px dotted #555; min-width: 140pt; padding: 0 6px; font-weight: 600; }

        /* ── Field Rows ── */
        .row { display: flex; align-items: baseline; gap: 6px; margin-bottom: 6pt; font-size: 14.5pt; }
        .label { white-space: nowrap; font-weight: 700; }
        .field { border-bottom: 1px dotted #555; flex: 1; padding: 0 6px; font-weight: 600; min-width: 60pt; }
        .field-sm { border-bottom: 1px dotted #555; min-width: 80pt; padding: 0 6px; font-weight: 600; display: inline-block; }

        /* ── Body paragraph ── */
        .body-para { font-size: 14.5pt; text-indent: 36pt; line-height: 1.9; margin-bottom: 4pt; }

        /* ── Checkbox line ── */
        .cb-line { display: flex; flex-wrap: wrap; align-items: center; gap: 10pt 18pt; font-size: 14pt; margin: 6pt 0 6pt 36pt; }
        .cb-item { display: flex; align-items: center; gap: 4px; }
        .cb-box {
            width: 14pt; height: 14pt; border: 1.5px solid #333;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 13pt; font-weight: 900; flex-shrink: 0;
        }
        .cb-box.checked::after { content: '✓'; }

        /* ── Dotted underline span ── */
        .ul { border-bottom: 1px dotted #555; display: inline-block; padding: 0 6px; font-weight: 600; }
        .ul-lg { border-bottom: 1px dotted #555; display: inline-block; padding: 0 6px; font-weight: 600; min-width: 200pt; }

        /* ── Stats Table ── */
        .stats-table {
            width: 100%; border-collapse: collapse;
            font-size: 13pt; margin: 10pt 0;
        }
        .stats-table th, .stats-table td {
            border: 1px solid #555;
            padding: 4pt 6pt; text-align: center;
        }
        .stats-table th { background: #f3f4f6; font-weight: 700; }
        .stats-table td:first-child { text-align: left; }

        /* ── Signature Grid ── */
        .sig-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20pt;
            margin-top: 12pt;
        }
        .sig-box {
            border: 1px solid #888;
            padding: 8pt;
            font-size: 12.5pt;
            text-align: center;
            min-height: 110pt;
        }
        .sig-box .sig-title {
            font-weight: 700; font-size: 13pt;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4pt; margin-bottom: 8pt;
            text-align: center;
        }
        .sig-img { max-height: 50pt; max-width: 140pt; object-fit: contain; margin: 4pt auto 2pt; display: block; }
        .sig-line { border-bottom: 1px dotted #555; margin: 4pt 10pt 2pt; min-height: 40pt; }
        .sig-name { font-size: 12pt; font-weight: 600; }
        .sig-date { font-size: 11pt; color: #444; }

        .stamp {
            display: inline-block; border: 2px solid;
            padding: 2pt 8pt; font-weight: 800;
            border-radius: 4pt; margin: 4pt auto;
            transform: rotate(-4deg); font-size: 13pt;
        }
        .stamp-approved { color: #059669; border-color: #059669; }
        .stamp-pending   { color: #d97706; border-color: #d97706; }
        .stamp-rejected  { color: #dc2626; border-color: #dc2626; }

        /* ── Requester Signature (right-aligned) ── */
        .requester-sig {
            margin-top: 20pt;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            padding-right: 20pt;
        }
        .requester-sig-box {
            text-align: center;
            min-width: 220pt;
        }
        .requester-sig img.sig-img { margin: 0 auto; }

        hr.dotted { border: none; border-top: 1px dotted #999; margin: 10pt 0; }

        .section-note { font-size: 13pt; color: #333; margin-bottom: 6pt; }
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">🖨️ พิมพ์ใบลา</button>

    <div class="page">

        <!-- ══ TITLE ══ -->
        <div class="form-title">ใบลาป่วย  ลาคลอดบุตร  ลากิจส่วนตัว  ลาพักผ่อน</div>

        <!-- ══ เขียนที่ / วันที่ ══ -->
        <div class="meta-row">
            เขียนที่&nbsp;<span class="field" style="min-width:130pt;">โรงเรียนละลมวิทยา</span>&nbsp;&nbsp;
            วันที่&nbsp;<span class="field" style="min-width:110pt;"><?= thaiDateFull($request['created_at']) ?></span>
        </div>

        <!-- ══ ชื่อ / ตำแหน่ง ══ -->
        <div class="row">
            <span class="label">ชื่อ</span>
            <span class="field"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="label">ตำแหน่ง</span>
            <p><strong>เนื่องจาก:</strong> <?= htmlspecialchars($request['reason'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (!empty($request['attachment_path'])): ?>
                <p style="color: #64748b; font-size: 10pt; font-weight: bold; margin-top: 4pt;">
                    📎 มีเอกสารหลักฐานแนบประกอบ (ใบรับรองแพทย์/อื่นๆ)
                </p>
            <?php endif; ?>
        </div>
        <div class="row">
            <span class="label">สังกัด (กลุ่มสาระฯ / งาน)</span>
            <span class="field"><?= htmlspecialchars($request['subject_group'] ?: 'โรงเรียนละลมวิทยา', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <hr class="dotted">

        <!-- ══ เรื่อง / ประเภทลา (Checkbox) ══ -->
        <div class="row" style="margin-bottom:2pt;">
            <span class="label">มีความประสงค์ขอลา</span>
        </div>
        <div class="cb-line">
            <span class="cb-item"><span class="cb-box <?= $leaveType === 'sick' ? 'checked' : '' ?>"></span> ป่วย</span>
            <span class="cb-item"><span class="cb-box <?= $leaveType === 'maternity' ? 'checked' : '' ?>"></span> คลอดบุตร</span>
            <span class="cb-item"><span class="cb-box <?= $leaveType === 'personal' ? 'checked' : '' ?>"></span> กิจส่วนตัว</span>
            <span class="cb-item"><span class="cb-box <?= $leaveType === 'vacation' ? 'checked' : '' ?>"></span> พักผ่อน</span>
            <span class="cb-item"><span class="cb-box <?= $leaveType === 'other' ? 'checked' : '' ?>"></span> อื่นๆ</span>
        </div>

        <!-- ══ เหตุผล ══ -->
        <div class="row">
            <span class="label">เนื่องจาก</span>
            <span class="field" style="min-width:300pt;"><?= htmlspecialchars($request['reason'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- ══ เรียน ══ -->
        <div class="row">
            <span class="label">เรียน</span>
            <span class="field">ผู้อำนวยการโรงเรียนละลมวิทยา</span>
        </div>

        <!-- ══ ตั้งแต่วันที่ / ถึงวันที่ ══ -->
        <div class="row">
            <span class="label">ตั้งแต่วันที่</span>
            <span class="field" style="min-width:130pt;"><?= thaiDateFull($request['date_start']) ?></span>
            &nbsp;
            <span class="label">ถึงวันที่</span>
            <span class="field" style="min-width:130pt;"><?= thaiDateFull($request['date_end']) ?></span>
            &nbsp;
            <span class="label">มีกำหนด</span>
            <span class="ul" style="min-width:30pt; text-align:center; font-size:16pt;"><?= (float)$request['days_count'] ?></span>
            <span class="label">วัน</span>
        </div>

        <!-- ══ ช่วงเวลาที่ลา ══ -->
        <div class="row" style="font-size:14pt;">
            <span class="label">ช่วงที่ลา</span>
            <span class="cb-item"><span class="cb-box"></span>&nbsp;เช้า</span>
            <span class="cb-item"><span class="cb-box"></span>&nbsp;บ่าย</span>
            <span class="cb-item"><span class="cb-box checked"></span>&nbsp;ทั้งวัน</span>
        </div>

        <!-- ══ ที่อยู่ระหว่างลา ══ -->
        <div class="row">
            <span class="label">ที่อยู่ระหว่างลา / เบอร์โทร</span>
            <span class="field"><?= htmlspecialchars($request['contact_info'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <hr class="dotted">

        <!-- ══ สถิติการลา ══ -->
        <div class="section-note"><strong>สถิติการลาในปีงบประมาณ <?= $request['fiscal_year'] > 2500 ? $request['fiscal_year'] : ($request['fiscal_year'] + 543) ?></strong></div>
        <table class="stats-table">
            <thead>
                <tr>
                    <th width="34%">ประเภทการลา</th>
                    <th width="22%">ลามาแล้ว (วัน)</th>
                    <th width="22%">ลาครั้งนี้ (วัน)</th>
                    <th width="22%">รวมเป็น (วัน)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>ลาป่วย</td>
                    <td><?= ($leaveType === 'sick') ? max(0, (float)$stats['sick_taken'] - (float)$request['days_count']) : (float)($stats['sick_taken'] ?? 0) ?></td>
                    <td><?= ($leaveType === 'sick') ? (float)$request['days_count'] : '—' ?></td>
                    <td><?= ($leaveType === 'sick') ? (float)$stats['sick_taken'] : (float)($stats['sick_taken'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>ลากิจส่วนตัว</td>
                    <td><?= ($leaveType === 'personal') ? max(0, (float)$stats['personal_taken'] - (float)$request['days_count']) : (float)($stats['personal_taken'] ?? 0) ?></td>
                    <td><?= ($leaveType === 'personal') ? (float)$request['days_count'] : '—' ?></td>
                    <td><?= ($leaveType === 'personal') ? (float)$stats['personal_taken'] : (float)($stats['personal_taken'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>ลาพักผ่อน (โควตา <?= $vacQuota ?> วัน)</td>
                    <td><?= ($leaveType === 'vacation') ? max(0, (float)$stats['vacation_taken'] - (float)$request['days_count']) : (float)($stats['vacation_taken'] ?? 0) ?></td>
                    <td><?= ($leaveType === 'vacation') ? (float)$request['days_count'] : '—' ?></td>
                    <td><?= ($leaveType === 'vacation') ? (float)$stats['vacation_taken'] : (float)($stats['vacation_taken'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>ลาคลอดบุตร / อื่นๆ</td>
                    <td><?= in_array($leaveType, ['maternity','other']) ? max(0, (float)$stats['other_taken'] - (float)$request['days_count']) : (float)($stats['other_taken'] ?? 0) ?></td>
                    <td><?= in_array($leaveType, ['maternity','other']) ? (float)$request['days_count'] : '—' ?></td>
                    <td><?= in_array($leaveType, ['maternity','other']) ? (float)$stats['other_taken'] : (float)($stats['other_taken'] ?? 0) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- ══ จึงเรียนมาเพื่อโปรดพิจารณาอนุญาต ══ -->
        <p class="body-para" style="margin-top:8pt;">จึงเรียนมาเพื่อโปรดพิจารณาอนุญาต</p>

        <!-- ══ ลายเซ็นผู้ขอลา ══ -->
        <div class="requester-sig">
            <div class="requester-sig-box">
                <?php if ($request['signature_path']): ?>
                <img src="<?= $base_path ?>/<?= htmlspecialchars($request['signature_path'], ENT_QUOTES, 'UTF-8') ?>" class="sig-img" alt="ลายเซ็น">
                <?php else: ?>
                    <div style="height:44pt;"></div>
                <?php endif; ?>
                <p style="margin-top:4pt;">(ลงชื่อ).......................................................... ผู้ขอลา</p>
                <p style="margin-top:6pt;">( <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?> )</p>
                <p style="font-size:13pt; color:#333; margin-top:4pt;">วันที่ <?= thaiDateFull($request['created_at']) ?></p>
            </div>
        </div>

        <hr class="dotted" style="margin-top:12pt;">

        <!-- ══ กล่องอนุมัติ 2 ช่อง ══ -->
        <div class="sig-grid" style="margin-top:10pt;">

            <!-- Lv.1 เจ้าหน้าที่ -->
            <div class="sig-box">
                <div class="sig-title">ความเห็นเจ้าหน้าที่<br><small style="font-weight:400; font-size:11pt;">(ตรวจสอบเอกสาร)</small></div>
                <?php if (isset($approvalMap[1]) && $approvalMap[1]['status'] != 0): ?>
                    <div style="text-align:center;">
                        <span class="stamp <?= $approvalMap[1]['status'] == 1 ? 'stamp-approved' : 'stamp-rejected' ?>">
                            <?= $approvalMap[1]['status'] == 1 ? '✓ ตรวจสอบแล้วถูกต้อง' : '✗ ไม่ถูกต้อง' ?>
                        </span>
                    </div>
                    <?php if (!empty($approvalMap[1]['comment'])): ?>
                        <p style="font-size:11pt; font-style:italic; margin-top:4pt;"><?= htmlspecialchars($approvalMap[1]['comment'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($approvalMap[1]['signature_path'])): ?>
                        <img src="<?= $base_path ?>/<?= htmlspecialchars($approvalMap[1]['signature_path'], ENT_QUOTES, 'UTF-8') ?>" class="sig-img" alt="ลายเซ็นเจ้าหน้าที่">
                    <?php else: ?>
                        <div class="sig-line"></div>
                    <?php endif; ?>
                    <p class="sig-name" style="margin-top:8pt;">(<?= htmlspecialchars(($approvalMap[1]['firstname'] ?? '') . ' ' . ($approvalMap[1]['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</p>
                    <p class="sig-date" style="margin-top:4pt;">วันที่ <?= !empty($approvalMap[1]['approved_at']) ? thaiDateFull($approvalMap[1]['approved_at']) : '......./......./ .......' ?></p>
                <?php else: ?>
                    <div style="text-align:center; margin:10pt 0;">
                        <span class="stamp stamp-pending">รอตรวจสอบ</span>
                    </div>
                    <div class="sig-line"></div>
                    <p class="sig-name">(..................................................)</p>
                    <p class="sig-date" style="margin-top:4pt;">วันที่ ......./......./ .......</p>
                <?php endif; ?>
            </div>

            <!-- Lv.2 ผู้อำนวยการ -->
            <div class="sig-box">
                <div class="sig-title">คำสั่ง<br>ผู้อำนวยการ/รองผู้อำนวยการ</div>
                <?php if (isset($approvalMap[2]) && $approvalMap[2]['status'] != 0): ?>
                    <div style="text-align:center;">
                        <span class="stamp <?= $approvalMap[2]['status'] == 1 ? 'stamp-approved' : 'stamp-rejected' ?>">
                            <?= $approvalMap[2]['status'] == 1 ? '✓ อนุญาต' : '✗ ไม่อนุญาต' ?>
                        </span>
                    </div>
                    <?php if ($approvalMap[2]['status'] == 1 && !empty($approvalMap[2]['signature_path'])): ?>
                        <img src="<?= $base_path ?>/<?= htmlspecialchars($approvalMap[2]['signature_path'], ENT_QUOTES, 'UTF-8') ?>" class="sig-img" alt="ลายเซ็น ผอ./รองฯ">
                    <?php else: ?>
                        <div style="height:36pt;"></div>
                    <?php endif; ?>
                    <?php if (!empty($approvalMap[2]['comment'])): ?>
                        <p style="font-size:11pt; font-style:italic;"><?= htmlspecialchars($approvalMap[2]['comment'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <p class="sig-name" style="margin-top:8pt;">(<?= htmlspecialchars(($approvalMap[2]['firstname'] ?? '') . ' ' . ($approvalMap[2]['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</p>
                    <p class="sig-date" style="margin-top:4pt;">วันที่ <?= !empty($approvalMap[2]['approved_at']) ? thaiDateFull($approvalMap[2]['approved_at']) : '......./......./ .......' ?></p>
                <?php else: ?>
                    <div style="text-align:center; margin:10pt 0;">
                        <span class="stamp stamp-pending">รออนุมัติ ผอ./รองฯ</span>
                    </div>
                    <div class="sig-line"></div>
                    <p class="sig-name">(..................................................)</p>
                    <p class="sig-date" style="margin-top:4pt;">วันที่ ......./......./ .......</p>
                <?php endif; ?>
            </div>

        </div><!-- /sig-grid -->

    </div><!-- /page -->
</body>
</html>
