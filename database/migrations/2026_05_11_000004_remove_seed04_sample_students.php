<?php
/**
 * Migration: Remove seed04 sample student data from att_students
 * IDs from database/seeds/04_assembly_sample_students.php ที่หลุดเข้า att_students
 */
return [
    'up' => function (PDO $pdo) {
        $sampleIds = [
            '10101','10102','10103','10104','10105',
            '10201','10202','10203','10204','10205',
            '20101','20102','20103','20104','20105',
            '20201','20202','20203','20204','20205',
            '30101','30102','30103','30104','30105',
            '30201','30202','30203','30204','30205',
            '40101','40102','40103','40104','40105',
            '40201','40202','40203','40204','40205',
            '50101','50102','50103','50104','50105',
            '50201','50202','50203','50204','50205',
            '60101','60102','60103','60104','60105',
            '60201','60202','60203','60204','60205',
        ];

        $placeholders = implode(',', array_fill(0, count($sampleIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM att_students WHERE student_id IN ($placeholders)");
        $stmt->execute($sampleIds);
        $deleted = $stmt->rowCount();

        echo "✅ ลบนักเรียนตัวอย่าง seed04: $deleted รายการ\n";
    },

    'down' => function (PDO $pdo) {
        // ไม่ revert
    },
];
