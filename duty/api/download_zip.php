<?php
/**
 * duty/api/download_zip.php — ดาวน์โหลดรูปทั้งหมดของ report เป็น ZIP
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(403); exit('Forbidden');
}

$reportId = (int)($_GET['report_id'] ?? 0);
if (!$reportId) { http_response_code(400); exit('Bad request'); }

$pdo = getPdo();

// ดึงข้อมูล report + รูป
$stmtR = $pdo->prepare("SELECT * FROM duty_reports WHERE id = ?");
$stmtR->execute([$reportId]);
$report = $stmtR->fetch(PDO::FETCH_ASSOC);
if (!$report) { http_response_code(404); exit('Not found'); }

$stmtP = $pdo->prepare(
    "SELECT file_path FROM duty_report_photos WHERE report_id=? AND is_deleted=0 ORDER BY received_at ASC"
);
$stmtP->execute([$reportId]);
$photos = $stmtP->fetchAll(PDO::FETCH_COLUMN);

if (empty($photos)) { http_response_code(404); exit('No photos'); }

if (!class_exists('ZipArchive')) {
    http_response_code(500); exit('ZipArchive not available');
}

$baseDir   = realpath(__DIR__ . '/../../');
$uploadBase = realpath($baseDir . '/uploads/reports');
$tmpFile   = tempnam(sys_get_temp_dir(), 'duty_zip_');

$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

foreach ($photos as $i => $relPath) {
    $fullPath = realpath($baseDir . '/' . $relPath);
    if (!$fullPath || !str_starts_with($fullPath, $uploadBase)) continue;
    if (!file_exists($fullPath)) continue;
    $zip->addFile($fullPath, ($i + 1) . '_' . basename($fullPath));
}

$zip->close();

$filename = 'report_' . $report['duty_date'] . '_' . $report['shift'] . '_point' . $report['point_no'] . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache');
readfile($tmpFile);
@unlink($tmpFile);
exit;
