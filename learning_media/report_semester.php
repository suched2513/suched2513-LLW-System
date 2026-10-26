<?php
/**
 * learning_media/report_semester.php — รายงานสรุปผลการพัฒนาและใช้สื่อนวัตกรรมการจัดการเรียนรู้ประจำภาคเรียน
 */
session_start();
require_once __DIR__ . '/../config/database.php';

$isLoggedIn = isset($_SESSION['llw_role']);
$userRole   = $_SESSION['llw_role'] ?? 'guest';
$userName   = $_SESSION['fullname'] ?? ($_SESSION['teacher_name'] ?? 'ครูผู้สอน');
$pdo = getPdo();

// ── Calculate Academic Year & Semester ──
$currentYearBE = (int)date('Y') + 543;
$currentMonth  = (int)date('m');

// Defaults:
// Semester 1: May - Oct (เดือน 5 - 10)
// Semester 2: Nov - Apr (เดือน 11 - 4)
if ($currentMonth >= 5 && $currentMonth <= 10) {
    $defaultSemester = 1;
    $defaultYearBE   = $currentYearBE;
    $defaultStart    = ($currentYearBE - 543) . '-05-16';
    $defaultEnd      = ($currentYearBE - 543) . '-10-31';
} elseif ($currentMonth >= 11) {
    $defaultSemester = 2;
    $defaultYearBE   = $currentYearBE;
    $defaultStart    = ($currentYearBE - 543) . '-11-01';
    $defaultEnd      = ($currentYearBE - 543 + 1) . '-04-30';
} else {
    $defaultSemester = 2;
    $defaultYearBE   = $currentYearBE - 1;
    $defaultStart    = ($currentYearBE - 543 - 1) . '-11-01';
    $defaultEnd      = ($currentYearBE - 543) . '-04-30';
}

$selectedYear     = isset($_GET['year']) ? (int)$_GET['year'] : $defaultYearBE;
$selectedSemester = isset($_GET['semester']) ? (int)$_GET['semester'] : $defaultSemester;
$selectedSubject  = trim($_GET['subject'] ?? '');

// Compute date boundaries
if ($selectedSemester === 1) {
    $startDate = ($selectedYear - 543) . '-05-16';
    $endDate   = ($selectedYear - 543) . '-10-31';
} elseif ($selectedSemester === 2) {
    $startDate = ($selectedYear - 543) . '-11-01';
    $endDate   = ($selectedYear - 543 + 1) . '-04-30';
} else {
    // All year
    $startDate = ($selectedYear - 543) . '-05-16';
    $endDate   = ($selectedYear - 543 + 1) . '-04-30';
}

if (!empty($_GET['start_date'])) $startDate = $_GET['start_date'];
if (!empty($_GET['end_date']))   $endDate   = $_GET['end_date'];

// ── Query Media Data for the Period ──
$whereSql = "status = 'active' AND media_date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($selectedSubject !== '') {
    $whereSql .= " AND subject = ?";
    $params[] = $selectedSubject;
}

// 1. Overall Stats
$stmtKPI = $pdo->prepare("
    SELECT 
        COUNT(*) as total_media,
        COUNT(DISTINCT author) as total_authors,
        COALESCE(SUM(views), 0) as total_views,
        COALESCE(AVG(NULLIF(rating_avg, 0)), 0) as overall_rating,
        SUM(CASE WHEN academic_head_approved = 1 THEN 1 ELSE 0 END) as count_head_approved,
        SUM(CASE WHEN director_approved = 1 THEN 1 ELSE 0 END) as count_director_approved
    FROM learning_media
    WHERE {$whereSql}
");
$stmtKPI->execute($params);
$kpi = $stmtKPI->fetch(PDO::FETCH_ASSOC);

// 2. Breakdown by Subject (8 กลุ่มสาระฯ)
$stmtSubj = $pdo->prepare("
    SELECT 
        subject,
        COUNT(*) as total_media,
        COUNT(DISTINCT author) as total_authors,
        COALESCE(SUM(views), 0) as total_views,
        COALESCE(AVG(NULLIF(rating_avg, 0)), 0) as avg_rating,
        SUM(CASE WHEN director_approved = 1 THEN 1 ELSE 0 END) as approved_count
    FROM learning_media
    WHERE {$whereSql}
    GROUP BY subject
    ORDER BY total_media DESC, subject ASC
");
$stmtSubj->execute($params);
$subjList = $stmtSubj->fetchAll(PDO::FETCH_ASSOC);

// 3. Breakdown by Media Type
$stmtType = $pdo->prepare("
    SELECT 
        media_type,
        COUNT(*) as total_media,
        COALESCE(SUM(views), 0) as total_views,
        COALESCE(AVG(NULLIF(rating_avg, 0)), 0) as avg_rating
    FROM learning_media
    WHERE {$whereSql}
    GROUP BY media_type
    ORDER BY total_media DESC
");
$stmtType->execute($params);
$typeList = $stmtType->fetchAll(PDO::FETCH_ASSOC);

// 4. Detailed Media List
$stmtAll = $pdo->prepare("
    SELECT id, media_date, author, grade, subject, media_type, media_name, media_link, views, rating_avg, rating_count,
           academic_head_approved, director_approved
    FROM learning_media
    WHERE {$whereSql}
    ORDER BY media_date ASC, id ASC
");
$stmtAll->execute($params);
$mediaItems = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

function thaiDateShort($dateStr) {
    if (!$dateStr) return '';
    $months = [1=>'ม.ค.', 2=>'ก.พ.', 3=>'มี.ค.', 4=>'เม.ย.', 5=>'พ.ค.', 6=>'มิ.ย.', 7=>'ก.ค.', 8=>'ส.ค.', 9=>'ก.ย.', 10=>'ต.ค.', 11=>'พ.ย.', 12=>'ธ.ค.'];
    $ts = strtotime($dateStr);
    $d = (int)date('d', $ts);
    $m = $months[(int)date('m', $ts)];
    $y = (int)date('Y', $ts) + 543;
    return "{$d} {$m} {$y}";
}

$mediaTypeNames = [
    'slide'     => 'สไลด์/เอกสาร',
    'video'     => 'วิดีโอคลิป',
    'worksheet' => 'ใบงาน/แบบฝึก',
    'game'      => 'เกม/Interactive',
    'website'   => 'เว็บไซต์/LMS',
    'other'     => 'สื่ออื่นๆ',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสรุปผลการใช้สื่อนวัตกรรม ภาคเรียนที่ <?= $selectedSemester ?>/<?= $selectedYear ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
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
            padding: 16mm 18mm 16mm 18mm;
            margin: 0 auto 25px auto;
            background: #fff;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            position: relative;
        }

        .header-box {
            text-align: center;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .school-title {
            font-size: 18pt;
            font-weight: 800;
            color: #0369a1;
            margin: 0;
        }
        .sub-title {
            font-size: 14pt;
            font-weight: 700;
            color: #334155;
            margin: 2px 0 0 0;
        }
        .term-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
            padding: 2px 14px;
            border-radius: 9999px;
            font-size: 12pt;
            font-weight: 800;
            margin-top: 6px;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 18px;
            text-align: center;
        }
        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
        }
        .kpi-val {
            font-size: 20pt;
            font-weight: 800;
            color: #0284c7;
            line-height: 1.1;
        }
        .kpi-lbl {
            font-size: 11pt;
            color: #64748b;
            font-weight: 600;
            margin-top: 2px;
        }

        .section-title {
            font-size: 14pt;
            font-weight: 800;
            color: #0369a1;
            border-left: 4px solid #0284c7;
            padding-left: 8px;
            margin: 16px 0 8px 0;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12pt;
            margin-bottom: 16px;
        }
        .report-table thead {
            display: table-header-group;
        }
        .report-table tr {
            page-break-inside: avoid;
        }
        .report-table th, .report-table td {
            border: 1px solid #94a3b8;
            padding: 6px 8px;
        }
        .report-table th {
            background: #f1f5f9;
            font-weight: 700;
            color: #334155;
            text-align: center;
        }
        .report-table tr:nth-child(even) {
            background: #fafafa;
        }

        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .sign-card {
            text-align: center;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 8px;
            background: #fafafa;
            font-size: 11.5pt;
        }

        .top-control-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: #0f172a;
            color: #fff;
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        }
        .btn-action {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-action:hover {
            background: #0369a1;
            color: #fff;
        }

        .filter-panel {
            background: #fff;
            border-radius: 12px;
            padding: 12px 18px;
            margin: 0 auto 20px auto;
            max-width: 210mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .page { width: 100%; box-shadow: none; margin: 0; padding: 12mm 15mm; min-height: auto; }
            .no-print, .top-control-bar, .filter-panel { display: none !important; }
            @page { size: A4 portrait; margin: 8mm; }
        }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="top-control-bar no-print">
    <div style="font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-chart-pie text-sky-400"></i>
        <span>รายงานสรุปผลการใช้สื่อนวัตกรรม ประจำภาคเรียนที่ <?= $selectedSemester ?>/<?= $selectedYear ?></span>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="/learning_media/index.php" class="btn-action" style="background:#475569;">
            <i class="fas fa-arrow-left"></i> กลับหน้าคลังสื่อ
        </a>
        <button type="button" class="btn-action" onclick="window.print()">
            <i class="fas fa-print"></i> พิมพ์รายงานสรุป (Print / PDF)
        </button>
    </div>
</div>

<!-- Filter Box -->
<div class="filter-panel no-print">
    <form method="GET" action="" class="row g-2 align-items-center" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <div style="display:flex;align-items:center;gap:6px;">
            <label style="font-weight:700;font-size:13px;white-space:nowrap;">ปีการศึกษา:</label>
            <select name="year" style="padding:5px 10px;border-radius:6px;border:1px solid #cbd5e1;font-size:13px;font-weight:700;">
                <?php for ($y = $currentYearBE; $y >= $currentYearBE - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div style="display:flex;align-items:center;gap:6px;">
            <label style="font-weight:700;font-size:13px;white-space:nowrap;">ภาคเรียน:</label>
            <select name="semester" style="padding:5px 10px;border-radius:6px;border:1px solid #cbd5e1;font-size:13px;font-weight:700;">
                <option value="1" <?= $selectedSemester === 1 ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                <option value="2" <?= $selectedSemester === 2 ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                <option value="0" <?= $selectedSemester === 0 ? 'selected' : '' ?>>ตลอดทั้งปีการศึกษา</option>
            </select>
        </div>

        <div style="display:flex;align-items:center;gap:6px;">
            <label style="font-weight:700;font-size:13px;white-space:nowrap;">กลุ่มสาระฯ:</label>
            <select name="subject" style="padding:5px 10px;border-radius:6px;border:1px solid #cbd5e1;font-size:13px;">
                <option value="">ทุกกลุ่มสาระการเรียนรู้</option>
                <option value="ภาษาไทย" <?= $selectedSubject === 'ภาษาไทย' ? 'selected' : '' ?>>ภาษาไทย</option>
                <option value="คณิตศาสตร์" <?= $selectedSubject === 'คณิตศาสตร์' ? 'selected' : '' ?>>คณิตศาสตร์</option>
                <option value="วิทยาศาสตร์และเทคโนโลยี" <?= $selectedSubject === 'วิทยาศาสตร์และเทคโนโลยี' ? 'selected' : '' ?>>วิทยาศาสตร์และเทคโนโลยี</option>
                <option value="สังคมศึกษา" <?= $selectedSubject === 'สังคมศึกษา' ? 'selected' : '' ?>>สังคมศึกษา</option>
                <option value="สุขศึกษา" <?= $selectedSubject === 'สุขศึกษา' ? 'selected' : '' ?>>สุขศึกษาและพลศึกษา</option>
                <option value="ศิลปะ" <?= $selectedSubject === 'ศิลปะ' ? 'selected' : '' ?>>ศิลปะ</option>
                <option value="การงานอาชีพ" <?= $selectedSubject === 'การงานอาชีพ' ? 'selected' : '' ?>>การงานอาชีพ</option>
                <option value="ภาษาต่างประเทศ" <?= $selectedSubject === 'ภาษาต่างประเทศ' ? 'selected' : '' ?>>ภาษาต่างประเทศ</option>
            </select>
        </div>

        <button type="submit" style="background:#0284c7;color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;">
            <i class="fas fa-filter me-1"></i> ประมวลผล
        </button>
    </form>
</div>

<!-- ══════════════════════════════════════════════════════
     A4 PRINTABLE REPORT
     ══════════════════════════════════════════════════════ -->
<div class="page">
    <!-- Header -->
    <div class="header-box">
        <h1 class="school-title">โรงเรียนละลมวิทยา</h1>
        <p class="sub-title">รายงานสรุปผลการพัฒนาและเผยแพร่สื่อนวัตกรรมการจัดการเรียนรู้</p>
        <div style="font-size: 12pt; color: #64748b; margin-top: 2px;">สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร</div>
        <span class="term-badge">
            ประจำภาคเรียนที่ <?= $selectedSemester > 0 ? $selectedSemester : '๑-๒' ?> ปีการศึกษา <?= $selectedYear ?>
        </span>
    </div>

    <!-- Executive KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-val"><?= number_format((int)$kpi['total_media']) ?></div>
            <div class="kpi-lbl">สื่อนวัตกรรมทั้งหมด (รายการ)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-val" style="color:#059669;"><?= number_format((int)$kpi['total_authors']) ?></div>
            <div class="kpi-lbl">ครูผู้ผลิต/ใช้สื่อ (คน)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-val" style="color:#2563eb;"><?= number_format((int)$kpi['total_views']) ?></div>
            <div class="kpi-lbl">ยอดเข้าชมสะสม (ครั้ง)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-val" style="color:#b45309;">
                ⭐ <?= number_format((float)$kpi['overall_rating'], 2) ?>
            </div>
            <div class="kpi-lbl">ความพึงพอใจเฉลี่ย (เต็ม 5)</div>
        </div>
    </div>

    <!-- Section 1: สรุปตามกลุ่มสาระการเรียนรู้ -->
    <div class="section-title">
        ๑. สถิติสื่อนวัตกรรมจำแนกตามกลุ่มสาระการเรียนรู้
    </div>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 45px;">ลำดับ</th>
                <th>กลุ่มสาระการเรียนรู้</th>
                <th style="width: 90px;">จำนวนสื่อ (เรื่อง)</th>
                <th style="width: 80px;">ครูผู้จัดทำ (คน)</th>
                <th style="width: 85px;">ยอดเข้าชม (ครั้ง)</th>
                <th style="width: 85px;">คะแนนเฉลี่ย</th>
                <th style="width: 85px;">ผอ. อนุมัติแล้ว</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($subjList)): ?>
                <tr><td colspan="7" style="text-align:center;color:#64748b;">ไม่พบข้อมูลสื่อนวัตกรรมในภาคเรียนนี้</td></tr>
            <?php else: ?>
                <?php foreach ($subjList as $i => $s): ?>
                <tr>
                    <td style="text-align: center;"><?= $i + 1 ?></td>
                    <td style="font-weight: 600;"><?= htmlspecialchars($s['subject']) ?></td>
                    <td style="text-align: center; font-weight: 700; color: #0284c7;"><?= number_format($s['total_media']) ?></td>
                    <td style="text-align: center;"><?= number_format($s['total_authors']) ?></td>
                    <td style="text-align: center;"><?= number_format($s['total_views']) ?></td>
                    <td style="text-align: center; color: #b45309; font-weight: 700;">
                        <?= $s['avg_rating'] > 0 ? number_format($s['avg_rating'], 2) : '-' ?>
                    </td>
                    <td style="text-align: center; color: #059669; font-weight: 700;">
                        <?= number_format($s['approved_count']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Section 2: สรุปตามรูปแบบสื่อ -->
    <div class="section-title">
        ๒. สถิติสื่อนวัตกรรมจำแนกตามรูปแบบของสื่อ
    </div>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 45px;">ลำดับ</th>
                <th>รูปแบบสื่อนวัตกรรม</th>
                <th style="width: 120px;">จำนวนสื่อ (เรื่อง)</th>
                <th style="width: 100px;">ยอดเข้าชม (ครั้ง)</th>
                <th style="width: 100px;">ความพึงพอใจเฉลี่ย</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($typeList)): ?>
                <tr><td colspan="5" style="text-align:center;color:#64748b;">ไม่พบข้อมูล</td></tr>
            <?php else: ?>
                <?php foreach ($typeList as $idx => $t): ?>
                <tr>
                    <td style="text-align: center;"><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($mediaTypeNames[$t['media_type']] ?? $t['media_type']) ?></td>
                    <td style="text-align: center; font-weight: 700;"><?= number_format($t['total_media']) ?></td>
                    <td style="text-align: center;"><?= number_format($t['total_views']) ?></td>
                    <td style="text-align: center; color: #b45309; font-weight: 700;">
                        <?= $t['avg_rating'] > 0 ? number_format($t['avg_rating'], 2) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Section 3: ทะเบียนรายการสื่อทั้งหมดในภาคเรียน -->
    <div class="section-title">
        ๓. บัญชีทะเบียนสื่อนวัตกรรมการจัดการเรียนรู้ประจำภาคเรียน
    </div>
    <table class="report-table" style="font-size: 11pt;">
        <thead>
            <tr>
                <th style="width: 40px;">ที่</th>
                <th style="width: 75px;">วันที่</th>
                <th>ชื่อสื่อการเรียนรู้</th>
                <th style="width: 65px;">ระดับชั้น</th>
                <th style="width: 100px;">กลุ่มสาระฯ</th>
                <th style="width: 120px;">ผู้พัฒนา/ครูผู้สอน</th>
                <th style="width: 55px;">วิว</th>
                <th style="width: 55px;">คะแนน</th>
                <th style="width: 65px;">สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($mediaItems)): ?>
                <tr><td colspan="9" style="text-align:center;color:#64748b;">ไม่พบข้อมูลในภาคเรียนนี้</td></tr>
            <?php else: ?>
                <?php foreach ($mediaItems as $idx => $m): ?>
                <tr>
                    <td style="text-align: center;"><?= $idx + 1 ?></td>
                    <td style="text-align: center;"><?= thaiDateShort($m['media_date']) ?></td>
                    <td style="font-weight: 600;">
                        <a href="<?= htmlspecialchars($m['media_link']) ?>" target="_blank" style="color:#0284c7;text-decoration:none;">
                            <?= htmlspecialchars($m['media_name']) ?>
                        </a>
                    </td>
                    <td style="text-align: center;"><?= htmlspecialchars($m['grade']) ?></td>
                    <td><?= htmlspecialchars($m['subject']) ?></td>
                    <td><?= htmlspecialchars($m['author']) ?></td>
                    <td style="text-align: center;"><?= number_format($m['views']) ?></td>
                    <td style="text-align: center; color:#b45309; font-weight:700;">
                        <?= $m['rating_avg'] > 0 ? number_format($m['rating_avg'], 1) : '-' ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if ($m['director_approved']): ?>
                            <span style="color:#15803d;font-weight:700;">อนุมัติ</span>
                        <?php elseif ($m['academic_head_approved']): ?>
                            <span style="color:#0284c7;font-weight:700;">วิชาการ</span>
                        <?php else: ?>
                            <span style="color:#94a3b8;">รอ</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ส่วนลงนามรับรองรายงาน 3 ท่าน -->
    <div class="sign-grid">
        <!-- 1. ผู้รายงาน -->
        <div class="sign-card">
            <p style="margin: 0 0 45px 0;">ลงชื่อ ....................................................</p>
            <p style="margin: 0; font-weight: 700;">( <?= $isLoggedIn && !empty($userName) ? htmlspecialchars($userName) : '....................................................' ?> )</p>
            <p style="margin: 2px 0; color: #64748b; font-size: 10.5pt;">เจ้าหน้าที่งานพัฒนาสื่อนวัตกรรม</p>
            <p style="margin: 4px 0 0 0; font-size: 10.5pt;">ผู้รวบรวมและรายงาน</p>
        </div>

        <!-- 2. หัวหน้าฝ่ายวิชาการ (นายเฉลิมชัย ศรีชัย) -->
        <div class="sign-card">
            <p style="margin: 0 0 45px 0;">ลงชื่อ ....................................................</p>
            <p style="margin: 0; font-weight: 700;">( นายเฉลิมชัย ศรีชัย )</p>
            <p style="margin: 2px 0; color: #64748b; font-size: 10.5pt;">หัวหน้าฝ่ายบริหารงานวิชาการ</p>
            <p style="margin: 4px 0 0 0; font-size: 10.5pt;">ผู้ตรวจทานและเห็นชอบ</p>
        </div>

        <!-- 3. ผู้อำนวยการ (นายสถาน ปรางมาศ) -->
        <div class="sign-card">
            <p style="margin: 0 0 45px 0;">ลงชื่อ ....................................................</p>
            <p style="margin: 0; font-weight: 700;">( นายสถาน ปรางมาศ )</p>
            <p style="margin: 2px 0; color: #64748b; font-size: 10.5pt;">ผู้อำนวยการโรงเรียนละลมวิทยา</p>
            <p style="margin: 4px 0 0 0; font-size: 10.5pt;">ผู้อนุมัติรายงาน</p>
        </div>
    </div>
</div>

</body>
</html>
