<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'club_admin'])) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$pdo = getPdo();
$msg = '';
$err = '';

// Handle Direct Quick-Activate Action
if (isset($_GET['activate_sem'], $_GET['activate_year'])) {
    $actSem  = (int)$_GET['activate_sem'];
    $actYear = (int)$_GET['activate_year'];
    if ($actSem >= 1 && $actSem <= 2 && $actYear >= 2500) {
        try {
            $pdo->beginTransaction();
            $pdo->exec("UPDATE club_settings SET is_active = 0");
            $pdo->prepare("
                INSERT INTO club_settings (semester, year, reg_open, reg_close, allow_change, is_active)
                VALUES (?, ?, NULL, NULL, 1, 1)
                ON DUPLICATE KEY UPDATE is_active = 1
            ")->execute([$actSem, $actYear]);
            $pdo->commit();
            header('Location: /club/settings.php?msg=' . urlencode("เปิดใช้งานภาคเรียนที่ $actSem/$actYear สำเร็จ"));
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = 'เกิดข้อผิดพลาดในการเปิดใช้งาน: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semester     = (int)($_POST['semester'] ?? 2);
    $year         = (int)($_POST['year'] ?? 2569);
    $reg_open     = trim($_POST['reg_open'] ?? '');
    $reg_close    = trim($_POST['reg_close'] ?? '');
    $allow_change = isset($_POST['allow_change']) ? 1 : 0;
    $is_active    = isset($_POST['is_active']) ? 1 : 0;

    try {
        if ($is_active) {
            $pdo->exec("UPDATE club_settings SET is_active = 0");
        }
        $pdo->prepare("INSERT INTO club_settings (semester, year, reg_open, reg_close, allow_change, is_active)
                       VALUES (?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE reg_open=VALUES(reg_open), reg_close=VALUES(reg_close),
                       allow_change=VALUES(allow_change), is_active=VALUES(is_active)")
            ->execute([$semester, $year, $reg_open ?: null, $reg_close ?: null, $allow_change, $is_active]);
        $msg = "บันทึกการตั้งค่าภาคเรียนที่ $semester/$year สำเร็จ";
    } catch (Exception $e) {
        error_log('[club settings] ' . $e->getMessage());
        $err = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
    }
}

if (isset($_GET['msg'])) {
    $msg = trim($_GET['msg']);
}

$settings = $pdo->query("SELECT * FROM club_settings ORDER BY year DESC, semester DESC")->fetchAll(PDO::FETCH_ASSOC);
$active   = null;
foreach ($settings as $s) { if ($s['is_active']) { $active = $s; break; } }

// Form default values (prefer active, fallback to semester 2 / 2569)
$formSem   = $active ? (int)$active['semester'] : 2;
$formYear  = $active ? (int)$active['year']     : 2569;
$editSem   = isset($_GET['edit_sem'])  ? (int)$_GET['edit_sem']  : $formSem;
$editYear  = isset($_GET['edit_year']) ? (int)$_GET['edit_year'] : $formYear;

$editRow = null;
foreach ($settings as $s) {
    if ((int)$s['semester'] === $editSem && (int)$s['year'] === $editYear) {
        $editRow = $s;
        break;
    }
}
if (!$editRow && $active) {
    $editRow = $active;
}

// Count clubs per term
$clubCounts = [];
$countsRaw = $pdo->query("SELECT semester, year, COUNT(*) AS cnt FROM club_groups GROUP BY semester, year")->fetchAll(PDO::FETCH_ASSOC);
foreach ($countsRaw as $cr) {
    $clubCounts[$cr['semester'] . '-' . $cr['year']] = (int)$cr['cnt'];
}

$pageTitle    = 'ตั้งค่าระบบชุมนุม';
$pageSubtitle = 'กำหนดภาคเรียนและช่วงเปิด-ปิดลงทะเบียน';
$activeSystem = 'club';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm border-0"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Quick Actions Banner -->
<div class="card border-0 shadow-sm rounded-3 mb-4" style="background: linear-gradient(135deg, #f5f3ff, #ede9fe);">
    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge text-white px-2 py-1 rounded-pill" style="background:#7c3aed"><i class="fas fa-bolt me-1"></i>Quick Switch</span>
                <h5 class="fw-black mb-0 text-dark">สลับเข้าสู่ภาคเรียนที่ 2/2569</h5>
            </div>
            <p class="text-muted small mb-0">ระบบพร้อมเปิดใช้งานเทอม 2/2569 ทันที พร้อมฟังก์ชันคัดลอกรายชื่อชุมนุมเดิมโดยอัตโนมัติ</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if (!$active || $active['semester'] != 2 || $active['year'] != 2569): ?>
            <a href="/club/settings.php?activate_sem=2&activate_year=2569" class="btn btn-sm text-white fw-bold px-3 py-2 rounded-3 shadow-sm" style="background:#7c3aed">
                <i class="fas fa-check-circle me-1"></i>เปิดใช้งานเทอม 2/2569 เดี๋ยวนี้
            </a>
            <?php else: ?>
            <span class="badge bg-success rounded-pill px-3 py-2 fs-6">
                <i class="fas fa-check-circle me-1"></i>เทอม 2/2569 กำลังเปิดใช้งานอยู่
            </span>
            <?php endif; ?>

            <button type="button" onclick="confirmCloneClubs()" class="btn btn-sm btn-outline-primary fw-bold px-3 py-2 rounded-3 bg-white">
                <i class="fas fa-clone me-1"></i>คัดลอกชุมนุมจากเทอม 1 สู่เทอม 2
            </button>
            <a href="/club/index.php" class="btn btn-sm btn-outline-secondary px-3 py-2 rounded-3 bg-white">
                <i class="fas fa-arrow-left me-1"></i>กลับหน้ารายการชุมนุม
            </a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Form -->
    <div class="col-lg-7">
        <div class="card rounded-3 border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="fas fa-cog text-violet-600 me-2" style="color:#7c3aed"></i>แบบฟอร์มกำหนดภาคเรียนและการลงทะเบียน</h6>
                <?php if ($editRow): ?>
                <span class="badge bg-light text-secondary border small">กำลังแก้ไข: เทอม <?= $editRow['semester'] ?>/<?= $editRow['year'] ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">ภาคเรียน <span class="text-danger">*</span></label>
                            <select name="semester" class="form-select rounded-3">
                                <option value="1" <?= ($editRow['semester'] ?? 2) == 1 ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                                <option value="2" <?= ($editRow['semester'] ?? 2) == 2 ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">ปีการศึกษา (พ.ศ.) <span class="text-danger">*</span></label>
                            <input type="number" name="year" class="form-control rounded-3"
                                   value="<?= htmlspecialchars($editRow['year'] ?? 2569, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">วัน-เวลาเปิดลงทะเบียน</label>
                            <input type="datetime-local" name="reg_open" class="form-control rounded-3"
                                   value="<?= $editRow && $editRow['reg_open'] ? str_replace(' ', 'T', substr($editRow['reg_open'], 0, 16)) : '' ?>">
                            <div class="form-text small">หากเว้นว่างไว้ จะเปิดตลอดเวลา</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">วัน-เวลาปิดลงทะเบียน</label>
                            <input type="datetime-local" name="reg_close" class="form-control rounded-3"
                                   value="<?= $editRow && $editRow['reg_close'] ? str_replace(' ', 'T', substr($editRow['reg_close'], 0, 16)) : '' ?>">
                            <div class="form-text small">หากเว้นว่างไว้ จะไม่กำหนดวันสิ้นสุด</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch py-1">
                                <input class="form-check-input" type="checkbox" name="allow_change" id="allow_change"
                                       <?= (!isset($editRow['allow_change']) || $editRow['allow_change']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="allow_change">อนุญาตให้นักเรียนเปลี่ยนชุมนุมได้ (ช่วงเปิดลงทะเบียน)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch py-1">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                       <?= ($editRow['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-success" for="is_active">เปิดใช้งานภาคเรียนนี้ในระบบชุมนุม (Active Semester)</label>
                            </div>
                        </div>
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn text-white rounded-3 w-100 py-2 fw-bold" style="background:#7c3aed">
                                <i class="fas fa-save me-1"></i>บันทึกการตั้งค่าภาคเรียน
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Active Status Card -->
    <div class="col-lg-5">
        <div class="card rounded-3 border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-info"></i>สถานะเทอมที่เปิดใช้งานอยู่ปัจจุบัน</h6>
            </div>
            <div class="card-body">
                <?php if ($active): ?>
                <div class="text-center py-3">
                    <span class="badge fs-6 rounded-pill bg-success mb-3 px-3 py-2 shadow-sm">
                        <i class="fas fa-check-circle me-1"></i>กำลังเปิดใช้งาน
                    </span>
                    <div class="fw-black fs-3 text-dark">ภาคเรียนที่ <?= $active['semester'] ?> / <?= $active['year'] ?></div>
                    <div class="text-muted small mt-1">
                        ชุมนุมในเทอมนี้: <b class="text-dark"><?= $clubCounts[$active['semester'] . '-' . $active['year']] ?? 0 ?></b> ชุมนุม
                    </div>

                    <div class="border rounded-3 p-3 mt-3 bg-light text-start small">
                        <div class="mb-1"><i class="fas fa-clock text-muted me-2"></i>เปิดลงทะเบียน: <b><?= $active['reg_open'] ?: 'เปิดตลอดเวลา (ไม่จำกัดวันเริ่ม)' ?></b></div>
                        <div class="mb-1"><i class="fas fa-calendar-times text-muted me-2"></i>ปิดลงทะเบียน: <b><?= $active['reg_close'] ?: 'ไม่จำกัดวันสิ้นสุด' ?></b></div>
                        <div><i class="fas fa-exchange-alt text-muted me-2"></i>การเปลี่ยนชุมนุม: <b><?= $active['allow_change'] ? 'อนุญาต' : 'ไม่อนุญาต' ?></b></div>
                    </div>

                    <div class="mt-3">
                        <?php
                        $now = date('Y-m-d H:i:s');
                        if ($active['reg_open'] && $now < $active['reg_open']):
                        ?>
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><i class="fas fa-hourglass-start me-1"></i>ยังไม่ถึงเวลาเปิดลงทะเบียน</span>
                        <?php elseif ($active['reg_close'] && $now > $active['reg_close']): ?>
                        <span class="badge bg-danger rounded-pill px-3 py-2"><i class="fas fa-times-circle me-1"></i>หมดเวลาลงทะเบียนแล้ว</span>
                        <?php else: ?>
                        <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-door-open me-1"></i>ระบบกำลังเปิดรับลงทะเบียน</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-2 d-block opacity-50"></i>
                    ยังไม่มีภาคเรียนที่ถูกเปิดใช้งาน
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- History / List of Configured Semesters -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>รายการภาคเรียนทั้งหมดที่ตั้งค่าไว้</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-3 py-3 small text-muted text-uppercase fw-bold">ภาคเรียน / ปีการศึกษา</th>
                        <th class="px-3 py-3 small text-muted text-uppercase fw-bold text-center">สถานะ</th>
                        <th class="px-3 py-3 small text-muted text-uppercase fw-bold text-center">จำนวนชุมนุม</th>
                        <th class="px-3 py-3 small text-muted text-uppercase fw-bold">ช่วงเวลาลงทะเบียน</th>
                        <th class="px-3 py-3 small text-muted text-uppercase fw-bold text-center">เปลี่ยนชุมนุม</th>
                        <th class="px-3 py-3 small text-muted text-uppercase fw-bold text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($settings)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">ยังไม่มีรายการ</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($settings as $s): ?>
                    <?php 
                        $key = $s['semester'] . '-' . $s['year'];
                        $cnt = $clubCounts[$key] ?? 0;
                    ?>
                    <tr class="<?= $s['is_active'] ? 'table-success bg-opacity-25' : '' ?>">
                        <td class="px-3 py-3">
                            <span class="fw-bold">ภาคเรียนที่ <?= $s['semester'] ?> / <?= $s['year'] ?></span>
                            <?php if ($s['is_active']): ?>
                            <span class="badge bg-success rounded-pill ms-2 small">ปัจจุบัน</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <?php if ($s['is_active']): ?>
                            <span class="badge bg-success rounded-pill">● เปิดใช้งาน</span>
                            <?php else: ?>
                            <span class="badge bg-secondary rounded-pill">○ ปิดใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="badge bg-light text-dark border"><?= $cnt ?> ชุมนุม</span>
                        </td>
                        <td class="px-3 py-3 small">
                            <?= $s['reg_open'] ? htmlspecialchars($s['reg_open'], ENT_QUOTES, 'UTF-8') : 'ไม่กำหนด' ?> 
                            ถึง 
                            <?= $s['reg_close'] ? htmlspecialchars($s['reg_close'], ENT_QUOTES, 'UTF-8') : 'ไม่กำหนด' ?>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <?= $s['allow_change'] ? '<span class="text-success"><i class="fas fa-check"></i> ได้</span>' : '<span class="text-muted"><i class="fas fa-times"></i> ไม่ได้</span>' ?>
                        </td>
                        <td class="px-3 py-3 text-center text-nowrap">
                            <?php if (!$s['is_active']): ?>
                            <a href="/club/settings.php?activate_sem=<?= $s['semester'] ?>&activate_year=<?= $s['year'] ?>" class="btn btn-sm btn-outline-success rounded-2 me-1">
                                <i class="fas fa-power-off me-1"></i>เปิดใช้งานเทอมนี้
                            </a>
                            <?php endif; ?>
                            <a href="/club/settings.php?edit_sem=<?= $s['semester'] ?>&edit_year=<?= $s['year'] ?>" class="btn btn-sm btn-outline-secondary rounded-2">
                                <i class="fas fa-edit me-1"></i>แก้ไข
                            </a>
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
async function confirmCloneClubs() {
    const { isConfirmed } = await Swal.fire({
        icon: 'question',
        title: 'คัดลอกชุมนุมสู่ภาคเรียนที่ 2?',
        html: `ต้องการคัดลอกรายชื่อชุมนุมเดิมจาก <b>ภาคเรียนที่ 1/2569</b> มายัง <b>ภาคเรียนที่ 2/2569</b> หรือไม่?<br><small class="text-muted">ระบบจะคัดลอกชื่อชุมนุม ครูที่ปรึกษา และห้อง โดยเปิดสถานะเป็น 'เปิดรับ' และเริ่มรับนักเรียนใหม่</small>`,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-clone me-1"></i>ยืนยันคัดลอก',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#7c3aed'
    });
    if (!isConfirmed) return;

    Swal.fire({ title: 'กำลังคัดลอกข้อมูล...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('/club/api/clone_clubs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ from_semester: 1, from_year: 2569, to_semester: 2, to_year: 2569 })
        });
        const data = await res.json();
        if (data.status === 'success') {
            await Swal.fire({
                icon: 'success',
                title: 'คัดลอกเรียบร้อย',
                text: data.message,
                confirmButtonColor: '#7c3aed'
            });
            window.location.reload();
        } else {
            Swal.fire({ icon: 'warning', title: 'แจ้งเตือน', text: data.message, confirmButtonColor: '#7c3aed' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message, confirmButtonColor: '#7c3aed' });
    }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
