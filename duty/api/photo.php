<?php
/**
 * duty/api/photo.php — Proxy ส่งไฟล์รูปผ่าน session guard
 * ป้องกันคนนอกเข้าถึงรูปโดยเดา URL
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    http_response_code(403);
    exit('Forbidden');
}

$relPath = $_GET['path'] ?? '';

// ── Sanitize: ป้องกัน path traversal ──
// ลบ .. และ null bytes
$relPath = str_replace(["\0", '..'], '', $relPath);
$relPath = ltrim(str_replace('\\', '/', $relPath), '/');

// ตรวจให้แน่ใจว่าอยู่ใน uploads/reports/
if (!str_starts_with($relPath, 'uploads/reports/')) {
    http_response_code(403);
    exit('Forbidden');
}

$fullPath = realpath(__DIR__ . '/../../' . $relPath);
$uploadBase = realpath(__DIR__ . '/../../uploads/reports');

// ตรวจว่า path จริงอยู่ใน uploadBase
if (!$fullPath || !$uploadBase || !str_starts_with($fullPath, $uploadBase)) {
    http_response_code(404);
    exit('Not found');
}

if (!file_exists($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

// ── ตรวจ MIME type ──
$mime = mime_content_type($fullPath);
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed)) {
    http_response_code(403);
    exit('Forbidden file type');
}

// ── ส่งไฟล์ ──
$size = filesize($fullPath);
header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
