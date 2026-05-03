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

$ts = $pdo->prepare("SELECT COUNT(*) FROM club_sessions WHERE club_id = ? AND status = 'done'");
$ts->execute([$club_id]);
$totalSess = (int)$ts->fetchColumn();

$members = $pdo->prepare("
    SELECT s.student_id, s.name, s.classroom,
           SUM(CASE WHEN ca.status IN ('present','late') THEN 1 ELSE 0 END) AS present_count,
           cr2.result, cr2.teacher_comment, cr2.attendance_pct
    FROM club_registrations cr
    JOIN att_students s ON s.student_id = cr.student_id
    LEFT JOIN club_sessions cs ON cs.club_id = cr.club_id AND cs.status = 'done'
    LEFT JOIN club_attendance ca ON ca.session_id = cs.id AND ca.student_id = s.student_id
    LEFT JOIN club_results cr2 ON cr2.student_id = s.student_id AND cr2.club_id = cr.club_id
    WHERE cr.club_id = ?
    GROUP BY s.student_id, s.name, s.classroom, cr2.result, cr2.teacher_comment, cr2.attendance_pct
    ORDER BY s.classroom, s.name
");
$members->execute([$club_id]);
$memberList = $members->fetchAll(PDO::FETCH_ASSOC);

$pageTitle    = 'ประเมินผลชุมนุม';
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
                    เกณฑ์ผ่าน: <strong><?= $club['pass_threshold'] ?>%</strong>
                    &nbsp;·&nbsp; คาบที่จัดแล้ว: <strong><?= $totalSess ?> คาบ</strong>
                    &nbsp;·&nbsp; สมาชิก: <strong><?= count($memberList) ?> คน</strong>
                </div>
            </div>
            <button onclick="finalizeResults()" class="btn text-white rounded-3 fw-bold" style="background:#7c3aed">
                <i class="fas fa-check-circle me-1"></i>คำนวณและบันทึกผล
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="results_table">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">#</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ชื่อ-สกุล</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ห้อง</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">เข้าร่วม/ทั้งหมด</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">%</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">ผล</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($memberList as $i => $m):
                        $pct = $totalSess > 0 ? round($m['present_count'] / $totalSess * 100, 1) : 0;
                        $autoResult = $pct >= $club['pass_threshold'] ? 'pass' : 'fail';
                        $curResult  = $m['result'] ?? $autoResult;
                        $barCls = $pct >= $club['pass_threshold'] ? 'bg-success' : 'bg-danger';
                    ?>
                    <tr data-sid="<?= htmlspecialchars($m['student_id'], ENT_QUOTES, 'UTF-8') ?>">
                        <td class="px-3 py-2 text-muted small"><?= $i + 1 ?></td>
                        <td class="px-3 py-2 small fw-bold"><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-2 small"><?= htmlspecialchars($m['classroom'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-2 text-center small fw-bold"><?= $m['present_count'] ?>/<?= $totalSess ?></td>
                        <td class="px-3 py-2 text-center">
                            <span class="badge <?= $pct >= $club['pass_threshold'] ? 'bg-success' : 'bg-danger' ?> rounded-pill"><?= $pct ?>%</span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <select class="form-select form-select-sm rounded-3 result-select" style="width:auto;min-width:100px;display:inline-block">
                                <option value="pass" <?= $curResult === 'pass' ? 'selected' : '' ?>>✓ ผ่าน</option>
                                <option value="fail" <?= $curResult === 'fail' ? 'selected' : '' ?>>✗ ไม่ผ่าน</option>
                            </select>
                        </td>
                        <td class="px-3 py-2">
                            <input type="text" class="form-control form-control-sm rounded-3 result-comment"
                                   value="<?= htmlspecialchars($m['teacher_comment'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="หมายเหตุ" style="min-width:140px">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const CLUB_ID = <?= json_encode($club_id) ?>;
const SEMESTER = <?= json_encode($club['semester']) ?>;
const YEAR = <?= json_encode($club['year']) ?>;

async function finalizeResults() {
    const { isConfirmed } = await Swal.fire({
        icon:'question', title:'บันทึกผลการประเมิน?',
        text:'ระบบจะคำนวณและบันทึกผลของนักเรียนทุกคน',
        showCancelButton:true, confirmButtonText:'บันทึก', cancelButtonText:'ยกเลิก',
        confirmButtonColor:'#7c3aed'
    });
    if (!isConfirmed) return;

    const overrides = [];
    document.querySelectorAll('#results_table tbody tr').forEach(tr => {
        overrides.push({
            student_id: tr.dataset.sid,
            result:     tr.querySelector('.result-select').value,
            comment:    tr.querySelector('.result-comment').value.trim(),
        });
    });

    const res  = await fetch('/club/api/finalize_results.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ club_id: CLUB_ID, semester: SEMESTER, year: YEAR, overrides })
    });
    const data = await res.json();
    if (data.status === 'success') {
        await Swal.fire({ icon:'success', title:'บันทึกผลสำเร็จ', text:data.message, timer:1500, showConfirmButton:false });
        location.reload();
    } else {
        Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:data.message, confirmButtonColor:'#7c3aed' });
    }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
