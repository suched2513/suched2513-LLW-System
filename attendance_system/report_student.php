<?php
require_once 'functions.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'];
$pageTitle = 'รายงานรายบุคคล';
$pageSubtitle = 'ตรวจสอบประวัติการเข้าเรียนของนักเรียนรายบุคคล';

$search = $_GET['search'] ?? '';
$student_info = null; $report_data = []; $summary = ['มา'=>0,'ขาด'=>0,'ลา'=>0,'โดด'=>0,'สาย'=>0];

if ($search) {
    // Find student by ID or Name
    $st = $pdo->prepare("SELECT * FROM att_students WHERE student_id = :s OR name LIKE :n LIMIT 1");
    $st->execute(['s'=>$search, 'n'=>"%$search%"]);
    $student_info = $st->fetch();

    if ($student_info) {
        $stmt = $pdo->prepare("
            SELECT a.*, sj.subject_name, sj.subject_code, sj.classroom as sj_cls
            FROM att_attendance a
            JOIN att_subjects sj ON sj.id = a.subject_id
            WHERE a.student_id = :sid
            ORDER BY a.date DESC, a.period DESC
        ");
        $stmt->execute(['sid'=>$student_info['id']]);
        $report_data = $stmt->fetchAll();
        foreach($report_data as $row) { if(isset($summary[$row['status']])) $summary[$row['status']]++; }
    }
}

require_once 'components/layout_start.php';
?>

<div class="flex flex-col gap-8">
    <!-- Search Bar -->
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 no-print flex flex-col md:flex-row gap-4 items-center">
        <div class="flex-1 w-full relative">
            <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <form method="GET" class="w-full">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาด้วยรหัสนักเรียน หรือชื่อ-สกุล..." 
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-12 pr-4 py-3 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition-all" id="search-input">
            </form>
        </div>
        <button onclick="document.querySelector('form').submit()" class="bg-blue-600 text-white px-8 py-3 rounded-2xl font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition flex items-center gap-2 w-full md:w-auto justify-center">
            <i class="bi bi-person-bounding-box"></i> ค้นหา
        </button>
    </div>

    <?php if ($search && !$student_info): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 p-8 rounded-3xl text-center shadow-sm">
            <i class="bi bi-person-x-fill text-3xl mb-3 block opacity-50"></i>
            <p class="font-bold">ไม่พบข้อมูลนักเรียน: "<?= htmlspecialchars($search) ?>"</p>
        </div>
    <?php elseif ($student_info): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Student Info Card -->
        <div class="lg:col-span-1 bg-white rounded-3xl p-8 shadow-sm border border-slate-100 flex flex-col items-center text-center gap-4 h-fit">
            <div class="w-24 h-24 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-4xl font-black border-4 border-white shadow-xl">
                 <?= mb_substr($student_info['name'], 0, 1) ?>
            </div>
            <div>
                <h3 class="text-xl font-black text-slate-800"><?= htmlspecialchars($student_info['name']) ?></h3>
                <p class="text-sm font-bold text-blue-600 font-mono mt-1">รหัส: <?= $student_info['student_id'] ?></p>
                <span class="inline-block mt-3 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-xl font-bold text-xs border border-emerald-100">ชั้นเรียน <?= $student_info['classroom'] ?></span>
            </div>
            <div class="w-full grid grid-cols-2 gap-2 mt-4">
                <div class="p-3 bg-slate-50 rounded-2xl">
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">มาเรียน</p>
                    <p class="text-xl font-black text-slate-700"><?= $summary['มา'] ?></p>
                </div>
                <div class="p-3 bg-slate-50 rounded-2xl">
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">ขาดเรียน</p>
                    <p class="text-xl font-black text-rose-500"><?= $summary['ขาด'] ?></p>
                </div>
            </div>
            <div class="w-full h-40 mt-4">
                 <canvas id="studentPieChart"></canvas>
            </div>
        </div>

        <!-- History Table -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 flex items-center gap-2 italic">
                    <i class="bi bi-clock-history text-indigo-500"></i> ประวัติการเข้าเรียนรายวิชา
                </h3>
                <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-1 rounded-lg font-bold uppercase tracking-widest">ทั้งหมด <?= count($report_data) ?> รายการ</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50 text-xs">
                    <thead class="bg-white text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-8 py-4 text-left">วันที่/คาบ</th>
                            <th class="px-8 py-4 text-left">รหัสวิชา - รายวิชา</th>
                            <th class="px-8 py-4 text-center">สถานะ</th>
                            <th class="px-8 py-4 text-left">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($report_data)): ?>
                            <tr><td colspan="4" class="px-8 py-16 text-center text-slate-300 font-bold uppercase tracking-widest">ยังไม่มีประวัติการเช็คชื่อ</td></tr>
                        <?php else: ?>
                            <?php foreach($report_data as $row): 
                                $colors = ['มา'=>'emerald','ขาด'=>'rose','ลา'=>'amber','โดด'=>'violet','สาย'=>'orange'];
                                $sc = $colors[$row['status']] ?? 'slate';
                            ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-8 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-700"><?= date('d/m/Y', strtotime($row['date'])) ?></span>
                                        <span class="text-[10px] text-slate-400 font-medium">คาบที่ <?= $row['period'] ?></span>
                                    </div>
                                </td>
                                <td class="px-8 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-bold text-blue-600 font-mono"><?= $row['subject_code'] ?></span>
                                        <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($row['subject_name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-8 py-4 text-center">
                                    <span class="px-3 py-1 rounded-xl font-black text-[10px] bg-<?= $sc ?>-100 text-<?= $sc ?>-700"><?= $row['status'] ?></span>
                                </td>
                                <td class="px-8 py-4 italic text-slate-400"><?= htmlspecialchars($row['note']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    <?php if ($student_info && !empty($summary)): ?>
    const ctx = document.getElementById('studentPieChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_keys($summary)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($summary)) ?>,
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#8b5cf6', '#f97316'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: '#1e293b', padding: 10, cornerRadius: 8 }
            }
        }
    });
    <?php endif; ?>
</script>

<?php require_once 'components/layout_end.php'; ?>
