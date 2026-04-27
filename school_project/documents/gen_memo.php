<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$req = $db->prepare("SELECT pr.*,bp.project_name,bp.activity,d.name AS dept_name,u.full_name AS teacher_name FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id JOIN departments d ON bp.department_id=d.id JOIN users u ON pr.user_id=u.id WHERE pr.id=?");
$req->execute([$id]); $r = $req->fetch();
if (!$r) die('ไม่พบข้อมูล');
$items = $db->prepare("SELECT * FROM request_items WHERE request_id=? ORDER BY item_order");
$items->execute([$id]); $itemList = $items->fetchAll();
$sigs = $db->query("SELECT * FROM signatories WHERE is_active=1 ORDER BY order_no")->fetchAll();
$sigMap = [];
foreach ($sigs as $s) $sigMap[$s['role_label']] = $s;
auditLog('download','project_request',$id);

// Check PhpWord available
$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorPath)) {
    // Fallback: output HTML version for printing
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8">
<title>บันทึกข้อความ</title>
<style>
body{font-family:'TH Sarabun New',Sarabun,sans-serif;font-size:16pt;margin:2cm 2.5cm}
table{width:100%;border-collapse:collapse}
td,th{padding:4px 8px}
.border-table td,.border-table th{border:1px solid #000}
h2{text-align:center;font-size:18pt}
.text-center{text-align:center}
.text-right{text-align:right}
@media print{.no-print{display:none}}
</style>
</head><body>
<div class="no-print" style="margin-bottom:20px">
  <button onclick="window.print()" style="padding:8px 20px;background:#1a56db;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px">🖨️ พิมพ์เอกสาร</button>
  <a href="javascript:history.back()" style="margin-left:10px;color:#64748b">← กลับ</a>
</div>
<h2>บันทึกข้อความ</h2>
<table style="margin-bottom:20px">
  <tr><td width="200"><strong>ส่วนราชการ</strong></td><td><?= h(SCHOOL_NAME . ' ' . SCHOOL_DISTRICT . ' ' . SCHOOL_PROVINCE) ?></td></tr>
  <tr><td><strong>ที่</strong></td><td><?= h($r['project_no'] ?? '') ?></td><td width="120"><strong>วันที่</strong></td><td><?= formatDate($r['request_date']) ?></td></tr>
  <tr><td><strong>เรื่อง</strong></td><td colspan="3">ขออนุมัติใช้เงินงบประมาณตามแผนปฏิบัติราชการ</td></tr>
  <tr><td><strong>เรียน</strong></td><td colspan="3">ผู้อำนวยการ<?= h(SCHOOL_NAME) ?></td></tr>
</table>
<p style="text-indent:2cm">ข้าพเจ้า <strong><?= h($r['teacher_name']) ?></strong> รับผิดชอบงาน/กลุ่มสาระการเรียนรู้/หัวหน้าฝ่าย<?= h($r['dept_name']) ?> มีความประสงค์ขอใช้เงินเพื่อ <?= $r['proc_type']==='hire'?'จัดจ้าง':'จัดซื้อ' ?></p>
<p style="text-indent:2cm">ตามโครงการ <strong><?= h($r['project_name']) ?></strong> กิจกรรม <?= h($r['activity'] ?? '') ?></p>
<p style="text-indent:2cm">ตามรายการดังนี้</p>
<table class="border-table" style="margin:16px 0">
  <thead><tr style="background:#eee"><th width="40">ที่</th><th>รายการ</th><th width="80">จำนวน</th><th width="60">หน่วย</th><th width="120">ราคามาตรฐาน/ราคากลาง</th><th width="120">จำนวนเงินที่ขอซื้อ/จ้าง</th></tr></thead>
  <tbody>
  <?php foreach ($itemList as $i => $item): ?>
  <tr><td class="text-center"><?= $i+1 ?></td><td><?= h($item['item_name']) ?></td><td class="text-center"><?= $item['quantity'] ?></td><td class="text-center"><?= h($item['unit']) ?></td><td class="text-right"><?= formatMoney($item['unit_price']) ?></td><td class="text-right"><?= formatMoney($item['total_price']) ?></td></tr>
  <?php endforeach; ?>
  <tr><td colspan="4" class="text-center">(<?= numberToThai($r['amount_requested']) ?>)</td><td class="text-center">รวมทั้งสิ้น</td><td class="text-right"><strong><?= formatMoney($r['amount_requested']) ?></strong></td></tr>
  </tbody>
</table>
<p style="text-indent:2cm">เหตุผลที่ขอใช้ครั้งนี้ เพื่อ <?= h($r['reason']) ?></p>
<p style="text-indent:2cm">และขอเสนอผู้ตรวจรับพัสดุ ดังนี้ <?= h($r['inspector_name'] ?? '') ?></p>
<p style="text-indent:2cm">จึงเรียนมาเพื่อโปรดพิจารณา</p>
<table style="margin-top:30px">
  <tr>
    <td width="50%" class="text-center">ลงชื่อ......................................ผู้ขอใช้<br>(<?= h($r['teacher_name']) ?>)</td>
    <td width="50%" class="text-center">ลงชื่อ......................................ผู้รับผิดชอบโครงการ<br>(<?= h($sigMap['ผู้รับผิดชอบโครงการ']['full_name'] ?? '') ?>)</td>
  </tr>
</table>
<table class="border-table" style="margin-top:24px">
  <tr>
    <td width="33%" style="vertical-align:top;padding:12px">
      <strong>ความเห็นของหัวหน้าฝ่ายแผนงาน</strong><br><br>
      □ อยู่ในแผน □ ไม่อยู่ในแผน<br>
      งบประมาณที่ได้รับ.................บาท<br>ใช้ไปแล้ว.................บาท<br>คงเหลือ.................บาท<br>ขอใช้ครั้งนี้.................บาท<br>คงเหลือสุทธิ.................บาท<br>เห็นควรดำเนินการ<br><br>
      ลงชื่อ.................................<br>(<?= h($sigMap['หัวหน้าแผนงาน']['full_name'] ?? '') ?>)<br>หัวหน้างานแผนงาน
    </td>
    <td width="33%" style="vertical-align:top;padding:12px">
      <strong>การดำเนินงานของฝ่ายพัสดุ</strong><br><br>
      พัสดุตามรายการที่เสนอ<br>เห็นควร<br>□ จัดซื้อ/จัดจ้างได้<br>□ ไม่สามารถจัดซื้อ/จัดจ้างได้<br><br><br>
      ลงชื่อ.................................<br>(<?= h($sigMap['หัวหน้าพัสดุ']['full_name'] ?? '') ?>)<br>หัวหน้าเจ้าหน้าที่พัสดุ
    </td>
    <td width="33%" style="vertical-align:top;padding:12px">
      <strong>การตรวจสอบและรับรอง</strong><br><br>
      ได้ทำการตรวจสอบรายการตามเสนอแล้ว<br>เห็นควร □ อนุมัติ □ ไม่อนุมัติ โดยใช้เงิน<br>□ เงินอุดหนุน □ พัฒนาคุณภาพผู้เรียน<br>□ เงินรายได้สถานศึกษา □ เงินสำรองจ่าย<br><br>
      ลงชื่อ.................................<br>(<?= h($sigMap['หัวหน้าการเงิน']['full_name'] ?? '') ?>)<br>หัวหน้างานการเงิน
    </td>
  </tr>
</table>
<div style="text-align:center;margin-top:30px">
  <p>อนุมัติ</p>
  <br><br>
  <p>ลงชื่อ..........................................</p>
  <p>(<?= h($sigMap['ผู้อำนวยการโรงเรียน']['full_name'] ?? '') ?>)</p>
  <p><?= h($sigMap['ผู้อำนวยการโรงเรียน']['position'] ?? '') ?></p>
</div>
</body></html>
<?php
    exit;
}
// If vendor exists, use PhpWord
require_once $vendorPath;
// PhpWord generation code here (same structure as HTML above)
