<?php
/**
 * parent_meeting/settings.php - หน้าจัดการข้อมูลห้องเรียนและครูที่ปรึกษา (เฉพาะแอดมิน)
 */
require_once __DIR__ . '/config.php';
checkRole(['admin']); // เฉพาะแอดมินเท่านั้น

$pageTitle = 'ตั้งค่าระบบ';
$pageSubtitle = 'จัดการข้อมูลระดับชั้นเรียน ห้องเรียน และครูที่ปรึกษาประจำชั้น';
$activePage = 'settings';

$pdo = getPmPdo();

try {
    // ดึงห้องเรียนทั้งหมด
    $stmt = $pdo->query("SELECT * FROM pm_classrooms ORDER BY level, room_name");
    $classrooms = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('[Parent Meeting] Classrooms Fetch Error: ' . $e->getMessage());
    $classrooms = [];
}

require_once __DIR__ . '/components/layout_start.php';
?>

<!-- หัวข้อและการจัดการหลัก -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <h5 class="m-0 font-black text-dark-blue">รายการห้องเรียนทั้งหมด</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-success rounded-3 font-bold shadow-sm" onclick="openSyncModal()" id="btnSync">
            <i class="bi bi-arrow-repeat me-1"></i> Sync จากข้อมูลกลาง LLW
        </button>
        <button type="button" class="btn btn-primary rounded-3 font-bold shadow-sm" onclick="openAddModal()">
            <i class="bi bi-plus-circle-fill me-1"></i> เพิ่มห้องเรียนใหม่
        </button>
    </div>
</div>

<!-- Alert: สถานะ Sync -->
<div id="syncAlert" class="alert alert-info d-none mb-3 rounded-3" role="alert">
    <i class="bi bi-info-circle me-2"></i> <span id="syncAlertText"></span>
</div>

<!-- ตารางรายชื่อห้องเรียน -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive p-3">
            <table id="classroomsTable" class="table table-hover w-100 mb-0">
                <thead>
                    <tr>
                        <th width="10%">ลำดับ</th>
                        <th width="20%">ระดับชั้นเรียน</th>
                        <th width="20%">ห้อง (ทับ)</th>
                        <th width="35%">ครูที่ปรึกษา</th>
                        <th width="15%" class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($classrooms as $c): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="badge bg-primary-subtle text-primary rounded px-2.5 py-1.5 font-bold">มัธยมศึกษาปีที่ <?= esc(str_replace('ม.', '', $c['level'])) ?></span></td>
                            <td>ห้อง <strong><?= esc($c['room_name']) ?></strong></td>
                            <td><span class="font-bold text-dark"><i class="bi bi-person-circle text-muted me-1.5"></i>ครู<?= esc($c['teacher_name']) ?></span></td>
                            <td class="text-center">
                                <div class="btn-group gap-1">
                                    <button class="btn btn-sm btn-outline-info rounded px-2 py-1" onclick="openEditModal(<?= $c['id'] ?>)" title="แก้ไขข้อมูล">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger rounded px-2 py-1" onclick="deleteClassroom(<?= $c['id'] ?>)" title="ลบข้อมูล">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal สำหรับ เพิ่ม / แก้ไข ห้องเรียน -->
<div class="modal fade" id="classroomModal" tabindex="-1" aria-labelledby="classroomModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form id="classroomForm">
                <input type="hidden" name="action" id="action" value="save_classroom">
                <input type="hidden" name="classroom_id" id="classroom_id" value="">
                
                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title font-black text-dark-blue" id="classroomModalLabel">จัดการข้อมูลห้องเรียน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ระดับชั้นเรียน</label>
                            <select class="form-select rounded-3 py-2 text-sm" name="level" id="level" required>
                                <option value="">-- เลือกระดับ --</option>
                                <option value="ม.1">ม.1</option>
                                <option value="ม.2">ม.2</option>
                                <option value="ม.3">ม.3</option>
                                <option value="ม.4">ม.4</option>
                                <option value="ม.5">ม.5</option>
                                <option value="ม.6">ม.6</option>
                            </select>
                        </div>
                        
                        <div class="col-12 col-sm-6">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ห้องเรียน (ระบุเฉพาะตัวเลข)</label>
                            <input type="text" class="form-control rounded-3 py-2 text-sm" name="room_name" id="room_name" placeholder="เช่น 1, 2, 3" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ชื่อ-นามสกุลครูที่ปรึกษาประจำชั้น</label>
                            <input type="text" class="form-control rounded-3 py-2 text-sm" name="teacher_name" id="teacher_name" placeholder="กรอกชื่อ-นามสกุลครูที่ปรึกษาประจำชั้น" required>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-3 text-sm font-bold" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-3 text-sm font-bold" id="btnSaveClassroom">
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
    $('#classroomsTable').DataTable();
    
    // บันทึกข้อมูลแบบ AJAX
    const classroomForm = document.getElementById('classroomForm');
    classroomForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const btnSave = document.getElementById('btnSaveClassroom');
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
                $('#classroomModal').modal('hide');
                showAlert('สำเร็จ', 'บันทึกข้อมูลห้องเรียนเรียบร้อยแล้ว', 'success')
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
            showAlert('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        });
    });
});

// เปิดโหมดเพิ่มใหม่
function openAddModal() {
    document.getElementById('classroomModalLabel').textContent = 'เพิ่มห้องเรียนประจำปีการศึกษา';
    document.getElementById('classroomForm').reset();
    document.getElementById('classroom_id').value = '';
    document.getElementById('action').value = 'save_classroom';
    $('#classroomModal').modal('show');
}

// เปิดโหมดแก้ไข
function openEditModal(classroomId) {
    document.getElementById('classroomModalLabel').textContent = 'แก้ไขข้อมูลห้องเรียน';
    document.getElementById('classroomForm').reset();
    document.getElementById('classroom_id').value = classroomId;
    document.getElementById('action').value = 'save_classroom';

    // ดึงข้อมูลผ่าน API
    fetch(`api.php?action=get_classroom&id=${classroomId}`)
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const c = data.data;
            document.getElementById('level').value = c.level;
            document.getElementById('room_name').value = c.room_name;
            document.getElementById('teacher_name').value = c.teacher_name;
            $('#classroomModal').modal('show');
        } else {
            showAlert('ข้อผิดพลาด', 'ไม่สามารถดึงข้อมูลได้: ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('ข้อผิดพลาด', 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้', 'error');
    });
}

// ลบห้องเรียน
function deleteClassroom(classroomId) {
    confirmDelete('ยืนยันการลบห้องเรียน?', 'ข้อมูลบันทึกและรูปภาพแนบทั้งหมดที่เชื่อมโยงกับห้องเรียนนี้จะถูกลบออกด้วย!', function() {
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_classroom',
                classroom_id: classroomId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('สำเร็จ', 'ลบข้อมูลห้องเรียนเรียบร้อยแล้ว', 'success')
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

<!-- ── SYNC MODAL: นำเข้าข้อมูลจากระบบกลาง LLW ── -->
<div class="modal fade" id="syncModal" tabindex="-1" aria-labelledby="syncModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4">
                <h5 class="modal-title font-black text-dark-blue" id="syncModalLabel">
                    <i class="bi bi-arrow-repeat me-2 text-success"></i>Sync ข้อมูลจากระบบกลาง LLW
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Loading state -->
                <div id="syncLoading" class="text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-3 text-muted">กำลังดึงข้อมูลจากระบบกลาง...</p>
                </div>

                <!-- Error state -->
                <div id="syncError" class="d-none">
                    <div class="alert alert-warning rounded-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>ไม่สามารถดึงข้อมูลกลางได้:</strong>
                        <span id="syncErrorMsg"></span>
                    </div>
                    <p class="text-muted text-sm">คุณยังสามารถเพิ่มห้องเรียนด้วยตนเองได้โดยคลิก "เพิ่มห้องเรียนใหม่"</p>
                </div>

                <!-- Data preview -->
                <div id="syncData" class="d-none">
                    <div class="alert alert-success rounded-3 mb-3">
                        <i class="bi bi-check-circle me-2"></i>
                        พบข้อมูลจากระบบกลาง LLW — เลือกห้องเรียนที่ต้องการนำเข้า
                    </div>

                    <!-- Teacher selector -->
                    <div class="mb-4">
                        <label class="form-label text-xs font-black uppercase text-muted tracking-wider">
                            <i class="bi bi-person-badge me-1"></i> เลือกครูที่ปรึกษา (สำหรับกรอกในฟิลด์อัตโนมัติ)
                        </label>
                        <select class="form-select rounded-3 py-2 text-sm" id="syncTeacherSelect">
                            <option value="">-- กรอกชื่อครูเองหลังนำเข้า --</option>
                        </select>
                    </div>

                    <!-- Classroom checkboxes -->
                    <div class="mb-3">
                        <label class="form-label text-xs font-black uppercase text-muted tracking-wider">
                            <i class="bi bi-grid me-1"></i> เลือกห้องเรียนที่ต้องการ Sync
                        </label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" onclick="checkAllSync(true)">เลือกทั้งหมด</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" onclick="checkAllSync(false)">ยกเลิกทั้งหมด</button>
                        </div>
                        <div id="syncClassroomList" class="row g-2" style="max-height: 300px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px;">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 px-4">
                <button type="button" class="btn btn-outline-secondary rounded-3 text-sm font-bold" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-success rounded-3 text-sm font-bold d-none" id="btnPerformSync" onclick="performSync()">
                    <i class="bi bi-cloud-download me-1"></i> นำเข้าห้องเรียนที่เลือก
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── SYNC MODAL LOGIC ──────────────────────────────────────────────
let syncClassroomData = [];

function openSyncModal() {
    syncClassroomData = [];
    document.getElementById('syncLoading').classList.remove('d-none');
    document.getElementById('syncError').classList.add('d-none');
    document.getElementById('syncData').classList.add('d-none');
    document.getElementById('btnPerformSync').classList.add('d-none');
    $('#syncModal').modal('show');
    fetchLlwData();
}

function fetchLlwData() {
    fetch('api.php?action=get_llw_classrooms')
    .then(res => res.json())
    .then(data => {
        document.getElementById('syncLoading').classList.add('d-none');
        if (data.status !== 'success') {
            document.getElementById('syncError').classList.remove('d-none');
            document.getElementById('syncErrorMsg').textContent = data.message || 'ข้อผิดพลาดไม่ทราบสาเหตุ';
            return;
        }

        const { teachers, classrooms } = data.data;
        syncClassroomData = classrooms;

        // Populate teacher dropdown
        const teacherSel = document.getElementById('syncTeacherSelect');
        teacherSel.innerHTML = '<option value="">-- กรอกชื่อครูเองหลังนำเข้า --</option>';
        teachers.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.teacher_name;
            opt.textContent = t.teacher_name;
            teacherSel.appendChild(opt);
        });

        // Populate classroom checkboxes
        const listEl = document.getElementById('syncClassroomList');
        if (classrooms.length === 0) {
            listEl.innerHTML = '<div class="col-12 text-muted text-sm">ไม่พบข้อมูลห้องเรียนในระบบกลาง (att_students)</div>';
        } else {
            listEl.innerHTML = '';
            classrooms.forEach((cls, idx) => {
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4 col-lg-3';
                col.innerHTML = `
                    <div class="form-check d-flex align-items-center gap-2 p-2 border rounded-3 bg-white hover:bg-gray-50" style="cursor:pointer;">
                        <input class="form-check-input sync-cls-chk" type="checkbox" value="${idx}" id="cls_${idx}" checked style="margin-top:0;">
                        <label class="form-check-label text-sm font-bold" for="cls_${idx}" style="cursor:pointer;">
                            <span class="badge bg-primary-subtle text-primary rounded me-1">${cls.level}</span>ห้อง ${cls.room}
                        </label>
                    </div>`;
                listEl.appendChild(col);
            });
        }

        document.getElementById('syncData').classList.remove('d-none');
        if (classrooms.length > 0) {
            document.getElementById('btnPerformSync').classList.remove('d-none');
        }
    })
    .catch(err => {
        document.getElementById('syncLoading').classList.add('d-none');
        document.getElementById('syncError').classList.remove('d-none');
        document.getElementById('syncErrorMsg').textContent = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้';
        console.error(err);
    });
}

function checkAllSync(state) {
    document.querySelectorAll('.sync-cls-chk').forEach(chk => { chk.checked = state; });
}

function performSync() {
    const teacherName = document.getElementById('syncTeacherSelect').value;
    const selected = [];

    document.querySelectorAll('.sync-cls-chk:checked').forEach(chk => {
        const idx = parseInt(chk.value);
        const cls = syncClassroomData[idx];
        if (cls) {
            selected.push({
                level: cls.level,
                room: cls.room,
                teacher_name: teacherName || 'ครูที่ปรึกษา'
            });
        }
    });

    if (selected.length === 0) {
        showAlert('แจ้งเตือน', 'กรุณาเลือกห้องเรียนอย่างน้อย 1 ห้อง', 'warning');
        return;
    }

    const btnSync = document.getElementById('btnPerformSync');
    btnSync.disabled = true;
    btnSync.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังนำเข้า...';

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'sync_classrooms_from_llw', classrooms: selected })
    })
    .then(res => res.json())
    .then(data => {
        btnSync.disabled = false;
        btnSync.innerHTML = '<i class="bi bi-cloud-download me-1"></i> นำเข้าห้องเรียนที่เลือก';

        if (data.status === 'success') {
            $('#syncModal').modal('hide');
            const alertEl = document.getElementById('syncAlert');
            document.getElementById('syncAlertText').textContent = data.message;
            alertEl.className = 'alert alert-success mb-3 rounded-3';
            alertEl.classList.remove('d-none');
            setTimeout(() => { location.reload(); }, 2000);
        } else {
            showAlert('เกิดข้อผิดพลาด', data.message || 'นำเข้าไม่สำเร็จ', 'error');
        }
    })
    .catch(err => {
        btnSync.disabled = false;
        btnSync.innerHTML = '<i class="bi bi-cloud-download me-1"></i> นำเข้าห้องเรียนที่เลือก';
        console.error(err);
        showAlert('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    });
}
</script>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
