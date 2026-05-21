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
        pmEnsureTables($pdo); // Self-healing: สร้างตาราง pm_* ถ้ายังไม่มี
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

// ── Ensure Columns (ALTER TABLE) ──────────────────────────────────────────
// เพิ่ม column ที่ขาดหายในตาราง pm_meetings โดยไม่ทำลายข้อมูลเดิม
// เรียกใช้เมื่อตาราง pm_users มีอยู่แล้ว (คือ migration เก่าสร้างไว้ก่อน)
function pmEnsureColumns(PDO $pdo): void {
    static $colChecked = false;
    if ($colChecked) return;
    $colChecked = true;

    // Helper: เพิ่ม column เฉพาะเมื่อยังไม่มี (safe idempotent)
    $addCol = function (string $column, string $def) use ($pdo): void {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `pm_meetings` LIKE '{$column}'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE `pm_meetings` ADD COLUMN `{$column}` {$def}");
            }
        } catch (Exception $e) {
            error_log("[Parent Meeting] pmEnsureColumns({$column}): " . $e->getMessage());
        }
    };

    // columns สำหรับบันทึกข้อความ (ชล.๐๑ ส่วนบน)
    $addCol('doc_no',             'VARCHAR(100) NULL');
    $addCol('doc_date',           'DATE NULL');
    $addCol('command_no',         'VARCHAR(100) NULL');
    $addCol('command_date',       'DATE NULL');

    // columns สำหรับวาระและมติการประชุม (ชล.๐๑ ส่วนกลาง)
    $addCol('agenda_1',           'TEXT NULL');
    $addCol('agenda_2',           'TEXT NULL');
    $addCol('agenda_3',           'TEXT NULL');
    $addCol('consensus',          'TEXT NULL');

    // columns สำหรับบรรยากาศการประชุม (ชล.๐๑ ส่วนล่าง)
    $addCol('cooperation_rating', 'TEXT NULL');
    $addCol('useful_suggestions', 'TEXT NULL');
    $addCol('support_received',   'TEXT NULL');
    $addCol('other_observations', 'TEXT NULL');
}

// ── Self-Healing Migration ───────────────────────────────────────────────
// สร้างตาราง pm_* อัตโนมัติหากยังไม่มี (แก้ปัญหา Production ไม่ต้องรัน migrate ด้วยมือ)
function pmEnsureTables(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        // ตรวจสอบว่าตารางหลักมีอยู่แล้วหรือไม่
        $pdo->query("SELECT 1 FROM pm_users LIMIT 1");
        // ตารางมีอยู่แล้ว → ตรวจสอบและเพิ่ม column ใหม่ที่อาจขาด
        pmEnsureColumns($pdo);
        return;
    } catch (Exception $e) {
        // ตารางยังไม่มี → รัน migration
    }

    // รัน migration file ผ่าน migration runner หากมี
    $migrationFile = __DIR__ . '/../database/migrations/2026_05_21_000002_create_pm_tables.php';
    if (file_exists($migrationFile)) {
        try {
            $migration = require $migrationFile;
            if (is_array($migration) && isset($migration['up']) && is_callable($migration['up'])) {
                $migration['up']($pdo);
            }
        } catch (Exception $ex) {
            error_log('[Parent Meeting] Self-healing migration failed: ' . $ex->getMessage());
        }
        return;
    }

    // Fallback: สร้างตารางขั้นต่ำโดยตรงหาก migration file ไม่พบ
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fullname VARCHAR(150) NOT NULL,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','executive','teacher') NOT NULL DEFAULT 'teacher',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_classrooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(50) NOT NULL,
            room_name VARCHAR(50) NOT NULL,
            teacher_name VARCHAR(150) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meetings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_date DATE NOT NULL,
            semester VARCHAR(10) NOT NULL,
            academic_year INT NOT NULL,
            classroom_id INT NOT NULL,
            total_students INT DEFAULT 0,
            total_parents INT DEFAULT 0,
            attend_count INT DEFAULT 0,
            absent_count INT DEFAULT 0,
            summary TEXT NULL,
            problems TEXT NULL,
            suggestions TEXT NULL,
            doc_no VARCHAR(100) NULL,
            doc_date DATE NULL,
            command_no VARCHAR(100) NULL,
            command_date DATE NULL,
            agenda_1 TEXT NULL,
            agenda_2 TEXT NULL,
            agenda_3 TEXT NULL,
            consensus TEXT NULL,
            cooperation_rating TEXT NULL,
            useful_suggestions TEXT NULL,
            support_received TEXT NULL,
            other_observations TEXT NULL,
            created_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meeting_attendants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            parent_name VARCHAR(150) NOT NULL DEFAULT '',
            phone VARCHAR(30) NOT NULL DEFAULT '',
            relationship VARCHAR(100) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meeting_absents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            parent_name VARCHAR(150) NOT NULL DEFAULT '',
            phone VARCHAR(30) NOT NULL DEFAULT '',
            relationship VARCHAR(100) NOT NULL DEFAULT '',
            absent_reason VARCHAR(255) NULL,
            follow_up_status VARCHAR(255) NULL,
            follow_up_date DATE NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_student_relations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            classroom_no VARCHAR(20) NOT NULL DEFAULT '',
            student_no INT DEFAULT 0,
            parent_name VARCHAR(150) NOT NULL DEFAULT '',
            relationship VARCHAR(100) NOT NULL DEFAULT '',
            grade_zero_count INT DEFAULT 0,
            grade_r_count INT DEFAULT 0,
            grade_ms_count INT DEFAULT 0,
            grade_mp_count INT DEFAULT 0,
            behavior_score_deducted INT DEFAULT 0,
            praise_teacher_json TEXT NULL,
            praise_teacher_other VARCHAR(255) NULL,
            praise_parent_json TEXT NULL,
            praise_parent_other VARCHAR(255) NULL,
            improve_teacher_json TEXT NULL,
            improve_teacher_other VARCHAR(255) NULL,
            improve_parent_json TEXT NULL,
            improve_parent_other VARCHAR(255) NULL,
            teacher_remedy TEXT NULL,
            parent_remedy TEXT NULL,
            parent_support_request TEXT NULL,
            parent_meeting_impression TEXT NULL,
            parent_teacher_feedback TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meet_greet_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            group_topic VARCHAR(255) NOT NULL,
            attendants_json TEXT NULL,
            discussion_summary TEXT NULL,
            discussion_resolution TEXT NULL,
            school_support_request TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_student_letters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            classroom_no VARCHAR(20) NOT NULL DEFAULT '',
            student_no INT DEFAULT 0,
            letter_to_whom VARCHAR(150) NOT NULL DEFAULT '',
            impressed_story TEXT NULL,
            inner_feelings TEXT NULL,
            proud_story TEXT NULL,
            improvement_plan TEXT NULL,
            parent_response TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_network_parents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            position_name ENUM('ประธาน','รองประธาน','กรรมการ','เลขานุการ') NOT NULL,
            parent_name VARCHAR(150) NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            student_class VARCHAR(50) NOT NULL,
            address TEXT NOT NULL,
            phone VARCHAR(20) NOT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_meeting_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pm_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            comment_text TEXT NOT NULL,
            commented_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed default admin user
        $cnt = $pdo->query("SELECT COUNT(*) FROM pm_users")->fetchColumn();
        if ($cnt == 0) {
            $ins = $pdo->prepare("INSERT INTO pm_users (fullname, username, password, role) VALUES (?, ?, ?, ?)");
            $ins->execute(['ผู้ดูแลระบบ', 'admin_user', password_hash('admin1234', PASSWORD_DEFAULT), 'admin']);
        }
    } catch (Exception $ex2) {
        error_log('[Parent Meeting] Fallback table creation failed: ' . $ex2->getMessage());
    }
}

// ── Central LLW Database Connection ──────────────────────────────────────
// ดึงข้อมูลจาก DB กลาง (att_teachers, att_students) สำหรับ sync ข้อมูลชั้นเรียน
function getLlwPdo(): ?PDO {
    static $llwPdo = null;
    static $tried = false;
    if ($tried) return $llwPdo;
    $tried = true;

    // ลอง getPdo() จากระบบหลักก่อน (ถ้าโหลดมาแล้ว)
    if (function_exists('getPdo')) {
        try {
            $llwPdo = getPdo();
            return $llwPdo;
        } catch (Exception $e) {
            // fallthrough
        }
    }

    // ลอง connect โดยตรง
    try {
        $dsn = 'mysql:host=' . PM_DB_HOST . ';dbname=' . (defined('DB_NAME') ? DB_NAME : 'llw_db') . ';charset=utf8mb4';
        $llwPdo = new PDO($dsn, PM_DB_USER, PM_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $llwPdo;
    } catch (Exception $e) {
        error_log('[Parent Meeting] getLlwPdo failed: ' . $e->getMessage());
        return null;
    }
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
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // อ้างอิงสิทธิ์จากเซสชันกลางของระบบหลัก LLW (ถ้ามี) เพื่อทำการลงทะเบียนหรือล็อคอินให้อัตโนมัติ
    if (!isset($_SESSION['pm_user_id']) && isset($_SESSION['llw_role'])) {
        try {
            $pdo = getPmPdo();
            $stmt = $pdo->prepare("SELECT * FROM pm_users WHERE username = ?");
            $stmt->execute([$_SESSION['username']]);
            $pmUser = $stmt->fetch();
            
            $roleMap = [
                'super_admin' => 'admin',
                'wfh_admin' => 'executive',
                'att_teacher' => 'teacher',
                'wfh_staff' => 'teacher',
                'cb_admin' => 'teacher'
            ];
            $pmRole = $roleMap[$_SESSION['llw_role']] ?? 'teacher';
            
            if ($pmUser) {
                $_SESSION['pm_user_id'] = $pmUser['id'];
                $_SESSION['pm_fullname'] = $pmUser['fullname'];
                $_SESSION['pm_username'] = $pmUser['username'];
                $_SESSION['pm_role'] = $pmUser['role'];
            } else {
                $fullname = $_SESSION['fullname'] ?? ($_SESSION['firstname'] . ' ' . ($_SESSION['lastname'] ?? ''));
                if (empty(trim($fullname))) {
                    $fullname = $_SESSION['username'];
                }
                $randPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO pm_users (fullname, username, password, role) VALUES (?, ?, ?, ?)");
                $ins->execute([$fullname, $_SESSION['username'], $randPass, $pmRole]);
                
                $_SESSION['pm_user_id'] = $pdo->lastInsertId();
                $_SESSION['pm_fullname'] = $fullname;
                $_SESSION['pm_username'] = $_SESSION['username'];
                $_SESSION['pm_role'] = $pmRole;
            }
        } catch (Exception $e) {
            error_log('[Parent Meeting] Auto-login failed: ' . $e->getMessage());
        }
    }
    
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
