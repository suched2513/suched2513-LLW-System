<?php
/**
 * duty/admin/print_pr.php — จดหมายข่าวประชาสัมพันธ์ (PR News) อัตโนมัติ
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
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

// ── ดึงรูป 6 รูปแรก ──
$stmtPh = $pdo->prepare(
    "SELECT * FROM duty_report_photos
     WHERE report_id = ? AND is_deleted = 0
     ORDER BY received_at ASC LIMIT 6"
);
$stmtPh->execute([$reportId]);
$photos = $stmtPh->fetchAll(PDO::FETCH_ASSOC);

// ── ดึงชื่อจุดจาก settings ──
$stmtSet = $pdo->prepare("SELECT svalue FROM duty_settings WHERE skey = ?");
$stmtSet->execute(['point_' . $report['point_no'] . '_name']);
$pointName = $stmtSet->fetchColumn() ?: 'จุดที่ ' . $report['point_no'];

$thaiDate = date('d/m/') . (date('Y', strtotime($report['duty_date'])) + 543);
$shiftName = $report['shift'] === 'day' ? 'กลางวัน' : 'กลางคืน';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานเวร - <?= htmlspecialchars($report['teacher_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f0f9ff; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; margin: 0; }
            .pr-container { box-shadow: none !important; border: none !important; width: 100% !important; height: 100vh !important; }
        }
        .pr-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: linear-gradient(to bottom, #f0f9ff 0%, #ffffff 40%, #ffffff 80%, #dcfce7 100%);
            position: relative;
            box-shadow: 0 0 50px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 40px;
        }
        .cloud { position: absolute; background: white; border-radius: 50%; opacity: 0.6; filter: blur(20px); }
        .glass-box { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 30px; border: 4px solid #1e40af; }
        .title-gradient { background: linear-gradient(to right, #1e3a8a, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .photo-slot { aspect-ratio: 4/3; border-radius: 20px; border: 4px solid #3b82f6; overflow: hidden; background: #e0f2fe; }
        .photo-slot img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body class="p-4">

    <div class="no-print mb-6 flex justify-center gap-4">
        <button onclick="window.print()" class="bg-blue-800 text-white px-8 py-3 rounded-2xl font-black shadow-lg hover:scale-105 transition-all">
            <i class="fas fa-print me-2"></i> พิมพ์รายงาน / บันทึก PDF
        </button>
        <button onclick="window.close()" class="bg-slate-200 text-slate-600 px-8 py-3 rounded-2xl font-bold">
            ปิดหน้าต่าง
        </button>
    </div>

    <div class="pr-container rounded-[40px]">
        <!-- Clouds background deco -->
        <div class="cloud w-64 h-24 -top-10 -left-10"></div>
        <div class="cloud w-80 h-32 top-20 -right-20"></div>
        
        <!-- Header Section -->
        <div class="relative z-10 text-center mb-10">
            <img src="https://www.krusuched.com/wp-content/uploads/2023/10/logo-llw.png" alt="Logo" class="w-24 h-24 mx-auto mb-4 drop-shadow-lg">
            <h1 class="text-4xl font-black title-gradient mb-2">รายงานการปฏิบัติหน้าที่เวรประจำวัน</h1>
            <h2 class="text-2xl font-black text-blue-800">โรงเรียนละลมวิทยา</h2>
            <p class="text-blue-500 font-bold">สำนักงานเขตพื้นที่การศึกษาประถมศึกษาศรีสะเกษ เขต 3</p>
        </div>

        <div class="flex justify-between items-start gap-6 mb-8 relative z-10">
            <div class="flex-1">
                <div class="bg-white border-4 border-blue-400 rounded-full py-4 px-10 text-center shadow-lg">
                    <h3 class="text-2xl font-black text-blue-700">
                        <?= htmlspecialchars($pointName) ?>
                    </h3>
                    <p class="text-blue-400 font-bold">( กะ<?= $shiftName ?> )</p>
                </div>
            </div>
            <div class="w-48 text-center">
                <div class="w-32 h-32 mx-auto rounded-full border-4 border-blue-400 overflow-hidden mb-2 bg-white shadow-xl">
                    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="Teacher" class="w-full h-full object-cover">
                </div>
                <div class="bg-blue-50 border-2 border-blue-400 rounded-full py-2 px-4 shadow-md">
                    <p class="text-[9px] font-black text-blue-600 uppercase tracking-tighter">ครูผู้ปฏิบัติหน้าที่</p>
                    <p class="text-xs font-bold text-blue-800"><?= htmlspecialchars($report['teacher_prefix'] . $report['teacher_name']) ?></p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="glass-box p-8 mb-10 relative z-10 min-h-[300px]">
            <div class="text-lg text-slate-700 leading-relaxed font-medium">
                <p class="mb-4">
                    เมื่อวันที่ <span class="font-bold text-blue-600"><?= $thaiDate ?></span> 
                    เวลา <span class="font-bold text-blue-600"><?= date('H:i', strtotime($report['completed_at'] ?: $report['created_at'])) ?> น.</span>
                    ทางโรงเรียนละลมวิทยา โดย <span class="font-bold text-blue-600"><?= htmlspecialchars($report['teacher_prefix'] . $report['teacher_name']) ?></span> 
                    ได้รับมอบหมายให้ปฏิบัติหน้าที่เวรประจำจุด <span class="font-bold text-blue-600">จุดที่ <?= $report['point_no'] ?></span> 
                    ในช่วงกะ<?= $shiftName ?>
                </p>
                <div class="p-6 bg-blue-50/50 rounded-2xl border-2 border-dashed border-blue-200">
                    <p class="text-blue-800 italic">
                        " <?= !empty($report['report_note']) ? nl2br(htmlspecialchars($report['report_note'])) : 'ปฏิบัติหน้าที่ตามปกติ เหตุการณ์ทั่วไปปกติ' ?> "
                    </p>
                </div>
                <p class="mt-4 text-right text-slate-500 italic font-bold">— บันทึกโดย <?= htmlspecialchars($report['teacher_name']) ?></p>
            </div>
        </div>

        <!-- Photo Grid (2x3) -->
        <div class="grid grid-cols-3 gap-6 relative z-10">
            <?php for($i=0; $i<6; $i++): ?>
                <div class="photo-slot shadow-xl">
                    <?php if (isset($photos[$i])): ?>
                        <img src="<?= $base_path ?>/duty/api/photo.php?path=<?= urlencode($photos[$i]['file_path']) ?>" alt="Photo">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-blue-200">
                            <i class="fas fa-image text-4xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Footer Deco -->
        <div class="absolute bottom-0 left-0 right-0 h-40 pointer-events-none opacity-40">
            <img src="https://img.freepik.com/free-vector/park-scene-with-many-trees_1308-36069.jpg" class="w-full h-full object-cover object-bottom" style="mask-image: linear-gradient(to top, black, transparent);">
        </div>
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-20 text-center w-full">
            <p class="text-emerald-800 font-black text-sm uppercase tracking-widest">Lalom Wittaya School - Smart Duty System</p>
        </div>

    </div>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</body>
</html>
