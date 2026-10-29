<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Class Database
 * จัดการการเชื่อมต่อฐานข้อมูล PDO แบบ Singleton
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        return self::$instance;
    }

    private static function createConnection(): PDO
    {
        // 1. โหลด config กลางของระบบ LLW ถ้ายังไม่ได้โหลด
        $mainConfig = __DIR__ . '/../../../config/database.php';
        if (file_exists($mainConfig)) {
            require_once $mainConfig;
        }

        // 2. ถ้ามี getPdo() จากระบบหลัก ให้ใช้ได้ทันที
        if (function_exists('getPdo')) {
            try {
                $pdo = getPdo();
                if ($pdo instanceof PDO) {
                    self::$instance = $pdo;
                    self::ensureTablesExist($pdo);
                    return $pdo;
                }
            } catch (\Throwable $te) {
                error_log('[Financial DB getPdo Error] ' . $te->getMessage());
            }
        }

        // 3. ตรวจสอบไฟล์ .env ใน financial หรือ parent
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim(trim($v), '"\'');
                if (!isset($_ENV[$k])) {
                    $_ENV[$k] = $v;
                    putenv("$k=$v");
                }
            }
        }

        // 4. ดึงค่า config หรือ fallback เข้ากับ config กลางของระบบ LLW
        $host = $_ENV['DB_HOST'] ?? (defined('DB_HOST') ? DB_HOST : '127.0.0.1');
        $port = $_ENV['DB_PORT'] ?? '3306';
        $db   = $_ENV['DB_DATABASE'] ?? (defined('DB_NAME') ? DB_NAME : 'llw_db');
        $user = $_ENV['DB_USERNAME'] ?? (defined('DB_USER') ? DB_USER : 'root');
        $pass = $_ENV['DB_PASSWORD'] ?? (defined('DB_PASS') ? DB_PASS : '');

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+07:00'"
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            self::$instance = $pdo;
            self::ensureTablesExist($pdo);
            return $pdo;
        } catch (PDOException $e) {
            error_log('[Financial DB Error] ' . $e->getMessage());
            throw new PDOException("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $e->getMessage());
        }
    }

    private static function ensureTablesExist(PDO $pdo): void
    {
        try {
            $hasUsers = $pdo->query("SHOW TABLES LIKE 'fin_users'")->rowCount() > 0;
            if (!$hasUsers) {
                self::autoMigrate($pdo);
            } else {
                // ตรวจสอบว่าได้ seed สิทธิ์และบทบาทแล้วหรือยัง
                $hasUserRoles = (int)$pdo->query("SELECT COUNT(*) FROM fin_user_roles")->fetchColumn();
                $hasRolePerms = (int)$pdo->query("SELECT COUNT(*) FROM fin_role_permissions")->fetchColumn();
                if ($hasUserRoles === 0 || $hasRolePerms === 0) {
                    $seedFile = __DIR__ . '/../../database/seed.php';
                    if (file_exists($seedFile)) {
                        require_once $seedFile;
                    }
                }
            }

            // ปรับปรุงคอลัมน์ที่จำเป็นสำหรับสภาพแวดล้อมต่างๆ (Schema Patching)
            self::patchDatabaseSchema($pdo);
        } catch (\Throwable $e) {
            error_log('[Financial DB ensureTablesExist] ' . $e->getMessage());
        }
    }

    public static function patchDatabaseSchema(PDO $pdo): void
    {
        try {
            $hasColumn = function(string $table, string $column) use ($pdo): bool {
                $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
                $stmt->execute([$column]);
                return $stmt->rowCount() > 0;
            };

            // 1. fin_bank_accounts
            if ($pdo->query("SHOW TABLES LIKE 'fin_bank_accounts'")->rowCount() > 0) {
                if (!$hasColumn('fin_bank_accounts', 'account_number')) {
                    $pdo->exec("ALTER TABLE fin_bank_accounts ADD COLUMN account_number VARCHAR(50) NULL AFTER account_no");
                    $pdo->exec("UPDATE fin_bank_accounts SET account_number = account_no WHERE account_number IS NULL OR account_number = ''");
                }
                if (!$hasColumn('fin_bank_accounts', 'current_balance')) {
                    $pdo->exec("ALTER TABLE fin_bank_accounts ADD COLUMN current_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER account_type");
                }
            }

            // 2. fin_accounting_periods
            if ($pdo->query("SHOW TABLES LIKE 'fin_accounting_periods'")->rowCount() > 0) {
                if (!$hasColumn('fin_accounting_periods', 'period_number')) {
                    $pdo->exec("ALTER TABLE fin_accounting_periods ADD COLUMN period_number INT NULL AFTER period_no");
                    $pdo->exec("UPDATE fin_accounting_periods SET period_number = period_no WHERE period_number IS NULL");
                }
                if (!$hasColumn('fin_accounting_periods', 'period_name')) {
                    $pdo->exec("ALTER TABLE fin_accounting_periods ADD COLUMN period_name VARCHAR(100) NULL AFTER period_number");
                    $pdo->exec("UPDATE fin_accounting_periods SET period_name = CONCAT('งวดที่ ', period_no) WHERE period_name IS NULL OR period_name = ''");
                }
                if (!$hasColumn('fin_accounting_periods', 'is_closed')) {
                    $pdo->exec("ALTER TABLE fin_accounting_periods ADD COLUMN is_closed TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
                    $pdo->exec("UPDATE fin_accounting_periods SET is_closed = CASE WHEN status = 'closed' OR status = 'locked' THEN 1 ELSE 0 END");
                }
            }

            // 3. fin_audit_logs
            if ($pdo->query("SHOW TABLES LIKE 'fin_audit_logs'")->rowCount() > 0) {
                if (!$hasColumn('fin_audit_logs', 'module')) {
                    $pdo->exec("ALTER TABLE fin_audit_logs ADD COLUMN module VARCHAR(50) NULL AFTER user_id");
                    if ($hasColumn('fin_audit_logs', 'entity_type')) {
                        $pdo->exec("UPDATE fin_audit_logs SET module = entity_type WHERE module IS NULL");
                    }
                }
                if (!$hasColumn('fin_audit_logs', 'table_name')) {
                    $pdo->exec("ALTER TABLE fin_audit_logs ADD COLUMN table_name VARCHAR(100) NULL AFTER module");
                }
                if (!$hasColumn('fin_audit_logs', 'record_id')) {
                    $pdo->exec("ALTER TABLE fin_audit_logs ADD COLUMN record_id INT NULL AFTER table_name");
                }
                if (!$hasColumn('fin_audit_logs', 'old_values')) {
                    $pdo->exec("ALTER TABLE fin_audit_logs ADD COLUMN old_values TEXT NULL");
                }
                if (!$hasColumn('fin_audit_logs', 'new_values')) {
                    $pdo->exec("ALTER TABLE fin_audit_logs ADD COLUMN new_values TEXT NULL");
                }
                if (!$hasColumn('fin_audit_logs', 'notes')) {
                    $pdo->exec("ALTER TABLE fin_audit_logs ADD COLUMN notes TEXT NULL");
                    if ($hasColumn('fin_audit_logs', 'details')) {
                        $pdo->exec("UPDATE fin_audit_logs SET notes = details WHERE notes IS NULL");
                    }
                }
            }

            // 4. fin_projects
            if ($pdo->query("SHOW TABLES LIKE 'fin_projects'")->rowCount() > 0) {
                if (!$hasColumn('fin_projects', 'activity_name')) {
                    $pdo->exec("ALTER TABLE fin_projects ADD COLUMN activity_name VARCHAR(255) NULL AFTER project_name");
                }
                if (!$hasColumn('fin_projects', 'responsible_person')) {
                    $pdo->exec("ALTER TABLE fin_projects ADD COLUMN responsible_person VARCHAR(150) NULL AFTER department");
                }
                if (!$hasColumn('fin_projects', 'planned_amount')) {
                    $pdo->exec("ALTER TABLE fin_projects ADD COLUMN planned_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER responsible_person");
                }
                if (!$hasColumn('fin_projects', 'notes')) {
                    $pdo->exec("ALTER TABLE fin_projects ADD COLUMN notes TEXT NULL AFTER planned_amount");
                }
            }

            // 5. fin_funding_sources: ประเภทการเงินและแหล่งเงินสถานศึกษา 11 ประเภท
            if ($pdo->query("SHOW TABLES LIKE 'fin_funding_sources'")->rowCount() > 0) {
                $fundingSources = [
                    ['SUB-01', 'ค่าจัดการเรียนการสอน', 'subsidy'],
                    ['SUB-02', 'กิจกรรมพัฒนาคุณภาพผู้เรียน', 'subsidy'],
                    ['SUB-03', 'ค่าอุปกรณ์การเรียน', 'subsidy'],
                    ['SUB-04', 'ค่าหนังสือเรียน', 'subsidy'],
                    ['SUB-05', 'ค่าเครื่องแต่งกาย', 'subsidy'],
                    ['SUB-06', 'เงินปัจจัยพื้นฐาน นร.ยากจน', 'subsidy'],
                    ['REV-01', 'เงินรายได้สถานศึกษา', 'revenue'],
                    ['DON-01', 'เงินบริจาค', 'donation'],
                    ['SUB-07', 'เงินค่ากระแสไฟฟ้า', 'subsidy'],
                    ['SUB-08', 'เงินค่ารถรับ-ส่ง นักเรียน', 'subsidy'],
                    ['OTH-01', 'เงินสนับสนุนอื่นๆ', 'other'],
                    ['FEE-01', 'ค่าบำรุงการศึกษาห้องเรียนพิเศษ', 'tuition_fee'],
                ];
                $stmtFs = $pdo->prepare("INSERT INTO fin_funding_sources (source_code, source_name, source_type, is_active) 
                                         VALUES (?, ?, ?, 1) 
                                         ON DUPLICATE KEY UPDATE source_name = VALUES(source_name), source_type = VALUES(source_type)");
                foreach ($fundingSources as $fs) {
                    $stmtFs->execute($fs);
                }
            }

            // 6. ตรวจสอบการนำเข้าแผนงบประมาณปี 2570 ครบทั้ง 4 ฝ่าย
            if ($pdo->query("SHOW TABLES LIKE 'fin_projects'")->rowCount() > 0 &&
                $pdo->query("SHOW TABLES LIKE 'fin_fiscal_years'")->rowCount() > 0) {
                $hasProjects2570 = (int)$pdo->query("
                    SELECT COUNT(*) FROM fin_projects p 
                    JOIN fin_fiscal_years fy ON p.fiscal_year_id = fy.id 
                    WHERE fy.year_be = 2570
                ")->fetchColumn();
                if ($hasProjects2570 === 0) {
                    $importScript = __DIR__ . '/../../database/import_school_plan_2570.php';
                    if (file_exists($importScript)) {
                        require_once $importScript;
                        runSchoolPlanImport($pdo);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[Financial DB patchDatabaseSchema] ' . $e->getMessage());
        }
    }

    public static function autoMigrate(PDO $pdo): void
    {
        $schemaFile = __DIR__ . '/../../database/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    try {
                        $pdo->exec($stmt);
                    } catch (\Throwable $e) {}
                }
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            // รัน seeder
            $seedFile = __DIR__ . '/../../database/seed.php';
            if (file_exists($seedFile)) {
                try {
                    require_once $seedFile;
                } catch (\Throwable $se) {}
            }
        }
    }

    /**
     * สำหรับการทดสอบหรือ reset connection
     */
    public static function setConnection(PDO $pdo): void
    {
        self::$instance = $pdo;
    }
}
