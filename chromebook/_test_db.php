<?php
if (php_sapi_name() !== 'cli') exit('CLI only');
foreach (['', 'root', 'laragon'] as $pass) {
    try {
        $pdo = new PDO('mysql:host=localhost;port=3306;charset=utf8mb4', 'root', $pass);
        echo "OK pass='$pass'\n";
        echo $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN, 0)[0] ?? '';
        echo "\n";
        break;
    } catch (Exception $e) {
        echo "FAIL pass='$pass': " . $e->getMessage() . "\n";
    }
}
