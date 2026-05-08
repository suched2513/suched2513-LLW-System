<?php
/**
 * duty/admin/print_pr.php — รายงานการปฏิบัติหน้าที่เวรประจำวัน (รายบุคคล/รายจุด)
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin', 'att_teacher'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();
$reportId = (int)($_GET['id'] ?? 0);

if (!$reportId) { header('Location: reports.php'); exit(); }

// ── ดึงข้อมูล report ──
$stmtR = $pdo->prepare(
    "SELECT dr.*, dt.full_name AS teacher_name, dt.prefix AS teacher_prefix
     FROM duty_reports dr
     LEFT JOIN duty_teachers dt ON dt.id = dr.teacher_id
     WHERE dr.id = ?"
);
$stmtR->execute([$reportId]);
$report = $stmtR->fetch(PDO::FETCH_ASSOC);

if (!$report) { header('Location: reports.php'); exit(); }

// ── Mapping ชื่อจุด (Point Names) ──
$pointNames = [
    1 => 'รับนักเรียนหน้าโรงเรียน',
    2 => 'ประตูหน้า/จราจร',
    3 => 'อาคารเรียน/สนามเด็กเล่น',
    4 => 'โรงอาหาร/ดูแลความสะอาด',
    5 => 'เดินตรวจรอบโรงเรียน',
    6 => 'ประธานกิจกรรม/ควบคุมแถว'
];
$pointName = $pointNames[$report['point_no']] ?? 'จุดปฏิบัติหน้าที่';

// ── ดึงรูป 6 รูปแรก ──
$stmtPh = $pdo->prepare(
    "SELECT * FROM duty_report_photos
     WHERE report_id = ? AND is_deleted = 0
     ORDER BY received_at ASC LIMIT 6"
);
$stmtPh->execute([$reportId]);
$photos = $stmtPh->fetchAll(PDO::FETCH_ASSOC);

$thaiDate = date('d/m/') . (date('Y', strtotime($report['duty_date'])) + 543);
$shiftName = $report['shift'] === 'day' ? 'กลางวัน' : 'กลางคืน';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานเวร - จุดที่ <?= $report['point_no'] ?> - <?= $report['teacher_name'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f0f9ff; }
        @page { size: A4; margin: 0; }
        body { font-family: 'Prompt', sans-serif; background: #f0f9ff; margin: 0; padding: 0; }
        .pr-container {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: linear-gradient(to bottom, #eff6ff 0%, #ffffff 40%, #ffffff 80%, #f0fdf4 100%);
            position: relative;
            box-shadow: 0 0 50px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 15mm;
            border-top: 10px solid #1e3a8a;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .pr-container { box-shadow: none !important; border: none !important; margin: 0; }
        }
        .glass-box { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 30px; border: 4px solid #1e3a8a; }
        .title-official { color: #1e3a8a; }
        .photo-slot { aspect-ratio: 4/3; border-radius: 20px; border: 4px solid #1e3a8a; overflow: hidden; background: #e0f2fe; }
        .photo-slot img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body class="p-4">

    <div class="no-print mb-6 flex justify-center gap-4">
        <button onclick="window.print()" class="bg-blue-900 text-white px-8 py-3 rounded-2xl font-black shadow-lg hover:scale-105 transition-all">
            <i class="fas fa-print me-2"></i> พิมพ์รายงาน / บันทึกเป็น PDF
        </button>
        <button onclick="window.close()" class="bg-slate-200 text-slate-600 px-8 py-3 rounded-2xl font-bold">
            ปิดหน้าต่าง
        </button>
    </div>

    <div class="pr-container rounded-[40px]">
        
        <!-- Header Section -->
        <div class="relative z-10 text-center mb-10">
            <img src="https://suched2513.github.io/image/%E0%B8%95%E0%B8%A3%E0%B8%B2%E0%B8%A5%E0%B8%B0%E0%B8%A5%E0%B8%A1%E0%B8%A7%E0%B8%B4%E0%B8%9767.png" alt="Logo" class="w-24 h-24 mx-auto mb-4 drop-shadow-lg">
            <h1 class="text-4xl font-black title-official mb-2">รายงานการปฏิบัติหน้าที่เวรประจำวัน</h1>
            <h2 class="text-2xl font-black text-slate-600">โรงเรียนละลมวิทยา</h2>
            <div class="w-32 h-1 bg-blue-900 mx-auto mt-4 rounded-full"></div>
        </div>

        <div class="flex justify-between items-center gap-6 mb-8 relative z-10">
            <div class="flex-1">
                <div class="bg-blue-900 text-white rounded-2xl py-4 px-8 shadow-xl">
                    <p class="text-xs font-bold uppercase tracking-widest opacity-80 mb-1">ตำแหน่งที่ได้รับมอบหมาย</p>
                    <h3 class="text-2xl font-black">
                        จุดที่ <?= $report['point_no'] ?> : <?= $pointName ?>
                    </h3>
                    <p class="text-sm mt-2 font-bold text-blue-200">ปฏิบัติหน้าที่ช่วงกะ<?= $shiftName ?></p>
                </div>
            </div>
            <div class="w-48 text-center">
                <div class="w-32 h-32 mx-auto rounded-3xl border-4 border-blue-900 overflow-hidden mb-2 bg-white shadow-lg rotate-3">
                    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="Teacher" class="w-full h-full object-cover">
                </div>
            </div>
        </div>

        <!-- Reporter Info -->
        <div class="flex justify-between items-center mb-8 relative z-10">
             <div class="bg-blue-50 border-l-4 border-blue-900 py-3 px-6 rounded-r-2xl">
                <p class="text-[10px] font-black text-blue-900 uppercase tracking-[0.2em] mb-1">ผู้รายงาน (ครูเวร)</p>
                <p class="text-xl font-bold text-slate-800"><?= htmlspecialchars($report['teacher_prefix'] . $report['teacher_name']) ?></p>
             </div>
             <div class="text-right">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">วันที่ปฏิบัติหน้าที่</p>
                <p class="text-lg font-black text-blue-900"><?= $thaiDate ?></p>
             </div>
        </div>

        <!-- Main Content -->
        <div class="glass-box p-6 mb-6 relative z-10 min-h-[180px] shadow-2xl">
            <div class="text-slate-700 leading-relaxed font-medium">
                <div class="flex items-center gap-3 mb-3 text-blue-900">
                    <i class="fas fa-file-alt text-xl"></i>
                    <h4 class="text-lg font-black">รายละเอียดการปฏิบัติหน้าที่ (ใคร ทำอะไร ที่ไหน อย่างไร)</h4>
                </div>
                <div class="p-4 bg-blue-50/30 rounded-2xl border-2 border-slate-100 italic text-sm">
                    " <?= !empty($report['report_note']) ? nl2br(htmlspecialchars($report['report_note'])) : 'ปฏิบัติหน้าที่เรียบร้อย เหตุการณ์ทั่วไปปกติ' ?> "
                </div>
                <p class="mt-4 text-xs text-slate-500 font-bold flex items-center gap-2">
                    <i class="fas fa-clock"></i> บันทึกเข้าระบบเมื่อเวลา <?= date('H:i', strtotime($report['completed_at'] ?: $report['created_at'])) ?> น.
                </p>
            </div>
        </div>

        <!-- Photo Grid (2x2) -->
        <div class="grid grid-cols-2 gap-6 relative z-10">
            <?php for($i=0; $i<4; $i++): ?>
                <div class="photo-slot shadow-xl hover:scale-[1.02] transition-all h-[200px]">
                    <?php if (isset($photos[$i])): ?>
                        <img src="<?= $base_path ?>/duty/api/photo.php?path=<?= urlencode($photos[$i]['file_path']) ?>" alt="Photo">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-blue-100 bg-blue-50/50">
                            <i class="fas fa-camera text-5xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Signature Section -->
        <div class="mt-8 flex justify-between gap-10 relative z-10">
            <div class="flex-1 text-center border-t border-slate-200 pt-4">
                <div class="h-12"></div>
                <p class="text-sm font-bold text-slate-800">( <?= htmlspecialchars($report['teacher_prefix'] . $report['teacher_name']) ?> )</p>
                <p class="text-[10px] font-bold text-slate-500 mt-1 uppercase tracking-widest">ผู้ปฏิบัติหน้าที่</p>
            </div>
            <div class="flex-1 text-center border-t border-slate-200 pt-4">
                <div class="h-12"></div>
                <p class="text-sm font-bold text-slate-800">( ............................................................ )</p>
                <p class="text-[10px] font-bold text-slate-500 mt-1 uppercase tracking-widest">หัวหน้าเวร / ผู้ประเมิน</p>
            </div>
        </div>

        <!-- Footer Deco -->
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 text-center w-full">
            <p class="text-blue-900/30 font-black text-[9px] uppercase tracking-[0.5em]">Lalom Wittaya Smart Duty System</p>
        </div>

    </div>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</body>
</html>
