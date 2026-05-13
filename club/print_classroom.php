<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'club_admin', 'att_teacher', 'wfh_admin'], true)) {
    die("ไม่มีสิทธิ์เข้าถึง");
}

$pdo       = getPdo();
$classroom = trim($_GET['classroom'] ?? '');
if ($classroom === '') { die("กรุณาระบุห้องเรียน"); }

$cfg      = $pdo->query("SELECT * FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$semester = (int)($cfg['semester'] ?? 1);
$year     = (int)($cfg['year'] ?? (date('Y') + 543));

$stmt = $pdo->prepare("
    SELECT s.student_id, s.name,
           CASE WHEN cr.id IS NOT NULL THEN 1 ELSE 0 END AS is_registered,
           cg.name AS club_name
    FROM att_students s
    LEFT JOIN club_registrations cr ON cr.student_id = s.student_id
                                    AND cr.semester = ? AND cr.year = ?
    LEFT JOIN club_groups cg ON cg.id = cr.club_id
    WHERE s.classroom = ?
    ORDER BY s.student_id
");
$stmt->execute([$semester, $year, $classroom]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($students);
$reg   = count(array_filter($students, fn($s) => $s['is_registered']));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานการลงทะเบียนชุมนุม ห้อง <?= htmlspecialchars($classroom) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; margin: 40px; font-size: 12pt; }
        h2 { text-align: center; margin-bottom: 5px; }
        .subtitle { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px 8px; }
        th { background-color: #f9f9f9; font-weight: 600; }
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
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #7c3aed; color: white; border: none; border-radius: 5px;">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: middle; margin-right: 5px;">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            พิมพ์เอกสาร
        </button>
    </div>

    <h2>รายงานการติดตามการลงทะเบียนชุมนุม</h2>
    <div class="subtitle">ห้องเรียน <?= htmlspecialchars($classroom) ?> | ภาคเรียนที่ <?= $semester ?> ปีการศึกษา <?= $year ?></div>
    
    <div style="margin-bottom: 10px; font-size: 13pt;">
        <strong>จำนวนนักเรียนทั้งหมด:</strong> <?= $total ?> คน &nbsp;&nbsp;|&nbsp;&nbsp; 
        <strong>ลงทะเบียนแล้ว:</strong> <?= $reg ?> คน &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>ยังไม่ลงทะเบียน:</strong> <span style="<?= ($total - $reg) > 0 ? 'color:red; font-weight:bold;' : '' ?>"><?= $total - $reg ?> คน</span>
    </div>

    <table>
        <thead>
            <tr>
                <th width="8%">ลำดับ</th>
                <th width="15%">รหัสนักเรียน</th>
                <th width="35%">ชื่อ - นามสกุล</th>
                <th width="42%">ชื่อชุมนุมที่ลงทะเบียน</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $idx => $s): ?>
            <tr>
                <td class="text-center"><?= $idx + 1 ?></td>
                <td class="text-center"><?= htmlspecialchars($s['student_id']) ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td>
                    <?php if ($s['is_registered']): ?>
                        <?= htmlspecialchars($s['club_name']) ?>
                    <?php else: ?>
                        <span style="color: #999; font-style: italic;">(ยังไม่ลงทะเบียน)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="signature-box">
            ลงชื่อ......................................................<br>
            (......................................................)<br>
            ครูที่ปรึกษาประจำชั้น
        </div>
        <div class="signature-box">
            ลงชื่อ......................................................<br>
            (......................................................)<br>
            หัวหน้างานกิจกรรมพัฒนาผู้เรียน
        </div>
    </div>
</body>
</html>
