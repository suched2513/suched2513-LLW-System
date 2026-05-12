<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) { exit('Access Denied'); }

$session_id = (int)($_GET['session_id'] ?? 0);
if (!$session_id) { exit('Missing Session ID'); }

$pdo = getPdo();
$stmt = $pdo->prepare("
    SELECT cs.*, cg.name AS club_name, cg.room, cg.semester, cg.year,
           t1.name AS teacher_name, t2.name AS teacher_name_2, t3.name AS teacher_name_3
    FROM club_sessions cs
    JOIN club_groups cg ON cg.id = cs.club_id
    LEFT JOIN att_teachers t1 ON t1.id = cg.teacher_id
    LEFT JOIN att_teachers t2 ON t2.id = cg.teacher_id_2
    LEFT JOIN att_teachers t3 ON t3.id = cg.teacher_id_3
    WHERE cs.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) { exit('Session Not Found'); }

// Members with attendance
$members = $pdo->prepare("
    SELECT s.student_id, s.name, s.classroom, ca.status AS att_status, ca.note
    FROM club_registrations cr
    JOIN att_students s ON s.student_id = cr.student_id
    LEFT JOIN club_attendance ca ON ca.session_id = ? AND ca.student_id = s.student_id
    WHERE cr.club_id = ?
    ORDER BY s.classroom, s.name
");
$members->execute([$session_id, $session['club_id']]);
$memberList = $members->fetchAll(PDO::FETCH_ASSOC);

$advisors = array_filter([$session['teacher_name'], $session['teacher_name_2'], $session['teacher_name_3']]);
$thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$dp = $session['session_date'] ? explode('-', $session['session_date']) : [];
$dateStr = count($dp) === 3 ? ((int)$dp[2].' '.$thaiMonths[(int)$dp[1]].' '.((int)$dp[0]+543)) : '-';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบเช็คชื่อชุมนุม - <?= htmlspecialchars($session['club_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; line-height: 1.5; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 700; }
        .header p { margin: 5px 0 0; font-size: 14px; }
        .info { margin-bottom: 20px; font-size: 14px; display: flex; justify-content: space-between; flex-wrap: wrap; }
        .info div { min-width: 200px; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #333; padding: 8px 12px; font-size: 13px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: 600; }
        .text-center { text-align: center; }
        .footer { margin-top: 40px; display: flex; justify-content: flex-end; }
        .signature { text-align: center; width: 250px; }
        .signature p { margin: 40px 0 0; border-bottom: 1px dotted #333; }
        .status-box { display: inline-block; width: 14px; height: 14px; border: 1px solid #333; vertical-align: middle; margin-right: 5px; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #059669; color: white; border: none; border-radius: 8px; cursor: pointer; font-family: inherit; font-weight: bold;">
            พิมพ์ใบเช็คชื่อ
        </button>
    </div>

    <div class="header">
        <h1>ใบเช็คชื่อและบันทึกกิจกรรมชุมนุม</h1>
        <p>โรงเรียนละลมวิทยา ภาคเรียนที่ <?= $session['semester'] ?> ปีการศึกษา <?= $session['year'] ?></p>
    </div>

    <div class="info">
        <div style="width: 50%;"><strong>ชื่อชุมนุม:</strong> <?= htmlspecialchars($session['club_name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div style="width: 50%;"><strong>วันที่:</strong> <?= $dateStr ?> <?= $session['period'] ? ' ('.$session['period'].')' : '' ?></div>
        <div style="width: 50%;"><strong>หัวข้อ:</strong> <?= htmlspecialchars($session['topic'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
        <div style="width: 50%;"><strong>สถานที่:</strong> <?= htmlspecialchars($session['room'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
        <div style="width: 100%;">
            <strong>ครูที่ปรึกษา:</strong> <?= htmlspecialchars(implode(', ', $advisors), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;" class="text-center">ที่</th>
                <th style="width: 100px;" class="text-center">รหัสนักเรียน</th>
                <th>ชื่อ - นามสกุล</th>
                <th style="width: 80px;" class="text-center">ห้อง</th>
                <th style="width: 120px;" class="text-center">สถานะ</th>
                <th style="width: 120px;">หมายเหตุ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($memberList as $i => $m): 
                $att = $m['att_status'] ?? '';
                $lbl = ['present'=>'มา','late'=>'สาย','leave'=>'ลา','absent'=>'ขาด'];
            ?>
            <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td class="text-center"><?= htmlspecialchars($m['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-center"><?= htmlspecialchars($m['classroom'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-center">
                    <?php if ($att): ?>
                        <strong><?= $lbl[$att] ?? $att ?></strong>
                    <?php else: ?>
                        <span class="status-box"></span> มา 
                        <span class="status-box"></span> ขาด
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($m['note'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 20px; font-size: 14px;">
        <strong>บันทึกกิจกรรม:</strong><br>
        <div style="border: 1px solid #333; min-height: 80px; padding: 10px; margin-top: 5px;">
            <?= nl2br(htmlspecialchars($session['description'] ?: '', ENT_QUOTES, 'UTF-8')) ?>
        </div>
    </div>

    <div class="footer">
        <div class="signature">
            <p></p>
            <div style="margin-top: 10px;">ลงชื่อ......................................................ครูที่ปรึกษา</div>
            <div style="margin-top: 5px;">( <?= htmlspecialchars($session['teacher_name'], ENT_QUOTES, 'UTF-8') ?> )</div>
        </div>
    </div>

</body>
</html>
