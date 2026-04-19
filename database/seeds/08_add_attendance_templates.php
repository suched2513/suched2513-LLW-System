<?php
/**
 * Seed: Add Attendance Violation Templates
 */
return [
    'run' => function (PDO $pdo) {
        $pdo->beginTransaction();
        try {
            $templates = [
                ['type' => 'ความผิด', 'name' => 'ขาดการเข้าแถว', 'score' => 5],
                ['type' => 'ความผิด', 'name' => 'โดดเข้าแถว/ไม่เข้าแถว', 'score' => 10],
                ['type' => 'ความผิด', 'name' => 'แต่งกายผิดระเบียบ (จากการเข้าแถว)', 'score' => 5]
            ];

            $stmt = $pdo->prepare("INSERT IGNORE INTO beh_templates (type, name, score, status) VALUES (?, ?, ?, 'active')");
            foreach ($templates as $t) {
                $stmt->execute([$t['type'], $t['name'], $t['score']]);
            }

            $pdo->commit();
            echo "    ✓ Attendance violation templates added.\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
];
