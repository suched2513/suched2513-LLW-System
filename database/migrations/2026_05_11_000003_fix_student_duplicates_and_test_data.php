<?php
/**
 * Migration: Fix duplicate student IDs and remove test/example data
 * 1. Remove test students (classroom NOT LIKE 'ม.%')
 * 2. Merge duplicate IDs caused by padding mismatch (4702 vs 04702)
 */
return [
    'up' => function (PDO $pdo) {
        $pdo->beginTransaction();

        // ── 1. ลบนักเรียนตัวอย่าง (classroom ไม่ใช่รูปแบบ ม.X/Y) ──
        $stmt = $pdo->query("SELECT student_id FROM att_students WHERE classroom NOT LIKE 'ม.%'");
        $testIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($testIds) {
            $placeholders = implode(',', array_fill(0, count($testIds), '?'));
            $pdo->prepare("DELETE FROM att_students WHERE student_id IN ($placeholders)")->execute($testIds);
            echo "✅ ลบนักเรียนตัวอย่าง " . count($testIds) . " รายการ\n";
        } else {
            echo "ℹ️ ไม่พบนักเรียนตัวอย่าง\n";
        }

        // ── 2. หา ID ที่ยังไม่ได้ pad (< 5 หลัก, เป็นตัวเลขล้วน) ──
        $unpadded = $pdo->query("
            SELECT student_id FROM att_students
            WHERE student_id REGEXP '^[0-9]+$'
              AND CHAR_LENGTH(student_id) < 5
        ")->fetchAll(PDO::FETCH_COLUMN);

        // ตารางที่มี student_id เป็น FK ต้องอัปเดตด้วย
        $refTables = ['att_attendance', 'club_registrations', 'club_attendance', 'club_results',
                      'assembly_attendance', 'beh_records'];

        $stmtCheck  = $pdo->prepare("SELECT COUNT(*) FROM att_students WHERE student_id = ?");
        $stmtUpdate = $pdo->prepare("UPDATE att_students SET student_id = ? WHERE student_id = ?");
        $stmtDelete = $pdo->prepare("DELETE FROM att_students WHERE student_id = ?");

        $merged = 0; $renamed = 0;

        foreach ($unpadded as $oldId) {
            $newId = str_pad($oldId, 5, '0', STR_PAD_LEFT);

            $stmtCheck->execute([$newId]);
            $paddedExists = (int) $stmtCheck->fetchColumn() > 0;

            if ($paddedExists) {
                // ─ มี padded อยู่แล้ว → ลบ unpadded ทิ้ง ─
                $stmtDelete->execute([$oldId]);
                $merged++;
            } else {
                // ─ ยังไม่มี padded → เปลี่ยนชื่อ + อัปเดต FK ─
                foreach ($refTables as $tbl) {
                    $exists = $pdo->query("SHOW TABLES LIKE '$tbl'")->rowCount();
                    if ($exists > 0) {
                        $pdo->prepare("UPDATE $tbl SET student_id = ? WHERE student_id = ?")
                            ->execute([$newId, $oldId]);
                    }
                }
                try {
                    $stmtUpdate->execute([$newId, $oldId]);
                    $renamed++;
                } catch (Exception $e) {
                    $stmtDelete->execute([$oldId]);
                    $merged++;
                }
            }
        }

        echo "✅ รวม (ลบ unpadded ซ้ำ): $merged รายการ\n";
        echo "✅ เปลี่ยนชื่อ unpadded → padded: $renamed รายการ\n";

        $pdo->commit();
    },

    'down' => function (PDO $pdo) {
        // ไม่ revert — ป้องกัน data loss
    },
];
