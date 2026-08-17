<?php
/**
 * report_skippers_print.php — พิมพ์รายงานนักเรียนที่โดดเรียน (รายห้อง/ช่วงวันที่)
 * GET ?classroom=&start_date=&end_date=
 * Role: super_admin
 */
require_once 'functions.php';
checkLogin();

if ($_SESSION['llw_role'] !== 'super_admin') {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}

$classroom  = trim($_GET['classroom']  ?? '');
$start_date = trim($_GET['start_date'] ?? date('Y-m-01'));
$end_date   = trim($_GET['end_date']   ?? date('Y-m-t'));

$skippers = [];
if ($classroom !== '') {
    $q = $pdo->prepare("
        SELECT s.student_id, s.name, sub.subject_code, COUNT(*) AS skip_count
        FROM att_attendance a
        JOIN att_students s   ON s.id   = a.student_id
        JOIN att_subjects sub ON sub.id = a.subject_id
        WHERE a.status = 'โดด' AND s.classroom = ? AND a.date BETWEEN ? AND ?
        GROUP BY s.id, sub.id
        ORDER BY s.student_id, sub.subject_code
    ");
    $q->execute([$classroom, $start_date, $end_date]);

    $byStudent = [];
    foreach ($q->fetchAll() as $r) {
        $sid = $r['student_id'];
        if (!isset($byStudent[$sid])) {
            $byStudent[$sid] = ['student_id' => $sid, 'name' => $r['name'], 'total' => 0, 'subjects' => []];
        }
        $byStudent[$sid]['total']      += (int)$r['skip_count'];
        $byStudent[$sid]['subjects'][]  = $r['subject_code'] . ' (' . $r['skip_count'] . ')';
    }
    $skippers = array_values($byStudent);
    usort($skippers, fn($a, $b) => $b['total'] <=> $a['total']);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รายงานนักเรียนที่โดดเรียน - <?= htmlspecialchars($classroom) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { font-family: 'Prompt', sans-serif; background: #f1f5f9; color: #1e293b; }
@media print {
    body { background: white; padding: 0; margin: 0; }
    .no-print { display: none !important; }
    .print-area { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: none !important; border: none !important; box-shadow: none !important; }
}
</style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-3xl mx-auto mb-6 no-print flex justify-between items-center">
        <a href="javascript:window.close()" class="text-slate-500 font-bold flex items-center gap-2 hover:text-slate-800 transition-all">
            <i class="bi bi-arrow-left"></i> กลับ
        </a>
        <button onclick="window.print()" class="bg-purple-600 text-white px-6 py-3 rounded-2xl font-bold shadow-lg hover:bg-purple-700 hover:scale-[1.02] transition-all flex items-center gap-2">
            <i class="bi bi-printer-fill"></i> ปริ้นรายงาน
        </button>
    </div>

    <div class="max-w-3xl mx-auto bg-white rounded-[2rem] shadow-2xl border border-slate-100 print-area p-8 md:p-12">
        <header class="flex justify-between items-start mb-10 border-b-2 border-slate-100 pb-8">
            <div>
                <div class="inline-flex items-center gap-2 bg-purple-50 text-purple-700 px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest mb-4">
                    <i class="bi bi-exclamation-triangle-fill"></i> รายงานนักเรียนที่โดดเรียน
                </div>
                <h2 class="text-2xl font-black text-slate-800">ห้อง <?= htmlspecialchars($classroom) ?></h2>
                <p class="text-xs text-slate-400 font-bold mt-1">
                    <?= date('d/m/Y', strtotime($start_date)) ?> – <?= date('d/m/Y', strtotime($end_date)) ?>
                </p>
            </div>
            <div class="text-right">
                <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-1">จำนวนคน</p>
                <p class="text-3xl font-black text-purple-600"><?= count($skippers) ?></p>
            </div>
        </header>

        <?php if (empty($skippers)): ?>
        <div class="py-20 text-center text-slate-300 font-bold">
            <i class="bi bi-emoji-smile text-5xl block mb-3 opacity-50"></i>
            ไม่มีนักเรียนโดดเรียนในช่วงเวลานี้
        </div>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b-2 border-slate-100">
                    <th class="py-3 text-xs font-black text-slate-400 uppercase tracking-widest w-12">#</th>
                    <th class="py-3 text-xs font-black text-slate-400 uppercase tracking-widest">รหัส</th>
                    <th class="py-3 text-xs font-black text-slate-400 uppercase tracking-widest">ชื่อ–สกุล</th>
                    <th class="py-3 text-xs font-black text-purple-600 uppercase tracking-widest text-center">โดดรวม</th>
                    <th class="py-3 text-xs font-black text-slate-400 uppercase tracking-widest">โดดวิชาไหนบ้าง</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($skippers as $i => $sk): ?>
                <tr>
                    <td class="py-3 text-slate-400 font-bold"><?= $i + 1 ?></td>
                    <td class="py-3 font-mono font-bold text-blue-600"><?= htmlspecialchars($sk['student_id']) ?></td>
                    <td class="py-3 font-bold text-slate-700"><?= htmlspecialchars($sk['name']) ?></td>
                    <td class="py-3 text-center font-black text-purple-600"><?= $sk['total'] ?></td>
                    <td class="py-3 text-slate-500"><?= htmlspecialchars(implode(', ', $sk['subjects'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <footer class="mt-16 pt-10 border-t border-slate-100 flex justify-end gap-10">
            <div class="w-48 text-center">
                <div class="border-b border-slate-300 h-10 mb-2"></div>
                <p class="text-xs font-black text-slate-400 uppercase">ฝ่ายปกครอง</p>
            </div>
        </footer>
    </div>

</body>
</html>
