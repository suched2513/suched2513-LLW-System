<?php
/**
 * ระบบ WFH:LLW — ตั้งค่าการเชื่อมต่อฐานข้อมูล
 * ดึงค่าทั้งหมดจากศูนย์กลาง: config/database.php
 */
require_once __DIR__ . '/config/database.php';

// --- Automated Base Path Detection ---
// This calculates the relative path from the Apache DocumentRoot to the project folder.
// Works for local (e.g., /llw or /htdocs/llw) and production (e.g., /) automatically.
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$appDir  = str_replace('\\', '/', realpath(__DIR__));
$base_path = str_replace($docRoot, '', $appDir);
$base_path = '/' . trim($base_path, '/');
if ($base_path === '/') $base_path = '';

// Global connection
$conn = getWfhConn();

// ── CSRF Helpers ──────────────────────────────────────────────────────────────
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'CSRF token invalid']));
    }
}

// ── Role Helpers (Multi-Role 1:M) ─────────────────────────────────────────────
// llw_user_roles เก็บ role ทั้งหมดของผู้ใช้แต่ละคน (ดู migration 2026_05_19_000002)
// llw_users.role ยังคงไว้เป็น "primary role" เพื่อ backward-compat กับโค้ดเดิม

function llw_get_user_roles(int $userId): array {
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];
    try {
        $stmt = getPdo()->prepare("SELECT role FROM llw_user_roles WHERE user_id = ? ORDER BY is_primary DESC, role ASC");
        $stmt->execute([$userId]);
        return $cache[$userId] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

function llw_has_role(string $role): bool {
    if (($_SESSION['llw_role'] ?? '') === $role) return true;
    return in_array($role, $_SESSION['llw_roles'] ?? [], true);
}

function llw_has_any_role(array $roles): bool {
    if (in_array($_SESSION['llw_role'] ?? '', $roles, true)) return true;
    return (bool) array_intersect($roles, $_SESSION['llw_roles'] ?? []);
}

