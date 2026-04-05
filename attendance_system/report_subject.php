<?php
require_once 'functions.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'];
$pageTitle = 'รายงานสรุปวิชา';
$pageSubtitle = 'สถิติการเช็คชื่อแยกตามรายวิชาและคาบเรียน';

$subjects = getTeacherSubjects($teacher_id, $pdo);
$selected_subject_id = $_GET['subject_id'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 month'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$subject_info = null; $report_data = []; $summary = ['มา'=>0,'ขาด'=>0,'ลา'=>0,'โดด'=>0,'สาย'=>0];
$period_stats = array_fill(1, 8, ['มา'=>0,'ขาด'=>0,'ลา'=>0,'โดด'=>0,'สาย'=>0]);

if ($selected_subject_id) {
    $subject_info = getSubjectById($selected_subject_id, $pdo);
    if ($subject_info) {
        $stmt = $pdo->prepare("
            SELECT a.*, s.name as student_name, s.student_id as student_code
            FROM att_attendance a
            JOIN att_students s ON s.id = a.student_id
            WHERE a.subject_id = :subject_id AND (a.date BETWEEN :start AND :end)
            ORDER BY a.date DESC, a.period DESC, s.student_id ASC
        ");
        $stmt->execute(['subject_id'=>$selected_subject_id, 'start'=>$start_date, 'end'=>$end_date]);
        $report_data = $stmt->fetchAll();
        foreach($report_data as $row) {
            if(isset($summary[$row['status']])) $summary[$row['status']]++;
            if(isset($period_stats[$row['period']][$row['status']])) $period_stats[$row['period']][$row['status']]++;
        }
    }
}

require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-8">
    <!-- Filter -->
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 no-print">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">เลือกวิชา</label>
                <select name="subject_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition-all" required>
                    <option value="">-- เลือกวิชา --</option>
                    <?php foreach($subjects as $subj): ?>
                        <option value="<?= $subj['id'] ?>" <?= $selected_subject_id == $subj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subj['subject_code'] . ' - ' . $subj['subject_name'] . ' (' . $subj['classroom'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ตั้งแต่วันที่</label>
                <input type="date" name="start_date" value="<?= $start_date ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ถึงวันที่</label>
                    <input type="date" name="end_date" value="<?= $end_date ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl hover:bg-blue-700 transition font-bold shadow-lg shadow-blue-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>

    <?php if ($selected_subject_id && $subject_info): ?>
    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <?php 
        $colors = ['มา'=>'emerald','ขาด'=>'rose','ลา'=>'amber','โดด'=>'violet','สาย'=>'orange'];
        foreach($summary as $st => $count): $c = $colors[$st]; ?>
        <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center">
            <span class="text-[10px] font-black text-<?= $c ?>-500 uppercase tracking-widest mb-1"><?= $st ?></span>
            <span class="text-2xl font-black text-slate-800"><?= $count ?></span>
            <span class="text-[9px] text-slate-400 font-bold uppercase mt-1">ครั้ง</span>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Chart -->
        <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 h-fit">
            <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2 italic">
               <i class="bi bi-pie-chart-fill text-indigo-500"></i> เปอร์เซ็นต์การเข้าเรียนตามสถานะ
            </h3>
            <div class="h-64 flex items-center justify-center">
                <canvas id="subjectPieChart"></canvas>
            </div>
        </div>

        <!-- Period Breakdown Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-5 bg-slate-50 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-sm">สถิติสะสมแยกตามคาบเรียน</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-4 py-3 text-left">คาบ</th>
                            <?php foreach($summary as $st => $v): ?> <th class="px-4 py-3 text-center"><?= $st ?></th> <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php for($p=1; $p<=8; $p++): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3 font-bold text-blue-600 italic">คาบที่ <?= $p ?></td>
                            <?php foreach($summary as $st => $v): 
                                $count = $period_stats[$p][$st];
                                $sc = $colors[$st];
                            ?>
                            <td class="px-4 py-3 text-center font-bold <?= $count > 0 ? "text-$sc-600 bg-$sc-50/30" : "text-slate-300" ?>"><?= $count ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detailed Logs -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-50">
            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                <i class="bi bi-list-stars text-blue-600"></i> รายการเช็คชื่อฉบับละเอียด
            </h3>
            <button onclick="window.print()" class="text-xs font-bold text-slate-500 hover:text-blue-600 flex items-center gap-1">
                <i class="bi bi-printer"></i> พิมพ์
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    <tr>
                        <th class="px-6 py-4 text-left">วันที่/คาบ</th>
                        <th class="px-6 py-4 text-left">รหัสนักเรียน</th>
                        <th class="px-6 py-4 text-left">ชื่อ-สกุล</th>
                        <th class="px-6 py-4 text-center">สถานะ</th>
                        <th class="px-6 py-4 text-center">เวลาเข้า</th>
                        <th class="px-6 py-4 text-left">หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($report_data)): ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-300 uppercase tracking-widest font-bold">ไม่พบข้อมูลในช่วงเวลาดังกล่าว</td></tr>
                    <?php else: ?>
                        <?php foreach($report_data as $row): 
                            $sc = $colors[$row['status']] ?? 'slate';
                        ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-bold text-slate-700"><?= date('d/m/Y', strtotime($row['date'])) ?></span>
                                <span class="bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded ml-1 font-bold text-[9px]">P<?= $row['period'] ?></span>
                            </td>
                            <td class="px-6 py-4 font-mono text-blue-600"><?= $row['student_code'] ?></td>
                            <td class="px-6 py-4 font-bold text-slate-600"><?= htmlspecialchars($row['student_name']) ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 rounded-lg font-black text-[10px] bg-<?= $sc ?>-100 text-<?= $sc ?>-700"><?= $row['status'] ?></span>
                            </td>
                            <td class="px-6 py-4 text-center text-slate-500"><?= $row['time_in'] ?: '-' ?></td>
                            <td class="px-6 py-4 italic text-slate-400"><?= htmlspecialchars($row['note']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    <?php if ($selected_subject_id && !empty($summary)): ?>
    const ctx = document.getElementById('subjectPieChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($summary)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($summary)) ?>,
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#8b5cf6', '#f97316'],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 10, weight: 'bold' } } },
                tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 10 }
            }
        }
    });
    <?php endif; ?>
</script>

<?php require_once '../components/layout_end.php'; ?>
