<?php
require_once 'school_project/config/db.php';
$pdo = getDB();
$migration = require 'database/migrations/2026_04_28_000047_upgrade_project_requests_workflow.php';
try {
    $result = $migration['up']($pdo);
    echo "Migration Success: " . $result . "\n";
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
