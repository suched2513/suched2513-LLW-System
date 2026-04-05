<?php
session_start();
require_once '../config.php';

// Auth Check: ต้อง login + เป็น staff/admin เท่านั้น
if (!isset($_SESSION['llw_role'])) {
    header("Location: ../login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? ($_SESSION['firstname'] . ' ' . $_SESSION['lastname']);
$today    = date('Y-m-d');

$pageTitle = 'ลงเวลาปฏิบัติงาน';
$pageSubtitle = 'ระบบลงเวลา WFH สำหรับบุคลากร';
$activeSystem = 'wfh';

// Get today's log
$stmt = $conn->prepare("SELECT * FROM wfh_timelogs WHERE user_id = ? AND log_date = ?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$log = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get settings
$settings = $conn->query("SELECT * FROM wfh_system_settings LIMIT 1")->fetch_assoc();
$late_time = $settings['late_time'] ?? '08:30:00';

// Get 7-day history
$stmt2 = $conn->prepare("SELECT * FROM wfh_timelogs WHERE user_id = ? ORDER BY log_date DESC LIMIT 7");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$history = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

require_once '../components/layout_start.php';
?>

<div class="flex flex-col lg:flex-row gap-8">
    
    <!-- Left Column: Clock & Actions -->
    <div class="lg:w-1/3 flex flex-col gap-8">
        
        <!-- Live Clock Card -->
        <div class="bg-white p-10 rounded-[2.5rem] shadow-sm border border-slate-100 flex flex-col items-center justify-center text-center group">
            <h2 id="live-clock" class="text-6xl font-black text-blue-600 tracking-tighter mb-2 tabular-nums">00:00:00</h2>
            <p id="live-date" class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6"></p>
            
            <div class="w-full h-px bg-slate-50 mb-6"></div>
            
            <div class="flex items-center gap-3">
                <?php if (!$log): ?>
                    <span class="px-6 py-2 rounded-2xl bg-slate-50 text-slate-400 font-black text-[10px] uppercase tracking-widest flex items-center gap-2">
                        <i class="bi bi-circle"></i> Not Checked In
                    </span>
                <?php elseif ($log && !$log['check_out_time']): ?>
                    <span class="px-6 py-2 rounded-2xl bg-emerald-50 text-emerald-600 font-black text-[10px] uppercase tracking-widest flex items-center gap-2">
                        <i class="bi bi-check-circle-fill"></i> In: <?= $log['check_in_time'] ?>
                    </span>
                <?php else: ?>
                    <span class="px-6 py-2 rounded-2xl bg-blue-50 text-blue-600 font-black text-[10px] uppercase tracking-widest flex items-center gap-2">
                        <i class="bi bi-check2-all"></i> Done / ออกงานแล้ว
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Verification Card (Camera & GPS) -->
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 space-y-6">
            <h3 class="font-black text-slate-800 text-sm flex items-center gap-3">
                <i class="bi bi-shield-lock-fill text-blue-600"></i> Identity Verification
            </h3>
            
            <!-- Camera -->
            <div class="relative group">
                <div class="aspect-video bg-slate-900 rounded-[2rem] overflow-hidden shadow-inner relative">
                    <video id="video" autoplay playsinline class="w-full h-full object-cover"></video>
                    <canvas id="canvas-preview" class="absolute inset-0 w-full h-full object-cover hidden"></canvas>
                    <div id="camera-error" class="hidden absolute inset-0 flex flex-col items-center justify-center text-slate-500 p-6 text-center">
                        <i class="bi bi-camera-video-off text-3xl mb-2"></i>
                        <p class="text-[10px] font-bold uppercase tracking-widest">Camera Access Required</p>
                    </div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button class="flex-1 bg-white border border-slate-100 p-3 rounded-2xl text-[10px] font-black uppercase tracking-widest text-slate-400 hover:bg-slate-50 transition-all hidden" id="btn-retake">
                        <i class="bi bi-arrow-counterclockwise mr-1"></i> Retake
                    </button>
                    <button class="flex-1 bg-blue-600 text-white p-3 rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-blue-100 hover:scale-[1.02] transition-all" id="btn-capture">
                        <i class="bi bi-camera-fill mr-1"></i> Capture Photo
                    </button>
                </div>
                <input type="hidden" id="photo-data" value="">
            </div>

            <!-- GPS -->
            <div class="p-6 bg-slate-50 rounded-[2rem] border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-rose-500 text-xl shadow-sm">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">GPS Location</p>
                    <p id="gps-status" class="text-xs font-bold text-slate-700 truncate">Searching for location...</p>
                    <p id="gps-coords" class="text-[8px] font-bold text-slate-300 mt-0.5 truncate"></p>
                </div>
                <input type="hidden" id="gps-lat" value="">
                <input type="hidden" id="gps-lng" value="">
            </div>

            <!-- Submit Buttons -->
            <div class="pt-4 space-y-3">
                <?php if (!$log || ($log && !$log['check_in_time'])): ?>
                    <button onclick="submitLog('checkin')" id="btn-submit" class="w-full py-4 bg-emerald-600 text-white rounded-[1.5rem] font-black text-sm shadow-xl shadow-emerald-100 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-3">
                        <i class="bi bi-box-arrow-in-right text-lg"></i> ลงเวลาเข้างาน (Check-in)
                    </button>
                <?php elseif ($log && $log['check_in_time'] && !$log['check_out_time']): ?>
                    <button onclick="submitLog('checkout')" id="btn-submit" class="w-full py-4 bg-rose-500 text-white rounded-[1.5rem] font-black text-sm shadow-xl shadow-rose-100 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-3">
                        <i class="bi bi-box-arrow-right text-lg"></i> ลงเวลาออกงาน (Check-out)
                    </button>
                <?php else: ?>
                    <div class="w-full p-4 bg-blue-50 text-blue-600 rounded-[1.5rem] font-bold text-xs text-center border border-blue-100">
                        <i class="bi bi-info-circle mr-1"></i> You have completed attendance for today.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: History & Stats -->
    <div class="lg:w-2/3 flex flex-col gap-8">
        
        <!-- Summary Cards Row -->
        <?php if ($log): ?>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-6 no-print">
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Checked In At</p>
                <h4 class="text-3xl font-black text-emerald-600"><?= $log['check_in_time'] ?? '--:--' ?></h4>
            </div>
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Checked Out At</p>
                <h4 class="text-3xl font-black text-rose-500"><?= $log['check_out_time'] ?? '--:--' ?></h4>
            </div>
            <div class="hidden lg:block bg-blue-600 p-8 rounded-[2.5rem] shadow-xl shadow-blue-100 text-white">
                <p class="text-[10px] font-black text-blue-200 uppercase tracking-widest mb-1">Work Hours</p>
                <?php
                    $hrs = '--'; $mns = '--';
                    if ($log['check_in_time'] && $log['check_out_time']) {
                        $diff = strtotime($log['check_out_time']) - strtotime($log['check_in_time']);
                        $hrs = floor($diff/3600); $mns = floor(($diff%3600)/60);
                    }
                ?>
                <h4 class="text-3xl font-black"><?= $hrs ?>h <?= $mns ?>m</h4>
            </div>
        </div>
        <?php endif; ?>

        <!-- History Table -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-10 py-8 border-b border-slate-50 flex items-center justify-between">
                <h3 class="font-black text-slate-800 flex items-center gap-3"><i class="bi bi-clock-history text-blue-600"></i> ประวัติการลงเวลา</h3>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest font-bold">Past 7 days records</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <tr>
                            <th class="px-10 py-5 text-left">Date</th>
                            <th class="px-6 py-5 text-left">In</th>
                            <th class="px-6 py-5 text-left">Out</th>
                            <th class="px-6 py-5 text-center">Location/Photo</th>
                            <th class="px-10 py-5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-slate-600">
                        <?php if (empty($history)): ?>
                            <tr><td colspan="5" class="py-20 text-center font-bold italic text-slate-300">No history found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($history as $h): ?>
                                <tr class="hover:bg-slate-50/50 transition-all">
                                    <td class="px-10 py-5 font-bold text-slate-700"><?= date('d/m/Y', strtotime($h['log_date'])) ?></td>
                                    <td class="px-6 py-5 font-bold text-slate-500"><?= $h['check_in_time'] ?: '--' ?></td>
                                    <td class="px-6 py-5 font-bold text-slate-400"><?= $h['check_out_time'] ?: '--' ?></td>
                                    <td class="px-6 py-5 text-center flex items-center justify-center gap-2">
                                        <?php if ($h['check_in_lat']): ?>
                                            <div class="w-7 h-7 bg-rose-50 text-rose-500 rounded-lg flex items-center justify-center text-[10px]" title="GPS Recorded"><i class="bi bi-geo-alt-fill"></i></div>
                                        <?php endif; ?>
                                        <?php if ($h['check_in_photo']): ?>
                                            <div class="w-7 h-7 bg-blue-50 text-blue-500 rounded-lg flex items-center justify-center text-[10px]" title="Photo Recorded"><i class="bi bi-camera-fill"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-10 py-5 text-right">
                                        <?php if (!$h['check_in_time']): ?>
                                            <span class="px-3 py-1 rounded-lg bg-slate-50 text-slate-400 font-black text-[9px] uppercase tracking-wider">MISSING</span>
                                        <?php elseif ($h['check_in_status'] === 'มาสาย'): ?>
                                            <span class="px-3 py-1 rounded-lg bg-amber-50 text-amber-600 font-black text-[9px] uppercase tracking-wider">LATE</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 rounded-lg bg-emerald-50 text-emerald-600 font-black text-[9px] uppercase tracking-wider">ON TIME</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Live Clock
    const thDays = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    const thMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    function updateClock() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2,'0');
        const m = String(now.getMinutes()).padStart(2,'0');
        const s = String(now.getSeconds()).padStart(2,'0');
        document.getElementById('live-clock').textContent = `${h}:${m}:${s}`;
        document.getElementById('live-date').textContent = `วัน${thDays[now.getDay()]}ที่ ${now.getDate()} ${thMonths[now.getMonth()]} ${now.getFullYear()+543}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Camera
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas-preview');
    const btnCapture = document.getElementById('btn-capture');
    const btnRetake = document.getElementById('btn-retake');
    const photoInput = document.getElementById('photo-data');

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
        .then(stream => { video.srcObject = stream; })
        .catch(() => { 
            document.getElementById('camera-error').classList.remove('hidden'); 
            video.style.display = 'none';
            btnCapture.disabled = true;
        });

    btnCapture.addEventListener('click', () => {
        const ctx = canvas.getContext('2d');
        canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        photoInput.value = canvas.toDataURL('image/jpeg', 0.8);
        canvas.classList.remove('hidden');
        video.classList.add('hidden');
        btnCapture.classList.add('hidden');
        btnRetake.classList.remove('hidden');
    });

    btnRetake.addEventListener('click', () => {
        canvas.classList.add('hidden');
        video.classList.remove('hidden');
        btnCapture.classList.remove('hidden');
        btnRetake.classList.add('hidden');
        photoInput.value = '';
    });

    // GPS
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            document.getElementById('gps-lat').value = pos.coords.latitude;
            document.getElementById('gps-lng').value = pos.coords.longitude;
            document.getElementById('gps-status').textContent = 'Location Verified';
            document.getElementById('gps-coords').textContent = `Lat: ${pos.coords.latitude.toFixed(6)}, Lng: ${pos.coords.longitude.toFixed(6)}`;
        }, () => {
            document.getElementById('gps-status').textContent = 'Geolocation Denied';
            document.getElementById('gps-status').classList.add('text-rose-400');
        });
    }

    // Submit
    function submitLog(action) {
        const btn = document.getElementById('btn-submit');
        const oldText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin mr-2"></i> Processing...';
        btn.disabled = true;

        const fd = new FormData();
        fd.append('action', action);
        fd.append('lat', document.getElementById('gps-lat').value);
        fd.append('lng', document.getElementById('gps-lng').value);
        fd.append('photo', document.getElementById('photo-data').value);

        fetch('log_action.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        customClass: { popup: 'rounded-[2.5rem]', confirmButton: 'bg-emerald-600 rounded-2xl px-10 py-3 font-black text-xs uppercase' }
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message,
                        icon: 'error',
                        customClass: { popup: 'rounded-[2.5rem]', confirmButton: 'bg-rose-500 rounded-2xl px-10 py-3 font-black text-xs uppercase' }
                    });
                    btn.innerHTML = oldText;
                    btn.disabled = false;
                }
            })
            .catch(() => {
                Swal.fire('Error', 'Connection failed', 'error');
                btn.innerHTML = oldText;
                btn.disabled = false;
            });
    }
</script>

<?php require_once '../components/layout_end.php'; ?>
