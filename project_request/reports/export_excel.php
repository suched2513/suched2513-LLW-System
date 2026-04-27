<?php
/**
 * reports/export_excel.php — Universal Excel Export using PhpSpreadsheet
 */
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$type = $_GET['type'] ?? '';
$fy = $_GET['fiscal_year'] ?? FISCAL_YEAR;
$pdo = getPdo();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

try {
    switch ($type) {
        case 'budget_overview':
            $filename = "budget_overview_{$fy}.xlsx";
            $sheet->setTitle('Budget Overview');
            
            // Header Info
            $sheet->setCellValue('A1', SCHOOL_NAME);
            $sheet->setCellValue('A2', 'รายงานภาพรวมการใช้งบประมาณ ปีงบประมาณ ' . $fy);
            $sheet->setCellValue('A3', 'วันที่ส่งออก: ' . date('d/m/Y H:i'));
            $sheet->mergeCells('A1:F1');
            $sheet->mergeCells('A2:F2');
            
            // Table Header
            $headers = ['ฝ่าย/กลุ่มงาน', 'งบจัดสรร', 'ใช้ไป', 'คงเหลือ', 'ร้อยละ (%)', 'สถานะ'];
            $sheet->fromArray($headers, NULL, 'A5');
            
            // Data
            $stmt = $pdo->prepare("SELECT * FROM v_budget_usage WHERE fiscal_year = ? ORDER BY department_name ASC");
            $stmt->execute([$fy]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $rowNum = 6;
            foreach ($data as $d) {
                $rem = $d['alloc_total'] - $d['used_total'];
                $p = ($d['alloc_total'] > 0) ? ($d['used_total'] / $d['alloc_total']) * 100 : 0;
                $status = ($p > 90) ? 'เกือบหมด' : (($p > 70) ? 'ระวัง' : 'ปกติ');
                
                $sheet->setCellValue('A' . $rowNum, $d['department_name']);
                $sheet->setCellValue('B' . $rowNum, $d['alloc_total']);
                $sheet->setCellValue('C' . $rowNum, $d['used_total']);
                $sheet->setCellValue('D' . $rowNum, $rem);
                $sheet->setCellValue('E' . $rowNum, $p / 100);
                $sheet->setCellValue('F' . $rowNum, $status);
                $rowNum++;
            }
            
            // Formatting
            $sheet->getStyle('A5:F5')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
            $sheet->getStyle('A5:F5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
            $sheet->getStyle('B6:D' . ($rowNum-1))->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('E6:E' . ($rowNum-1))->getNumberFormat()->setFormatCode('0.0%');
            break;

        case 'annual_summary':
            $filename = "annual_summary_{$fy}.xlsx";
            $sheet->setTitle('Annual Summary');
            // Similar logic for Annual Summary...
            $sheet->setCellValue('A1', 'สรุปปีงบประมาณ ' . $fy);
            break;

        default:
            die('Invalid report type');
    }

    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die('Error exporting Excel: ' . $e->getMessage());
}
