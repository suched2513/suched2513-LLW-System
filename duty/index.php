<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();
$userId = $_SESSION['user_id'];
$today = date('Y-m-d');

// ── ดึงข้อมูลครูเวรที่เชื่อมกับ User ปัจจุบัน ──
$stmtT = $pdo->prepare("
    SELECT dt.id, dt.full_name 
    FROM duty_teachers dt
    JOIN att_teachers at ON at.id = dt.att_teacher_id
    WHERE at.llw_user_id = ? AND dt.status = 'active'
    LIMIT 1
");
$stmtT->execute([$userId]);
$myDutyTeacher = $stmtT->fetch(PDO::FETCH_ASSOC);

$assignment = null;
if ($myDutyTeacher) {
    // ── ค้นหาเวรวันนี้ (กะปัจจุบัน) ──
    $hour = (int)date('H');
    $currentShift = ($hour >= 17 || $hour < 5) ? 'night' : 'day';
    
    $stmtS = $pdo->prepare("
        SELECT * FROM duty_schedule 
        WHERE duty_date = ? AND teacher_id = ? AND shift = ?
        LIMIT 1
    ");
    $stmtS->execute([$today, $myDutyTeacher['id'], $currentShift]);
    $assignment = $stmtS->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        // ลองหากะอื่นในวันเดียวกัน
        $stmtS2 = $pdo->prepare("
            SELECT * FROM duty_schedule 
            WHERE duty_date = ? AND teacher_id = ?
            ORDER BY ABS(HOUR(TIMEDIFF(NOW(), CASE WHEN shift='day' THEN '08:00' ELSE '18:00' END))) ASC
            LIMIT 1
        ");
        $stmtS2->execute([$today, $myDutyTeacher['id']]);
        $assignment = $stmtS2->fetch(PDO::FETCH_ASSOC);
    }
}

// ── [ADMIN PREVIEW] ถ้าเป็น Super Admin และไม่เจอเวร ให้สร้าง Mock Assignment เพื่อดู UI ──
$isAdminPreview = false;
if (!$assignment && isset($_SESSION['llw_role']) && $_SESSION['llw_role'] === 'super_admin') {
    $isAdminPreview = true;
    $assignment = [
        'id' => 0,
        'duty_date' => $today,
        'shift' => (date('H') >= 17 || date('H') < 5) ? 'night' : 'day',
        'point_no' => 1,
        'teacher_id' => 0,
        'is_preview' => true
    ];
}

$pageTitle    = 'รายงานการปฏิบัติหน้าที่';
$pageSubtitle = 'ถ่ายภาพและส่งรายงานเวรประจำจุด';
$activeSystem = 'duty';

require_once __DIR__ . '/../components/layout_start.php';
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
        --glass-bg: rgba(255, 255, 255, 0.85);
    }
    
    .reporting-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 32px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.05);
    }
    
    .camera-btn {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: var(--primary-gradient);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: white;
        margin: 0 auto;
        box-shadow: 0 15px 30px rgba(37, 99, 235, 0.3);
        border: 8px solid rgba(255,255,255,0.8);
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        cursor: pointer;
        text-decoration: none;
    }
    
    .camera-btn:active {
        transform: scale(0.9);
        box-shadow: 0 5px 15px rgba(37, 99, 235, 0.2);
    }
    
    .point-badge {
        background: #f1f5f9;
        color: #1e293b;
        padding: 8px 20px;
        border-radius: 16px;
        font-weight: 800;
        display: inline-block;
        font-size: 0.9rem;
        margin-bottom: 12px;
    }
    
    .photo-grid {
        display: grid;
        grid-template-cols: repeat(auto-fill, minmax(100px, 1fr));
        gap: 12px;
        margin-top: 24px;
    }
    
    .photo-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .photo-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .photo-delete {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 24px;
        height: 24px;
        background: rgba(220, 38, 38, 0.8);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        cursor: pointer;
    }
    
    .upload-progress {
        height: 8px;
        border-radius: 4px;
        background: #f1f5f9;
        overflow: hidden;
        margin: 20px 0;
    }
    
    .progress-bar-fill {
        height: 100%;
        background: var(--primary-gradient);
        width: 0%;
        transition: width 0.5s ease;
    }

    .shift-tag {
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .shift-day { background: #fff7ed; color: #c2410c; }
    .shift-night { background: #f0f9ff; color: #0369a1; }
</style>

<div class="container py-4">
    <?php if (!$assignment): ?>
        <div class="reporting-card p-8 text-center animate__animated animate__fadeIn">
            <div class="mb-6">
                <i class="bi bi-calendar-x text-6xl text-slate-300"></i>
            </div>
            <h3 class="font-black text-slate-800 mb-2">ไม่พบตารางเวรของคุณในวันนี้</h3>
            <p class="text-slate-500 mb-6">หากคุณได้รับมอบหมายหน้างานพิเศษ กรุณาติดต่อผู้ประสานงานระบบเวร</p>
            <a href="../index.php" class="btn btn-slate-100 rounded-2xl px-6 py-3 font-bold text-slate-600">กลับหน้าหลัก</a>
        </div>
    <?php else: ?>
        <div class="reporting-card p-6 p-md-8 animate__animated animate__fadeInUp">
            <?php if ($isAdminPreview): ?>
                <div class="alert alert-info rounded-2xl border-0 bg-blue-50 text-blue-600 text-xs font-bold mb-6 flex items-center gap-2">
                    <i class="bi bi-info-circle-fill"></i>
                    โหมดทดสอบสำหรับ Admin: คุณสามารถทดสอบอัปโหลดรูปภาพได้ (ข้อมูลจะไม่ถูกบันทึกจริง)
                </div>
            <?php endif; ?>
            <div class="text-center mb-8">
                <div class="point-badge">
                    <i class="bi bi-geo-alt-fill me-2 text-primary"></i>
                    จุดปฏิบัติหน้าที่ที่ <?= $assignment['point_no'] ?>
                </div>
                <h2 class="font-black text-slate-800 mb-1">กะ<?= $assignment['shift'] === 'day' ? 'กลางวัน' : 'กลางคืน' ?></h2>
                <div class="flex items-center justify-center gap-2 text-slate-500 font-bold small">
                    <span><?= date('d/m/') . (date('Y') + 543) ?></span>
                    <span>•</span>
                    <span class="shift-tag <?= $assignment['shift'] === 'day' ? 'shift-day' : 'shift-night' ?>">
                        <?= strtoupper($assignment['shift']) ?>
                    </span>
                </div>
            </div>

            <!-- Details Section -->
            <div class="mb-8">
                <label class="text-xs font-black text-slate-600 uppercase tracking-widest mb-3 block">รายละเอียดการปฏิบัติหน้าที่</label>
                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 focus-within:ring-2 focus-within:ring-blue-500 transition-all">
                    <textarea id="reportNote" 
                              class="w-full bg-transparent border-0 outline-none text-sm text-slate-700 min-h-[120px] resize-none"
                              placeholder="ระบุรายละเอียด: ใคร? ทำอะไร? ที่ไหน? เมื่อไหร่? อย่างไร?"></textarea>
                </div>
                <p class="text-[10px] text-slate-400 mt-2 font-medium">บันทึกข้อมูลเหตุการณ์ปกติ หรือเหตุการณ์พิเศษที่พบระหว่างปฏิบัติหน้าที่</p>
            </div>

            <!-- Upload Zone -->
            <div class="text-center">
                <label for="photoCapture" class="camera-btn">
                    <i class="bi bi-camera-fill text-4xl"></i>
                    <span class="text-[10px] font-black uppercase tracking-widest mt-1">ถ่ายภาพ</span>
                </label>
                <input type="file" id="photoCapture" accept="image/*" capture="environment" class="hidden">
                <input type="file" id="photoGallery" accept="image/*" class="hidden" multiple>

                <div class="mt-4">
                    <label for="photoGallery" class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-black text-sm px-6 py-3 rounded-2xl cursor-pointer transition-all">
                        <i class="bi bi-images text-lg"></i> เลือกหลายรูปจากคลัง
                    </label>
                </div>
                <p class="text-slate-400 font-bold text-xs mt-3 uppercase tracking-widest">ถ่ายทีละรูป หรือเลือกหลายรูปพร้อมกัน</p>
            </div>

            <!-- Progress -->
            <div class="mt-8">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-xs font-black text-slate-600 uppercase">ความคืบหน้าการส่ง</span>
                    <span class="text-xs font-black text-primary" id="progressText">0 รูป</span>
                </div>
                <div class="upload-progress">
                    <div class="progress-bar-fill" id="progressBar"></div>
                </div>
            </div>

            <!-- Photos Grid -->
            <div class="photo-grid" id="photoGrid">
                <!-- Dynamic Content -->
            </div>
            
            <div class="mt-10">
                <button id="submitBtn" class="btn btn-primary w-100 rounded-[20px] py-4 font-black shadow-xl shadow-blue-200 disabled:opacity-50" disabled>
                    <i class="bi bi-send-fill me-2"></i> ส่งรายงานการปฏิบัติงาน
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    const photoCapture = document.getElementById('photoCapture');
    const photoGallery = document.getElementById('photoGallery');
    const photoGrid = document.getElementById('photoGrid');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const submitBtn = document.getElementById('submitBtn');

    let photos = [];
    const assignmentId = <?= json_encode($assignment['id'] ?? 0) ?>;
    const requiredPhotos = 4;

    function addFiles(files) {
        Array.from(files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(re) {
                const id = Date.now() + Math.random();
                photos.push({ id, file, url: re.target.result });
                renderPhotos();
            };
            reader.readAsDataURL(file);
        });
    }

    photoCapture.addEventListener('change', function(e) {
        addFiles(e.target.files);
        photoCapture.value = '';
    });

    photoGallery.addEventListener('change', function(e) {
        addFiles(e.target.files);
        photoGallery.value = '';
    });

    function renderPhotos() {
        photoGrid.innerHTML = '';
        photos.forEach(p => {
            const div = document.createElement('div');
            div.className = 'photo-item animate__animated animate__zoomIn';
            div.innerHTML = `
                <img src="${p.url}">
                <div class="photo-delete" onclick="removePhoto(${p.id})"><i class="bi bi-x-lg"></i></div>
            `;
            photoGrid.appendChild(div);
        });
        
        const count = photos.length;
        const progress = Math.min((count / requiredPhotos) * 100, 100);
        progressBar.style.width = `${progress}%`;
        progressText.innerText = `${count} รูป`;
        
        submitBtn.disabled = count === 0;
        
        if (count >= requiredPhotos) {
            progressText.classList.add('text-success');
            progressBar.classList.add('bg-success');
        } else {
            progressText.classList.remove('text-success');
            progressBar.classList.remove('bg-success');
        }
    }

    function removePhoto(id) {
        photos = photos.filter(p => p.id !== id);
        renderPhotos();
    }

    submitBtn.addEventListener('click', async function() {
        if (photos.length === 0) return;
        
        const reportNote = document.getElementById('reportNote').value;
        
        <?php if ($isAdminPreview): ?>
            Swal.fire({
                icon: 'info',
                title: 'ทดสอบสำเร็จ',
                html: `<b>บันทึกข้อความ:</b><br>${reportNote}<br><br>ในโหมด Admin Preview ระบบจะไม่บันทึกข้อมูลจริงครับ`,
                confirmButtonColor: '#2563eb'
            });
            return;
        <?php endif; ?>

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> กำลังส่ง...';
        
        const formData = new FormData();
        formData.append('assignment_id', assignmentId);
        formData.append('report_note', reportNote);
        photos.forEach((p, i) => {
            formData.append(`photos[]`, p.file);
        });

        try {
            const res = await fetch('api/save_report.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'ส่งรายงานสำเร็จ!',
                    text: 'ขอบคุณที่ปฏิบัติหน้าที่อย่างเต็มกำลังครับ',
                    confirmButtonColor: '#2563eb',
                    timer: 3000
                }).then(() => {
                    window.location.reload();
                });
            } else {
                throw new Error(data.message || 'เกิดข้อผิดพลาด');
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: err.message,
                confirmButtonColor: '#2563eb'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i> ส่งรายงานการปฏิบัติงาน';
        }
    });
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
