<?php
/**
 * Web Migration Runner สำหรับ school_project
 *
 * ใช้เมื่อไม่มี SSH access (deploy แบบ FTP).
 * เรียกผ่าน browser เพื่อรัน pending migrations + seeds:
 *   https://llw.krusuched.com/school_project/_migrate.php          → status (text)
 *   https://llw.krusuched.com/school_project/_migrate.php?run=1    → run pending migrations
 *   https://llw.krusuched.com/school_project/_migrate.php?seed=1   → run seeds only
 *
 * Auth: ต้อง login ด้วย super_admin จาก /login.php (LLW central) ก่อน
 *
 * เมื่อใช้เสร็จ ลบไฟล์นี้ออกได้ผ่าน commit ใหม่
 */

session_start();
header('Content-Type: text/plain; charset=utf-8');

// ── Auth: เฉพาะ super_admin จากระบบกลาง LLW ────────────────
if (($_SESSION['llw_role'] ?? '') !== 'super_admin' && ($_SESSION['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo "FORBIDDEN — กรุณาเข้าสู่ระบบ /login.php ด้วย super_admin ก่อน\n";
    exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = getPdo();

$migrationsDir = __DIR__ . '/../database/migrations';
$seedsDir      = __DIR__ . '/../database/seeds';

// ── Ensure _migrations table ────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS _migrations (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        migration   VARCHAR(255) NOT NULL UNIQUE,
        batch       INT NOT NULL DEFAULT 1,
        executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

function listFiles(string $dir): array {
    if (!is_dir($dir)) return [];
    $files = glob($dir . '/*.php');
    sort($files);
    return $files;
}

$action = '';
if (isset($_GET['run']))  $action = 'run';
if (isset($_GET['seed'])) $action = 'seed';
if (isset($_GET['skip'])) $action = 'skip';

// ── Default: แสดง status ───────────────────────────────────
if ($action === '') {
    echo "Migration Status\n";
    echo str_repeat('=', 60) . "\n\n";

    $executed = $pdo->query("SELECT migration FROM _migrations ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $files    = listFiles($migrationsDir);
    $pending  = 0;

    foreach ($files as $f) {
        $name = basename($f, '.php');
        if (in_array($name, $executed, true)) {
            echo "  [✓] $name\n";
        } else {
            echo "  [ ] $name  (PENDING)\n";
            $pending++;
        }
    }

    echo "\n";
    echo "Pending: $pending migration(s)\n\n";
    echo "Actions:\n";
    echo "  ?run=1                 → run pending migrations\n";
    echo "  ?seed=1                → run seeds (idempotent)\n";
    echo "  ?skip=<migration_name> → mark a migration as executed WITHOUT running it\n";
    echo "                            (ใช้เมื่อ schema มีอยู่แล้วและ migration ไม่ idempotent)\n";
    exit;
}

// ── Action: skip — mark migration as run without executing ────
if ($action === 'skip') {
    $name = trim($_GET['skip']);
    if ($name === '' || $name === '1') {
        http_response_code(400);
        echo "ระบุชื่อ migration ที่จะ skip ผ่าน ?skip=<name>\n";
        exit;
    }

    $file = $migrationsDir . '/' . $name . '.php';
    if (!file_exists($file)) {
        http_response_code(404);
        echo "ไม่เจอไฟล์ migration: $name\n";
        exit;
    }

    $batch = (int)$pdo->query("SELECT COALESCE(MAX(batch),0) FROM _migrations")->fetchColumn() + 1;
    $stmt = $pdo->prepare("INSERT IGNORE INTO _migrations (migration, batch) VALUES (?, ?)");
    $stmt->execute([$name, $batch]);
    echo "✓ Marked as executed (batch=$batch): $name\n";
    echo "ต่อด้วย ?run=1 เพื่อรัน migration ที่เหลือ\n";
    exit;
}

// ── Action: run migrations ──────────────────────────────────
if ($action === 'run') {
    echo "Running pending migrations...\n";
    echo str_repeat('=', 60) . "\n\n";

    $executed = $pdo->query("SELECT migration FROM _migrations ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $files    = listFiles($migrationsDir);
    $pending  = [];

    foreach ($files as $f) {
        $name = basename($f, '.php');
        if (!in_array($name, $executed, true)) $pending[] = $f;
    }

    if (!$pending) {
        echo "ไม่มี migration ที่ pending — ทุกอย่าง up-to-date\n";
        exit;
    }

    $batch = (int)$pdo->query("SELECT COALESCE(MAX(batch),0) FROM _migrations")->fetchColumn() + 1;

    foreach ($pending as $file) {
        $name = basename($file, '.php');
        try {
            $migration = require $file;
            if (!is_array($migration) || !isset($migration['up'])) {
                echo "  [✗] $name — ไม่ใช่รูปแบบ migration ที่ถูกต้อง\n";
                continue;
            }
            $migration['up']($pdo);
            $stmt = $pdo->prepare("INSERT IGNORE INTO _migrations (migration, batch) VALUES (?, ?)");
            $stmt->execute([$name, $batch]);
            echo "  [✓] Migrated: $name\n";
        } catch (Throwable $e) {
            echo "  [✗] FAILED: $name\n";
            echo "       Error: " . $e->getMessage() . "\n";
            echo "       File:  " . $e->getFile() . ":" . $e->getLine() . "\n";
            exit;
        }
    }

    echo "\nbatch=$batch — " . count($pending) . " migration(s) เรียบร้อย\n";
    echo "\nต่อด้วย ?seed=1 เพื่อใส่ seed data\n";
    exit;
}

// ── Action: run seeds ──────────────────────────────────────
if ($action === 'seed') {
    echo "Running seeds...\n";
    echo str_repeat('=', 60) . "\n\n";

    $files = listFiles($seedsDir);
    if (!$files) {
        echo "ไม่มี seed file\n";
        exit;
    }

    foreach ($files as $file) {
        $name = basename($file, '.php');
        try {
            $seed = require $file;
            if (is_callable($seed)) {
                $seed($pdo);
            } elseif (is_array($seed) && isset($seed['run'])) {
                $seed['run']($pdo);
            } else {
                echo "  [!] Skipped (รูปแบบไม่ถูกต้อง): $name\n";
                continue;
            }
            echo "  [✓] Seeded: $name\n";
        } catch (Throwable $e) {
            echo "  [✗] FAILED: $name\n";
            echo "       Error: " . $e->getMessage() . "\n";
        }
    }

    echo "\n✅ Seed เสร็จสิ้น\n";
    exit;
}
