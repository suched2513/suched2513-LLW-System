<?php
require_once 'functions.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'];
$pageTitle   = 'รายงาน ปพ.5';
$pageSubtitle = 'บันทึกเวลาเรียนรายคาบ';

$isAdmin  = in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin']);
$subjects = getTeacherSubjects($isAdmin ? 0 : (int)$teacher_id, $pdo);

$sid       = (int)($_GET['subject_id'] ?? 0);
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date']   ?? date('Y-m-t');

function p5MonthShort(int $m): string {
    return ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][$m];
}
function p5DayShort(int $n): string {
    return ['','จ','อ','พ','พฤ','ศ','ส','อา'][$n];
}
function p5StatusSymbol(string $s): string {
    return ['มา'=>'/','ขาด'=>'ข','ลา'=>'ล','โดด'=>'โ','สาย'=>'ส'][$s] ?? '-';
}
function p5StatusColor(string $s): string {
    return ['มา'=>'#15803d','ขาด'=>'#dc2626','ลา'=>'#92400e','โดด'=>'#7c3aed','สาย'=>'#c2410c'][$s] ?? '#64748b';
}

$subject      = null;
$sessions     = [];
$weekGroups   = [];
$students     = [];
$attendMap    = [];

if ($sid) {
    $subject = getSubjectById($sid, $pdo);

    // ดึง sessions ที่มีการเช็คชื่อจริง
    $stmt = $pdo->prepare("
        SELECT DISTINCT date, period
        FROM att_attendance
        WHERE subject_id = ? AND date BETWEEN ? AND ?
        ORDER BY date, period
    ");
    $stmt->execute([$sid, $startDate, $endDate]);
    $rawSessions = $stmt->fetchAll();

    $sessionNum  = 1;
    $weekSeq     = []; // isoWeek → sequential week number
    $weekCounter = 0;
    foreach ($rawSessions as $s) {
        $ts      = strtotime($s['date']);
        $isoWeek = date('oW', $ts); // year+week unique key
        if (!isset($weekSeq[$isoWeek])) {
            $weekSeq[$isoWeek] = ++$weekCounter;
        }
        $sessions[] = [
            'date'    => $s['date'],
            'period'  => (int)$s['period'],
            'isoWeek' => $isoWeek,
            'weekNum' => $weekSeq[$isoWeek],
            'yearBE'  => (int)date('Y', $ts) + 543,
            'month'   => p5MonthShort((int)date('m', $ts)),
            'day'     => (int)date('j', $ts),
            'dayName' => p5DayShort((int)date('N', $ts)),
            'num'     => $sessionNum++,
        ];
    }

    // จัดกลุ่มตามสัปดาห์
    foreach ($sessions as $s) {
        $weekGroups[$s['isoWeek']]['label'] = $s['weekNum'];
        $weekGroups[$s['isoWeek']]['sessions'][] = $s;
    }

    // ดึงนักเรียน
    if (!empty($subject['is_elective'])) {
        $stmt = $pdo->prepare("
            SELECT s.student_id, s.name
            FROM att_students s
            JOIN att_subject_students ss ON ss.student_id = s.id AND ss.subject_id = ?
            ORDER BY s.student_id
        ");
        $stmt->execute([$sid]);
    } else {
        $stmt = $pdo->prepare("SELECT student_id, name FROM att_students WHERE classroom = ? ORDER BY student_id");
        $stmt->execute([$subject['classroom']]);
    }
    $students = $stmt->fetchAll();

    // ดึงข้อมูลเช็คชื่อทั้งหมด
    $stmt = $pdo->prepare("
        SELECT student_id, date, period, status
        FROM att_attendance
        WHERE subject_id = ? AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$sid, $startDate, $endDate]);
    foreach ($stmt->fetchAll() as $r) {
        $attendMap[$r['student_id']][$r['date']][$r['period']] = $r['status'];
    }
}

require_once '../components/layout_start.php';
?>

<style>
.p5-table { border-collapse: collapse; font-size: 11px; width: 100%; }
.p5-table th, .p5-table td {
    border: 1px solid #999;
    text-align: center;
    padding: 2px 3px;
    white-space: nowrap;
}
.p5-table .th-name  { text-align: left; min-width: 140px; }
.p5-table .th-week  { background: #b8860b; color: #fff; font-weight: 700; }
.p5-table .th-meta  { background: #f5e6a3; font-weight: 600; }
.p5-table .th-label { background: #d4a017; color: #fff; font-weight: 700; text-align: right; padding-right: 6px; }
.p5-table .td-come  { color: #15803d; font-weight: 700; }
.p5-table .td-absent{ color: #dc2626; font-weight: 700; }
.p5-table .td-leave { color: #92400e; }
.p5-table .td-skip  { color: #7c3aed; }
.p5-table .td-late  { color: #c2410c; }
.p5-table .td-none  { color: #cbd5e1; }
.p5-table .td-sum   { background: #f0f9ff; font-weight: 600; }
.p5-table .td-ms    { background: #fef2f2; color: #dc2626; font-weight: 700; }
.p5-table .row-odd  { background: #fffdf0; }
.p5-table .row-even { background: #fff; }
@media print {
    .no-print  { display: none !important; }
    .p5-wrapper{ padding: 0 !important; }
    body       { background: #fff !important; }
    @page      { size: A4 landscape; margin: 8mm; }
    .p5-table  { font-size: 9px; }
}
</style>

<div class="flex flex-col gap-6 p5-wrapper">

<!-- Filter (no-print) -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 no-print">
    <h3 class="text-base font-bold text-slate-800 mb-4"><i class="bi bi-funnel me-2 text-indigo-500"></i>เลือกวิชาและช่วงเวลา</h3>
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">วิชาเรียน</label>
            <select name="subject_id" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                <option value="">-- เลือกวิชา --</option>
                <?php foreach ($subjects as $sub): ?>
                <option value="<?= $sub['id'] ?>" <?= $sid == $sub['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sub['subject_code'] . ' ' . $sub['subject_name'] . ' (' . $sub['classroom'] . ')') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">วันที่เริ่มต้น</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>"
                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">วันที่สิ้นสุด</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"
                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-2xl font-bold hover:bg-indigo-700 transition shadow-md">
                <i class="bi bi-search me-1"></i>แสดง
            </button>
            <?php if ($sid && !empty($sessions)): ?>
            <button type="button" onclick="window.print()"
                    class="flex-1 bg-emerald-500 text-white py-3 rounded-2xl font-bold hover:bg-emerald-600 transition shadow-md">
                <i class="bi bi-printer me-1"></i>พิมพ์
            </button>
            <?php endif; ?>
        </div>
    </form>
    <!-- Legend -->
    <div class="flex flex-wrap gap-4 mt-4 text-xs font-semibold">
        <span class="text-green-700">/ = มาเรียน</span>
        <span class="text-red-600">ข = ขาดเรียน</span>
        <span class="text-amber-800">ล = ลา</span>
        <span class="text-violet-600">โ = โดดเรียน</span>
        <span class="text-orange-700">ส = มาสาย</span>
        <span class="text-slate-400">- = ไม่มีข้อมูล</span>
    </div>
</div>

<?php if (!$sid): ?>
<div class="bg-white rounded-3xl p-12 text-center text-slate-400">
    <i class="bi bi-file-earmark-text text-5xl"></i>
    <p class="mt-3 font-medium">เลือกวิชาเรียนเพื่อสร้างรายงาน ปพ.5</p>
</div>

<?php elseif (empty($sessions)): ?>
<div class="bg-white rounded-3xl p-12 text-center text-slate-400">
    <i class="bi bi-calendar-x text-5xl"></i>
    <p class="mt-3 font-medium">ไม่พบข้อมูลการเช็คชื่อในช่วงเวลาที่เลือก</p>
</div>

<?php else: ?>

<!-- Title for print -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-4">
    <div class="text-center mb-2 print-title">
        <div class="font-bold text-lg text-slate-800">บันทึกเวลาเรียน (ปพ.5)</div>
        <div class="text-sm text-slate-600">
            วิชา <?= htmlspecialchars($subject['subject_code'] . ' ' . $subject['subject_name']) ?>
            &nbsp;|&nbsp; ระดับชั้น <?= htmlspecialchars($subject['classroom']) ?>
            &nbsp;|&nbsp; ครูผู้สอน <?= htmlspecialchars($_SESSION['teacher_name'] ?? '') ?>
        </div>
        <div class="text-xs text-slate-500">
            ช่วงเวลา: <?= date('d/m/Y', strtotime($startDate)) ?> — <?= date('d/m/Y', strtotime($endDate)) ?>
            &nbsp;|&nbsp; จำนวนคาบเรียน: <?= count($sessions) ?> คาบ
        </div>
    </div>

    <div class="overflow-x-auto">
    <table class="p5-table">
        <thead>
            <!-- Row 1: สัปดาห์ที่ -->
            <tr>
                <th class="th-label" rowspan="6" style="width:28px">เลขที่</th>
                <th class="th-label" rowspan="6" style="width:58px">เลขประจำตัว</th>
                <th class="th-label th-name" rowspan="6">ชื่อ – สกุล</th>
                <?php foreach ($weekGroups as $wg): ?>
                <th class="th-week" colspan="<?= count($wg['sessions']) ?>">สัปดาห์ที่ <?= $wg['label'] ?></th>
                <?php endforeach; ?>
                <th class="th-week" rowspan="2" colspan="7">สรุป</th>
            </tr>
            <!-- Row 2: ปี พ.ศ. -->
            <tr>
                <?php foreach ($sessions as $s): ?>
                <th class="th-meta"><?= $s['yearBE'] ?></th>
                <?php endforeach; ?>
            </tr>
            <!-- Row 3: เดือน -->
            <tr>
                <?php foreach ($sessions as $s): ?>
                <th class="th-meta"><?= $s['month'] ?></th>
                <?php endforeach; ?>
                <th class="th-meta" rowspan="4" style="font-size:9px">รวม</th>
                <th class="th-meta" rowspan="4" style="font-size:9px">มา</th>
                <th class="th-meta" rowspan="4" style="font-size:9px">ขาด</th>
                <th class="th-meta" rowspan="4" style="font-size:9px">ลา</th>
                <th class="th-meta" rowspan="4" style="font-size:9px">โดด</th>
                <th class="th-meta" rowspan="4" style="font-size:9px">สาย</th>
                <th class="th-meta" rowspan="4" style="font-size:9px">มส.</th>
            </tr>
            <!-- Row 4: วันที่ -->
            <tr>
                <?php foreach ($sessions as $s): ?>
                <th class="th-meta"><?= $s['day'] ?></th>
                <?php endforeach; ?>
            </tr>
            <!-- Row 5: วัน -->
            <tr>
                <?php foreach ($sessions as $s): ?>
                <th class="th-meta"><?= $s['dayName'] ?></th>
                <?php endforeach; ?>
            </tr>
            <!-- Row 6: คาบ (ลำดับ) -->
            <tr>
                <?php foreach ($sessions as $s): ?>
                <th class="th-meta" style="background:#e8d58a"><?= $s['num'] ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php
        $rowNum = 1;
        foreach ($students as $stu):
            $sid_str = $stu['student_id'];
            $cntCome = $cntAbsent = $cntLeave = $cntSkip = $cntLate = $total = 0;
            $rowClass = ($rowNum % 2 === 0) ? 'row-even' : 'row-odd';
        ?>
        <tr class="<?= $rowClass ?>">
            <td><?= $rowNum++ ?></td>
            <td><?= htmlspecialchars($sid_str) ?></td>
            <td class="th-name" style="text-align:left;padding-left:4px"><?= htmlspecialchars($stu['name']) ?></td>
            <?php foreach ($sessions as $s):
                $status = $attendMap[$sid_str][$s['date']][$s['period']] ?? '';
                $sym    = $status ? p5StatusSymbol($s['status'] ?? $status) : '-';
                $sym    = $status ? p5StatusSymbol($status) : '-';
                $cls    = ['มา'=>'td-come','ขาด'=>'td-absent','ลา'=>'td-leave','โดด'=>'td-skip','สาย'=>'td-late'][$status] ?? 'td-none';
                if ($status === 'มา')   $cntCome++;
                elseif ($status === 'ขาด') $cntAbsent++;
                elseif ($status === 'ลา')  $cntLeave++;
                elseif ($status === 'โดด') $cntSkip++;
                elseif ($status === 'สาย') $cntLate++;
                if ($status) $total++;
            ?>
            <td class="<?= $cls ?>"><?= $sym ?></td>
            <?php endforeach; ?>
            <?php
                $pct  = $total > 0 ? round($cntCome / $total * 100, 1) : 0;
                $isMas = ($total > 0 && $pct < 80) ? true : false;
            ?>
            <td class="td-sum"><?= $total ?></td>
            <td class="td-come"><?= $cntCome ?></td>
            <td class="td-absent"><?= $cntAbsent > 0 ? $cntAbsent : '' ?></td>
            <td class="td-leave"><?= $cntLeave > 0 ? $cntLeave : '' ?></td>
            <td class="td-skip"><?= $cntSkip  > 0 ? $cntSkip  : '' ?></td>
            <td class="td-late"><?= $cntLate  > 0 ? $cntLate  : '' ?></td>
            <td class="<?= $isMas ? 'td-ms' : 'td-sum' ?>"><?= $isMas ? 'มส.' : '' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#e2e8f0;font-weight:700">
                <td colspan="3" style="text-align:right;padding-right:6px">รวมทั้งหมด</td>
                <?php
                // Column totals
                $colTotals = [];
                foreach ($sessions as $s) {
                    $comeCnt = 0;
                    foreach ($students as $stu) {
                        $st = $attendMap[$stu['student_id']][$s['date']][$s['period']] ?? '';
                        if ($st === 'มา') $comeCnt++;
                    }
                    $colTotals[] = $comeCnt;
                }
                foreach ($colTotals as $ct): ?>
                <td style="font-size:9px"><?= $ct ?></td>
                <?php endforeach; ?>
                <td colspan="7"></td>
            </tr>
        </tfoot>
    </table>
    </div>

    <!-- Signature block (for print) -->
    <div class="flex justify-end gap-16 mt-6 text-xs" style="page-break-inside:avoid">
        <div class="text-center">
            <div class="mb-8">ลงชื่อ .............................................</div>
            <div>(<?= htmlspecialchars($_SESSION['teacher_name'] ?? '') ?>)</div>
            <div class="text-slate-500">ครูผู้สอน</div>
        </div>
        <div class="text-center">
            <div class="mb-8">ลงชื่อ .............................................</div>
            <div>(............................................)</div>
            <div class="text-slate-500">หัวหน้ากลุ่มสาระ / ผู้รับรอง</div>
        </div>
    </div>
</div><!-- end card -->
<?php endif; ?>

</div><!-- end wrapper -->

<?php require_once '../components/layout_end.php'; ?>
