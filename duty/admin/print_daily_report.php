<?php
/**
 * duty/admin/print_daily_report.php — รายงานสรุปการปฏิบัติหน้าที่เวรประจำวัน (ฉบับสมบูรณ์)
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();
$targetDate = $_GET['date'] ?? date('Y-m-d');
$thaiDate   = date('d/m/', strtotime($targetDate)) . (date('Y', strtotime($targetDate)) + 543);

// 1. ดึงข้อมูลรายงานทั้งหมดของวันนั้น
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

// จัดกลุ่มข้อมูลตามกะ
$shifts = ['day' => [], 'night' => []];
foreach ($allSchedules as $row) {
    $shifts[$row['shift']][] = $row;
}

// 2. ดึงรูปภาพประกอบ (เอาแค่ 2 รูปเด่นต่อจุด)
function getPointPhotos($pdo, $reportId) {
    if (!$reportId) return [];
    $stmt = $pdo->prepare("SELECT file_path FROM duty_report_photos WHERE report_id = ? AND is_deleted = 0 LIMIT 2");
    $stmt->execute([$reportId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานเวรประจำวัน - <?= $thaiDate ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f8fafc; color: #1e293b; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .report-page { box-shadow: none !important; border: none !important; margin: 0; width: 100%; }
            .page-break { page-break-after: always; }
        }
        .report-page {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: white;
            padding: 20mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            position: relative;
        }
        .header-line { height: 4px; background: linear-gradient(to right, #2563eb, #60a5fa); border-radius: 2px; }
        .point-card { border: 1px solid #e2e8f0; border-radius: 16px; margin-bottom: 1.5rem; overflow: hidden; page-break-inside: avoid; }
        .point-header { background: #f1f5f9; padding: 10px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; }
        .photo-box { width: 100%; aspect-ratio: 4/3; background: #f8fafc; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body class="p-4">

    <div class="no-print mb-8 flex justify-center gap-4">
        <button onclick="window.print()" class="bg-blue-600 text-white px-10 py-4 rounded-2xl font-black shadow-lg hover:bg-blue-700 transition-all">
            <i class="fas fa-print me-2"></i> พิมพ์รายงานสรุปประจำวัน
        </button>
        <button onclick="window.close()" class="bg-white text-slate-500 border border-slate-200 px-10 py-4 rounded-2xl font-bold">
            ปิดหน้าต่าง
        </button>
    </div>

    <div class="report-page">
        <!-- Header -->
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-3xl font-black text-slate-800">รายงานสรุปการปฏิบัติหน้าที่เวรประจำวัน</h1>
                <p class="text-xl font-bold text-blue-600 mt-1">โรงเรียนละลมวิทยา</p>
                <p class="text-slate-500 font-medium">วันปฏิบัติหน้าที่: <?= $thaiDate ?></p>
            </div>
            <img src="https://www.krusuched.com/wp-content/uploads/2023/10/logo-llw.png" class="w-20 h-20 object-contain">
        </div>
        <div class="header-line mb-8"></div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100">
                <p class="text-xs font-bold text-blue-500 uppercase">รายงานแล้ว</p>
                <p class="text-2xl font-black text-blue-700">
                    <?php 
                    $reported = array_filter($allSchedules, fn($s) => $s['status'] === 'complete');
                    echo count($reported) . ' / ' . count($allSchedules);
                    ?>
                </p>
            </div>
            <div class="bg-emerald-50 p-4 rounded-2xl border border-emerald-100">
                <p class="text-xs font-bold text-emerald-500 uppercase">สถานะภาพรวม</p>
                <p class="text-2xl font-black text-emerald-700">เหตุการณ์ปกติ</p>
            </div>
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <p class="text-xs font-bold text-slate-500 uppercase">ผู้ตรวจสอบ</p>
                <p class="text-lg font-black text-slate-700">นายสมชาย ใจดี</p>
            </div>
        </div>

        <?php foreach (['day' => '☀️ เวรช่วงกลางวัน', 'night' => '🌙 เวรช่วงกลางคืน'] as $shiftKey => $shiftTitle): ?>
            <h3 class="text-xl font-black text-slate-800 mb-4 flex items-center gap-3">
                <span class="w-2 h-8 bg-blue-600 rounded-full"></span>
                <?= $shiftTitle ?>
            </h3>

            <?php if (empty($shifts[$shiftKey])): ?>
                <div class="p-6 text-center text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200 mb-8">
                    ไม่มีข้อมูลการจัดเวรในกะนี้
                </div>
            <?php else: ?>
                <?php foreach ($shifts[$shiftKey] as $s): 
                    $photos = getPointPhotos($pdo, $s['report_id']);
                ?>
                    <div class="point-card shadow-sm">
                        <div class="point-header flex justify-between items-center">
                            <span>จุดที่ <?= $s['point_no'] ?>: <?= htmlspecialchars($s['teacher_prefix'] . $s['teacher_name']) ?></span>
                            <?php if ($s['status'] === 'complete'): ?>
                                <span class="text-emerald-600 text-xs font-black"><i class="fas fa-check-circle"></i> รายงานแล้ว</span>
                            <?php else: ?>
                                <span class="text-rose-500 text-xs font-black"><i class="fas fa-times-circle"></i> ยังไม่ได้รายงาน</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-12 gap-4">
                                <div class="col-span-8">
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">บันทึกรายละเอียด</p>
                                    <div class="text-sm text-slate-600 leading-relaxed min-h-[60px]">
                                        <?= !empty($s['report_note']) ? nl2br(htmlspecialchars($s['report_note'])) : '<span class="italic text-slate-300">ไม่มีบันทึกรายละเอียด</span>' ?>
                                    </div>
                                    <?php if ($s['completed_at']): ?>
                                        <p class="text-[10px] text-slate-400 mt-2 font-bold">
                                            บันทึกเมื่อเวลา <?= date('H:i', strtotime($s['completed_at'])) ?> น.
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-span-4 flex gap-2">
                                    <?php for($i=0; $i<2; $i++): ?>
                                        <div class="photo-box">
                                            <?php if (isset($photos[$i])): ?>
                                                <img src="<?= $base_path ?>/duty/api/photo.php?path=<?= urlencode($photos[$i]['file_path']) ?>">
                                            <?php else: ?>
                                                <div class="flex items-center justify-center h-full text-slate-200">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if ($shiftKey === 'day'): ?>
                <div class="page-break"></div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Signatures -->
        <div class="mt-12 grid grid-cols-2 gap-20 text-center">
            <div>
                <div class="h-20 border-b border-dashed border-slate-300 w-64 mx-auto mb-4"></div>
                <p class="font-bold">นายสมชาย ใจดี</p>
                <p class="text-xs text-slate-500">ผู้อำนวยการโรงเรียนละลมวิทยา</p>
            </div>
            <div>
                <div class="h-20 border-b border-dashed border-slate-300 w-64 mx-auto mb-4"></div>
                <p class="font-bold"><?= htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']) ?></p>
                <p class="text-xs text-slate-500">ผู้จัดทำรายงาน</p>
            </div>
        </div>

    </div>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</body>
</html>
