<?php
/**
 * check_duplicates.php — Find duplicate student IDs or names in 2569
 */
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') { die('Unauthorized'); }

$pdo = getPdo();
$year = 2569;

echo "<h2>🔍 ตรวจสอบความซ้ำซ้อนของข้อมูลนักเรียน (ปี $year)</h2>";
echo "<hr>";

// 1. ตรวจสอบ Student ID ซ้ำ
echo "<h3>1. รายชื่อที่เลขประจำตัวซ้ำกัน (Duplicate Student IDs):</h3>";
$stmt = $pdo->prepare("
    SELECT student_id, COUNT(*) as cnt, GROUP_CONCAT(name SEPARATOR ' | ') as names, GROUP_CONCAT(classroom SEPARATOR ' | ') as rooms
    FROM att_students
    WHERE academic_year = ?
    GROUP BY student_id
    HAVING cnt > 1
");
$stmt->execute([$year]);
$dupes = $stmt->fetchAll();

if (empty($dupes)) {
    echo "✓ ไม่พบเลขประจำตัวซ้ำกัน<br>";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse; font-size:12px;'>";
    echo "<tr><th>เลขประจำตัว</th><th>จำนวนที่ซ้ำ</th><th>รายชื่อที่พบ</th><th>ห้องเรียน</th></tr>";
    foreach ($dupes as $d) {
        echo "<tr><td>{$d['student_id']}</td><td>{$d['cnt']}</td><td>{$d['names']}</td><td>{$d['rooms']}</td></tr>";
    }
    echo "</table>";
}

// 2. ตรวจสอบชื่อซ้ำ (แต่คนละ ID)
echo "<h3>2. รายชื่อที่ชื่อ-นามสกุลซ้ำกัน (Duplicate Names):</h3>";
$stmt = $pdo->prepare("
    SELECT name, COUNT(*) as cnt, GROUP_CONCAT(student_id SEPARATOR ' | ') as ids, GROUP_CONCAT(classroom SEPARATOR ' | ') as rooms
    FROM att_students
    WHERE academic_year = ?
    GROUP BY name
    HAVING cnt > 1
");
$stmt->execute([$year]);
$dupesName = $stmt->fetchAll();

if (empty($dupesName)) {
    echo "✓ ไม่พบชื่อซ้ำกัน<br>";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse; font-size:12px;'>";
    echo "<tr><th>ชื่อ-นามสกุล</th><th>จำนวนที่ซ้ำ</th><th>เลขประจำตัวที่พบ</th><th>ห้องเรียน</th></tr>";
    foreach ($dupesName as $d) {
        echo "<tr><td>{$d['name']}</td><td>{$d['cnt']}</td><td>{$d['ids']}</td><td>{$d['rooms']}</td></tr>";
    }
    echo "</table>";
}

echo "<br><hr>";
echo "<p>ยอดรวมนักเรียนปี $year ในระบบตอนนี้คือ: <b>" . $pdo->query("SELECT COUNT(*) FROM att_students WHERE academic_year = $year")->fetchColumn() . "</b> คน</p>";
echo "<p>หากต้องการลบทั้งหมดเพื่อเริ่มใหม่รันสคริปต์ <a href='cleanup_data.php'>Cleanup Data</a> ได้เลยครับ</p>";
