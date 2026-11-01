<?php
/**
 * pp5/course_pdf.php — สร้างและพิมพ์เอกสารรายงาน ปพ.5 ขนาด A4 แนวนอน (Landscape)
 * ตรงตามแบบฟอร์มทางการของโรงเรียนละลมวิทยา 100%:
 * - mode=pok : ชีต "ปกน(p)" แบบบันทึกผลการพัฒนาคุณภาพผู้เรียน (ปพ.5-พ)
 * - mode=sgs : ชีต "sgs(p)" แบบบันทึกคะแนน SGS-ONLINE 43 คอลัมน์
 * - mode=all : เอกสาร ปพ.5 ครบชุด
 */

require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\Pp5ReportService;

pp5_require_login();

try {

$policy        = new Pp5Policy($pdo);
$reportService = new Pp5ReportService($pdo);

$courseId = (int)($_GET['course_id'] ?? 0);
$data     = $reportService->compileCourseReportData($courseId);

if (!$data || !$policy->canViewCourse($data['course'])) {
    header('Location: /pp5/index.php');
    exit();
}

$course = $data['course'];

// โหมดการแสดงผล (pok | desc | plan | sgs | all)
$mode = (string)($_GET['mode'] ?? '');
$rawSections = trim((string)($_GET['sections'] ?? ''));

if ($mode === 'pok' || $rawSections === '1' || $rawSections === 'pok') {
    $mode = 'pok';
    $sections = ['pok'];
} elseif ($mode === 'desc' || $rawSections === '2' || $rawSections === 'desc') {
    $mode = 'desc';
    $sections = ['desc'];
} elseif ($mode === 'plan' || $rawSections === '3' || $rawSections === 'plan') {
    $mode = 'plan';
    $sections = ['plan'];
} elseif ($mode === 'sgs' || $rawSections === '4' || $rawSections === 'sgs') {
    $mode = 'sgs';
    $sections = ['sgs'];
} else {
    $mode = 'all';
    $sections = [1, 2, 3, 4, 5];
}

$isDownload = !empty($_GET['download']);
$safeRoom   = str_replace(['/', '\\'], '-', (string)$course['classroom_name']);

// กรณีดาวน์โหลดเป็นไฟล์เอกสาร ปพ.5 พร้อมพิมพ์
if ($isDownload) {
    $prefixMap  = ['pok' => 'POK', 'desc' => 'DESC', 'plan' => 'PLAN', 'sgs' => 'SGS', 'all' => 'PP5'];
    $prefixName = $prefixMap[$mode] ?? 'PP5';
    $fileName   = "{$prefixName}_{$course['academic_year']}_{$course['semester']}_{$course['subject_code']}_ห้อง_{$safeRoom}.html";
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    echo $reportService->renderReportHtml($data, $sections, true, $mode);
    exit;
}

$titleMap = [
    'pok'  => 'ชีต ปกน(p) (ปพ.5-พ)',
    'desc' => 'ชีต คำอธิบายรายวิชา',
    'plan' => 'ชีต แผนการวัดผล (ตชว.)',
    'sgs'  => 'ชีต sgs(p) (SGS-ONLINE)',
    'all'  => 'ปพ.5 ครบชุด',
];
$pageTitle = ($titleMap[$mode] ?? 'ปพ.5') . ' — ' . htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'] . ' (' . $course['classroom_name'] . ')', ENT_QUOTES, 'UTF-8');

$innerPagesHtml = $reportService->renderReportHtml($data, $sections, false, $mode);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @page {
            size: A4 landscape;
            margin: 4mm 6mm 4mm 6mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'TH Sarabun New', 'THSarabunNew', 'Sarabun', sans-serif;
            font-size: 13.5pt;
            line-height: 1.35;
            color: #000000;
            margin: 0;
            padding: 60px 0 32px 0;
            background: #334155;
        }
        .toolbar-no-print {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 52px;
            background: #0f172a;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            z-index: 1000;
            font-family: 'Prompt', sans-serif;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
        }
        .toolbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
        }
        .pp5-page-landscape, .pp5-page-sgs {
            width: 297mm;
            min-height: 210mm;
            margin: 8mm auto;
            padding: 8mm 12mm;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            page-break-after: always;
            box-sizing: border-box;
        }
        .pp5-page-landscape:last-child, .pp5-page-sgs:last-child {
            page-break-after: auto;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .toolbar-no-print {
                display: none !important;
            }
            .pp5-page-landscape, .pp5-page-sgs {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
        }
        table.pp5-pok-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
            table-layout: fixed;
        }
        table.pp5-pok-table th,
        table.pp5-pok-table td {
            border: 1px solid #000000;
            padding: 4px 2px;
            vertical-align: middle;
            text-align: center;
        }
        table.pp5-pok-table th {
            font-weight: bold;
            background: #ffffff;
        }
        table.pp5-sgs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            table-layout: fixed;
        }
        table.pp5-sgs-table th,
        table.pp5-sgs-table td {
            border: 1px solid #000000;
            padding: 1.5px 1px;
            vertical-align: middle;
            text-align: center;
            overflow: hidden;
            white-space: nowrap;
        }
        table.pp5-sgs-table th {
            font-weight: bold;
            background: #ffffff;
        }
        table.pp5-sgs-table td.sgs-name {
            text-align: left;
            padding-left: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>

    <!-- แถบควบคุมการพิมพ์ด้านบน (ซ่อนอัตโนมัติเวลาสั่งพิมพ์ลงกระดาษ A4 หรือ Save as PDF) -->
    <div class="toolbar-no-print">
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="/pp5/course_summary.php?course_id=<?= $courseId ?>" class="toolbar-btn" style="background:#1e293b; color:#e2e8f0;">
                <i class="bi bi-arrow-left"></i> กลับหน้าสรุปรายวิชา
            </a>
            <div style="font-size:12px; font-weight:700; color:#e2e8f0;">
                ปพ.5 : <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'] . ' (ม.' . $course['grade_level'] . '/' . $course['room_no'] . ')', ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
            <!-- สลับโหมดพิมพ์ ชีต ปกน(p) / คำอธิบาย / ตัวชี้วัด (แผนวัดผล) / sgs(p) / ครบชุด -->
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=pok"
               class="toolbar-btn"
               style="<?= $mode === 'pok' ? 'background:#2563eb; color:#fff;' : 'background:#1e293b; color:#cbd5e1;' ?>">
                <i class="bi bi-file-earmark-text"></i> ชีต ปกน(p)
            </a>
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=desc"
               class="toolbar-btn"
               style="<?= $mode === 'desc' ? 'background:#8b5cf6; color:#fff;' : 'background:#1e293b; color:#cbd5e1;' ?>">
                <i class="bi bi-card-text"></i> ชีต คำอธิบาย
            </a>
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=plan"
               class="toolbar-btn"
               style="<?= $mode === 'plan' ? 'background:#059669; color:#fff;' : 'background:#1e293b; color:#cbd5e1;' ?>">
                <i class="bi bi-check2-square"></i> ชีต ตัวชี้วัด
            </a>
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=sgs"
               class="toolbar-btn"
               style="<?= $mode === 'sgs' ? 'background:#0891b2; color:#fff;' : 'background:#1e293b; color:#cbd5e1;' ?>">
                <i class="bi bi-table"></i> ชีต sgs(p)
            </a>
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=all"
               class="toolbar-btn"
               style="<?= $mode === 'all' ? 'background:#4f46e5; color:#fff;' : 'background:#1e293b; color:#cbd5e1;' ?>">
                <i class="bi bi-collection"></i> รวมครบทุกชีต
            </a>

            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&mode=<?= $mode ?>&download=1"
               class="toolbar-btn" style="background:#334155; color:#e2e8f0;">
                <i class="bi bi-download"></i> ดาวน์โหลด HTML
            </a>

            <button type="button" onclick="window.print()" class="toolbar-btn" style="background:#10b981; color:#ffffff; margin-left:4px;">
                <i class="bi bi-printer-fill"></i> สั่งพิมพ์ / บันทึก PDF (A4 แนวนอน)
            </button>
        </div>
    </div>

    <?= $innerPagesHtml ?>

</body>
</html>
<?php
} catch (\Throwable $e) {
    error_log('[PP5 course_pdf] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $pageTitle = 'เกิดข้อผิดพลาดในการโหลดรายงาน ปพ.5';
    $pageSubtitle = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $activeSystem = 'pp5';
    require_once __DIR__ . '/../components/layout_start.php';
    ?>
    <div class="bg-rose-50 border border-rose-200 rounded-2xl p-6 text-rose-800 m-6 shadow-sm">
        <h3 class="text-base font-black flex items-center gap-2 mb-2">
            <i class="bi bi-exclamation-octagon-fill text-rose-600 text-xl"></i> ไม่สามารถสร้างรายงาน ปพ.5 ได้
        </h3>
        <p class="text-xs text-rose-700 leading-relaxed font-semibold">
            ข้อผิดพลาด: <?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="mt-4 flex items-center gap-2">
            <a href="/pp5/index.php" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition-all">
                กลับแดชบอร์ด ปพ.5
            </a>
            <a href="/pp5/course_summary.php?course_id=<?= (int)($_GET['course_id'] ?? 0) ?>" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-all">
                กลับหน้ารายวิชา
            </a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../components/layout_end.php';
}