<?php
session_start();
$pageTitle = 'คำขอรอพิจารณา';
$pageSubtitle = 'รายการคำขอดำเนินโครงการที่รอการอนุมัติจากผู้อำนวยการ';
require_once __DIR__ . '/../components/layout_start.php';

checkRole('director');

$pdo = getPdo();

$stmt = $pdo->query("
    SELECT r.*, p.project_name, p.activity, u.full_name as teacher_name
    FROM project_requests r
    JOIN budget_projects p ON r.budget_project_id = p.id
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'submitted'
    ORDER BY r.created_at ASC
");
$requests = $stmt->fetchAll();
?>

<div class="grid grid-cols-1 gap-6">
    <?php if (empty($requests)): ?>
        <div class="bg-white rounded-[2.5rem] p-12 text-center border border-slate-100 shadow-xl shadow-slate-200/50">
            <i class="bi bi-check-circle text-5xl text-emerald-200 mb-4 block"></i>
            <p class="text-slate-400 font-bold">ไม่มีรายการที่รอการพิจารณาในขณะนี้</p>
        </div>
    <?php endif; ?>

    <?php foreach ($requests as $r): ?>
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl shadow-slate-200/50 flex flex-col lg:flex-row items-center gap-8 group hover:border-blue-200 transition-all">
        <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-[1.5rem] flex items-center justify-center text-3xl flex-shrink-0">
            <i class="bi bi-file-earmark-text"></i>
        </div>
        
        <div class="flex-1 text-center lg:text-left">
            <div class="flex items-center justify-center lg:justify-start gap-2 mb-2">
                <span class="text-[10px] font-black bg-blue-100 text-blue-600 px-2 py-0.5 rounded uppercase"><?= date('d/m/Y', strtotime($r['request_date'])) ?></span>
                <span class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-0.5 rounded uppercase"><?= htmlspecialchars($r['proc_type'] === 'buy' ? 'จัดซื้อ' : 'จัดจ้าง') ?></span>
            </div>
            <h4 class="text-lg font-black text-slate-800 leading-tight mb-1"><?= htmlspecialchars($r['project_name']) ?></h4>
            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-2"><?= htmlspecialchars($r['activity']) ?></p>
            <p class="text-sm text-slate-600">ผู้ขอ: <span class="font-bold"><?= htmlspecialchars($r['teacher_name']) ?></span></p>
            <p class="text-sm text-slate-500 mt-2 italic">"<?= htmlspecialchars($r['reason']) ?>"</p>
        </div>

        <div class="text-center lg:text-right flex-shrink-0">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ยอดเงินที่ขอใช้</p>
            <p class="text-3xl font-black text-slate-800 underline decoration-blue-200 underline-offset-4"><?= number_format($r['amount_requested'], 2) ?></p>
            <p class="text-xs text-slate-400 font-bold mt-1">บาท</p>
        </div>

        <div class="flex gap-2 flex-shrink-0">
            <button onclick='approveRequest(<?= $r['id'] ?>)' class="bg-emerald-500 text-white px-6 py-3 rounded-2xl font-black text-sm shadow-lg shadow-emerald-200 hover:bg-emerald-600 transition-all flex items-center gap-2">
                <i class="bi bi-check-lg"></i>
                อนุมัติ
            </button>
            <button onclick='rejectRequest(<?= $r['id'] ?>)' class="bg-white border-2 border-rose-500 text-rose-500 px-6 py-3 rounded-2xl font-black text-sm hover:bg-rose-50 transition-all flex items-center gap-2">
                <i class="bi bi-x-lg"></i>
                ไม่อนุมัติ
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Approval Actions Form (Hidden) -->
<form id="actionForm" action="approve.php" method="POST" class="hidden">
    <input type="hidden" name="id" id="requestId">
    <input type="hidden" name="status" id="requestStatus">
    <input type="hidden" name="note" id="requestNote">
</form>

<script>
function approveRequest(id) {
    Swal.fire({
        title: 'ยืนยันการอนุมัติ?',
        text: 'คุณต้องการอนุมัติคำขอดำเนินโครงการนี้ใช่หรือไม่',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, อนุมัติเลย',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-[2rem]', confirmButton: 'rounded-xl px-6', cancelButton: 'rounded-xl px-6' }
    }).then((result) => {
        if (result.isConfirmed) {
            submitAction(id, 'approved', '');
        }
    });
}

function rejectRequest(id) {
    Swal.fire({
        title: 'ไม่อนุมัติคำขอ?',
        text: 'กรุณาระบุเหตุผลที่ไม่สามารถอนุมัติได้',
        input: 'textarea',
        inputPlaceholder: 'ระบุหมายเหตุที่นี่...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f43f5e',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ยืนยันไม่อนุมัติ',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-[2rem]', confirmButton: 'rounded-xl px-6', cancelButton: 'rounded-xl px-6' }
    }).then((result) => {
        if (result.isConfirmed) {
            submitAction(id, 'rejected', result.value);
        }
    });
}

function submitAction(id, status, note) {
    document.getElementById('requestId').value = id;
    document.getElementById('requestStatus').value = status;
    document.getElementById('requestNote').value = note;
    document.getElementById('actionForm').submit();
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
