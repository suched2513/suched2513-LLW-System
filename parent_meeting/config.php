<?php
/**
 * parent_meeting/config.php - การตั้งค่าระบบและการเชื่อมต่อฐานข้อมูล
 */

// เริ่มต้น Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตั้งค่า Charset & Timezone
ini_set('default_charset', 'UTF-8');
date_default_timezone_set('Asia/Bangkok');

// โหลดค่ากำหนดเชื่อมต่อฐานข้อมูลจากระบบหลัก (สำหรับกรณีขึ้น Production)
$central_db_file = __DIR__ . '/../config/database.php';
if (file_exists($central_db_file)) {
    require_once $central_db_file;
}

// กำหนดค่า Default หากไม่มีการระบุไว้ในฐานข้อมูลกลาง
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'school_parent_meeting');

define('PM_DB_HOST', DB_HOST);
define('PM_DB_USER', DB_USER);
define('PM_DB_PASS', DB_PASS);
define('PM_DB_NAME', DB_NAME);

// ฟังก์ชันเชื่อมต่อ PDO พร้อมระบบสร้างฐานข้อมูลอัตโนมัติ (Self-Healing DB Connection)
function getPmPdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    
    // พยายามเชื่อมต่อฐานข้อมูลหลักก่อน
    try {
        $dsn = 'mysql:host=' . PM_DB_HOST . ';dbname=' . PM_DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, PM_DB_USER, PM_DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        // หากฐานข้อมูลไม่มีอยู่ (Error Code 1049) ให้พยายามสร้างฐานข้อมูลขึ้นมาใหม่
        if ($e->getCode() == 1049 || strpos($e->getMessage(), '1049') !== false) {
            try {
                // เชื่อมต่อโดยไม่ระบุ dbname เพื่อเข้าสู่ระบบและสร้างฐานข้อมูล
                $initDsn = 'mysql:host=' . PM_DB_HOST . ';charset=utf8mb4';
                $initPdo = new PDO($initDsn, PM_DB_USER, PM_DB_PASS);
                $initPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // สร้างฐานข้อมูลหลัก
                $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . PM_DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // เชื่อมต่อใหม่อีกครั้งไปยังฐานข้อมูลที่เพิ่งสร้าง
                $dsn = 'mysql:host=' . PM_DB_HOST . ';dbname=' . PM_DB_NAME . ';charset=utf8mb4';
                $pdo = new PDO($dsn, PM_DB_USER, PM_DB_PASS);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                return $pdo;
            } catch (PDOException $ex) {
                // หากสร้างฐานข้อมูลหลักไม่ได้ ลอง fallback ไปหา llw_db
                try {
                    $fallbackDsn = 'mysql:host=' . PM_DB_HOST . ';dbname=llw_db;charset=utf8mb4';
                    $pdo = new PDO($fallbackDsn, PM_DB_USER, PM_DB_PASS);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    return $pdo;
                } catch (PDOException $ex2) {
                    // หากไม่มี llw_db ให้พยายามสร้าง llw_db
                    try {
                        $initDsn = 'mysql:host=' . PM_DB_HOST . ';charset=utf8mb4';
                        $initPdo = new PDO($initDsn, PM_DB_USER, PM_DB_PASS);
                        $initPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $initPdo->exec("CREATE DATABASE IF NOT EXISTS `llw_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        
                        $fallbackDsn = 'mysql:host=' . PM_DB_HOST . ';dbname=llw_db;charset=utf8mb4';
                        $pdo = new PDO($fallbackDsn, PM_DB_USER, PM_DB_PASS);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                        return $pdo;
                    } catch (PDOException $ex3) {
                        error_log('[Parent Meeting] Database creation and connection error: ' . $ex3->getMessage());
                        die('ไม่สามารถสร้างหรือเชื่อมต่อฐานข้อมูลได้: ' . htmlspecialchars($ex3->getMessage()));
                    }
                }
            }
        } else {
            error_log('[Parent Meeting] Database connection error: ' . $e->getMessage());
            die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . htmlspecialchars($e->getMessage()));
        }
    }
}

// ตรวจสอบและสร้างตาราง + ข้อมูลเดโมอัตโนมัติ (Self-Healing / Auto-Seed System)
function initDatabaseStructure() {
    $pdo = getPmPdo();
    
    // สร้างตาราง users
    $pdo->exec("CREATE TABLE IF NOT EXISTS pm_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(150) NOT NULL,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'executive', 'teacher') NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // สร้างตาราง classrooms
    $pdo->exec("CREATE TABLE IF NOT EXISTS pm_classrooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level VARCHAR(50) NOT NULL,
        room_name VARCHAR(50) NOT NULL,
        teacher_name VARCHAR(150) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ตรวจสอบและสร้างห้องเรียนเดโม หากยังไม่มี
    $stmt = $pdo->query("SELECT COUNT(*) FROM pm_classrooms");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO pm_classrooms (level, room_name, teacher_name) VALUES 
        ('ม.1', '1', 'สมชาย ใจดี'),
        ('ม.1', '2', 'สมศรี รักษ์ดี'),
        ('ม.2', '1', 'ประยุทธ์ สู้ๆ'),
        ('ม.2', '2', 'ประวิตร วงษ์สวย'),
        ('ม.3', '1', 'อนุทิน กัญชาดี'),
        ('ม.3', '2', 'พิธา ก้าวหน้า')");
    }

    // ตรวจสอบและสร้างผู้ใช้เดโม
    $stmt = $pdo->query("SELECT COUNT(*) FROM pm_users");
    if ($stmt->fetchColumn() == 0) {
        // แอดมิน: admin_user / admin1234
        // ผู้บริหาร: director_user / director1234
        // ครูที่ปรึกษา: teacher_user / teacher1234
        $users = [
            ['fullname' => 'แอดมิน ระบบ', 'username' => 'admin_user', 'password' => password_hash('admin1234', PASSWORD_DEFAULT), 'role' => 'admin'],
            ['fullname' => 'ผู้อำนวยการ สมศักดิ์', 'username' => 'director_user', 'password' => password_hash('director1234', PASSWORD_DEFAULT), 'role' => 'executive'],
            ['fullname' => 'สมชาย ใจดี', 'username' => 'teacher_user', 'password' => password_hash('teacher1234', PASSWORD_DEFAULT), 'role' => 'teacher']
        ];
        
        $insert = $pdo->prepare("INSERT INTO pm_users (fullname, username, password, role) VALUES (?, ?, ?, ?)");
        foreach ($users as $u) {
            $insert->execute([$u['fullname'], $u['username'], $u['password'], $u['role']]);
        }
    }

    // สร้างตาราง meetings
    $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_date DATE NOT NULL,
        semester VARCHAR(10) NOT NULL,
        academic_year INT NOT NULL,
        classroom_id INT NOT NULL,
        total_students INT NOT NULL,
        total_parents INT NOT NULL,
        attend_count INT NOT NULL,
        absent_count INT NOT NULL,
        summary TEXT,
        problems TEXT,
        suggestions TEXT,
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (classroom_id) REFERENCES pm_classrooms(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES pm_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // สร้างตาราง network_parents
    $pdo->exec("CREATE TABLE IF NOT EXISTS pm_network_parents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        position_name ENUM('ประธาน', 'รองประธาน', 'กรรมการ', 'เลขานุการ') NOT NULL,
        parent_name VARCHAR(150) NOT NULL,
        student_name VARCHAR(150) NOT NULL,
        student_class VARCHAR(50) NOT NULL,
        address TEXT NOT NULL,
        phone VARCHAR(20) NOT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // สร้างตาราง meeting_images
    $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meeting_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // สร้างตาราง comments
    $pdo->exec("CREATE TABLE IF NOT EXISTS pm_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        comment_text TEXT NOT NULL,
        commented_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (meeting_id) REFERENCES pm_meetings(id) ON DELETE CASCADE,
        FOREIGN KEY (commented_by) REFERENCES pm_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// เรียกให้ระบบตรวจสอบตารางฐานข้อมูลและ Seed อัตโนมัติเมื่อมีการโหลด
try {
    initDatabaseStructure();
} catch (Exception $e) {
    // ป้องกันการล่มถ้ายังไม่มีการ import sql หรือ db ยังไม่พร้อม
    error_log('[Parent Meeting] Database initialization error: ' . $e->getMessage());
}

// ── Base Path Detection ──────────────────────────────────────────
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$appDir  = str_replace('\\', '/', realpath(__DIR__));
$base_path = str_replace($docRoot, '', $appDir);
$base_path = '/' . trim($base_path, '/');
if ($base_path === '/') $base_path = '';

// Helper ในการแสดง Path
function pm_url($path = '') {
    global $base_path;
    return $base_path . '/' . ltrim($path, '/');
}

// ฟังก์ชันกรองความปลอดภัย XSS (Escape Output)
function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ฟังก์ชันแปลงวันที่เป็นภาษาไทย
function th_date($dateStr) {
    if (!$dateStr) return '-';
    $time = strtotime($dateStr);
    $thai_months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    $day = date('j', $time);
    $month = $thai_months[(int)date('n', $time)];
    $year = date('Y', $time) + 543;
    return "$day $month $year";
}

// ฟังก์ชันตรวจสอบการ Login
function checkLogin() {
    if (!isset($_SESSION['pm_user_id'])) {
        header('Location: ' . pm_url('login.php'));
        exit;
    }
}

// ฟังก์ชันตรวจสอบสิทธิ์ (Role Check)
function checkRole($allowedRoles = []) {
    checkLogin();
    if (!in_array($_SESSION['pm_role'], $allowedRoles)) {
        header('Location: ' . pm_url('dashboard.php?error=unauthorized'));
        exit;
    }
}
