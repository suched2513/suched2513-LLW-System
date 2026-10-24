<?php
/**
 * includes/system_helpers.php — ศูนย์กลางฟังก์ชันช่วยเหลือระดับระบบ (System Helpers)
 * ====================================================================================
 * 1. มาตรฐานรหัสนักเรียน 5 หลัก (Student ID Normalization)
 * 2. ระบบบันทึกข้อผิดพลาดอัตโนมัติ (Central Error Telemetry)
 * 3. ระบบตรวจสอบสิทธิ์ครูที่ปรึกษาและ JSON Response มาตรฐาน
 */

if (!function_exists('llw_normalize_student_id')) {
    /**
     * แปลงรหัสนักเรียนให้เป็นมาตรฐานเดียวกัน (ตัวเลขล้วน เติม 0 นำหน้าให้ครบ 5 หลัก เช่น 04715)
     */
    function llw_normalize_student_id($sid): string {
        $s = trim((string)$sid);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^\d+$/', $s) && strlen($s) < 5) {
            return str_pad($s, 5, '0', STR_PAD_LEFT);
        }
        return $s;
    }
}

if (!function_exists('llw_student_id_variants')) {
    /**
     * คืนค่ารูปแบบรหัสนักเรียนทั้งแบบเติม 0 (5 หลัก) และไม่เติม 0 เสมอ 2 ค่า [$padded, $unpadded]
     * สำหรับใช้ใน WHERE student_id IN (?, ?) เพื่อให้ MySQL ใช้ Index ได้เต็มประสิทธิภาพ
     */
    function llw_student_id_variants($sid): array {
        $norm = llw_normalize_student_id($sid);
        if ($norm === '') {
            return ['', ''];
        }
        $unpadded = ltrim($norm, '0');
        if ($unpadded === '') {
            $unpadded = '0';
        }
        return [$norm, $unpadded];
    }
}

if (!function_exists('llw_check_advisor_permission')) {
    /**
     * ตรวจสอบสิทธิ์การเข้าถึงห้องเรียนสำหรับครูที่ปรึกษา
     */
    function llw_check_advisor_permission(PDO $pdo, string $classroom, ?int $userId = null, ?string $role = null): bool {
        $role   = $role   ?? ($_SESSION['llw_role'] ?? '');
        $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);

        if (in_array($role, ['super_admin', 'wfh_admin', 'director', 'deputy_director'], true)) {
            return true;
        }
        if ($userId <= 0 || $classroom === '') {
            return false;
        }
        $stmt = $pdo->prepare("SELECT 1 FROM llw_class_advisors WHERE classroom = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$classroom, $userId]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('llw_log_error')) {
    /**
     * บันทึกข้อผิดพลาดลงทั้ง PHP error_log และตาราง llw_system_error_logs
     */
    function llw_log_error(Throwable $e, string $context = '', array $extra = []): void {
        $fileLine = basename($e->getFile()) . ':' . $e->getLine();
        $ctxLabel = $context !== '' ? $context : ($_SERVER['SCRIPT_NAME'] ?? 'unknown');
        error_log("[LLW][{$ctxLabel}] " . $e->getMessage() . " ({$fileLine})");

        try {
            if (!function_exists('getPdo')) {
                return;
            }
            $pdo = getPdo();
            static $tableChecked = null;
            if ($tableChecked === null) {
                $tableChecked = (bool)$pdo->query("SHOW TABLES LIKE 'llw_system_error_logs'")->fetchColumn();
            }
            if (!$tableChecked) {
                return;
            }

            $userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $username = $_SESSION['username'] ?? ($_SESSION['firstname'] ?? null);
            $role     = $_SESSION['llw_role'] ?? null;
            $endpoint = substr($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? ''), 0, 255);
            $method   = substr($_SERVER['REQUEST_METHOD'] ?? 'CLI', 0, 10);
            $ip       = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
            $errMsg   = $e->getMessage();
            if (!empty($extra)) {
                $errMsg .= ' | Extra: ' . json_encode($extra, JSON_UNESCAPED_UNICODE);
            }

            $stmt = $pdo->prepare("
                INSERT INTO llw_system_error_logs
                    (user_id, username, role, endpoint, method, context, error_message, file_line, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $username ? substr((string)$username, 0, 100) : null,
                $role ? substr((string)$role, 0, 50) : null,
                $endpoint,
                $method,
                substr($ctxLabel, 0, 100),
                substr($errMsg, 0, 2000),
                substr($fileLine, 0, 255),
                $ip
            ]);
        } catch (Throwable $ignore) {
            // ห้ามให้การเขียน log ทำให้ระบบหลักล่มซ้ำ
        }
    }
}

if (!function_exists('llw_json_error')) {
    /**
     * ส่ง JSON Error มาตรฐาน พร้อมบันทึก Telemetry อัตโนมัติ
     */
    function llw_json_error(string $userMessage = 'เกิดข้อผิดพลาดในระบบ', ?Throwable $e = null, int $httpCode = 500, string $context = ''): void {
        if ($e !== null) {
            llw_log_error($e, $context);
        }
        if (ob_get_length()) {
            ob_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($httpCode);
        }
        $payload = [
            'status'  => 'error',
            'message' => $userMessage,
        ];
        if ($e !== null && (($_SESSION['llw_role'] ?? '') === 'super_admin')) {
            $payload['debug'] = $e->getMessage() . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']';
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
