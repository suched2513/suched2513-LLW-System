<?php
require_once 'functions.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'];
$pageTitle = 'สรุปภาพรวมรายวิชา';
$pageSubtitle = 'สรุปยอดรวมสถานะการเช็คชื่อแยกตามนักเรียน';

$subjects = getTeacherSubjects($teacher_id, $pdo);
$selected_subject_id = $_GET['subject_id'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$report_data = []; $subject_info = null;

if ($selected_subject_id) {
    $subject_info = getSubjectById($selected_subject_id, $pdo);
    $stmt = $pdo->prepare("
        SELECT 
            s.student_id, s.name,
            SUM(CASE WHEN a.status = 'มา' THEN 1 ELSE 0 END) as count_come,
            SUM(CASE WHEN a.status = 'ขาด' THEN 1 ELSE 0 END) as count_absent,
            SUM(CASE WHEN a.status = 'ลา' THEN 1 ELSE 0 END) as count_leave,
            SUM(CASE WHEN a.status = 'โดด' THEN 1 ELSE 0 END) as count_skip,
            SUM(CASE WHEN a.status = 'สาย' THEN 1 ELSE 0 END) as count_late,
            COUNT(a.id) as total_attendance
        FROM att_students s
        LEFT JOIN att_attendance a ON s.id = a.student_id 
            AND a.subject_id = :subject_id 
            AND a.date BETWEEN :start_date AND :end_date
        WHERE s.classroom = :classroom
        GROUP BY s.id ORDER BY s.student_id ASC
    ");
    $stmt->execute(['subject_id'=>$selected_subject_id, 'start_date'=>$start_date, 'end_date'=>$end_date, 'classroom'=>$subject_info['classroom']]);
    $report_data = $stmt->fetchAll();
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv' && $selected_subject_id) {
    $filename = "Summary_" . $subject_info['subject_code'] . "_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, ['รหัสนักเรียน', 'ชื่อ-สกุล', 'มา', 'ขาด', 'ลา', 'โดด', 'สาย', 'รวม']);
    foreach ($report_data as $row) {
        fputcsv($output, [$row['student_id'], $row['name'], $row['count_come'], $row['count_absent'], $row['count_leave'], $row['count_skip'], $row['count_late'], $row['total_attendance']]);
    }
    fclose($output); exit();
}

require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-8">
    <!-- Filter -->
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 no-print">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">เลือกวิชา</label>
                <select name="subject_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none" required>
                    <option value="">-- เลือกวิชา --</option>
                    <?php foreach($subjects as $subj): ?>
                        <option value="<?= $subj['id'] ?>" <?= $selected_subject_id == $subj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subj['subject_code'] . ' - ' . $subj['subject_name'] . ' (' . $subj['classroom'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ตั้งแต่</label>
                <input type="date" name="start_date" value="<?= $start_date ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ถึง</label>
                    <input type="date" name="end_date" value="<?= $end_date ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl hover:bg-blue-700 transition font-bold shadow-lg shadow-blue-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>

    <?php if ($selected_subject_id && $subject_info): ?>
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($subject_info['subject_name']) ?></h3>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">ห้อง <?= $subject_info['classroom'] ?> — สรุปยอดสะสม</p>
            </div>
            <div class="flex gap-2">
                <a href="report.php?subject_id=<?= $selected_subject_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=csv" class="bg-emerald-50 text-emerald-600 px-4 py-2 rounded-xl text-xs font-bold hover:bg-emerald-100 transition border border-emerald-100 flex items-center gap-2">
                    <i class="bi bi-file-earmark-spreadsheet-fill"></i> Export CSV
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-50 text-xs text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    <tr>
                        <th class="px-8 py-4">รหัสนักเรียน</th>
                        <th class="px-8 py-4">ชื่อ-สกุล</th>
                        <th class="px-4 py-4 text-center text-emerald-600">มา</th>
                        <th class="px-4 py-4 text-center text-rose-600">ขาด</th>
                        <th class="px-4 py-4 text-center text-amber-600">ลา</th>
                        <th class="px-4 py-4 text-center text-violet-600">โดด</th>
                        <th class="px-4 py-4 text-center text-orange-600">สาย</th>
                        <th class="px-8 py-4 text-center text-blue-600">% มาเรียน</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach($report_data as $row): 
                        $rate = $row['total_attendance'] > 0 ? round(($row['count_come'] / $row['total_attendance']) * 100, 1) : 0;
                        $rc = $rate > 80 ? 'emerald' : ($rate > 50 ? 'amber' : 'rose');
                    ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-8 py-4 font-mono font-bold text-slate-500 uppercase"><?= $row['student_id'] ?></td>
                        <td class="px-8 py-4 font-bold text-slate-700"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="px-4 py-4 text-center font-bold text-emerald-600"><?= $row['count_come'] ?></td>
                        <td class="px-4 py-4 text-center font-bold text-rose-600"><?= $row['count_absent'] ?></td>
                        <td class="px-4 py-4 text-center font-bold text-amber-600"><?= $row['count_leave'] ?></td>
                        <td class="px-4 py-4 text-center font-bold text-violet-600"><?= $row['count_skip'] ?></td>
                        <td class="px-4 py-4 text-center font-bold text-orange-600"><?= $row['count_late'] ?></td>
                        <td class="px-8 py-4 text-center">
                            <div class="flex flex-col items-center">
                                <span class="font-black text-<?= $rc ?>-600"><?= $rate ?>%</span>
                                <div class="w-16 h-1 mt-1 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-<?= $rc ?>-500" style="width: <?= $rate ?>%"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../components/layout_end.php'; ?>
