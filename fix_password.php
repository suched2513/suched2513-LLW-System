<?php
require_once 'config.php';

// รหัสผ่านที่ต้องการตั้งใหม่
$new_password = "123456";
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h3>ระบบตรวจสอบและแก้ไขรหัสผ่าน WFH:LLW</h3>";
echo "รหัสผ่านที่ต้องการ: <b>$new_password</b><br>";
echo "Bcrypt Hash ที่สร้างได้: <code>$hashed_password</code><br><br>";

// อัปเดตรหัสผ่านสำหรับ admin_llw และ user001
$sql = "UPDATE wfh_users SET password = '$hashed_password' WHERE username IN ('admin_llw', 'user001')";


if ($conn->query($sql) === TRUE) {
    echo "<div style='color: green; padding: 10px; border: 1px solid green;'>";
    echo "<b>สำเร็จ!</b> อัปเดตรหัสผ่านในฐานข้อมูลเรียบร้อยแล้ว<br>";
    echo "ตอนนี้คุณสามารถเข้าสู่ระบบด้วย Username: <b>admin_llw</b> และรหัสผ่าน: <b>123456</b> ได้ทันที";
    echo "</div>";
    echo "<br><a href='index.php'>กลับไปยังหน้าเข้าสู่ระบบ</a>";
} else {
    echo "<div style='color: red;'>";
    echo "เกิดข้อผิดพลาดในการอัปเดต: " . $conn->error;
    echo "</div>";
}
?>
