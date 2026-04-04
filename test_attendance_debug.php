<?php
require_once 'attendance_system/db.php';

// ทดสอบดึงข้อมูลการเช็คชื่อล่าสุด
echo "<h2>ตรวจสอบข้อมูลการเช็คชื่อล่าสุด</h2>";

// 1. ดูข้อมูลทั้งหมดใน att_attendance
$stmt = $pdo->query("SELECT * FROM att_attendance ORDER BY created_at DESC LIMIT 10");
$records = $stmt->fetchAll();

echo "<h3>10 รายการล่าสุดใน att_attendance:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Date</th><th>Period</th><th>Subject</th><th>Teacher</th><th>Student</th><th>Status</th><th>Time In</th><th>Created</th></tr>";

foreach ($records as $r) {
    echo "<tr>";
    echo "<td>{$r['id']}</td>";
    echo "<td>{$r['date']}</td>";
    echo "<td>{$r['period']}</td>";
    echo "<td>{$r['subject_id']}</td>";
    echo "<td>{$r['teacher_id']}</td>";
    echo "<td>{$r['student_id']}</td>";
    echo "<td><strong>{$r['status']}</strong></td>";
    echo "<td>{$r['time_in']}</td>";
    echo "<td>{$r['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// 2. ทดสอบ query สรุปสถิติเหมือนใน dashboard
echo "<h3>ทดสอบ query สรุปสถิติ (teacher_id = 1):</h3>";

$teacher_id = 1;
$start_date = date('Y-m-01');
$end_date = date('Y-m-d');

$statsStmt = $pdo->prepare("
    SELECT status, COUNT(*) as total 
    FROM att_attendance 
    WHERE teacher_id = :teacher_id AND (date BETWEEN :start_date AND :end_date)
    GROUP BY status
");
$statsStmt->execute([
    'teacher_id' => $teacher_id,
    'start_date' => $start_date,
    'end_date' => $end_date
]);
$statsData = $statsStmt->fetchAll();

echo "<p>ช่วงวันที่: $start_date ถึง $end_date</p>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Status</th><th>Count</th></tr>";

$summary = ['มา' => 0, 'ขาด' => 0, 'ลา' => 0, 'โดด' => 0, 'สาย' => 0];
foreach($statsData as $row) {
    echo "<tr><td>{$row['status']}</td><td>{$row['total']}</td></tr>";
    if(isset($summary[$row['status']])) {
        $summary[$row['status']] = $row['total'];
    }
}
echo "</table>";

echo "<h3>สรุป:</h3>";
foreach($summary as $status => $count) {
    echo "$status: $count<br>";
}

// 3. ตรวจสอบข้อมูลครูและวิชา
echo "<h3>ข้อมูลครูและวิชา:</h3>";
$teachers = $pdo->query("SELECT * FROM att_teachers")->fetchAll();
foreach ($teachers as $t) {
    echo "ครู: {$t['name']} (ID: {$t['id']})<br>";
}

$subjects = $pdo->query("SELECT * FROM att_subjects")->fetchAll();
foreach ($subjects as $s) {
    echo "วิชา: {$s['subject_code']} - {$s['subject_name']} (ID: {$s['id']}, Teacher: {$s['teacher_id']})<br>";
}

// 4. ตรวจสอบ session ปัจจุบัน
session_start();
echo "<h3>Session ปัจจุบัน:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>
