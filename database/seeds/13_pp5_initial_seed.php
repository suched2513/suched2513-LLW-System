<?php
/**
 * Seeder: database/seeds/13_pp5_initial_seed.php
 *
 * สร้างข้อมูลเริ่มต้นสำหรับ "ระบบ ปพ.5 ออนไลน์":
 * 1. ตั้งค่าโรงเรียน (pp5_school_settings) และเกณฑ์การตัดเกรด 8 ระดับ (pp5_grade_criteria)
 * 2. 8 กลุ่มสาระการเรียนรู้ และกิจกรรมพัฒนาผู้เรียน (pp5_departments)
 * 3. ปีการศึกษา 2568 และ 2569 (llw_academic_terms)
 * 4. ห้องเรียน 19 ห้องเรียน (ม.1/1–ม.1/4 และ ม.2–ม.6 ชั้นละ 3 ห้อง) พร้อมเชื่อมครูที่ปรึกษา (pp5_classrooms)
 * 5. นักเรียนประจำห้องเรียน (pp5_classroom_students) ซิงค์จาก att_students (หรือสร้างข้อมูลตัวอย่างหากยังไม่มี)
 * 6. รายวิชาและตัวชี้วัดตามหลักสูตรแกนกลางฯ 2551 (pp5_subjects, pp5_indicators) และรายวิชาที่เปิดสอน (pp5_courses)
 */
return [
    'run' => function (PDO $pdo) {
        // ─── 1. ตั้งค่าโรงเรียนเริ่มต้น (pp5_school_settings) ───
        $hasSettings = (int)$pdo->query("SELECT COUNT(*) FROM `pp5_school_settings`")->fetchColumn();
        if ($hasSettings === 0) {
            $pdo->exec("
                INSERT INTO `pp5_school_settings` (
                    `id`, `school_name`, `school_code`, `affiliation`, `province`,
                    `director_name`, `director_position`,
                    `deputy_academic_name`, `deputy_academic_position`,
                    `measurement_head_name`,
                    `min_attendance_percent`, `count_late_as_present`, `count_leave_as_present`, `count_sick_as_present`,
                    `assessment_summary_mode`, `zero_item_forces_fail`
                ) VALUES (
                    1, 'โรงเรียนละลมวิทยา', '1033720344',
                    'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร', 'ศรีสะเกษ',
                    'นายสมชาย ใจดี', 'ผู้อำนวยการโรงเรียนละลมวิทยา',
                    'นางสาววิชาการ เก่งงาน', 'รองผู้อำนวยการกลุ่มบริหารวิชาการ',
                    'หัวหน้างานวัดและประเมินผล',
                    80.00, 1, 0, 0,
                    'mode', 1
                )
            ");
        }

        // ─── 2. เกณฑ์การตัดเกรดเริ่มต้น 8 ระดับ (pp5_grade_criteria) ───
        $criteria = [
            [80, 100, '4',   'ดีเยี่ยม',       1],
            [75, 79,  '3.5', 'ดีมาก',         2],
            [70, 74,  '3',   'ดี',            3],
            [65, 69,  '2.5', 'ค่อนข้างดี',    4],
            [60, 64,  '2',   'ปานกลาง',       5],
            [55, 59,  '1.5', 'พอใช้',         6],
            [50, 54,  '1',   'ผ่านเกณฑ์ขั้นต่ำ', 7],
            [0,  49,  '0',   'ต่ำกว่าเกณฑ์',   8],
        ];
        $stmtCrit = $pdo->prepare("
            INSERT INTO `pp5_grade_criteria` (`min_score`, `max_score`, `grade`, `meaning`, `sort_order`)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `min_score`  = VALUES(`min_score`),
                `max_score`  = VALUES(`max_score`),
                `meaning`    = VALUES(`meaning`),
                `sort_order` = VALUES(`sort_order`)
        ");
        foreach ($criteria as $c) {
            $stmtCrit->execute($c);
        }

        // ─── 3. 8 กลุ่มสาระการเรียนรู้ + 1 กิจกรรมพัฒนาผู้เรียน (pp5_departments) ───
        $departments = [
            ['TH',  'ภาษาไทย',                        0, 1],
            ['MA',  'คณิตศาสตร์',                     0, 2],
            ['SC',  'วิทยาศาสตร์และเทคโนโลยี',         0, 3],
            ['SO',  'สังคมศึกษา ศาสนา และวัฒนธรรม',     0, 4],
            ['HP',  'สุขศึกษาและพลศึกษา',              0, 5],
            ['AR',  'ศิลปะ',                          0, 6],
            ['OC',  'การงานอาชีพ',                     0, 7],
            ['FL',  'ภาษาต่างประเทศ',                  0, 8],
            ['ACT', 'กิจกรรมพัฒนาผู้เรียน',             1, 9],
        ];
        $stmtDept = $pdo->prepare("
            INSERT INTO `pp5_departments` (`code`, `name`, `is_activity`, `sort_order`)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `name`        = VALUES(`name`),
                `is_activity` = VALUES(`is_activity`),
                `sort_order`  = VALUES(`sort_order`)
        ");
        foreach ($departments as $d) {
            $stmtDept->execute($d);
        }

        // ดึง map code -> id ของกลุ่มสาระ
        $deptRows = $pdo->query("SELECT `code`, `id` FROM `pp5_departments`")->fetchAll(PDO::FETCH_KEY_PAIR);

        // ─── 4. ปีการศึกษา 2568 และ 2569 (llw_academic_terms) ───
        try {
            $pdo->exec("
                INSERT IGNORE INTO `llw_academic_terms` (`academic_year`, `semester`, `start_date`, `end_date`, `is_active`) VALUES
                (2568, 1, '2025-05-16', '2025-10-31', 0),
                (2568, 2, '2025-11-01', '2026-03-31', 0),
                (2569, 1, '2026-05-16', '2026-10-31', 0),
                (2569, 2, '2026-11-01', '2027-03-31', 0)
            ");
        } catch (Throwable $e) {
            error_log('[PP5 Seed] academic_terms: ' . $e->getMessage());
        }

        $activeYear = function_exists('llw_current_academic_year') ? llw_current_academic_year() : 2569;
        $activeSem  = function_exists('llw_current_semester') ? llw_current_semester() : 2;

        // ─── 5. ห้องเรียน 19 ห้องเรียน (ม.1 มี 4 ห้อง, ม.2–ม.6 มีชั้นละ 3 ห้อง = 19 ห้อง) ───
        $standardRooms = [
            [1, 1, 'ม.1/1', 'วิทย์-คณิต'],
            [1, 2, 'ม.1/2', 'วิทย์-คณิต'],
            [1, 3, 'ม.1/3', 'ทั่วไป'],
            [2, 4, 'ม.2/4', 'ทั่วไป'],
            [2, 1, 'ม.2/1', 'วิทย์-คณิต'],
            [2, 2, 'ม.2/2', 'ทั่วไป'],
            [2, 3, 'ม.2/3', 'ทั่วไป'],
            [3, 1, 'ม.3/1', 'วิทย์-คณิต'],
            [3, 2, 'ม.3/2', 'ทั่วไป'],
            [3, 3, 'ม.3/3', 'ทั่วไป'],
            [4, 1, 'ม.4/1', 'วิทย์-คณิต'],
            [4, 2, 'ม.4/2', 'ศิลป์-ภาษา'],
            [4, 3, 'ม.4/3', 'ศิลป์-ทั่วไป'],
            [5, 1, 'ม.5/1', 'วิทย์-คณิต'],
            [5, 2, 'ม.5/2', 'ศิลป์-ภาษา'],
            [5, 3, 'ม.5/3', 'ศิลป์-ทั่วไป'],
            [6, 1, 'ม.6/1', 'วิทย์-คณิต'],
            [6, 2, 'ม.6/2', 'ศิลป์-ภาษา'],
            [6, 3, 'ม.6/3', 'ศิลป์-ทั่วไป'],
        ];

        // ดึงครูที่ปรึกษาจาก llw_class_advisors (ถ้ามี)
        $advisorMap = [];
        try {
            $hasAdvTbl = (bool)$pdo->query("SHOW TABLES LIKE 'llw_class_advisors'")->fetchColumn();
            if ($hasAdvTbl) {
                $advRows = $pdo->query("SELECT `classroom`, `user_id` FROM `llw_class_advisors` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($advRows as $ar) {
                    $rm = trim((string)$ar['classroom']);
                    if (!isset($advisorMap[$rm])) {
                        $advisorMap[$rm] = [
                            'primary' => (int)$ar['user_id'],
                            'co'      => null,
                        ];
                    } elseif ($advisorMap[$rm]['co'] === null && (int)$ar['user_id'] !== $advisorMap[$rm]['primary']) {
                        $advisorMap[$rm]['co'] = (int)$ar['user_id'];
                    }
                }
            }
        } catch (Throwable $e) {}

        $stmtCls = $pdo->prepare("
            INSERT INTO `pp5_classrooms` (
                `academic_year`, `semester`, `grade_level`, `room_no`, `classroom_name`,
                `study_plan`, `advisor_user_id`, `co_advisor_user_id`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `study_plan`      = VALUES(`study_plan`),
                `advisor_user_id` = COALESCE(`pp5_classrooms`.`advisor_user_id`, VALUES(`advisor_user_id`)),
                `co_advisor_user_id` = COALESCE(`pp5_classrooms`.`co_advisor_user_id`, VALUES(`co_advisor_user_id`))
        ");

        $termsToSeed = [
            [$activeYear, 1],
            [$activeYear, 2],
        ];
        foreach ($termsToSeed as [$yr, $sem]) {
            foreach ($standardRooms as [$gLevel, $rNo, $cName, $sPlan]) {
                $adv1 = $advisorMap[$cName]['primary'] ?? null;
                $adv2 = $advisorMap[$cName]['co'] ?? null;
                $stmtCls->execute([$yr, $sem, $gLevel, $rNo, $cName, $sPlan, $adv1, $adv2]);
            }
        }

        // ─── 6. แยกคำนำหน้า ชื่อ นามสกุล เพศ ใน att_students และซิงค์เข้า pp5_classroom_students ───
        $activeStuCount = (int)$pdo->query("
            SELECT COUNT(*) FROM `att_students`
            WHERE `status` = 'active' AND `student_id` REGEXP '^[0-9]+$'
        ")->fetchColumn();

        // กรณีฐานข้อมูลว่างเปล่า (เพิ่งติดตั้งใหม่) ให้สร้างนักเรียนตัวอย่างครบทั้ง 19 ห้อง
        if ($activeStuCount === 0) {
            $stmtInsStu = $pdo->prepare("
                INSERT INTO `att_students` (`student_id`, `prefix`, `first_name`, `last_name`, `name`, `gender`, `classroom`, `student_number`, `status`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $seqCode = 50001;
            foreach ($standardRooms as [$gLevel, $rNo, $cName, $sPlan]) {
                for ($num = 1; $num <= 10; $num++) {
                    $isMale = ($num <= 5);
                    $prefix = $gLevel <= 3
                        ? ($isMale ? 'เด็กชาย' : 'เด็กหญิง')
                        : ($isMale ? 'นาย' : 'นางสาว');
                    $fName  = ($isMale ? 'สมชาย' : 'สมหญิง') . $gLevel . $rNo . '_' . $num;
                    $lName  = 'ละลมวิทยา';
                    $full   = $prefix . $fName . ' ' . $lName;
                    $gender = $isMale ? 'male' : 'female';
                    $sCode  = str_pad((string)($seqCode++), 5, '0', STR_PAD_LEFT);
                    $stmtInsStu->execute([$sCode, $prefix, $fName, $lName, $full, $gender, $cName, $num]);
                }
            }
        }

        // แยก prefix / first_name / last_name / gender ให้กับนักเรียนใน att_students ที่ยังว่างอยู่
        $rawStudents = $pdo->query("
            SELECT `id`, `student_id`, `name`, `prefix`, `first_name`, `last_name`, `gender`, `classroom`, `status`
            FROM `att_students`
            WHERE `student_id` REGEXP '^[0-9]+$'
            ORDER BY `classroom` ASC, `student_id` ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtUpdStu = $pdo->prepare("
            UPDATE `att_students`
            SET `prefix` = ?, `first_name` = ?, `last_name` = ?, `gender` = ?, `student_number` = ?
            WHERE `id` = ?
        ");

        $byRoom = [];
        foreach ($rawStudents as $st) {
            $rm = trim((string)$st['classroom']);
            if ($rm === '') continue;
            $byRoom[$rm][] = $st;
        }

        foreach ($byRoom as $rm => $stuList) {
            $num = 1;
            foreach ($stuList as $st) {
                $fullName = trim((string)$st['name']);
                $prefix   = trim((string)$st['prefix']);
                $fName    = trim((string)$st['first_name']);
                $lName    = trim((string)$st['last_name']);
                $gender   = $st['gender'] ?? 'unspecified';

                if ($prefix === '' || $fName === '') {
                    $prefixes = ['เด็กชาย', 'เด็กหญิง', 'ด.ช.', 'ด.ญ.', 'นางสาว', 'น.ส.', 'นาย', 'นาง'];
                    $rest     = $fullName;
                    foreach ($prefixes as $p) {
                        if (mb_strpos($fullName, $p, 0, 'UTF-8') === 0) {
                            $prefix = $p;
                            $rest   = trim(mb_substr($fullName, mb_strlen($p, 'UTF-8'), null, 'UTF-8'));
                            break;
                        }
                    }
                    $parts = preg_split('/\s+/u', $rest, 2);
                    $fName = $parts[0] ?? $rest;
                    $lName = $parts[1] ?? '';
                }

                if ($gender === 'unspecified' || $gender === '') {
                    if (in_array($prefix, ['เด็กชาย', 'ด.ช.', 'นาย'], true)) {
                        $gender = 'male';
                    } elseif (in_array($prefix, ['เด็กหญิง', 'ด.ญ.', 'นางสาว', 'น.ส.', 'นาง'], true)) {
                        $gender = 'female';
                    } else {
                        $gender = 'unspecified';
                    }
                }

                $curNo = ($st['status'] === 'active') ? $num++ : 99;
                $stmtUpdStu->execute([$prefix, $fName, $lName, $gender, $curNo, (int)$st['id']]);
            }
        }

        // ซิงค์นักเรียนที่ active เข้า pp5_classroom_students สำหรับปีการศึกษาปัจจุบัน
        foreach ($termsToSeed as [$yr, $sem]) {
            $clsMap = $pdo->prepare("SELECT `classroom_name`, `id` FROM `pp5_classrooms` WHERE `academic_year` = ? AND `semester` = ?");
            $clsMap->execute([$yr, $sem]);
            $roomToClsId = $clsMap->fetchAll(PDO::FETCH_KEY_PAIR);

            $stmtCs = $pdo->prepare("
                INSERT INTO `pp5_classroom_students` (`classroom_id`, `student_id`, `student_code`, `student_number`, `status`)
                VALUES (?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE
                    `student_code`   = VALUES(`student_code`),
                    `student_number` = VALUES(`student_number`)
            ");

            $activeStus = $pdo->query("
                SELECT `id`, `student_id`, `classroom`, COALESCE(`student_number`, 1) AS `student_number`
                FROM `att_students`
                WHERE `status` = 'active' AND `student_id` REGEXP '^[0-9]+$'
                ORDER BY `classroom` ASC, `student_id` ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($activeStus as $as) {
                $rm = trim((string)$as['classroom']);
                if (isset($roomToClsId[$rm])) {
                    $stmtCs->execute([
                        (int)$roomToClsId[$rm],
                        (int)$as['id'],
                        str_pad(trim((string)$as['student_id']), 5, '0', STR_PAD_LEFT),
                        (int)$as['student_number'],
                    ]);
                }
            }
        }

        // ─── 7. รายวิชาและตัวชี้วัดเริ่มต้น + ซิงค์จาก att_subjects ───
        $helperGuessDept = function (string $code, string $name) use ($deptRows): array {
            $firstChar = mb_substr(trim($code), 0, 1, 'UTF-8');
            $map = [
                'ท' => 'TH',
                'ค' => 'MA',
                'ว' => 'SC',
                'ส' => 'SO',
                'พ' => 'HP',
                'ศ' => 'AR',
                'ง' => 'OC',
                'อ' => 'FL',
                'จ' => 'FL',
                'ก' => 'ACT',
            ];
            $deptCode = $map[$firstChar] ?? 'OC';
            if (mb_strpos($name, 'แนะแนว', 0, 'UTF-8') !== false || mb_strpos($name, 'ลูกเสือ', 0, 'UTF-8') !== false || mb_strpos($name, 'ชุมนุม', 0, 'UTF-8') !== false || mb_strpos($name, 'กิจกรรม', 0, 'UTF-8') !== false) {
                $deptCode = 'ACT';
            }
            $deptId = (int)($deptRows[$deptCode] ?? reset($deptRows));

            // ตรวจประเภทวิชาจากหลักที่ 4 ของรหัสวิชา (เช่น ค21101 -> '1'=พื้นฐาน, '2'=เพิ่มเติม)
            $digits = preg_replace('/[^0-9]/', '', $code);
            $type   = 'basic';
            $credit = 1.5;
            $hours  = 60;
            $grade  = 1;

            if (strlen($digits) >= 2) {
                $lvlDigit = (int)$digits[0]; // 2=ม.ต้น, 3=ม.ปลาย
                $yrDigit  = (int)$digits[1]; // 1,2,3
                if ($lvlDigit === 2) {
                    $grade = max(1, min(3, $yrDigit ?: 1));
                } elseif ($lvlDigit === 3) {
                    $grade = max(4, min(6, ($yrDigit ?: 1) + 3));
                }
            }
            if ($deptCode === 'ACT') {
                $type   = 'activity';
                $credit = 0.0;
                $hours  = 20;
            } elseif (strlen($digits) >= 3 && $digits[2] === '2') {
                $type   = 'additional';
                $credit = 1.0;
                $hours  = 40;
            }
            return [$deptId, $type, $credit, $hours, $grade];
        };

        $stmtInsSubj = $pdo->prepare("
            INSERT INTO `pp5_subjects` (`code`, `name`, `department_id`, `type`, `credit`, `hours`, `grade_level`)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `name`          = VALUES(`name`),
                `department_id` = VALUES(`department_id`)
        ");

        // เพิ่มวิชากิจกรรมพัฒนาผู้เรียนพื้นฐาน
        $defaultActivities = [
            ['ก21901', 'กิจกรรมแนะแนว ม.1',    'ACT', 'activity', 0.0, 20, 1],
            ['ก21902', 'กิจกรรมลูกเสือ-เนตรนารี ม.1', 'ACT', 'activity', 0.0, 20, 1],
            ['ก21903', 'กิจกรรมชุมนุม ม.1',     'ACT', 'activity', 0.0, 20, 1],
            ['ก31901', 'กิจกรรมแนะแนว ม.4',    'ACT', 'activity', 0.0, 20, 4],
            ['ก31902', 'กิจกรรมนักศึกษาวิชาทหาร/บำเพ็ญประโยชน์', 'ACT', 'activity', 0.0, 20, 4],
        ];
        foreach ($defaultActivities as [$sCode, $sName, $dCode, $sType, $sCred, $sHrs, $sGrade]) {
            $dId = (int)($deptRows[$dCode] ?? reset($deptRows));
            $stmtInsSubj->execute([$sCode, $sName, $dId, $sType, $sCred, $sHrs, $sGrade]);
        }

        // ซิงค์วิชาและ Course จาก att_subjects (ถ้ามี)
        $defaultAdminId = (int)($pdo->query("SELECT `user_id` FROM `llw_users` ORDER BY (`role`='super_admin') DESC, `user_id` ASC LIMIT 1")->fetchColumn() ?: 1);

        $attSubjs = $pdo->query("
            SELECT s.*, t.`llw_user_id`
            FROM `att_subjects` s
            LEFT JOIN `att_teachers` t ON t.`id` = s.`teacher_id`
            ORDER BY s.`id` ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtGetSubjId = $pdo->prepare("SELECT `id` FROM `pp5_subjects` WHERE `code` = ? LIMIT 1");
        $stmtGetClsId  = $pdo->prepare("SELECT `id` FROM `pp5_classrooms` WHERE `academic_year` = ? AND `semester` = ? AND `classroom_name` = ? LIMIT 1");
        $stmtInsCourse = $pdo->prepare("
            INSERT INTO `pp5_courses` (
                `academic_year`, `semester`, `subject_id`, `classroom_id`,
                `teacher_user_id`, `att_subject_id`, `ratio_collect`, `ratio_final`, `status`
            ) VALUES (?, ?, ?, ?, ?, ?, 70, 30, 'draft')
            ON DUPLICATE KEY UPDATE
                `att_subject_id`  = COALESCE(`pp5_courses`.`att_subject_id`, VALUES(`att_subject_id`)),
                `teacher_user_id` = VALUES(`teacher_user_id`)
        ");

        foreach ($attSubjs as $as) {
            $code = trim((string)$as['subject_code']);
            $name = trim((string)$as['subject_name']);
            $room = preg_replace('/^ม\.+/', 'ม.', trim((string)$as['classroom']));
            if ($code === '' || $name === '') continue;

            [$deptId, $type, $credit, $hours, $grade] = $helperGuessDept($code, $name);
            $stmtInsSubj->execute([$code, $name, $deptId, $type, $credit, $hours, $grade]);

            $stmtGetSubjId->execute([$code]);
            $pp5SubjId = (int)$stmtGetSubjId->fetchColumn();
            if ($pp5SubjId <= 0) continue;

            $yr  = (int)($as['academic_year'] ?? $activeYear) ?: $activeYear;
            $sem = (int)($as['semester'] ?? $activeSem) ?: $activeSem;

            $stmtGetClsId->execute([$yr, $sem, $room]);
            $clsId = (int)$stmtGetClsId->fetchColumn();

            // ถ้าระบุห้องเป็นกลุ่มวิชาเลือก เช่น ม.4(ค.ศ.) ให้ผูกกับห้องแรกของระดับชั้นนั้น
            if ($clsId <= 0 && preg_match('/ม\.([1-6])/', $room, $m)) {
                $fallbackRoom = 'ม.' . $m[1] . '/1';
                $stmtGetClsId->execute([$yr, $sem, $fallbackRoom]);
                $clsId = (int)$stmtGetClsId->fetchColumn();
            }

            if ($clsId > 0) {
                $teacherUserId = (int)($as['llw_user_id'] ?? 0) ?: $defaultAdminId;
                $stmtInsCourse->execute([$yr, $sem, $pp5SubjId, $clsId, $teacherUserId, (int)$as['id']]);
            }
        }

        // เพิ่มตัวชี้วัดมาตรฐานตัวอย่างให้ทุกวิชาที่ยังไม่มีตัวชี้วัด
        $allSubjs = $pdo->query("SELECT `id`, `code`, `name`, `type` FROM `pp5_subjects`")->fetchAll(PDO::FETCH_ASSOC);
        $stmtChkInd = $pdo->prepare("SELECT COUNT(*) FROM `pp5_indicators` WHERE `subject_id` = ?");
        $stmtInsInd = $pdo->prepare("INSERT IGNORE INTO `pp5_indicators` (`subject_id`, `code`, `description`, `sort_order`) VALUES (?, ?, ?, ?)");

        foreach ($allSubjs as $sb) {
            $stmtChkInd->execute([(int)$sb['id']]);
            if ((int)$stmtChkInd->fetchColumn() === 0) {
                $prefixLabel = $sb['type'] === 'activity' ? 'จป.' : ($sb['type'] === 'additional' ? 'ผล.' : 'ตชว.');
                $stmtInsInd->execute([(int)$sb['id'], "{$prefixLabel}1", "อธิบายหลักการและแนวคิดสำคัญของรายวิชา {$sb['name']} ได้อย่างถูกต้อง", 1]);
                $stmtInsInd->execute([(int)$sb['id'], "{$prefixLabel}2", "ประยุกต์ใช้ความรู้และทักษะในรายวิชา {$sb['name']} เพื่อแก้ปัญหาในชีวิตประจำวันได้", 2]);
                $stmtInsInd->execute([(int)$sb['id'], "{$prefixLabel}3", "มีความรับผิดชอบและมุ่งมั่นในการปฏิบัติงานรายวิชา {$sb['name']} จนสำเร็จ", 3]);
            }
        }
    },
];
