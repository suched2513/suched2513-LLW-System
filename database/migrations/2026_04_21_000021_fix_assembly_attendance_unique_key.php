<?php
/**
 * Migration: fix_assembly_attendance_unique_key
 * Created: 2026-04-21
 *
 * ปัญหา: UNIQUE KEY เดิม (date, student_id) ไม่มี classroom
 * ทำให้ student_id เดียวกันไม่สามารถมีหลาย classroom ได้
 * และ ON DUPLICATE KEY UPDATE อาจ overwrite classroom field ผิด
 *
 * แก้ไข: เปลี่ยน UNIQUE KEY เป็น (date, student_id, classroom)
 * เพื่อให้แต่ละห้องมี record ของตัวเองได้อย่างถูกต้อง
 */
return [
    'up' => function (PDO $pdo) {
        // 1. ลบ UNIQUE KEY เดิม (date, student_id)
        try {
            $pdo->exec("ALTER TABLE `assembly_attendance` DROP INDEX `uq_asma_date_student`");
        } catch (Exception $e) {
            // อาจไม่มี index นี้ ปล่อยผ่าน
            error_log('Migration: drop index skipped: ' . $e->getMessage());
        }

        // 2. ลบ rows ที่ duplicate (date + student_id) โดยเก็บ record ล่าสุดของแต่ละ student ในแต่ละวัน
        // ก่อนสร้าง unique key ใหม่ ต้อง clean ข้อมูล duplicate เก่าออกก่อน
        $pdo->exec("
            DELETE a1 FROM assembly_attendance a1
            INNER JOIN assembly_attendance a2
            ON a1.date = a2.date
            AND a1.student_id = a2.student_id
            AND a1.classroom = a2.classroom
            AND a1.id < a2.id
        ");

        // 3. สร้าง UNIQUE KEY ใหม่ที่รวม classroom
        $pdo->exec("ALTER TABLE `assembly_attendance`
            ADD UNIQUE KEY `uq_asma_date_student_class` (`date`, `student_id`, `classroom`)
        ");
    },

    'down' => function (PDO $pdo) {
        // Revert: ลบ key ใหม่ แล้วสร้าง key เดิมกลับ
        try {
            $pdo->exec("ALTER TABLE `assembly_attendance` DROP INDEX `uq_asma_date_student_class`");
        } catch (Exception $e) {
            error_log('Migration down: drop index skipped: ' . $e->getMessage());
        }

        $pdo->exec("ALTER TABLE `assembly_attendance`
            ADD UNIQUE KEY `uq_asma_date_student` (`date`, `student_id`)
        ");
    },
];
