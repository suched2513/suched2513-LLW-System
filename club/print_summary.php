<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'club_admin', 'att_teacher', 'wfh_admin'], true)) {
    die("ไม่มีสิทธิ์เข้าถึง");
}

$pdo = getPdo();

// Get active semester settings
$settRow = $pdo->query("SELECT semester, year FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$activeSemester = $settRow['semester'] ?? '-';
$activeYear = $settRow['year'] ?? '-';

$sql = "SELECT cg.name, cg.room, cg.max_capacity, cg.semester, cg.year,
               t1.name AS teacher_name, t2.name AS teacher_name_2, t3.name AS teacher_name_3,
               (SELECT COUNT(*) FROM club_registrations cr WHERE cr.club_id = cg.id AND cr.semester = cg.semester AND cr.year = cg.year) AS registered_count
        FROM club_groups cg
        LEFT JOIN att_teachers t1 ON t1.id = cg.teacher_id
        LEFT JOIN att_teachers t2 ON t2.id = cg.teacher_id_2
        LEFT JOIN att_teachers t3 ON t3.id = cg.teacher_id_3
        WHERE cg.status != 'archived' AND cg.semester = ? AND cg.year = ?
        ORDER BY cg.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$activeSemester, $activeYear]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalClubs = count($clubs);
$totalMembers = 0;
foreach ($clubs as $c) {
    $totalMembers += (int)$c['registered_count'];
}

// Summary By Class
$stmtByClass = $pdo->prepare("
    SELECT s.classroom,
           COUNT(s.student_id) AS total,
           COUNT(cr.id) AS registered
    FROM att_students s
    LEFT JOIN club_registrations cr ON cr.student_id = s.student_id
                                    AND cr.semester = ? AND cr.year = ?
    WHERE s.classroom REGEXP '^ม\\.[1-6]/'
    GROUP BY s.classroom
    ORDER BY s.classroom
");
$stmtByClass->execute([$activeSemester, $activeYear]);
$byClass = $stmtByClass->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สรุปข้อมูลชุมนุม</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; margin: 40px; font-size: 11pt; line-height: 1.4; }
        h2 { text-align: center; margin-bottom: 5px; font-size: 16pt; }
        h3 { margin-top: 30px; margin-bottom: 10px; font-size: 14pt; border-left: 5px solid #333; padding-left: 10px; }
        .subtitle { text-align: center; margin-bottom: 20px; font-size: 13pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px 8px; }
        th { background-color: #f2f2f2; font-weight: 600; }
        .text-center { text-align: center; }
        .footer { margin-top: 60px; display: flex; justify-content: space-around; page-break-inside: avoid; }
        .signature-box { text-align: center; line-height: 1.8; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
            @page { size: A4; margin: 1cm; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #7c3aed; color: white; border: none; border-radius: 5px; font-family: inherit;">
            พิมพ์เอกสาร
        </button>
    </div>

    <h2>รายงานสรุปข้อมูลกิจกรรมชุมนุม</h2>
    <div class="subtitle">ภาคเรียนที่ <?= htmlspecialchars($activeSemester) ?> ปีการศึกษา <?= htmlspecialchars($activeYear) ?></div>
    
    <div style="margin-bottom: 10px; font-weight: bold; font-size: 12pt;">
        จำนวนชุมนุมทั้งหมด: <?= $totalClubs ?> ชุมนุม &nbsp;&nbsp;|&nbsp;&nbsp; 
        จำนวนสมาชิกรวม: <?= $totalMembers ?> คน
    </div>

    <h3>1. รายละเอียดข้อมูลรายชุมนุม</h3>
    <table>
        <thead>
            <tr>
                <th width="7%">ที่</th>
                <th width="33%">ชื่อชุมนุม</th>
                <th width="30%">ครูผู้สอน / ที่ปรึกษา</th>
                <th width="15%">ห้องเรียน</th>
                <th width="15%">สมาชิก (คน)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($clubs) === 0): ?>
            <tr>
                <td colspan="5" class="text-center">ไม่พบข้อมูลชุมนุม</td>
            </tr>
            <?php else: ?>
                <?php foreach ($clubs as $idx => $c): ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td>
                        <?php
                        $advisors = array_filter([$c['teacher_name'], $c['teacher_name_2'], $c['teacher_name_3']]);
                        echo implode(', ', array_map(fn($t) => htmlspecialchars($t, ENT_QUOTES, 'UTF-8'), $advisors)) ?: '-';
                        ?>
                    </td>
                    <td class="text-center"><?= htmlspecialchars($c['room'] ?: '-') ?></td>
                    <td class="text-center"><?= $c['registered_count'] ?> / <?= $c['max_capacity'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="page-break-before: auto;"></div>

    <h3>2. สรุปการลงทะเบียนแยกตามห้องเรียน</h3>
    <table style="width: 70%; margin-left: auto; margin-right: auto;">
        <thead>
            <tr>
                <th class="text-center">ห้องเรียน</th>
                <th class="text-center">นักเรียนทั้งหมด</th>
                <th class="text-center">ลงทะเบียนแล้ว</th>
                <th class="text-center">ยังไม่ลงทะเบียน</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $gTotal = 0; $gReg = 0;
            foreach ($byClass as $row): 
                $unreg = $row['total'] - $row['registered'];
                $gTotal += $row['total'];
                $gReg += $row['registered'];
            ?>
            <tr>
                <td class="text-center"><?= htmlspecialchars($row['classroom']) ?></td>
                <td class="text-center"><?= $row['total'] ?></td>
                <td class="text-center"><?= $row['registered'] ?></td>
                <td class="text-center" style="<?= $unreg > 0 ? 'color: red;' : '' ?>"><?= $unreg ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background: #f9f9f9;">
                <td class="text-center">รวมทั้งหมด</td>
                <td class="text-center"><?= $gTotal ?></td>
                <td class="text-center"><?= $gReg ?></td>
                <td class="text-center"><?= $gTotal - $gReg ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <div class="signature-box">
            ลงชื่อ......................................................<br>
            (......................................................)<br>
            หัวหน้างานกิจกรรมพัฒนาผู้เรียน<br>
            ครูผู้รับผิดชอบระบบชุมนุม
        </div>
        <div class="signature-box">
            ลงชื่อ......................................................<br>
            (......................................................)<br>
            แอดมินผู้ดูแลระบบ / ผู้อำนวยการ
        </div>
    </div>
</body>
</html>
