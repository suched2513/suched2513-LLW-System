<?php
require_once 'attendance_system/db.php';

echo "<h2>ทดสอบการแก้ไขปัญหาสถิติ</h2>";

// 1. ตรวจสอบข้อมูลการเช็คชื่อที่มีอยู่
$stmt = $pdo->query("SELECT COUNT(*) as total FROM att_attendance");
$total = $stmt->fetchColumn();
echo "<p>จำนวนข้อมูลการเช็คชื่อทั้งหมด: $total รายการ</p>";

// 2. ตรวจสอบสถิติตามครู
echo "<h3>สถิติตามครู:</h3>";
$teachers = $pdo->query("SELECT id, name FROM att_teachers") or die("Query failed");
foreach ($teachers as $t) {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM att_attendance WHERE teacher_id = ? GROUP BY status");
    $stmt->execute([$t['id']]);
    $stats = $stmt->fetchAll();
    
    echo "<p><strong>{$t['name']} (ID: {$t['id']}):</strong> ";
    foreach ($stats as $s) {
        echo "{$s['status']}: {$s['cnt']} ";
    }
    echo "</p>";
}

// 3. ตรวจสอบข้อมูลที่มี teacher_id = 0
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM att_attendance WHERE teacher_id = 0");
$stmt->execute();
$zero_count = $stmt->fetchColumn();
echo "<p>ข้อมูลที่มี teacher_id = 0: $zero_count รายการ</p>";

// 4. ทดสอบ query สรุปสถิติแบบใหม่ (รองรับ teacher_id = 0)
echo "<h3>ทดสอบ query ใหม่ (teacher_id = 0):</h3>";

$teacher_id = 0;
$start_date = date('Y-m-01');
$end_date = date('Y-m-d');

$whereClause = (int)$teacher_id === 0 ? "WHERE 1=1" : "WHERE teacher_id = :teacher_id";
$params = (int)$teacher_id === 0 ? [] : ['teacher_id' => $teacher_id];

$statsQuery = "
    SELECT status, COUNT(*) as total 
    FROM att_attendance 
    $whereClause
    AND (date BETWEEN :start_date AND :end_date)
    GROUP BY status
";

if ((int)$teacher_id === 0) {
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
} else {
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute([
        'teacher_id' => $teacher_id,
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
}
$statsData = $statsStmt->fetchAll();

echo "<p>ผลลัพธ์ query ใหม่:</p>";
foreach($statsData as $row) {
    echo "- {$row['status']}: {$row['total']}<br>";
}

// 5. สร้างข้อมูลทดสอบใหม่
echo "<h3>สร้างข้อมูลทดสอบใหม่:</h3>";

// ตรวจสอบว่ามีข้อมูลวันนี้หรือไม่
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM att_attendance WHERE date = ?");
$stmt->execute([$today]);
$today_count = $stmt->fetchColumn();

if ($today_count == 0) {
    echo "<p>กำลังสร้างข้อมูลทดสอบใหม่...</p>";
    
    // สร้างข้อมูลทดสอบ
    $test_data = [
        ['student_id' => 1, 'status' => 'มา', 'time_in' => '08:30'],
        ['student_id' => 2, 'status' => 'มา', 'time_in' => '08:35'],
        ['student_id' => 3, 'status' => 'สาย', 'time_in' => '08:50']
    ];
    
    foreach ($test_data as $data) {
        $stmt = $pdo->prepare("INSERT INTO att_attendance (date, period, subject_id, teacher_id, student_id, status, time_in) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $today,
            1,
            1, // MATH101
            1, // teacher1
            $data['student_id'],
            $data['status'],
            $data['time_in']
        ]);
    }
    echo "<p>✅ สร้างข้อมูลทดสอบ 3 รายการสำเร็จ</p>";
} else {
    echo "<p>มีข้อมูลวันนี้แล้ว $today_count รายการ</p>";
}

echo "<p><a href='attendance_system/'>ไปทดสอบระบบ</a></p>";
?>
