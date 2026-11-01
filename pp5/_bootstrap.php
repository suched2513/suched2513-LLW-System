<?php
/**
 * pp5/_bootstrap.php — ศูนย์กลางการเริ่มต้นระบบ ปพ.5 ออนไลน์ (PP5 Online Bootstrap)
 *
 * หน้าที่:
 * 1. เชื่อมต่อฐานข้อมูลและ Session ร่วมกับ LLW System
 * 2. โหลด Autoloader สำหรับคลาสใน `pp5/app/` (Policies, Requests, Services)
 * 3. ตรวจสอบและรัน Migration + Seeder อัตโนมัติหากตาราง `pp5_school_settings` ยังไม่ถูกสร้าง
 * 4. จัดเตรียม Context ของผู้ใช้ปัจจุบัน บทบาทหลายสิทธิ์ (Multi-role) และปีการศึกษา/ภาคเรียน
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// ─── PSR-4 Autoloader สำหรับ Namespace PP5\App\* ───
spl_autoload_register(function (string $class): void {
    $prefix  = 'PP5\\App\\';
    $baseDir = __DIR__ . '/app/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file          = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ─── Auto-Migration & Initial Seeder (รันครั้งเดียวเมื่อยังไม่มีตาราง pp5_*) ───
if (!function_exists('pp5_ensure_schema')) {
    function pp5_ensure_schema(PDO $pdo): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        try {
            $hasTable = (bool)$pdo->query("SHOW TABLES LIKE 'pp5_school_settings'")->fetchColumn();
            if ($hasTable && (int)$pdo->query("SELECT COUNT(*) FROM `pp5_departments`")->fetchColumn() === 0) {
                $seedFileInit = __DIR__ . '/../database/seeds/13_pp5_initial_seed.php';
                if (file_exists($seedFileInit)) {
                    $seedInit = require $seedFileInit;
                    if (is_array($seedInit) && isset($seedInit['run'])) {
                        $seedInit['run']($pdo);
                    }
                }
            }
            if (!$hasTable) {
                $migFile  = __DIR__ . '/../database/migrations/2026_11_01_000001_create_pp5_online_tables.php';
                $seedFile = __DIR__ . '/../database/seeds/13_pp5_initial_seed.php';

                if (file_exists($migFile)) {
                    $mig = require $migFile;
                    if (is_array($mig) && isset($mig['up'])) {
                        $mig['up']($pdo);
                        $pdo->exec("CREATE TABLE IF NOT EXISTS `_migrations` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `migration` VARCHAR(255) NOT NULL UNIQUE,
                            `batch` INT NOT NULL DEFAULT 1,
                            `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        $st = $pdo->prepare("INSERT IGNORE INTO `_migrations` (`migration`, `batch`) VALUES ('2026_11_01_000001_create_pp5_online_tables', 100)");
                        $st->execute();
                    }
                }

                if (file_exists($seedFile)) {
                    $seed = require $seedFile;
                    if (is_array($seed) && isset($seed['run'])) {
                        $seed['run']($pdo);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[PP5 Bootstrap] Schema ensure error: ' . $e->getMessage());
        }
    }
}

$pdo = getPdo();
pp5_ensure_schema($pdo);

// ─── Auth Guard มาตรฐาน LLW System (ใช้ $_SESSION['llw_role']) ───
if (!function_exists('pp5_require_login')) {
    function pp5_require_login(bool $isApi = false): void
    {
        if (!isset($_SESSION['llw_role'])) {
            if ($isApi) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/pp5/index.php');
            header('Location: /login.php?redirect=' . $redirect);
            exit();
        }
    }
}

// ─── ดึงปีการศึกษาและภาคเรียนที่กำลังเลือกทำงานในระบบ ปพ.5 ───
if (!function_exists('pp5_get_term_context')) {
    function pp5_get_term_context(PDO $pdo): array
    {
        $defaultYear = function_exists('llw_current_academic_year') ? llw_current_academic_year() : 2569;
        $defaultSem  = function_exists('llw_current_semester') ? llw_current_semester() : 2;

        if (isset($_GET['term_year']) && is_numeric($_GET['term_year'])) {
            $_SESSION['pp5_term_year'] = (int)$_GET['term_year'];
        }
        if (isset($_GET['term_sem']) && in_array((int)$_GET['term_sem'], [1, 2], true)) {
            $_SESSION['pp5_term_sem'] = (int)$_GET['term_sem'];
        }

        $year = (int)($_SESSION['pp5_term_year'] ?? $defaultYear);
        $sem  = (int)($_SESSION['pp5_term_sem'] ?? $defaultSem);

        $terms = [];
        try {
            $terms = $pdo->query("
                SELECT `academic_year`, `semester`, `start_date`, `end_date`, `is_active`
                FROM `llw_academic_terms`
                ORDER BY `academic_year` DESC, `semester` DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}

        if (empty($terms)) {
            $terms = [
                ['academic_year' => $defaultYear, 'semester' => $defaultSem, 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d'), 'is_active' => 1],
            ];
        }

        return [
            'year'  => $year,
            'sem'   => $sem,
            'label' => "ภาคเรียนที่ {$sem}/{$year}",
            'terms' => $terms,
        ];
    }
}

// ─── Helper แสดงวันที่แบบไทย (เช่น 15 พฤษภาคม 2568) ───
if (!function_exists('pp5_thai_date')) {
    function pp5_thai_date(?string $dateStr, bool $shortMonth = false): string
    {
        if (!$dateStr || $dateStr === '0000-00-00') {
            return '-';
        }
        $ts = strtotime($dateStr);
        if (!$ts) {
            return '-';
        }
        $monthsFull  = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        $monthsShort = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $d           = (int)date('j', $ts);
        $m           = (int)date('n', $ts);
        $y           = (int)date('Y', $ts) + 543;
        $mLabel      = $shortMonth ? ($monthsShort[$m] ?? '') : ($monthsFull[$m] ?? '');
        return "{$d} {$mLabel} {$y}";
    }
}

// ─── Helper แถบส่วนหัวระบบ ปพ.5 + ตัวสลับปีการศึกษา/ภาคเรียน ───
if (!function_exists('pp5_render_top_bar')) {
    function pp5_render_top_bar(PDO $pdo, array $termCtx, \PP5\App\Policies\Pp5Policy $policy, string $activeTab = 'dashboard'): string
    {
        $year       = (int)$termCtx['year'];
        $sem        = (int)$termCtx['sem'];
        $roleBadges = $policy->getRoleLabels();
        $unreadCnt  = $policy->getUnreadNotificationCount();

        ob_start();
        ?>
        <div class="bg-white rounded-2xl shadow-xl shadow-indigo-100/50 p-4 sm:p-5 border border-slate-100 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Left: System Identity & Active Role Badges -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-600 flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                        <i class="bi bi-journal-bookmark-fill text-xl"></i>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-base sm:text-lg font-black text-slate-800">ระบบ ปพ.5 ออนไลน์</span>
                            <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-xs font-black border border-indigo-200">
                                หลักสูตรแกนกลางฯ 2551
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            <span class="text-[11px] text-slate-400 font-semibold">บทบาทของคุณ:</span>
                            <?php foreach ($roleBadges as $rb): ?>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?= htmlspecialchars($rb['class'], ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi <?= htmlspecialchars($rb['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <?= htmlspecialchars($rb['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Right: Term Selector & Quick Navigation -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <form method="GET" class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-2xl px-3 py-1.5">
                        <?php foreach ($_GET as $k => $v): ?>
                            <?php if (!in_array($k, ['term_year', 'term_sem'], true) && is_scalar($v)): ?>
                                <input type="hidden" name="<?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <i class="bi bi-calendar3 text-indigo-600 text-sm"></i>
                        <span class="text-xs font-bold text-slate-500">ภาคเรียน:</span>
                        <select name="term_sem" onchange="this.form.submit()" class="bg-white border border-slate-200 rounded-xl px-2.5 py-1 text-xs font-black text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="1" <?= $sem === 1 ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                            <option value="2" <?= $sem === 2 ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                        </select>
                        <select name="term_year" onchange="this.form.submit()" class="bg-white border border-slate-200 rounded-xl px-2.5 py-1 text-xs font-black text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                            <?php
                            $yearsList = array_unique(array_merge([2569, 2568], array_map('intval', array_column($termCtx['terms'], 'academic_year'))));
                            rsort($yearsList);
                            foreach ($yearsList as $yOpt):
                            ?>
                                <option value="<?= $yOpt ?>" <?= $year === $yOpt ? 'selected' : '' ?>>ปีการศึกษา <?= $yOpt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- Navigation Pills -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <a href="/pp5/index.php" class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeTab === 'dashboard' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                            <i class="bi bi-grid-1x2-fill"></i> แดชบอร์ด
                            <?php if ($unreadCnt > 0): ?>
                                <span class="px-1.5 py-0.2 rounded-full bg-rose-500 text-white text-[10px] font-black"><?= $unreadCnt ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/pp5/batch_zip.php" class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeTab === 'batch_zip' ? 'bg-rose-600 text-white shadow-md shadow-rose-200' : 'bg-slate-100 text-slate-600 hover:bg-rose-50 hover:text-rose-600' ?>">
                            <i class="bi bi-file-earmark-zip-fill"></i> ดาวน์โหลด .ZIP
                        </a>
                        <?php if ($policy->isAdmin()): ?>
                            <a href="/pp5/master_data.php" class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeTab === 'master' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                                <i class="bi bi-database-fill-gear"></i> จัดการข้อมูลพื้นฐาน
                            </a>
                            <a href="/pp5/import_data.php" class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeTab === 'import' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600' ?>">
                                <i class="bi bi-file-earmark-spreadsheet-fill"></i> นำเข้า/ส่งออก Excel
                            </a>
                            <a href="/pp5/activity_logs.php" class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeTab === 'logs' ? 'bg-slate-800 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                                <i class="bi bi-shield-check"></i> ประวัติการแก้ไข
                            </a>
                            <a href="/pp5/unit_tests.php" class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeTab === 'tests' ? 'bg-violet-600 text-white shadow-md shadow-violet-200' : 'bg-slate-100 text-slate-600 hover:bg-violet-50 hover:text-violet-600' ?>">
                                <i class="bi bi-check2-circle"></i> Unit Tests
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// ─── Helper แถบเมนูย่อยของรายวิชา ปพ.5 (Course Header & Navigation Tabs) ───
if (!function_exists('pp5_render_course_nav')) {
    function pp5_render_course_nav(array $course, \PP5\App\Policies\Pp5Policy $policy, string $activeCourseTab = 'scores'): string
    {
        $courseId    = (int)$course['id'];
        $canEdit     = $policy->canEditCourse($course);
        $statusMap   = [
            'draft'           => ['label' => 'กำลังบันทึก (Draft)',      'class' => 'bg-slate-100 text-slate-700 border-slate-300',     'icon' => 'bi-pencil-square'],
            'submitted'       => ['label' => 'ส่งตรวจแล้ว',               'class' => 'bg-amber-50 text-amber-700 border-amber-200',       'icon' => 'bi-hourglass-split'],
            'head_approved'   => ['label' => 'หน.กลุ่มสาระอนุมัติ',        'class' => 'bg-cyan-50 text-cyan-700 border-cyan-200',         'icon' => 'bi-check2'],
            'deputy_approved' => ['label' => 'รองฯ วิชาการอนุมัติ',        'class' => 'bg-blue-50 text-blue-700 border-blue-200',         'icon' => 'bi-check2-all'],
            'approved'        => ['label' => 'ผอ.อนุมัติแล้ว',            'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'bi-patch-check-fill'],
            'locked'          => ['label' => 'ล็อกผลแล้ว',                'class' => 'bg-indigo-50 text-indigo-700 border-indigo-200',   'icon' => 'bi-lock-fill'],
        ];
        $st = $statusMap[$course['status']] ?? $statusMap['draft'];
        $typeMap = [
            'basic'      => ['label' => 'วิชาพื้นฐาน',      'class' => 'bg-indigo-50 text-indigo-700'],
            'additional' => ['label' => 'วิชาเพิ่มเติม',    'class' => 'bg-cyan-50 text-cyan-700'],
            'activity'   => ['label' => 'กิจกรรมพัฒนาผู้เรียน', 'class' => 'bg-amber-50 text-amber-700'],
        ];
        $tp = $typeMap[$course['subject_type']] ?? $typeMap['basic'];

        ob_start();
        ?>
        <div class="bg-white rounded-2xl shadow-xl shadow-indigo-100/50 p-5 border border-slate-100 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-slate-100">
                <div class="flex items-start gap-3.5">
                    <a href="/pp5/index.php" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 flex items-center justify-center transition-all shrink-0 mt-0.5" title="กลับหน้าแดชบอร์ด ปพ.5">
                        <i class="bi bi-arrow-left text-lg"></i>
                    </a>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-lg bg-indigo-600 text-white text-xs font-black tracking-wide">
                                <?= htmlspecialchars($course['subject_code'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <h2 class="text-base sm:text-lg font-black text-slate-800">
                                <?= htmlspecialchars($course['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                            </h2>
                            <span class="px-2.5 py-0.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 text-xs font-black">
                                ห้อง <?= htmlspecialchars($course['classroom_name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold <?= $tp['class'] ?>">
                                <?= $tp['label'] ?>
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 mt-1.5">
                            <span><i class="bi bi-person-workspace text-indigo-500"></i> ครูผู้สอน: <strong class="text-slate-700"><?= htmlspecialchars(trim(($course['teacher_prefix'] ?? '') . ($course['teacher_first_name'] ?? '') . ' ' . ($course['teacher_last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></strong></span>
                            <span><i class="bi bi-journal-check text-emerald-500"></i> <?= number_format((float)$course['credit'], 1) ?> หน่วยกิต (<?= (int)$course['total_hours'] ?> ชม./ภาค)</span>
                            <?php if ($course['subject_type'] !== 'activity'): ?>
                                <span><i class="bi bi-pie-chart-fill text-amber-500"></i> สัดส่วนคะแนน: <strong class="text-indigo-600"><?= (int)$course['ratio_collect'] ?> : <?= (int)$course['ratio_final'] ?></strong></span>
                            <?php else: ?>
                                <span><i class="bi bi-award-fill text-amber-500"></i> เกณฑ์ตัดสิน: <strong class="text-amber-700">ผ่าน (ผ) / ไม่ผ่าน (มผ)</strong></span>
                            <?php endif; ?>
                            <span><i class="bi bi-diagram-3 text-slate-400"></i> <?= htmlspecialchars($course['department_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1.5 rounded-full border text-xs font-bold flex items-center gap-1.5 <?= $st['class'] ?>">
                        <i class="bi <?= $st['icon'] ?>"></i> <?= $st['label'] ?>
                    </span>
                    <?php if (!$canEdit): ?>
                        <span class="px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold flex items-center gap-1">
                            <i class="bi bi-eye-fill"></i> โหมดอ่านอย่างเดียว (Read-only)
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Course Sub-navigation Tabs -->
            <div class="flex flex-wrap items-center gap-2 pt-3.5">
                <a href="/pp5/course_setup.php?course_id=<?= $courseId ?>"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeCourseTab === 'setup' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                    <i class="bi bi-sliders"></i> 1. ตั้งค่ารายวิชา & ชิ้นงาน
                </a>
                <a href="/pp5/course_attendance.php?course_id=<?= $courseId ?>"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeCourseTab === 'attendance' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600' ?>">
                    <i class="bi bi-calendar2-check-fill"></i> 2. บันทึกเวลาเรียน (เช็กชื่อ)
                </a>
                <a href="/pp5/course_scores.php?course_id=<?= $courseId ?>"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeCourseTab === 'scores' ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'bg-slate-100 text-slate-600 hover:bg-blue-50 hover:text-blue-600' ?>">
                    <i class="bi bi-table"></i> 3. กรอกคะแนน & ตัดเกรด (Excel Grid)
                </a>
                <a href="/pp5/course_assessments.php?course_id=<?= $courseId ?>"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeCourseTab === 'assessments' ? 'bg-violet-600 text-white shadow-md shadow-violet-200' : 'bg-slate-100 text-slate-600 hover:bg-violet-50 hover:text-violet-600' ?>">
                    <i class="bi bi-heart-pulse-fill"></i> 4. คุณลักษณะ / อ่านคิดเขียน / สมรรถนะ
                </a>
                <a href="/pp5/course_summary.php?course_id=<?= $courseId ?>"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $activeCourseTab === 'summary' ? 'bg-rose-600 text-white shadow-md shadow-rose-200' : 'bg-slate-100 text-slate-600 hover:bg-rose-50 hover:text-rose-600' ?>">
                    <i class="bi bi-clipboard2-data-fill"></i> 5. สรุปผล & พิมพ์เล่ม ปพ.5 (A4)
                </a>
                <a href="/pp5/course_pdf.php?course_id=<?= $courseId ?>" target="_blank"
                   class="ml-auto px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition-all flex items-center gap-1.5">
                    <i class="bi bi-printer-fill text-amber-400"></i> พิมพ์ ปพ.5 (ครบ 5 ส่วน)
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
