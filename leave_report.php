<?php
// leave_report.php — บันทึกข้อความ (พิมพ์) A4
session_start();
require_once 'config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: login.php'); exit();
}

$r_id = (int)($_GET['r_id'] ?? 0);
if (!$r_id) { die('ไม่พบ r_id'); }

try {
    $pdo = getPdo();

    // ดึงข้อมูลคำขอ + ชื่อผู้ขอ
    $stmt = $pdo->prepare("
        SELECT r.*,
               CONCAT(u.firstname,' ',u.lastname) AS t_name,
               u.firstname, u.lastname
        FROM leave_requests r
        JOIN llw_users u ON r.teacher_id = u.user_id
        WHERE r.r_id = ?
    ");
    $stmt->execute([$r_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$req) { die('ไม่พบข้อมูลคำขอ'); }

    // ดึงรายการครูสอนแทน
    $stmt2 = $pdo->prepare("
        SELECT rd.*,
               CONCAT(u.firstname,' ',u.lastname) AS sub_name
        FROM leave_request_details rd
        JOIN llw_users u ON rd.sub_teacher_id = u.user_id
        WHERE rd.r_id = ?
        ORDER BY rd.period ASC
    ");
    $stmt2->execute([$r_id]);
    $details = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // ดึงชื่อ ผอ. จาก settings
    $sStmt = $pdo->query("SELECT boss_name FROM wfh_system_settings LIMIT 1");
    $sRow     = $sStmt->fetch(PDO::FETCH_ASSOC);
    $bossName = htmlspecialchars($sRow['boss_name'] ?? '', ENT_QUOTES, 'UTF-8');

} catch (Exception $e) {
    die('เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()));
}

// แปลงวันที่เป็น พ.ศ.
function thaiDate($dateStr) {
    if (!$dateStr) return '..........................';
    $ts = strtotime($dateStr);
    $months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    $d = date('j', $ts);
    $m = $months[(int)date('n', $ts)];
    $y = date('Y', $ts) + 543;
    return "$d $m $y";
}

$reqDate    = thaiDate($req['req_date'] ?? date('Y-m-d'));
$createdAt  = thaiDate($req['created_at'] ?? date('Y-m-d'));
$tName      = htmlspecialchars($req['t_name'] ?? '');
$reason     = htmlspecialchars($req['reason'] ?? '');
$detail     = htmlspecialchars($req['detail'] ?? '');
$timeStart  = htmlspecialchars($req['time_start'] ?? '');
$timeEnd    = htmlspecialchars($req['time_end'] ?? '');
$totalHr    = htmlspecialchars($req['total_hr'] ?? '');
$statusText = match((int)($req['status_boss1'] ?? 0)) {
    1 => 'อนุมัติ', 2 => 'ไม่อนุมัติ', default => 'รออนุมัติ'
};
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>บันทึกข้อความ — <?= $tName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Sarabun', 'TH Sarabun New', serif;
    font-size: 14pt;
    color: #000;
    background: #f0f0f0;
  }

  /* ── Print Trigger ── */
  .no-print {
    background: #1e40af;
    color: #fff;
    padding: 12px 28px;
    border: none;
    border-radius: 8px;
    font-size: 14pt;
    cursor: pointer;
    font-family: 'Sarabun', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .no-print:hover { background: #1d4ed8; }
  .print-bar {
    position: fixed; top: 0; left: 0; right: 0;
    background: #fff;
    padding: 10px 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 100;
  }
  .print-bar a {
    color: #64748b;
    text-decoration: none;
    font-size: 12pt;
  }

  /* ── Page ── */
  .page-wrapper {
    padding: 60px 20px 20px;
    display: flex;
    justify-content: center;
  }
  .page {
    width: 210mm;
    min-height: 297mm;
    background: #fff;
    padding: 14mm 18mm 10mm;
    box-shadow: 0 4px 24px rgba(0,0,0,0.12);
    position: relative;
  }

  /* ── Header ── */
  .doc-header {
    text-align: center;
    margin-bottom: 8pt;
  }
  .doc-header img.emblem {
    width: 60pt;
    margin-bottom: 6pt;
  }
  .doc-title {
    font-size: 20pt;
    font-weight: 700;
    letter-spacing: 2pt;
  }
  .doc-subtitle {
    font-size: 14pt;
    color: #333;
    margin-top: 2pt;
  }

  /* ── Meta line ── */
  .meta-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4pt;
    font-size: 13pt;
  }
  .meta-line span { min-width: 40%; }

  /* ── Underline field ── */
  .field {
    display: inline-block;
    border-bottom: 1px solid #000;
    min-width: 200pt;
    padding: 0 4pt;
    font-size: 15pt;
  }
  .field-long  { min-width: 340pt; }
  .field-short { min-width: 80pt; }

  /* ── Body paragraphs ── */
  .body-text {
    font-size: 15pt;
    line-height: 1.7;
    text-indent: 36pt;
    margin-bottom: 4pt;
  }
  .body-text.no-indent { text-indent: 0; }

  /* ── Substitution Table ── */
  .sub-table {
    width: 100%;
    border-collapse: collapse;
    margin: 10pt 0;
    font-size: 14pt;
  }
  .sub-table th, .sub-table td {
    border: 1px solid #555;
    padding: 5pt 8pt;
    text-align: center;
  }
  .sub-table th {
    background: #f5f5f5;
    font-weight: 600;
  }
  .sub-table td:nth-child(2) { text-align: left; }
  .sub-table td:nth-child(4) { text-align: left; }

  /* ── Signature blocks ── */
  .sig-block {
    text-align: center;
    margin: 10pt auto;
    width: 180pt;
  }
  .sig-line {
    border-bottom: 1px solid #000;
    height: 28pt;
    margin-bottom: 4pt;
  }
  .sig-name { font-size: 14pt; }
  .sig-role { font-size: 12pt; color: #444; }

  .approval-grid {
    page-break-inside: avoid;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16pt;
    margin-top: 16pt;
  }
  .approval-box {
    border: 1px solid #aaa;
    border-radius: 6pt;
    padding: 8pt 12pt;
    font-size: 14pt;
  }
  .approval-box h4 {
    font-size: 15pt;
    font-weight: 700;
    border-bottom: 1px solid #ddd;
    padding-bottom: 6pt;
    margin-bottom: 8pt;
  }
  .checkbox-row {
    display: flex;
    align-items: center;
    gap: 8pt;
    margin-bottom: 6pt;
  }
  .checkbox-box {
    width: 14pt; height: 14pt;
    border: 1.5px solid #000;
    display: inline-block;
    flex-shrink: 0;
  }
  .checkbox-box.checked { background: #000; }
  .remark-line {
    border-bottom: 1px solid #aaa;
    min-height: 14pt;
    margin: 6pt 0;
  }
  .approved-stamp {
    color: #16a34a;
    font-size: 20pt;
    font-weight: 700;
    border: 2px solid #16a34a;
    padding: 2pt 10pt;
    display: inline-block;
    border-radius: 4pt;
    margin: 4pt 0;
    transform: rotate(-5deg);
  }
  .rejected-stamp {
    color: #dc2626;
    font-size: 20pt;
    font-weight: 700;
    border: 2px solid #dc2626;
    padding: 2pt 10pt;
    display: inline-block;
    border-radius: 4pt;
    margin: 4pt 0;
    transform: rotate(-5deg);
  }

  /* ── Print ── */
  @media print {
    .print-bar { display: none !important; }
    .page-wrapper { padding: 0; background: none; }
    .page { box-shadow: none; margin: 0; }
    body { background: #fff; }
  }
  @page { size: A4; margin: 0; }
</style>
</head>
<body>

<!-- Print bar -->
<div class="print-bar no-print">
  <a href="leave_system.php">← กลับ</a>
  <button class="no-print" onclick="window.print()">🖨️ พิมพ์เอกสาร</button>
  <span style="color:#64748b; font-size:12pt">คำขอ #<?= $r_id ?> — สถานะ: <strong><?= $statusText ?></strong></span>
</div>

<div class="page-wrapper">
<div class="page">

  <!-- Header -->
  <div class="doc-header">
    <img class="emblem" src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a9/Seal_of_the_Ministry_of_Education_of_Thailand.svg/120px-Seal_of_the_Ministry_of_Education_of_Thailand.svg.png"
         onerror="this.style.display='none'" alt="">
    <div class="doc-title">บันทึกข้อความ</div>
  </div>

  <!-- Meta -->
  <div class="meta-line">
    <span>ส่วนราชการ &nbsp; โรงเรียนละลมวิทยา &nbsp; สพม.ศรีสะเกษ ยโสธร</span>
    <span>วันที่ &nbsp;<span class="field field-short"><?= $reqDate ?></span></span>
  </div>
  <div style="margin-bottom:6pt; font-size:15pt;">
    เรื่อง &nbsp;<span class="field field-long">ขออนุญาตออกนอกบริเวณโรงเรียน</span>
  </div>
  <div style="margin-bottom:10pt; font-size:15pt;">
    เรียน &nbsp;<span class="field field-long">ผู้อำนวยการโรงเรียนละลมวิทยา</span>
  </div>

  <hr style="border:0.5px solid #999; margin-bottom:10pt;">

  <!-- Body -->
  <div class="body-text">
    ข้าพเจ้า <span class="field" style="min-width:180pt"><?= $tName ?></span>
    ตำแหน่ง <span class="field" style="min-width:140pt">ครู</span>
    มีความประสงค์จะขออนุญาตออกนอกบริเวณโรงเรียน
  </div>

  <div class="body-text">
    เนื่องจาก <span class="field" style="min-width:300pt"><?= $reason ?></span>
    <?php if ($detail): ?>
    รายละเอียด <span class="field" style="min-width:280pt"><?= $detail ?></span>
    <?php endif; ?>
  </div>

  <div class="body-text">
    ตั้งแต่เวลา <span class="field field-short"><?= $timeStart ?></span> น.
    ถึงเวลา <span class="field field-short"><?= $timeEnd ?></span> น.
    รวมเวลา <span class="field field-short"><?= $totalHr ?></span> ชั่วโมง
    วันที่ <span class="field" style="min-width:120pt"><?= $reqDate ?></span>
  </div>

  <?php if ($req['has_class'] && count($details) > 0): ?>
  <div class="body-text">
    โดยในช่วงเวลาดังกล่าวมีคาบการสอน ข้าพเจ้าได้จัดครูสอนแทนดังนี้
  </div>
  <table class="sub-table">
    <thead>
      <tr>
        <th width="10%">คาบที่</th>
        <th width="30%">วิชา / สาขา</th>
        <th width="15%">ชั้น</th>
        <th width="45%">ครูผู้สอนแทน</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($details as $d): ?>
      <tr>
        <td><?= htmlspecialchars($d['period'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($d['subject'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($d['class_level'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($d['sub_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php
      // เติมแถวว่างให้ครบ 5 แถว
      for ($i = count($details); $i < 5; $i++): ?>
      <tr>
        <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
      </tr>
      <?php endfor; ?>
    </tbody>
  </table>
  <?php else: ?>
  <br>
  <?php endif; ?>

  <div class="body-text">
    จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ จักเป็นพระคุณยิ่ง
  </div>

  <!-- Signature (requester) -->
  <div style="display:flex; justify-content:flex-end; margin-top:16pt; margin-right:20pt;">
    <div class="sig-block">
      <div class="sig-line"></div>
      <div class="sig-name">(<?= $tName ?>)</div>
      <div class="sig-role">ผู้ขออนุญาต</div>
    </div>
  </div>

  <!-- Approval boxes -->
  <div class="approval-grid">

    <!-- ฝ่ายบริหาร -->
    <div class="approval-box">
      <h4><i>ฝ่ายบริหาร</i></h4>
      <div class="checkbox-row">
        <span class="checkbox-box"></span> ความเห็นชอบ
      </div>
      <div class="checkbox-row">
        <span class="checkbox-box"></span> ไม่เห็นชอบ
      </div>
      <div class="remark-line"></div>
      <div class="remark-line"></div>
      <div style="margin-top:10pt; text-align:center;">
        <div class="sig-line" style="width:130pt; margin:0 auto;"></div>
        <div class="sig-name">(.........................)</div>
        <div class="sig-role">ตำแหน่ง (แจ้งชื่อ)</div>
      </div>
    </div>

    <!-- ผอ. -->
    <div class="approval-box">
      <h4><i>ความเห็นผู้บริหาร</i></h4>
      <?php if ((int)($req['status_boss1'] ?? 0) === 1): ?>
        <div style="text-align:center; margin:8pt 0;">
          <span class="approved-stamp">✓ อนุมัติ</span>
        </div>
      <?php elseif ((int)($req['status_boss1'] ?? 0) === 2): ?>
        <div style="text-align:center; margin:8pt 0;">
          <span class="rejected-stamp">✗ ไม่อนุมัติ</span>
        </div>
      <?php else: ?>
      <div class="checkbox-row">
        <span class="checkbox-box"></span> อนุมัติ
      </div>
      <div class="checkbox-row">
        <span class="checkbox-box"></span> ไม่อนุมัติ
      </div>
      <?php endif; ?>
      <div class="remark-line"></div>
      <div class="remark-line"></div>
      <div style="margin-top:10pt; text-align:center;">
        <div class="sig-line" style="width:130pt; margin:0 auto;"></div>
        <div class="sig-name">(<?= $bossName ?: '.............................' ?>)</div>
        <div class="sig-role">ผู้อำนวยการโรงเรียนละลมวิทยา</div>
      </div>
    </div>

  </div><!-- /approval-grid -->

</div><!-- /page -->
</div><!-- /page-wrapper -->

</body>
</html>
