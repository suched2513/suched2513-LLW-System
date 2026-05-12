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
        $pdo->exec("CREATE TABLE IF NOT EXISTS duty_groups (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, color VARCHAR(7) NOT NULL DEFAULT '#3B82F6', description VARCHAR(255) NULL, group_type ENUM('day','night','chairman') NOT NULL DEFAULT 'day', sort_order TINYINT NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS duty_group_members (id INT AUTO_INCREMENT PRIMARY KEY, group_id INT NOT NULL, teacher_id INT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_gm(group_id,teacher_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch (Exception $e) { error_log($e->getMessage()); }
// Auto-migrate: เพิ่ม group_type column ถ้ายังไม่มี
try {
    $c = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='duty_groups' AND COLUMN_NAME='group_type'");
    if ((int)$c->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE duty_groups ADD COLUMN group_type ENUM('day','night','chairman') NOT NULL DEFAULT 'day' AFTER description");
    }
} catch (Exception $e) { error_log($e->getMessage()); }

$typeFilter = in_array($_GET['type']??'', ['day','night','chairman']) ? $_GET['type'] : 'day';
$typeLabels = ['day'=>'☀️ เวรกลางวัน','night'=>'🌙 เวรกลางคืน','chairman'=>'👑 ประธานกิจกรรม'];

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_group') {
        $name  = trim($_POST['name'] ?? '');
        $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['color']??'') ? $_POST['color'] : '#3B82F6';
        $desc  = trim($_POST['description'] ?? '');
        $gtype = in_array($_POST['group_type']??'', ['day','night','chairman']) ? $_POST['group_type'] : 'day';
        if ($name) {
            $pdo->prepare("INSERT INTO duty_groups (name,color,description,group_type) VALUES (?,?,?,?)")->execute([$name,$color,$desc,$gtype]);
            $msg = 'success:สร้างกลุ่ม "' . $name . '" เรียบร้อย';
        } else { $msg = 'error:กรุณากรอกชื่อกลุ่ม'; }
    }

    if ($action === 'edit_group') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['color']??'') ? $_POST['color'] : '#3B82F6';
        $desc  = trim($_POST['description'] ?? '');
        $gtype = in_array($_POST['group_type']??'', ['day','night','chairman']) ? $_POST['group_type'] : 'day';
        if ($id && $name) {
            $pdo->prepare("UPDATE duty_groups SET name=?,color=?,description=?,group_type=? WHERE id=?")->execute([$name,$color,$desc,$gtype,$id]);
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

// ── ดึงข้อมูล (filter ตาม type) ──
$stmtGroups = $pdo->prepare("
    SELECT g.*, COUNT(m.id) as member_count
    FROM duty_groups g
    LEFT JOIN duty_group_members m ON m.group_id=g.id
    WHERE g.status='active' AND g.group_type=?
    GROUP BY g.id
    ORDER BY g.sort_order, g.name
");
$stmtGroups->execute([$typeFilter]);
$groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

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

<?php if ($msg): 
    $isErr = (strpos($msg, 'error:') === 0); 
    $msgTxt = substr($msg, strpos($msg, ':') + 1);
?>
<script>
Swal.fire({
    icon: '<?= $isErr ? 'error' : 'success' ?>',
    title: '<?= $isErr ? 'ผิดพลาด' : 'สำเร็จ' ?>',
    text: '<?= htmlspecialchars($msgTxt, ENT_QUOTES) ?>',
    confirmButtonColor: '#2563eb'
});
</script>
<?php endif; ?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-bold"><i class="fas fa-layer-group me-2 text-primary"></i>กลุ่มเวร</h4>
        <small class="text-muted">จัดกลุ่มครูล่วงหน้า แล้วนำไปจัดลงปฏิทิน</small>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i> สร้างกลุ่มใหม่
    </button>
</div>

<!-- Type Tabs -->
<ul class="nav nav-pills mb-4 gap-2">
    <?php foreach ($typeLabels as $tk => $tl): ?>
    <li class="nav-item">
        <a class="nav-link rounded-pill border px-4 <?= $typeFilter===$tk ? 'active bg-primary text-white border-primary shadow-sm' : 'bg-white text-dark' ?>" 
           href="?type=<?= $tk ?>">
            <?= $tl ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if (empty($groups)): ?>
<div class="card border-0 shadow-sm rounded-4 text-center py-5">
    <div class="card-body">
        <i class="fas fa-layer-group fa-3x mb-3 opacity-25 d-block"></i>
        <h5 class="text-muted">ยังไม่มีกลุ่มเวรในประเภทนี้</h5>
        <p class="text-muted small">กดปุ่ม "สร้างกลุ่มใหม่" เพื่อเริ่มต้นจัดกลุ่มครู</p>
    </div>
</div>
<?php else: ?>

<!-- Groups Grid -->
<div class="row g-4">
<?php foreach ($groups as $g):
    $members = $memberMap[$g['id']] ?? [];
    $memberIds = array_column($members, 'id');
?>
<div class="col-md-6 col-xl-4">
    <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden animate__animated animate__fadeIn" 
         style="border-top: 5px solid <?= htmlspecialchars($g['color']) ?> !important;">
        <div class="card-body p-4">
            <!-- Group Header -->
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle me-3 flex-shrink-0 shadow-sm"
                     style="width:45px;height:45px;background:<?= htmlspecialchars($g['color']) ?>;display:flex;align-items:center;justify-content:center;color:white;">
                     <i class="fas fa-users"></i>
                </div>
                <div class="flex-fill">
                    <h6 class="mb-0 fw-bold fs-5 text-slate-800"><?= htmlspecialchars($g['name']) ?></h6>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="badge rounded-pill bg-light text-dark border small" style="font-size:10px">
                            <?= $typeLabels[$g['group_type']] ?? $g['group_type'] ?>
                        </span>
                        <small class="text-muted fw-bold"><?= count($members) ?> คน</small>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light rounded-circle shadow-sm" data-bs-toggle="dropdown" style="width:32px;height:32px;">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3">
                        <li><a class="dropdown-item py-2" href="#" onclick='openEditGroup(<?= json_encode($g,JSON_UNESCAPED_UNICODE) ?>)'>
                            <i class="fas fa-edit me-2 text-primary"></i>แก้ไขกลุ่ม</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="#" onclick='deleteGroup(<?= $g['id'] ?>, "<?= htmlspecialchars($g['name'],ENT_QUOTES) ?>")'>
                            <i class="fas fa-trash me-2"></i>ลบกลุ่ม</a></li>
                    </ul>
                </div>
            </div>

            <!-- Members -->
            <div class="mb-4" style="min-height:80px">
                <?php if (empty($members)): ?>
                    <p class="text-muted small fst-italic py-2">ยังไม่มีสมาชิกในกลุ่มนี้</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($members as $m): ?>
                        <span class="badge rounded-pill bg-blue-50 text-blue-600 border border-blue-100 fw-medium">
                            <?= htmlspecialchars($m['prefix'].$m['full_name']) ?>
                        </span>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <button class="btn btn-primary btn-sm w-100 rounded-pill py-2 shadow-sm"
                    onclick='openMemberModal(<?= $g['id'] ?>, "<?= htmlspecialchars($g['name'],ENT_QUOTES) ?>", <?= json_encode($memberIds) ?>)'>
                <i class="fas fa-users-cog me-1"></i> จัดการสมาชิก
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ Modal Area (Moved to bottom for better z-index context) ═══ -->
<div id="modalContainer">

    <!-- Modal: สร้างกลุ่ม -->
    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content border-0 shadow-lg rounded-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_group">
                <div class="modal-header border-bottom-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i>สร้างกลุ่มเวรใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทกลุ่ม <span class="text-danger">*</span></label>
                        <select name="group_type" class="form-select rounded-3">
                            <option value="day" <?= $typeFilter==='day'?'selected':'' ?>>☀️ เวรกลางวัน</option>
                            <option value="night" <?= $typeFilter==='night'?'selected':'' ?>>🌙 เวรกลางคืน</option>
                            <option value="chairman" <?= $typeFilter==='chairman'?'selected':'' ?>>👑 ประธานกิจกรรม</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อกลุ่ม <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control rounded-3" placeholder="เช่น กลุ่ม ก, ทีม 1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">สีประจำกลุ่ม</label>
                        <div class="d-flex gap-3 align-items-center">
                            <input type="color" name="color" class="form-control form-control-color border-0" value="#3B82F6" style="width:60px;height:45px">
                            <div class="d-flex gap-2 flex-wrap" id="colorPresets">
                                <?php foreach(['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#06B6D4','#EC4899','#6B7280'] as $c): ?>
                                <div class="rounded-circle shadow-sm border border-2 border-white" style="width:30px;height:30px;background:<?=$c?>;cursor:pointer;"
                                     onclick="document.querySelector('#createModal [name=color]').value='<?=$c?>'"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">คำอธิบาย (ไม่บังคับ)</label>
                        <input type="text" name="description" class="form-control rounded-3" placeholder="เช่น เวรสัปดาห์คี่">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="fas fa-save me-1"></i>สร้างกลุ่ม</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: แก้ไขกลุ่ม -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content border-0 shadow-lg rounded-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_group">
                <input type="hidden" name="id" id="editGroupId">
                <div class="modal-header border-bottom-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2 text-primary"></i>แก้ไขกลุ่ม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทกลุ่ม</label>
                        <select name="group_type" id="editGroupType" class="form-select rounded-3">
                            <option value="day">☀️ เวรกลางวัน</option>
                            <option value="night">🌙 เวรกลางคืน</option>
                            <option value="chairman">👑 ประธานกิจกรรม</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อกลุ่ม <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editGroupName" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">สีประจำกลุ่ม</label>
                        <input type="color" name="color" id="editGroupColor" class="form-control form-control-color border-0" style="width:60px;height:45px">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">คำอธิบาย</label>
                        <input type="text" name="description" id="editGroupDesc" class="form-control rounded-3">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="fas fa-save me-1"></i>บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: จัดการสมาชิก -->
    <div class="modal fade" id="memberModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" class="modal-content border-0 shadow-lg rounded-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_members">
                <input type="hidden" name="group_id" id="memberGroupId">
                <div class="modal-header border-bottom-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-users-cog me-2 text-primary"></i>สมาชิกกลุ่ม: <span id="memberGroupName" class="text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 rounded-start-3"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="memberSearch" class="form-control border-start-0 rounded-end-3" placeholder="ค้นหาชื่อครู..." oninput="filterMembers()">
                        </div>
                    </div>
                    <div class="form-check mb-3 ms-2">
                        <input type="checkbox" class="form-check-input" id="selectAllMembers"
                               onchange="document.querySelectorAll('.member-check').forEach(c=>c.checked=this.checked)">
                        <label class="form-check-label fw-bold text-slate-700" for="selectAllMembers">เลือกทั้งหมดในรายชื่อ</label>
                    </div>
                    <div style="max-height:400px;overflow-y:auto;" class="border rounded-3 p-2 bg-slate-50">
                        <div class="row g-1">
                            <?php foreach ($teachers as $t): ?>
                            <div class="col-md-6 member-item" data-name="<?= htmlspecialchars(mb_strtolower($t['full_name'])) ?>">
                                <div class="form-check p-2 hover:bg-white rounded-2 transition-all">
                                    <input type="checkbox" class="form-check-input member-check ms-0 me-2"
                                           name="teacher_ids[]" value="<?= $t['id'] ?>"
                                           id="mt_<?= $t['id'] ?>">
                                    <label class="form-check-label small fw-medium" for="mt_<?= $t['id'] ?>" style="cursor:pointer">
                                        <?= htmlspecialchars($t['prefix'].$t['full_name']) ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="fas fa-save me-1"></i>บันทึกสมาชิก</button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_group">
    <input type="hidden" name="id" id="deleteGroupId">
</form>

<style>
.bg-blue-50 { background-color: #eff6ff; }
.text-blue-600 { color: #2563eb; }
.border-blue-100 { border-color: #dbeafe; }
.hover\:bg-white:hover { background-color: white; }
.transition-all { transition: all 0.2s ease-in-out; }
.rounded-4 { border-radius: 1rem !important; }
.rounded-3 { border-radius: 0.75rem !important; }

/* Fix Bootstrap Modal Backdrop issue */
.modal-backdrop {
    z-index: 1040 !important;
}
.modal {
    z-index: 1055 !important;
}
</style>

<script>
function openEditGroup(g) {
    document.getElementById('editGroupId').value   = g.id;
    document.getElementById('editGroupName').value = g.name;
    document.getElementById('editGroupColor').value= g.color;
    document.getElementById('editGroupDesc').value = g.description || '';
    document.getElementById('editGroupType').value = g.group_type || 'day';
    
    const modalEl = document.getElementById('editModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function deleteGroup(id, name) {
    Swal.fire({
        icon:'warning', 
        title:'ลบกลุ่ม?',
        text: 'ยืนยันลบกลุ่ม "' + name + '"? ข้อมูลสมาชิกในกลุ่มนี้จะถูกยกเลิก',
        showCancelButton:true, 
        confirmButtonColor:'#ef4444',
        confirmButtonText:'ลบ', 
        cancelButtonText:'ยกเลิก',
        reverseButtons: true
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
    const checks = document.querySelectorAll('.member-check');
    const memberIdsSet = new Set(currentMemberIds.map(Number));
    
    checks.forEach(c => {
        c.checked = memberIdsSet.has(Number(c.value));
    });
    
    document.getElementById('memberSearch').value = '';
    document.querySelectorAll('.member-item').forEach(el => el.style.display = '');
    document.getElementById('selectAllMembers').checked = false;
    
    const modalEl = document.getElementById('memberModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function filterMembers() {
    const q = document.getElementById('memberSearch').value.toLowerCase();
    document.querySelectorAll('.member-item').forEach(el => {
        const name = el.getAttribute('data-name');
        el.style.display = name.includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../../components/layout_end.php'; ?>
