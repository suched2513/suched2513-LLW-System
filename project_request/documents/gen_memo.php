<?php
/**
 * documents/gen_memo.php — Generate Memo using PhpWord
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

use PhpOffice\PhpWord\TemplateProcessor;

$id = $_GET['id'] ?? '';
if (!$id) die('Missing ID');

$pdo = getPdo();

// Fetch request data
$stmt = $pdo->prepare("
    SELECT r.*, p.project_name, p.activity, u.full_name as teacher_name, u.department as teacher_dept
    FROM project_requests r
    JOIN budget_projects p ON r.budget_project_id = p.id
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$request = $stmt->fetch();

if (!$request) die('Request not found');

// Fetch items
$stmt = $pdo->prepare("SELECT * FROM request_items WHERE request_id = ? ORDER BY item_order");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Fetch Signatories
$stmt = $pdo->query("SELECT * FROM signatories WHERE is_active = 1");
$signers = $stmt->fetchAll();
$signerMap = [];
foreach ($signers as $s) {
    $signerMap[$s['role_label']] = $s;
}

try {
    // Note: Template file must exist in documents/templates/memo.docx
    $templateFile = __DIR__ . '/templates/memo.docx';
    if (!file_exists($templateFile)) {
        // Fallback or Error
        die('ไม่พบไฟล์เทมเพลต (documents/templates/memo.docx)');
    }

    $template = new TemplateProcessor($templateFile);

    // Basic Info
    $template->setValue('project_name', $request['project_name']);
    $template->setValue('activity', $request['activity']);
    $template->setValue('request_date', date('j ', strtotime($request['request_date'])) . monthThai(date('n', strtotime($request['request_date']))) . ' ' . (date('Y', strtotime($request['request_date'])) + 543));
    $template->setValue('amount', number_format($request['amount_requested'], 2));
    $template->setValue('amount_thai', bathText($request['amount_requested']));
    $template->setValue('teacher_name', $request['teacher_name']);
    $template->setValue('teacher_dept', $request['teacher_dept']);
    $template->setValue('reason', $request['reason']);

    // Signers
    $template->setValue('director_name', $signerMap['ผู้อำนวยการ']['full_name'] ?? '');
    $template->setValue('director_pos', $signerMap['ผู้อำนวยการ']['position'] ?? '');
    
    // Items Table
    $template->cloneRow('item_name', count($items));
    foreach ($items as $index => $item) {
        $row = $index + 1;
        $template->setValue('item_no#' . $row, $row);
        $template->setValue('item_name#' . $row, $item['item_name']);
        $template->setValue('item_qty#' . $row, number_format($item['quantity']));
        $template->setValue('item_unit#' . $row, $item['unit']);
        $template->setValue('item_price#' . $row, number_format($item['unit_price'], 2));
        $template->setValue('item_total#' . $row, number_format($item['total_price'], 2));
    }

    $outputFile = 'Memo_' . $id . '.docx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $outputFile . '"');
    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die('Error generating document: ' . $e->getMessage());
}

// Helpers
function monthThai($n) {
    $m = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
    return $m[$n];
}

function bathText($number) {
    $number = number_format($number, 2, '.', '');
    $numberx = explode(".", $number);
    $num = $numberx[0];
    $point = $numberx[1];
    
    $output = "";
    $t_num = ["ศูนย์", "หนึ่ง", "สอง", "สาม", "สี่", "ห้า", "หก", "เจ็ด", "แปด", "เก้า"];
    $t_unit = ["", "สิบ", "ร้อย", "พัน", "หมื่น", "แสน", "ล้าน"];
    
    if ($num == "0") {
        $output = "ศูนย์";
    } else {
        $len = strlen($num);
        for ($i = 0; $i < $len; $i++) {
            $n = substr($num, $i, 1);
            $u = $len - $i - 1;
            if ($n != "0") {
                if ($u == 1 && $n == "1") $output .= "สิบ";
                else if ($u == 1 && $n == "2") $output .= "ยี่สิบ";
                else if ($u == 0 && $n == "1" && $len > 1) $output .= "เอ็ด";
                else $output .= $t_num[$n] . $t_unit[$u % 6];
                
                if ($u > 0 && $u % 6 == 0) $output .= "ล้าน";
            }
        }
    }
    $output .= "บาท";
    if ($point == "00" || $point == "0") {
        $output .= "ถ้วน";
    } else {
        $len = strlen($point);
        for ($i = 0; $i < $len; $i++) {
            $n = substr($point, $i, 1);
            $u = $len - $i - 1;
            if ($n != "0") {
                if ($u == 1 && $n == "1") $output .= "สิบ";
                else if ($u == 1 && $n == "2") $output .= "ยี่สิบ";
                else if ($u == 0 && $n == "1" && $len > 1) $output .= "เอ็ด";
                else $output .= $t_num[$n] . ($u == 1 ? "สิบ" : "");
            }
        }
        $output .= "สตางค์";
    }
    return $output;
}
