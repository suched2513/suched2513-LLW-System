<?php
/**
 * setup.php — ตรวจสอบและสร้างฐานข้อมูล Production
 * ⚠️ ลบไฟล์นี้หลังจาก setup เสร็จ!
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 LLW System — Production Setup</h2>";
echo "<hr>";

// ─── 1. ทดสอบ DB Connection ────────────────────────────────
echo "<h3>1. ทดสอบเชื่อมต่อ Database</h3>";

require_once __DIR__ . '/config/database.php';

try {
    $conn = getWfhConn();
    echo "✅ MySQLi: เชื่อมต่อ <b>" . DB_NAME . "</b> สำเร็จ<br>";
} catch (Exception $e) {
    die("❌ MySQLi ล้มเหลว: " . $e->getMessage());
}

try {
    $pdo = getPdo();
    echo "✅ PDO: เชื่อมต่อ <b>" . DB_NAME . "</b> สำเร็จ<br>";
} catch (Exception $e) {
    die("❌ PDO ล้มเหลว: " . $e->getMessage());
}

// ─── 2. สร้างตารางทั้งหมด ──────────────────────────────────
echo "<h3>2. สร้าง/ตรวจสอบตาราง</h3>";

$tables_sql = [

    'llw_users' => "CREATE TABLE IF NOT EXISTS llw_users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        firstname VARCHAR(100) NOT NULL DEFAULT '',
        lastname VARCHAR(100) NOT NULL DEFAULT '',
        role ENUM('super_admin','wfh_admin','wfh_staff','cb_admin','att_teacher') NOT NULL DEFAULT 'wfh_staff',
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        last_login DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'wfh_users' => "CREATE TABLE IF NOT EXISTS wfh_users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        firstname VARCHAR(100) NOT NULL DEFAULT '',
        lastname VARCHAR(100) NOT NULL DEFAULT '',
        position VARCHAR(200) DEFAULT '',
        dept_id INT DEFAULT 0,
        role ENUM('admin','user') NOT NULL DEFAULT 'user',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'wfh_departments' => "CREATE TABLE IF NOT EXISTS wfh_departments (
        dept_id INT AUTO_INCREMENT PRIMARY KEY,
        dept_name VARCHAR(200) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'wfh_timelogs' => "CREATE TABLE IF NOT EXISTS wfh_timelogs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        log_date DATE NOT NULL,
        check_in_time TIME NULL,
        check_out_time TIME NULL,
        check_in_status VARCHAR(50) DEFAULT 'ปกติ',
        check_in_lat DECIMAL(10,7) NULL,
        check_in_lng DECIMAL(10,7) NULL,
        check_in_photo VARCHAR(500) NULL,
        check_out_lat DECIMAL(10,7) NULL,
        check_out_lng DECIMAL(10,7) NULL,
        check_out_photo VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'wfh_system_settings' => "CREATE TABLE IF NOT EXISTS wfh_system_settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        regular_time_in TIME NOT NULL DEFAULT '08:00:00',
        late_time TIME NOT NULL DEFAULT '08:30:00',
        school_lat DECIMAL(10,7) NOT NULL DEFAULT 0,
        school_lng DECIMAL(10,7) NOT NULL DEFAULT 0,
        geofence_radius INT NOT NULL DEFAULT 200,
        telegram_token VARCHAR(255) DEFAULT '',
        admin_chat_id VARCHAR(100) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'cb_chromebooks' => "CREATE TABLE IF NOT EXISTS cb_chromebooks (
        chromebook_id INT AUTO_INCREMENT PRIMARY KEY,
        model VARCHAR(200) DEFAULT '',
        serial_number VARCHAR(200) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'cb_teachers' => "CREATE TABLE IF NOT EXISTS cb_teachers (
        teacher_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'cb_students' => "CREATE TABLE IF NOT EXISTS cb_students (
        student_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        class_name VARCHAR(100) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'cb_borrow_logs' => "CREATE TABLE IF NOT EXISTS cb_borrow_logs (
        entry_id INT AUTO_INCREMENT PRIMARY KEY,
        borrower_type VARCHAR(50) NOT NULL DEFAULT 'teacher',
        borrower_id INT NOT NULL,
        class_name VARCHAR(100) DEFAULT '',
        chromebook_id INT NULL,
        chromebook_serial VARCHAR(200) DEFAULT '',
        images VARCHAR(1000) DEFAULT '',
        status ENUM('Borrowed','Returned') NOT NULL DEFAULT 'Borrowed',
        date_borrowed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_returned DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'cb_inspections' => "CREATE TABLE IF NOT EXISTS cb_inspections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        borrow_log_id INT NOT NULL,
        condition_status VARCHAR(100) DEFAULT '',
        notes VARCHAR(500) DEFAULT '',
        images VARCHAR(1000) DEFAULT '',
        inspected_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'att_teachers' => "CREATE TABLE IF NOT EXISTS att_teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        username VARCHAR(100) DEFAULT '',
        password VARCHAR(255) DEFAULT '',
        llw_user_id INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'att_students' => "CREATE TABLE IF NOT EXISTS att_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        name VARCHAR(200) NOT NULL,
        classroom VARCHAR(100) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'att_subjects' => "CREATE TABLE IF NOT EXISTS att_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_code VARCHAR(50) NOT NULL,
        subject_name VARCHAR(200) NOT NULL,
        classroom VARCHAR(100) DEFAULT '',
        teacher_id INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'att_attendance' => "CREATE TABLE IF NOT EXISTS att_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        period INT NOT NULL DEFAULT 1,
        subject_id INT NOT NULL DEFAULT 0,
        teacher_id INT NOT NULL DEFAULT 0,
        student_id VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'มา',
        time_in TIME NULL,
        note VARCHAR(500) DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

foreach ($tables_sql as $table => $sql) {
    if ($conn->query($sql)) {
        $count = $conn->query("SELECT COUNT(*) FROM $table")->fetch_row()[0];
        echo "✅ <b>$table</b> — OK ($count rows)<br>";
    } else {
        echo "❌ <b>$table</b> — Error: " . $conn->error . "<br>";
    }
}

// ─── 3. สร้าง Default Data ─────────────────────────────────
echo "<h3>3. สร้างข้อมูลเริ่มต้น</h3>";

// ตรวจสอบว่ามี admin_llw อยู่ใน llw_users หรือยัง
$check = $conn->query("SELECT user_id FROM llw_users WHERE username = 'admin_llw' LIMIT 1");
if ($check->num_rows === 0) {
    $hashed = password_hash('123456', PASSWORD_DEFAULT);
    $sql = "INSERT INTO llw_users (username, password, firstname, lastname, role, status)
            VALUES ('admin_llw', '$hashed', 'Admin', 'LLW', 'super_admin', 'active')";
    if ($conn->query($sql)) {
        echo "✅ สร้าง user <b>admin_llw</b> (pass: 123456, role: super_admin) สำเร็จ<br>";
    } else {
        echo "❌ สร้าง admin_llw ล้มเหลว: " . $conn->error . "<br>";
    }
} else {
    // อัพเดต password ให้เป็น 123456
    $hashed = password_hash('123456', PASSWORD_DEFAULT);
    $conn->query("UPDATE llw_users SET password = '$hashed' WHERE username = 'admin_llw'");
    echo "✅ <b>admin_llw</b> มีอยู่แล้ว — อัพเดต password เป็น <b>123456</b><br>";
}

// ตรวจสอบ wfh_system_settings
$check2 = $conn->query("SELECT setting_id FROM wfh_system_settings LIMIT 1");
if ($check2->num_rows === 0) {
    $conn->query("INSERT INTO wfh_system_settings (regular_time_in, late_time, geofence_radius) VALUES ('08:00:00','08:30:00',200)");
    echo "✅ สร้างค่า <b>wfh_system_settings</b> เริ่มต้นสำเร็จ<br>";
} else {
    echo "✅ <b>wfh_system_settings</b> มีข้อมูลแล้ว<br>";
}

// ─── 4. สรุป ────────────────────────────────────────────────
echo "<hr>";
echo "<h3>🎉 Setup เสร็จสมบูรณ์!</h3>";
echo "<p>ทดลอง login ด้วย:</p>";
echo "<ul>";
echo "<li><b>Username:</b> admin_llw</li>";
echo "<li><b>Password:</b> 123456</li>";
echo "<li><b>Role:</b> super_admin</li>";
echo "</ul>";
echo "<p><a href='login.php'>➡️ ไปหน้า Login</a></p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ อย่าลืมลบไฟล์ setup.php หลังจากตั้งค่าเสร็จ!</p>";

$conn->close();
?>
