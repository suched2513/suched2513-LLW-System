<?php
/**
 * LLW Migration Runner v2 — Auto-create DB if not exists
 * เปิดใช้: http://localhost/llw/run_pending.php
 * ลบไฟล์นี้ทันทีหลังใช้งาน!
 */
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

// ── Step 1: Connect ไม่ระบุ DB เพื่อสร้าง DB ถ้าไม่มี ──────────
try {
    $pdoRoot = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo '<div class="card">';
    echo '<h3 style="color:#38bdf8;font-weight:900;margin-bottom:.8rem">Step 1: Database Setup</h3>';

    // ตรวจว่า llw_db มีอยู่ไหม
    $exists = $pdoRoot->query("SHOW DATABASES LIKE 'llw_db'")->fetchColumn();
    if (!$exists) {
        $pdoRoot->exec("CREATE DATABASE `llw_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo '<div class="ok">✅ Created database <code>llw_db</code></div>';
    } else {
        echo '<div class="ok">✅ Database <code>llw_db</code> exists</div>';
    }
    echo '</div>';

} catch (Exception $e) {
    die('<div class="card err">❌ Cannot connect to MySQL: ' . htmlspecialchars($e->getMessage()) . '</div></body></html>');
}

// ── Step 2: Connect to llw_db ───────────────────────────────────
try {
    $pdo = new PDO('mysql:host=localhost;dbname=llw_db;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    die('<div class="card err">❌ Cannot connect to llw_db: ' . htmlspecialchars($e->getMessage()) . '</div></body></html>');
}

// ── Step 3: ปิด innodb_file_per_table ชั่วคราว ────────────────
// เพื่อข้ามปัญหา ghost tablespace (.ibd files เก่าใน ibdata1)
try {
    $pdo->exec("SET GLOBAL innodb_file_per_table = 0");
    echo '<div class="card warn">⚙️ Set innodb_file_per_table=OFF (bypass ghost tablespace)</div>';
} catch (Exception $e) {
    echo '<div class="card warn">⚠️ Could not set innodb_file_per_table: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// ── Step 4: สร้าง _migrations table ────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    migration   VARCHAR(255) NOT NULL UNIQUE,
    batch       INT NOT NULL DEFAULT 1,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Step 4: Run pending migrations ─────────────────────────────
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
    echo '<div class="card ok">✅ All migrations up to date!</div>';
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
            // ⚠️ DDL statements (CREATE TABLE, ALTER TABLE) cause implicit commit in MySQL
            // DO NOT wrap in beginTransaction — execute directly
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

    // ── Step 5: Seed admin user (ครั้งแรก) ─────────────────────
    if ($success > 0) {
        echo '<div class="card">';
        echo '<h3 style="color:#a78bfa;font-weight:900;margin-bottom:.8rem">Seeding Initial Data</h3>';
        try {
            $exists = $pdo->query("SELECT COUNT(*) FROM llw_users WHERE username='admin_llw'")->fetchColumn();
            if (!$exists) {
                $hash = password_hash('123456', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO llw_users (username,password,firstname,lastname,role,status) VALUES (?,?,?,?,'super_admin','active')");
                $stmt->execute(['admin_llw', $hash, 'Admin', 'LLW']);
                echo '<div class="ok">✅ Created: <code>admin_llw</code> / <code>123456</code> (Super Admin)</div>';
            } else {
                echo '<div class="ok">✅ Admin user already exists</div>';
            }

            $settingExists = $pdo->query("SELECT COUNT(*) FROM wfh_system_settings")->fetchColumn();
            if (!$settingExists) {
                $pdo->exec("INSERT INTO wfh_system_settings (regular_time_in,late_time,school_lat,school_lng,geofence_radius) VALUES ('08:00:00','08:30:00',0,0,200)");
                echo '<div class="ok">✅ WFH settings (default)</div>';
            }
        } catch (Exception $e) {
            echo '<div class="err">⚠️ Seed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        echo '</div>';
    }

    // ── Summary ─────────────────────────────────────────────────
    $color = $fail > 0 ? '#f87171' : '#34d399';
    echo "<div style='background:#0f172a;border:2px solid $color;border-radius:.75rem;padding:1.2rem;'>
        <div style='color:$color;font-weight:900;font-size:1.2rem'>
            " . ($fail === 0 ? '🎉 สำเร็จทั้งหมด!' : '⚠️ มีบางส่วนล้มเหลว') . "
        </div>
        <div style='color:#94a3b8;margin-top:.5rem;font-size:.9rem'>
            $success succeeded / $fail failed<br><br>
            " . ($fail === 0 ? "✅ <a href='/llw/login.php'><strong>เข้าสู่ระบบ</strong></a> (admin_llw / 123456)<br>" : "") . "
            <span style='color:#f87171'>⚠️ ลบไฟล์: run_pending.php, smart_reset.php, nuclear_reset.php, repair_db.php</span>
        </div>
    </div>";
}

echo '</body></html>';
