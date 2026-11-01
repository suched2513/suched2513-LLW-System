<?php
namespace PP5\App\Services;

use PDO;
use Throwable;

/**
 * ExcelImportExportService — บริการนำเข้าและส่งออกข้อมูลด้วยไฟล์ Excel (.xlsx / .csv)
 *
 * คุณสมบัติตามข้อกำหนด:
 * 1. มีไฟล์แม่แบบ (Template) ให้ดาวน์โหลดทั้ง 4 ประเภท: นักเรียน, ครูผู้สอน, รายวิชา, การจัดครูสอน
 * 2. ตรวจสอบความถูกต้องทีละแถว (Dry-run Validation) และแสดงแถวที่ผิดพลาดพร้อมสาเหตุก่อนกดยืนยันนำเข้าจริง
 * 3. บันทึกข้อมูลแบบ Transaction และบันทึกประวัติลง ActivityLogger
 */
class ExcelImportExportService
{
    private PDO $pdo;
    private MasterDataService $masterService;
    private ActivityLogger $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo           = $pdo;
        $this->masterService = new MasterDataService($pdo);
        $this->logger        = new ActivityLogger($pdo);
    }

    /**
     * คืนค่าโครงสร้างคอลัมน์และข้อมูลตัวอย่างของไฟล์แม่แบบทั้ง 4 ประเภท
     */
    public function getTemplateDefinitions(): array
    {
        return [
            'students' => [
                'title'    => 'ข้อมูลนักเรียน (Students)',
                'filename' => 'template_pp5_students',
                'headers'  => ['student_code', 'student_number', 'prefix', 'first_name', 'last_name', 'classroom', 'citizen_id', 'status'],
                'labels'   => ['รหัสนักเรียน (5 หลัก)', 'เลขที่', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'ห้องเรียน (เช่น ม.1/1)', 'เลขบัตรประชาชน (13 หลัก)', 'สถานะ (active/moved/graduated/dropped)'],
                'samples'  => [
                    ['04849', '1', 'เด็กชาย', 'กฤษณะ', 'ยกจำนวน', 'ม.1/1', '1339901213741', 'active'],
                    ['04866', '2', 'เด็กหญิง', 'กัญญาพัชร', 'บุญกู่', 'ม.1/1', '1331700145342', 'active'],
                ],
            ],
            'teachers' => [
                'title'    => 'ข้อมูลครูและบุคลากร (Teachers)',
                'filename' => 'template_pp5_teachers',
                'headers'  => ['username', 'prefix', 'first_name', 'last_name', 'department_code', 'role', 'email'],
                'labels'   => ['ชื่อผู้ใช้ (Username)', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'รหัสกลุ่มสาระ (TH/MA/SC/SO/HP/AR/OC/FL/ACT)', 'บทบาท (att_teacher/department_head/deputy_director/director/super_admin)', 'อีเมล'],
                'samples'  => [
                    ['teacher_ma01', 'นาย', 'วิชัย', 'คณิตเก่ง', 'MA', 'att_teacher', 'wichai@llw.ac.th'],
                    ['teacher_th01', 'นางสาว', 'สุดา', 'รักภาษา', 'TH', 'att_teacher', 'suda@llw.ac.th'],
                ],
            ],
            'subjects' => [
                'title'    => 'ข้อมูลรายวิชาตามหลักสูตร (Subjects)',
                'filename' => 'template_pp5_subjects',
                'headers'  => ['code', 'name', 'department_code', 'type', 'credit', 'hours', 'grade_level'],
                'labels'   => ['รหัสวิชา', 'ชื่อรายวิชา', 'รหัสกลุ่มสาระ (TH/MA/SC/SO/HP/AR/OC/FL/ACT)', 'ประเภท (basic/additional/activity)', 'หน่วยกิต', 'ชั่วโมง/ภาคเรียน', 'ระดับชั้น (1-6)'],
                'samples'  => [
                    ['ค21101', 'คณิตศาสตร์พื้นฐาน 1', 'MA', 'basic', '1.5', '60', '1'],
                    ['ว21201', 'วิทยาศาสตร์เพิ่มเติม 1', 'SC', 'additional', '1.0', '40', '1'],
                    ['ก21901', 'กิจกรรมแนะแนว 1', 'ACT', 'activity', '0.0', '20', '1'],
                ],
            ],
            'courses' => [
                'title'    => 'การจัดครูสอนประจำภาคเรียน (Courses / ปพ.5)',
                'filename' => 'template_pp5_courses',
                'headers'  => ['subject_code', 'classroom_name', 'teacher_username', 'co_teacher_username', 'ratio_collect', 'ratio_final'],
                'labels'   => ['รหัสวิชา', 'ห้องเรียน (เช่น ม.1/1)', 'Username ครูผู้สอนหลัก', 'Username ครูสอนร่วม (ถ้ามี)', 'สัดส่วนคะแนนเก็บ (เช่น 70)', 'สัดส่วนคะแนนปลายภาค (เช่น 30)'],
                'samples'  => [
                    ['ค21101', 'ม.1/1', 'llw20', '', '70', '30'],
                    ['ท21101', 'ม.1/1', 'llw20', '', '80', '20'],
                ],
            ],
        ];
    }

    /**
     * ส่งออกไฟล์แม่แบบ CSV (รองรับการเปิดด้วย Microsoft Excel ภาษาไทยด้วย UTF-8 BOM)
     */
    public function outputCsvTemplate(string $type): void
    {
        $defs = $this->getTemplateDefinitions();
        if (!isset($defs[$type])) {
            http_response_code(400);
            die('ไม่พบประเภทไฟล์แม่แบบที่ระบุ');
        }
        $def = $defs[$type];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $def['filename'] . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM สำหรับ Excel
        fputcsv($out, $def['headers']);
        foreach ($def['samples'] as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    /**
     * แปลงไฟล์ CSV ที่อัปโหลดให้เป็น Array ของแถวข้อมูล
     */
    public function parseCsvFile(string $tmpFilePath): array
    {
        $content = (string)file_get_contents($tmpFilePath);
        // ลบ UTF-8 BOM ถ้ามี
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8') && function_exists('iconv')) {
            $converted = @iconv('WINDOWS-874', 'UTF-8//IGNORE', $content);
            if ($converted !== false && $converted !== '') {
                $content = $converted;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $rows  = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line);
        }
        return $rows;
    }

    /**
     * ตรวจสอบความถูกต้องของข้อมูลทุกแถวก่อนยืนยันการนำเข้า (Dry-Run Validation)
     * คืนค่ารายการแถวที่ถูกต้อง (valid_rows) และแถวที่มีข้อผิดพลาด (error_rows) พร้อมระบุเลขแถวและสาเหตุชัดเจน
     */
    public function validateImportRows(string $type, array $rawRows, int $academicYear, int $semester): array
    {
        $defs = $this->getTemplateDefinitions();
        if (!isset($defs[$type])) {
            return ['valid_rows' => [], 'error_rows' => [['row_no' => 1, 'errors' => ['ประเภทการนำเข้าไม่ถูกต้อง'], 'raw' => []]]];
        }

        if (empty($rawRows)) {
            return ['valid_rows' => [], 'error_rows' => [['row_no' => 1, 'errors' => ['ไม่พบข้อมูลในไฟล์'], 'raw' => []]]];
        }

        // ตรวจแถวแรก: ถ้าเป็น Header ให้ข้ามแถวที่ 1
        $firstCell = strtolower(trim((string)($rawRows[0][0] ?? '')));
        $headerKeywords = ['student_code', 'รหัสนักเรียน', 'username', 'ชื่อผู้ใช้', 'code', 'รหัสวิชา', 'subject_code'];
        $startIndex = in_array($firstCell, $headerKeywords, true) ? 1 : 0;

        // โหลด Lookup Maps ล่วงหน้าเพื่อตรวจสอบ FK อย่างรวดเร็ว
        $deptMap = $this->pdo->query("SELECT UPPER(`code`), `id` FROM `pp5_departments`")->fetchAll(PDO::FETCH_KEY_PAIR);

        $clsStmt = $this->pdo->prepare("SELECT `classroom_name`, `id` FROM `pp5_classrooms` WHERE `academic_year` = ? AND `semester` = ?");
        $clsStmt->execute([$academicYear, $semester]);
        $clsMap = $clsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $subjMap = $this->pdo->query("SELECT `code`, `id` FROM `pp5_subjects`")->fetchAll(PDO::FETCH_KEY_PAIR);
        $userMap = $this->pdo->query("SELECT LOWER(`username`), `user_id` FROM `llw_users`")->fetchAll(PDO::FETCH_KEY_PAIR);

        $validRows = [];
        $errorRows = [];

        for ($i = $startIndex; $i < count($rawRows); $i++) {
            $rowNo = $i + 1;
            $cols  = array_map(fn($v) => trim((string)$v), (array)$rawRows[$i]);

            // ข้ามแถวว่างทั้งหมด
            if (implode('', $cols) === '') {
                continue;
            }

            $rowErrors = [];
            $cleanItem = [];

            if ($type === 'students') {
                $stuCode   = $cols[0] ?? '';
                if (ctype_digit($stuCode) && strlen($stuCode) < 5) {
                    $stuCode = str_pad($stuCode, 5, '0', STR_PAD_LEFT);
                }
                $stuNum    = (int)($cols[1] ?? 1);
                $prefix    = $cols[2] ?? '';
                $firstName = $cols[3] ?? '';
                $lastName  = $cols[4] ?? '';
                $classroom = preg_replace('/^ม\.+/', 'ม.', $cols[5] ?? '');
                $citizenId = preg_replace('/[^0-9]/', '', $cols[6] ?? '');
                $status    = strtolower($cols[7] ?? 'active') ?: 'active';

                if (!preg_match('/^\d{4,10}$/', $stuCode)) {
                    $rowErrors[] = "รหัสนักเรียน '{$stuCode}' ต้องเป็นตัวเลข 4-10 หลัก";
                }
                if ($stuNum < 1 || $stuNum > 99) {
                    $rowErrors[] = "เลขที่ '{$stuNum}' ต้องอยู่ระหว่าง 1-99";
                }
                if ($firstName === '') {
                    $rowErrors[] = 'ไม่ได้ระบุชื่อนักเรียน';
                }
                if ($classroom === '' || !isset($clsMap[$classroom])) {
                    $rowErrors[] = "ไม่พบห้องเรียน '{$classroom}' ในระบบ (ต้องเป็น ม.1/1 ถึง ม.6/3)";
                }
                if ($citizenId !== '' && strlen($citizenId) !== 13) {
                    $rowErrors[] = "เลขบัตรประชาชน '{$citizenId}' ต้องมี 13 หลัก";
                }
                if (!in_array($status, ['active', 'moved', 'graduated', 'dropped'], true)) {
                    $rowErrors[] = "สถานะ '{$status}' ไม่ถูกต้อง (ต้องเป็น active, moved, graduated หรือ dropped)";
                }

                $gender = in_array($prefix, ['เด็กชาย', 'ด.ช.', 'นาย'], true) ? 'male'
                    : (in_array($prefix, ['เด็กหญิง', 'ด.ญ.', 'นางสาว', 'น.ส.'], true) ? 'female' : 'unspecified');

                $cleanItem = [
                    'student_code'   => $stuCode,
                    'student_number' => $stuNum,
                    'prefix'         => $prefix,
                    'first_name'     => $firstName,
                    'last_name'      => $lastName,
                    'full_name'      => trim($prefix . $firstName . ' ' . $lastName),
                    'gender'         => $gender,
                    'classroom'      => $classroom,
                    'citizen_id'     => $citizenId,
                    'status'         => $status,
                ];
            } elseif ($type === 'teachers') {
                $username  = ltrim($cols[0] ?? '', '@');
                $prefix    = $cols[1] ?? '';
                $firstName = $cols[2] ?? '';
                $lastName  = $cols[3] ?? '';
                $deptCode  = strtoupper($cols[4] ?? 'OC');
                $role      = strtolower($cols[5] ?? 'att_teacher') ?: 'att_teacher';
                $email     = $cols[6] ?? '';

                if ($username === '' || strlen($username) < 3) {
                    $rowErrors[] = 'Username ต้องมีความยาวอย่างน้อย 3 ตัวอักษร';
                }
                if ($firstName === '') {
                    $rowErrors[] = 'ไม่ได้ระบุชื่อครู';
                }
                if (!isset($deptMap[$deptCode])) {
                    $rowErrors[] = "รหัสกลุ่มสาระ '{$deptCode}' ไม่ถูกต้อง (ใช้ TH, MA, SC, SO, HP, AR, OC, FL, ACT)";
                }
                $allowedRoles = ['att_teacher', 'department_head', 'deputy_director', 'director', 'super_admin', 'academic_admin'];
                if (!in_array($role, $allowedRoles, true)) {
                    $rowErrors[] = "บทบาท '{$role}' ไม่ถูกต้อง";
                }

                $cleanItem = [
                    'username'        => $username,
                    'prefix'          => $prefix,
                    'first_name'      => $firstName,
                    'last_name'       => $lastName,
                    'department_code' => $deptCode,
                    'department_id'   => $deptMap[$deptCode] ?? null,
                    'role'            => $role,
                    'email'           => $email,
                ];
            } elseif ($type === 'subjects') {
                $code     = $cols[0] ?? '';
                $name     = $cols[1] ?? '';
                $deptCode = strtoupper($cols[2] ?? '');
                $sType    = strtolower($cols[3] ?? 'basic') ?: 'basic';
                $credit   = (float)($cols[4] ?? 1.5);
                $hours    = (int)($cols[5] ?? 60);
                $grade    = (int)($cols[6] ?? 1);

                if ($code === '') {
                    $rowErrors[] = 'ไม่ได้ระบุรหัสวิชา';
                }
                if ($name === '') {
                    $rowErrors[] = 'ไม่ได้ระบุชื่อรายวิชา';
                }
                if (!isset($deptMap[$deptCode])) {
                    $rowErrors[] = "รหัสกลุ่มสาระ '{$deptCode}' ไม่ถูกต้อง (ใช้ TH, MA, SC, SO, HP, AR, OC, FL, ACT)";
                }
                if (!in_array($sType, ['basic', 'additional', 'activity'], true)) {
                    $rowErrors[] = "ประเภทวิชา '{$sType}' ไม่ถูกต้อง (ใช้ basic, additional หรือ activity)";
                }
                if ($grade < 1 || $grade > 6) {
                    $rowErrors[] = "ระดับชั้น '{$grade}' ต้องเป็น 1 ถึง 6";
                }

                $cleanItem = [
                    'code'            => $code,
                    'name'            => $name,
                    'department_code' => $deptCode,
                    'department_id'   => $deptMap[$deptCode] ?? 0,
                    'type'            => $sType,
                    'credit'          => $sType === 'activity' ? 0.0 : $credit,
                    'hours'           => $hours > 0 ? $hours : 60,
                    'grade_level'     => $grade,
                ];
            } elseif ($type === 'courses') {
                $subjCode   = $cols[0] ?? '';
                $clsName    = preg_replace('/^ม\.+/', 'ม.', $cols[1] ?? '');
                $tUser      = strtolower(ltrim($cols[2] ?? '', '@'));
                $coUser     = strtolower(ltrim($cols[3] ?? '', '@'));
                $rCollect   = (int)($cols[4] ?? 70);
                $rFinal     = (int)($cols[5] ?? 30);

                if (!isset($subjMap[$subjCode])) {
                    $rowErrors[] = "ไม่พบรหัสวิชา '{$subjCode}' ในระบบ (กรุณานำเข้ารายวิชาก่อน)";
                }
                if (!isset($clsMap[$clsName])) {
                    $rowErrors[] = "ไม่พบห้องเรียน '{$clsName}' ในภาคเรียนนี้";
                }
                if (!isset($userMap[$tUser])) {
                    $rowErrors[] = "ไม่พบ Username ครูผู้สอน '{$tUser}' ในระบบ";
                }
                if ($coUser !== '' && !isset($userMap[$coUser])) {
                    $rowErrors[] = "ไม่พบ Username ครูสอนร่วม '{$coUser}' ในระบบ";
                }
                if (($rCollect + $rFinal) !== 100 || $rCollect < 0 || $rFinal < 0) {
                    $rowErrors[] = "สัดส่วนคะแนน ({$rCollect}:{$rFinal}) รวมกันต้องได้ 100 พอดี";
                }

                $cleanItem = [
                    'academic_year'      => $academicYear,
                    'semester'           => $semester,
                    'subject_code'       => $subjCode,
                    'subject_id'         => $subjMap[$subjCode] ?? 0,
                    'classroom_name'     => $clsName,
                    'classroom_id'       => $clsMap[$clsName] ?? 0,
                    'teacher_username'   => $tUser,
                    'teacher_user_id'    => $userMap[$tUser] ?? 0,
                    'co_teacher_user_id' => ($coUser !== '' && isset($userMap[$coUser])) ? $userMap[$coUser] : null,
                    'ratio_collect'      => $rCollect,
                    'ratio_final'        => $rFinal,
                ];
            }

            if (!empty($rowErrors)) {
                $errorRows[] = [
                    'row_no' => $rowNo,
                    'errors' => $rowErrors,
                    'raw'    => $cols,
                ];
            } else {
                $cleanItem['row_no'] = $rowNo;
                $validRows[]         = $cleanItem;
            }
        }

        return [
            'valid_rows' => $validRows,
            'error_rows' => $errorRows,
        ];
    }

    /**
     * ยืนยันและบันทึกข้อมูลที่ผ่านการตรวจสอบแล้วลงฐานข้อมูล
     */
    public function commitImportRows(string $type, array $validRows, int $academicYear, int $semester): int
    {
        if (empty($validRows)) {
            return 0;
        }

        $importedCount = 0;

        if ($type === 'students') {
            foreach ($validRows as $item) {
                $this->masterService->saveStudent($item, $academicYear, $semester);
                $importedCount++;
            }
        } elseif ($type === 'teachers') {
            $this->pdo->beginTransaction();
            try {
                $defaultHash = password_hash('123456', PASSWORD_BCRYPT);
                $stmtUser = $this->pdo->prepare("
                    INSERT INTO `llw_users` (`username`, `password`, `prefix`, `firstname`, `lastname`, `department_id`, `email`, `role`, `status`, `force_password_change`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1)
                    ON DUPLICATE KEY UPDATE
                        `prefix`        = VALUES(`prefix`),
                        `firstname`     = VALUES(`firstname`),
                        `lastname`      = VALUES(`lastname`),
                        `department_id` = VALUES(`department_id`),
                        `email`         = VALUES(`email`)
                ");
                $stmtGetUid = $this->pdo->prepare("SELECT `user_id` FROM `llw_users` WHERE `username` = ? LIMIT 1");
                $stmtAttT   = $this->pdo->prepare("
                    INSERT INTO `att_teachers` (`name`, `username`, `password`, `llw_user_id`)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `llw_user_id` = VALUES(`llw_user_id`)
                ");

                foreach ($validRows as $item) {
                    $primaryRole = $item['role'] === 'department_head' ? 'att_teacher' : $item['role'];
                    $stmtUser->execute([
                        $item['username'],
                        $defaultHash,
                        $item['prefix'],
                        $item['first_name'],
                        $item['last_name'],
                        $item['department_id'],
                        $item['email'],
                        $primaryRole,
                    ]);
                    $stmtGetUid->execute([$item['username']]);
                    $uid = (int)$stmtGetUid->fetchColumn();

                    if ($uid > 0) {
                        $fullName = trim($item['prefix'] . $item['first_name'] . ' ' . $item['last_name']);
                        $stmtAttT->execute([$fullName, $item['username'], $defaultHash, $uid]);
                        if ($item['role'] === 'department_head' && !empty($item['department_id'])) {
                            $this->pdo->prepare("UPDATE `pp5_departments` SET `head_user_id` = ? WHERE `id` = ?")->execute([$uid, $item['department_id']]);
                        }
                    }
                    $importedCount++;
                }
                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        } elseif ($type === 'subjects') {
            foreach ($validRows as $item) {
                $this->masterService->saveSubject($item);
                $importedCount++;
            }
        } elseif ($type === 'courses') {
            foreach ($validRows as $item) {
                $this->masterService->saveCourse($item);
                $importedCount++;
            }
        }

        $this->logger->log(
            'excel.import',
            "นำเข้าข้อมูลประเภท {$type} สำเร็จจำนวน {$importedCount} รายการ (ภาคเรียนที่ {$semester}/{$academicYear})",
            "pp5_{$type}"
        );

        return $importedCount;
    }
}
