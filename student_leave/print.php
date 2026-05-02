<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$isStudent = isset($_SESSION['is_student']) && $_SESSION['is_student'] === true;
$isStaff   = isset($_SESSION['llw_role']);

if (!$isStudent && !$isStaff) {
    header('Location: /student/login.php');
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ไม่พบหมายเลขคำขอลา');
}

try {
    $pdo = getPdo();

    $stmt = $pdo->prepare("
        SELECT r.*, s.name AS student_name, s.classroom
        FROM stl_requests r
        LEFT JOIN att_students s ON s.student_id = r.student_id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        die('ไม่พบข้อมูลใบลา');
    }

    // Authorization check
    if ($isStudent && !$isStaff) {
        $student_code = $_SESSION['student_code'] ?? '';
        if ($req['student_id'] !== $student_code) {
            die('คุณไม่มีสิทธิ์ดูใบลานี้');
        }
    }

} catch (Exception $e) {
    error_log('[stl print] ' . $e->getMessage());
    die('เกิดข้อผิดพลาด กรุณาลองใหม่');
}

// Helper functions
function stripTitle($fullName) {
    $name = trim($fullName);
    $prefixes = ['เด็กชาย', 'เด็กหญิง', 'ด.ช.', 'ด.ญ.', 'นาย', 'นางสาว', 'นาง', 'น.ส.'];
    foreach ($prefixes as $p) {
        if (mb_strpos($name, $p, 0, 'UTF-8') === 0) {
            return ltrim(mb_substr($name, mb_strlen($p, 'UTF-8'), null, 'UTF-8'));
        }
    }
    return $name;
}

function thaiDate($dateStr) {
    if (!$dateStr) return '...........';
    $months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    $p = explode('-', $dateStr);
    if (count($p) < 3) return $dateStr;
    return (int)$p[2] . ' ' . $months[(int)$p[1]] . ' พ.ศ. ' . ((int)$p[0] + 543);
}

function thaiShortDate($dateStr) {
    if (!$dateStr) return '...........';
    $months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
               'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $p = explode('-', $dateStr);
    if (count($p) < 3) return $dateStr;
    return (int)$p[2] . ' ' . $months[(int)$p[1]] . ' ' . ((int)$p[0] + 543);
}

$leaveTypeLabel = ['sick' => 'ลาป่วย', 'personal' => 'ลากิจ', 'other' => 'ลา'];
$leaveType = $leaveTypeLabel[$req['leave_type']] ?? 'ลา';
$leaveWord = $req['leave_type'] === 'sick' ? 'ป่วย' : ($req['leave_type'] === 'personal' ? 'กิจ' : '');

$printDate   = thaiDate(date('Y-m-d'));
$dateFrom    = thaiShortDate($req['date_from']);
$dateTo      = thaiShortDate($req['date_to']);
$studentName = $req['student_name'] ?? $req['student_id'];
$classroom   = $req['classroom']    ?? '';
$days        = (int)$req['days'];
$reason      = $req['reason']       ?? '';
$parentName  = $req['parent_name']  ?? '';
$parentPhone = $req['parent_phone'] ?? '';
$teacherNote = $req['teacher_note'] ?? '';
$studentNameNoTitle = stripTitle($studentName);
$parentNameNoTitle  = $parentName ? stripTitle($parentName) : '';
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ใบลา – <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
    font-size: 16pt;
    line-height: 1.7;
    color: #000;
    background: #f9f9f9;
}
.page {
    width: 210mm;
    min-height: 297mm;
    background: white;
    margin: 0 auto;
    padding: 20mm 25mm 20mm 30mm;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}
.header { text-align: right; margin-bottom: 10mm; }
.header .doc-no { margin-bottom: 4mm; }
.title { text-align: center; font-size: 18pt; font-weight: 700; margin-bottom: 6mm; }
.body-line { margin-bottom: 3mm; }
.underline { border-bottom: 1px solid #000; min-width: 60px; display: inline-block; }
.signature-section { margin-top: 10mm; }
.sig-table {
    border-collapse: collapse;
    margin-left: auto;
    margin-right: 10mm;
}
.sig-table td { padding: 0; }
.sig-table .td-label  { white-space: nowrap; padding: 0 6px; vertical-align: bottom; }
.sig-table .td-name-inline { min-width: 180px; text-align: center; vertical-align: bottom; padding: 0 8px; }
.sig-table .td-full-name   { text-align: center; font-size: 14pt; color: #333; padding-top: 1px; }
.divider { border-top: 1px solid #999; margin: 8mm 0 6mm 0; }
.teacher-section { background: #f9f9f9; padding: 4mm 6mm; border: 1px solid #ccc; border-radius: 4px; }
.print-btn {
    position: fixed; bottom: 20px; right: 20px; z-index: 100;
    background: #0d9488; color: white; border: none; padding: 12px 24px;
    border-radius: 24px; font-size: 15pt; cursor: pointer; font-family: 'Sarabun', sans-serif;
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
}
.back-btn {
    position: fixed; bottom: 20px; left: 20px; z-index: 100;
    background: #64748b; color: white; border: none; padding: 12px 24px;
    border-radius: 24px; font-size: 15pt; cursor: pointer; font-family: 'Sarabun', sans-serif;
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    text-decoration: none; display: inline-block;
}
@media print {
    body { background: white; }
    .page { box-shadow: none; margin: 0; padding: 15mm 20mm 15mm 25mm; }
    .print-btn, .back-btn { display: none !important; }
}
</style>
</head>
<body>

<div class="page">

    <!-- Header: ที่ + วันที่ -->
    <div class="header">
        <div class="doc-no">ที่ <span class="underline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>/<span class="underline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></div>
        <div>วันที่ <?= $printDate ?></div>
    </div>

    <!-- เรื่อง / เรียน -->
    <div class="body-line">เรื่อง &nbsp;&nbsp; ขอ<?= $leaveType ?></div>
    <div class="body-line" style="margin-bottom: 6mm;">
        เรียน &nbsp;&nbsp; ผู้อำนวยการโรงเรียนละลมวิทยา
    </div>

    <!-- เนื้อหา -->
    <div class="body-line" style="text-indent: 30mm;">
        ข้าพเจ้า&nbsp;<strong><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></strong>&nbsp;
        ชั้น&nbsp;<strong><?= htmlspecialchars($classroom, ENT_QUOTES, 'UTF-8') ?></strong>&nbsp;
        เลขที่ <span class="underline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
    </div>
    <div class="body-line" style="text-indent: 30mm;">
        <?php if ($req['leave_type'] === 'sick'): ?>
        มีความประสงค์ขอ<strong>ลาป่วย</strong> เนื่องจาก
        <?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>
        <?php else: ?>
        มีความประสงค์ขอ<strong><?= $leaveType ?></strong> เนื่องจาก
        <?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
    </div>
    <div class="body-line" style="text-indent: 30mm;">
        มีกำหนด <strong><?= $days ?></strong> วัน
        ตั้งแต่วันที่ <strong><?= $dateFrom ?></strong>
        ถึงวันที่ <strong><?= $dateTo ?></strong>
    </div>

    <div class="body-line" style="margin-top: 4mm; text-indent: 30mm;">
        จึงเรียนมาเพื่อโปรดพิจารณาอนุญาต
    </div>

    <!-- Signatures -->
    <div class="signature-section">

        <!-- นักเรียน -->
        <table class="sig-table" style="margin-bottom: 8mm;">
            <tr>
                <td class="td-label">ลงชื่อ</td>
                <td class="td-name-inline"><?= htmlspecialchars($studentNameNoTitle, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="td-label">นักเรียน</td>
            </tr>
            <tr>
                <td></td>
                <td class="td-full-name">(<?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>)</td>
                <td></td>
            </tr>
        </table>

        <!-- ผู้ปกครอง -->
        <table class="sig-table">
            <tr>
                <td class="td-label">ลงชื่อ</td>
                <td class="td-name-inline"><?= htmlspecialchars($parentNameNoTitle, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="td-label">ผู้ปกครอง</td>
            </tr>
            <tr>
                <td></td>
                <td class="td-full-name">(<?= htmlspecialchars($parentName, ENT_QUOTES, 'UTF-8') ?>)</td>
                <td></td>
            </tr>
            <?php if ($parentPhone): ?>
            <tr>
                <td></td>
                <td class="td-full-name" style="font-size:13pt; color:#555;">โทร. <?= htmlspecialchars($parentPhone, ENT_QUOTES, 'UTF-8') ?></td>
                <td></td>
            </tr>
            <?php endif; ?>
        </table>

    </div>

    <!-- Teacher Section -->
    <div class="divider"></div>
    <div class="teacher-section">
        <div style="font-weight: 700; margin-bottom: 3mm;">ความเห็นครูที่ปรึกษา</div>
        <?php if ($teacherNote): ?>
        <div style="margin-bottom: 4mm;"><?= htmlspecialchars($teacherNote, ENT_QUOTES, 'UTF-8') ?></div>
        <?php else: ?>
        <div style="margin-bottom: 8mm; border-bottom: 1px dotted #999; padding-bottom: 4mm;">&nbsp;</div>
        <div style="margin-bottom: 8mm; border-bottom: 1px dotted #999; padding-bottom: 4mm;">&nbsp;</div>
        <?php endif; ?>
        <div style="margin-top: 4mm; font-weight: 700;">
            <?php if ($req['status'] === 'approved'): ?>
                <span style="color: #16a34a;">✓ อนุญาต</span>
            <?php elseif ($req['status'] === 'rejected'): ?>
                <span style="color: #dc2626;">✗ ไม่อนุญาต</span>
            <?php else: ?>
                [ ] อนุญาต &nbsp;&nbsp; [ ] ไม่อนุญาต
            <?php endif; ?>
        </div>

        <!-- ครูที่ปรึกษา -->
        <table class="sig-table" style="margin-top: 4mm;">
            <tr>
                <td class="td-label">ลงชื่อ</td>
                <td class="td-name-inline" style="border-bottom: 1px dotted #999;">&nbsp;</td>
                <td class="td-label">ครูที่ปรึกษา</td>
            </tr>
            <tr>
                <td></td>
                <td class="td-full-name">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</td>
                <td></td>
            </tr>
            <?php if ($req['approved_at']): ?>
            <tr>
                <td></td>
                <td class="td-full-name" style="font-size:13pt; color:#666;">วันที่ <?= thaiDate(substr($req['approved_at'], 0, 10)) ?></td>
                <td></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

</div>

<button class="print-btn" onclick="window.print()">🖨 พิมพ์ใบลา</button>
<a href="javascript:history.back()" class="back-btn">← กลับ</a>

<script>
// Auto print when page loads if ?autoprint=1
if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
    window.addEventListener('load', function() { window.print(); });
}
</script>
</body>
</html>
