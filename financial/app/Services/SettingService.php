<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Class SettingService
 * จัดการข้อมูลการตั้งค่าองค์กรและนโยบายระบบ (fin_system_settings)
 */
class SettingService
{
    private static ?array $settings = null;

    public static function all(): array
    {
        if (self::$settings === null) {
            self::loadSettings();
        }
        return self::$settings;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$settings === null) {
            self::loadSettings();
        }
        return self::$settings[$key] ?? $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general', ?string $description = null): bool
    {
        $pdo = Database::getConnection();
        $oldValue = self::get($key);

        $stmt = $pdo->prepare("
            INSERT INTO fin_system_settings (`setting_key`, `setting_value`, `group_name`, `description`, `updated_at`)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `updated_at` = NOW()
        ");
        $success = $stmt->execute([$key, (string)$value, $group, $description]);

        if ($success) {
            self::$settings[$key] = $value;
            AuditTrailService::log('settings', 'update', 'fin_system_settings', null, [$key => $oldValue], [$key => $value], "แก้ไขการตั้งค่า {$key}");
        }

        return $success;
    }

    public static function setMany(array $keyValues, string $group = 'general'): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            foreach ($keyValues as $key => $val) {
                self::set($key, $val, $group);
            }
            $pdo->commit();
            self::loadSettings();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[SettingService::setMany Error] ' . $e->getMessage());
            return false;
        }
    }

    private static function loadSettings(): void
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM fin_system_settings");
            self::$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (\Throwable $e) {
            self::$settings = [];
        }
    }
}
