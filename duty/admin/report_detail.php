<?php
/**
 * duty/admin/report_detail.php — รายละเอียดรายงาน 1 จุด/วัน
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin', 'att_teacher'])) {
    header('Location: /login.php'); exit();
}

$activeSystem = 'duty';
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

<style>
.info-card { border: none; border-radius: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; background: #fff; }
.info-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; }
.info-table tr:last-child td { border-bottom: none; }
.label-text { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; }
.value-text { font-size: 1rem; font-weight: 700; color: #1e293b; }

.status-badge { padding: 6px 12px; border-radius: 12px; font-weight: 800; font-size: 11px; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; }
.badge-glow-success { background: #ecfdf5; color: #059669; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.1); }
.badge-glow-warning { background: #fffbeb; color: #d97706; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.1); }
.badge-glow-danger  { background: #fef2f2; color: #dc2626; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.1); }

.photo-card { border: none; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.3s ease; overflow: hidden; }
.photo-card:hover { transform: translateY(-5px); box-shadow: 0 12px 24px rgba(0,0,0,0.1); }
.photo-img-box { position: relative; height: 180px; overflow: hidden; }
.photo-img-box img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
.photo-card:hover .photo-img-box img { transform: scale(1.1); }
.photo-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.2); opacity: 0; transition: opacity 0.3s; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.2rem; }
.photo-card:hover .photo-overlay { opacity: 1; }

.back-btn { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; color: #64748b; transition: all 0.2s; padding: 8px 16px; border-radius: 12px; text-decoration: none; border: 1px solid #e2e8f0; margin-bottom: 20px; }
.back-btn:hover { background: #f8fafc; color: #1e293b; border-color: #cbd5e1; }
</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">

<!-- Back -->
<a href="reports.php?date=<?= $report['duty_date'] ?>" class="back-btn shadow-sm">
    <i class="fas fa-chevron-left"></i> กลับหน้ารายงาน
</a>

<!-- Info Card -->
<div class="info-card mb-4">
    <div class="card-body p-4">
        <div class="row g-4">
            <div class="col-md-6">
                <table class="w-100 info-table">
                    <tr>
                        <td>
                            <div class="label-text">วันที่ปฏิบัติหน้าที่</div>
                            <div class="value-text"><?= date('d/m/') . (date('Y', strtotime($report['duty_date'])) + 543) ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label-text">ช่วงเวลา (กะ)</div>
                            <div class="value-text"><?= $shiftLabel[$report['shift']] ?? $report['shift'] ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label-text">ตำแหน่ง/จุดที่</div>
                            <div class="value-text">จุดที่ <?= $report['point_no'] ?></div>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="w-100 info-table">
                    <tr>
                        <td>
                            <div class="label-text">สถานะรายงาน</div>
                            <div class="mt-1">
                                <?php
                                $stBadge = [
                                    'pending' => '<span class="status-badge badge-glow-danger"><i class="fas fa-times-circle"></i> ยังไม่รายงาน</span>',
                                    'partial' => '<span class="status-badge badge-glow-warning"><i class="fas fa-hourglass-half"></i> ส่งบางส่วน</span>',
                                    'complete' => '<span class="status-badge badge-glow-success"><i class="fas fa-check-circle"></i> ครบแล้ว</span>'
                                ];
                                echo $stBadge[$report['status']] ?? $report['status'];
                                ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label-text">ครูผู้รับผิดชอบ</div>
                            <div class="value-text">
                                <?= htmlspecialchars($report['teacher_name'] ?? '— ไม่ระบุ —') ?>
                                <?php if ($report['telegram_username']): ?>
                                <a href="https://t.me/<?= htmlspecialchars($report['telegram_username']) ?>"
                                   target="_blank" class="ms-2 text-info text-decoration-none small">
                                    <i class="fab fa-telegram"></i> @<?= htmlspecialchars($report['telegram_username']) ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label-text">จำนวนรูป / เวลาที่เสร็จสิ้น</div>
                            <div class="value-text">
                                <?= count($photos) ?> รูป 
                                <?php if ($report['completed_at']): ?>
                                · <span class="text-success"><?= date('H:i', strtotime($report['completed_at'])) ?> น.</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="card-footer bg-slate-50 border-top p-3 px-4">
        <div class="d-flex gap-3 flex-wrap">
            <?php if (!empty($photos)): ?>
            <a href="<?= $base_path ?>/duty/api/download_zip.php?report_id=<?= $reportId ?>"
               class="btn btn-primary rounded-xl px-4 fw-bold shadow-sm">
                <i class="fas fa-file-archive me-2"></i>ดาวน์โหลด ZIP ทั้งหมด
            </a>
            <?php endif; ?>
            <a href="print_pr.php?id=<?= $reportId ?>" target="_blank"
               class="btn btn-info text-white rounded-xl px-4 fw-bold shadow-sm"
               style="background: linear-gradient(to right, #ec4899, #8b5cf6); border: none;">
                <i class="fas fa-bullhorn me-2"></i>สร้างจดหมายข่าว (PR News)
            </a>
            <?php if ($report['status'] !== 'complete' && $report['teacher_id']): ?>
            <a href="remind.php?report_id=<?= $reportId ?>"
               class="btn btn-warning rounded-xl px-4 fw-bold shadow-sm"
               onclick="return confirm('ส่งการเตือนไปยัง <?= htmlspecialchars($report['teacher_name'] ?? '') ?>?')">
                <i class="fas fa-bell me-2"></i>ส่งเตือนครูเวร
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Report Note Section -->
<?php if (!empty($report['report_note'])): ?>
<div class="info-card mb-4 border-start border-4 border-primary">
    <div class="card-body p-4">
        <div class="label-text mb-3"><i class="fas fa-file-alt me-2 text-primary"></i>บันทึกรายละเอียดการปฏิบัติหน้าที่ (ใคร ทำอะไร ที่ไหน เมื่อไร อย่างไร)</div>
        <div class="p-3 bg-slate-50 rounded-2xl text-slate-700 leading-relaxed" style="white-space: pre-line; font-size: 0.95rem;">
            <?= htmlspecialchars($report['report_note']) ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Photo Grid -->
<?php if (empty($photos)): ?>
<div class="alert alert-secondary">
    <i class="fas fa-images me-2"></i>ยังไม่มีรูปภาพในรายงานนี้
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($photos as $i => $ph): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="photo-card card">
            <div class="photo-img-box">
                <a href="<?= $base_path ?>/duty/api/photo.php?path=<?= urlencode($ph['file_path']) ?>"
                   class="glightbox"
                   data-gallery="report-<?= $reportId ?>"
                   data-description="รูปที่ <?= $i+1 ?> — <?= date('H:i', strtotime($ph['received_at'])) ?> น.<?= $ph['caption'] ? ' — ' . htmlspecialchars($ph['caption']) : '' ?>">
                    <img src="<?= $base_path ?>/duty/api/photo.php?path=<?= urlencode($ph['thumbnail_path'] ?: $ph['file_path']) ?>"
                         alt="รูปที่ <?= $i+1 ?>">
                    <div class="photo-overlay">
                        <i class="fas fa-search-plus"></i>
                    </div>
                </a>
            </div>
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold text-dark small">รูปที่ <?= $i+1 ?></span>
                    <form method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_photo">
                        <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm p-1 px-2 border-0"
                                onclick="return confirm('ลบรูปนี้?')"
                                title="ลบรูป">
                            <i class="fas fa-trash-alt" style="font-size: 0.8rem"></i>
                        </button>
                    </form>
                </div>
                <div class="d-flex align-items-center gap-2 text-muted small">
                    <i class="fas fa-clock opacity-50"></i>
                    <?= date('H:i', strtotime($ph['received_at'])) ?> น.
                </div>
                <?php if ($ph['caption']): ?>
                <div class="mt-2 p-2 bg-slate-50 rounded-lg small border-start border-3 border-primary">
                    <?= htmlspecialchars($ph['caption']) ?>
                </div>
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
