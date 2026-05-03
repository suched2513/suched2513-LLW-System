<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
$allowed = ['att_teacher', 'super_admin', 'wfh_admin'];
if (!in_array($_SESSION['llw_role'], $allowed, true)) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$pageTitle    = 'ระบบชุมนุม';
$pageSubtitle = 'จัดการชุมนุมและกิจกรรม';
$activeSystem = 'club';
$userRole     = $_SESSION['llw_role'];
$teacherId    = $_SESSION['teacher_id'] ?? 0;
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">

    <!-- KPI Row -->
    <div class="row g-3 mb-4" id="kpi_row">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 text-white h-100" style="background:linear-gradient(135deg,#7c3aed,#6d28d9)">
                <div class="card-body py-3">
                    <div class="small fw-bold opacity-75 text-uppercase">ชุมนุมทั้งหมด</div>
                    <div class="fs-2 fw-black" id="kpi_clubs">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 text-white h-100" style="background:linear-gradient(135deg,#059669,#047857)">
                <div class="card-body py-3">
                    <div class="small fw-bold opacity-75 text-uppercase">ลงทะเบียนแล้ว</div>
                    <div class="fs-2 fw-black" id="kpi_registered">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 text-white h-100" style="background:linear-gradient(135deg,#d97706,#b45309)">
                <div class="card-body py-3">
                    <div class="small fw-bold opacity-75 text-uppercase">ยังไม่ลงทะเบียน</div>
                    <div class="fs-2 fw-black" id="kpi_unreg">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 text-white h-100" style="background:linear-gradient(135deg,#0891b2,#0e7490)">
                <div class="card-body py-3">
                    <div class="small fw-bold opacity-75 text-uppercase">คาบที่จัดแล้ว</div>
                    <div class="fs-2 fw-black" id="kpi_sessions">—</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Club Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between py-3">
            <h6 class="mb-0 fw-bold"><i class="fas fa-users me-2" style="color:#7c3aed"></i>รายชื่อชุมนุม</h6>
            <?php if ($userRole === 'super_admin'): ?>
            <a href="/club/manage.php" class="btn btn-sm rounded-3 text-white" style="background:#7c3aed">
                <i class="fas fa-plus me-1"></i>สร้างชุมนุม
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div id="table_loading" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>กำลังโหลด...</div>
            <div id="table_empty" class="text-center py-5 text-muted d-none"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>ไม่มีชุมนุม</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="clubs_table" style="display:none">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ชุมนุม</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ครูที่ปรึกษา</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ห้อง</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">สมาชิก</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">สถานะ</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="clubs_tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const userRole = <?= json_encode($userRole) ?>;
const teacherId = <?= json_encode($teacherId) ?>;
const STATUS_LABEL = { draft:'ร่าง', open:'เปิดรับ', closed:'ปิด', archived:'เก็บถาวร' };
const STATUS_CLS   = { draft:'bg-secondary', open:'bg-success', closed:'bg-danger', archived:'bg-dark' };

async function loadClubs() {
    try {
        const res  = await fetch('/club/api/clubs_list.php');
        const data = await res.json();
        document.getElementById('table_loading').classList.add('d-none');

        if (!data.data || !data.data.length) {
            document.getElementById('table_empty').classList.remove('d-none');
            setKpi(0, 0, 0, 0);
            return;
        }

        let totalReg = 0, totalSess = 0;
        const tbody = document.getElementById('clubs_tbody');
        tbody.innerHTML = '';
        data.data.forEach(c => {
            totalReg  += parseInt(c.registered_count) || 0;
            totalSess += parseInt(c.session_count) || 0;
            const pct = c.max_capacity > 0 ? Math.round(c.registered_count / c.max_capacity * 100) : 0;
            const barCls = pct >= 90 ? 'bg-danger' : pct >= 70 ? 'bg-warning' : 'bg-success';

            let btns = `<a href="/club/sessions.php?club_id=${c.id}" class="btn btn-outline-primary btn-sm rounded-2 me-1" title="คาบ"><i class="fas fa-calendar-alt"></i></a>
                        <a href="/club/members.php?club_id=${c.id}" class="btn btn-outline-secondary btn-sm rounded-2 me-1" title="สมาชิก"><i class="fas fa-users"></i></a>`;
            if (userRole === 'super_admin') {
                btns += `<a href="/club/manage.php?id=${c.id}" class="btn btn-outline-warning btn-sm rounded-2 me-1" title="แก้ไข"><i class="fas fa-edit"></i></a>
                         <button onclick="deleteClub(${c.id},'${escHtml(c.name)}')" class="btn btn-outline-danger btn-sm rounded-2" title="ลบ"><i class="fas fa-trash"></i></button>`;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-3 py-3">
                    <div class="fw-bold small">${escHtml(c.name)}</div>
                    ${c.objectives ? `<div class="text-muted" style="font-size:.75rem;max-width:220px" class="text-truncate">${escHtml(c.objectives.substring(0,60))}...</div>` : ''}
                </td>
                <td class="px-3 py-3 small">${escHtml(c.teacher_name || '-')}</td>
                <td class="px-3 py-3 small">${escHtml(c.room || '-')}</td>
                <td class="px-3 py-3 text-center">
                    <div class="small fw-bold">${c.registered_count}/${c.max_capacity}</div>
                    <div class="progress mt-1" style="height:4px;width:80px;margin:0 auto">
                        <div class="progress-bar ${barCls}" style="width:${pct}%"></div>
                    </div>
                </td>
                <td class="px-3 py-3 text-center">
                    <span class="badge rounded-pill ${STATUS_CLS[c.status] || 'bg-secondary'}">${STATUS_LABEL[c.status] || c.status}</span>
                </td>
                <td class="px-3 py-3 text-center text-nowrap">${btns}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('clubs_table').style.display = '';
        setKpi(data.data.length, totalReg, data.total_students - totalReg, totalSess);
    } catch(e) {
        document.getElementById('table_loading').innerHTML = '<div class="text-danger py-5 text-center">เกิดข้อผิดพลาด</div>';
    }
}

function setKpi(clubs, reg, unreg, sess) {
    document.getElementById('kpi_clubs').textContent      = clubs;
    document.getElementById('kpi_registered').textContent = reg;
    document.getElementById('kpi_unreg').textContent      = Math.max(0, unreg);
    document.getElementById('kpi_sessions').textContent   = sess;
}

async function deleteClub(id, name) {
    const { isConfirmed } = await Swal.fire({
        icon:'warning', title:'ลบชุมนุม?', text:`"${name}" จะถูกลบถาวร`,
        showCancelButton:true, confirmButtonText:'ลบ', cancelButtonText:'ยกเลิก',
        confirmButtonColor:'#dc3545'
    });
    if (!isConfirmed) return;
    const res  = await fetch('/club/api/delete_club.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id}) });
    const data = await res.json();
    if (data.status === 'success') {
        Swal.fire({ icon:'success', title:'ลบสำเร็จ', timer:1200, showConfirmButton:false });
        loadClubs();
    } else {
        Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:data.message, confirmButtonColor:'#7c3aed' });
    }
}

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
document.addEventListener('DOMContentLoaded', loadClubs);
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
