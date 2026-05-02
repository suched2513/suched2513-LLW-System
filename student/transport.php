<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_guard.php';

$pdo  = getPdo();
$uid  = (int)$_SESSION['student_uid'];
$code = $_SESSION['student_code'];
$name = $_SESSION['student_name'];

if (!function_exists('busGetSemester')) {
    function busGetSemester(): string {
        $m = (int)date('n'); $y = (int)date('Y') + 543;
        return $y . '-' . ($m >= 5 && $m <= 10 ? 1 : 2);
    }
}
$semester = busGetSemester();

$msg = ''; $err = '';

// Fetch current survey record
$survey = null;
try {
    $stmt = $pdo->prepare("SELECT st.*, br.route_name, br.route_code FROM student_transport st LEFT JOIN bus_routes br ON br.id = st.route_id WHERE st.att_student_id = ? AND st.semester = ? LIMIT 1");
    $stmt->execute([$uid, $semester]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); }

// Fetch available bus routes
$routes = [];
try {
    $stmt = $pdo->prepare("SELECT id, route_code, route_name FROM bus_routes WHERE is_active = 1 ORDER BY route_code");
    $stmt->execute();
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); }

// Handle POST (submit or update survey)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Block edit if already confirmed by admin
    if ($survey && $survey['status'] === 'confirmed') {
        $err = 'ข้อมูลได้รับการยืนยันจากโรงเรียนแล้ว ไม่สามารถแก้ไขได้';
    } else {
        $type    = $_POST['transport_type'] ?? '';
        $routeId = ($type === 'school_bus') ? (int)($_POST['route_id'] ?? 0) : null;
        $village = trim($_POST['home_village'] ?? '');
        $note    = trim($_POST['note'] ?? '');

        $validTypes = ['school_bus','motorcycle','bicycle','walk','private_car','other'];
        if (!in_array($type, $validTypes, true)) {
            $err = 'กรุณาเลือกประเภทการเดินทาง';
        } elseif ($type === 'school_bus' && (!$routeId || empty($routes))) {
            $err = 'กรุณาเลือกสายรถรับส่ง';
        } else {
            try {
                if ($survey) {
                    // Update existing
                    $pdo->prepare("UPDATE student_transport SET transport_type=?, route_id=?, home_village=?, note=?, updated_at=NOW() WHERE id=?")
                        ->execute([$type, $routeId, $village ?: null, $note ?: null, $survey['id']]);
                } else {
                    // Insert new
                    $pdo->prepare("INSERT INTO student_transport (att_student_id, semester, transport_type, route_id, home_village, note) VALUES (?,?,?,?,?,?)")
                        ->execute([$uid, $semester, $type, $routeId, $village ?: null, $note ?: null]);
                }
                header('Location: /student/dashboard.php?msg=' . urlencode('บันทึกข้อมูลการเดินทางเรียบร้อยแล้ว') . '&t=ok');
                exit();
            } catch (Exception $e) {
                error_log($e->getMessage());
                $err = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            }
        }
    }
    // Refresh survey after POST attempt
    if (!$err) {
        try {
            $stmt = $pdo->prepare("SELECT st.*, br.route_name, br.route_code FROM student_transport st LEFT JOIN bus_routes br ON br.id = st.route_id WHERE st.att_student_id = ? AND st.semester = ? LIMIT 1");
            $stmt->execute([$uid, $semester]);
            $survey = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }
}

$typeOptions = [
    'school_bus'  => ['label'=>'ใช้รถรับส่งโรงเรียน',     'icon'=>'bi-bus-front-fill',    'color'=>'orange'],
    'motorcycle'  => ['label'=>'ขี่มอเตอร์ไซค์',           'icon'=>'bi-bicycle',           'color'=>'amber'],
    'bicycle'     => ['label'=>'ขี่จักรยาน',                'icon'=>'bi-bicycle',           'color'=>'lime'],
    'walk'        => ['label'=>'เดินมาโรงเรียน',            'icon'=>'bi-person-walking',    'color'=>'emerald'],
    'private_car' => ['label'=>'รถส่วนตัว/ผู้ปกครองรับส่ง','icon'=>'bi-car-front-fill',    'color'=>'blue'],
    'other'       => ['label'=>'อื่นๆ',                     'icon'=>'bi-three-dots-vertical','color'=>'slate'],
];
$selected = $_POST['transport_type'] ?? ($survey['transport_type'] ?? '');
$confirmed = $survey && $survey['status'] === 'confirmed';
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>แบบสำรวจการเดินทาง | นักเรียน LLW</title>
<meta name="theme-color" content="#f97316">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { font-family:'Prompt',sans-serif; }
.type-card input[type=radio]:checked ~ .card-inner { border-color:#f97316; background:#fff7ed; }
.type-card input[type=radio]:checked ~ .card-inner .check-icon { display:flex; }
.check-icon { display:none; }
</style>
</head>
<body class="bg-slate-100 min-h-screen" style="padding-bottom:env(safe-area-inset-bottom)">

<!-- Header -->
<header class="bg-gradient-to-r from-orange-500 to-amber-500 text-white sticky top-0 z-50 shadow-md"
        style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto flex items-center gap-3 px-4 py-3">
        <a href="/student/dashboard.php"
           class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/30 flex-shrink-0">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <div class="font-black text-sm leading-tight">แบบสำรวจการเดินทาง</div>
            <div class="text-orange-100 text-[10px] font-bold">ภาคเรียน <?= htmlspecialchars($semester) ?></div>
        </div>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 py-5 space-y-4">

<?php if ($err): ?>
<div class="bg-rose-50 border border-rose-200 rounded-2xl px-4 py-3 text-rose-700 text-sm font-bold flex items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i> <?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<!-- Info Card -->
<div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100">
    <div class="flex items-center gap-3 pb-4 border-b border-slate-50">
        <div class="w-12 h-12 bg-orange-50 rounded-2xl flex items-center justify-center flex-shrink-0">
            <i class="bi bi-signpost-fill text-orange-500 text-xl"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-black text-slate-700 truncate"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-slate-400 text-xs">รหัส <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($_SESSION['student_class'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php if ($survey): ?>
        <span class="px-3 py-1 rounded-full text-[10px] font-black
                     <?= $confirmed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
            <?= $confirmed ? 'ยืนยันแล้ว' : 'รอยืนยัน' ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if ($confirmed): ?>
    <!-- ── Confirmed: Show read-only view ─────────────────────────── -->
    <div class="pt-4 space-y-3">
        <p class="text-xs font-black text-slate-400 uppercase tracking-widest">ข้อมูลที่บันทึก</p>
        <?php $tOpt = $typeOptions[$survey['transport_type']] ?? ['label'=>$survey['transport_type'],'icon'=>'bi-question','color'=>'slate']; ?>
        <div class="flex items-center gap-3 bg-<?= $tOpt['color'] ?>-50 rounded-2xl px-4 py-3">
            <i class="bi <?= $tOpt['icon'] ?> text-<?= $tOpt['color'] ?>-500 text-xl flex-shrink-0"></i>
            <div>
                <p class="font-black text-slate-700 text-sm"><?= htmlspecialchars($tOpt['label']) ?></p>
                <?php if ($survey['route_name']): ?>
                <p class="text-slate-500 text-xs">สาย <?= htmlspecialchars($survey['route_code'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($survey['route_name'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($survey['home_village']): ?>
        <div class="flex items-start gap-2 text-sm text-slate-600">
            <i class="bi bi-geo-alt-fill text-slate-400 mt-0.5"></i>
            <span><?= htmlspecialchars($survey['home_village'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>
        <div class="bg-emerald-50 border border-emerald-100 rounded-2xl px-4 py-3 text-emerald-700 text-xs font-bold flex items-center gap-2">
            <i class="bi bi-check-circle-fill flex-shrink-0"></i>
            โรงเรียนยืนยันข้อมูลของคุณแล้ว ไม่สามารถแก้ไขได้ หากต้องการเปลี่ยนแปลงติดต่อครูที่ปรึกษา
        </div>
    </div>

    <?php else: ?>
    <!-- ── Form ───────────────────────────────────────────────────── -->
    <form method="POST" class="pt-4 space-y-5">
        <?= csrf_field() ?>

        <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">
                คุณเดินทางมาโรงเรียนด้วยอะไร? <span class="text-rose-500">*</span>
            </p>
            <div class="space-y-2">
                <?php foreach ($typeOptions as $val => $opt): ?>
                <label class="type-card block cursor-pointer">
                    <input type="radio" name="transport_type" value="<?= $val ?>"
                           class="sr-only" <?= $selected === $val ? 'checked' : '' ?>
                           onchange="toggleRouteSelect()">
                    <div class="card-inner relative flex items-center gap-3 border-2 border-slate-200 bg-white rounded-2xl px-4 py-3.5 transition-all">
                        <div class="w-10 h-10 bg-<?= $opt['color'] ?>-50 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="bi <?= $opt['icon'] ?> text-<?= $opt['color'] ?>-500 text-lg"></i>
                        </div>
                        <span class="font-bold text-slate-700 text-sm flex-1"><?= htmlspecialchars($opt['label']) ?></span>
                        <div class="check-icon w-6 h-6 bg-orange-500 rounded-full items-center justify-center flex-shrink-0">
                            <i class="bi bi-check text-white text-xs font-black"></i>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Route select (only if school_bus) -->
        <div id="routeSection" class="<?= $selected === 'school_bus' ? '' : 'hidden' ?> space-y-3">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">
                    เลือกสายรถรับส่ง <span class="text-rose-500">*</span>
                </label>
                <?php if (empty($routes)): ?>
                <p class="text-slate-400 text-sm font-bold px-1">ยังไม่มีสายรถที่เปิดรับ กรุณาติดต่อครู</p>
                <?php else: ?>
                <select name="route_id"
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-orange-400 focus:border-orange-400 outline-none">
                    <option value="">-- เลือกสายรถ --</option>
                    <?php foreach ($routes as $rt): ?>
                    <option value="<?= (int)$rt['id'] ?>"
                            <?= ((int)($_POST['route_id'] ?? $survey['route_id'] ?? 0)) === (int)$rt['id'] ? 'selected' : '' ?>>
                        สาย <?= htmlspecialchars($rt['route_code'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($rt['route_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
        </div>

        <!-- Village / area -->
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">
                หมู่บ้าน / ตำบล ที่อยู่ (ไม่บังคับ)
            </label>
            <input type="text" name="home_village" maxlength="200"
                   placeholder="เช่น บ้านละลม หมู่ 3 ต.ละลม"
                   value="<?= htmlspecialchars($_POST['home_village'] ?? ($survey['home_village'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-orange-400 outline-none transition-all">
        </div>

        <!-- Note -->
        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">หมายเหตุ (ไม่บังคับ)</label>
            <textarea name="note" rows="2" maxlength="500"
                      placeholder="ข้อมูลเพิ่มเติม..."
                      class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm resize-none focus:ring-2 focus:ring-orange-400 outline-none transition-all"><?= htmlspecialchars($_POST['note'] ?? ($survey['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <button type="submit"
                class="w-full py-4 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-2xl font-black text-sm shadow-xl shadow-orange-200/60 active:scale-95 transition-transform flex items-center justify-center gap-2">
            <i class="bi bi-check-circle-fill text-base"></i>
            <?= $survey ? 'อัปเดตข้อมูล' : 'บันทึกการเดินทาง' ?>
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- Info box -->
<div class="bg-blue-50 border border-blue-100 rounded-2xl px-4 py-3.5 flex items-start gap-3">
    <i class="bi bi-info-circle-fill text-blue-400 mt-0.5 flex-shrink-0"></i>
    <div class="text-xs text-blue-700 space-y-1">
        <p class="font-black">ทำไมต้องกรอกข้อมูลนี้?</p>
        <p>โรงเรียนต้องการทราบว่านักเรียนแต่ละคนเดินทางมาอย่างไร เพื่อวางแผนสายรถรับส่งให้ครบทุกพื้นที่ และดูแลความปลอดภัยในการเดินทาง</p>
    </div>
</div>

</div><!-- /container -->
<script>
function toggleRouteSelect() {
    const val = document.querySelector('input[name="transport_type"]:checked')?.value;
    document.getElementById('routeSection').classList.toggle('hidden', val !== 'school_bus');
}
</script>
</body>
</html>
