<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'cb_admin'], true)) {
    die("ไม่มีสิทธิ์เข้าถึง");
}

$pdo = getPdo();
$classroom = trim($_GET['classroom'] ?? '');
$type = trim($_GET['type'] ?? '');

if ($classroom === '' && $type === '') {
    die("กรุณาระบุห้องเรียนหรือประเภท");
}

// Fetch the logs with borrower names using SQL Joins
if ($type === 'Teacher') {
    $stmt = $pdo->prepare("
        SELECT b.entry_id, b.borrower_type, b.borrower_id, b.class_name,
               b.chromebook_id, b.chromebook_serial, b.status, b.date_borrowed, b.date_returned,
               (SELECT notes FROM cb_inspections i WHERE i.borrow_log_id = b.entry_id ORDER BY i.id DESC LIMIT 1) AS inspect_notes,
               (SELECT name FROM cb_teachers t WHERE t.teacher_id = b.borrower_id LIMIT 1) AS borrower_name
        FROM cb_borrow_logs b
        WHERE b.borrower_type = 'Teacher'
        ORDER BY b.date_borrowed DESC
    ");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("
        SELECT b.entry_id, b.borrower_type, b.borrower_id, b.class_name,
               b.chromebook_id, b.chromebook_serial, b.status, b.date_borrowed, b.date_returned,
               (SELECT notes FROM cb_inspections i WHERE i.borrow_log_id = b.entry_id ORDER BY i.id DESC LIMIT 1) AS inspect_notes,
               (SELECT name FROM att_students s WHERE s.student_id = LPAD(b.borrower_id, 5, '0') AND s.academic_year = 2569 LIMIT 1) AS borrower_name
        FROM cb_borrow_logs b
        WHERE b.borrower_type = 'Student' AND b.class_name = ?
        ORDER BY b.date_borrowed DESC
    ");
    $stmt->execute([$classroom]);
}
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function fmtDate($d) {
    if (!$d) return '—';
    try {
        return date('d/m/y H:i', strtotime($d));
    } catch (Exception $e) {
        return $d;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานการใช้งาน Chromebook - <?= htmlspecialchars($type === 'Teacher' ? 'ครูและบุคลากร' : 'ห้อง ' . $classroom) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; margin: 40px; font-size: 11pt; color: #1e293b; line-height: 1.5; }
        h2 { text-align: center; margin-bottom: 5px; font-weight: 800; font-size: 16pt; color: #0f172a; }
        .subtitle { text-align: center; margin-bottom: 25px; font-size: 11pt; color: #64748b; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; font-size: 10pt; }
        th { background-color: #f8fafc; font-weight: 600; color: #334155; text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 8.5pt; font-weight: 600; }
        .badge-borrowed { background-color: #fef3c7; color: #d97706; }
        .badge-returned { background-color: #d1fae5; color: #059669; }
        .footer { margin-top: 60px; display: flex; justify-content: space-around; page-break-inside: avoid; }
        .signature-box { text-align: center; line-height: 2; font-size: 10.5pt; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; color: #000; }
            th, td { border-color: #000; }
            @page { size: A4; margin: 1.5cm 1cm; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 25px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 22px; font-size: 14px; font-weight: bold; cursor: pointer; background: #0891b2; color: white; border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(8, 145, 178, 0.2); transition: all 0.2s;">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 6px;">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            พิมพ์รายงาน
        </button>
    </div>

    <h2>รายงานการใช้งาน Chromebook</h2>
    <div class="subtitle">
        ประจำชั้นเรียน: <?= htmlspecialchars($type === 'Teacher' ? 'ครูและบุคลากรทางการศึกษา' : 'ห้องเรียน ' . $classroom) ?>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%" class="text-center">ลำดับ</th>
                <th width="25%">ชื่อ - นามสกุล ผู้ยืม</th>
                <th width="10%" class="text-center">ห้องเรียน</th>
                <th width="15%">รหัสเครื่อง / Serial</th>
                <th width="10%" class="text-center">สถานะ</th>
                <th width="15%">วันที่ยืม</th>
                <th width="20%">หมายเหตุ (ผลตรวจสภาพล่าสุด)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($logs) === 0): ?>
                <tr>
                    <td colspan="7" class="text-center" style="padding: 30px; color: #94a3b8; font-style: italic;">
                        ไม่มีประวัติการยืมอุปกรณ์ของห้องเรียนนี้
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $idx => $l): ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td style="font-weight: 600;">
                        <?= htmlspecialchars($l['borrower_name'] ?? $l['borrower_id']) ?>
                    </td>
                    <td class="text-center"><?= htmlspecialchars($l['class_name'] ?: 'ครู/บุคลากร') ?></td>
                    <td style="font-family: monospace; font-size: 9.5pt;">
                        <strong><?= htmlspecialchars($l['chromebook_id']) ?></strong><br>
                        <span style="color: #64748b; font-size: 8.5pt;"><?= htmlspecialchars($l['chromebook_serial']) ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($l['status'] === 'Borrowed'): ?>
                            <span class="badge badge-borrowed">ยืมอยู่</span>
                        <?php else: ?>
                            <span class="badge badge-returned">คืนแล้ว</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 9pt;">
                        ยืม: <?= fmtDate($l['date_borrowed']) ?>
                        <?php if ($l['status'] === 'Returned' && $l['date_returned']): ?>
                            <br><span style="color: #059669;">คืน: <?= fmtDate($l['date_returned']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="color: #475569; font-size: 9pt;">
                        <?= htmlspecialchars($l['inspect_notes'] ?: '—') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="signature-box">
            ลงชื่อ......................................................ผู้รายงาน<br>
            (......................................................)<br>
            ตำแหน่ง ครูผู้รับผิดชอบระบบงาน
        </div>
        <div class="signature-box">
            ลงชื่อ......................................................ผู้ตรวจสอบ<br>
            (......................................................)<br>
            ตำแหน่ง หัวหน้างานระดับชั้น / หัวหน้าฝ่ายบริหาร
        </div>
    </div>
</body>
</html>
