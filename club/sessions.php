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

$club_id  = (int)($_GET['club_id'] ?? 0);
if (!$club_id) { header('Location: /club/index.php'); exit(); }

$pdo      = getPdo();
$userRole = $_SESSION['llw_role'];
$teacherId = (int)($_SESSION['teacher_id'] ?? 0);

$stmt = $pdo->prepare("SELECT cg.*, t.name AS teacher_name FROM club_groups cg LEFT JOIN att_teachers t ON t.id = cg.teacher_id WHERE cg.id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$club) { header('Location: /club/index.php'); exit(); }

// Access: super_admin sees all, teacher sees own
if ($userRole === 'att_teacher' && (int)$club['teacher_id'] !== $teacherId) {
    header('Location: /club/index.php'); exit();
}

$sessions = $pdo->prepare("
    SELECT cs.*, COUNT(ca.id) AS att_count,
           SUM(CASE WHEN ca.status IN ('present','late') THEN 1 ELSE 0 END) AS present_count
    FROM club_sessions cs
    LEFT JOIN club_attendance ca ON ca.session_id = cs.id
    WHERE cs.club_id = ?
    GROUP BY cs.id ORDER BY cs.session_date DESC, cs.id DESC
");
$sessions->execute([$club_id]);
$sessionList = $sessions->fetchAll(PDO::FETCH_ASSOC);

$memberCount = (int)$pdo->prepare("SELECT COUNT(*) FROM club_registrations WHERE club_id = ?")->execute([$club_id]) ? 0 : 0;
$mc = $pdo->prepare("SELECT COUNT(*) FROM club_registrations WHERE club_id = ?");
$mc->execute([$club_id]);
$memberCount = (int)$mc->fetchColumn();

$pageTitle    = 'คาบชุมนุม';
$pageSubtitle = htmlspecialchars($club['name'], ENT_QUOTES, 'UTF-8');
$activeSystem = 'club';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="container-fluid">

    <!-- Club Header -->
    <div class="card border-0 shadow-sm rounded-3 mb-4" style="border-left:4px solid #7c3aed !important">
        <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-1 fw-black"><?= htmlspecialchars($club['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                    <div class="text-muted small">
                        <i class="fas fa-chalkboard-teacher me-1"></i><?= htmlspecialchars($club['teacher_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;|&nbsp;<i class="fas fa-door-open me-1"></i><?= htmlspecialchars($club['room'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;|&nbsp;<i class="fas fa-users me-1"></i><?= $memberCount ?> คน
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="/club/members.php?club_id=<?= $club_id ?>" class="btn btn-outline-secondary btn-sm rounded-3">
                        <i class="fas fa-users me-1"></i>สมาชิก
                    </a>
                    <button onclick="openSessionModal()" class="btn btn-sm rounded-3 text-white" style="background:#7c3aed">
                        <i class="fas fa-plus me-1"></i>สร้างคาบ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sessions Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 py-3">
            <h6 class="mb-0 fw-bold"><i class="fas fa-calendar-alt me-2" style="color:#7c3aed"></i>รายการคาบ (<?= count($sessionList) ?> คาบ)</h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($sessionList)): ?>
            <div class="text-center py-5 text-muted"><i class="fas fa-calendar-plus fa-2x mb-2 d-block"></i>ยังไม่มีคาบ</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">#</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">วันที่</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3">หัวข้อ</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">เข้าร่วม</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">สถานะ</th>
                            <th class="fw-bold text-uppercase small text-muted px-3 py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sessionList as $i => $s): ?>
                    <?php
                    $statusCls = ['planned'=>'bg-warning text-dark','done'=>'bg-success','cancelled'=>'bg-danger'];
                    $statusLbl = ['planned'=>'วางแผน','done'=>'เสร็จแล้ว','cancelled'=>'ยกเลิก'];
                    $thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
                    $dp = $s['session_date'] ? explode('-', $s['session_date']) : [];
                    $dateStr = count($dp) === 3 ? ((int)$dp[2].' '.$thaiMonths[(int)$dp[1]].' '.((int)$dp[0]+543)) : '-';
                    ?>
                    <tr>
                        <td class="px-3 py-3 text-muted small"><?= count($sessionList) - $i ?></td>
                        <td class="px-3 py-3 small"><?= $dateStr ?><?= $s['period'] ? '<br><span class="text-muted">'.htmlspecialchars($s['period'],ENT_QUOTES,'UTF-8').'</span>' : '' ?></td>
                        <td class="px-3 py-3 small">
                            <div class="fw-bold"><?= htmlspecialchars($s['topic'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                            <?php if ($s['description']): ?>
                            <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars(mb_substr($s['description'],0,60,'UTF-8'), ENT_QUOTES, 'UTF-8') ?>...</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 text-center small">
                            <?php if ($s['att_count'] > 0): ?>
                            <span class="fw-bold text-success"><?= $s['present_count'] ?></span>/<span class="text-muted"><?= $memberCount ?></span>
                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="badge rounded-pill <?= $statusCls[$s['status']] ?? 'bg-secondary' ?>"><?= $statusLbl[$s['status']] ?? $s['status'] ?></span>
                        </td>
                        <td class="px-3 py-3 text-center text-nowrap">
                            <a href="/club/attendance.php?session_id=<?= $s['id'] ?>" class="btn btn-outline-success btn-sm rounded-2 me-1" title="เช็คชื่อ"><i class="fas fa-clipboard-check"></i></a>
                            <a href="/club/activity.php?session_id=<?= $s['id'] ?>" class="btn btn-outline-info btn-sm rounded-2 me-1" title="บันทึกกิจกรรม"><i class="fas fa-camera"></i></a>
                            <button onclick='openSessionModal(<?= json_encode($s) ?>)' class="btn btn-outline-warning btn-sm rounded-2 me-1" title="แก้ไข"><i class="fas fa-edit"></i></button>
                            <button onclick="deleteSession(<?= $s['id'] ?>)" class="btn btn-outline-danger btn-sm rounded-2" title="ลบ"><i class="fas fa-trash"></i></button>
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

<!-- Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="sessionModalTitle">สร้างคาบ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <input type="hidden" id="m_id">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">วันที่ <span class="text-danger">*</span></label>
                    <input type="date" id="m_date" class="form-control rounded-3">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">คาบ/ช่วงเวลา</label>
                    <input type="text" id="m_period" class="form-control rounded-3" placeholder="เช่น คาบ 7-8 (14:30-16:30)">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">หัวข้อกิจกรรม <span class="text-danger">*</span></label>
                    <input type="text" id="m_topic" class="form-control rounded-3" placeholder="หัวข้อในคาบนี้">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">รายละเอียดเพิ่มเติม</label>
                    <textarea id="m_description" class="form-control rounded-3" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">สถานะ</label>
                    <select id="m_status" class="form-select rounded-3">
                        <option value="planned">วางแผน</option>
                        <option value="done">เสร็จแล้ว</option>
                        <option value="cancelled">ยกเลิก</option>
                    </select>
                </div>
                <button onclick="saveSession()" class="btn w-100 text-white rounded-3 fw-bold" style="background:#7c3aed">
                    <i class="fas fa-save me-1"></i>บันทึก
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CLUB_ID = <?= json_encode($club_id) ?>;
let sessionModal;
document.addEventListener('DOMContentLoaded', () => { sessionModal = new bootstrap.Modal(document.getElementById('sessionModal')); });

function openSessionModal(s) {
    document.getElementById('sessionModalTitle').textContent = s ? 'แก้ไขคาบ' : 'สร้างคาบ';
    document.getElementById('m_id').value          = s ? s.id : '';
    document.getElementById('m_date').value        = s ? s.session_date : '';
    document.getElementById('m_period').value      = s ? (s.period || '') : '';
    document.getElementById('m_topic').value       = s ? (s.topic || '') : '';
    document.getElementById('m_description').value = s ? (s.description || '') : '';
    document.getElementById('m_status').value      = s ? s.status : 'planned';
    sessionModal.show();
}

async function saveSession() {
    const date  = document.getElementById('m_date').value;
    const topic = document.getElementById('m_topic').value.trim();
    if (!date)  { Swal.fire({ icon:'warning', title:'กรุณาเลือกวันที่', confirmButtonColor:'#7c3aed' }); return; }
    if (!topic) { Swal.fire({ icon:'warning', title:'กรุณากรอกหัวข้อ', confirmButtonColor:'#7c3aed' }); return; }

    const body = {
        id:          parseInt(document.getElementById('m_id').value) || 0,
        club_id:     CLUB_ID,
        session_date: date,
        period:      document.getElementById('m_period').value.trim(),
        topic,
        description: document.getElementById('m_description').value.trim(),
        status:      document.getElementById('m_status').value,
    };
    const res  = await fetch('/club/api/save_session.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
    const data = await res.json();
    if (data.status === 'success') {
        sessionModal.hide();
        await Swal.fire({ icon:'success', title:'บันทึกสำเร็จ', timer:1000, showConfirmButton:false });
        location.reload();
    } else {
        Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:data.message, confirmButtonColor:'#7c3aed' });
    }
}

async function deleteSession(id) {
    const { isConfirmed } = await Swal.fire({ icon:'warning', title:'ลบคาบนี้?', showCancelButton:true, confirmButtonText:'ลบ', cancelButtonText:'ยกเลิก', confirmButtonColor:'#dc3545' });
    if (!isConfirmed) return;
    const res  = await fetch('/club/api/save_session.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ id, _delete:true }) });
    const data = await res.json();
    if (data.status === 'success') { location.reload(); }
    else { Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:data.message, confirmButtonColor:'#7c3aed' }); }
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
