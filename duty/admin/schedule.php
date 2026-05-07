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

// ── Auto-migrate: ตรวจ duty_day_groups โดยตรง ──
try {
    $pdo->query("SELECT 1 FROM duty_day_groups LIMIT 1");
} catch (Exception $e) {
    // duty_day_groups ยังไม่มี → สร้างทุกตาราง (IF NOT EXISTS ปลอดภัย)
    $pdo->exec("CREATE TABLE IF NOT EXISTS duty_groups (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        color       VARCHAR(20)  DEFAULT '#6c757d',
        description TEXT,
        sort_order  INT          DEFAULT 0,
        status      ENUM('active','inactive') DEFAULT 'active',
        created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS duty_group_members (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        group_id   INT NOT NULL,
        teacher_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_gm (group_id, teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS duty_day_groups (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        duty_date  DATE NOT NULL,
        shift      ENUM('day','night') DEFAULT 'day',
        group_id   INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_ddg (duty_date, shift)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}


// ── Auto-migrate: ตั้งค่า max_duty_points เป็น 6 ──
try {
    $pdo->prepare("INSERT INTO duty_settings (skey, svalue) VALUES ('max_duty_points', '6') ON DUPLICATE KEY UPDATE svalue='6'")->execute();
} catch (Exception $e) { /* ignore */ }

// ── Auto-migrate: เพิ่ม group_id ใน duty_schedule ถ้ายังไม่มี ──
try {
    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='duty_schedule' AND COLUMN_NAME='group_id'");
    if ((int)$chk->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE duty_schedule ADD COLUMN group_id INT NULL");
    }
} catch (Exception $e) { /* ignore */ }

// ── Shift param ──
$shiftParam = in_array($_GET['shift'] ?? '', ['day','night']) ? $_GET['shift'] : 'day';

// ── คำนวณสัปดาห์ (จันทร์-อาทิตย์) ──
$weekParam = $_GET['week'] ?? date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($weekParam)));
$weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days')); // Sunday
$prevWeek  = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek  = date('Y-m-d', strtotime($weekStart . ' +7 days'));

// ── จำนวนจุดเวร (default 5) ──
// ── ชื่อจุดเวร (hardcode 6 จุด) ──
$pointNames = [
    'รับนักเรียนจุดที่ 1',
    'รับนักเรียนจุดที่ 2',
    'หน้าเสาธง',
    'ปล่อยกลับบ้าน',
    'ตรวจรอบโรงเรียน',
    'ประธานกิจกรรมหน้าเสาธง'
];
$maxPts = count($pointNames);

// ── ดึงตารางเวรทั้งสัปดาห์ (ตาม shift) ──
$stmtSched = $pdo->prepare("
    SELECT ds.duty_date, ds.point_no, ds.role,
           dt.id AS teacher_id, dt.prefix, dt.full_name
    FROM duty_schedule ds
    JOIN duty_teachers dt ON dt.id = ds.teacher_id
    WHERE ds.duty_date BETWEEN ? AND ? AND ds.shift = ?
    ORDER BY ds.duty_date, ds.point_no, ds.id
");
$stmtSched->execute([$weekStart, $weekEnd, $shiftParam]);

// $slots[date][point_no][] = teacher
$slots = [];
foreach ($stmtSched->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $slots[$r['duty_date']][$r['point_no']][] = $r;
}

// ── ครูทั้งหมดสำหรับ dropdown ──
$teachers = $pdo->query(
    "SELECT id, prefix, full_name FROM duty_teachers WHERE status='active' ORDER BY full_name"
)->fetchAll(PDO::FETCH_ASSOC);

// ── ดึง duty_groups ทั้งหมด (สำหรับ picker) ──
$allGroups = [];
try {
    // กรองกลุ่มตามประเภท (Day/Night) หรือแสดงกลุ่ม Chairman ด้วย
    $stmtAllG = $pdo->prepare("
        SELECT id, name, color, group_type 
        FROM duty_groups 
        WHERE status='active' AND (group_type = ? OR group_type = 'chairman')
        ORDER BY sort_order, name
    ");
    $stmtAllG->execute([$shiftParam]);
    $allGroups = $stmtAllG->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { $allGroups = []; }

// ── ดึง day_groups สัปดาห์นี้ (ตาม shift) ──
$dayGroups = [];
try {
    $dgRows = $pdo->prepare("
        SELECT ddg.duty_date, ddg.group_id, g.name AS group_name, g.color AS group_color
        FROM duty_day_groups ddg
        LEFT JOIN duty_groups g ON g.id = ddg.group_id
        WHERE ddg.duty_date BETWEEN ? AND ? AND ddg.shift = ?
    ");
    $dgRows->execute([$weekStart, $weekEnd, $shiftParam]);
    foreach ($dgRows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $dayGroups[$r['duty_date']] = $r;
    }
} catch(Exception $e) { $dayGroups = []; }

// ── Thai helpers ──
$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
function thDateShort($ymd) {
    global $thMonths;
    $ts  = strtotime($ymd);
    return date('j', $ts) . ' ' . $thMonths[(int)date('n', $ts)];
}
$thYear    = (int)date('Y', strtotime($weekEnd)) + 543;
$weekLabel = thDateShort($weekStart) . ' – ' . thDateShort($weekEnd) . ' ' . $thYear;
$dayNames  = ['จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์','อาทิตย์'];
$numDays   = 7;

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
.group-cell { padding:6px 8px; text-align:center; border-bottom:2px solid #dee2e6; background:#f8f9fa; }
.group-badge { display:inline-flex; align-items:center; gap:5px; border-radius:20px; padding:3px 10px; font-size:11px; font-weight:700; cursor:pointer; border:none; transition:opacity .15s; }
.group-badge:hover { opacity:.8; }
.group-empty { display:inline-flex; align-items:center; gap:4px; color:#adb5bd; font-size:11px; cursor:pointer; padding:3px 8px; border:1.5px dashed #dee2e6; border-radius:20px; transition:all .15s; background:transparent; }
.group-empty:hover { color:#2563eb; border-color:#2563eb; background:#f0f5ff; }
</style>

<!-- ── Header ── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="fas fa-calendar-week me-2 text-primary"></i>ตารางเวรประจำสัปดาห์</h4>
        <small class="text-muted">คลิกที่วันใดวันหนึ่งในปฏิทินเพื่อจัดครูทุกจุดในวันนั้น (กรองเฉพาะกลุ่มที่ assign)</small>
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
    <a href="?week=<?= $prevWeek ?>&shift=<?= $shiftParam ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-chevron-left"></i>
    </a>
    <h5 class="mb-0 fw-bold text-primary"><?= htmlspecialchars($weekLabel) ?></h5>
    <a href="?week=<?= $nextWeek ?>&shift=<?= $shiftParam ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-chevron-right"></i>
    </a>
    <a href="?week=<?= date('Y-m-d') ?>&shift=<?= $shiftParam ?>" class="btn btn-sm btn-outline-primary ms-auto">วันนี้</a>
</div>

<!-- ── Shift Tabs ── -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $shiftParam==='day' ? 'active fw-bold' : '' ?>" href="?week=<?= $weekStart ?>&shift=day">
            ☀️ เวรกลางวัน
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $shiftParam==='night' ? 'active fw-bold' : '' ?>" href="?week=<?= $weekStart ?>&shift=night">
            🌙 เวรกลางคืน
        </a>
    </li>
</ul>

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
                        <th class="text-center align-middle" style="width:120px; font-size:13px;">จุดเวร</th>
                        <?php for ($i = 0; $i < $numDays; $i++):
                            $day     = date('Y-m-d', strtotime($weekStart . " +$i days"));
                            $isToday = ($day === date('Y-m-d'));
                            $isWkend = ($i >= 5);
                        ?>
                        <th class="text-center <?= $isToday ? 'table-primary' : ($isWkend ? 'table-warning' : '') ?>" style="min-width:120px;">
                            <div class="fw-bold"><?= $dayNames[$i] ?></div>
                            <div class="small <?= $isToday ? 'text-primary fw-bold' : 'text-muted' ?>">
                                <?= thDateShort($day) ?>
                            </div>
                        </th>
                        <?php endfor; ?>
                    </tr>
                    <!-- ── Row: กลุ่มเวร ── -->
                    <tr>
                        <td class="text-center align-middle group-cell" style="font-size:11px;font-weight:700;color:#6c757d;">
                            <i class="fas fa-layer-group me-1"></i>กลุ่ม
                        </td>
                        <?php for ($i = 0; $i < $numDays; $i++):
                            $day = date('Y-m-d', strtotime($weekStart . " +$i days"));
                            $dg  = $dayGroups[$day] ?? null;
                        ?>
                        <td class="group-cell">
                            <?php if ($dg && $dg['group_id']): ?>
                            <button class="group-badge text-white"
                                    style="background:<?= htmlspecialchars($dg['group_color'] ?? '#6c757d') ?>"
                                    onclick="openGroupPicker('<?= $day ?>')"
                                    title="คลิกเพื่อเปลี่ยนกลุ่ม">
                                <i class="fas fa-users" style="font-size:10px"></i>
                                <?= htmlspecialchars(mb_substr($dg['group_name'] ?? '', 0, 10)) ?>
                            </button>
                            <?php else: ?>
                            <button class="group-empty" onclick="openGroupPicker('<?= $day ?>')">
                                <i class="fas fa-plus" style="font-size:10px"></i>เลือกกลุ่ม
                            </button>
                            <?php endif; ?>
                        </td>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                <?php for ($pt = 1; $pt <= $maxPts; $pt++):
                    $ptName = $pointNames[$pt-1] ?? 'จุดที่ '.$pt;
                ?>
                <tr>
                    <td class="text-center align-middle point-label" style="font-size:11px;">
                        <span class="badge bg-primary" style="white-space:normal;line-height:1.3"><?= htmlspecialchars($ptName) ?></span>
                    </td>
                    <?php for ($i = 0; $i < $numDays; $i++):
                        $day    = date('Y-m-d', strtotime($weekStart . " +$i days"));
                        $isToday = ($day === date('Y-m-d'));
                        $assigned = $slots[$day][$pt] ?? [];
                    ?>
                    <td class="duty-cell <?= $isToday ? 'today-col' : '' ?>"
                        onclick="openDayModal('<?= $day ?>')">
                        <div class="duty-cell-inner">
                            <?php if (!empty($assigned)): ?>
                                <?php foreach ($assigned as $idx => $t): ?>
                                <span class="teacher-chip <?= $idx > 0 ? 'chip-2' : '' ?>">
                                    <i class="fas fa-user" style="font-size:10px"></i>
                                    <?= htmlspecialchars(mb_substr($t['prefix'].$t['full_name'], 0, 14)) ?>
                                </span>
                                <?php endforeach; ?>
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

<!-- ═══ Modal: เลือกกลุ่มเวร ═══ -->
<div class="modal fade" id="groupPickerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-layer-group me-2 text-primary"></i>
                    เลือกกลุ่มเวร — <span id="gpDate"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="gpBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" onclick="clearGroup()">
                    <i class="fas fa-times me-1"></i>ล้างกลุ่มวันนี้
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Modal: จัดจุดเวรทั้งวัน ═══ -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-calendar-day me-2 text-primary"></i>
                    จัดเวร — <span id="assignTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="assignBody">
                <div class="text-center py-3"><i class="fas fa-spinner fa-spin text-muted fa-2x"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" onclick="clearDay()">
                    <i class="fas fa-trash me-1"></i>ล้างทั้งวัน
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="saveDay()">
                    <i class="fas fa-save me-1"></i>บันทึก
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken  = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>';
const apiUrl     = '../../duty/api/schedule_api.php';
const maxPtsJS   = <?= (int)$maxPts ?>;
const curShiftPage = '<?= $shiftParam ?>';
const pointNamesJS = <?= json_encode($pointNames, JSON_UNESCAPED_UNICODE) ?>;
const allTeachersData = <?= json_encode($teachers, JSON_UNESCAPED_UNICODE) ?>;
const CHAIRMAN_INDEX = <?= count($pointNames) ?>; // จุดสุดท้าย = ประธานกิจกรรม

const thDaysFull = ['','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์','อาทิตย์'];
const thMonAbbr  = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

let curDate    = '';
let curShift   = curShiftPage;
let modalMembers = [];
let gpCurDate  = '';  // วันที่ที่กำลัง pick กลุ่ม

// allGroups จาก PHP
const allGroupsData = <?= json_encode($allGroups, JSON_UNESCAPED_UNICODE) ?>;

// ─── เปิด Group Picker Modal ─────────────────────────────────
function openGroupPicker(date) {
    gpCurDate = date;
    const d  = new Date(date + 'T00:00:00');
    const mo = thMonAbbr[d.getMonth()+1];
    const shLabel = curShiftPage === 'night' ? '🌙 กลางคืน' : '☀️ กลางวัน';
    document.getElementById('gpDate').textContent =
        thDaysFull[d.getDay()] + ' ' + d.getDate() + ' ' + mo + ' — ' + shLabel;

    const body = document.getElementById('gpBody');
    if (!allGroupsData.length) {
        body.innerHTML = `<div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ยังไม่มีกลุ่มเวร — <a href="groups.php" class="alert-link">ไปสร้างกลุ่มก่อน</a>
        </div>`;
    } else {
        body.innerHTML = '<div class="d-grid gap-2">' +
            allGroupsData.map(g => `
                <button class="btn text-white text-start fw-bold d-flex align-items-center gap-2"
                        style="background:${g.color}"
                        onclick="assignGroup('${date}','${g.id}')">
                    <i class="fas fa-users"></i> ${g.name}
                </button>`).join('') +
            '</div>';
    }
    new bootstrap.Modal(document.getElementById('groupPickerModal')).show();
}

// ─── Assign กลุ่มลงวัน ──────────────────────────────────────
function assignGroup(date, groupId) {
    const fd = new FormData();
    fd.append('action',     'assign_group');
    fd.append('csrf_token', csrfToken);
    fd.append('duty_date',  date);
    fd.append('shift',      curShiftPage);
    fd.append('group_id',   groupId);
    fetch(apiUrl, {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            bootstrap.Modal.getInstance(document.getElementById('groupPickerModal'))?.hide();
            if (d.status === 'success') location.reload();
            else Swal.fire({icon:'error', title:'ผิดพลาด', text: d.message});
        });
}

// ─── ล้างกลุ่มของวัน ─────────────────────────────────────────
function clearGroup() {
    Swal.fire({
        icon:'warning', title:'ล้างกลุ่มวันนี้?',
        text:'กลุ่มที่ assign ไว้จะถูกล้าง (จุดเวรยังคงอยู่)',
        showCancelButton:true, confirmButtonColor:'#dc3545',
        confirmButtonText:'ล้างเลย', cancelButtonText:'ยกเลิก'
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action',     'assign_group');
        fd.append('csrf_token', csrfToken);
        fd.append('duty_date',  gpCurDate);
        fd.append('shift',      curShiftPage);
        fd.append('group_id',   '');
        fetch(apiUrl, {method:'POST', body:fd})
            .then(r => r.json())
            .then(d => {
                bootstrap.Modal.getInstance(document.getElementById('groupPickerModal'))?.hide();
                location.reload();
            });
    });
}

// ─── เปิด Modal ทั้งวัน ──────────────────────────────────────
function openDayModal(date, shift) {
    curDate  = date;
    curShift = shift || curShiftPage;

    const d   = new Date(date + 'T00:00:00');
    const mo  = thMonAbbr[d.getMonth()+1];
    const shiftTh = (curShift === 'night') ? '🌙 กลางคืน' : '☀️ กลางวัน';
    document.getElementById('assignTitle').textContent =
        thDaysFull[d.getDay()] + ' ' + d.getDate() + ' ' + mo + ' — ' + shiftTh;

    const body = document.getElementById('assignBody');
    body.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><div class="mt-2 small text-muted">กำลังโหลด...</div></div>';
    new bootstrap.Modal(document.getElementById('assignModal')).show();

    fetch(`${apiUrl}?action=get_day_detail&duty_date=${date}&shift=${curShift}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                body.innerHTML = '<p class="text-danger">โหลดข้อมูลไม่สำเร็จ</p>'; return;
            }
            const dayGroup = data.day_group;
            modalMembers   = data.members || [];
            const maxPts   = data.max_points || maxPtsJS;
            renderDayForm(dayGroup, modalMembers, maxPts);
        })
        .catch(() => { body.innerHTML = '<p class="text-danger">เชื่อมต่อ server ไม่ได้</p>'; });
}

// ─── Render ฟอร์ม (จุดเป็น row, ครูกลุ่มเป็น dropdown) ───────
function renderDayForm(dayGroup, members, maxPts) {
    const body = document.getElementById('assignBody');

    // ถ้ายังไม่ได้ assign กลุ่ม
    if (!dayGroup || !dayGroup.group_id) {
        body.innerHTML = `
            <div class="alert alert-warning mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                วันนี้ยังไม่ได้กำหนดกลุ่มเวร — ไปที่
                <a href="schedule.php" class="alert-link">ปฏิทินสัปดาห์</a>
                แล้วกำหนดกลุ่มก่อน
            </div>`;
        return;
    }

    // build point→member map (รองรับ 2 คนต่อจุด)
    const ptMap = {}; // ptMap[point_no] = [member1, member2]
    members.forEach(m => {
        if (m.point_no) {
            if (!ptMap[m.point_no]) ptMap[m.point_no] = [];
            ptMap[m.point_no].push(m);
        }
    });

    // build teacher options (เฉพาะในกลุ่ม)
    const blankOpt = '<option value="">— ไม่จัด —</option>';
    function buildOpts(list, selectedId) {
        return blankOpt + list.map(m =>
            `<option value="${m.id}" ${m.id == selectedId ? 'selected' : ''}>${m.prefix||''}${m.full_name}</option>`
        ).join('');
    }

    // point rows (2 dropdowns per point)
    const gc = dayGroup.group_color || '#6c757d';
    let rows = '';
    for (let i = 1; i <= maxPts; i++) {
        const assigned = ptMap[i] || [];
        const t1 = assigned[0] || null;
        const t2 = assigned[1] || null;
        const ptName = (pointNamesJS[i-1]) || ('จุดที่ '+i);
        const isChairman = (i === CHAIRMAN_INDEX);
        // ประธานกิจกรรม → แสดงครูทุกคน, จุดอื่น → เฉพาะกลุ่ม
        const optList = isChairman ? allTeachersData : members;
        rows += `
        <tr class="point-row" data-point="${i}">
            <td class="align-middle" style="width:160px">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center text-white fw-bold"
                         style="width:28px;height:28px;background:${gc};font-size:11px">${i}</div>
                    <span class="fw-bold small text-secondary">${ptName}</span>
                </div>
                ${isChairman ? '<span class="badge bg-warning text-dark" style="font-size:9px">ครูทั้งหมด</span>' : ''}
            </td>
            <td class="align-middle">
                <select class="form-select form-select-sm mb-1" data-field="teacher_id" onchange="refreshUnassigned()">
                    ${buildOpts(optList, t1 ? t1.id : '')}
                </select>
                <select class="form-select form-select-sm" data-field="teacher_id" onchange="refreshUnassigned()">
                    ${buildOpts(optList, t2 ? t2.id : '')}
                </select>
            </td>
            <td class="align-middle" style="width:80px">
                <input type="hidden" data-field="role" value="${esc(ptName)}">
            </td>
        </tr>`;
    }

    const gn  = dayGroup.group_name  || 'กลุ่มเวร';
    const cnt = members.length;
    body.innerHTML = `
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge rounded-pill px-3 py-2" style="background:${gc};font-size:13px">
                ${gn}
            </span>
            <span class="text-muted small">${cnt} คนในกลุ่ม — เลือกครูลงแต่ละจุด (2 คน/จุด)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-2">
                <thead class="table-light">
                    <tr>
                        <th>จุดเวร</th>
                        <th>ครูคนที่ 1 / คนที่ 2</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="pointTbody">${rows}</tbody>
            </table>
        </div>
        <div class="border rounded p-2 bg-light small">
            <span class="fw-bold text-muted me-2">ครูที่ยังไม่ได้จัด:</span>
            <span id="unassignedList"></span>
        </div>`;

    refreshUnassigned();
}

// ─── อัปเดต badge ครูที่ยังไม่ได้จัด ────────────────────────
function refreshUnassigned() {
    const el = document.getElementById('unassignedList');
    if (!el) return;
    const selected = new Set(
        [...document.querySelectorAll('.point-row [data-field="teacher_id"]')]
            .map(s => parseInt(s.value)).filter(Boolean)
    );
    const unassigned = modalMembers.filter(m => !selected.has(parseInt(m.id)));
    el.innerHTML = unassigned.length
        ? unassigned.map(m => `<span class="badge rounded-pill bg-white border text-dark me-1 fw-normal">${m.prefix||''}${m.full_name}</span>`).join('')
        : '<span class="text-success fw-bold">ทุกคนถูกจัดแล้ว ✅</span>';
}

// ─── บันทึกทั้งวัน (เก็บ 2 คนต่อจุด) ────────────────────────
function saveDay() {
    const assignments = [];
    document.querySelectorAll('.point-row').forEach(row => {
        const ptNo = parseInt(row.dataset.point);
        const role = row.querySelector('[data-field="role"]').value.trim();
        // เก็บทุก dropdown ที่มีค่า
        row.querySelectorAll('[data-field="teacher_id"]').forEach(sel => {
            if (sel.value) assignments.push({teacher_id: sel.value, point_no: ptNo, role: role});
        });
    });

    const fd = new FormData();
    fd.append('action', 'save_points');
    fd.append('csrf_token', csrfToken);
    fd.append('duty_date', curDate);
    fd.append('shift', curShift);
    fd.append('assignments', JSON.stringify(assignments));

    fetch(apiUrl, {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('assignModal'))?.hide();
                location.reload();
            } else {
                Swal.fire({icon:'error', title:'ผิดพลาด', text: d.message || 'บันทึกไม่สำเร็จ', confirmButtonColor:'#2563eb'});
            }
        })
        .catch(() => Swal.fire({icon:'error', title:'เชื่อมต่อไม่ได้', confirmButtonColor:'#2563eb'}));
}

// ─── ล้างทั้งวัน ──────────────────────────────────────────────
function clearDay() {
    Swal.fire({
        icon:'warning', title:'ล้างเวรทั้งวัน?',
        text:'จุดเวรทั้งหมดของวันนี้จะถูกล้าง',
        showCancelButton:true, confirmButtonColor:'#dc3545',
        confirmButtonText:'ล้างเลย', cancelButtonText:'ยกเลิก'
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'save_points');
        fd.append('csrf_token', csrfToken);
        fd.append('duty_date', curDate);
        fd.append('shift', curShift);
        fd.append('assignments', '[]');
        fetch(apiUrl, {method:'POST', body:fd})
            .then(r => r.json())
            .then(d => { if (d.status === 'success') { bootstrap.Modal.getInstance(document.getElementById('assignModal'))?.hide(); location.reload(); }});
    });
}

// ─── ล้างทั้งสัปดาห์ ─────────────────────────────────────────
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
        fd.append('shift', curShiftPage);
        fetch(apiUrl, {method:'POST', body:fd})
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') location.reload();
                else Swal.fire({icon:'error', title:'ผิดพลาด', text: d.message});
            });
    });
}

// ─── util ─────────────────────────────────────────────────────
function esc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;') : ''; }

</script>


<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
