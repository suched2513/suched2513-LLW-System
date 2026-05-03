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
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semester     = (int)($_POST['semester'] ?? 1);
    $year         = (int)($_POST['year'] ?? date('Y'));
    $reg_open     = trim($_POST['reg_open'] ?? '');
    $reg_close    = trim($_POST['reg_close'] ?? '');
    $allow_change = isset($_POST['allow_change']) ? 1 : 0;
    $is_active    = isset($_POST['is_active']) ? 1 : 0;

    try {
        // Deactivate all first, then set active on current
        if ($is_active) {
            $pdo->exec("UPDATE club_settings SET is_active = 0");
        }
        $pdo->prepare("INSERT INTO club_settings (semester, year, reg_open, reg_close, allow_change, is_active)
                       VALUES (?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE reg_open=VALUES(reg_open), reg_close=VALUES(reg_close),
                       allow_change=VALUES(allow_change), is_active=VALUES(is_active)")
            ->execute([$semester, $year, $reg_open ?: null, $reg_close ?: null, $allow_change, $is_active]);
        $msg = 'บันทึกการตั้งค่าสำเร็จ';
    } catch (Exception $e) {
        error_log('[club settings] ' . $e->getMessage());
        $err = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
    }
}

$settings = $pdo->query("SELECT * FROM club_settings ORDER BY year DESC, semester DESC")->fetchAll(PDO::FETCH_ASSOC);
$active   = null;
foreach ($settings as $s) { if ($s['is_active']) { $active = $s; break; } }

$pageTitle    = 'ตั้งค่าระบบชุมนุม';
$pageSubtitle = 'กำหนดภาคเรียนและช่วงลงทะเบียน';
$activeSystem = 'club';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show rounded-3"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show rounded-3"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form -->
    <div class="col-lg-6">
        <div class="card rounded-3 border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-cog text-violet-600 me-2" style="color:#7c3aed"></i>ตั้งค่าการลงทะเบียน</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">ภาคเรียน</label>
                            <select name="semester" class="form-select rounded-3">
                                <option value="1" <?= ($active['semester'] ?? 1) == 1 ? 'selected' : '' ?>>1</option>
                                <option value="2" <?= ($active['semester'] ?? 1) == 2 ? 'selected' : '' ?>>2</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">ปีการศึกษา (พ.ศ.)</label>
                            <input type="number" name="year" class="form-control rounded-3"
                                   value="<?= htmlspecialchars($active['year'] ?? (date('Y') + 543), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">เปิดลงทะเบียน</label>
                            <input type="datetime-local" name="reg_open" class="form-control rounded-3"
                                   value="<?= $active ? str_replace(' ', 'T', substr($active['reg_open'] ?? '', 0, 16)) : '' ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">ปิดลงทะเบียน</label>
                            <input type="datetime-local" name="reg_close" class="form-control rounded-3"
                                   value="<?= $active ? str_replace(' ', 'T', substr($active['reg_close'] ?? '', 0, 16)) : '' ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="allow_change" id="allow_change"
                                       <?= ($active['allow_change'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="allow_change">อนุญาตให้เปลี่ยนชุมนุมได้</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                       <?= ($active['is_active'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-success" for="is_active">เปิดใช้งานภาคเรียนนี้</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary rounded-3 w-100">
                                <i class="fas fa-save me-1"></i>บันทึกการตั้งค่า
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Status -->
    <div class="col-lg-6">
        <div class="card rounded-3 border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-info"></i>สถานะปัจจุบัน</h6>
            </div>
            <div class="card-body">
                <?php if ($active): ?>
                <div class="text-center py-2">
                    <span class="badge fs-6 rounded-pill <?= $active['is_active'] ? 'bg-success' : 'bg-secondary' ?> mb-3">
                        <?= $active['is_active'] ? '● เปิดใช้งาน' : '○ ปิดใช้งาน' ?>
                    </span>
                    <div class="fw-bold fs-5">ภาคเรียน <?= $active['semester'] ?> / <?= $active['year'] ?></div>
                    <?php if ($active['reg_open']): ?>
                    <div class="text-muted small mt-2">เปิดลงทะเบียน: <?= $active['reg_open'] ?></div>
                    <div class="text-muted small">ปิดลงทะเบียน: <?= $active['reg_close'] ?: '-' ?></div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <?php
                        $now = date('Y-m-d H:i:s');
                        if ($active['reg_open'] && $now >= $active['reg_open'] && (!$active['reg_close'] || $now <= $active['reg_close'])):
                        ?>
                        <span class="badge bg-success rounded-pill">กำลังเปิดรับลงทะเบียน</span>
                        <?php elseif ($active['reg_close'] && $now > $active['reg_close']): ?>
                        <span class="badge bg-danger rounded-pill">หมดเวลาลงทะเบียน</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark rounded-pill">ยังไม่ถึงเวลา</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>ยังไม่มีการตั้งค่า</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
