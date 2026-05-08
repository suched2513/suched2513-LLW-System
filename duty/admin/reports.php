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

<style>
/* Dashboard Stats */
.stat-card { border: none; border-radius: 24px; padding: 20px; position: relative; overflow: hidden; transition: all 0.3s ease; height: 100%; }
.stat-card:hover { transform: translateY(-5px); }
.stat-card .icon-box { width: 48px; height: 48px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 15px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); color: #fff; }
.stat-card .stat-value { font-size: 2.5rem; font-weight: 900; line-height: 1; margin-bottom: 5px; color: #fff; }
.stat-card .stat-label { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.8); }

.bg-grad-primary { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4); }
.bg-grad-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 10px 20px -5px rgba(5, 150, 105, 0.4); }
.bg-grad-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); box-shadow: 0 10px 20px -5px rgba(217, 119, 6, 0.4); }
.bg-grad-danger  { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); box-shadow: 0 10px 20px -5px rgba(220, 38, 38, 0.4); }

/* Filter Panel */
.glass-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 24px; box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07); padding: 20px; }

/* Report Cards */
.report-card { border: none; border-radius: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.3s ease; overflow: hidden; height: 100%; border: 1px solid #f1f5f9; }
.report-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
.card-night { background: #1e293b; border-color: #334155; color: #f1f5f9; }
.card-night .text-dark { color: #f8fafc !important; }
.card-night .text-muted { color: #94a3b8 !important; }

.status-badge { padding: 6px 12px; border-radius: 12px; font-weight: 800; font-size: 11px; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; }
.badge-glow-success { background: #ecfdf5; color: #059669; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.1); }
.badge-glow-warning { background: #fffbeb; color: #d97706; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.1); }
.badge-glow-danger  { background: #fef2f2; color: #dc2626; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.1); }

.img-preview-box { position: relative; border-radius: 18px; overflow: hidden; margin: 15px 0; aspect-ratio: 16/9; background: #f8fafc; }
.img-preview-box img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
.img-preview-box:hover img { transform: scale(1.1); }
.img-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.2); opacity: 0; transition: opacity 0.3s; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.5rem; }
.img-preview-box:hover .img-overlay { opacity: 1; }

.teacher-avatar { width: 36px; height: 36px; border-radius: 12px; background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; }
.card-night .teacher-avatar { background: #334155; color: #60a5fa; }
</style>

<!-- Link GLightbox -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">

<!-- ── KPI Cards ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-primary">
            <div class="icon-box"><i class="fas fa-th-list"></i></div>
            <div class="stat-value"><?= $totalPoints ?></div>
            <div class="stat-label">จุดทั้งหมด</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-success">
            <div class="icon-box"><i class="fas fa-check-double"></i></div>
            <div class="stat-value"><?= $completePoints ?></div>
            <div class="stat-label">รายงานครบ</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-warning">
            <div class="icon-box"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-value"><?= $partialPoints ?></div>
            <div class="stat-label">ส่งบางส่วน</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-danger">
            <div class="icon-box"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-value"><?= $pendingPoints ?></div>
            <div class="stat-label">ยังไม่ส่ง</div>
        </div>
    </div>
</div>

<!-- Progress Bar -->
<?php if ($totalPoints > 0): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius: 20px;">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="mb-0 fw-black text-dark">สรุปความคืบหน้าภาพรวม</h6>
                <p class="text-muted small mb-0">สถานะการรายงานรูปถ่ายประจำวัน</p>
            </div>
            <div class="text-end">
                <span class="fs-4 fw-black text-primary"><?= $progressPct ?>%</span>
                <div class="small text-muted fw-bold"><?= $completePoints ?> จาก <?= $totalPoints ?> จุด</div>
            </div>
        </div>
        <div class="progress shadow-sm" style="height:12px; border-radius: 10px; background: #f1f5f9;">
            <div class="progress-bar bg-grad-success" style="width:<?= $progressPct ?>%; border-radius: 10px;"></div>
        </div>
    </div>
</div>
<!-- Actions & Filter -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="glass-panel h-100 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="bg-blue-600 text-white p-3 rounded-2xl shadow-lg">
                    <i class="fas fa-file-invoice text-2xl"></i>
                </div>
                <div>
                    <h5 class="fw-black mb-0">รายงานสรุปผลการปฏิบัติงาน</h5>
                    <p class="text-muted small mb-0">รวบรวมข้อมูลทุกจุดเป็นฉบับเดียวเพื่อเสนอผู้บริหาร</p>
                </div>
            </div>
            <a href="print_daily_report.php?date=<?= $filterDate ?>" target="_blank" 
               class="btn btn-primary rounded-xl px-5 py-3 fw-black shadow-lg shadow-blue-200 hover:scale-[1.02] transition-all">
                <i class="fas fa-print me-2"></i>พิมพ์รายงานสรุปประจำวัน
            </a>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass-panel">
            <form method="GET" class="row g-2">
                <div class="col-12">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 block">เลือกวันที่</label>
                    <input type="date" name="date" class="form-control rounded-xl border-slate-200" 
                           value="<?= $filterDate ?>" onchange="this.form.submit()">
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Filter & Actions ── -->
<div class="glass-panel mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-black small text-muted uppercase tracking-wider">เลือกวันที่</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 rounded-start-2xl"><i class="fas fa-calendar text-primary"></i></span>
                <input type="date" name="date" class="form-control border-start-0 rounded-end-2xl ps-0"
                       value="<?= htmlspecialchars($filterDate) ?>" max="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-black small text-muted uppercase tracking-wider">เลือกกะ</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 rounded-start-2xl"><i class="fas fa-clock text-primary"></i></span>
                <select name="shift" class="form-select border-start-0 rounded-end-2xl ps-0">
                    <option value="" <?= $filterShift==='' ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="day"   <?= $filterShift==='day'   ? 'selected' : '' ?>>☀️ เวรกลางวัน</option>
                    <option value="night" <?= $filterShift==='night' ? 'selected' : '' ?>>🌙 เวรกลางคืน</option>
                </select>
            </div>
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
    if (empty($items)) { echo '<p class="text-muted p-4">ไม่มีข้อมูลการจัดเวร</p>'; return; }
    echo '<div class="row g-3">';
    foreach ($items as $s):
        $status     = $s['status'] ?? 'pending';
        $shift      = $s['shift'] ?? 'day';
        $photoCount = (int)($s['photo_count'] ?? 0);
        $reportId   = $s['report_id'] ?? null;
        $initials   = mb_substr($s['teacher_name'] ?? '?', 0, 1);

        // Badge config
        if ($status === 'complete') {
            $badgeClass = 'badge-glow-success';
            $badgeText  = '<i class="fas fa-check-circle"></i> ครบ ' . $photosRequired . ' รูป';
        } elseif ($status === 'partial') {
            $badgeClass = 'badge-glow-warning';
            $badgeText  = '<i class="fas fa-clock"></i> ' . $photoCount . '/' . $photosRequired . ' รูป';
        } else {
            $badgeClass = 'badge-glow-danger';
            $badgeText  = '<i class="fas fa-times-circle"></i> ยังไม่รายงาน';
        }
        
        $cardClass = ($shift === 'night') ? 'card-night' : '';
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="report-card card <?= $cardClass ?>">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="status-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                        <div class="text-end">
                            <span class="fw-black text-muted d-block small uppercase tracking-tighter">จุดที่ <?= $s['point_no'] ?></span>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="teacher-avatar">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-black text-dark"><?= htmlspecialchars($s['teacher_name'] ?? '— ไม่ระบุ —') ?></h6>
                            <?php if ($s['role']): ?>
                            <span class="text-muted small fw-bold">
                                <i class="fas fa-tag me-1 opacity-50"></i><?= htmlspecialchars($s['role']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($s['completed_at']): ?>
                    <p class="mb-3 small text-success fw-bold">
                        <i class="fas fa-history me-1"></i>
                        ส่งครบเมื่อ <?= date('H:i', strtotime($s['completed_at'])) ?> น.
                    </p>
                    <?php endif; ?>

                    <!-- Thumbnail -->
                    <div class="img-preview-box">
                        <?php if ($s['first_thumb']): ?>
                        <a href="<?= htmlspecialchars($basePath . '/duty/api/photo.php?path=' . urlencode($s['first_thumb'])) ?>"
                           class="glightbox" data-gallery="report-<?= $reportId ?>">
                            <img src="<?= htmlspecialchars($basePath . '/duty/api/photo.php?path=' . urlencode($s['first_thumb'])) ?>"
                                 alt="รูปรายงาน">
                            <div class="img-overlay">
                                <i class="fas fa-search-plus"></i>
                            </div>
                        </a>
                        <?php else: ?>
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted opacity-30">
                            <i class="fas fa-image mb-2" style="font-size: 2rem"></i>
                            <span class="small fw-bold">ยังไม่มีรูปภาพ</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <?php if ($reportId): ?>
                        <a href="report_detail.php?id=<?= $reportId ?>"
                           class="btn btn-primary flex-fill rounded-xl fw-bold shadow-sm">
                            <i class="fas fa-images me-1"></i>ดูรายงาน
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($status !== 'complete' && $s['teacher_id']): ?>
                        <a href="remind.php?schedule_id=<?= $s['schedule_id'] ?>"
                           class="btn btn-warning rounded-xl fw-bold shadow-sm"
                           title="ส่งการเตือน"
                           onclick="return confirm('ส่งการเตือนไปยัง <?= htmlspecialchars($s['teacher_name'] ?? '') ?>?')">
                            <i class="fas fa-bell"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($s['telegram_username']): ?>
                        <a href="https://t.me/<?= htmlspecialchars($s['telegram_username']) ?>"
                           target="_blank" class="btn btn-info rounded-xl text-white fw-bold shadow-sm" title="ทัก Telegram">
                            <i class="fab fa-telegram-plane"></i>
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
