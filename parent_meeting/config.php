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

// Database structure is initialized via standard migration files.

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
