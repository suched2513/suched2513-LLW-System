<?php
session_start();
require_once '../config.php';

// Auth guard
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header("Location: ../login.php"); exit();
}

$msg = '';

// AJAX: ทดสอบบอทเวร (ใช้ duty_bot_token แทน telegram_token)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_duty_telegram') {
    header('Content-Type: application/json; charset=utf-8');
    require_once '../includes/telegram_bot.php';
    $token   = trim($_POST['token'] ?? '');
    $chat_id = trim($_POST['chat_id'] ?? '');
    if (!$token || !$chat_id) {
        echo json_encode(['ok' => false, 'message' => 'กรุณากรอก Bot Token และ Chat ID ของบอทเวรก่อน']);
        exit;
    }
    Telegram::init($token);
    $res = Telegram::sendMessage("🛡️ <b>ทดสอบบอทเวรประจำวัน</b>\nโรงเรียนละลมวิทยา พร้อมใช้งานแล้ว!", $chat_id);
    if ($res['ok'] ?? false) {
        echo json_encode(['ok' => true, 'message' => 'ส่งสำเร็จ! ตรวจสอบในกลุ่มเวรได้เลย']);
    } else {
        $desc = $res['description'] ?? '';
        $known = [
            'chat not found'      => 'ไม่พบกลุ่ม — ตรวจสอบ Chat ID และต้องเพิ่มบอทเข้ากลุ่มก่อน',
            'bot was kicked'      => 'บอทถูกเตะออกจากกลุ่ม — เพิ่มบอทเข้ากลุ่มใหม่',
            'bot is not a member' => 'บอทยังไม่ได้เข้ากลุ่ม — เพิ่มบอทเข้ากลุ่มก่อน',
            'Unauthorized'        => 'Bot Token ไม่ถูกต้อง — ตรวจสอบ Token อีกครั้ง',
            'not enough rights'   => 'บอทไม่มีสิทธิ์ส่งข้อความในกลุ่มนี้',
        ];
        $msg = 'ส่งไม่สำเร็จ';
        foreach ($known as $en => $th) {
            if (stripos($desc, $en) !== false) { $msg = $th; break; }
        }
        if ($msg === 'ส่งไม่สำเร็จ' && $desc) $msg .= ": {$desc}";
        echo json_encode(['ok' => false, 'message' => $msg]);
    }
    exit;
}

// AJAX: ทดสอบ Telegram (ก่อน csrf check เพราะ return JSON แล้ว exit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_telegram') {
    header('Content-Type: application/json; charset=utf-8');
    require_once '../includes/telegram_bot.php';
    $token   = trim($_POST['token'] ?? '');
    $chat_id = trim($_POST['chat_id'] ?? '');
    if (!$token || !$chat_id) {
        echo json_encode(['ok' => false, 'message' => 'กรุณากรอก Token และ Chat ID ก่อน']);
        exit;
    }
    // ใช้ Telegram static class (cURL) เพื่อรับ error จาก API จริง
    Telegram::init($token);
    $res = Telegram::sendMessage("✅ <b>ทดสอบการเชื่อมต่อ Telegram</b>\nระบบลงเวลา โรงเรียนละลมวิทยา พร้อมใช้งานแล้ว!", $chat_id);
    if ($res['ok'] ?? false) {
        echo json_encode(['ok' => true, 'message' => 'ส่งสำเร็จ! ตรวจสอบใน Telegram ได้เลย']);
    } else {
        $desc = $res['description'] ?? '';
        $known = [
            'chat not found'        => 'ไม่พบกลุ่ม — ตรวจสอบ Chat ID และต้องเพิ่มบอทเข้ากลุ่มก่อน',
            'bot was kicked'        => 'บอทถูกเตะออกจากกลุ่ม — เพิ่มบอทเข้ากลุ่มใหม่',
            'bot is not a member'   => 'บอทยังไม่ได้เข้ากลุ่ม — เพิ่มบอทเข้ากลุ่มก่อน',
            'Unauthorized'          => 'Bot Token ไม่ถูกต้อง — ตรวจสอบ Token อีกครั้ง',
            'not enough rights'     => 'บอทไม่มีสิทธิ์ส่งข้อความในกลุ่มนี้',
            'PEER_ID_INVALID'       => 'Chat ID ไม่ถูกต้อง — ตรวจสอบค่าอีกครั้ง',
        ];
        $msg = 'ส่งไม่สำเร็จ';
        foreach ($known as $en => $th) {
            if (stripos($desc, $en) !== false) { $msg = $th; break; }
        }
        if ($msg === 'ส่งไม่สำเร็จ' && $desc) $msg .= ": {$desc}";
        echo json_encode(['ok' => false, 'message' => $msg]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['telegram_token'])) {
        $tg_token        = trim($_POST['telegram_token']);
        $tg_chat_id      = trim($_POST['admin_chat_id']);
        $tg_leave_chat   = trim($_POST['leave_chat_id']);
        $tg_att_chat     = trim($_POST['att_chat_id']);
        $tg_att_token    = trim($_POST['att_bot_token']);
        $tg_duty_chat    = trim($_POST['duty_chat_id']);
        $tg_duty_token   = trim($_POST['duty_bot_token']);
        $stmt = $conn->prepare("UPDATE wfh_system_settings SET telegram_token=?, admin_chat_id=?, leave_chat_id=?, att_chat_id=?, att_bot_token=?, duty_chat_id=?, duty_bot_token=? WHERE setting_id=1");
        $stmt->bind_param('sssssss', $tg_token, $tg_chat_id, $tg_leave_chat, $tg_att_chat, $tg_att_token, $tg_duty_chat, $tg_duty_token);
        $stmt->execute();
        $stmt->close();
        $msg = 'บันทึกการตั้งค่า Telegram เรียบร้อย';
    } elseif (isset($_POST['regular_time_in'])) {
        $time_in   = $_POST['regular_time_in'];
        $time_late = $_POST['late_time'];
        $stmt = $conn->prepare("UPDATE wfh_system_settings SET regular_time_in=?, late_time=? WHERE setting_id=1");
        $stmt->bind_param('ss', $time_in, $time_late);
        $stmt->execute();
        $stmt->close();
        $msg = 'บันทึกการตั้งค่าเวลาเรียบร้อย';
    } elseif (isset($_POST['boss_name'])) {
        $bossName = trim($_POST['boss_name']);
        $stmt = $conn->prepare("UPDATE wfh_system_settings SET boss_name = ? WHERE setting_id = 1");
        $stmt->bind_param('s', $bossName);
        $stmt->execute();
        $msg = 'บันทึกชื่อผู้อำนวยการเรียบร้อย';
    } elseif (isset($_POST['school_lat'])) {
        $lat    = (float)$_POST['school_lat'];
        $lng    = (float)$_POST['school_lng'];
        $radius = max(50, min(2000, (int)$_POST['geofence_radius']));
        $stmt = $conn->prepare("UPDATE wfh_system_settings SET school_lat=?, school_lng=?, geofence_radius=? WHERE setting_id=1");
        $stmt->bind_param('ssi', $lat, $lng, $radius);
        $stmt->execute();
        $stmt->close();
        $msg = 'บันทึกพิกัดโรงเรียนเรียบร้อย';
    } elseif (isset($_POST['boss_pin'])) {
        $pin = trim($_POST['boss_pin']);
        if (strlen($pin) >= 4 && strlen($pin) <= 6 && ctype_digit($pin)) {
            $hashed = password_hash($pin, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE wfh_system_settings SET boss_pin = ? WHERE setting_id = 1");
            $stmt->bind_param('s', $hashed);
            $stmt->execute();
            $msg = 'ตั้ง PIN เรียบร้อยแล้ว';
        } else {
            $msg = 'error:PIN ต้องเป็นตัวเลข 4-6 หลักเท่านั้น';
        }
    }
}

$settings = $conn->query("SELECT * FROM wfh_system_settings LIMIT 1")->fetch_assoc();
$hasPIN = !empty($settings['boss_pin']);

// Layout variables
$pageTitle = 'ตั้งค่าระบบ';
$pageSubtitle = 'กำหนดค่าพื้นฐานและระบบความปลอดภัย';
$activeSystem = 'wfh';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Alert Handler -->
<?php if ($msg): ?>
<script>
    Swal.fire({
        icon: '<?= strpos($msg, "error:") === 0 ? "error" : "success" ?>',
        title: '<?= strpos($msg, "error:") === 0 ? "ข้อผิดพลาด" : "สำเร็จ" ?>',
        text: '<?= str_replace("error:", "", $msg) ?>',
        confirmButtonColor: '#4f46e5'
    });
</script>
<?php endif; ?>

<div class="max-w-4xl mx-auto space-y-8">

    <!-- School GPS / Geofence -->
    <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-xl shadow-emerald-100/40 p-8 border border-white/60">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl">
                <i class="bi bi-geo-alt-fill"></i>
            </div>
            <div>
                <h3 class="text-xl font-black text-slate-800">พิกัดโรงเรียน & รัศมีลงเวลา</h3>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">School Location & Geofence Radius</p>
            </div>
        </div>

        <!-- Current values -->
        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Latitude</p>
                <p class="font-black text-slate-700 text-sm" id="cur-lat"><?= $settings['school_lat'] ?? '-' ?></p>
            </div>
            <div class="bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Longitude</p>
                <p class="font-black text-slate-700 text-sm" id="cur-lng"><?= $settings['school_lng'] ?? '-' ?></p>
            </div>
            <div class="bg-emerald-50 rounded-2xl p-4 text-center border border-emerald-100">
                <p class="text-[10px] font-black text-emerald-400 uppercase tracking-widest mb-1">Radius</p>
                <p class="font-black text-emerald-700 text-sm"><?= $settings['geofence_radius'] ?? '200' ?> ม.</p>
            </div>
        </div>

        <form method="POST" class="space-y-4" id="gps-form">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-black text-slate-700 mb-2">Latitude (ละติจูด)</label>
                    <input type="number" step="0.000001" name="school_lat" id="inp-lat"
                        value="<?= htmlspecialchars($settings['school_lat'] ?? '') ?>"
                        placeholder="เช่น 15.118200"
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold" required>
                </div>
                <div>
                    <label class="block text-sm font-black text-slate-700 mb-2">Longitude (ลองจิจูด)</label>
                    <input type="number" step="0.000001" name="school_lng" id="inp-lng"
                        value="<?= htmlspecialchars($settings['school_lng'] ?? '') ?>"
                        placeholder="เช่น 104.223900"
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-black text-slate-700 mb-2">
                    รัศมีพื้นที่ลงเวลา (เมตร)
                    <span class="text-xs font-normal text-slate-400 ml-1">— โรงเรียน 49 ไร่ ≈ รัศมี 158 ม. แนะนำ 200 ม.</span>
                </label>
                <input type="number" name="geofence_radius" min="50" max="2000"
                    value="<?= htmlspecialchars($settings['geofence_radius'] ?? '200') ?>"
                    class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold" required>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" id="btn-gps"
                    class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3 rounded-2xl shadow-lg shadow-emerald-100 transition-all hover:scale-[1.02] flex items-center justify-center gap-2">
                    <i class="bi bi-crosshair2"></i> ใช้พิกัดตำแหน่งปัจจุบัน
                </button>
                <button type="submit"
                    class="flex-1 bg-slate-800 hover:bg-slate-900 text-white font-black py-3 rounded-2xl shadow-lg transition-all hover:scale-[1.02]">
                    <i class="bi bi-save-fill me-1"></i> บันทึก
                </button>
            </div>
        </form>

        <div id="gps-msg" class="hidden mt-4 p-4 rounded-2xl text-sm font-bold"></div>
    </div>

    <!-- Telegram Settings -->
    <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-xl shadow-sky-100/40 p-8 border border-white/60">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 bg-sky-50 text-sky-500 rounded-2xl flex items-center justify-center text-xl">
                <i class="bi bi-telegram"></i>
            </div>
            <div>
                <h3 class="text-xl font-black text-slate-800">การแจ้งเตือน Telegram</h3>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Bot Token & Chat ID</p>
            </div>
            <?php
                $tg_ok   = !empty($settings['telegram_token']) && !empty($settings['admin_chat_id']);
                $lv_ok   = !empty($settings['leave_chat_id']);
                $att_ok  = !empty($settings['att_chat_id']) && !empty($settings['att_bot_token']);
                $duty_ok = !empty($settings['duty_chat_id']);
                $done    = array_sum([$tg_ok, $lv_ok, $att_ok, $duty_ok]);
            ?>
            <?php if ($done === 4): ?>
            <span class="ml-auto px-4 py-1.5 rounded-full bg-emerald-50 text-emerald-600 text-xs font-black border border-emerald-100 flex items-center gap-1.5">
                <i class="bi bi-check-circle-fill"></i> ครบทั้ง 4 กลุ่ม
            </span>
            <?php elseif ($tg_ok): ?>
            <span class="ml-auto px-4 py-1.5 rounded-full bg-amber-50 text-amber-600 text-xs font-black border border-amber-100 flex items-center gap-1.5">
                <i class="bi bi-exclamation-circle-fill"></i> ตั้งค่าแล้ว <?= $done ?>/4 กลุ่ม
            </span>
            <?php else: ?>
            <span class="ml-auto px-4 py-1.5 rounded-full bg-rose-50 text-rose-600 text-xs font-black border border-rose-100 flex items-center gap-1.5">
                <i class="bi bi-x-circle-fill"></i> ยังไม่ได้ตั้งค่า
            </span>
            <?php endif; ?>
        </div>

        <!-- วิธีหา Chat ID -->
        <div class="bg-sky-50 rounded-2xl p-5 mb-6 border border-sky-100">
            <p class="text-xs font-black text-sky-700 uppercase tracking-widest mb-3 flex items-center gap-2">
                <i class="bi bi-info-circle-fill"></i> วิธีหา Chat ID ของคุณ
            </p>
            <ol class="space-y-1.5 text-sm text-sky-800 font-bold">
                <li class="flex items-start gap-2"><span class="shrink-0 w-5 h-5 bg-sky-200 text-sky-700 rounded-full flex items-center justify-center text-xs font-black">1</span> เปิด Telegram แล้วค้นหา <code class="bg-white px-1.5 py-0.5 rounded-lg text-sky-600 font-mono text-xs">@userinfobot</code></li>
                <li class="flex items-start gap-2"><span class="shrink-0 w-5 h-5 bg-sky-200 text-sky-700 rounded-full flex items-center justify-center text-xs font-black">2</span> กด <strong>Start</strong> หรือพิมพ์ <code class="bg-white px-1.5 py-0.5 rounded-lg text-sky-600 font-mono text-xs">/start</code></li>
                <li class="flex items-start gap-2"><span class="shrink-0 w-5 h-5 bg-sky-200 text-sky-700 rounded-full flex items-center justify-center text-xs font-black">3</span> บอทจะตอบกลับพร้อม <strong>Id:</strong> นั่นคือ Chat ID ของคุณ</li>
                <li class="flex items-start gap-2"><span class="shrink-0 w-5 h-5 bg-sky-200 text-sky-700 rounded-full flex items-center justify-center text-xs font-black">4</span> สำหรับ Bot Token: สร้างผ่าน <code class="bg-white px-1.5 py-0.5 rounded-lg text-sky-600 font-mono text-xs">@BotFather</code> → /newbot</li>
            </ol>
        </div>

        <form method="POST" class="space-y-4" id="telegram-form">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-black text-slate-700 mb-2">Bot Token</label>
                <div class="relative">
                    <input type="password" name="telegram_token" id="inp-tg-token"
                        value="<?= htmlspecialchars($settings['telegram_token'] ?? '') ?>"
                        placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz"
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 pr-12 text-sm focus:ring-4 focus:ring-sky-500/10 focus:border-sky-500 outline-none transition-all font-mono" required>
                    <button type="button" onclick="toggleToken()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-all">
                        <i class="bi bi-eye-fill" id="eye-icon"></i>
                    </button>
                </div>
            </div>
            <!-- Chat ID group 1: ลงเวลา -->
            <div class="bg-emerald-50/50 rounded-2xl p-5 border border-emerald-100 space-y-3">
                <p class="text-xs font-black text-emerald-700 uppercase tracking-widest flex items-center gap-2">
                    <i class="bi bi-clock-history"></i> กลุ่มแจ้งเตือนการลงเวลา
                </p>
                <input type="text" name="admin_chat_id" id="inp-tg-chat"
                    value="<?= htmlspecialchars($settings['admin_chat_id'] ?? '') ?>"
                    placeholder="เช่น -4725182755"
                    class="w-full bg-white border border-emerald-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 outline-none transition-all font-bold">
                <p class="text-xs text-emerald-600/70 font-bold">รับแจ้งเตือนเมื่อ: เช็คอิน / เช็คเอาท์ / เข้างานสาย / ลงเวลานอกพื้นที่</p>
                <button type="button" onclick="testTelegram('inp-tg-chat','tg-result-checkin')"
                    class="w-full bg-white hover:bg-emerald-50 text-emerald-600 font-black py-2.5 rounded-xl border border-emerald-200 transition-all text-sm flex items-center justify-center gap-2">
                    <i class="bi bi-send-fill"></i> ทดสอบกลุ่มลงเวลา
                </button>
                <div id="tg-result-checkin" class="hidden p-3 rounded-xl text-sm font-bold"></div>
            </div>

            <!-- Chat ID group 2: การลา -->
            <div class="bg-rose-50/50 rounded-2xl p-5 border border-rose-100 space-y-3">
                <p class="text-xs font-black text-rose-700 uppercase tracking-widest flex items-center gap-2">
                    <i class="bi bi-calendar-x"></i> กลุ่มแจ้งเตือนการลา
                </p>
                <input type="text" name="leave_chat_id" id="inp-leave-chat"
                    value="<?= htmlspecialchars($settings['leave_chat_id'] ?? '') ?>"
                    placeholder="เช่น -4987654321"
                    class="w-full bg-white border border-rose-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 outline-none transition-all font-bold">
                <p class="text-xs text-rose-600/70 font-bold">รับแจ้งเตือนเมื่อ: ครูยื่นขอลา / ขออนุญาตมาสาย</p>
                <button type="button" onclick="testTelegram('inp-leave-chat','tg-result-leave')"
                    class="w-full bg-white hover:bg-rose-50 text-rose-600 font-black py-2.5 rounded-xl border border-rose-200 transition-all text-sm flex items-center justify-center gap-2">
                    <i class="bi bi-send-fill"></i> ทดสอบกลุ่มการลา
                </button>
                <div id="tg-result-leave" class="hidden p-3 rounded-xl text-sm font-bold"></div>
            </div>

            <!-- Chat ID group 3: เช็คชื่อนักเรียน -->
            <div class="bg-indigo-50/50 rounded-2xl p-5 border border-indigo-100 space-y-3">
                <p class="text-xs font-black text-indigo-700 uppercase tracking-widest flex items-center gap-2">
                    <i class="bi bi-people-fill"></i> กลุ่มแจ้งเตือนเช็คชื่อนักเรียน
                </p>
                <p class="text-xs text-indigo-500 font-bold -mt-1">ใช้บอทแยก — กรอก Token เฉพาะของบอทเช็คชื่อ</p>
                <div class="relative">
                    <input type="password" name="att_bot_token" id="inp-att-token"
                        value="<?= htmlspecialchars($settings['att_bot_token'] ?? '') ?>"
                        placeholder="Bot Token ของบอทเช็คชื่อ"
                        class="w-full bg-white border border-indigo-200 rounded-2xl px-4 py-3 pr-12 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-mono">
                    <button type="button" onclick="toggleAttToken()" class="absolute right-4 top-1/2 -translate-y-1/2 text-indigo-300 hover:text-indigo-500 transition-all">
                        <i class="bi bi-eye-fill" id="eye-att-icon"></i>
                    </button>
                </div>
                <input type="text" name="att_chat_id" id="inp-att-chat"
                    value="<?= htmlspecialchars($settings['att_chat_id'] ?? '') ?>"
                    placeholder="Chat ID กลุ่มเช็คชื่อ เช่น -4111333555"
                    class="w-full bg-white border border-indigo-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold">
                <p class="text-xs text-indigo-600/70 font-bold">รับแจ้งเตือนเมื่อ: ครูเช็คชื่อรายคาบและมีนักเรียนขาด/โดด</p>
                <button type="button" onclick="testAttTelegram()"
                    class="w-full bg-white hover:bg-indigo-50 text-indigo-600 font-black py-2.5 rounded-xl border border-indigo-200 transition-all text-sm flex items-center justify-center gap-2">
                    <i class="bi bi-send-fill"></i> ทดสอบกลุ่มเช็คชื่อ
                </button>
                <div id="tg-result-att" class="hidden p-3 rounded-xl text-sm font-bold"></div>
            </div>

            <!-- Chat ID group 4: เวรประจำวัน -->
            <div class="bg-violet-50/50 rounded-2xl p-5 border border-violet-100 space-y-3">
                <p class="text-xs font-black text-violet-700 uppercase tracking-widest flex items-center gap-2">
                    <i class="bi bi-shield-check"></i> กลุ่มแจ้งเตือนเวรประจำวัน
                </p>
                <p class="text-xs text-violet-500 font-bold -mt-1">ใช้บอทแยก — กรอก Token เฉพาะของบอทเวร</p>
                <div class="relative">
                    <input type="password" name="duty_bot_token" id="inp-duty-token"
                        value="<?= htmlspecialchars($settings['duty_bot_token'] ?? '') ?>"
                        placeholder="Bot Token ของบอทเวร"
                        class="w-full bg-white border border-violet-200 rounded-2xl px-4 py-3 pr-12 text-sm focus:ring-4 focus:ring-violet-500/10 focus:border-violet-500 outline-none transition-all font-mono">
                    <button type="button" onclick="toggleDutyToken()" class="absolute right-4 top-1/2 -translate-y-1/2 text-violet-300 hover:text-violet-500 transition-all">
                        <i class="bi bi-eye-fill" id="eye-duty-icon"></i>
                    </button>
                </div>
                <input type="text" name="duty_chat_id" id="inp-duty-chat"
                    value="<?= htmlspecialchars($settings['duty_chat_id'] ?? '') ?>"
                    placeholder="Chat ID กลุ่มเวร เช่น -4111222333"
                    class="w-full bg-white border border-violet-200 rounded-2xl px-4 py-3 text-sm focus:ring-4 focus:ring-violet-500/10 focus:border-violet-500 outline-none transition-all font-bold">
                <p class="text-xs text-violet-600/70 font-bold">รับแจ้งเตือนอัตโนมัติ: เวรเช้า 06:30 / เวรเย็น 16:20 / เวรกลาง 17:30</p>
                <button type="button" onclick="testDutyTelegram()"
                    class="w-full bg-white hover:bg-violet-50 text-violet-600 font-black py-2.5 rounded-xl border border-violet-200 transition-all text-sm flex items-center justify-center gap-2">
                    <i class="bi bi-send-fill"></i> ทดสอบกลุ่มเวร
                </button>
                <div id="tg-result-duty" class="hidden p-3 rounded-xl text-sm font-bold"></div>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="w-full bg-slate-800 hover:bg-slate-900 text-white font-black py-3.5 rounded-2xl shadow-lg transition-all hover:scale-[1.02]">
                    <i class="bi bi-save-fill me-2"></i> บันทึกการตั้งค่า Telegram ทั้งหมด
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Working Time -->
        <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-xl shadow-indigo-100/40 p-8 border border-white/60">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-slate-800">เวลาทำงาน</h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Time Settings</p>
                </div>
            </div>
            
            <form method="POST" class="space-y-6">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-sm font-black text-slate-700 mb-2">เวลาเริ่มงานปกติ</label>
                    <input type="time" name="regular_time_in" value="<?= $settings['regular_time_in'] ?>" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xlt px-5 py-4 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold" required>
                    <p class="mt-2 text-xs text-slate-400 font-bold uppercase tracking-wide">Standard Check-in Time</p>
                </div>
                <div>
                    <label class="block text-sm font-black text-slate-700 mb-2">เวลาที่เริ่มนับว่า "มาสาย"</label>
                    <input type="time" name="late_time" value="<?= $settings['late_time'] ?>" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold" required>
                    <p class="mt-2 text-xs text-slate-400 font-bold uppercase tracking-wide">Mark as "Late" after this time</p>
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 rounded-2xl shadow-lg shadow-indigo-200 transition-all hover:scale-[1.02] active:scale-[0.98]">
                    <i class="bi bi-save-fill me-2"></i> บันทึกเวลา
                </button>
            </form>
        </div>

        <!-- Director Name -->
        <div class="bg-indigo-600 rounded-[2.5rem] shadow-xl shadow-indigo-200/50 p-8 text-white relative overflow-hidden">
            <div class="relative z-10">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-lg text-white rounded-2xl flex items-center justify-center text-xl">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black">ผู้อำนวยการ</h3>
                        <p class="text-xs text-white/60 font-bold uppercase tracking-wider">Director Information</p>
                    </div>
                </div>

                <form method="POST" class="space-y-6">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-sm font-black mb-2 opacity-80">ชื่อ-สกุล ผอ. (ใช้แสดงในเอกสาร)</label>
                        <input type="text" name="boss_name" value="<?= htmlspecialchars($settings['boss_name'] ?? '') ?>" 
                            placeholder="เช่น นาย สมศักดิ์์ ใจดี" 
                            class="w-full bg-white/10 border border-white/20 rounded-2xl px-5 py-4 text-sm focus:bg-white focus:text-slate-800 outline-none transition-all font-black placeholder:text-white/40" required>
                        <p class="mt-2 text-xs text-white/50 font-bold uppercase tracking-wide">Will be used in print-ready reports</p>
                    </div>
                    <button type="submit" class="w-full bg-white text-indigo-600 font-black py-4 rounded-2xl shadow-xl transition-all hover:bg-slate-50 hover:scale-[1.02] active:scale-[0.98]">
                        <i class="bi bi-check2-circle me-2"></i> อัปเดตข้อมูล
                    </button>
                </form>
            </div>
            <!-- Decorative circle -->
            <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Security PIN -->
        <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-xl shadow-indigo-100/40 p-8 border border-white/60 flex flex-col">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 <?= $hasPIN ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' ?> rounded-2xl flex items-center justify-center text-xl transition-colors">
                    <i class="bi <?= $hasPIN ? 'bi-shield-check' : 'bi-shield-exclamation' ?>"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-slate-800">รหัสความปลอดภัย (PIN)</h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Approval Verification</p>
                </div>
            </div>

            <div class="flex-1">
                <?php if ($hasPIN): ?>
                <div class="bg-emerald-50 text-emerald-700 px-6 py-4 rounded-2xl text-[13px] font-bold border border-emerald-100 mb-6 flex items-center gap-3">
                    <i class="bi bi-check-circle-fill text-lg"></i>
                    <span>ตั้งค่า PIN เรียบร้อย ผอ. ต้องใช้ PIN ในการอนุมัติคำขอ</span>
                </div>
                <?php else: ?>
                <div class="bg-amber-50 text-amber-700 px-6 py-4 rounded-2xl text-[13px] font-bold border border-amber-100 mb-6 flex items-center gap-3">
                    <i class="bi bi-exclamation-triangle-fill text-lg"></i>
                    <span>ยังไม่ได้ตั้ง PIN ระบบจะอนุมัติทันทีโดยไม่ถามรหัส</span>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-sm font-black text-slate-700 mb-2"><?= $hasPIN ? 'เปลี่ยน PIN ใหม่' : 'ตั้ง PIN ใหม่' ?></label>
                        <input type="password" name="boss_pin" maxlength="6" 
                            placeholder="ตัวเลข 4-6 หลัก" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all font-bold tracking-widest" required>
                    </div>
                    <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-black py-4 rounded-2xl shadow-lg transition-all hover:scale-[1.02] active:scale-[0.98]">
                        <i class="bi bi-lock-fill me-2"></i> <?= $hasPIN ? 'ยืนยันการเปลี่ยน PIN' : 'สร้าง PIN ใหม่' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- System Info -->
        <div class="bg-slate-50 rounded-[2.5rem] p-8 border border-slate-200/50">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 bg-white text-slate-400 rounded-2xl flex items-center justify-center text-xl border border-slate-100">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-slate-800">ข้อมูลระบบ</h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">System Information</p>
                </div>
            </div>

            <div class="space-y-4">
                <?php 
                $info = [
                    ['icon' => 'bi-cpu', 'label' => 'ชื่อระบบ', 'value' => 'WFH:LLW Attendance'],
                    ['icon' => 'bi-building', 'label' => 'หน่วยงาน', 'value' => 'โรงเรียนละลมวิทยา'],
                    ['icon' => 'bi-git', 'label' => 'เวอร์ชั่นคงที่', 'value' => 'v1.2-indigo-unified'],
                    ['icon' => 'bi-calendar-check', 'label' => 'วันที่ปัจจุบัน', 'value' => date('d/m/').(date('Y')+543)],
                ];
                foreach ($info as $item): 
                ?>
                <div class="flex items-center justify-between p-4 bg-white rounded-2xl border border-slate-100 shadow-sm">
                    <div class="flex items-center gap-3">
                        <i class="bi <?= $item['icon'] ?> text-indigo-500"></i>
                        <span class="text-xs font-black text-slate-500 uppercase tracking-widest"><?= $item['label'] ?></span>
                    </div>
                    <span class="text-sm font-black text-slate-800 tracking-tight"><?= $item['value'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Path Diagnostic (Admin Only Help) -->
    <div class="bg-indigo-50/50 rounded-[2.5rem] p-8 border border-indigo-100 mt-8">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 bg-white text-indigo-600 rounded-2xl flex items-center justify-center text-xl shadow-sm">
                <i class="bi bi-terminal-fill"></i>
            </div>
            <div>
                <h3 class="text-lg font-black text-indigo-900">Environment Diagnostics</h3>
                <p class="text-xs text-indigo-400 font-bold uppercase tracking-wider">Automated Path Tracking</p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="space-y-1">
                <p class="text-xs font-black text-indigo-300 uppercase tracking-widest ml-1">Detected Base Path</p>
                <div class="bg-white px-4 py-3 rounded-xl border border-indigo-100 font-mono text-xs font-bold text-indigo-600">
                    <?= $base_path ?: '(Root /)' ?>
                </div>
            </div>
            <div class="space-y-1">
                <p class="text-xs font-black text-indigo-300 uppercase tracking-widest ml-1">Current Request URI</p>
                <div class="bg-white px-4 py-3 rounded-xl border border-indigo-100 font-mono text-xs font-bold text-slate-500">
                    <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>
                </div>
            </div>
            <div class="space-y-1">
                <p class="text-xs font-black text-indigo-300 uppercase tracking-widest ml-1">Document Root</p>
                <div class="bg-white px-4 py-3 rounded-xl border border-indigo-100 font-mono text-xs font-bold text-slate-500 overflow-hidden text-ellipsis">
                    <?= htmlspecialchars($_SERVER['DOCUMENT_ROOT']) ?>
                </div>
            </div>
        </div>
        
        <div class="mt-6 flex items-start gap-3 bg-white/60 p-4 rounded-2xl border border-indigo-100">
            <i class="bi bi-lightbulb-fill text-indigo-500"></i>
            <p class="text-sm font-bold text-indigo-800/80 leading-relaxed uppercase tracking-tight">
                PRO-TIP: ระบบตรวจสอบเส้นทาง (Path) โดยการเปรียบเทียบ Directory หลักกับ Document Root อัตโนมัติ ทำให้ลิงก์ใน Sidebar และ Breadcrumbs ทำงานถูกต้องเสมอไม่ว่าจะย้ายโฟลเดอร์โปรเจกต์ไปที่ใด
            </p>
        </div>
    </div>
</div>

<script>
// Toggle Att Bot Token visibility
function toggleAttToken() {
    const inp  = document.getElementById('inp-att-token');
    const icon = document.getElementById('eye-att-icon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash-fill';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye-fill';
    }
}

// ทดสอบบอทเช็คชื่อ (ใช้ token แยก)
function testAttTelegram() {
    const result = document.getElementById('tg-result-att');
    const token  = document.getElementById('inp-att-token').value.trim();
    const chatId = document.getElementById('inp-att-chat').value.trim();
    const btn    = event.currentTarget;

    if (!token || !chatId) {
        result.className = 'p-3 rounded-xl text-sm font-bold bg-amber-50 text-amber-700 border border-amber-100';
        result.textContent = 'กรุณากรอก Bot Token และ Chat ID ของบอทเช็คชื่อก่อน';
        result.classList.remove('hidden');
        return;
    }

    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังส่ง...';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'test_duty_telegram');
    fd.append('token', token);
    fd.append('chat_id', chatId);

    fetch('settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            result.className = 'p-3 rounded-xl text-sm font-bold border ' +
                (data.ok ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100');
            result.innerHTML = (data.ok ? '<i class="bi bi-check-circle-fill me-2"></i>' : '<i class="bi bi-x-circle-fill me-2"></i>') + data.message;
            result.classList.remove('hidden');
        })
        .catch(() => {
            result.className = 'p-3 rounded-xl text-sm font-bold bg-rose-50 text-rose-600 border border-rose-100';
            result.textContent = 'เกิดข้อผิดพลาด ไม่สามารถเชื่อมต่อได้';
            result.classList.remove('hidden');
        })
        .finally(() => {
            btn.innerHTML = oldHtml;
            btn.disabled = false;
        });
}

// Toggle Duty Bot Token visibility
function toggleDutyToken() {
    const inp  = document.getElementById('inp-duty-token');
    const icon = document.getElementById('eye-duty-icon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash-fill';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye-fill';
    }
}

// ทดสอบบอทเวร (ใช้ token แยก)
function testDutyTelegram() {
    const result = document.getElementById('tg-result-duty');
    const token  = document.getElementById('inp-duty-token').value.trim();
    const chatId = document.getElementById('inp-duty-chat').value.trim();
    const btn    = event.currentTarget;

    if (!token || !chatId) {
        result.className = 'p-3 rounded-xl text-sm font-bold bg-amber-50 text-amber-700 border border-amber-100';
        result.textContent = 'กรุณากรอก Bot Token และ Chat ID ของบอทเวรก่อน';
        result.classList.remove('hidden');
        return;
    }

    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังส่ง...';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'test_duty_telegram');
    fd.append('token', token);
    fd.append('chat_id', chatId);

    fetch('settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            result.className = 'p-3 rounded-xl text-sm font-bold border ' +
                (data.ok ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100');
            result.innerHTML = (data.ok ? '<i class="bi bi-check-circle-fill me-2"></i>' : '<i class="bi bi-x-circle-fill me-2"></i>') + data.message;
            result.classList.remove('hidden');
        })
        .catch(() => {
            result.className = 'p-3 rounded-xl text-sm font-bold bg-rose-50 text-rose-600 border border-rose-100';
            result.textContent = 'เกิดข้อผิดพลาด ไม่สามารถเชื่อมต่อได้';
            result.classList.remove('hidden');
        })
        .finally(() => {
            btn.innerHTML = oldHtml;
            btn.disabled = false;
        });
}

// Toggle Bot Token visibility
function toggleToken() {
    const inp = document.getElementById('inp-tg-token');
    const icon = document.getElementById('eye-icon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash-fill';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye-fill';
    }
}

// ทดสอบ Telegram (ใช้ร่วมกันทั้ง 2 กลุ่ม)
function testTelegram(chatInputId, resultDivId) {
    const result = document.getElementById(resultDivId);
    const token  = document.getElementById('inp-tg-token').value.trim();
    const chatId = document.getElementById(chatInputId).value.trim();
    const btn    = event.currentTarget;

    if (!token || !chatId) {
        result.className = 'p-3 rounded-xl text-sm font-bold bg-amber-50 text-amber-700 border border-amber-100';
        result.textContent = 'กรุณากรอก Token และ Chat ID ก่อนทดสอบ';
        result.classList.remove('hidden');
        return;
    }

    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังส่ง...';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'test_telegram');
    fd.append('token', token);
    fd.append('chat_id', chatId);

    fetch('settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            result.className = 'p-3 rounded-xl text-sm font-bold border ' +
                (data.ok ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100');
            result.innerHTML = (data.ok ? '<i class="bi bi-check-circle-fill me-2"></i>' : '<i class="bi bi-x-circle-fill me-2"></i>') + data.message;
            result.classList.remove('hidden');
        })
        .catch(() => {
            result.className = 'p-3 rounded-xl text-sm font-bold bg-rose-50 text-rose-600 border border-rose-100';
            result.textContent = 'เกิดข้อผิดพลาด ไม่สามารถเชื่อมต่อได้';
            result.classList.remove('hidden');
        })
        .finally(() => {
            btn.innerHTML = oldHtml;
            btn.disabled = false;
        });
}

document.getElementById('btn-gps').addEventListener('click', function() {
    const btn = this;
    const msg = document.getElementById('gps-msg');
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังค้นหาตำแหน่ง...';
    btn.disabled = true;
    msg.className = 'mt-4 p-4 rounded-2xl text-sm font-bold bg-slate-50 text-slate-600 border border-slate-100';
    msg.textContent = 'กำลังรับสัญญาณ GPS...';
    msg.classList.remove('hidden');

    if (!navigator.geolocation) {
        msg.className = 'mt-4 p-4 rounded-2xl text-sm font-bold bg-rose-50 text-rose-600 border border-rose-100';
        msg.textContent = 'เบราว์เซอร์นี้ไม่รองรับ Geolocation';
        btn.innerHTML = '<i class="bi bi-crosshair2"></i> ใช้พิกัดตำแหน่งปัจจุบัน';
        btn.disabled = false;
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude.toFixed(6);
            const lng = pos.coords.longitude.toFixed(6);
            document.getElementById('inp-lat').value = lat;
            document.getElementById('inp-lng').value = lng;
            msg.className = 'mt-4 p-4 rounded-2xl text-sm font-bold bg-emerald-50 text-emerald-700 border border-emerald-100';
            msg.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>ได้พิกัด: ' + lat + ', ' + lng + ' — ความแม่นยำ ±' + Math.round(pos.coords.accuracy) + ' ม. <br><span class="text-xs font-normal">กด "บันทึก" เพื่อยืนยัน</span>';
            btn.innerHTML = '<i class="bi bi-crosshair2"></i> ใช้พิกัดตำแหน่งปัจจุบัน';
            btn.disabled = false;
        },
        function(err) {
            msg.className = 'mt-4 p-4 rounded-2xl text-sm font-bold bg-rose-50 text-rose-600 border border-rose-100';
            msg.textContent = 'ไม่สามารถรับพิกัดได้: ' + (err.message || 'Permission denied');
            btn.innerHTML = '<i class="bi bi-crosshair2"></i> ใช้พิกัดตำแหน่งปัจจุบัน';
            btn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
