<?php
/**
 * homeroom/log.php — สมุดบันทึกโฮมรูม (Platinum Edition)
 * ระบบบันทึกหัวข้อกิจกรรม, อัปเดตรูปภาพ และตรวจสอบสถิติรายสัปดาห์
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}

$pdo = getPdo();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['llw_role'];

// Get classrooms for this teacher
$stmt = $pdo->prepare("SELECT classroom FROM llw_class_advisors WHERE user_id = ? ORDER BY classroom");
$stmt->execute([$userId]);
$myClasses = $stmt->fetchAll(PDO::FETCH_COLUMN);

$isAdmin = in_array($userRole, ['super_admin', 'wfh_admin']);
$targetClass = $_GET['classroom'] ?? ($myClasses[0] ?? '');

$pageTitle = 'สมุดบันทึกโฮมรูม';
$pageSubtitle = 'ระบบบันทึกกิจกรรมและเช็คชื่อที่ปรึกษารายวัน';
$activeSystem = 'homeroom';

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 animate-fade-in">
    
    <!-- LEFT COLUMN: Student List (Attendance) -->
    <div class="lg:col-span-4 space-y-6">
        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-50 flex items-center gap-4 bg-slate-50/50">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center shadow-sm">
                    <i class="bi bi-people-fill text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800 leading-tight">เช็คชื่อนักเรียน</h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Attendance Correction</p>
                </div>
                <div class="ml-auto">
                    <span id="att-count" class="px-2 py-1 bg-indigo-50 text-indigo-600 text-[10px] font-black rounded-lg">0 คน</span>
                </div>
            </div>
            
            <div class="max-h-[calc(100vh-320px)] overflow-y-auto p-2 custom-scrollbar" id="student-list">
                <!-- Students will be injected here -->
                <div class="py-20 text-center text-slate-400 opacity-50">
                    <i class="bi bi-search text-4xl block mb-3"></i>
                    <p class="text-xs font-bold">เลือกวันที่เพื่อดึงรายชื่อ</p>
                </div>
            </div>
            
            <div class="p-4 bg-slate-50/80 border-t border-slate-100">
                <div class="grid grid-cols-4 gap-1">
                    <div class="text-center">
                        <div class="text-[9px] font-black text-emerald-500 uppercase">มา</div>
                        <div id="count-มา" class="text-sm font-black text-slate-700">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-[9px] font-black text-rose-500 uppercase">ขาด</div>
                        <div id="count-ขาด" class="text-sm font-black text-slate-700">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-[9px] font-black text-amber-500 uppercase">ลา</div>
                        <div id="count-ลา" class="text-sm font-black text-slate-700">0</div>
                    </div>
                    <div class="text-center">
                        <div class="text-[9px] font-black text-indigo-500 uppercase">สาย</div>
                        <div id="count-สาย" class="text-sm font-black text-slate-700">0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Column 2: Activity Form -->
    <div class="lg:col-span-5 space-y-6">
        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-50 bg-slate-50/50 flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-200">
                    <i class="bi bi-file-earmark-plus-fill text-xl"></i>
                </div>
                <div>
                    <h2 class="text-lg font-black text-slate-800 leading-tight">บันทึกกิจกรรมประจำวัน</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Daily Activity Log</p>
                </div>
            </div>
            
            <form id="hr-form" class="p-8 space-y-6">
                <!-- Select Class & Date -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ห้องเรียน</label>
                        <select id="sel-class" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <?php 
                            $classesToLink = $isAdmin ? $pdo->query("SELECT DISTINCT classroom FROM llw_class_advisors ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN) : $myClasses;
                            foreach ($classesToLink as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $c === $targetClass ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">วันที่กิจกรรม</label>
                        <input type="date" id="sel-date" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                </div>

                <!-- Topic -->
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">เรื่องที่แจ้งนักเรียน / สรุปกิจกรรม</label>
                    <textarea id="log-topic" rows="8" placeholder="เช่น แจ้งเรื่องการสอบกลางภาค, ตรวจเครื่องแบบนักเรียน..." class="w-full bg-slate-50 border border-slate-200 rounded-3xl px-6 py-5 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500 transition-all resize-none"></textarea>
                </div>

                <!-- Photo Upload -->
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">รูปภาพประกอบกิจกรรม</label>
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
                    <p class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-2">ครูที่ปรึกษา</p>
                    <div id="advisor-names" class="text-xs font-bold text-indigo-700">กำลังโหลด...</div>
                </div>

                <button type="submit" class="w-full bg-indigo-600 text-white py-4 rounded-2xl font-black shadow-xl shadow-indigo-200 hover:scale-[1.02] active:scale-100 transition-all flex items-center justify-center gap-3">
                    <i class="bi bi-check-circle-fill"></i> บันทึกข้อมูลทั้งหมด
                </button>
            </form>
        </div>
    </div>

    <!-- Column 3: Weekly Summary -->
    <div class="lg:col-span-3 space-y-6">
        <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden sticky top-24">
            <div class="p-6 border-b border-slate-50 flex items-center gap-4 bg-slate-50/50">
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center shadow-sm">
                    <i class="bi bi-file-earmark-text text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800 leading-tight">สรุปรายงาน</h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Weekly Logbook Status</p>
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
    width: 2.2rem; height: 2.2rem; border-radius: 0.8rem; 
    font-size: 0.7rem; font-weight: 900; color: #94a3b8; 
    background: #f8fafc; border: 2px solid transparent; 
    cursor: pointer; transition: all 0.2s;
}
.att-radio:checked + .att-label.label-มา { background: #ecfdf5; color: #10b981; border-color: #10b981; box-shadow: 0 4px 12px rgba(16,185,129,0.2); }
.att-radio:checked + .att-label.label-ขาด { background: #fef2f2; color: #f43f5e; border-color: #f43f5e; box-shadow: 0 4px 12px rgba(244,63,94,0.2); }
.att-radio:checked + .att-label.label-ลา { background: #fffbeb; color: #f59e0b; border-color: #f59e0b; box-shadow: 0 4px 12px rgba(245,158,11,0.2); }
.att-radio:checked + .att-label.label-สาย { background: #f5f3ff; color: #8b5cf6; border-color: #8b5cf6; box-shadow: 0 4px 12px rgba(139,92,246,0.2); }
</style>

<script>
let currentData = null;

document.addEventListener('DOMContentLoaded', () => {
    // Start by loading data for the selected class/date
    loadWorkdesk();

    // Event listeners
    document.getElementById('sel-class').onchange = loadWorkdesk;
    document.getElementById('sel-date').onchange = loadWorkdesk;
    document.getElementById('hr-form').onsubmit = saveHomeroom;
});

async function loadWorkdesk() {
    const classroom = document.getElementById('sel-class').value;
    const date = document.getElementById('sel-date').value;
    
    // Calculate Monday of the week for the summary
    const d = new Date(date);
    const day = d.getDay() || 7;
    if (day !== 1) d.setHours(-24 * (day - 1));
    const startOfWeek = d.toISOString().split('T')[0];
    const endOfWeek = new Date(d);
    endOfWeek.setDate(endOfWeek.getDate() + 4);
    const endOfWeekStr = endOfWeek.toISOString().split('T')[0];

    // Show loading
    const listContainer = document.getElementById('student-list');
    listContainer.innerHTML = '<div class="py-20 text-center text-slate-400"><div class="animate-spin w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full mx-auto mb-3"></div>กำลังโหลดข้อมูล...</div>';

    try {
        const r = await fetch(`/homeroom/api/get_log_data.php?classroom=${encodeURIComponent(classroom)}&start_date=${startOfWeek}&end_date=${endOfWeekStr}`);
        const res = await r.json();
        
        if (res.status === 'success') {
            currentData = res.data;
            renderStudents(date);
            renderForm(date);
            renderWeeklySummary(startOfWeek);
            updateStats();
        }
    } catch (e) {
        console.error(e);
        listContainer.innerHTML = '<div class="py-20 text-center text-rose-400 font-bold">ไม่สามารถโหลดข้อมูลได้</div>';
    }
}

function renderStudents(date) {
    const container = document.getElementById('student-list');
    const students = currentData.students;
    const attendance = currentData.attendance;
    
    if (!students.length) {
        container.innerHTML = '<div class="py-20 text-center text-slate-400 font-bold">ไม่พบข้อมูลนักเรียน</div>';
        return;
    }

    let html = '';
    students.forEach((s, i) => {
        const currentStatus = (attendance[s.student_id] && attendance[s.student_id][date]) || 'มา';
        html += `
        <div class="flex items-center justify-between p-3.5 rounded-2xl hover:bg-slate-50 transition-all border-b border-slate-50 last:border-0 group">
            <div class="flex items-center gap-3">
                <span class="w-6 h-6 bg-slate-100 text-slate-400 rounded-lg flex items-center justify-center text-[10px] font-black group-hover:bg-indigo-50 group-hover:text-indigo-500 transition-all">${i + 1}</span>
                <div>
                    <p class="text-xs font-bold text-slate-700">${esc(s.name)}</p>
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter">${esc(s.student_id)}</p>
                </div>
            </div>
            <div class="flex gap-1.5 student-att-row" data-id="${s.student_id}">
                ${['มา', 'ขาด', 'ลา', 'สาย'].map(st => `
                    <input type="radio" name="att_${s.student_id}" id="att_${s.student_id}_${st}" value="${st}" class="att-radio" ${currentStatus === st ? 'checked' : ''} onchange="updateStats()">
                    <label for="att_${s.student_id}_${st}" class="att-label label-${st}">${st}</label>
                `).join('')}
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function renderForm(date) {
    const topic = currentData.logs[date] || '';
    const photo = currentData.photos[date] || '';
    const advisors = currentData.advisors;

    document.getElementById('log-topic').value = topic;
    
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

function renderWeeklySummary(mondayStr) {
    const container = document.getElementById('week-list');
    const logs = currentData.logs || {};
    
    let html = '';
    const monday = new Date(mondayStr);
    
    // We show 20 weeks, but calculate status for the visible ones
    for (let i = 1; i <= 20; i++) {
        // Simple logic: If we have at least one log entry in the current visible week range, 
        // we can mark it as somewhat active. 
        // For a more precise "Weekly" view, we'd need a broader API call.
        // But for now, let's focus on the CURRENT week (Week 10 placeholder)
        
        const isCurrent = i === 10; // Still a placeholder for week number calculation
        
        // Count how many days in this week have logs (based on the current data)
        const loggedDays = Object.keys(logs).length;
        const isComplete = loggedDays >= 3; // Example: 3/5 days done
        
        html += `
        <div class="p-3.5 rounded-2xl ${isCurrent ? 'bg-indigo-50 border border-indigo-100' : 'bg-slate-50 border border-slate-100'} flex items-center justify-between group transition-all">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl ${isCurrent ? 'bg-indigo-600 text-white' : 'bg-white text-slate-400'} flex items-center justify-center text-[10px] font-black shadow-sm">
                    ${i}
                </div>
                <div>
                    <p class="text-[11px] font-black ${isCurrent ? 'text-indigo-600' : 'text-slate-700'}">สัปดาห์ที่ ${i}</p>
                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">${loggedDays}/5 วันที่บันทึกแล้ว</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill ${loggedDays > 0 ? 'text-emerald-500' : 'text-slate-200'} text-lg"></i>
                <button onclick="printWeeklyReport(${i})" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-200 transition-all shadow-sm">
                    <i class="bi bi-printer text-xs"></i>
                </button>
            </div>
        </div>`;
    }
    container.innerHTML = html;
}

function updateStats() {
    const statuses = ['มา', 'ขาด', 'ลา', 'สาย'];
    statuses.forEach(st => {
        const count = document.querySelectorAll(`.att-radio[value="${st}"]:checked`).length;
        document.getElementById(`count-${st}`).innerText = count;
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
    
    // Collect attendance
    const attendance = [];
    document.querySelectorAll('.student-att-row').forEach(row => {
        const sid = row.dataset.id;
        const status = row.querySelector('input:checked').value;
        attendance.push({ id: sid, status: status });
    });

    const fd = new FormData();
    fd.append('classroom', classroom);
    fd.append('log_date', date);
    fd.append('topic', topic);
    fd.append('attendance', JSON.stringify(attendance));
    if (photo) fd.append('photo', photo);

    Swal.fire({ title: 'กำลังบันทึกข้อมูล...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    try {
        const r = await fetch('/homeroom/api/save_homeroom.php', { method: 'POST', body: fd });
        const res = await r.json();
        
        if (res.status === 'success') {
            await Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', text: 'ข้อมูลกิจกรรมและการเช็คชื่อถูกบันทึกแล้ว', timer: 2000, showConfirmButton: false });
            loadWorkdesk(); // Refresh
        } else {
            Swal.fire('ผิดพลาด', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('ผิดพลาด', 'ไม่สามารถบันทึกได้', 'error');
    }
}

function printWeeklyReport(weekNum) {
    const classroom = document.getElementById('sel-class').value;
    const date = document.getElementById('sel-date').value;
    
    // Find the Monday of the current week selected in the date picker
    // (This logic assumes weekNum is relative to the current selection for simplicity, 
    // or you can adjust to anchor to semester start)
    const d = new Date(date);
    const day = d.getDay() || 7;
    if (day !== 1) d.setHours(-24 * (day - 1));
    const monday = d.toISOString().split('T')[0];
    
    window.open(`/homeroom/report_print.php?classroom=${encodeURIComponent(classroom)}&monday=${monday}`, '_blank');
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str || '');
    return d.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
