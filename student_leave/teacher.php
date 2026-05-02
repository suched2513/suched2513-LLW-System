<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
$allowed_roles = ['att_teacher', 'super_admin', 'wfh_admin'];
if (!in_array($_SESSION['llw_role'], $allowed_roles, true)) {
    header('Location: ' . $base_path . '/login.php');
    exit();
}

$pageTitle    = 'ใบลานักเรียน';
$pageSubtitle = 'อนุมัติและจัดการใบลานักเรียน';
$activeSystem = 'student_leave';

require_once __DIR__ . '/../components/layout_start.php';

$userRole = $_SESSION['llw_role'];
$status_filter    = $_GET['status']    ?? 'all';
$classroom_filter = $_GET['classroom'] ?? '';
$allowed_statuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($status_filter, $allowed_statuses, true)) $status_filter = 'all';
?>

<!-- Page Content -->
<div class="container-fluid">

    <!-- Filter Bar -->
    <div class="card rounded-3 border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-uppercase text-muted">สถานะ</label>
                    <select name="status" class="form-select rounded-3">
                        <option value="all"      <?= $status_filter === 'all'      ? 'selected' : '' ?>>ทั้งหมด</option>
                        <option value="pending"  <?= $status_filter === 'pending'  ? 'selected' : '' ?>>รอการอนุมัติ</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>ไม่อนุมัติ</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-uppercase text-muted">ห้องเรียน</label>
                    <input type="text" name="classroom" value="<?= htmlspecialchars($classroom_filter, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="เช่น ม.1/1" class="form-control rounded-3">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary rounded-3 w-100">
                        <i class="fas fa-filter me-1"></i>กรอง
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card rounded-3 border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between py-3">
            <h6 class="mb-0 fw-bold">
                <i class="fas fa-file-medical-alt text-danger me-2"></i>รายการใบลานักเรียน
            </h6>
            <span id="total_badge" class="badge bg-secondary rounded-pill">กำลังโหลด...</span>
        </div>
        <div class="card-body p-0">
            <div id="table_loading" class="text-center py-5 text-muted">
                <i class="fas fa-spinner fa-spin me-2"></i>กำลังโหลดข้อมูล...
            </div>
            <div id="table_empty" class="text-center py-5 text-muted d-none">
                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                ไม่มีรายการใบลา
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="requests_table" style="display:none">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">นักเรียน</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ประเภท</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">วันที่ลา</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">เหตุผล</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">สถานะ</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="requests_tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
const TYPE_LABELS   = { sick: 'ลาป่วย', personal: 'ลากิจ', other: 'ลาอื่นๆ' };
const STATUS_LABELS = { pending: 'รอการอนุมัติ', approved: 'อนุมัติแล้ว', rejected: 'ไม่อนุมัติ' };
const STATUS_BADGE  = {
    pending:  'bg-warning text-dark',
    approved: 'bg-success',
    rejected: 'bg-danger',
};

const statusFilter    = <?= json_encode($status_filter) ?>;
const classroomFilter = <?= json_encode($classroom_filter) ?>;

async function loadRequests() {
    let url = '/student_leave/api/list.php?context=teacher&status=' + encodeURIComponent(statusFilter);
    if (classroomFilter) url += '&classroom=' + encodeURIComponent(classroomFilter);

    try {
        const res  = await fetch(url);
        const data = await res.json();

        document.getElementById('table_loading').classList.add('d-none');

        if (!data.data || data.data.length === 0) {
            document.getElementById('table_empty').classList.remove('d-none');
            document.getElementById('total_badge').textContent = '0 รายการ';
            return;
        }

        document.getElementById('total_badge').textContent = data.data.length + ' รายการ';
        const tbody = document.getElementById('requests_tbody');
        tbody.innerHTML = '';

        data.data.forEach(r => {
            const typeLabel   = TYPE_LABELS[r.leave_type]  || r.leave_type;
            const statusLabel = STATUS_LABELS[r.status]     || r.status;
            const badgeCls    = STATUS_BADGE[r.status]       || 'bg-secondary';
            const dateRange   = r.date_from === r.date_to
                ? formatDate(r.date_from)
                : formatDate(r.date_from) + ' – ' + formatDate(r.date_to);

            let actionBtns = '';
            if (r.status === 'pending') {
                actionBtns = `
                    <button onclick="handleAction(${r.id},'approved')"
                            class="btn btn-success btn-sm rounded-2 me-1" title="อนุมัติ">
                        <i class="fas fa-check"></i>
                    </button>
                    <button onclick="handleAction(${r.id},'rejected')"
                            class="btn btn-danger btn-sm rounded-2 me-1" title="ปฏิเสธ">
                        <i class="fas fa-times"></i>
                    </button>`;
            }
            actionBtns += `<a href="/student_leave/print.php?id=${r.id}" target="_blank"
                              class="btn btn-outline-secondary btn-sm rounded-2" title="พิมพ์">
                              <i class="fas fa-print"></i>
                           </a>`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-3 py-3">
                    <div class="fw-bold small">${escHtml(r.student_name || r.student_id)}</div>
                    <div class="text-muted" style="font-size:.75rem">${escHtml(r.classroom || '')} · ${escHtml(r.student_id)}</div>
                </td>
                <td class="px-3 py-3">
                    <span class="badge rounded-pill bg-light text-dark border">${typeLabel}</span>
                </td>
                <td class="px-3 py-3 small">
                    <div>${dateRange}</div>
                    <div class="text-muted">${r.days} วัน</div>
                </td>
                <td class="px-3 py-3 small" style="max-width:200px">
                    <div class="text-truncate" title="${escHtml(r.reason)}">${escHtml(r.reason)}</div>
                    ${r.parent_name ? `<div class="text-muted">${escHtml(r.parent_name)} ${escHtml(r.parent_phone || '')}</div>` : ''}
                </td>
                <td class="px-3 py-3">
                    <span class="badge rounded-pill ${badgeCls}">${statusLabel}</span>
                    ${r.teacher_note ? `<div class="text-muted mt-1" style="font-size:.7rem">${escHtml(r.teacher_note)}</div>` : ''}
                </td>
                <td class="px-3 py-3 text-center text-nowrap">${actionBtns}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('requests_table').style.display = '';

    } catch (err) {
        document.getElementById('table_loading').innerHTML =
            '<div class="text-danger py-5 text-center"><i class="fas fa-exclamation-circle me-2"></i>เกิดข้อผิดพลาด กรุณาลองใหม่</div>';
    }
}

async function handleAction(id, action) {
    const actionLabel = action === 'approved' ? 'อนุมัติ' : 'ปฏิเสธ';
    const { value: note, isConfirmed } = await Swal.fire({
        title: actionLabel + 'ใบลา',
        input: 'textarea',
        inputLabel: 'หมายเหตุ (ไม่บังคับ)',
        inputPlaceholder: 'ระบุหมายเหตุ...',
        inputAttributes: { maxlength: 500 },
        showCancelButton: true,
        confirmButtonText: actionLabel,
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: action === 'approved' ? '#198754' : '#dc3545',
        icon: action === 'approved' ? 'question' : 'warning',
    });

    if (!isConfirmed) return;

    try {
        const res = await fetch('/student_leave/api/approve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, action, note: note || '' }),
        });
        const data = await res.json();

        if (data.status === 'success') {
            await Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: data.message, timer: 1500, showConfirmButton: false });
            loadRequests();
        } else {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message, confirmButtonColor: '#2563eb' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อได้', confirmButtonColor: '#2563eb' });
    }
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    const p = dateStr.split('-');
    if (p.length < 3) return dateStr;
    return parseInt(p[2]) + ' ' + months[parseInt(p[1])] + ' ' + (parseInt(p[0]) + 543);
}

document.addEventListener('DOMContentLoaded', loadRequests);
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
