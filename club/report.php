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

// Clubs (filtered by teacher if att_teacher — checks all 3 advisor slots)
$clubParams = [$semester, $year, $semester, $year, $semester, $year];
$clubWhere  = '';
if ($userRole === 'att_teacher' && $teacherId > 0) {
    $clubWhere = 'AND (cg.teacher_id = ? OR cg.teacher_id_2 = ? OR cg.teacher_id_3 = ?)';
    $clubParams[] = $teacherId;
    $clubParams[] = $teacherId;
    $clubParams[] = $teacherId;
}
$stmtClubs = $pdo->prepare("
    SELECT cg.id, cg.name, cg.max_capacity, cg.pass_threshold, 
           t1.name AS teacher_name, t2.name AS teacher_name_2, t3.name AS teacher_name_3,
           COUNT(DISTINCT cr.id) AS reg_count,
           COUNT(DISTINCT CASE WHEN cr2.result='pass' THEN cr2.id END) AS pass_count,
           COUNT(DISTINCT CASE WHEN cr2.result='fail' THEN cr2.id END) AS fail_count
    FROM club_groups cg
    LEFT JOIN att_teachers t1 ON t1.id = cg.teacher_id
    LEFT JOIN att_teachers t2 ON t2.id = cg.teacher_id_2
    LEFT JOIN att_teachers t3 ON t3.id = cg.teacher_id_3
    LEFT JOIN club_registrations cr ON cr.club_id = cg.id AND cr.semester = ? AND cr.year = ?
    LEFT JOIN club_results cr2 ON cr2.club_id = cg.id AND cr2.semester = ? AND cr2.year = ?
    WHERE cg.semester = ? AND cg.year = ? $clubWhere
    GROUP BY cg.id ORDER BY cg.name
");
$stmtClubs->execute($clubParams);
$clubs = $stmtClubs->fetchAll(PDO::FETCH_ASSOC);

// Unregistered students — filter to real students only (numeric ID, not subject codes)
$stmtUnreg = $pdo->prepare("
    SELECT s.student_id, s.name, s.classroom
    FROM att_students s
    WHERE s.classroom REGEXP '^ม\\.[1-6]/'
      AND s.student_id NOT IN (
          SELECT student_id FROM club_registrations WHERE semester = ? AND year = ?
      )
    ORDER BY s.classroom, s.name
");
$stmtUnreg->execute([$semester, $year]);
$unreg = $stmtUnreg->fetchAll(PDO::FETCH_ASSOC);

// จำนวนนักเรียนต่อห้อง (นับเฉพาะห้องจริง ม.X/Y)
$stmtByClass = $pdo->prepare("
    SELECT s.classroom,
           COUNT(s.student_id) AS total,
           COUNT(cr.id) AS registered
    FROM att_students s
    LEFT JOIN club_registrations cr ON cr.student_id = s.student_id
                                    AND cr.semester = ? AND cr.year = ?
    WHERE s.classroom REGEXP '^ม\\.[1-6]/'
    GROUP BY s.classroom
    ORDER BY s.classroom
");
$stmtByClass->execute([$semester, $year]);
$byClass = $stmtByClass->fetchAll(PDO::FETCH_ASSOC);

$pageTitle    = 'รายงานชุมนุม';
$pageSubtitle = "ภาคเรียน $semester / $year";
$activeSystem = 'club';
require_once __DIR__ . '/../components/layout_start.php';
?>

<style>
    @media print {
        .nav-tabs, .sidebar, .topbar, .btn, .no-print { display: none !important; }
        .card { border: 0 !important; shadow: none !important; }
        body { background: white !important; }
        .container-fluid { padding: 0 !important; }
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-end mb-3 no-print">
        <button onclick="window.print()" class="btn btn-dark rounded-3">
            <i class="fas fa-print me-1"></i>พิมพ์รายงาน
        </button>
    </div>

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
        <li class="nav-item"><a href="#tab-clubs"   class="nav-link active" data-bs-toggle="tab">รายชุมนุม</a></li>
        <li class="nav-item"><a href="#tab-unreg"   class="nav-link"        data-bs-toggle="tab">ยังไม่ลงทะเบียน (<?= count($unreg) ?>)</a></li>
        <li class="nav-item"><a href="#tab-byclass" class="nav-link"        data-bs-toggle="tab">แยกตามห้อง</a></li>
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
                                <td class="px-3 py-3 small">
                                    <?php
                                    $advisors = array_filter([$c['teacher_name'], $c['teacher_name_2'], $c['teacher_name_3']]);
                                    echo implode('<br>', array_map(fn($t) => htmlspecialchars($t, ENT_QUOTES, 'UTF-8'), $advisors));
                                    ?>
                                </td>
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

        <!-- By Classroom -->
        <div class="tab-pane fade" id="tab-byclass">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3">ห้องเรียน</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">นักเรียนทั้งหมด</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">ลงทะเบียนแล้ว</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">ยังไม่ลง</th>
                                    <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">รายชื่อ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $grandTotal = 0; $grandReg = 0;
                            foreach ($byClass as $row):
                                $unreg_count = $row['total'] - $row['registered'];
                                $grandTotal += $row['total'];
                                $grandReg   += $row['registered'];
                            ?>
                            <tr>
                                <td class="px-3 py-2 fw-bold small"><?= htmlspecialchars($row['classroom'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 text-center small"><?= $row['total'] ?></td>
                                <td class="px-3 py-2 text-center"><span class="badge bg-success rounded-pill"><?= $row['registered'] ?></span></td>
                                <td class="px-3 py-2 text-center"><span class="badge <?= $unreg_count > 0 ? 'bg-danger' : 'bg-secondary' ?> rounded-pill"><?= $unreg_count ?></span></td>
                                <td class="px-3 py-2 text-center">
                                    <a href="/club/class_students.php?classroom=<?= urlencode($row['classroom']) ?>" class="btn btn-outline-secondary btn-sm rounded-2"><i class="fas fa-search"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td class="px-3 py-2 fw-black small">รวม</td>
                                    <td class="px-3 py-2 text-center fw-black"><?= $grandTotal ?></td>
                                    <td class="px-3 py-2 text-center fw-black"><?= $grandReg ?></td>
                                    <td class="px-3 py-2 text-center fw-black"><?= $grandTotal - $grandReg ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
