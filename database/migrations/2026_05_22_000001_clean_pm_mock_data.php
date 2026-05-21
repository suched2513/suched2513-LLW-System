<?php
/**
 * Migration: clean_pm_mock_data
 * ลบข้อมูลตัวอย่าง (Mock Data) ทั้งหมดในระบบประชุมผู้ปกครอง
 * เพื่อเตรียมระบบให้พร้อมเชื่อมข้อมูลจริง
 */
return [
    'up' => function (PDO $pdo) {
        // ลบผู้ใช้ตัวอย่าง
        $pdo->exec("DELETE FROM pm_users WHERE username IN ('admin_user', 'director_user', 'teacher_user')");
        
        // ลบห้องเรียนตัวอย่าง
        $pdo->exec("DELETE FROM pm_classrooms WHERE teacher_name IN (
            'สมชาย ใจดี', 
            'สมศรี รักษ์ดี', 
            'ประยุทธ์ สู้ๆ', 
            'ประวิตร วงษ์สวย', 
            'อนุทิน กัญชาดี', 
            'พิธา ก้าวหน้า'
        )");
    },
    'down' => function (PDO $pdo) {
        // เพื่อความปลอดภัย ไม่ต้องย้อนกลับข้อมูลตัวอย่างเดิม
    }
];
