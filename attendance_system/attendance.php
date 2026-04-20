<?php
require_once 'functions.php';
checkLogin();

$teacher_id = $_SESSION['teacher_id'];
$pageTitle = 'เช็คชื่อรายคาบ';
$pageSubtitle = 'บันทึกข้อมูลการเข้าเรียนของนักเรียน';

$subjects = getTeacherSubjects($teacher_id, $pdo);

$selected_subject_id = $_GET['subject_id'] ?? '';
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_period = $_GET['period'] ?? 1;
$start_time = $_GET['start_time'] ?? '08:40';

$students = [];
$subject_info = null;

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
            
            // Save each student's attendance
            saveAttendance($date, $period, $subject_id, $teacher_id, $sid_code, $status, $time_in, $p_start_time, $note, $pdo);
        }
        $pdo->commit();
        $success_msg = "บันทึกข้อมูลเรียบร้อยแล้ว";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        error_log("Attendance Save Error: " . $e->getMessage());
    }
}

require_once '../components/layout_start.php';
?>

<style>
    .row-มา { background-color: rgba(236, 253, 245, 0.5); }
    .row-ขาด { background-color: rgba(254, 242, 242, 0.8); }
    .row-ลา { background-color: rgba(255, 251, 235, 0.8); }
    .row-โดด { background-color: rgba(250, 245, 255, 0.8); }
    .row-สาย { background-color: rgba(255, 247, 237, 0.8); }
</style>

<div class="flex flex-col gap-6">

    <!-- ── Selection Form ── -->
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 no-print">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
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
                <select name="subject_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none transition-all" required>
                    <option value="">-- เลือกรายวิชา --</option>
                    <?php foreach($subjects as $subj): ?>
                        <option value="<?= $subj['id'] ?>" <?= $selected_subject_id == $subj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subj['subject_code'] . ' - ' . $subj['subject_name'] . ' (' . $subj['classroom'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
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
                  <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?= count($students) ?> คนในชั้นเรียน</p>
                </div>
            </div>
            <div class="flex gap-2">
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
                            <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">รหัส/ชื่อ-สกุล</th>
                            <th class="px-6 py-4 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest">สถานะเช็คชื่อ</th>
                            <th class="px-6 py-4 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest">เวลาเข้า/หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($students as $student): ?>
                            <?php
                                $sid_db_id = $student['id'];
                                $sid_display_code = $student['student_id'];
                                
                                $stmt = $pdo->prepare("SELECT * FROM att_attendance WHERE date = :date AND period = :period AND subject_id = :subject_id AND student_id = :student_id LIMIT 1");
                                $stmt->execute(['date' => $selected_date,'period' => $selected_period,'subject_id' => $selected_subject_id,'student_id' => $sid_db_id]);
                                $record = $stmt->fetch();
                                $cur_status = $record ? $record['status'] : 'มา';
                                $cur_time = $record ? $record['time_in'] : '';
                                $cur_note = $record ? $record['note'] : '';
                            ?>
                            <tr id="row-<?= $sid_db_id ?>" class="row-<?= $cur_status ?> transition-colors duration-200">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-mono font-bold text-blue-600"><?= htmlspecialchars($sid_display_code) ?></span>
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
                                               class="bg-white border border-slate-200 rounded-lg p-1.5 text-[10px] outline-none focus:ring-2 focus:ring-blue-400 w-full">
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
    <?php elseif ($selected_subject_id): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 p-8 rounded-3xl text-center shadow-sm">
            <i class="bi bi-exclamation-triangle-fill text-3xl mb-3 block opacity-50"></i>
            <p class="font-bold">ไม่พบรายชื่อนักเรียนในรายวิชานี้</p>
            <p class="text-xs mt-1">กรุณาตรวจสอบการตั้งค่าวิชาในหน้า Admin หรือนำเข้าข้อมูลนักเรียน</p>
        </div>
    <?php endif; ?>

</div>

<script>
    // คาบ → เวลาเริ่ม (คาบ 1 = 08:40, คาบละ 50 นาที)
    const periodTimes = {
        1: '08:40', 2: '09:30', 3: '10:20', 4: '11:10',
        5: '12:00', 6: '12:50', 7: '13:40', 8: '14:30'
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

    function confirmSave(e) {
        e.preventDefault();
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
            if(r.isConfirmed) {
                Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                document.getElementById('attendance-form').submit();
            }
        });
        return false;
    }

    document.addEventListener('DOMContentLoaded', updateCounts);
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
