<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
requireRole(['admin','director','budget_officer']);
$db = getDB();
$type = $_GET['type'] ?? 'budget';
$fy = (int)($_GET['fy'] ?? FISCAL_YEAR);
$rows = $db->prepare("SELECT * FROM v_budget_usage WHERE fiscal_year=? ORDER BY department_name");
$rows->execute([$fy]); $data = $rows->fetchAll();
$totalAlloc = array_sum(array_column($data,'alloc_total'));
$totalUsed = array_sum(array_column($data,'used_total'));
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8"><title>รายงานงบประมาณ <?=$fy?></title>
<style>
body{font-family:'TH Sarabun New',Sarabun,sans-serif;font-size:14pt;margin:1cm 2cm}
h2{text-align:center;font-size:18pt}
table{width:100%;border-collapse:collapse;margin:12px 0}
th,td{border:1px solid #000;padding:5px 8px}
th{background:#eee;text-align:center}
.text-right{text-align:right}
.text-center{text-align:center}
tfoot tr{font-weight:bold;background:#f5f5f5}
@media print{.no-print{display:none}}
</style>
</head><body>
<div class="no-print" style="margin-bottom:16px"><button onclick="window.print()" style="padding:8px 20px;background:#1a56db;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px">🖨️ พิมพ์</button></div>
<h2>รายงานภาพรวมงบประมาณ<br><?=h(SCHOOL_NAME)?></h2>
<p class="text-center">ปีงบประมาณ <?=$fy?> | <?=h(SCHOOL_DISTRICT)?> <?=h(SCHOOL_PROVINCE)?></p>
<table>
  <thead><tr><th>ฝ่าย</th><th>งบจัดสรร (บาท)</th><th>อุดหนุน</th><th>คุณภาพ</th><th>รายได้</th><th>ใช้ไปแล้ว</th><th>คงเหลือ</th><th>%</th></tr></thead>
  <tbody>
  <?php foreach ($data as $r): $remain = $r['alloc_total']-$r['used_total']; ?>
  <tr>
    <td><?=h($r['department_name'])?></td>
    <td class="text-right"><?=number_format((float)$r['alloc_total'],2)?></td>
    <td class="text-right"><?=number_format((float)$r['alloc_subsidy'],2)?></td>
    <td class="text-right"><?=number_format((float)$r['alloc_quality'],2)?></td>
    <td class="text-right"><?=number_format((float)$r['alloc_revenue'],2)?></td>
    <td class="text-right"><?=number_format((float)$r['used_total'],2)?></td>
    <td class="text-right"><?=number_format((float)$remain,2)?></td>
    <td class="text-center"><?=number_format((float)$r['usage_pct'],1)?>%</td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><td>รวม</td><td class="text-right"><?=number_format($totalAlloc,2)?></td><td colspan="3"></td><td class="text-right"><?=number_format($totalUsed,2)?></td><td class="text-right"><?=number_format($totalAlloc-$totalUsed,2)?></td><td></td></tr></tfoot>
</table>
<p style="font-size:12pt;color:#555">พิมพ์วันที่ <?=date('d/m/').(date('Y')+543)?> เวลา <?=date('H:i:s')?></p>
</body></html>