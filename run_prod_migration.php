<?php
/**
 * Production one-off migration execution runner
 */
header('Content-Type: text/plain; charset=utf-8');

try {
    require_once __DIR__ . '/config/database.php';
    $pdo = getPdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Checking order_no column in lms_units...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM lms_units LIKE 'order_no'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "Adding order_no column...\n";
        $pdo->exec("ALTER TABLE lms_units ADD COLUMN order_no INT NOT NULL DEFAULT 1");
        $pdo->exec("UPDATE lms_units SET order_no = unit_number WHERE unit_number IS NOT NULL");
        echo "Column order_no added and synchronized with unit_number successfully.\n";
    } else {
        echo "Column order_no already exists. Synchronizing values...\n";
        $pdo->exec("UPDATE lms_units SET order_no = unit_number WHERE unit_number IS NOT NULL");
        echo "Values synchronized successfully.\n";
    }

    echo "Logging migration run...\n";
    // We add the migration name to _migrations table so run_pending.php knows it's already run!
    $stmt2 = $pdo->prepare("INSERT IGNORE INTO _migrations (migration, batch) VALUES ('2026_07_19_000000_restore_order_no_to_lms_units', 999)");
    $stmt2->execute();
    echo "Migration record saved in _migrations.\n";

    echo "Migration completed successfully on production.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

unlink(__FILE__); // self-destruct!
