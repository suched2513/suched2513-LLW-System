<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Class AuthService
 * ให้บริการการยืนยันตัวตน, จัดการสิทธิ์การใช้งาน (RBAC), CSRF Token และ Session
 */
class AuthService
{
    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    public static function login(string $username, string $password): array
    {
        self::startSession();
        $pdo = Database::getConnection();

        // ค้นหาผู้ใช้งาน
        $stmt = $pdo->prepare("SELECT * FROM fin_users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            AuditTrailService::log('auth', 'login_failed', 'fin_users', null, null, ['username' => $username], 'ชื่อผู้ใช้ไม่ถูกต้อง');
            return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }

        if (!$user['is_active']) {
            AuditTrailService::log('auth', 'login_blocked', 'fin_users', (int)$user['id'], null, null, 'บัญชีถูกระงับการใช้งาน');
            return ['success' => false, 'message' => 'บัญชีผู้ใช้งานนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            AuditTrailService::log('auth', 'login_failed', 'fin_users', (int)$user['id'], null, ['username' => $username], 'รหัสผ่านไม่ถูกต้อง');
            return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }

        // ดึงบทบาทของผู้ใช้
        $stmtRoles = $pdo->prepare("
            SELECT r.id, r.role_code, r.role_name
            FROM fin_user_roles ur
            JOIN fin_roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
        $stmtRoles->execute([$user['id']]);
        $roles = $stmtRoles->fetchAll();
        $roleCodes = array_column($roles, 'role_code');

        if ($user['username'] === 'admin' && empty($roleCodes)) {
            $roleCodes = ['admin'];
            $roles = [['id' => 1, 'role_code' => 'admin', 'role_name' => 'ผู้ดูแลระบบ (System Administrator)']];
        }

        // ดึงสิทธิ์ทั้งหมดของผู้ใช้ตามบทบาท (รวมเป็น Set ของ module:action)
        $stmtPerms = $pdo->prepare("
            SELECT DISTINCT CONCAT(p.module, ':', p.action) as perm_key
            FROM fin_user_roles ur
            JOIN fin_role_permissions rp ON ur.role_id = rp.role_id
            JOIN fin_permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ?
        ");
        $stmtPerms->execute([$user['id']]);
        $permissions = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);

        // ดึงปีงบประมาณปัจจุบัน
        $stmtFy = $pdo->query("SELECT * FROM fin_fiscal_years WHERE is_current = 1 LIMIT 1");
        $currentFy = $stmtFy->fetch();
        if (!$currentFy) {
            $stmtFy = $pdo->query("SELECT * FROM fin_fiscal_years ORDER BY year_be DESC LIMIT 1");
            $currentFy = $stmtFy->fetch();
        }

        // เก็บข้อมูลลงใน Session
        $_SESSION['fin_user'] = [
            'id'          => (int)$user['id'],
            'username'    => $user['username'],
            'full_name'   => $user['full_name'],
            'email'       => $user['email'],
            'roles'       => $roleCodes,
            'role_names'  => array_column($roles, 'role_name'),
            'permissions' => $permissions,
        ];

        $_SESSION['fin_active_fy'] = $currentFy ?: [
            'id' => null,
            'year_be' => thai_fiscal_year(),
            'year_ce' => thai_fiscal_year() - 543,
            'is_current' => 1
        ];

        // บันทึกประวัติการเข้าสู่ระบบ
        AuditTrailService::log('auth', 'login_success', 'fin_users', (int)$user['id'], null, null, 'เข้าสู่ระบบสำเร็จ');

        return ['success' => true, 'message' => 'เข้าสู่ระบบสำเร็จ'];
    }

    public static function logout(): void
    {
        self::startSession();
        $user = self::getCurrentUser();
        if ($user) {
            AuditTrailService::log('auth', 'logout', 'fin_users', (int)$user['id'], null, null, 'ออกจากระบบ');
        }

        unset($_SESSION['fin_user']);
        unset($_SESSION['fin_active_fy']);
        unset($_SESSION['fin_csrf_token']);
        session_destroy();
    }

    public static function getCurrentUser(): ?array
    {
        self::startSession();
        $user = $_SESSION['fin_user'] ?? null;
        if ($user && ($user['username'] ?? '') === 'admin' && empty($user['roles'])) {
            $_SESSION['fin_user']['roles'] = ['admin'];
            $_SESSION['fin_user']['role_names'] = ['ผู้ดูแลระบบ (System Administrator)'];
            $user = $_SESSION['fin_user'];
        }
        return $user;
    }

    public static function getActiveFiscalYear(): ?array
    {
        self::startSession();
        if (!isset($_SESSION['fin_active_fy'])) {
            $pdo = Database::getConnection();
            $stmtFy = $pdo->query("SELECT * FROM fin_fiscal_years WHERE is_current = 1 LIMIT 1");
            $currentFy = $stmtFy->fetch();
            $_SESSION['fin_active_fy'] = $currentFy ?: [
                'id' => null,
                'year_be' => thai_fiscal_year(),
                'year_ce' => thai_fiscal_year() - 543,
                'is_current' => 1
            ];
        }
        return $_SESSION['fin_active_fy'];
    }

    public static function switchFiscalYear(int $fyId): bool
    {
        self::startSession();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM fin_fiscal_years WHERE id = ?");
        $stmt->execute([$fyId]);
        $fy = $stmt->fetch();
        if ($fy) {
            $_SESSION['fin_active_fy'] = $fy;
            return true;
        }
        return false;
    }

    public static function check(): bool
    {
        return self::getCurrentUser() !== null;
    }

    public static function hasRole(string|array $roles): bool
    {
        $user = self::getCurrentUser();
        if (!$user) return false;

        $userRoles = $user['roles'] ?? [];
        if (is_string($roles)) {
            $roles = [$roles];
        }

        foreach ($roles as $r) {
            if (in_array($r, $userRoles, true)) {
                return true;
            }
        }
        return false;
    }

    public static function hasPermission(string $module, string $action): bool
    {
        $user = self::getCurrentUser();
        if (!$user) return false;

        $moduleAliases = [
            'reports'             => 'report',
            'receipts'            => 'receipt',
            'disbursements'       => 'payment',
            'payments'            => 'payment',
            'advances'            => 'advance',
            'accounts'            => 'accounting',
            'banks'               => 'bank',
            'bank_reconciliation' => 'bank',
            'special_programs'    => 'special_program',
            'users'               => 'setting',
            'fiscal_years'        => 'setting',
            'audit_logs'          => 'audit_log',
            'settings'            => 'setting',
            'budgets'             => 'budget',
        ];
        $canonicalModule = $moduleAliases[$module] ?? $module;

        $userRoles = $user['roles'] ?? [];
        $isAdmin = in_array('admin', $userRoles, true) || (($user['username'] ?? '') === 'admin');

        // ผู้ดูแลระบบ (admin) สามารถดูข้อมูลและจัดการระบบได้ทุกโมดูล
        // ยกเว้น "การอนุมัติการเงิน" (approve) ที่ต้องเป็นผู้อนุมัติตามสายการบังคับบัญชา (Separation of Duties)
        if ($isAdmin) {
            if ($action === 'approve') {
                $userPerms = $user['permissions'] ?? [];
                return in_array("{$canonicalModule}:{$action}", $userPerms, true) || in_array("{$module}:{$action}", $userPerms, true);
            }
            return true;
        }

        $userPerms = $user['permissions'] ?? [];
        $key1 = "{$canonicalModule}:{$action}";
        $key2 = "{$module}:{$action}";

        return in_array($key1, $userPerms, true) || in_array($key2, $userPerms, true);
    }

    public static function getCsrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['fin_csrf_token'])) {
            $_SESSION['fin_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['fin_csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool
    {
        self::startSession();
        if (empty($_SESSION['fin_csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['fin_csrf_token'], $token);
    }
}
