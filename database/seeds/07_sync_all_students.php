<?php
/**
 * Seed: Sync & Standardize All Students (Global Unification)
 * - ปรับปรุง student_id ทุกตารางให้เป็น 5 หลัก (เช่น 4100 -> 04100)
 * - คัดลอกรายชื่อจาก att_students ไปยัง assembly_students และ beh_students
 * - ปรับปรุงข้อมูลในตาราง attendance, records ให้ตรงตามรหัสใหม่
 */
return [
    'run' => function (PDO $pdo) {
        $pdo->beginTransaction();
        try {
            echo "    Starting Global Student Data Unification...\n";

            // 1. ฟังก์ชันช่วยปรับรหัสเป็น 5 หลัก
            $normalize = function($sid) {
                $sid = trim($sid);
                return is_numeric($sid) ? str_pad($sid, 5, '0', STR_PAD_LEFT) : $sid;
            };

            // 2. ปรับปรุงตารางหลัก (Master Tables)
            $tables = [
                'att_students'      => ['id_col' => 'student_id'],
                'assembly_students' => ['id_col' => 'student_id'],
                'beh_students'      => ['id_col' => 'student_id']
            ];

            foreach ($tables as $table => $conf) {
                $col = $conf['id_col'];
                $rows = $pdo->query("SELECT $col FROM $table")->fetchAll(PDO::FETCH_ASSOC);
                $stmt = $pdo->prepare("UPDATE $table SET $col = ? WHERE $col = ?");
                foreach ($rows as $r) {
                    $old = $r[$col];
                    $new = $normalize($old);
                    if ($old !== $new) {
                        try {
                            $stmt->execute([$new, $old]);
                        } catch (Exception $e) {
                            // กรณีชน UNIQUE ให้ลบตัวเก่า
                            $pdo->prepare("DELETE FROM $table WHERE $col = ?")->execute([$old]);
                        }
                    }
                }
                echo "    ✓ Normalized IDs in $table\n";
            }

            // 3. ปรับปรุงตารางบันทึก (Record Tables)
            $recordTables = [
                'att_attendance'      => 'student_id',
                'assembly_attendance' => 'student_id',
                'beh_records'         => 'student_id'
            ];

            foreach ($recordTables as $table => $col) {
                $rows = $pdo->query("SELECT DISTINCT $col FROM $table")->fetchAll(PDO::FETCH_COLUMN);
                $stmt = $pdo->prepare("UPDATE $table SET $col = ? WHERE $col = ?");
                foreach ($rows as $old) {
                    $new = $normalize($old);
                    if ($old !== $new) {
                        $stmt->execute([$new, $old]);
                    }
                }
                echo "    ✓ Updated foreign IDs in $table\n";
            }

            // 4. คัดลอกรายชื่อจาก att_students (Master) ไปยังโต๊ะอื่นๆ
            $masterStudents = $pdo->query("SELECT student_id, name, classroom FROM att_students")->fetchAll(PDO::FETCH_ASSOC);
            
            // Sync to assembly_students
            $insAssembly = $pdo->prepare("INSERT IGNORE INTO assembly_students (student_id, name, classroom) VALUES (?, ?, ?)");
            // Sync to beh_students
            $insBeh = $pdo->prepare("INSERT IGNORE INTO beh_students (student_id, name, level, room) VALUES (?, ?, ?, ?)");

            foreach ($masterStudents as $s) {
                $sid = $normalize($s['student_id']);
                $name = $s['name'];
                $classroom = $s['classroom'];
                $parts = explode('/', $classroom);
                $level = $parts[0] ?? '';
                $room = $parts[1] ?? '';

                $insAssembly->execute([$sid, $name, $classroom]);
                $insBeh->execute([$sid, $name, $level, $room]);
            }

            echo "    ✓ Synced " . count($masterStudents) . " students across all systems.\n";

            $pdo->commit();
            echo "    Global Unification Completed Successfully!\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "    Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
];
