<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
$allowed = ['att_teacher', 'super_admin', 'club_admin', 'wfh_admin'];
if (!in_array($_SESSION['llw_role'], $allowed, true)) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$pdo       = getPdo();
$classroom = trim($_GET['classroom'] ?? '');
if ($classroom === '') { header('Location: /club/report.php'); exit(); }

$cfg      = $pdo->query("SELECT * FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$semester = (int)($cfg['semester'] ?? 1);
$year     = (int)($cfg['year'] ?? (date('Y') + 543));

$stmt = $pdo->prepare("
    SELECT s.student_id, s.name,
           CASE WHEN cr.id IS NOT NULL THEN 1 ELSE 0 END AS is_registered,
           cg.name AS club_name
    FROM att_students s
    LEFT JOIN club_registrations cr ON cr.student_id = s.student_id
                                    AND cr.semester = ? AND cr.year = ?
    LEFT JOIN club_groups cg ON cg.id = cr.club_id
    WHERE s.classroom = ?
    ORDER BY s.student_id
");
$stmt->execute([$semester, $year, $classroom]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle    = 'รายชื่อนักเรียน ' . $classroom;
$pageSubtitle = "ภาคเรียน $semester / $year";
$activeSystem = 'club';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/club/report.php" class="btn btn-outline-secondary btn-sm rounded-2"><i class="fas fa-arrow-left me-1"></i>กลับ</a>
        <h5 class="mb-0 fw-bold">นักเรียนห้อง <?= htmlspecialchars($classroom, ENT_QUOTES, 'UTF-8') ?></h5>
        <span class="badge bg-secondary rounded-pill"><?= count($students) ?> คน</span>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">#</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">รหัสนักเรียน</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ชื่อ-สกุล</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">สถานะชุมนุม</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $i => $s): ?>
                    <tr>
                        <td class="px-3 py-2 text-muted small"><?= $i + 1 ?></td>
                        <td class="px-3 py-2 small fw-bold text-monospace"><?= htmlspecialchars($s['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-2 small"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-2 text-center">
                            <?php if ($s['is_registered']): ?>
                                <span class="badge bg-success rounded-pill"><?= htmlspecialchars($s['club_name'] ?? 'ลงทะเบียนแล้ว', ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="badge bg-danger rounded-pill">ยังไม่ลง</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
