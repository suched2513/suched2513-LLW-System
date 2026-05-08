<?php
/**
 * duty/admin/print_daily_report.php — รายงานสรุปการปฏิบัติหน้าที่เวรประจำวัน (Professional Version)
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
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
        @page { size: A4; margin: 15mm; }
        body { font-family: 'Prompt', sans-serif; background: #f1f5f9; color: #334155; line-height: 1.6; }
        .report-paper { width: 210mm; min-height: 297mm; padding: 20mm; margin: 20px auto; background: white; box-shadow: 0 0 40px rgba(0,0,0,0.1); border-top: 8px solid #1e3a8a; }
        @media print {
            body { background: white; padding: 0; }
            .report-paper { margin: 0; box-shadow: none; width: 100%; border-top: none; }
            .no-print { display: none !important; }
            .page-break { page-break-after: always; }
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
        <!-- Header -->
        <div class="flex justify-between items-start border-bottom-2 border-slate-200 pb-8 mb-8">
            <div class="flex items-center gap-6">
                <img src="https://www.krusuched.com/wp-content/uploads/2023/10/logo-llw.png" class="w-24 h-24 object-contain">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 leading-tight">สรุปผลการปฏิบัติหน้าที่เวรประจำวัน</h1>
                    <p class="text-lg text-blue-700 font-semibold mt-1">โรงเรียนละลมวิทยา</p>
                    <p class="text-slate-500 mt-2 font-medium">ประจำวันที่ <?= $thaiDate ?></p>
                </div>
            </div>
            <div class="text-right">
                <div class="inline-block px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-500">
                    เลขที่เอกสาร: LLW-DT-<?= date('Ymd', strtotime($targetDate)) ?>
                </div>
            </div>
        </div>

        <!-- Executive Summary -->
        <section class="mb-12">
            <h2 class="text-sm font-bold text-slate-400 uppercase tracking-[0.2em] mb-4">Executive Summary</h2>
            <div class="grid grid-cols-4 gap-6">
                <div class="p-5 border border-slate-100 bg-slate-50/50 rounded-2xl">
                    <p class="text-xs text-slate-500 font-bold mb-1">จุดทั้งหมด</p>
                    <p class="text-3xl font-bold text-slate-800"><?= count($allSchedules) ?> <span class="text-sm font-normal text-slate-400">จุด</span></p>
                </div>
                <div class="p-5 border border-slate-100 bg-emerald-50/50 rounded-2xl">
                    <p class="text-xs text-emerald-600 font-bold mb-1">รายงานครบถ้วน</p>
                    <?php $comp = count(array_filter($allSchedules, fn($s) => $s['status'] === 'complete')); ?>
                    <p class="text-3xl font-bold text-emerald-700"><?= $comp ?> <span class="text-sm font-normal text-emerald-400">จุด</span></p>
                </div>
                <div class="p-5 border border-slate-100 bg-amber-50/50 rounded-2xl">
                    <p class="text-xs text-amber-600 font-bold mb-1">อยู่ระหว่างดำเนินการ</p>
                    <?php $part = count(array_filter($allSchedules, fn($s) => $s['status'] === 'partial')); ?>
                    <p class="text-3xl font-bold text-amber-700"><?= $part ?> <span class="text-sm font-normal text-amber-400">จุด</span></p>
                </div>
                <div class="p-5 border border-slate-100 bg-rose-50/50 rounded-2xl">
                    <p class="text-xs text-rose-600 font-bold mb-1">ยังไม่มีการรายงาน</p>
                    <?php $pend = count(array_filter($allSchedules, fn($s) => $s['status'] === 'pending' || !$s['status'])); ?>
                    <p class="text-3xl font-bold text-rose-700"><?= $pend ?> <span class="text-sm font-normal text-rose-400">จุด</span></p>
                </div>
            </div>
        </section>

        <!-- Detailed Records -->
        <?php foreach (['day' => 'ผลการบันทึกเวรช่วงกลางวัน', 'night' => 'ผลการบันทึกเวรช่วงกลางคืน'] as $shiftKey => $shiftTitle): ?>
            <div class="mb-10 page-break-inside-avoid">
                <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-blue-700 rounded-full"></span>
                    <?= $shiftTitle ?>
                </h3>

                <?php if (empty($shifts[$shiftKey])): ?>
                    <div class="p-8 text-center text-slate-400 border border-dashed border-slate-200 rounded-2xl mb-8">
                        — ไม่มีการบันทึกข้อมูลในกะนี้ —
                    </div>
                <?php else: ?>
                    <?php foreach ($shifts[$shiftKey] as $s): 
                        $photos = getPointPhotos($pdo, $s['report_id']);
                    ?>
                        <div class="mb-12 page-break-inside-avoid">
                            <div class="flex justify-between items-end mb-4 border-b border-slate-100 pb-2">
                                <div class="point-title mb-0">จุดที่ <?= $s['point_no'] ?>: <?= htmlspecialchars($s['teacher_prefix'] . $s['teacher_name']) ?></div>
                                <div class="text-xs font-bold text-slate-400">
                                    <?php if ($s['completed_at']): ?>
                                        บันทึกสำเร็จเมื่อเวลา <?= date('H:i', strtotime($s['completed_at'])) ?> น.
                                    <?php else: ?>
                                        <span class="text-rose-400">ยังไม่ได้รับการบันทึกข้อมูล</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="bg-white p-2">
                                <div class="text-sm text-slate-600 mb-4 leading-relaxed bg-slate-50/50 p-4 rounded-xl border-l-4 border-slate-200">
                                    <span class="font-bold text-slate-800 block mb-1">บันทึกรายละเอียดการปฏิบัติงาน:</span>
                                    <?= !empty($s['report_note']) ? nl2br(htmlspecialchars($s['report_note'])) : 'ไม่ระบุบันทึกรายละเอียด (สถานะปกติ)' ?>
                                </div>

                                <?php if (!empty($photos)): ?>
                                    <div class="photo-grid-mini">
                                        <?php foreach ($photos as $p): ?>
                                            <div class="photo-item shadow-sm">
                                                <img src="<?= $base_path ?>/duty/api/photo.php?path=<?= urlencode($p['file_path']) ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($shiftKey === 'day' && !empty($shifts['night'])): ?>
                <div class="page-break"></div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Footer / Approval Section -->
        <div class="mt-20 border-t border-slate-200 pt-12">
            <div class="grid grid-cols-2 gap-20 text-center">
                <div>
                    <p class="text-sm text-slate-500 mb-16 italic">(ลงชื่อ)............................................................</p>
                    <p class="font-bold text-slate-800"><?= htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']) ?></p>
                    <p class="text-xs text-slate-500 font-medium">ผู้รายงาน / ผู้จัดทำเอกสาร</p>
                    <p class="text-[10px] text-slate-400 mt-1"><?= date('d/m/Y H:i') ?></p>
                </div>
                <div>
                    <p class="text-sm text-slate-500 mb-16 italic">(ลงชื่อ)............................................................</p>
                    <p class="font-bold text-slate-800">นายสมชาย ใจดี</p>
                    <p class="text-xs text-slate-500 font-medium">ผู้อำนวยการโรงเรียนละลมวิทยา</p>
                    <p class="text-[10px] text-slate-400 mt-1">ผู้อนุมัติ/ผู้รับทราบรายงาน</p>
                </div>
            </div>
        </div>

        <!-- System Footer -->
        <div class="mt-16 text-center border-t border-slate-50 pt-6">
            <p class="text-[9px] text-slate-300 font-bold uppercase tracking-[0.3em]">
                Generated by LLW Platinum Smart Duty Reporting System
            </p>
        </div>
    </div>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</body>
</html>
