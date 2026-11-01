<?php
/**
 * Migration: 2026_11_01_000003_fix_classrooms_m14_to_m24
 * Created: 2026-11-01
 *
 * แก้ไขความถูกต้องของโครงสร้างห้องเรียนทั้ง 19 ห้องเรียนของโรงเรียนละลมวิทยา:
 * - ยกเลิก/ลบห้อง ม.1/4 ออกจากระบบ ปพ.5 (ระดับชั้น ม.1 มีเพียง 3 ห้อง: ม.1/1 - ม.1/3 ไม่มี ม.1/4)
 * - เพิ่มห้อง ม.2/4 (ระดับชั้น ม.2 มี 4 ห้อง: ม.2/1 - ม.2/4) ใน pp5_classrooms
 * - ซิงค์นักเรียนชั้น ม.2/4 จาก att_students เข้าสู่ pp5_classroom_students
 * - จัดการสร้างรายวิชา ปพ.5 ประจำห้อง ม.2/4 ใน pp5_courses ให้ครบถ้วนตามหลักสูตร
 */

return [
    'up' => function (PDO $pdo) {
        $curYear = function_exists('llw_current_academic_year') ? llw_current_academic_year() : 2569;
        $curSem  = function_exists('llw_current_semester') ? llw_current_semester() : 2;

        // ─── 1. ลบห้อง ม.1/4 ที่ไม่มีอยู่จริงออกจาก pp5_classrooms ───
        try {
            $m14Rooms = $pdo->query("SELECT `id` FROM `pp5_classrooms` WHERE `classroom_name` = 'ม.1/4'")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($m14Rooms)) {
                $inM14 = implode(',', array_map('intval', $m14Rooms));

                // ลบรายวิชาของ ม.1/4 ใน pp5_courses (ถ้ามี)
                $m14Courses = $pdo->query("SELECT `id` FROM `pp5_courses` WHERE `classroom_id` IN ($inM14)")->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($m14Courses)) {
                    $inCourses = implode(',', array_map('intval', $m14Courses));
                    $pdo->exec("DELETE FROM `pp5_scores` WHERE `course_id` IN ($inCourses)");
                    $pdo->exec("DELETE FROM `pp5_score_items` WHERE `course_id` IN ($inCourses)");
                    $pdo->exec("DELETE FROM `pp5_course_approvals` WHERE `course_id` IN ($inCourses)");
                    $pdo->exec("DELETE FROM `pp5_characteristic_assessments` WHERE `course_id` IN ($inCourses)");
                    $pdo->exec("DELETE FROM `pp5_rtw_assessments` WHERE `course_id` IN ($inCourses)");
                    $pdo->exec("DELETE FROM `pp5_competency_assessments` WHERE `course_id` IN ($inCourses)");
                    $pdo->exec("DELETE FROM `pp5_courses` WHERE `id` IN ($inCourses)");
                }

                $pdo->exec("DELETE FROM `pp5_classroom_students` WHERE `classroom_id` IN ($inM14)");
                $pdo->exec("DELETE FROM `pp5_classrooms` WHERE `id` IN ($inM14)");
            }
        } catch (\Throwable $e) {
            error_log('[PP5 Migration 000003] cleanup m1/4: ' . $e->getMessage());
        }

        // ─── 2. เพิ่มห้อง ม.2/4 ใน pp5_classrooms (ภาคเรียนที่ 1 และ 2) ───
        try {
            // ดึงครูที่ปรึกษาของ ม.2/4 จาก llw_class_advisors หรือ assembly_classrooms
            $advisorId = null;
            $coAdvisorId = null;

            $hasAdvTbl = (bool)$pdo->query("SHOW TABLES LIKE 'llw_class_advisors'")->fetchColumn();
            if ($hasAdvTbl) {
                $advSt = $pdo->prepare("SELECT `user_id` FROM `llw_class_advisors` WHERE `classroom` = 'ม.2/4' ORDER BY `id` ASC");
                $advSt->execute();
                $uids = $advSt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($uids)) {
                    $advisorId = (int)$uids[0];
                    if (isset($uids[1])) {
                        $coAdvisorId = (int)$uids[1];
                    }
                }
            }

            if (!$advisorId) {
                $hasAsmTbl = (bool)$pdo->query("SHOW TABLES LIKE 'assembly_classrooms'")->fetchColumn();
                if ($hasAsmTbl) {
                    $asmSt = $pdo->prepare("SELECT `llw_user_id` FROM `assembly_classrooms` WHERE `classroom` = 'ม.2/4' AND `llw_user_id` > 0 LIMIT 1");
                    $asmSt->execute();
                    $asmUid = (int)$asmSt->fetchColumn();
                    if ($asmUid > 0) {
                        $advisorId = $asmUid;
                    }
                }
            }

            // เพิ่ม ม.2/4 ให้ทุกภาคเรียนที่มีอยู่ในระบบ (อย่างน้อย ภาค 1 และ ภาค 2 ของปีปัจจุบัน)
            $terms = $pdo->query("SELECT DISTINCT `academic_year`, `semester` FROM `pp5_classrooms`")->fetchAll(PDO::FETCH_ASSOC);
            if (empty($terms)) {
                $terms = [
                    ['academic_year' => $curYear, 'semester' => 1],
                    ['academic_year' => $curYear, 'semester' => 2],
                ];
            }

            $stmtInsCls = $pdo->prepare("
                INSERT INTO `pp5_classrooms` (
                    `academic_year`, `semester`, `grade_level`, `room_no`, `classroom_name`,
                    `study_plan`, `advisor_user_id`, `co_advisor_user_id`
                ) VALUES (?, ?, 2, 4, 'ม.2/4', 'ทั่วไป', ?, ?)
                ON DUPLICATE KEY UPDATE
                    `study_plan`         = VALUES(`study_plan`),
                    `advisor_user_id`    = COALESCE(`advisor_user_id`, VALUES(`advisor_user_id`)),
                    `co_advisor_user_id` = COALESCE(`co_advisor_user_id`, VALUES(`co_advisor_user_id`))
            ");

            foreach ($terms as $t) {
                $stmtInsCls->execute([(int)$t['academic_year'], (int)$t['semester'], $advisorId, $coAdvisorId]);
            }
        } catch (\Throwable $e) {
            error_log('[PP5 Migration 000003] insert m2/4 classroom: ' . $e->getMessage());
        }

        // ─── 3. ซิงค์นักเรียน ม.2/4 จาก att_students เข้า pp5_classroom_students ───
        try {
            $m24ClsList = $pdo->query("SELECT `id`, `academic_year`, `semester` FROM `pp5_classrooms` WHERE `classroom_name` = 'ม.2/4'")->fetchAll(PDO::FETCH_ASSOC);

            // ดึงนักเรียน ม.2/4 จาก att_students
            $studentsM24 = $pdo->query("
                SELECT `id`, `student_id`, `name`, COALESCE(`student_number`, 1) AS `student_number`
                FROM `att_students`
                WHERE `classroom` = 'ม.2/4' AND `status` = 'active'
                ORDER BY CAST(`student_number` AS UNSIGNED) ASC, `student_id` ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $stmtInsCs = $pdo->prepare("
                INSERT INTO `pp5_classroom_students` (`classroom_id`, `student_id`, `student_code`, `student_number`, `status`)
                VALUES (?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE
                    `student_code`   = VALUES(`student_code`),
                    `student_number` = VALUES(`student_number`),
                    `status`         = 'active'
            ");

            foreach ($m24ClsList as $cls) {
                $clsId = (int)$cls['id'];
                $num = 1;
                foreach ($studentsM24 as $st) {
                    $stdCode = str_pad(trim((string)$st['student_id']), 5, '0', STR_PAD_LEFT);
                    $stdNum  = (int)$st['student_number'] > 0 ? (int)$st['student_number'] : $num++;
                    $stmtInsCs->execute([$clsId, (int)$st['id'], $stdCode, $stdNum]);
                }
            }
        } catch (\Throwable $e) {
            error_log('[PP5 Migration 000003] sync m2/4 students: ' . $e->getMessage());
        }

        // ─── 4. สร้างรายวิชา ปพ.5 (pp5_courses) สำหรับห้อง ม.2/4 ───
        try {
            // ดึงห้อง ม.2/4 ของภาคเรียนปัจจุบัน
            $stM24Now = $pdo->prepare("SELECT `id` FROM `pp5_classrooms` WHERE `classroom_name` = 'ม.2/4' AND `academic_year` = ? AND `semester` = ? LIMIT 1");
            $stM24Now->execute([$curYear, $curSem]);
            $m24ClsIdNow = (int)$stM24Now->fetchColumn();

            if ($m24ClsIdNow > 0) {
                // ดึงรายวิชาของห้อง ม.2/3 ในภาคเรียนเดียวกัน เพื่อใช้เป็นโครงสร้างวิชาของ ม.2
                $stM23 = $pdo->prepare("SELECT `id` FROM `pp5_classrooms` WHERE `classroom_name` = 'ม.2/3' AND `academic_year` = ? AND `semester` = ? LIMIT 1");
                $stM23->execute([$curYear, $curSem]);
                $m23ClsId = (int)$stM23->fetchColumn();

                if ($m23ClsId > 0) {
                    $coursesM23 = $pdo->prepare("
                        SELECT `subject_id`, `teacher_user_id`, `ratio_collect`, `ratio_final`
                        FROM `pp5_courses`
                        WHERE `classroom_id` = ? AND `academic_year` = ? AND `semester` = ?
                    ");
                    $coursesM23->execute([$m23ClsId, $curYear, $curSem]);
                    $m23List = $coursesM23->fetchAll(PDO::FETCH_ASSOC);

                    $stmtInsCourse = $pdo->prepare("
                        INSERT INTO `pp5_courses` (
                            `academic_year`, `semester`, `subject_id`, `classroom_id`,
                            `teacher_user_id`, `ratio_collect`, `ratio_final`, `status`
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')
                        ON DUPLICATE KEY UPDATE
                            `teacher_user_id` = VALUES(`teacher_user_id`)
                    ");

                    foreach ($m23List as $c) {
                        $stmtInsCourse->execute([
                            $curYear,
                            $curSem,
                            (int)$c['subject_id'],
                            $m24ClsIdNow,
                            (int)$c['teacher_user_id'],
                            (int)$c['ratio_collect'],
                            (int)$c['ratio_final'],
                        ]);
                    }
                }

                // ซิงค์วิชาเฉพาะของ ม.2/4 จาก att_subjects (ถ้ามีระบุครูไว้)
                $hasAttSubj = (bool)$pdo->query("SHOW TABLES LIKE 'att_subjects'")->fetchColumn();
                if ($hasAttSubj) {
                    $attSubjs = $pdo->query("
                        SELECT s.`id`, s.`subject_code`, s.`subject_name`, s.`teacher_id`, t.`llw_user_id`
                        FROM `att_subjects` s
                        LEFT JOIN `att_teachers` t ON t.`id` = s.`teacher_id`
                        WHERE s.`classroom` = 'ม.2/4'
                    ")->fetchAll(PDO::FETCH_ASSOC);

                    $stmtFindSubj = $pdo->prepare("SELECT `id` FROM `pp5_subjects` WHERE `code` = ? LIMIT 1");
                    $stmtUpdTeacher = $pdo->prepare("
                        UPDATE `pp5_courses`
                        SET `teacher_user_id` = ?, `att_subject_id` = ?
                        WHERE `classroom_id` = ? AND `subject_id` = ? AND `academic_year` = ? AND `semester` = ?
                    ");

                    foreach ($attSubjs as $as) {
                        $stmtFindSubj->execute([trim((string)$as['subject_code'])]);
                        $subId = (int)$stmtFindSubj->fetchColumn();
                        $uid   = (int)($as['llw_user_id'] ?? 0);
                        if ($subId > 0 && $uid > 0) {
                            $stmtUpdTeacher->execute([$uid, (int)$as['id'], $m24ClsIdNow, $subId, $curYear, $curSem]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[PP5 Migration 000003] generate courses for m2/4: ' . $e->getMessage());
        }
    },

    'down' => function (PDO $pdo) {
        // Rollback if needed
    }
];
