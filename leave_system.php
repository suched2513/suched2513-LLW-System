<?php
session_start();
require_once 'config.php';

// Auth: super_admin or staff
if (!isset($_SESSION['llw_role'])) {
    header("Location: login.php"); exit();
}

$pageTitle = 'ระบบขออนุญาตออกนอกบริเวณ';
$pageSubtitle = 'ยื่นคำขอและติดตามสถานะการขอออกนอกบริเวณโรงเรียน';
$activeSystem = 'leave';

require_once 'components/layout_start.php';
?>

<div class="flex flex-col gap-8">
    
    <!-- Action Header -->
    <div class="flex flex-wrap justify-between items-center gap-6 bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm">
                <i class="bi bi-person-walking"></i>
            </div>
            <div>
                <h3 class="font-black text-slate-800 tracking-tight">รายการคำขอของฉัน</h3>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1 italic">My Leave Requests & History</p>
            </div>
        </div>
        <button class="px-8 py-4 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-3" data-bs-toggle="modal" data-bs-target="#requestModal">
            <i class="bi bi-plus-lg text-lg"></i> ยื่นคำขอใหม่
        </button>
    </div>

    <!-- Data Table Card -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-10 py-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
            <h3 class="font-black text-slate-800 flex items-center gap-3"><i class="bi bi-list-ul text-blue-600"></i> ประวัติการขออนุญาต</h3>
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest font-bold">List of all requests</span>
        </div>
        
        <div class="p-8">
            <div class="overflow-x-auto">
                <table id="requestTable" class="min-w-full text-sm">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-5">วันที่ / เวลา</th>
                            <th class="px-6 py-5">ผู้ขออนุญาต</th>
                            <th class="px-6 py-5">เหตุผล / สถานที่</th>
                            <th class="px-6 py-5">รวม (ชม.)</th>
                            <th class="px-6 py-5">สถานะ ผอ.</th>
                            <th class="px-6 py-5 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-600">
                        <!-- DataTables will populate this -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal ยื่นคำขอ -->
<div class="modal fade" id="requestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-[2.5rem] overflow-hidden shadow-2xl">
            <div class="modal-header bg-blue-600 p-8 border-0">
                <div class="flex items-center gap-4 text-white">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-xl">
                        <i class="bi bi-file-earmark-plus-fill"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-black text-lg">แบบฟอร์มขออนุญาตออกนอกบริเวณ</h5>
                        <p class="text-[10px] font-bold text-blue-100 uppercase tracking-widest mt-0.5">Please fill in the details correctly</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white opacity-50 hover:opacity-100" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-10 bg-slate-50">
                <form id="requestForm" class="space-y-8">
                    <!-- Reason -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">เหตุผลที่ขออนุญาต / Reason</label>
                        <input type="text" class="w-full bg-white border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 outline-none focus:ring-4 focus:ring-blue-100 transition-all" name="reason" required placeholder="เช่น ไปราชการ, ธุระส่วนตัว">
                    </div>
                    
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">รายละเอียด/สถานที่ / Detail & Location</label>
                        <textarea class="w-full bg-white border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 outline-none focus:ring-4 focus:ring-blue-100 transition-all" name="detail" rows="2" placeholder="ระบุรายละเอียดเพิ่มเติม..."></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">เริ่มเวลา / From</label>
                            <input type="time" class="w-full bg-white border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 outline-none focus:ring-4 focus:ring-blue-100 transition-all" name="time_start" required>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">ถึงเวลา / Until</label>
                            <input type="time" class="w-full bg-white border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 outline-none focus:ring-4 focus:ring-blue-100 transition-all" name="time_end" required>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">รวมชั่วโมง / Total Hours</label>
                            <input type="number" step="0.5" class="w-full bg-white border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 outline-none focus:ring-4 focus:ring-blue-100 transition-all" name="total_hr" required placeholder="0.0">
                        </div>
                    </div>

                    <div class="p-6 bg-white rounded-3xl border border-slate-100 flex items-center justify-between group cursor-pointer" onclick="document.getElementById('hasClassSwitch').click()">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-110"><i class="bi bi-journal-check"></i></div>
                            <div>
                                <h6 class="text-sm font-black text-slate-700">มีคาบการสอนในช่วงเวลาดังกล่าว</h6>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Toggle to manage substitutions</p>
                            </div>
                        </div>
                        <div class="form-check form-switch p-0 m-0">
                            <input class="form-check-input w-12 h-6 scale-125 cursor-pointer" type="checkbox" id="hasClassSwitch" name="has_class">
                        </div>
                    </div>

                    <!-- Substitution Cart -->
                    <div id="substitutionSection" style="display: none;" class="p-8 bg-white rounded-[2rem] border-2 border-dashed border-slate-200 space-y-6">
                        <div class="flex items-center gap-4 mb-2">
                            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg"><i class="bi bi-stack"></i></div>
                            <h6 class="font-black text-slate-800 uppercase tracking-widest text-xs">Substitution Management</h6>
                        </div>
                        
                        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                            <input type="text" id="sub_period" class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none" placeholder="คาบที่">
                            <input type="text" id="sub_subject" class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none" placeholder="วิชา">
                            <input type="text" id="sub_class" class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none" placeholder="ชั้น">
                            <select id="sub_teacher_id" class="lg:col-span-1 bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none cursor-pointer">
                                <option value="">เลือกครูสอนแทน</option>
                            </select>
                            <button type="button" id="addToCart" class="bg-indigo-600 text-white rounded-xl py-3 text-xs font-black uppercase tracking-widest shadow-lg shadow-indigo-100 hover:scale-105 transition-all">Add Item</button>
                        </div>
                        
                        <div class="overflow-hidden rounded-2xl border border-slate-100">
                            <table class="min-w-full text-[10px] text-left">
                                <thead class="bg-slate-50 font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                    <tr>
                                        <th class="px-6 py-3">คาบ</th>
                                        <th class="px-6 py-3">วิชา/ชั้น</th>
                                        <th class="px-6 py-3">ครูสอนแทน</th>
                                        <th class="px-6 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="cartItems" class="divide-y divide-slate-50">
                                    <tr><td colspan="4" class="text-center py-6 text-slate-300 font-bold italic">No items added to cart.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-slate-50 p-8 border-0 flex gap-4">
                <button type="button" class="flex-1 py-4 bg-white border border-slate-200 text-slate-400 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-100 transition-all" data-bs-dismiss="modal">Cancel / ยกเลิก</button>
                <button type="button" id="saveRequest" class="flex-[2] py-4 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:scale-[1.02] active:scale-95 transition-all">
                    Send Request & Notify BOSS
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables JS Integrated with the theme -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    let cart = [];
    let teachers = [];

    // 1. Initial DataTables with premium styling
    const table = $('#requestTable').DataTable({
        ajax: { url: 'api/get_requests.php', dataSrc: 'data' },
        dom: 'rtip',
        columns: [
            { 
                data: 'req_date',
                render: (d, t, r) => `
                    <div class="fw-bold small">${d ?? '-'}</div>
                    <div style="font-size:9px;font-weight:900;color:#6366f1;text-transform:uppercase;letter-spacing:.05em">${r.time_start ?? ''} - ${r.time_end ?? ''}</div>
                `
            },
            { 
                data: 't_name',
                render: (d) => `<div class="fw-bold small">${d ?? '-'}</div>`
            },
            { 
                data: 'reason',
                render: (d, t, r) => `
                    <div class="small fw-bold">${d}</div>
                    <div style="font-size:9px;color:#94a3b8;font-style:italic">${r.detail || ''}</div>
                `
            },
            { 
                data: 'total_hr',
                render: (d) => `<div class="fw-bold small font-monospace">${d} Hr</div>`
            },
            { 
                data: 'status_boss1',
                render: (data) => {
                    if(data == 0) return '<span class="badge rounded-pill bg-warning text-dark" style="font-size:9px;font-weight:900;letter-spacing:.05em">PENDING</span>';
                    if(data == 1) return '<span class="badge rounded-pill bg-success" style="font-size:9px;font-weight:900;letter-spacing:.05em">APPROVED</span>';
                    return '<span class="badge rounded-pill bg-danger" style="font-size:9px;font-weight:900;letter-spacing:.05em">REJECTED</span>';
                }
            },
            {
                data: 'r_id',
                className: 'text-end',
                render: (id, t, r) => {
                    if (r.status_boss1 != 0) return '<span class="text-muted small">—</span>';
                    return `<button class="btn btn-sm btn-outline-primary approve-btn" data-id="${id}" title="Fast Approval" style="border-radius:10px;font-size:10px;font-weight:900">
                        <i class="bi bi-shield-check-fill me-1"></i>อนุมัติ
                    </button>`;
                }
            }
        ],
        language: {
            emptyTable: "ไม่มีคำขอออกนอกบริเวณ",
            zeroRecords: "ไม่พบข้อมูล"
        }
    });

    // 2. Load Teacher List for Dropdown
    $.get('api/get_teachers.php', function(res) {
        if(res.status === 'success') {
            teachers = res.data;
            let options = '<option value="">เลือกครูสอนแทน</option>';
            teachers.forEach(t => {
                options += `<option value="${t.t_id}">${t.t_name}</option>`;
            });
            $('#sub_teacher_id').html(options);
        }
    });

    // 3. Logic Show/Hide Substitution Cart
    $('#hasClassSwitch').change(function() {
        if($(this).is(':checked')) {
            $('#substitutionSection').slideDown();
        } else {
            $('#substitutionSection').slideUp();
            cart = []; 
            renderCart();
        }
    });

    // 4. Cart Logic
    $('#addToCart').click(function() {
        const period = $('#sub_period').val();
        const subject = $('#sub_subject').val();
        const class_level = $('#sub_class').val();
        const sub_teacher_id = $('#sub_teacher_id').val();
        const sub_teacher_name = $('#sub_teacher_id option:selected').text();

        if(!period || !subject || !class_level || !sub_teacher_id) {
            Swal.fire({
                title: 'Data Incomplete', text: 'กรุณากรอกข้อมูลสอนแทนให้ครบถ้วน', icon: 'warning',
                customClass: { popup: 'rounded-[2rem]', confirmButton: 'bg-blue-600 rounded-xl px-10' }
            });
            return;
        }

        cart.push({ period, subject, class_level, sub_teacher_id, sub_teacher_name });
        renderCart();
        $('#sub_period, #sub_subject, #sub_class').val('');
        $('#sub_teacher_id').val('');
    });

    function renderCart() {
        let html = '';
        if(cart.length === 0) {
            html = '<tr><td colspan="4" class="text-center py-6 text-slate-300 font-bold italic">No items added to cart.</td></tr>';
        } else {
            cart.forEach((item, index) => {
                html += `<tr class="hover:bg-slate-50/50 transition-all">
                    <td class="px-6 py-4 font-bold text-slate-700">${item.period}</td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800">${item.subject}</div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">${item.class_level}</div>
                    </td>
                    <td class="px-6 py-4 font-bold text-indigo-500">${item.sub_teacher_name}</td>
                    <td class="px-6 py-4 text-right">
                        <button type="button" class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition-all remove-item" data-index="${index}">
                            <i class="bi bi-trash3-fill"></i>
                        </button>
                    </td>
                </tr>`;
            });
        }
        $('#cartItems').html(html);
    }

    $(document).on('click', '.remove-item', function() {
        const index = $(this).data('index');
        cart.splice(index, 1);
        renderCart();
    });

    // 5. Submit Form
    $('#saveRequest').click(function() {
        const formData = {
            reason: $('input[name="reason"]').val(),
            detail: $('textarea[name="detail"]').val(),
            time_start: $('input[name="time_start"]').val(),
            time_end: $('input[name="time_end"]').val(),
            total_hr: $('input[name="total_hr"]').val(),
            has_class: $('#hasClassSwitch').is(':checked'),
            cart: cart
        };

        if(!formData.reason || !formData.time_start || !formData.time_end) {
            Swal.fire({
                title: 'Required Fields', text: 'กรุณากรอกข้อมูลหลักให้ครบถ้วน', icon: 'warning',
                customClass: { popup: 'rounded-[2rem]', confirmButton: 'bg-blue-600 rounded-xl px-10' }
            });
            return;
        }

        Swal.fire({
            title: 'Confirm Submission?',
            text: "คำขอของคุณจะถูกส่งไปเพื่ออนุมัติทาง Telegram",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Submit',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'rounded-[2.5rem]', confirmButton: 'bg-blue-600 rounded-2xl px-10 py-3', cancelButton: 'bg-slate-100 text-slate-400 rounded-2xl px-10 py-3' }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/save_request.php',
                    type: 'POST',
                    data: JSON.stringify(formData),
                    contentType: 'application/json',
                    success: function(res) {
                        if(res.status === 'success') {
                            Swal.fire({ title: 'Success!', text: res.message, icon: 'success', customClass: { popup: 'rounded-[2rem]' } });
                            $('#requestModal').modal('hide');
                            $('#requestForm')[0].reset();
                            cart = [];
                            renderCart();
                            table.ajax.reload();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // 6. Approval Action (Admin Only)
    $(document).on('click', '.approve-btn', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Fast Approve?',
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Approve',
            cancelButtonText: 'Cancel',
            customClass: { popup: 'rounded-[2.5rem]', confirmButton: 'rounded-2xl px-10 py-3', cancelButton: 'rounded-2xl px-10 py-3' }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/approve_action.php',
                    type: 'POST',
                    data: JSON.stringify({ r_id: id, status: 1 }),
                    contentType: 'application/json',
                    success: function(res) {
                        Swal.fire({ title: 'Done!', text: res.message, icon: 'success', customClass: { popup: 'rounded-[2rem]' } });
                        table.ajax.reload();
                    }
                });
            }
        });
    });
});
</script>

<?php require_once 'components/layout_end.php'; ?>
