<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
requireRole(['admin','director','budget_officer']);
$db = getDB();
$type = $_GET['type'] ?? 'budget';
$fy = (int)($_GET['fy'] ?? FISCAL_YEAR);
$filename = 'budget_report_'.$fy.'_'.date('Ymd').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo "ï»¿"; // BOM for Excel Thai
$out = fopen('php://output','w');
if ($type==='budget') {
    fputcsv($out, ['ฝ่าย','ปีงบประมาณ','งบอุดหนุน','งบคุณภาพ','งบรายได้','งบงานประจำ','รวมจัดสรร','ยอดอนุมัติ','คงเหลือ','%ใช้']);
    $rows = $db->prepare("SELECT * FROM v_budget_usage WHERE fiscal_year=? ORDER BY department_name");
    $rows->execute([$fy]);
    foreach ($rows->fetchAll() as $r) {
        fputcsv($out, [$r['department_name'],$r['fiscal_year'],$r['alloc_subsidy'],$r['alloc_quality'],$r['alloc_revenue'],$r['alloc_operation'],$r['alloc_total'],$r['used_total'],$r['alloc_total']-$r['used_total'],number_format((float)$r['usage_pct'],2).'%']);
    }
} else {
    fputcsv($out, ['ฝ่าย','ปีงบประมาณ','จัดสรรรวม','อนุมัติ','คงเหลือ']);
    $rows = $db->prepare("SELECT * FROM v_budget_usage WHERE fiscal_year=? ORDER BY department_name");
    $rows->execute([$fy]);
    foreach ($rows->fetchAll() as $r) {
        fputcsv($out, [$r['department_name'],$r['fiscal_year'],$r['alloc_total'],$r['used_total'],$r['alloc_total']-$r['used_total']]);
    }
}
fclose($out);
auditLog('download','report',null,null,['type'=>$type,'fy'=>$fy]);