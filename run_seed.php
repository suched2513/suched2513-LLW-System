<?php
/**
 * run_seed.php — Web-based Seed Runner (development only)
 * เข้าถึงได้เฉพาะ localhost เท่านั้น
 */
session_start();
require_once __DIR__ . '/config/database.php';

// Security: localhost only + must be super_admin
$isLocal = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']);
if (!$isLocal) {
    http_response_code(403); die('Access denied: localhost only');
}
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403); die('Access denied: super_admin only');
}

$seedsDir = __DIR__ . '/database/seeds';
$seeds    = glob($seedsDir . '/*.php');
sort($seeds);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Seed Runner — LLW</title>
<style>
    body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
    h1   { color: #f59e0b; margin-bottom: 1rem; }
    .row { padding: .4rem 0; border-bottom: 1px solid #1e293b; display: flex; align-items: center; gap: 1rem; }
    .ok  { color: #10b981; font-weight: bold; }
    .err { color: #f43f5e; font-weight: bold; }
    .info{ color: #94a3b8; }
    code { background: #1e293b; padding: .1rem .4rem; border-radius: 4px; font-size: .85rem; }
    .summary { margin-top: 1.5rem; padding: 1rem; background: #1e293b; border-radius: 8px; font-size: 1rem; }
    form { margin-bottom: 1rem; }
    select, input[type=submit] { font-family: monospace; padding: .4rem .8rem; border-radius: 6px; border: 1px solid #334155; background: #1e293b; color: #e2e8f0; cursor: pointer; }
    input[type=submit] { background: #f59e0b; color: #000; font-weight: bold; border: none; }
</style>
</head>
<body>
<h1>🌱 LLW Seed Runner</h1>
<p class="info">Seeds directory: <code><?= htmlspecialchars($seedsDir) ?></code></p>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'):
    $selected = $_POST['seed_file'] ?? 'all';
    $pdo      = getPdo();
    $success  = 0; $fail = 0;

    $toRun = ($selected === 'all') ? $seeds : array_filter($seeds, fn($f) => basename($f) === $selected);

    foreach ($toRun as $file):
        $name = basename($file);
        echo "<div class='row'><span class='info'>→ <code>$name</code></span> ";
        try {
            $fn = require $file;
            if (!is_callable($fn)) throw new RuntimeException('Seed must return a callable function');
            ob_start();
            $fn($pdo);
            $out = ob_get_clean();
            echo "<span class='ok'>✅ Done</span>";
            if ($out) echo " <span class='info'>— $out</span>";
            $success++;
        } catch (Exception $e) {
            echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
            $fail++;
        }
        echo "</div>";
    endforeach;
    echo "<div class='summary'>สรุป: <span class='ok'>$success สำเร็จ</span> / <span class='err'>$fail ล้มเหลว</span></div>";
?>
<form method="GET"><input type="submit" value="← กลับ"></form>

<?php else: ?>

<form method="POST">
    <label class="info">เลือก Seed ที่ต้องการ:</label><br><br>
    <select name="seed_file" style="min-width: 400px;">
        <option value="all">🌱 รัน Seed ทั้งหมด</option>
        <?php foreach ($seeds as $f): ?>
        <option value="<?= htmlspecialchars(basename($f)) ?>"><?= htmlspecialchars(basename($f)) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="submit" value="▶ Run Seed">
</form>

<div class="info">
    <p>Seed files พบ <?= count($seeds) ?> ไฟล์:</p>
    <?php foreach ($seeds as $f): ?>
    <div class="row"><code><?= htmlspecialchars(basename($f)) ?></code></div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
</body>
</html>
