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

$club_id   = (int)($_GET['club_id'] ?? 0);
if (!$club_id) { header('Location: /club/index.php'); exit(); }

$pdo       = getPdo();
$userRole  = $_SESSION['llw_role'];
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);

$stmt = $pdo->prepare("SELECT cg.*, t.name AS teacher_name FROM club_groups cg LEFT JOIN att_teachers t ON t.id = cg.teacher_id WHERE cg.id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$club) { header('Location: /club/index.php'); exit(); }

if ($userRole === 'att_teacher' && (int)$club['teacher_id'] !== $teacherId) {
    header('Location: /club/index.php'); exit();
}

// Total done sessions
$totalSess = (int)$pdo->prepare("SELECT COUNT(*) FROM club_sessions WHERE club_id = ? AND status = 'done'")->execute([$club_id]) ? 0 : 0;
$ts = $pdo->prepare("SELECT COUNT(*) FROM club_sessions WHERE club_id = ? AND status = 'done'");
$ts->execute([$club_id]);
$totalSess = (int)$ts->fetchColumn();

// Members with attendance stats
$members = $pdo->prepare("
    SELECT s.student_id, s.name, s.classroom, cr.registered_at,
           COUNT(ca.id) AS att_count,
           SUM(CASE WHEN ca.status IN ('present','late') THEN 1 ELSE 0 END) AS present_count,
           cr2.result, cr2.attendance_pct
    FROM club_registrations cr
    JOIN att_students s ON s.student_id = cr.student_id
    LEFT JOIN club_sessions cs ON cs.club_id = cr.club_id AND cs.status = 'done'
    LEFT JOIN club_attendance ca ON ca.session_id = cs.id AND ca.student_id = s.student_id
    LEFT JOIN club_results cr2 ON cr2.student_id = s.student_id AND cr2.club_id = cr.club_id
    WHERE cr.club_id = ?
    GROUP BY s.student_id, s.name, s.classroom, cr.registered_at, cr2.result, cr2.attendance_pct
    ORDER BY s.classroom, s.name
");
$members->execute([$club_id]);
$memberList = $members->fetchAll(PDO::FETCH_ASSOC);

$pageTitle    = 'สมาชิกชุมนุม';
$pageSubtitle = htmlspecialchars($club['name'], ENT_QUOTES, 'UTF-8');
$activeSystem = 'club';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="mb-1 fw-black"><?= htmlspecialchars($club['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                <div class="text-muted small">
                    <i class="fas fa-chalkboard-teacher me-1"></i><?= htmlspecialchars($club['teacher_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    &nbsp;·&nbsp;<i class="fas fa-users me-1"></i><?= count($memberList) ?>/<?= $club['max_capacity'] ?> คน
                    &nbsp;·&nbsp;<i class="fas fa-calendar me-1"></i><?= $totalSess ?> คาบที่จัดแล้ว
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="/club/sessions.php?club_id=<?= $club_id ?>" class="btn btn-outline-primary btn-sm rounded-3">
                    <i class="fas fa-calendar-alt me-1"></i>จัดการคาบ
                </a>
                <a href="/club/results.php?club_id=<?= $club_id ?>" class="btn btn-sm rounded-3 text-white" style="background:#7c3aed">
                    <i class="fas fa-star me-1"></i>ประเมินผล
                </a>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 py-3">
            <h6 class="mb-0 fw-bold"><i class="fas fa-users me-2" style="color:#7c3aed"></i>รายชื่อสมาชิก</h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($memberList)): ?>
            <div class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>ยังไม่มีสมาชิก</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">#</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ชื่อ-สกุล</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ห้อง</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">เข้าร่วม</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">%</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">ผล</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($memberList as $i => $m):
                        $pct = $totalSess > 0 ? round($m['present_count'] / $totalSess * 100, 1) : 0;
                        $barCls = $pct >= $club['pass_threshold'] ? 'bg-success' : ($pct >= 60 ? 'bg-warning' : 'bg-danger');
                        $resultCls = ['pass'=>'bg-success','fail'=>'bg-danger','pending'=>'bg-warning text-dark'];
                        $resultLbl = ['pass'=>'ผ่าน','fail'=>'ไม่ผ่าน','pending'=>'รอประเมิน'];
                    ?>
                    <tr>
                        <td class="px-3 py-2 text-muted small"><?= $i + 1 ?></td>
                        <td class="px-3 py-2 small fw-bold"><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-2 small"><?= htmlspecialchars($m['classroom'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-2 text-center small"><?= $m['present_count'] ?>/<?= $totalSess ?></td>
                        <td class="px-3 py-2 text-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-fill" style="height:6px">
                                    <div class="progress-bar <?= $barCls ?>" style="width:<?= $pct ?>%"></div>
                                </div>
                                <small class="fw-bold" style="min-width:38px"><?= $pct ?>%</small>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <?php if ($m['result']): ?>
                            <span class="badge rounded-pill <?= $resultCls[$m['result']] ?? 'bg-secondary' ?>"><?= $resultLbl[$m['result']] ?? $m['result'] ?></span>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
