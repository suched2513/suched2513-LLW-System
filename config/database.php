<?php
/**
 * =====================================================================
 * ศูนย์กลางการตั้งค่าฐานข้อมูล — LLW System
 * =====================================================================
 * ฐานข้อมูลเดียว: llw_db
 *   wfh_*  → ตารางระบบลงเวลาปฏิบัติงาน (WFH:LLW)
 *   cb_*   → ตารางระบบบริหารจัดการ Chromebook
 *   att_*  → ตารางระบบเช็คชื่อนักเรียน
 * =====================================================================
 */

// ─── ข้อมูลเซิร์ฟเวอร์ MySQL ───────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // ← แก้ตรงนี้ถ้าเปลี่ยน password
define('DB_NAME', 'llw_db');   // ← ฐานข้อมูลรวมเพียง 1 ฐาน

// ─── Charset: บังคับ UTF-8 ทุกฟังก์ชัน PHP (ป้องกัน htmlspecialchars ใช้ ISO-8859-1) ──
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// ─── Timezone ──────────────────────────────────────────────────────
date_default_timezone_set('Asia/Bangkok');

// ─── Factory: MySQLi (ใช้ในระบบ WFH) ──────────────────────────────
function getWfhConn(): mysqli
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log('[LLW] DB connect error: ' . $conn->connect_error);
        die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ─── Factory: PDO (ใช้ใน Chromebook และ Attendance) ────────────────
function getPdo(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log('[LLW] PDO connect error: ' . $e->getMessage());
        die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ');
    }
}
