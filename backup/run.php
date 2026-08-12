<?php
/**
 * Database backup — run via cron only, never through the browser.
 * cPanel/DirectAdmin "Cron Jobs" command:
 *   php /full/path/to/backup/run.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line (cron).\n");
}

require_once __DIR__ . '/../config/database.php';

set_time_limit(0);

$RETENTION_DAYS = 14;
$BATCH_SIZE     = 500;

$backupDir = __DIR__ . '/storage';
if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true)) {
    fwrite(STDERR, "Cannot create backup directory: $backupDir\n");
    exit(1);
}

$logFile = $backupDir . '/backup.log';
function backup_log(string $logFile, string $msg): void {
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

$pdo       = getPdo();
$timestamp = date('Y-m-d_His');
$outFile   = $backupDir . '/backup_' . DB_NAME . "_{$timestamp}.sql.gz";

$gz = gzopen($outFile, 'w9');
if (!$gz) {
    backup_log($logFile, "ERROR: cannot open output file $outFile");
    fwrite(STDERR, "Cannot open output file: $outFile\n");
    exit(1);
}

try {
    gzwrite($gz, "-- LLW System database backup\n-- Database: " . DB_NAME . "\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
    gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    // Base tables and views need different handling (views have no rows of their
    // own, and must be created after the tables they select from). Order base
    // tables first so a restore doesn't fail on a view referencing a missing table.
    $objects = $pdo->query("
        SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        ORDER BY (TABLE_TYPE = 'VIEW'), TABLE_NAME
    ")->fetchAll();

    foreach ($objects as $obj) {
        $table  = $obj['TABLE_NAME'];
        $isView = $obj['TABLE_TYPE'] === 'VIEW';

        if ($isView) {
            $createRow = $pdo->query("SHOW CREATE VIEW `{$table}`")->fetch();
            gzwrite($gz, "\n--\n-- View: {$table}\n--\n");
            gzwrite($gz, "DROP VIEW IF EXISTS `{$table}`;\n");
            gzwrite($gz, $createRow['Create View'] . ";\n\n");
            continue;
        }

        $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
        gzwrite($gz, "\n--\n-- Table: {$table}\n--\n");
        gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n");
        gzwrite($gz, $createRow['Create Table'] . ";\n\n");

        $rowCount = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        for ($offset = 0; $offset < $rowCount; $offset += $BATCH_SIZE) {
            $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT {$BATCH_SIZE} OFFSET {$offset}")->fetchAll();
            if (empty($rows)) break;
            $cols    = '`' . implode('`,`', array_keys($rows[0])) . '`';
            $valSets = [];
            foreach ($rows as $row) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                $valSets[] = '(' . implode(',', $vals) . ')';
            }
            gzwrite($gz, "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $valSets) . ";\n");
        }
    }

    gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($gz);

    $sizeKb = round(filesize($outFile) / 1024, 1);
    backup_log($logFile, 'OK — ' . basename($outFile) . " ({$sizeKb} KB, " . count($objects) . ' objects)');
    echo 'Backup complete: ' . basename($outFile) . " ({$sizeKb} KB)\n";
} catch (Throwable $e) {
    if (is_resource($gz)) gzclose($gz);
    if (file_exists($outFile)) @unlink($outFile);
    backup_log($logFile, 'ERROR: ' . $e->getMessage());
    error_log('[LLW backup] ' . $e->getMessage());
    fwrite(STDERR, 'Backup failed: ' . $e->getMessage() . "\n");
    exit(1);
}

// Rotate: delete backups older than the retention window
$cutoff  = time() - ($RETENTION_DAYS * 86400);
$deleted = 0;
foreach (glob($backupDir . '/backup_*.sql.gz') as $file) {
    if (filemtime($file) < $cutoff) {
        @unlink($file);
        $deleted++;
    }
}
if ($deleted > 0) backup_log($logFile, "ลบไฟล์สำรองที่เก่ากว่า {$RETENTION_DAYS} วัน จำนวน {$deleted} ไฟล์");
