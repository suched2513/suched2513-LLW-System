<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getPdo();
$migration = require __DIR__ . '/../database/migrations/2026_05_08_000001_add_report_note_to_duty_reports.php';
$migration['up']($pdo);
echo "Migration successful\n";
