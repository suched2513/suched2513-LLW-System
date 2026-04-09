<?php
/**
 * export_elective.php — หน้าค้นหา + Export รายชื่อผู้ลงทะเบียนวิชาเลือก
 */
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();

// ── Fetch filter options ───────────────────────────────────────
$elective_subjects = $pdo->query("
    SELECT s.id, s.subject_code, s.subject_name, s.classroom, t.name as teacher_name,
           COUNT(ss.id) as enrolled_count
    FROM att_subjects s
    JOIN att_teachers t ON t.id = s.teacher_id
    LEFT JOIN att_subject_students ss ON ss.subject_id = s.id
    WHERE s.is_elective = 1
    GROUP BY s.id ORDER BY s.subject_code
")->fetchAll();

$classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

// ── Filters ────────────────────────────────────────────────────
$f_subject  = (int)($_GET['subject_id'] ?? 0);
$f_cls      = trim($_GET['cls'] ?? '');
$f_name     = trim($_GET['name'] ?? '');
$do_export  = isset($_GET['export']);

// ── Build query ────────────────────────────────────────────────
$where  = ["1=1"];
$params = [];

if ($f_subject) {
    $where[] = "ss.subject_id = :subj";
    $params[':subj'] = $f_subject;
}
if ($f_cls) {
    $where[] = "st.classroom = :cls";
    $params[':cls'] = $f_cls;
}
if ($f_name) {
    $where[] = "(st.name LIKE :nm OR st.student_id LIKE :nm2)";
    $params[':nm']  = '%'.$f_name.'%';
    $params[':nm2'] = '%'.$f_name.'%';
}

$sql = "
    SELECT st.student_id, st.name as student_name, st.classroom,
           sj.subject_code, sj.subject_name, t.name as teacher_name,
           ss.created_at
    FROM att_subject_students ss
    JOIN att_students st ON st.id = ss.student_id
    JOIN att_subjects sj ON sj.id = ss.subject_id
    JOIN att_teachers t  ON t.id  = sj.teacher_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY sj.subject_code, st.classroom, st.student_id
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ── CSV Export ─────────────────────────────────────────────────
if ($do_export) {
    $parts = [];
    if ($f_subject) {
        foreach ($elective_subjects as $es) {
            if ($es['id'] == $f_subject) { $parts[] = $es['subject_code']; break; }
        }
    }
    if ($f_cls)  $parts[] = $f_cls;
    $fname = 'elective_enrollment_' . (empty($parts) ? 'all' : implode('_', $parts)) . '_' . date('Ymd') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['รหัสนักเรียน','ชื่อ-สกุล','ห้องเรียน','รหัสวิชา','ชื่อวิชา','ครูผู้สอน','วันที่ลงทะเบียน']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['student_id'], $r['student_name'], $r['classroom'], $r['subject_code'], $r['subject_name'], $r['teacher_name'], date('d/m/Y', strtotime($r['created_at']))]);
    }
    fclose($out); exit;
}

// ── Layout ─────────────────────────────────────────────────────
$pageTitle    = 'Export วิชาเลือก';
$pageSubtitle = 'ค้นหาและ Export รายชื่อนักเรียนที่ลงทะเบียนวิชาเลือก';
$activeSystem = 'attendance';
require_once '../components/layout_start.php';
?>

<div class="flex flex-col gap-6">

    <!-- ── Filter Card ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h2 class="font-black text-slate-800 mb-5 flex items-center gap-2">
            <div class="w-9 h-9 bg-violet-100 text-violet-600 rounded-xl flex items-center justify-center"><i class="bi bi-funnel-fill"></i></div>
            ตัวกรองข้อมูล
        </h2>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- วิชา -->
            <div>
                <label class="block text-xs font-black text-slate-400 uppercase tracking-wider mb-2">วิชาเลือก</label>
                <select name="subject_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none">
                    <option value="">— ทุกวิชา —</option>
                    <?php foreach ($elective_subjects as $es): ?>
                    <option value="<?= $es['id'] ?>" <?= $f_subject == $es['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($es['subject_code']) ?> — <?= htmlspecialchars($es['subject_name']) ?>
                        (<?= $es['enrolled_count'] ?> คน)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ห้อง -->
            <div>
                <label class="block text-xs font-black text-slate-400 uppercase tracking-wider mb-2">ห้องเรียน</label>
                <select name="cls" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none">
                    <option value="">— ทุกห้อง —</option>
                    <?php foreach ($classrooms as $cls): ?>
                    <option value="<?= htmlspecialchars($cls) ?>" <?= $f_cls === $cls ? 'selected' : '' ?>><?= htmlspecialchars($cls) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ชื่อ/รหัส -->
            <div>
                <label class="block text-xs font-black text-slate-400 uppercase tracking-wider mb-2">ค้นหานักเรียน</label>
                <div class="relative">
                    <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="name" value="<?= htmlspecialchars($f_name) ?>"
                           placeholder="ชื่อ หรือ รหัสนักเรียน"
                           class="w-full pl-10 pr-4 bg-slate-50 border border-slate-200 rounded-xl py-2.5 text-sm font-bold focus:ring-2 focus:ring-violet-400 outline-none">
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-violet-600 text-white py-2.5 rounded-xl font-black text-sm shadow-lg shadow-violet-100 hover:bg-violet-700 transition flex items-center justify-center gap-2">
                    <i class="bi bi-search"></i> ค้นหา
                </button>
                <?php if ($f_subject || $f_cls || $f_name): ?>
                <a href="export_elective.php" class="px-3 py-2.5 rounded-xl border border-slate-200 text-slate-400 hover:bg-slate-50 transition text-sm font-bold" title="ล้างตัวกรอง">
                    <i class="bi bi-x-lg"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ── Result Header ── -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <p class="text-sm font-black text-slate-600">
                พบ <span class="text-violet-600 text-lg"><?= count($rows) ?></span> รายการ
                <?php if ($f_subject || $f_cls || $f_name): ?>
                <span class="text-slate-400 font-bold text-xs ml-2">(กรองแล้ว)</span>
                <?php endif; ?>
            </p>
        </div>
        <?php if (!empty($rows)): ?>
        <a href="?<?= http_build_query(array_filter(['subject_id' => $f_subject, 'cls' => $f_cls, 'name' => $f_name])) ?>&export=1"
           class="inline-flex items-center gap-2 bg-emerald-600 text-white px-5 py-2.5 rounded-xl font-black text-sm shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition">
            <i class="bi bi-file-earmark-spreadsheet-fill"></i>
            Download CSV (<?= count($rows) ?> แถว)
        </a>
        <?php endif; ?>
    </div>

    <!-- ── Table ── -->
    <?php if (empty($rows)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-16 text-center">
        <i class="bi bi-inbox text-5xl text-slate-300 block mb-3"></i>
        <p class="font-black text-slate-400">ไม่พบข้อมูล<?= ($f_subject || $f_cls || $f_name) ? 'ตามเงื่อนไขที่กรอง' : '' ?></p>
        <?php if ($f_subject || $f_cls || $f_name): ?>
        <a href="export_elective.php" class="text-violet-500 text-sm font-bold mt-2 inline-block hover:underline">ล้างตัวกรอง</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-50 text-sm">
                <thead class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    <tr>
                        <th class="px-5 py-4 text-left">#</th>
                        <th class="px-5 py-4 text-left">รหัส</th>
                        <th class="px-5 py-4 text-left">ชื่อ-สกุล</th>
                        <th class="px-5 py-4 text-left">ห้อง</th>
                        <th class="px-5 py-4 text-left">วิชา</th>
                        <th class="px-5 py-4 text-left">ครูผู้สอน</th>
                        <th class="px-5 py-4 text-left">วันที่ลง</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($rows as $i => $r): ?>
                    <tr class="hover:bg-violet-50/30 transition">
                        <td class="px-5 py-3.5 text-slate-300 font-bold text-xs"><?= $i + 1 ?></td>
                        <td class="px-5 py-3.5 font-mono font-black text-blue-600 text-xs"><?= htmlspecialchars($r['student_id']) ?></td>
                        <td class="px-5 py-3.5 font-bold text-slate-700"><?= htmlspecialchars($r['student_name']) ?></td>
                        <td class="px-5 py-3.5">
                            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 font-bold text-[10px] rounded-lg"><?= htmlspecialchars($r['classroom']) ?></span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="font-mono text-[10px] text-violet-500 font-black"><?= htmlspecialchars($r['subject_code']) ?></span>
                            <p class="text-xs text-slate-600 font-bold mt-0.5"><?= htmlspecialchars($r['subject_name']) ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-slate-500 font-bold"><?= htmlspecialchars($r['teacher_name']) ?></td>
                        <td class="px-5 py-3.5 text-xs text-slate-400 font-bold"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Table Footer -->
        <div class="px-5 py-3 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs font-bold text-slate-400">แสดง <?= count($rows) ?> รายการ</p>
            <a href="?<?= http_build_query(array_filter(['subject_id' => $f_subject, 'cls' => $f_cls, 'name' => $f_name])) ?>&export=1"
               class="inline-flex items-center gap-1.5 text-xs font-black text-emerald-600 hover:text-emerald-700 transition">
                <i class="bi bi-download"></i> Download CSV
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once '../components/layout_end.php'; ?>
