<?php
require_once 'attendance_system/db.php';
try {
    $pdo = getPdo();
    $tables = ['att_students', 'beh_students', 'assembly_students', 'cb_students'];
    $data = [];
    foreach ($tables as $t) {
        $count = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        $sample = $pdo->query("SELECT * FROM $t LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
        $data[$t] = ['count' => $count, 'sample' => $sample];
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
