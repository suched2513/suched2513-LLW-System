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
    // 1. ดึงข้อมูลรายงานการประชุม
    $stmt = $pdo->prepare("
        SELECT m.*, c.level, c.room_name, c.teacher_name, u.fullname as creator_name
        FROM meetings m
        JOIN classrooms c ON m.classroom_id = c.id
        JOIN users u ON m.created_by = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$meetingId]);
    $meeting = $stmt->fetch();
    
    if (!$meeting) {
        exit('ไม่พบข้อมูลรายงานการประชุมนี้ในระบบ');
    }
    
    // 2. ดึงรูปกิจกรรมประกอบรายงาน
    $imgStmt = $pdo->prepare("SELECT * FROM meeting_images WHERE meeting_id = ?");
    $imgStmt->execute([$meetingId]);
    $images = $imgStmt->fetchAll();
    
    // 3. ดึงความเห็นผู้บริหาร
    $cmtStmt = $pdo->prepare("
        SELECT c.*, u.fullname as commenter_name 
        FROM comments c
        JOIN users u ON c.commented_by = u.id
        WHERE c.meeting_id = ?
        ORDER BY c.created_at ASC
    ");
    $cmtStmt->execute([$meetingId]);
    $comments = $cmtStmt->fetchAll();
    
    // 4. ดึงเครือข่ายผู้ปกครอง
    $netStmt = $pdo->prepare("SELECT * FROM network_parents WHERE meeting_id = ?");
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
    
} catch (Exception $e) {
    error_log('[Parent Meeting] Print Report Error: ' . $e->getMessage());
    exit('เกิดข้อผิดพลาดในการโหลดข้อมูลเพื่อจัดทำรายงานพิมพ์');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานการประชุมผู้ปกครอง - ม.<?= esc($meeting['level'] . '/' . $meeting['room_name']) ?></title>
    <!-- Google Fonts: Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Prompt', sans-serif;
            background: #fff;
            color: #1e293b;
            line-height: 1.6;
            margin: 0;
            padding: 15mm;
            font-size: 14px;
        }
        
        .no-print-bar {
            background: #f1f5f9;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .btn-print {
            background: #0d6efd;
            color: #white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
        }
        .btn-print:hover { background: #0a58ca; }
        
        .header-doc {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px double #cbd5e1;
            padding-bottom: 15px;
        }
        .header-doc h2 { margin: 0 0 5px 0; font-weight: 900; font-size: 20px; }
        .header-doc p { margin: 0; font-weight: 600; color: #475569; font-size: 14px; }
        
        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 1.5px solid #000;
            padding-bottom: 3px;
            margin-top: 25px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        
        .grid-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 10px;
            border-radius: 6px;
        }
        .info-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .info-value { font-weight: 700; color: #1e293b; font-size: 13px; }
        
        .content-block {
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            min-height: 50px;
            white-space: pre-wrap;
            font-size: 13px;
        }
        
        /* ผังเครือข่ายผู้ปกครอง */
        .network-tree {
            margin-top: 20px;
            margin-bottom: 25px;
        }
        .tree-row {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .member-card {
            border: 1px solid #94a3b8;
            border-radius: 8px;
            width: 180px;
            background: #fff;
            overflow: hidden;
            font-size: 11px;
            text-align: center;
            display: flex;
            flex-direction: column;
        }
        .member-pos {
            background: #f1f5f9;
            font-weight: 700;
            border-bottom: 1px solid #cbd5e1;
            padding: 4px;
            font-size: 11px;
        }
        .member-body {
            padding: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-grow: 1;
        }
        .member-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #cbd5e1;
            margin-bottom: 6px;
        }
        .member-name { font-weight: 700; font-size: 11px; color: #0f172a; margin-bottom: 2px; }
        .member-phone { font-size: 9.5px; color: #475569; }
        
        /* รูปภาพบรรยากาศ */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 25px;
        }
        .gallery-item {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            height: 110px;
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* ลายเซ็น */
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .sig-box {
            text-align: center;
            width: 45%;
        }
        .sig-line {
            border-bottom: 1px dashed #475569;
            margin: 45px auto 8px auto;
            width: 75%;
        }
        
        @media print {
            .no-print-bar { display: none !important; }
            body { padding: 0; }
            .info-box { background: none; }
            .content-block { background: none; }
            .member-card { background: none; }
            .member-pos { background: #f8fafc; }
        }
    </style>
</head>
<body>

    <!-- แถบเครื่องมือควบคุมการพิมพ์ (ซ่อนตอนพิมพ์จริง) -->
    <div class="no-print-bar">
        <div style="font-size: 13px;">
            <strong>พิมพ์รายงานการประชุม:</strong> ม.<?= esc($meeting['level'] . '/' . $meeting['room_name']) ?> (เทอม <?= esc($meeting['semester'] . '/' . $meeting['academic_year']) ?>)
        </div>
        <button class="btn-print" onclick="window.print()">
            <svg style="width:16px;height:16px;margin-right:6px;fill:white" viewBox="0 0 24 24"><path d="M18,3H6V7H18M19,12A1,1 0 0,1 18,11A1,1 0 0,1 19,10A1,1 0 0,1 20,11A1,1 0 0,1 19,12M16,19H8V14H16M19,8H5A3,3 0 0,0 2,11V17H6V21H18V17H22V11A3,3 0 0,0 19,8Z" /></svg>
            พิมพ์รายงาน / บันทึกเป็น PDF
        </button>
    </div>

    <!-- หัวเอกสาร -->
    <div class="header-doc">
        <h2>รายงานผลการประชุมผู้ปกครองนักเรียน (Parent Meeting Report)</h2>
        <p>โรงเรียนละลมวิทยา สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร</p>
    </div>

    <!-- ข้อมูลรายงานทั่วไป -->
    <div class="grid-info">
        <div class="info-box">
            <div class="info-label">ห้องเรียนที่รายงาน</div>
            <div class="info-value">ชั้นมัธยมศึกษาปีที่ <?= esc($meeting['level'] . '/' . $meeting['room_name']) ?></div>
        </div>
        <div class="info-box">
            <div class="info-label">ครูที่ปรึกษา / ผู้จัดประชุม</div>
            <div class="info-value">ครู<?= esc($meeting['teacher_name']) ?></div>
        </div>
        <div class="info-box">
            <div class="info-label">ภาคเรียน / ปีการศึกษา</div>
            <div class="info-value">ภาคเรียนที่ <?= esc($meeting['semester']) ?> / ปีการศึกษา <?= esc($meeting['academic_year']) ?></div>
        </div>
        <div class="info-box">
            <div class="info-label">วันที่จัดประชุม</div>
            <div class="info-value"><?= th_date($meeting['meeting_date']) ?></div>
        </div>
        <div class="info-box">
            <div class="info-label">จำนวนผู้เรียนทั้งหมดในห้อง</div>
            <div class="info-value"><?= esc($meeting['total_students']) ?> คน</div>
        </div>
        <div class="info-box">
            <div class="info-label">สถิติผู้ปกครอง (เข้าร่วม / ขาดประชุม)</div>
            <div class="info-value"><?= esc($meeting['attend_count']) ?> คน / <?= esc($meeting['absent_count']) ?> คน (คิดเป็น <?= $meeting['total_parents'] > 0 ? round(($meeting['attend_count'] / $meeting['total_parents']) * 100, 1) : 0 ?>%)</div>
        </div>
    </div>

    <!-- ส่วนเนื้อหาที่สรุป -->
    <div class="section-title">1. สรุปสาระสำคัญจากการจัดประชุมผู้ปกครอง</div>
    <div class="content-block"><?= esc($meeting['summary'] ? $meeting['summary'] : 'ไม่ได้ระบุเนื้อหา') ?></div>

    <div class="section-title">2. ปัญหา พฤติกรรม หรือข้อขัดข้องที่ตรวจพบ</div>
    <div class="content-block"><?= esc($meeting['problems'] ? $meeting['problems'] : 'ไม่มีประเด็นปัญหาอุปสรรค') ?></div>

    <div class="section-title">3. ข้อเสนอแนะ หรือแนวทางการแก้ไขปัญหาร่วมกัน</div>
    <div class="content-block"><?= esc($meeting['suggestions'] ? $meeting['suggestions'] : 'ไม่มีข้อเสนอแนะเพิ่มเติม') ?></div>

    <!-- บอร์ดทำเนียบเครือข่ายผู้ปกครอง -->
    <div class="section-title" style="page-break-before: always;">4. รายชื่อผู้แทนคณะกรรมการเครือข่ายผู้ปกครองประจำห้องเรียน</div>
    <div class="network-tree">
        <!-- แถว 1: ประธาน -->
        <div class="tree-row">
            <?php displayPrintMemberCard('ประธานเครือข่าย', $network['ประธาน'] ?? null); ?>
        </div>
        <!-- แถว 2: รองประธาน -->
        <div class="tree-row">
            <?php displayPrintMemberCard('รองประธานเครือข่าย', $network['รองประธาน'] ?? null); ?>
        </div>
        <!-- แถว 3: กรรมการ & เลขานุการ -->
        <div class="tree-row">
            <?php 
            $k1 = isset($network['กรรมการ'][0]) ? $network['กรรมการ'][0] : null;
            $k2 = isset($network['กรรมการ'][1]) ? $network['กรรมการ'][1] : null;
            displayPrintMemberCard('กรรมการเครือข่าย', $k1);
            displayPrintMemberCard('กรรมการเครือข่าย', $k2);
            displayPrintMemberCard('เลขานุการเครือข่าย', $network['เลขานุการ'] ?? null);
            ?>
        </div>
    </div>

    <!-- ภาพกิจกรรมประกอบ -->
    <?php if (!empty($images)): ?>
        <div class="section-title">5. ภาพบรรยากาศการจัดประชุมผู้ปกครอง</div>
        <div class="gallery-grid">
            <?php foreach ($images as $img): ?>
                <div class="gallery-item">
                    <img src="<?= esc($img['image_path']) ?>">
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ความเห็นผู้บริหาร -->
    <div class="section-title">6. ความคิดเห็น / ข้อสั่งการของผู้บริหารโรงเรียน</div>
    <?php if (empty($comments)): ?>
        <div class="content-block" style="font-style: italic; color: #64748b;">(ยังไม่มีข้อเสนอแนะหรือความเห็นสั่งการจากผู้บริหารสถานศึกษา)</div>
    <?php else: ?>
        <?php foreach ($comments as $c): ?>
            <div class="content-block" style="border-left: 3px solid #10b981;">
                <strong>ความเห็นจากคุณ: <?= esc($c['commenter_name']) ?></strong><br>
                <span><?= esc($c['comment_text']) ?></span>
                <div style="font-size: 10px; color: #64748b; text-align: right; margin-top: 5px;">ลงข้อมูลเมื่อ: <?= th_date($c['created_at']) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ลายเซ็น -->
    <div class="signature-section">
        <div class="sig-box">
            <p>ลงชื่อ............................................................ ครูที่ปรึกษา</p>
            <p>(............................................................)</p>
            <p>ผู้จัดทำรายงานการประชุม</p>
        </div>
        <div class="sig-box">
            <p>ลงชื่อ............................................................ ผู้บริหารโรงเรียน</p>
            <p>(............................................................)</p>
            <p>ผู้อำนวยการโรงเรียนละลมวิทยา</p>
        </div>
    </div>

    <div style="margin-top: 50px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px;">
        รายงานนี้พิมพ์จากระบบบันทึกรายงานการประชุมผู้ปกครอง - โรงเรียนละลมวิทยา
    </div>

</body>
</html>

<?php
/**
 * ฟังก์ชันช่วยวาดการ์ดแสดงผลรูปและข้อมูลคนในผัง PDF
 */
function displayPrintMemberCard($title, $data) {
    ?>
    <div class="member-card">
        <div class="member-pos"><?= esc($title) ?></div>
        <div class="member-body">
            <?php if ($data && $data['image_path']): ?>
                <img src="<?= esc($data['image_path']) ?>" class="member-img">
            <?php else: ?>
                <div style="width: 50px; height: 50px; border-radius: 50%; background: #f1f5f9; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; margin-bottom: 6px; font-weight: bold; color: #94a3b8; font-size: 16px;">👤</div>
            <?php endif; ?>
            
            <div class="member-name"><?= $data ? esc($data['parent_name']) : 'ยังไม่ได้ระบุชื่อ' ?></div>
            <?php if ($data): ?>
                <div class="member-phone">โทร: <?= esc($data['phone']) ?></div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 3px;">นักเรียน: <?= esc($data['student_name']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
