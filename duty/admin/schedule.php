<?php
/**
 * duty/admin/schedule.php — ตารางเวรรายสัปดาห์ (5 จุด × วันจันทร์-ศุกร์)
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin','wfh_admin'])) {
    header('Location: /login.php'); exit();
}

if (isset($_GET['trigger_notification']) && $_SESSION['llw_role'] === 'super_admin') {
    define('DUTY_CRON_INTERNAL', true);
    require_once __DIR__ . '/../cron/notify_duty.php';
    echo "<script>alert('ส่งแจ้งเตือนเข้า Telegram ทันทีเรียบร้อยแล้ว!'); window.location.href='schedule.php';</script>";
    exit();
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

    // ── Sync point names and swap order 2 <-> 3 ──
    $checkSync = $pdo->query("SELECT COUNT(*) FROM duty_schedule WHERE role LIKE '1. %' LIMIT 1")->fetchColumn();
    if (!$checkSync) {
        // Swap point_no 2 and 3 for day shift to keep teachers with their original tasks
        $pdo->exec("UPDATE duty_schedule SET point_no = 99 WHERE point_no = 2 AND shift = 'day'");
        $pdo->exec("UPDATE duty_schedule SET point_no = 2 WHERE point_no = 3 AND shift = 'day'");
        $pdo->exec("UPDATE duty_schedule SET point_no = 3 WHERE point_no = 99 AND shift = 'day'");
        
        $syncNames = [
            1 => '1. รับนักเรียนจุดที่ 1',
            2 => '2. หน้าเสาธง',
            3 => '3. รับนักเรียนจุดที่ 2',
            4 => '4. ปล่อยกลับบ้าน',
            5 => '5. ตรวจรอบโรงเรียน',
            6 => '6. ประธานกิจกรรมหน้าเสาธง'
        ];
        foreach ($syncNames as $no => $name) {
            $pdo->prepare("UPDATE duty_schedule SET role = ? WHERE point_no = ? AND shift = 'day'")->execute([$name, $no]);
        }
    }
} catch (Exception $e) { /* ignore */ }

// ── View Mode & Params ──
$viewParam  = $_GET['view'] ?? ''; // week | month
$shiftParam = in_array($_GET['shift'] ?? '', ['day','night']) ? $_GET['shift'] : 'day';

// Default view mode based on shift if not specified
if (!$viewParam) {
    $viewParam = ($shiftParam === 'night') ? 'month' : 'week';
}

// ── คำนวณช่วงวันที่ ──
$dateParam = $_GET['date'] ?? date('Y-m-d'); // ใช้วันที่อ้างอิง

if ($viewParam === 'month') {
    $monthParam = date('Y-m', strtotime($dateParam));
    $firstDay   = "$monthParam-01";
    $lastDay    = date('Y-m-t', strtotime($firstDay));
    $weekStart  = date('Y-m-d', strtotime('monday this week', strtotime($firstDay)));
    $weekEnd    = date('Y-m-d', strtotime('sunday this week', strtotime($lastDay)));
    
    $prevDate   = date('Y-m-d', strtotime($firstDay . ' -1 month'));
    $nextDate   = date('Y-m-d', strtotime($firstDay . ' +1 month'));
    $pageLabel  = 'เดือน' . thMonthFull(date('n', strtotime($firstDay))) . ' ' . (date('Y', strtotime($firstDay)) + 543);
} else {
    $weekStart  = date('Y-m-d', strtotime('monday this week', strtotime($dateParam)));
    $weekEnd    = date('Y-m-d', strtotime($weekStart . ' +6 days'));
    
    $prevDate   = date('Y-m-d', strtotime($weekStart . ' -7 days'));
    $nextDate   = date('Y-m-d', strtotime($weekStart . ' +7 days'));
    $pageLabel  = thDateShort($weekStart) . ' – ' . thDateShort($weekEnd) . ' ' . (date('Y', strtotime($weekEnd)) + 543);
}

// ── จำนวนจุดเวร (default 5) ──
// ── ชื่อจุดเวร (แยกตามกะ) ──
if ($shiftParam === 'night') {
    $pointNames = ['เวรตรวจความเรียบร้อยกลางคืน'];
} else {
    $pointNames = [
        '1. รับนักเรียนจุดที่ 1',
        '2. หน้าเสาธง',
        '3. รับนักเรียนจุดที่ 2',
        '4. ปล่อยกลับบ้าน',
        '5. ตรวจรอบโรงเรียน',
        '6. ประธานกิจกรรมหน้าเสาธง'
    ];
}
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
$thMonthsFull = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];

function thDateShort($ymd) {
    global $thMonths;
    $ts  = strtotime($ymd);
    return date('j', $ts) . ' ' . $thMonths[(int)date('n', $ts)];
}
function thMonthFull($n) {
    global $thMonthsFull;
    return $thMonthsFull[(int)$n];
}
$dayNames  = ['จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์','อาทิตย์'];

$pageTitle    = 'ตารางเวร';
$pageSubtitle = 'จัดตารางเวรรายสัปดาห์';
$activeSystem = 'duty';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<!-- ── Header ── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="fas fa-calendar-alt me-2 text-primary"></i>ตารางจัดเวร</h4>
        <small class="text-muted">คลิกที่ช่องวันที่เพื่อจัดการครูเวรในวันนั้นๆ</small>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <div class="btn-group btn-group-sm me-2 shadow-sm">
            <a href="?view=week&shift=<?= $shiftParam ?>&date=<?= $dateParam ?>" 
               class="btn <?= $viewParam==='week' ? 'btn-primary' : 'btn-outline-primary' ?>">สัปดาห์</a>
            <a href="?view=month&shift=<?= $shiftParam ?>&date=<?= $dateParam ?>" 
               class="btn <?= $viewParam==='month' ? 'btn-primary' : 'btn-outline-primary' ?>">เดือน</a>
        </div>
        <?php if ($_SESSION['llw_role'] === 'super_admin'): ?>
        <a href="?trigger_notification=1" onclick="return confirm('ยืนยันส่งแจ้งเตือนตารางเวรของวันนี้เข้า Telegram ทันที?');" class="btn btn-warning btn-sm rounded-pill px-3 me-2 shadow-sm fw-bold">
            <i class="fas fa-bell me-1"></i>ทดสอบแจ้งเตือน
        </a>
        <?php endif; ?>
        <a href="teachers.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-users me-1"></i>จัดการครูเวร
        </a>
    </div>
</div>

<!-- ── Navigation ── -->
<div class="d-flex align-items-center gap-3 mb-3 bg-white p-2 rounded-4 shadow-sm border border-slate-100">
    <a href="?view=<?= $viewParam ?>&shift=<?= $shiftParam ?>&date=<?= $prevDate ?>" class="btn btn-light btn-sm rounded-circle shadow-sm" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center">
        <i class="fas fa-chevron-left"></i>
    </a>
    <h5 class="mb-0 fw-bold text-primary flex-grow-1 text-center"><?= htmlspecialchars($pageLabel) ?></h5>
    <a href="?view=<?= $viewParam ?>&shift=<?= $shiftParam ?>&date=<?= $nextDate ?>" class="btn btn-light btn-sm rounded-circle shadow-sm" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center">
        <i class="fas fa-chevron-right"></i>
    </a>
    <a href="?view=<?= $viewParam ?>&shift=<?= $shiftParam ?>&date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">วันนี้</a>
</div>

<!-- ── Shift Tabs ── -->
<ul class="nav nav-pills mb-4 gap-2 bg-slate-50 p-1 rounded-4 border border-slate-100 d-inline-flex">
    <li class="nav-item">
        <a class="nav-link rounded-4 px-4 <?= $shiftParam==='day' ? 'active shadow-sm fw-bold' : 'text-secondary' ?>" 
           href="?view=<?= $viewParam ?>&shift=day&date=<?= $dateParam ?>">
            ☀️ เวรกลางวัน
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-4 px-4 <?= $shiftParam==='night' ? 'active shadow-sm fw-bold' : 'text-secondary' ?>" 
           href="?view=<?= $viewParam ?>&shift=night&date=<?= $dateParam ?>">
            🌙 เวรกลางคืน
        </a>
    </li>
</ul>

<?php if (empty($teachers)): ?>
<div class="alert alert-warning border-0 shadow-sm rounded-4 p-4">
    <div class="d-flex gap-3 align-items-center">
        <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div>
            <h6 class="mb-1 fw-bold">ยังไม่มีรายชื่อครูเวร</h6>
            <p class="mb-0 small opacity-75">กรุณาเพิ่มรายชื่อครูก่อนเริ่มจัดตาราง — <a href="teachers.php" class="alert-link">ไปหน้าจัดการครู</a></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Schedule Grid ── -->
<div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4">
    <div class="card-body p-0">
        <?php if ($viewParam === 'month'): ?>
            <!-- ═══ MONTHLY CALENDAR VIEW ═══ -->
            <style>
                .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); border: none; }
                .cal-head { background: #f8f9fa; padding: 12px; text-align: center; font-weight: 800; border-right: 1px solid #edf2f7; border-bottom: 2px solid #edf2f7; font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.05em; }
                .cal-day { min-height: 140px; padding: 10px; border-right: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; background: #fff; cursor: pointer; transition: all .2s; }
                .cal-day:hover { background: #f7fafc; transform: scale(1.01); z-index: 1; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
                .cal-day.other-month { background: #fcfcfc; color: #cbd5e0; opacity: 0.6; }
                .cal-day.today { background: #ebf8ff; border-top: 2px solid #4299e1; }
                .cal-day .date-num { font-weight: 800; font-size: 16px; margin-bottom: 8px; color: #2d3748; }
                .cal-day.today .date-num { color: #3182ce; }
                .cal-content { display: flex; flex-direction: column; gap: 4px; }
            </style>
            <div class="cal-grid">
                <?php foreach ($dayNames as $dn): ?>
                    <div class="cal-head"><?= $dn ?></div>
                <?php endforeach; ?>

                <?php
                $curr = $weekStart;
                while ($curr <= $weekEnd) {
                    $isOther = (date('Y-m', strtotime($curr)) !== $monthParam);
                    $isToday = ($curr === date('Y-m-d'));
                    $dayNum  = date('j', strtotime($curr));
                    ?>
                    <div class="cal-day <?= $isOther?'other-month':'' ?> <?= $isToday?'today':'' ?>" 
                         onclick="llwSchedule.openDayModal('<?= $curr ?>')">
                        <div class="date-num"><?= $dayNum ?></div>
                        <div class="cal-content">
                            <?php 
                            for ($p=1; $p<=$maxPts; $p++) {
                                $assigned = $slots[$curr][$p] ?? [];
                                foreach ($assigned as $t) {
                                    $colorClass = ($p == $maxPts) ? 'chip-2' : '';
                                    echo '<span class="teacher-chip '.$colorClass.'"><i class="fas fa-user" style="font-size:9px"></i> '.htmlspecialchars(mb_substr($t['prefix'].$t['full_name'], 0, 12)).'</span>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <?php
                    $curr = date('Y-m-d', strtotime($curr . ' +1 day'));
                }
                ?>
            </div>
        <?php else: ?>
            <!-- ═══ WEEKLY LIST VIEW ═══ -->
            <div class="table-responsive">
                <table class="table table-bordered mb-0 border-0">
                    <thead class="table-light">
                        <tr class="border-0">
                            <th class="text-center align-middle bg-slate-50 border-0" style="width:120px; font-size:12px; font-weight:800; color:#718096; text-transform:uppercase;">จุดเวร</th>
                            <?php 
                            for ($i = 0; $i < 7; $i++):
                                $day     = date('Y-m-d', strtotime($weekStart . " +$i days"));
                                $isToday = ($day === date('Y-m-d'));
                                $isWkend = ($i >= 5);
                            ?>
                            <th class="text-center border-0 <?= $isToday ? 'bg-primary-subtle text-primary' : ($isWkend ? 'bg-amber-50 text-amber-700' : 'bg-slate-50 text-slate-700') ?>" style="min-width:140px; padding:15px 10px;">
                                <div class="fw-black fs-6 mb-1"><?= $dayNames[$i] ?></div>
                                <div class="small fw-bold opacity-75"><?= thDateShort($day) ?></div>
                            </th>
                            <?php endfor; ?>
                        </tr>
                        <?php if ($shiftParam !== 'night'): ?>
                        <!-- ── Row: กลุ่มเวร ── -->
                        <tr class="border-0">
                            <td class="text-center align-middle group-cell bg-slate-50 border-0" style="font-size:10px;font-weight:800;color:#94a3b8;letter-spacing:0.05em;">
                                <i class="fas fa-layer-group d-block mb-1 fs-5"></i>กลุ่ม
                            </td>
                            <?php for ($i = 0; $i < 7; $i++):
                                $day = date('Y-m-d', strtotime($weekStart . " +$i days"));
                                $dg  = $dayGroups[$day] ?? null;
                            ?>
                            <td class="group-cell border-0">
                                <div class="group-btn">
                                    <?php if ($dg && $dg['group_id']): ?>
                                    <button class="group-badge border-0"
                                            style="background:<?= htmlspecialchars($dg['group_color'] ?? '#6c757d') ?>"
                                            onclick="llwSchedule.openGroupPicker('<?= $day ?>')"
                                            title="คลิกเพื่อเปลี่ยนกลุ่ม">
                                        <i class="fas fa-users" style="font-size:10px"></i>
                                        <?= htmlspecialchars(mb_substr($dg['group_name'] ?? '', 0, 12)) ?>
                                    </button>
                                    <?php else: ?>
                                    <button class="group-empty border-dashed" onclick="llwSchedule.openGroupPicker('<?= $day ?>')">
                                        <i class="fas fa-plus" style="font-size:10px"></i>เลือกกลุ่ม
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                    <?php for ($pt = 1; $pt <= $maxPts; $pt++):
                        $ptName = $pointNames[$pt-1] ?? 'จุดที่ '.$pt;
                    ?>
                    <tr class="border-0">
                        <td class="text-center align-middle point-label border-0 bg-slate-50" style="padding:15px 10px;">
                            <span class="badge bg-primary rounded-pill px-2 py-2 w-100 shadow-sm" style="white-space:normal;line-height:1.2;font-size:10px;"><?= htmlspecialchars($ptName) ?></span>
                        </td>
                        <?php for ($i = 0; $i < 7; $i++):
                            $day    = date('Y-m-d', strtotime($weekStart . " +$i days"));
                            $isToday = ($day === date('Y-m-d'));
                            $assigned = $slots[$day][$pt] ?? [];
                        ?>
                        <td class="duty-cell border-bottom border-end <?= $isToday ? 'today-col' : '' ?>"
                            onclick="llwSchedule.openDayModal('<?= $day ?>', '<?= $shiftParam ?>', <?= $pt ?>)">
                            <div class="duty-cell-inner">
                                <?php if (!empty($assigned)): ?>
                                    <?php foreach ($assigned as $idx => $t): ?>
                                    <span class="teacher-chip <?= $idx > 0 ? 'chip-2' : '' ?> shadow-xs">
                                        <i class="fas fa-user" style="font-size:10px"></i>
                                        <?= htmlspecialchars(mb_substr($t['prefix'].$t['full_name'], 0, 16)) ?>
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
        <?php endif; ?>
    </div>
</div>

<style>
.duty-cell { min-width:130px; vertical-align:top; padding:0 !important; cursor:pointer; transition:background .12s; position:relative; }
.duty-cell:hover { background:rgba(13,110,253,.06); }
.duty-cell-inner { min-height:64px; padding:8px; display:flex; flex-direction:column; gap:4px; align-items:flex-start; width:100%; height:100%; }
.teacher-chip { display:inline-flex; align-items:center; gap:4px; background:#e7f0ff; color:#1d4ed8; border-radius:8px; padding:2px 8px; font-size:12px; font-weight:600; white-space:nowrap; max-width:100%; overflow:hidden; text-overflow:ellipsis; }
.teacher-chip.chip-2 { background:#fef3c7; color:#92400e; }
.add-chip { display:inline-flex; align-items:center; gap:3px; color:#adb5bd; font-size:12px; cursor:pointer; padding:2px 6px; border:1.5px dashed #dee2e6; border-radius:8px; transition:all .12s; pointer-events: none; }
.duty-cell:hover .add-chip { color:#2563eb; border-color:#2563eb; background:#f0f5ff; }
.point-label { font-weight:700; white-space:nowrap; min-width:70px; }
.today-col { background:rgba(13,110,253,.04) !important; }
.group-cell { padding:0 !important; text-align:center; border-bottom:2px solid #dee2e6; background:#f8f9fa; }
.group-btn { width:100%; height:100%; border:none; background:transparent; padding:8px; display:flex; align-items:center; justify-content:center; }
.group-badge { display:inline-flex; align-items:center; gap:5px; border-radius:20px; padding:4px 12px; font-size:11px; font-weight:700; color:white; border:none; transition:opacity .15s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.group-badge:hover { opacity:.85; transform: translateY(-1px); }
.group-empty { display:inline-flex; align-items:center; gap:4px; color:#adb5bd; font-size:11px; padding:4px 10px; border:1.5px dashed #dee2e6; border-radius:20px; transition:all .15s; background:transparent; }
.group-btn:hover .group-empty { color:#2563eb; border-color:#2563eb; background:#f0f5ff; }

/* Visual feedback for clicks */
.btn:active, .duty-cell:active { transform: scale(0.98); opacity: 0.8; }
.hover-bg-white:hover { background-color: white !important; }
</style>

<!-- ── Legend ── -->
<div class="mt-3 d-flex gap-3 align-items-center flex-wrap">
    <small class="text-muted fw-bold">สัญลักษณ์:</small>
    <span class="teacher-chip"><i class="fas fa-user" style="font-size:10px"></i>ครูคนที่ 1</span>
    <span class="teacher-chip chip-2"><i class="fas fa-user" style="font-size:10px"></i>ครูคนที่ 2</span>
    <span class="add-chip" style="pointer-events:auto"><i class="fas fa-plus" style="font-size:10px"></i>ว่าง</span>
</div>

<!-- ═══ Modal Area ═══ -->
<div id="scheduleModalContainer">

    <!-- Modal: เลือกกลุ่มเวร -->
    <div class="modal fade" id="groupPickerModal" tabindex="-1" aria-labelledby="gpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="gpModalLabel">
                        <i class="fas fa-layer-group me-2 text-primary"></i>
                        เลือกกลุ่มเวร — <span id="gpDate" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="gpBody">
                    <!-- Dynamic content -->
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-danger rounded-pill px-4 me-auto shadow-sm" onclick="llwSchedule.clearGroup()">
                        <i class="fas fa-times me-1"></i>ล้างกลุ่มวันนี้
                    </button>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: จัดจุดเวรทั้งวัน -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="assignModalLabel">
                        <i class="fas fa-calendar-day me-2 text-primary"></i>
                        จัดเวร — <span id="assignTitle" class="text-primary"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="assignBody">
                    <div class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <div class="mt-2 text-muted">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-danger rounded-pill px-4 me-auto shadow-sm" onclick="llwSchedule.clearDay()">
                        <i class="fas fa-trash me-1"></i>ล้างทั้งวัน
                    </button>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="llwSchedule.saveDay()">
                        <i class="fas fa-save me-1"></i>บันทึกข้อมูล
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
window.llwSchedule = (function() {
    const csrfToken  = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>';
    const apiUrl     = '../../duty/api/schedule_api.php';
    const maxPtsJS   = <?= (int)$maxPts ?>;
    const curShiftPage = '<?= $shiftParam ?>';
    const pointNamesJS = <?= json_encode($pointNames, JSON_UNESCAPED_UNICODE) ?>;
    const allTeachersData = <?= json_encode($teachers, JSON_UNESCAPED_UNICODE) ?>;
    const CHAIRMAN_INDEX = <?= count($pointNames) ?>;
    const allGroupsData = <?= json_encode($allGroups, JSON_UNESCAPED_UNICODE) ?>;
    const thDaysFull = ['','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์','อาทิตย์'];
    const thMonAbbr  = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

    let curDate    = '';
    let curShift   = curShiftPage;
    let curTargetPt = null;
    let modalMembers = [];
    let gpCurDate  = '';

    function esc(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    return {
        init: function() {
            console.log("LLW Duty Schedule: Initializing...");
        },

        getModal: function(id) {
            const el = document.getElementById(id);
            if (!el) return null;
            if (window.bootstrap && bootstrap.Modal) {
                return bootstrap.Modal.getOrCreateInstance(el);
            }
            return null;
        },

        openGroupPicker: function(date) {
            gpCurDate = date;
            const d  = new Date(date + 'T00:00:00');
            const mo = thMonAbbr[d.getMonth()+1];
            const shLabel = curShiftPage === 'night' ? '🌙 กลางคืน' : '☀️ กลางวัน';
            document.getElementById('gpDate').textContent =
                thDaysFull[d.getDay()] + ' ' + d.getDate() + ' ' + mo + ' — ' + shLabel;

            const body = document.getElementById('gpBody');
            if (!allGroupsData.length) {
                body.innerHTML = `<div class="alert alert-warning py-3 rounded-4"><i class="fas fa-exclamation-triangle me-2"></i>ยังไม่มีกลุ่มเวร — <a href="groups.php" class="alert-link">ไปสร้างกลุ่มก่อน</a></div>`;
            } else {
                let html = '<div class="d-grid gap-3">';
                allGroupsData.forEach(g => {
                    html += `
                        <button class="btn btn-lg text-white text-start fw-bold d-flex align-items-center gap-3 rounded-4 border-0 shadow-sm transition-all hover-scale gp-select-btn"
                                style="background:${g.color}; padding: 1.25rem 1.5rem;"
                                data-date="${date}" data-id="${g.id}">
                            <i class="fas fa-users fs-4"></i> 
                            <div>
                                <div class="fs-6">${esc(g.name)}</div>
                                <div class="small fw-normal opacity-75">${g.group_type === 'chairman' ? 'ประธาน' : (g.group_type === 'night' ? 'เวรกลางคืน' : 'เวรกลางวัน')}</div>
                            </div>
                        </button>`;
                });
                html += '</div>';
                body.innerHTML = html;
                
                // Attach event delegation for the new buttons
                body.querySelectorAll('.gp-select-btn').forEach(btn => {
                    btn.onclick = () => this.assignGroup(btn.dataset.date, btn.dataset.id);
                });
            }
            this.getModal('groupPickerModal')?.show();
        },

        assignGroup: function(date, groupId) {
            const fd = new FormData();
            fd.append('action',     'assign_group');
            fd.append('csrf_token', csrfToken);
            fd.append('duty_date',  date);
            fd.append('shift',      curShiftPage);
            fd.append('group_id',   groupId);
            fetch(apiUrl, {method:'POST', body:fd})
                .then(r => r.json())
                .then(d => {
                    this.getModal('groupPickerModal')?.hide();
                    if (d.status === 'success') location.reload();
                    else Swal.fire({icon:'error', title:'ผิดพลาด', text: d.message});
                });
        },

        clearGroup: function() {
            Swal.fire({
                icon:'warning', title:'ล้างกลุ่มวันนี้?',
                text:'กลุ่มที่ระบุไว้จะถูกล้าง แต่จุดเวรรายบุคคลยังคงอยู่',
                showCancelButton:true, confirmButtonColor:'#ef4444',
                confirmButtonText:'ล้างเลย', cancelButtonText:'ยกเลิก', reverseButtons:true
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
                        this.getModal('groupPickerModal')?.hide();
                        location.reload();
                    });
            });
        },

        openDayModal: function(date, shift, targetPt) {
            curDate  = date;
            curShift = shift || curShiftPage;
            curTargetPt = targetPt || null;

            const d   = new Date(date + 'T00:00:00');
            const mo  = thMonAbbr[d.getMonth()+1];
            const shiftTh = (curShift === 'night') ? '🌙 กลางคืน' : '☀️ กลางวัน';
            document.getElementById('assignTitle').textContent =
                thDaysFull[d.getDay()] + ' ' + d.getDate() + ' ' + mo + ' — ' + shiftTh;

            const body = document.getElementById('assignBody');
            body.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><div class="mt-2 text-muted">กำลังโหลด...</div></div>';
            this.getModal('assignModal')?.show();

            fetch(`${apiUrl}?action=get_day_detail&duty_date=${date}&shift=${curShift}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status !== 'success') {
                        body.innerHTML = '<p class="text-danger p-4 text-center">โหลดข้อมูลไม่สำเร็จ</p>'; return;
                    }
                    const dayGroup = data.day_group;
                    modalMembers   = data.members || [];
                    const allTeachers = data.all_teachers || [];
                    const maxPts   = data.max_points || maxPtsJS;
                    this.renderDayForm(dayGroup, modalMembers, maxPts, allTeachers);
                })
                .catch(() => { body.innerHTML = '<p class="text-danger p-4 text-center">เชื่อมต่อ server ไม่ได้</p>'; });
        },

        renderDayForm: function(dayGroup, members, maxPts, allTeachers) {
            const body = document.getElementById('assignBody');
            const targetPt = curTargetPt;
            const isChairmanMode = (targetPt === CHAIRMAN_INDEX);

            const ptMap = {};
            members.forEach(m => {
                if (m.point_no) {
                    if (!ptMap[m.point_no]) ptMap[m.point_no] = [];
                    ptMap[m.point_no].push(m);
                }
            });

            const blankOpt = '<option value="">— ไม่จัด —</option>';
            function buildOpts(list, selectedId) {
                if (!list || !list.length) return blankOpt;
                return blankOpt + list.map(m =>
                    `<option value="${m.id}" ${m.id == selectedId ? 'selected' : ''}>${m.prefix||''}${m.full_name}</option>`
                ).join('');
            }

            const gc = dayGroup?.group_color || '#6c757d';
            let rows = '';
            for (let i = 1; i <= maxPts; i++) {
                if (isChairmanMode && i !== CHAIRMAN_INDEX) continue;
                if (!isChairmanMode && i === CHAIRMAN_INDEX) continue;

                const assigned = ptMap[i] || [];
                const t1 = assigned[0] || null;
                const t2 = assigned[1] || null;
                const ptName = (pointNamesJS[i-1]) || ('จุดที่ '+i);
                const isChairman = (i === CHAIRMAN_INDEX);
                const optList = isChairman ? (allTeachers.length ? allTeachers : allTeachersData) : members;

                if (isChairman) {
                    rows += `
                    <tr class="point-row bg-light" data-point="${i}">
                        <td class="align-middle p-3" style="width:180px">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center text-white" style="width:24px;height:24px;font-size:12px"><i class="fas fa-crown"></i></div>
                                <span class="fw-bold text-primary">${esc(ptName)}</span>
                            </div>
                        </td>
                        <td class="align-middle p-3" colspan="2">
                            <select class="form-select rounded-3 border-primary-subtle" data-field="teacher_id" onchange="llwSchedule.refreshUnassigned()">
                                ${buildOpts(optList, t1 ? t1.id : '')}
                            </select>
                            <input type="hidden" data-field="role" value="${esc(ptName)}">
                        </td>
                    </tr>`;
                } else {
                    const finalOptList = (optList && optList.length) ? optList : (allTeachers.length ? allTeachers : allTeachersData);
                    rows += `
                    <tr class="point-row" data-point="${i}">
                        <td class="align-middle p-3" style="width:180px">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center text-white fw-bold shadow-sm"
                                     style="width:28px;height:28px;background:${gc};font-size:11px">${i}</div>
                                <span class="fw-bold text-secondary small">${esc(ptName)}</span>
                            </div>
                        </td>
                        <td class="align-middle p-3">
                            <div class="d-flex flex-column gap-2">
                                <select class="form-select form-select-sm rounded-3" data-field="teacher_id" onchange="llwSchedule.refreshUnassigned()">
                                    ${buildOpts(finalOptList, t1 ? t1.id : '')}
                                </select>
                                <select class="form-select form-select-sm rounded-3" data-field="teacher_id" onchange="llwSchedule.refreshUnassigned()">
                                    ${buildOpts(finalOptList, t2 ? t2.id : '')}
                                </select>
                            </div>
                        </td>
                        <td class="align-middle" style="width:10px">
                            <input type="hidden" data-field="role" value="${esc(ptName)}">
                        </td>
                    </tr>`;
                }
            }

            const gn  = dayGroup?.group_name  || 'ไม่ได้ระบุกลุ่ม';
            const cnt = members.filter(m => !m.schedule_id || m.point_no != CHAIRMAN_INDEX).length;

            let headerHtml = `
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="badge rounded-pill shadow-sm" style="background:${gc}; padding:10px 20px; font-size:14px">
                        <i class="fas fa-users me-2"></i>${esc(gn)}
                    </div>
                    <div class="text-muted small fw-medium">
                        <i class="fas fa-info-circle me-1"></i> ${cnt} คนในกลุ่ม — เลือกครูลงแต่ละจุดเวร
                    </div>
                </div>
                ${!dayGroup && !isChairmanMode ? `<div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4 small"><i class="fas fa-exclamation-triangle me-2"></i><strong>ยังไม่ได้ระบุกลุ่มเวร:</strong> ระบบกำลังแสดงรายชื่อครูทั้งหมดให้คุณเลือก</div>` : ''}`;

            const filterBar = `
                <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                    <div class="input-group" style="max-width:280px">
                        <span class="input-group-text bg-white border-end-0 rounded-start-3"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="teacherSearch" class="form-control border-start-0 rounded-end-3" placeholder="ค้นหาชื่อ..."
                            oninput="llwSchedule.filterTeacherOpts()">
                    </div>
                    <div class="form-check form-check-inline mb-0 p-2 bg-slate-50 rounded-3 px-3 border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="maleOnlyChk"
                            ${curShift === 'night' ? 'checked' : ''} onchange="llwSchedule.filterTeacherOpts()">
                        <label class="form-check-label small fw-bold text-primary" for="maleOnlyChk" style="cursor:pointer">
                            <i class="fas fa-mars me-1"></i>เฉพาะผู้ชาย (นาย)
                        </label>
                    </div>
                </div>`;

            body.innerHTML = headerHtml + filterBar + `
                <div class="table-responsive rounded-4 border">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr class="small fw-bold text-muted">
                                <th class="p-3 border-0">จุดเวร</th>
                                <th class="p-3 border-0">${isChairmanMode ? 'รายชื่อครู' : 'ครูคนที่ 1 / คนที่ 2'}</th>
                                <th class="p-3 border-0"></th>
                            </tr>
                        </thead>
                        <tbody id="pointTbody">${rows}</tbody>
                    </table>
                </div>
                ${!isChairmanMode ? `
                <div class="mt-4 p-3 bg-light rounded-4 border-dashed border-2">
                    <div class="fw-bold text-muted mb-2 small"><i class="fas fa-user-clock me-1"></i>ครูที่ยังว่างในกลุ่ม:</div>
                    <div id="unassignedList" class="d-flex flex-wrap gap-2"></div>
                </div>` : ''}`;

            this.refreshUnassigned();
            this.filterTeacherOpts();
        },

        filterTeacherOpts: function() {
            const q = (document.getElementById('teacherSearch')?.value || '').trim().toLowerCase();
            const maleOnly = document.getElementById('maleOnlyChk')?.checked ?? false;

            document.querySelectorAll('#assignBody select[data-field="teacher_id"] option').forEach(opt => {
                if (!opt.value) { opt.hidden = false; return; }
                const txt  = opt.textContent.trim();
                const male = txt.includes('นาย');
                const match = (!maleOnly || male) && (!q || txt.toLowerCase().includes(q));
                opt.hidden = !match;
            });
        },

        refreshUnassigned: function() {
            const el = document.getElementById('unassignedList');
            if (!el) return;
            const selected = new Set(
                [...document.querySelectorAll('.point-row [data-field="teacher_id"]')]
                    .map(s => parseInt(s.value)).filter(Boolean)
            );
            const unassigned = modalMembers.filter(m => !selected.has(parseInt(m.id)));
            el.innerHTML = unassigned.length
                ? unassigned.map(m => `<span class="badge rounded-pill bg-white border text-secondary px-3 py-2 shadow-xs fw-normal">${esc(m.prefix+m.full_name)}</span>`).join('')
                : '<div class="text-success fw-bold py-2"><i class="fas fa-check-circle me-1"></i>ทุกคนถูกจัดเวรครบแล้ว</div>';
        },

        saveDay: function() {
            const assignments = [];
            const targetPoints = [];
            document.querySelectorAll('.point-row').forEach(row => {
                const ptNo = parseInt(row.dataset.point);
                if (!targetPoints.includes(ptNo)) targetPoints.push(ptNo);
                
                const role = row.querySelector('[data-field="role"]').value.trim();
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
            fd.append('target_points', JSON.stringify(targetPoints));

            const btn = document.querySelector('#assignModal .btn-primary');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>กำลังบันทึก...';

            fetch(apiUrl, {method:'POST', body:fd})
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        this.getModal('assignModal')?.hide();
                        location.reload();
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        Swal.fire({icon:'error', title:'ผิดพลาด', text: d.message || 'บันทึกไม่สำเร็จ'});
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    Swal.fire({icon:'error', title:'เชื่อมต่อไม่ได้'});
                });
        },

        clearDay: function() {
            const targetPoints = [];
            document.querySelectorAll('.point-row').forEach(row => {
                const ptNo = parseInt(row.dataset.point);
                if (!targetPoints.includes(ptNo)) targetPoints.push(ptNo);
            });

            const isChairman = targetPoints.includes(CHAIRMAN_INDEX);
            const titleMsg = isChairman ? 'ล้างตำแหน่งประธาน?' : 'ล้างเวรจุดที่ 1-5?';
            const textMsg  = isChairman ? 'ข้อมูลตำแหน่งประธานวันนี้จะถูกลบ' : 'ข้อมูลการจัดเวรทั้งหมดของจุดที่ 1-5 ในวันนี้จะถูกลบทิ้ง';

            Swal.fire({
                icon:'warning', title: titleMsg,
                text: textMsg,
                showCancelButton:true, confirmButtonColor:'#ef4444',
                confirmButtonText:'ล้างเลย', cancelButtonText:'ยกเลิก', reverseButtons:true
            }).then(r => {
                if (!r.isConfirmed) return;
                const fd = new FormData();
                fd.append('action', 'save_points');
                fd.append('csrf_token', csrfToken);
                fd.append('duty_date', curDate);
                fd.append('shift', curShift);
                fd.append('assignments', '[]');
                fd.append('target_points', JSON.stringify(targetPoints));
                fetch(apiUrl, {method:'POST', body:fd})
                    .then(r => r.json())
                    .then(d => { if (d.status === 'success') location.reload(); });
            });
        }
    };
})();

// Initialize when ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => llwSchedule.init());
} else {
    llwSchedule.init();
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
