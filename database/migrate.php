<?php
/**
 * database/migrate.php — Lightweight Migration Runner
 * =====================================================
 * ใช้งาน:
 *   php database/migrate.php                → run pending migrations
 *   php database/migrate.php --seed         → run seeds after migration
 *   php database/migrate.php --rollback     → rollback last batch
 *   php database/migrate.php --rollback=3   → rollback last 3 migrations
 *   php database/migrate.php --fresh        → drop all + re-migrate + seed
 *   php database/migrate.php --status       → show migration status
 *   php database/migrate.php --make=name    → create new migration file
 *   php database/migrate.php --seed-only    → run seeds only (no migration)
 *
 * ⚠️ ห้ามเรียกผ่าน browser — CLI only
 */

// ─── Guard: CLI only ──────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Migration runner is CLI only.');
}

// ─── Bootstrap ────────────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';

$pdo = getPdo();
$migrationsDir = __DIR__ . '/migrations';
$seedsDir      = __DIR__ . '/seeds';

// ─── Parse CLI args ───────────────────────────────────────────
$args = array_slice($argv, 1);
$command = 'migrate'; // default
$param   = null;

foreach ($args as $arg) {
    if ($arg === '--seed')       { $command = 'migrate+seed'; }
    elseif ($arg === '--seed-only') { $command = 'seed-only'; }
    elseif ($arg === '--status') { $command = 'status'; }
    elseif ($arg === '--fresh')  { $command = 'fresh'; }
    elseif (str_starts_with($arg, '--rollback')) {
        $command = 'rollback';
        $param = str_contains($arg, '=') ? (int)explode('=', $arg)[1] : 1;
    }
    elseif (str_starts_with($arg, '--make=')) {
        $command = 'make';
        $param = explode('=', $arg, 2)[1];
    }
}

// ─── Ensure _migrations table exists ──────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS _migrations (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        migration   VARCHAR(255) NOT NULL UNIQUE,
        batch       INT NOT NULL DEFAULT 1,
        executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ─── Helper functions ─────────────────────────────────────────

function out(string $msg, string $type = 'info'): void {
    $colors = ['info' => "\033[36m", 'ok' => "\033[32m", 'warn' => "\033[33m", 'err' => "\033[31m", 'reset' => "\033[0m"];
    $prefix = match($type) {
        'ok'   => $colors['ok']   . '  ✓ ',
        'warn' => $colors['warn'] . '  ⚠ ',
        'err'  => $colors['err']  . '  ✗ ',
        default => $colors['info'] . '  → ',
    };
    echo $prefix . $msg . $colors['reset'] . PHP_EOL;
}

function getExecuted(PDO $pdo): array {
    return $pdo->query("SELECT migration FROM _migrations ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
}

function getMigrationFiles(string $dir): array {
    if (!is_dir($dir)) return [];
    $files = glob($dir . '/*.php');
    sort($files);
    return $files;
}

function getNextBatch(PDO $pdo): int {
    $max = $pdo->query("SELECT COALESCE(MAX(batch), 0) FROM _migrations")->fetchColumn();
    return (int)$max + 1;
}

function loadMigration(string $file): array {
    $migration = require $file;
    if (!is_array($migration) || !isset($migration['up'])) {
        throw new RuntimeException("Invalid migration file: $file — must return ['up' => fn, 'down' => fn]");
    }
    return $migration;
}

// ─── Command: make ────────────────────────────────────────────
if ($command === 'make') {
    $timestamp = date('Y_m_d_His');
    $name = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($param)));
    $filename = "{$timestamp}_{$name}.php";
    $filepath = $migrationsDir . '/' . $filename;

    $template = <<<'PHP'
<?php
/**
 * Migration: %NAME%
 * Created: %DATE%
 */
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            -- สร้างตาราง / ALTER TABLE / เพิ่ม column ที่นี่
        ");
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("
            -- Rollback: DROP TABLE / DROP COLUMN ที่นี่
        ");
    },
];
PHP;

    $content = str_replace(
        ['%NAME%', '%DATE%'],
        [$name, date('Y-m-d H:i:s')],
        $template
    );

    file_put_contents($filepath, $content);
    out("Created: database/migrations/{$filename}", 'ok');
    exit(0);
}

// ─── Command: status ──────────────────────────────────────────
if ($command === 'status') {
    echo PHP_EOL . "  Migration Status" . PHP_EOL;
    echo "  ═══════════════════════════════════════════════════" . PHP_EOL;

    $executed = getExecuted($pdo);
    $files = getMigrationFiles($migrationsDir);

    if (empty($files)) {
        out('No migration files found.', 'warn');
        exit(0);
    }

    $rows = $pdo->query("SELECT migration, batch, executed_at FROM _migrations ORDER BY id")->fetchAll();
    $executedMap = [];
    foreach ($rows as $r) $executedMap[$r['migration']] = $r;

    foreach ($files as $f) {
        $name = basename($f, '.php');
        if (isset($executedMap[$name])) {
            $r = $executedMap[$name];
            out("{$name}  [batch {$r['batch']}  {$r['executed_at']}]", 'ok');
        } else {
            out("{$name}  [PENDING]", 'warn');
        }
    }
    echo PHP_EOL;
    exit(0);
}

// ─── Command: fresh ───────────────────────────────────────────
if ($command === 'fresh') {
    out('Dropping all tables...', 'warn');

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        out("Dropped: $table");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Re-create _migrations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS _migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT NOT NULL DEFAULT 1,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    out('All tables dropped. Running fresh migration...', 'warn');
    $command = 'migrate+seed'; // fall through to migrate + seed
}

// ─── Command: rollback ────────────────────────────────────────
if ($command === 'rollback') {
    $count = $param ?? 1;
    $rows = $pdo->query("SELECT migration, batch FROM _migrations ORDER BY id DESC LIMIT $count")->fetchAll();

    if (empty($rows)) {
        out('Nothing to rollback.', 'warn');
        exit(0);
    }

    echo PHP_EOL . "  Rolling back {$count} migration(s)..." . PHP_EOL;

    foreach ($rows as $row) {
        $file = $migrationsDir . '/' . $row['migration'] . '.php';
        if (!file_exists($file)) {
            out("File not found: {$row['migration']}", 'err');
            continue;
        }

        try {
            $migration = loadMigration($file);
            if (isset($migration['down'])) {
                $pdo->beginTransaction();
                $migration['down']($pdo);
                $stmt = $pdo->prepare("DELETE FROM _migrations WHERE migration = ?");
                $stmt->execute([$row['migration']]);
                $pdo->commit();
                out("Rolled back: {$row['migration']}", 'ok');
            } else {
                out("No down() defined: {$row['migration']}", 'warn');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            out("Failed: {$row['migration']} — {$e->getMessage()}", 'err');
        }
    }
    echo PHP_EOL;
    exit(0);
}

// ─── Command: seed-only ───────────────────────────────────────
if ($command === 'seed-only') {
    echo PHP_EOL . "  Running seeds..." . PHP_EOL;
    runSeeds($pdo, $seedsDir);
    echo PHP_EOL;
    exit(0);
}

// ─── Command: migrate (+ optional seed) ───────────────────────
echo PHP_EOL . "  Running migrations..." . PHP_EOL;

$executed = getExecuted($pdo);
$files    = getMigrationFiles($migrationsDir);
$pending  = [];

foreach ($files as $f) {
    $name = basename($f, '.php');
    if (!in_array($name, $executed)) {
        $pending[] = $f;
    }
}

if (empty($pending)) {
    out('Nothing to migrate. All up to date.', 'ok');
} else {
    $batch = getNextBatch($pdo);

    foreach ($pending as $file) {
        $name = basename($file, '.php');
        try {
            $migration = loadMigration($file);
            $pdo->beginTransaction();
            $migration['up']($pdo);
            $stmt = $pdo->prepare("INSERT INTO _migrations (migration, batch) VALUES (?, ?)");
            $stmt->execute([$name, $batch]);
            $pdo->commit();
            out("Migrated: {$name}", 'ok');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            out("Failed: {$name} — {$e->getMessage()}", 'err');
            exit(1);
        }
    }

    out("Batch {$batch} complete. " . count($pending) . " migration(s) executed.", 'ok');
}

// Run seeds if requested
if (str_contains($command, 'seed')) {
    echo PHP_EOL . "  Running seeds..." . PHP_EOL;
    runSeeds($pdo, $seedsDir);
}

echo PHP_EOL;
exit(0);

// ─── Seed runner ──────────────────────────────────────────────
function runSeeds(PDO $pdo, string $seedsDir): void {
    $files = getMigrationFiles($seedsDir);
    if (empty($files)) {
        out('No seed files found.', 'warn');
        return;
    }

    foreach ($files as $file) {
        $name = basename($file, '.php');
        try {
            $seed = require $file;
            if (is_callable($seed)) {
                $seed($pdo);
            } elseif (is_array($seed) && isset($seed['run'])) {
                $seed['run']($pdo);
            }
            out("Seeded: {$name}", 'ok');
        } catch (Exception $e) {
            out("Seed failed: {$name} — {$e->getMessage()}", 'err');
        }
    }
}
