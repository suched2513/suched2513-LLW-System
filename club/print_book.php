<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth Guard
if (!isset($_SESSION['llw_role'])) {
    exit('กรุณาเข้าสู่ระบบ');
}

$pdo     = getPdo();
$clubId  = (int)($_GET['club_id'] ?? 0);

if ($clubId <= 0) {
    exit('ไม่พบรหัสชุมนุม');
}

// 1. Fetch Club Info
$stmt = $pdo->prepare("
    SELECT cg.*, 
           t1.name AS teacher_name, t2.name AS teacher_name_2, t3.name AS teacher_name_3
    FROM club_groups cg
    LEFT JOIN att_teachers t1 ON t1.id = cg.teacher_id
    LEFT JOIN att_teachers t2 ON t2.id = cg.teacher_id_2
    LEFT JOIN att_teachers t3 ON t3.id = cg.teacher_id_3
    WHERE cg.id = ?
");
$stmt->execute([$clubId]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    exit('ไม่พบข้อมูลชุมนุม');
}

$semester = $club['semester'];
$year     = $club['year'];

// 2. Fetch Members
$stmt = $pdo->prepare("
    SELECT s.student_id, s.name, s.classroom
    FROM club_registrations cr
    JOIN att_students s ON s.student_id = cr.student_id
    WHERE cr.club_id = ? AND cr.semester = ? AND cr.year = ?
    ORDER BY s.classroom, s.student_id
");
$stmt->execute([$clubId, $semester, $year]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Sessions
$stmt = $pdo->prepare("
    SELECT * FROM club_sessions 
    WHERE club_id = ? AND status = 'done'
    ORDER BY session_date ASC
");
$stmt->execute([$clubId]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Fetch Results
$stmt = $pdo->prepare("
    SELECT student_id, attendance_pct, result, teacher_comment
    FROM club_results
    WHERE club_id = ? AND semester = ? AND year = ?
");
$stmt->execute([$clubId, $semester, $year]);
$resultsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$results = [];
foreach ($resultsRaw as $r) {
    $results[$r['student_id']] = $r;
}

// 5. Attendance Summary Matrix (Optional but useful)
// For now we'll just show the final results and session list.

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานผลการดำเนินงานชุมนุม - <?= htmlspecialchars($club['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #000;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
        }
        body {
            font-family: 'Prompt', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
            color: #333;
            line-height: 1.6;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        @media print {
            body { background: none; }
            .page { margin: 0; box-shadow: none; page-break-after: always; }
            .no-print { display: none; }
        }

        /* Typography */
        h1, h2, h3 { margin: 0; font-weight: 700; color: #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-5 { margin-top: 3rem; }
        .mb-4 { margin-bottom: 1.5rem; }

        /* Cover Page */
        .cover {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 257mm; /* 297 - 40 padding */
            text-align: center;
        }
        .school-logo { width: 100px; margin-bottom: 20px; }
        .cover-title { font-size: 32px; margin-top: 50px; }
        .cover-subtitle { font-size: 24px; margin-top: 20px; }
        .cover-footer { font-size: 18px; margin-bottom: 50px; }

        /* Content Sections */
        .section-title {
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
            margin-bottom: 20px;
            font-size: 20px;
            text-transform: uppercase;
        }

        /* Table Style */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: left;
        }
        th { background-color: #f9f9f9; font-weight: 600; }
        .col-center { text-align: center; }

        /* Signature */
        .sig-container {
            margin-top: 50px;
            display: flex;
            justify-content: space-around;
        }
        .sig-box {
            text-align: center;
            width: 250px;
        }
        .sig-line {
            border-bottom: 1px dotted #000;
            margin-bottom: 10px;
            height: 40px;
        }

        .badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }
        .bg-pass { background-color: #dcfce7; color: #166534; }
        .bg-fail { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 100;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #000; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
            พิมพ์รายงาน (Print)
        </button>
    </div>

    <!-- PAGE 1: COVER -->
    <div class="page">
        <div class="cover">
            <div>
                <img src="/assets/img/logo.png" alt="School Logo" class="school-logo" onerror="this.src='https://llw.krusuched.com/assets/img/logo.png'">
                <h1>รายงานผลการดำเนินงานชุมนุม</h1>
                <div class="cover-title"><?= htmlspecialchars($club['name']) ?></div>
                <div class="cover-subtitle">ภาคเรียนที่ <?= $semester ?> ปีการศึกษา <?= $year ?></div>
            </div>

            <div>
                <p style="font-size: 20px; font-weight: 600;">ครูที่ปรึกษา</p>
                <?php
                $advisors = array_filter([$club['teacher_name'], $club['teacher_name_2'], $club['teacher_name_3']]);
                foreach ($advisors as $name) {
                    echo "<p style='font-size: 18px;'>".htmlspecialchars($name)."</p>";
                }
                ?>
            </div>

            <div class="cover-footer">
                <strong>โรงเรียนละลมวิทยา</strong><br>
                สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร
            </div>
        </div>
    </div>

    <!-- PAGE 2: OBJECTIVES & INFO -->
    <div class="page">
        <h2 class="section-title">1. ข้อมูลทั่วไปและวัตถุประสงค์</h2>
        <div style="margin-bottom: 30px;">
            <h3>คำอธิบายชุมนุม</h3>
            <p style="text-indent: 2em;"><?= nl2br(htmlspecialchars($club['description'] ?: '—')) ?></p>
        </div>
        <div style="margin-bottom: 30px;">
            <h3>วัตถุประสงค์</h3>
            <p style="text-indent: 2em;"><?= nl2br(htmlspecialchars($club['objectives'] ?: '—')) ?></p>
        </div>
        <div>
            <h3>เกณฑ์การวัดผลและประเมินผล</h3>
            <ul>
                <li>เวลาเรียนไม่น้อยกว่าร้อยละ <?= (int)$club['pass_threshold'] ?></li>
                <li>การปฏิบัติกิจกรรมและผลงานตามที่กำหนด</li>
                <li>การทดสอบปลายภาคเรียน (ถ้ามี)</li>
            </ul>
        </div>
    </div>

    <!-- PAGE 3: MEMBER LIST -->
    <div class="page">
        <h2 class="section-title">2. บัญชีรายชื่อสมาชิกชุมนุม</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;" class="col-center">ลำดับ</th>
                    <th style="width: 120px;" class="col-center">รหัสประจำตัว</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th style="width: 100px;" class="col-center">ชั้นเรียน</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $i => $m): ?>
                <tr>
                    <td class="col-center"><?= $i + 1 ?></td>
                    <td class="col-center"><?= htmlspecialchars($m['student_id']) ?></td>
                    <td><?= htmlspecialchars($m['name']) ?></td>
                    <td class="col-center"><?= htmlspecialchars($m['classroom']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($members)): ?>
                <tr><td colspan="4" class="col-center">ไม่พบรายชื่อสมาชิก</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGE 4: ACTIVITY LOGS -->
    <div class="page">
        <h2 class="section-title">3. บันทึกการจัดกิจกรรมชุมนุม</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;" class="col-center">ครั้งที่</th>
                    <th style="width: 120px;" class="col-center">วันที่</th>
                    <th>หัวข้อ / รายละเอียดกิจกรรม</th>
                    <th style="width: 80px;" class="col-center">สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $i => $s): ?>
                <tr>
                    <td class="col-center"><?= $i + 1 ?></td>
                    <td class="col-center"><?= date('d/m/Y', strtotime($s['session_date'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($s['description'] ?: 'ดำเนินการจัดกิจกรรมตามแผน')) ?></td>
                    <td class="col-center"><?= $s['status'] === 'done' ? 'เรียบร้อย' : 'รอดำเนินการ' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sessions)): ?>
                <tr><td colspan="4" class="col-center">ยังไม่มีการบันทึกกิจกรรม</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGE 5: EVALUATION -->
    <div class="page">
        <h2 class="section-title">4. รายงานผลการประเมินรายบุคคล</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;" class="col-center">ที่</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th style="width: 80px;" class="col-center">เวลาเรียน (%)</th>
                    <th style="width: 80px;" class="col-center">ผลการประเมิน</th>
                    <th>หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $i => $m): 
                    $res = $results[$m['student_id']] ?? null;
                ?>
                <tr>
                    <td class="col-center"><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($m['name']) ?></td>
                    <td class="col-center"><?= $res ? number_format($res['attendance_pct'], 0) : '—' ?></td>
                    <td class="col-center">
                        <?php if ($res): ?>
                            <span class="badge <?= $res['result'] === 'pass' ? 'bg-pass' : 'bg-fail' ?>">
                                <?= $res['result'] === 'pass' ? 'ผ่าน' : 'ไม่ผ่าน' ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($res['teacher_comment'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sig-container">
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>( ลงชื่อ ) ......................................................</p>
                <p>( <?= htmlspecialchars($club['teacher_name']) ?> )</p>
                <p>ครูที่ปรึกษาชุมนุม</p>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>( ลงชื่อ ) ......................................................</p>
                <p>( ...................................................... )</p>
                <p>หัวหน้างานชุมนุม</p>
            </div>
        </div>

        <div class="sig-container" style="justify-content: center;">
            <div class="sig-box">
                <div class="sig-line" style="width: 300px; margin: 0 auto 10px;"></div>
                <p>( ลงชื่อ ) ......................................................</p>
                <p>( ...................................................... )</p>
                <p>ผู้อำนวยการโรงเรียนละลมวิทยา</p>
            </div>
        </div>
    </div>

</body>
</html>
