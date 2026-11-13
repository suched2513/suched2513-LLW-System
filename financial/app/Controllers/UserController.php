<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditTrailService;
use PDO;

/**
 * Class UserController
 * จัดการบัญชีผู้ใช้งาน สิทธิ์ บทบาท และการระงับการใช้งาน
 */
class UserController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('users', 'view');
        $pdo = Database::getConnection();

        $search = trim((string)$this->request->query('search', ''));
        $roleFilter = trim((string)$this->request->query('role', ''));
        $statusFilter = $this->request->query('status', '');

        $sql = "
            SELECT u.*, 
                   GROUP_CONCAT(r.role_name SEPARATOR ', ') as role_names,
                   GROUP_CONCAT(r.role_code SEPARATOR ',') as role_codes
            FROM fin_users u
            LEFT JOIN fin_user_roles ur ON u.id = ur.user_id
            LEFT JOIN fin_roles r ON ur.role_id = r.id
            WHERE 1=1
        ";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if ($roleFilter !== '') {
            $sql .= " AND u.id IN (SELECT user_id FROM fin_user_roles ur2 JOIN fin_roles r2 ON ur2.role_id = r2.id WHERE r2.role_code = ?)";
            $params[] = $roleFilter;
        }

        if ($statusFilter !== '') {
            $sql .= " AND u.is_active = ?";
            $params[] = (int)$statusFilter;
        }

        $sql .= " GROUP BY u.id ORDER BY u.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        // Get all roles for filter & modal
        $roles = $pdo->query("SELECT * FROM fin_roles ORDER BY id ASC")->fetchAll();

        $this->render('users/index', [
            'pageTitle'    => 'จัดการผู้ใช้งานและกำหนดสิทธิ์',
            'users'        => $users,
            'roles'        => $roles,
            'search'       => $search,
            'roleFilter'   => $roleFilter,
            'statusFilter' => $statusFilter
        ]);
    }

    public function create(): void
    {
        $this->checkPermission('users', 'create');
        $pdo = Database::getConnection();
        $roles = $pdo->query("SELECT * FROM fin_roles ORDER BY id ASC")->fetchAll();

        // ครู/บุคลากรจากระบบกลางที่ยังไม่ได้ผูกกับบัญชีการเงิน
        $centralUsers = [];
        try {
            $centralUsers = $pdo->query("
                SELECT u.user_id, u.username, u.firstname, u.lastname
                FROM llw_users u
                WHERE u.status = 'active'
                  AND u.user_id NOT IN (SELECT llw_user_id FROM fin_users WHERE llw_user_id IS NOT NULL)
                ORDER BY u.firstname, u.lastname
            ")->fetchAll();
        } catch (\Throwable $e) {
            error_log('[UserController::create] ' . $e->getMessage());
        }

        $this->render('users/form', [
            'pageTitle' => 'เพิ่มผู้ใช้งานใหม่',
            'user'      => null,
            'userRoles' => [],
            'roles'     => $roles,
            'centralUsers' => $centralUsers
        ]);
    }

    public function store(): void
    {
        $this->checkPermission('users', 'create');
        $pdo = Database::getConnection();

        $llwUserId = (int)$this->request->input('llw_user_id', 0);
        $selectedRoles = (array)$this->request->input('roles', []);

        // กรณีเลือกจากระบบกลาง: ดึงชื่อ/username จาก llw_users และไม่ใช้รหัสผ่านของระบบการเงิน
        if ($llwUserId > 0) {
            $stmtC = $pdo->prepare("SELECT user_id, username, firstname, lastname FROM llw_users WHERE user_id = ? AND status = 'active'");
            $stmtC->execute([$llwUserId]);
            $central = $stmtC->fetch();
            if (!$central) {
                $this->setFlash('danger', 'ไม่พบผู้ใช้ในระบบกลาง หรือบัญชีถูกระงับ');
                $this->redirect('/users/create');
            }
            $stmtDup = $pdo->prepare("SELECT id FROM fin_users WHERE llw_user_id = ? OR username = ?");
            $stmtDup->execute([$llwUserId, $central['username']]);
            if ($stmtDup->fetch()) {
                $this->setFlash('danger', 'ผู้ใช้นี้มีบัญชีในระบบการเงินแล้ว');
                $this->redirect('/users/create');
            }

            $username = $central['username'];
            $fullName = trim($central['firstname'] . ' ' . $central['lastname']);
            $email = trim((string)$this->request->input('email', ''));
            // รหัสผ่านสุ่มที่ไม่มีใครทราบ: ผู้ใช้เข้าผ่าน SSO จากระบบกลางเท่านั้น
            $password = bin2hex(random_bytes(16));
        } else {
            $username = trim((string)$this->request->input('username', ''));
            $password = (string)$this->request->input('password', '');
            $fullName = trim((string)$this->request->input('full_name', ''));
            $email = trim((string)$this->request->input('email', ''));
        }

        if (empty($username) || empty($password) || empty($fullName)) {
            $this->setFlash('danger', 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
            $this->redirect('/users/create');
        }

        // Check duplicate username
        $stmt = $pdo->prepare("SELECT id FROM fin_users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $this->setFlash('danger', "ชื่อผู้ใช้ '{$username}' มีอยู่ในระบบแล้ว");
            $this->redirect('/users/create');
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO fin_users (username, password_hash, full_name, email, llw_user_id, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
            $stmtInsert->execute([$username, $passwordHash, $fullName, $email, $llwUserId > 0 ? $llwUserId : null]);
            $userId = (int)$pdo->lastInsertId();

            if (!empty($selectedRoles)) {
                $stmtRole = $pdo->prepare("INSERT INTO fin_user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($selectedRoles as $roleId) {
                    $stmtRole->execute([$userId, (int)$roleId]);
                }
            }

            $pdo->commit();
            AuditTrailService::log('users', 'create', 'fin_users', $userId, null, ['username' => $username, 'full_name' => $fullName], "เพิ่มผู้ใช้ใหม่: {$username}");

            $this->setFlash('success', "เพิ่มผู้ใช้งาน '{$fullName}' สำเร็จแล้ว");
            $this->redirect('/users');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->setFlash('danger', 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage());
            $this->redirect('/users/create');
        }
    }

    public function edit(string $id): void
    {
        $this->checkPermission('users', 'edit');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM fin_users WHERE id = ?");
        $stmt->execute([(int)$id]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->setFlash('danger', 'ไม่พบผู้ใช้งานที่ระบุ');
            $this->redirect('/users');
        }

        $stmtRoles = $pdo->prepare("SELECT role_id FROM fin_user_roles WHERE user_id = ?");
        $stmtRoles->execute([(int)$id]);
        $userRoles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

        $roles = $pdo->query("SELECT * FROM fin_roles ORDER BY id ASC")->fetchAll();

        // ครู/บุคลากรจากระบบกลางที่ยังไม่ถูกผูก หรือผูกกับบัญชีนี้อยู่แล้ว
        $centralUsers = [];
        try {
            $stmtC = $pdo->prepare("
                SELECT u.user_id, u.username, u.firstname, u.lastname
                FROM llw_users u
                WHERE u.status = 'active'
                  AND (
                      u.user_id NOT IN (SELECT llw_user_id FROM fin_users WHERE llw_user_id IS NOT NULL)
                      OR u.user_id = ?
                  )
                ORDER BY u.firstname, u.lastname
            ");
            $stmtC->execute([(int)($user['llw_user_id'] ?? 0)]);
            $centralUsers = $stmtC->fetchAll();
        } catch (\Throwable $e) {
            error_log('[UserController::edit] ' . $e->getMessage());
        }

        $this->render('users/form', [
            'pageTitle'    => "แก้ไขผู้ใช้งาน: {$user['full_name']}",
            'user'         => $user,
            'userRoles'    => $userRoles,
            'roles'        => $roles,
            'centralUsers' => $centralUsers
        ]);
    }

    public function update(string $id): void
    {
        $this->checkPermission('users', 'edit');
        $pdo = Database::getConnection();
        $userId = (int)$id;

        $username = trim((string)$this->request->input('username', ''));
        $fullName = trim((string)$this->request->input('full_name', ''));
        $email = trim((string)$this->request->input('email', ''));
        $password = (string)$this->request->input('password', '');
        $llwUserId = (int)$this->request->input('llw_user_id', 0);
        $selectedRoles = (array)$this->request->input('roles', []);

        $stmt = $pdo->prepare("SELECT * FROM fin_users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldUser = $stmt->fetch();
        if (!$oldUser) {
            $this->setFlash('danger', 'ไม่พบผู้ใช้ที่ต้องการแก้ไข');
            $this->redirect('/users');
        }

        if ($username === '' || $fullName === '') {
            $this->setFlash('danger', 'กรุณากรอกชื่อผู้ใช้งานและชื่อ-นามสกุลให้ครบถ้วน');
            $this->redirect("/users/edit/{$userId}");
        }

        // ตรวจสอบชื่อผู้ใช้งานซ้ำกับบัญชีอื่น
        $stmtDup = $pdo->prepare("SELECT id FROM fin_users WHERE username = ? AND id != ?");
        $stmtDup->execute([$username, $userId]);
        if ($stmtDup->fetch()) {
            $this->setFlash('danger', "ชื่อผู้ใช้ '{$username}' มีอยู่ในระบบแล้ว");
            $this->redirect("/users/edit/{$userId}");
        }

        // ตรวจสอบการผูก llw_user_id ซ้ำกับบัญชีอื่น
        $newLlwUserId = $llwUserId > 0 ? $llwUserId : null;
        if ($newLlwUserId !== null) {
            $stmtDupLlw = $pdo->prepare("SELECT id FROM fin_users WHERE llw_user_id = ? AND id != ?");
            $stmtDupLlw->execute([$newLlwUserId, $userId]);
            if ($stmtDupLlw->fetch()) {
                $this->setFlash('danger', 'ผู้ใช้ระบบกลางที่เลือกถูกผูกกับบัญชีอื่นอยู่แล้ว');
                $this->redirect("/users/edit/{$userId}");
            }
        }

        $pdo->beginTransaction();
        try {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtUp = $pdo->prepare("UPDATE fin_users SET username = ?, full_name = ?, email = ?, llw_user_id = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmtUp->execute([$username, $fullName, $email, $newLlwUserId, $hash, $userId]);
            } else {
                $stmtUp = $pdo->prepare("UPDATE fin_users SET username = ?, full_name = ?, email = ?, llw_user_id = ?, updated_at = NOW() WHERE id = ?");
                $stmtUp->execute([$username, $fullName, $email, $newLlwUserId, $userId]);
            }

            // Sync roles
            $pdo->prepare("DELETE FROM fin_user_roles WHERE user_id = ?")->execute([$userId]);
            if (!empty($selectedRoles)) {
                $stmtRole = $pdo->prepare("INSERT INTO fin_user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($selectedRoles as $roleId) {
                    $stmtRole->execute([$userId, (int)$roleId]);
                }
            }

            $pdo->commit();

            // อัปเดต session หากแก้ไขบัญชีของตัวเองที่กำลังล็อกอินอยู่
            if (isset($_SESSION['fin_user']['id']) && (int)$_SESSION['fin_user']['id'] === $userId) {
                $_SESSION['fin_user']['username'] = $username;
                $_SESSION['fin_user']['full_name'] = $fullName;
                $_SESSION['fin_user']['email'] = $email;
            }

            AuditTrailService::log('users', 'update', 'fin_users', $userId, $oldUser, [
                'username' => $username,
                'full_name' => $fullName,
                'email' => $email,
                'llw_user_id' => $newLlwUserId,
            ], "แก้ไขข้อมูลผู้ใช้ {$oldUser['username']}");

            $this->setFlash('success', "ปรับปรุงข้อมูลผู้ใช้ '{$fullName}' เรียบร้อยแล้ว");
            $this->redirect('/users');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->setFlash('danger', 'เกิดข้อผิดพลาดในการแก้ไข: ' . $e->getMessage());
            $this->redirect("/users/edit/{$userId}");
        }
    }

    public function toggleStatus(): void
    {
        $this->checkPermission('users', 'edit');
        $userId = (int)$this->request->input('user_id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id, username, is_active FROM fin_users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();

        if ($u) {
            $newStatus = $u['is_active'] ? 0 : 1;
            $stmtUp = $pdo->prepare("UPDATE fin_users SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmtUp->execute([$newStatus, $userId]);

            $actionText = $newStatus ? 'เปิดใช้งาน' : 'ระงับการใช้งาน';
            AuditTrailService::log('users', 'toggle_status', 'fin_users', $userId, ['is_active' => $u['is_active']], ['is_active' => $newStatus], "{$actionText} บัญชี {$u['username']}");

            $this->setFlash('success', "{$actionText} บัญชีผู้ใช้ {$u['username']} เรียบร้อยแล้ว");
        }

        $this->redirect('/users');
    }
}
