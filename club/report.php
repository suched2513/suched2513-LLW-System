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

$pdo       = getPdo();
$userRole  = $_SESSION['llw_role'];
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);

$cfg = $pdo->query("SELECT * FROM club_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$semester = (int)($cfg['semester'] ?? 1);
$year     = (int)($cfg['year'] ?? (date('Y') + 543));

// Clubs (filtered by teacher if att_teacher)
$clubWhere = $userRole === 'att_teacher' ? "AND cg.teacher_id = $teacherId" : '';
$clubs = $pdo->query("
    SELECT cg.id, cg.name, cg.max_capacity, cg.pass_threshold, t.name AS teacher_name,
           COUNT(DISTINCT cr.id) AS reg_count,
           COUNT(DISTINCT CASE WHEN cr2.result='pass' THEN cr2.id END) AS pass_count,
           COUNT(DISTINCT CASE WHEN cr2.result='fail' THEN cr2.id END) AS fail_count
    FROM club_groups cg
    LEFT JOIN att_teachers t ON t.id = cg.teacher_id
    LEFT JOIN club_registrations cr ON cr.club_id = cg.id AND cr.semester = $semester AND cr.year = $year
    LEFT JOIN club_results cr2 ON cr2.club_id = cg.id AND cr2.semester = $semester AND cr2.year = $year
    WHERE cg.semester = $semester AND cg.year = $year $clubWhere
    GROUP BY cg.id ORDER BY cg.name
")->fetchAll(PDO::FETCH_ASSOC);

// Unregistered students
$unreg = $pdo->query("
    SELECT s.student_id, s.name, s.classroom
    FROM att_students s
    WHERE s.student_id NOT IN (
        SELECT student_id FROM club_registrations WHERE semester = $semester AND year = $year
    )
    ORDER BY s.classroom, s.name
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle    = 'รายงานชุมนุม';
$pageSubtitle = "ภาคเรียน $semester / $year";
$activeSystem = 'club';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">

    <!-- Summary KPIs -->
    <?php
    $totalReg  = array_sum(array_column($clubs, 'reg_count'));
    $totalPass = array_sum(array_column($clubs, 'pass_count'));
    $totalFail = array_sum(array_column($clubs, 'fail_count'));
    ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 text-white h-100" style="background:linear-gradient(135deg,#7c3aed,#6d28d9)">
                <div class="card-body py-3">
                    <div class="small fw-bold opacity-75 text-uppercase">ชุมนุมทั้งหมด</div>
                    <div class="fs-2 fw-black"><?= count($clubs) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 text-white h-100" style="background:linear-gradient(135deg,#2563eb,#1d4ed8)">
                <div class="card-body py-3">
                    <div class="small fw-bold opacity-75 text-uppercase">ลงทะเบียนแล้ว</div>
                    <div class="fs-2 fw-black"><?= $totalReg ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 text-white h-100" style="background:linear-gradient(135deg,#059669,#047857)">
                <div class="card-body py-3">
                    <div class="small fw-bold opacity-75 text-uppercase">ผ่าน</div>
                    <div class="fs-2 fw-black"><?= $totalPass ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 text-white h-100" style="background:linear-gradient(135deg,#dc2626,#b91c1c)">
                <div class="card-body py-3">
                    <div class="small fw-bold opacity-75 text-uppercase">ไม่ผ่าน / ยังไม่ลง</div>
                    <div class="fs-2 fw-black"><?= $totalFail ?> / <?= count($unreg) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs border-0 mb-3">
        <li class="nav-item"><a href="#tab-clubs"  class="nav-link active" data-bs-toggle="tab">รายชุมนุม</a></li>
        <li class="nav-item"><a href="#tab-unreg"  class="nav-link"        data-bs-toggle="tab">ยังไม่ลงทะเบียน (<?= count($unreg) ?>)</a></li>
    </ul>

    <div class="tab-content">
        <!-- By Club -->
        <div class="tab-pane fade show active" id="tab-clubs">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3">ชุมนุม</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3">ครูที่ปรึกษา</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">สมาชิก/ความจุ</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">ผ่าน</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">ไม่ผ่าน</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($clubs as $c): ?>
                            <tr>
                                <td class="px-3 py-3 small fw-bold"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-3 small"><?= htmlspecialchars($c['teacher_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-3 text-center small"><?= $c['reg_count'] ?>/<?= $c['max_capacity'] ?></td>
                                <td class="px-3 py-3 text-center"><span class="badge bg-success rounded-pill"><?= $c['pass_count'] ?></span></td>
                                <td class="px-3 py-3 text-center"><span class="badge bg-danger rounded-pill"><?= $c['fail_count'] ?></span></td>
                                <td class="px-3 py-3 text-center text-nowrap">
                                    <a href="/club/members.php?club_id=<?= $c['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-2 me-1"><i class="fas fa-users"></i></a>
                                    <a href="/club/results.php?club_id=<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm rounded-2"><i class="fas fa-star"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unregistered -->
        <div class="tab-pane fade" id="tab-unreg">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <?php if (empty($unreg)): ?>
                    <div class="text-center py-5 text-muted"><i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>นักเรียนทุกคนลงทะเบียนแล้ว</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3">#</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3">ชื่อ-สกุล</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3">ห้อง</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3">รหัส</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($unreg as $i => $u): ?>
                            <tr>
                                <td class="px-3 py-2 text-muted small"><?= $i + 1 ?></td>
                                <td class="px-3 py-2 small fw-bold"><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 small"><?= htmlspecialchars($u['classroom'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 small text-muted"><?= htmlspecialchars($u['student_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
