<?php
session_start();
require_once 'config.php';

// Unified Auth Redirection
if (isset($_SESSION['llw_role'])) {
    if (in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
        header("Location: admin/dashboard.php"); exit();
    } elseif ($_SESSION['llw_role'] === 'wfh_staff') {
        header("Location: user/dashboard.php"); exit();
    }
}

// Fallback to legacy WFH session
if (isset($_SESSION['user_id'])) {
    header($_SESSION['role'] === 'admin'
        ? "Location: admin/dashboard.php"
        : "Location: user/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ WFH — โรงเรียนละลมวิทยา</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Sarabun', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0d1b3e 0%, #0a1628 50%, #0d2b1e 100%);
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 30% 40%, rgba(0,230,118,0.12) 0%, transparent 60%),
                        radial-gradient(ellipse at 80% 70%, rgba(68,138,255,0.08) 0%, transparent 60%);
        }

        .back-link {
            position: fixed;
            top: 20px; left: 20px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
            z-index: 10;
        }
        .back-link:hover { color: #00e676; }

        .login-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px;
            padding: 44px 36px;
            width: 100%;
            max-width: 400px;
            margin: 20px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
            animation: up 0.5s ease-out;
            position: relative;
            z-index: 1;
        }
        @keyframes up { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }

        .card-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .logo-ring {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00e676, #1de9b6);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: 800; color: #000;
            margin: 0 auto 14px;
            box-shadow: 0 0 0 4px rgba(0,230,118,0.2);
        }
        .card-title { font-size: 1.3rem; font-weight: 700; color: #fff; }
        .card-sub   { font-size: 0.83rem; color: rgba(255,255,255,0.5); margin-top: 4px; }

        .alert-error {
            background: rgba(255,82,82,0.12);
            border: 1px solid rgba(255,82,82,0.3);
            color: #ff8a80;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 8px; color: rgba(255,255,255,0.7); }
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.35); font-size: 0.9rem; }
        .form-input {
            width: 100%;
            padding: 13px 16px 13px 44px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.07);
            color: #fff;
            font-family: 'Sarabun', sans-serif;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }
        .form-input::placeholder { color: rgba(255,255,255,0.3); }
        .form-input:focus {
            border-color: #00e676;
            background: rgba(0,230,118,0.05);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #00e676, #1de9b6);
            color: #000;
            font-family: 'Sarabun', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 8px;
            box-shadow: 0 4px 20px rgba(0,230,118,0.3);
        }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,230,118,0.4); }
        .login-btn:active { transform: translateY(0); }

        .footer-note { text-align: center; margin-top: 22px; font-size: 0.78rem; color: rgba(255,255,255,0.35); }
        .footer-note a { color: #00e676; text-decoration: none; }
    </style>
</head>
<body>

<a href="index.php" class="back-link" id="back-btn">
    <i class="fa-solid fa-arrow-left"></i> กลับหน้าหลัก
</a>

<div class="login-card">
    <div class="card-logo">
        <div class="logo-ring"><i class="fa-solid fa-user-clock"></i></div>
        <div class="card-title">ระบบลงเวลาปฏิบัติงาน</div>
        <div class="card-sub">WFH:LLW &mdash; โรงเรียนละลมวิทยา</div>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert-error">
            <i class="fa-solid fa-circle-exclamation" style="margin-right:6px"></i>
            Username หรือ Password ไม่ถูกต้อง
        </div>
    <?php endif; ?>

    <form action="auth.php" method="POST">
        <div class="form-group">
            <label class="form-label" for="username">ชื่อผู้ใช้</label>
            <div class="input-wrap">
                <i class="fa-solid fa-user"></i>
                <input type="text" class="form-input" id="username" name="username"
                       placeholder="รหัสผู้ใช้งาน" required autocomplete="username">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="password">รหัสผ่าน</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input type="password" class="form-input" id="password" name="password"
                       placeholder="รหัสผ่าน" required autocomplete="current-password">
            </div>
        </div>
        <button type="submit" class="login-btn" id="submit-btn">
            <i class="fa-solid fa-right-to-bracket" style="margin-right:8px"></i>เข้าสู่ระบบ
        </button>
    </form>

    <div class="footer-note">
        ลืมรหัสผ่าน? ติดต่อ <a href="#">ผู้ดูแลระบบ</a>
    </div>
</div>

</body>
</html>
