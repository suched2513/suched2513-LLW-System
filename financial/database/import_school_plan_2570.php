<?php
/**
 * financial/database/import_school_plan_2570.php
 * นำเข้าข้อมูลแผนปฏิบัติการและงบประมาณประจำปี พ.ศ. 2570 ของโรงเรียนละลมวิทยา
 * ครบทุกฝ่ายงาน (วิชาการ, บริหารทั่วไป, บุคคล, แผนงานงบประมาณ) รวม 148 กิจกรรม
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

function runSchoolPlanImport(PDO $pdo): array
{
    // 1. ตรวจสอบ Schema Patch
    Database::patchDatabaseSchema($pdo);

    // 2. ตรวจสอบปีงบประมาณ 2570
    $fyStmt = $pdo->prepare("SELECT id FROM fin_fiscal_years WHERE year_be = 2570 LIMIT 1");
    $fyStmt->execute();
    $fyId = (int)$fyStmt->fetchColumn();

    if (!$fyId) {
        $pdo->exec("INSERT INTO fin_fiscal_years (year_be, start_date, end_date, is_current, is_closed) 
                    VALUES (2570, '2026-10-01', '2027-09-30', 1, 0)");
        $fyId = (int)$pdo->lastInsertId();
    }

    // 3. ตรวจสอบ Funding Sources (SUB-01, SUB-02, REV-01)
    $getSourceId = function(string $code) use ($pdo): int {
        $stmt = $pdo->prepare("SELECT id FROM fin_funding_sources WHERE source_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            throw new Exception("ไม่พบประเภทการเงินรหัส {$code} ในระบบ กรุณา seed ประเภทการเงินก่อน");
        }
        return (int)$id;
    };

    $sub01Id = $getSourceId('SUB-01'); // ค่าจัดการเรียนการสอน (เงินอุดหนุน)
    $sub02Id = $getSourceId('SUB-02'); // กิจกรรมพัฒนาคุณภาพผู้เรียน
    $rev01Id = $getSourceId('REV-01'); // เงินรายได้สถานศึกษา

    // 4. ไฟล์ CSV ทั้ง 4 ฝ่าย
    $files = [
        'ACAD' => [
            'name' => 'ฝ่ายบริหารงานวิชาการ',
            'file' => __DIR__ . '/../storage/sheets/2_academic_397508199.csv'
        ],
        'GEN'  => [
            'name' => 'ฝ่ายบริหารงานทั่วไป',
            'file' => __DIR__ . '/../storage/sheets/3_general_1711717387.csv'
        ],
        'PERS' => [
            'name' => 'ฝ่ายบริหารงานบุคคล',
            'file' => __DIR__ . '/../storage/sheets/4_personnel_914416083.csv'
        ],
        'PLAN' => [
            'name' => 'ฝ่ายบริหารงานงบประมาณ นโยบายและแผนงาน',
            'file' => __DIR__ . '/../storage/sheets/5_budget_plan_1078076210.csv'
        ]
    ];

    $cleanNum = function($v): float {
        if (!$v) return 0.0;
        $v = preg_replace('/[^\d\.\-]/', '', (string)$v);
        return is_numeric($v) ? (float)$v : 0.0;
    };

    $insertedProjects = 0;
    $insertedBudgets = 0;
    $totalSubsidy = 0.0;
    $totalDevelop = 0.0;
    $totalRevenue = 0.0;

    $stmtProject = $pdo->prepare("
        INSERT INTO fin_projects 
            (fiscal_year_id, project_code, project_name, activity_name, department, responsible_person, planned_amount, notes, status, created_at)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ON DUPLICATE KEY UPDATE 
            project_name = VALUES(project_name),
            activity_name = VALUES(activity_name),
            department = VALUES(department),
            responsible_person = VALUES(responsible_person),
            planned_amount = VALUES(planned_amount),
            notes = VALUES(notes),
            status = 'active'
    ");

    $stmtBudget = $pdo->prepare("
        INSERT INTO fin_budgets 
            (fiscal_year_id, project_id, funding_source_id, budget_category, initial_amount, committed_amount, paid_amount)
        VALUES 
            (?, ?, ?, ?, ?, 0.00, 0.00)
        ON DUPLICATE KEY UPDATE 
            initial_amount = VALUES(initial_amount)
    ");

    $pdo->beginTransaction();

    try {
        foreach ($files as $prefix => $info) {
            $deptName = $info['name'];
            $filepath = $info['file'];

            if (!file_exists($filepath)) {
                continue;
            }

            $fh = fopen($filepath, 'r');
            $currentProject = '';
            $projIndex = 0;
            $actIndex = 0;
            $rowIdx = 0;

            while (($row = fgetcsv($fh)) !== false) {
                $rowIdx++;
                if ($rowIdx <= 4) continue;

                $pName = trim($row[0] ?? '');
                $aName = trim($row[1] ?? '');
                $reqBudget = trim($row[2] ?? '');
                $appSubsidy = trim($row[3] ?? '');
                $appDevelop = trim($row[4] ?? '');
                $appRevenue = ($prefix === 'GEN') ? trim($row[5] ?? '') : '';
                $responsible = ($prefix === 'GEN') ? trim($row[6] ?? '') : trim($row[5] ?? '');
                $notes = ($prefix === 'GEN') ? trim($row[7] ?? '') : trim($row[6] ?? '');

                if (str_starts_with($pName, 'รวม') || str_starts_with($aName, 'รวม') || $pName === 'ย' || $aName === 'ย') continue;
                if (empty($pName) && empty($aName) && empty($reqBudget) && empty($appSubsidy)) continue;

                if (!empty($pName) && $pName !== 'ชื่อโครงการ') {
                    $currentProject = $pName;
                    $projIndex++;
                    $actIndex = 0;
                }

                if (!empty($aName) && $aName !== 'กิจกรรม') {
                    $actIndex++;
                    $sub = $cleanNum($appSubsidy);
                    $dev = $cleanNum($appDevelop);
                    $rev = $cleanNum($appRevenue);
                    $totalApproved = $sub + $dev + $rev;

                    // สร้างรหัสโครงการ/กิจกรรมที่เป็นมาตรฐาน: เช่น ACAD-01-01, GEN-02-04
                    $pCode = sprintf('%s-%02d-%02d', $prefix, $projIndex, $actIndex);

                    $stmtProject->execute([
                        $fyId,
                        $pCode,
                        $currentProject ?: $aName,
                        $aName,
                        $deptName,
                        $responsible ?: null,
                        $totalApproved,
                        $notes ?: null
                    ]);

                    // ดึง project_id
                    $projIdStmt = $pdo->prepare("SELECT id FROM fin_projects WHERE fiscal_year_id = ? AND project_code = ? LIMIT 1");
                    $projIdStmt->execute([$fyId, $pCode]);
                    $projectId = (int)$projIdStmt->fetchColumn();

                    // บันทึกงบประมาณแยกตามแหล่งเงิน
                    // 1. เงินอุดหนุน (SUB-01)
                    if ($sub > 0) {
                        $stmtBudget->execute([$fyId, $projectId, $sub01Id, 'subsidy', $sub]);
                        $insertedBudgets++;
                        $totalSubsidy += $sub;
                    }

                    // 2. กิจกรรมพัฒนาคุณภาพผู้เรียน (SUB-02)
                    if ($dev > 0) {
                        $stmtBudget->execute([$fyId, $projectId, $sub02Id, 'subsidy', $dev]);
                        $insertedBudgets++;
                        $totalDevelop += $dev;
                    }

                    // 3. เงินรายได้สถานศึกษา (REV-01)
                    if ($rev > 0) {
                        $stmtBudget->execute([$fyId, $projectId, $rev01Id, 'operating', $rev]);
                        $insertedBudgets++;
                        $totalRevenue += $rev;
                    }

                    $insertedProjects++;
                }
            }
            fclose($fh);
        }

        $pdo->commit();

        return [
            'success'          => true,
            'total_activities' => $insertedProjects,
            'total_budgets'    => $insertedBudgets,
            'total_subsidy'    => $totalSubsidy,
            'total_develop'    => $totalDevelop,
            'total_revenue'    => $totalRevenue,
            'grand_total'      => $totalSubsidy + $totalDevelop + $totalRevenue
        ];
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ถ้าเรียกใช้งานโดยตรงผ่าน CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "=====================================================================" . PHP_EOL;
    echo "  นำเข้าแผนงบประมาณโครงการและกิจกรรม ประจำปี พ.ศ. 2570" . PHP_EOL;
    echo "  โรงเรียนละลมวิทยา | Lalompittayakhom School" . PHP_EOL;
    echo "=====================================================================" . PHP_EOL;

    try {
        $pdo = Database::getConnection();
        $result = runSchoolPlanImport($pdo);
        echo ">>> นำเข้าข้อมูลสำเร็จสมบูรณ์!" . PHP_EOL;
        echo "  - จำนวนโครงการ/กิจกรรมทั้งหมด : {$result['total_activities']} รายการ" . PHP_EOL;
        echo "  - รายการจัดสรรงบประมาณ (Budgets) : {$result['total_budgets']} รายการ" . PHP_EOL;
        echo "  - งบประมาณเงินอุดหนุน (SUB-01)    : " . number_format($result['total_subsidy'], 2) . " บาท" . PHP_EOL;
        echo "  - งบพัฒนาคุณภาพผู้เรียน (SUB-02)  : " . number_format($result['total_develop'], 2) . " บาท" . PHP_EOL;
        echo "  - เงินรายได้สถานศึกษา (REV-01)   : " . number_format($result['total_revenue'], 2) . " บาท" . PHP_EOL;
        echo "  - ยอดรวมงบประมาณที่จัดสรรทั้งสิ้น : " . number_format($result['grand_total'], 2) . " บาท" . PHP_EOL;
        echo "=====================================================================" . PHP_EOL;
    } catch (\Throwable $e) {
        echo "[ERROR] ไม่สามารถนำเข้าข้อมูลได้: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}
