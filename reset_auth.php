<?php
/**
 * reset_auth.php — Critical Utility to Fix Login Issues
 * Synchronizes llw_users table and resets key account passwords.
 */
require_once 'config/database.php';

try {
    $pdo = getPdo();
    echo "<h1>Authentication Sync Utility</h1>";

    $password = "123456";
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // 1. Reset admin_llw (Super Admin)
    $stmt = $pdo->prepare("UPDATE llw_users SET password = ?, status = 'active' WHERE username = 'admin_llw'");
    $stmt->execute([$hashed]);
    echo "<p style='color:green;'>✔️ Reset <b>admin_llw</b> password to <b>123456</b></p>";

    // 2. Reset admin (CB Admin)
    $stmt = $pdo->prepare("UPDATE llw_users SET password = ?, status = 'active' WHERE username = 'admin'");
    $stmt->execute([$hashed]);
    echo "<p style='color:green;'>✔️ Reset <b>admin</b> password to <b>123456</b></p>";

    // 3. Reset teacher1 (Att Teacher)
    $stmt = $pdo->prepare("UPDATE llw_users SET password = ?, status = 'active' WHERE username = 'teacher1'");
    $stmt->execute([$hashed]);
    echo "<p style='color:green;'>✔️ Reset <b>teacher1</b> password to <b>123456</b></p>";

    echo "<h3>Auth System is now ready. Please use these credentials to test.</h3>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";

} catch (Exception $e) {
    echo "<h1 style='color:red;'>Error: " . $e->getMessage() . "</h1>";
}
?>
