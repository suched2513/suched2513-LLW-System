<?php
/**
 * duty/admin/schedule.php — ตารางเวรรายสัปดาห์ (5 จุด × วันจันทร์-ศุกร์)
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin','wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();

// ── คำนวณสัปดาห์ (จันทร์-ศุกร์) ──
$weekParam = $_GET['week'] ?? date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($weekParam)));
$weekEnd   = date('Y-m-d', strtotime($weekStart . ' +4 days')); // Friday
$prevWeek  = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek  = date('Y-m-d', strtotime($weekStart . ' +7 days'));

// ── จำนวนจุดเวร (default 5) ──
try {
    $maxPts = (int)($pdo->query("SELECT svalue FROM duty_settings WHERE skey='max_duty_points'")->fetchColumn() ?: 5);
} catch(Exception $e) { $maxPts = 5; }

// ── ดึงตารางเวรทั้งสัปดาห์ ──
$stmtSched = $pdo->prepare("
    SELECT ds.duty_date, ds.point_no, ds.role,
           dt.id AS teacher_id, dt.prefix, dt.full_name
    FROM duty_schedule ds
    JOIN duty_teachers dt ON dt.id = ds.teacher_id
    WHERE ds.duty_date BETWEEN ? AND ? AND ds.shift = 'day'
    ORDER BY ds.duty_date, ds.point_no, ds.id
");
$stmtSched->execute([$weekStart, $weekEnd]);

// $slots[date][point_no][] = teacher
$slots = [];
foreach ($stmtSched->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $slots[$r['duty_date']][$r['point_no']][] = $r;
}

// ── ครูทั้งหมดสำหรับ dropdown ──
$teachers = $pdo->query(
    "SELECT id, prefix, full_name FROM duty_teachers WHERE status='active' ORDER BY full_name"
)->fetchAll(PDO::FETCH_ASSOC);

// ── Thai helpers ──
$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
function thDateShort($ymd) {
    global $thMonths;
    $ts  = strtotime($ymd);
    return date('j', $ts) . ' ' . $thMonths[(int)date('n', $ts)];
}
$thYear    = (int)date('Y', strtotime($weekEnd)) + 543;
$weekLabel = thDateShort($weekStart) . ' – ' . thDateShort($weekEnd) . ' ' . $thYear;
$dayNames  = ['จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์'];

$pageTitle    = 'ตารางเวร';
$pageSubtitle = 'จัดตารางเวรรายสัปดาห์';
$activeSystem = 'duty';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<style>
.duty-cell { min-width:130px; vertical-align:top; padding:6px 8px; cursor:pointer; transition:background .12s; }
.duty-cell:hover { background:rgba(13,110,253,.06); }
.duty-cell-inner { min-height:56px; display:flex; flex-direction:column; gap:3px; align-items:flex-start; }
.teacher-chip { display:inline-flex; align-items:center; gap:4px; background:#e7f0ff; color:#1d4ed8; border-radius:8px; padding:2px 8px; font-size:12px; font-weight:600; white-space:nowrap; max-width:100%; overflow:hidden; text-overflow:ellipsis; }
.teacher-chip.chip-2 { background:#fef3c7; color:#92400e; }
.add-chip { display:inline-flex; align-items:center; gap:3px; color:#adb5bd; font-size:12px; cursor:pointer; padding:2px 6px; border:1.5px dashed #dee2e6; border-radius:8px; transition:all .12s; }
.add-chip:hover { color:#2563eb; border-color:#2563eb; background:#f0f5ff; }
.point-label { font-weight:700; white-space:nowrap; min-width:70px; }
.today-col { background:rgba(13,110,253,.04) !important; }
</style>

<!-- ── Header ── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="fas fa-calendar-week me-2 text-primary"></i>ตารางเวรประจำสัปดาห์</h4>
        <small class="text-muted">คลิกช่องเพื่อกำหนดครูประจำจุดเวร (สูงสุด 2 คน/จุด)</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="teachers.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-users me-1"></i>จัดการครูเวร
        </a>
        <button class="btn btn-outline-danger btn-sm" onclick="confirmClearWeek()">
            <i class="fas fa-trash-alt me-1"></i>ล้างตารางสัปดาห์นี้
        </button>
    </div>
</div>

<!-- ── Week Navigation ── -->
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="?week=<?= $prevWeek ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-chevron-left"></i>
    </a>
    <h5 class="mb-0 fw-bold text-primary"><?= htmlspecialchars($weekLabel) ?></h5>
    <a href="?week=<?= $nextWeek ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-chevron-right"></i>
    </a>
    <a href="?week=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary ms-auto">วันนี้</a>
</div>

<?php if (empty($teachers)): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    ยังไม่มีครูเวรในระบบ — <a href="teachers.php" class="alert-link">เพิ่มครูก่อน</a> แล้วกลับมาจัดตาราง
</div>
<?php endif; ?>

<!-- ── Schedule Grid ── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center align-middle" style="width:90px; font-size:13px;">จุดเวร</th>
                        <?php for ($i = 0; $i < 5; $i++):
                            $day     = date('Y-m-d', strtotime($weekStart . " +$i days"));
                            $isToday = ($day === date('Y-m-d'));
                        ?>
                        <th class="text-center <?= $isToday ? 'table-primary' : '' ?>" style="min-width:140px;">
                            <div class="fw-bold"><?= $dayNames[$i] ?></div>
                            <div class="small <?= $isToday ? 'text-primary fw-bold' : 'text-muted' ?>">
                                <?= thDateShort($day) ?>
                            </div>
                        </th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                <?php for ($pt = 1; $pt <= $maxPts; $pt++): ?>
                <tr>
                    <td class="text-center align-middle point-label">
                        <span class="badge bg-secondary">จุดที่ <?= $pt ?></span>
                    </td>
                    <?php for ($i = 0; $i < 5; $i++):
                        $day    = date('Y-m-d', strtotime($weekStart . " +$i days"));
                        $isToday = ($day === date('Y-m-d'));
                        $assigned = $slots[$day][$pt] ?? [];
                    ?>
                    <td class="duty-cell <?= $isToday ? 'today-col' : '' ?>"
                        onclick="openAssign('<?= $day ?>',<?= $pt ?>)">
                        <div class="duty-cell-inner">
                            <?php if (!empty($assigned)): ?>
                                <?php foreach ($assigned as $idx => $t): ?>
                                <span class="teacher-chip <?= $idx > 0 ? 'chip-2' : '' ?>">
                                    <i class="fas fa-user" style="font-size:10px"></i>
                                    <?= htmlspecialchars(mb_substr($t['prefix'].$t['full_name'], 0, 14)) ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if (count($assigned) < 2): ?>
                                <span class="add-chip"><i class="fas fa-plus" style="font-size:10px"></i>เพิ่ม</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="add-chip"><i class="fas fa-plus" style="font-size:10px"></i>จัดเวร</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endfor; ?>
                </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Legend ── -->
<div class="mt-3 d-flex gap-3 align-items-center flex-wrap">
    <small class="text-muted fw-bold">สัญลักษณ์:</small>
    <span class="teacher-chip"><i class="fas fa-user" style="font-size:10px"></i>ครูคนที่ 1</span>
    <span class="teacher-chip chip-2"><i class="fas fa-user" style="font-size:10px"></i>ครูคนที่ 2</span>
    <span class="add-chip"><i class="fas fa-plus" style="font-size:10px"></i>ว่าง</span>
</div>

<!-- ═══ Modal: จัดจุดเวร ═══ -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                    จัดเวร — <span id="assignTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="assignBody">
                <div class="text-center py-3"><i class="fas fa-spinner fa-spin text-muted fa-2x"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" onclick="clearPoint()">
                    <i class="fas fa-times me-1"></i>ล้างจุดนี้
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="savePoint()">
                    <i class="fas fa-save me-1"></i>บันทึก
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>';
const apiUrl    = '../../duty/api/schedule_api.php';

let curDate  = '';
let curPoint = 0;

const thDays    = ['','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์','อาทิตย์'];
const teacherOpts = <?= json_encode(array_map(fn($t) => [
    'id'   => $t['id'],
    'name' => $t['prefix'] . $t['full_name']
], $teachers), JSON_UNESCAPED_UNICODE) ?>;

function openAssign(date, ptNo) {
    curDate  = date;
    curPoint = ptNo;

    const d     = new Date(date + 'T00:00:00');
    const dayTh = thDays[d.getDay()];
    document.getElementById('assignTitle').textContent = 'จุดที่ ' + ptNo + ' — ' + dayTh + ' ' + date;

    const body = document.getElementById('assignBody');
    body.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin text-muted fa-2x"></i></div>';
    new bootstrap.Modal(document.getElementById('assignModal')).show();

    fetch(`${apiUrl}?action=get_point&duty_date=${date}&point_no=${ptNo}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                body.innerHTML = '<p class="text-danger">โหลดข้อมูลไม่สำเร็จ</p>';
                return;
            }
            renderAssignForm(data.teachers || []);
        })
        .catch(() => { body.innerHTML = '<p class="text-danger">เชื่อมต่อ server ไม่ได้</p>'; });
}

function buildSelect(id, label, selectedId, excludeId) {
    const opts = teacherOpts
        .filter(t => !excludeId || t.id != excludeId)
        .map(t => `<option value="${t.id}" ${t.id == selectedId ? 'selected' : ''}>${t.name}</option>`)
        .join('');
    return `
    <div class="mb-3">
        <label class="form-label fw-bold">${label}</label>
        <select class="form-select" id="${id}" onchange="refreshSelects()">
            <option value="">— ไม่ระบุ —</option>
            ${opts}
        </select>
    </div>`;
}

function renderAssignForm(existing) {
    const t1 = existing[0] ? existing[0].teacher_id : '';
    const t2 = existing[1] ? existing[1].teacher_id : '';

    document.getElementById('assignBody').innerHTML = `
        <p class="text-muted small mb-3">
            <i class="fas fa-info-circle me-1"></i>
            เลือกครูประจำจุดเวรนี้ (สูงสุด 2 ท่าน) ไม่บังคับทั้งสองช่อง
        </p>
        ${buildSelect('sel1','ครูคนที่ 1',t1,t2)}
        ${buildSelect('sel2','ครูคนที่ 2',t2,t1)}
    `;
}

function refreshSelects() {
    const v1 = document.getElementById('sel1')?.value || '';
    const v2 = document.getElementById('sel2')?.value || '';
    // rebuild sel2 excluding sel1's value
    const existing = [{teacher_id: v1},{teacher_id: v2}].filter(t=>t.teacher_id);
    renderAssignForm(existing);
    // restore values
    if (v1) document.getElementById('sel1').value = v1;
    if (v2) document.getElementById('sel2').value = v2;
}

function savePoint() {
    const t1 = document.getElementById('sel1')?.value || '';
    const t2 = document.getElementById('sel2')?.value || '';
    const ids = [t1, t2].filter(v => v !== '');

    const fd = new FormData();
    fd.append('action', 'save_point');
    fd.append('csrf_token', csrfToken);
    fd.append('duty_date', curDate);
    fd.append('point_no', curPoint);
    ids.forEach(id => fd.append('teacher_ids[]', id));

    fetch(apiUrl, {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('assignModal'))?.hide();
                location.reload();
            } else {
                Swal.fire({icon:'error', title:'ผิดพลาด', text:d.message});
            }
        });
}

function clearPoint() {
    Swal.fire({
        icon:'warning', title:'ล้างจุดนี้?',
        text:'ครูที่จัดไว้จะถูกลบออกจากจุดที่ ' + curPoint,
        showCancelButton:true, confirmButtonColor:'#dc3545',
        confirmButtonText:'ล้างเลย', cancelButtonText:'ยกเลิก'
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'save_point');
        fd.append('csrf_token', csrfToken);
        fd.append('duty_date', curDate);
        fd.append('point_no', curPoint);
        // no teacher_ids → clears the slot
        fetch(apiUrl, {method:'POST', body:fd})
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('assignModal'))?.hide();
                    location.reload();
                }
            });
    });
}

function confirmClearWeek() {
    Swal.fire({
        icon:'warning', title:'ล้างตารางสัปดาห์นี้?',
        html:`ตารางเวร <b><?= htmlspecialchars($weekLabel, ENT_QUOTES) ?></b> จะถูกลบทั้งหมด`,
        showCancelButton:true, confirmButtonColor:'#dc3545',
        confirmButtonText:'ล้างเลย', cancelButtonText:'ยกเลิก'
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'clear_week');
        fd.append('csrf_token', csrfToken);
        fd.append('week_start', '<?= $weekStart ?>');
        fetch(apiUrl, {method:'POST', body:fd})
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') location.reload();
                else Swal.fire({icon:'error', title:'ผิดพลาด', text:d.message});
            });
    });
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
