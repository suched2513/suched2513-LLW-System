<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getPdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Running Migration: 2026_04_19_000019_add_status_and_mapping.php\n";

    // 1. Add status to beh_records
    try {
        $pdo->exec("ALTER TABLE beh_records ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER image_path");
        echo "[SUCCESS] Added 'status' column to beh_records\n";
    } catch (Exception $e) {
        echo "[SKIPPED] 'status' column might already exist\n";
    }

    // 2. Create beh_advisors
    $pdo->exec("CREATE TABLE IF NOT EXISTS beh_advisors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        level VARCHAR(20) NOT NULL,
        room VARCHAR(20) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (level, room)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[SUCCESS] Created table beh_advisors\n";

    // 3. Fix potential teacher id mismatch
    // (Optional: ensure llw_user_id exists if missing)

    echo "\nMigration Complete.\n";

} catch (Exception $e) {
    echo "\n[ERROR] " . $e->getMessage();
}
