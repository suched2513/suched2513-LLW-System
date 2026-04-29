<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
requireRole(['admin','super_admin','director','budget_officer','wfh_admin','procurement_head','finance_head','deputy_director']);
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
    try {
        $stmt = $db->prepare("
            SELECT d.name AS department_name, ? AS fiscal_year,
                COALESCE(SUM(bp.budget_subsidy),0) AS alloc_subsidy,
                COALESCE(SUM(bp.budget_quality),0) AS alloc_quality,
                COALESCE(SUM(bp.budget_revenue),0) AS alloc_revenue,
                COALESCE(SUM(bp.budget_operation),0) AS alloc_operation,
                COALESCE(SUM(bp.budget_subsidy + bp.budget_quality + bp.budget_revenue + bp.budget_operation + bp.budget_reserve), 0) AS alloc_total,
                COALESCE(SUM(pr_approved.amount_requested), 0) AS used_total
            FROM departments d
            LEFT JOIN budget_projects bp ON bp.department_id = d.id AND bp.fiscal_year = ?
            LEFT JOIN (
                SELECT budget_project_id, SUM(amount_requested) AS amount_requested
                FROM project_requests WHERE status = 'approved' GROUP BY budget_project_id
            ) pr_approved ON pr_approved.budget_project_id = bp.id
            GROUP BY d.id, d.name
            ORDER BY d.order_no, d.name
        ");
        $stmt->execute([$fy, $fy]);
        foreach ($stmt->fetchAll() as $r) {
            $pct = $r['alloc_total'] > 0 ? ($r['used_total'] / $r['alloc_total'] * 100) : 0;
            fputcsv($out, [$r['department_name'],$r['fiscal_year'],$r['alloc_subsidy'],$r['alloc_quality'],$r['alloc_revenue'],$r['alloc_operation'],$r['alloc_total'],$r['used_total'],$r['alloc_total']-$r['used_total'],number_format($pct,2).'%']);
        }
    } catch (Exception $e) { error_log($e->getMessage()); }
} else {
    fputcsv($out, ['ฝ่าย','ปีงบประมาณ','จัดสรรรวม','อนุมัติ','คงเหลือ']);
    try {
        $stmt = $db->prepare("
            SELECT d.name AS department_name, ? AS fiscal_year,
                COALESCE(SUM(bp.budget_subsidy + bp.budget_quality + bp.budget_revenue + bp.budget_operation + bp.budget_reserve), 0) AS alloc_total,
                COALESCE(SUM(pr_approved.amount_requested), 0) AS used_total
            FROM departments d
            LEFT JOIN budget_projects bp ON bp.department_id = d.id AND bp.fiscal_year = ?
            LEFT JOIN (
                SELECT budget_project_id, SUM(amount_requested) AS amount_requested
                FROM project_requests WHERE status = 'approved' GROUP BY budget_project_id
            ) pr_approved ON pr_approved.budget_project_id = bp.id
            GROUP BY d.id, d.name
            ORDER BY d.order_no, d.name
        ");
        $stmt->execute([$fy, $fy]);
        foreach ($stmt->fetchAll() as $r) {
            fputcsv($out, [$r['department_name'],$r['fiscal_year'],$r['alloc_total'],$r['used_total'],$r['alloc_total']-$r['used_total']]);
        }
    } catch (Exception $e) { error_log($e->getMessage()); }
}
fclose($out);
auditLog('download','report',null,null,['type'=>$type,'fy'=>$fy]);