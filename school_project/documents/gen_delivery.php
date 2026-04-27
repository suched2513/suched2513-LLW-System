<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$req = $db->prepare("SELECT pr.*,bp.project_name,bp.activity,u.full_name AS teacher_name FROM project_requests pr JOIN budget_projects bp ON pr.budget_project_id=bp.id JOIN users u ON pr.user_id=u.id WHERE pr.id=?");
$req->execute([$id]); $r = $req->fetch();
if (!$r) die('ไม่พบข้อมูล');
$sigs = $db->query("SELECT * FROM signatories WHERE is_active=1 ORDER BY order_no")->fetchAll();
$sigMap = []; foreach ($sigs as $s) $sigMap[$s['role_label']] = $s;
auditLog('download','project_request',$id);
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8"><title>ใบส่งมอบงาน</title>
<style>body{font-family:'TH Sarabun New',Sarabun,sans-serif;font-size:16pt;margin:2cm 2.5cm}h2{text-align:center;font-size:18pt}@media print{.no-print{display:none}}</style>
</head><body>
<div class="no-print" style="margin-bottom:20px"><button onclick="window.print()" style="padding:8px 20px;background:#1a56db;color:#fff;border:none;border-radius:6px;cursor:pointer">🖨️ พิมพ์</button> <a href="javascript:history.back()" style="margin-left:10px">← กลับ</a></div>
<p style="text-align:right"><?=h(SCHOOL_NAME)?><br><?=h(SCHOOL_DISTRICT)?> <?=h(SCHOOL_PROVINCE)?></p>
<h2>ใบส่งมอบงาน</h2>
<p>วันที่............เดือน..................................พ.ศ. ..................</p>
<table style="margin-bottom:16px;width:100%"><tr><td><strong>เรื่อง</strong></td><td>ส่งมอบงาน<?=$r['proc_type']==='hire'?'จ้าง':'ซื้อ'?></td></tr><tr><td><strong>เรียน</strong></td><td>ผู้อำนวยการ<?=h(SCHOOL_NAME)?></td></tr></table>
<p style="text-indent:2cm">ตามที่<?=h(SCHOOL_NAME)?> ได้ตกลงให้ ข้าพเจ้า <strong><?=h($r['teacher_name'])?></strong> ในนามผู้รับจ้าง/ผู้ขาย จัดทำ <strong><?=h($r['activity']??$r['project_name'])?></strong></p>
<p style="text-indent:2cm">ตาม ( ) ใบสั่งจ้าง ( ) บันทึกตกลงจ้าง เลขที่........../.......... ลงวันที่..................................</p>
<p style="text-indent:2cm">บัดนี้ ข้าพเจ้าได้ปฏิบัติงานตาม ( ✓ ) ใบสั่งจ้าง ( ) บันทึกตกลงจ้าง ดังกล่าว เสร็จเรียบร้อยแล้ว จึงขอส่งมอบงานจ้างเพื่อตรวจรับและขอเบิกจ่ายเงินจำนวน <strong><?=formatMoney($r['amount_requested'])?></strong> บาท</p>
<p style="text-indent:2cm">(<strong><?=numberToThai($r['amount_requested'])?></strong>)</p>
<p style="text-indent:2cm">จึงเรียนมาเพื่อโปรดทราบและพิจารณา</p>
<div style="text-align:right;margin-top:30px"><p>ขอแสดงความนับถือ<br><br><br>(<strong><?=h($r['teacher_name'])?></strong>)<br>ผู้รับจ้าง/ผู้ขาย</div>
<hr style="margin:30px 0">
<p><strong>การตรวจรับ</strong></p>
<p>ข้าพเจ้า <?=h($r['inspector_name']??'')?> ตำแหน่ง <?=h($r['inspector_position']??'')?> ได้ตรวจรับงานดังกล่าวแล้ว</p>
<p>□ ถูกต้องครบถ้วน &nbsp;&nbsp; □ ไม่ถูกต้อง เนื่องจาก...................................................</p>
<div style="text-align:center;margin-top:30px"><p>ลงชื่อ......................................ผู้ตรวจรับ<br>(<?=h($r['inspector_name']??'')?>)<br>วันที่...............................................<br><br>ลงชื่อ......................................ผู้อำนวยการ<br>(<?=h($sigMap['ผู้อำนวยการโรงเรียน']['full_name']??'')?><br><?=h($sigMap['ผู้อำนวยการโรงเรียน']['position']??'')?></p></div>
</body></html>
