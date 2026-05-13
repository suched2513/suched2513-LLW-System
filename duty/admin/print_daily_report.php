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
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page { size: A4; margin: 0; }
        body { font-family: 'Prompt', sans-serif; background: #f1f5f9; color: #334155; line-height: 1.4; margin: 0; padding: 0; }
        .report-paper { 
            width: 210mm; 
            min-height: 297mm; 
            padding: 15mm 20mm; 
            margin: 20px auto; 
            background: white; 
            box-shadow: 0 0 40px rgba(0,0,0,0.1); 
            position: relative;
        }
        @media print {
            body { background: white; margin: 0; padding: 0; }
            .report-paper { margin: 0; box-shadow: none; border-top: none; width: 100%; height: auto; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; margin-top: 20mm; }
        }
        .table-official th { background: #f8fafc; color: #1e3a8a; font-weight: 900; font-size: 11px; padding: 10px; border: 1px solid #e2e8f0; }
        .table-official td { padding: 8px 10px; border: 1px solid #e2e8f0; font-size: 11px; vertical-align: middle; }
        .point-box { border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 10px; overflow: hidden; page-break-inside: avoid; }
        .point-header { background: #f8fafc; padding: 8px 12px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body class="p-4">

    <div class="no-print flex justify-center gap-4 mb-8">
        <button onclick="window.print()" class="bg-blue-900 text-white px-8 py-3 rounded-2xl font-black shadow-lg hover:scale-105 transition-all">
            <i class="fas fa-print me-2"></i> พิมพ์รายงาน / บันทึกเป็น PDF
        </button>
        <button onclick="window.close()" class="bg-white text-slate-500 border border-slate-200 px-8 py-3 rounded-xl font-bold">
            ปิดหน้าต่าง
        </button>
    </div>

    <div class="report-paper">
        <!-- Header -->
        <div class="text-center mb-8">
            <img src="https://suched2513.github.io/image/%E0%B8%95%E0%B8%A3%E0%B8%B2%E0%B8%A5%E0%B8%B0%E0%B8%A5%E0%B8%A1%E0%B8%A7%E0%B8%B4%E0%B8%9767.png" alt="Logo" class="w-16 h-16 mx-auto mb-2">
            <h1 class="text-2xl font-black text-blue-900 leading-tight">รายงานสรุปผลการปฏิบัติหน้าที่เวรประจำวัน</h1>
            <h2 class="text-lg font-bold text-slate-500">โรงเรียนละลมวิทยา </h2>
            <div class="mt-2 text-sm font-bold text-slate-400">ประจำ<?= $thaiDate ?></div>
        </div>

        <!-- Summary Table (Director's Overview) -->
        <div class="mb-8">
            <h3 class="text-sm font-black text-blue-900 mb-3 flex items-center gap-2">
                <i class="fas fa-tasks"></i>
                สรุปสถานะการปฏิบัติหน้าที่รายจุด
            </h3>
            <table class="table-official w-full text-left">
                <thead>
                    <tr>
                        <th class="text-center" width="10%">กะ/จุด</th>
                        <th width="35%">ครูที่ปรึกษา / ผู้ปฏิบัติหน้าที่</th>
                        <th width="25%">ภารกิจ/ตำแหน่ง</th>
                        <th class="text-center" width="15%">สถานะ</th>
                        <th class="text-center" width="15%">เวลาบันทึก</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allSchedules as $s): 
                        $isComplete = ($s['status'] === 'complete');
                        $shiftIcon = $s['shift'] === 'day' ? '☀️' : '🌙';
                    ?>
                    <tr>
                        <td class="text-center font-bold"><?= $shiftIcon ?> <?= $s['point_no'] ?></td>
                        <td class="font-bold text-slate-800"><?= htmlspecialchars($s['teacher_prefix'] . $s['teacher_name']) ?></td>
                        <td class="text-slate-500 italic"><?= htmlspecialchars($s['role'] ?: 'ครูเวรประจำวัน') ?></td>
                        <td class="text-center">
                            <?php if ($isComplete): ?>
                                <span class="text-green-600 font-black">✓ เรียบร้อย</span>
                            <?php elseif ($s['status'] === 'partial'): ?>
                                <span class="text-orange-500 font-bold">! บางส่วน</span>
                            <?php else: ?>
                                <span class="text-red-500 font-bold">✗ ยังไม่รายงาน</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-slate-400">
                            <?= $s['completed_at'] ? date('H:i', strtotime($s['completed_at'])) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="page-break"></div>

        <!-- Detailed Evidence (Photos & Notes) -->
        <h3 class="text-sm font-black text-blue-900 mb-4 flex items-center gap-2">
            <i class="fas fa-camera"></i>
            รายละเอียดและภาพถ่ายการปฏิบัติหน้าที่
        </h3>

        <?php foreach (['day' => '☀️ กะกลางวัน', 'night' => '🌙 กะกลางคืน'] as $shiftKey => $shiftTitle): ?>
            <?php if (!empty($shifts[$shiftKey])): ?>
                <div class="mb-6">
                    <div class="bg-blue-900 text-white px-4 py-1.5 rounded-lg text-xs font-black uppercase tracking-widest mb-3 inline-block">
                        <?= $shiftTitle ?>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <?php foreach ($shifts[$shiftKey] as $s): 
                            $photos = getPointPhotos($pdo, $s['report_id']);
                        ?>
                            <div class="point-box">
                                <div class="point-header">
                                    <div class="text-[11px] font-black text-blue-900">
                                        จุดที่ <?= $s['point_no'] ?> : <?= htmlspecialchars($s['teacher_prefix'] . $s['teacher_name']) ?>
                                    </div>
                                    <div class="text-[9px] font-bold text-slate-400">
                                        <?= $s['completed_at'] ? 'บันทึกเมื่อ ' . date('H:i', strtotime($s['completed_at'])) . ' น.' : 'ยังไม่มีข้อมูล' ?>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <div class="text-[10px] text-slate-600 mb-3 italic leading-relaxed">
                                        " <?= !empty($s['report_note']) ? nl2br(htmlspecialchars($s['report_note'])) : 'ปฏิบัติหน้าที่เรียบร้อย เหตุการณ์ทั่วไปปกติ' ?> "
                                    </div>
                                    <?php if (!empty($photos)): ?>
                                        <div class="grid grid-cols-4 gap-2">
                                            <?php foreach (array_slice($photos, 0, 4) as $p): ?>
                                                <div class="aspect-[4/3] rounded-md overflow-hidden border border-slate-200">
                                                    <img src="<?= $base_path ?>/duty/api/photo.php?path=<?= urlencode($p['file_path']) ?>" class="w-full h-full object-cover">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="h-16 flex items-center justify-center bg-slate-50 border border-dashed border-slate-200 rounded-lg text-slate-300 text-[10px] font-bold italic">
                                            <i class="fas fa-image me-2"></i> ไม่พบภาพถ่ายประกอบรายงาน
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Approval Section -->
        <div class="mt-12 pt-8 border-t-2 border-slate-100">
            <div class="grid grid-cols-2 gap-20 text-center">
                <div>
                    <p class="text-xs text-slate-400 mb-12">(ลงชื่อ)............................................................</p>
                    <p class="text-sm font-black text-slate-800"><?= htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']) ?></p>
                    <p class="text-[10px] text-blue-900 font-black uppercase tracking-widest mt-1">ผู้จัดทำรายงาน / แอดมินระบบ</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-12">(ลงชื่อ)............................................................</p>
                    <p class="text-sm font-black text-slate-800">นายสถาน ปรางมาศ</p>
                    <p class="text-[10px] text-slate-500 font-black uppercase tracking-widest mt-1">ผู้อำนวยการโรงเรียนละลมวิทยา</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="absolute bottom-4 left-0 right-0 text-center opacity-30">
            <p class="text-[7px] text-slate-400 font-black uppercase tracking-[0.4em]">
                LLW Platinum Smart Duty Reporting System — Generated at <?= date('d/m/Y H:i:s') ?>
            </p>
        </div>
    </div>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</body>
</html>
