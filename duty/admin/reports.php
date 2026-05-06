<?php
/**
 * duty/admin/reports.php — Dashboard ตรวจรายงานเวร
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();

// ── Filter ──
$filterDate  = $_GET['date']  ?? date('Y-m-d');
$filterShift = $_GET['shift'] ?? '';

// ── ถ้ากด "ส่งสรุปตอนนี้" ──
if (isset($_GET['send_summary'])) {
    csrf_verify();
    include __DIR__ . '/../cron/daily_summary.php';
    header('Location: reports.php?date=' . $filterDate . '&sent=1');
    exit();
}

// ── ดึง duty_settings ──
$stmtDS = $pdo->query("SELECT skey, svalue FROM duty_settings");
$ds = [];
while ($r = $stmtDS->fetch()) $ds[$r['skey']] = $r['svalue'];
$photosRequired = (int)($ds['photos_required_per_point'] ?? 3);

// ── ดึงข้อมูลตารางเวร + รายงาน ──
$sql = "
    SELECT
        ds.id AS schedule_id,
        ds.shift,
        ds.point_no,
        ds.role,
        dt.id   AS teacher_id,
        dt.full_name AS teacher_name,
        dt.telegram_user_id,
        dt.telegram_username,
        dr.id   AS report_id,
        dr.status,
        dr.completed_at,
        dr.reminder_sent_at,
        (SELECT COUNT(*) FROM duty_report_photos drp
         WHERE drp.report_id = dr.id AND drp.is_deleted = 0) AS photo_count,
        (SELECT drp2.thumbnail_path FROM duty_report_photos drp2
         WHERE drp2.report_id = dr.id AND drp2.is_deleted = 0
         ORDER BY drp2.received_at ASC LIMIT 1) AS first_thumb
    FROM duty_schedule ds
    LEFT JOIN duty_teachers dt  ON dt.id = ds.teacher_id
    LEFT JOIN duty_reports  dr  ON dr.duty_date = ? AND dr.shift = ds.shift
                                    AND dr.point_no = ds.point_no AND dr.report_round = 1
    WHERE ds.duty_date = ?
";
$params = [$filterDate, $filterDate];

if ($filterShift !== '') {
    $sql .= " AND ds.shift = ?";
    $params[] = $filterShift;
}

$sql .= " ORDER BY ds.shift ASC, ds.point_no ASC";

$stmtSch = $pdo->prepare($sql);
$stmtSch->execute($params);
$schedules = $stmtSch->fetchAll(PDO::FETCH_ASSOC);

// ── สถิติ ──
$totalPoints    = count($schedules);
$completePoints = 0;
$partialPoints  = 0;
$pendingPoints  = 0;
foreach ($schedules as $s) {
    $st = $s['status'] ?? 'pending';
    if ($st === 'complete')     $completePoints++;
    elseif ($st === 'partial') $partialPoints++;
    else                        $pendingPoints++;
}
$progressPct = $totalPoints > 0 ? round(($completePoints / $totalPoints) * 100) : 0;

$pageTitle    = 'รายงานเวรประจำวัน';
$pageSubtitle = 'ตรวจสอบสถานะการรายงานรูปถ่ายของครูเวร';
$activeSystem = 'duty';

require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if (isset($_GET['sent'])): ?>
<script>
Swal.fire({icon:'success',title:'ส่งสรุปแล้ว',text:'ส่งข้อความสรุปเข้ากลุ่ม Telegram เรียบร้อย',confirmButtonColor:'#2563eb'});
</script>
<?php endif; ?>

<!-- Link GLightbox -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">

<!-- ── KPI Cards ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-1 fw-black text-primary"><?= $totalPoints ?></div>
                <div class="small text-muted fw-bold">จุดทั้งหมด</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 shadow-sm bg-success bg-opacity-10">
            <div class="card-body py-3">
                <div class="fs-1 fw-black text-success"><?= $completePoints ?></div>
                <div class="small text-muted fw-bold">ครบแล้ว</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 shadow-sm bg-warning bg-opacity-10">
            <div class="card-body py-3">
                <div class="fs-1 fw-black text-warning"><?= $partialPoints ?></div>
                <div class="small text-muted fw-bold">ส่งบางส่วน</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 shadow-sm bg-danger bg-opacity-10">
            <div class="card-body py-3">
                <div class="fs-1 fw-black text-danger"><?= $pendingPoints ?></div>
                <div class="small text-muted fw-bold">ยังไม่รายงาน</div>
            </div>
        </div>
    </div>
</div>

<!-- Progress Bar -->
<?php if ($totalPoints > 0): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-1">
            <span class="fw-bold small">ความคืบหน้าภาพรวม</span>
            <span class="fw-bold small"><?= $completePoints ?>/<?= $totalPoints ?> จุด (<?= $progressPct ?>%)</span>
        </div>
        <div class="progress" style="height:12px;">
            <div class="progress-bar bg-success" style="width:<?= $progressPct ?>%"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Filter & Actions ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold small mb-1">วันที่</label>
                <input type="date" name="date" class="form-control"
                       value="<?= htmlspecialchars($filterDate) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small mb-1">กะ</label>
                <select name="shift" class="form-select">
                    <option value="" <?= $filterShift==='' ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="day"   <?= $filterShift==='day'   ? 'selected' : '' ?>>☀️ กลางวัน</option>
                    <option value="night" <?= $filterShift==='night' ? 'selected' : '' ?>>🌙 กลางคืน</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i> กรอง
                </button>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="reports.php?date=<?= $filterDate ?>&shift=<?= $filterShift ?>&send_summary=1&csrf_token=<?= csrf_token() ?>"
                   class="btn btn-outline-secondary"
                   onclick="return confirm('ส่งสรุปเข้ากลุ่ม Telegram ตอนนี้?')">
                    <i class="fab fa-telegram me-1"></i> ส่งสรุปเข้ากลุ่มตอนนี้
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ── Grid Cards ── -->
<?php if (empty($schedules)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    ไม่มีตารางเวรสำหรับวันที่ <?= htmlspecialchars($filterDate) ?>
    <a href="schedule.php" class="alert-link ms-2">ไปจัดตารางเวร</a>
</div>
<?php else: ?>

<!-- Day Shift -->
<?php
$daySchedules   = array_filter($schedules, fn($s) => $s['shift'] === 'day');
$nightSchedules = array_filter($schedules, fn($s) => $s['shift'] === 'night');

function renderScheduleCards(array $items, int $photosRequired, string $basePath): void {
    if (empty($items)) { echo '<p class="text-muted">ไม่มีข้อมูล</p>'; return; }
    echo '<div class="row g-3">';
    foreach ($items as $s):
        $status     = $s['status'] ?? 'pending';
        $photoCount = (int)($s['photo_count'] ?? 0);
        $reportId   = $s['report_id'] ?? null;

        // Badge config
        if ($status === 'complete') {
            $badgeClass = 'bg-success';
            $badgeText  = '✅ ครบ ' . $photosRequired . ' รูป';
        } elseif ($status === 'partial') {
            $badgeClass = 'bg-warning text-dark';
            $badgeText  = '🟡 ' . $photoCount . '/' . $photosRequired . ' รูป';
        } else {
            $badgeClass = 'bg-danger';
            $badgeText  = '🔴 ยังไม่รายงาน';
        }
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2">
                    <strong class="text-dark">จุดที่ <?= $s['point_no'] ?></strong>
                    <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                </div>
                <div class="card-body">
                    <p class="mb-1 small">
                        <i class="fas fa-user text-primary me-1"></i>
                        <?= htmlspecialchars($s['teacher_name'] ?? '— ไม่ระบุ —') ?>
                    </p>
                    <?php if ($s['role']): ?>
                    <p class="mb-1 small text-muted">
                        <i class="fas fa-tag me-1"></i><?= htmlspecialchars($s['role']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($s['completed_at']): ?>
                    <p class="mb-2 small text-success">
                        <i class="fas fa-check-circle me-1"></i>
                        รายงานครบ <?= date('H:i', strtotime($s['completed_at'])) ?> น.
                    </p>
                    <?php endif; ?>

                    <!-- Thumbnail -->
                    <?php if ($s['first_thumb']): ?>
                    <div class="mb-2">
                        <a href="<?= htmlspecialchars($basePath . '/duty/api/photo.php?path=' . urlencode($s['first_thumb'])) ?>"
                           class="glightbox" data-gallery="report-<?= $reportId ?>">
                            <img src="<?= htmlspecialchars($basePath . '/duty/api/photo.php?path=' . urlencode($s['first_thumb'])) ?>"
                                 alt="รูปรายงาน" class="img-fluid rounded" style="max-height:100px;object-fit:cover;width:100%;">
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-flex gap-2">
                        <?php if ($reportId): ?>
                        <a href="report_detail.php?id=<?= $reportId ?>"
                           class="btn btn-sm btn-outline-primary flex-fill">
                            <i class="fas fa-images me-1"></i>ดูรูปทั้งหมด
                        </a>
                        <?php endif; ?>
                        <?php if ($status !== 'complete' && $s['teacher_id']): ?>
                        <a href="remind.php?schedule_id=<?= $s['schedule_id'] ?>"
                           class="btn btn-sm btn-outline-warning"
                           onclick="return confirm('ส่งการเตือนไปยัง <?= htmlspecialchars($s['teacher_name'] ?? '') ?>?')">
                            <i class="fas fa-bell"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($s['telegram_username']): ?>
                        <a href="https://t.me/<?= htmlspecialchars($s['telegram_username']) ?>"
                           target="_blank" class="btn btn-sm btn-outline-info">
                            <i class="fab fa-telegram"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php
    endforeach;
    echo '</div>';
}
?>

<?php if (!$filterShift || $filterShift === 'day'): ?>
<h5 class="fw-bold mb-3"><i class="fas fa-sun text-warning me-2"></i>เวรกลางวัน</h5>
<?php renderScheduleCards($daySchedules, $photosRequired, $base_path); ?>
<?php endif; ?>

<?php if (!$filterShift || $filterShift === 'night'): ?>
<h5 class="fw-bold mt-4 mb-3"><i class="fas fa-moon text-indigo-600 me-2"></i>เวรกลางคืน</h5>
<?php renderScheduleCards($nightSchedules, $photosRequired, $base_path); ?>
<?php endif; ?>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
<script>GLightbox({ selector: '.glightbox' });</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
