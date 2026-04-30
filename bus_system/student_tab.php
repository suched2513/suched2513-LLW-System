<!-- Bus System Tab Fragment -->
<div id="sectionBusSystem" class="hidden mt-6 text-left fade-in">
    <div class="bg-gradient-to-br from-blue-50/50 to-indigo-50/50 rounded-2xl p-4 border border-blue-100/30">
        <h6 class="text-[9px] font-black text-blue-600 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="bi bi-bus-front-fill"></i> ระบบรถรับส่งนักเรียน
            <span class="ml-auto opacity-50" id="syncTimeBus"></span>
        </h6>
        
        <div id="busStatusCard" class="bg-white/80 rounded-2xl p-4 border border-blue-100 shadow-sm mb-4">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase">สายรถที่ใช้บริการ</p>
                    <h4 class="text-base font-black text-slate-800" id="busRouteName">-</h4>
                </div>
                <div id="busStatusBadge">
                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-400 text-[9px] font-black uppercase">ยังไม่ลงทะเบียน</span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mt-4">
                <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                    <p class="text-[8px] font-bold text-slate-400 uppercase">ยอดค้างชำระ</p>
                    <p class="text-lg font-black text-rose-500" id="busOwedAmount">฿0</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                    <p class="text-[8px] font-bold text-slate-400 uppercase">จ่ายแล้วรวม</p>
                    <p class="text-lg font-black text-emerald-600" id="busPaidAmount">฿0</p>
                </div>
            </div>
        </div>

        <div id="busActions" class="space-y-2">
            <!-- Dynamic actions based on status -->
        </div>

        <div class="mt-4 p-3 bg-white/40 rounded-xl border border-white/50">
            <h6 class="text-[8px] font-black text-slate-400 uppercase mb-2">ประวัติการชำระเงิน</h6>
            <div id="busPaymentHistory" class="space-y-2 max-h-[150px] overflow-y-auto pr-1 custom-scrollbar">
                <!-- Payment items -->
                <p class="text-[10px] text-slate-400 italic text-center py-2">ไม่มีประวัติการชำระเงิน</p>
            </div>
        </div>
    </div>
</div>

<script>
async function loadBusData(sid) {
    const section = document.getElementById('sectionBusSystem');
    const routeName = document.getElementById('busRouteName');
    const statusBadge = document.getElementById('busStatusBadge');
    const owed = document.getElementById('busOwedAmount');
    const paid = document.getElementById('busPaidAmount');
    const historyList = document.getElementById('busPaymentHistory');
    const actions = document.getElementById('busActions');

    if (!section) return;
    section.classList.remove('hidden');

    try {
        const res = await fetch(basePath + '/api/bus/student_actions.php?action=get_info&sid=' + sid);
        const data = await res.json();
        
        if (data.status === 'success' && data.data) {
            const b = data.data;
            routeName.innerText = b.route_name || 'ยังไม่ลงทะเบียน';
            owed.innerText = '฿' + parseFloat(b.balance).toLocaleString();
            paid.innerText = '฿' + parseFloat(b.total_paid).toLocaleString();
            
            // Status Badge
            const s = b.reg_status;
            if (s === 'active') {
                statusBadge.innerHTML = '<span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-600 text-[9px] font-black uppercase">กำลังใช้บริการ</span>';
                actions.innerHTML = `
                    <button onclick="requestBusCancel('${sid}')" class="w-full py-2.5 rounded-xl bg-white border border-rose-100 text-rose-600 text-[10px] font-bold hover:bg-rose-50 transition-all flex items-center justify-center gap-2">
                        <i class="bi bi-x-circle"></i> ขอยกเลิกใช้บริการ
                    </button>
                `;
            } else if (s === 'pending') {
                statusBadge.innerHTML = '<span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-600 text-[9px] font-black uppercase">รออนุมัติ</span>';
                actions.innerHTML = '<p class="text-[10px] text-amber-600 text-center font-bold">อยู่ระหว่างการตรวจสอบข้อมูล</p>';
            } else if (s === 'cancelled') {
                statusBadge.innerHTML = '<span class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-600 text-[9px] font-black uppercase">ยกเลิกแล้ว</span>';
                actions.innerHTML = '<button onclick="openBusRegistrationModal()" class="w-full py-2.5 rounded-xl bg-blue-600 text-white text-[10px] font-bold shadow-lg shadow-blue-100 transition-all">ลงทะเบียนใหม่</button>';
            } else {
                actions.innerHTML = '<button onclick="openBusRegistrationModal()" class="w-full py-2.5 rounded-xl bg-blue-600 text-white text-[10px] font-bold shadow-lg shadow-blue-100 transition-all">ลงทะเบียนใช้บริการ</button>';
            }

            // Payment History
            if (b.payments && b.payments.length > 0) {
                historyList.innerHTML = b.payments.map(p => `
                    <div class="flex justify-between items-center p-2 bg-white/60 rounded-lg border border-white/50 text-[10px]">
                        <div>
                            <p class="font-bold text-slate-700">${new Date(p.payment_date).toLocaleDateString('th-TH')}</p>
                            <p class="text-[8px] text-slate-400">${p.note || 'ชำระค่าบริการ'}</p>
                        </div>
                        <p class="font-black text-emerald-600">฿${parseFloat(p.amount).toLocaleString()}</p>
                    </div>
                `).join('');
            } else {
                historyList.innerHTML = '<p class="text-[10px] text-slate-400 italic text-center py-2">ไม่มีประวัติการชำระเงิน</p>';
            }

            // Cancellation Check
            if (b.has_pending_cancel) {
                actions.innerHTML = '<div class="p-3 bg-amber-50 border border-amber-100 rounded-xl text-[10px] text-amber-700 font-bold text-center">ส่งคำขอยกเลิกแล้ว รอการตรวจสอบ</div>';
            }

            document.getElementById('syncTimeBus').innerText = `อัปเดต ${new Date().toLocaleTimeString('th-TH', {hour:'2-digit', minute:'2-digit'})}`;
        }
    } catch (err) {
        console.error('Bus Data Fetch Error:', err);
    }
}

async function requestBusCancel(sid) {
    const { value: confirmId } = await Swal.fire({
        title: 'ขอยกเลิกใช้บริการ',
        text: 'กรุณากรอกเลขบัตรประชาชน 13 หลักเพื่อยืนยันตัวตน',
        input: 'text',
        inputPlaceholder: 'เลขบัตรประชาชน 13 หลัก',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันยกเลิก',
        cancelButtonText: 'ยกเลิก',
        inputValidator: (value) => {
            if (!value) return 'กรุณากรอกข้อมูล';
            if (value.length !== 13) return 'เลขบัตรประชาชนต้องมี 13 หลัก';
        }
    });

    if (confirmId) {
        const { value: reason } = await Swal.fire({
            title: 'ระบุเหตุผลการยกเลิก',
            input: 'textarea',
            inputPlaceholder: 'ระบุเหตุผลที่ต้องการยกเลิกการใช้บริการ...',
            showCancelButton: true,
            confirmButtonText: 'ส่งคำขอ',
            cancelButtonText: 'ยกเลิก'
        });

        if (reason) {
            try {
                const res = await fetch(basePath + '/api/bus/student_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'request_cancel', sid: sid, citizen_id: confirmId, reason: reason })
                });
                const result = await res.json();
                if (result.status === 'success') {
                    Swal.fire('สำเร็จ!', 'ส่งคำขอยกเลิกเรียบร้อยแล้ว รอการตรวจสอบจากการเงิน', 'success').then(() => loadBusData(sid));
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', result.message, 'error');
                }
            } catch (err) {
                Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
            }
        }
    }
}

async function openBusRegistrationModal() {
    // Show available routes
    Swal.fire({
        title: 'ลงทะเบียนใช้บริการรถรับส่ง',
        text: 'กรุณาติดต่อคุณครูวิรัตน์เพื่อเลือกลงทะเบียนสายรถที่ต้องการครับ',
        icon: 'info'
    });
}
</script>
