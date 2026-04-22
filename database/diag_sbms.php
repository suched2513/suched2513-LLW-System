<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getPdo();

$tables = ['sbms_fiscal_years', 'sbms_budgets', 'sbms_projects', 'sbms_disbursements'];

foreach ($tables as $table) {
    echo "<h3>Table: $table</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
}
