<?php
/**
 * behavior/manage_advisors_ui.php — หน้าจัดการห้องที่ปรึกษาสำหรับครู
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$pageTitle = 'จัดการห้องที่ปรึกษา';
$pageSubtitle = 'กำหนดว่าคุณเป็นครูที่ปรึกษาของห้องใดบ้าง';
$activeSystem = 'behavior';

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="max-w-4xl mx-auto space-y-8">
    
    <!-- Hero / Info Card -->
    <div class="bg-gradient-to-br from-violet-600 to-indigo-700 rounded-[2.5rem] p-10 text-white shadow-2xl shadow-violet-200/50 relative overflow-hidden">
        <div class="relative z-10">
            <h2 class="text-3xl font-black mb-2">ระบุห้องที่ปรึกษาของคุณ</h2>
            <p class="text-violet-100 text-sm font-medium opacity-80 leading-relaxed max-w-xl">
                เมื่อคุณเพิ่มห้องที่ปรึกษา ระบบจะดึงรายชื่อนักเรียนในห้องนั้นมาแสดงใน Dashboard โดยอัตโนมัติ เพื่อความสะดวกในการบันทึกพฤติกรรมและติดตามข้อมูล
            </p>
        </div>
        <i class="bi bi-mortarboard absolute -right-10 -bottom-10 text-[12rem] text-white/10 rotate-12"></i>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- Form Column -->
        <div class="md:col-span-1">
            <div class="bg-white rounded-[2rem] p-8 shadow-sm border border-slate-100 sticky top-8">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-widest mb-6 flex items-center gap-2">
                    <i class="bi bi-plus-circle-fill text-violet-600"></i> เพิ่มห้องใหม่
                </h3>
                
                <form id="formAddMapping" class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">ระดับชั้น (Level)</label>
                        <select id="mapLevel" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                            <option value="">-- เลือก --</option>
                            <option value="ม.1">ม.1</option>
                            <option value="ม.2">ม.2</option>
                            <option value="ม.3">ม.3</option>
                            <option value="ม.4">ม.4</option>
                            <option value="ม.5">ม.5</option>
                            <option value="ม.6">ม.6</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">ห้อง (Room)</label>
                        <input type="text" id="mapRoom" placeholder="เช่น 1" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-violet-500 outline-none transition-all">
                    </div>
                    
                    <button type="submit" class="w-full py-4 bg-violet-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-violet-100 hover:scale-[1.02] active:scale-95 transition-all">
                        เพิ่มข้อมูล
                    </button>
                </form>
            </div>
        </div>

        <!-- List Column -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden min-h-[400px]">
                <div class="px-10 py-8 border-b border-slate-50 flex items-center justify-between">
                    <h3 class="font-black text-slate-800 flex items-center gap-3">
                        <i class="bi bi-list-stars text-violet-600"></i> รายการห้องที่คุณดูแล
                    </h3>
                </div>
                
                <div class="p-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                <th class="px-6 py-4 text-left">ห้องเรียน</th>
                                <th class="px-6 py-4 text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="mappingListBody" class="divide-y divide-slate-50">
                            <tr>
                                <td colspan="2" class="px-6 py-12 text-center text-slate-300 font-bold italic">
                                    <div class="animate-spin w-5 h-5 border-2 border-violet-200 border-t-violet-600 rounded-full mx-auto mb-2"></div>
                                    กำลังโหลดข้อมูล...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const BASE = '<?= rtrim(str_replace("/behavior", "", dirname($_SERVER["SCRIPT_NAME"])), "/") ?>';

async function loadMappings() {
    try {
        const r = await fetch(BASE + '/behavior/api/manage_advisors.php?action=list');
        const res = await r.json();
        
        const body = document.getElementById('mappingListBody');
        body.innerHTML = '';
        
        if (res.status === 'success' && res.data && res.data.length > 0) {
            res.data.forEach(m => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/50 transition-colors';
                tr.innerHTML = `
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-violet-100 text-violet-600 rounded-xl flex items-center justify-center font-black">
                                ${m.level}/${m.room}
                            </div>
                            <span class="font-bold text-slate-700">ชั้น ${m.level} ห้อง ${m.room}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <button onclick="deleteMapping(${m.id})" class="w-9 h-9 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center hover:bg-rose-100 transition-all">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </td>
                `;
                body.appendChild(tr);
            });
        } else {
            body.innerHTML = '<tr><td colspan="2" class="px-6 py-20 text-center text-slate-400 font-bold italic">คุณยังไม่ได้ระบุห้องที่ปรึกษา</td></tr>';
        }
    } catch (e) {
        console.error(e);
    }
}

document.getElementById('formAddMapping').onsubmit = async (e) => {
    e.preventDefault();
    const level = document.getElementById('mapLevel').value;
    const room = document.getElementById('mapRoom').value.trim();
    
    if (!level || !room) return;
    
    try {
        const r = await fetch(BASE + '/behavior/api/manage_advisors.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', level, room })
        });
        const res = await r.json();
        if (res.status === 'success') {
            document.getElementById('mapRoom').value = '';
            loadMappings();
            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: res.message, timer: 1500, showConfirmButton: false });
        } else {
            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message });
        }
    } catch (e) {
        Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเครื่องแม่ข่ายได้', 'error');
    }
};

async function deleteMapping(id) {
    const confirm = await Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "คุณต้องการยกเลิกการเป็นครูที่ปรึกษาห้องนี้ใช่หรือไม่?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#rose-500',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก'
    });
    
    if (confirm.isConfirmed) {
        try {
            const r = await fetch(BASE + '/behavior/api/manage_advisors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', mappingId: id })
            });
            const res = await r.json();
            if (res.status === 'success') {
                loadMappings();
                Swal.fire({ icon: 'success', title: 'ลบแล้ว', text: res.message, timer: 1000, showConfirmButton: false });
            }
        } catch (e) {
            Swal.fire('Error', 'ไม่สามารถลบได้', 'error');
        }
    }
}

loadMappings();
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
