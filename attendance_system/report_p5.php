<?php
require_once 'functions.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'];
$pageTitle   = 'รายงาน ปพ.5';
$pageSubtitle = 'บันทึกเวลาเรียนรายคาบ';

$isAdmin  = $_SESSION['llw_role'] === 'super_admin';
$subjects = getTeacherSubjects($isAdmin ? 0 : (int)$teacher_id, $pdo);

$sid         = (int)($_GET['subject_id'] ?? 0);
$startDate   = $_GET['start_date'] ?? date('Y-m-01');
$endDate     = $_GET['end_date']   ?? date('Y-m-t');
$teachDays   = $_GET['teach_days'] ?? [];   // array of day numbers 1-7 (Mon=1)
$teachPeriod = (int)($_GET['teach_period'] ?? 0);  // คาบที่ (1-8), 0 = ไม่ระบุ

function p5MonthShort(int $m): string {
    return ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][$m];
}
function p5DayShort(int $n): string {
    return ['','จ','อ','พ','พฤ','ศ','ส','อา'][$n];
}
function p5StatusSymbol(string $s): string {
    return ['มา'=>'/','ขาด'=>'ข','ลา'=>'ล','โดด'=>'โ','สาย'=>'ส'][$s] ?? '-';
}

$subject    = null;
$sessions   = [];
$weekGroups = [];
$students   = [];
$attendMap  = [];

if ($sid) {
    $subject = getSubjectById($sid, $pdo);

    // --- สร้างรายการ sessions ---
    $rawDates = [];

    if (!empty($teachDays)) {
        // Mode A: ครูเลือกวันที่สอน → สร้างคอลัมน์ทุกวันนั้นในช่วงวันที่
        $teachDaysInt = array_map('intval', $teachDays);
        $cur = strtotime($startDate);
        $end = strtotime($endDate);
        while ($cur <= $end) {
            $dow = (int)date('N', $cur); // 1=Mon…7=Sun
            if (in_array($dow, $teachDaysInt)) {
                // ถ้าระบุคาบ → 1 row ต่อวัน, ถ้าไม่ระบุ → ดู att_attendance ว่ามีคาบอะไร
                if ($teachPeriod > 0) {
                    $rawDates[] = ['date' => date('Y-m-d', $cur), 'period' => $teachPeriod];
                } else {
                    // ดึงคาบที่บันทึกจริง หรือใส่คาบ 1 เป็น placeholder
                    $rawDates[] = ['date' => date('Y-m-d', $cur), 'period' => 1];
                }
            }
            $cur = strtotime('+1 day', $cur);
        }
    } else {
        // Mode B (fallback): ใช้วันที่มีเช็คชื่อจริง
        $stmt = $pdo->prepare("
            SELECT DISTINCT date, period
            FROM att_attendance
            WHERE subject_id = ? AND date BETWEEN ? AND ?
            ORDER BY date, period
        ");
        $stmt->execute([$sid, $startDate, $endDate]);
        $rawDates = $stmt->fetchAll();
    }

    // สร้าง metadata ต่อ session
    $sessionNum  = 1;
    $weekSeq     = [];
    $weekCounter = 0;
    foreach ($rawDates as $s) {
        $ts      = strtotime($s['date']);
        $isoWeek = date('oW', $ts);
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

    foreach ($sessions as $s) {
        $weekGroups[$s['isoWeek']]['label']      = $s['weekNum'];
        $weekGroups[$s['isoWeek']]['sessions'][] = $s;
    }

    // ดึงนักเรียน (รวม id เพื่อใช้ตรง key กับ att_attendance)
    if (!empty($subject['is_elective'])) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.student_id, s.name
            FROM att_students s
            JOIN att_subject_students ss ON ss.student_id = s.id AND ss.subject_id = ?
            ORDER BY s.student_id
        ");
        $stmt->execute([$sid]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, student_id, name FROM att_students
            WHERE classroom = ?
              AND student_id REGEXP '^[0-9]+$'
              AND student_id NOT IN (SELECT subject_code FROM att_subjects)
            ORDER BY student_id
        ");
        $stmt->execute([$subject['classroom']]);
    }
    $students = $stmt->fetchAll();

    // ดึงข้อมูลเช็คชื่อ — JOIN att_students เพื่อแปลง db_id กลับเป็น school_code
    // (att_attendance.student_id เก็บ str_pad(db_id) ไม่ใช่ school code โดยตรง)
    $stmt = $pdo->prepare("
        SELECT s.student_id AS school_code, a.date, a.period, a.status
        FROM att_attendance a
        JOIN att_students s ON s.id = a.student_id
        WHERE a.subject_id = ? AND a.date BETWEEN ? AND ?
    ");
    $stmt->execute([$sid, $startDate, $endDate]);
    foreach ($stmt->fetchAll() as $r) {
        $attendMap[$r['school_code']][$r['date']][$r['period']] = $r['status'];
    }
}

require_once '../components/layout_start.php';
?>

<style>
.p5-table { border-collapse: collapse; font-size: 11px; width: 100%; }
.p5-table th, .p5-table td {
    border: 1px solid #999; text-align: center; padding: 2px 3px; white-space: nowrap;
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
.day-btn { display:inline-block; padding:6px 10px; border:2px solid #d4a017; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; color:#92400e; transition:all .2s; }
.day-btn:has(input:checked) { background:#d4a017; color:#fff; }
@media print {
    .no-print  { display: none !important; }
    .p5-wrapper{ padding: 0 !important; }
    body       { background: #fff !important; }
    @page      { size: A4 landscape; margin: 8mm; }
    .p5-table  { font-size: 9px; }
}
</style>

<div class="flex flex-col gap-6 p5-wrapper">

<!-- Filter -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 no-print">
    <h3 class="text-base font-bold text-slate-800 mb-4"><i class="bi bi-funnel me-2 text-indigo-500"></i>เลือกวิชาและกำหนดการสอน</h3>
    <form method="GET" class="flex flex-col gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
        </div>

        <!-- วันที่สอน -->
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-2">
                วันที่สอนต่อสัปดาห์
                <span class="text-slate-400 font-normal ml-1">(เลือกวันที่วิชานี้สอน เพื่อแสดงทุกวันในช่วงเวลาที่เลือก)</span>
            </label>
            <div class="flex flex-wrap gap-2">
                <?php
                $dayLabels = [1=>'จันทร์',2=>'อังคาร',3=>'พุธ',4=>'พฤหัส',5=>'ศุกร์',6=>'เสาร์',7=>'อาทิตย์'];
                $dayShorts = [1=>'จ',2=>'อ',3=>'พ',4=>'พฤ',5=>'ศ',6=>'ส',7=>'อา'];
                foreach ($dayLabels as $num => $label):
                    $checked = in_array((string)$num, (array)$teachDays) ? 'checked' : '';
                ?>
                <label class="day-btn">
                    <input type="checkbox" name="teach_days[]" value="<?= $num ?>" <?= $checked ?> class="hidden">
                    <span><?= $dayShorts[$num] ?></span>
                    <span class="text-xs font-normal hidden sm:inline"> <?= $label ?></span>
                </label>
                <?php endforeach; ?>
                <div class="flex items-center gap-2 ms-3">
                    <label class="text-xs font-bold text-slate-500">คาบที่:</label>
                    <select name="teach_period" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="0" <?= $teachPeriod==0?'selected':'' ?>>ทุกคาบ</option>
                        <?php for ($p=1;$p<=8;$p++): ?>
                        <option value="<?=$p?>" <?= $teachPeriod==$p?'selected':'' ?>>คาบ <?=$p?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <p class="text-xs text-amber-600 mt-1">
                <i class="bi bi-info-circle me-1"></i>
                ถ้าไม่เลือกวัน ระบบจะแสดงเฉพาะวันที่มีการบันทึกเช็คชื่อแล้วเท่านั้น
            </p>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded-2xl font-bold hover:bg-indigo-700 transition shadow-md">
                <i class="bi bi-search me-1"></i>แสดงรายงาน
            </button>
            <?php if ($sid && !empty($sessions)): ?>
            <button type="button" onclick="window.print()"
                    class="bg-emerald-500 text-white px-8 py-3 rounded-2xl font-bold hover:bg-emerald-600 transition shadow-md">
                <i class="bi bi-printer me-1"></i>พิมพ์ ปพ.5
            </button>
            <?php endif; ?>
        </div>
    </form>

    <!-- Legend -->
    <div class="flex flex-wrap gap-4 mt-3 text-xs font-semibold border-t border-slate-100 pt-3">
        <span class="text-green-700">/ = มาเรียน</span>
        <span class="text-red-600">ข = ขาดเรียน</span>
        <span class="text-amber-800">ล = ลา</span>
        <span class="text-violet-600">โ = โดดเรียน</span>
        <span class="text-orange-700">ส = มาสาย</span>
        <span class="text-slate-400">— = ยังไม่บันทึก</span>
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
    <p class="mt-3 font-medium">ไม่พบข้อมูลในช่วงเวลาที่เลือก</p>
    <?php if (empty($teachDays)): ?>
    <p class="text-sm mt-1">ลองเลือก "วันที่สอนต่อสัปดาห์" เพื่อแสดงวันครบ</p>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-4">
    <!-- Header info -->
    <div class="text-center mb-3 print-title">
        <div class="font-bold text-lg text-slate-800">บันทึกเวลาเรียน (ปพ.5)</div>
        <div class="text-sm text-slate-600">
            วิชา <?= htmlspecialchars($subject['subject_code'] . ' ' . $subject['subject_name']) ?>
            &nbsp;|&nbsp; ระดับชั้น <?= htmlspecialchars($subject['classroom']) ?>
            &nbsp;|&nbsp; ครูผู้สอน <?= htmlspecialchars($_SESSION['teacher_name'] ?? '') ?>
        </div>
        <div class="text-xs text-slate-500">
            ช่วงเวลา: <?= date('d/m/Y', strtotime($startDate)) ?> — <?= date('d/m/Y', strtotime($endDate)) ?>
            &nbsp;|&nbsp; จำนวนคาบ: <?= count($sessions) ?> คาบ
            <?php if (!empty($teachDays)): ?>
            &nbsp;|&nbsp; วันที่สอน: <?= implode(', ', array_map(fn($d) => p5DayShort((int)$d), $teachDays)) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="overflow-x-auto">
    <table class="p5-table">
        <thead>
            <!-- Row 1: สัปดาห์ที่ -->
            <tr>
                <th class="th-label" rowspan="6" style="width:28px">เลขที่</th>
                <th class="th-label" rowspan="6" style="width:60px">เลขประจำตัว</th>
                <th class="th-label th-name" rowspan="6">ชื่อ – สกุล</th>
                <?php foreach ($weekGroups as $wg): ?>
                <th class="th-week" colspan="<?= count($wg['sessions']) ?>">สัปดาห์ที่ <?= $wg['label'] ?></th>
                <?php endforeach; ?>
                <th class="th-week" colspan="7">สรุป</th>
            </tr>
            <!-- Row 2: ปี พ.ศ. -->
            <tr>
                <?php foreach ($sessions as $s): ?>
                <th class="th-meta"><?= $s['yearBE'] ?></th>
                <?php endforeach; ?>
                <th class="th-meta" rowspan="5" style="font-size:9px">รวม</th>
                <th class="th-meta" rowspan="5" style="font-size:9px">มา</th>
                <th class="th-meta" rowspan="5" style="font-size:9px">ขาด</th>
                <th class="th-meta" rowspan="5" style="font-size:9px">ลา</th>
                <th class="th-meta" rowspan="5" style="font-size:9px">โดด</th>
                <th class="th-meta" rowspan="5" style="font-size:9px">สาย</th>
                <th class="th-meta" rowspan="5" style="font-size:9px">มส.</th>
            </tr>
            <!-- Row 3: เดือน -->
            <tr>
                <?php foreach ($sessions as $s): ?>
                <th class="th-meta"><?= $s['month'] ?></th>
                <?php endforeach; ?>
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
            <!-- Row 6: คาบลำดับ -->
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
            $sid_str = $stu['student_id'];  // school code เช่น 04742
            $cntCome = $cntAbsent = $cntLeave = $cntSkip = $cntLate = $totalRec = 0;
            $rowClass = ($rowNum % 2 === 0) ? 'row-even' : 'row-odd';
        ?>
        <tr class="<?= $rowClass ?>">
            <td><?= $rowNum++ ?></td>
            <td><?= htmlspecialchars($sid_str) ?></td>
            <td class="th-name" style="text-align:left;padding-left:4px"><?= htmlspecialchars($stu['name']) ?></td>
            <?php foreach ($sessions as $s):
                $status = $attendMap[$sid_str][$s['date']][$s['period']] ?? '';
                $cls    = ['มา'=>'td-come','ขาด'=>'td-absent','ลา'=>'td-leave','โดด'=>'td-skip','สาย'=>'td-late'][$status] ?? 'td-none';
                if ($status === 'มา')   { $cntCome++;   $totalRec++; }
                elseif ($status === 'ขาด') { $cntAbsent++; $totalRec++; }
                elseif ($status === 'ลา')  { $cntLeave++;  $totalRec++; }
                elseif ($status === 'โดด') { $cntSkip++;   $totalRec++; }
                elseif ($status === 'สาย') { $cntLate++;   $totalRec++; }
            ?>
            <td class="<?= $cls ?>"><?= $status ? p5StatusSymbol($status) : '—' ?></td>
            <?php endforeach; ?>
            <?php
                $pct   = $totalRec > 0 ? round($cntCome / $totalRec * 100, 1) : 0;
                $isMas = ($totalRec >= 5 && $pct < 80);
            ?>
            <td class="td-sum"><?= $totalRec ?></td>
            <td class="td-come"><?= $cntCome ?></td>
            <td class="td-absent"><?= $cntAbsent ?: '' ?></td>
            <td class="td-leave"><?= $cntLeave  ?: '' ?></td>
            <td class="td-skip"><?= $cntSkip   ?: '' ?></td>
            <td class="td-late"><?= $cntLate   ?: '' ?></td>
            <td class="<?= $isMas ? 'td-ms' : 'td-sum' ?>"><?= $isMas ? 'มส.' : '' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#e2e8f0;font-weight:700">
                <td colspan="3" style="text-align:right;padding-right:6px">จำนวนนักเรียนที่มา</td>
                <?php foreach ($sessions as $s):
                    $cnt = 0;
                    foreach ($students as $stu) {
                        if (($attendMap[$stu['student_id']][$s['date']][$s['period']] ?? '') === 'มา') $cnt++;
                    }
                ?>
                <td style="font-size:9px;color:#15803d"><?= $cnt ?: '' ?></td>
                <?php endforeach; ?>
                <td colspan="7"></td>
            </tr>
        </tfoot>
    </table>
    </div>

    <!-- Signature block -->
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
</div>
<?php endif; ?>

</div>

<script>
// Toggle day buttons visual state
document.querySelectorAll('.day-btn input[type=checkbox]').forEach(cb => {
    cb.addEventListener('change', () => {
        cb.closest('.day-btn').style.background = cb.checked ? '#d4a017' : '';
        cb.closest('.day-btn').style.color = cb.checked ? '#fff' : '';
    });
    // Init state
    if (cb.checked) {
        cb.closest('.day-btn').style.background = '#d4a017';
        cb.closest('.day-btn').style.color = '#fff';
    }
});
</script>

<?php require_once '../components/layout_end.php'; ?>
