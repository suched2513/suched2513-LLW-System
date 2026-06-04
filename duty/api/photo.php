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

// อนุญาตเฉพาะ 2 path ที่กำหนด
$allowedPrefixes = ['uploads/reports/', 'uploads/teacher_photos/'];
$inAllowed = false;
foreach ($allowedPrefixes as $prefix) {
    if (strpos($relPath, $prefix) === 0) { $inAllowed = true; break; }
}
if (!$inAllowed) {
    http_response_code(403);
    exit('Forbidden');
}

$fullPath = realpath(__DIR__ . '/../../' . $relPath);
$uploadBase = realpath(__DIR__ . '/../../uploads');

// ตรวจว่า path จริงอยู่ใน uploadBase
if (!$fullPath || !$uploadBase || strpos($fullPath, $uploadBase) !== 0) {
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
