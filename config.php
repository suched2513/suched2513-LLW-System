<?php
/**
 * ระบบ WFH:LLW — ตั้งค่าการเชื่อมต่อฐานข้อมูล
 * ดึงค่าทั้งหมดจากศูนย์กลาง: config/database.php
 */
require_once __DIR__ . '/config/database.php';

// --- Automated Base Path Detection ---
// This calculates the relative path from the Apache DocumentRoot to the project folder.
// Works for local (e.g., /llw or /htdocs/llw) and production (e.g., /) automatically.
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$appDir  = str_replace('\\', '/', realpath(__DIR__));
$base_path = str_replace($docRoot, '', $appDir);
$base_path = '/' . trim($base_path, '/');
if ($base_path === '/') $base_path = '';

// Global connection
$conn = getWfhConn();

