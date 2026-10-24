<?php
/**
 * homeroom/log.php — สมุดบันทึกโฮมรูม (Platinum Edition)
 * ระบบบันทึกหัวข้อกิจกรรม, ดึงข้อมูลการเช็คชื่อเข้าแถวอัตโนมัติ และตรวจสอบสถิติรายสัปดาห์ตามภาคเรียนจริง
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}

$pdo = getPdo();
llw_ensure_semester_schema($pdo);

$active_year   = llw_current_academic_year();
$system_sem    = llw_current_semester();
$selected_sem  = isset($_GET['semester']) && in_array((int)$_GET['semester'], [1, 2], true)
    ? (int)$_GET['semester']
    : $system_sem;
$cur_term_info = llw_get_term_dates($active_year, $selected_sem, $pdo);

// Allow Super Admin to switch active semester directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_active_semester' && ($_SESSION['llw_role'] ?? '') === 'super_admin') {
    csrf_verify();
    $targetSem  = max(1, min(2, (int)($_POST['semester'] ?? 2)));
    $targetYear = (int)($_POST['academic_year'] ?? llw_current_academic_year());
    $tDef       = llw_default_term_dates($targetYear, $targetSem);
    $pdo->exec("UPDATE llw_academic_terms SET is_active = 0");
    $up = $pdo->prepare("
        INSERT INTO llw_academic_terms (academic_year, semester, start_date, end_date, is_active)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE is_active = 1
    ");
    $up->execute([$targetYear, $targetSem, $tDef['start_date'], $tDef['end_date']]);
    $redir = "log.php?semester={$targetSem}&switched=1" . (!empty($_GET['classroom']) ? '&classroom=' . urlencode($_GET['classroom']) : '');
    header("Location: " . $redir);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['llw_role'];

// Get classrooms for this teacher
$stmt = $pdo->prepare("SELECT classroom FROM llw_class_advisors WHERE user_id = ? ORDER BY classroom");
$stmt->execute([$userId]);
$myClasses = $stmt->fetchAll(PDO::FETCH_COLUMN);

$isAdmin = $userRole === 'super_admin';
$targetClass = $_GET['classroom'] ?? ($myClasses[0] ?? '');

$defaultDate = $_GET['date'] ?? date('Y-m-d');

$pageTitle = "สมุดบันทึกโฮมรูม (ภาคเรียนที่ {$selected_sem}/{$active_year})";
$pageSubtitle = 'ระบบบันทึกกิจกรรมโฮมรูมและดึงข้อมูลเช็คชื่อจากระบบเข้าแถวอัตโนมัติ';
$activeSystem = 'homeroom';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- ─── Semester Control Bar (ภาคเรียนที่ 1 / 2) ─── -->
<div class="bg-white rounded-2xl shadow-lg shadow-indigo-100/40 p-4 mb-6 border border-indigo-200/60 flex flex-wrap items-center justify-between gap-4">
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-black text-xs shadow-sm">
            <i class="bi bi-calendar3-range-fill"></i>
            <span>ปีการศึกษา <?= $active_year ?></span>
        </div>
        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200">
            <a href="?semester=1<?= !empty($targetClass) ? '&classroom=' . urlencode($targetClass) : '' ?>" id="sem-pill-1"
               class="px-4 py-1.5 rounded-lg text-xs font-black transition-all flex items-center gap-1.5 <?= $selected_sem === 1 ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white' ?>">
                <i class="bi bi-1-circle-fill"></i> ภาคเรียนที่ 1
                <?php if ($system_sem === 1): ?><span class="px-1.5 py-0.2 rounded bg-white/20 text-[10px]">เทอมปัจจุบัน</span><?php endif; ?>
            </a>
            <a href="?semester=2<?= !empty($targetClass) ? '&classroom=' . urlencode($targetClass) : '' ?>" id="sem-pill-2"
               class="px-4 py-1.5 rounded-lg text-xs font-black transition-all flex items-center gap-1.5 <?= $selected_sem === 2 ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white' ?>">
                <i class="bi bi-2-circle-fill"></i> ภาคเรียนที่ 2
                <?php if ($system_sem === 2): ?><span class="px-1.5 py-0.2 rounded bg-white/20 text-[10px]">เทอมปัจจุบัน</span><?php endif; ?>
            </a>
        </div>
        <span class="text-xs font-bold text-slate-500">
            <i class="bi bi-clock-history text-indigo-500 mr-1"></i>ช่วงวันที่เทอม <?= $selected_sem ?>:
            <strong class="text-slate-700"><?= date('d/m/Y', strtotime($cur_term_info['start_date'])) ?> – <?= date('d/m/Y', strtotime($cur_term_info['end_date'])) ?></strong>
        </span>
    </div>

    <?php if (($_SESSION['llw_role'] ?? '') === 'super_admin'): ?>
    <div class="flex items-center gap-2">
        <?php if ($system_sem !== 2): ?>
        <form method="POST" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_active_semester">
            <input type="hidden" name="semester" value="2">
            <input type="hidden" name="academic_year" value="<?= $active_year ?>">
            <button type="submit" class="px-3.5 py-2 rounded-xl text-xs font-black bg-emerald-600 text-white hover:bg-emerald-700 transition shadow-sm flex items-center gap-1.5">
                <i class="bi bi-lightning-charge-fill"></i> ตั้ง "ภาคเรียนที่ 2" เป็นเทอมปัจจุบันของระบบทันที
            </button>
        </form>
        <?php else: ?>
        <form method="POST" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_active_semester">
            <input type="hidden" name="semester" value="1">
            <input type="hidden" name="academic_year" value="<?= $active_year ?>">
            <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 transition flex items-center gap-1">
                <i class="bi bi-arrow-counterclockwise"></i> สลับกลับเป็นเทอม 1
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch animate-fade-in">
    
    <!-- LEFT COLUMN: Student List (Auto-Synced from Assembly Attendance) -->
    <div class="lg:col-span-4 space-y-6">
        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden h-full flex flex-col">
            <div class="p-5 border-b border-slate-100 flex items-center gap-3 bg-slate-50/50">
                <div class="w-11 h-11 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center shadow-sm flex-shrink-0">
                    <i class="bi bi-cloud-check-fill text-xl"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-black text-slate-800 leading-tight truncate">ข้อมูลเช็คชื่อเข้าแถว</h3>
                    <p class="text-[11px] font-bold text-slate-400">ดึงอัตโนมัติจากระบบเช็คชื่อหน้าเสาธง</p>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" onclick="loadWorkdesk()" title="ดึงข้อมูลเช็คชื่อเข้าแถวใหม่"
                            class="w-8 h-8 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-200 flex items-center justify-center transition-all shadow-sm">
                        <i class="bi bi-arrow-clockwise text-sm"></i>
                    </button>
                    <span id="att-count" class="px-2.5 py-1 bg-indigo-50 text-indigo-600 text-xs font-black rounded-lg whitespace-nowrap">0 คน</span>
                </div>
            </div>

            <!-- Auto-Sync Status Banner -->
            <div id="assembly-sync-banner" class="px-4 pt-3 pb-1"></div>

            <!-- Summary of Absent/Leave/Skip Students (if any) -->
            <div id="absent-highlight-box" class="px-4 pb-2 hidden"></div>
            
            <div class="max-h-[calc(100vh-380px)] overflow-y-auto p-2 custom-scrollbar flex-1" id="student-list">
                <div class="py-20 text-center text-slate-400 opacity-50">
                    <i class="bi bi-search text-4xl block mb-3"></i>
                    <p class="text-xs font-bold">เลือกวันที่เพื่อดึงรายชื่อจากการเช็คเข้าแถว</p>
                </div>
            </div>
            
            <div class="p-4 bg-slate-50/80 border-t border-slate-100">
                <div class="grid grid-cols-4 gap-1">
                    <div class="text-center">
                        <div class="text-xs font-black text-emerald-500 uppercase">มา</div>
                        <div id="count-มา" class="text-sm font-black text-slate-700">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs font-black text-rose-500 uppercase">ขาด</div>
                        <div id="count-ขาด" class="text-sm font-black text-slate-700">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs font-black text-amber-500 uppercase">ลา</div>
                        <div id="count-ลา" class="text-sm font-black text-slate-700">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs font-black text-indigo-500 uppercase">โดด/สาย</div>
                        <div id="count-โดด" class="text-sm font-black text-slate-700">0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Column 2: Activity Form -->
    <div class="lg:col-span-5 space-y-6">
        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden h-full flex flex-col">
            <div class="p-6 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-indigo-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-200">
                        <i class="bi bi-file-earmark-plus-fill text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-slate-800 leading-tight">บันทึกกิจกรรมประจำวัน</h2>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Daily Activity Log</p>
                    </div>
                </div>
                <span id="current-week-badge" class="px-3 py-1.5 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-black border border-indigo-100">
                    สัปดาห์ที่ -
                </span>
            </div>
            
            <form id="hr-form" class="p-8 space-y-6">
                <!-- Select Class & Date -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 block">ห้องเรียน</label>
                        <select id="sel-class" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <?php 
                            $classesToLink = $pdo->query("SELECT DISTINCT classroom FROM att_students WHERE classroom IS NOT NULL AND classroom != '' ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);
                            if (empty($classesToLink)) {
                                $classesToLink = $pdo->query("SELECT DISTINCT classroom FROM assembly_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);
                            }
                            foreach ($classesToLink as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $c === $targetClass ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 block">วันที่กิจกรรม</label>
                        <input type="date" id="sel-date" value="<?= htmlspecialchars($defaultDate) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                </div>

                <!-- Topic -->
                <div>
                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 block">เรื่องที่แจ้งนักเรียน / สรุปกิจกรรม</label>
                    <textarea id="log-topic" rows="8" placeholder="เช่น แจ้งเรื่องการสอบกลางภาค, ตรวจเครื่องแบบนักเรียน, ติดตามนักเรียนขาดเรียน..." class="w-full bg-slate-50 border border-slate-200 rounded-3xl px-6 py-5 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500 transition-all resize-none"></textarea>
                </div>

                <!-- Photo Upload -->
                <div>
                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 block">รูปภาพประกอบกิจกรรม</label>
                    <div id="photo-preview-container" class="mb-3 hidden">
                        <div class="relative w-full aspect-video rounded-3xl overflow-hidden border border-slate-200 shadow-inner group">
                            <img id="photo-preview" src="" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <button type="button" onclick="clearPhoto()" class="bg-rose-500 text-white px-4 py-2 rounded-xl text-xs font-black">
                                    <i class="bi bi-trash me-1"></i> ลบรูปภาพ
                                </button>
                            </div>
                        </div>
                    </div>
                    <label id="upload-label" class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-slate-200 rounded-3xl cursor-pointer hover:bg-slate-50 hover:border-indigo-300 transition-all">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <i class="bi bi-cloud-arrow-up text-3xl text-slate-300 mb-2"></i>
                            <p class="text-xs font-bold text-slate-400">คลิกเพื่ออัปโหลดรูปภาพกิจกรรม</p>
                        </div>
                        <input type="file" id="log-photo" accept="image/*" class="hidden" onchange="previewPhoto(this)">
                    </label>
                </div>

                <!-- Advisor Info -->
                <div class="bg-indigo-50/50 rounded-2xl p-4 border border-indigo-100/50">
                    <p class="text-xs font-black text-indigo-400 uppercase tracking-widest mb-2">ครูที่ปรึกษา</p>
                    <div id="advisor-names" class="text-xs font-bold text-indigo-700">กำลังโหลด...</div>
                </div>

                <button type="submit" class="w-full bg-indigo-600 text-white py-4 rounded-2xl font-black shadow-xl shadow-indigo-200 hover:scale-[1.02] active:scale-100 transition-all flex items-center justify-center gap-3">
                    <i class="bi bi-check-circle-fill"></i> บันทึกกิจกรรมโฮมรูม
                </button>
            </form>
        </div>
    </div>

    <!-- Column 3: Weekly Summary -->
    <div class="lg:col-span-3 space-y-6">
        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden h-full flex flex-col">
            <div class="p-6 border-b border-slate-50 flex items-center gap-4 bg-slate-50/50">
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center shadow-sm">
                    <i class="bi bi-file-earmark-text text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800 leading-tight">สรุปรายสัปดาห์</h3>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">ภาคเรียนที่ <?= $selected_sem ?>/<?= $active_year ?> (20 สัปดาห์)</p>
                </div>
            </div>
            
            <div class="p-4 space-y-2 max-h-[calc(100vh-200px)] overflow-y-auto custom-scrollbar" id="week-list">
                <!-- Weekly items injected here -->
            </div>
        </div>
    </div>
</div>

<style>
.att-radio { display: none; }
.att-label { 
    display: flex; align-items: center; justify-content: center; 
    width: 2.1rem; height: 2.1rem; border-radius: 0.75rem; 
    font-size: 0.68rem; font-weight: 900; color: #94a3b8; 
    background: #f8fafc; border: 2px solid transparent; 
    cursor: pointer; transition: all 0.2s;
}
.att-radio:checked + .att-label.label-มา { background: #ecfdf5; color: #10b981; border-color: #10b981; box-shadow: 0 4px 12px rgba(16,185,129,0.2); }
.att-radio:checked + .att-label.label-ขาด { background: #fef2f2; color: #f43f5e; border-color: #f43f5e; box-shadow: 0 4px 12px rgba(244,63,94,0.2); }
.att-radio:checked + .att-label.label-ลา { background: #fffbeb; color: #f59e0b; border-color: #f59e0b; box-shadow: 0 4px 12px rgba(245,158,11,0.2); }
.att-radio:checked + .att-label.label-โดด { background: #f5f3ff; color: #8b5cf6; border-color: #8b5cf6; box-shadow: 0 4px 12px rgba(139,92,246,0.2); }
</style>

<script>
const SELECTED_SEMESTER = <?= (int)$selected_sem ?>;
const ACTIVE_YEAR       = <?= (int)$active_year ?>;
let currentData = null;
let attendanceModified = false;

document.addEventListener('DOMContentLoaded', () => {
    loadWorkdesk();

    document.getElementById('sel-class').onchange = loadWorkdesk;
    document.getElementById('sel-date').onchange = loadWorkdesk;
    document.getElementById('hr-form').onsubmit = saveHomeroom;
});

async function loadWorkdesk() {
    const classroom = document.getElementById('sel-class').value;
    const date = document.getElementById('sel-date').value;
    attendanceModified = false;
    
    // Calculate Monday and Friday of the selected date's week
    const d = new Date(date + 'T00:00:00');
    const day = d.getDay(); // 0: Sun, 1: Mon, ..., 6: Sat
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    const monday = new Date(d.setDate(diff));
    const startOfWeek = formatDate(monday);
    const friday = new Date(monday);
    friday.setDate(friday.getDate() + 4);
    const endOfWeekStr = formatDate(friday);

    const listContainer = document.getElementById('student-list');
    listContainer.innerHTML = '<div class="py-20 text-center text-slate-400"><div class="animate-spin w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full mx-auto mb-3"></div>กำลังดึงข้อมูลจากการเช็คชื่อเข้าแถว...</div>';

    try {
        const url = `/homeroom/api/get_log_data.php?classroom=${encodeURIComponent(classroom)}&start_date=${startOfWeek}&end_date=${endOfWeekStr}&date=${encodeURIComponent(date)}&semester=${SELECTED_SEMESTER}&academic_year=${ACTIVE_YEAR}`;
        const r = await fetch(url);
        const res = await r.json();
        
        if (res.status === 'success') {
            currentData = res.data;
            renderStudents(date, classroom);
            renderForm(date);
            renderWeeklySummary();
            updateStats();
        } else {
            listContainer.innerHTML = `<div class="py-20 text-center text-rose-500 font-bold">${esc(res.message || 'ไม่สามารถโหลดข้อมูลได้')}</div>`;
        }
    } catch (e) {
        console.error(e);
        listContainer.innerHTML = '<div class="py-20 text-center text-rose-400 font-bold">ไม่สามารถโหลดข้อมูลได้</div>';
    }
}

function renderStudents(date, classroom) {
    const container = document.getElementById('student-list');
    const bannerEl  = document.getElementById('assembly-sync-banner');
    const absentBox = document.getElementById('absent-highlight-box');

    const students   = currentData.students || [];
    const attendance = currentData.attendance || {};
    const notes      = currentData.notes || {};
    const checkedCnt = (currentData.checked_by_date && currentData.checked_by_date[date]) ? currentData.checked_by_date[date] : 0;
    const updatedAt  = (currentData.updated_by_date && currentData.updated_by_date[date]) ? currentData.updated_by_date[date] : '';

    if (!students.length) {
        bannerEl.innerHTML = '';
        absentBox.classList.add('hidden');
        container.innerHTML = '<div class="py-20 text-center text-slate-400 font-bold">ไม่พบข้อมูลนักเรียนในห้องนี้</div>';
        return;
    }

    // 1. Render Auto-Sync Banner
    if (checkedCnt > 0) {
        const timeStr = updatedAt ? ` (อัปเดตล่าสุด ${updatedAt.substring(11, 16)} น.)` : '';
        bannerEl.innerHTML = `
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-3 flex items-start gap-2.5">
                <i class="bi bi-check-circle-fill text-emerald-600 text-base mt-0.5"></i>
                <div class="text-xs leading-relaxed">
                    <p class="font-black text-emerald-800">ดึงข้อมูลจากการเช็คชื่อเข้าแถวอัตโนมัติแล้ว!</p>
                    <p class="text-emerald-700 font-medium">เช็คแล้ว ${checkedCnt}/${students.length} คน${esc(timeStr)} — <b>ไม่ต้องเช็คชื่อซ้ำ</b> (กดเปลี่ยนเฉพาะคนที่ต้องการแก้ไขได้)</p>
                </div>
            </div>
        `;
    } else {
        bannerEl.innerHTML = `
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-3 flex items-start justify-between gap-2">
                <div class="flex items-start gap-2.5 text-xs leading-relaxed">
                    <i class="bi bi-info-circle-fill text-amber-500 text-base mt-0.5"></i>
                    <div>
                        <p class="font-black text-amber-800">วันที่เลือกยังไม่มีการเช็คชื่อเข้าแถว</p>
                        <p class="text-amber-700 font-medium">ระบบตั้งค่าเริ่มต้นเป็น "มา" ครบทุกคน หรือไปเช็คที่หน้าระบบเข้าแถวก่อนได้</p>
                    </div>
                </div>
                <a href="/assembly/dashboard.php?classroom=${encodeURIComponent(classroom)}&date=${encodeURIComponent(date)}"
                   class="px-2.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-[11px] font-black whitespace-nowrap shadow-sm transition">
                    ไปหน้าเข้าแถว
                </a>
            </div>
        `;
    }

    // 2. Highlight Absent / Leave / Skip Students at the Top
    const nonPresent = [];
    students.forEach((s) => {
        let st = (attendance[s.student_id] && attendance[s.student_id][date]) || 'มา';
        if (st === 'สาย') st = 'โดด';
        if (st !== 'มา') {
            nonPresent.push({ student: s, status: st, note: (notes[s.student_id] && notes[s.student_id][date]) || '' });
        }
    });

    if (nonPresent.length > 0) {
        absentBox.classList.remove('hidden');
        const badges = nonPresent.map(item => {
            const badgeColor = item.status === 'ขาด'
                ? 'bg-rose-100 text-rose-700 border-rose-200'
                : (item.status === 'ลา' ? 'bg-amber-100 text-amber-700 border-amber-200' : 'bg-purple-100 text-purple-700 border-purple-200');
            const noteTxt = item.note ? ` (${esc(item.note)})` : '';
            return `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl border text-[11px] font-bold ${badgeColor}">
                        <b>${esc(item.status)}:</b> ${esc(item.student.name)}${noteTxt}
                    </span>`;
        }).join(' ');
        absentBox.innerHTML = `
            <div class="bg-rose-50/60 border border-rose-100 rounded-2xl p-3 mt-1">
                <p class="text-[11px] font-black text-rose-600 uppercase tracking-wider mb-1.5">
                    <i class="bi bi-person-exclamation mr-1"></i>นักเรียนที่ไม่ได้มาเข้าแถววันนี้ (${nonPresent.length} คน)
                </p>
                <div class="flex flex-wrap gap-1.5">${badges}</div>
            </div>
        `;
    } else {
        absentBox.classList.add('hidden');
        absentBox.innerHTML = '';
    }

    // 3. Render Student Rows
    let html = '';
    students.forEach((s, i) => {
        let currentStatus = (attendance[s.student_id] && attendance[s.student_id][date]) || 'มา';
        if (currentStatus === 'สาย') currentStatus = 'โดด';
        const stNote = (notes[s.student_id] && notes[s.student_id][date]) ? notes[s.student_id][date] : '';
        const isNotPresent = currentStatus !== 'มา';

        html += `
        <div class="flex items-center justify-between p-3 rounded-2xl ${isNotPresent ? 'bg-rose-50/40' : 'hover:bg-slate-50'} transition-all border-b border-slate-50 last:border-0 group">
            <div class="flex items-center gap-2.5 min-w-0 pr-2">
                <span class="w-6 h-6 bg-slate-100 text-slate-400 rounded-lg flex items-center justify-center text-xs font-black flex-shrink-0 group-hover:bg-indigo-50 group-hover:text-indigo-500 transition-all">${i + 1}</span>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-700 truncate">${esc(s.name)}</p>
                    <p class="text-[11px] text-slate-400 font-bold tracking-tighter">
                        ${esc(s.student_id)}
                        ${stNote ? `<span class="text-amber-600 ml-1">· ${esc(stNote)}</span>` : ''}
                    </p>
                </div>
            </div>
            <div class="flex gap-1 student-att-row flex-shrink-0" data-id="${esc(s.student_id)}">
                ${['มา', 'ขาด', 'ลา', 'โดด'].map(st => `
                    <input type="radio" name="att_${esc(s.student_id)}" id="att_${esc(s.student_id)}_${st}" value="${st}" class="att-radio" ${currentStatus === st ? 'checked' : ''} onchange="onManualAttendanceChange()">
                    <label for="att_${esc(s.student_id)}_${st}" class="att-label label-${st}">${st}</label>
                `).join('')}
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function onManualAttendanceChange() {
    attendanceModified = true;
    updateStats();
}

function renderForm(date) {
    const topic = (currentData.logs && currentData.logs[date]) || '';
    const photo = (currentData.photos && currentData.photos[date]) || '';
    const advisors = currentData.advisors || [];
    const activeWeek = currentData.active_week || 1;

    document.getElementById('log-topic').value = topic;
    document.getElementById('current-week-badge').innerText = `สัปดาห์ที่ ${activeWeek} (เทอม ${SELECTED_SEMESTER})`;
    
    if (photo) {
        document.getElementById('photo-preview').src = photo;
        document.getElementById('photo-preview-container').classList.remove('hidden');
        document.getElementById('upload-label').classList.add('hidden');
    } else {
        clearPhoto();
    }

    const advText = advisors.length ? advisors.map(a => `ครู${a.firstname} ${a.lastname}`).join(' / ') : 'ยังไม่ได้มอบหมาย';
    document.getElementById('advisor-names').innerText = advText;
}

function renderWeeklySummary() {
    const container  = document.getElementById('week-list');
    const weeks      = currentData.weeks || [];
    const activeWeek = currentData.active_week || 1;
    
    let html = '';
    weeks.forEach((w) => {
        const isCurrent = w.week === activeWeek;
        const hrDays    = w.logged_days || 0;
        const asmDays   = w.assembly_days || 0;
        const doneDays  = Math.max(hrDays, asmDays);

        html += `
        <div id="week-card-${w.week}" onclick="selectWeek('${w.monday}')"
             class="cursor-pointer p-3.5 rounded-2xl ${isCurrent ? 'bg-indigo-50 border-2 border-indigo-400 shadow-sm' : 'bg-slate-50 border border-slate-100 hover:border-indigo-200'} flex items-center justify-between group transition-all">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl ${isCurrent ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500'} flex items-center justify-center text-xs font-black shadow-sm">
                    ${w.week}
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <p class="text-sm font-black ${isCurrent ? 'text-indigo-700' : 'text-slate-700'}">สัปดาห์ที่ ${w.week}</p>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-white text-slate-500 border border-slate-200/70">${esc(w.label_range)}</span>
                    </div>
                    <p class="text-[11px] font-bold text-slate-400 mt-0.5">
                        โฮมรูม ${hrDays}/5 · เข้าแถว ${asmDays}/5 วัน
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill ${doneDays > 0 ? 'text-emerald-500' : 'text-slate-200'} text-lg"></i>
                <button type="button" onclick="event.stopPropagation(); printWeeklyReport('${w.monday}', ${w.week})"
                        title="พิมพ์รายงานสัปดาห์ที่ ${w.week}"
                        class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-300 transition-all shadow-sm">
                    <i class="bi bi-printer text-xs"></i>
                </button>
            </div>
        </div>`;
    });
    container.innerHTML = html;

    // Scroll active week into view smoothly
    const activeEl = document.getElementById(`week-card-${activeWeek}`);
    if (activeEl) {
        activeEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
}

function selectWeek(mondayDateStr) {
    const dateInput = document.getElementById('sel-date');
    if (dateInput.value !== mondayDateStr) {
        dateInput.value = mondayDateStr;
        loadWorkdesk();
    }
}

function updateStats() {
    const statuses = ['มา', 'ขาด', 'ลา', 'โดด'];
    statuses.forEach(st => {
        const count = document.querySelectorAll(`.att-radio[value="${st}"]:checked`).length;
        const el = document.getElementById(`count-${st}`);
        if (el) el.innerText = count;
    });
    document.getElementById('att-count').innerText = document.querySelectorAll('.att-radio:checked').length + ' คน';
}

function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('photo-preview').src = e.target.result;
            document.getElementById('photo-preview-container').classList.remove('hidden');
            document.getElementById('upload-label').classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearPhoto() {
    document.getElementById('log-photo').value = '';
    document.getElementById('photo-preview-container').classList.add('hidden');
    document.getElementById('upload-label').classList.remove('hidden');
}

async function saveHomeroom(e) {
    e.preventDefault();
    const classroom = document.getElementById('sel-class').value;
    const date = document.getElementById('sel-date').value;
    const topic = document.getElementById('log-topic').value.trim();
    const photo = document.getElementById('log-photo').files[0];
    
    const attendance = [];
    document.querySelectorAll('.student-att-row').forEach(row => {
        const sid = row.dataset.id;
        const checkedInput = row.querySelector('input:checked');
        if (checkedInput) {
            attendance.push({ id: sid, status: checkedInput.value });
        }
    });

    const alreadyCheckedInAssembly = (currentData && currentData.checked_by_date && currentData.checked_by_date[date] > 0);
    const shouldSyncAttendance = (!alreadyCheckedInAssembly || attendanceModified) ? '1' : '0';

    const fd = new FormData();
    fd.append('classroom', classroom);
    fd.append('log_date', date);
    fd.append('topic', topic);
    fd.append('attendance', JSON.stringify(attendance));
    fd.append('sync_attendance', shouldSyncAttendance);
    if (photo) fd.append('photo', photo);

    Swal.fire({ title: 'กำลังบันทึกข้อมูล...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    try {
        const r = await fetch('/homeroom/api/save_homeroom.php', { method: 'POST', body: fd });
        const res = await r.json();
        
        if (res.status === 'success') {
            await Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', text: 'บันทึกกิจกรรมโฮมรูมเรียบร้อยแล้ว', timer: 1400, showConfirmButton: false });
            loadWorkdesk();
        } else {
            Swal.fire('ผิดพลาด', res.message || 'ไม่สามารถบันทึกได้', 'error');
        }
    } catch (e) {
        Swal.fire('ผิดพลาด', 'ไม่สามารถบันทึกได้', 'error');
    }
}

function printWeeklyReport(mondayStr, weekNum) {
    const classroom = document.getElementById('sel-class').value;
    window.open(`/homeroom/report_print.php?classroom=${encodeURIComponent(classroom)}&monday=${encodeURIComponent(mondayStr)}&week=${weekNum || ''}&semester=${SELECTED_SEMESTER}`, '_blank');
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str || '');
    return d.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
