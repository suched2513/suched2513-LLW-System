<?php
/**
 * reports/export_pdf.php — Universal PDF Export using TCPDF
 */
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

$type = $_GET['type'] ?? '';
$fy = $_GET['fiscal_year'] ?? FISCAL_YEAR;
$pdo = getPdo();

class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('thsarabunnew', 'B', 18);
        $this->Cell(0, 10, SCHOOL_NAME, 0, 1, 'C');
        $this->SetFont('thsarabunnew', '', 14);
        $this->Cell(0, 10, 'รายงานระบบบริหารจัดการงบประมาณ', 0, 1, 'C');
        $this->Line(10, 28, 200, 28);
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('thsarabunnew', 'I', 10);
        $this->Cell(0, 10, 'หน้า '.$this->getAliasNumPage().'/'.$this->getAliasNbPages() . ' | พิมพ์เมื่อ ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('SBMS');
$pdf->SetAuthor('Lalom Wittaya');
$pdf->SetTitle('Report Export');

// Set Thai font (Ensure thsarabunnew exists in TCPDF fonts)
$pdf->SetFont('thsarabunnew', '', 14);
$pdf->AddPage();

try {
    switch ($type) {
        case 'budget_overview':
            $html = '<h2 style="text-align:center;">ภาพรวมการใช้งบประมาณ ปี ' . $fy . '</h2>';
            $html .= '<table border="1" cellpadding="5">
                <tr style="background-color:#2563eb; color:#ffffff; font-weight:bold;">
                    <th width="40%">ฝ่าย/กลุ่มงาน</th>
                    <th width="20%">งบจัดสรร</th>
                    <th width="20%">ใช้ไป</th>
                    <th width="20%">คงเหลือ</th>
                </tr>';
            
            $stmt = $pdo->prepare("SELECT * FROM v_budget_usage WHERE fiscal_year = ? ORDER BY department_name ASC");
            $stmt->execute([$fy]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($data as $d) {
                $rem = $d['alloc_total'] - $d['used_total'];
                $html .= '<tr>
                    <td>' . htmlspecialchars($d['department_name']) . '</td>
                    <td align="right">' . number_format($d['alloc_total'], 2) . '</td>
                    <td align="right">' . number_format($d['used_total'], 2) . '</td>
                    <td align="right">' . number_format($rem, 2) . '</td>
                </tr>';
            }
            $html .= '</table>';
            break;

        default:
            $html = '<h1>Invalid Report Type</h1>';
    }

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('report_' . $type . '.pdf', 'I');
    exit;

} catch (Exception $e) {
    die('Error exporting PDF: ' . $e->getMessage());
}
