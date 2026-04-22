<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}

$requestId = (int)($_GET['id'] ?? 0);
if (!$requestId) die('ไม่พบรหัสรายการ');

try {
    $pdo = getPdo();

    $stmt = $pdo->prepare("
        SELECT d.*, a.activity_name, p.project_name, f.year_name
        FROM sbms_disbursements d
        JOIN sbms_activities a ON d.activity_id = a.id
        JOIN sbms_projects p ON a.project_id = p.id
        JOIN sbms_fiscal_years f ON p.fiscal_year_id = f.id
        WHERE d.id = ?
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    if (!$request) die('ไม่พบข้อมูลการเบิกจ่าย');

} catch (Exception $e) {
    die('เกิดข้อผิดพลาด: ' . $e->getMessage());
}

function thaiDate($dateStr) {
    if (!$dateStr) return '...............';
    $ts = strtotime($dateStr);
    $months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบขออนุญาตดำเนินงาน - <?= htmlspecialchars($request['doc_no']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 20mm; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .page { box-shadow: none; margin: 0; padding: 0; width: 100%; }
        }
        body {
            font-family: 'Sarabun', sans-serif;
            font-size: 16pt;
            line-height: 1.6;
            color: #000;
            background: #f3f4f6;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30pt;
        }
        .title {
            font-size: 20pt;
            font-weight: 800;
            text-decoration: underline;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20pt;
        }
        .meta-table td {
            padding: 4pt 0;
        }
        .content {
            text-align: justify;
        }
        .row {
            margin-bottom: 10pt;
            display: flex;
            align-items: baseline;
        }
        .label { font-weight: 800; white-space: nowrap; }
        .dotted {
            flex: 1;
            border-bottom: 1px dotted #000;
            padding: 0 10px;
            font-weight: 400;
        }
        .sig-section {
            margin-top: 50pt;
            display: flex;
            justify-content: flex-end;
        }
        .sig-box {
            text-align: center;
            width: 250pt;
        }
        .sig-img {
            max-height: 60pt;
            max-width: 200pt;
            margin-bottom: 5pt;
        }
        .btn-print {
            position: fixed; top: 20px; right: 20px;
            padding: 10px 20px; background: #059669; color: white;
            border: none; border-radius: 8px; font-weight: 800; cursor: pointer;
        }
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">พิมพ์เอกสาร</button>

    <div class="page">
        <div class="header">
            <h1 class="title">บันทึกข้อความ</h1>
        </div>

        <div class="meta-table">
            <div class="row">
                <span class="label">ส่วนราชการ</span>
                <span class="dotted">โรงเรียนละลมวิทยา</span>
            </div>
            <div class="row">
                <span class="label">ที่</span>
                <span class="dotted"><?= htmlspecialchars($request['book_no']) ?></span>
                <span class="label">วันที่</span>
                <span class="dotted"><?= thaiDate($request['book_date']) ?></span>
            </div>
            <div class="row">
                <span class="label">เรื่อง</span>
                <span class="dotted">ขออนุญาตดำเนินงานและเบิกจ่ายงบประมาณโครงการ</span>
            </div>
        </div>

        <div class="content">
            <p><strong>เรียน</strong> ผู้อำนวยการโรงเรียนละลมวิทยา</p>
            
            <p style="text-indent: 50pt; margin-top: 20pt;">
                ข้าพเจ้า <?= htmlspecialchars($request['requester_name']) ?> ตำแหน่ง <?= htmlspecialchars($request['requester_position']) ?> 
                มีความประสงค์ขออนุญาตดำเนินงานตาม <strong>กิจกรรม <?= htmlspecialchars($request['activity_name']) ?></strong> 
                ภายใต้ <strong>โครงการ <?= htmlspecialchars($request['project_name']) ?></strong> 
                ประจำปีงบประมาณ พ.ศ. <?= htmlspecialchars($request['year_name']) ?> 
            </p>

            <p style="text-indent: 50pt; margin-top: 10pt;">
                โดยมีรายละเอียดการเบิกจ่ายงบประมาณ ดังนี้
            </p>

            <div style="margin-left: 50pt; margin-top: 10pt;">
                - งบประมาณที่ได้รับจัดสรร: <?= number_format($request['amount'], 2) ?> บาท<br>
                - เบิกจ่ายในครั้งนี้: <?= number_format($request['amount'], 2) ?> บาท<br>
                - (รายละเอียดอื่นๆ ตามเอกสารแนบ)
            </div>

            <p style="text-indent: 50pt; margin-top: 20pt;">
                จึงเรียนมาเพื่อโปรดพิจารณาอนุญาต
            </p>
        </div>

        <div class="sig-section">
            <div class="sig-box">
                <?php if ($request['signature_data']): ?>
                <img src="<?= $request['signature_data'] ?>" class="sig-img">
                <?php else: ?>
                <div style="height: 60pt;"></div>
                <?php endif; ?>
                <p>(ลงชื่อ)..........................................................</p>
                <p>( <?= htmlspecialchars($request['requester_name']) ?> )</p>
                <p>ตำแหน่ง <?= htmlspecialchars($request['requester_position']) ?></p>
            </div>
        </div>

        <div class="sig-section" style="margin-top: 40pt; justify-content: flex-start;">
            <div class="sig-box" style="text-align: left;">
                <p>คำสั่ง/ความเห็นของผู้อำนวยการ</p>
                <p>( ) อนุญาต</p>
                <p>( ) ไม่อนุญาต เนื่องจาก .....................................</p>
                <br>
                <p style="text-align: center;">(ลงชื่อ)..........................................................</p>
                <p style="text-align: center;">( .......................................................... )</p>
                <p style="text-align: center;">ผู้อำนวยการโรงเรียนละลมวิทยา</p>
            </div>
        </div>
    </div>
</body>
</html>
