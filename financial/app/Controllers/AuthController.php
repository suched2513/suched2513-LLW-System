<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuthService;
use App\Services\AuditTrailService;

/**
 * Class AuthController
 * จัดการหน้าเข้าสู่ระบบ ออกจากระบบ และโปรไฟล์ผู้ใช้งาน
 */
class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (AuthService::check()) {
            $this->redirect('/dashboard');
        }

        $this->render('auth/login', [
            'pageTitle' => 'เข้าสู่ระบบ - ระบบบริหารการเงินและบัญชีสถานศึกษา'
        ], null); // No layout
    }

    public function login(): void
    {
        $username = (string)$this->request->input('username', '');
        $password = (string)$this->request->input('password', '');

        if (empty($username) || empty($password)) {
            $this->setFlash('danger', 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
            $this->redirect('/login');
        }

        $result = AuthService::login($username, $password);

        if ($result['success']) {
            $this->setFlash('success', 'เข้าสู่ระบบเรียบร้อย ยินดีต้อนรับเข้าสู่ระบบการเงินและบัญชี');
            $this->redirect('/dashboard');
        } else {
            $this->setFlash('danger', $result['message']);
            $this->redirect('/login');
        }
    }

    public function logout(): void
    {
        AuthService::logout();
        $this->setFlash('info', 'ออกจากระบบเรียบร้อยแล้ว');
        $this->redirect('/login');
    }

    public function profile(): void
    {
        $user = $this->user();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id, username, full_name, email, is_active, created_at, updated_at FROM fin_users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();

        // Get user roles
        $stmtRoles = $pdo->prepare("
            SELECT r.role_code, r.role_name, r.description
            FROM fin_user_roles ur
            JOIN fin_roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
        $stmtRoles->execute([$user['id']]);
        $roles = $stmtRoles->fetchAll();

        $this->render('auth/profile', [
            'pageTitle' => 'ข้อมูลส่วนตัวและสิทธิ์การใช้งาน',
            'profile'   => $profile,
            'roles'     => $roles
        ]);
    }

    public function updatePassword(): void
    {
        $currentPassword = (string)$this->request->input('current_password', '');
        $newPassword = (string)$this->request->input('new_password', '');
        $confirmPassword = (string)$this->request->input('confirm_password', '');

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->setFlash('danger', 'กรุณากรอกข้อมูลให้ครบถ้วน');
            $this->redirect('/profile');
        }

        if (strlen($newPassword) < 8) {
            $this->setFlash('danger', 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร');
            $this->redirect('/profile');
        }

        if ($newPassword !== $confirmPassword) {
            $this->setFlash('danger', 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน');
            $this->redirect('/profile');
        }

        $pdo = Database::getConnection();
        $user = $this->user();

        $stmt = $pdo->prepare("SELECT password_hash FROM fin_users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPassword, $hash)) {
            $this->setFlash('danger', 'รหัสผ่านเดิมไม่ถูกต้อง');
            $this->redirect('/profile');
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmtUpdate = $pdo->prepare("UPDATE fin_users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$newHash, $user['id']]);

        AuditTrailService::log('auth', 'password_change', 'fin_users', (int)$user['id'], null, null, 'เปลี่ยนรหัสผ่านสำเร็จ');

        $this->setFlash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        $this->redirect('/profile');
    }
}
