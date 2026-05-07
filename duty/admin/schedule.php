<?php
/**
 * duty/admin/schedule.php — Week Calendar จัดตารางเวร (ออกแบบใหม่)
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin','wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();

// ── คำนวณสัปดาห์ปัจจุบัน ──
$weekParam  = $_GET['week'] ?? date('Y-m-d');
$weekStart  = date('Y-m-d', strtotime('monday this week', strtotime($weekParam)));
$weekEnd    = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$prevWeek   = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek   = date('Y-m-d', strtotime($weekStart . ' +7 days'));

// ── ดึงข้อมูลสัปดาห์นี้ ──
$ddgRows = $pdo->prepare("
    SELECT ddg.duty_date, ddg.shift, ddg.group_id,
           g.name AS group_name, g.color AS group_color,
           COUNT(DISTINCT m.id) AS member_count,
           COALESCE(pc.cnt, 0) AS point_count
    FROM duty_day_groups ddg
    LEFT JOIN duty_groups g ON g.id = ddg.group_id
    LEFT JOIN duty_group_members m ON m.group_id = g.id
    LEFT JOIN (
        SELECT duty_date, shift, COUNT(*) AS cnt
        FROM duty_schedule WHERE duty_date BETWEEN ? AND ?
        GROUP BY duty_date, shift
    ) pc ON pc.duty_date=ddg.duty_date AND pc.shift=ddg.shift
    WHERE ddg.duty_date BETWEEN ? AND ?
    GROUP BY ddg.id
");
$ddgRows->execute([$weekStart, $weekEnd, $weekStart, $weekEnd]);

$dayGroups = [];
foreach ($ddgRows->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $dayGroups[$r['duty_date']][$r['shift']] = $r;
}

// ── กลุ่มทั้งหมดสำหรับ picker ──
$groups = $pdo->query("
    SELECT g.*, COUNT(m.id) AS member_count
    FROM duty_groups g
    LEFT JOIN duty_group_members m ON m.group_id=g.id
    WHERE g.status='active'
    GROUP BY g.id ORDER BY g.sort_order, g.name
")->fetchAll(PDO::FETCH_ASSOC);

// ── ครูทั้งหมดสำหรับ modal จัดจุด ──
$teachers = $pdo->query("SELECT id, prefix, full_name FROM duty_teachers WHERE status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// ── max_duty_points setting ──
try {
    $maxPts = (int)($pdo->query("SELECT svalue FROM duty_settings WHERE skey='max_duty_points'")->fetchColumn() ?: 5);
} catch(Exception $e) { $maxPts = 5; }

// ── Thai month names ──
$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$dayNames = ['จ','อ','พ','พฤ','ศ','ส','อ'];

// ── แปลง BE year ──
function thDate($ymd, $fmt='j') {
    global $thMonths;
    $ts  = strtotime($ymd);
    $day = date('j',$ts);
    $mon = (int)date('n',$ts);
    $yr  = (int)date('Y',$ts) + 543;
    return $fmt === 'short' ? "$day {$thMonths[$mon]}" : "$day {$thMonths[$mon]} $yr";
}

$weekLabelStr = thDate($weekStart,'short') . ' – ' . thDate($weekEnd,'short') . ' ' . ((int)date('Y',strtotime($weekEnd))+543);

$pageTitle    = 'ตารางเวร';
$pageSubtitle = 'จัดตารางเวรรายสัปดาห์';
$activeSystem = 'duty';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<style>
.calendar-cell { min-width:130px; vertical-align:middle; padding:8px; }
.group-badge { border-radius:10px; padding:8px 10px; cursor:pointer; transition:opacity .15s; }
.group-badge:hover { opacity:.85; }
.assign-btn { min-height:80px; border-style:dashed !important; color:#adb5bd; transition:all .15s; }
.assign-btn:hover { border-color:#0d6efd !important; color:#0d6efd; background:#f0f5ff; }
.shift-header { white-space:nowrap; }
.today-col { background:rgba(13,110,253,.04); }
</style>

<!-- ── Header ── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="fas fa-calendar-week me-2 text-primary"></i>ตารางเวรประจำสัปดาห์</h4>
        <small class="text-muted">คลิก "จัดเวร" เพื่อเลือกกลุ่ม → คลิกกลุ่มเพื่อจัดจุดเวร</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="groups.php" class="btn btn-outline-success">
            <i class="fas fa-layer-group me-1"></i>จัดการกลุ่ม
        </a>
        <?php if (!empty($groups)): ?>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickFillModal">
            <i class="fas fa-magic me-1"></i>สร้างตารางด่วน
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ── Week Navigation ── -->
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="?week=<?= $prevWeek ?>" class="btn btn-outline-secondary">
        <i class="fas fa-chevron-left"></i>
    </a>
    <h5 class="mb-0 fw-bold"><?= htmlspecialchars($weekLabelStr) ?></h5>
    <a href="?week=<?= $nextWeek ?>" class="btn btn-outline-secondary">
        <i class="fas fa-chevron-right"></i>
    </a>
    <a href="?week=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary ms-auto">วันนี้</a>
</div>

<?php if (empty($groups)): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    ยังไม่มีกลุ่มเวร — <a href="groups.php" class="alert-link">สร้างกลุ่มก่อน</a> แล้วกลับมาจัดตาราง
</div>
<?php endif; ?>

<!-- ── Calendar ── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width:110px">กะ</th>
                        <?php for ($i = 0; $i < 7; $i++):
                            $day     = date('Y-m-d', strtotime($weekStart . " +$i days"));
                            $isToday = $day === date('Y-m-d');
                        ?>
                        <th class="text-center <?= $isToday?'table-primary':'' ?>" style="min-width:130px">
                            <div class="fw-bold"><?= $dayNames[$i] ?></div>
                            <div class="small <?= $isToday?'text-primary fw-bold':'text-muted' ?>">
                                <?= thDate($day,'short') ?>
                            </div>
                        </th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (['day'=>['☀️','กลางวัน','warning','dark'],'night'=>['🌙','กลางคืน','dark','white']] as $shift=>[$icon,$label,$badgeBg,$badgeFg]): ?>
                <tr>
                    <td class="text-center shift-header align-middle">
                        <span class="badge bg-<?=$badgeBg?> text-<?=$badgeFg?> p-2">
                            <?=$icon?> <?=$label?>
                        </span>
                    </td>
                    <?php for ($i = 0; $i < 7; $i++):
                        $day    = date('Y-m-d', strtotime($weekStart . " +$i days"));
                        $isToday= $day === date('Y-m-d');
                        $dg     = $dayGroups[$day][$shift] ?? null;
                    ?>
                    <td class="calendar-cell <?= $isToday?'today-col':'' ?>">
                        <?php if ($dg && $dg['group_id']): ?>
                            <!-- มีกลุ่มแล้ว -->
                            <div class="group-badge text-white text-center"
                                 style="background:<?= htmlspecialchars($dg['group_color']) ?>"
                                 onclick="openPointModal('<?=$day?>','<?=$shift?>','<?=htmlspecialchars($dg['group_name'],ENT_QUOTES)?>','<?=htmlspecialchars($dg['group_color'],ENT_QUOTES)?>')"
                                 title="คลิกเพื่อจัดจุดเวร">
                                <div class="fw-bold small"><?= htmlspecialchars($dg['group_name']) ?></div>
                                <div style="font-size:11px;opacity:.85"><?= $dg['member_count'] ?> คน</div>
                                <?php if ($dg['point_count'] > 0): ?>
                                    <span class="badge bg-white text-dark mt-1" style="font-size:10px">
                                        <i class="fas fa-check-circle text-success"></i> <?= $dg['point_count'] ?> จุด
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-white text-warning mt-1" style="font-size:10px">
                                        <i class="fas fa-exclamation-circle"></i> ยังไม่จัดจุด
                                    </span>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-outline-danger btn-sm w-100 mt-1"
                                    style="font-size:10px;padding:2px"
                                    onclick="removeGroup('<?=$day?>','<?=$shift?>')">
                                <i class="fas fa-times"></i> ยกเลิก
                            </button>
                        <?php else: ?>
                            <!-- ยังไม่มีกลุ่ม -->
                            <?php if (!empty($groups)): ?>
                            <button class="btn w-100 assign-btn"
                                    onclick="openGroupPicker('<?=$day?>','<?=$shift?>')">
                                <i class="fas fa-plus d-block mb-1"></i>
                                <small>จัดเวร</small>
                            </button>
                            <?php else: ?>
                            <div class="text-center text-muted small py-2">—</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php endfor; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══ Modal: เลือกกลุ่ม (Group Picker) ═══ -->
<div class="modal fade" id="groupPickerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>เลือกกลุ่มเวร</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="pickerDateLabel"></p>
                <div class="row g-2" id="groupPickerList">
                    <?php foreach ($groups as $g): ?>
                    <div class="col-6">
                        <button class="btn w-100 text-white p-3 rounded-3"
                                style="background:<?= htmlspecialchars($g['color']) ?>;border:none"
                                onclick="selectGroup(<?= $g['id'] ?>)">
                            <div class="fw-bold"><?= htmlspecialchars($g['name']) ?></div>
                            <small class="opacity-75"><?= $g['member_count'] ?> คน</small>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Modal: จัดจุดเวร ═══ -->
<div class="modal fade" id="pointModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-map-marker-alt me-2"></i>จัดจุดเวร: <span id="pointModalTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="pointModalBody">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="savePoints()">
                    <i class="fas fa-save me-1"></i>บันทึกจุดเวร
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Modal: สร้างตารางด่วน ═══ -->
<div class="modal fade" id="quickFillModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-magic me-2"></i>สร้างตารางด่วน — สัปดาห์นี้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">เลือกกลุ่มสำหรับแต่ละวัน+กะ แล้วกด "สร้างพร้อมกัน"</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>วัน</th>
                                <th>☀️ กลางวัน</th>
                                <th>🌙 กลางคืน</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php for ($i = 0; $i < 7; $i++):
                            $day    = date('Y-m-d', strtotime($weekStart . " +$i days"));
                            $dayTh  = ['จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์','อาทิตย์'][$i];
                        ?>
                        <tr>
                            <td class="align-middle fw-bold"><?= $dayTh ?><br><small class="text-muted fw-normal"><?= thDate($day,'short') ?></small></td>
                            <?php foreach (['day','night'] as $shift):
                                $existing = $dayGroups[$day][$shift]['group_id'] ?? null;
                            ?>
                            <td>
                                <select class="form-select form-select-sm quick-select"
                                        data-date="<?=$day?>" data-shift="<?=$shift?>">
                                    <option value="">— ไม่จัด —</option>
                                    <?php foreach ($groups as $g): ?>
                                    <option value="<?= $g['id'] ?>" <?= $existing==$g['id']?'selected':'' ?>
                                            data-color="<?= htmlspecialchars($g['color']) ?>">
                                        <?= htmlspecialchars($g['name']) ?> (<?= $g['member_count'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="runQuickFill()">
                    <i class="fas fa-magic me-1"></i>สร้างพร้อมกัน
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>';
const apiUrl    = '../../duty/api/schedule_api.php';
const maxPoints = <?= $maxPts ?>;
const weekStart = '<?= $weekStart ?>';

let pickerDate  = '';
let pickerShift = '';
let pointDate   = '';
let pointShift  = '';

// ── Group Picker ─────────────────────────────────────────────────────────────
function openGroupPicker(date, shift) {
    pickerDate  = date;
    pickerShift = shift;
    const shiftTh = shift === 'day' ? '☀️ กลางวัน' : '🌙 กลางคืน';
    const d = new Date(date);
    const dayNames = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    document.getElementById('pickerDateLabel').textContent =
        'วัน' + dayNames[d.getDay()] + ' ' + date + ' — กะ' + shiftTh;
    new bootstrap.Modal(document.getElementById('groupPickerModal')).show();
}

function selectGroup(groupId) {
    const fd = new FormData();
    fd.append('action','assign_group');
    fd.append('csrf_token', csrfToken);
    fd.append('duty_date', pickerDate);
    fd.append('shift', pickerShift);
    fd.append('group_id', groupId);

    bootstrap.Modal.getInstance(document.getElementById('groupPickerModal'))?.hide();

    fetch(apiUrl, {method:'POST', body:fd})
        .then(r=>r.json())
        .then(d=>{
            if (d.status==='success') location.reload();
            else Swal.fire({icon:'error',title:'ผิดพลาด',text:d.message});
        });
}

// ── Remove Group ─────────────────────────────────────────────────────────────
function removeGroup(date, shift) {
    Swal.fire({
        icon:'warning', title:'ยืนยันยกเลิกเวร?',
        text:'ข้อมูลการจัดจุดในวันนี้จะถูกลบด้วย',
        showCancelButton:true, confirmButtonColor:'#dc3545',
        confirmButtonText:'ยืนยัน', cancelButtonText:'ยกเลิก'
    }).then(r=>{
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action','remove_group');
        fd.append('csrf_token', csrfToken);
        fd.append('duty_date', date);
        fd.append('shift', shift);
        fetch(apiUrl, {method:'POST', body:fd})
            .then(r=>r.json())
            .then(d=>{ if(d.status==='success') location.reload(); });
    });
}

// ── Point Modal ──────────────────────────────────────────────────────────────
function openPointModal(date, shift, groupName, groupColor) {
    pointDate  = date;
    pointShift = shift;
    document.getElementById('pointModalTitle').textContent = groupName;
    document.getElementById('pointModalBody').innerHTML =
        '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
    new bootstrap.Modal(document.getElementById('pointModal')).show();

    fetch(`${apiUrl}?action=get_day_detail&duty_date=${date}&shift=${shift}`)
        .then(r=>r.json())
        .then(d=>{
            if (d.status!=='success') { document.getElementById('pointModalBody').innerHTML='<p class="text-danger">โหลดข้อมูลไม่สำเร็จ</p>'; return; }
            renderPointForm(d.members, d.max_points, groupColor);
        });
}

function renderPointForm(members, maxPts, groupColor) {
    const ptOptions = Array.from({length:maxPts}, (_,i)=>`<option value="${i+1}">จุดที่ ${i+1}</option>`).join('');

    let rows = members.map(m => {
        const name = (m.prefix||'') + m.full_name;
        const selected = p => m.point_no == p ? 'selected' : '';
        const opts = `<option value="">— ไม่จัด —</option>` +
            Array.from({length:maxPts}, (_,i)=>`<option value="${i+1}" ${selected(i+1)}>จุดที่ ${i+1}</option>`).join('');
        return `
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle flex-shrink-0" style="width:28px;height:28px;background:${groupColor}"></div>
                    <span>${name}</span>
                </div>
            </td>
            <td>
                <select class="form-select form-select-sm" data-tid="${m.id}" data-field="point_no">
                    ${opts}
                </select>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" placeholder="หน้าที่ (ไม่บังคับ)"
                       data-tid="${m.id}" data-field="role" value="${m.role||''}">
            </td>
        </tr>`;
    }).join('');

    if (!rows) {
        rows = '<tr><td colspan="3" class="text-center text-muted py-3">ไม่มีสมาชิกในกลุ่ม</td></tr>';
    }

    document.getElementById('pointModalBody').innerHTML = `
        <div class="alert alert-info py-2 small mb-3">
            <i class="fas fa-info-circle me-1"></i>
            กำหนดจุดเวรให้สมาชิกแต่ละคน ไม่จำเป็นต้องครบทุกคน
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="pointTable">
                <thead class="table-light">
                    <tr><th>ชื่อครู</th><th style="width:140px">จุดที่</th><th>หน้าที่/บทบาท</th></tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
}

function savePoints() {
    const table = document.getElementById('pointTable');
    if (!table) return;

    const assignments = [];
    table.querySelectorAll('[data-field="point_no"]').forEach(sel => {
        const tid   = sel.dataset.tid;
        const ptNo  = sel.value;
        const role  = table.querySelector(`[data-tid="${tid}"][data-field="role"]`)?.value || '';
        if (ptNo) assignments.push({teacher_id:tid, point_no:ptNo, role});
    });

    const fd = new FormData();
    fd.append('action','save_points');
    fd.append('csrf_token', csrfToken);
    fd.append('duty_date', pointDate);
    fd.append('shift', pointShift);
    fd.append('assignments', JSON.stringify(assignments));

    fetch(apiUrl, {method:'POST', body:fd})
        .then(r=>r.json())
        .then(d=>{
            if (d.status==='success') {
                bootstrap.Modal.getInstance(document.getElementById('pointModal'))?.hide();
                Swal.fire({icon:'success',title:'บันทึกแล้ว',timer:1500,showConfirmButton:false})
                    .then(()=>location.reload());
            } else {
                Swal.fire({icon:'error',title:'ผิดพลาด',text:d.message});
            }
        });
}

// ── Quick Fill ───────────────────────────────────────────────────────────────
function runQuickFill() {
    const selects = document.querySelectorAll('.quick-select');
    const fd = new FormData();
    fd.append('action','assign_group'); // ส่งทีละรายการ
    fd.append('csrf_token', csrfToken);

    const promises = [];
    selects.forEach(sel => {
        if (!sel.value) return; // skip "ไม่จัด"
        const f = new FormData();
        f.append('action','assign_group');
        f.append('csrf_token', csrfToken);
        f.append('duty_date', sel.dataset.date);
        f.append('shift', sel.dataset.shift);
        f.append('group_id', sel.value);
        promises.push(fetch(apiUrl, {method:'POST', body:f}).then(r=>r.json()));
    });

    Promise.all(promises).then(results => {
        const ok = results.every(r=>r.status==='success');
        bootstrap.Modal.getInstance(document.getElementById('quickFillModal'))?.hide();
        Swal.fire({icon: ok?'success':'warning', title: ok?'สร้างตารางสำเร็จ':'เสร็จบางส่วน',
            timer:1800, showConfirmButton:false}).then(()=>location.reload());
    });
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
