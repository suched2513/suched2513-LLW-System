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

$session_id = (int)($_GET['session_id'] ?? 0);
if (!$session_id) { header('Location: /club/index.php'); exit(); }

$pdo       = getPdo();
$userRole  = $_SESSION['llw_role'];
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT cs.*, cg.name AS club_name, cg.teacher_id, cg.id AS club_id
    FROM club_sessions cs JOIN club_groups cg ON cg.id = cs.club_id
    WHERE cs.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) { header('Location: /club/index.php'); exit(); }

if ($userRole === 'att_teacher' && (int)$session['teacher_id'] !== $teacherId) {
    header('Location: /club/index.php'); exit();
}

// Get members
$members = $pdo->prepare("
    SELECT s.student_id, s.name, s.classroom, ca.status AS att_status, ca.note
    FROM club_registrations cr
    JOIN att_students s ON s.student_id = cr.student_id
    LEFT JOIN club_attendance ca ON ca.session_id = ? AND ca.student_id = s.student_id
    WHERE cr.club_id = ?
    ORDER BY s.classroom, s.name
");
$members->execute([$session_id, $session['club_id']]);
$memberList = $members->fetchAll(PDO::FETCH_ASSOC);

$thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$dp = $session['session_date'] ? explode('-', $session['session_date']) : [];
$dateStr = count($dp) === 3 ? ((int)$dp[2].' '.$thaiMonths[(int)$dp[1]].' '.((int)$dp[0]+543)) : '-';

$pageTitle    = 'เช็คชื่อชุมนุม';
$pageSubtitle = htmlspecialchars($session['club_name'], ENT_QUOTES, 'UTF-8') . ' – ' . $dateStr;
$activeSystem = 'club';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="mb-1 fw-black"><?= htmlspecialchars($session['club_name'], ENT_QUOTES, 'UTF-8') ?></h5>
                <div class="text-muted small">
                    <i class="fas fa-calendar me-1"></i><?= $dateStr ?>
                    <?= $session['period'] ? ' · '.htmlspecialchars($session['period'],ENT_QUOTES,'UTF-8') : '' ?>
                    <?= $session['topic'] ? ' · <strong>'.htmlspecialchars($session['topic'],ENT_QUOTES,'UTF-8').'</strong>' : '' ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button onclick="setAll('present')" class="btn btn-success btn-sm rounded-3">
                    <i class="fas fa-check-double me-1"></i>มาทั้งหมด
                </button>
                <button onclick="saveAttendance()" class="btn btn-sm rounded-3 text-white fw-bold" style="background:#7c3aed">
                    <i class="fas fa-save me-1"></i>บันทึกเช็คชื่อ
                </button>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 py-3">
            <h6 class="mb-0 fw-bold"><i class="fas fa-clipboard-check me-2" style="color:#7c3aed"></i>รายชื่อนักเรียน (<?= count($memberList) ?> คน)</h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($memberList)): ?>
            <div class="text-center py-5 text-muted">ยังไม่มีสมาชิกในชุมนุมนี้</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">#</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ชื่อ-สกุล</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">ห้อง</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">สถานะ</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($memberList as $i => $m): ?>
                    <tr>
                        <td class="px-3 py-2 text-muted small"><?= $i + 1 ?></td>
                        <td class="px-3 py-2 small fw-bold"><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-2 small"><?= htmlspecialchars($m['classroom'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-3 py-2 text-center">
                            <select class="form-select form-select-sm rounded-3 att-status" data-sid="<?= htmlspecialchars($m['student_id'], ENT_QUOTES, 'UTF-8') ?>" style="width:auto;min-width:110px;display:inline-block">
                                <option value="present" <?= ($m['att_status'] ?? 'absent') === 'present' ? 'selected' : '' ?>>✓ มา</option>
                                <option value="late"    <?= ($m['att_status'] ?? '') === 'late'    ? 'selected' : '' ?>>⏰ สาย</option>
                                <option value="leave"   <?= ($m['att_status'] ?? '') === 'leave'   ? 'selected' : '' ?>>📋 ลา</option>
                                <option value="absent"  <?= ($m['att_status'] ?? 'absent') === 'absent' && !$m['att_status'] ? 'selected' : ($m['att_status'] === 'absent' ? 'selected' : '') ?>>✗ ขาด</option>
                            </select>
                        </td>
                        <td class="px-3 py-2">
                            <input type="text" class="form-control form-control-sm rounded-3 att-note" data-sid="<?= htmlspecialchars($m['student_id'], ENT_QUOTES, 'UTF-8') ?>"
                                   value="<?= htmlspecialchars($m['note'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="หมายเหตุ" style="min-width:120px">
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

<script>
const SESSION_ID = <?= json_encode($session_id) ?>;

function setAll(status) {
    document.querySelectorAll('.att-status').forEach(s => s.value = status);
}

async function saveAttendance() {
    const records = [];
    document.querySelectorAll('.att-status').forEach(sel => {
        const sid  = sel.dataset.sid;
        const note = document.querySelector(`.att-note[data-sid="${sid}"]`)?.value?.trim() || '';
        records.push({ student_id: sid, status: sel.value, note });
    });

    const res  = await fetch('/club/api/save_attendance.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ session_id: SESSION_ID, records })
    });
    const data = await res.json();
    if (data.status === 'success') {
        await Swal.fire({ icon:'success', title:'บันทึกเช็คชื่อสำเร็จ', text:data.message, timer:1500, showConfirmButton:false });
        location.reload();
    } else {
        Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:data.message, confirmButtonColor:'#7c3aed' });
    }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
