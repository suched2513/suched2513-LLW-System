<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
$allowed = ['att_teacher', 'super_admin', 'club_admin', 'wfh_admin', 'wfh_staff', 'finance_head', 'procurement_head', 'deputy_director', 'director', 'cb_admin', 'bus_admin', 'bus_finance'];
if (!in_array($_SESSION['llw_role'], $allowed, true)) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$pageTitle    = 'ระบบชุมนุม';
$pageSubtitle = 'จัดการชุมนุมและกิจกรรมนักเรียน';
$activeSystem = 'club';
$userRole     = $_SESSION['llw_role'];
$teacherId    = $_SESSION['teacher_id'] ?? 0;

$pdo = getPdo();
$activeSetting = $pdo->query("SELECT semester, year FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$currentSem  = $activeSetting ? (int)$activeSetting['semester'] : (function_exists('llw_current_semester') ? llw_current_semester() : 2);
$currentYear = $activeSetting ? (int)$activeSetting['year']     : (function_exists('llw_current_academic_year') ? llw_current_academic_year() : 2569);

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">

    <!-- Top Action & Semester Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="fw-bold text-muted small text-uppercase me-1"><i class="fas fa-calendar-alt text-violet-600 me-1" style="color:#7c3aed"></i>ภาคเรียน:</div>
                <select id="term_selector" onchange="switchTerm(this.value)" class="form-select form-select-sm fw-bold rounded-3 shadow-none border-secondary-subtle" style="width:auto;min-width:180px">
                    <option value="2-2569" <?= ($currentSem == 2 && $currentYear == 2569) ? 'selected' : '' ?>>ภาคเรียนที่ 2/2569 <?= ($currentSem == 2 && $currentYear == 2569) ? '★ (ปัจจุบัน)' : '' ?></option>
                    <option value="1-2569" <?= ($currentSem == 1 && $currentYear == 2569) ? 'selected' : '' ?>>ภาคเรียนที่ 1/2569 <?= ($currentSem == 1 && $currentYear == 2569) ? '★ (ปัจจุบัน)' : '' ?></option>
                </select>
                <span id="term_active_badge" class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small">
                    <i class="fas fa-check-circle me-1"></i>ภาคเรียนปัจจุบัน
                </span>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                <?php if (in_array($userRole, ['super_admin', 'club_admin', 'att_teacher'])): ?>
                <button type="button" onclick="confirmCloneClubs()" class="btn btn-sm btn-outline-primary rounded-3">
                    <i class="fas fa-clone me-1"></i>คัดลอกชุมนุมจากเทอม 1
                </button>
                <?php endif; ?>

                <?php if (in_array($userRole, ['super_admin', 'club_admin', 'att_teacher'])): ?>
                <a id="btn_print_summary" href="/club/print_summary.php?semester=<?= $currentSem ?>&year=<?= $currentYear ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-3">
                    <i class="fas fa-print me-1"></i>พิมพ์สรุป
                </a>
                <?php endif; ?>

                <?php if (in_array($userRole, ['super_admin', 'club_admin'])): ?>
                <a href="/club/settings.php" class="btn btn-sm btn-outline-secondary rounded-3" title="ตั้งค่าภาคเรียนและลงทะเบียน">
                    <i class="fas fa-cog me-1"></i>ตั้งค่าเทอม
                </a>
                <a href="/club/manage.php" class="btn btn-sm rounded-3 text-white" style="background:#7c3aed">
                    <i class="fas fa-plus me-1"></i>สร้างชุมนุม
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

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

    <!-- Club Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between py-3">
            <div class="d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-users me-2" style="color:#7c3aed"></i>รายชื่อชุมนุม</h6>
                <span id="table_term_badge" class="badge bg-light text-dark border small fw-normal">ภาคเรียนที่ <?= $currentSem ?>/<?= $currentYear ?></span>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="table_loading" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>กำลังโหลด...</div>
            
            <!-- Empty state with 1-click clone suggestion -->
            <div id="table_empty" class="text-center py-5 px-3 text-muted d-none">
                <i class="fas fa-inbox fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                <h6 class="fw-bold text-dark mb-1">ยังไม่มีรายชื่อชุมนุมในภาคเรียนที่เลือก</h6>
                <p class="small text-muted mb-3" id="table_empty_sub">ท่านสามารถคัดลอกรายชื่อชุมนุมจากภาคเรียนที่ 1 หรือสร้างชุมนุมใหม่ได้ทันที</p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <?php if (in_array($userRole, ['super_admin', 'club_admin', 'att_teacher'])): ?>
                    <button type="button" onclick="confirmCloneClubs()" class="btn btn-sm text-white rounded-3 px-3" style="background:#7c3aed">
                        <i class="fas fa-clone me-1"></i>คัดลอกชุมนุมจากภาคเรียนที่ 1 สู่เทอม 2
                    </button>
                    <?php endif; ?>
                    <?php if (in_array($userRole, ['super_admin', 'club_admin'])): ?>
                    <a href="/club/manage.php" class="btn btn-sm btn-outline-secondary rounded-3 px-3">
                        <i class="fas fa-plus me-1"></i>สร้างชุมนุมใหม่
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="clubs_table" style="display:none">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ชุมนุม</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ครูที่ปรึกษา</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ห้อง</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">สมาชิก</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">คาบที่จัดแล้ว</th>
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
let activeSystemSem = <?= (int)$currentSem ?>;
let activeSystemYear = <?= (int)$currentYear ?>;
let selectedSem = <?= (int)$currentSem ?>;
let selectedYear = <?= (int)$currentYear ?>;

const STATUS_LABEL = { draft:'ร่าง', open:'เปิดรับ', closed:'ปิด', archived:'เก็บถาวร' };
const STATUS_CLS   = { draft:'bg-secondary', open:'bg-success', closed:'bg-danger', archived:'bg-dark' };

function switchTerm(val) {
    const parts = val.split('-');
    if (parts.length === 2) {
        selectedSem = parseInt(parts[0]);
        selectedYear = parseInt(parts[1]);
        updateTermUI();
        loadClubs();
    }
}

function updateTermUI() {
    const isCurrent = (selectedSem === activeSystemSem && selectedYear === activeSystemYear);
    const badge = document.getElementById('term_active_badge');
    if (badge) {
        badge.className = isCurrent 
            ? 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small' 
            : 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 small';
        badge.innerHTML = isCurrent 
            ? '<i class="fas fa-check-circle me-1"></i>ภาคเรียนปัจจุบัน' 
            : '<i class="fas fa-history me-1"></i>ข้อมูลย้อนหลัง';
    }

    const tBadge = document.getElementById('table_term_badge');
    if (tBadge) {
        tBadge.textContent = `ภาคเรียนที่ ${selectedSem}/${selectedYear}`;
    }

    const printBtn = document.getElementById('btn_print_summary');
    if (printBtn) {
        printBtn.href = `/club/print_summary.php?semester=${selectedSem}&year=${selectedYear}`;
    }
}

async function loadClubs() {
    document.getElementById('table_loading').classList.remove('d-none');
    document.getElementById('table_empty').classList.add('d-none');
    document.getElementById('clubs_table').style.display = 'none';

    try {
        const res = await fetch(`/club/api/clubs_list.php?semester=${selectedSem}&year=${selectedYear}`);
        const data = await res.json();
        document.getElementById('table_loading').classList.add('d-none');

        if (data.active_semester && data.active_year) {
            activeSystemSem = parseInt(data.active_semester);
            activeSystemYear = parseInt(data.active_year);
            updateTermUI();
        }

        // Populate terms in dropdown if returned and not present
        if (data.terms && Array.isArray(data.terms)) {
            const sel = document.getElementById('term_selector');
            const currentVal = `${selectedSem}-${selectedYear}`;
            let html = '';
            data.terms.forEach(t => {
                const val = `${t.semester}-${t.year}`;
                const isCur = (t.semester == activeSystemSem && t.year == activeSystemYear);
                const label = `ภาคเรียนที่ ${t.semester}/${t.year}` + (isCur ? ' ★ (ปัจจุบัน)' : '');
                html += `<option value="${val}" ${val === currentVal ? 'selected' : ''}>${label}</option>`;
            });
            if (html) sel.innerHTML = html;
        }

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
            if (userRole === 'super_admin' || userRole === 'club_admin') {
                btns += `<a href="/club/manage.php?id=${c.id}" class="btn btn-outline-warning btn-sm rounded-2 me-1" title="แก้ไข"><i class="fas fa-edit"></i></a>
                         <button onclick="deleteClub(${c.id},'${escHtml(c.name)}')" class="btn btn-outline-danger btn-sm rounded-2" title="ลบ"><i class="fas fa-trash"></i></button>`;
            } else if (userRole === 'att_teacher') {
                const myId = parseInt(teacherId) || 0;
                const advisors = [parseInt(c.teacher_id)||0, parseInt(c.teacher_id_2)||0, parseInt(c.teacher_id_3)||0];
                if (myId > 0 && advisors.includes(myId)) {
                    btns += `<a href="/club/manage.php?id=${c.id}" class="btn btn-outline-warning btn-sm rounded-2 me-1" title="แก้ไข"><i class="fas fa-edit"></i></a>`;
                }
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-3 py-3">
                    <div class="fw-bold small">${escHtml(c.name)}</div>
                    ${c.objectives ? `<div class="text-muted" style="font-size:.75rem;max-width:220px" class="text-truncate">${escHtml(c.objectives.substring(0,60))}...</div>` : ''}
                </td>
                <td class="px-3 py-3 small">
                    ${[c.teacher_name, c.teacher_name_2, c.teacher_name_3].filter(t => t).join('<br>')}
                </td>
                <td class="px-3 py-3 small">${escHtml(c.room || '-')}</td>
                <td class="px-3 py-3 text-center">
                    <div class="small fw-bold">${c.registered_count}/${c.max_capacity}</div>
                    <div class="progress mt-1" style="height:4px;width:80px;margin:0 auto">
                        <div class="progress-bar ${barCls}" style="width:${pct}%"></div>
                    </div>
                </td>
                <td class="px-3 py-3 text-center">
                    ${parseInt(c.session_count) > 0
                        ? `<span class="fw-bold">${c.session_count}</span>`
                        : `<span class="badge bg-danger rounded-pill" title="ยังไม่จัดกิจกรรมเลยสักครั้ง"><i class="fas fa-exclamation-triangle me-1"></i>ยังไม่จัด</span>`}
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
        document.getElementById('table_loading').innerHTML = '<div class="text-danger py-5 text-center">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
    }
}

function setKpi(clubs, reg, unreg, sess) {
    document.getElementById('kpi_clubs').textContent      = clubs;
    document.getElementById('kpi_registered').textContent = reg;
    document.getElementById('kpi_unreg').textContent      = Math.max(0, unreg);
    document.getElementById('kpi_sessions').textContent   = sess;
}

async function confirmCloneClubs() {
    const { isConfirmed } = await Swal.fire({
        icon: 'question',
        title: 'คัดลอกชุมนุมสู่ภาคเรียนที่ 2?',
        html: `ต้องการคัดลอกรายชื่อชุมนุมเดิมจาก <b>ภาคเรียนที่ 1/2569</b> มายัง <b>ภาคเรียนที่ 2/2569</b> หรือไม่?<br><small class="text-muted">ระบบจะคัดลอกชื่อชุมนุม ครูที่ปรึกษา และห้อง โดยเปิดสถานะเป็น 'เปิดรับ' และเริ่มรับนักเรียนใหม่</small>`,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-clone me-1"></i>ยืนยันคัดลอก',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#7c3aed'
    });
    if (!isConfirmed) return;

    Swal.fire({ title: 'กำลังคัดลอกข้อมูล...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('/club/api/clone_clubs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ from_semester: 1, from_year: 2569, to_semester: 2, to_year: 2569 })
        });
        const data = await res.json();
        if (data.status === 'success') {
            await Swal.fire({
                icon: 'success',
                title: 'คัดลอกเรียบร้อย',
                text: data.message,
                confirmButtonColor: '#7c3aed'
            });
            // Switch dropdown to Term 2 and reload
            selectedSem = 2;
            selectedYear = 2569;
            const sel = document.getElementById('term_selector');
            if (sel) sel.value = '2-2569';
            updateTermUI();
            loadClubs();
        } else {
            Swal.fire({ icon: 'warning', title: 'แจ้งเตือน', text: data.message, confirmButtonColor: '#7c3aed' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message, confirmButtonColor: '#7c3aed' });
    }
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
document.addEventListener('DOMContentLoaded', () => {
    updateTermUI();
    loadClubs();
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
