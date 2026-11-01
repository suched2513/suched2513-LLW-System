<?php
/**
 * pp5/index.php — แดชบอร์ดระบบ ปพ.5 ออนไลน์ (แบบบันทึกผลการพัฒนาคุณภาพผู้เรียน)
 *
 * รองรับการแสดงผลตามบทบาทผู้ใช้หลายสิทธิ์ (Multi-role):
 * - ครูผู้สอน (teacher): เห็นรายวิชาที่ตนเองสอน สถานะ ปพ.5 และงานค้าง
 * - ครูที่ปรึกษา (advisor): ดูสถานะและผลการเรียนทุกวิชาของห้องที่ตนเองเป็นที่ปรึกษา
 * - หัวหน้ากลุ่มสาระ (head): ดูและตรวจอนุมัติรายวิชาในกลุ่มสาระของตนเอง
 * - รองฯ วิชาการ / ผู้อำนวยการ / งานทะเบียน (deputy / director / admin): ดูภาพรวมทั้งโรงเรียน 19 ห้องเรียน
 */

require_once __DIR__ . '/_bootstrap.php';
pp5_require_login();

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\MasterDataService;

$policy        = new Pp5Policy($pdo);
$masterService = new MasterDataService($pdo);
$termCtx       = pp5_get_term_context($pdo);
$curYear       = (int)$termCtx['year'];
$curSem        = (int)$termCtx['sem'];

// กดอ่านแจ้งเตือน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_notifications_read') {
    csrf_verify();
    $stmt = $pdo->prepare("UPDATE `pp5_notifications` SET `is_read` = 1 WHERE `user_id` = ?");
    $stmt->execute([$policy->getUserId()]);
    header("Location: /pp5/index.php?term_year={$curYear}&term_sem={$curSem}");
    exit();
}

// ตัวกรองการแสดงผล
$viewMode   = trim((string)($_GET['view'] ?? ($policy->isAdmin() || $policy->isDeputy() || $policy->isDirector() ? 'all' : 'my_teaching')));
$filterDept = (int)($_GET['dept_id'] ?? 0);
$filterGrad = (int)($_GET['grade'] ?? 0);
$filterStat = trim((string)($_GET['status'] ?? ''));
$filterRoom = trim((string)($_GET['room'] ?? ''));

$departments = $masterService->getDepartments();
$classrooms  = $masterService->getClassrooms($curYear, $curSem);

// ดึงรายการ ปพ.5 ตามโหมดการดูและสิทธิ์ Policy
if ($viewMode === 'my_teaching') {
    $courses = $masterService->getCourses($curYear, $curSem, $filterDept ?: null, $filterGrad ?: null, $policy->getUserId(), $filterStat ?: null, $filterRoom ?: null);
} elseif ($viewMode === 'advisor' && $policy->isAdvisor()) {
    $advisedRooms = $policy->getAdvisedClassrooms();
    $selAdvisorRoom = ($filterRoom !== '' && in_array($filterRoom, $advisedRooms, true)) ? $filterRoom : ($advisedRooms[0] ?? '');
    $courses = $selAdvisorRoom !== ''
        ? $masterService->getCourses($curYear, $curSem, $filterDept ?: null, null, null, $filterStat ?: null, $selAdvisorRoom)
        : [];
} elseif ($viewMode === 'department_head' && $policy->isDepartmentHead()) {
    $headedDepts = $policy->getHeadedDepartmentIds();
    $selHeadDept = ($filterDept > 0 && in_array($filterDept, $headedDepts, true)) ? $filterDept : ($headedDepts[0] ?? 0);
    $courses = $selHeadDept > 0
        ? $masterService->getCourses($curYear, $curSem, $selHeadDept, $filterGrad ?: null, null, $filterStat ?: null, $filterRoom ?: null)
        : [];
} elseif ($policy->isAdmin() || $policy->isDeputy() || $policy->isDirector()) {
    $courses = $masterService->getCourses($curYear, $curSem, $filterDept ?: null, $filterGrad ?: null, null, $filterStat ?: null, $filterRoom ?: null);
} else {
    // บังคับ Policy: ครูทั่วไปดูได้เฉพาะวิชาที่ตนเองสอน
    $viewMode = 'my_teaching';
    $courses  = $masterService->getCourses($curYear, $curSem, $filterDept ?: null, $filterGrad ?: null, $policy->getUserId(), $filterStat ?: null, $filterRoom ?: null);
}

// คำนวณสถิติ KPI
$kpi = [
    'total_courses'   => count($courses),
    'draft'           => 0,
    'pending_review'  => 0,
    'approved_locked' => 0,
    'total_students'  => array_sum(array_map(fn($c) => (int)$c['student_count'], $classrooms)),
    'total_rooms'     => count($classrooms),
];
foreach ($courses as $c) {
    if ($c['status'] === 'draft') {
        $kpi['draft']++;
    } elseif (in_array($c['status'], ['submitted', 'head_approved', 'deputy_approved'], true)) {
        $kpi['pending_review']++;
    } elseif (in_array($c['status'], ['approved', 'locked'], true)) {
        $kpi['approved_locked']++;
    }
}

// ดึงการแจ้งเตือนล่าสุดของผู้ใช้
$notifications = [];
if ($policy->getUserId() > 0) {
    $stmtNotif = $pdo->prepare("SELECT * FROM `pp5_notifications` WHERE `user_id` = ? ORDER BY `created_at` DESC LIMIT 5");
    $stmtNotif->execute([$policy->getUserId()]);
    $notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
}

$statusMeta = [
    'draft'           => ['label' => 'กำลังจัดทำ (Draft)',        'badge' => 'bg-slate-100 text-slate-700 border-slate-200',     'icon' => 'bi-pencil-square'],
    'submitted'       => ['label' => 'รอหัวหน้ากลุ่มสาระตรวจ',     'badge' => 'bg-amber-50 text-amber-700 border-amber-200',       'icon' => 'bi-hourglass-split'],
    'head_approved'   => ['label' => 'รอรองฯ วิชาการอนุมัติ',      'badge' => 'bg-blue-50 text-blue-700 border-blue-200',         'icon' => 'bi-person-check'],
    'deputy_approved' => ['label' => 'รอผู้อำนวยการอนุมัติ',       'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200',   'icon' => 'bi-award'],
    'approved'        => ['label' => 'อนุมัติแล้ว',                'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'bi-check-circle-fill'],
    'locked'          => ['label' => 'ล็อกเล่ม ปพ.5 แล้ว',        'badge' => 'bg-purple-50 text-purple-700 border-purple-200',   'icon' => 'bi-lock-fill'],
];

$pageTitle    = 'ระบบ ปพ.5 ออนไลน์';
$pageSubtitle = 'แบบบันทึกผลการพัฒนาคุณภาพผู้เรียน ตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน พ.ศ. 2551';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
?>

<?= pp5_render_top_bar($pdo, $termCtx, $policy, 'dashboard') ?>

<!-- ─── KPI Cards ─── -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-2xl p-5 text-white shadow-xl shadow-indigo-200/50">
        <div class="flex items-center justify-between">
            <p class="text-xs font-bold opacity-85 uppercase tracking-wider">รายวิชา ปพ.5 ที่แสดง</p>
            <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center"><i class="bi bi-journal-text text-lg"></i></div>
        </div>
        <p class="text-3xl font-black mt-2"><?= number_format($kpi['total_courses']) ?> <span class="text-sm font-semibold opacity-80">เล่ม</span></p>
        <p class="text-xs opacity-80 mt-1">1 วิชา × 1 ห้องเรียน = 1 เล่ม ปพ.5</p>
    </div>

    <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-5 text-white shadow-xl shadow-amber-200/50">
        <div class="flex items-center justify-between">
            <p class="text-xs font-bold opacity-85 uppercase tracking-wider">กำลังจัดทำ / รอส่งตรวจ</p>
            <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center"><i class="bi bi-pencil-square text-lg"></i></div>
        </div>
        <p class="text-3xl font-black mt-2"><?= number_format($kpi['draft']) ?> <span class="text-sm font-semibold opacity-80">เล่ม</span></p>
        <p class="text-xs opacity-80 mt-1">รออนุมัติตามลำดับขั้น: <?= number_format($kpi['pending_review']) ?> เล่ม</p>
    </div>

    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-5 text-white shadow-xl shadow-emerald-200/50">
        <div class="flex items-center justify-between">
            <p class="text-xs font-bold opacity-85 uppercase tracking-wider">อนุมัติ / ล็อกเล่มแล้ว</p>
            <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center"><i class="bi bi-patch-check-fill text-lg"></i></div>
        </div>
        <p class="text-3xl font-black mt-2"><?= number_format($kpi['approved_locked']) ?> <span class="text-sm font-semibold opacity-80">เล่ม</span></p>
        <p class="text-xs opacity-80 mt-1">ภาคเรียนที่ <?= $curSem ?>/<?= $curYear ?></p>
    </div>

    <div class="bg-gradient-to-br from-purple-600 to-indigo-700 rounded-2xl p-5 text-white shadow-xl shadow-purple-200/50">
        <div class="flex items-center justify-between">
            <p class="text-xs font-bold opacity-85 uppercase tracking-wider">ข้อมูลนักเรียนทั้งโรงเรียน</p>
            <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center"><i class="bi bi-people-fill text-lg"></i></div>
        </div>
        <p class="text-3xl font-black mt-2"><?= number_format($kpi['total_students']) ?> <span class="text-sm font-semibold opacity-80">คน</span></p>
        <p class="text-xs opacity-80 mt-1">ระดับ ม.1–ม.6 รวม <?= number_format($kpi['total_rooms']) ?> ห้องเรียน</p>
    </div>
</div>

<!-- ─── แจ้งเตือนในระบบ (ถ้ามี) ─── -->
<?php if (!empty($notifications)): ?>
<div class="bg-white rounded-2xl shadow-lg shadow-blue-100/40 p-4 border border-slate-100 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-black text-slate-800 flex items-center gap-2">
            <i class="bi bi-bell-fill text-amber-500"></i> การแจ้งเตือนล่าสุดของคุณ
        </h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_notifications_read">
            <button type="submit" class="text-xs font-bold text-indigo-600 hover:underline">
                <i class="bi bi-check2-all"></i> ทำเครื่องหมายว่าอ่านแล้วทั้งหมด
            </button>
        </form>
    </div>
    <div class="space-y-2">
        <?php foreach ($notifications as $nf): ?>
            <div class="flex items-start justify-between gap-3 p-3 rounded-xl border <?= (int)$nf['is_read'] === 0 ? 'bg-indigo-50/60 border-indigo-200' : 'bg-slate-50 border-slate-100' ?>">
                <div class="flex items-start gap-2.5">
                    <i class="bi bi-info-circle-fill text-indigo-600 mt-0.5"></i>
                    <div>
                        <p class="text-xs font-black text-slate-800"><?= htmlspecialchars($nf['title'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-600 mt-0.5"><?= htmlspecialchars($nf['message'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <span class="text-[11px] text-slate-400 whitespace-nowrap"><?= htmlspecialchars($nf['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ─── แถบสลับมุมมองตามบทบาท (Role View Switcher) & ตัวกรอง ─── -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/40 p-5 border border-slate-100 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-100">
        <div class="flex flex-wrap items-center gap-2">
            <a href="/pp5/index.php?view=my_teaching"
               class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 <?= $viewMode === 'my_teaching' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                <i class="bi bi-easel2-fill"></i> รายวิชาที่ฉันสอน
            </a>

            <?php if ($policy->isAdvisor()): ?>
                <a href="/pp5/index.php?view=advisor"
                   class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 <?= $viewMode === 'advisor' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    <i class="bi bi-people-fill"></i> ห้องที่ปรึกษา (<?= htmlspecialchars(implode(', ', $policy->getAdvisedClassrooms()), ENT_QUOTES, 'UTF-8') ?>)
                </a>
            <?php endif; ?>

            <?php if ($policy->isDepartmentHead()): ?>
                <a href="/pp5/index.php?view=department_head"
                   class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 <?= $viewMode === 'department_head' ? 'bg-teal-600 text-white shadow-md shadow-teal-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    <i class="bi bi-diagram-3-fill"></i> ตรวจงานในกลุ่มสาระ
                </a>
            <?php endif; ?>

            <?php if ($policy->isAdmin() || $policy->isDeputy() || $policy->isDirector()): ?>
                <a href="/pp5/index.php?view=all"
                   class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 <?= $viewMode === 'all' ? 'bg-purple-600 text-white shadow-md shadow-purple-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    <i class="bi bi-building-fill-check"></i> ภาพรวมทั้งโรงเรียน (ทุกกลุ่มสาระ)
                </a>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="/pp5/user_guide.php" class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white text-xs font-bold shadow-md shadow-amber-200 transition-all flex items-center gap-1.5">
                <i class="bi bi-book-half"></i> คู่มือการใช้งาน (1-2-3)
            </a>
            <?php if ($policy->isAdmin()): ?>
                <a href="/pp5/master_data.php?tab=courses" class="px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold shadow-md shadow-blue-200 hover:bg-blue-700 transition-all flex items-center gap-1.5">
                    <i class="bi bi-plus-circle-fill"></i> จัดวิชาเปิดสอน ปพ.5
                </a>
                <a href="/pp5/import_data.php" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold shadow-md shadow-emerald-200 hover:bg-emerald-700 transition-all flex items-center gap-1.5">
                    <i class="bi bi-file-earmark-excel-fill"></i> นำเข้าด้วย Excel
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ฟอร์มกรองรายวิชา -->
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-4">
        <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode, ENT_QUOTES, 'UTF-8') ?>">

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">กลุ่มสาระการเรียนรู้</label>
            <select name="dept_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="0">— ทุกกลุ่มสาระ —</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $filterDept === (int)$d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">ระดับชั้น</label>
            <select name="grade" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="0">— ทุกระดับชั้น (ม.1–ม.6) —</option>
                <?php for ($g = 1; $g <= 6; $g++): ?>
                    <option value="<?= $g ?>" <?= $filterGrad === $g ? 'selected' : '' ?>>มัธยมศึกษาปีที่ <?= $g ?> (ม.<?= $g ?>)</option>
                <?php endfor; ?>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">ห้องเรียน</label>
            <select name="room" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">— ทุกห้องเรียน —</option>
                <?php foreach ($classrooms as $cl): ?>
                    <option value="<?= htmlspecialchars($cl['classroom_name'], ENT_QUOTES, 'UTF-8') ?>" <?= $filterRoom === $cl['classroom_name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['classroom_name'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$cl['student_count'] ?> คน)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">สถานะ ปพ.5</label>
            <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">— ทุกสถานะ —</option>
                <?php foreach ($statusMeta as $sKey => $sInfo): ?>
                    <option value="<?= $sKey ?>" <?= $filterStat === $sKey ? 'selected' : '' ?>><?= htmlspecialchars($sInfo['label'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md shadow-indigo-200 hover:bg-indigo-700 transition-all">
                <i class="bi bi-funnel-fill"></i> กรองข้อมูล
            </button>
            <a href="/pp5/index.php?view=<?= urlencode($viewMode) ?>" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-xs font-bold hover:bg-slate-200 transition-all">
                ล้างค่า
            </a>
        </div>
    </form>
</div>

<!-- ─── ตารางรายการเล่ม ปพ.5 ─── -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/40 overflow-hidden border border-slate-100">
    <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-base font-black text-slate-800">
                รายการแบบบันทึกผลการพัฒนาคุณภาพผู้เรียน (ปพ.5) · ภาคเรียนที่ <?= $curSem ?>/<?= $curYear ?>
            </h3>
            <p class="text-xs text-slate-400">พบทั้งหมด <?= number_format(count($courses)) ?> รายการ</p>
        </div>
    </div>

    <?php if (empty($courses)): ?>
        <div class="p-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-2xl">
                <i class="bi bi-journal-x"></i>
            </div>
            <p class="text-base font-bold text-slate-700">ไม่พบรายการ ปพ.5 ตามเงื่อนไขที่เลือก</p>
            <p class="text-xs text-slate-400 mt-1">หากยังไม่ได้จัดวิชาเปิดสอนประจำภาคเรียน สามารถจัดการหรือซิงค์ได้จากเมนู "จัดการข้อมูลพื้นฐาน"</p>
            <?php if ($policy->isAdmin()): ?>
                <a href="/pp5/master_data.php?tab=courses" class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 rounded-2xl bg-indigo-600 text-white text-xs font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all">
                    <i class="bi bi-database-fill-gear"></i> ไปที่หน้าจัดการรายวิชาที่เปิดสอน
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="text-[11px] font-black text-slate-400 uppercase tracking-wider px-5 py-3.5">รหัสวิชา / ชื่อรายวิชา</th>
                        <th class="text-[11px] font-black text-slate-400 uppercase tracking-wider px-4 py-3.5">ห้องเรียน</th>
                        <th class="text-[11px] font-black text-slate-400 uppercase tracking-wider px-4 py-3.5">กลุ่มสาระ / ประเภท</th>
                        <th class="text-[11px] font-black text-slate-400 uppercase tracking-wider px-4 py-3.5">ครูผู้สอน</th>
                        <th class="text-[11px] font-black text-slate-400 uppercase tracking-wider px-4 py-3.5 text-center">สัดส่วนคะแนน</th>
                        <th class="text-[11px] font-black text-slate-400 uppercase tracking-wider px-4 py-3.5 text-center">สถานะ ปพ.5</th>
                        <th class="text-[11px] font-black text-slate-400 uppercase tracking-wider px-5 py-3.5 text-right">การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php foreach ($courses as $cr):
                        $stMeta = $statusMeta[$cr['status']] ?? $statusMeta['draft'];
                        $typeLabel = [
                            'basic'      => ['พื้นฐาน', 'bg-blue-50 text-blue-700'],
                            'additional' => ['เพิ่มเติม', 'bg-violet-50 text-violet-700'],
                            'activity'   => ['กิจกรรมฯ', 'bg-teal-50 text-teal-700'],
                        ][$cr['subject_type']] ?? ['พื้นฐาน', 'bg-blue-50 text-blue-700'];
                    ?>
                        <tr class="hover:bg-slate-50/80 transition-all">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-black text-xs border border-indigo-100">
                                        <?= htmlspecialchars($cr['subject_code'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <div>
                                        <p class="font-bold text-slate-800 text-xs sm:text-sm"><?= htmlspecialchars($cr['subject_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-[11px] text-slate-400"><?= number_format((float)$cr['credit'], 1) ?> หน่วยกิต · <?= (int)$cr['hours'] ?> ชม./ภาค</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-800 font-black text-xs">
                                    <?= htmlspecialchars($cr['classroom_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="block text-[11px] text-slate-400 mt-0.5">นักเรียน <?= (int)$cr['student_count'] ?> คน</span>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="text-xs font-bold text-slate-700"><?= htmlspecialchars($cr['department_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="inline-block mt-0.5 px-2 py-0.5 rounded-md text-[10px] font-bold <?= $typeLabel[1] ?>">
                                    <?= $typeLabel[0] ?>
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="text-xs font-bold text-slate-700">
                                    <i class="bi bi-person-badge text-indigo-500"></i>
                                    <?= htmlspecialchars($cr['teacher_name'] ?: 'ไม่ระบุครู', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <?php if ($cr['subject_type'] === 'activity'): ?>
                                    <span class="px-2.5 py-1 rounded-full bg-teal-50 text-teal-700 text-xs font-bold">ผ / มผ</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-black">
                                        <?= (int)$cr['ratio_collect'] ?> : <?= (int)$cr['ratio_final'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-bold <?= $stMeta['badge'] ?>">
                                    <i class="bi <?= $stMeta['icon'] ?>"></i> <?= htmlspecialchars($stMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!empty($cr['rejected_reason'])): ?>
                                    <p class="text-[11px] text-rose-600 font-semibold mt-1" title="<?= htmlspecialchars($cr['rejected_reason'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars(mb_strimwidth($cr['rejected_reason'], 0, 40, '...', 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <?php if ($policy->isAdmin()): ?>
                                        <a href="/pp5/master_data.php?tab=courses&edit_course_id=<?= (int)$cr['id'] ?>"
                                           class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 text-xs font-bold transition-all"
                                           title="ตั้งค่าครูผู้สอน/ปลดล็อก">
                                            <i class="bi bi-gear-fill"></i> ตั้งค่า
                                        </a>
                                    <?php endif; ?>
                                    <a href="/pp5/course_setup.php?course_id=<?= (int)$cr['id'] ?>"
                                       class="px-2.5 py-1.5 rounded-xl bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-bold transition-all"
                                       title="ตั้งค่าสัดส่วนคะแนนและชิ้นงาน">
                                        <i class="bi bi-sliders"></i> ชิ้นงาน
                                    </a>
                                    <a href="/pp5/course_attendance.php?course_id=<?= (int)$cr['id'] ?>"
                                       class="px-2.5 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-bold transition-all"
                                       title="บันทึกเวลาเรียนรายคาบ (เช็กชื่อ)">
                                        <i class="bi bi-calendar2-check-fill"></i> เวลาเรียน
                                    </a>
                                    <a href="/pp5/course_scores.php?course_id=<?= (int)$cr['id'] ?>"
                                       class="px-3 py-1.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-xs font-bold shadow-sm shadow-blue-200 transition-all"
                                       title="กรอกคะแนนและตัดเกรดแบบ Excel">
                                        <i class="bi bi-table"></i> กรอกคะแนน
                                    </a>
                                    <a href="/pp5/course_assessments.php?course_id=<?= (int)$cr['id'] ?>"
                                       class="px-2.5 py-1.5 rounded-xl bg-violet-50 text-violet-700 hover:bg-violet-100 text-xs font-bold transition-all"
                                       title="ประเมินคุณลักษณะอันพึงประสงค์ อ่านคิดเขียน และสมรรถนะ">
                                        <i class="bi bi-heart-pulse-fill"></i> คุณลักษณะฯ
                                    </a>
                                    <a href="/pp5/course_summary.php?course_id=<?= (int)$cr['id'] ?>"
                                       class="px-2.5 py-1.5 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-bold transition-all"
                                       title="สรุปผลและพิมพ์เล่ม ปพ.5 ขนาด A4 ครบ 5 ส่วน">
                                        <i class="bi bi-printer-fill"></i> พิมพ์ ปพ.5
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
