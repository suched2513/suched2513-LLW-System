<?php
/**
 * behavior/student_view.php — หน้านักเรียนดูข้อมูลตัวเอง (public / standalone)
 * ไม่ต้อง login ผ่าน llw_users — กรอกรหัสนักเรียนดูได้เลย
 */
session_start();
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบคะแนนพฤติกรรม — LLW System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .blob { position: absolute; filter: blur(80px); z-index: 0; opacity: 0.5; border-radius: 50%; }
        .blob-1 { top: -10%; left: -10%; width: 400px; height: 400px; background: #a78bfa; animation: float 6s infinite ease-in-out; }
        .blob-2 { bottom: -10%; right: -10%; width: 350px; height: 350px; background: #f472b6; animation: float 8s infinite ease-in-out reverse; }
        @keyframes float { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-20px);} }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.4s ease-out forwards; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen relative overflow-x-hidden">

<!-- Background blobs -->
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>

<!-- ═══ LOGIN SCREEN ═══ -->
<div id="loginScreen" class="min-h-screen flex items-center justify-center p-4 relative z-10">
    <div class="bg-white/90 backdrop-blur-xl rounded-[32px] shadow-2xl border border-white/50 p-8 sm:p-10 w-full max-w-md fade-in">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-gradient-to-br from-violet-600 to-indigo-700 rounded-[20px] shadow-xl shadow-violet-200/50 flex items-center justify-center text-white text-2xl font-black mx-auto mb-4">
                LLW
            </div>
            <h2 class="text-2xl font-black text-slate-800">ระบบบันทึกพฤติกรรมนักเรียน</h2>
            <p class="text-sm text-slate-400 mt-1">ตรวจสอบคะแนนความประพฤติ</p>
        </div>

        <form id="studentLoginForm">
            <div class="mb-4">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5 block">รหัสนักเรียน (5 หลัก)</label>
                <input type="text" id="studentLoginId" placeholder="เช่น 12345" required
                    class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-3.5 text-center text-lg font-bold tracking-widest focus:ring-2 focus:ring-violet-500 outline-none transition-all">
            </div>
            <button type="submit" id="btnStudentLogin"
                class="w-full bg-gradient-to-r from-cyan-500 to-blue-600 text-white py-3.5 rounded-2xl font-black shadow-lg shadow-blue-200/50 hover:shadow-blue-300/50 hover:scale-[1.01] transition-all flex items-center justify-center gap-2">
                <i class="bi bi-search"></i>
                <span id="btnStudentLoginText">ตรวจสอบข้อมูล</span>
                <div id="btnStudentLoginSpinner" class="hidden animate-spin w-5 h-5 border-2 border-white/30 border-t-white rounded-full"></div>
            </button>
        </form>

        <div class="text-center mt-5">
            <a href="/login.php" class="text-xs text-slate-400 hover:text-violet-600 transition-colors">
                <i class="bi bi-arrow-left me-1"></i> กลับหน้าหลัก
            </a>
        </div>
    </div>
</div>

<!-- ═══ STUDENT DATA SCREEN ═══ -->
<div id="studentScreen" class="hidden relative z-10">
    <div class="container mx-auto max-w-5xl px-4 py-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-xl font-black text-slate-800">Student Portal</h3>
                <span class="text-sm text-slate-400" id="studentWelcomeText">ยินดีต้อนรับ</span>
            </div>
            <button onclick="handleStudentLogout()" class="bg-rose-50 text-rose-600 px-4 py-2 rounded-xl font-bold text-xs hover:bg-rose-100 transition-all flex items-center gap-2 border border-rose-200">
                <i class="bi bi-box-arrow-right"></i> ออก
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- LEFT: Profile Card -->
            <div class="lg:col-span-4">
                <div class="bg-white rounded-2xl shadow-xl p-6 border border-slate-100 relative overflow-hidden">
                    <div class="h-20 bg-gradient-to-r from-pink-500 to-rose-500 rounded-xl -mx-6 -mt-6 mb-0 relative">
                        <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 w-20 h-20 rounded-full border-4 border-white bg-white shadow-lg overflow-hidden group">
                            <img id="stuViewPhoto" src="" alt="Student" class="w-full h-full object-cover">
                            <div onclick="triggerProfileUpload()" class="absolute inset-0 bg-black/40 flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                                <i class="bi bi-camera-fill text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <input type="file" id="profileUploadInput" class="hidden" accept="image/*" onchange="handleProfileUpload(this)">
                    <div class="text-center mt-12">
                        <h4 class="text-lg font-black text-slate-800" id="stuViewName">-</h4>
                        <p class="text-xs text-slate-400 mt-1" id="stuViewMeta">-</p>
                        <span class="inline-block mt-2 px-3 py-1 bg-violet-50 text-violet-600 rounded-full text-[10px] font-bold border border-violet-100">
                            <i class="bi bi-person me-1"></i><span id="stuViewAdvisor">-</span>
                        </span>
                        <div class="grid grid-cols-3 gap-2 mt-4">
                            <div class="bg-emerald-50 rounded-xl p-3 border border-emerald-100">
                                <div class="text-2xl font-black text-emerald-600" id="stuViewScoreGood">0</div>
                                <div class="text-[9px] font-bold text-emerald-400 uppercase">ความดี</div>
                            </div>
                            <div class="bg-rose-50 rounded-xl p-3 border border-rose-100">
                                <div class="text-2xl font-black text-rose-600" id="stuViewScoreBad">0</div>
                                <div class="text-[9px] font-bold text-rose-400 uppercase">ความผิด</div>
                            </div>
                            <div class="bg-sky-50 rounded-xl p-3 border border-sky-100">
                                <div class="text-2xl font-black text-sky-600" id="stuViewScoreNet">100</div>
                                <div class="text-[9px] font-bold text-sky-400 uppercase">สุทธิ</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Assembly & Discipline Sync Panel -->
                <div id="sectionAssemblySync" class="hidden mt-6 text-left fade-in">
                    <div class="bg-gradient-to-br from-indigo-50/50 to-blue-50/50 rounded-2xl p-4 border border-indigo-100/30">
                        <h6 class="text-[9px] font-black text-indigo-600 uppercase tracking-widest mb-3 flex items-center gap-2">
                            <i class="bi bi-shield-check"></i> การเข้าแถว & ระเบียบวินัย
                        </h6>
                        <div id="assemblyHistoryList" class="space-y-2 max-h-[150px] overflow-y-auto pr-2 custom-scrollbar text-[9px]">
                            <!-- Assembly items -->
                        </div>
                    </div>
                </div>

                <!-- NEW: Subject Attendance Panel -->
                <div id="sectionAttendanceSync" class="hidden mt-4 text-left fade-in">
                    <div class="bg-gradient-to-br from-emerald-50/50 to-teal-50/50 rounded-2xl p-4 border border-emerald-100/30">
                        <h6 class="text-[9px] font-black text-emerald-600 uppercase tracking-widest mb-3 flex items-center gap-2">
                            <i class="bi bi-calendar-check-fill"></i> การเข้าเรียนรายวิชา (คาบ 1-8)
                        </h6>
                        <div id="attendanceHistoryList" class="space-y-3 max-h-[250px] overflow-y-auto pr-2 custom-scrollbar text-[9px]">
                            <!-- Attendance items -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: History -->
            <div class="lg:col-span-8">
                <div class="bg-white rounded-2xl shadow-xl p-6 border border-slate-100">
                    <div class="flex justify-between items-center mb-4 pl-3 border-l-4 border-cyan-500">
                        <h5 class="text-lg font-black text-slate-800">ประวัติการบันทึก</h5>
                        <button onclick="openSubmitDeedModal()" class="bg-violet-600 text-white px-3 py-1.5 rounded-xl font-bold text-[10px] shadow-lg shadow-violet-200 hover:scale-[1.02] transition-all flex items-center gap-2">
                            <i class="bi bi-plus-circle-fill"></i> ส่งบันทึกความดี
                        </button>
                    </div>
                    <div id="studentHistoryList">
                        <div class="text-center py-12 text-slate-400 opacity-50">
                            <i class="bi bi-inbox-fill text-5xl block mb-3"></i>
                            <p>ยังไม่มีการบันทึก</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="text-center py-6 mt-8 text-xs text-slate-400">
            พัฒนาโดย : <strong>ฝ่ายบริหารกิจการนักเรียน</strong>
        </footer>
    </div>
</div>

<script>
const BASE_BEHAVIOR_SCORE = 100;
const clamp = (n, min, max) => Math.min(max, Math.max(min, Number(n) || 0));
const normId = sid => { sid = String(sid || '').trim(); if (/^\d+$/.test(sid)) sid = sid.padStart(5, '0'); return sid; };

let basePath = '';
try {
    const p = window.location.pathname;
    basePath = p.substring(0, p.lastIndexOf('/behavior'));
} catch(e) {}

let currentSid = '';

document.getElementById('studentLoginForm').onsubmit = async function(e) {
    e.preventDefault();
    const sid = normId(document.getElementById('studentLoginId').value.trim());
    if (!sid) { Swal.fire('กรุณากรอกรหัสนักเรียน', '', 'warning'); return; }

    currentSid = sid;

    document.getElementById('btnStudentLogin').disabled = true;
    document.getElementById('btnStudentLoginSpinner').classList.remove('hidden');

    try {
        const res = await fetch(basePath + '/behavior/api/get_student_focus.php?sid=' + encodeURIComponent(sid) + '&mode=student');
        const data = await res.json();

        document.getElementById('btnStudentLogin').disabled = false;
        document.getElementById('btnStudentLoginSpinner').classList.add('hidden');

        if (!data || !data.st) {
            Swal.fire({ icon: 'error', title: 'ไม่พบรหัสนักเรียนนี้', text: 'กรุณาตรวจสอบรหัสนักเรียนอีกครั้ง' });
            return;
        }

        renderStudentView(data, sid);
        loadAssemblySync(sid); // Fetch assembly data
        loadAttendanceSync(sid); // Fetch subject attendance

        document.getElementById('loginScreen').classList.add('hidden');
        document.getElementById('studentScreen').classList.remove('hidden');
        document.getElementById('studentScreen').classList.add('fade-in');
    } catch (err) {
        console.error('Student Login Fetch Error:', err);
        document.getElementById('btnStudentLogin').disabled = false;
        document.getElementById('btnStudentLoginSpinner').classList.add('hidden');
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้' });
    }
};

function renderStudentView(data, sid) {
    const st = data.st || {};
    const scores = data.scores || {};

    const good = clamp(scores.good, 0, 999999);
    const bad = clamp(scores.bad, 0, BASE_BEHAVIOR_SCORE);
    const net = clamp(BASE_BEHAVIOR_SCORE - bad, 0, 999999);

    document.getElementById('studentWelcomeText').innerText = `ยินดีต้อนรับ, ${st.name || '-'} (${st.classText || ''})`;
    document.getElementById('stuViewName').innerText = st.name || '-';
    document.getElementById('stuViewMeta').innerText = `รหัส: ${st.studentId || sid} | ชั้น: ${st.classText || '-'}`;
    document.getElementById('stuViewAdvisor').innerText = st.homeroom || 'ไม่ระบุ';
    document.getElementById('stuViewPhoto').src = st.img || 'https://via.placeholder.com/100x120?text=No+Img';

    document.getElementById('stuViewScoreGood').innerText = good.toLocaleString();
    document.getElementById('stuViewScoreBad').innerText = bad.toLocaleString();
    document.getElementById('stuViewScoreNet').innerText = net.toLocaleString();

    const historyDiv = document.getElementById('studentHistoryList');
    historyDiv.innerHTML = data.html || '<div class="text-center py-12 text-slate-400 opacity-50"><i class="bi bi-inbox-fill text-5xl block mb-3"></i><p>ยังไม่มีการบันทึก</p></div>';

    // Hide delete buttons for student view
    historyDiv.querySelectorAll('button').forEach(btn => {
        if (btn.innerText.includes('ลบ') || btn.innerHTML.includes('bi-trash')) btn.style.display = 'none';
    });
}

async function loadAssemblySync(sid) {
    const list = document.getElementById('assemblyHistoryList');
    const section = document.getElementById('sectionAssemblySync');
    
    if (!list || !section) return;

    list.innerHTML = '<div class="text-center py-4"><div class="animate-spin w-5 h-5 border-2 border-indigo-200 border-t-indigo-600 rounded-full mx-auto"></div></div>';
    section.classList.remove('hidden');

    try {
        const res = await fetch(basePath + '/behavior/api/get_assembly_sync.php?sid=' + encodeURIComponent(sid) + '&mode=student');
        const data = await res.json();
        
        if (data.status === 'success' && data.data && data.data.length > 0) {
            list.innerHTML = '';
            data.data.forEach(r => {
                const dateStr = new Date(r.date).toLocaleDateString('th-TH', { day:'numeric', month:'short', year:'2-digit' });
                
                const dressVio = [];
                if (r.nail === 'ผิด') dressVio.push('เล็บ');
                if (r.hair === 'ผิด') dressVio.push('ทรงผม');
                if (r.shirt === 'ผิด') dressVio.push('เสื้อ');
                if (r.pants === 'ผิด') dressVio.push('กางเกง/กระโปรง');
                if (r.socks === 'ผิด') dressVio.push('ถุงเท้า');
                if (r.shoes === 'ผิด') dressVio.push('รองเท้า');
                
                const colorClass = r.status === 'ข' ? 'bg-rose-100/50 border-rose-200 text-rose-700' : 'bg-white/80 border-indigo-100/30 text-slate-600';
                const statusMap = { 'ม': 'มาเข้าแถว', 'ข': 'ขาดแถว', 'ล': 'ลา', 'ด': 'โดดสาย' };
                const statusText = statusMap[r.status] || r.status;

                const item = document.createElement('div');
                item.className = `p-3 rounded-xl border shadow-sm ${colorClass} transition-all hover:scale-[1.01]`;
                item.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="font-bold text-slate-800">${dateStr}</span>
                            <span class="ml-2 px-2 py-0.5 rounded-full font-bold bg-white/50 text-[9px] uppercase">${statusText}</span>
                        </div>
                    </div>
                    ${dressVio.length > 0 ? `<div class="mt-2 font-bold text-rose-600 flex flex-wrap gap-1">
                        <i class="bi bi-exclamation-triangle-fill"></i> ผิดระเบียบ: ${dressVio.join(', ')}
                    </div>` : ''}
                    ${r.note ? `<div class="mt-1 opacity-70 italic text-slate-500">หมายเหตุ: ${r.note}</div>` : ''}
                `;
                list.appendChild(item);
            });
        } else {
            list.innerHTML = '<div class="text-center py-8 text-slate-400 italic">ไม่พบประวัติการเข้าแถวในช่วงนี้</div>';
        }
    } catch (err) {
        console.error('Assembly Sync Error:', err);
        list.innerHTML = '<div class="text-center py-8 text-rose-400 italic">โหลดข้อมูลล้มเหลว</div>';
    }
}

async function loadAttendanceSync(sid) {
    const list = document.getElementById('attendanceHistoryList');
    const section = document.getElementById('sectionAttendanceSync');
    
    if (!list || !section) return;

    list.innerHTML = '<div class="text-center py-4"><div class="animate-spin w-4 h-4 border-2 border-emerald-200 border-t-emerald-600 rounded-full mx-auto"></div></div>';
    section.classList.remove('hidden');

    try {
        const res = await fetch(basePath + '/behavior/api/get_attendance_records.php?sid=' + encodeURIComponent(sid) + '&mode=student');
        const data = await res.json();
        
        if (data.status === 'success' && data.data && data.data.length > 0) {
            list.innerHTML = '';
            data.data.forEach(day => {
                const dateStr = new Date(day.date).toLocaleDateString('th-TH', { day:'numeric', month:'short' });
                
                const card = document.createElement('div');
                card.className = 'bg-white/80 rounded-xl p-2 border border-emerald-100 shadow-sm';
                
                let pHtml = '';
                for(let i=1; i<=8; i++) {
                    const p = day.periods[i];
                    const status = p ? p.status : '-';
                    let bgColor = 'bg-slate-100 text-slate-400';
                    if (status === 'มา') bgColor = 'bg-emerald-500 text-white';
                    if (status === 'ขาด' || status === 'โดด') bgColor = 'bg-rose-500 text-white';
                    if (status === 'ลา') bgColor = 'bg-amber-500 text-white';
                    if (status === 'สาย') bgColor = 'bg-orange-400 text-white';

                    pHtml += `<div class="flex flex-col items-center gap-0.5" title="${p ? esc(p.subject) : 'ไม่พบข้อมูล'}">
                        <span class="text-[7px] font-black text-slate-400">คาบ ${i}</span>
                        <div class="w-4 h-4 rounded-md flex items-center justify-center font-black ${bgColor}">${status[0]}</div>
                    </div>`;
                }

                card.innerHTML = `
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-black text-slate-700">${dateStr}</span>
                    </div>
                    <div class="flex justify-between gap-0.5">${pHtml}</div>
                `;
                list.appendChild(card);
            });
        } else {
            list.innerHTML = '<div class="text-center py-6 text-slate-400 italic">ไม่พบประวัติเข้าเรียน</div>';
        }
    } catch (err) {
        console.error('Attendance Sync Error:', err);
        list.innerHTML = '<div class="text-center py-8 text-rose-400 italic">โหลดข้อมูลล้มเหลว</div>';
    }
}

function handleStudentLogout() {
    currentSid = '';
    document.getElementById('studentLoginId').value = '';
    document.getElementById('studentScreen').classList.add('hidden');
    document.getElementById('loginScreen').classList.remove('hidden');
}

function triggerProfileUpload() {
    document.getElementById('profileUploadInput').click();
}

async function handleProfileUpload(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('student_id', currentSid);
    formData.append('file', file);

    Swal.fire({ title: 'กำลังอัปโหลด...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch(basePath + '/behavior/api/update_student_profile.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.status === 'success') {
            document.getElementById('stuViewPhoto').src = data.img_url;
            Swal.fire('สำเร็จ!', 'อัปโหลดรูปโปรไฟล์เรียบร้อยแล้ว', 'success');
        } else {
            Swal.fire('ข้อผิดพลาด', data.message, 'error');
        }
    } catch (err) {
        console.error('Profile Upload Error:', err);
        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    }
}

async function openSubmitDeedModal() {
    const { value: formValues } = await Swal.fire({
        title: 'บันทึกความดีของคุณ 💚',
        html: `
            <div class="text-left space-y-4 p-2">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">กิจกรรมที่ทำ</label>
                    <textarea id="swal-activity" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all h-24" placeholder="เช่น ช่วยคุณแม่ล้างจาน, ช่วยกวาดลานวัด..."></textarea>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">รูปถ่ายหลักฐาน</label>
                    <input type="file" id="swal-file" accept="image/*" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs">
                </div>
                <div class="bg-blue-50 p-3 rounded-xl border border-blue-100 flex gap-2 items-start">
                    <i class="bi bi-info-circle-fill text-blue-500 mt-0.5"></i>
                    <p class="text-[10px] text-blue-600 font-medium leading-relaxed">ข้อมูลนี้จะถูกส่งไปยังครูที่ปรึกษาเพื่อตรวจสอบและอนุมัติคะแนนความดีให้คุณครับ</p>
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'ส่งบันทึกความดี',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#7c3aed',
        preConfirm: () => {
            const activity = document.getElementById('swal-activity').value.trim();
            const file = document.getElementById('swal-file').files[0];
            if (!activity) { Swal.showValidationMessage('กรุณาระบุรายละเอียดกิจกรรม'); return false; }
            if (!file) { Swal.showValidationMessage('กรุณาอัปโหลดรูปภาพหลักฐาน'); return false; }
            return { activity, file };
        }
    });

    if (formValues) {
        const formData = new FormData();
        formData.append('student_id', currentSid);
        formData.append('activity', formValues.activity);
        formData.append('file', formValues.file);
        // Default score 5 or let them pick? 
        formData.append('score', 5); 

        Swal.fire({ title: 'กำลังส่งบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const res = await fetch(basePath + '/behavior/api/submit_student_deed.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                Swal.fire('ส่งสำเร็จ!', data.message, 'success').then(() => {
                    // Refresh data
                    document.getElementById('studentLoginForm').dispatchEvent(new Event('submit'));
                });
            } else {
                Swal.fire('ข้อผิดพลาด', data.message, 'error');
            }
        } catch (err) {
            console.error('Deed Submission Error:', err);
            Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        }
    }
}
</script>

</body>
</html>
