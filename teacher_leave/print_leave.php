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

function thaiDateFull($dateStr) {
    if (!$dateStr) return '...............';
    $ts = strtotime($dateStr);
    $months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

$fullName  = $request['firstname'] . ' ' . $request['lastname'];
$leaveType = $request['leave_type'];
$vacQuota  = $stats['vacation_quota'] ?? 10;

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบลา - <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 15mm 8mm 20mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page { box-shadow: none; margin: 0; padding: 0; width: 100%; }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
            font-size: 13.5pt;
            line-height: 1.45;
            color: #000;
            background: #e5e7eb;
        }

        .btn-print {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 24px;
            background: #1d4ed8; color: white; border: none;
            border-radius: 50px; font-family: 'Sarabun', sans-serif;
            font-size: 14pt; font-weight: 700;
            box-shadow: 0 8px 24px rgba(29,78,216,.35);
            cursor: pointer; z-index: 999;
            display: flex; align-items: center; gap: 8px;
            transition: background .2s;
        }
        .btn-print:hover { background: #1e40af; }

        .page {
            width: 210mm;
            min-height: 297mm;
            max-height: 297mm;
            padding: 8mm 15mm 8mm 20mm;
            margin: 8mm auto;
            background: white;
            box-shadow: 0 4px 24px rgba(0,0,0,.13);
            overflow: hidden;
        }

        .form-title {
            text-align: center;
            font-size: 16pt;
            font-weight: 800;
            margin-bottom: 6pt;
            letter-spacing: .3px;
        }

        .meta-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 5pt;
            font-size: 13.5pt;
        }
        .meta-row .field {
            display: inline-block;
            border-bottom: 1px dotted #555;
            min-width: 120pt;
            padding: 0 5px;
            font-weight: 600;
        }

        .row {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 3px;
            margin-bottom: 3pt;
            font-size: 13.5pt;
        }
        .label { white-space: nowrap; font-weight: 700; }
        .field {
            border-bottom: 1px dotted #555;
            flex: 1;
            padding: 0 5px;
            font-weight: 600;
            min-width: 50pt;
        }

        .ul { border-bottom: 1px dotted #555; display: inline-block; padding: 0 5px; font-weight: 600; }

        .cb-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8pt 16pt;
            font-size: 13.5pt;
            margin: 3pt 0 3pt 30pt;
        }
        .cb-item { display: flex; align-items: center; gap: 3px; }
        .cb-box {
            width: 13pt; height: 13pt; border: 1.5px solid #333;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 12pt; font-weight: 900; flex-shrink: 0;
        }
        .cb-box.checked::after { content: '✓'; }

        hr.dotted { border: none; border-top: 1px dotted #999; margin: 5pt 0; }

        /* Stats table */
        .stats-table {
            width: 100%; border-collapse: collapse;
            font-size: 12pt; margin: 5pt 0;
        }
        .stats-table th, .stats-table td {
            border: 1px solid #555;
            padding: 1.5pt 4pt; text-align: center;
        }
        .stats-table th { background: #f3f4f6; font-weight: 700; }
        .stats-table td:first-child { text-align: left; }

        /* Requester sig */
        .requester-sig {
            margin-top: 5pt;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            padding-right: 10pt;
        }
        .requester-sig-box { text-align: center; min-width: 200pt; }
        .sig-img { max-height: 34pt; max-width: 120pt; object-fit: contain; margin: 1pt auto; display: block; }

        /* Approval grid */
        .sig-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10pt;
            margin-top: 4pt;
        }
        .sig-box {
            border: 1px solid #888;
            padding: 5pt;
            font-size: 11.5pt;
            text-align: center;
            min-height: 80pt;
        }
        .sig-box .sig-title {
            font-weight: 700; font-size: 12pt;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2pt; margin-bottom: 4pt;
        }
        .sig-img-box { max-height: 34pt; max-width: 110pt; object-fit: contain; margin: 1pt auto; display: block; }
        .sig-line { border-bottom: 1px dotted #555; margin: 2pt 8pt 2pt; min-height: 28pt; }
        .sig-name { font-size: 11pt; font-weight: 600; }
        .sig-date { font-size: 10.5pt; color: #444; }

        .stamp {
            display: inline-block; border: 2px solid;
            padding: 1.5pt 7pt; font-weight: 800;
            border-radius: 4pt; margin: 3pt auto;
            transform: rotate(-4deg); font-size: 12.5pt;
        }
        .stamp-approved { color: #059669; border-color: #059669; }
        .stamp-pending   { color: #d97706; border-color: #d97706; }
        .stamp-rejected  { color: #dc2626; border-color: #dc2626; }
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">🖨️ พิมพ์ใบลา</button>

    <div class="page">

        <!-- ══ TITLE ══ -->
        <div class="form-title">ใบลาป่วย &nbsp; ลาคลอดบุตร &nbsp; ลากิจส่วนตัว &nbsp; ลาพักผ่อน</div>

        <!-- ══ เขียนที่ / วันที่ ══ -->
        <div class="meta-row">
            เขียนที่&nbsp;<span class="field" style="min-width:120pt;">โรงเรียนละลมวิทยา</span>&nbsp;&nbsp;
            วันที่&nbsp;<span class="field" style="min-width:105pt;"><?= thaiDateFull($request['created_at']) ?></span>
        </div>

        <!-- ══ เรียน ══ -->
        <div class="row">
            <span class="label">เรียน</span>
            <span class="field">ผู้อำนวยการโรงเรียนละลมวิทยา</span>
        </div>

        <!-- ══ ชื่อ / ตำแหน่ง ══ -->
        <div class="row">
            <span class="label">ข้าพเจ้า</span>
            <span class="field" style="max-width:180pt;"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="label">ตำแหน่ง</span>
            <span class="field"><?= htmlspecialchars($request['position'] ?: 'ครู', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- ══ สังกัด ══ -->
        <div class="row">
            <span class="label">สังกัด (กลุ่มสาระฯ / งาน)</span>
            <span class="field"><?= htmlspecialchars($request['subject_group'] ?: 'โรงเรียนละลมวิทยา', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- ══ ประเภทการลา (Checkbox) ══ -->
        <div class="row" style="margin-bottom:2pt;">
            <span class="label">มีความประสงค์ขอลา</span>
        </div>
        <div class="cb-line">
            <span class="cb-item"><span class="cb-box <?= $leaveType === 'sick' ? 'checked' : '' ?>"></span>&nbsp;ป่วย</span>
            <span class="cb-item"><span class="cb-box <?= $leaveType === 'maternity' ? 'checked' : '' ?>"></span>&nbsp;คลอดบุตร</span>
            <span class="cb-item"><span class="cb-box <?= $leaveType === 'personal' ? 'checked' : '' ?>"></span>&nbsp;กิจส่วนตัว</span>
            <span class="cb-item"><span class="cb-box <?= $leaveType === 'vacation' ? 'checked' : '' ?>"></span>&nbsp;พักผ่อน</span>
            <span class="cb-item"><span class="cb-box <?= $leaveType === 'other' ? 'checked' : '' ?>"></span>&nbsp;อื่นๆ</span>
        </div>

        <!-- ══ เหตุผล ══ -->
        <div class="row">
            <span class="label">เนื่องจาก</span>
            <span class="field"><?= htmlspecialchars($request['reason'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- ══ ตั้งแต่วันที่ / ถึงวันที่ ══ -->
        <div class="row">
            <span class="label">ตั้งแต่วันที่</span>
            <span class="field" style="min-width:120pt;"><?= thaiDateFull($request['date_start']) ?></span>
            <span class="label">ถึงวันที่</span>
            <span class="field" style="min-width:120pt;"><?= thaiDateFull($request['date_end']) ?></span>
            <span class="label">มีกำหนด</span>
            <span class="ul" style="min-width:28pt; text-align:center; font-size:15pt; font-weight:700;"><?= (float)$request['days_count'] ?></span>
            <span class="label">วัน</span>
        </div>

        <!-- ══ ที่อยู่ระหว่างลา ══ -->
        <div class="row" style="margin-bottom:3pt;">
            <span class="label">ที่อยู่ระหว่างลา / เบอร์โทร</span>
            <span class="field"><?= htmlspecialchars($request['contact_info'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <?php if (!empty($request['attachment_path'])): ?>
        <div style="font-size:11.5pt; color:#475569; margin-bottom:3pt; font-weight:600;">
            📎 มีเอกสารหลักฐานแนบประกอบ (ใบรับรองแพทย์/อื่นๆ)
        </div>
        <?php endif; ?>

        <hr class="dotted">

        <!-- ══ สถิติการลา ══ -->
        <div style="font-size:12.5pt; font-weight:700; margin-bottom:3pt;">
            สถิติการลาในปีงบประมาณ <?= $request['fiscal_year'] > 2500 ? $request['fiscal_year'] : ($request['fiscal_year'] + 543) ?>
        </div>
        <table class="stats-table">
            <thead>
                <tr>
                    <th width="36%">ประเภทการลา</th>
                    <th width="21%">ลามาแล้ว (วัน)</th>
                    <th width="21%">ลาครั้งนี้ (วัน)</th>
                    <th width="22%">รวมเป็น (วัน)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rows = [
                    ['ลาป่วย',                               'sick',     'sick_taken'],
                    ['ลากิจส่วนตัว',                         'personal', 'personal_taken'],
                    ["ลาพักผ่อน (โควตา {$vacQuota} วัน)", 'vacation', 'vacation_taken'],
                    ['ลาคลอดบุตร / อื่นๆ',                  'other',    'other_taken'],
                ];
                foreach ($rows as [$label, $type, $col]):
                    $isCurrent = $leaveType === $type || $type === 'other' && in_array($leaveType, ['maternity','other']);
                    $taken  = (float)($stats[$col] ?? 0);
                    $before = $isCurrent ? max(0, $taken - (float)$request['days_count']) : $taken;
                    $curr   = $isCurrent ? (float)$request['days_count'] : '—';
                    $total  = $isCurrent ? $taken : $taken;
                ?>
                <tr>
                    <td><?= $label ?></td>
                    <td><?= $before ?></td>
                    <td><?= $curr ?></td>
                    <td><?= $total ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- ══ จึงเรียนมาเพื่อโปรดพิจารณา ══ -->
        <p style="font-size:13.5pt; text-indent:30pt; margin: 5pt 0 4pt;">จึงเรียนมาเพื่อโปรดพิจารณาอนุญาต</p>

        <!-- ══ ลายเซ็นผู้ขอลา ══ -->
        <div class="requester-sig">
            <div class="requester-sig-box">
                <?php if ($request['signature_path']): ?>
                <img src="<?= $base_path ?>/<?= htmlspecialchars($request['signature_path'], ENT_QUOTES, 'UTF-8') ?>" class="sig-img" alt="ลายเซ็น">
                <?php else: ?>
                <div style="height:24pt;"></div>
                <?php endif; ?>
                <p style="font-size:13pt;">(ลงชื่อ)...................................................ผู้ขอลา</p>
                <p style="margin-top:3pt; font-size:13pt;">( <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?> )</p>
                <p style="font-size:12pt; color:#333; margin-top:3pt;">วันที่ <?= thaiDateFull($request['created_at']) ?></p>
            </div>
        </div>

        <hr class="dotted" style="margin-top:6pt;">

        <!-- ══ กล่องอนุมัติ 2 ช่อง ══ -->
        <div class="sig-grid">

            <!-- Lv.1 เจ้าหน้าที่ -->
            <div class="sig-box">
                <div class="sig-title">ความเห็นเจ้าหน้าที่<br><small style="font-weight:400; font-size:11pt;">(ตรวจสอบเอกสาร)</small></div>
                <?php if (isset($approvalMap[1]) && $approvalMap[1]['status'] != 0): ?>
                    <div><span class="stamp <?= $approvalMap[1]['status'] == 1 ? 'stamp-approved' : 'stamp-rejected' ?>">
                        <?= $approvalMap[1]['status'] == 1 ? '✓ ตรวจสอบแล้วถูกต้อง' : '✗ ไม่ถูกต้อง' ?>
                    </span></div>
                    <?php if (!empty($approvalMap[1]['comment'])): ?>
                    <p style="font-size:10.5pt; font-style:italic; margin-top:3pt;"><?= htmlspecialchars($approvalMap[1]['comment'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($approvalMap[1]['signature_path'])): ?>
                    <img src="<?= $base_path ?>/<?= htmlspecialchars($approvalMap[1]['signature_path'], ENT_QUOTES, 'UTF-8') ?>" class="sig-img-box" alt="ลายเซ็นเจ้าหน้าที่">
                    <?php else: ?>
                    <div class="sig-line"></div>
                    <?php endif; ?>
                    <p class="sig-name" style="margin-top:5pt;">(<?= htmlspecialchars(($approvalMap[1]['firstname'] ?? '') . ' ' . ($approvalMap[1]['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</p>
                    <p class="sig-date" style="margin-top:2pt;">วันที่ <?= !empty($approvalMap[1]['approved_at']) ? thaiDateFull($approvalMap[1]['approved_at']) : '......./......./ .......' ?></p>
                <?php else: ?>
                    <div><span class="stamp stamp-pending">รอตรวจสอบ</span></div>
                    <div class="sig-line"></div>
                    <p class="sig-name">(..................................................)</p>
                    <p class="sig-date" style="margin-top:2pt;">วันที่ ......./......./ .......</p>
                <?php endif; ?>
            </div>

            <!-- Lv.2 ผู้อำนวยการ -->
            <div class="sig-box">
                <div class="sig-title">คำสั่ง<br>ผู้อำนวยการ / รองผู้อำนวยการ</div>
                <?php if (isset($approvalMap[2]) && $approvalMap[2]['status'] != 0): ?>
                    <div><span class="stamp <?= $approvalMap[2]['status'] == 1 ? 'stamp-approved' : 'stamp-rejected' ?>">
                        <?= $approvalMap[2]['status'] == 1 ? '✓ อนุญาต' : '✗ ไม่อนุญาต' ?>
                    </span></div>
                    <?php if ($approvalMap[2]['status'] == 1 && !empty($approvalMap[2]['signature_path'])): ?>
                    <img src="<?= $base_path ?>/<?= htmlspecialchars($approvalMap[2]['signature_path'], ENT_QUOTES, 'UTF-8') ?>" class="sig-img-box" alt="ลายเซ็น ผอ./รองฯ">
                    <?php else: ?>
                    <div style="height:20pt;"></div>
                    <?php endif; ?>
                    <?php if (!empty($approvalMap[2]['comment'])): ?>
                    <p style="font-size:10.5pt; font-style:italic;"><?= htmlspecialchars($approvalMap[2]['comment'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <p class="sig-name" style="margin-top:5pt;">(<?= htmlspecialchars(($approvalMap[2]['firstname'] ?? '') . ' ' . ($approvalMap[2]['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</p>
                    <p class="sig-date" style="margin-top:2pt;">วันที่ <?= !empty($approvalMap[2]['approved_at']) ? thaiDateFull($approvalMap[2]['approved_at']) : '......./......./ .......' ?></p>
                <?php else: ?>
                    <div><span class="stamp stamp-pending">รออนุมัติ ผอ./รองฯ</span></div>
                    <div class="sig-line"></div>
                    <p class="sig-name">(..................................................)</p>
                    <p class="sig-date" style="margin-top:2pt;">วันที่ ......./......./ .......</p>
                <?php endif; ?>
            </div>

        </div><!-- /sig-grid -->

    </div><!-- /page -->
</body>
</html>
