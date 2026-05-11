<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (($_SESSION['llw_role'] ?? '') !== 'super_admin') {
    http_response_code(403); die('Forbidden');
}

$pdo  = getPdo();
$file = __DIR__ . '/database/migrations/2026_05_11_000004_remove_seed04_sample_students.php';

echo '<pre style="font-family:monospace;font-size:14px;padding:20px">';
echo "Running: 2026_05_11_000004_remove_seed04_sample_students\n\n";
flush(); ob_flush();

try {
    if (!file_exists($file)) {
        echo "ERROR: migration file not found\n";
    } else {
        $migration = require $file;
        if (!is_array($migration) || !isset($migration['up'])) {
            echo "ERROR: invalid migration format\n";
        } else {
            $migration['up']($pdo);
            echo "\nMigration SUCCESS\n";
        }
    }
} catch (Throwable $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo '</pre>';
echo '<p style="color:red;font-weight:bold">DELETE THIS FILE NOW</p>';
