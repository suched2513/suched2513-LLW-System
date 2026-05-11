<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;color:red">Access Denied: Super Admin Only</h2>');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>Run Migration</title>
<style>
body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
h2 { color: #38bdf8; }
pre { background: #1e293b; padding: 1rem; border-radius: 8px; white-space: pre-wrap; line-height: 1.6; }
.ok  { color: #4ade80; }
.err { color: #f87171; }
.btn { display:inline-block; margin-top:1rem; padding:.6rem 1.4rem; background:#2563eb;
       color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:1rem;
       text-decoration:none; }
.btn:hover { background:#1d4ed8; }
.warn { color: #fbbf24; }
</style>
</head>
<body>
<h2>&#9889; LLW Migration Runner</h2>
<?php

$migrationsDir = __DIR__ . '/database/migrations';
$pdo = getPdo();

$pdo->exec("CREATE TABLE IF NOT EXISTS _migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL DEFAULT 1,
    run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$ran = $pdo->query("SELECT migration FROM _migrations")->fetchAll(PDO::FETCH_COLUMN);
$files = glob($migrationsDir . '/*.php');
sort($files);
$pending = array_filter($files, fn($f) => !in_array(basename($f), $ran, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    $batch = (int)($pdo->query("SELECT MAX(batch) FROM _migrations")->fetchColumn() ?: 0) + 1;
    echo '<pre>';
    foreach ($pending as $file) {
        $name = basename($file);
        echo "<span class='warn'>▶ Running: $name</span>\n";
        try {
            $migration = require $file;
            if (is_array($migration) && isset($migration['up'])) {
                $migration['up']($pdo);
                $stmt = $pdo->prepare("INSERT INTO _migrations (migration, batch) VALUES (?, ?)");
                $stmt->execute([$name, $batch]);
                echo "<span class='ok'>  ✔ Done</span>\n";
            }
        } catch (Throwable $e) {
            echo "<span class='err'>  ✘ ERROR: " . htmlspecialchars($e->getMessage()) . "</span>\n";
        }
    }
    echo "\n<span class='ok'>&#10003; Finished</span>";
    echo '</pre>';
    echo '<p style="color:#94a3b8;margin-top:1rem">&#9888; กรุณาลบไฟล์ migrate_now.php ออกจาก production หลังใช้งาน</p>';
} else {
    echo '<pre>';
    if (empty($pending)) {
        echo "<span class='ok'>&#10003; ไม่มี pending migrations</span>\n";
    } else {
        echo "<span class='warn'>Pending (" . count($pending) . " รายการ):</span>\n\n";
        foreach ($pending as $f) echo "  • " . htmlspecialchars(basename($f)) . "\n";
    }
    echo '</pre>';
    if (!empty($pending)) {
        echo '<form method="POST"><button name="run" value="1" class="btn">&#9889; Run ' . count($pending) . ' migration(s)</button></form>';
    }
}
?>
</body>
</html>
