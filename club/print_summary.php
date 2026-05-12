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

$sql = "SELECT cg.name, cg.room, cg.max_capacity, 
               COALESCE(t.name,'-') AS teacher_name,
               (SELECT COUNT(*) FROM club_registrations cr WHERE cr.club_id = cg.id AND cr.semester = cg.semester AND cr.year = cg.year) AS registered_count
        FROM club_groups cg
        LEFT JOIN att_teachers t ON t.id = cg.teacher_id
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สรุปข้อมูลชุมนุม</title>
    <!-- Use Sarabun font -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; margin: 40px; font-size: 14pt; }
        h2 { text-align: center; margin-bottom: 5px; }
        .subtitle { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 8px; }
        th { background-color: #f9f9f9; }
        .text-center { text-align: center; }
        .footer { margin-top: 60px; display: flex; justify-content: space-around; page-break-inside: avoid; }
        .signature-box { text-align: center; line-height: 1.8; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #7c3aed; color: white; border: none; border-radius: 5px;">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 5px;">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            พิมพ์เอกสาร
        </button>
    </div>

    <h2>รายงานสรุปข้อมูลชุมนุม</h2>
    <div class="subtitle">ภาคเรียนที่ <?= htmlspecialchars($activeSemester) ?> ปีการศึกษา <?= htmlspecialchars($activeYear) ?></div>
    
    <div style="margin-bottom: 10px;">
        <strong>จำนวนชุมนุมทั้งหมด:</strong> <?= $totalClubs ?> ชุมนุม &nbsp;&nbsp;|&nbsp;&nbsp; 
        <strong>จำนวนสมาชิกรวม:</strong> <?= $totalMembers ?> คน
    </div>

    <table>
        <thead>
            <tr>
                <th width="8%">ลำดับ</th>
                <th width="32%">ชื่อชุมนุม</th>
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
                    <td><?= htmlspecialchars($c['teacher_name']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($c['room'] ?: '-') ?></td>
                    <td class="text-center"><?= $c['registered_count'] ?> / <?= $c['max_capacity'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
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
