<?php
/**
 * ระบบ WFH:LLW — ตั้งค่าการเชื่อมต่อฐานข้อมูล
 * ดึงค่าทั้งหมดจากศูนย์กลาง: config/database.php
 */
require_once __DIR__ . '/config/database.php';

$conn = getWfhConn();

