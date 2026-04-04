<?php
require_once 'attendance_system/db.php';

echo "<h2>Debug Report - ตรวจสอบข้อมูลรายงาน</h2>";

// 1. ตรวจสอบข้อมูลการเช็คชื่อทั้งหมด
echo "<h3>ข้อมูลการเช็คชื่อทั้งหมด:</h3>";
$stmt = $pdo->query("SELECT a.*, s.name as student_name, sub.subject_name, t.name as teacher_name 
                    FROM att_attendance a 
                    JOIN att_students s ON a.student_id = s.id 
                    JOIN att_subjects sub ON a.subject_id = sub.id 
                    JOIN att_teachers t ON a.teacher_id = t.id 
                    ORDER BY a.date DESC, a.period DESC 
                    LIMIT 10");
$records = $stmt->fetchAll();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>วันที่</th><th>คาบ</th><th>วิชา</th><th>ครู</th><th>นักเรียน</th><th>สถานะ</th><th>เวลา</th></tr>";
foreach ($records as $r) {
    echo "<tr>";
    echo "<td>{$r['date']}</td>";
    echo "<td>{$r['period']}</td>";
    echo "<td>{$r['subject_name']}</td>";
    echo "<td>{$r['teacher_name']}</td>";
    echo "<td>{$r['student_name']}</td>";
    echo "<td><strong>{$r['status']}</strong></td>";
    echo "<td>{$r['time_in']}</td>";
    echo "</tr>";
}
echo "</table>";

// 2. ตรวจสอบวิชาที่เลือก (ท21101)
echo "<h3>ตรวจสอบวิชา ท21101:</h3>";
$stmt = $pdo->prepare("SELECT * FROM att_subjects WHERE subject_code = ?");
$stmt->execute(['ท21101']);
$subject = $stmt->fetch();

if ($subject) {
    echo "<p>พบวิชา: {$subject['subject_name']} (ID: {$subject['id']}, ห้อง: {$subject['classroom']}, ครู ID: {$subject['teacher_id']})</p>";
    
    // 3. ตรวจสอบนักเรียนในห้อง
    echo "<h3>นักเรียนในห้อง {$subject['classroom']}:</h3>";
    $stmt = $pdo->prepare("SELECT * FROM att_students WHERE classroom = ?");
    $stmt->execute([$subject['classroom']]);
    $students = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>รหัส</th><th>ชื่อ</th><th>ห้อง</th></tr>";
    foreach ($students as $s) {
        echo "<tr>";
        echo "<td>{$s['id']}</td>";
        echo "<td>{$s['student_id']}</td>";
        echo "<td>{$s['name']}</td>";
        echo "<td>{$s['classroom']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 4. ตรวจสอบข้อมูลการเช็คชื่อของวิชานี้
    echo "<h3>ข้อมูลการเช็คชื่อของวิชานี้ (เดือน มี.ค. 2026):</h3>";
    $stmt = $pdo->prepare("SELECT a.*, s.name as student_name 
                           FROM att_attendance a 
                           JOIN att_students s ON a.student_id = s.id 
                           WHERE a.subject_id = ? AND a.date BETWEEN '2026-03-01' AND '2026-03-31'
                           ORDER BY a.date, a.period");
    $stmt->execute([$subject['id']]);
    $attendance = $stmt->fetchAll();
    
    if (count($attendance) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>วันที่</th><th>คาบ</th><th>นักเรียน</th><th>สถานะ</th><th>เวลา</th></tr>";
        foreach ($attendance as $a) {
            echo "<tr>";
            echo "<td>{$a['date']}</td>";
            echo "<td>{$a['period']}</td>";
            echo "<td>{$a['student_name']}</td>";
            echo "<td><strong>{$a['status']}</strong></td>";
            echo "<td>{$a['time_in']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ ไม่พบข้อมูลการเช็คชื่อของวิชานี้ในช่วงเวลาดังกล่าว</p>";
    }
    
    // 5. ทดสอบ query รายงานแบบเดียวกับใน report.php
    echo "<h3>ทดสอบ query รายงาน (เหมือนใน report.php):</h3>";
    $start_date = '2026-03-01';
    $end_date = '2026-03-31';
    
    $stmt = $pdo->prepare("
        SELECT 
            s.student_id, 
            s.name,
            SUM(CASE WHEN a.status = 'มา' THEN 1 ELSE 0 END) as count_come,
            SUM(CASE WHEN a.status = 'ขาด' THEN 1 ELSE 0 END) as count_absent,
            SUM(CASE WHEN a.status = 'ลา' THEN 1 ELSE 0 END) as count_leave,
            SUM(CASE WHEN a.status = 'โดด' THEN 1 ELSE 0 END) as count_skip,
            SUM(CASE WHEN a.status = 'สาย' THEN 1 ELSE 0 END) as count_late,
            COUNT(a.id) as total_attendance
        FROM att_students s
        LEFT JOIN att_attendance a ON s.id = a.student_id 
            AND a.subject_id = :subject_id 
            AND a.date BETWEEN :start_date AND :end_date
        WHERE s.classroom = :classroom
        GROUP BY s.id
        ORDER BY s.student_id ASC
    ");
    $stmt->execute([
        'subject_id' => $subject['id'],
        'start_date' => $start_date,
        'end_date' => $end_date,
        'classroom' => $subject['classroom']
    ]);
    
    $report_data = $stmt->fetchAll();
    
    if (count($report_data) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>รหัส</th><th>ชื่อ</th><th>มา</th><th>ขาด</th><th>ลา</th><th>โดด</th><th>สาย</th><th>รวม</th></tr>";
        foreach ($report_data as $row) {
            echo "<tr>";
            echo "<td>{$row['student_id']}</td>";
            echo "<td>{$row['name']}</td>";
            echo "<td>{$row['count_come']}</td>";
            echo "<td>{$row['count_absent']}</td>";
            echo "<td>{$row['count_leave']}</td>";
            echo "<td>{$row['count_skip']}</td>";
            echo "<td>{$row['count_late']}</td>";
            echo "<td>{$row['total_attendance']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ Query รายงานไม่พบข้อมูล</p>";
    }
    
} else {
    echo "<p>❌ ไม่พบวิชา ท21101</p>";
}

// 6. แสดงวิชาทั้งหมด
echo "<h3>วิชาทั้งหมดในระบบ:</h3>";
$subjects = $pdo->query("SELECT * FROM att_subjects ORDER BY subject_code");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>รหัส</th><th>ชื่อ</th><th>ห้อง</th><th>ครู ID</th></tr>";
foreach ($subjects as $s) {
    echo "<tr>";
    echo "<td>{$s['subject_code']}</td>";
    echo "<td>{$s['subject_name']}</td>";
    echo "<td>{$s['classroom']}</td>";
    echo "<td>{$s['teacher_id']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><a href='attendance_system/'>ไปทดสอบระบบ</a></p>";
?>
