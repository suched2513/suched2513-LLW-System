<?php
session_start();
require_once '../config.php';

// Auth guard
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header("Location: ../login.php"); exit();
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['regular_time_in'])) {
        $time_in   = $_POST['regular_time_in'];
        $time_late = $_POST['late_time'];
        $conn->query("UPDATE wfh_system_settings SET regular_time_in='$time_in', late_time='$time_late' WHERE setting_id=1");
        $msg = 'บันทึกการตั้งค่าเวลาเรียบร้อย';
    } elseif (isset($_POST['boss_name'])) {
        $bossName = trim($_POST['boss_name']);
        $stmt = $conn->prepare("UPDATE wfh_system_settings SET boss_name = ? WHERE setting_id = 1");
        $stmt->bind_param('s', $bossName);
        $stmt->execute();
        $msg = 'บันทึกชื่อผู้อำนวยการเรียบร้อย';
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
                    ['icon' => 'bi-calendar-check', 'label' => 'วันที่ปัจจุบัน', 'value' => date('d/y/').(date('Y')+543)],
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

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
