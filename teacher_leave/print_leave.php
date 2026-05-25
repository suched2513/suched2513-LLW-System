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

    // ── ประวัติลาครั้งสุดท้ายประเภทเดียวกัน ──
    $stmtPrev = $pdo->prepare("
        SELECT date_start, date_end, days_count
        FROM tl_requests
        WHERE user_id = ?
          AND leave_type = ?
          AND id < ?
          AND status IN ('approved','pending')
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtPrev->execute([$request['user_id'], $request['leave_type'], $requestId]);
    $prevLeave = $stmtPrev->fetch();

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
    if (!$dateStr) return '......................................';
    $ts = strtotime($dateStr);
    $months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

$fullName  = $request['firstname'] . ' ' . $request['lastname'];
$lt        = $request['leave_type'];
$vacQuota  = $stats['vacation_quota'] ?? 10;

// label สำหรับประเภทลา
$typeLabels = [
    'sick'      => 'ป่วย',
    'personal'  => 'กิจส่วนตัว',
    'vacation'  => 'พักผ่อน',
    'maternity' => 'คลอดบุตร',
    'other'     => 'อื่นๆ',
];
$cbTypes = ['sick', 'personal', 'vacation', 'maternity', 'other'];

function cb($checked) {
    return '<span class="cb-box' . ($checked ? ' checked' : '') . '"></span>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบลา - <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 15mm 18mm 12mm 22mm; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page { box-shadow: none; margin: 0; padding: 0; }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
            font-size: 14pt;
            line-height: 1.5;
            color: #000;
            background: #e5e7eb;
        }

        .btn-print {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 24px; background: #1d4ed8; color: white; border: none;
            border-radius: 50px; font-family: 'Sarabun', sans-serif;
            font-size: 14pt; font-weight: 700; cursor: pointer; z-index: 999;
            box-shadow: 0 8px 24px rgba(29,78,216,.35);
            display: flex; align-items: center; gap: 8px; transition: background .2s;
        }
        .btn-print:hover { background: #1e40af; }

        .page {
            width: 210mm; min-height: 297mm;
            padding: 15mm 18mm 12mm 22mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 4px 24px rgba(0,0,0,.13);
        }

        /* ── Title ── */
        .form-title {
            text-align: center; font-size: 16pt; font-weight: 800;
            margin-bottom: 10pt; letter-spacing: .5px;
        }

        /* ── Dotted underline ── */
        .ul {
            border-bottom: 1px solid #000;
            display: inline-block; padding: 0 4px;
            font-weight: 600; vertical-align: baseline;
        }
        .ul-flex { border-bottom: 1px solid #000; flex: 1; padding: 0 4px; font-weight: 600; }

        /* ── Checkbox ── */
        .cb-box {
            width: 14pt; height: 14pt; border: 1.5px solid #000;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 12pt; font-weight: 900; flex-shrink: 0;
            vertical-align: middle; margin-bottom: 1px;
        }
        .cb-box.checked::after { content: '✓'; }

        /* ── Row helpers ── */
        .row {
            display: flex; align-items: baseline; gap: 5px;
            margin-bottom: 5pt; font-size: 14pt;
        }
        .label { white-space: nowrap; font-weight: 700; }
        .cb-inline { display: flex; align-items: center; gap: 4px; white-space: nowrap; }
        .cb-inline + .cb-inline { margin-left: 12pt; }

        /* ── ขอลา section (vertical checkboxes) ── */
        .req-grid {
            display: grid;
            grid-template-columns: 60pt 1fr;
            gap: 0; margin-bottom: 5pt;
        }
        .req-label { font-weight: 700; padding-top: 1pt; }
        .req-body { }
        .req-cb-item {
            display: flex; align-items: center; gap: 6px;
            line-height: 1.7;
        }
        .req-reason {
            display: flex; align-items: baseline; gap: 5px;
            margin-top: 2pt; font-size: 14pt;
        }

        /* ── Stats Table ── */
        .stats-table {
            width: 100%; border-collapse: collapse;
            font-size: 12.5pt; margin: 5pt 0;
        }
        .stats-table th, .stats-table td {
            border: 1px solid #555; padding: 2pt 5pt; text-align: center;
        }
        .stats-table th { background: #f3f4f6; font-weight: 700; }
        .stats-table td:first-child { text-align: left; }

        /* ── Requester sig ── */
        .requester-sig {
            margin-top: 6pt;
            display: flex; flex-direction: column; align-items: flex-end; padding-right: 10pt;
        }
        .requester-sig-box { text-align: center; min-width: 200pt; }
        .sig-img { max-height: 34pt; max-width: 120pt; object-fit: contain; margin: 1pt auto; display: block; }

        /* ── Approval Grid ── */
        .sig-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 10pt; margin-top: 6pt;
        }
        .sig-box {
            border: 1px solid #888; padding: 5pt;
            font-size: 11.5pt; text-align: center; min-height: 80pt;
        }
        .sig-box .sig-title {
            font-weight: 700; font-size: 12pt;
            border-bottom: 1px solid #ccc; padding-bottom: 2pt; margin-bottom: 4pt;
        }
        .sig-img-box { max-height: 34pt; max-width: 110pt; object-fit: contain; margin: 1pt auto; display: block; }
        .sig-line { border-bottom: 1px dotted #555; margin: 2pt 8pt; min-height: 28pt; }
        .sig-name { font-size: 11pt; font-weight: 600; }
        .sig-date { font-size: 10.5pt; color: #444; }

        .stamp {
            display: inline-block; border: 2px solid;
            padding: 1.5pt 7pt; font-weight: 800; border-radius: 4pt;
            margin: 3pt auto; transform: rotate(-4deg); font-size: 12.5pt;
        }
        .stamp-approved { color: #059669; border-color: #059669; }
        .stamp-pending   { color: #d97706; border-color: #d97706; }
        .stamp-rejected  { color: #dc2626; border-color: #dc2626; }

        hr.dotted { border: none; border-top: 1px dotted #999; margin: 6pt 0; }
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">🖨️ พิมพ์ใบลา</button>

    <div class="page">

        <!-- ══ TITLE ══ -->
        <div class="form-title">ใบลาป่วย&ensp;ลาคลอดบุตร&ensp;ลากิจส่วนตัว&ensp;ลาพักผ่อน</div>

        <!-- ══ วันที่เขียน (ขวา) ══ -->
        <div style="text-align:right; margin-bottom:6pt; font-size:14pt;">
            วันที่&nbsp;<span class="ul" style="min-width:140pt;"><?= thaiDateFull($request['created_at']) ?></span>
        </div>

        <!-- ══ เรื่อง ══ -->
        <div class="row" style="margin-bottom:4pt;">
            <span class="label">เรื่อง</span>
            <span style="margin-right:4pt;">ขออนุญาตลา</span>
            <?php foreach ($cbTypes as $t): ?>
            <span class="cb-inline"><?= cb($lt === $t) ?>&nbsp;<?= $typeLabels[$t] ?></span>
            <?php endforeach; ?>
        </div>

        <!-- ══ เรียน ══ -->
        <div class="row" style="margin-bottom:10pt;">
            <span class="label">เรียน</span>
            <span class="ul-flex">ผู้อำนวยการโรงเรียนละลมวิทยา</span>
        </div>

        <!-- ══ ข้าพเจ้า / ตำแหน่ง ══ -->
        <div style="display:flex; align-items:baseline; gap:5px; margin-bottom:5pt; font-size:14pt; padding-left:40pt;">
            <span class="label">ข้าพเจ้า</span>
            <span class="ul" style="min-width:180pt;"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="label">ตำแหน่ง</span>
            <span class="ul" style="min-width:120pt; flex:1;"><?= htmlspecialchars($request['position'] ?: 'ครู', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- ══ สังกัด ══ -->
        <div class="row" style="margin-bottom:8pt;">
            <span class="label">สังกัด</span>
            <span class="ul-flex"><?= htmlspecialchars($request['subject_group'] ?: 'โรงเรียนละลมวิทยา สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- ══ ขอลา (vertical) + เนื่องจาก ══ -->
        <div class="req-grid">
            <div class="req-label">ขอลา</div>
            <div class="req-body">
                <?php foreach ($cbTypes as $t): ?>
                <div class="req-cb-item"><?= cb($lt === $t) ?>&nbsp;<?= $typeLabels[$t] ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="row">
            <span class="label">เนื่องจาก</span>
            <span class="ul-flex"><?= htmlspecialchars($request['reason'] ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <!-- ══ ตั้งแต่วันที่ / ถึงวันที่ ══ -->
        <div class="row" style="margin-bottom:4pt;">
            <span class="label">ตั้งแต่วันที่</span>
            <span class="ul" style="min-width:130pt;"><?= thaiDateFull($request['date_start']) ?></span>
            <span class="label">ถึงวันที่</span>
            <span class="ul" style="min-width:130pt;"><?= thaiDateFull($request['date_end']) ?></span>
            <span class="label">มีกำหนด</span>
            <span class="ul" style="min-width:26pt; text-align:center; font-weight:800;"><?= (float)$request['days_count'] ?></span>
            <span>วัน</span>
            <span style="margin-left:2pt;">(</span>
            <span class="ul" style="min-width:26pt; text-align:center; font-weight:800;"><?= (float)$request['days_count'] ?></span>
            <span>วันทำการ)</span>
        </div>

        <!-- ══ ครั้งสุดท้าย ══ -->
        <div class="row" style="margin-bottom:4pt; flex-wrap:wrap;">
            <span class="label">ข้าพเจ้าได้ลา</span>
            <?php foreach ($cbTypes as $t): ?>
            <span class="cb-inline"><?= cb($lt === $t) ?>&nbsp;<?= $typeLabels[$t] ?></span>
            <?php endforeach; ?>
        </div>
        <div class="row" style="margin-bottom:5pt;">
            <span>ครั้งสุดท้ายตั้งแต่วันที่</span>
            <span class="ul" style="min-width:130pt;"><?= $prevLeave ? thaiDateFull($prevLeave['date_start']) : 'ไม่เคยลาประเภทนี้' ?></span>
            <?php if ($prevLeave): ?>
            <span class="label">ถึงวันที่</span>
            <span class="ul" style="min-width:130pt;"><?= thaiDateFull($prevLeave['date_end']) ?></span>
            <span class="label">มีกำหนด</span>
            <span class="ul" style="min-width:26pt; text-align:center; font-weight:800;"><?= (float)$prevLeave['days_count'] ?></span>
            <span>วัน</span>
            <span style="margin-left:2pt;">(</span>
            <span class="ul" style="min-width:26pt; text-align:center; font-weight:800;"><?= (float)$prevLeave['days_count'] ?></span>
            <span>วันทำการ)</span>
            <?php endif; ?>
        </div>

        <!-- ══ ที่อยู่ระหว่างลา ══ -->
        <div class="row" style="margin-bottom:3pt;">
            <span class="label">ในระหว่างลาจะติดต่อข้าพเจ้าได้ที่</span>
            <span class="ul-flex"><?= htmlspecialchars($request['contact_info'] ?: '..............................................', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="row" style="margin-bottom:8pt;">
            <span class="label">หมายเลขโทรศัพท์</span>
            <span class="ul-flex"><?= htmlspecialchars($request['contact_info'] ?: '..............................................', ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <?php if (!empty($request['attachment_path'])): ?>
        <div style="font-size:11.5pt; color:#475569; margin-bottom:4pt; font-weight:600;">
            📎 มีเอกสารหลักฐานแนบประกอบ (ใบรับรองแพทย์/อื่นๆ)
        </div>
        <?php endif; ?>

        <!-- ══ จึงเรียน ══ -->
        <p style="font-size:14pt; text-indent:30pt; margin-bottom:4pt;">จึงเรียนมาเพื่อโปรดพิจารณาอนุญาต</p>

        <!-- ══ ลายเซ็นผู้ขอลา ══ -->
        <div class="requester-sig">
            <div class="requester-sig-box">
                <?php if ($request['signature_path']): ?>
                <img src="<?= $base_path ?>/<?= htmlspecialchars($request['signature_path'], ENT_QUOTES, 'UTF-8') ?>" class="sig-img" alt="ลายเซ็น">
                <?php else: ?>
                <div style="height:22pt;"></div>
                <?php endif; ?>
                <p style="font-size:13pt;">(ลงชื่อ).................................................ผู้ขอลา</p>
                <p style="margin-top:2pt; font-size:13pt;">( <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?> )</p>
                <p style="font-size:12pt; color:#333; margin-top:2pt;">วันที่ <?= thaiDateFull($request['created_at']) ?></p>
            </div>
        </div>

        <hr class="dotted" style="margin-top:8pt;">

        <!-- ══ สถิติการลา ══ -->
        <div style="font-size:12pt; font-weight:700; margin-bottom:2pt;">
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
                $statRows = [
                    ['ลาป่วย',                                  'sick',     'sick_taken'],
                    ['ลากิจส่วนตัว',                            'personal', 'personal_taken'],
                    ["ลาพักผ่อน (โควตา {$vacQuota} วัน)",       'vacation', 'vacation_taken'],
                    ['ลาคลอดบุตร / อื่นๆ',                     'other',    'other_taken'],
                ];
                foreach ($statRows as [$label, $type, $col]):
                    $isCurrent = $lt === $type || $type === 'other' && in_array($lt, ['maternity','other']);
                    $taken  = (float)($stats[$col] ?? 0);
                    $before = $isCurrent ? max(0, $taken - (float)$request['days_count']) : $taken;
                    $curr   = $isCurrent ? (float)$request['days_count'] : '—';
                ?>
                <tr>
                    <td><?= $label ?></td>
                    <td><?= $before ?></td>
                    <td><?= $curr ?></td>
                    <td><?= $taken ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <hr class="dotted" style="margin-top:6pt;">

        <!-- ══ กล่องอนุมัติ ══ -->
        <div class="sig-grid">

            <div class="sig-box">
                <div class="sig-title">ความเห็นเจ้าหน้าที่<br><small style="font-weight:400; font-size:11pt;">(ตรวจสอบเอกสาร)</small></div>
                <?php if (isset($approvalMap[1]) && $approvalMap[1]['status'] != 0): ?>
                    <div><span class="stamp <?= $approvalMap[1]['status'] == 1 ? 'stamp-approved' : 'stamp-rejected' ?>">
                        <?= $approvalMap[1]['status'] == 1 ? '✓ ตรวจสอบแล้วถูกต้อง' : '✗ ไม่ถูกต้อง' ?>
                    </span></div>
                    <?php if (!empty($approvalMap[1]['comment'])): ?>
                    <p style="font-size:10.5pt; font-style:italic; margin-top:2pt;"><?= htmlspecialchars($approvalMap[1]['comment'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($approvalMap[1]['signature_path'])): ?>
                    <img src="<?= $base_path ?>/<?= htmlspecialchars($approvalMap[1]['signature_path'], ENT_QUOTES, 'UTF-8') ?>" class="sig-img-box" alt="">
                    <?php else: ?>
                    <div class="sig-line"></div>
                    <?php endif; ?>
                    <p class="sig-name" style="margin-top:4pt;">(<?= htmlspecialchars(($approvalMap[1]['firstname'] ?? '') . ' ' . ($approvalMap[1]['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</p>
                    <p class="sig-date" style="margin-top:2pt;">วันที่ <?= !empty($approvalMap[1]['approved_at']) ? thaiDateFull($approvalMap[1]['approved_at']) : '......./......./ .......' ?></p>
                <?php else: ?>
                    <div><span class="stamp stamp-pending">รอตรวจสอบ</span></div>
                    <div class="sig-line"></div>
                    <p class="sig-name">(..................................................)</p>
                    <p class="sig-date" style="margin-top:2pt;">วันที่ ......./......./ .......</p>
                <?php endif; ?>
            </div>

            <div class="sig-box">
                <div class="sig-title">คำสั่ง<br>ผู้อำนวยการ / รองผู้อำนวยการ</div>
                <?php if (isset($approvalMap[2]) && $approvalMap[2]['status'] != 0): ?>
                    <div><span class="stamp <?= $approvalMap[2]['status'] == 1 ? 'stamp-approved' : 'stamp-rejected' ?>">
                        <?= $approvalMap[2]['status'] == 1 ? '✓ อนุญาต' : '✗ ไม่อนุญาต' ?>
                    </span></div>
                    <?php if ($approvalMap[2]['status'] == 1 && !empty($approvalMap[2]['signature_path'])): ?>
                    <img src="<?= $base_path ?>/<?= htmlspecialchars($approvalMap[2]['signature_path'], ENT_QUOTES, 'UTF-8') ?>" class="sig-img-box" alt="">
                    <?php else: ?>
                    <div style="height:20pt;"></div>
                    <?php endif; ?>
                    <?php if (!empty($approvalMap[2]['comment'])): ?>
                    <p style="font-size:10.5pt; font-style:italic;"><?= htmlspecialchars($approvalMap[2]['comment'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <p class="sig-name" style="margin-top:4pt;">(<?= htmlspecialchars(($approvalMap[2]['firstname'] ?? '') . ' ' . ($approvalMap[2]['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</p>
                    <p class="sig-date" style="margin-top:2pt;">วันที่ <?= !empty($approvalMap[2]['approved_at']) ? thaiDateFull($approvalMap[2]['approved_at']) : '......./......./ .......' ?></p>
                <?php else: ?>
                    <div><span class="stamp stamp-pending">รออนุมัติ ผอ./รองฯ</span></div>
                    <div class="sig-line"></div>
                    <p class="sig-name">(..................................................)</p>
                    <p class="sig-date" style="margin-top:2pt;">วันที่ ......./......./ .......</p>
                <?php endif; ?>
            </div>

        </div>

    </div><!-- /page -->
</body>
</html>
