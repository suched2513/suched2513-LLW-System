<?php
/**
 * learning_media/print_summary.php — แบบสรุปรายงานและประเมินผลการใช้สื่อนวัตกรรม (แนบ ว.PA / SAR)
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

// Fetch feedback/ratings
$rStmt = $pdo->prepare("
    SELECT rating, feedback, evaluator_name, evaluator_role, created_at
    FROM learning_media_ratings
    WHERE media_id = ?
    ORDER BY id DESC
    LIMIT 10
");
$rStmt->execute([$id]);
$reviews = $rStmt->fetchAll(PDO::FETCH_ASSOC);

// Thai Date Formatter
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
    'slide'     => 'สไลด์นำเสนอ / เอกสารประกอบการสอน',
    'video'     => 'วิดีโอคลิป / สื่อมัลติมีเดีย',
    'worksheet' => 'ใบงาน / ใบกิจกรรม / แบบฝึกทักษะ',
    'game'      => 'สื่อเกมการศึกษา / Interactive Quiz',
    'website'   => 'เว็บไซต์ / บทเรียนออนไลน์ (Web/LMS)',
    'other'     => 'สื่อและนวัตกรรมการเรียนรู้อื่นๆ',
];
$typeName = $mediaTypesThai[$media['media_type'] ?? 'slide'] ?? 'สื่อการเรียนรู้ออนไลน์';

$coverImg = !empty($media['cover_url']) ? $media['cover_url'] : 'https://cdn-icons-png.flaticon.com/512/847/847969.png';
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($media['media_link']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เอกสารสรุปผลการใช้สื่อ ว.PA - <?= htmlspecialchars($media['media_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
            font-size: 14pt;
            line-height: 1.5;
            color: #1e293b;
            background: #f1f5f9;
            margin: 0;
            padding: 20px 0;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 18mm 20mm 18mm 20mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            box-sizing: border-box;
            position: relative;
        }
        .header-title-box {
            text-align: center;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .school-title {
            font-size: 17pt;
            font-weight: 800;
            color: #0369a1;
            margin: 0;
        }
        .sub-school-title {
            font-size: 13pt;
            color: #475569;
            margin: 2px 0 0 0;
        }
        .doc-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
            padding: 3px 14px;
            border-radius: 9999px;
            font-size: 11pt;
            font-weight: 700;
            margin-top: 6px;
        }

        .media-headline {
            font-size: 18pt;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 14px;
            line-height: 1.35;
            text-align: center;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 16px;
            margin-bottom: 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
        }
        .cover-box {
            text-align: center;
        }
        .cover-box img {
            width: 100%;
            height: 145px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 4px 6px;
            vertical-align: top;
            font-size: 13pt;
        }
        .info-label {
            font-weight: 700;
            color: #475569;
            width: 125px;
            white-space: nowrap;
        }

        .section-header {
            font-size: 14pt;
            font-weight: 800;
            color: #0369a1;
            border-left: 4px solid #0284c7;
            padding-left: 8px;
            margin: 14px 0 8px 0;
        }

        .content-box {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13pt;
            line-height: 1.6;
            margin-bottom: 14px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
            text-align: center;
        }
        .stat-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 10px;
        }
        .stat-card.blue {
            background: #f0f9ff;
            border-color: #bae6fd;
        }
        .stat-card.amber {
            background: #fefce8;
            border-color: #fef08a;
        }
        .stat-val {
            font-size: 20pt;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }
        .stat-lbl {
            font-size: 11pt;
            color: #64748b;
            font-weight: 600;
            margin-top: 2px;
        }

        .access-box {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #f8fafc;
            border: 1px dashed #94a3b8;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
        }
        .qr-img {
            width: 90px;
            height: 90px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 4px;
        }
        .access-details {
            flex: 1;
            font-size: 12pt;
        }
        .link-text {
            word-break: break-all;
            color: #0284c7;
            font-weight: 600;
            font-size: 11pt;
        }

        .reviews-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12pt;
            margin-bottom: 16px;
        }
        .reviews-table th, .reviews-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
        }
        .reviews-table th {
            background: #f1f5f9;
            font-weight: 700;
            color: #334155;
            text-align: center;
        }

        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 24px;
            page-break-inside: avoid;
        }
        .sign-card {
            text-align: center;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 10px;
            background: #fafafa;
            font-size: 12pt;
        }
        .dots-line {
            display: inline-block;
            width: 180px;
            border-bottom: 1px dotted #64748b;
            margin-bottom: 4px;
        }

        /* Print Controls Bar */
        .print-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #0f172a;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        }
        .print-btn {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 6px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .print-btn:hover {
            background: #0369a1;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
                font-size: 13pt;
            }
            .page {
                box-shadow: none;
                margin: 0;
                padding: 12mm 15mm;
                width: 100%;
                min-height: auto;
            }
            .print-bar {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

<!-- Control Bar -->
<div class="print-bar no-print">
    <div style="font-size: 14px; font-weight: 700;">
        <i class="fas fa-file-invoice text-sky-400 me-2"></i> เอกสารสรุปผลการใช้สื่อและนวัตกรรม (ว.PA / SAR)
    </div>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> สั่งพิมพ์เอกสาร (Print / PDF)
        </button>
        <button type="button" style="background:#475569;color:#fff;border:none;padding:6px 14px;border-radius:8px;font-size:13px;cursor:pointer;" onclick="window.close()">
            <i class="fas fa-times me-1"></i> ปิดหน้านี้
        </button>
    </div>
</div>

<div class="page">
    <!-- Header -->
    <div class="header-title-box">
        <h1 class="school-title">โรงเรียนละลมวิทยา</h1>
        <p class="sub-school-title">สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร</p>
        <span class="doc-badge">แบบรายงานและสรุปผลการใช้สื่อนวัตกรรมการจัดการเรียนรู้</span>
    </div>

    <!-- Media Headline -->
    <div class="media-headline">
        <?= htmlspecialchars($media['media_name']) ?>
    </div>

    <!-- Media & Author Info -->
    <div class="summary-grid">
        <div class="cover-box">
            <img src="<?= htmlspecialchars($coverImg) ?>" alt="Cover" onerror="this.src='https://cdn-icons-png.flaticon.com/512/847/847969.png'">
            <div style="font-size: 10pt; color: #64748b; margin-top: 4px;">ภาพหน้าปกสื่อการเรียนรู้</div>
        </div>
        <div>
            <table class="info-table">
                <tr>
                    <td class="info-label">ผู้พัฒนา/จัดทำ:</td>
                    <td><strong><?= htmlspecialchars($media['author']) ?></strong></td>
                </tr>
                <tr>
                    <td class="info-label">กลุ่มสาระฯ:</td>
                    <td><?= htmlspecialchars($media['subject']) ?></td>
                </tr>
                <tr>
                    <td class="info-label">ระดับชั้น:</td>
                    <td><?= htmlspecialchars($media['grade']) ?></td>
                </tr>
                <tr>
                    <td class="info-label">ประเภทสื่อ:</td>
                    <td><span style="background:#e2e8f0;padding:2px 8px;border-radius:6px;font-weight:600;font-size:11pt;"><?= htmlspecialchars($typeName) ?></span></td>
                </tr>
                <tr>
                    <td class="info-label">วันที่เผยแพร่:</td>
                    <td><?= thaiDateFull($media['media_date']) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Access & QR Code Box -->
    <div class="access-box">
        <img src="<?= $qrUrl ?>" alt="QR Code" class="qr-img">
        <div class="access-details">
            <div style="font-weight: 700; color: #0f172a; margin-bottom: 2px;">
                <i class="fas fa-qrcode text-sky-600 me-1"></i> ช่องทางการเข้าถึงสื่อการเรียนรู้ (Online Access)
            </div>
            <div style="font-size: 11pt; color: #64748b; margin-bottom: 4px;">
                สแกน QR Code หรือเปิดใช้งานผ่านลิงก์ด้านล่างนี้:
            </div>
            <div class="link-text">
                <a href="<?= htmlspecialchars($media['media_link']) ?>" target="_blank" style="color:#0284c7;text-decoration:none;">
                    <?= htmlspecialchars($media['media_link']) ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card blue">
            <div class="stat-val"><?= number_format((int)$media['views']) ?></div>
            <div class="stat-lbl"><i class="fas fa-eye text-sky-500 me-1"></i> ยอดเข้าชม (ครั้ง)</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-val" style="color:#b45309;">
                ⭐ <?= number_format((float)$media['rating_avg'], 1) ?>
            </div>
            <div class="stat-lbl"><i class="fas fa-star text-amber-500 me-1"></i> ความพึงพอใจเฉลี่ย (เต็ม 5)</div>
        </div>
        <div class="stat-card">
            <div class="stat-val" style="color:#15803d;"><?= number_format((int)$media['rating_count']) ?></div>
            <div class="stat-lbl"><i class="fas fa-users text-emerald-500 me-1"></i> จำนวนผู้ร่วมประเมิน (คน)</div>
        </div>
    </div>

    <!-- Objective -->
    <div class="section-header">
        <i class="fas fa-bullseye me-1"></i> วัตถุประสงค์ในการสร้างและนำไปใช้
    </div>
    <div class="content-box">
        <?= !empty($media['objective']) ? nl2br(htmlspecialchars($media['objective'])) : 'เพื่อใช้เป็นสื่อนวัตกรรมประกอบการจัดการเรียนรู้ พัฒนาทักษะและความรู้ของผู้เรียนในรายวิชา และส่งเสริมการเรียนรู้ด้วยตนเอง' ?>
    </div>

    <!-- Usage Results -->
    <div class="section-header">
        <i class="fas fa-chart-line me-1"></i> ผลการนำไปใช้ในการจัดการเรียนรู้ (Learning Impact)
    </div>
    <div class="content-box">
        <?= !empty($media['usage_results']) ? nl2br(htmlspecialchars($media['usage_results'])) : 'ผู้เรียนมีความสนใจ กระตือรือร้นในการเรียนรู้ และผลสัมฤทธิ์ทางการเรียนหลังการใช้สื่ออยู่ในเกณฑ์ดี มีความพึงพอใจต่อการจัดการเรียนรู้' ?>
    </div>

    <!-- Recent Feedback Table -->
    <?php if (!empty($reviews)): ?>
    <div class="section-header">
        <i class="fas fa-comments me-1"></i> ข้อคิดเห็นและข้อเสนอแนะจากผู้ประเมิน
    </div>
    <table class="reviews-table">
        <thead>
            <tr>
                <th style="width: 50px;">ลำดับ</th>
                <th style="width: 100px;">คะแนน</th>
                <th>ข้อคิดเห็น / ข้อเสนอแนะ</th>
                <th style="width: 140px;">ผู้ประเมิน</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reviews as $idx => $rev): ?>
            <tr>
                <td style="text-align: center;"><?= $idx + 1 ?></td>
                <td style="text-align: center; color: #b45309; font-weight: 700;">
                    <?= str_repeat('★', (int)$rev['rating']) ?><?= str_repeat('☆', 5 - (int)$rev['rating']) ?>
                </td>
                <td><?= htmlspecialchars($rev['feedback'] ?: 'มีความพึงพอใจในระดับดีมาก สื่อเข้าใจง่าย น่าสนใจ') ?></td>
                <td style="text-align: center; font-size: 11pt; color: #475569;">
                    <?= htmlspecialchars($rev['evaluator_name'] ?: 'ผู้เรียน/ครู') ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Signature Blocks -->
    <div class="sign-grid">
        <div class="sign-card">
            <p style="margin: 0 0 45px 0;">ลงชื่อ <span class="dots-line"></span> ครูผู้จัดทำและรายงาน</p>
            <p style="margin: 0; font-weight: 700;">( <?= htmlspecialchars($media['author']) ?> )</p>
            <p style="margin: 2px 0 0 0; color: #64748b; font-size: 11pt;">ตำแหน่ง ครู โรงเรียนละลมวิทยา</p>
            <p style="margin: 4px 0 0 0; font-size: 11pt;">วันที่ <span class="dots-line" style="width:120px;"></span></p>
        </div>

        <div class="sign-card">
            <p style="margin: 0 0 45px 0;">ลงชื่อ <span class="dots-line"></span> ผู้รับรองรายงาน</p>
            <p style="margin: 0; font-weight: 700;">( ............................................................ )</p>
            <p style="margin: 2px 0 0 0; color: #64748b; font-size: 11pt;">หัวหน้ากลุ่มสาระการเรียนรู้ / รองผู้อำนวยการกลุ่มบริหารวิชาการ</p>
            <p style="margin: 4px 0 0 0; font-size: 11pt;">วันที่ <span class="dots-line" style="width:120px;"></span></p>
        </div>
    </div>
</div>

</body>
</html>
