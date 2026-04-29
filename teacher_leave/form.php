<?php
/**
 * teacher_leave/form.php
 * Official Leave Form mimicking the paper equivalent
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}

$pageTitle = 'แบบฟอร์มใบลาออนไลน์';
$pageSubtitle = 'กรอกข้อมูลใบลาตามระเบียบสำนักนายกรัฐมนตรี';
$activeSystem = 'leave';

require_once '../components/layout_start.php';
?>

<div class="max-w-4xl mx-auto mb-10">
    <!-- Paper Form Container -->
    <div class="bg-white shadow-2xl rounded-[1rem] p-10 md:p-16 border border-slate-200 relative overflow-hidden font-[Prompt]">
        <!-- Watermark / Decorative element -->
        <div class="absolute top-[-50px] right-[-50px] w-40 h-40 bg-blue-50 rounded-full opacity-50"></div>
        
        <!-- Form Header -->
        <div class="flex flex-col md:flex-row justify-between mb-10 gap-6">
            <div class="space-y-4">
                <h1 class="text-2xl font-black text-slate-800 border-b-4 border-blue-600 pb-2 inline-block">แบบใบลา</h1>
                <p class="text-sm text-slate-500 font-bold uppercase tracking-widest">โรงเรียนละลมวิทยา</p>
            </div>
            <div class="md:text-right space-y-2">
                <div class="flex flex-col md:items-end">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">เขียนที่</label>
                    <input type="text" id="written_at" value="โรงเรียนละลมวิทยา" class="text-right font-bold border-b border-dashed border-slate-300 outline-none focus:border-blue-500 py-1">
                </div>
                <div class="flex flex-col md:items-end">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">วันที่</label>
                    <input type="date" id="current_date" value="<?= date('Y-m-d') ?>" class="text-right font-bold border-b border-dashed border-slate-300 outline-none focus:border-blue-500 py-1">
                </div>
            </div>
        </div>

        <form id="leaveForm" class="space-y-8">
            <!-- Subject Line -->
            <div class="flex items-center gap-4">
                <span class="font-black text-slate-800 whitespace-nowrap">เรื่อง</span>
                <select id="leave_type" name="leave_type" class="flex-1 bg-slate-50 border-b-2 border-slate-200 px-4 py-2 font-bold text-slate-700 outline-none focus:border-blue-500 rounded-t-lg transition-all" required>
                    <option value="">-- เลือกประเภทการลา --</option>
                    <option value="sick">ขอลาป่วย (Sick Leave)</option>
                    <option value="personal">ขอลากิจส่วนตัว (Personal Leave)</option>
                    <option value="vacation">ขอลาพักผ่อน (Vacation Leave)</option>
                    <option value="maternity">ขอลาคลอดบุตร (Maternity Leave)</option>
                    <option value="other">ลาอื่นๆ</option>
                </select>
            </div>

            <div class="flex items-center gap-4">
                <span class="font-black text-slate-800 whitespace-nowrap">เรียน</span>
                <span class="flex-1 border-b border-dashed border-slate-300 py-1 font-bold">ผู้อำนวยการโรงเรียนละลมวิทยา</span>
            </div>

            <!-- Body Details -->
            <div class="space-y-6 pt-4">
                <div class="leading-loose text-slate-700 font-medium">
                    ข้าพเจ้า <span class="font-bold border-b border-dashed border-slate-400 px-4"><?= htmlspecialchars($_SESSION['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    ตำแหน่ง <span class="font-bold border-b border-dashed border-slate-400 px-4" id="display_position">กำลังโหลด...</span>
                    สังกัด <span class="font-bold border-b border-dashed border-slate-400 px-4" id="display_dept">กำลังโหลด...</span>
                </div>

                <div class="leading-loose text-slate-700 font-medium">
                    มีความประสงค์จะขอ <span class="font-bold text-blue-600" id="display_type_text">.........</span>
                    เนื่องจาก <input type="text" name="reason" placeholder="ระบุเหตุผลการลา" class="w-full md:w-auto min-w-[300px] border-b border-dashed border-slate-400 outline-none focus:border-blue-500 font-bold px-2 py-1" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 py-2">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">ตั้งแต่วันที่</label>
                        <input type="date" name="date_start" id="date_start" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">ถึงวันที่</label>
                        <input type="date" name="date_end" id="date_end" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                </div>

                <div class="bg-blue-50 rounded-2xl p-6 flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-black text-blue-800 uppercase tracking-wider">จำนวนวันที่ลาครั้งนี้</h4>
                        <p class="text-xs text-blue-500 font-bold mt-1">* ระบบจะหักวันหยุดเสาร์-อาทิตย์และวันหยุดราชการให้อัตโนมัติ</p>
                    </div>
                    <div class="text-right">
                        <span id="display_days" class="text-4xl font-black text-blue-600">0</span>
                        <span class="text-lg font-black text-blue-800 ml-2">วัน</span>
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">ในระหว่างการลา ข้าพเจ้าสามารถติดต่อได้ที่</label>
                    <textarea name="contact_info" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 font-bold outline-none focus:ring-2 focus:ring-blue-500" placeholder="ที่อยู่ หรือเบอร์โทรศัพท์ที่ติดต่อได้"></textarea>
                </div>

                <!-- Medical Attachment Section -->
                <div id="attachment-section" class="pt-4">
                    <div class="bg-rose-50/50 rounded-2xl p-6 border-2 border-dashed border-rose-100 flex flex-col md:flex-row items-center gap-6">
                        <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center text-3xl text-rose-500 shadow-sm grow-0 shrink-0">
                            <i class="bi bi-file-earmark-medical"></i>
                        </div>
                        <div class="flex-1 text-center md:text-left">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider">แนบใบรับรองแพทย์ (ถ้ามี)</h4>
                            <p class="text-[10px] text-slate-500 font-bold mt-1">รองรับไฟล์ภาพ (JPG, PNG) หรือ PDF ขนาดไม่เกิน 5MB</p>
                            <div class="mt-4">
                                <label for="attachment" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-rose-500 text-white text-xs font-black rounded-xl hover:bg-rose-600 transition-all">
                                    <i class="bi bi-upload"></i> เลือกไฟล์
                                </label>
                                <input type="file" id="attachment" name="attachment" class="hidden" accept=".jpg,.jpeg,.png,.pdf">
                                <span id="file-name" class="ml-3 text-[10px] font-black text-rose-600">ยังไม่ได้เลือกไฟล์</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Statistics Table -->
            <div class="mt-12 space-y-4">
                <h3 class="text-sm font-black text-slate-800 flex items-center gap-2">
                    <i class="bi bi-graph-up text-blue-600 text-lg"></i> สถิติการลาในปีงบประมาณนี้
                </h3>
                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <table class="w-full text-center text-xs">
                        <thead class="bg-slate-50 font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">ประเภทการลา</th>
                                <th class="px-4 py-3">ลามาแล้ว (วัน)</th>
                                <th class="px-4 py-3">ลาครั้งนี้ (วัน)</th>
                                <th class="px-4 py-3 bg-blue-50 text-blue-600">รวมเป็น (วัน)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-bold text-slate-600">
                            <tr>
                                <td class="px-4 py-4 text-left font-black">ลาป่วย / ลากิจ / อื่นๆ</td>
                                <td class="px-4 py-4" id="stat_taken">0</td>
                                <td class="px-4 py-4 text-blue-600" id="stat_current">0</td>
                                <td class="px-4 py-4 bg-blue-50/50 text-blue-700" id="stat_total">0</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Signature Pad -->
            <div class="mt-16 flex flex-col items-center gap-6">
                <div class="text-center space-y-2">
                    <p class="text-sm font-bold text-slate-700">(ลงชื่อ)............................................................ ผู้ขอลา</p>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ลงชื่อในกรอบด้านล่างนี้</p>
                </div>
                
                <div class="relative group">
                    <canvas id="signature-pad" width="400" height="200" class="border-2 border-dashed border-slate-300 rounded-3xl bg-slate-50/50 cursor-crosshair transition-all group-hover:border-blue-400"></canvas>
                    <button type="button" id="clear-signature" class="absolute top-4 right-4 w-10 h-10 bg-white shadow-lg rounded-full flex items-center justify-center text-slate-400 hover:text-rose-500 transition-all">
                        <i class="bi bi-eraser-fill"></i>
                    </button>
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-10 group-hover:opacity-0 transition-all">
                        <i class="bi bi-pencil-square text-6xl"></i>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-10 flex justify-center">
                <button type="submit" class="group relative px-12 py-5 bg-blue-600 text-white rounded-3xl font-black text-lg shadow-2xl shadow-blue-200 hover:scale-[1.05] active:scale-95 transition-all overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-700 to-indigo-700 opacity-0 group-hover:opacity-100 transition-all"></div>
                    <span class="relative flex items-center gap-3">
                        <i class="bi bi-send-check-fill text-xl"></i> ยื่นใบลาออนไลน์
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Signature Pad Setup
    const canvas = document.getElementById('signature-pad');
    const signaturePad = new SignaturePad(canvas, {
        minWidth: 2,
        maxWidth: 4,
        penColor: "rgb(30, 64, 175)" // blue-800
    });

    document.getElementById('clear-signature').addEventListener('click', () => signaturePad.clear());

    // File Preview
    const fileInput = document.getElementById('attachment');
    const fileNameDisplay = document.getElementById('file-name');
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            fileNameDisplay.textContent = e.target.files[0].name;
            fileNameDisplay.classList.add('text-emerald-600');
            fileNameDisplay.classList.remove('text-rose-600');
        } else {
            fileNameDisplay.textContent = 'ยังไม่ได้เลือกไฟล์';
            fileNameDisplay.classList.remove('text-emerald-600');
            fileNameDisplay.classList.add('text-rose-600');
        }
    });

    // 2. Local State
    let stats = { sick_taken: 0, personal_taken: 0, vacation_taken: 0, other_taken: 0 };
    let currentDays = 0;

    // 3. Update UI on Type Change
    const leaveTypeSelect = document.getElementById('leave_type');
    const typeDisplayText = document.getElementById('display_type_text');
    
    leaveTypeSelect.addEventListener('change', (e) => {
        const type = e.target.value;
        const textMap = {
            'sick': 'ลาป่วย',
            'personal': 'ลากิจส่วนตัว',
            'vacation': 'ลาพักผ่อน',
            'maternity': 'ลาคลอดบุตร',
            'other': 'ลาอื่นๆ'
        };
        typeDisplayText.textContent = textMap[type] || '.........';
        updateStatsView();
    });

    // 4. Calculate Days (Realtime)
    const startDateInput = document.getElementById('date_start');
    const endDateInput = document.getElementById('date_end');

    async function updateDays() {
        const start = startDateInput.value;
        const end = endDateInput.value;
        if (!start || !end) return;

        try {
            // เราจะใช้วันที่ที่เลือกเพื่อเช็คสถิติด้วย
            if (start !== lastFetchedDate) {
                fetchStats(start);
                lastFetchedDate = start;
            }

            // ในระบบจริงเราอาจจะส่งไป API เพื่อคำนวณวันหยุดที่ถูกต้อง
            // แต่เบื้องต้นคำนวณ JS ก่อน แล้วค่อยเช็ค API
            const res = await fetch(`api/calculate_days.php?start=${start}&end=${end}`);
            const json = await res.json();
            if (json.status === 'success') {
                currentDays = json.days;
                document.getElementById('display_days').textContent = currentDays;
                document.getElementById('stat_current').textContent = currentDays;
                updateStatsView();
            }
        } catch (e) {
            console.error(e);
        }
    }

    let lastFetchedDate = '';
    startDateInput.addEventListener('change', updateDays);
    endDateInput.addEventListener('change', updateDays);

    // 5. Fetch User Profile & Stats
    async function fetchProfile() {
        try {
            const res = await fetch(`api/get_profile.php`); // สมมติว่ามี API นี้
            const json = await res.json();
            if (json.status === 'success') {
                document.getElementById('display_position').textContent = json.data.position || 'ครู';
                document.getElementById('display_dept').textContent = json.data.subject_group || 'กลุ่มสาระฯ';
            }
        } catch (e) {
            document.getElementById('display_position').textContent = '—';
            document.getElementById('display_dept').textContent = '—';
        }
    }

    async function fetchStats(date) {
        try {
            const res = await fetch(`api/get_stats.php?date=${date}`);
            const json = await res.json();
            if (json.status === 'success') {
                stats = json.data;
                updateStatsView();
            }
        } catch (e) {}
    }

    function updateStatsView() {
        const type = leaveTypeSelect.value;
        let taken = 0;
        if (type === 'sick') taken = stats.sick_taken;
        else if (type === 'personal') taken = stats.personal_taken;
        else if (type === 'vacation') taken = stats.vacation_taken;
        else taken = stats.other_taken;

        document.getElementById('stat_taken').textContent = taken;
        document.getElementById('stat_total').textContent = (parseFloat(taken) + parseFloat(currentDays)).toFixed(1);
    }

    // 6. Form Submission
    document.getElementById('leaveForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        if (signaturePad.isEmpty()) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาลงลายมือชื่อ',
                text: 'คุณต้องเซ็นชื่อในช่องว่างก่อนส่งใบลา',
                customClass: { popup: 'rounded-[1.5rem]' }
            });
            return;
        }

        const formData = new FormData(e.target);
        formData.append('signature', signaturePad.toDataURL()); // Base64 signature
        // File is already in FormData if chosen

        Swal.fire({
            title: 'ยืนยันการส่งใบลา?',
            text: "เมื่อส่งแล้วคุณจะไม่สามารถแก้ไขข้อมูลได้จนกว่าจะถูกปฏิเสธ",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ยืนยัน ส่งใบลา',
            cancelButtonText: 'ยกเลิก',
            customClass: { 
                popup: 'rounded-[2.5rem]',
                confirmButton: 'bg-blue-600 rounded-2xl px-10 py-3',
                cancelButton: 'bg-slate-100 text-slate-400 rounded-2xl px-10 py-3'
            }
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading() });
                
                try {
                    const res = await fetch('api/save_leave.php', {
                        method: 'POST',
                        body: formData // Use FormData directly
                    });
                    const resJson = await res.json();
                    
                    if (resJson.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'ส่งใบลาสำเร็จ!',
                            text: resJson.message,
                            timer: 2000,
                            showConfirmButton: false,
                            customClass: { popup: 'rounded-[2.5rem]' }
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        Swal.fire('เข้าพบข้อผิดพลาด', resJson.message, 'error');
                    }
                } catch (err) {
                    Swal.fire('Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                }
            }
        });
    });

    // Initialize
    fetchProfile();
    fetchStats('<?= date('Y-m-d') ?>');
});
</script>

<?php require_once '../components/layout_end.php'; ?>
