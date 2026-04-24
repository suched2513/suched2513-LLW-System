<?php
require_once 'config.php';
if (!isLoggedIn()) die("Unauthorized");

$id = $_GET['id'] ?? 0;
$db = connectDB();

// Fetch Data
$stmt = $db->prepare("
    SELECT d.*, p.project_name, p.fiscal_year, fs.source_name, u.firstname, u.lastname, u.role, dept.dept_name
    FROM budget_disbursements d
    JOIN budget_projects p ON d.project_id = p.project_id
    LEFT JOIN wfh_departments dept ON p.department_id = dept.dept_id
    LEFT JOIN budget_fund_sources fs ON d.fund_source_id = fs.source_id
    LEFT JOIN llw_users u ON d.requested_by = u.user_id
    WHERE d.disbursement_id = ?
");
$stmt->execute([$id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) die("Data not found");

// Fetch Items
$stmtItems = $db->prepare("SELECT * FROM budget_disbursement_items WHERE disbursement_id = ? ORDER BY item_id ASC");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Helper for Thai Date
function thaiDate($date) {
    $months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
    $d = date('j', strtotime($date));
    $m = $months[date('n', strtotime($date))];
    $y = date('Y', strtotime($date)) + 543;
    return "$d $m $y";
}

// Convert Number to Thai Words (Simplified version for demo)
function bahtText($amount) {
    // Basic implementation for numbers up to millions
    // In production, use a more robust library
    return " ( " . number_format($amount, 2) . " บาทถ้วน )"; 
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกข้อความ - <?php echo h($req['activity_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @media print {
            @page { size: A4; margin: 2.5cm 2cm; }
            body { font-size: 16px; }
            .no-print { display: none; }
        }
        body {
            font-family: 'Sarabun', sans-serif;
            line-height: 1.2;
            color: #000;
            margin: 0;
            padding: 40px;
            background: #fff;
        }
        .container {
            width: 19cm;
            margin: 0 auto;
        }
        .header {
            position: relative;
            margin-bottom: 20px;
        }
        .krut {
            width: 60px;
            height: auto;
            position: absolute;
            left: 0;
            top: 0;
        }
        .header-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            padding-top: 20px;
        }
        .meta-info {
            margin-top: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .meta-row {
            display: flex;
            margin-bottom: 5px;
        }
        .meta-label {
            font-weight: bold;
            min-width: 100px;
        }
        .meta-value {
            border-bottom: 1px dotted #666;
            flex: 1;
            padding-left: 10px;
        }
        .content {
            margin-top: 20px;
            text-indent: 2.5cm;
            text-align: justify;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table-items th, .table-items td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        .table-items th {
            font-weight: bold;
            background: #f9f9f9;
        }
        .table-items td.left { text-align: left; }
        .table-items td.right { text-align: right; }
        
        .footer-boxes {
            display: grid;
            grid-template-cols: 1fr 1fr 1fr;
            gap: 0;
            margin-top: 40px;
            border: 1px solid #000;
        }
        .footer-box {
            border-right: 1px solid #000;
            padding: 10px;
            font-size: 12px;
        }
        .footer-box:last-child { border-right: none; }
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .signature-line {
            border-bottom: 1px dotted #000;
            width: 150px;
            display: inline-block;
            margin: 20px 0 5px 0;
        }
        .fund-source-list {
            list-style: none;
            padding: 0;
            margin: 5px 0;
        }
        .fund-source-list li {
            margin-bottom: 3px;
        }
        .checkbox {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #000;
            margin-right: 5px;
            vertical-align: middle;
        }
        .checked {
            background: #000;
        }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" style="background: #2563eb; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer;">พิมพ์เอกสาร</button>
    </div>

    <div class="container">
        <div class="header">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/c8/Garuda_Emblem_of_Thailand.svg/1200px-Garuda_Emblem_of_Thailand.svg.png" class="krut">
            <div class="header-title">บันทึกข้อความ</div>
        </div>

        <div class="meta-info">
            <div class="meta-row">
                <div class="meta-label">ส่วนราชการ</div>
                <div class="meta-value">โรงเรียนละลมวิทยา อำเภอภูสิงห์ จังหวัดศรีสะเกษ</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">ที่</div>
                <div class="meta-value"><?php echo h($req['doc_no'] ?: '........../..........'); ?></div>
                <div class="meta-label" style="min-width: 60px; text-align: center;">วันที่</div>
                <div class="meta-value"><?php echo thaiDate($req['request_date']); ?></div>
            </div>
            <div class="meta-row">
                <div class="meta-label">เรื่อง</div>
                <div class="meta-value">ขออนุมัติใช้เงินงบประมาณตามแผนปฏิบัติราชการ (กิจกรรม: <?php echo h($req['activity_name']); ?>)</div>
            </div>
        </div>

        <div style="margin-top: 15px;">
            <span style="font-weight: bold;">เรียน</span> ผู้อำนวยการโรงเรียนละลมวิทยา
        </div>

        <div class="content">
            ข้าพเจ้า <?php echo h($req['firstname'] . ' ' . $req['lastname']); ?> ตำแหน่ง <?php echo getRoleDisplay($req['role']); ?>
            ฝ่าย <?php echo h($req['dept_name'] ?: '....................'); ?> มีความประสงค์ขอใช้เงินเพื่อจัดซื้อ/จัดจ้าง
            ตามโครงการ <?php echo h($req['project_name']); ?> กิจกรรม <?php echo h($req['activity_name']); ?> ตามรายการดังนี้
        </div>

        <table class="table-items">
            <thead>
                <tr>
                    <th width="40">ที่</th>
                    <th>รายการ</th>
                    <th width="80">จำนวน</th>
                    <th width="80">หน่วย</th>
                    <th width="100">ราคา/หน่วย</th>
                    <th width="120">จำนวนเงิน</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td class="left"><?php echo h($item['item_name']); ?></td>
                    <td><?php echo number_format($item['quantity'], 2); ?></td>
                    <td><?php echo h($item['unit']); ?></td>
                    <td class="right"><?php echo number_format($item['price_per_unit'], 2); ?></td>
                    <td class="right"><?php echo number_format($item['total_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <!-- Empty rows for spacing if needed -->
                <?php for($i = count($items); $i < 3; $i++): ?>
                <tr>
                    <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align: right; font-weight: bold;">รวมทั้งสิ้น</td>
                    <td class="right" style="font-weight: bold;"><?php echo number_format($req['total_amount'], 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top: 20px;">
            เหตุผลที่ขอใช้ครั้งนี้ เพื่อ <?php echo h($req['reason'] ?: '............................................................'); ?>
        </div>

        <div class="signature-section">
            <div style="width: 250px;">
                ลงชื่อ............................................................ผู้ขอใช้<br>
                ( <?php echo h($req['firstname'] . ' ' . $req['lastname']); ?> )<br>
                ตำแหน่ง <?php echo getRoleDisplay($req['role']); ?>
            </div>
            <div style="width: 250px;">
                ลงชื่อ............................................................ผู้รับผิดชอบโครงการ<br>
                ( ............................................................ )<br>
                ตำแหน่ง ............................................................
            </div>
        </div>

        <div class="footer-boxes">
            <div class="footer-box">
                <strong>ความเห็นหัวหน้าฝ่ายแผนงาน</strong><br>
                <span class="checkbox"></span> อยู่ในแผน <span class="checkbox"></span> ไม่อยู่ในแผน<br>
                งบประมาณที่ได้รับ.......................บาท<br>
                ใช้ไปแล้ว.......................................บาท<br>
                คงเหลือ..........................................บาท<br>
                ขอใช้ครั้งนี้.......................................บาท<br>
                คงเหลือสุทธิ....................................บาท
            </div>
            <div class="footer-box">
                <strong>การดำเนินงานของฝ่ายพัสดุ</strong><br>
                พัสดุตามรายการที่เสนอ เห็นควร<br>
                <span class="checkbox"></span> จัดซื้อ/จัดจ้างได้<br>
                <span class="checkbox"></span> ไม่สามารถจัดซื้อ/จัดจ้างได้<br><br>
                ลงชื่อ............................................................<br>
                ( ............................................................ )
            </div>
            <div class="footer-box">
                <strong>การตรวจสอบและรับรอง</strong><br>
                เห็นควร <span class="checkbox"></span> อนุมัติ <span class="checkbox"></span> ไม่อนุมัติ โดยใช้เงิน<br>
                <ul class="fund-source-list">
                    <?php foreach (getFundSources() as $fs): ?>
                        <li><span class="checkbox <?php echo $req['fund_source_id'] == $fs['source_id'] ? 'checked' : ''; ?>"></span> <?php echo h($fs['source_name']); ?></li>
                    <?php endforeach; ?>
                </ul>
                ลงชื่อ............................................................<br>
                ( ............................................................ )
            </div>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <strong>คำสั่ง / ผลการพิจารณา</strong><br>
            <span class="checkbox"></span> อนุมัติ <span class="checkbox"></span> ไม่อนุมัติ<br><br>
            ลงชื่อ............................................................<br>
            ( ............................................................ )<br>
            ผู้อำนวยการโรงเรียนละลมวิทยา
        </div>
    </div>
</body>
</html>
