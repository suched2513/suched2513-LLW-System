<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$req = $db->prepare("SELECT pr.*,bp.project_name,bp.activity,d.name AS dept_name,CONCAT(u.firstname,' ',u.lastname) AS teacher_name FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id JOIN departments d ON bp.department_id=d.id JOIN llw_users u ON pr.user_id=u.user_id WHERE pr.id=?");
$req->execute([$id]); $r = $req->fetch();
if (!$r) die('ไม่พบข้อมูล');
$committee = $db->prepare("SELECT * FROM request_committee WHERE request_id=? ORDER BY member_order");
$committee->execute([$id]); $members = $committee->fetchAll();
$sigs = $db->query("SELECT * FROM signatories WHERE is_active=1 ORDER BY order_no")->fetchAll();
$sigMap = []; foreach ($sigs as $s) $sigMap[$s['role_label']] = $s;
$typeLabel = $r['proc_type']==='hire' ? 'จัดจ้าง' : 'จัดซื้อ';
auditLog('download','project_request',$id);
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8"><title>บันทึกขอแต่งตั้งคณะกรรมการ</title>
<style>body{font-family:'TH Sarabun New',Sarabun,sans-serif;font-size:16pt;margin:2cm 2.5cm}h2{text-align:center;font-size:18pt}table{width:100%;border-collapse:collapse}.text-center{text-align:center}@media print{.no-print{display:none}}</style>
</head><body>
<div class="no-print" style="margin-bottom:20px"><button onclick="window.print()" style="padding:8px 20px;background:#1a56db;color:#fff;border:none;border-radius:6px;cursor:pointer">🖨️ พิมพ์</button> <a href="javascript:history.back()" style="margin-left:10px;color:#64748b">← กลับ</a></div>
<h2>บันทึกข้อความ</h2>
<table style="margin-bottom:20px">
  <tr><td width="200"><strong>ส่วนราชการ</strong></td><td><?=h(SCHOOL_NAME.' '.SCHOOL_DISTRICT.' '.SCHOOL_PROVINCE)?></td></tr>
  <tr><td><strong>ที่</strong></td><td><?=h($r['project_no']??'')?></td><td width="120"><strong>วันที่</strong></td><td><?=formatDate($r['request_date'])?></td></tr>
  <tr><td><strong>เรื่อง</strong></td><td colspan="3">ขอแต่งตั้งคณะกรรมการกำหนดราคากลาง<?=$typeLabel?></td></tr>
  <tr><td><strong>เรียน</strong></td><td colspan="3">ผู้อำนวยการ<?=h(SCHOOL_NAME)?></td></tr>
</table>
<p style="text-indent:2cm">ด้วย<?=h(SCHOOL_NAME)?> มีความประสงค์จะดำเนินการ<?=$typeLabel?> ตามโครงการ <strong><?=h($r['project_name'])?></strong> กิจกรรม <?=h($r['activity']??'')?> งบประมาณ <strong><?=formatMoney($r['amount_requested'])?> บาท</strong> (<?=numberToThai($r['amount_requested'])?>)</p>
<p style="text-indent:2cm">เพื่อให้การดำเนินการ<?=$typeLabel?>เป็นไปด้วยความเรียบร้อย จึงขอแต่งตั้งคณะกรรมการกำหนดราคากลาง<?=$typeLabel?> ดังนี้</p>
<table style="margin:20px 0;border-collapse:collapse;width:100%">
  <thead><tr style="background:#eee"><th style="border:1px solid #000;padding:6px 10px;width:40px">ที่</th><th style="border:1px solid #000;padding:6px 10px">ชื่อ-สกุล</th><th style="border:1px solid #000;padding:6px 10px;width:150px">ตำแหน่ง</th><th style="border:1px solid #000;padding:6px 10px;width:220px">หน้าที่</th></tr></thead>
  <tbody>
  <?php foreach ($members as $i => $m): ?>
  <tr><td style="border:1px solid #000;padding:6px 10px;text-align:center"><?=$i+1?></td><td style="border:1px solid #000;padding:6px 10px"><?=h($m['member_name'])?></td><td style="border:1px solid #000;padding:6px 10px"><?=h($m['position'])?></td><td style="border:1px solid #000;padding:6px 10px"><?=h($m['role'])?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p style="text-indent:2cm">จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ</p>
<table style="margin-top:30px">
  <tr>
    <td width="50%" class="text-center">ลงชื่อ......................................ผู้เสนอ<br>(<?=h($r['teacher_name'])?>)</td>
    <td width="50%" class="text-center">ลงชื่อ......................................หัวหน้าเจ้าหน้าที่<br>(<?=h($sigMap['ผู้รับผิดชอบโครงการ']['full_name']??'')?><br><?=h($sigMap['ผู้รับผิดชอบโครงการ']['position']??'')?></td>
  </tr>
</table>
<div class="text-center" style="margin-top:40px">
  <p>อนุมัติ / ไม่อนุมัติ เหตุผล...............................................<br><br>
  ลงชื่อ..........................................<br>(<?=h($sigMap['ผู้อำนวยการโรงเรียน']['full_name']??'')?><br><?=h($sigMap['ผู้อำนวยการโรงเรียน']['position']??'')?></p>
</div>
</body></html>
