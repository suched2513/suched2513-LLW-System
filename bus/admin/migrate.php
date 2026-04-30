<?php
/**
 * One-time migration runner for Bus System tables.
 * Accessible only to super_admin via browser when SSH/CLI is unavailable.
 */
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../config.php';

// Super admin only
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    header('Location: /login.php');
    exit();
}

$pdo     = getPdo();
$results = [];
$ran     = false;

$tables = [
    'bus_students'         => "CREATE TABLE IF NOT EXISTS bus_students (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        student_id         VARCHAR(20)  NOT NULL,
        fullname           VARCHAR(200) NOT NULL,
        classroom          VARCHAR(20)  NOT NULL DEFAULT '',
        national_id_hash   VARCHAR(255) NOT NULL,
        national_id_masked VARCHAR(20)  NOT NULL,
        phone              VARCHAR(20)  NULL,
        parent_phone       VARCHAR(20)  NULL,
        is_active          TINYINT(1)   NOT NULL DEFAULT 1,
        created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_student_id (student_id),
        INDEX idx_classroom (classroom)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'bus_routes' => "CREATE TABLE IF NOT EXISTS bus_routes (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        route_code   VARCHAR(20)   NOT NULL,
        route_name   VARCHAR(200)  NOT NULL,
        description  TEXT          NULL,
        price        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        seats        INT           NOT NULL DEFAULT 0,
        driver_name  VARCHAR(200)  NULL,
        driver_phone VARCHAR(20)   NULL,
        is_active    TINYINT(1)    NOT NULL DEFAULT 1,
        created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_route_code (route_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'bus_registrations' => "CREATE TABLE IF NOT EXISTS bus_registrations (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        student_id    INT         NOT NULL,
        route_id      INT         NOT NULL,
        semester      VARCHAR(10) NOT NULL,
        status        ENUM('active','cancelled','pending_cancel') NOT NULL DEFAULT 'active',
        registered_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        cancelled_at  DATETIME    NULL,
        notes         TEXT        NULL,
        UNIQUE KEY uk_student_semester (student_id, semester),
        INDEX idx_route_id (route_id),
        INDEX idx_status (status),
        INDEX idx_semester (semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'bus_payments' => "CREATE TABLE IF NOT EXISTS bus_payments (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        registration_id INT           NOT NULL,
        amount          DECIMAL(10,2) NOT NULL,
        paid_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        note            TEXT          NULL,
        recorded_by     INT           NULL,
        INDEX idx_registration_id (registration_id),
        INDEX idx_paid_at (paid_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'bus_cancel_requests' => "CREATE TABLE IF NOT EXISTS bus_cancel_requests (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        registration_id INT      NOT NULL,
        reason          TEXT     NULL,
        status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_note      TEXT     NULL,
        reviewed_by     INT      NULL,
        reviewed_at     DATETIME NULL,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_registration_id (registration_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

// Check current status
foreach (array_keys($tables) as $tbl) {
    $exists = $pdo->query("SHOW TABLES LIKE '$tbl'")->rowCount() > 0;
    $results[$tbl] = ['exists' => $exists, 'created' => false, 'error' => ''];
}

// Run if POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run') {
    csrf_verify();
    $ran = true;

    // Extend ENUM for bus roles
    try {
        $pdo->exec("ALTER TABLE llw_users MODIFY COLUMN role
            ENUM('super_admin','wfh_admin','wfh_staff','cb_admin','att_teacher','bus_admin','bus_finance')
            NOT NULL DEFAULT 'wfh_staff'");
        $results['_enum'] = ['exists' => true, 'created' => true, 'error' => ''];
    } catch (Exception $e) {
        $results['_enum'] = ['exists' => false, 'created' => false, 'error' => $e->getMessage()];
    }

    foreach ($tables as $tbl => $sql) {
        try {
            $pdo->exec($sql);
            $results[$tbl]['created'] = !$results[$tbl]['exists'];
            $results[$tbl]['exists']  = true;
        } catch (Exception $e) {
            $results[$tbl]['error'] = $e->getMessage();
        }
    }
}

$allOk = !$ran || !array_filter($results, fn($r) => $r['error'] !== '');
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bus Migration | LLW Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;700;900&display=swap" rel="stylesheet">
<style>body{font-family:'Prompt',sans-serif}</style>
</head>
<body class="bg-slate-100 min-h-screen p-8">
<div class="max-w-2xl mx-auto">

  <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="bg-gradient-to-r from-orange-500 to-amber-500 px-6 py-5">
      <h1 class="text-white font-black text-xl">Bus System — สร้างตารางฐานข้อมูล</h1>
      <p class="text-orange-100 text-sm mt-1">สำหรับ Super Admin เท่านั้น · ใช้ครั้งเดียว</p>
    </div>

    <div class="p-6 space-y-4">

      <!-- Status Table -->
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b">
            <th class="text-left py-2 font-black text-slate-500 text-xs uppercase tracking-wider">ตาราง</th>
            <th class="text-center py-2 font-black text-slate-500 text-xs uppercase tracking-wider">สถานะ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($results as $tbl => $r): ?>
          <tr>
            <td class="py-2.5 font-mono text-slate-700"><?= htmlspecialchars($tbl) ?></td>
            <td class="py-2.5 text-center">
              <?php if ($r['error']): ?>
              <span class="text-rose-600 font-bold text-xs">✗ <?= htmlspecialchars($r['error']) ?></span>
              <?php elseif ($r['created']): ?>
              <span class="text-emerald-600 font-bold">✓ สร้างแล้ว</span>
              <?php elseif ($r['exists']): ?>
              <span class="text-slate-400 text-xs">มีอยู่แล้ว</span>
              <?php else: ?>
              <span class="text-amber-600 font-bold text-xs">⚠ ยังไม่มี</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($ran && $allOk): ?>
      <div class="bg-emerald-50 text-emerald-700 rounded-2xl px-4 py-3 font-bold text-sm">
        ✓ สร้างตารางเรียบร้อยแล้ว — สามารถใช้งานระบบรถรับส่งได้ทันที
      </div>
      <?php endif; ?>

      <!-- Run Button -->
      <?php if (!$ran): ?>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="run">
        <button type="submit"
          onclick="return confirm('สร้างตาราง bus_* ในฐานข้อมูล?')"
          class="w-full py-3.5 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-2xl font-black shadow-lg shadow-orange-200 hover:opacity-90 transition-all">
          สร้างตาราง Bus System
        </button>
      </form>
      <?php else: ?>
      <a href="/bus/admin/routes.php" class="block w-full py-3 bg-slate-800 text-white rounded-2xl font-black text-center hover:bg-slate-700 transition-all">
        → ไปที่หน้าจัดการสายรถ
      </a>
      <?php endif; ?>

      <p class="text-xs text-slate-400 text-center">ลบไฟล์นี้หลังใช้งาน: <code>bus/admin/migrate.php</code></p>

      <a href="/bus/admin/dashboard.php" class="block text-center text-slate-400 text-sm hover:text-slate-600">← กลับ Dashboard</a>
    </div>
  </div>

</div>
</body>
</html>
