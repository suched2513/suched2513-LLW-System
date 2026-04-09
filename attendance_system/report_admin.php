<?php
/**
 * report_admin.php — Executive Dashboard: สถิติการเข้าเรียนสำหรับผู้บริหาร
 * Role: super_admin, wfh_admin
 */
require_once 'functions.php';
checkLogin();

// Only admin roles
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pageTitle    = 'รายงานผู้บริหาร';
$pageSubtitle = 'สถิติการเข้าเรียนแยกห้อง/วิชา';
$activeSystem = 'attendance';

// ดึง classrooms ที่มีในระบบ
$classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

$selected_class   = $_GET['classroom']   ?? ($classrooms[0] ?? '');
$start_date       = $_GET['start_date']  ?? date('Y-m-01');
$end_date         = $_GET['end_date']    ?? date('Y-m-t');

$students      = [];
$subjects      = [];
$kpi           = ['total'=>0,'avg_rate'=>0,'ms_count'=>0,'present_today'=>0];
$chart_labels  = [];
$chart_rates   = [];
$chart_colors  = [];

if ($selected_class) {

    // --- ดึงรายชื่อนักเรียน ---
    $st = $pdo->prepare("SELECT * FROM att_students WHERE classroom=? ORDER BY student_id");
    $st->execute([$selected_class]);
    $students = $st->fetchAll();

    // --- ดึงรายวิชาของห้องนี้ ---
    $sj = $pdo->prepare("SELECT * FROM att_subjects WHERE classroom=? ORDER BY subject_code");
    $sj->execute([$selected_class]);
    $subjects = $sj->fetchAll();

    // --- สร้าง matrix: student x subject ---
    $matrix = [];   // [student_id][subject_id] => ['come'=>0,'absent'=>0,...,'total'=>0]
    $subj_sessions = [];  // [subject_id] => total sessions

    foreach ($subjects as $sub) {
        $s = $pdo->prepare("SELECT COUNT(DISTINCT CONCAT(date,'_',period)) FROM att_attendance WHERE subject_id=? AND date BETWEEN ? AND ?");
        $s->execute([$sub['id'], $start_date, $end_date]);
        $subj_sessions[$sub['id']] = (int)$s->fetchColumn();
    }

    if (!empty($students) && !empty($subjects)) {
        $student_ids = array_column($students, 'id');
        $subject_ids = array_column($subjects, 'id');

        $placeholders_std  = implode(',', array_fill(0, count($student_ids), '?'));
        $placeholders_sub  = implode(',', array_fill(0, count($subject_ids), '?'));
        $params = array_merge($student_ids, $subject_ids, [$start_date, $end_date]);

        $q = $pdo->prepare("
            SELECT student_id, subject_id, status, COUNT(*) as cnt
            FROM att_attendance
            WHERE student_id IN ($placeholders_std)
              AND subject_id IN ($placeholders_sub)
              AND date BETWEEN ? AND ?
            GROUP BY student_id, subject_id, status
        ");
        $q->execute($params);
        $rows = $q->fetchAll();

        foreach ($rows as $r) {
            $matrix[$r['student_id']][$r['subject_id']][$r['status']] = $r['cnt'];
        }
    }

    // --- คำนวณ KPI ---
    $total_rate = 0; $ms_count = 0;
    foreach ($students as $stu) {
        $s_id = $stu['id'];
        $total_come = 0; $total_possible = 0;
        foreach ($subjects as $sub) {
            $come = $matrix[$s_id][$sub['id']]['มา'] ?? 0;
            $sess = $subj_sessions[$sub['id']];
            $total_come     += $come;
            $total_possible += $sess;
        }
        $rate = $total_possible > 0 ? round(($total_come / $total_possible) * 100, 1) : 0;
        $total_rate += $rate;
        if ($total_possible > 0 && $rate < 80) $ms_count++;

        // Chart data
        $chart_labels[] = $stu['student_id'];
        $chart_rates[]  = $rate;
        $chart_colors[] = $rate >= 80 ? 'rgba(16,185,129,0.8)' : ($rate >= 60 ? 'rgba(245,158,11,0.8)' : 'rgba(239,68,68,0.8)');
    }
    $kpi = [
        'total'    => count($students),
        'avg_rate' => count($students) > 0 ? round($total_rate / count($students), 1) : 0,
        'ms_count' => $ms_count,
        'subjects' => count($subjects),
    ];
}

// ── CSV Export (ต้องทำก่อน output ใดๆ) ──
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $selected_class && !empty($students)) {
    $filename = "Attendance_{$selected_class}_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    $hdr = ['รหัส', 'ชื่อ-สกุล'];
    foreach ($subjects as $s) $hdr[] = $s['subject_code'];
    $hdr[] = 'รวม %'; $hdr[] = 'มส.';
    fputcsv($out, $hdr);
    foreach ($students as $stu) {
        $row = [$stu['student_id'], $stu['name']];
        $tc=0; $tp=0;
        foreach ($subjects as $sub) {
            $c = $matrix[$stu['id']][$sub['id']]['มา'] ?? 0;
            $ss = $subj_sessions[$sub['id']];
            $row[] = $ss > 0 ? round(($c/$ss)*100,0).'%' : '-';
            $tc+=$c; $tp+=$ss;
        }
        $or = $tp>0 ? round(($tc/$tp)*100,1) : 0;
        $row[] = $or.'%';
        $row[] = ($tp>0 && $or<80) ? 'มส.' : 'ผ่าน';
        fputcsv($out, $row);
    }
    fclose($out); exit();
}

require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-6">

    <!-- ── Filter Bar ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">ห้องเรียน</label>
                <select name="classroom" onchange="this.form.submit()"
                        class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-indigo-400 outline-none transition-all pr-10">
                    <?php foreach ($classrooms as $cls): ?>
                    <option value="<?= htmlspecialchars($cls) ?>" <?= $selected_class === $cls ? 'selected' : '' ?>><?= htmlspecialchars($cls) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">ตั้งแต่</label>
                <input type="date" name="start_date" value="<?= $start_date ?>"
                       class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition-all">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">ถึง</label>
                <input type="date" name="end_date" value="<?= $end_date ?>"
                       class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition-all">
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-100 flex items-center gap-2">
                <i class="bi bi-bar-chart-fill"></i> แสดงรายงาน
            </button>
            <?php if ($selected_class): ?>
            <a href="report_admin.php?classroom=<?= urlencode($selected_class) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=csv"
               class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-5 py-2.5 rounded-xl font-bold hover:bg-emerald-100 transition text-sm flex items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i> Export CSV
            </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($selected_class && !empty($students)): ?>

    <!-- ── KPI Cards ── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-2xl p-5 text-white shadow-xl shadow-indigo-200/50">
            <p class="text-[10px] font-black opacity-70 uppercase tracking-widest">นักเรียนทั้งหมด</p>
            <p class="text-4xl font-black mt-2"><?= $kpi['total'] ?></p>
            <p class="text-xs opacity-60 mt-1">ห้อง <?= htmlspecialchars($selected_class) ?></p>
        </div>
        <div class="bg-gradient-to-br from-<?= $kpi['avg_rate'] >= 80 ? 'emerald-500 to-teal-600' : 'amber-500 to-orange-600' ?> rounded-2xl p-5 text-white shadow-xl shadow-emerald-200/50">
            <p class="text-[10px] font-black opacity-70 uppercase tracking-widest">เฉลี่ยมาเรียน</p>
            <p class="text-4xl font-black mt-2"><?= $kpi['avg_rate'] ?>%</p>
            <p class="text-xs opacity-60 mt-1"><?= $kpi['avg_rate'] >= 80 ? 'ผ่านเกณฑ์ภาพรวม' : 'ต่ำกว่าเกณฑ์ 80%' ?></p>
        </div>
        <div class="bg-gradient-to-br from-rose-500 to-pink-700 rounded-2xl p-5 text-white shadow-xl shadow-rose-200/50">
            <p class="text-[10px] font-black opacity-70 uppercase tracking-widest">เสี่ยงติด มส.</p>
            <p class="text-4xl font-black mt-2"><?= $kpi['ms_count'] ?></p>
            <p class="text-xs opacity-60 mt-1">มาเรียน&lt;80%</p>
        </div>
        <div class="bg-gradient-to-br from-slate-600 to-slate-800 rounded-2xl p-5 text-white shadow-xl shadow-slate-200/50">
            <p class="text-[10px] font-black opacity-70 uppercase tracking-widest">จำนวนวิชา</p>
            <p class="text-4xl font-black mt-2"><?= $kpi['subjects'] ?></p>
            <p class="text-xs opacity-60 mt-1">วิชาในห้องนี้</p>
        </div>
    </div>

    <!-- ── Chart ── -->
    <?php if (!empty($chart_labels)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h3 class="font-black text-slate-800 mb-1">กราฟ % มาเรียนรายบุคคล</h3>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-5">ห้อง <?= htmlspecialchars($selected_class) ?> · สีเขียว = ≥80% · สีส้ม = 60-79% · สีแดง = &lt;60%</p>
        <div style="max-height:280px">
            <canvas id="classChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Student x Subject Matrix Table ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-black text-slate-800">ตารางสถิติรายบุคคล × รายวิชา</h3>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">ห้อง <?= htmlspecialchars($selected_class) ?> · <?= count($students) ?> คน · <?= count($subjects) ?> วิชา</p>
            </div>
            <?php if ($kpi['ms_count'] > 0): ?>
            <span class="flex items-center gap-2 bg-rose-50 text-rose-600 border border-rose-200 px-4 py-2 rounded-xl text-xs font-black">
                <i class="bi bi-exclamation-triangle-fill"></i> เสี่ยงติด มส. <?= $kpi['ms_count'] ?> คน
            </span>
            <?php endif; ?>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-5 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest sticky left-0 bg-slate-50 z-10 min-w-[180px]">นักเรียน</th>
                        <?php foreach ($subjects as $sub): ?>
                        <th class="px-3 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">
                            <?= htmlspecialchars($sub['subject_code']) ?><br>
                            <span class="text-[9px] normal-case font-medium opacity-60"><?= mb_substr($sub['subject_name'],0,12) ?>…</span>
                        </th>
                        <?php endforeach; ?>
                        <th class="px-5 py-4 text-center text-[10px] font-black text-indigo-500 uppercase tracking-widest">รวม %</th>
                        <th class="px-3 py-4 text-center text-[10px] font-black text-rose-600 uppercase tracking-widest">มส.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($students as $stu):
                        $s_id = $stu['id'];
                        $total_come = 0; $total_possible = 0;
                        foreach ($subjects as $sub) {
                            $total_come     += $matrix[$s_id][$sub['id']]['มา'] ?? 0;
                            $total_possible += $subj_sessions[$sub['id']];
                        }
                        $overall_rate = $total_possible > 0 ? round(($total_come / $total_possible) * 100, 1) : 0;
                        $is_ms = $total_possible > 0 && $overall_rate < 80;
                        $orc   = $overall_rate >= 80 ? 'emerald' : ($overall_rate >= 60 ? 'amber' : 'rose');
                    ?>
                    <tr class="hover:bg-slate-50 transition <?= $is_ms ? 'bg-rose-50/30' : '' ?>">
                        <td class="px-5 py-3.5 sticky left-0 bg-white hover:bg-slate-50 z-10 border-r border-slate-50">
                            <div class="flex flex-col">
                                <span class="font-mono font-bold text-blue-600 text-[10px]"><?= $stu['student_id'] ?></span>
                                <span class="font-bold text-slate-700 <?= $is_ms ? 'text-rose-700' : '' ?>"><?= htmlspecialchars($stu['name']) ?></span>
                            </div>
                        </td>
                        <?php foreach ($subjects as $sub):
                            $come   = $matrix[$s_id][$sub['id']]['มา']   ?? 0;
                            $absent = $matrix[$s_id][$sub['id']]['ขาด'] ?? 0;
                            $skip   = $matrix[$s_id][$sub['id']]['โดด'] ?? 0;
                            $sess   = $subj_sessions[$sub['id']];
                            $r      = $sess > 0 ? round(($come / $sess) * 100, 0) : 0;
                            $tc     = $r >= 80 ? 'text-emerald-600 bg-emerald-50' : ($r >= 60 ? 'text-amber-600 bg-amber-50' : 'text-rose-600 bg-rose-50');
                        ?>
                        <td class="px-3 py-3.5 text-center">
                            <?php if ($sess > 0): ?>
                            <div class="inline-flex flex-col items-center gap-1">
                                <span class="font-black <?= $tc ?> rounded-lg px-2.5 py-1"><?= $r ?>%</span>
                                <span class="text-[9px] text-slate-400"><?= $come ?>/<?= $sess ?></span>
                            </div>
                            <?php else: ?>
                            <span class="text-slate-200 font-bold">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <!-- Overall -->
                        <td class="px-5 py-3.5 text-center">
                            <div class="flex flex-col items-center gap-1">
                                <span class="font-black text-<?= $orc ?>-600 text-sm"><?= $overall_rate ?>%</span>
                                <div class="w-14 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-<?= $orc ?>-500 rounded-full" style="width:<?= min($overall_rate,100) ?>%"></div>
                                </div>
                            </div>
                        </td>
                        <!-- มส. -->
                        <td class="px-3 py-3.5 text-center">
                            <?php if ($is_ms): ?>
                            <span class="inline-flex items-center gap-1 bg-rose-600 text-white px-2.5 py-1 rounded-xl text-[10px] font-black shadow-sm">
                                <i class="bi bi-x-circle-fill"></i> มส.
                            </span>
                            <?php elseif ($total_possible > 0): ?>
                            <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-600 border border-emerald-100 px-2.5 py-1 rounded-xl text-[10px] font-black">
                                <i class="bi bi-check-circle-fill"></i> ผ่าน
                            </span>
                            <?php else: ?>
                            <span class="text-slate-200 text-[10px] font-bold">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($selected_class): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center text-amber-800">
        <i class="bi bi-inbox text-4xl block mb-3 opacity-50"></i>
        <p class="font-black">ยังไม่มีนักเรียนในห้อง <?= htmlspecialchars($selected_class) ?></p>
        <p class="text-sm mt-1">กรุณาเพิ่มนักเรียนในหน้าจัดการข้อมูลก่อนครับ</p>
    </div>
    <?php elseif (empty($classrooms)): ?>
    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-8 text-center text-slate-500">
        <i class="bi bi-people text-4xl block mb-3 opacity-40"></i>
        <p class="font-black">ยังไม่มีข้อมูลนักเรียนในระบบ</p>
    </div>
    <?php endif; ?>

</div>

<?php if (!empty($chart_labels)): ?>
<script>
const ctx = document.getElementById('classChart')?.getContext('2d');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: '% มาเรียน',
                data: <?= json_encode($chart_rates) ?>,
                backgroundColor: <?= json_encode($chart_colors) ?>,
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 10, cornerRadius: 10,
                    callbacks: { label: c => `มาเรียน ${c.raw}%` }
                }
            },
            scales: {
                y: {
                    beginAtZero: true, max: 100,
                    grid: { color: '#f1f5f9' },
                    ticks: { callback: v => v + '%', font: { weight: 'bold' } }
                },
                x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' } } }
            }
        }
    });

    // Draw 80% reference line
    Chart.register({
        id: 'msLine',
        afterDraw(chart) {
            const { ctx, chartArea: { left, right }, scales: { y } } = chart;
            const y80 = y.getPixelForValue(80);
            ctx.save();
            ctx.beginPath();
            ctx.moveTo(left, y80); ctx.lineTo(right, y80);
            ctx.strokeStyle = 'rgba(239,68,68,0.6)';
            ctx.lineWidth = 2;
            ctx.setLineDash([6, 4]);
            ctx.stroke();
            ctx.fillStyle = 'rgba(239,68,68,0.8)';
            ctx.font = 'bold 10px Prompt, sans-serif';
            ctx.fillText('เกณฑ์ 80%', right - 65, y80 - 5);
            ctx.restore();
        }
    });
}
</script>
<?php endif; ?>


<?php require_once '../components/layout_end.php'; ?>
