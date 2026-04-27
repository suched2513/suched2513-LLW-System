<?php
/**
 * Seed: school_project signatories
 * รายชื่อผู้ลงนามในเอกสาร (สามารถแก้ไขได้ผ่านหน้า /admin/signatories.php)
 */
return [
    'run' => function (PDO $pdo) {
        $exists = $pdo->query("SHOW TABLES LIKE 'signatories'")->fetchColumn();
        if (!$exists) return;

        // ตรวจว่ามีข้อมูลอยู่แล้วหรือไม่ (safe re-run)
        $count = $pdo->query("SELECT COUNT(*) FROM `signatories`")->fetchColumn();
        if ($count > 0) return;

        $signatories = [
            ['ผู้อำนวยการโรงเรียน',          '',                      'ผู้อำนวยการ',         1, 1],
            ['รองผู้อำนวยการฝ่ายบริหารงบประมาณ', '',                  'รองผู้อำนวยการ',     2, 1],
            ['หัวหน้างานแผนงานและงบประมาณ',   '',                      'ครู',                 3, 1],
            ['หัวหน้าเจ้าหน้าที่พัสดุ',         '',                      'ครู',                 4, 1],
            ['เจ้าหน้าที่พัสดุ',               '',                      'ครู',                 5, 1],
        ];

        $stmt = $pdo->prepare("INSERT INTO `signatories` (`role_label`, `full_name`, `position`, `order_no`, `is_active`)
                                VALUES (?, ?, ?, ?, ?)");
        foreach ($signatories as $row) {
            $stmt->execute($row);
        }
    },
];
