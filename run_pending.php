<?php
/**
 * LLW Migration Runner — Production Safe
 * ต้อง login เป็น super_admin ก่อนจึงใช้ได้
 * เข้าใช้: https://llw.krusuched.com/run_pending.php
 */
session_start();
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');

echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">
<title>Migration Runner - LLW</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;700;900&display=swap" rel="stylesheet">
<style>
  body{font-family:Prompt,sans-serif;background:#0f172a;color:#e2e8f0;padding:2rem;}
  h1{color:#38bdf8;font-weight:900;margin-bottom:1.5rem;}
  .card{background:#1e293b;border-radius:1rem;padding:1.5rem;margin-bottom:1rem;border:1px solid #334155;}
  .ok{color:#34d399;font-weight:700;} .err{color:#f87171;font-weight:700;}
  .warn{color:#fbbf24;} .info{color:#94a3b8;}
  .badge{display:inline-block;padding:.2rem .8rem;border-radius:999px;font-size:.75rem;font-weight:900;}
  .badge-ok{background:#064e3b;color:#34d399;} .badge-warn{background:#451a03;color:#fbbf24;}
  code{background:#0f172a;padding:.1rem .4rem;border-radius:.3rem;font-size:.85rem;}
  hr{border-color:#334155;margin:.6rem 0;}
  .row{margin:.3rem 0;}
  a{color:#38bdf8;}
</style></head><body>';

echo '<h1>🚀 LLW Migration Runner</h1>';

// ── Auth Guard: ต้องเป็น super_admin ──────────────────────────────
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    echo '<div class="card err">❌ กรุณา <a href="/login.php">เข้าสู่ระบบ</a> ด้วยบัญชี Super Admin ก่อน</div>';
    echo '</body></html>';
    exit;
}

// ── Connect ผ่าน getPdo() จาก config/database.php ─────────────────
try {
    $pdo = getPdo();
    echo '<div class="card ok">✅ เชื่อมต่อฐานข้อมูล ' . DB_NAME . ' สำเร็จ</div>';
} catch (Exception $e) {
    die('<div class="card err">❌ Cannot connect to MySQL: ' . htmlspecialchars($e->getMessage()) . '</div></body></html>');
}

// ── สร้าง _migrations table ────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    migration   VARCHAR(255) NOT NULL UNIQUE,
    batch       INT NOT NULL DEFAULT 1,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── อ่าน migration files ───────────────────────────────────────────
$migrationsDir = __DIR__ . '/database/migrations';
$files = glob($migrationsDir . '/*.php');
sort($files);

$executed = $pdo->query("SELECT migration FROM _migrations ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$batch    = (int)$pdo->query("SELECT COALESCE(MAX(batch),0) FROM _migrations")->fetchColumn() + 1;

$pending = [];
foreach ($files as $f) {
    $name = basename($f, '.php');
    if (!in_array($name, $executed)) $pending[] = $f;
}

// ── แสดงสถานะ migration ทั้งหมด ───────────────────────────────────
echo '<div class="card">';
echo '<h3 style="color:#94a3b8;font-weight:900;font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.8rem">Migration Status</h3>';
foreach ($files as $f) {
    $name = basename($f, '.php');
    $done = in_array($name, $executed);
    $badge = $done ? '<span class="badge badge-ok">✓ Done</span>' : '<span class="badge badge-warn">⏳ Pending</span>';
    echo "<div class='row'>$badge &nbsp;<code>$name</code></div>";
}
echo '</div>';

if (empty($pending)) {
    echo '<div class="card ok">✅ All migrations up to date! ไม่มี migration ที่ต้อง run</div>';
} else {
    echo '<div class="card">';
    echo '<h3 style="color:#38bdf8;font-weight:900;margin-bottom:.8rem">Running ' . count($pending) . ' migration(s) — Batch ' . $batch . '</h3>';

    $success = 0; $fail = 0;
    foreach ($pending as $file) {
        $name = basename($file, '.php');
        echo "<div class='row'><span class='info'>→ <code>$name</code></span> ";
        try {
            $migration = require $file;
            if (!is_array($migration) || !isset($migration['up'])) {
                throw new RuntimeException("Invalid format — must return ['up' => fn, 'down' => fn]");
            }
            $migration['up']($pdo);
            $stmt = $pdo->prepare("INSERT IGNORE INTO _migrations (migration, batch) VALUES (?, ?)");
            $stmt->execute([$name, $batch]);
            echo "<span class='ok'>✅ Done</span>";
            $success++;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
            $fail++;
        }
        echo "</div>";
    }
    echo '</div>';

    $color = $fail > 0 ? '#f87171' : '#34d399';
    echo "<div style='background:#0f172a;border:2px solid $color;border-radius:.75rem;padding:1.2rem;'>
        <div style='color:$color;font-weight:900;font-size:1.2rem'>
            " . ($fail === 0 ? '🎉 สำเร็จทั้งหมด!' : '⚠️ มีบางส่วนล้มเหลว') . "
        </div>
        <div style='color:#94a3b8;margin-top:.5rem;font-size:.9rem'>
            $success succeeded / $fail failed
        </div>
    </div>";
}

echo '<div class="card warn" style="margin-top:1rem">
    ⚠️ <strong>หมายเหตุ:</strong> หน้านี้ใช้สำหรับ Super Admin เท่านั้น
    สามารถ <a href="/central_dashboard.php">กลับไปหน้า Dashboard</a> ได้เลย
</div>';

echo '</body></html>';
