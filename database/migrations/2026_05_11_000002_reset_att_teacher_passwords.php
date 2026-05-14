<?php
/**
 * Migration: (NEUTRALIZED) Reset att_teacher passwords
 * ปิดการใช้งานถาวรเพื่อป้องกันการรีเซ็ตรหัสผ่านครูโดยไม่ตั้งใจ
 */
return [
    'up' => function (PDO $pdo) {
        // ทำการข้าม (Skip) การรีเซ็ตรหัสผ่านเพื่อความปลอดภัย
        echo "ℹ️ Migration รีเซ็ตรหัสผ่านถูกปิดใช้งานถาวรเพื่อความปลอดภัยของข้อมูลผู้ใช้\n";
    },
    'down' => function (PDO $pdo) {
        // No revert
    },
];
