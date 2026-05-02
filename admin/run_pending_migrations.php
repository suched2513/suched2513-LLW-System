<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403); die('Forbidden');
}

$pdo = getPdo();
$results = [];

$steps = [
    'att_students: เพิ่มคอลัมน์ national_id_hash / masked / last_login' => function(PDO $pdo) {
        $cols = array_column($pdo->query("SHOW COLUMNS FROM att_students")->fetchAll(PDO::FETCH_ASSOC), 'Field');
        if (!in_array('national_id_hash', $cols)) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN national_id_hash VARCHAR(255) NULL AFTER classroom");
        }
        if (!in_array('national_id_masked', $cols)) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN national_id_masked VARCHAR(20) NULL AFTER national_id_hash");
        }
        if (!in_array('last_login', $cols)) {
            $pdo->exec("ALTER TABLE att_students ADD COLUMN last_login DATETIME NULL AFTER national_id_masked");
        }
    },
    'สร้างตาราง student_transport' => function(PDO $pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS student_transport (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            att_student_id INT NOT NULL,
            semester       VARCHAR(20) NOT NULL,
            transport_type ENUM('school_bus','motorcycle','bicycle','walk','private_car','other') NOT NULL,
            route_id       INT NULL,
            home_village   VARCHAR(200) NULL,
            note           VARCHAR(500) NULL,
            status         ENUM('submitted','confirmed') NOT NULL DEFAULT 'submitted',
            confirmed_by   INT NULL,
            confirmed_at   DATETIME NULL,
            created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_student_semester (att_student_id, semester),
            INDEX idx_att_student (att_student_id),
            INDEX idx_semester (semester)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'bus_students: แก้ national_id_hash / masked เป็น nullable' => function(PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM bus_students")->fetchAll(PDO::FETCH_ASSOC);
        $nullMap = array_column($cols, 'Null', 'Field');
        if (isset($nullMap['national_id_hash']) && $nullMap['national_id_hash'] === 'NO') {
            $pdo->exec("ALTER TABLE bus_students MODIFY COLUMN national_id_hash VARCHAR(255) NULL");
        }
        if (isset($nullMap['national_id_masked']) && $nullMap['national_id_masked'] === 'NO') {
            $pdo->exec("ALTER TABLE bus_students MODIFY COLUMN national_id_masked VARCHAR(20) NULL");
        }
    },
];

$allOk = true;
foreach ($steps as $label => $fn) {
    try {
        $fn($pdo);
        $results[] = ['ok' => true, 'label' => $label];
    } catch (Exception $e) {
        $results[] = ['ok' => false, 'label' => $label, 'err' => $e->getMessage()];
        $allOk = false;
        error_log('[migration] ' . $label . ': ' . $e->getMessage());
    }
}

// Self-delete on success
if ($allOk) {
    @unlink(__FILE__);
}
?><!DOCTYPE html>
<html lang="th"><head><meta charset="UTF-8"><title>Migration</title>
<style>body{font-family:sans-serif;max-width:600px;margin:40px auto;padding:20px}
.ok{color:#16a34a}.err{color:#dc2626}.box{border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:8px 0}</style>
</head><body>
<h2>Migration Results</h2>
<?php foreach ($results as $r): ?>
<div class="box">
  <span class="<?= $r['ok'] ? 'ok' : 'err' ?>"><?= $r['ok'] ? '✅' : '❌' ?></span>
  <?= htmlspecialchars($r['label']) ?>
  <?php if (!$r['ok']): ?><br><small style="color:#dc2626"><?= htmlspecialchars($r['err']) ?></small><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if ($allOk): ?>
<p class="ok"><strong>✅ เสร็จสมบูรณ์ — ไฟล์นี้ถูกลบแล้ว</strong></p>
<?php else: ?>
<p class="err"><strong>❌ มีข้อผิดพลาด กรุณาตรวจสอบ error log</strong></p>
<?php endif; ?>
<p><a href="/index.php">← กลับหน้าหลัก</a></p>
</body></html>
