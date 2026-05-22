<?php
/**
 * parent_meeting/print_report.php - หน้าพรีวิวรายงานการประชุมและเครือข่ายสำหรับพิมพ์ / บันทึก PDF (Print-Ready)
 */
require_once __DIR__ . '/config.php';
checkLogin();

$meetingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($meetingId === 0) {
    exit('ไม่พบรหัสรายงานที่ต้องการพิมพ์');
}

$pdo = getPmPdo();

try {
    // 1. ดึงข้อมูลรายงานการประชุม พร้อมครูที่ปรึกษาจาก llw_class_advisors (dynamic — รองรับ 2 คน)
    $stmt = $pdo->prepare("
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
        WHERE m.id = ?
    ");
    $stmt->execute([$meetingId]);
    $meeting = $stmt->fetch();
    
    if (!$meeting) {
        exit('ไม่พบข้อมูลรายงานการประชุมนี้ในระบบ');
    }
    
    // ตรวจสอบสิทธิ์การเข้าถึงรายงานการประชุม (สำหรับครู)
    if ($_SESSION['pm_role'] === 'teacher') {
        if (!hasMeetingAccess($meetingId)) {
            exit('คุณไม่มีสิทธิ์เข้าถึงรายงานการประชุมฉบับนี้');
        }
    }
    
    // 2. ดึงรูปกิจกรรมประกอบรายงาน
    $imgStmt = $pdo->prepare("SELECT * FROM pm_meeting_images WHERE meeting_id = ?");
    $imgStmt->execute([$meetingId]);
    $images = $imgStmt->fetchAll();
    
    // 3. ดึงความเห็นผู้บริหาร
    $cmtStmt = $pdo->prepare("
        SELECT c.*, u.fullname as commenter_name 
        FROM pm_comments c
        JOIN pm_users u ON c.commented_by = u.id
        WHERE c.meeting_id = ?
        ORDER BY c.created_at ASC
    ");
    $cmtStmt->execute([$meetingId]);
    $comments = $cmtStmt->fetchAll();
    
    // 4. ดึงเครือข่ายผู้ปกครอง
    $netStmt = $pdo->prepare("SELECT * FROM pm_network_parents WHERE meeting_id = ?");
    $netStmt->execute([$meetingId]);
    $rawNetwork = $netStmt->fetchAll();
    
    $network = [];
    foreach ($rawNetwork as $p) {
        if ($p['position_name'] === 'กรรมการ') {
            $network['กรรมการ'][] = $p;
        } else {
            $network[$p['position_name']] = $p;
        }
    }

    // 5. ดึงข้อมูลผู้เข้าร่วม (ลลว.๐๒)
    $attStmt = $pdo->prepare("SELECT * FROM pm_meeting_attendants WHERE meeting_id = ? ORDER BY id ASC");
    $attStmt->execute([$meetingId]);
    $attendants = $attStmt->fetchAll();
    
    // 6. ดึงข้อมูลผู้ขาด (ลลว.๐๓)
    $absStmt = $pdo->prepare("SELECT * FROM pm_meeting_absents WHERE meeting_id = ? ORDER BY id ASC");
    $absStmt->execute([$meetingId]);
    $absents = $absStmt->fetchAll();
    
    // 7. ดึงข้อมูลประสานสัมพันธ์ (ลลว.๐๔)
    $relStmt = $pdo->prepare("SELECT * FROM pm_student_relations WHERE meeting_id = ? ORDER BY id ASC");
    $relStmt->execute([$meetingId]);
    $relations = $relStmt->fetchAll();
    foreach ($relations as &$rel) {
        $rel['praise_teacher_json'] = json_decode($rel['praise_teacher_json'] ?? '[]', true);
        $rel['praise_parent_json'] = json_decode($rel['praise_parent_json'] ?? '[]', true);
        $rel['improve_teacher_json'] = json_decode($rel['improve_teacher_json'] ?? '[]', true);
        $rel['improve_parent_json'] = json_decode($rel['improve_parent_json'] ?? '[]', true);
    }
    unset($rel);
    
    // 8. ดึงข้อมูลกลุ่ม Meet & Greet (ลลว.๐๕)
    $grpStmt = $pdo->prepare("SELECT * FROM pm_meet_greet_groups WHERE meeting_id = ? ORDER BY id ASC");
    $grpStmt->execute([$meetingId]);
    $groups = $grpStmt->fetchAll();
    foreach ($groups as &$grp) {
        $grp['attendants_json'] = json_decode($grp['attendants_json'] ?? '[]', true);
    }
    unset($grp);
    
    // 9. ดึงข้อมูลจดหมายความในใจ (ลลว.๐๖)
    $letStmt = $pdo->prepare("SELECT * FROM pm_student_letters WHERE meeting_id = ? ORDER BY id ASC");
    $letStmt->execute([$meetingId]);
    $letters = $letStmt->fetchAll();
    
} catch (Exception $e) {
    error_log('[Parent Meeting] Print Report Error: ' . $e->getMessage());
    exit('เกิดข้อผิดพลาดในการโหลดข้อมูลเพื่อจัดทำรายงานพิมพ์');
}

// แตกรายชื่อครูที่ปรึกษา
$teachers = preg_split('/(,|และ|\/)/u', $meeting['teacher_name']);
$teachers = array_map('trim', $teachers);

// สถิติติดตาม
$followed_count = 0;
foreach ($absents as $abs) {
    if (!empty($abs['follow_up_status']) && $abs['follow_up_status'] !== 'not_followed_up') {
        $followed_count++;
    }
}

$attend_pct = $meeting['total_parents'] > 0 ? round(($meeting['attend_count'] / $meeting['total_parents']) * 100, 1) : 0;
$absent_pct = $meeting['total_parents'] > 0 ? round(($meeting['absent_count'] / $meeting['total_parents']) * 100, 1) : 0;
$follow_pct = $meeting['absent_count'] > 0 ? round(($followed_count / $meeting['absent_count']) * 100, 1) : 0;

/**
 * ฟังก์ชันแปลงตัวเลขเป็นเลขไทย
 */
function th_num($num) {
    $thai_nums = ['0'=>'๐','1'=>'๑','2'=>'๒','3'=>'๓','4'=>'๔','5'=>'๕','6'=>'๖','7'=>'๗','8'=>'๘','9'=>'๙'];
    return strtr((string)$num, $thai_nums);
}

/**
 * ฟังก์ชันแปลงวันที่รูปแบบเต็มภาษาไทย (เลขไทย)
 */
function th_date_full($dateStr) {
    if (!$dateStr) return '...................................................';
    $time = strtotime($dateStr);
    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $day = date('j', $time);
    $month = $thai_months[(int)date('n', $time)];
    $year = date('Y', $time) + 543;
    return "วันที่ " . th_num($day) . " เดือน " . $month . " พ.ศ. " . th_num($year);
}

/**
 * ฟังก์ชันช่วยวาดการ์ดแสดงผลรูปและข้อมูลคนในผังทำเนียบเครือข่าย
 */
function displayPrintMemberCard($title, $data) {
    ?>
    <div class="member-card" style="border: 1.5px solid #000; border-radius: 8px; width: 190px; background: #fff; text-align: center; padding: 8px; font-family: 'Sarabun', sans-serif;">
        <div style="font-weight: bold; border-bottom: 1px solid #000; padding-bottom: 3px; margin-bottom: 6px; font-size: 12px;"><?= esc($title) ?></div>
        <?php if ($data && $data['image_path']): ?>
            <img src="<?= esc($data['image_path']) ?>" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 1px solid #ccc; margin-bottom: 6px;" alt="รูปผู้ปกครอง">
        <?php else: ?>
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #f1f5f9; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; margin: 0 auto 6px auto; font-weight: bold; color: #94a3b8; font-size: 28px;">👤</div>
        <?php endif; ?>
        <div style="font-weight: bold; font-size: 12px; color: #000;"><?= $data ? esc($data['parent_name']) : 'ยังไม่ได้แต่งตั้ง' ?></div>
        <?php if ($data): ?>
            <div style="font-size: 10px; color: #475569; margin-top: 2px;">เบอร์โทร: <?= esc($data['phone']) ?></div>
            <div style="font-size: 10px; color: #475569;">นักเรียน: <?= esc($data['student_name']) ?></div>
        <?php endif; ?>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แบบรายงานการประชุมผู้ปกครอง ม.<?= esc($meeting['level'] . '/' . $meeting['room_name']) ?></title>
    <!-- Dependencies for Screen layout & Icons -->
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
            page-break-after: always;
        }

        /* Table Styles for official document */
        .print-page table {
            color: #000 !important;
            border-color: #000 !important;
        }
        
        .behavior-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 5px;
        }
        .behavior-table th, .behavior-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            vertical-align: middle;
        }
        .behavior-table th {
            background-color: #f1f5f9;
            font-weight: bold;
        }

        /* General alignment and helpers for print output */
        .text-justify {
            text-align: justify;
        }
        .indent-1 {
            text-indent: 1.5cm;
        }
        .dotted-line {
            border-bottom: 1px dotted #000;
            display: inline-block;
            min-height: 18px;
        }
        
        /* Print rules */
        @media print {
            body {
                background: none;
                color: #000;
                margin: 0;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .print-page {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
                width: 100%;
                min-height: auto;
                padding: 20mm 15mm 20mm 20mm;
                page-break-after: always;
            }
            .print-exclude {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- แถบตั้งค่าและควบคุมการพิมพ์แบบ Interactive -->
    <div class="no-print-bar">
        <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="m-0 font-bold" style="color: #2563eb;"><i class="bi bi-printer-fill me-2"></i>ระบบรายงาน Classroom Meeting (ลลว.๐๑ - ลลว.๐๖)</h5>
                <small class="text-muted">เลือกส่วนที่ต้องการออกรายงาน/จัดพิมพ์ แล้วกดปุ่มพิมพ์รายงานหรือบันทึกเป็นไฟล์ PDF</small>
            </div>
            
            <div class="d-flex flex-wrap gap-3 my-2" style="font-size: 13px; max-width: 700px;">
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-cover" checked data-target="page-cover">
                    <label class="form-check-label font-bold" for="toggle-cover">หน้าปก</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-preface" checked data-target="page-preface">
                    <label class="form-check-label font-bold" for="toggle-preface">คำนำ/สารบัญ</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-chon01" checked data-target="page-chon01">
                    <label class="form-check-label font-bold" for="toggle-chon01">ลลว.๐๑ (บันทึก/รายงาน)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-appendix" checked data-target="page-appendix-cover">
                    <label class="form-check-label font-bold" for="toggle-appendix">หน้าคั่นภาคผนวก</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-chon02" checked data-target="page-chon02">
                    <label class="form-check-label font-bold" for="toggle-chon02">ลลว.๐๒ (ผู้เข้าร่วม)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-chon03" checked data-target="page-chon03">
                    <label class="form-check-label font-bold" for="toggle-chon03">ลลว.๐๓ (ติดตามผู้ขาด)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-chon04" checked data-target="page-chon04">
                    <label class="form-check-label font-bold" for="toggle-chon04">ลลว.๐๔ (ประสานสัมพันธ์)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-chon05" checked data-target="page-chon05">
                    <label class="form-check-label font-bold" for="toggle-chon05">ลลว.๐๕ (Meet & Greet)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-chon06" checked data-target="page-chon06">
                    <label class="form-check-label font-bold" for="toggle-chon06">ลลว.๐๖ (ความในใจลูก)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-network" checked data-target="page-network">
                    <label class="form-check-label font-bold" for="toggle-network">ผังเครือข่ายผู้ปกครอง</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input section-toggle" type="checkbox" id="toggle-gallery" checked data-target="page-gallery">
                    <label class="form-check-label font-bold" for="toggle-gallery">ภาพบรรยากาศ</label>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm rounded-pill px-4 py-2 font-bold shadow-lg" onclick="window.print()">
                    <i class="bi bi-printer-fill me-2"></i>พิมพ์ / บันทึก PDF
                </button>
                <a href="reports.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2">
                    <i class="bi bi-arrow-left me-1"></i>กลับ
                </a>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 1. COVER PAGE -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-cover text-center d-flex flex-column justify-content-between">
        <div>
            <div style="margin-top: 25mm; margin-bottom: 20mm;">
                <img src="https://suched2513.github.io/image/%E0%B8%95%E0%B8%A3%E0%B8%B2%E0%B8%A5%E0%B8%B0%E0%B8%A5%E0%B8%A1%E0%B8%A7%E0%B8%B4%E0%B8%9767.png" style="width: 4.2cm; height: auto;" alt="Lalom Wittaya School Logo">
            </div>
            <h2 style="font-weight: 800; font-size: 26px; margin-bottom: 10px; line-height: 1.6;">แบบรายงานผล<br>การจัดกิจกรรมประชุมผู้ปกครองชั้นเรียน (Classroom Meeting)</h2>
            <h3 style="font-weight: 600; font-size: 19px; margin-bottom: 20px;">ชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> &nbsp; ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> &nbsp; ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?></h3>
        </div>
        
        <div style="margin-bottom: 40mm; font-size: 16px; line-height: 2.0;">
            <strong style="font-size: 18px; display: inline-block; margin-bottom: 8px;">ครูที่ปรึกษา</strong><br>
            <?php foreach ($teachers as $idx => $t_name): ?>
                <?= th_num($idx + 1) ?>) <?= esc($t_name) ?><br>
            <?php endforeach; ?>
            <?php if (count($teachers) < 2): ?>
                ๒) ................................................................................................<br>
            <?php endif; ?>
        </div>
        
        <div style="margin-bottom: 15mm; font-size: 15px; line-height: 1.8;">
            <strong>งานระบบดูแลช่วยเหลือนักเรียนและเครือข่ายผู้ปกครองนักเรียน</strong><br>
            กลุ่มบริหารงานบุคคล โรงเรียนละลมวิทยา<br>
            สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 2. PREFACE PAGE -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-preface" style="padding: 25mm 20mm 20mm 25mm;">
        <h3 class="text-center font-bold" style="font-size: 22px; margin-bottom: 25px;">คำนำ</h3>
        
        <div class="text-justify indent-1 mb-3" style="font-size: 15px; line-height: 1.8;">
            การดูแลช่วยเหลือนักเรียน คือ การส่งเสริมพัฒนา การป้องกัน และการแก้ไขปัญหาให้แก่นักเรียน เพื่อให้นักเรียนมีคุณลักษณะที่พึงประสงค์ มีภูมิคุ้มกันทางจิตใจที่เข้มแข็ง มีคุณภาพชีวิตที่ดี มีทักษะในการดำรงชีวิตและรอดพ้นจากวิกฤตทั้งปวง ระบบการดูแลช่วยเหลือนักเรียน เป็นกระบวนการดำเนินงานดูแลช่วยเหลือนักเรียนอย่างเป็นระบบมีขั้นตอน มีครูที่ปรึกษาเป็นบุคลากรหลักในการดำเนินงาน โดยการมีส่วนร่วมของบุคลากรทุกฝ่ายที่เกี่ยวข้องทั้งภายในและภายนอกสถานศึกษา อันได้แก่ คณะกรรมการสถานศึกษาขั้นพื้นฐาน ผู้ปกครอง ชุมชน ผู้บริหาร และครูทุกคน มีวิธีการและเครื่องมือที่ชัดเจน มีมาตรฐานคุณภาพและมีหลักฐานการทำงานที่ตรวจสอบได้ ซึ่งในจำนวนผู้ที่เกี่ยวข้องทั้งหมดนี้ ฝ่ายที่น่าจะมีบทบาทมากที่สุด ก็คือ ผู้ปกครองและโรงเรียน
        </div>
        
        <div class="text-justify indent-1 mb-5" style="font-size: 15px; line-height: 1.8;">
            การจัดกิจกรรมการประชุมผู้ปกครองในชั้นเรียน (Classroom Meeting) เป็นวิธีการหนึ่งในระบบดูแลช่วยเหลือนักเรียน ที่จัดให้ครูที่ปรึกษาและผู้ปกครองได้พบปะเพื่อสนทนา ปรึกษาหารือ และแลกเปลี่ยนความคิดเห็นและประสบการณ์ในการดูแลนักเรียนระหว่างกัน เพื่อหาแนวทางในการแก้ไข ปรับปรุง และพัฒนานักเรียนในปกครองให้เป็นบุคคลที่มีคุณภาพต่อไป ใน<?= th_date_full($meeting['meeting_date']) ?> ทางโรงเรียนละลมวิทยา ได้จัดกิจกรรมประชุมผู้ปกครองในชั้นเรียน (Classroom Meeting) ในระดับชั้นมัธยมศึกษาปีที่ ๑–๖ ขึ้น ในฐานะครูที่ปรึกษาชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> จึงได้จัดทำรายงานผลการดำเนินงานเพื่อให้เห็นแนวทางในการประชุมดังกล่าว
        </div>
        
        <div style="display: flex; justify-content: flex-end; margin-top: 60px; font-family: 'Sarabun', sans-serif;">
            <div style="width: <?= count($teachers) > 1 ? '70%' : '45%' ?>; display: flex; justify-content: space-around; line-height: 1.8; font-size: 15px;">
                <?php foreach ($teachers as $t_name): ?>
                    <div style="text-align: center; width: 45%;">
                        ลงชื่อ............................................................<br>
                        (<?= esc($t_name) ?>)<br>
                        ครูที่ปรึกษา
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 3. TABLE OF CONTENTS (สารบัญ) -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-preface" style="padding: 25mm 20mm 20mm 25mm;">
        <h3 class="text-center font-bold" style="font-size: 22px; margin-bottom: 25px;">สารบัญ</h3>
        
        <table style="width: 100%; font-size: 15px; line-height: 2.2; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="border-bottom: 2px solid #000; font-weight: bold;">
                    <th style="text-align: left; padding-bottom: 5px;">เรื่อง</th>
                    <th style="text-align: right; width: 10%; padding-bottom: 5px;">หน้า</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>บันทึกข้อความรายงานผลการดำเนินงาน</td>
                    <td style="text-align: right;"></td>
                </tr>
                <tr>
                    <td>คำนำ</td>
                    <td style="text-align: right;"></td>
                </tr>
                <tr>
                    <td>สารบัญ</td>
                    <td style="text-align: right;"></td>
                </tr>
                <tr>
                    <td>รายงานผลการจัดกิจกรรมการประชุมผู้ปกครองชั้นเรียน (ลลว.๐๑)</td>
                    <td style="text-align: right;">๑</td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- ความสำคัญและความเป็นมา</td>
                    <td style="text-align: right;">๑</td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- วัตถุประสงค์ของการจัดกิจกรรมประชุมผู้ปกครองในชั้นเรียน</td>
                    <td style="text-align: right;">๑</td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- บทบาทหน้าที่ครูที่ปรึกษาในการจัดประชุมผู้ปกครองชั้นเรียน</td>
                    <td style="text-align: right;">๒</td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- ขั้นตอนแนวปฏิบัติในการประชุมผู้ปกครองชั้นเรียน</td>
                    <td style="text-align: right;">๒</td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- ผลที่คาดว่าจะได้รับ</td>
                    <td style="text-align: right;">๔</td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- สรุป และข้อเสนอแนะ</td>
                    <td style="text-align: right;">๔</td>
                </tr>
                <tr style="font-weight: bold;">
                    <td>ภาคผนวก</td>
                    <td style="text-align: right;"></td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- ใบลงชื่อผู้เข้าร่วมประชุมผู้ปกครองชั้นเรียน (ลลว.๐๒)</td>
                    <td style="text-align: right;"></td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- ใบลงชื่อการติดตามผู้ปกครองที่ไม่มาเข้าร่วมประชุม (ลลว.๐๓)</td>
                    <td style="text-align: right;"></td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- แบบบันทึกประสานสัมพันธ์ผู้ปกครองกับครูที่ปรึกษา (ลลว.๐๔)</td>
                    <td style="text-align: right;"></td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- แบบบันทึกกิจกรรมกลุ่มย่อย Meet and Greet (ลลว.๐๕)</td>
                    <td style="text-align: right;"></td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- จดหมายความในใจของลูกที่อยากบอกผู้ปกครอง (ลลว.๐๖)</td>
                    <td style="text-align: right;"></td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- ผังทำเนียบคณะกรรมการเครือข่ายผู้ปกครองประจำห้องเรียน</td>
                    <td style="text-align: right;"></td>
                </tr>
                <tr>
                    <td style="padding-left: 20px;">- ภาพบรรยากาศการจัดกิจกรรมการประชุมผู้ปกครองชั้นเรียน</td>
                    <td style="text-align: right;"></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 4. ลลว.๐๑ PAGE 1 (MEMO / บันทึกข้อความ) -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-chon01" style="padding: 20mm 15mm 20mm 20mm;">
        <div style="text-align: right; font-size: 12px; font-weight: bold; margin-bottom: 5px;">ลลว.๐๑</div>
        
        <div style="position: relative; margin-bottom: 10px; height: 2.2cm;">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/0/06/Krut_Muang_Thai.svg/120px-Krut_Muang_Thai.svg.png" style="width: 1.6cm; height: auto; position: absolute; left: 0; top: 0;" alt="Garuda Logo">
            <div style="text-align: center; font-size: 28px; font-weight: bold; padding-top: 10px;">บันทึกข้อความ</div>
        </div>
        
        <div style="font-size: 15px; line-height: 1.8; border-bottom: 1px solid #000; padding-bottom: 8px; margin-bottom: 12px;">
            <div><strong>ส่วนราชการ</strong> <span style="margin-left: 10px;">งานระบบดูแลช่วยเหลือนักเรียนและเครือข่ายผู้ปกครอง โรงเรียนละลมวิทยา</span></div>
            <div style="display: flex; justify-content: space-between;">
                <div style="flex: 1;"><strong>ที่</strong> <span style="margin-left: 10px;">พิเศษ/<?= th_num(esc($meeting['doc_no'])) ?></span></div>
                <div style="flex: 1;"><strong>วันที่</strong> <span style="margin-left: 10px;"><?= th_date_full($meeting['doc_date']) ?></span></div>
            </div>
            <div><strong>เรื่อง</strong> <span style="margin-left: 10px;">รายงานผลการดำเนินงานประชุมผู้ปกครองชั้นเรียน (Classroom Meeting) ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?></span></div>
        </div>
        
        <div style="font-size: 15px; margin-bottom: 15px;"><strong>เรียน</strong> <span style="margin-left: 10px;">ผู้อำนวยการโรงเรียนละลมวิทยา</span></div>
        
        <div class="text-justify indent-1 mb-3" style="font-size: 15px; line-height: 1.8;">
            ตามที่โรงเรียนละลมวิทยา มีคำสั่งที่ <?= th_num(esc($meeting['command_no'])) ?>/<?= th_num(esc($meeting['academic_year'])) ?> เรื่องแต่งตั้งคณะกรรมการดำเนินโครงการประชุมผู้ปกครองนักเรียน และกิจกรรมประชุมผู้ปกครองในชั้นเรียน (Classroom Meeting) ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?> ลงวันที่ <?= th_date_full($meeting['command_date']) ?> เพื่อให้บุคลากรในโรงเรียนปฏิบัติงานดังกล่าวตามที่ได้รับมอบหมาย และเพื่อให้การดำเนินการดูแลช่วยเหลือนักเรียนมีร่องรอยหลักฐานการทำงานและเป็นการรายงานผลการปฏิบัติงานอย่างต่อเนื่องอันนำไปสู่การดูแลนักเรียนอย่างมีประสิทธิภาพและเกิดประสิทธิผลสูงสุดต่อไป
        </div>
        
        <div class="text-justify indent-1 mb-4" style="font-size: 15px; line-height: 1.8;">
            บัดนี้ การดำเนินกิจกรรมประชุมผู้ปกครองนักเรียน และกิจกรรมประชุมผู้ปกครองในชั้นเรียน (Classroom Meeting) ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?> ได้เสร็จสิ้นเป็นที่เรียบร้อยแล้ว ข้าพเจ้าขอรายงานผลการจัดกิจกรรมดังกล่าว ในระดับชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> เพื่อทราบ รายละเอียดดังเอกสารที่แนบมาพร้อมนี้
        </div>
        
        <div class="indent-1 mb-4" style="font-size: 15px;">จึงเรียนมาเพื่อโปรดทราบ</div>
        
        <!-- signatures advisor -->
        <div style="display: flex; justify-content: flex-end; margin-top: 15px; font-size: 14px;">
            <div style="width: 55%; text-align: center; line-height: 1.8;">
                <?php foreach ($teachers as $idx => $t_name): ?>
                    <div style="margin-bottom: 12px;">
                        ลงชื่อ............................................................<br>
                        (<?= esc($t_name) ?>)<br>
                        ครูที่ปรึกษาชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> / ผู้รายงาน
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- admin signatures -->
        <div style="margin-top: 20px; font-size: 13px; border-top: 1px dashed #ccc; padding-top: 15px; line-height: 1.8;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding-right: 15px;">
                        <strong>ความเห็นของหัวหน้าระดับชั้น</strong><br>
                        ..............................................................................................<br>
                        ..............................................................................................<br>
                        <div style="text-align: center; margin-top: 10px;">
                            ลงชื่อ............................................................<br>
                            (............................................................)<br>
                            หัวหน้าระดับชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>
                        </div>
                    </td>
                    <td style="width: 50%; vertical-align: top; padding-left: 15px;">
                        <strong>ความเห็นของหัวหน้างานระบบดูแลช่วยเหลือนักเรียนฯ</strong><br>
                        ..............................................................................................<br>
                        ..............................................................................................<br>
                        <div style="text-align: center; margin-top: 10px;">
                            ลงชื่อ............................................................<br>
                            (นายธฤต ชำนิกุล)<br>
                            หัวหน้างานระบบดูแลช่วยเหลือนักเรียนฯ
                        </div>
                    </td>
                </tr>
                <tr style="height: 15px;">
                    <td colspan="2"></td>
                </tr>
                <tr>
                    <td style="vertical-align: top; padding-right: 15px;">
                        <strong>ความเห็นของรองผู้อำนวยการ</strong><br>
                        ..............................................................................................<br>
                        ..............................................................................................<br>
                        <div style="text-align: center; margin-top: 10px;">
                            ลงชื่อ............................................................<br>
                            (นางสาววรรณธนา วงศ์พิทักษ์)<br>
                            รองผู้อำนวยการโรงเรียนละลมวิทยา
                        </div>
                    </td>
                    <td style="vertical-align: top; padding-left: 15px;">
                        <strong>ความเห็นของผู้อำนวยการโรงเรียน</strong><br>
                        [  ] ทราบ  [  ] อนุญาต  [  ] อนุมัติ<br>
                        สั่งการเพิ่มเติม.............................................................................<br>
                        ..............................................................................................<br>
                        <div style="text-align: center; margin-top: 10px;">
                            ลงชื่อ............................................................<br>
                            (นายสถาน ปรางมาศ)<br>
                            ผู้อำนวยการโรงเรียนละลมวิทยา
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 5. ลลว.๐๑ PAGE 2 (SUMMARY REPORT) -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-chon01" style="padding: 20mm 15mm 20mm 25mm; font-size: 14px;">
        <div style="text-align: right; font-size: 12px; font-weight: bold; margin-bottom: 5px;">ลลว.๐๑ (หน้า ๒)</div>
        
        <div style="text-align: center; margin-bottom: 15px;">
            <h4 style="font-weight: bold; margin-bottom: 5px;">รายงานผลการจัดกิจกรรมการประชุมผู้ปกครองชั้นเรียน (Classroom Meeting)</h4>
            <h5 style="font-weight: bold;">ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?></h5>
            <p style="margin: 0;">ชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> &nbsp; โรงเรียนละลมวิทยา</p>
        </div>
        
        <div style="line-height: 1.6; text-align: justify;">
            <strong>๑. ความสำคัญและความเป็นมา</strong><br>
            <div class="indent-1">
                การจัดกิจกรรมประชุมผู้ปกครองในชั้นเรียน (Classroom Meeting) เป็นกิจกรรมที่ทางโรงเรียนได้จัดขึ้น เพื่อให้ครูที่ปรึกษาและผู้ปกครองได้มาพบปะพูดคุย สนทนา แลกเปลี่ยนความคิดเห็นซึ่งกันและกัน เพื่อหาแนวทางและร่วมมือกันในการดูแลช่วยเหลือนักเรียน โรงเรียนละลมวิทยา ได้เล็งเห็นความสำคัญของการประชุมผู้ปกครองชั้นเรียน จึงกำหนดให้มีการจัดกิจกรรมประชุมขึ้น เพื่อหาแนวทางในการปรับปรุง แก้ไข พัฒนาพฤติกรรมที่ไม่พึงประสงค์ของนักเรียน และส่งเสริมพฤติกรรมที่ดีของนักเรียนให้ดียิ่งๆ ขึ้นไป
            </div>
            
            <strong class="d-block mt-2">๒. วัตถุประสงค์ของการจัดกิจกรรมประชุมผู้ปกครองในชั้นเรียน</strong>
            <div style="padding-left: 10px;">
                ๑. เพื่อให้ผู้ปกครองได้รู้และเข้าใจถึงกฎระเบียบของทางโรงเรียน สามารถนำไปอบรมสั่งสอนนักเรียนในปกครองได้<br>
                ๒. เพื่อให้ผู้ปกครองได้มีโอกาสพบปะกับครูประจำชั้นและรับทราบพฤติกรรมด้านการเรียน ความประพฤติ การปรับตัวตามศักยภาพ และอื่น ๆ ของนักเรียน<br>
                ๓. เพื่อให้ผู้ปกครองตระหนักถึงบทบาทหน้าที่ในการดูแลเอาใจใส่บุตรหลาน และร่วมกันหาแนวทางในการดูแลช่วยเหลือนักเรียน<br>
                ๔. เพื่อให้ผู้ปกครองได้มีโอกาสเสนอแนะแนวทางการมีส่วนร่วมในการดำเนินงานตามระบบดูแลช่วยเหลือนักเรียน และสร้างความสัมพันธ์ที่ดี ความร่วมมือระหว่างบ้านกับโรงเรียนในการป้องกัน แก้ไขและพัฒนานักเรียน
            </div>
            
            <strong class="d-block mt-2">๓. บทบาทหน้าที่ครูที่ปรึกษาในการจัดประชุมผู้ปกครองชั้นเรียน</strong>
            <div style="padding-left: 10px;">
                ครูที่ปรึกษามีบทบาทในการเตรียมข้อมูลการเรียน พฤติกรรม และข้อมูลระดับคะแนนพฤติกรรมของนักเรียน และเป็นผู้ดำเนินกิจกรรมห้องเรียน ประสานสัมพันธ์กับผู้ปกครองเพื่อรวบรวมข้อมูลตามเอกสาร ลลว.๐๒ - ลลว.๐๖ สรุปเป็นรูปเล่มรายงานส่งระดับชั้นและกลุ่มบริหารงานบุคคลตามลำดับ
            </div>
            
            <strong class="d-block mt-2">๔. ขั้นตอนแนวปฏิบัติในการประชุมผู้ปกครองชั้นเรียน</strong>
            <div style="padding-left: 10px;">
                - ดำเนินการจัดประชุมในวันที่ <?= th_date($meeting['meeting_date']) ?> ณ ห้องเรียนโฮมรูม<br>
                - ครูที่ปรึกษาชี้แจงระเบียบ แจกเอกสารแสดงผลการเรียนและบันทึกประสานสัมพันธ์ ลลว.๐๔<br>
                - จัดกิจกรรมกลุ่ม Meet & Greet ลลว.๐๕ และให้นักเรียนส่งมอบบันทึกจดหมายความในใจลูก ลลว.๐๖ แก่ผู้ปกครอง<br>
                - ติดตามผู้ปกครองที่ไม่มาร่วมประชุมพร้อมบันทึกลงในเอกสาร ลลว.๐๓
            </div>
            
            <strong class="d-block mt-2">๕. ผลที่คาดว่าจะได้รับ</strong>
            <div style="padding-left: 10px;">
                ผู้ปกครองได้รับรู้แนวทางการช่วยเหลือ ร่วมมือปรับพฤติกรรม ตระหนักในสิทธิหน้าที่และสร้างสายสัมพันธ์ความอบอุ่นระหว่างบ้านกับโรงเรียนเพื่อพัฒนาผู้เรียนอย่างมีประสิทธิภาพ
            </div>
            
            <strong class="d-block mt-2">๖. สรุปและข้อเสนอแนะ</strong><br>
            <div style="padding-left: 10px;">
                <strong>๑. ข้อมูลทั่วไป:</strong> จำนวนผู้ปกครองทั้งหมด <span class="dotted-line" style="width: 30px; text-align: center;"><?= th_num($meeting['total_parents']) ?></span> คน 
                เข้าร่วมประชุมจำนวน <span class="dotted-line" style="width: 30px; text-align: center;"><?= th_num($meeting['attend_count']) ?></span> คน (คิดเป็นร้อยละ <?= th_num($attend_pct) ?>) 
                ไม่เข้าร่วมจำนวน <span class="dotted-line" style="width: 30px; text-align: center;"><?= th_num($meeting['absent_count']) ?></span> คน (คิดเป็นร้อยละ <?= th_num($absent_pct) ?>)
                ได้รับการติดตามจำนวน <span class="dotted-line" style="width: 30px; text-align: center;"><?= th_num($followed_count) ?></span> คน (คิดเป็นร้อยละ <?= th_num($follow_pct) ?>)<br>
                
                <strong>๒. หัวข้อสำคัญของการประชุม:</strong><br>
                &nbsp;&nbsp;&nbsp;&nbsp;๒.๑ <?= esc($meeting['agenda_1'] ? $meeting['agenda_1'] : 'ชี้แจงกฎระเบียบโรงเรียน และผลการเรียนกลางภาคเรียน') ?><br>
                &nbsp;&nbsp;&nbsp;&nbsp;๒.๒ <?= esc($meeting['agenda_2'] ? $meeting['agenda_2'] : 'ปรึกษาแนวทางแก้ไขพฤติกรรมและการตัดคะแนนพฤติกรรม') ?><br>
                &nbsp;&nbsp;&nbsp;&nbsp;๒.๓ <?= esc($meeting['agenda_3'] ? $meeting['agenda_3'] : 'จัดตั้งคณะกรรมการเครือข่ายผู้ปกครอง และกิจกรรม Meet & Greet') ?><br>
                
                <strong>๓. ข้อสรุปจากการประชุม:</strong><br>
                <div style="border: 1px solid #ccc; background: #fafafa; padding: 6px; font-size: 13px; min-height: 35px; white-space: pre-wrap; margin-top: 3px; border-radius: 4px;"><?= esc($meeting['consensus'] ? $meeting['consensus'] : 'ไม่มีข้อสรุปเพิ่มเติม') ?></div>
                
                <strong>๔. บรรยากาศการประชุม และข้อสังเกต:</strong><br>
                &nbsp;&nbsp;&nbsp;&nbsp;๔.๑ ความร่วมมือในการเสนอความคิดเห็น: <span class="dotted-line" style="width: 75%;"><?= esc($meeting['cooperation_rating'] ? $meeting['cooperation_rating'] : 'ดีเยี่ยม ผู้ปกครองให้ความร่วมมือดี') ?></span><br>
                &nbsp;&nbsp;&nbsp;&nbsp;๔.๒ การให้ข้อคิดเห็นที่มีประโยชน์: <span class="dotted-line" style="width: 78%;"><?= esc($meeting['useful_suggestions'] ? $meeting['useful_suggestions'] : 'ผู้ปกครองเสนอแนะเรื่องความปลอดภัยในการเดินทาง') ?></span><br>
                &nbsp;&nbsp;&nbsp;&nbsp;๔.๓ การให้การสนับสนุนโรงเรียน: <span class="dotted-line" style="width: 80%;"><?= esc($meeting['support_received'] ? $meeting['support_received'] : 'ผู้ปกครองพร้อมสนับสนุนการจัดกิจกรรมห้องเรียน') ?></span><br>
                &nbsp;&nbsp;&nbsp;&nbsp;๔.๔ สังเกตอื่นๆ: <span class="dotted-line" style="width: 88%;"><?= esc($meeting['other_observations'] ? $meeting['other_observations'] : 'บรรยากาศเป็นกันเองและอบอุ่น') ?></span>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-around; margin-top: 25px; line-height: 1.8;">
            <?php foreach ($teachers as $t_name): ?>
                <div style="text-align: center; width: 45%;">
                    ลงชื่อ............................................................<br>
                    (<?= esc($t_name) ?>)<br>
                    ครูที่ปรึกษา / ผู้จัดทำรายงานการประชุม
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 6. APPENDIX COVER PAGE -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-appendix-cover text-center d-flex flex-column justify-content-center align-items-center">
        <div style="border: 4px double #000; padding: 25px 60px; border-radius: 12px; margin-top: 80mm;">
            <h1 style="font-size: 42px; font-weight: bold; margin: 0; font-family: 'Sarabun', sans-serif;">ภาคผนวก</h1>
        </div>
        <p style="margin-top: 15px; font-size: 16px; font-family: 'Sarabun', sans-serif; color: #475569;">(เอกสารหลักฐานประกอบรายงาน ลลว.๐๒ - ลลว.๐๖)</p>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 7. ลลว.๐๒ (ใบลงชื่อเข้าร่วม) -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-chon02">
        <div style="text-align: right; font-size: 12px; font-weight: bold;">ลลว.๐๒</div>
        <div style="text-align: center; margin-bottom: 15px;">
            <h4 style="font-weight: bold; margin-bottom: 5px;">ใบลงชื่อผู้เข้าร่วมกิจกรรมประชุมผู้ปกครองชั้นเรียน (Classroom Meeting)</h4>
            <h5 style="font-weight: bold;">ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?></h5>
            <p style="margin: 0;">ชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> &nbsp; โรงเรียนละลมวิทยา</p>
        </div>
        
        <table class="table table-bordered text-center align-middle" style="font-size: 13px; font-family: 'Sarabun', sans-serif;">
            <thead class="table-light">
                <tr>
                    <th style="width: 5%;">ที่</th>
                    <th style="width: 25%;">ชื่อ-นามสกุลนักเรียน</th>
                    <th style="width: 25%;">ชื่อผู้ปกครอง (ตัวบรรจง)</th>
                    <th style="width: 15%;">เบอร์โทรศัพท์มือถือ</th>
                    <th style="width: 15%;">ความสัมพันธ์</th>
                    <th style="width: 15%;">ลายมือชื่อ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendants)): ?>
                    <tr>
                        <td colspan="6" class="text-muted text-center py-4">ไม่พบข้อมูลผู้เข้าร่วมประชุมในระบบ</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attendants as $index => $att): ?>
                        <tr>
                            <td><?= th_num($index + 1) ?></td>
                            <td class="text-start"><?= esc($att['student_name']) ?></td>
                            <td class="text-start"><?= esc($att['parent_name']) ?></td>
                            <td><?= esc($att['phone']) ?></td>
                            <td><?= esc($att['relationship']) ?></td>
                            <td style="font-style: italic; color: #475569;">ลงชื่อแล้ว</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="display: flex; justify-content: space-around; margin-top: 30px; line-height: 1.8;">
            <?php foreach ($teachers as $t_name): ?>
                <div style="text-align: center; width: 45%;">
                    ลงชื่อ............................................................<br>
                    (<?= esc($t_name) ?>)<br>
                    ครูที่ปรึกษา
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 8. ลลว.๐๓ (ติดตามผู้ขาด) -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-chon03">
        <div style="text-align: right; font-size: 12px; font-weight: bold;">ลลว.๐๓</div>
        <div style="text-align: center; margin-bottom: 15px;">
            <h4 style="font-weight: bold; margin-bottom: 5px;">ใบลงชื่อการติดตามผู้ปกครองนักเรียนที่ไม่มาเข้าร่วมกิจกรรม (Classroom Meeting)</h4>
            <h5 style="font-weight: bold;">ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?></h5>
            <p style="margin: 0;">ชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> &nbsp; โรงเรียนละลมวิทยา</p>
        </div>
        
        <table class="table table-bordered text-center align-middle" style="font-size: 12px; font-family: 'Sarabun', sans-serif;">
            <thead class="table-light">
                <tr>
                    <th style="width: 5%;">ที่</th>
                    <th style="width: 20%;">ชื่อ-นามสกุลนักเรียน</th>
                    <th style="width: 20%;">ชื่อผู้ปกครอง</th>
                    <th style="width: 12%;">เบอร์โทรศัพท์</th>
                    <th style="width: 10%;">ความสัมพันธ์</th>
                    <th style="width: 15%;">สาเหตุที่ไม่เข้าร่วม</th>
                    <th style="width: 10%;">สถานะติดตาม</th>
                    <th style="width: 8%;">ลายมือชื่อ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($absents)): ?>
                    <tr>
                        <td colspan="8" class="text-muted text-center py-4">ไม่พบข้อมูลผู้ขาดการประชุมในระบบ</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($absents as $index => $abs): ?>
                        <tr>
                            <td><?= th_num($index + 1) ?></td>
                            <td class="text-start"><?= esc($abs['student_name']) ?></td>
                            <td class="text-start"><?= esc($abs['parent_name']) ?></td>
                            <td><?= esc($abs['phone']) ?></td>
                            <td><?= esc($abs['relationship']) ?></td>
                            <td class="text-start"><?= esc($abs['absent_reason'] ? $abs['absent_reason'] : 'ไม่ได้ระบุ') ?></td>
                            <td>
                                <?php
                                $status = $abs['follow_up_status'];
                                if ($status === 'followed_up') {
                                    echo 'ติดตามแล้ว (' . th_date($abs['follow_up_date']) . ')';
                                } else {
                                    echo 'ยังไม่ได้ติดตาม';
                                }
                                ?>
                            </td>
                            <td style="font-style: italic; font-size: 11px;">
                                <?= $status === 'followed_up' ? 'รับทราบแล้ว' : '................' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="display: flex; justify-content: space-around; margin-top: 30px; line-height: 1.8;">
            <?php foreach ($teachers as $t_name): ?>
                <div style="text-align: center; width: 45%;">
                    ลงชื่อ............................................................<br>
                    (<?= esc($t_name) ?>)<br>
                    ครูที่ปรึกษา
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 9. ลลว.๐๔ (แบบประสานสัมพันธ์รายคน - ๑ หน้าต่อคน) -->
    <!-- ──────────────────────────────────────────────────────── -->
    <?php foreach ($relations as $rel): ?>
        <div class="print-page page-chon04" style="font-size: 12px; line-height: 1.35;">
            <div style="display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 5px;">
                <div>ลลว.๐๔</div>
                <div>แบบบันทึกประสานสัมพันธ์ผู้ปกครองกับครูที่ปรึกษา</div>
            </div>
            
            <div style="border: 1px solid #000; padding: 5px; margin-bottom: 8px; font-family: 'Sarabun', sans-serif;">
                <strong>ชื่อ-สกุล (นักเรียน):</strong> <?= esc($rel['student_name']) ?> &nbsp;&nbsp;&nbsp;
                <strong>ชั้นมัธยมศึกษาปีที่:</strong> <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> &nbsp;&nbsp;&nbsp;
                <strong>เลขที่:</strong> <?= th_num(esc($rel['student_no'])) ?> &nbsp;&nbsp;&nbsp;
                <strong>ชื่อ-สกุล (ผู้ปกครอง):</strong> <?= esc($rel['parent_name']) ?> &nbsp;&nbsp;&nbsp;
                <strong>เกี่ยวข้องเป็น:</strong> <?= esc($rel['relationship']) ?>
            </div>
            
            <div style="display: flex; justify-content: space-between; font-family: 'Sarabun', sans-serif; font-size: 11px; margin-bottom: 6px; border-bottom: 1.5px solid #000; padding-bottom: 3px;">
                <div>
                    <strong>๑. พฤติกรรมด้านการเรียน:</strong>
                    ติด ๐: <span style="text-decoration: underline; font-weight: bold;">&nbsp;<?= th_num($rel['grade_zero_count']) ?>&nbsp;</span> วิชา &nbsp;&nbsp;
                    ติด ร: <span style="text-decoration: underline; font-weight: bold;">&nbsp;<?= th_num($rel['grade_r_count']) ?>&nbsp;</span> วิชา &nbsp;&nbsp;
                    ติด มส: <span style="text-decoration: underline; font-weight: bold;">&nbsp;<?= th_num($rel['grade_ms_count']) ?>&nbsp;</span> วิชา &nbsp;&nbsp;
                    ติด มผ: <span style="text-decoration: underline; font-weight: bold;">&nbsp;<?= th_num($rel['grade_mp_count']) ?>&nbsp;</span> วิชา
                </div>
                <div>
                    <strong>๒. คะแนนความประพฤติที่โดนตัด:</strong> <span style="text-decoration: underline; font-weight: bold;">&nbsp;<?= th_num($rel['behavior_score_deducted']) ?>&nbsp;</span> คะแนน
                </div>
            </div>
            
            <!-- Behavior Grid side-by-side -->
            <div style="display: flex; gap: 12px; font-family: 'Sarabun', sans-serif; margin-bottom: 8px;">
                <!-- Praise List -->
                <div style="flex: 1; border: 1.5px solid #000; border-radius: 4px; padding: 5px;">
                    <div style="font-weight: bold; background: #e2e8f0; padding: 2px; margin-bottom: 4px; text-align: center; border: 1px solid #000;">๓. พฤติกรรมที่ควรยกย่องชมเชย</div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                        <thead>
                            <tr style="border-bottom: 1px solid #000;">
                                <th style="text-align: left; padding: 2px;">พฤติกรรม / คุณลักษณะที่ดี</th>
                                <th style="width: 25px; text-align: center; padding: 2px;">ครู</th>
                                <th style="width: 25px; text-align: center; padding: 2px;">ผปก.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $praises = [
                                'มีน้ำใจต่อครู เพื่อนและบุคคลอื่น',
                                'มีความซื่อสัตย์สุจริต',
                                'ความรับผิดชอบ',
                                'มีความกระตือรือร้น',
                                'ตั้งใจเรียน ไม่หนีเรียน หรือเข้าห้องเรียนช้า',
                                'ช่วยดูแลรักษาความสะอาดภายในโรงเรียน',
                                'แต่งกายและทรงผมถูกระเบียบ',
                                'เข้าร่วมกิจกรรมต่าง ๆ ที่โรงเรียนจัดให้ ทั้งภายในและภายนอกโรงเรียน',
                                'ตรงต่อเวลา มาโรงเรียนทันเข้าแถว และร่วมกิจกรรมหน้าเสาธง',
                                'บำเพ็ญตนเป็นประโยชน์ต่อสังคมและสาธารณประโยชน์ (จิตอาสา)'
                            ];
                            foreach ($praises as $item):
                                $has_t = in_array($item, $rel['praise_teacher_json'] ?? []);
                                $has_p = in_array($item, $rel['praise_parent_json'] ?? []);
                            ?>
                                <tr style="border-bottom: 1px dashed #bbb; height: 16px;">
                                    <td style="padding: 1px; font-size: 9px; line-height: 1.1;"><?= esc($item) ?></td>
                                    <td style="text-align: center; padding: 1px; font-size: 11px;"><?= $has_t ? '☑' : '☐' ?></td>
                                    <td style="text-align: center; padding: 1px; font-size: 11px;"><?= $has_p ? '☑' : '☐' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="3" style="padding-top: 3px; font-size: 9px;">
                                    <strong>อื่นๆ:</strong> 
                                    <span class="dotted-line" style="width: 82%;">
                                        <?= esc($rel['praise_teacher_other'] ? 'ครู: '.$rel['praise_teacher_other'] : '') ?>
                                        <?= esc($rel['praise_parent_other'] ? ' ผปก: '.$rel['praise_parent_other'] : '') ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Improvement List -->
                <div style="flex: 1; border: 1.5px solid #000; border-radius: 4px; padding: 5px;">
                    <div style="font-weight: bold; background: #e2e8f0; padding: 2px; margin-bottom: 4px; text-align: center; border: 1px solid #000;">๔. พฤติกรรมที่ต้องปรับปรุงแก้ไข</div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                        <thead>
                            <tr style="border-bottom: 1px solid #000;">
                                <th style="text-align: left; padding: 2px;">พฤติกรรมที่เป็นประเด็นท้าทาย</th>
                                <th style="width: 25px; text-align: center; padding: 2px;">ครู</th>
                                <th style="width: 25px; text-align: center; padding: 2px;">ผปก.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $improves = [
                                'มาสาย ไม่ทันเข้าแถว หรือหลบหนีการเข้าแถว',
                                'เข้าห้องเรียนช้า',
                                'หนีเรียนตลอดทั้งวัน หรือหนีเรียนบางคาบเรียน',
                                'แต่งกายหรือทรงผมผิดระเบียบ',
                                'สวมใส่เครื่องประดับหรือของมีค่า',
                                'ใช้โทรศัพท์มือถือระหว่างเรียน',
                                'สูบบุหรี่ทั้งภายในและภายนอกโรงเรียน',
                                'ประพฤติตนในทำนองชู้สาว',
                                'แสดงกิริยาวาจาที่ไม่สุภาพต่อครูและบุคคลอื่น'
                            ];
                            foreach ($improves as $item):
                                $has_t = in_array($item, $rel['improve_teacher_json'] ?? []);
                                $has_p = in_array($item, $rel['improve_parent_json'] ?? []);
                            ?>
                                <tr style="border-bottom: 1px dashed #bbb; height: 16px;">
                                    <td style="padding: 1px; font-size: 9px; line-height: 1.1;"><?= esc($item) ?></td>
                                    <td style="text-align: center; padding: 1px; font-size: 11px;"><?= $has_t ? '☑' : '☐' ?></td>
                                    <td style="text-align: center; padding: 1px; font-size: 11px;"><?= $has_p ? '☑' : '☐' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <!-- blank alignment row -->
                            <tr style="height: 16px; border-bottom: 1px dashed #eee;">
                                <td>&nbsp;</td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="padding-top: 3px; font-size: 9px;">
                                    <strong>อื่นๆ:</strong> 
                                    <span class="dotted-line" style="width: 82%;">
                                        <?= esc($rel['improve_teacher_other'] ? 'ครู: '.$rel['improve_teacher_other'] : '') ?>
                                        <?= esc($rel['improve_parent_other'] ? ' ผปก: '.$rel['improve_parent_other'] : '') ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Feedbacks sections -->
            <div style="font-family: 'Sarabun', sans-serif; font-size: 11px; line-height: 1.35; margin-top: 5px;">
                <div class="mb-1">
                    <strong>๕. วิธีการ/การแก้ไขพฤติกรรมที่ไม่พึงประสงค์ของครูที่ปรึกษาที่ผ่านมา:</strong>
                    <div style="border-bottom: 1px dotted #888; min-height: 25px; padding-left: 5px; word-break: break-all;">
                        <?= esc($rel['teacher_remedy'] ? $rel['teacher_remedy'] : 'ตักเตือน แนะนำ และแจ้งผู้ปกครองให้ร่วมรับทราบพฤติกรรมเป็นระยะ') ?>
                    </div>
                </div>
                <div class="mb-1">
                    <strong>๖. แนวทางการแก้ไขร่วมกันของผู้ปกครอง:</strong>
                    <div style="border-bottom: 1px dotted #888; min-height: 25px; padding-left: 5px; word-break: break-all;">
                        <?= esc($rel['parent_remedy'] ? $rel['parent_remedy'] : 'กำชับตักเตือนเมื่ออยู่บ้าน กวดขันเวลาตื่นนอนและการใช้อุปกรณ์มือถือในเวลาวิกาล') ?>
                    </div>
                </div>
                <div class="mb-1">
                    <strong>๗. ความต้องการของผู้ปกครองที่ต้องการให้โรงเรียนช่วยเหลือ:</strong>
                    <div style="border-bottom: 1px dotted #888; min-height: 25px; padding-left: 5px; word-break: break-all;">
                        <?= esc($rel['parent_support_request'] ? $rel['parent_support_request'] : 'ขอให้ช่วยติดตามพฤติกรรมการเรียนในห้องอย่างใกล้ชิดและแจ้งความคืบหน้าผ่านไลน์ห้องเรียน') ?>
                    </div>
                </div>
                <div class="mb-1">
                    <strong>๘. ความรู้สึก/ความเห็นของผู้ปกครองที่ได้มาประชุมผู้ปกครองครั้งนี้:</strong>
                    <div style="border-bottom: 1px dotted #888; min-height: 25px; padding-left: 5px; word-break: break-all;">
                        <?= esc($rel['parent_meeting_impression'] ? $rel['parent_meeting_impression'] : 'มีความยินดีอย่างยิ่งที่ได้พูดคุยปรึกษากับครู ได้รับข้อมูลการเรียนที่เป็นจริงและมีประโยชน์ในการช่วยกวดขันลูก') ?>
                    </div>
                </div>
                <div class="mb-1">
                    <strong>๙. คำขอบคุณ/ความรู้สึก/ความเห็นของผู้ปกครองที่มีต่อครูที่ปรึกษา:</strong>
                    <div style="border-bottom: 1px dotted #888; min-height: 25px; padding-left: 5px; word-break: break-all;">
                        <?= esc($rel['parent_teacher_feedback'] ? $rel['parent_teacher_feedback'] : 'ขอขอบคุณครูที่กรุณาอบรมสั่งสอน เอาใจใส่นักเรียน และส่งข่าวสารประสานสัมพันธ์อย่างสม่ำเสมอ') ?>
                    </div>
                </div>
            </div>
            
            <!-- Signatures -->
            <div style="display: flex; justify-content: space-between; margin-top: 15px; font-family: 'Sarabun', sans-serif; font-size: 12px;">
                <div style="width: 45%; text-align: center; line-height: 1.8;">
                    ลงชื่อ............................................................ ผู้ปกครอง<br>
                    (<?= esc($rel['parent_name']) ?>)<br>
                    เกี่ยวข้องเป็น: <?= esc($rel['relationship']) ?>
                </div>
                <div style="width: 45%; text-align: center; line-height: 1.8;">
                    <?php foreach ($teachers as $t_name): ?>
                        <div style="margin-top: 4px;">
                            ลงชื่อ............................................................<br>
                            (<?= esc($t_name) ?>)<br>
                            ครูที่ปรึกษา
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 10. ลลว.๐๕ (Meet & Greet) -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-chon05">
        <div style="text-align: right; font-size: 12px; font-weight: bold;">ลลว.๐๕</div>
        <div style="text-align: center; margin-bottom: 20px;">
            <h4 style="font-weight: bold; margin-bottom: 5px;">แบบบันทึกกิจกรรมกลุ่มย่อย Meet and Greet</h4>
            <h5 style="font-weight: bold;">ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?></h5>
            <p style="margin: 0;">ชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> &nbsp; โรงเรียนละลมวิทยา</p>
        </div>
        
        <?php if (empty($groups)): ?>
            <div class="text-center text-muted py-5" style="border: 1px dashed #ccc; border-radius: 8px; font-family: 'Sarabun', sans-serif;">
                ไม่พบข้อมูลกลุ่มกิจกรรมย่อย Meet & Greet ในระบบ
            </div>
        <?php else: ?>
            <?php foreach ($groups as $g_idx => $grp): ?>
                <div style="border: 1.5px solid #000; padding: 12px; margin-bottom: 20px; font-family: 'Sarabun', sans-serif; page-break-inside: avoid; border-radius: 6px;">
                    <div style="font-size: 15px; font-weight: bold; border-bottom: 1.5px solid #000; padding-bottom: 4px; margin-bottom: 10px;">
                        กลุ่มที่ <?= th_num($g_idx + 1) ?> : ประเด็นหัวข้ออภิปราย "<?= esc($grp['group_topic']) ?>"
                    </div>
                    
                    <div style="font-weight: bold; font-size: 12px; margin-bottom: 4px;">รายชื่อสมาชิกเครือข่ายผู้ปกครองในกลุ่ม:</div>
                    <table class="table table-bordered text-center align-middle" style="font-size: 12px; margin-bottom: 12px;">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 10%;">ที่</th>
                                <th style="width: 45%;">ชื่อผู้ปกครอง</th>
                                <th style="width: 45%;">ผู้ปกครองของนักเรียน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($grp['attendants_json'])): ?>
                                <tr>
                                    <td colspan="3" class="text-muted">ไม่มีสมาชิกในกลุ่มนี้</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($grp['attendants_json'] as $m_idx => $member): ?>
                                    <tr>
                                        <td><?= th_num($m_idx + 1) ?></td>
                                        <td class="text-start"><?= esc($member['parent_name'] ?? '') ?></td>
                                        <td class="text-start"><?= esc($member['student_name'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <div style="font-size: 13px; line-height: 1.5; margin-bottom: 8px;">
                        <strong>สรุปสาระสำคัญจากการพูดคุย:</strong>
                        <div style="border: 1px solid #ccc; padding: 6px; background: #fafafa; border-radius: 4px; min-height: 35px; white-space: pre-wrap; margin-top: 3px;"><?= esc($grp['discussion_summary'] ? $grp['discussion_summary'] : 'ร่วมพูดคุยปรึกษาแนวทางสนับสนุนการเรียนของนักเรียนและการเตรียมตัวสอบปลายภาคเรียน') ?></div>
                    </div>
                    
                    <div style="font-size: 13px; line-height: 1.5; margin-bottom: 8px;">
                        <strong>แนวทางแก้ไข/ข้อตกลงร่วมกัน:</strong>
                        <div style="border: 1px solid #ccc; padding: 6px; background: #fafafa; border-radius: 4px; min-height: 35px; white-space: pre-wrap; margin-top: 3px;"><?= esc($grp['discussion_resolution'] ? $grp['discussion_resolution'] : 'กวดขันเวลาอ่านหนังสือที่บ้าน และจำกัดเวลาใช้อุปกรณ์สมาร์ทโฟนไม่ให้เกินวันละ ๒ ชั่วโมง') ?></div>
                    </div>
                    
                    <div style="font-size: 13px; line-height: 1.5;">
                        <strong>ข้อเสนอแนะ/ประเด็นที่เสนอโรงเรียนให้ช่วยเหลือ:</strong>
                        <div style="border: 1px solid #ccc; padding: 6px; background: #fafafa; border-radius: 4px; min-height: 35px; white-space: pre-wrap; margin-top: 3px;"><?= esc($grp['school_support_request'] ? $grp['school_support_request'] : 'ต้องการให้โรงเรียนจัดสรรชั่วโมงสอนเสริมทบทวนในรายวิชาที่ยาก เช่น วิทยาศาสตร์และคณิตศาสตร์') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="display: flex; justify-content: space-around; margin-top: 30px; line-height: 1.8;">
            <?php foreach ($teachers as $t_name): ?>
                <div style="text-align: center; width: 45%;">
                    ลงชื่อ............................................................<br>
                    (<?= esc($t_name) ?>)<br>
                    ครูที่ปรึกษา
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 11. ลลว.๐๖ (ความในใจของลูก - ๑ หน้าต่อคน) -->
    <!-- ──────────────────────────────────────────────────────── -->
    <?php foreach ($letters as $let): ?>
        <div class="print-page page-chon06" style="font-size: 13px; line-height: 1.45; padding: 25mm 20mm 20mm 25mm;">
            <div style="display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 5px;">
                <div>ลลว.๐๖</div>
                <div>ความในใจของลูกที่อยากบอกผู้ปกครอง</div>
            </div>
            
            <div style="text-align: center; margin-bottom: 20px; font-family: 'Sarabun', sans-serif;">
                <h4 style="font-weight: bold; margin-bottom: 2px;">จดหมายสื่อสายใยรักในการประชุมผู้ปกครองชั้นเรียน</h4>
                <h5 style="font-weight: bold;">ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?></h5>
                <p style="margin: 0;">ชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> &nbsp; โรงเรียนละลมวิทยา</p>
            </div>
            
            <div style="border: 1px solid #000; padding: 6px; margin-bottom: 15px; font-family: 'Sarabun', sans-serif;">
                <strong>ชื่อนักเรียน:</strong> <?= esc($let['student_name']) ?> &nbsp;&nbsp;&nbsp;
                <strong>ชั้นมัธยมศึกษาปีที่:</strong> <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> &nbsp;&nbsp;&nbsp;
                <strong>เลขที่:</strong> <?= th_num(esc($let['student_no'])) ?>
            </div>
            
            <div style="font-family: 'Sarabun', sans-serif; margin-bottom: 10px;">
                <strong>๑. ความในใจนี้อยากบอกกับ:</strong> <span class="dotted-line" style="width: 60%; font-weight: bold;">&nbsp;<?= esc($let['letter_to_whom']) ?></span>
            </div>
            
            <div style="font-family: 'Sarabun', sans-serif; margin-bottom: 10px;">
                <strong>๒. สิ่งที่ประทับใจที่มีต่อผู้ปกครอง:</strong>
                <div style="border-bottom: 1px dotted #888; min-height: 45px; padding-left: 10px; width: 100%; word-break: break-all;">
                    <?= esc($let['impressed_story'] ? $let['impressed_story'] : 'ประทับใจที่คุณพ่อคุณแม่เอาใจใส่ คอยถามไถ่เรื่องเรียน และคอยดูแลเวลาเจ็บไข้ได้ป่วย') ?>
                </div>
            </div>
            
            <div style="font-family: 'Sarabun', sans-serif; margin-bottom: 10px;">
                <strong>๓. ความในใจที่อยากจะบอกผู้ปกครองให้รู้:</strong>
                <div style="border-bottom: 1px dotted #888; min-height: 45px; padding-left: 10px; width: 100%; word-break: break-all;">
                    <?= esc($let['inner_feelings'] ? $let['inner_feelings'] : 'อยากบอกว่ารักคุณพ่อคุณแม่มาก และขอบคุณที่ส่งเสียสนับสนุนให้ได้เข้าเรียนในโรงเรียนนี้') ?>
                </div>
            </div>
            
            <div style="font-family: 'Sarabun', sans-serif; margin-bottom: 10px;">
                <strong>๔. สิ่งที่นักเรียนภาคภูมิใจในตนเอง:</strong>
                <div style="border-bottom: 1px dotted #888; min-height: 45px; padding-left: 10px; width: 100%; word-break: break-all;">
                    <?= esc($let['proud_story'] ? $let['proud_story'] : 'ภูมิใจที่ตั้งใจเรียน ช่วยทำงานบ้าน คอยแบ่งเบาภาระ และพยายามทำตัวเป็นเด็กดีไม่ทำให้พ่อแม่ผิดหวัง') ?>
                </div>
            </div>
            
            <div style="font-family: 'Sarabun', sans-serif; margin-bottom: 10px;">
                <strong>๕. สิ่งที่นักเรียนจะปรับปรุงแก้ไขตนเอง:</strong>
                <div style="border-bottom: 1px dotted #888; min-height: 45px; padding-left: 10px; width: 100%; word-break: break-all;">
                    <?= esc($let['improvement_plan'] ? $let['improvement_plan'] : 'จะพยายามขยันอ่านหนังสือเพิ่มขึ้น ลดการเล่นเกมบนมือถือ และพยายามส่งงานให้ตรงตามเวลากำหนด') ?>
                </div>
            </div>
            
            <div style="font-family: 'Sarabun', sans-serif; margin-bottom: 15px;">
                <strong>๖. ความรู้สึกของความรัก/ตอบกลับจากผู้ปกครองที่อยากบอกลูก:</strong>
                <div style="border-bottom: 1px dotted #888; min-height: 55px; padding-left: 10px; width: 100%; word-break: break-all;">
                    <?= esc($let['parent_response'] ? $let['parent_response'] : 'พ่อและแม่ภูมิใจในตัวลูก ขอให้ลูกตั้งใจศึกษาเล่าเรียน พ่อและแม่จะคอยสนับสนุนและอยู่เคียงข้างลูกเสมอ') ?>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-top: 25px; font-family: 'Sarabun', sans-serif; font-size: 13px;">
                <div style="width: 45%; text-align: center; line-height: 1.8;">
                    ลงชื่อ............................................................ นักเรียน<br>
                    (<?= esc($let['student_name']) ?>)
                </div>
                <div style="width: 45%; text-align: center; line-height: 1.8;">
                    ลงชื่อ............................................................ ผู้ปกครอง<br>
                    (<?= esc($let['letter_to_whom'] ? $let['letter_to_whom'] : '............................................................') ?>)
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 12. NETWORK PARENT CHART -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-network">
        <div style="text-align: right; font-size: 12px; font-weight: bold; font-family: 'Sarabun', sans-serif;">เครือข่ายผู้ปกครอง</div>
        <div style="text-align: center; margin-bottom: 25px; font-family: 'Sarabun', sans-serif;">
            <h4 style="font-weight: bold; margin-bottom: 5px;">ทำเนียบคณะกรรมการเครือข่ายผู้ปกครองประจำห้องเรียน</h4>
            <h5 style="font-weight: bold;">ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?></h5>
            <p style="margin: 0;">ชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> &nbsp; โรงเรียนละลมวิทยา</p>
        </div>
        
        <div class="network-tree" style="margin-top: 35px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <!-- Row 1: President -->
            <div class="d-flex justify-content-center mb-4">
                <?php displayPrintMemberCard('ประธานเครือข่ายผู้ปกครอง', $network['ประธาน'] ?? null); ?>
            </div>
            <!-- Row 2: Vice President -->
            <div class="d-flex justify-content-center mb-4">
                <?php displayPrintMemberCard('รองประธานเครือข่ายผู้ปกครอง', $network['รองประธาน'] ?? null); ?>
            </div>
            <!-- Row 3: Members & Secretary -->
            <div class="d-flex justify-content-center gap-4">
                <?php 
                $k1 = isset($network['กรรมการ'][0]) ? $network['กรรมการ'][0] : null;
                $k2 = isset($network['กรรมการ'][1]) ? $network['กรรมการ'][1] : null;
                displayPrintMemberCard('กรรมการเครือข่าย', $k1);
                displayPrintMemberCard('กรรมการเครือข่าย', $k2);
                displayPrintMemberCard('เลขานุการเครือข่าย', $network['เลขานุการ'] ?? null);
                ?>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-around; margin-top: 60px; font-family: 'Sarabun', sans-serif; line-height: 1.8;">
            <?php foreach ($teachers as $t_name): ?>
                <div style="text-align: center; width: 45%;">
                    ลงชื่อ............................................................<br>
                    (<?= esc($t_name) ?>)<br>
                    ครูที่ปรึกษา
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────────────── -->
    <!-- 13. PHOTO GALLERY -->
    <!-- ──────────────────────────────────────────────────────── -->
    <div class="print-page page-gallery">
        <div style="text-align: right; font-size: 12px; font-weight: bold; font-family: 'Sarabun', sans-serif;">ภาพบรรยากาศ</div>
        <div style="text-align: center; margin-bottom: 25px; font-family: 'Sarabun', sans-serif;">
            <h4 style="font-weight: bold; margin-bottom: 5px;">ภาพบรรยากาศการจัดกิจกรรมการประชุมผู้ปกครองชั้นเรียน (Classroom Meeting)</h4>
            <h5 style="font-weight: bold;">ภาคเรียนที่ <?= th_num(esc($meeting['semester'])) ?> ปีการศึกษา <?= th_num(esc($meeting['academic_year'])) ?></h5>
            <p style="margin: 0;">ชั้นมัธยมศึกษาปีที่ <?= th_num(esc($meeting['level'])) ?>/<?= th_num(esc($meeting['room_name'])) ?> &nbsp; โรงเรียนละลมวิทยา</p>
        </div>
        
        <?php if (empty($images)): ?>
            <div class="text-center text-muted py-5" style="border: 1px dashed #ccc; border-radius: 8px; font-family: 'Sarabun', sans-serif; margin-top: 30px;">
                ไม่มีภาพบรรยากาศกิจกรรมที่อัปโหลดในระบบ
            </div>
        <?php else: ?>
            <div class="row g-4" style="font-family: 'Sarabun', sans-serif; margin-top: 15px;">
                <?php foreach ($images as $img): ?>
                    <div class="col-6">
                        <div style="border: 1px solid #000; padding: 10px; background: #fff; text-align: center; height: 100%;">
                            <img src="<?= esc($img['image_path']) ?>" style="max-width: 100%; max-height: 240px; object-fit: contain; margin-bottom: 8px;" alt="บรรยากาศการประชุม">
                            <div style="border-bottom: 1px dotted #ccc; height: 20px; margin-top: 5px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="display: flex; justify-content: space-around; margin-top: 60px; font-family: 'Sarabun', sans-serif; line-height: 1.8;">
            <?php foreach ($teachers as $t_name): ?>
                <div style="text-align: center; width: 45%;">
                    ลงชื่อ............................................................<br>
                    (<?= esc($t_name) ?>)<br>
                    ครูที่ปรึกษา
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Script to dynamically handle toggle functionality -->
    <script>
        document.querySelectorAll('.section-toggle').forEach(function(chk) {
            chk.addEventListener('change', function() {
                var targetId = this.getAttribute('data-target');
                var pages = document.querySelectorAll('.' + targetId);
                pages.forEach(function(p) {
                    if (chk.checked) {
                        p.classList.remove('print-exclude');
                        p.style.setProperty('display', 'block', 'important');
                    } else {
                        p.classList.add('print-exclude');
                        p.style.setProperty('display', 'none', 'important');
                    }
                });
            });
        });
    </script>
</body>
</html>
