<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: ' . $base_path . '/teacher_leave/index.php'); exit();
}

$pdo   = getPdo();
$today = date('Y-m-d');

$dayTh = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
$monTh = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$todayStr = 'วัน' . $dayTh[(int)date('w')] . 'ที่ ' . (int)date('j') . ' ' . $monTh[(int)date('n')] . ' ' . ((int)date('Y') + 543);

$typeMap = [
    'sick'      => ['label' => 'ลาป่วย',      'color' => 'rose',    'icon' => 'fas fa-thermometer-half'],
    'personal'  => ['label' => 'ลากิจ',        'color' => 'amber',   'icon' => 'fas fa-calendar-alt'],
    'vacation'  => ['label' => 'ลาพักผ่อน',   'color' => 'blue',    'icon' => 'fas fa-umbrella-beach'],
    'maternity' => ['label' => 'ลาคลอดบุตร',  'color' => 'pink',    'icon' => 'fas fa-baby'],
    'other'     => ['label' => 'ลาอื่นๆ',      'color' => 'slate',   'icon' => 'fas fa-file-alt'],
];

// ── วันนี้ลา ──
$stmt = $pdo->prepare("
    SELECT r.id, r.leave_type, r.date_start, r.date_end, r.days_count, r.reason,
           u.firstname, u.lastname, u.position
    FROM tl_requests r
    JOIN llw_users u ON r.user_id = u.user_id
    WHERE r.status = 'approved'
      AND ? BETWEEN r.date_start AND r.date_end
    ORDER BY r.leave_type, u.firstname
");
$stmt->execute([$today]);
$today_leavers = $stmt->fetchAll();

// ── KPI counts ──
$pending_staff    = (int)$pdo->query("SELECT COUNT(*) FROM tl_requests WHERE status='pending' AND level_at=1")->fetchColumn();
$pending_director = (int)$pdo->query("SELECT COUNT(*) FROM tl_requests WHERE status='pending' AND level_at=2")->fetchColumn();
$approved_month   = (int)$pdo->query("
    SELECT COUNT(*) FROM tl_requests
    WHERE status='approved'
      AND MONTH(date_start)=MONTH(CURDATE())
      AND YEAR(date_start)=YEAR(CURDATE())
")->fetchColumn();

// ── รายการรอดำเนินการ (สูงสุด 20 รายการ) ──
$pending_stmt = $pdo->query("
    SELECT r.id, r.leave_type, r.date_start, r.date_end, r.days_count, r.level_at, r.created_at,
           u.firstname, u.lastname, u.position
    FROM tl_requests r
    JOIN llw_users u ON r.user_id = u.user_id
    WHERE r.status='pending'
    ORDER BY r.level_at ASC, r.created_at ASC
    LIMIT 20
");
$pending_leaves = $pending_stmt->fetchAll();

// ── สถิติเดือนนี้ตามประเภท ──
$monthly_stmt = $pdo->query("
    SELECT leave_type, COUNT(*) AS cnt, SUM(days_count) AS total_days
    FROM tl_requests
    WHERE status='approved'
      AND MONTH(date_start)=MONTH(CURDATE())
      AND YEAR(date_start)=YEAR(CURDATE())
    GROUP BY leave_type
    ORDER BY cnt DESC
");
$monthly_stats = $monthly_stmt->fetchAll();
$max_count = max(1, ...array_column($monthly_stats, 'cnt'));

// ── 7 วันข้างหน้า ──
$upcoming_stmt = $pdo->prepare("
    SELECT r.leave_type, r.date_start, r.date_end, r.days_count,
           u.firstname, u.lastname
    FROM tl_requests r
    JOIN llw_users u ON r.user_id = u.user_id
    WHERE r.status = 'approved'
      AND r.date_start BETWEEN DATE_ADD(?, INTERVAL 1 DAY) AND DATE_ADD(?, INTERVAL 7 DAY)
    ORDER BY r.date_start ASC
    LIMIT 10
");
$upcoming_stmt->execute([$today, $today]);
$upcoming = $upcoming_stmt->fetchAll();

$pageTitle    = 'ภาพรวมการลา';
$pageSubtitle = 'สรุปสถานะการลาและรายการรอดำเนินการ';
$activeSystem = 'teacher_leave';
require_once '../components/layout_start.php';
?>

<!-- ── Hero Banner ── -->
<div class="rounded-[2.5rem] p-8 mb-6 text-white overflow-hidden relative"
     style="background:linear-gradient(135deg,#e11d48,#be123c)">
    <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 80% 50%,#fff 0%,transparent 60%)"></div>
    <div class="relative flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-xs font-black uppercase tracking-widest opacity-70 mb-1">สรุปการลาประจำวัน</p>
            <h2 class="text-2xl font-black"><?= $todayStr ?></h2>
        </div>
        <div class="text-right">
            <p class="text-xs font-black uppercase tracking-widest opacity-70 mb-1">ขาดราชการวันนี้</p>
            <p class="text-6xl font-black leading-none"><?= count($today_leavers) ?></p>
            <p class="text-sm font-black opacity-70 mt-1">คน</p>
        </div>
    </div>
</div>

<!-- ── KPI Row ── -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-xl flex-shrink-0">
            <i class="fas fa-user-minus"></i>
        </div>
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ลาวันนี้</p>
            <p class="text-3xl font-black text-rose-600 leading-none mt-0.5"><?= count($today_leavers) ?></p>
            <p class="text-[10px] text-slate-400 font-bold">คน</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center text-xl flex-shrink-0">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">รอตรวจสอบ</p>
            <p class="text-3xl font-black <?= $pending_staff > 0 ? 'text-amber-600' : 'text-slate-600' ?> leading-none mt-0.5"><?= $pending_staff ?></p>
            <p class="text-[10px] text-slate-400 font-bold">ใบ</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center text-xl flex-shrink-0">
            <i class="fas fa-user-tie"></i>
        </div>
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">รอ ผอ./รองฯ</p>
            <p class="text-3xl font-black <?= $pending_director > 0 ? 'text-blue-600' : 'text-slate-600' ?> leading-none mt-0.5"><?= $pending_director ?></p>
            <p class="text-[10px] text-slate-400 font-bold">ใบ</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl flex-shrink-0">
            <i class="fas fa-check-circle"></i>
        </div>
        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">อนุมัติเดือนนี้</p>
            <p class="text-3xl font-black text-emerald-600 leading-none mt-0.5"><?= $approved_month ?></p>
            <p class="text-[10px] text-slate-400 font-bold">ใบ</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- ── วันนี้ลา (รายชื่อ) ── -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 flex items-center justify-between border-b border-slate-50 bg-rose-50/30">
            <h3 class="font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-user-minus text-rose-500"></i>
                ผู้ที่ลาวันนี้
                <?php if (count($today_leavers) > 0): ?>
                <span class="px-2 py-0.5 bg-rose-500 text-white text-xs font-black rounded-full"><?= count($today_leavers) ?></span>
                <?php endif; ?>
            </h3>
            <a href="index.php" class="text-xs font-black text-rose-500 hover:underline flex items-center gap-1">
                <i class="fas fa-list"></i> จัดการใบลา
            </a>
        </div>

        <?php if (empty($today_leavers)): ?>
        <div class="p-12 text-center text-slate-300">
            <i class="fas fa-calendar-check text-5xl mb-3 block"></i>
            <p class="font-black text-slate-400">ไม่มีผู้ลาวันนี้</p>
            <p class="text-sm text-slate-300 mt-1">บุคลากรทุกคนมาปฏิบัติงานครบ</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-slate-50">
            <?php foreach ($today_leavers as $lv):
                $tm = $typeMap[$lv['leave_type']] ?? $typeMap['other'];
                $col = $tm['color'];
            ?>
            <div class="px-6 py-4 flex items-center gap-4 hover:bg-slate-50/50 transition-all">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 text-sm
                    <?php if ($col==='rose') echo 'bg-rose-100 text-rose-600';
                    elseif ($col==='amber') echo 'bg-amber-100 text-amber-600';
                    elseif ($col==='blue') echo 'bg-blue-100 text-blue-600';
                    elseif ($col==='pink') echo 'bg-pink-100 text-pink-600';
                    else echo 'bg-slate-100 text-slate-500'; ?>">
                    <i class="<?= $tm['icon'] ?>"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-black text-slate-800 text-sm leading-tight">
                        <?= htmlspecialchars($lv['firstname'] . ' ' . $lv['lastname'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <?php if (!empty($lv['position'])): ?>
                    <p class="text-[10px] text-slate-400 font-bold"><?= htmlspecialchars($lv['position'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="px-2 py-1 text-[10px] font-black rounded-lg
                        <?php if ($col==='rose') echo 'bg-rose-50 text-rose-600';
                        elseif ($col==='amber') echo 'bg-amber-50 text-amber-600';
                        elseif ($col==='blue') echo 'bg-blue-50 text-blue-600';
                        else echo 'bg-slate-50 text-slate-500'; ?>">
                        <?= $tm['label'] ?>
                    </span>
                    <p class="text-[10px] text-slate-400 mt-1">
                        <?= date('d/m', strtotime($lv['date_start'])) ?> – <?= date('d/m/Y', strtotime($lv['date_end'])) ?>
                        <span class="font-black">(<?= $lv['days_count'] ?> วัน)</span>
                    </p>
                </div>
                <a href="print_leave.php?id=<?= $lv['id'] ?>" target="_blank"
                   class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center hover:bg-slate-200 transition-all flex-shrink-0" title="พิมพ์">
                    <i class="fas fa-print text-xs"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Right column ── -->
    <div class="flex flex-col gap-6">

        <!-- สถิติเดือนนี้ -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-50">
                <h3 class="font-black text-slate-800 text-sm flex items-center gap-2">
                    <i class="fas fa-chart-bar text-indigo-500"></i>
                    สถิติเดือนนี้
                </h3>
            </div>
            <div class="p-5 space-y-3">
                <?php if (empty($monthly_stats)): ?>
                <p class="text-sm text-slate-300 text-center py-4">ยังไม่มีข้อมูลเดือนนี้</p>
                <?php else: ?>
                <?php foreach ($monthly_stats as $ms):
                    $tm2 = $typeMap[$ms['leave_type']] ?? $typeMap['other'];
                    $col2 = $tm2['color'];
                    $pct = round($ms['cnt'] / $max_count * 100);
                ?>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-black text-slate-600 flex items-center gap-1.5">
                            <i class="<?= $tm2['icon'] ?> text-[10px]
                                <?php if ($col2==='rose') echo 'text-rose-500';
                                elseif ($col2==='amber') echo 'text-amber-500';
                                elseif ($col2==='blue') echo 'text-blue-500';
                                else echo 'text-slate-400'; ?>"></i>
                            <?= $tm2['label'] ?>
                        </span>
                        <span class="text-xs font-black text-slate-500"><?= $ms['cnt'] ?> ใบ / <?= (int)$ms['total_days'] ?> วัน</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all
                            <?php if ($col2==='rose') echo 'bg-rose-400';
                            elseif ($col2==='amber') echo 'bg-amber-400';
                            elseif ($col2==='blue') echo 'bg-blue-400';
                            else echo 'bg-slate-400'; ?>"
                             style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 7 วันข้างหน้า -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-50">
                <h3 class="font-black text-slate-800 text-sm flex items-center gap-2">
                    <i class="fas fa-calendar-week text-violet-500"></i>
                    กำลังจะลา (7 วัน)
                </h3>
            </div>
            <div class="divide-y divide-slate-50">
                <?php if (empty($upcoming)): ?>
                <p class="text-sm text-slate-300 text-center py-6">ไม่มีผู้ลา 7 วันข้างหน้า</p>
                <?php else: ?>
                <?php foreach ($upcoming as $up):
                    $tm3 = $typeMap[$up['leave_type']] ?? $typeMap['other'];
                ?>
                <div class="px-5 py-3 flex items-center gap-3">
                    <div class="text-center flex-shrink-0 w-10">
                        <p class="text-[10px] font-black text-slate-400"><?= date('M', strtotime($up['date_start'])) ?></p>
                        <p class="text-xl font-black text-slate-700 leading-tight"><?= date('j', strtotime($up['date_start'])) ?></p>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-black text-slate-700 truncate"><?= htmlspecialchars($up['firstname'] . ' ' . $up['lastname'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-[10px] text-slate-400"><?= $tm3['label'] ?> · <?= $up['days_count'] ?> วัน</p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ── รอดำเนินการ ── -->
<?php if (!empty($pending_leaves)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 flex items-center justify-between border-b border-slate-50 bg-amber-50/30">
        <h3 class="font-black text-slate-800 flex items-center gap-2">
            <i class="fas fa-tasks text-amber-500"></i>
            รอดำเนินการ
            <span class="px-2 py-0.5 bg-amber-500 text-white text-xs font-black rounded-full"><?= count($pending_leaves) ?></span>
        </h3>
        <a href="index.php" class="px-4 py-2 bg-amber-500 text-white text-xs font-black rounded-xl hover:bg-amber-600 transition-all shadow-md shadow-amber-100 flex items-center gap-2">
            <i class="fas fa-check-double"></i> ไปอนุมัติ
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">ผู้ยื่น</th>
                    <th class="px-6 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">ประเภท</th>
                    <th class="px-6 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">วันที่</th>
                    <th class="px-6 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">จำนวน</th>
                    <th class="px-6 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">ขั้นตอน</th>
                    <th class="px-6 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">ยื่นเมื่อ</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($pending_leaves as $pl):
                $tm4 = $typeMap[$pl['leave_type']] ?? $typeMap['other'];
                $col4 = $tm4['color'];
                $daysWait = (int)((time() - strtotime($pl['created_at'])) / 86400);
            ?>
            <tr class="hover:bg-slate-50/50 transition-all">
                <td class="px-6 py-4">
                    <p class="font-black text-slate-800 text-sm"><?= htmlspecialchars($pl['firstname'] . ' ' . $pl['lastname'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($pl['position'])): ?>
                    <p class="text-[10px] text-slate-400"><?= htmlspecialchars($pl['position'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 text-[10px] font-black rounded-lg
                        <?php if ($col4==='rose') echo 'bg-rose-50 text-rose-600';
                        elseif ($col4==='amber') echo 'bg-amber-50 text-amber-600';
                        elseif ($col4==='blue') echo 'bg-blue-50 text-blue-600';
                        else echo 'bg-slate-50 text-slate-500'; ?>">
                        <?= $tm4['label'] ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-center text-xs text-slate-600 font-bold">
                    <?= date('d/m/Y', strtotime($pl['date_start'])) ?>
                    <?php if ($pl['date_start'] !== $pl['date_end']): ?>
                    <br><span class="text-slate-400">ถึง <?= date('d/m/Y', strtotime($pl['date_end'])) ?></span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="font-black text-slate-700"><?= $pl['days_count'] ?></span>
                    <span class="text-[10px] text-slate-400"> วัน</span>
                </td>
                <td class="px-6 py-4 text-center">
                    <?php if ($pl['level_at'] <= 1): ?>
                    <span class="px-2 py-1 bg-amber-50 text-amber-600 text-[10px] font-black rounded-lg whitespace-nowrap">
                        <i class="fas fa-user-check mr-1"></i>รอเจ้าหน้าที่
                    </span>
                    <?php else: ?>
                    <span class="px-2 py-1 bg-blue-50 text-blue-600 text-[10px] font-black rounded-lg whitespace-nowrap">
                        <i class="fas fa-user-tie mr-1"></i>รอ ผอ./รองฯ
                    </span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-center text-[10px] text-slate-400 font-bold">
                    <?= date('d/m/Y', strtotime($pl['created_at'])) ?>
                    <?php if ($daysWait > 0): ?>
                    <br><span class="text-rose-400">(รอ <?= $daysWait ?> วัน)</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="print_leave.php?id=<?= $pl['id'] ?>" target="_blank"
                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 text-slate-500 text-xs font-black rounded-lg hover:bg-slate-200 transition-all mr-1">
                        <i class="fas fa-print"></i>
                    </a>
                    <a href="index.php" class="inline-flex items-center gap-1 px-3 py-1.5 bg-rose-500 text-white text-xs font-black rounded-lg hover:bg-rose-600 transition-all shadow-sm shadow-rose-100">
                        <i class="fas fa-pen"></i> ดำเนินการ
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="bg-emerald-50 rounded-2xl p-6 flex items-center gap-4 border border-emerald-100">
    <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center text-xl flex-shrink-0">
        <i class="fas fa-check-circle"></i>
    </div>
    <div>
        <p class="font-black text-emerald-800">ไม่มีรายการรอดำเนินการ</p>
        <p class="text-sm text-emerald-600">ใบลาทั้งหมดได้รับการอนุมัติหรือดำเนินการแล้ว</p>
    </div>
</div>
<?php endif; ?>

<?php require_once '../components/layout_end.php'; ?>
