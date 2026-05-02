<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

try {
    $pdo = getPdo();
    
    // Fetch summary stats
    $today = date('Y-m-d');
    $stmtStats = $pdo->prepare("SELECT 
        COUNT(*) as total_records,
        AVG(score) as avg_score,
        (SELECT COUNT(*) FROM clean_areas) as total_areas
        FROM clean_scores 
        WHERE score_date = ?");
    $stmtStats->execute([$today]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Fetch areas with their last score today if exists
    $stmtAreas = $pdo->query("SELECT a.*, 
        (SELECT score FROM clean_scores s WHERE s.area_id = a.id AND s.score_date = CURDATE() ORDER BY s.created_at DESC LIMIT 1) as today_score,
        (SELECT class_name FROM clean_scores s WHERE s.area_id = a.id AND s.score_date = CURDATE() ORDER BY s.created_at DESC LIMIT 1) as recorded_class
        FROM clean_areas a 
        ORDER BY a.name ASC");
    $areas = $stmtAreas->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
    $areas = [];
    $stats = ['total_records' => 0, 'avg_score' => 0, 'total_areas' => 0];
}

$pageTitle = 'ระบบบันทึกความสะอาด';
$pageSubtitle = 'ประเมินความสะอาดและความเรียบร้อยของพื้นที่รับผิดชอบ';
$activeSystem = 'cleanliness';

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Stat Cards -->
    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-[2rem] p-6 text-white shadow-xl shadow-emerald-200/50 relative overflow-hidden group">
        <i class="bi bi-check2-circle absolute -right-4 -top-4 text-8xl opacity-10 group-hover:scale-110 transition-transform duration-500"></i>
        <p class="text-xs font-black uppercase tracking-wider opacity-80">ประเมินแล้ววันนี้</p>
        <div class="flex items-end gap-2 mt-2">
            <span class="text-5xl font-black"><?= $stats['total_records'] ?></span>
            <span class="text-lg font-bold mb-1 opacity-80">/ <?= $stats['total_areas'] ?> พื้นที่</span>
        </div>
    </div>

    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-[2rem] p-6 text-white shadow-xl shadow-blue-200/50 relative overflow-hidden group">
        <i class="bi bi-star absolute -right-4 -top-4 text-8xl opacity-10 group-hover:scale-110 transition-transform duration-500"></i>
        <p class="text-xs font-black uppercase tracking-wider opacity-80">คะแนนเฉลี่ยวันนี้</p>
        <div class="flex items-end gap-2 mt-2">
            <span class="text-5xl font-black"><?= number_format($stats['avg_score'] ?: 0, 1) ?></span>
            <span class="text-lg font-bold mb-1 opacity-80">คะแนน</span>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] p-6 shadow-xl shadow-slate-200/50 border border-slate-100 relative overflow-hidden group">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-xs font-black text-slate-400 uppercase tracking-wider">จัดการระบบ</p>
                <h3 class="text-xl font-black text-slate-800 mt-1">ตั้งค่าพื้นที่</h3>
            </div>
            <a href="manage_areas.php" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:bg-emerald-500 hover:text-white transition-all shadow-sm">
                <i class="bi bi-gear-fill"></i>
            </a>
        </div>
        <p class="text-sm text-slate-500 mt-4 font-medium leading-relaxed">กำหนดขอบเขตพื้นที่และมอบหมายห้องเรียนที่รับผิดชอบ</p>
    </div>
</div>

<!-- Area List -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-2xl shadow-slate-200/40 border border-white p-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl font-black text-slate-800 flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                พื้นที่ประเมินผล
            </h2>
            <p class="text-slate-500 mt-1 font-medium pl-13">เลือกพื้นที่เพื่อเริ่มบันทึกคะแนนประจำวัน</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-4 py-2 rounded-full bg-slate-100 text-slate-500 text-xs font-black uppercase tracking-wider">
                วันที่: <?= date('d/m/Y') ?>
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($areas as $area): ?>
            <div class="group bg-white rounded-3xl border border-slate-100 p-6 hover:border-emerald-200 hover:shadow-xl hover:shadow-emerald-100/30 transition-all duration-300 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <span class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-400 group-hover:bg-emerald-50 group-hover:text-emerald-500 flex items-center justify-center transition-all duration-300">
                            <i class="bi bi-map text-xl"></i>
                        </span>
                        <?php if ($area['today_score']): ?>
                            <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-black uppercase tracking-wider">
                                ประเมินแล้ว: <?= $area['today_score'] ?>
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full bg-rose-50 text-rose-500 text-xs font-black uppercase tracking-wider">
                                รอการประเมิน
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="text-lg font-black text-slate-800 group-hover:text-emerald-600 transition-colors"><?= htmlspecialchars($area['name']) ?></h3>
                    <p class="text-xs text-slate-400 mt-1 font-medium italic">ผู้รับผิดชอบ: <?= htmlspecialchars($area['assigned_class'] ?: 'ยังไม่มอบหมาย') ?></p>
                    <p class="text-sm text-slate-500 mt-3 line-clamp-2 leading-relaxed"><?= htmlspecialchars($area['description'] ?: 'ไม่มีคำอธิบายเพิ่มเติม') ?></p>
                </div>

                <div class="mt-6 pt-6 border-t border-slate-50 flex items-center justify-between">
                    <div class="flex -space-x-2">
                         <!-- Placeholder for avatars if needed -->
                    </div>
                    <a href="record.php?id=<?= $area['id'] ?>" class="flex items-center gap-2 text-emerald-600 font-black text-sm hover:gap-3 transition-all">
                        <span>ลงคะแนน</span>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
