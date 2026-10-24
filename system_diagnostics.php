<?php
/**
 * system_diagnostics.php — ศูนย์ตรวจสอบความเสถียรและบันทึกข้อผิดพลาดระบบ (Super Admin)
 */
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    header('Location: /login.php');
    exit();
}

$pdo = getPdo();
$flashMsg = '';
$flashType = 'success';

// ─── Handle One-Click Maintenance Actions (POST + CSRF) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();
    $action = $_POST['action'];

    try {
        if ($action === 'run_stability_migration') {
            $targetMigrations = [
                '2026_10_20_233000_add_semester_support',
                '2026_10_24_233000_system_stability_and_performance',
            ];
            $ranCount = 0;
            $nextBatch = 1;
            try {
                $nextBatch = (int)$pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM _migrations")->fetchColumn();
            } catch (Exception $ignore) {}

            foreach ($targetMigrations as $mName) {
                $mFile = __DIR__ . '/database/migrations/' . $mName . '.php';
                if (file_exists($mFile)) {
                    $mig = require $mFile;
                    if (is_array($mig) && isset($mig['up']) && is_callable($mig['up'])) {
                        $mig['up']($pdo);
                        try {
                            $stmtM = $pdo->prepare("INSERT IGNORE INTO _migrations (migration, batch) VALUES (?, ?)");
                            $stmtM->execute([$mName, $nextBatch]);
                        } catch (Exception $ignore) {}
                        $ranCount++;
                    }
                }
            }
            $flashMsg = "รันชุดอัปเกรดความเสถียรและ Indexes สำเร็จ ({$ranCount} รายการ)";
        } elseif ($action === 'standardize_ids') {
            $tables = [
                'att_students', 'att_attendance', 'assembly_students', 'assembly_attendance',
                'assembly_checkout', 'beh_students', 'beh_records', 'cb_students',
                'pm_registrations', 'health_records', 'student_leave_requests',
                'lms_exam_results', 'lms_student_exercises', 'club_members', 'bus_students'
            ];
            $totalUpdated = 0;
            foreach ($tables as $tb) {
                try {
                    if (!$pdo->query("SHOW TABLES LIKE " . $pdo->quote($tb))->fetchColumn()) continue;
                    $affected = $pdo->exec("
                        UPDATE IGNORE `{$tb}`
                        SET `student_id` = LPAD(TRIM(`student_id`), 5, '0')
                        WHERE TRIM(`student_id`) REGEXP '^[0-9]+$'
                          AND CHAR_LENGTH(TRIM(`student_id`)) > 0
                          AND CHAR_LENGTH(TRIM(`student_id`)) < 5
                    ");
                    $totalUpdated += (int)$affected;
                } catch (Exception $ignore) {}
            }
            $flashMsg = "จัดมาตรฐานรหัสนักเรียน 5 หลักสำเร็จ (ปรับปรุง {$totalUpdated} แถว)";
        } elseif ($action === 'clear_error_logs') {
            if ($pdo->query("SHOW TABLES LIKE 'llw_system_error_logs'")->fetchColumn()) {
                $pdo->exec("DELETE FROM llw_system_error_logs");
            }
            $flashMsg = 'ล้างประวัติบันทึกข้อผิดพลาด (Error Logs) เรียบร้อยแล้ว';
        } elseif ($action === 'clear_opcache') {
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $flashMsg = 'ล้าง PHP OPcache สำเร็จแล้ว โค้ดล่าสุดพร้อมทำงานทันที';
            } else {
                $flashType = 'warning';
                $flashMsg = 'เซิร์ฟเวอร์นี้ไม่ได้เปิดใช้งานฟังก์ชัน opcache_reset()';
            }
        } elseif ($action === 'test_error_log') {
            llw_log_error(new RuntimeException('ทดสอบระบบบันทึกข้อผิดพลาดอัตโนมัติ (Diagnostic Test)'), 'system_diagnostics/test');
            $flashMsg = 'ส่งรายการทดสอบเข้าสู่ระบบ Error Telemetry สำเร็จ';
        }
    } catch (Exception $e) {
        $flashType = 'error';
        $flashMsg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

// ─── System Health Checks ──────────────────────────────────────────────
$curYear = llw_current_academic_year($pdo);
$curSem  = llw_current_semester($pdo);

// 1. Check Error Logs Table & Counts
$hasErrorTable = false;
$errorCount24h = 0;
$errorCountTotal = 0;
$errorLogs = [];
$filterContext = trim($_GET['ctx'] ?? '');

try {
    $hasErrorTable = (bool)$pdo->query("SHOW TABLES LIKE 'llw_system_error_logs'")->fetchColumn();
    if ($hasErrorTable) {
        $errorCount24h = (int)$pdo->query("SELECT COUNT(*) FROM llw_system_error_logs WHERE created_at >= NOW() - INTERVAL 24 HOUR")->fetchColumn();
        $errorCountTotal = (int)$pdo->query("SELECT COUNT(*) FROM llw_system_error_logs")->fetchColumn();

        if ($filterContext !== '') {
            $stmtLogs = $pdo->prepare("SELECT * FROM llw_system_error_logs WHERE context LIKE ? ORDER BY id DESC LIMIT 100");
            $stmtLogs->execute(["%{$filterContext}%"]);
        } else {
            $stmtLogs = $pdo->query("SELECT * FROM llw_system_error_logs ORDER BY id DESC LIMIT 100");
        }
        $errorLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('[Diagnostics] ' . $e->getMessage());
}

// 2. Check Unpadded Student IDs across core tables
$auditTables = [
    'att_students'        => 'นักเรียนกลาง (att_students)',
    'assembly_students'   => 'นักเรียนเข้าแถว (assembly_students)',
    'beh_students'        => 'นักเรียนพฤติกรรม (beh_students)',
    'beh_records'         => 'ประวัติพฤติกรรม (beh_records)',
    'att_attendance'      => 'เวลาเรียนรายวิชา (att_attendance)',
    'assembly_attendance' => 'เวลาเข้าแถวหน้าเสาธง (assembly_attendance)',
];
$unpaddedSummary = [];
$totalUnpadded = 0;

foreach ($auditTables as $tb => $label) {
    try {
        if (!$pdo->query("SHOW TABLES LIKE " . $pdo->quote($tb))->fetchColumn()) continue;
        $cnt = (int)$pdo->query("
            SELECT COUNT(*) FROM `{$tb}`
            WHERE TRIM(`student_id`) REGEXP '^[0-9]+$'
              AND CHAR_LENGTH(TRIM(`student_id`)) > 0
              AND CHAR_LENGTH(TRIM(`student_id`)) < 5
        ")->fetchColumn();
        $totalRows = (int)$pdo->query("SELECT COUNT(*) FROM `{$tb}`")->fetchColumn();
        $unpaddedSummary[] = [
            'table'    => $tb,
            'label'    => $label,
            'unpadded' => $cnt,
            'total'    => $totalRows,
        ];
        $totalUnpadded += $cnt;
    } catch (Exception $ignore) {}
}

// 3. Check Composite Performance Indexes
$expectedIndexes = [
    ['table' => 'assembly_attendance', 'index' => 'idx_asma_class_date',   'desc' => 'ค้นหาเช็คชื่อเข้าแถวตามห้อง+วันที่'],
    ['table' => 'assembly_attendance', 'index' => 'idx_asma_student_date', 'desc' => 'ค้นหาเช็คชื่อเข้าแถวตามรหัสนักเรียน+วันที่'],
    ['table' => 'beh_records',         'index' => 'idx_beh_student_date',  'desc' => 'ค้นหาคะแนนพฤติกรรมรายบุคคล'],
    ['table' => 'beh_records',         'index' => 'idx_beh_date_type',     'desc' => 'สรุปพฤติกรรมตามช่วงเวลา/ภาคเรียน'],
    ['table' => 'att_attendance',      'index' => 'idx_att_subj_date',     'desc' => 'ค้นหาเช็คชื่อรายวิชาตามวันที่'],
    ['table' => 'att_students',        'index' => 'idx_atts_class_year',   'desc' => 'ค้นหานักเรียนตามห้อง+ปีการศึกษา'],
];
$indexAudit = [];
$installedIndexCount = 0;
foreach ($expectedIndexes as $item) {
    $installed = false;
    try {
        if ($pdo->query("SHOW TABLES LIKE " . $pdo->quote($item['table']))->fetchColumn()) {
            $installed = (bool)$pdo->query("SHOW INDEX FROM `{$item['table']}` WHERE Key_name = " . $pdo->quote($item['index']))->fetch();
        }
    } catch (Exception $ignore) {}
    if ($installed) $installedIndexCount++;
    $indexAudit[] = array_merge($item, ['installed' => $installed]);
}

$pageTitle = 'ศูนย์ตรวจสอบความเสถียรและประสิทธิภาพระบบ';
$pageSubtitle = 'System Health, 5-Digit ID Compliance, Composite Indexes & Error Telemetry';
$activeSystem = 'portal';
require_once __DIR__ . '/components/layout_start.php';
?>

<div class="max-w-7xl mx-auto space-y-6 pb-12">

    <?php if ($flashMsg !== ''): ?>
    <div class="rounded-2xl p-4 font-bold flex items-center gap-3 shadow-lg <?= $flashType === 'error' ? 'bg-rose-500 text-white shadow-rose-200/50' : ($flashType === 'warning' ? 'bg-amber-500 text-white shadow-amber-200/50' : 'bg-emerald-500 text-white shadow-emerald-200/50') ?>">
        <i class="bi <?= $flashType === 'error' ? 'bi-exclamation-octagon-fill' : 'bi-check-circle-fill' ?> text-xl"></i>
        <span><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-blue-950 to-indigo-950 rounded-[2rem] p-6 sm:p-8 text-white shadow-xl flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/20 border border-blue-400/30 text-blue-300 text-xs font-bold mb-3">
                <i class="bi bi-shield-check"></i> SYSTEM DIAGNOSTICS & TELEMETRY
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">ศูนย์ควบคุมความเสถียรและประสิทธิภาพระบบ</h1>
            <p class="text-slate-300 text-sm mt-1">ตรวจสอบมาตรฐานรหัสนักเรียน 5 หลัก, ดัชนีฐานข้อมูล (Composite Indexes), และบันทึกข้อผิดพลาด (Error Logs)</p>
        </div>

        <div class="flex flex-wrap gap-2.5">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="run_stability_migration">
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-900/40 transition-all flex items-center gap-2">
                    <i class="bi bi-lightning-charge-fill"></i> อัปเกรด DB & Indexes
                </button>
            </form>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="standardize_ids">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-emerald-900/40 transition-all flex items-center gap-2">
                    <i class="bi bi-123"></i> จัดมาตรฐานรหัส 5 หลัก
                </button>
            </form>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear_opcache">
                <button type="submit" class="bg-white/10 hover:bg-white/20 text-white border border-white/15 px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                    <i class="bi bi-arrow-repeat"></i> ล้าง OPcache
                </button>
            </form>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-5 text-white shadow-xl shadow-blue-200/50">
            <p class="text-[11px] font-bold uppercase tracking-wider opacity-80">ปีการศึกษา / ภาคเรียนปัจจุบัน</p>
            <p class="text-3xl font-black mt-2"><?= (int)$curSem ?> / <?= (int)$curYear ?></p>
            <p class="text-xs opacity-80 mt-2 flex items-center gap-1">
                <i class="bi bi-calendar3"></i> ช่วงวันที่: <?= htmlspecialchars( implode(' ถึง ', llw_get_semester_date_range($curYear, $curSem, $pdo)), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <div class="bg-gradient-to-br <?= $totalUnpadded === 0 ? 'from-emerald-500 to-teal-600 shadow-emerald-200/50' : 'from-amber-500 to-orange-600 shadow-amber-200/50' ?> rounded-2xl p-5 text-white shadow-xl">
            <p class="text-[11px] font-bold uppercase tracking-wider opacity-80">มาตรฐานรหัสนักเรียน 5 หลัก</p>
            <p class="text-3xl font-black mt-2"><?= $totalUnpadded === 0 ? '100% ปกติ' : number_format($totalUnpadded) . ' รายการ' ?></p>
            <p class="text-xs opacity-80 mt-2">
                <?= $totalUnpadded === 0 ? 'รหัสนักเรียนทุกตารางเป็น 5 หลักตรงกัน' : 'พบรหัสที่ยังไม่ได้เติม 0 นำหน้า กดปุ่มจัดมาตรฐานด้านบน' ?>
            </p>
        </div>

        <div class="bg-gradient-to-br <?= $installedIndexCount === count($expectedIndexes) ? 'from-indigo-500 to-purple-600 shadow-indigo-200/50' : 'from-amber-500 to-orange-600 shadow-amber-200/50' ?> rounded-2xl p-5 text-white shadow-xl">
            <p class="text-[11px] font-bold uppercase tracking-wider opacity-80">ดัชนีความเร็ว (Composite Indexes)</p>
            <p class="text-3xl font-black mt-2"><?= $installedIndexCount ?> / <?= count($expectedIndexes) ?></p>
            <p class="text-xs opacity-80 mt-2">
                <?= $installedIndexCount === count($expectedIndexes) ? 'ติดตั้งครบทุกตารางหลักแล้ว' : 'กดปุ่ม "อัปเกรด DB & Indexes" เพื่อติดตั้งให้ครบ' ?>
            </p>
        </div>

        <div class="bg-gradient-to-br <?= $errorCount24h === 0 ? 'from-slate-700 to-slate-900 shadow-slate-200/50' : 'from-rose-500 to-pink-600 shadow-rose-200/50' ?> rounded-2xl p-5 text-white shadow-xl">
            <p class="text-[11px] font-bold uppercase tracking-wider opacity-80">ข้อผิดพลาดในระบบ (24 ชม. ล่าสุด)</p>
            <p class="text-3xl font-black mt-2"><?= number_format($errorCount24h) ?> <span class="text-sm font-normal opacity-80">รายการ</span></p>
            <p class="text-xs opacity-80 mt-2">สะสมทั้งหมด: <?= number_format($errorCountTotal) ?> รายการ</p>
        </div>
    </div>

    <!-- Audit Grid: Student ID Compliance & Indexes -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Student ID Audit -->
        <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black text-slate-800"><i class="bi bi-person-badge text-blue-600 mr-2"></i>ตรวจสอบรหัสนักเรียน 5 หลัก (LPAD)</h3>
                    <p class="text-xs text-slate-500">ป้องกันปัญหา JOIN ข้อมูลข้ามโมดูลไม่เจอ</p>
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($unpaddedSummary as $row): ?>
                <div class="px-6 py-3.5 flex items-center justify-between text-sm">
                    <div>
                        <p class="font-bold text-slate-700"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-400">จำนวนทั้งหมด <?= number_format($row['total']) ?> แถว</p>
                    </div>
                    <?php if ($row['unpadded'] === 0): ?>
                        <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold">
                            <i class="bi bi-check-circle-fill mr-1"></i>สมบูรณ์
                        </span>
                    <?php else: ?>
                        <span class="px-3 py-1 rounded-full bg-amber-50 text-amber-600 text-xs font-bold">
                            <i class="bi bi-exclamation-triangle-fill mr-1"></i>ไม่ครบ 5 หลัก <?= number_format($row['unpadded']) ?> แถว
                        </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Composite Index Audit -->
        <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black text-slate-800"><i class="bi bi-speedometer2 text-indigo-600 mr-2"></i>สถานะดัชนีเร่งความเร็ว (Database Indexes)</h3>
                    <p class="text-xs text-slate-500">ลดเวลา Query หน้าเช็คชื่อและรายงานพฤติกรรม</p>
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($indexAudit as $idx): ?>
                <div class="px-6 py-3.5 flex items-center justify-between text-sm">
                    <div>
                        <p class="font-bold text-slate-700 font-mono text-xs"><?= htmlspecialchars($idx['table'] . '.' . $idx['index'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars($idx['desc'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php if ($idx['installed']): ?>
                        <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold">
                            <i class="bi bi-check-circle-fill mr-1"></i>พร้อมใช้งาน
                        </span>
                    <?php else: ?>
                        <span class="px-3 py-1 rounded-full bg-rose-50 text-rose-600 text-xs font-bold">
                            <i class="bi bi-x-circle-fill mr-1"></i>ยังไม่ติดตั้ง
                        </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Error Telemetry Table -->
    <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-base font-black text-slate-800"><i class="bi bi-bug text-rose-500 mr-2"></i>บันทึกข้อผิดพลาดระบบ (Central Error Telemetry)</h3>
                <p class="text-xs text-slate-500">แสดงรายการ Error ล่าสุดที่ถูกดักจับจาก API และหน้าเว็บ (สูงสุด 100 รายการล่าสุด)</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="test_error_log">
                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold transition-all">
                        <i class="bi bi-send-check mr-1"></i>ทดสอบส่ง Log
                    </button>
                </form>
                <?php if ($errorCountTotal > 0): ?>
                <form method="POST" class="inline" onsubmit="return confirm('ยืนยันการล้างประวัติ Error Logs ทั้งหมด?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_error_logs">
                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold transition-all">
                        <i class="bi bi-trash mr-1"></i>ล้างประวัติทั้งหมด
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$hasErrorTable): ?>
            <div class="p-10 text-center text-slate-500">
                <i class="bi bi-database-exclamation text-4xl text-amber-500 block mb-2"></i>
                <p class="font-bold">ยังไม่ได้สร้างตาราง <code class="text-xs bg-slate-100 px-2 py-0.5 rounded">llw_system_error_logs</code></p>
                <p class="text-xs mt-1">กรุณากดปุ่ม <strong>"อัปเกรด DB & Indexes"</strong> ด้านบนขวาเพื่อเปิดใช้งานระบบบันทึกข้อผิดพลาด</p>
            </div>
        <?php elseif (empty($errorLogs)): ?>
            <div class="p-12 text-center text-slate-400">
                <i class="bi bi-shield-check text-5xl text-emerald-500 block mb-2"></i>
                <p class="font-bold text-slate-700">ไม่พบข้อผิดพลาดในระบบ</p>
                <p class="text-xs mt-1">ระบบทำงานได้ตามปกติ ไม่มีรายงาน Exception ใหม่</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="text-xs font-bold text-slate-400 uppercase tracking-wider px-5 py-3.5">วัน-เวลา</th>
                            <th class="text-xs font-bold text-slate-400 uppercase tracking-wider px-5 py-3.5">โมดูล / Endpoint</th>
                            <th class="text-xs font-bold text-slate-400 uppercase tracking-wider px-5 py-3.5">ผู้ใช้งาน</th>
                            <th class="text-xs font-bold text-slate-400 uppercase tracking-wider px-5 py-3.5">รายละเอียดข้อผิดพลาด</th>
                            <th class="text-xs font-bold text-slate-400 uppercase tracking-wider px-5 py-3.5">ไฟล์:บรรทัด</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php foreach ($errorLogs as $log): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5 whitespace-nowrap text-xs text-slate-500 font-mono">
                                <?= htmlspecialchars($log['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-600 text-xs font-bold">
                                    <?= htmlspecialchars($log['context'] ?: 'system', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <div class="text-[11px] text-slate-400 font-mono mt-1 truncate max-w-xs">
                                    <?= htmlspecialchars(($log['method'] ?? '') . ' ' . ($log['endpoint'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-xs">
                                <div class="font-bold text-slate-700"><?= htmlspecialchars($log['username'] ?: 'Guest/System', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-slate-400"><?= htmlspecialchars($log['role'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-rose-600 font-medium max-w-md break-words">
                                <?= htmlspecialchars($log['error_message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-xs font-mono text-slate-500">
                                <?= htmlspecialchars($log['file_line'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
