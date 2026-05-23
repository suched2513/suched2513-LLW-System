<?php
/**
 * parent_meeting/print_summary.php - หน้าพรีวิวรายงานสรุปการเข้าประชุมและการขาดประชุมเพื่อพิมพ์ / PDF
 */
require_once __DIR__ . '/config.php';
checkRole(['executive', 'admin']); // เฉพาะผู้บริหารและแอดมิน

$pdo = getPmPdo();

$type = $_GET['type'] ?? 'summary'; // 'summary' หรือ 'absents'
$selSemester = $_GET['semester'] ?? '';
$selYear = $_GET['academic_year'] ?? '';
$selLevel = $_GET['level'] ?? '';

// สร้าง SQL Query แบบ Dynamic ตามตัวเลือก
$query = "
    SELECT m.*, c.level, c.room_name, u.fullname as creator_name,
           COALESCE(
               (SELECT GROUP_CONCAT(CONCAT(lu.firstname, ' ', lu.lastname)
                       ORDER BY la.role_type ASC, la.id ASC SEPARATOR ' และ ')
                FROM llw_class_advisors la
                LEFT JOIN llw_users lu ON la.user_id = lu.user_id
                WHERE la.classroom = CONCAT(c.level, '/', c.room_name)),
               c.teacher_name
           ) as teacher_name
    FROM pm_meetings m
    JOIN pm_classrooms c ON m.classroom_id = c.id
    JOIN pm_users u ON m.created_by = u.id
    WHERE 1=1
";
$params = [];

if ($selSemester !== '') {
    $query .= " AND m.semester = ?";
    $params[] = $selSemester;
}
if ($selYear !== '') {
    $query .= " AND m.academic_year = ?";
    $params[] = $selYear;
}
if ($selLevel !== '') {
    $query .= " AND c.level = ?";
    $params[] = $selLevel;
}

$query .= " ORDER BY c.level, c.room_name";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    // ดึงข้อมูลผู้ขาดประชุมของแต่ละห้อง
    $meetingIds = array_column($reports, 'id');
    $absentsByMeeting = [];
    if (!empty($meetingIds)) {
        $inClause = implode(',', array_fill(0, count($meetingIds), '?'));
        $absentStmt = $pdo->prepare("SELECT * FROM pm_meeting_absents WHERE meeting_id IN ($inClause) ORDER BY id ASC");
        $absentStmt->execute($meetingIds);
        $allAbsents = $absentStmt->fetchAll();
        
        foreach ($allAbsents as $abs) {
            $absentsByMeeting[$abs['meeting_id']][] = $abs;
        }
    }
} catch (Exception $e) {
    error_log('[Parent Meeting] Summary Print Fetch Error: ' . $e->getMessage());
    $reports = [];
    $absentsByMeeting = [];
}

// อัปเดตยอดจริงด้วยข้อมูลจาก pm_meeting_absents เสมอ
foreach ($reports as &$r) {
    $mId = $r['id'];
    $actualAbsent = isset($absentsByMeeting[$mId]) ? count($absentsByMeeting[$mId]) : 0;
    $r['absent_count'] = $actualAbsent;
    $r['attend_count'] = max(0, (int)$r['total_parents'] - $actualAbsent);
}
unset($r);

// คำนวณสถิติรวมสำหรับรายงาน
$totalClassroomsCount = count($reports);
$sumStudents = 0;
$sumAttend = 0;
$sumAbsent = 0;
foreach ($reports as $r) {
    $sumStudents += (int)$r['total_parents'];
    $sumAttend += (int)$r['attend_count'];
    $sumAbsent += (int)$r['absent_count'];
}
$overallAttendRate = $sumStudents > 0 ? round(($sumAttend / $sumStudents) * 100, 1) : 0;
$overallAbsentRate = $sumStudents > 0 ? round(($sumAbsent / $sumStudents) * 100, 1) : 0;

function th_num($num) {
    $thai_nums = ['0'=>'๐','1'=>'๑','2'=>'๒','3'=>'๓','4'=>'๔','5'=>'๕','6'=>'๖','7'=>'๗','8'=>'๘','9'=>'๙'];
    return strtr((string)$num, $thai_nums);
}

// ฟังก์ชันแปลงวันที่รูปแบบย่อ (ไทย)
function th_date_short($dateStr) {
    if (!$dateStr) return '';
    $time = strtotime($dateStr);
    $thai_months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    $y = date('Y', $time) + 543;
    $m = date('n', $time);
    $d = date('j', $time);
    return "$d " . $thai_months[$m] . " " . substr($y, 2, 2);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $type === 'absents' ? 'รายชื่อผู้ปกครองไม่เข้าร่วมประชุม' : 'รายงานสรุปผลภาพรวมการประชุมผู้ปกครอง' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Prompt:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: 'Prompt', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Sticky Control Bar */
        .no-print-bar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border-bottom: 2px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            padding: 15px 25px;
        }
        
        /* A4 Page layout simulated on screen */
        .print-page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 15mm 20mm 20mm; /* Left 2cm, Right 1.5cm, Top 2cm, Bottom 2cm */
            margin: 15mm auto;
            background: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            position: relative;
            font-family: 'Sarabun', sans-serif;
            color: #000;
            font-size: 15px;
            line-height: 1.6;
            box-sizing: border-box;
        }

        /* Table Styles for official document */
        .print-page table {
            color: #000 !important;
            border-color: #000 !important;
            font-family: 'Sarabun', sans-serif;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .report-table th, .report-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            vertical-align: middle;
        }
        .report-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
        }

        /* Official Signature Block stacked vertically */
        .signature-section {
            margin-top: 30px;
            font-size: 15px;
            font-family: 'Sarabun', sans-serif;
            page-break-inside: avoid;
        }
        
        .signature-container {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            padding-right: 50px;
            margin-top: 15px;
        }
        
        .signature-block {
            text-align: center;
            width: 280px;
            line-height: 1.8;
        }

        .text-justify {
            text-align: justify;
        }
        .indent-1 {
            text-indent: 1.5cm;
        }
        
        @media print {
            body {
                background-color: #fff !important;
            }
            .no-print-bar {
                display: none !important;
            }
            .print-page {
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                width: 100% !important;
                min-height: auto !important;
                padding: 0mm 0mm 0mm 10mm !important;
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>

    <!-- Control Bar (Visible on screen only) -->
    <div class="no-print-bar d-flex justify-content-between align-items-center">
        <div>
            <h5 class="m-0 font-bold text-dark"><i class="bi bi-printer me-2 text-primary"></i> preview การพิมพ์เอกสารสรุป</h5>
            <small class="text-muted">ปรับแต่งหน้าตาให้ตรงตามสัดส่วนกระดาษ A4 เพื่อพร้อมพิมพ์หรือบันทึกเป็น PDF</small>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary font-bold shadow-sm px-4">
                <i class="bi bi-printer-fill me-1"></i> พิมพ์เอกสาร / PDF
            </button>
            <button onclick="window.close()" class="btn btn-outline-secondary font-bold px-4">
                <i class="bi bi-x-lg me-1"></i> ปิดหน้าต่าง
            </button>
        </div>
    </div>

    <!-- Container page A4 -->
    <div class="print-page">
        
        <!-- ส่วนหัวตราโรงเรียน -->
        <div class="text-center position-relative mb-4">
            <img src="https://suched2513.github.io/image/%E0%B8%95%E0%B8%A3%E0%B8%B2%E0%B8%A5%E0%B8%B0%E0%B8%A5%E0%B8%A1%E0%B8%A7%E0%B8%B4%E0%B8%9767.png" style="width: 2.8cm; height: auto;" alt="Lalom Wittaya School Logo">
            
            <h4 class="font-bold mt-3 mb-1" style="font-size: 20px;">โรงเรียนละลมวิทยา</h4>
            
            <?php if ($type === 'absents'): ?>
                <h5 class="font-bold mb-1" style="font-size: 18px;">รายชื่อผู้ปกครองที่ไม่ได้เข้าร่วมประชุมผู้ปกครองภาคเรียนที่ <?= th_num($selSemester ?: '...') ?> ปีการศึกษา <?= th_num($selYear ?: '...') ?></h5>
                <?php if (!empty($selLevel)): ?>
                    <p class="mb-0 text-muted" style="font-size: 15px;">ระดับชั้นมัธยมศึกษาปีที่ <?= th_num(str_replace('ม.', '', $selLevel)) ?></p>
                <?php else: ?>
                    <p class="mb-0 text-muted" style="font-size: 15px;">ระดับชั้นมัธยมศึกษาตอนต้นและตอนปลาย</p>
                <?php endif; ?>
            <?php else: ?>
                <h5 class="font-bold mb-1" style="font-size: 18px;">รายงานสรุปผลภาพรวมการเข้าร่วมประชุมผู้ปกครอง (Classroom Meeting)</h5>
                <p class="mb-0 text-muted" style="font-size: 15px;">ภาคเรียนที่ <?= th_num($selSemester ?: '...') ?> ปีการศึกษา <?= th_num($selYear ?: '...') ?> <?= !empty($selLevel) ? 'ระดับชั้น ' . $selLevel : '' ?></p>
            <?php endif; ?>
        </div>

        <hr style="border-top: 1.5px solid #000;" class="my-3">

        <?php if ($type === 'absents'): ?>
            <!-- -----------------------------------------
                 LAYOUT: รายชื่อผู้ขาดประชุม ( type = absents )
                 ----------------------------------------- -->
            <div class="mb-4">
                <p class="indent-1 text-justify">
                    ตามที่โรงเรียนละลมวิทยา ได้ดำเนินการจัดการประชุมผู้ปกครองนักเรียน (Classroom Meeting) 
                    เพื่อประสานความเข้าใจร่วมกันระหว่างโรงเรียนและผู้ปกครอง ในภาคเรียนที่ <?= th_num($selSemester ?: '...') ?> ปีการศึกษา <?= th_num($selYear ?: '...') ?> 
                    บัดนี้ ได้มีรายงานสรุปรายชื่อผู้ปกครองของนักเรียนที่ไม่สามารถเข้าร่วมประชุมตามกำหนดการดังกล่าวได้ เพื่อให้ครูที่ปรึกษาและงานที่เกี่ยวข้องดำเนินการติดตาม ประสานสัมพันธ์ และชี้แจงข่าวสารย้อนหลัง ดังรายละเอียดบัญชีรายชื่อดังต่อไปนี้
                </p>
            </div>

            <?php if (empty($reports)): ?>
                <div class="text-center py-5 border rounded bg-light">
                    ไม่พบข้อมูลรายงานการประชุมและรายชื่อผู้ขาดการประชุมตามเงื่อนไขที่เลือก
                </div>
            <?php else: ?>
                <?php 
                $hasAbsents = false;
                foreach ($reports as $r):
                    $mId = $r['id'];
                    $absentsList = $absentsByMeeting[$mId] ?? [];
                    if (empty($absentsList)) continue;
                    $hasAbsents = true;
                    $classroomName = 'ชั้นมัธยมศึกษาปีที่ ' . esc($r['level'] . '/' . $r['room_name']);
                ?>
                    <div class="mt-4 page-break-inside-avoid">
                        <h6 class="font-bold border-bottom pb-1 mb-2 text-primary" style="font-size: 16px;">
                            <i class="bi bi-door-open-fill me-1"></i> ห้อง <?= esc($r['level'] . '/' . $r['room_name']) ?> — ครูที่ปรึกษา: <?= esc(format_teacher_names($r['teacher_name'])) ?>
                        </h6>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th style="width: 8%;">ลำดับ</th>
                                    <th style="width: 25%;">ชื่อนักเรียน</th>
                                    <th style="width: 25%;">ชื่อผู้ปกครอง</th>
                                    <th style="width: 15%;">ความสัมพันธ์</th>
                                    <th style="width: 15%;">เบอร์ติดต่อ</th>
                                    <th style="width: 22%;">สาเหตุการขาด</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($absentsList as $index => $abs): ?>
                                    <tr>
                                        <td class="text-center"><?= th_num($index + 1) ?></td>
                                        <td><?= esc($abs['student_name']) ?></td>
                                        <td><?= esc($abs['parent_name'] ?: '-') ?></td>
                                        <td class="text-center"><?= esc($abs['relationship'] ?: '-') ?></td>
                                        <td class="text-center"><?= esc($abs['phone'] ?: '-') ?></td>
                                        <td><?= esc($abs['absent_reason'] ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$hasAbsents): ?>
                    <div class="text-center py-5 border rounded bg-light">
                        <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                        ไม่มีรายชื่อผู้ขาดประชุมตามเงื่อนไขที่เลือก (ผู้ปกครองเข้าร่วมประชุมครบ 100%)
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ลายมือชื่อการลงประกาศ -->
            <div class="signature-section">
                <div class="signature-container">
                    <div class="signature-block">
                        <p class="mb-4">ลงชื่อ.......................................................... ผู้เสนอประกาศ</p>
                        <p class="mb-0">(..........................................................)</p>
                        <p class="mb-0">ตำแหน่ง..........................................................</p>
                        <p class="mb-0">วันที่......./......./.......</p>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- -----------------------------------------
                 LAYOUT: รายงานสรุปภาพรวม ( type = summary )
                 ----------------------------------------- -->
            <div class="mb-4">
                <p class="indent-1 text-justify">
                    ตามที่ โรงเรียนละลมวิทยา ได้ดำเนินการจัดกิจกรรมการประชุมผู้ปกครองนักเรียน (Classroom Meeting) 
                    ภาคเรียนที่ <?= th_num($selSemester ?: '...') ?> ปีการศึกษา <?= th_num($selYear ?: '...') ?> 
                    บัดนี้ งานพัฒนาผู้เรียนและงานกิจการนักเรียนได้ทำการรวบรวมรายงานสรุปผลการเข้าประชุมผู้ปกครองแยกตามห้องเรียนและระดับชั้น
                    โดยสรุปข้อมูลภาพรวมการเข้าร่วมประชุมได้ดังรายละเอียดด้านล่างนี้:
                </p>
            </div>

            <!-- กล่องสถิติสี่เหลี่ยมอย่างเป็นทางการ -->
            <table class="report-table mb-4">
                <thead>
                    <tr>
                        <th colspan="4" class="py-2 text-center" style="font-size: 16px;">สถิติการเข้าร่วมประชุมผู้ปกครองภาพรวมทั้งสิ้น</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="text-center" style="font-size: 15px;">
                        <td style="width: 25%;" class="py-3">
                            <div class="text-muted text-xs">จำนวนห้องที่รายงาน</div>
                            <div class="font-bold fs-4"><?= th_num($totalClassroomsCount) ?> ห้อง</div>
                        </td>
                        <td style="width: 25%;" class="py-3">
                            <div class="text-muted text-xs">จำนวนนักเรียนทั้งหมด</div>
                            <div class="font-bold fs-4"><?= th_num(number_format($sumStudents)) ?> คน</div>
                        </td>
                        <td style="width: 25%;" class="py-3 text-success">
                            <div class="text-success-emphasis text-xs">ผู้ปกครองที่เข้าร่วม</div>
                            <div class="font-bold fs-4"><?= th_num(number_format($sumAttend)) ?> คน</div>
                            <small class="fw-bold opacity-75">(คิดเป็นร้อยละ <?= th_num($overallAttendRate) ?>)</small>
                        </td>
                        <td style="width: 25%;" class="py-3 text-danger">
                            <div class="text-danger-emphasis text-xs">ผู้ปกครองที่ไม่มาประชุม</div>
                            <div class="font-bold fs-4"><?= th_num(number_format($sumAbsent)) ?> คน</div>
                            <small class="fw-bold opacity-75">(คิดเป็นร้อยละ <?= th_num($overallAbsentRate) ?>)</small>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h6 class="font-bold mb-2" style="font-size: 16px;"><i class="bi bi-table me-1"></i> ตารางแจกแจงอัตราการเข้าร่วมประชุมผู้ปกครองรายห้องเรียน:</h6>
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">ลำดับ</th>
                        <th style="width: 15%;">ห้องเรียน</th>
                        <th>ครูที่ปรึกษา</th>
                        <th style="width: 15%;">นักเรียนทั้งหมด (คน)</th>
                        <th style="width: 13%;">มา (คน)</th>
                        <th style="width: 13%;">ขาด (คน)</th>
                        <th style="width: 15%;">ร้อยละที่มา</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1; 
                    foreach ($reports as $r): 
                        $rate = $r['total_parents'] > 0 ? round(($r['attend_count'] / $r['total_parents']) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td class="text-center"><?= th_num($no++) ?></td>
                            <td class="text-center font-bold">ม.<?= esc($r['level'] . '/' . $r['room_name']) ?></td>
                            <td><?= esc(format_teacher_names($r['teacher_name'])) ?></td>
                            <td class="text-center"><?= th_num($r['total_parents']) ?></td>
                            <td class="text-center text-success"><?= th_num($r['attend_count']) ?></td>
                            <td class="text-center text-danger"><?= th_num($r['absent_count']) ?></td>
                            <td class="text-center font-bold <?= $rate >= 80 ? 'text-success' : 'text-warning-emphasis' ?>"><?= th_num($rate) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="fw-bold bg-light">
                        <td colspan="3" class="text-center">สรุปรวมทั้งหมด</td>
                        <td class="text-center"><?= th_num(number_format($sumStudents)) ?></td>
                        <td class="text-center text-success"><?= th_num(number_format($sumAttend)) ?></td>
                        <td class="text-center text-danger"><?= th_num(number_format($sumAbsent)) ?></td>
                        <td class="text-center text-primary" style="font-size: 16px;"><?= th_num($overallAttendRate) ?>%</td>
                    </tr>
                </tbody>
            </table>

            <!-- ส่วนลงความเห็นของผู้บริหาร -->
            <div class="signature-section mt-5 page-break-inside-avoid">
                <div class="row g-4 text-center mt-3">
                    <div class="col-4">
                        <p class="mb-4">ลงชื่อ.......................................................... ผู้สรุปรายงาน</p>
                        <p class="mb-0">(..........................................................)</p>
                        <p class="mb-0">ตำแหน่ง..........................................................</p>
                    </div>
                    <div class="col-4">
                        <p class="mb-4">ลงชื่อ.......................................................... ผู้ตรวจประเมิน</p>
                        <p class="mb-0">(..........................................................)</p>
                        <p class="mb-0">ตำแหน่ง หัวหน้างานกิจการนักเรียน</p>
                    </div>
                    <div class="col-4">
                        <p class="mb-4">ลงชื่อ.......................................................... ผู้อนุมัติ</p>
                        <p class="mb-0">(..........................................................)</p>
                        <p class="mb-0">ตำแหน่ง ผู้อำนวยการโรงเรียนละลมวิทยา</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
