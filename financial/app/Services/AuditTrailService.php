<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Class AuditTrailService
 * บันทึกประวัติการใช้งาน (Audit Log / Activity Trail) อย่างละเอียดและปลอดภัย
 */
class AuditTrailService
{
    private static array $sensitiveKeys = [
        'password', 'password_hash', 'password_confirmation', 'token', 'csrf_token', 'secret'
    ];

    public static function log(
        string $module,
        string $action,
        ?string $tableName = null,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $notes = null,
        string $status = 'success'
    ): void {
        try {
            $pdo = Database::getConnection();

            $user = AuthService::getCurrentUser();
            $userId = $user['id'] ?? null;

            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown', 0, 500);

            $oldJson = $oldValues !== null ? json_encode(self::maskSensitive($oldValues), JSON_UNESCAPED_UNICODE) : null;
            $newJson = $newValues !== null ? json_encode(self::maskSensitive($newValues), JSON_UNESCAPED_UNICODE) : null;

            $stmt = $pdo->prepare("
                INSERT INTO fin_audit_logs 
                (user_id, action, module, table_name, record_id, old_values, new_values, ip_address, user_agent, notes, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                $action,
                $module,
                $tableName,
                $recordId,
                $oldJson,
                $newJson,
                $ipAddress,
                $userAgent,
                $notes,
                $status
            ]);
        } catch (\Throwable $e) {
            // Audit log failure should never crash the main application, but should be logged to error_log
            error_log('[AuditTrailService Error] ' . $e->getMessage());
        }
    }

    private static function maskSensitive(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $val) {
            if (in_array(strtolower($key), self::$sensitiveKeys, true)) {
                $sanitized[$key] = '********';
            } elseif (is_array($val)) {
                $sanitized[$key] = self::maskSensitive($val);
            } else {
                $sanitized[$key] = $val;
            }
        }
        return $sanitized;
    }
}
