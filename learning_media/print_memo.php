<?php
/**
 * learning_media/print_memo.php — แบบบันทึกข้อความราชการ ขออนุมัติและรายงานผลการใช้สื่อการเรียนรู้
 */
session_start();
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ไม่พบรหัสสื่อการเรียนรู้');
}

$pdo = getPdo();
$stmt = $pdo->prepare("SELECT * FROM learning_media WHERE id = ?");
$stmt->execute([$id]);
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$media) {
    die('ไม่พบข้อมูลสื่อการเรียนรู้');
}

// Convert date to Thai Buddhist Date
function thaiDateFull($dateStr) {
    if (!$dateStr) return '';
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $ts = strtotime($dateStr);
    $d = (int)date('d', $ts);
    $m = $months[(int)date('m', $ts)];
    $y = (int)date('Y', $ts) + 543;
    return "{$d} {$m} {$y}";
}

$mediaTypesThai = [
    'slide'     => 'สไลด์นำเสนอ / เอกสารประกอบการสอน (Canva, PPT, PDF)',
    'video'     => 'วิดีโอคลิป / สื่อมัลติมีเดีย (YouTube, MP4)',
    'worksheet' => 'ใบงาน / ใบกิจกรรม / แบบฝึกทักษะ',
    'game'      => 'สื่อเกมการศึกษา / Interactive (Wordwall, Quizizz, Kahoot)',
    'website'   => 'เว็บไซต์ / บทเรียนออนไลน์ (Google Sites, LMS)',
    'other'     => 'สื่อและนวัตกรรมการเรียนรู้อื่นๆ',
];
$typeName = $mediaTypesThai[$media['media_type']] ?? 'สื่อการเรียนรู้ออนไลน์';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกข้อความรายงานการใช้สื่อ - <?= htmlspecialchars($media['media_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
            font-size: 15pt;
            line-height: 1.5;
            color: #000;
            background: #f1f5f9;
            margin: 0;
            padding: 20px 0;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 20mm 20mm 25mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            box-sizing: border-box;
            position: relative;
        }
        .header-krut {
            text-align: left;
            position: relative;
            margin-bottom: 12px;
        }
        .krut-img {
            width: 60px;
            height: auto;
        }
        .memo-title {
            text-align: center;
            font-size: 24pt;
            font-weight: bold;
            margin-top: -35px;
            margin-bottom: 20px;
        }
        .memo-meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .memo-meta td {
            padding: 4px 0;
            vertical-align: top;
        }
        .meta-label {
            font-weight: bold;
            font-size: 16pt;
        }
        p {
            margin: 0 0 10px 0;
            text-align: justify;
        }
        .indent {
            text-indent: 2.5cm;
        }
        .info-box {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px 16px;
            margin: 12px 0 16px;
            background: #f8fafc;
        }
        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .sign-box {
            text-align: center;
            line-height: 1.6;
        }
        .approval-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .approval-table td {
            border: 1px solid #94a3b8;
            padding: 10px 14px;
            vertical-align: top;
            font-size: 13.5pt;
        }
        .no-print-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #1e293b;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        @media print {
            body { background: #fff; padding: 0; }
            .page { width: 100%; min-height: auto; box-shadow: none; margin: 0; padding: 15mm 15mm 15mm 20mm; }
            .no-print-bar { display: none !important; }
            @page { size: A4 portrait; margin: 0; }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div style="font-size:14px;font-weight:bold;">
        <i class="fas fa-file-alt me-2" style="color:#38bdf8;"></i> บันทึกข้อความขออนุมัติและรายงานการใช้สื่อการเรียนรู้
    </div>
    <div style="display:flex;gap:10px;">
        <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;padding:6px 18px;border-radius:8px;font-weight:bold;cursor:pointer;font-size:13px;">
            <i class="fas fa-print me-1"></i> สั่งพิมพ์เอกสาร
        </button>
        <button onclick="window.close()" style="background:#475569;color:#fff;border:none;padding:6px 14px;border-radius:8px;font-weight:bold;cursor:pointer;font-size:13px;">
            ปิดหน้าต่าง
        </button>
    </div>
</div>

<div class="page" style="margin-top:40px;">
    <!-- ตราครุฑ -->
    <div class="header-krut">
        <img class="krut-img" src="https://upload.wikimedia.org/wikipedia/commons/8/8f/Emblem_of_Thailand.svg" alt="ตราครุฑ">
    </div>
    <div class="memo-title">บันทึกข้อความ</div>

    <table class="memo-meta">
        <tr>
            <td style="width:55%;">
                <span class="meta-label">ส่วนราชการ</span> โรงเรียนละลมวิทยา สพม.ศรีสะเกษ ยโสธร
            </td>
            <td style="width:45%;text-align:right;">
                <span class="meta-label">กลุ่มสาระฯ</span> <?= htmlspecialchars($media['subject']) ?>
            </td>
        </tr>
        <tr>
            <td>
                <span class="meta-label">ที่</span> ศธ ....................................
            </td>
            <td style="text-align:right;">
                <span class="meta-label">วันที่</span> <?= thaiDateFull($media['media_date']) ?>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="meta-label">เรื่อง</span> ขอส่งแบบรายงานและสรุปผลการนำสื่อนวัตกรรมการจัดการเรียนรู้ไปใช้ในการจัดการเรียนการสอน
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="meta-label">เรียน</span> ผู้อำนวยการโรงเรียนละลมวิทยา
            </td>
        </tr>
    </table>

    <p class="indent">
        ด้วย ข้าพเจ้า <strong><?= htmlspecialchars($media['author']) ?></strong> ตำแหน่ง ครู กลุ่มสาระการเรียนรู้<?= htmlspecialchars($media['subject']) ?> 
        ได้รับมอบหมายให้ปฏิบัติหน้าที่การจัดการเรียนการสอนในระดับชั้น <strong><?= htmlspecialchars($media['grade']) ?></strong> 
        เพื่อเป็นการพัฒนาคุณภาพผู้เรียนตามมาตรฐานและตัวชี้วัดของหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน และส่งเสริมการจัดการเรียนรู้เชิงรุก (Active Learning)
    </p>

    <p class="indent">
        ในการนี้ ข้าพเจ้าได้ดำเนินการจัดทำ/ผลิต และนำสื่อนวัตกรรมทางการศึกษาไปใช้ในการจัดกิจกรรมการเรียนรู้ เรื่อง 
        <strong>"<?= htmlspecialchars($media['media_name']) ?>"</strong> ประเภท <strong><?= htmlspecialchars($typeName) ?></strong> 
        โดยได้เผยแพร่ผ่านระบบคลังสื่อการเรียนรู้ออนไลน์ของโรงเรียนละลมวิทยา ซึ่งมีรายละเอียดผลการดำเนินงานดังนี้
    </p>

    <div class="info-box" style="font-size:14pt;">
        <div><strong>๑. วัตถุประสงค์:</strong> <?= htmlspecialchars($media['objective'] ?: 'เพื่อใช้เป็นสื่อประกอบการจัดกิจกรรมการเรียนรู้ และพัฒนาทักษะของผู้เรียน') ?></div>
        <div><strong>๒. ผลการนำไปใช้:</strong> <?= htmlspecialchars($media['usage_results'] ?: 'ผู้เรียนให้ความสนใจ มีส่วนร่วมในกิจกรรมการเรียนรู้ และมีความเข้าใจในบทเรียนเพิ่มขึ้น') ?></div>
        <div><strong>๓. สถิติการเข้าถึงและประเมินผล:</strong> 
            ยอดเข้าชมรวม <strong><?= number_format($media['views']) ?></strong> ครั้ง, 
            คะแนนความพึงพอใจเฉลี่ย <strong><?= number_format($media['rating_avg'], 2) ?></strong> / 5.00 
            (จากการประเมิน <strong><?= number_format($media['rating_count']) ?></strong> คน)
        </div>
        <div><strong>๔. ลิงก์เข้าถึงสื่อ:</strong> <span style="font-size:11pt;color:#1d4ed8;word-break:break-all;"><?= htmlspecialchars($media['media_link']) ?></span></div>
    </div>

    <p class="indent">
        จึงเรียนมาเพื่อโปรดทราบและพิจารณาอนุมัติให้ใช้เป็นหลักฐานประกอบการประเมินการปฏิบัติงานต่อไป
    </p>

    <!-- ส่วนลงนาม -->
    <div class="sign-grid">
        <div></div>
        <div class="sign-box">
            ลงชื่อ..........................................................ผู้จัดทำ/รายงาน<br>
            ( <?= htmlspecialchars($media['author']) ?> )<br>
            ตำแหน่ง ครู โรงเรียนละลมวิทยา
        </div>
    </div>

    <table class="approval-table">
        <tr>
            <td style="width:50%;">
                <strong>ความเห็นของหัวหน้ากลุ่มสาระการเรียนรู้</strong><br>
                ( &nbsp; ) ทราบ และเห็นควรอนุมัติ<br>
                ( &nbsp; ) อื่นๆ ................................................................<br><br>
                ลงชื่อ..........................................................<br>
                (..........................................................)<br>
                วันที่ ......./......./.......
            </td>
            <td style="width:50%;">
                <strong>คำสั่ง / ข้อสั่งการผู้อำนวยการโรงเรียน</strong><br>
                ( &nbsp; ) ทราบ และอนุมัติ<br>
                ( &nbsp; ) อื่นๆ ................................................................<br><br>
                ลงชื่อ..........................................................<br>
                (..........................................................)<br>
                ผู้อำนวยการโรงเรียนละลมวิทยา<br>
                วันที่ ......./......./.......
            </td>
        </tr>
    </table>
</div>

</body>
</html>
