<?php
/**
 * learning_media/print_summary.php — แบบสรุปรายงานและประเมินผลการใช้สื่อนวัตกรรม (แนบ ว.PA / SAR)
 * 2-Page Official Print Layout with Digital Signature Pad
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
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
            font-size: 14pt;
            line-height: 1.5;
            color: #1e293b;
            background: #e2e8f0;
            margin: 0;
            padding: 60px 0 40px 0;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 18mm 20mm 16mm 20mm;
            margin: 0 auto 25px auto;
            background: #fff;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .page-content {
            flex: 1;
        }

        .page-footer {
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 10pt;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title-box {
            text-align: center;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
            margin-bottom: 16px;
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
            padding: 2px 14px;
            border-radius: 9999px;
            font-size: 11pt;
            font-weight: 700;
            margin-top: 6px;
        }

        .media-headline {
            font-size: 17pt;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 14px;
            line-height: 1.35;
            text-align: center;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 190px 1fr;
            gap: 16px;
            margin-bottom: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
        }
        .cover-box {
            text-align: center;
        }
        .cover-box img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 3px 6px;
            vertical-align: top;
            font-size: 13pt;
        }
        .info-label {
            font-weight: 700;
            color: #475569;
            width: 120px;
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
            margin-bottom: 14px;
            text-align: center;
        }
        .stat-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 8px;
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
            font-size: 19pt;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }
        .stat-lbl {
            font-size: 10.5pt;
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
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .qr-img {
            width: 85px;
            height: 85px;
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

        /* ── Signature Cards (Aligned & Compact) ── */
        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 18px;
            page-break-inside: avoid;
        }
        .sign-card {
            text-align: center;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 12px;
            background: #fafafa;
            font-size: 12pt;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .sign-role-title {
            font-weight: 800;
            color: #1e293b;
            font-size: 12pt;
            margin-bottom: 4px;
        }
        .sig-display-box {
            min-height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 4px 0 -8px 0;
            position: relative;
        }
        .sig-display-box img {
            max-height: 52px;
            max-width: 180px;
            object-fit: contain;
            filter: drop-shadow(0 1px 1px rgba(0,0,0,0.1));
        }
        .dots-line {
            display: inline-block;
            width: 180px;
            border-bottom: 1px dotted #475569;
            margin-bottom: 2px;
        }
        .btn-sig {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-sig:hover {
            background: #0369a1;
        }
        .btn-sig-clear {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fca5a5;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-sig-clear:hover {
            background: #fecaca;
        }

        /* ── Top Bar Controls ── */
        .print-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #0f172a;
            color: #fff;
            padding: 10px 24px;
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

        /* ── Signature Modal ── */
        .sig-modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        .sig-modal-dialog {
            background: #fff;
            border-radius: 16px;
            width: 480px;
            max-width: 95vw;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: fadeInScale 0.2s ease-out;
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .sig-modal-header {
            background: linear-gradient(135deg, #0284c7, #2563eb);
            color: #fff;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 800;
            font-size: 15px;
        }
        .sig-modal-body {
            padding: 16px 20px;
            text-align: center;
        }
        .sig-canvas-wrap {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
            display: inline-block;
            position: relative;
            cursor: crosshair;
            margin-bottom: 10px;
            touch-action: none;
        }
        #sigCanvas {
            display: block;
            border-radius: 10px;
        }
        .sig-guide-line {
            position: absolute;
            left: 20px;
            right: 20px;
            bottom: 40px;
            border-bottom: 1px dashed #cbd5e1;
            pointer-events: none;
        }
        .sig-modal-footer {
            background: #f8fafc;
            padding: 12px 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* ── Print Styles ── */
        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
                font-size: 13.5pt;
            }
            .page {
                box-shadow: none;
                margin: 0;
                padding: 12mm 15mm 10mm 15mm;
                width: 100%;
                min-height: 275mm;
                page-break-after: always;
                break-after: page;
            }
            .page:last-child {
                page-break-after: auto;
                break-after: auto;
            }
            .print-bar, .no-print, .sig-modal-backdrop {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 8mm 10mm;
            }
        }
    </style>
</head>
<body>

<!-- Control Bar -->
<div class="print-bar no-print">
    <div style="font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-file-invoice text-sky-400"></i>
        <span>เอกสารสรุปผลการใช้สื่อและนวัตกรรม (ว.PA / SAR) — <span style="color:#38bdf8;">๒ หน้า (A4)</span></span>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <button type="button" class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> สั่งพิมพ์เอกสาร (Print / PDF)
        </button>
        <button type="button" style="background:#475569;color:#fff;border:none;padding:6px 14px;border-radius:8px;font-size:13px;cursor:pointer;" onclick="window.close()">
            <i class="fas fa-times me-1"></i> ปิดหน้านี้
        </button>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     PAGE 1: ภาพรวมสื่อ, ลิงก์/QR Code, สถิติ, และวัตถุประสงค์
     ══════════════════════════════════════════════════════ -->
<div class="page" id="docPage1">
    <div class="page-content">
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

        <!-- Stats Row -->
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
    </div>

    <!-- Page 1 Footer -->
    <div class="page-footer">
        <div>คลังสื่อการเรียนรู้ โรงเรียนละลมวิทยา</div>
        <div>หน้า ๑ จาก ๒</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     PAGE 2: ผลการนำไปใช้, ข้อคิดเห็น/ประเมิน, และบล็อกลงนาม (เซ็นชื่อได้)
     ══════════════════════════════════════════════════════ -->
<div class="page" id="docPage2">
    <div class="page-content">
        <!-- Page 2 Header -->
        <div class="header-title-box" style="margin-bottom: 16px; padding-bottom: 8px;">
            <h2 style="font-size: 15pt; font-weight: 800; color: #0369a1; margin: 0;">โรงเรียนละลมวิทยา</h2>
            <p style="font-size: 11pt; color: #475569; margin: 2px 0 0 0;">แบบรายงานและสรุปผลการใช้สื่อนวัตกรรมการจัดการเรียนรู้ (ต่อ)</p>
            <div style="font-size: 12pt; font-weight: 700; color: #0f172a; margin-top: 4px;">
                เรื่อง: <?= htmlspecialchars($media['media_name']) ?>
            </div>
        </div>

        <!-- Usage Results (ตัดมาหน้าที่ 2 ตามที่ต้องการ) -->
        <div class="section-header">
            <i class="fas fa-chart-line me-1"></i> ผลการนำไปใช้ในการจัดการเรียนรู้ (Learning Impact)
        </div>
        <div class="content-box" style="min-height: 90px;">
            <?= !empty($media['usage_results']) ? nl2br(htmlspecialchars($media['usage_results'])) : 'ผู้เรียนมีความสนใจ กระตือรือร้นในการเรียนรู้ และผลสัมฤทธิ์ทางการเรียนหลังการใช้สื่ออยู่ในเกณฑ์ดี มีความพึงพอใจต่อการจัดการเรียนรู้' ?>
        </div>

        <!-- Recent Feedback Table -->
        <div class="section-header">
            <i class="fas fa-comments me-1"></i> ข้อคิดเห็นและข้อเสนอแนะจากผู้ประเมิน
        </div>
        <?php if (!empty($reviews)): ?>
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
        <?php else: ?>
        <div class="content-box" style="text-align:center; color:#64748b; font-size:12pt; padding:16px;">
            <i class="far fa-smile me-1"></i> มีผลการประเมินความพึงพอใจภาพรวมอยู่ในเกณฑ์ดีมาก
        </div>
        <?php endif; ?>

        <!-- Signature Blocks (จัดชิดบรรทัดเดียว ไม่ตัดคำ + มีระบบเซ็นชื่อดิจิทัล) -->
        <div class="sign-grid">
            <!-- Left Card: ครูผู้จัดทำและรายงาน -->
            <div class="sign-card">
                <div>
                    <div class="sign-role-title">ครูผู้จัดทำและรายงาน</div>
                    
                    <!-- Digital Signature Area -->
                    <div id="sig-display-1" class="sig-display-box">
                        <!-- Rendered signature image will appear here -->
                    </div>

                    <div style="margin: 4px 0 6px 0;">
                        ลงชื่อ <span class="dots-line" style="width:170px;"></span>
                    </div>
                    <div style="font-weight: 700; margin: 2px 0;">
                        ( <?= htmlspecialchars($media['author']) ?> )
                    </div>
                    <div style="color: #64748b; font-size: 11pt; margin: 2px 0;">
                        ตำแหน่ง ครู โรงเรียนละลมวิทยา
                    </div>
                    <div style="font-size: 11pt; margin-top: 6px;">
                        วันที่ <?= thaiDateFull($media['media_date']) ?>
                    </div>
                </div>

                <!-- Digital Sign Controls (Hidden on Print) -->
                <div class="no-print" style="margin-top: 10px; display: flex; justify-content: center; gap: 6px;">
                    <button type="button" class="btn-sig" onclick="openSignatureModal('1')">
                        <i class="fas fa-signature me-1"></i> เซ็นชื่อดิจิทัล
                    </button>
                    <button type="button" class="btn-sig-clear" id="btn-clear-1" style="display:none;" onclick="clearSignature('1')">
                        <i class="fas fa-eraser me-1"></i> ลบ
                    </button>
                </div>
            </div>

            <!-- Right Card: ผู้รับรองรายงาน -->
            <div class="sign-card">
                <div>
                    <div class="sign-role-title">ผู้รับรองรายงาน</div>
                    
                    <!-- Digital Signature Area -->
                    <div id="sig-display-2" class="sig-display-box">
                        <!-- Rendered signature image will appear here -->
                    </div>

                    <div style="margin: 4px 0 6px 0;">
                        ลงชื่อ <span class="dots-line" style="width:170px;"></span>
                    </div>
                    <div style="font-weight: 700; margin: 2px 0;">
                        ( ............................................................ )
                    </div>
                    <div style="color: #64748b; font-size: 11pt; margin: 2px 0;">
                        หัวหน้ากลุ่มสาระฯ / รองผู้อำนวยการกลุ่มบริหารวิชาการ
                    </div>
                    <div style="font-size: 11pt; margin-top: 6px;">
                        วันที่ <span class="dots-line" style="width:110px;"></span>
                    </div>
                </div>

                <!-- Digital Sign Controls (Hidden on Print) -->
                <div class="no-print" style="margin-top: 10px; display: flex; justify-content: center; gap: 6px;">
                    <button type="button" class="btn-sig" onclick="openSignatureModal('2')">
                        <i class="fas fa-signature me-1"></i> เซ็นชื่อดิจิทัล
                    </button>
                    <button type="button" class="btn-sig-clear" id="btn-clear-2" style="display:none;" onclick="clearSignature('2')">
                        <i class="fas fa-eraser me-1"></i> ลบ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Page 2 Footer -->
    <div class="page-footer">
        <div>คลังสื่อการเรียนรู้ โรงเรียนละลมวิทยา</div>
        <div>หน้า ๒ จาก ๒</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     SIGNATURE MODAL (วาดลายเซ็นดิจิทัล)
     ══════════════════════════════════════════════════════ -->
<div class="sig-modal-backdrop" id="sigModal">
    <div class="sig-modal-dialog">
        <div class="sig-modal-header">
            <span><i class="fas fa-pen-nib me-2"></i> วาดลายเซ็นดิจิทัล</span>
            <button type="button" onclick="closeSignatureModal()" style="background:transparent;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div class="sig-modal-body">
            <p style="font-size:13px;color:#64748b;margin:0 0 10px 0;">
                ใช้เมาส์ นิ้วมือ หรือปากกาสไตลัสเซ็นชื่อบนพื้นที่ด้านล่าง:
            </p>
            <div class="sig-canvas-wrap">
                <canvas id="sigCanvas" width="420" height="180"></canvas>
                <div class="sig-guide-line"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;gap:8px;align-items:center;">
                    <span style="font-size:12px;color:#475569;font-weight:700;">สีหมึก:</span>
                    <button type="button" onclick="setPenColor('#0f2b5c')" style="width:20px;height:20px;border-radius:50%;background:#0f2b5c;border:2px solid #fff;box-shadow:0 0 0 1px #cbd5e1;cursor:pointer;" title="น้ำเงินเข้ม"></button>
                    <button type="button" onclick="setPenColor('#000000')" style="width:20px;height:20px;border-radius:50%;background:#000000;border:2px solid #fff;box-shadow:0 0 0 1px #cbd5e1;cursor:pointer;" title="สีดำ"></button>
                </div>
                <button type="button" onclick="clearCanvas()" style="background:#f1f5f9;border:1px solid #cbd5e1;padding:4px 12px;border-radius:6px;font-size:12px;cursor:pointer;font-weight:700;color:#475569;">
                    <i class="fas fa-undo me-1"></i> ล้างกระดาน
                </button>
            </div>
        </div>
        <div class="sig-modal-footer">
            <button type="button" onclick="closeSignatureModal()" style="background:#e2e8f0;border:none;padding:6px 16px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;color:#334155;">
                ยกเลิก
            </button>
            <button type="button" onclick="applySignature()" style="background:#0284c7;color:#fff;border:none;padding:6px 20px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                <i class="fas fa-check me-1"></i> นำลายเซ็นไปใช้
            </button>
        </div>
    </div>
</div>

<script>
// ── Digital Signature Pad Logic ──
let currentSigTarget = '1';
let isDrawing = false;
let lastX = 0;
let lastY = 0;
let penColor = '#0f2b5c';
const canvas = document.getElementById('sigCanvas');
const ctx = canvas.getContext('2d');

function initCanvas() {
    ctx.strokeStyle = penColor;
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Mouse events
    canvas.addEventListener('mousedown', (e) => {
        isDrawing = true;
        const rect = canvas.getBoundingClientRect();
        lastX = e.clientX - rect.left;
        lastY = e.clientY - rect.top;
    });
    canvas.addEventListener('mousemove', (e) => {
        if (!isDrawing) return;
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(x, y);
        ctx.stroke();
        lastX = x;
        lastY = y;
    });
    window.addEventListener('mouseup', () => { isDrawing = false; });

    // Touch events for mobile/tablet/stylus
    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        isDrawing = true;
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches[0];
        lastX = touch.clientX - rect.left;
        lastY = touch.clientY - rect.top;
    }, { passive: false });

    canvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        if (!isDrawing) return;
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches[0];
        const x = touch.clientX - rect.left;
        const y = touch.clientY - rect.top;
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(x, y);
        ctx.stroke();
        lastX = x;
        lastY = y;
    }, { passive: false });

    canvas.addEventListener('touchend', (e) => {
        e.preventDefault();
        isDrawing = false;
    }, { passive: false });
}

function setPenColor(color) {
    penColor = color;
    ctx.strokeStyle = color;
}

function clearCanvas() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function openSignatureModal(target) {
    currentSigTarget = target;
    clearCanvas();
    document.getElementById('sigModal').style.display = 'flex';
}

function closeSignatureModal() {
    document.getElementById('sigModal').style.display = 'none';
}

function isCanvasBlank(c) {
    const blank = document.createElement('canvas');
    blank.width = c.width;
    blank.height = c.height;
    return c.toDataURL() === blank.toDataURL();
}

function applySignature() {
    if (isCanvasBlank(canvas)) {
        alert('กรุณาเซ็นชื่อก่อนกดบันทึก');
        return;
    }
    const dataUrl = canvas.toDataURL('image/png');
    renderSignature(currentSigTarget, dataUrl);

    // Save to localStorage so it persists for this media/author
    try {
        localStorage.setItem('llw_media_sig_' + currentSigTarget + '_<?= $id ?>', dataUrl);
    } catch(e) {}

    closeSignatureModal();
}

function renderSignature(target, dataUrl) {
    const displayBox = document.getElementById('sig-display-' + target);
    const clearBtn = document.getElementById('btn-clear-' + target);

    if (displayBox) {
        displayBox.innerHTML = `<img src="${dataUrl}" alt="ลายเซ็น">`;
    }
    if (clearBtn) {
        clearBtn.style.display = 'inline-block';
    }
}

function clearSignature(target) {
    const displayBox = document.getElementById('sig-display-' + target);
    const clearBtn = document.getElementById('btn-clear-' + target);

    if (displayBox) {
        displayBox.innerHTML = '';
    }
    if (clearBtn) {
        clearBtn.style.display = 'none';
    }
    try {
        localStorage.removeItem('llw_media_sig_' + target + '_<?= $id ?>');
    } catch(e) {}
}

// ── Restore saved signatures on page load ──
document.addEventListener('DOMContentLoaded', () => {
    initCanvas();

    ['1', '2'].forEach(target => {
        const saved = localStorage.getItem('llw_media_sig_' + target + '_<?= $id ?>');
        if (saved) {
            renderSignature(target, saved);
        }
    });
});
</script>

</body>
</html>
