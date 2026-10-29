<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// ข้อมูลหลัก
$docNo = $doc['doc_no'] ?? '     /2570';
$docDate = $doc['doc_date'] ?? thai_date(date('Y-m-d'), false, true);
$schoolName = $doc['school_name'] ?? 'โรงเรียนละลมวิทยา';
$affiliation = $doc['affiliation'] ?? 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร';
$fiscalYearBe = $doc['fiscal_year_be'] ?? '2570';

$requesterName = $doc['requester_name'] ?? '...................................................';
$requesterPosition = $doc['requester_position'] ?? 'ครู';
$department = $doc['department'] ?? 'ฝ่ายบริหารงานทั่วไป';
$workSection = $doc['work_section'] ?? ($doc['department'] ?? 'งานตามแผนปฏิบัติราชการ');

$projectName = $doc['project_name'] ?? '...................................................';
$activityName = $doc['activity_name'] ?? ($doc['project_name'] ?? '...................................................');
$objective = $doc['objective'] ?? 'เพื่อใช้ในการดำเนินกิจกรรมตามแผนปฏิบัติราชการ';
$totalAmount = (float)($doc['total_amount'] ?? 0.0);
$totalAmountText = $doc['total_amount_text'] ?? baht_text($totalAmount);
$inspectorName = $doc['inspector_name'] ?? '...................................................';

// ผู้ลงนาม
$activityLeaderName = $doc['activity_leader_name'] ?? $requesterName;
$budgetHeadName = $doc['budget_head_name'] ?? 'นางชลลัดดา มากนวล';
$planHeadName = $doc['plan_head_name'] ?? 'นางชลลัดดา มากนวล';
$procurementHeadName = $doc['procurement_head_name'] ?? 'นางบานเย็น ภูรักษา';
$financeHeadName = $doc['finance_head_name'] ?? 'นางรัตนา หงษ์โสภา';
$directorName = $doc['director_name'] ?? 'นายสถาน ปรางมาศ';

// ตัวเลขงบประมาณ
$budgetAllocated = (float)($doc['budget_allocated'] ?? $totalAmount);
$budgetSpentPrev = (float)($doc['budget_spent_prev'] ?? 0.0);
$budgetRemainingBefore = $budgetAllocated - $budgetSpentPrev;
$budgetRemainingAfter = $budgetRemainingBefore - $totalAmount;

// รายการค่าใช้จ่ายแนบท้าย
$items = $doc['items'] ?? [];
if (empty($items)) {
    $items = [
        [
            'item_name' => $objective,
            'quantity' => 1,
            'unit' => 'งาน',
            'unit_price' => $totalAmount,
            'amount' => $totalAmount
        ]
    ];
}

// รายการคุณลักษณะเฉพาะ
$specs = $doc['specs'] ?? [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบ ผง 1/1 - <?= e($activityName) ?></title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'TH Sarabun New', 'TH Sarabun PSK', 'Sarabun', sans-serif;
            font-size: 14.5pt;
            line-height: 1.35;
            color: #000000;
            background-color: #F1F5F9;
            margin: 0;
            padding: 20px;
        }
        .action-bar {
            max-width: 210mm;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .btn {
            font-family: 'TH Sarabun New', 'TH Sarabun PSK', 'Sarabun', sans-serif;
            font-size: 14pt;
            padding: 6px 16px;
            border-radius: 6px;
            border: 1px solid #CBD5E1;
            background: #ffffff;
            cursor: pointer;
            text-decoration: none;
            color: #1E293B;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background: #188038;
            color: #ffffff;
            border-color: #188038;
        }
        .btn-primary:hover { background: #13652c; }
        .btn:hover { background: #F8FAFC; }

        /* A4 Page Container */
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 18mm 20mm 15mm 25mm; /* ขอบกระดาษราชการ ซ้าย 2.5 cm, บน-ขวา-ล่าง ~2 cm */
            margin: 0 auto 20px auto;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: relative;
        }

        .form-id-badge {
            position: absolute;
            top: 15mm;
            right: 20mm;
            border: 1.5px solid #000;
            padding: 2px 10px;
            font-size: 13pt;
            font-weight: bold;
            line-height: 1.2;
            text-align: center;
        }

        /* Memo Header with guaranteed clear spacing */
        .memo-header {
            position: relative;
            min-height: 25mm;
            margin-bottom: 5mm;
        }
        .garuda-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 22mm;
            height: 24mm;
        }
        .garuda-img {
            width: 20mm;
            height: auto;
            max-height: 24mm;
            display: block;
        }
        .memo-title-container {
            text-align: center;
            padding-top: 3mm;
        }
        .memo-title {
            font-family: 'TH Sarabun New', 'TH Sarabun PSK', 'Sarabun', sans-serif;
            font-size: 24pt;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 0;
            line-height: 1.2;
        }

        .memo-meta {
            margin-top: 4px;
            margin-bottom: 10px;
            font-size: 14.5pt;
        }
        .meta-line {
            display: flex;
            margin-bottom: 3px;
            align-items: baseline;
        }
        .meta-label {
            font-weight: bold;
            white-space: nowrap;
            margin-right: 6px;
        }
        .meta-value {
            border-bottom: 1px dotted #000;
            flex-grow: 1;
            padding-left: 4px;
        }
        .meta-value-clean {
            flex-grow: 1;
            padding-left: 4px;
        }

        .memo-body {
            text-align: justify;
            text-justify: inter-cluster;
            line-height: 1.35;
            margin-bottom: 12px;
        }
        .memo-p {
            text-indent: 2.5cm;
            margin: 0;
            text-align: justify;
            text-justify: inter-cluster;
            line-height: 1.35;
            font-size: 14.5pt;
        }

        /* Signatures Grid */
        .sig-col-triple {
            display: flex;
            justify-content: space-between;
            margin-top: 14px;
            margin-bottom: 12px;
            gap: 8px;
        }
        .sig-block {
            width: 32%;
            text-align: center;
            font-size: 12.5pt;
            line-height: 1.3;
        }
        .sig-line {
            white-space: nowrap;
            font-size: 12pt;
        }
        .sig-name {
            margin-top: 4px;
            font-size: 12pt;
        }
        .sig-role {
            font-size: 11pt;
            color: #333333;
        }

        /* Approval Table 2x2 */
        .approval-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 12pt;
        }
        .approval-table td {
            border: 1.5px solid #000;
            padding: 6px 8px;
            vertical-align: top;
            width: 50%;
            line-height: 1.25;
        }
        .box-title {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 5px;
        }
        .checkbox-item {
            margin-bottom: 2px;
        }
        .checkbox-square {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #000;
            margin-right: 5px;
            vertical-align: middle;
        }

        /* Table Attachment */
        .attachment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 12px;
            font-size: 13.5pt;
        }
        .attachment-table th, .attachment-table td {
            border: 1.5px solid #000;
            padding: 5px 8px;
            vertical-align: middle;
        }
        .attachment-table th {
            text-align: center;
            font-weight: bold;
            background-color: #F8FAFC;
        }

        /* Print Media Style */
        @media print {
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 14pt !important;
            }
            .action-bar {
                display: none !important;
            }
            .page {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 16mm 18mm 14mm 24mm !important;
                page-break-after: always !important;
            }
            .page:last-child {
                page-break-after: auto !important;
            }
        }
    </style>
</head>
<body>

<!-- Control Action Bar -->
<div class="action-bar no-print">
    <div>
        <a href="<?= $baseUrl ?>/budgets" class="btn">
            <i class="bi bi-arrow-left"></i> กลับหน้างบประมาณ
        </a>
    </div>
    <div style="font-weight: bold; color: #0B2545;">
        <i class="bi bi-file-earmark-text me-1"></i> แบบ ผง 1/1 - บันทึกขออนุมัติใช้เงินงบประมาณ
    </div>
    <div style="display: flex; gap: 8px;">
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> สั่งพิมพ์เอกสาร (Print / PDF)
        </button>
    </div>
</div>

<!-- ================= PAGE 1: บันทึกข้อความ ขออนุมัติใช้เงินงบประมาณ ================= -->
<div class="page">
    <div class="form-id-badge">
        แบบ ผง 1/1
    </div>

    <!-- Header: Garuda + Title with separate container to prevent any overlap -->
    <div class="memo-header">
        <div class="garuda-container">
            <img src="<?= $baseUrl ?>/assets/garuda.png" alt="ตราครุฑ" class="garuda-img">
        </div>
        <div class="memo-title-container">
            <h1 class="memo-title">บันทึกข้อความ</h1>
        </div>
    </div>

    <div class="memo-meta">
        <div class="meta-line">
            <span class="meta-label">ส่วนราชการ</span>
            <span class="meta-value-clean"><?= e($schoolName) ?>  <?= e($affiliation) ?></span>
        </div>
        <div style="display: flex; gap: 20px;">
            <div class="meta-line" style="flex: 1;">
                <span class="meta-label">ที่</span>
                <span class="meta-value"><?= e($docNo) ?></span>
            </div>
            <div class="meta-line" style="flex: 1;">
                <span class="meta-label">วันที่</span>
                <span class="meta-value"><?= e($docDate) ?></span>
            </div>
        </div>
        <div class="meta-line">
            <span class="meta-label">เรื่อง</span>
            <span class="meta-value-clean">ขออนุมัติใช้เงินงบประมาณตามแผนปฏิบัติราชการ ประจำปีงบประมาณ พ.ศ. <?= e($fiscalYearBe) ?></span>
        </div>
        <div class="meta-line">
            <span class="meta-label">เรียน</span>
            <span class="meta-value-clean">ผู้อำนวยการโรงเรียนละลมวิทยา</span>
        </div>
    </div>

    <div class="memo-body">
        <p class="memo-p">
            ด้วย ข้าพเจ้า <?= e($requesterName) ?> ตำแหน่ง <?= e($requesterPosition) ?> รับผิดชอบ<?= e($workSection) ?> <?= e($department) ?> มีความประสงค์ขอใช้เงินงบประมาณตามแผนปฏิบัติราชการ ประจำปีงบประมาณ พ.ศ. <?= e($fiscalYearBe) ?> ตามโครงการ<?= e($projectName) ?> กิจกรรม<?= e($activityName) ?> เพื่อ<?= e($objective) ?> เป็นจำนวนเงิน <?= money_format_th($totalAmount) ?> บาท (<?= e($totalAmountText) ?>) ตามรายละเอียดแนบท้าย และขอเสนอ <?= e($inspectorName) ?> เป็นผู้ตรวจรับพัสดุ
        </p>
        <p class="memo-p" style="margin-top: 8px;">
            จึงเรียนมาเพื่อโปรดพิจารณา
        </p>
    </div>

    <!-- ลายมือชื่อ 3 คน -->
    <div class="sig-col-triple">
        <div class="sig-block">
            <div class="sig-line">ลงชื่อ..................................ผู้ขอใช้</div>
            <div class="sig-name">( <?= e($requesterName) ?> )</div>
            <div class="sig-role"><?= e($requesterPosition) ?></div>
        </div>
        <div class="sig-block">
            <div class="sig-line">ลงชื่อ......................ผู้รับผิดชอบกิจกรรม</div>
            <div class="sig-name">( <?= e($activityLeaderName) ?> )</div>
            <div class="sig-role">ผู้รับผิดชอบกิจกรรม</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">ลงชื่อ......................หัวหน้าฝ่ายงบประมาณฯ</div>
            <div class="sig-name">( <?= e($budgetHeadName) ?> )</div>
            <div class="sig-role">หัวหน้าฝ่ายงบประมาณฯ</div>
        </div>
    </div>

    <!-- ตารางความเห็นกล่อง 1 และ กล่อง 2 -->
    <table class="approval-table">
        <tr>
            <!-- กล่องที่ 1: แผนงาน -->
            <td>
                <div class="box-title">ความเห็นของหัวหน้างานแผนงาน</div>
                <div style="display: flex; gap: 15px; margin-bottom: 4px;">
                    <span class="checkbox-item"><span class="checkbox-square"></span> อยู่ในแผน</span>
                    <span class="checkbox-item"><span class="checkbox-square"></span> ไม่อยู่นอกแผน</span>
                </div>
                <div>งบประมาณที่ได้รับ <span style="float: right; font-weight: bold;"><?= money_format_th($budgetAllocated) ?> บาท</span></div>
                <div>ใช้ไปแล้ว <span style="float: right;"><?= money_format_th($budgetSpentPrev) ?> บาท</span></div>
                <div>คงเหลือ <span style="float: right;"><?= money_format_th($budgetRemainingBefore) ?> บาท</span></div>
                <div>ขอใช้ครั้งนี้ <span style="float: right; font-weight: bold;"><?= money_format_th($totalAmount) ?> บาท</span></div>
                <div style="border-top: 1px solid #ccc; padding-top: 2px;">คงเหลือสุทธิ <span style="float: right; font-weight: bold; color: #188038;"><?= money_format_th($budgetRemainingAfter) ?> บาท</span></div>
                <div style="margin-top: 4px;">เห็นควรดำเนินการ</div>
                <div style="text-align: center; margin-top: 10px;">
                    <div>ลงชื่อ....................................................</div>
                    <div style="margin-top: 2px;">( <?= e($planHeadName) ?> )</div>
                    <div style="font-size: 11pt; color: #475569;">หัวหน้างานแผนงาน</div>
                </div>
            </td>

            <!-- กล่องที่ 2: พัสดุ -->
            <td>
                <div class="box-title">การดำเนินงานของงานพัสดุ</div>
                <div style="margin-top: 6px;">พัสดุตามรายการที่เสนอ เห็นควร:</div>
                <div style="margin-top: 8px; margin-left: 8px;">
                    <div class="checkbox-item"><span class="checkbox-square"></span> จัดซื้อ/จัดจ้างได้</div>
                    <div class="checkbox-item" style="margin-top: 6px;"><span class="checkbox-square"></span> ไม่สามารถจัดซื้อ/จัดจ้างได้</div>
                </div>
                <div style="text-align: center; margin-top: 45px;">
                    <div>ลงชื่อ....................................................</div>
                    <div style="margin-top: 2px;">( <?= e($procurementHeadName) ?> )</div>
                    <div style="font-size: 11pt; color: #475569;">หัวหน้าเจ้าหน้าที่พัสดุ</div>
                </div>
            </td>
        </tr>
    </table>
</div>

<!-- ================= PAGE 2: การตรวจสอบการเงิน และคำสั่งผู้อำนวยการ ================= -->
<div class="page">
    <div class="form-id-badge" style="font-size: 12pt;">
        หน้า 2
    </div>

    <table class="approval-table" style="margin-top: 6mm;">
        <tr>
            <!-- กล่องที่ 3: การเงิน -->
            <td>
                <div class="box-title">การตรวจสอบและรับรอง</div>
                <div style="margin-top: 6px; text-indent: 1.2cm;">
                    ได้ทำการตรวจสอบเอกสารหลักฐานแล้วถูกต้อง ไม่มีเงินยืมค้างและมีงบประมาณเพียงพอ เห็นควร:
                </div>
                <div style="margin-top: 10px; margin-left: 10px;">
                    <div class="checkbox-item"><span class="checkbox-square"></span> อนุมัติ</div>
                    <div class="checkbox-item" style="margin-top: 6px;"><span class="checkbox-square"></span> ไม่อนุมัติ ให้เบิกจ่ายตามโครงการ</div>
                </div>
                <div style="text-align: center; margin-top: 50px;">
                    <div>ลงชื่อ....................................................</div>
                    <div style="margin-top: 2px;">( <?= e($financeHeadName) ?> )</div>
                    <div style="font-size: 11pt; color: #475569;">หัวหน้างานการเงิน</div>
                </div>
            </td>

            <!-- กล่องที่ 4: ผู้อำนวยการโรงเรียน -->
            <td>
                <div class="box-title">ความเห็นของผู้อำนวยการโรงเรียน</div>
                <div style="margin-top: 6px; text-indent: 1.2cm;">
                    ได้ทำการตรวจสอบรายการตามเสนอแล้ว:
                </div>
                <div style="margin-top: 6px; margin-left: 10px;">
                    <div class="checkbox-item"><span class="checkbox-square"></span> <strong>อนุมัติ</strong></div>
                    <div class="checkbox-item"><span class="checkbox-square"></span> ไม่อนุมัติ</div>
                </div>
                <div style="margin-top: 5px; font-weight: bold;">โดยใช้เงิน:</div>
                <div style="margin-left: 10px; margin-top: 3px;">
                    <div class="checkbox-item"><span class="checkbox-square"></span> เงินอุดหนุนทั่วไป</div>
                    <div class="checkbox-item"><span class="checkbox-square"></span> เงินรายได้สถานศึกษา</div>
                    <div class="checkbox-item"><span class="checkbox-square"></span> เงินบริจาค</div>
                    <div class="checkbox-item"><span class="checkbox-square"></span> เงินอื่น ๆ .....................................................</div>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <div>ลงชื่อ....................................................</div>
                    <div style="margin-top: 2px;">( <?= e($directorName) ?> )</div>
                    <div style="font-size: 11pt; color: #475569;">ผู้อำนวยการโรงเรียนละลมวิทยา</div>
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 12mm;">
        <h3 style="text-align: center; font-size: 16pt; margin-bottom: 4px;">
            รายละเอียดแนบท้ายบันทึกขออนุมัติใช้เงินงบประมาณตามแผนปฏิบัติราชการ
        </h3>
        <div style="text-align: center; font-size: 14pt; margin-bottom: 12px;">
            ประจำปีงบประมาณ พ.ศ. <?= e($fiscalYearBe) ?>
        </div>

        <table class="attachment-table">
            <thead>
                <tr>
                    <th style="width: 55px;">ลำดับที่</th>
                    <th>รายการ</th>
                    <th style="width: 110px;">จำนวน / หน่วย</th>
                    <th style="width: 120px;">ราคา / หน่วย</th>
                    <th style="width: 140px;">จำนวนเงิน (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $it): ?>
                    <tr>
                        <td style="text-align: center;"><?= $idx + 1 ?></td>
                        <td><?= e($it['item_name'] ?? $it['item_description'] ?? '') ?></td>
                        <td style="text-align: center;"><?= (float)($it['quantity'] ?? 1) ?> <?= e($it['unit'] ?? 'หน่วย') ?></td>
                        <td style="text-align: right;"><?= money_format_th((float)($it['unit_price'] ?? $it['amount'])) ?></td>
                        <td style="text-align: right; font-weight: bold;"><?= money_format_th((float)($it['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align: center; font-weight: bold;">รวมทั้งสิ้น</td>
                    <td style="text-align: right; font-weight: bold; font-size: 15pt;"><?= money_format_th($totalAmount) ?></td>
                </tr>
                <tr>
                    <td colspan="5" style="text-align: center; font-weight: bold; background-color: #F8FAFC;">
                        ( <?= e($totalAmountText) ?> )
                    </td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
            <div style="width: 50%; text-align: center;">
                <div>ลงชื่อ....................................................ผู้ขอใช้ / ผู้กำหนดรายละเอียด</div>
                <div style="margin-top: 4px;">( <?= e($requesterName) ?> )</div>
                <div style="color: #475569; font-size: 11pt;"><?= e($requesterPosition) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($specs)): ?>
<!-- ================= PAGE 3: รายละเอียดคุณลักษณะเฉพาะ (ถ้ามี) ================= -->
<div class="page">
    <div class="form-id-badge" style="font-size: 12pt;">
        หน้า 3
    </div>

    <div style="margin-top: 8mm;">
        <h3 style="text-align: center; font-size: 16pt; margin-bottom: 6px;">
            ตารางแสดงรายละเอียดคุณลักษณะเฉพาะของพัสดุ/ครุภัณฑ์
        </h3>
        <div style="text-align: center; font-size: 14pt; margin-bottom: 15px;">
            แนบท้ายบันทึกข้อความ ที่ <?= e($docNo) ?> ลงวันที่ <?= e($docDate) ?>
        </div>

        <table class="attachment-table">
            <thead>
                <tr>
                    <th style="width: 70px;">ลำดับที่</th>
                    <th>รายละเอียด / คุณลักษณะเฉพาะ</th>
                    <th style="width: 200px;">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($specs as $sIdx => $sp): ?>
                    <tr>
                        <td style="text-align: center;"><?= e($sp['item_no'] ?: ($sIdx + 1)) ?></td>
                        <td><?= nl2br(e($sp['description'])) ?></td>
                        <td><?= e($sp['note'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 25px; display: flex; justify-content: flex-end;">
            <div style="width: 50%; text-align: center;">
                <div>ลงชื่อ....................................................ผู้กำหนดคุณลักษณะเฉพาะ</div>
                <div style="margin-top: 4px;">( <?= e($requesterName) ?> )</div>
                <div style="color: #475569; font-size: 11pt;"><?= e($requesterPosition) ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>
