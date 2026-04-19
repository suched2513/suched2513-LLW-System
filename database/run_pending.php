<?php
/**
 * Temporary Migration Runner (Browser-based)
 * ============================================
 * เปิดใช้งาน: http://localhost/llw/database/run_pending.php
 * ลบไฟล์นี้ทันทีหลังใช้งาน!
 *
 * ⚠️ ไฟล์นี้จะถูกลบอัตโนมัติโดย deploy.yml ก่อน push ขึ้น production
 */

// ปิด browser caching
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../config/database.php';

/*
session_start();
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    die('<div style="color:red; font-family:sans-serif; padding:2rem;">❌ <b>Access Denied:</b> เฉพาะผู้ดูแลระบบสูงสุดเท่านั้นที่สามารถรันการตั้งค่านี้ได้</div>');
}
*/

echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">
<title>Migration Runner - LLW</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;700;900&display=swap" rel="stylesheet">
<style>
  body { font-family: Prompt, sans-serif; background:#0f172a; color:#e2e8f0; padding:2rem; }
  h1 { color:#38bdf8; font-weight:900; margin-bottom:1.5rem; }
  .card { background:#1e293b; border-radius:1rem; padding:1.5rem; margin-bottom:1rem; border:1px solid #334155; }
  .ok   { color:#34d399; font-weight:700; }
  .err  { color:#f87171; font-weight:700; }
  .warn { color:#fbbf24; font-weight:700; }
  .info { color:#94a3b8; }
  .badge { display:inline-block; padding:.2rem .8rem; border-radius:999px; font-size:.75rem; font-weight:900; }
  .badge-ok   { background:#064e3b; color:#34d399; }
  .badge-warn { background:#451a03; color:#fbbf24; }
  .badge-err  { background:#450a0a; color:#f87171; }
  pre { margin:0; font-family:monospace; white-space:pre-wrap; }
  hr { border-color:#334155; margin:1.5rem 0; }
  .summary { font-size:1.25rem; font-weight:900; color:#38bdf8; margin-top:1.5rem; }
</style></head><body>';

echo '<h1>🚀 LLW Migration Runner</h1>';
echo '<div class="card info">⚠️ ลบไฟล์นี้ทันทีหลังใช้งาน: <code>database/run_pending.php</code></div>';

try {
    $pdo = getPdo();

    // สร้าง _migrations table ถ้ายังไม่มี
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS _migrations (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            migration   VARCHAR(255) NOT NULL UNIQUE,
            batch       INT NOT NULL DEFAULT 1,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $migrationsDir = __DIR__ . '/migrations';
    $files = glob($migrationsDir . '/*.php');
    sort($files);

    $executed = $pdo->query("SELECT migration FROM _migrations ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $batch    = (int)$pdo->query("SELECT COALESCE(MAX(batch), 0) FROM _migrations")->fetchColumn() + 1;

    $pending = [];
    foreach ($files as $f) {
        $name = basename($f, '.php');
        if (!in_array($name, $executed)) $pending[] = $f;
    }

    // แสดงสถานะ
    echo '<div class="card"><h3 style="color:#94a3b8;font-weight:900;font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem">Migration Status</h3>';
    foreach ($files as $f) {
        $name = basename($f, '.php');
        $done = in_array($name, $executed);
        $badge = $done
            ? '<span class="badge badge-ok">✓ Done</span>'
            : '<span class="badge badge-warn">⏳ Pending</span>';
        echo "<div style='margin-bottom:.5rem'>$badge &nbsp;<code style='font-size:.8rem'>$name</code></div>";
    }
    echo '</div>';

    if (empty($pending)) {
        echo '<div class="card ok">✅ Nothing to migrate — all up to date!</div>';
    } else {
        echo '<div class="card"><h3 style="color:#38bdf8;font-weight:900;margin-bottom:1rem">Running ' . count($pending) . ' pending migration(s)...</h3>';

        $success = 0;
        $fail    = 0;

        foreach ($pending as $file) {
            $name = basename($file, '.php');
            echo "<div style='margin-bottom:1rem'>";
            echo "<div style='font-size:.85rem;color:#94a3b8;margin-bottom:.5rem'>→ <strong>$name</strong></div>";

            try {
                $migration = require $file;
                if (!is_array($migration) || !isset($migration['up'])) {
                    throw new RuntimeException("Invalid migration format — must return ['up' => fn, 'down' => fn]");
                }
                $pdo->beginTransaction();
                $migration['up']($pdo);
                $stmt = $pdo->prepare("INSERT INTO _migrations (migration, batch) VALUES (?, ?)");
                $stmt->execute([$name, $batch]);
                $pdo->commit();
                echo "<span class='ok'>✅ Migrated successfully</span>";
                $success++;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo "<span class='err'>❌ Failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</span>";
                $fail++;
            }
            echo "</div><hr>";
        }

        $color = $fail > 0 ? '#f87171' : '#34d399';
        echo "<div class='summary' style='color:$color'>Batch $batch: $success succeeded, $fail failed</div>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo '<div class="card err">❌ Connection Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
}

echo '<hr><div class="info" style="font-size:.8rem">⚠️ กรุณาลบไฟล์ <code>database/run_pending.php</code> ทันทีหลังใช้งาน</div>';
echo '</body></html>';
