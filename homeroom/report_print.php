<?php
/**
 * homeroom/report_print.php — รายงานบันทึกโฮมรูมรายสัปดาห์ (Print-Ready)
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) exit('Access Denied');

$classroom = $_GET['classroom'] ?? '';
$monday = $_GET['monday'] ?? '';

if (!$classroom || !$monday) exit('Data Missing');

try {
    $pdo = getPdo();

    // 1. Calculate the 5 days (Mon-Fri)
    // Ensure $monday is actually a Monday
    $timestamp = strtotime($monday);
    $dayOfWeek = date('N', $timestamp); // 1 (Mon) to 7 (Sun)
    if ($dayOfWeek != 1) {
        $timestamp = strtotime("last Monday", $timestamp + 86400); // +86400 to handle Sunday cases
    }
    $mondayDate = date('Y-m-d', $timestamp);

    $dates = [];
    for ($i = 0; $i < 5; $i++) {
        $dates[] = date('Y-m-d', strtotime("$mondayDate +$i day"));
    }
    $monday = $dates[0];
    $friday = $dates[4];

    // 2. Fetch Data
    // Students (Use assembly_students)
    $stmt = $pdo->prepare("SELECT student_id, name FROM assembly_students WHERE classroom = ? ORDER BY student_id");
    $stmt->execute([$classroom]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Topics
    $stmt = $pdo->prepare("SELECT log_date, topic FROM homeroom_logs WHERE classroom = ? AND log_date BETWEEN ? AND ?");
    $stmt->execute([$classroom, $monday, $friday]);
    $logs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Attendance Stats (Map short codes to long names)
    $stmt = $pdo->prepare("
        SELECT date, status, COUNT(*) as count 
        FROM assembly_attendance 
        WHERE classroom = ? AND date BETWEEN ? AND ?
        GROUP BY date, status
    ");
    $stmt->execute([$classroom, $monday, $friday]);
    $attRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map ALL possible statuses to the 4 standard columns
    $statusMap = [
        'ม' => 'มา', 'มา' => 'มา',
        'ข' => 'ขาด', 'ขาด' => 'ขาด', 'ด' => 'ขาด', 'โดด' => 'ขาด',
        'ล' => 'ลา', 'ลา' => 'ลา',
        'ส' => 'สาย', 'สาย' => 'สาย'
    ];
    
    $stats = [];
    foreach ($attRaw as $a) {
        $longStatus = $statusMap[$a['status']] ?? 'มา'; // Default to 'มา' if unknown to avoid 0s
        $stats[$a['date']][$longStatus] = ($stats[$a['date']][$longStatus] ?? 0) + $a['count'];
    }

    // Photos
    $stmt = $pdo->prepare("SELECT log_date, image_path FROM homeroom_photos WHERE classroom = ? AND log_date BETWEEN ? AND ?");
    $stmt->execute([$classroom, $monday, $friday]);
    $photos = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Advisors
    $stmt = $pdo->prepare("
        SELECT u.firstname, u.lastname 
        FROM llw_class_advisors a
        JOIN llw_users u ON a.user_id = u.user_id
        WHERE a.classroom = ?
    ");
    $stmt->execute([$classroom]);
    $advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    exit("เกิดข้อผิดพลาดในการโหลดข้อมูล: " . $e->getMessage() . "<br>โปรดตรวจสอบว่าได้รัน /homeroom/api/init.php หรือยัง");
}

$dayNames = ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกกิจกรรมโฮมรูม - <?= htmlspecialchars($classroom) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; padding: 20mm; background: #fff; color: #334155; line-height: 1.5; }
        @media print { body { padding: 0; } .no-print { display: none; } }
        .header { text-align: center; margin-bottom: 2rem; border-bottom: 3px solid #f1f5f9; padding-bottom: 1rem; }
        .title { font-size: 1.5rem; font-weight: 900; color: #1e293b; }
        .subtitle { font-size: 0.9rem; font-weight: 700; color: #64748b; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.85rem; }
        th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; }
        th { background: #f8fafc; font-weight: 900; }
        .center { text-align: center; }
        
        .photo-grid { display: grid; grid-cols: 5; gap: 10px; margin-top: 1rem; }
        .photo-item { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .photo-item img { width: 100%; height: 100px; object-cover; }
        
        .footer { margin-top: 3rem; display: flex; justify-content: space-between; }
        .sig-box { text-align: center; width: 45%; }
        .sig-line { border-bottom: 1px dashed #cbd5e1; margin: 10px auto; width: 80%; height: 60px; display: flex; items-center; justify-center; }
        .sig-img { max-height: 50px; }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:10px; font-weight:900; cursor:pointer;">พิมพ์รายงาน (PDF)</button>
    </div>

    <div class="header">
        <h1 class="title">บันทึกกิจกรรมโฮมรูม (Homeroom Logbook)</h1>
        <p class="subtitle">ระดับชั้นมัธยมศึกษาปีที่ <?= htmlspecialchars($classroom) ?> | ประจำสัปดาห์วันที่ <?= date('d/m/Y', strtotime($monday)) ?> - <?= date('d/m/Y', strtotime($friday)) ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="15%">วัน / วันที่</th>
                <th width="50%">เรื่องที่แจ้ง / กิจกรรมโฮมรูม</th>
                <th width="35%">สรุปสถิติจำนวนนักเรียน (มา / ขาด / ลา / สาย)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dates as $idx => $d): 
                $s = $stats[$d] ?? [];
                $m = $s['มา'] ?? 0;
                $k = $s['ขาด'] ?? 0;
                $l = $s['ลา'] ?? 0;
                $sy = $s['สาย'] ?? 0;
                $total = count($students);
            ?>
            <tr>
                <td class="center">
                    <strong><?= $dayNames[$idx] ?></strong><br>
                    <span style="font-size: 0.75rem; color: #94a3b8;"><?= date('d/m/Y', strtotime($d)) ?></span>
                </td>
                <td><?= nl2br(htmlspecialchars($logs[$d] ?? '-')) ?></td>
                <td class="center">
                    <div style="font-size: 1.1rem; font-weight: 900;">
                        <?= $m ?> / <?= $k ?> / <?= $l ?> / <?= $sy ?>
                    </div>
                    <div style="font-size: 0.7rem; color: #94a3b8;">
                        ทั้งหมด <?= $total ?> คน
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3 style="font-size: 1rem; font-weight: 900; margin-bottom: 0.5rem;">รูปภาพประกอบกิจกรรม</h3>
    <div style="display: flex; gap: 10px;">
        <?php foreach ($dates as $d): ?>
        <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #f8fafc; height: 120px;">
            <?php if (isset($photos[$d])): ?>
                <img src="<?= $photos[$d] ?>" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <div style="height: 100%; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 0.7rem;">ไม่มีรูปภาพ</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="footer">
        <?php foreach ($advisors as $idx => $adv): ?>
        <div class="sig-box">
            <p style="font-size: 0.8rem; font-weight: 700;">(ลงชื่อ) ครูที่ปรึกษาคนที่ <?= $idx + 1 ?></p>
            <div class="sig-line">
                <?php if ($adv['signature']): ?>
                    <img src="<?= $adv['signature'] ?>" class="sig-img">
                <?php endif; ?>
            </div>
            <p style="font-size: 0.85rem; font-weight: 900;">(ครู<?= htmlspecialchars($adv['firstname'] . ' ' . $adv['lastname']) ?>)</p>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top: 4rem; text-align: center; font-size: 0.7rem; color: #94a3b8; border-top: 1px solid #f1f5f9; pt: 1rem;">
        ระบบบริหารจัดการโรงเรียนละลมวิทยา (LLW Platinum Portal) - พัฒนาโดย AI Assistant
    </div>
</body>
</html>
