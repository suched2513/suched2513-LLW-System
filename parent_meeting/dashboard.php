<?php
/**
 * parent_meeting/dashboard.php - หน้าแสดงสถิติภาพรวม
 */
require_once __DIR__ . '/config.php';
checkLogin();

$pageTitle = 'Dashboard';
$pageSubtitle = 'ระบบบันทึกรายงานการประชุมผู้ปกครองและเครือข่ายผู้ปกครอง';
$activePage = 'dashboard';

$pdo = getPmPdo();

try {
    // 1. สถิติจำนวนห้องเรียนทั้งหมด
    $totalRooms = (int)$pdo->query("SELECT COUNT(*) FROM classrooms")->fetchColumn();
    
    // 2. จำนวนห้องที่ส่งรายงานแล้ว (อย่างน้อย 1 รายงาน)
    $submittedRooms = (int)$pdo->query("SELECT COUNT(DISTINCT classroom_id) FROM meetings")->fetchColumn();
    
    // 3. จำนวนห้องที่ยังไม่ได้ส่งรายงาน
    $pendingRooms = max(0, $totalRooms - $submittedRooms);
    
    // 4. จำนวนเครือข่ายผู้ปกครองทั้งหมด
    $totalNetworks = (int)$pdo->query("SELECT COUNT(*) FROM network_parents")->fetchColumn();
    
    // 5. สถิติร้อยละผู้ปกครองที่เข้าร่วมเฉลี่ย
    $avgAttendance = 0;
    $attStmt = $pdo->query("SELECT SUM(attend_count) as total_attend, SUM(total_parents) as total_parents FROM meetings");
    $attData = $attStmt->fetch();
    if ($attData && $attData['total_parents'] > 0) {
        $avgAttendance = round(($attData['total_attend'] / $attData['total_parents']) * 100, 2);
    }
    
    // 6. ดึงข้อมูลกราฟ: อัตราการเข้าร่วมประชุมเฉลี่ยแยกตามระดับชั้น (ม.1, ม.2, ม.3...)
    // เราจะดึงข้อมูล classrooms เชื่อมโยงกับ meetings เพื่อหากลุ่มตามระดับชั้น (level)
    $chartQuery = "
        SELECT c.level, 
               SUM(m.attend_count) as attend_sum, 
               SUM(m.total_parents) as parents_sum
        FROM meetings m
        JOIN classrooms c ON m.classroom_id = c.id
        GROUP BY c.level
        ORDER BY c.level
    ";
    $chartStmt = $pdo->query($chartQuery);
    $chartRaw = $chartStmt->fetchAll();
    
    $chartLabels = [];
    $chartValues = [];
    foreach ($chartRaw as $row) {
        $chartLabels[] = $row['level'];
        $rate = $row['parents_sum'] > 0 ? round(($row['attend_sum'] / $row['parents_sum']) * 100, 1) : 0;
        $chartValues[] = $rate;
    }
    
    // หากข้อมูลกราฟว่างเปล่า ให้ใส่ค่าจำลองไว้เพื่อให้กราฟแสดงได้สวยงาม
    if (empty($chartLabels)) {
        $chartLabels = ['ม.1', 'ม.2', 'ม.3'];
        $chartValues = [0, 0, 0];
    }
    
    // 7. รายการรายงานการประชุมล่าสุด 5 รายการ
    $recentMeetingsQuery = "
        SELECT m.id, m.meeting_date, m.semester, m.academic_year, c.level, c.room_name, u.fullname as creator_name
        FROM meetings m
        JOIN classrooms c ON m.classroom_id = c.id
        JOIN users u ON m.created_by = u.id
        ORDER BY m.created_at DESC
        LIMIT 5
    ";
    $recentMeetings = $pdo->query($recentMeetingsQuery)->fetchAll();

} catch (Exception $e) {
    error_log('[Parent Meeting] Dashboard Fetch Error: ' . $e->getMessage());
    $totalRooms = 0;
    $submittedRooms = 0;
    $pendingRooms = 0;
    $totalNetworks = 0;
    $avgAttendance = 0;
    $chartLabels = ['ม.1', 'ม.2', 'ม.3'];
    $chartValues = [0, 0, 0];
    $recentMeetings = [];
}

require_once __DIR__ . '/components/layout_start.php';
?>

<!-- สถิติแบบ KPI Cards -->
<div class="row g-4 mb-4">
    <!-- การ์ด 1: ห้องเรียนทั้งหมด -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card stat-card h-100 bg-white" style="border-left-color: #0d6efd !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <h6 class="text-xs font-black uppercase text-muted tracking-wider mb-2">ห้องเรียนทั้งหมด</h6>
                    <h2 class="font-black text-dark mb-0"><?= $totalRooms ?> <span class="fs-6 text-muted">ห้อง</span></h2>
                </div>
                <div class="bg-primary-subtle text-primary rounded-4 p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-houses"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- การ์ด 2: ส่งรายงานแล้ว -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card stat-card h-100 bg-white" style="border-left-color: #198754 !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <h6 class="text-xs font-black uppercase text-muted tracking-wider mb-2">ส่งรายงานแล้ว</h6>
                    <h2 class="font-black text-success mb-0"><?= $submittedRooms ?> <span class="fs-6 text-muted">ห้อง</span></h2>
                </div>
                <div class="bg-success-subtle text-success rounded-4 p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-file-earmark-check"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- การ์ด 3: ค้างส่งรายงาน -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card stat-card h-100 bg-white" style="border-left-color: #dc3545 !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <h6 class="text-xs font-black uppercase text-muted tracking-wider mb-2">ค้างส่งรายงาน</h6>
                    <h2 class="font-black text-danger mb-0"><?= $pendingRooms ?> <span class="fs-6 text-muted">ห้อง</span></h2>
                </div>
                <div class="bg-danger-subtle text-danger rounded-4 p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-file-earmark-exclamation"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- การ์ด 4: เครือข่ายผู้ปกครอง -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card stat-card h-100 bg-white" style="border-left-color: #ffc107 !important;">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <h6 class="text-xs font-black uppercase text-muted tracking-wider mb-2">เครือข่ายผู้ปกครอง</h6>
                    <h2 class="font-black text-warning mb-0"><?= $totalNetworks ?> <span class="fs-6 text-muted">คน</span></h2>
                </div>
                <div class="bg-warning-subtle text-warning rounded-4 p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-diagram-3"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ส่วนแสดงผลหลัก (กราฟ และ รายการล่าสุด) -->
<div class="row g-4">
    <!-- คอลัมน์ซ้าย: กราฟสถิติ -->
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0">อัตราการเข้าร่วมประชุมของผู้ปกครอง (%)</h5>
                    <small class="text-muted">เปรียบเทียบร้อยละการเข้าร่วมประชุมเฉลี่ยแยกตามระดับชั้น</small>
                </div>
                <div class="badge bg-primary px-3 py-2 rounded-pill font-bold">
                    เฉลี่ยทั้งโรงเรียน <?= $avgAttendance ?> %
                </div>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                <div style="width: 100%; height: 320px; position: relative;">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- คอลัมน์ขวา: รายงานล่าสุด -->
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">รายงานการประชุมล่าสุด</h5>
                <small class="text-muted">รายงานที่ส่งเข้ามาล่าสุดในระบบ</small>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentMeetings)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-file-earmark-break fs-1 d-block mb-2"></i>
                        ไม่มีข้อมูลรายงานการประชุม
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentMeetings as $m): ?>
                            <div class="list-group-item p-3 align-items-center">
                                <div class="d-flex w-100 justify-content-between mb-1">
                                    <h6 class="mb-0 font-bold text-dark-blue">ห้อง ม.<?= esc($m['level'] . '/' . $m['room_name']) ?></h6>
                                    <small class="text-muted text-xs"><?= th_date($m['meeting_date']) ?></small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted text-xs"><i class="bi bi-person me-1"></i> ครู<?= esc($m['creator_name']) ?></span>
                                    <span class="badge bg-light text-dark text-xs border border-slate">เทอม <?= esc($m['semester'] . '/' . $m['academic_year']) ?></span>
                                </div>
                                <div class="mt-2 text-end">
                                    <a href="<?= pm_url('print_report.php?id=' . $m['id']) ?>" target="_blank" class="btn btn-xs btn-outline-primary py-1 px-2.5 rounded text-xs">
                                        <i class="bi bi-file-earmark-pdf"></i> ดูรายงาน
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- สคริปต์วาดกราฟ Chart.js -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    // ดึงข้อมูลมาจาก PHP
    const labels = <?= json_encode($chartLabels) ?>;
    const dataValues = <?= json_encode($chartValues) ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'ร้อยละการเข้าร่วมประชุม (%)',
                data: dataValues,
                backgroundColor: 'rgba(13, 110, 253, 0.75)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 1,
                borderRadius: 8,
                barPercentage: 0.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ` การเข้าร่วม: ${context.raw}%`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + "%";
                        }
                    },
                    grid: {
                        borderDash: [5, 5],
                        color: '#e2e8f0'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
