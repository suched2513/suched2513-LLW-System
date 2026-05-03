<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
$allowed = ['att_teacher', 'super_admin', 'wfh_admin'];
if (!in_array($_SESSION['llw_role'], $allowed, true)) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$session_id = (int)($_GET['session_id'] ?? 0);
if (!$session_id) { header('Location: /club/index.php'); exit(); }

$pdo       = getPdo();
$userRole  = $_SESSION['llw_role'];
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT cs.*, cg.name AS club_name, cg.teacher_id, cg.id AS club_id
    FROM club_sessions cs JOIN club_groups cg ON cg.id = cs.club_id
    WHERE cs.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) { header('Location: /club/index.php'); exit(); }

if ($userRole === 'att_teacher' && (int)$session['teacher_id'] !== $teacherId) {
    header('Location: /club/index.php'); exit();
}

$log = $pdo->prepare("SELECT * FROM club_activity_logs WHERE session_id = ? LIMIT 1");
$log->execute([$session_id]);
$existing = $log->fetch(PDO::FETCH_ASSOC);

$existingPhotos = [];
if ($existing && $existing['photo_paths']) {
    $existingPhotos = json_decode($existing['photo_paths'], true) ?: [];
}

$thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$dp = $session['session_date'] ? explode('-', $session['session_date']) : [];
$dateStr = count($dp) === 3 ? ((int)$dp[2].' '.$thaiMonths[(int)$dp[1]].' '.((int)$dp[0]+543)) : '-';

$pageTitle    = 'บันทึกกิจกรรม';
$pageSubtitle = htmlspecialchars($session['club_name'], ENT_QUOTES, 'UTF-8') . ' – ' . $dateStr;
$activeSystem = 'club';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">
<div class="row justify-content-center">
<div class="col-lg-8">

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3">
            <h5 class="mb-1 fw-black"><?= htmlspecialchars($session['club_name'], ENT_QUOTES, 'UTF-8') ?></h5>
            <div class="text-muted small">
                <i class="fas fa-calendar me-1"></i><?= $dateStr ?>
                <?= $session['topic'] ? ' · <strong>'.htmlspecialchars($session['topic'],ENT_QUOTES,'UTF-8').'</strong>' : '' ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 py-3">
            <h6 class="mb-0 fw-bold"><i class="fas fa-camera me-2" style="color:#7c3aed"></i>บันทึกกิจกรรม</h6>
        </div>
        <div class="card-body">
            <form id="activityForm">
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase text-muted">บันทึกกิจกรรม</label>
                    <textarea id="f_content" class="form-control rounded-3" rows="5"
                              placeholder="บันทึกสิ่งที่ทำในคาบนี้..."><?= htmlspecialchars($existing['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Existing Photos -->
                <?php if (!empty($existingPhotos)): ?>
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase text-muted">รูปภาพที่บันทึกไว้</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($existingPhotos as $photo): ?>
                        <img src="<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>" class="rounded-3 border" style="height:100px;width:100px;object-fit:cover">
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Upload new photos -->
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase text-muted">เพิ่มรูปภาพ (สูงสุด 5 รูป)</label>
                    <input type="file" id="f_photos" accept=".jpg,.jpeg,.png" multiple class="form-control rounded-3">
                    <div id="photo_preview" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>

                <button type="button" onclick="saveActivity()" class="btn w-100 text-white rounded-3 fw-bold" style="background:#7c3aed">
                    <i class="fas fa-save me-1"></i>บันทึกกิจกรรม
                </button>
            </form>
        </div>
    </div>

</div></div>
</div>

<script>
const SESSION_ID = <?= json_encode($session_id) ?>;

document.getElementById('f_photos').addEventListener('change', function() {
    const preview = document.getElementById('photo_preview');
    preview.innerHTML = '';
    const files = Array.from(this.files).slice(0, 5);
    files.forEach(f => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'rounded-3 border';
            img.style.cssText = 'height:80px;width:80px;object-fit:cover';
            preview.appendChild(img);
        };
        reader.readAsDataURL(f);
    });
});

async function saveActivity() {
    const content = document.getElementById('f_content').value.trim();
    const files   = document.getElementById('f_photos').files;

    const fd = new FormData();
    fd.append('session_id', SESSION_ID);
    fd.append('content', content);
    Array.from(files).slice(0, 5).forEach(f => fd.append('photos[]', f));

    const btn = document.querySelector('button[onclick="saveActivity()"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>กำลังบันทึก...';

    try {
        const res  = await fetch('/club/api/save_activity.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.status === 'success') {
            await Swal.fire({ icon:'success', title:'บันทึกกิจกรรมสำเร็จ', timer:1500, showConfirmButton:false });
            location.reload();
        } else {
            Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:data.message, confirmButtonColor:'#7c3aed' });
        }
    } catch(e) {
        Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:'ไม่สามารถเชื่อมต่อได้', confirmButtonColor:'#7c3aed' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i>บันทึกกิจกรรม';
    }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
