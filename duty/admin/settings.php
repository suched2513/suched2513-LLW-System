<?php
/**
 * duty/admin/settings.php — ตั้งค่าระบบเวรประจำวัน
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();
$msg = '';

// ── บันทึก settings ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $fields = [
        'photos_required_per_point' => (int)($_POST['photos_required_per_point'] ?? 3),
        'max_duty_points'           => (int)($_POST['max_duty_points']           ?? 5),
        'report_deadline_day'       => preg_match('/^\d{2}:\d{2}$/', $_POST['report_deadline_day'] ?? '') ? $_POST['report_deadline_day'] : '10:00',
        'report_deadline_night'     => preg_match('/^\d{2}:\d{2}$/', $_POST['report_deadline_night'] ?? '') ? $_POST['report_deadline_night'] : '22:00',
        'remind_before_minutes'     => (int)($_POST['remind_before_minutes'] ?? 30),
        'max_photos_per_minute'     => (int)($_POST['max_photos_per_minute'] ?? 30),
        'duty_group_chat_id'        => trim($_POST['duty_group_chat_id'] ?? ''),
        'duty_bot_username'         => trim($_POST['duty_bot_username'] ?? ''),
    ];

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO duty_settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)"
        );
        foreach ($fields as $key => $val) {
            $stmt->execute([$key, (string)$val]);
        }
        $msg = 'success:บันทึกการตั้งค่าเรียบร้อย';
    } catch (Exception $e) {
        error_log($e->getMessage());
        $msg = 'error:เกิดข้อผิดพลาดในการบันทึก';
    }
}

// ── โหลด settings ──
$ds = [];
try {
    $rows = $pdo->query("SELECT skey, svalue FROM duty_settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $ds[$r['skey']] = $r['svalue'];
} catch (Exception $e) {}

// defaults
$s = [
    'photos_required_per_point' => $ds['photos_required_per_point'] ?? 3,
    'max_duty_points'           => $ds['max_duty_points']           ?? 5,
    'report_deadline_day'       => $ds['report_deadline_day']       ?? '10:00',
    'report_deadline_night'     => $ds['report_deadline_night']     ?? '22:00',
    'remind_before_minutes'     => $ds['remind_before_minutes']     ?? 30,
    'max_photos_per_minute'     => $ds['max_photos_per_minute']     ?? 30,
    'duty_group_chat_id'        => $ds['duty_group_chat_id']        ?? '',
    'duty_bot_username'         => $ds['duty_bot_username']         ?? '',
];

// ── ดึง Bot Token จาก wfh_system_settings ──
$botToken = '';
try {
    $botToken = (string)($pdo->query("SELECT telegram_token FROM wfh_system_settings LIMIT 1")->fetchColumn() ?? '');
} catch (Exception $e) {}

// ── Webhook info ──
$webhookSecret = $ds['telegram_webhook_secret'] ?? '';
$webhookUrl    = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/api/telegram_webhook.php';

$pageTitle    = 'ตั้งค่าระบบเวร';
$pageSubtitle = 'กำหนดค่าพื้นฐานสำหรับระบบแจ้งเตือนเวรประจำวัน';
$activeSystem = 'duty';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if ($msg): $isErr = str_starts_with($msg,'error:'); ?>
<script>
Swal.fire({icon:'<?= $isErr?'error':'success' ?>',title:'<?= $isErr?'ผิดพลาด':'สำเร็จ' ?>',
    text:'<?= htmlspecialchars(substr($msg,strpos($msg,':')+1),ENT_QUOTES) ?>',confirmButtonColor:'#2563eb'});
</script>
<?php endif; ?>

<div class="row g-4">

<!-- ═══ Col Left: Form Settings ═══ -->
<div class="col-lg-8">
<form method="POST">
    <?= csrf_field() ?>

    <!-- ── การรายงาน ── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom fw-bold">
            <i class="fas fa-camera me-2 text-primary"></i>การรายงานรูปภาพ
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">จำนวนรูปที่ต้องส่งต่อจุด <span class="text-danger">*</span></label>
                    <input type="number" name="photos_required_per_point" class="form-control"
                           value="<?= htmlspecialchars($s['photos_required_per_point']) ?>"
                           min="1" max="10" required>
                    <div class="form-text">ครูต้องส่งรูปกี่รูปถึงจะถือว่า "รายงานครบ"</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">จำนวนจุดเวรสูงสุด</label>
                    <input type="number" name="max_duty_points" class="form-control"
                           value="<?= htmlspecialchars($s['max_duty_points']) ?>"
                           min="1" max="20" required>
                    <div class="form-text">จำนวน dropdown "จุดที่" ในหน้าจัดตาราง</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Deadline กลางวัน (☀️)</label>
                    <input type="time" name="report_deadline_day" class="form-control"
                           value="<?= htmlspecialchars($s['report_deadline_day']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Deadline กลางคืน (🌙)</label>
                    <input type="time" name="report_deadline_night" class="form-control"
                           value="<?= htmlspecialchars($s['report_deadline_night']) ?>" required>
                </div>
            </div>
        </div>
    </div>

    <!-- ── การเตือน ── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom fw-bold">
            <i class="fas fa-bell me-2 text-warning"></i>การเตือน
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">เตือนก่อน deadline กี่นาที</label>
                    <input type="number" name="remind_before_minutes" class="form-control"
                           value="<?= htmlspecialchars($s['remind_before_minutes']) ?>"
                           min="5" max="120">
                    <div class="form-text">Cron จะส่งเตือนครูที่ยังไม่รายงาน</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Rate limit (รูป/นาที/คน)</label>
                    <input type="number" name="max_photos_per_minute" class="form-control"
                           value="<?= htmlspecialchars($s['max_photos_per_minute']) ?>"
                           min="1" max="100">
                </div>
            </div>
        </div>
    </div>

    <!-- ── Telegram ── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom fw-bold">
            <i class="fab fa-telegram me-2 text-info"></i>Telegram Bot
        </div>
        <div class="card-body">
            <div class="alert alert-light border small mb-3">
                <i class="fas fa-info-circle me-1"></i>
                Bot Token ใช้ร่วมกับระบบ WFH — ตั้งค่าได้ที่
                <a href="/admin/settings.php" class="alert-link">Admin Settings</a>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Bot Username</label>
                    <div class="input-group">
                        <span class="input-group-text">@</span>
                        <input type="text" name="duty_bot_username" class="form-control"
                               value="<?= htmlspecialchars(ltrim($s['duty_bot_username'],'@')) ?>"
                               placeholder="ชื่อ bot ไม่มี @">
                    </div>
                    <div class="form-text">ใช้แสดงใน QR Code ลิงก์บัญชี</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Chat ID กลุ่มรับสรุป</label>
                    <input type="text" name="duty_group_chat_id" class="form-control"
                           value="<?= htmlspecialchars($s['duty_group_chat_id']) ?>"
                           placeholder="-100xxxxxxxxxx">
                    <div class="form-text">กลุ่ม Telegram ที่รับสรุปรายวัน</div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary px-5">
        <i class="fas fa-save me-1"></i>บันทึกการตั้งค่า
    </button>
</form>
</div>

<!-- ═══ Col Right: Info ═══ -->
<div class="col-lg-4">

    <!-- Webhook Status -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom fw-bold">
            <i class="fas fa-plug me-2 text-success"></i>Webhook
        </div>
        <div class="card-body">
            <div class="mb-2">
                <label class="form-label fw-bold small text-muted">URL</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control font-monospace"
                           value="<?= htmlspecialchars($webhookUrl) ?>" readonly id="webhookUrl">
                    <button class="btn btn-outline-secondary" type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('webhookUrl').value);Swal.fire({icon:'success',title:'คัดลอกแล้ว',timer:1200,showConfirmButton:false})">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <?php if ($webhookSecret): ?>
            <div class="mb-3">
                <label class="form-label fw-bold small text-muted">Secret Token</label>
                <input type="text" class="form-control form-control-sm font-monospace"
                       value="<?= htmlspecialchars($webhookSecret) ?>" readonly>
            </div>
            <?php endif; ?>
            <?php if ($botToken): ?>
            <a href="/duty/install_webhook.php" class="btn btn-outline-success btn-sm w-100">
                <i class="fas fa-plug me-1"></i>ติดตั้ง/อัปเดต Webhook
            </a>
            <?php else: ?>
            <div class="alert alert-warning small mb-0">
                <i class="fas fa-exclamation-triangle me-1"></i>
                ยังไม่มี Bot Token — ตั้งค่าได้ที่ Admin Settings
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cron Jobs -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom fw-bold">
            <i class="fas fa-clock me-2 text-secondary"></i>Cron Jobs
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <tbody>
                    <tr>
                        <td class="px-3 py-2">
                            <div class="small fw-bold">ตรวจเตือนทุก 5 นาที</div>
                            <code class="text-muted" style="font-size:10px">*/5 * * * * php /path/llw/duty/cron/check_reminders.php</code>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2">
                            <div class="small fw-bold">แจ้งเตือนตารางเวร 06:00</div>
                            <code class="text-muted" style="font-size:10px">0 6 * * * php /path/llw/duty/cron/notify_duty.php</code>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2">
                            <div class="small fw-bold">สรุปรายวัน 18:30</div>
                            <code class="text-muted" style="font-size:10px">30 18 * * * php /path/llw/duty/cron/daily_summary.php</code>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom fw-bold">
            <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
        </div>
        <div class="card-body d-grid gap-2">
            <a href="/duty/admin/reports.php?send_notify=1&csrf_token=<?= csrf_token() ?>"
               class="btn btn-outline-success btn-sm"
               onclick="return confirm('ส่งแจ้งเตือนตารางเวรวันนี้เข้ากลุ่ม Telegram?')">
                <i class="fab fa-telegram me-1"></i>ส่งตารางเวรวันนี้ตอนนี้
            </a>
            <a href="/duty/admin/reports.php?send_summary=1&csrf_token=<?= csrf_token() ?>"
               class="btn btn-outline-primary btn-sm"
               onclick="return confirm('ส่งสรุปเข้ากลุ่ม Telegram ตอนนี้?')">
                <i class="fab fa-telegram me-1"></i>ส่งสรุปรายวันตอนนี้
            </a>
            <a href="/duty/cron/check_reminders.php" class="btn btn-outline-warning btn-sm"
               onclick="return confirm('รันตรวจเตือนทันที?')" target="_blank">
                <i class="fas fa-bell me-1"></i>รันตรวจเตือนทันที
            </a>
        </div>
    </div>

</div>
</div>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
