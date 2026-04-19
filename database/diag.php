<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getPdo();

echo "--- Table structure for targets ---\n";
foreach (['att_students', 'assembly_students', 'beh_students', 'cb_students'] as $t) {
    echo "\nTable: $t\n";
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM $t")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo "  {$c['Field']} | {$c['Type']} | PK:".($c['Key']=='PRI'?'Yes':'No')." | Extra:{$c['Extra']}\n";
        }
    } catch (Exception $e) { echo "  Error: " . $e->getMessage() . "\n"; }
}

echo "\n--- Invalid students in att_students ---\n";
$bad = $pdo->query("SELECT * FROM att_students WHERE student_id IS NULL OR TRIM(student_id) = ''")->fetchAll(PDO::FETCH_ASSOC);
foreach ($bad as $b) {
    echo "  ID: {$b['id']} | Name: {$b['name']} | Class: {$b['classroom']} | SID: '{$b['student_id']}'\n";
}

echo "\n--- Duplicate IDs in att_students ---\n";
$dupes = $pdo->query("SELECT student_id, COUNT(*) as c FROM att_students GROUP BY student_id HAVING c > 1")->fetchAll(PDO::FETCH_ASSOC);
foreach ($dupes as $d) {
    if (trim($d['student_id']) !== '') echo "  SID: '{$d['student_id']}' | Count: {$d['c']}\n";
}
