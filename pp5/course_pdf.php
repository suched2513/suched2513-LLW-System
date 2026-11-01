<?php
/**
 * pp5/course_pdf.php — สร้างและพิมพ์เอกสารรายงาน ปพ.5 ขนาด A4 ครบ 5 ส่วน (TH Sarabun New)
 *
 * รองรับ:
 * 1. mPDF (หากมี vendor/autoload.php และคลาส \Mpdf\Mpdf บนโฮสต์)
 * 2. โหมดพิมพ์ A4 มาตรฐานผ่านเบราว์เซอร์ (Print / Save as PDF) ที่แสดงสระและวรรณยุกต์ภาษาไทยถูกต้อง
 * 3. โหมดดาวน์โหลดไฟล์เล่ม ปพ.5 (`?download=1`)
 */

require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\Pp5ReportService;

pp5_require_login();

$policy        = new Pp5Policy($pdo);
$reportService = new Pp5ReportService($pdo);

$courseId = (int)($_GET['course_id'] ?? 0);
$data     = $reportService->compileCourseReportData($courseId);

if (!$data || !$policy->canViewCourse($data['course'])) {
    header('Location: /pp5/index.php');
    exit();
}

$course = $data['course'];

// เลือกส่วนที่ต้องการแสดง/พิมพ์ (ค่าเริ่มต้นคือครบทั้ง 5 ส่วน: 1,2,3,4,5)
$rawSections = trim((string)($_GET['sections'] ?? '1,2,3,4,5'));
$sections    = [];
foreach (explode(',', $rawSections) as $sPart) {
    $sNum = (int)trim($sPart);
    if (in_array($sNum, [1, 2, 3, 4, 5], true)) {
        $sections[] = $sNum;
    }
}
if (empty($sections)) {
    $sections = [1, 2, 3, 4, 5];
}

$isDownload = !empty($_GET['download']);
$useMpdf    = !empty($_GET['mpdf']) && class_exists('\Mpdf\Mpdf');

// กรณีมี mPDF และร้องขอไฟล์ PDF โดยตรง
if ($useMpdf) {
    $html = $reportService->renderReportHtml($data, $sections, true);
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'default_font'  => 'garuda',
        'margin_left'   => 14,
        'margin_right'  => 12,
        'margin_top'    => 12,
        'margin_bottom' => 12,
    ]);
    $mpdf->WriteHTML($html);
    $safeRoom = str_replace('/', '-', (string)$course['classroom_name']);
    $pdfName  = "PP5_{$course['subject_code']}_{$safeRoom}.pdf";
    $mpdf->Output($pdfName, $isDownload ? 'D' : 'I');
    exit;
}

// กรณีดาวน์โหลดเป็นไฟล์เอกสาร ปพ.5 พร้อมพิมพ์
if ($isDownload) {
    $safeRoom = str_replace('/', '-', (string)$course['classroom_name']);
    $fileName = "PP5_{$course['academic_year']}_{$course['semester']}_{$course['subject_code']}_ห้อง_{$safeRoom}.html";
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    echo $reportService->renderReportHtml($data, $sections, true);
    exit;
}

$innerPagesHtml = $reportService->renderReportHtml($data, $sections, false);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ปพ.5 — <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'] . ' (' . $course['classroom_name'] . ')', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 10mm 14mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'TH Sarabun New', 'THSarabunNew', 'Sarabun', sans-serif;
            font-size: 14pt;
            line-height: 1.35;
            color: #111827;
            margin: 0;
            padding: 64px 0 32px 0;
            background: #334155;
        }
        .toolbar-no-print {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: #0f172a;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
            font-family: 'Prompt', sans-serif;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
        }
        .toolbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
        }
        .pp5-page {
            width: 210mm;
            min-height: 297mm;
            margin: 10mm auto;
            padding: 12mm 14mm;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            page-break-after: always;
            position: relative;
        }
        .pp5-page:last-child {
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
            .pp5-page {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
        table.pp5-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
        }
        table.pp5-table th,
        table.pp5-table td {
            border: 1px solid #1e293b;
            padding: 3px 5px;
            vertical-align: middle;
        }
        table.pp5-table th {
            background-color: #f8fafc;
            font-weight: 700;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: 700; }
        .section-badge {
            display: inline-block;
            border: 1.5px solid #1e293b;
            padding: 2px 10px;
            font-weight: 700;
            font-size: 12.5pt;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <!-- แถบควบคุมการพิมพ์ด้านบน (ซ่อนอัตโนมัติเวลาสั่งพิมพ์ลงกระดาษ A4 หรือ Save as PDF) -->
    <div class="toolbar-no-print">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="/pp5/course_summary.php?course_id=<?= $courseId ?>" class="toolbar-btn" style="background:#1e293b; color:#e2e8f0;">
                <i class="bi bi-arrow-left"></i> กลับหน้าสรุปรายวิชา
            </a>
            <div style="font-size:13px; font-weight:700;">
                ปพ.5 : <?= htmlspecialchars($course['subject_code'] . ' ' . $course['subject_name'] . ' (ห้อง ' . $course['classroom_name'] . ')', ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&sections=1,2,3,4,5"
               class="toolbar-btn"
               style="<?= count($sections) === 5 ? 'background:#4f46e5; color:#fff;' : 'background:#1e293b; color:#cbd5e1;' ?>">
                ครบ 5 ส่วน
            </a>
            <?php for ($s = 1; $s <= 5; $s++): ?>
                <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>&sections=<?= $s ?>"
                   class="toolbar-btn"
                   style="<?= (count($sections) === 1 && $sections[0] === $s) ? 'background:#4f46e5; color:#fff;' : 'background:#1e293b; color:#cbd5e1;' ?>">
                    ส่วนที่ <?= $s ?>
                </a>
            <?php endfor; ?>

            <button type="button" onclick="window.print()" class="toolbar-btn" style="background:#10b981; color:#ffffff; margin-left:8px;">
                <i class="bi bi-printer-fill"></i> สั่งพิมพ์ / บันทึกเป็น PDF (A4)
            </button>
        </div>
    </div>

    <?= $innerPagesHtml ?>

</body>
</html>
