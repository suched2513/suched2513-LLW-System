<?php
/**
 * database/web_runner.php — TEMPORARY Web Migration Runner
 * บันนึก: รันเสร็จแล้วต้องลบทันที!
 */
header('Content-Type: text/plain; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/database.php';

// Secure Token Guard: Only the AI knows this for the one-time run
if (($_GET['token'] ?? '') !== 'llw_super_secret_migration_2026') {
    http_response_code(403);
    die('Unauthorized: Token missing or invalid.');
}

$pdo = getPdo();
$migrationsDir = __DIR__ . '/migrations';

echo "=== LLW Web Migration Runner ===\n\n";

try {
    // 1. Ensure _migrations table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS _migrations (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            migration   VARCHAR(255) NOT NULL UNIQUE,
            batch       INT NOT NULL DEFAULT 1,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $executed = $pdo->query("SELECT migration FROM _migrations")->fetchAll(PDO::FETCH_COLUMN);
    $files = glob($migrationsDir . '/*.php');
    sort($files);

    $only = $_GET['only'] ?? '';
    $pending = [];
    foreach ($files as $f) {
        $name = basename($f, '.php');
        if ($only && $name === $only) {
            $pending = [$f];
            break;
        }
        if (!$only && !in_array($name, $executed)) {
            $pending[] = $f;
        }
    }

    if (empty($pending)) {
        echo "Nothing to migrate. All up to date.\n";
    } else {
        $maxBatch = $pdo->query("SELECT COALESCE(MAX(batch), 0) FROM _migrations")->fetchColumn();
        $batch = (int)$maxBatch + 1;

        foreach ($pending as $file) {
            $name = basename($file, '.php');
            echo "Migrating: $name... ";
            
            $migration = require $file;
            if (isset($migration['up'])) {
                $migration['up']($pdo);
                $stmt = $pdo->prepare("INSERT INTO _migrations (migration, batch) VALUES (?, ?)");
                $stmt->execute([$name, $batch]);
                echo "SUCCESS\n";
            } else {
                echo "FAILED (No 'up' method)\n";
            }
        }
        echo "\nBatch $batch complete.\n";
    }

} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
}

echo "\nDONE. PLEASE DELETE THIS FILE IMMEDIATELY!\n";
