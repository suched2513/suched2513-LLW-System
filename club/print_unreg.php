<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'club_admin', 'att_teacher', 'wfh_admin'], true)) {
    die("ไม่มีสิทธิ์เข้าถึง");
}

$pdo = getPdo();
$cfg = $pdo->query("SELECT * FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$semester = (int)($cfg['semester'] ?? 1);
$year     = (int)($cfg['year'] ?? (date('Y') + 543));

// Unregistered students
$stmtUnreg = $pdo->prepare("
    SELECT s.student_id, s.name, s.classroom
    FROM att_students s
    WHERE s.classroom REGEXP '^ม\\.[1-6]/'
      AND s.student_id NOT IN (
          SELECT student_id FROM club_registrations WHERE semester = ? AND year = ?
      )
    ORDER BY s.classroom, s.name
");
$stmtUnreg->execute([$semester, $year]);
$unreg = $stmtUnreg->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายชื่อนักเรียนที่ยังไม่ลงทะเบียนชุมนุม ภาคเรียนที่ <?= $semester ?>/<?= $year ?></title>
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

    <h2>รายชื่อนักเรียนที่ยังไม่ลงทะเบียนชุมนุม</h2>
    <div class="subtitle">ภาคเรียนที่ <?= $semester ?> ปีการศึกษา <?= $year ?></div>
    
    <div style="margin-bottom: 10px; font-size: 13pt;">
        <strong>จำนวนนักเรียนที่ยังไม่ลงทะเบียน:</strong> <?= count($unreg) ?> คน
    </div>

    <table>
        <thead>
            <tr>
                <th width="10%">ลำดับ</th>
                <th width="20%">รหัสนักเรียน</th>
                <th width="40%">ชื่อ - นามสกุล</th>
                <th width="30%">ห้องเรียน</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($unreg as $idx => $s): ?>
            <tr>
                <td class="text-center"><?= $idx + 1 ?></td>
                <td class="text-center"><?= htmlspecialchars($s['student_id']) ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td class="text-center"><?= htmlspecialchars($s['classroom']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($unreg)): ?>
            <tr>
                <td colspan="4" class="text-center" style="color: green; padding: 20px;">นักเรียนทุกคนลงทะเบียนเรียบร้อยแล้ว</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="signature-box">
            ลงชื่อ......................................................<br>
            (......................................................)<br>
            ผู้รายงาน
        </div>
        <div class="signature-box">
            ลงชื่อ......................................................<br>
            (......................................................)<br>
            หัวหน้างานกิจกรรมพัฒนาผู้เรียน
        </div>
    </div>
</body>
</html>
