<?php
namespace PP5\App\Services;

use PDO;
use Throwable;

/**
 * ActivityLogger — บริการบันทึกประวัติการแก้ไขข้อมูล คะแนน เกรด และการแจ้งเตือนในระบบ ปพ.5
 * (เทียบเท่า spatie/laravel-activitylog + Database Notifications)
 */
class ActivityLogger
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * บันทึกประวัติการกระทำ (ใคร เมื่อไร ค่าเดิม ค่าใหม่ IP Address)
     */
    public function log(
        string $action,
        string $description,
        string $targetType = '',
        ?int $targetId = null,
        ?int $courseId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $userName = trim((string)($_SESSION['fullname'] ?? ($_SESSION['firstname'] ?? ($_SESSION['username'] ?? 'System'))));
            $ip       = substr((string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45);

            $stmt = $this->pdo->prepare("
                INSERT INTO `pp5_activity_logs` (
                    `user_id`, `user_name`, `action`, `target_type`, `target_id`,
                    `course_id`, `description`, `old_values`, `new_values`, `ip_address`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $userName,
                $action,
                $targetType,
                $targetId,
                $courseId,
                mb_substr($description, 0, 500, 'UTF-8'),
                $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                $ip,
            ]);
        } catch (Throwable $e) {
            error_log('[PP5 ActivityLogger] ' . $e->getMessage());
        }
    }

    /**
     * ส่งแจ้งเตือนในระบบให้ผู้ใช้ (เช่น มี ปพ.5 รออนุมัติ หรือถูกตีกลับ)
     */
    public function notify(
        int $recipientUserId,
        string $title,
        string $message,
        string $type = 'info',
        string $link = '/pp5/index.php',
        ?int $courseId = null
    ): void {
        if ($recipientUserId <= 0) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO `pp5_notifications` (`user_id`, `course_id`, `title`, `message`, `type`, `link`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$recipientUserId, $courseId, $title, $message, $type, $link]);
        } catch (Throwable $e) {
            error_log('[PP5 Notify] ' . $e->getMessage());
        }
    }
}
