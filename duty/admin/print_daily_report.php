<?php
/**
 * duty/admin/print_daily_report.php — รายงานสรุปการปฏิบัติหน้าที่เวรประจำวัน (Professional Version)
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin', 'att_teacher'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();
$targetDate = $_GET['date'] ?? date('Y-m-d');
$thaiDate   = date('d ') . getThaiMonth(date('n', strtotime($targetDate))) . date(' พ.ศ. ') . (date('Y', strtotime($targetDate)) + 543);

function getThaiMonth($m) {
    return ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'][$m];
}

// 1. ดึงข้อมูลรายงานทั้งหมด
$sql = "
    SELECT 
        ds.shift, ds.point_no, ds.role,
        dt.full_name AS teacher_name, dt.prefix AS teacher_prefix,
        dr.id AS report_id, dr.status, dr.report_note, dr.completed_at
    FROM duty_schedule ds
    LEFT JOIN duty_teachers dt ON dt.id = ds.teacher_id
    LEFT JOIN duty_reports dr ON dr.duty_date = ds.duty_date AND dr.shift = ds.shift AND dr.point_no = ds.point_no
    WHERE ds.duty_date = ?
    ORDER BY ds.shift DESC, ds.point_no ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$targetDate]);
$allSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$shifts = ['day' => [], 'night' => []];
foreach ($allSchedules as $row) { $shifts[$row['shift']][] = $row; }

function getPointPhotos($pdo, $reportId) {
    if (!$reportId) return [];
    $stmt = $pdo->prepare("SELECT file_path FROM duty_report_photos WHERE report_id = ? AND is_deleted = 0 ORDER BY received_at ASC");
    $stmt->execute([$reportId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานเวรประจำวัน - <?= $targetDate ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page { size: A4; margin: 0; }
        body { font-family: 'Prompt', sans-serif; background: #f1f5f9; color: #334155; line-height: 1.4; margin: 0; padding: 0; }
        .report-paper { 
            width: 210mm; 
            height: 297mm; 
            padding: 10mm 15mm; 
            margin: 0 auto; 
            background: white; 
            box-shadow: 0 0 40px rgba(0,0,0,0.1); 
            border-top: 8px solid #1e3a8a; 
            overflow: hidden;
            position: relative;
        }
        @media print {
            body { background: white; }
            .report-paper { margin: 0; box-shadow: none; border-top: none; }
            .no-print { display: none !important; }
        }
        .table-official th { background: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; padding: 12px; border-bottom: 2px solid #e2e8f0; }
        .table-official td { padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .point-title { font-size: 1.1rem; font-weight: 700; color: #1e3a8a; border-left: 4px solid #1e3a8a; padding-left: 12px; margin-bottom: 15px; }
        .photo-grid-mini { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 15px; }
        .photo-item { border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; aspect-ratio: 4/3; }
        .photo-item img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body class="p-4">

    <div class="no-print flex justify-center gap-4 mb-8">
        <button onclick="window.print()" class="bg-slate-800 text-white px-8 py-3 rounded-xl font-bold shadow-lg hover:bg-black transition-all">
            <i class="fas fa-print me-2"></i> Print Official Report
        </button>
        <button onclick="window.close()" class="bg-white text-slate-500 border border-slate-200 px-8 py-3 rounded-xl font-bold shadow-sm">
            Close
        </button>
    </div>

    <div class="report-paper">
        <!-- Header (Extremely Compact) -->
        <div class="flex justify-between items-center border-bottom-2 border-slate-200 pb-2 mb-4">
            <div class="flex items-center gap-3">
                <img src="https://suched2513.github.io/image/%E0%B8%95%E0%B8%A3%E0%B8%B2%E0%B8%A5%E0%B8%B0%E0%B8%A5%E0%B8%A1%E0%B8%A7%E0%B8%B4%E0%B8%9767.png" alt="Logo" class="w-12 h-12">
                <div>
                    <h1 class="text-lg font-bold text-slate-900 leading-tight">สรุปผลการปฏิบัติหน้าที่เวรประจำวัน</h1>
                    <p class="text-sm text-blue-700 font-semibold mt-0">โรงเรียนละลมวิทยา</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-[10px] text-slate-500 font-bold mb-1">วันที่ <?= $thaiDate ?></p>
                <div class="inline-block px-2 py-0.5 bg-slate-50 border border-slate-200 rounded text-[8px] font-bold text-slate-400">
                    LLW-DT-<?= date('Ymd', strtotime($targetDate)) ?>
                </div>
            </div>
        </div>

        <!-- Detailed Records -->
        <?php foreach (['day' => 'กลางวัน', 'night' => 'กลางคืน'] as $shiftKey => $shiftTitle): ?>
            <?php if (!empty($shifts[$shiftKey])): ?>
                <div class="mb-2">
                    <h3 class="text-[11px] font-bold text-slate-400 mb-2 flex items-center gap-2 uppercase tracking-widest">
                        <span class="w-0.5 h-3 bg-blue-700 rounded-full"></span>
                        เวรช่วง<?= $shiftTitle ?>
                    </h3>

                    <?php foreach ($shifts[$shiftKey] as $s): 
                        $photos = getPointPhotos($pdo, $s['report_id']);
                    ?>
                        <div class="mb-2 border border-slate-100 rounded-lg overflow-hidden page-break-inside-avoid">
                            <div class="bg-slate-50 px-3 py-1.5 border-bottom flex justify-between items-center">
                                <div class="text-[11px] font-bold text-blue-900">จุดที่ <?= $s['point_no'] ?>: <?= htmlspecialchars($s['teacher_prefix'] . $s['teacher_name']) ?></div>
                                <div class="text-[9px] font-bold text-slate-400">
                                    <?= $s['completed_at'] ? date('H:i', strtotime($s['completed_at'])) : '—' ?>
                                </div>
                            </div>
                            
                            <div class="p-2 flex gap-3 align-items-center">
                                <div class="flex-grow">
                                    <div class="text-[10px] text-slate-600 leading-tight italic">
                                        <?= !empty($s['report_note']) ? nl2br(htmlspecialchars($s['report_note'])) : 'ปกติ' ?>
                                    </div>
                                </div>

                                <?php if (!empty($photos)): ?>
                                    <div class="flex gap-1 flex-shrink-0">
                                        <?php foreach (array_slice($photos, 0, 4) as $p): ?>
                                            <div class="w-12 h-9 rounded border border-slate-200 overflow-hidden">
                                                <img src="<?= $base_path ?>/duty/api/photo.php?path=<?= urlencode($p['file_path']) ?>" class="w-full h-full object-cover">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Footer / Approval Section -->
        <div class="mt-4 border-t border-slate-200 pt-4">
            <div class="grid grid-cols-2 gap-10 text-center">
                <div>
                    <p class="text-[10px] text-slate-400 mb-6">(ลงชื่อ)............................................................</p>
                    <p class="text-xs font-bold text-slate-800"><?= htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']) ?></p>
                    <p class="text-[9px] text-slate-500 font-medium">ผู้จัดทำรายงาน</p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 mb-6">(ลงชื่อ)............................................................</p>
                    <p class="text-xs font-bold text-slate-800">นายสมชาย ใจดี</p>
                    <p class="text-[9px] text-slate-500 font-medium">ผู้อำนวยการโรงเรียนละลมวิทยา</p>
                </div>
            </div>
        </div>

        <!-- System Footer -->
        <div class="absolute bottom-4 left-0 right-0 text-center opacity-20">
            <p class="text-[7px] text-slate-300 font-bold uppercase tracking-[0.3em]">
                LLW Platinum Smart Duty Reporting System
            </p>
        </div>
    </div>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</body>
</html>
