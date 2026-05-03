<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
if ($_SESSION['llw_role'] !== 'super_admin') {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$pdo = getPdo();
$id  = (int)($_GET['id'] ?? 0);
$club = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM club_groups WHERE id = ?");
    $stmt->execute([$id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$club) { header('Location: /club/index.php'); exit(); }
}

$teachers = $pdo->query("SELECT id, name FROM att_teachers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$cfg      = $pdo->query("SELECT * FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$defSem   = $cfg['semester'] ?? 1;
$defYear  = $cfg['year']     ?? (date('Y') + 543);

$pageTitle    = $id ? 'แก้ไขชุมนุม' : 'สร้างชุมนุม';
$pageSubtitle = $id ? 'แก้ไขข้อมูลชุมนุม' : 'เพิ่มชุมนุมใหม่';
$activeSystem = 'club';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">
<div class="row justify-content-center">
<div class="col-lg-8">

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="mb-0 fw-bold"><i class="fas fa-users me-2" style="color:#7c3aed"></i><?= $id ? 'แก้ไข' : 'สร้าง' ?>ชุมนุม</h6>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold small text-uppercase text-muted">ชื่อชุมนุม <span class="text-danger">*</span></label>
                <input type="text" id="f_name" class="form-control rounded-3"
                       value="<?= htmlspecialchars($club['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น ชุมนุมคอมพิวเตอร์">
            </div>
            <div class="col-12">
                <label class="form-label fw-bold small text-uppercase text-muted">คำอธิบาย</label>
                <textarea id="f_description" class="form-control rounded-3" rows="2"><?= htmlspecialchars($club['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold small text-uppercase text-muted">วัตถุประสงค์</label>
                <textarea id="f_objectives" class="form-control rounded-3" rows="2"><?= htmlspecialchars($club['objectives'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small text-uppercase text-muted">ครูที่ปรึกษา</label>
                <select id="f_teacher_id" class="form-select rounded-3">
                    <option value="">-- เลือกครู --</option>
                    <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($club['teacher_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small text-uppercase text-muted">ห้องประชุม</label>
                <input type="text" id="f_room" class="form-control rounded-3"
                       value="<?= htmlspecialchars($club['room'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น ห้องคอมพิวเตอร์ 1">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-uppercase text-muted">ความจุ (คน)</label>
                <input type="number" id="f_max_capacity" class="form-control rounded-3" min="1"
                       value="<?= htmlspecialchars($club['max_capacity'] ?? 30, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-uppercase text-muted">ภาคเรียน</label>
                <select id="f_semester" class="form-select rounded-3">
                    <option value="1" <?= ($club['semester'] ?? $defSem) == 1 ? 'selected' : '' ?>>1</option>
                    <option value="2" <?= ($club['semester'] ?? $defSem) == 2 ? 'selected' : '' ?>>2</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-uppercase text-muted">ปีการศึกษา</label>
                <input type="number" id="f_year" class="form-control rounded-3"
                       value="<?= htmlspecialchars($club['year'] ?? $defYear, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small text-uppercase text-muted">เกณฑ์ผ่าน (%)</label>
                <input type="number" id="f_pass_threshold" class="form-control rounded-3" min="0" max="100"
                       value="<?= htmlspecialchars($club['pass_threshold'] ?? 80, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small text-uppercase text-muted">สถานะ</label>
                <select id="f_status" class="form-select rounded-3">
                    <option value="draft"    <?= ($club['status'] ?? 'draft') === 'draft'    ? 'selected' : '' ?>>ร่าง</option>
                    <option value="open"     <?= ($club['status'] ?? '') === 'open'     ? 'selected' : '' ?>>เปิดรับสมัคร</option>
                    <option value="closed"   <?= ($club['status'] ?? '') === 'closed'   ? 'selected' : '' ?>>ปิดรับ</option>
                    <option value="archived" <?= ($club['status'] ?? '') === 'archived' ? 'selected' : '' ?>>เก็บถาวร</option>
                </select>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button onclick="saveClub()" class="btn text-white rounded-3 flex-fill fw-bold" style="background:#7c3aed">
                <i class="fas fa-save me-1"></i><?= $id ? 'บันทึกการแก้ไข' : 'สร้างชุมนุม' ?>
            </button>
            <a href="/club/index.php" class="btn btn-outline-secondary rounded-3 px-4">ยกเลิก</a>
        </div>
    </div>
</div>

</div></div>
</div>

<script>
const CLUB_ID = <?= json_encode($id) ?>;

async function saveClub() {
    const name = document.getElementById('f_name').value.trim();
    if (!name) { Swal.fire({ icon:'warning', title:'กรุณากรอกชื่อชุมนุม', confirmButtonColor:'#7c3aed' }); return; }

    const body = {
        id: CLUB_ID,
        name,
        description:    document.getElementById('f_description').value.trim(),
        objectives:     document.getElementById('f_objectives').value.trim(),
        teacher_id:     document.getElementById('f_teacher_id').value,
        room:           document.getElementById('f_room').value.trim(),
        max_capacity:   parseInt(document.getElementById('f_max_capacity').value) || 30,
        semester:       parseInt(document.getElementById('f_semester').value),
        year:           parseInt(document.getElementById('f_year').value),
        pass_threshold: parseInt(document.getElementById('f_pass_threshold').value) || 80,
        status:         document.getElementById('f_status').value,
    };

    const res  = await fetch('/club/api/save_club.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
    const data = await res.json();
    if (data.status === 'success') {
        await Swal.fire({ icon:'success', title:'สำเร็จ!', text:data.message, timer:1500, showConfirmButton:false });
        window.location.href = '/club/index.php';
    } else {
        Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:data.message, confirmButtonColor:'#7c3aed' });
    }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
