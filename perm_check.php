<?php
header('Content-Type: text/plain; charset=utf-8');

$dirs = [
    'uploads/beh_deeds',
    'uploads/profiles',
    'uploads/behavior'
];

foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    echo "Directory: $dir\n";
    if (!is_dir($path)) {
        echo " - ERROR: Directory does not exist. Attempting to create...\n";
        if (mkdir($path, 0777, true)) {
            echo " - SUCCESS: Created.\n";
        } else {
            echo " - ERROR: Failed to create.\n";
        }
    } else {
        echo " - Info: Exists.\n";
    }
    
    echo " - Writable: " . (is_writable($path) ? "YES" : "NO") . "\n";
    
    // Try a test write
    $testFile = $path . '/test_' . time() . '.txt';
    if (@file_put_contents($testFile, 'test')) {
        echo " - Test Write: SUCCESS\n";
        unlink($testFile);
    } else {
        echo " - Test Write: FAILURE\n";
    }
    echo "---------------------------\n";
}
