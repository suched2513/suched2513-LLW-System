<?php
/**
 * parent_meeting/login.php - หน้าเข้าสู่ระบบสำหรับระบบประชุมผู้ปกครอง
 */
require_once __DIR__ . '/config.php';

// หากเข้าสู่ระบบไว้แล้ว ให้ส่งตัวไปหน้าแดชบอร์ด
if (isset($_SESSION['pm_user_id'])) {
    header('Location: ' . pm_url('dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน';
    } else {
        try {
            // ดึงข้อมูลผู้ใช้จากฐานข้อมูลกลาง (llw_users)
            $llwPdo = getLlwPdo();
            if (!$llwPdo) {
                throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลกลางได้");
            }
            
            $stmt = $llwPdo->prepare("SELECT * FROM llw_users WHERE username = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // การจับคู่สิทธิ์ (Role Mapping)
                $roleMap = [
                    'super_admin' => 'admin',
                    'wfh_admin' => 'executive',
                    'att_teacher' => 'teacher',
                    'wfh_staff' => 'teacher',
                    'cb_admin' => 'teacher',
                    'club_admin' => 'teacher',
                    'bus_admin' => 'teacher',
                    'bus_finance' => 'teacher'
                ];
                $pmRole = $roleMap[$user['role']] ?? 'teacher';
                
                $fullname = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
                if (empty($fullname)) {
                    $fullname = $user['username'];
                }
                
                $pmPdo = getPmPdo();
                // ตรวจสอบหรืออัปเดตผู้ใช้ใน pm_users
                $pmStmt = $pmPdo->prepare("SELECT * FROM pm_users WHERE username = ?");
                $pmStmt->execute([$username]);
                $pmUser = $pmStmt->fetch();
                
                if ($pmUser) {
                    $upd = $pmPdo->prepare("UPDATE pm_users SET fullname = ?, role = ?, password = ? WHERE id = ?");
                    $upd->execute([$fullname, $pmRole, $user['password'], $pmUser['id']]);
                    $pmUserId = $pmUser['id'];
                } else {
                    $ins = $pmPdo->prepare("INSERT INTO pm_users (fullname, username, password, role) VALUES (?, ?, ?, ?)");
                    $ins->execute([$fullname, $username, $user['password'], $pmRole]);
                    $pmUserId = $pmPdo->lastInsertId();
                }
                
                // บันทึก Session กลางของระบบโรงเรียน
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['firstname'] = $user['firstname'];
                $_SESSION['fullname'] = $fullname;
                $_SESSION['llw_role'] = $user['role'];
                $_SESSION['role'] = in_array($user['role'], ['super_admin', 'wfh_admin']) ? 'admin' : 'user';
                
                // ดึงบทบาททั้งหมดของผู้ใช้ (Multi-role support)
                $rolesAll = [$user['role']];
                try {
                    $rs = $llwPdo->prepare("SELECT role FROM llw_user_roles WHERE user_id = ?");
                    $rs->execute([$user['user_id']]);
                    $tmp = $rs->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($tmp)) {
                        $rolesAll = array_values(array_unique(array_merge($rolesAll, $tmp)));
                    }
                } catch (Throwable $e) {}
                $_SESSION['llw_roles'] = $rolesAll;
                
                // หากเป็นครู ให้ดึงรหัสครูที่ปรึกษา (teacher_id) ด้วย
                if (in_array($user['role'], ['att_teacher', 'super_admin', 'club_admin'])) {
                    $t = $llwPdo->prepare("SELECT id, name FROM att_teachers WHERE username = ? LIMIT 1");
                    $t->execute([$username]);
                    $teacher = $t->fetch();
                    if ($teacher) {
                        $_SESSION['teacher_id'] = $teacher['id'];
                        $_SESSION['teacher_name'] = $teacher['name'];
                    }
                }
                
                // อัปเดตเวลาเข้าสู่ระบบหลัก
                $u = $llwPdo->prepare("UPDATE llw_users SET last_login = NOW() WHERE user_id = ?");
                $u->execute([$user['user_id']]);
                
                // บันทึก Session ของระบบประชุมผู้ปกครอง
                $_SESSION['pm_user_id'] = $pmUserId;
                $_SESSION['pm_fullname'] = $fullname;
                $_SESSION['pm_username'] = $username;
                $_SESSION['pm_role'] = $pmRole;
                
                header('Location: ' . pm_url('dashboard.php'));
                exit;
            } else {
                $error = 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (Exception $e) {
            error_log('[Parent Meeting] Login Error: ' . $e->getMessage());
            $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | ระบบรายงานการประชุมผู้ปกครอง</title>
    <!-- Google Fonts: Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= pm_url('assets/css/style.css') ?>">
</head>
<body>
    <div class="glass-container">
        <div class="glass-card">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle p-3 mb-3 shadow">
                    <i class="bi bi-people-fill fs-2"></i>
                </div>
                <h3 class="font-black text-dark-blue">LLW Parent Meeting</h3>
                <p class="text-muted text-xs uppercase tracking-wider font-bold mb-0">ระบบบันทึกรายงานการประชุมผู้ปกครอง</p>
                <small class="text-muted">โรงเรียนละลมวิทยา</small>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger rounded-3 text-sm d-flex align-items-center py-2 px-3 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div><?= esc($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" autocomplete="off">
                <div class="mb-3">
                    <label for="username" class="form-label text-xs font-black uppercase text-muted tracking-wider">ชื่อผู้ใช้งาน</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" class="form-control bg-light border-start-0 rounded-end-3 py-2 text-sm focus:ring-0 outline-none" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label text-xs font-black uppercase text-muted tracking-wider">รหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="bi bi-lock text-muted"></i></span>
                        <input type="password" class="form-control bg-light border-start-0 rounded-end-3 py-2 text-sm focus:ring-0 outline-none" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full py-2.5 rounded-3 font-bold shadow-lg shadow-blue-200/50 hover:bg-primary-dark transition-all d-flex align-items-center justify-content-center">
                    <i class="bi bi-box-arrow-in-right me-2 fs-5"></i> เข้าสู่ระบบ
                </button>
            </form>

            <div class="text-center mt-4 border-top pt-3 border-white/50">
                <p class="mb-1 text-xs text-muted">กรุณาเข้าสู่ระบบด้วยบัญชีผู้ใช้ระบบโรงเรียน (LLW)</p>
                <a href="/index.php" class="d-inline-block text-xs mt-3 text-decoration-none text-primary">
                    <i class="bi bi-arrow-left"></i> กลับหน้าหลักโรงเรียน
                </a>
            </div>
        </div>
    </div>
</body>
</html>
