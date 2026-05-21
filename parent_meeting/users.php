<?php
/**
 * parent_meeting/users.php - หน้าจัดการผู้ใช้งานระบบ (เฉพาะแอดมิน)
 */
require_once __DIR__ . '/config.php';
checkRole(['admin']); // เฉพาะแอดมินเท่านั้น

$pageTitle = 'จัดการผู้ใช้งาน';
$pageSubtitle = 'จัดการรายชื่อผู้ใช้งานและระดับสิทธิ์การเข้าถึงระบบ';
$activePage = 'users';

$pdo = getPmPdo();

try {
    // ดึงผู้ใช้งานทั้งหมด
    $stmt = $pdo->query("SELECT id, fullname, username, role, created_at FROM pm_users ORDER BY role, fullname");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('[Parent Meeting] Users Fetch Error: ' . $e->getMessage());
    $users = [];
}

require_once __DIR__ . '/components/layout_start.php';
?>

<!-- หัวข้อและการจัดการหลัก -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <h5 class="m-0 font-black text-dark-blue">รายชื่อผู้ใช้งานในระบบ</h5>
    <button type="button" class="btn btn-primary rounded-3 font-bold shadow-sm" onclick="openAddModal()">
        <i class="bi bi-person-plus-fill me-1"></i> เพิ่มผู้ใช้ใหม่
    </button>
</div>

<div class="alert alert-info rounded-3 text-sm d-flex align-items-start py-3 px-4 mb-4 border-0" role="alert" style="background-color: rgba(13, 110, 253, 0.08); color: #084298;">
    <i class="bi bi-info-circle-fill me-2 fs-5 mt-0.5"></i>
    <div>
        <span class="font-bold">ระบบล็อกอินเชื่อมกับฐานข้อมูลกลาง (LLW)</span><br>
        บัญชีผู้ใช้งานและรหัสผ่านทั้งหมดอ้างอิงจากฐานข้อมูลหลักของโรงเรียนโดยตรง เมื่อผู้ใช้งานในระบบหลักเข้าใช้งานระบบประชุมผู้ปกครองเป็นครั้งแรก บัญชีและบทบาทของพวกเขาจะได้รับการซิงค์และบันทึกในตารางผู้ใช้งานของระบบนี้โดยอัตโนมัติ
    </div>
</div>

<!-- ตารางรายชื่อผู้ใช้งาน -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive p-3">
            <table id="usersTable" class="table table-hover w-100 mb-0">
                <thead>
                    <tr>
                        <th width="10%">ลำดับ</th>
                        <th width="30%">ชื่อ - นามสกุล</th>
                        <th width="25%">ชื่อผู้ใช้ (Username)</th>
                        <th width="20%">บทบาท (Role)</th>
                        <th width="15%" class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($users as $u): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="font-bold text-dark"><?= esc($u['fullname']) ?></span></td>
                            <td><code><?= esc($u['username']) ?></code></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge bg-danger-subtle text-danger rounded px-2.5 py-1.5 font-bold">
                                        <i class="bi bi-shield-lock me-1"></i> ผู้ดูแลระบบ
                                    </span>
                                <?php elseif ($u['role'] === 'executive'): ?>
                                    <span class="badge bg-success-subtle text-success rounded px-2.5 py-1.5 font-bold">
                                        <i class="bi bi-briefcase me-1"></i> ผู้บริหาร
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-primary-subtle text-primary rounded px-2.5 py-1.5 font-bold">
                                        <i class="bi bi-person-badge me-1"></i> ครูที่ปรึกษา
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group gap-1">
                                    <button class="btn btn-sm btn-outline-info rounded px-2 py-1" onclick="openEditModal(<?= $u['id'] ?>)" title="แก้ไขข้อมูล">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <?php if ($u['id'] != $_SESSION['pm_user_id']): ?>
                                        <button class="btn btn-sm btn-outline-danger rounded px-2 py-1" onclick="deleteUser(<?= $u['id'] ?>)" title="ลบข้อมูล">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary rounded px-2 py-1" disabled title="บัญชีของคุณกำลังใช้งานอยู่">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal สำหรับ เพิ่ม / แก้ไข ผู้ใช้งาน -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form id="userForm">
                <input type="hidden" name="action" id="action" value="save_user">
                <input type="hidden" name="user_id" id="user_id" value="">
                
                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title font-black text-dark-blue" id="userModalLabel">จัดการข้อมูลผู้ใช้งาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ชื่อ - นามสกุลจริง</label>
                            <input type="text" class="form-control rounded-3 py-2 text-sm" name="fullname" id="fullname" placeholder="กรอกชื่อ-นามสกุลผู้ใช้งาน" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ชื่อผู้ใช้สำหรับลงชื่อเข้าใช้ (Username)</label>
                            <input type="text" class="form-control rounded-3 py-2 text-sm" name="username" id="username" placeholder="ภาษาอังกฤษหรือตัวเลข (ไม่มีช่องว่าง)" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">รหัสผ่าน (Password)</label>
                            <input type="password" class="form-control rounded-3 py-2 text-sm" name="password" id="password" placeholder="ตั้งรหัสผ่านความยาว 6 ตัวอักษรขึ้นไป">
                            <small class="text-muted id-note d-none">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่านเดิม</small>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">สิทธิ์การใช้งาน (Role)</label>
                            <select class="form-select rounded-3 py-2 text-sm" name="role" id="role" required>
                                <option value="">-- เลือกสิทธิ์ --</option>
                                <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                                <option value="executive">ผู้บริหาร (Executive)</option>
                                <option value="teacher">ครูที่ปรึกษา (Teacher)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-3 text-sm font-bold" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-3 text-sm font-bold" id="btnSaveUser">
                        <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // โหลดตาราง DataTable
    $('#usersTable').DataTable();
    
    // บันทึกข้อมูลแบบ AJAX
    const userForm = document.getElementById('userForm');
    userForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const userId = document.getElementById('user_id').value;
        const passwordInput = document.getElementById('password');
        
        if (userId === '' && passwordInput.value.length < 4) {
            showAlert('เกิดข้อผิดพลาด', 'กรุณาระบุรหัสผ่านให้ผู้ใช้ใหม่อย่างน้อย 4 ตัวอักษร', 'error');
            return;
        }

        const formData = new FormData(this);
        const btnSave = document.getElementById('btnSaveUser');
        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';
        
        fetch('api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-save me-1"></i> บันทึกข้อมูล';
            
            if (data.status === 'success') {
                $('#userModal').modal('hide');
                showAlert('สำเร็จ', 'บันทึกข้อมูลเรียบร้อยแล้ว', 'success')
                .then(() => {
                    location.reload();
                });
            } else {
                showAlert('เกิดข้อผิดพลาด', data.message || 'บันทึกไม่สำเร็จ', 'error');
            }
        })
        .catch(err => {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-save me-1"></i> บันทึกข้อมูล';
            console.error(err);
            showAlert('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        });
    });
});

// เปิดโหมดเพิ่มใหม่
function openAddModal() {
    document.getElementById('userModalLabel').textContent = 'เพิ่มผู้ใช้เข้าระบบ';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('action').value = 'save_user';
    document.getElementById('password').required = true;
    document.querySelector('.id-note').classList.add('d-none');
    $('#userModal').modal('show');
}

// เปิดโหมดแก้ไข
function openEditModal(userId) {
    document.getElementById('userModalLabel').textContent = 'แก้ไขข้อมูลผู้ใช้งาน';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = userId;
    document.getElementById('action').value = 'save_user';
    document.getElementById('password').required = false;
    document.querySelector('.id-note').classList.remove('d-none');

    // ดึงข้อมูลผ่าน API
    fetch(`api.php?action=get_user&id=${userId}`)
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const u = data.data;
            document.getElementById('fullname').value = u.fullname;
            document.getElementById('username').value = u.username;
            document.getElementById('role').value = u.role;
            $('#userModal').modal('show');
        } else {
            showAlert('ข้อผิดพลาด', 'ไม่สามารถดึงข้อมูลได้: ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('ข้อผิดพลาด', 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้', 'error');
    });
}

// ลบผู้ใช้
function deleteUser(userId) {
    confirmDelete('ยืนยันการลบผู้ใช้?', 'ผู้ใช้คนนี้จะถูกถอดถอนออกจากระบบทันที!', function() {
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_user',
                user_id: userId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('สำเร็จ', 'ลบผู้ใช้งานเรียบร้อยแล้ว', 'success')
                .then(() => {
                    location.reload();
                });
            } else {
                showAlert('เกิดข้อผิดพลาด', data.message || 'ลบไม่สำเร็จ', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับระบบเซิร์ฟเวอร์ได้', 'error');
        });
    });
}
</script>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
