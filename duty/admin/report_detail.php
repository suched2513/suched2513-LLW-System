<?php
/**
 * duty/admin/report_detail.php — รายละเอียดรายงาน 1 จุด/วัน
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();
$reportId = (int)($_GET['id'] ?? 0);

if (!$reportId) { header('Location: reports.php'); exit(); }

// ── ดึงข้อมูล report ──
$stmtR = $pdo->prepare(
    "SELECT dr.*, dt.full_name AS teacher_name, dt.telegram_username
     FROM duty_reports dr
     LEFT JOIN duty_teachers dt ON dt.id = dr.teacher_id
     WHERE dr.id = ?"
);
$stmtR->execute([$reportId]);
$report = $stmtR->fetch(PDO::FETCH_ASSOC);

if (!$report) { header('Location: reports.php'); exit(); }

// ── ลบรูป (admin) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_photo') {
    csrf_verify();
    $photoId = (int)($_POST['photo_id'] ?? 0);

    // ดึง path ก่อนลบ
    $stmtP = $pdo->prepare("SELECT file_path, thumbnail_path FROM duty_report_photos WHERE id = ? AND report_id = ?");
    $stmtP->execute([$photoId, $reportId]);
    $photo = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($photo) {
        // Soft delete
        $upd = $pdo->prepare(
            "UPDATE duty_report_photos SET is_deleted=1, deleted_by=?, deleted_at=NOW() WHERE id=?"
        );
        $upd->execute([$_SESSION['user_id'] ?? 0, $photoId]);

        // อัปเดต status ของ report
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM duty_report_photos WHERE report_id=? AND is_deleted=0");
        $cnt->execute([$reportId]);
        $remaining = (int)$cnt->fetchColumn();

        $stmtDS = $pdo->query("SELECT svalue FROM duty_settings WHERE skey='photos_required_per_point'");
        $required = (int)($stmtDS->fetchColumn() ?? 3);

        $newStatus = $remaining >= $required ? 'complete' : ($remaining > 0 ? 'partial' : 'pending');
        $completedAt = ($newStatus === 'complete') ? date('Y-m-d H:i:s') : null;

        $pdo->prepare("UPDATE duty_reports SET status=?, completed_at=? WHERE id=?")
            ->execute([$newStatus, $completedAt, $reportId]);

        header('Location: report_detail.php?id=' . $reportId . '&deleted=1');
        exit();
    }
}

// ── ดึงรูปทั้งหมด ──
$stmtPh = $pdo->prepare(
    "SELECT * FROM duty_report_photos
     WHERE report_id = ? AND is_deleted = 0
     ORDER BY received_at ASC"
);
$stmtPh->execute([$reportId]);
$photos = $stmtPh->fetchAll(PDO::FETCH_ASSOC);

$shiftLabel = ['day' => '☀️ กลางวัน', 'night' => '🌙 กลางคืน'];
$thaiDate   = date('d/m/') . (date('Y') + 543);

$pageTitle    = 'รายละเอียดรายงานจุดที่ ' . $report['point_no'];
$pageSubtitle = ($shiftLabel[$report['shift']] ?? $report['shift']) . ' — ' . $thaiDate;
$activeSystem = 'duty';

require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if (isset($_GET['deleted'])): ?>
<script>Swal.fire({icon:'success',title:'ลบรูปแล้ว',timer:1500,showConfirmButton:false});</script>
<?php endif; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">

<!-- Back -->
<div class="mb-3">
    <a href="reports.php?date=<?= $report['duty_date'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>กลับหน้ารายงาน
    </a>
</div>

<!-- Info Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="fw-bold text-muted small w-40">วันที่</td>
                        <td><?= date('d/m/') . (date('Y', strtotime($report['duty_date'])) + 543) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted small">กะ</td>
                        <td><?= $shiftLabel[$report['shift']] ?? $report['shift'] ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted small">จุดที่</td>
                        <td><?= $report['point_no'] ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted small">ครู</td>
                        <td>
                            <?= htmlspecialchars($report['teacher_name'] ?? '— ไม่ระบุ —') ?>
                            <?php if ($report['telegram_username']): ?>
                            <a href="https://t.me/<?= htmlspecialchars($report['telegram_username']) ?>"
                               target="_blank" class="ms-1 text-info small">
                                <i class="fab fa-telegram"></i> @<?= htmlspecialchars($report['telegram_username']) ?>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="fw-bold text-muted small w-40">สถานะ</td>
                        <td>
                            <?php
                            $stBadge = ['pending'=>'<span class="badge bg-danger">🔴 ยังไม่รายงาน</span>',
                                        'partial'=>'<span class="badge bg-warning text-dark">🟡 ส่งบางส่วน</span>',
                                        'complete'=>'<span class="badge bg-success">✅ ครบแล้ว</span>'];
                            echo $stBadge[$report['status']] ?? $report['status'];
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted small">รูปที่ได้รับ</td>
                        <td><?= count($photos) ?> รูป</td>
                    </tr>
                    <?php if ($report['completed_at']): ?>
                    <tr>
                        <td class="fw-bold text-muted small">เวลาครบ</td>
                        <td><?= date('H:i', strtotime($report['completed_at'])) ?> น.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white border-top">
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!empty($photos)): ?>
            <a href="<?= $base_path ?>/duty/api/download_zip.php?report_id=<?= $reportId ?>"
               class="btn btn-outline-primary btn-sm">
                <i class="fas fa-file-archive me-1"></i>ดาวน์โหลด ZIP ทั้งหมด
            </a>
            <?php endif; ?>
            <?php if ($report['status'] !== 'complete' && $report['teacher_id']): ?>
            <a href="remind.php?report_id=<?= $reportId ?>"
               class="btn btn-outline-warning btn-sm"
               onclick="return confirm('ส่งการเตือนไปยัง <?= htmlspecialchars($report['teacher_name'] ?? '') ?>?')">
                <i class="fas fa-bell me-1"></i>ส่งเตือนครู
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Photo Grid -->
<?php if (empty($photos)): ?>
<div class="alert alert-secondary">
    <i class="fas fa-images me-2"></i>ยังไม่มีรูปภาพในรายงานนี้
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($photos as $i => $ph): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <a href="<?= $base_path ?>/duty/api/photo.php?path=<?= urlencode($ph['file_path']) ?>"
               class="glightbox"
               data-gallery="report-<?= $reportId ?>"
               data-description="รูปที่ <?= $i+1 ?> — <?= date('H:i', strtotime($ph['received_at'])) ?> น.<?= $ph['caption'] ? ' — ' . htmlspecialchars($ph['caption']) : '' ?>">
                <img src="<?= $base_path ?>/duty/api/photo.php?path=<?= urlencode($ph['thumbnail_path'] ?: $ph['file_path']) ?>"
                     alt="รูปที่ <?= $i+1 ?>"
                     class="card-img-top"
                     style="height:160px;object-fit:cover;">
            </a>
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted">
                        รูปที่ <?= $i+1 ?> · <?= date('H:i', strtotime($ph['received_at'])) ?> น.
                    </span>
                    <form method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_photo">
                        <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm p-1 lh-1"
                                onclick="return confirm('ลบรูปนี้?')"
                                title="ลบรูป">
                            <i class="fas fa-trash" style="font-size:.7rem;"></i>
                        </button>
                    </form>
                </div>
                <?php if ($ph['caption']): ?>
                <small class="text-muted d-block mt-1"><?= htmlspecialchars($ph['caption']) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
<script>GLightbox({ selector: '.glightbox' });</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
