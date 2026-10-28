<?php
require_once 'functions.php';
require_once '../includes/telegram_bot.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'];

// แยกตัวแปรออกเป็น2 อัน:
// 1. $save_teacher_id → teacher_id จริงจาก session ใช้ตอน save (ต้อง exist ใน att_teachers)
// 2. $subject_filter_tid → 0 สำหรับ admin เพื่อ bypass filter ใน getTeacherSubjects
$save_teacher_id = (int)$_SESSION['teacher_id'];
$subject_filter_tid = $_SESSION['llw_role'] === 'super_admin'
    ? 0
    : $save_teacher_id;

$pageTitle = 'เช็คชื่อรายคาบ';
$pageSubtitle = 'บันทึกข้อมูลการเข้าเรียนของนักเรียน';

$selected_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : (isset($_POST['semester']) ? (int)$_POST['semester'] : llw_current_semester());
$selected_year     = llw_current_academic_year();
$subjects = getTeacherSubjects($subject_filter_tid, $pdo, $selected_semester, $selected_year);

$selected_subject_id = $_GET['subject_id'] ?? ($_POST['subject_id'] ?? '');
$selected_date = $_GET['date'] ?? ($_POST['date'] ?? date('Y-m-d'));
$selected_period = $_GET['period'] ?? ($_POST['period'] ?? 1);
$start_time = $_GET['start_time'] ?? ($_POST['start_time'] ?? '08:40');

$students = [];
$subject_info = null;
$existing_att_map = [];
$morning_assembly_map = [];
$morning_absent_count = 0;
$subject_cum_map = [];

if ($selected_subject_id) {
    $subject_info = getSubjectById($selected_subject_id, $pdo);
    if ($subject_info) {
        $students = getStudentsBySubject($selected_subject_id, $pdo);
    }
}

// Handle Form Submit
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['status'])) {
    $date = $_POST['date'];
    $period = $_POST['period'];
    $subject_id = $_POST['subject_id'];
    $p_start_time = $_POST['start_time'];
    
    $student_status = $_POST['status'] ?? [];
    $student_time_in = $_POST['time_in'] ?? [];
    $student_note = $_POST['note'] ?? [];

    try {
        $pdo->beginTransaction();
        foreach ($student_status as $sid_code => $status) {
            $time_in = $student_time_in[$sid_code] ?? null;
            $note = $student_note[$sid_code] ?? '';
            
            // Save each student's attendance (ใช้ save_teacher_id จริง ไม่ใช้ 0 เพื่อไม่ผิด FK)
            saveAttendance($date, $period, $subject_id, $save_teacher_id, $sid_code, $status, $time_in, $p_start_time, $note, $pdo);
        }
        $pdo->commit();
        $success_msg = "บันทึกข้อมูลเรียบร้อยแล้ว";

        // ── Telegram Auto-Broadcast (ส่งอัตโนมัติทุกครั้ง) ──
        try {
            // Chat ID เฉพาะรายวิชา (ถ้ามี) มิฉะนั้นใช้กลุ่มเช็คชื่อกลางจาก wfh_system_settings
            $customSubChat = null;
            $_has_sub_chat = (bool)$pdo->query("SHOW COLUMNS FROM `att_subjects` LIKE 'telegram_chat_id'")->fetch();
            if ($_has_sub_chat && !empty($subject_info['telegram_chat_id'])) {
                $customSubChat = $subject_info['telegram_chat_id'];
            }

            $counts = ['มา' => 0, 'ขาด' => 0, 'ลา' => 0, 'โดด' => 0, 'สาย' => 0];
            $absentees = [];
            foreach ($student_status as $sid => $status) {
                if (isset($counts[$status])) $counts[$status]++;
                if (in_array($status, ['ขาด', 'โดด', 'ลา', 'สาย'])) {
                    $st = $pdo->prepare("SELECT name FROM att_students WHERE id = ?");
                    $st->execute([$sid]);
                    $sName = htmlspecialchars((string)$st->fetchColumn(), ENT_QUOTES, 'UTF-8');
                    $absentees[] = "• {$sName} (" . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . ")";
                }
            }

            $total = array_sum($counts);
            $msg  = "✅ <b>เช็คชื่อแล้ว</b>\n";
            $msg .= "📅 " . date('d/m/Y', strtotime($date)) . " | คาบที่ {$period}\n";
            $msg .= "📘 " . htmlspecialchars($subject_info['subject_name']) . " (" . htmlspecialchars($subject_info['classroom']) . ")\n";
            $msg .= "👤 " . htmlspecialchars($_SESSION['teacher_name'] ?? '') . "\n";
            $msg .= "📊 มา {$counts['มา']} | ขาด {$counts['ขาด']} | ลา {$counts['ลา']} | โดด {$counts['โดด']} | สาย {$counts['สาย']} / {$total} คน";

            if (!empty($absentees)) {
                $msg .= "\n\n<b>รายชื่อ:</b>\n" . implode("\n", $absentees);
            }

            $tgResult = Telegram::sendAttendanceNotify($pdo, $msg, null);
            if ($customSubChat !== null && trim((string)$customSubChat) !== '') {
                Telegram::sendAttendanceNotify($pdo, $msg, $customSubChat);
            }
            if (!($tgResult['ok'] ?? false)) {
                error_log('[Attendance] Telegram failed: ' . ($tgResult['description'] ?? 'unknown'));
            }
        } catch (\Throwable $tgEx) {
            error_log('[Attendance] Telegram error: ' . $tgEx->getMessage());
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        error_log("Attendance Save Error: " . $e->getMessage());
    }
}

// ── Batch-load current period attendance, morning assembly status, and cumulative subject risk ──
if ($subject_info && !empty($students)) {
    try {
        // 1) Current period records
        $stCur = $pdo->prepare("SELECT student_id, status, time_in, note FROM att_attendance WHERE date = ? AND period = ? AND subject_id = ?");
        $stCur->execute([$selected_date, $selected_period, $selected_subject_id]);
        foreach ($stCur->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $existing_att_map[$r['student_id']] = $r;
        }

        // 2) Morning assembly status for these students on $selected_date
        $studentCodes = array_values(array_filter(array_column($students, 'student_id')));
        if (!empty($studentCodes)) {
            $inPlaceholders = implode(',', array_fill(0, count($studentCodes), '?'));
            $stAsm = $pdo->prepare("SELECT student_id, status, note FROM assembly_attendance WHERE date = ? AND student_id IN ($inPlaceholders)");
            $stAsm->execute(array_merge([$selected_date], $studentCodes));
            foreach ($stAsm->fetchAll(PDO::FETCH_ASSOC) as $ar) {
                $morning_assembly_map[$ar['student_id']] = $ar;
                if (in_array($ar['status'], ['ขาด', 'ลา', 'โดด'])) {
                    $morning_absent_count++;
                }
            }
        }

        // 3) Cumulative attendance in this subject for early warning (เสี่ยง มส. / มส.)
        $stCum = $pdo->prepare("
            SELECT student_id,
                   COUNT(*) AS total_p,
                   SUM(CASE WHEN status IN ('มา','สาย','ลา') THEN 1 ELSE 0 END) AS present_p,
                   SUM(CASE WHEN status IN ('ขาด','โดด') THEN 1 ELSE 0 END) AS absent_p
            FROM att_attendance
            WHERE subject_id = ?
            GROUP BY student_id
        ");
        $stCum->execute([$selected_subject_id]);
        foreach ($stCum->fetchAll(PDO::FETCH_ASSOC) as $cr) {
            $tot = (int)$cr['total_p'];
            $pres = (int)$cr['present_p'];
            $pct = $tot > 0 ? round(($pres / $tot) * 100, 1) : 100.0;
            $subject_cum_map[$cr['student_id']] = [
                'total'   => $tot,
                'present' => $pres,
                'absent'  => (int)$cr['absent_p'],
                'pct'     => $pct
            ];
        }
    } catch (Throwable $e) {
        // Ignore if table missing
    }
}

require_once '../components/layout_start.php';
?>

<!-- Tom Select (For Searchable Dropdown) -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.default.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<style>
    /* Tom Select Custom Styles */
    .ts-control {
        border-radius: 0.75rem !important;
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        padding: 0.625rem 1rem !important;
        font-size: 0.875rem !important;
        box-shadow: none !important;
    }
    .ts-control.focus {
        box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.5) !important;
        border-color: #60a5fa !important;
    }
    .ts-dropdown {
        border-radius: 0.75rem !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        font-size: 0.875rem !important;
        z-index: 50 !important;
    }
    .ts-dropdown .optgroup-header {
        font-weight: 800;
        color: #475569;
        background-color: #f1f5f9;
        font-size: 0.75rem;
    }
    .row-มา { background-color: rgba(236, 253, 245, 0.5); }
    .row-ขาด { background-color: rgba(254, 242, 242, 0.8); }
    .row-ลา { background-color: rgba(255, 251, 235, 0.8); }
    .row-โดด { background-color: rgba(250, 245, 255, 0.8); }
    .row-สาย { background-color: rgba(255, 247, 237, 0.8); }
</style>

<div class="flex flex-col gap-6">

    <!-- ── Selection Form ── -->
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 no-print">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-600">
                <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-black">
                    <i class="bi bi-calendar3-range me-1"></i>ปีการศึกษา <?= $selected_year ?>
                </span>
                <span>เลือกภาคเรียน:</span>
                <a href="?semester=1&date=<?= urlencode($selected_date) ?>&period=<?= (int)$selected_period ?>"
                   class="px-3 py-1 rounded-lg font-black transition <?= $selected_semester === 1 ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    ภาคเรียนที่ 1
                </a>
                <a href="?semester=2&date=<?= urlencode($selected_date) ?>&period=<?= (int)$selected_period ?>"
                   class="px-3 py-1 rounded-lg font-black transition <?= $selected_semester === 2 ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    ภาคเรียนที่ 2
                </a>
            </div>
            <?php if (!empty($morning_assembly_map)): ?>
            <div class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-xl flex items-center gap-1.5">
                <i class="bi bi-sunrise-fill text-amber-500"></i>
                เช็คชื่อเข้าแถวเช้าวันนี้แล้ว (พบข้อมูล <?= count($morning_assembly_map) ?> คน · ขาด/ลา/โดด <?= $morning_absent_count ?> คน)
            </div>
            <?php endif; ?>
        </div>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <input type="hidden" name="semester" value="<?= (int)$selected_semester ?>">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">วันที่</label>
                <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" 
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition-all" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">คาบเรียน</label>
                <select name="period" id="period-select" onchange="updateStartTime(this.value)"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition-all" required>
                    <?php for($i=1; $i<=8; $i++): ?>
                        <option value="<?= $i ?>" <?= $selected_period == $i ? 'selected' : '' ?>><?= "คาบที่ $i" ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="lg:col-span-2">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">รายวิชา</label>
                <select id="subject-select" name="subject_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition-all" required>
                    <option value="">-- ค้นหารายวิชา --</option>
                    <?php
                    $regular = array_filter($subjects, fn($s) => empty($s['is_elective']));
                    $elective = array_filter($subjects, fn($s) => !empty($s['is_elective']));
                    
                    if (!empty($regular)): ?>
                    <optgroup label="📚 วิชาพื้นฐาน (ตามห้อง)">
                        <?php foreach($regular as $subj): ?>
                            <option value="<?= $subj['id'] ?>" <?= $selected_subject_id == $subj['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subj['subject_code'] . ' - ' . $subj['subject_name'] . ' (' . $subj['classroom'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>

                    <?php if (!empty($elective)): ?>
                    <optgroup label="✨ วิชาเพิ่มเติม/วิชาเลือก (ตามสาย)">
                        <?php foreach($elective as $subj): ?>
                            <option value="<?= $subj['id'] ?>" <?= $selected_subject_id == $subj['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subj['subject_code'] . ' - ' . $subj['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">เวลาเริ่ม</label>
                    <input type="time" name="start_time" value="<?= htmlspecialchars($start_time) ?>" 
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none" required>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl hover:bg-blue-700 transition font-bold shadow-lg shadow-blue-100 flex items-center justify-center">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>

    <?php if ($subject_info && count($students) > 0): ?>
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <!-- Table Header / Tools -->
        <div class="px-6 py-5 bg-slate-50 border-b border-slate-100 flex flex-wrap gap-4 items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-blue-600 shadow-sm border border-slate-100">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                  <h3 class="font-bold text-slate-800">นักเรียนห้อง <?= htmlspecialchars($subject_info['classroom']) ?></h3>
                  <p class="text-xs text-slate-400 font-bold uppercase tracking-wider"><?= count($students) ?> คนในชั้นเรียน</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($morning_absent_count > 0): ?>
                <button type="button" onclick="syncFromMorningAssembly()" class="bg-amber-50 text-amber-700 px-4 py-2 rounded-xl text-xs font-bold hover:bg-amber-100 transition flex items-center gap-2 border border-amber-200 shadow-sm">
                    <i class="bi bi-sunrise-fill text-amber-500 text-sm"></i> ดึงสถานะ ขาด/ลา จากแถวเช้า (<?= $morning_absent_count ?> คน)
                </button>
                <?php endif; ?>
                <button type="button" onclick="checkAllPresent()" class="bg-emerald-50 text-emerald-600 px-4 py-2 rounded-xl text-xs font-bold hover:bg-emerald-100 transition flex items-center gap-2 border border-emerald-100">
                    <i class="bi bi-check-all text-base"></i> มาทั้งหมด
                </button>
                <button type="button" onclick="resetAll()" class="bg-slate-100 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-200 transition flex items-center gap-2">
                    <i class="bi bi-arrow-counterclockwise"></i> รีเซ็ต
                </button>
            </div>
        </div>

        <form method="POST" id="attendance-form" action="attendance.php?date=<?= $selected_date ?>&period=<?= $selected_period ?>&subject_id=<?= $selected_subject_id ?>&start_time=<?= $start_time ?>">
            <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
            <input type="hidden" name="period" value="<?= htmlspecialchars($selected_period) ?>">
            <input type="hidden" name="subject_id" value="<?= htmlspecialchars($selected_subject_id) ?>">
            <input type="hidden" name="start_time" value="<?= htmlspecialchars($start_time) ?>">

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-widest">รหัส/ชื่อ-สกุล</th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-slate-400 uppercase tracking-widest">สถานะเช็คชื่อ</th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-slate-400 uppercase tracking-widest">เวลาเข้า/หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($students as $student): ?>
                            <?php
                                $sid_db_id = $student['id'];
                                $sid_display_code = $student['student_id'];
                                
                                $record = $existing_att_map[$sid_db_id] ?? null;
                                $cur_status = $record ? $record['status'] : 'มา';
                                $cur_time = $record ? $record['time_in'] : '';
                                $cur_note = $record ? $record['note'] : '';

                                $asm_rec = $morning_assembly_map[$sid_display_code] ?? null;
                                $asm_status = $asm_rec['status'] ?? '';

                                $cum_rec = $subject_cum_map[$sid_db_id] ?? null;
                            ?>
                            <tr id="row-<?= $sid_db_id ?>"
                                data-morning-status="<?= htmlspecialchars($asm_status) ?>"
                                data-morning-note="<?= htmlspecialchars($asm_rec['note'] ?? '') ?>"
                                class="row-<?= $cur_status ?> transition-colors duration-200">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-xs font-mono font-bold text-blue-600"><?= htmlspecialchars($sid_display_code) ?></span>
                                            <?php if ($asm_status && $asm_status !== 'มา'):
                                                $asmBadgeColors = [
                                                    'ขาด' => 'bg-rose-100 text-rose-700 border-rose-200',
                                                    'ลา'  => 'bg-amber-100 text-amber-800 border-amber-200',
                                                    'โดด' => 'bg-purple-100 text-purple-700 border-purple-200',
                                                    'สาย' => 'bg-orange-100 text-orange-700 border-orange-200',
                                                ];
                                                $bCls = $asmBadgeColors[$asm_status] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                                            ?>
                                            <span class="px-2 py-0.5 rounded-md border text-[10px] font-black <?= $bCls ?>" title="สถานะเช็คชื่อแถวเช้าวันนี้">
                                                🌅 แถวเช้า: <?= htmlspecialchars($asm_status) ?><?= !empty($asm_rec['note']) ? ' (' . htmlspecialchars($asm_rec['note']) . ')' : '' ?>
                                            </span>
                                            <?php endif; ?>

                                            <?php if ($cum_rec && $cum_rec['total'] >= 3):
                                                if ($cum_rec['pct'] < 80): ?>
                                                <span class="px-2 py-0.5 rounded-md bg-rose-600 text-white text-[10px] font-black" title="เวลาเรียนสะสมวิชานี้ต่ำกว่า 80%">
                                                    🚨 มส. (<?= $cum_rec['pct'] ?>% · ขาด <?= $cum_rec['absent'] ?> คาบ)
                                                </span>
                                                <?php elseif ($cum_rec['pct'] < 85): ?>
                                                <span class="px-2 py-0.5 rounded-md bg-amber-500 text-white text-[10px] font-black" title="เวลาเรียนสะสมวิชานี้ใกล้เกณฑ์ 80%">
                                                    ⚠️ เสี่ยง มส. (<?= $cum_rec['pct'] ?>% · ขาด <?= $cum_rec['absent'] ?> คาบ)
                                                </span>
                                                <?php endif;
                                            endif; ?>
                                        </div>
                                        <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($student['name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-center flex-wrap gap-2">
                                        <?php 
                                        $opts = [
                                            ['v'=>'มา','c'=>'emerald'], ['v'=>'ขาด','c'=>'rose'], 
                                            ['v'=>'ลา','c'=>'amber'], ['v'=>'โดด','c'=>'violet'], 
                                            ['v'=>'สาย','c'=>'orange']
                                        ];
                                        foreach($opts as $o):
                                        ?>
                                        <label class="group relative flex items-center justify-center">
                                            <input type="radio" name="status[<?= $sid_db_id ?>]" value="<?= $o['v'] ?>" 
                                                   <?= $cur_status == $o['v'] ? 'checked' : '' ?> 
                                                   onclick="onStatusChange('<?= $sid_db_id ?>', '<?= $o['v'] ?>')"
                                                   class="peer absolute opacity-0 cursor-pointer">
                                            <div class="px-3 py-1.5 rounded-xl border-2 border-transparent bg-slate-100 text-slate-400 peer-checked:bg-<?= $o['c'] ?>-100 peer-checked:text-<?= $o['c'] ?>-700 peer-checked:border-<?= $o['c'] ?>-200 text-xs font-bold cursor-pointer transition-all">
                                                <?= $o['v'] ?>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-2 max-w-[200px] mx-auto">
                                        <input type="time" name="time_in[<?= $sid_db_id ?>]" id="time_in_<?= $sid_db_id ?>" 
                                               value="<?= htmlspecialchars($cur_time) ?>" 
                                               class="bg-white border border-slate-200 rounded-lg p-1.5 text-xs outline-none focus:ring-2 focus:ring-blue-400">
                                        <input type="text" name="note[<?= $sid_db_id ?>]" value="<?= htmlspecialchars($cur_note) ?>" 
                                               placeholder="หมายเหตุ..."
                                               class="bg-white border border-slate-200 rounded-lg p-1.5 text-xs outline-none focus:ring-2 focus:ring-blue-400 w-full">
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                        <span id="count-มา" class="text-xs font-bold text-slate-600">มา 0</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div>
                        <span id="count-ขาด" class="text-xs font-bold text-slate-600">ขาด 0</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-slate-300"></div>
                        <span id="count-อื่น" class="text-xs font-bold text-slate-600">อื่นๆ 0</span>
                    </div>
                </div>
                
                <button type="submit" name="save_attendance" id="save-btn" onclick="return confirmSave(event)"
                        class="w-full sm:w-auto bg-blue-600 text-white px-10 py-3 rounded-2xl font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition flex items-center justify-center gap-2">
                    <i class="bi bi-cloud-check-fill"></i> บันทึกข้อมูลทั้งหมด
                </button>
            </div>
        </form>
    </div>
    <?php elseif ($selected_subject_id):
        // วินิจฉัย: ดูว่าห้องนี้มีนักเรียนกี่คนในระบบ (เฉพาะ active)
        $diagCls      = $subject_info['classroom'] ?? '';
        $diagAllCount = (int)$pdo->query("SELECT COUNT(*) FROM att_students WHERE status = 'active'")->fetchColumn();
        $diagCls2     = $pdo->query("SELECT DISTINCT classroom FROM att_students WHERE status = 'active' ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);
        $diagCount = 0;
        if ($diagCls) {
            $dStmt = $pdo->prepare("SELECT COUNT(*) FROM att_students WHERE classroom=? AND status = 'active'");
            $dStmt->execute([$diagCls]);
            $diagCount = (int)$dStmt->fetchColumn();
        }
    ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 p-8 rounded-3xl shadow-sm">
            <div class="text-center mb-4">
                <i class="bi bi-exclamation-triangle-fill text-3xl mb-3 block opacity-50"></i>
                <p class="font-bold text-base">ไม่พบรายชื่อนักเรียนในรายวิชานี้</p>
            </div>
            <div class="max-w-lg mx-auto space-y-3 text-sm">
                <div class="bg-white/60 rounded-2xl px-5 py-3 flex justify-between items-center">
                    <span class="font-bold">ห้องเรียนที่ระบบหา</span>
                    <span class="font-mono font-black text-amber-700"><?= htmlspecialchars($diagCls ?: '(ว่าง)') ?></span>
                </div>
                <div class="bg-white/60 rounded-2xl px-5 py-3 flex justify-between items-center">
                    <span class="font-bold">นักเรียนในระบบ (รวมทุกห้อง)</span>
                    <span class="font-black <?= $diagAllCount === 0 ? 'text-rose-600' : 'text-emerald-700' ?>">
                        <?= $diagAllCount ?> คน
                    </span>
                </div>
                <?php if ($diagAllCount > 0 && $diagCount === 0): ?>
                <div class="bg-rose-50 border border-rose-200 rounded-2xl px-5 py-3">
                    <p class="font-bold text-rose-700 text-xs mb-1"><i class="bi bi-info-circle me-1"></i>ห้องที่มีนักเรียนในระบบ:</p>
                    <p class="text-xs text-rose-600 font-mono"><?= implode(', ', array_map('htmlspecialchars', $diagCls2)) ?></p>
                    <p class="text-xs text-rose-600 mt-2">→ ตรวจสอบว่าชื่อห้องในวิชา <strong>"<?= htmlspecialchars($diagCls) ?>"</strong> ตรงกับห้องของนักเรียนหรือไม่</p>
                </div>
                <?php elseif ($diagAllCount === 0): ?>
                <div class="bg-rose-50 border border-rose-200 rounded-2xl px-5 py-3 text-xs text-rose-700">
                    <i class="bi bi-database-x me-1"></i>ยังไม่มีนักเรียนในระบบเลย —
                    <a href="import_students.php" class="font-black underline">นำเข้าข้อมูลนักเรียน CSV</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
    // คาบ → เวลาเริ่ม (คาบ 1-4, พักเที่ยง, คาบ 5-8 เริ่ม 13:00)
    const periodTimes = {
        1: '08:40', 2: '09:30', 3: '10:20', 4: '11:10',
        5: '13:00', 6: '13:50', 7: '14:40', 8: '15:30'
    };

    function updateStartTime(period) {
        const timeInput = document.querySelector('input[name="start_time"]');
        if (timeInput && periodTimes[period]) {
            timeInput.value = periodTimes[period];
        }
    }

    function getCurrentTime() {
        const now = new Date();
        return String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
    }

    function onStatusChange(studentId, status) {
        // Change row color
        const row = document.getElementById('row-' + studentId);
        if (row) row.className = row.className.replace(/row-\S+/g, 'row-' + status);
        
        // Auto-time for "มา"
        const timeInput = document.getElementById('time_in_' + studentId);
        if (status === 'มา' && timeInput && !timeInput.value) {
            timeInput.value = getCurrentTime();
        } else if ((status === 'ขาด' || status === 'ลา') && timeInput) {
            timeInput.value = '';
        }
        
        updateCounts();
    }

    function syncFromMorningAssembly() {
        let synced = 0;
        document.querySelectorAll('tr[id^="row-"]').forEach(row => {
            const sid = row.id.replace('row-', '');
            const mStatus = row.getAttribute('data-morning-status') || '';
            const mNote   = row.getAttribute('data-morning-note') || '';
            if (['ขาด', 'ลา', 'โดด'].includes(mStatus)) {
                const radio = row.querySelector(`input[type="radio"][value="${mStatus}"]`);
                if (radio) {
                    radio.checked = true;
                    onStatusChange(sid, mStatus);
                    const noteInput = row.querySelector(`input[name="note[${sid}]"]`);
                    if (noteInput && !noteInput.value && mNote) {
                        noteInput.value = 'แถวเช้า: ' + mNote;
                    }
                    synced++;
                }
            }
        });
        updateCounts();
        Swal.fire({
            icon: 'success',
            title: `ดึงสถานะขาด/ลาจากแถวเช้า ${synced} คนแล้ว`,
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    function checkAllPresent() {
        const now = getCurrentTime();
        document.querySelectorAll('input[type="radio"][value="มา"]').forEach(r => {
            r.checked = true;
            const sid = r.name.match(/\[(.+)\]/)?.[1];
            if (sid) {
                const row = document.getElementById('row-' + sid);
                if (row) row.className = row.className.replace(/row-\S+/g, 'row-มา');
                const t = document.getElementById('time_in_' + sid);
                if (t && !t.value) t.value = now;
            }
        });
        updateCounts();
        Swal.fire({ icon: 'success', title: 'เช็คมาให้ทั้งหมดแล้ว', timer: 1000, showConfirmButton: false, toast: true, position: 'top-end' });
    }

    function resetAll() {
        document.querySelectorAll('input[type="radio"]').forEach(r => r.checked = false);
        document.querySelectorAll('input[type="time"]').forEach(t => t.value = '');
        document.querySelectorAll('tr[id^="row-"]').forEach(row => { row.className = row.className.replace(/row-\S+/g, ''); });
        updateCounts();
    }

    function updateCounts() {
        let present = 0, absent = 0, other = 0;
        document.querySelectorAll('input[type="radio"]:checked').forEach(r => {
            if (r.value === 'มา') present++;
            else if (r.value === 'ขาด') absent++;
            else other++;
        });
        document.getElementById('count-มา').textContent = 'มา ' + present;
        document.getElementById('count-ขาด').textContent = 'ขาด ' + absent;
        document.getElementById('count-อื่น').textContent = 'อื่นๆ ' + other;
    }

    let isSubmittingAttendance = false;
    function confirmSave(e) {
        e.preventDefault();
        if (isSubmittingAttendance) return false;

        let absent=0, skip=0;
        document.querySelectorAll('input[type="radio"]:checked').forEach(r => { 
            if(r.value==='ขาด') absent++; 
            if(r.value==='โดด') skip++;
        });
        
        let msg = 'ยืนยันการบันทึกการเช็คชื่อทั้งหมด?';
        if (absent > 0 || skip > 0) {
            msg = `มีนักเรียน <span class="text-rose-600 font-bold">ขาด ${absent} คน</span> และ <span class="text-purple-600 font-bold">โดด ${skip} คน</span><br>ยืนยันการบันทึกข้อมูลหรือไม่?`;
        }

        Swal.fire({
            title: 'ยืนยันการบันทึก',
            html: msg,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'บันทึกข้อมูล',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#2563eb',
            borderRadius: '20px'
        }).then(r => {
            if(r.isConfirmed && !isSubmittingAttendance) {
                isSubmittingAttendance = true;
                const saveBtn = document.getElementById('save-btn');
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
                Swal.fire({ title: 'กำลังดำเนินการ...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                document.getElementById('attendance-form').submit();
            }
        });
        return false;
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateCounts();
        if(document.getElementById('subject-select')){
            new TomSelect('#subject-select', {
                create: false,
                sortField: false,
                placeholder: '-- ค้นหารายวิชา... --'
            });
        }
    });
</script>

<?php 
if ($success_msg) {
    echo "<script>Swal.fire({ icon: 'success', title: 'สำเร็จ', text: '$success_msg', timer: 2000, showConfirmButton: false });</script>";
}
if ($error_msg) {
    echo "<script>Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: '$error_msg' });</script>";
}
require_once '../components/layout_end.php'; 
?>
