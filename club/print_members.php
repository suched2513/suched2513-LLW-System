<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) { exit('Access Denied'); }

$club_id = (int)($_GET['club_id'] ?? 0);
if (!$club_id) { exit('Missing Club ID'); }

$pdo = getPdo();
$stmt = $pdo->prepare("
    SELECT cg.*, 
           t1.name AS teacher_name, t2.name AS teacher_name_2, t3.name AS teacher_name_3 
    FROM club_groups cg 
    LEFT JOIN att_teachers t1 ON t1.id = cg.teacher_id 
    LEFT JOIN att_teachers t2 ON t2.id = cg.teacher_id_2 
    LEFT JOIN att_teachers t3 ON t3.id = cg.teacher_id_3 
    WHERE cg.id = ?
");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$club) { exit('Club Not Found'); }

// Members
$members = $pdo->prepare("
    SELECT s.student_id, s.name, s.classroom
    FROM club_registrations cr
    JOIN att_students s ON s.student_id = cr.student_id
    WHERE cr.club_id = ?
    ORDER BY s.classroom, s.name
");
$members->execute([$club_id]);
$memberList = $members->fetchAll(PDO::FETCH_ASSOC);

$advisors = array_filter([$club['teacher_name'], $club['teacher_name_2'], $club['teacher_name_3']]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายชื่อสมาชิกชุมนุม - <?= htmlspecialchars($club['name'], ENT_QUOTES, 'UTF-8') ?></title>
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
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #7c3aed; color: white; border: none; border-radius: 8px; cursor: pointer; font-family: inherit; font-weight: bold;">
            พิมพ์รายงาน
        </button>
    </div>

    <div class="header">
        <h1>รายชื่อนักเรียนสมาชิกชุมนุม</h1>
        <p>โรงเรียนละลมวิทยา ภาคเรียนที่ <?= $club['semester'] ?> ปีการศึกษา <?= $club['year'] ?></p>
    </div>

    <div class="info">
        <div><strong>ชื่อชุมนุม:</strong> <?= htmlspecialchars($club['name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>สถานที่:</strong> <?= htmlspecialchars($club['room'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
        <div style="width: 100%;">
            <strong>ครูที่ปรึกษา:</strong> <?= htmlspecialchars(implode(', ', $advisors), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 50px;" class="text-center">ที่</th>
                <th style="width: 120px;" class="text-center">รหัสนักเรียน</th>
                <th>ชื่อ - นามสกุล</th>
                <th style="width: 100px;" class="text-center">ชั้น/ห้อง</th>
                <th style="width: 150px;">หมายเหตุ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($memberList as $i => $m): ?>
            <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td class="text-center"><?= htmlspecialchars($m['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-center"><?= htmlspecialchars($m['classroom'], ENT_QUOTES, 'UTF-8') ?></td>
                <td></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="signature">
            <p></p>
            <div style="margin-top: 10px;">ลงชื่อ......................................................ครูที่ปรึกษา</div>
            <div style="margin-top: 5px;">( <?= htmlspecialchars($club['teacher_name'], ENT_QUOTES, 'UTF-8') ?> )</div>
        </div>
    </div>

    <script>
        // window.print();
    </script>
</body>
</html>
