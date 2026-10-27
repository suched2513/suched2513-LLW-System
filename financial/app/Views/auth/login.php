<?php
$schoolName = \App\Services\SettingService::get('school_name', 'โรงเรียนละลมวิทยา');
$affiliation = \App\Services\SettingService::get('affiliation', 'สังกัดสำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร');
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบบริหารการเงินและบัญชีสถานศึกษา</title>
    <!-- Google Fonts: Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #0B2545 0%, #134074 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            border: 1px solid rgba(197, 155, 39, 0.3);
        }
        .login-header {
            background-color: #0B2545;
            color: #FFFFFF;
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
            position: relative;
        }
        .login-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #C59B27;
        }
        .btn-gold {
            background-color: #C59B27;
            color: #FFFFFF;
            font-weight: 600;
            border: none;
            padding: 0.65rem 1.25rem;
            transition: all 0.2s;
        }
        .btn-gold:hover {
            background-color: #D4A936;
            color: #FFFFFF;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="mb-2" style="font-size: 2.2rem; color: #C59B27;">
            <i class="bi bi-bank2"></i>
        </div>
        <h5 class="fw-bold mb-1">ระบบบริหารการเงินและบัญชี</h5>
        <div class="text-white-50 small"><?= e($schoolName) ?></div>
        <div class="text-white-50" style="font-size: 0.72rem;"><?= e($affiliation) ?></div>
    </div>

    <div class="p-4">
        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show small" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $baseUrl ?>/login">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

            <div class="mb-3">
                <label for="username" class="form-label fw-semibold text-secondary small">ชื่อผู้ใช้งาน (Username)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" required autofocus placeholder="กรอกชื่อผู้ใช้">
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold text-secondary small">รหัสผ่าน (Password)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="กรอกรหัสผ่าน">
                </div>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-gold shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                </button>
            </div>
        </form>

        <div class="border-top pt-3 mt-4">
            <div class="small fw-semibold text-muted mb-2"><i class="bi bi-info-circle me-1"></i> บัญชีทดสอบเริ่มต้น (Password: <code>Password123!</code>):</div>
            <div class="small text-muted d-flex flex-wrap gap-1">
                <span class="badge bg-light text-dark border">admin</span>
                <span class="badge bg-light text-dark border">finance</span>
                <span class="badge bg-light text-dark border">accountant</span>
                <span class="badge bg-light text-dark border">inventory</span>
                <span class="badge bg-light text-dark border">teacher1</span>
                <span class="badge bg-light text-dark border">director</span>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
