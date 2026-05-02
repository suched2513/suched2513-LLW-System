<?php
/**
 * report_ms.php — หน้าสรุปนักเรียนติด มส. ทั้งโรงเรียน (ส่งฝ่ายวิชาการ)
 * Role: super_admin, wfh_admin
 */
session_start();
require_once 'functions.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}

$pdo = getPdo();

// ── Filters ────────────────────────────────────────────────────
$filter_cls = trim($_GET['cls'] ?? '');

// ── Export CSV ─────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $where = $filter_cls ? "AND st.classroom = :cls" : "";
    $params = $filter_cls ? [':cls' => $filter_cls] : [];

    $sql = "
        SELECT
            st.classroom, st.student_id, st.name as student_name,
            sj.subject_code, sj.subject_name,
            COUNT(DISTINCT CONCAT(a.date,'_',a.period)) as my_sessions,
            (SELECT COUNT(DISTINCT CONCAT(date,'_',period)) FROM att_attendance WHERE subject_id=sj.id) as total_sessions,
            SUM(CASE WHEN a.status='มา' THEN 1 ELSE 0 END) as cnt_come
        FROM att_students st
        JOIN att_attendance a ON a.student_id = st.id
        JOIN att_subjects sj ON sj.id = a.subject_id
        WHERE 1=1 $where
        GROUP BY st.id, sj.id
        HAVING total_sessions > 0
            AND ROUND((cnt_come / total_sessions) * 100, 1) < 80
        ORDER BY st.classroom, st.student_id, sj.subject_code
    ";
    $s = $pdo->prepare($sql); $s->execute($params); $ms_rows = $s->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ms_summary_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ห้องเรียน','รหัสนักเรียน','ชื่อ-สกุล','รหัสวิชา','ชื่อวิชา','คาบที่เข้าเรียน','คาบทั้งหมด','% เข้าเรียน','ขาดอีก(คาบ)']);
    foreach ($ms_rows as $r) {
        $rate = round(($r['cnt_come'] / $r['total_sessions']) * 100, 1);
        $need = max(0, ceil($r['total_sessions'] * 0.8) - $r['cnt_come']);
        fputcsv($out, [$r['classroom'], $r['student_id'], $r['student_name'], $r['subject_code'], $r['subject_name'], $r['my_sessions'], $r['total_sessions'], $rate.'%', $need]);
    }
    fclose($out); exit;
}

// ── Data Fetching ───────────────────────────────────────────────
$classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

$where  = $filter_cls ? "AND st.classroom = :cls" : "";
$params = $filter_cls ? [':cls' => $filter_cls] : [];

// นักเรียนที่ติด มส. อย่างน้อย 1 วิชา
$sql = "
    SELECT
        st.id as std_id, st.classroom, st.student_id, st.name as student_name,
        sj.id as subj_id, sj.subject_code, sj.subject_name,
        COUNT(DISTINCT CONCAT(a.date,'_',a.period)) as my_sessions,
        (SELECT COUNT(DISTINCT CONCAT(date,'_',period)) FROM att_attendance WHERE subject_id=sj.id) as total_sessions,
        SUM(CASE WHEN a.status='มา' THEN 1 ELSE 0 END) as cnt_come
    FROM att_students st
    JOIN att_attendance a ON a.student_id = st.id
    JOIN att_subjects sj ON sj.id = a.subject_id
    WHERE 1=1 $where
    GROUP BY st.id, sj.id
    HAVING total_sessions > 0
        AND ROUND((cnt_come / total_sessions) * 100, 1) < 80
    ORDER BY st.classroom, st.student_id, sj.subject_code
";
$s = $pdo->prepare($sql); $s->execute($params); $ms_rows = $s->fetchAll();

// จัดกลุ่มตามห้อง → นักเรียน → วิชา
$grouped = [];
foreach ($ms_rows as $r) {
    $grouped[$r['classroom']][$r['std_id']]['info'] = [
        'student_id' => $r['student_id'],
        'name'       => $r['student_name'],
        'classroom'  => $r['classroom'],
    ];
    $rate = round(($r['cnt_come'] / $r['total_sessions']) * 100, 1);
    $grouped[$r['classroom']][$r['std_id']]['subjects'][] = [
        'code'    => $r['subject_code'],
        'name'    => $r['subject_name'],
        'come'    => $r['cnt_come'],
        'total'   => $r['total_sessions'],
        'rate'    => $rate,
        'need'    => max(0, ceil($r['total_sessions'] * 0.8) - $r['cnt_come']),
    ];
}

// KPIs
$total_ms_students = count($ms_rows ? array_unique(array_column($ms_rows, 'std_id')) : []);
$total_ms_cases    = count($ms_rows);
$ms_by_class       = [];
foreach ($grouped as $cls => $students) { $ms_by_class[$cls] = count($students); }

// Layout
$pageTitle    = 'สรุปนักเรียนติด มส.';
$pageSubtitle = 'รายงานนักเรียนที่มีเวลาเรียนต่ำกว่า 80% ส่งฝ่ายวิชาการ';
$activeSystem = 'attendance';
require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-6">

    <!-- ── Header Actions ── -->
    <div class="flex items-center justify-between flex-wrap gap-4 no-print">
        <div>
            <h2 class="text-2xl font-black text-slate-800 flex items-center gap-2">
                <div class="w-10 h-10 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center text-xl"><i class="bi bi-exclamation-triangle-fill"></i></div>
                สรุปนักเรียนที่อาจติด มส.
            </h2>
            <p class="text-sm text-slate-400 font-bold mt-1">เวลาเรียนต่ำกว่า 80% — ข้อมูล ณ <?= date('d/m/Y H:i') ?></p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <!-- Export CSV -->
            <a href="?<?= $filter_cls ? 'cls='.urlencode($filter_cls).'&' : '' ?>export=1"
               class="inline-flex items-center gap-2 bg-emerald-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i> Export CSV
            </a>
            <button onclick="window.print()"
               class="inline-flex items-center gap-2 bg-slate-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg hover:bg-slate-800 transition">
                <i class="bi bi-printer-fill"></i> พิมพ์
            </button>
        </div>
    </div>

    <!-- ── Filter ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 no-print">
        <form method="GET" class="flex items-center gap-3 flex-wrap">
            <label class="text-xs font-black text-slate-400 uppercase tracking-wider">กรองห้อง:</label>
            <select name="cls" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold focus:ring-2 focus:ring-rose-400 outline-none">
                <option value="">ทุกห้อง</option>
                <?php foreach ($classrooms as $cls): ?>
                <option value="<?= htmlspecialchars($cls) ?>" <?= $filter_cls === $cls ? 'selected' : '' ?>><?= htmlspecialchars($cls) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filter_cls): ?>
            <a href="report_ms.php" class="text-xs font-bold text-rose-500 hover:text-rose-700 transition">✕ ล้างตัวกรอง</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── KPI Cards ── -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-gradient-to-br from-rose-500 to-rose-700 rounded-2xl p-6 text-white shadow-xl shadow-rose-200/50">
            <p class="text-xs font-black opacity-80 uppercase tracking-widest">นักเรียน<?= $filter_cls ? "ห้อง $filter_cls" : 'ทั้งหมด' ?>ที่อาจติด มส.</p>
            <p class="text-5xl font-black mt-2"><?= count(array_unique(array_column($ms_rows, 'std_id'))) ?></p>
            <p class="text-xs opacity-70 mt-1">คน</p>
        </div>
        <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-6 text-white shadow-xl shadow-amber-200/50">
            <p class="text-xs font-black opacity-80 uppercase tracking-widest">รายการวิชาที่ติด มส.</p>
            <p class="text-5xl font-black mt-2"><?= $total_ms_cases ?></p>
            <p class="text-xs opacity-70 mt-1">รายการ</p>
        </div>
        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-6 text-white shadow-xl shadow-indigo-200/50">
            <p class="text-xs font-black opacity-80 uppercase tracking-widest">จำนวนห้องที่มีปัญหา</p>
            <p class="text-5xl font-black mt-2"><?= count($ms_by_class) ?></p>
            <p class="text-xs opacity-70 mt-1">ห้อง</p>
        </div>
    </div>

    <?php if (empty($grouped)): ?>
    <!-- Empty -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-16 text-center">
        <i class="bi bi-emoji-smile text-6xl text-emerald-400 block mb-4"></i>
        <p class="font-black text-slate-600 text-xl">ไม่มีนักเรียนติด มส.</p>
        <p class="text-sm text-slate-400 mt-2">นักเรียน<?= $filter_cls ? "ห้อง $filter_cls" : 'ทั้งหมด' ?>มีเวลาเรียนผ่านเกณฑ์ 80% ✅</p>
    </div>

    <?php else: ?>

    <!-- ── Summary by Classroom (Quick Overview) ── -->
    <?php if (!$filter_cls && count($ms_by_class) > 1): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden no-print">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-bar-chart-fill text-rose-500"></i>
            <h3 class="font-black text-slate-800">สรุปแยกตามห้อง</h3>
        </div>
        <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
            <?php foreach ($ms_by_class as $cls => $cnt): ?>
            <a href="?cls=<?= urlencode($cls) ?>" class="flex items-center justify-between bg-rose-50 hover:bg-rose-100 border border-rose-100 rounded-xl px-4 py-3 transition group">
                <span class="font-black text-slate-700 text-sm"><?= htmlspecialchars($cls) ?></span>
                <span class="px-2.5 py-1 bg-rose-500 text-white font-black text-xs rounded-lg group-hover:bg-rose-600"><?= $cnt ?> คน</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Detail by Classroom ── -->
    <?php foreach ($grouped as $cls => $students): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <!-- Class Header -->
        <div class="px-6 py-4 bg-rose-50 border-b border-rose-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-rose-100 text-rose-600 rounded-xl flex items-center justify-center font-black text-sm"><?= substr($cls, -3) ?></div>
                <div>
                    <h3 class="font-black text-slate-800">ห้อง <?= htmlspecialchars($cls) ?></h3>
                    <p class="text-xs text-rose-500 font-black uppercase tracking-widest">นักเรียนอาจติด มส. <?= count($students) ?> คน</p>
                </div>
            </div>
        </div>

        <!-- Students -->
        <div class="divide-y divide-slate-50">
            <?php foreach ($students as $std_id => $std_data): ?>
            <?php $info = $std_data['info']; $subjs = $std_data['subjects']; ?>
            <div class="px-6 py-5">
                <div class="flex items-start gap-4">
                    <!-- Avatar -->
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-400 to-pink-500 flex items-center justify-center text-white font-black text-sm flex-shrink-0">
                        <?= mb_substr($info['name'], 0, 1) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-black text-slate-800"><?= htmlspecialchars($info['name']) ?></p>
                            <span class="font-mono text-xs text-blue-500 font-bold"><?= $info['student_id'] ?></span>
                            <span class="px-2 py-0.5 bg-rose-100 text-rose-600 text-xs font-black rounded-lg"><?= count($subjs) ?> วิชา</span>
                        </div>
                        <!-- Subject list -->
                        <div class="mt-3 grid gap-2">
                            <?php foreach ($subjs as $sv): ?>
                            <div class="flex items-center gap-3 bg-rose-50 rounded-xl px-4 py-2.5">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-black text-rose-400"><?= $sv['code'] ?></span>
                                        <span class="font-bold text-xs text-slate-700 truncate"><?= htmlspecialchars($sv['name']) ?></span>
                                    </div>
                                    <!-- Progress -->
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <div class="flex-1 h-1.5 rounded-full bg-rose-100 overflow-hidden">
                                            <div class="h-full rounded-full bg-rose-500 transition-all" style="width:<?= min($sv['rate'],100) ?>%"></div>
                                        </div>
                                        <span class="text-xs font-black text-rose-600 w-10 text-right"><?= $sv['rate'] ?>%</span>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs font-bold text-slate-400"><?= $sv['come'] ?>/<?= $sv['total'] ?> คาบ</p>
                                    <p class="text-xs font-black text-amber-600">ขาดอีก <?= $sv['need'] ?> คาบ</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    .shadow-xl, .shadow-sm, .shadow-lg { box-shadow: none !important; }
}
</style>

<?php require_once '../components/layout_end.php'; ?>
