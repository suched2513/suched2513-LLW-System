<?php
/**
 * duty/admin/groups.php — จัดการกลุ่มเวร
 */
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin','wfh_admin'])) {
    header('Location: /login.php'); exit();
}

$pdo = getPdo();

// Auto-migrate: สร้างตารางถ้ายังไม่มี
try {
    $has = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='duty_groups'")->fetchColumn();
    if (!$has) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS duty_groups (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, color VARCHAR(7) NOT NULL DEFAULT '#3B82F6', description VARCHAR(255) NULL, sort_order TINYINT NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS duty_group_members (id INT AUTO_INCREMENT PRIMARY KEY, group_id INT NOT NULL, teacher_id INT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_gm(group_id,teacher_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch (Exception $e) { error_log($e->getMessage()); }

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_group') {
        $name  = trim($_POST['name'] ?? '');
        $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['color']??'') ? $_POST['color'] : '#3B82F6';
        $desc  = trim($_POST['description'] ?? '');
        if ($name) {
            $pdo->prepare("INSERT INTO duty_groups (name,color,description) VALUES (?,?,?)")->execute([$name,$color,$desc]);
            $msg = 'success:สร้างกลุ่ม "' . $name . '" เรียบร้อย';
        } else { $msg = 'error:กรุณากรอกชื่อกลุ่ม'; }
    }

    if ($action === 'edit_group') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['color']??'') ? $_POST['color'] : '#3B82F6';
        $desc  = trim($_POST['description'] ?? '');
        if ($id && $name) {
            $pdo->prepare("UPDATE duty_groups SET name=?,color=?,description=? WHERE id=?")->execute([$name,$color,$desc,$id]);
            $msg = 'success:บันทึกเรียบร้อย';
        }
    }

    if ($action === 'delete_group') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE duty_groups SET status='inactive' WHERE id=?")->execute([$id]);
        $msg = 'success:ปิดการใช้งานกลุ่มเรียบร้อย';
    }

    if ($action === 'save_members') {
        $groupId    = (int)($_POST['group_id'] ?? 0);
        $teacherIds = array_map('intval', $_POST['teacher_ids'] ?? []);
        if ($groupId) {
            $pdo->prepare("DELETE FROM duty_group_members WHERE group_id=?")->execute([$groupId]);
            $stmt = $pdo->prepare("INSERT IGNORE INTO duty_group_members (group_id, teacher_id) VALUES (?,?)");
            foreach ($teacherIds as $tid) { if ($tid) $stmt->execute([$groupId,$tid]); }
            $msg = 'success:บันทึกสมาชิกกลุ่มเรียบร้อย';
        }
    }
}

// ── ดึงข้อมูล ──
$groups = $pdo->query("
    SELECT g.*, COUNT(m.id) as member_count
    FROM duty_groups g
    LEFT JOIN duty_group_members m ON m.group_id=g.id
    WHERE g.status='active'
    GROUP BY g.id
    ORDER BY g.sort_order, g.name
")->fetchAll(PDO::FETCH_ASSOC);

$teachers = $pdo->query("SELECT id, prefix, full_name FROM duty_teachers WHERE status='active' AND TRIM(full_name) != '' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// ดึงสมาชิกทุกกลุ่มพร้อมกัน
$memberMap = [];
if ($groups) {
    $gids = implode(',', array_column($groups,'id'));
    $rows = $pdo->query("SELECT m.group_id, dt.id, dt.prefix, dt.full_name FROM duty_group_members m JOIN duty_teachers dt ON dt.id=m.teacher_id WHERE m.group_id IN ($gids) ORDER BY dt.full_name");
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $memberMap[$r['group_id']][] = $r;
    }
}

$pageTitle    = 'กลุ่มเวร';
$pageSubtitle = 'จัดการกลุ่มครูเวรและสมาชิก';
$activeSystem = 'duty';
require_once __DIR__ . '/../../components/layout_start.php';
?>

<?php if ($msg): $isErr = str_starts_with($msg,'error:'); ?>
<script>
Swal.fire({icon:'<?= $isErr?'error':'success' ?>',title:'<?= $isErr?'ผิดพลาด':'สำเร็จ' ?>',
    text:'<?= htmlspecialchars(substr($msg,strpos($msg,':')+1),ENT_QUOTES) ?>',confirmButtonColor:'#2563eb'});
</script>
<?php endif; ?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="fas fa-layer-group me-2 text-primary"></i>กลุ่มเวร</h4>
        <small class="text-muted">จัดกลุ่มครูล่วงหน้า แล้วนำไปจัดลงปฏิทิน</small>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i> สร้างกลุ่มใหม่
    </button>
</div>

<?php if (empty($groups)): ?>
<div class="text-center py-5 text-muted">
    <i class="fas fa-layer-group fa-3x mb-3 opacity-25 d-block"></i>
    ยังไม่มีกลุ่มเวร — กดปุ่ม "สร้างกลุ่มใหม่" เพื่อเริ่มต้น
</div>
<?php else: ?>

<!-- Groups Grid -->
<div class="row g-3">
<?php foreach ($groups as $g):
    $members = $memberMap[$g['id']] ?? [];
    $memberIds = array_column($members, 'id');
?>
<div class="col-md-6 col-xl-4">
    <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid <?= htmlspecialchars($g['color']) ?> !important;">
        <div class="card-body">
            <!-- Group Header -->
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle me-3 flex-shrink-0"
                     style="width:40px;height:40px;background:<?= htmlspecialchars($g['color']) ?>"></div>
                <div class="flex-fill">
                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($g['name']) ?></h6>
                    <small class="text-muted"><?= count($members) ?> คน</small>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick='openEditGroup(<?= json_encode($g,JSON_UNESCAPED_UNICODE) ?>)'>
                            <i class="fas fa-edit me-2"></i>แก้ไขกลุ่ม</a></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick='deleteGroup(<?= $g['id'] ?>, "<?= htmlspecialchars($g['name'],ENT_QUOTES) ?>")'>
                            <i class="fas fa-trash me-2"></i>ลบกลุ่ม</a></li>
                    </ul>
                </div>
            </div>

            <!-- Members -->
            <div class="mb-3" style="min-height:60px">
                <?php if (empty($members)): ?>
                    <p class="text-muted small fst-italic">ยังไม่มีสมาชิก</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($members as $m): ?>
                        <span class="badge rounded-pill text-bg-light border">
                            <?= htmlspecialchars($m['prefix'].$m['full_name']) ?>
                        </span>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <button class="btn btn-outline-primary btn-sm w-100"
                    onclick='openMemberModal(<?= $g['id'] ?>, "<?= htmlspecialchars($g['name'],ENT_QUOTES) ?>", <?= json_encode($memberIds) ?>)'>
                <i class="fas fa-users-cog me-1"></i> จัดการสมาชิก
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ Modal: สร้างกลุ่ม ═══ -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_group">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>สร้างกลุ่มเวรใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">ชื่อกลุ่ม <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="เช่น กลุ่ม ก, ทีม 1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">สีประจำกลุ่ม</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="color" name="color" class="form-control form-control-color" value="#3B82F6" style="width:50px">
                        <div class="d-flex gap-2 flex-wrap" id="colorPresets">
                            <?php foreach(['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#06B6D4','#EC4899','#6B7280'] as $c): ?>
                            <div class="rounded-circle" style="width:28px;height:28px;background:<?=$c?>;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #ccc"
                                 onclick="document.querySelector('[name=color]').value='<?=$c?>'"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">คำอธิบาย (ไม่บังคับ)</label>
                    <input type="text" name="description" class="form-control" placeholder="เช่น เวรสัปดาห์คี่">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>สร้างกลุ่ม</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Modal: แก้ไขกลุ่ม ═══ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_group">
            <input type="hidden" name="id" id="editGroupId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>แก้ไขกลุ่ม</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">ชื่อกลุ่ม <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="editGroupName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">สีประจำกลุ่ม</label>
                    <input type="color" name="color" id="editGroupColor" class="form-control form-control-color" style="width:50px">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">คำอธิบาย</label>
                    <input type="text" name="description" id="editGroupDesc" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Modal: จัดการสมาชิก ═══ -->
<div class="modal fade" id="memberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_members">
            <input type="hidden" name="group_id" id="memberGroupId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-users-cog me-2"></i>สมาชิกกลุ่ม: <span id="memberGroupName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" id="memberSearch" class="form-control" placeholder="🔍 ค้นหาชื่อครู..." oninput="filterMembers()">
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="selectAllMembers"
                           onchange="document.querySelectorAll('.member-check').forEach(c=>c.checked=this.checked)">
                    <label class="form-check-label fw-bold" for="selectAllMembers">เลือกทั้งหมด</label>
                </div>
                <div style="max-height:350px;overflow-y:auto;border:1px solid #dee2e6;border-radius:8px;padding:8px">
                    <?php foreach ($teachers as $t): ?>
                    <div class="member-item d-flex align-items-center py-1 border-bottom"
                         data-name="<?= htmlspecialchars(mb_strtolower($t['full_name'])) ?>">
                        <div class="form-check flex-fill mb-0">
                            <input type="checkbox" class="form-check-input member-check"
                                   name="teacher_ids[]" value="<?= $t['id'] ?>"
                                   id="mt_<?= $t['id'] ?>">
                            <label class="form-check-label" for="mt_<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['prefix'].$t['full_name']) ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>บันทึกสมาชิก</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_group">
    <input type="hidden" name="id" id="deleteGroupId">
</form>

<script>
function openEditGroup(g) {
    document.getElementById('editGroupId').value   = g.id;
    document.getElementById('editGroupName').value = g.name;
    document.getElementById('editGroupColor').value= g.color;
    document.getElementById('editGroupDesc').value = g.description || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteGroup(id, name) {
    Swal.fire({
        icon:'warning', title:'ลบกลุ่ม?',
        text: 'ยืนยันลบกลุ่ม "' + name + '"?',
        showCancelButton:true, confirmButtonColor:'#dc3545',
        confirmButtonText:'ลบ', cancelButtonText:'ยกเลิก'
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('deleteGroupId').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}

function openMemberModal(groupId, groupName, currentMemberIds) {
    document.getElementById('memberGroupId').value = groupId;
    document.getElementById('memberGroupName').textContent = groupName;
    // reset checkboxes
    document.querySelectorAll('.member-check').forEach(c => {
        c.checked = currentMemberIds.includes(parseInt(c.value));
    });
    document.getElementById('memberSearch').value = '';
    document.querySelectorAll('.member-item').forEach(el => el.style.display = '');
    document.getElementById('selectAllMembers').checked = false;
    new bootstrap.Modal(document.getElementById('memberModal')).show();
}

function filterMembers() {
    const q = document.getElementById('memberSearch').value.toLowerCase();
    document.querySelectorAll('.member-item').forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
