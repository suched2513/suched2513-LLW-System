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
            }
        } catch (\Throwable $e) {
            error_log('[Financial DB ensureTablesExist] ' . $e->getMessage());
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
