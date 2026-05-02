<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth: student only
if (!isset($_SESSION['is_student']) || $_SESSION['is_student'] !== true) {
    header('Location: /student/login.php?redirect=' . urlencode('/student_leave/index.php'));
    exit();
}

$student_code  = $_SESSION['student_code'] ?? '';
$student_name  = $_SESSION['student_name'] ?? '';
$student_class = $_SESSION['student_class'] ?? '';
$today = date('Y-m-d');
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>ใบลาออนไลน์ | โรงเรียนละลมวิทยา</title>
<meta name="theme-color" content="#0d9488">
<meta name="apple-mobile-web-app-capable" content="yes">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family: 'Prompt', sans-serif; overscroll-behavior-y: contain; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
.fade-up { animation: fadeUp .35s ease-out both; }
</style>
</head>
<body class="bg-slate-100 min-h-screen" style="padding-bottom: env(safe-area-inset-bottom)">

<!-- Header -->
<header class="bg-gradient-to-r from-teal-600 to-cyan-600 text-white sticky top-0 z-50 shadow-lg"
        style="padding-top: env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto px-4 py-3 flex items-center gap-3">
        <button onclick="history.back()"
                class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/30 flex-shrink-0">
            <i class="bi bi-arrow-left text-lg"></i>
        </button>
        <div>
            <div class="font-black text-sm leading-tight">ใบลาออนไลน์</div>
            <div class="text-teal-200 text-xs font-bold"><?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($student_class, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 py-5 space-y-5">

<!-- ── ฟอร์มยื่นใบลา ─────────────────────────────────────────── -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-5 space-y-4 fade-up">

    <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
        <div class="w-9 h-9 bg-rose-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="bi bi-file-earmark-text-fill text-rose-500 text-lg"></i>
        </div>
        <div>
            <p class="font-black text-slate-800">กรอกใบลา</p>
            <p class="text-slate-400 text-xs"><?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($student_class, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <!-- ประเภทการลา -->
    <div>
        <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
            ประเภทการลา <span class="text-rose-500">*</span>
        </label>
        <select id="f_leave_type"
                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all">
            <option value="">-- เลือกประเภท --</option>
            <option value="sick">ลาป่วย</option>
            <option value="personal">ลากิจ</option>
            <option value="other">ลาอื่นๆ</option>
        </select>
    </div>

    <!-- วันที่ -->
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
                ตั้งแต่วันที่ <span class="text-rose-500">*</span>
            </label>
            <input type="date" id="f_date_from" value="<?= $today ?>"
                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all">
        </div>
        <div>
            <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
                ถึงวันที่ <span class="text-rose-500">*</span>
            </label>
            <input type="date" id="f_date_to" value="<?= $today ?>"
                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all">
        </div>
    </div>
    <div id="days_preview" class="hidden bg-teal-50 rounded-xl px-4 py-2.5 text-sm text-teal-700 font-bold text-center"></div>

    <!-- เหตุผล -->
    <div>
        <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
            เหตุผลการลา <span class="text-rose-500">*</span>
        </label>
        <textarea id="f_reason" rows="3" placeholder="ระบุเหตุผล..."
                  class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all resize-none"></textarea>
    </div>

    <!-- ผู้ปกครอง -->
    <div class="bg-slate-50 rounded-2xl p-4 space-y-3">
        <p class="text-xs font-black text-slate-500 uppercase tracking-wider">ข้อมูลผู้ปกครอง <span class="text-rose-500">*</span></p>
        <input type="text" id="f_parent_name" placeholder="ชื่อผู้ปกครอง"
               class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all">
        <input type="tel" id="f_parent_phone" placeholder="เบอร์โทรศัพท์"
               class="w-full bg-white border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all">
    </div>

    <!-- ไฟล์แนบ -->
    <div>
        <label class="block text-xs font-black text-slate-500 uppercase tracking-wider mb-2">
            แนบไฟล์ (ไม่บังคับ)
        </label>
        <label for="f_attachment"
               class="flex items-center gap-2 bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl px-4 py-4 cursor-pointer hover:border-teal-400 transition-colors text-sm text-slate-400">
            <i class="bi bi-paperclip text-lg"></i>
            <span id="attachment_label">เลือกไฟล์ใบรับรองแพทย์ (JPG, PNG, PDF)</span>
        </label>
        <input type="file" id="f_attachment" accept=".jpg,.jpeg,.png,.pdf" class="hidden"
               onchange="updateAttachmentLabel(this)">
    </div>

    <!-- Submit -->
    <button id="submit_btn" onclick="submitLeave()"
            class="w-full bg-teal-600 text-white py-4 rounded-2xl font-black shadow-lg shadow-teal-200 hover:bg-teal-700 active:scale-[0.98] transition-all">
        <i class="bi bi-send-fill me-1"></i>ส่งใบลา
    </button>
</div>

<!-- ── ประวัติการลา ────────────────────────────────────────────── -->
<div class="fade-up" style="animation-delay:.1s">
    <div class="flex items-center gap-2 px-1 mb-3">
        <div class="w-8 h-8 bg-teal-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="bi bi-clock-history text-teal-600"></i>
        </div>
        <h2 class="font-black text-slate-800">ประวัติการลา</h2>
    </div>

    <div id="history_loading" class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 text-center text-slate-400 text-sm">
        <i class="bi bi-hourglass-split me-2 animate-spin"></i>กำลังโหลด...
    </div>
    <div id="history_list" class="space-y-3"></div>
    <div id="history_empty" class="hidden bg-white rounded-3xl shadow-sm border border-slate-100 p-8 text-center">
        <i class="bi bi-inbox text-4xl text-slate-300 block mb-2"></i>
        <p class="text-slate-400 font-bold text-sm">ยังไม่มีประวัติการลา</p>
    </div>
</div>

</div><!-- /container -->

<script>
const TYPE_LABELS   = { sick: 'ลาป่วย', personal: 'ลากิจ', other: 'ลาอื่นๆ' };
const STATUS_LABELS = { pending: 'รอการอนุมัติ', approved: 'อนุมัติแล้ว', rejected: 'ไม่อนุมัติ' };
const STATUS_COLORS = {
    pending:  'bg-amber-50 text-amber-700 border border-amber-200',
    approved: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    rejected: 'bg-rose-50 text-rose-700 border border-rose-200',
};

function updateAttachmentLabel(input) {
    document.getElementById('attachment_label').textContent =
        input.files[0] ? input.files[0].name : 'เลือกไฟล์ใบรับรองแพทย์ (JPG, PNG, PDF)';
}

function updateDaysPreview() {
    const from = document.getElementById('f_date_from').value;
    const to   = document.getElementById('f_date_to').value;
    const el   = document.getElementById('days_preview');
    if (from && to && to >= from) {
        const days = Math.round((new Date(to) - new Date(from)) / 86400000) + 1;
        el.textContent = 'จำนวน ' + days + ' วัน';
        el.classList.remove('hidden');
    } else {
        el.classList.add('hidden');
    }
}
document.getElementById('f_date_from').addEventListener('change', updateDaysPreview);
document.getElementById('f_date_to').addEventListener('change', updateDaysPreview);

async function submitLeave() {
    const leave_type   = document.getElementById('f_leave_type').value;
    const date_from    = document.getElementById('f_date_from').value;
    const date_to      = document.getElementById('f_date_to').value;
    const reason       = document.getElementById('f_reason').value.trim();
    const parent_name  = document.getElementById('f_parent_name').value.trim();
    const parent_phone = document.getElementById('f_parent_phone').value.trim();
    const fileInput    = document.getElementById('f_attachment');

    if (!leave_type) { Swal.fire({ icon: 'warning', title: 'กรุณาเลือกประเภทการลา', confirmButtonColor: '#0d9488' }); return; }
    if (!date_from)  { Swal.fire({ icon: 'warning', title: 'กรุณาระบุวันที่เริ่มลา',  confirmButtonColor: '#0d9488' }); return; }
    if (!date_to)    { Swal.fire({ icon: 'warning', title: 'กรุณาระบุวันที่สิ้นสุด',  confirmButtonColor: '#0d9488' }); return; }
    if (date_to < date_from) { Swal.fire({ icon: 'warning', title: 'วันที่ไม่ถูกต้อง', text: 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น', confirmButtonColor: '#0d9488' }); return; }
    if (!reason)       { Swal.fire({ icon: 'warning', title: 'กรุณาระบุเหตุผลการลา',     confirmButtonColor: '#0d9488' }); return; }
    if (!parent_name)  { Swal.fire({ icon: 'warning', title: 'กรุณาระบุชื่อผู้ปกครอง', confirmButtonColor: '#0d9488' }); return; }

    const btn = document.getElementById('submit_btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1 animate-spin"></i>กำลังส่ง...';

    try {
        const res = await fetch('/student_leave/api/submit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leave_type, date_from, date_to, reason, parent_name, parent_phone }),
        });
        const data = await res.json();

        if (data.status !== 'success') {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message, confirmButtonColor: '#0d9488' });
            return;
        }

        if (fileInput.files.length > 0) {
            const fd = new FormData();
            fd.append('request_id', data.id);
            fd.append('file', fileInput.files[0]);
            await fetch('/student_leave/api/upload.php', { method: 'POST', body: fd });
        }

        await Swal.fire({
            icon: 'success',
            title: 'ส่งใบลาสำเร็จ!',
            text: 'ครูที่ปรึกษาจะดำเนินการอนุมัติ',
            confirmButtonColor: '#0d9488',
        });

        // Reset form
        document.getElementById('f_leave_type').value   = '';
        document.getElementById('f_date_from').value    = '<?= $today ?>';
        document.getElementById('f_date_to').value      = '<?= $today ?>';
        document.getElementById('f_reason').value       = '';
        document.getElementById('f_parent_name').value  = '';
        document.getElementById('f_parent_phone').value = '';
        fileInput.value = '';
        document.getElementById('attachment_label').textContent = 'เลือกไฟล์ใบรับรองแพทย์ (JPG, PNG, PDF)';
        document.getElementById('days_preview').classList.add('hidden');

        loadHistory();

    } catch (err) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อได้', confirmButtonColor: '#0d9488' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill me-1"></i>ส่งใบลา';
    }
}

async function loadHistory() {
    document.getElementById('history_loading').classList.remove('hidden');
    document.getElementById('history_list').innerHTML = '';
    document.getElementById('history_empty').classList.add('hidden');

    try {
        const res  = await fetch('/student_leave/api/list.php?context=student');
        const data = await res.json();

        document.getElementById('history_loading').classList.add('hidden');

        if (!data.data || data.data.length === 0) {
            document.getElementById('history_empty').classList.remove('hidden');
            return;
        }

        const list = document.getElementById('history_list');
        data.data.forEach(r => {
            const card       = document.createElement('div');
            card.className   = 'bg-white rounded-3xl shadow-sm border border-slate-100 p-4 fade-up';
            const statusCls  = STATUS_COLORS[r.status]    || 'bg-slate-50 text-slate-600';
            const typeLabel  = TYPE_LABELS[r.leave_type]  || r.leave_type;
            const statusLabel = STATUS_LABELS[r.status]   || r.status;
            const dateFrom   = formatThaiDate(r.date_from);
            const dateTo     = formatThaiDate(r.date_to);
            const dateRange  = r.date_from === r.date_to ? dateFrom : dateFrom + ' – ' + dateTo;
            const printBtn   = r.status === 'approved'
                ? `<a href="/student_leave/print.php?id=${r.id}" target="_blank"
                      class="flex items-center gap-1 px-3 py-1.5 bg-teal-50 text-teal-600 rounded-xl text-xs font-black hover:bg-teal-100 transition-colors">
                      <i class="bi bi-printer"></i>พิมพ์
                   </a>` : '';

            card.innerHTML = `
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex-1">
                        <span class="text-sm font-black text-slate-800">${typeLabel}</span>
                        <p class="text-slate-400 text-xs mt-0.5">${dateRange} · ${r.days} วัน</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-black ${statusCls}">${statusLabel}</span>
                </div>
                <p class="text-slate-600 text-xs line-clamp-2">${escHtml(r.reason)}</p>
                ${r.teacher_note ? `<p class="mt-1.5 text-slate-400 text-xs italic">หมายเหตุ: ${escHtml(r.teacher_note)}</p>` : ''}
                ${printBtn ? `<div class="flex justify-end mt-3">${printBtn}</div>` : ''}
            `;
            list.appendChild(card);
        });

    } catch (err) {
        document.getElementById('history_loading').classList.add('hidden');
        document.getElementById('history_list').innerHTML =
            '<div class="text-center text-rose-500 text-sm py-8">เกิดข้อผิดพลาด กรุณาลองใหม่</div>';
    }
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatThaiDate(dateStr) {
    if (!dateStr) return '';
    const months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    const parts = dateStr.split('-');
    if (parts.length < 3) return dateStr;
    return parseInt(parts[2]) + ' ' + months[parseInt(parts[1])] + ' ' + (parseInt(parts[0]) + 543);
}

document.addEventListener('DOMContentLoaded', loadHistory);
</script>
</body>
</html>
