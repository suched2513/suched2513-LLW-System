<?php
/**
 * parent_meeting/meetings.php - จัดการบันทึกรายงานการประชุม
 */
require_once __DIR__ . '/config.php';
checkRole(['teacher', 'admin']); // เฉพาะครูและแอดมิน

$pageTitle = 'บันทึกการประชุม';
$pageSubtitle = 'เพิ่ม แก้ไข รายงานการประชุมผู้ปกครองประจำห้องเรียน';
$activePage = 'meetings';

$pdo = getPmPdo();

// ดึงข้อมูลห้องเรียนทั้งหมดเพื่อใช้ใน Dropdown
try {
    $classrooms = $pdo->query("SELECT * FROM pm_classrooms ORDER BY level, room_name")->fetchAll();
    
    // ดึงประวัติการประชุม
    // หากเป็นแอดมิน เห็นทั้งหมด, หากเป็นครูธรรมดา เห็นเฉพาะที่ตนเองสร้าง
    if ($_SESSION['pm_role'] === 'admin') {
        $stmt = $pdo->query("
            SELECT m.*, c.level, c.room_name, u.fullname as creator_name
            FROM pm_meetings m
            JOIN pm_classrooms c ON m.classroom_id = c.id
            JOIN pm_users u ON m.created_by = u.id
            ORDER BY m.meeting_date DESC, m.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT m.*, c.level, c.room_name, u.fullname as creator_name
            FROM pm_meetings m
            JOIN pm_classrooms c ON m.classroom_id = c.id
            JOIN pm_users u ON m.created_by = u.id
            WHERE m.created_by = ?
            ORDER BY m.meeting_date DESC, m.created_at DESC
        ");
        $stmt->execute([$_SESSION['pm_user_id']]);
    }
    $meetings = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('[Parent Meeting] Meetings Fetch Error: ' . $e->getMessage());
    $classrooms = [];
    $meetings = [];
}

require_once __DIR__ . '/components/layout_start.php';
?>

<!-- หัวข้อและการจัดการหลัก -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <h5 class="m-0 font-black text-dark-blue">รายการบันทึกการประชุมผู้ปกครอง</h5>
    <button type="button" class="btn btn-primary rounded-3 font-bold shadow-sm" onclick="openAddModal()">
        <i class="bi bi-plus-circle me-1"></i> เพิ่มบันทึกใหม่
    </button>
</div>

<!-- ตารางรายการข้อมูล -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive p-3">
            <table id="meetingsTable" class="table table-hover w-100 mb-0">
                <thead>
                    <tr>
                        <th width="8%">ลำดับ</th>
                        <th width="12%">วันที่ประชุม</th>
                        <th width="12%">เทอม/ปีการศึกษา</th>
                        <th width="12%">ห้องเรียน</th>
                        <th width="12%">จำนวนนักเรียน</th>
                        <th width="12%">ผู้ปกครอง (มา/ขาด)</th>
                        <th width="15%">ผู้บันทึก</th>
                        <th width="17%" class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($meetings as $m): 
                        $rate = $m['total_parents'] > 0 ? round(($m['attend_count'] / $m['total_parents']) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="font-bold text-dark"><?= th_date($m['meeting_date']) ?></span></td>
                            <td>เทอม <?= esc($m['semester'] . '/' . $m['academic_year']) ?></td>
                            <td><span class="badge bg-primary-subtle text-primary rounded px-2.5 py-1 font-bold">ม.<?= esc($m['level'] . '/' . $m['room_name']) ?></span></td>
                            <td><?= esc($m['total_students']) ?> คน</td>
                            <td>
                                <div class="font-bold text-success"><?= esc($m['attend_count']) ?> มา</div>
                                <div class="text-xs text-danger"><?= esc($m['absent_count']) ?> ขาด (<?= $rate ?>%)</div>
                            </td>
                            <td><small class="text-muted"><i class="bi bi-person me-1"></i><?= esc($m['creator_name']) ?></small></td>
                            <td class="text-center">
                                <div class="btn-group gap-1">
                                    <button class="btn btn-sm btn-outline-info rounded px-2 py-1" onclick="openEditModal(<?= $m['id'] ?>)" title="แก้ไขข้อมูล">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a href="<?= pm_url('print_report.php?id=' . $m['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded px-2 py-1" title="พิมพ์รายงาน / PDF">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger rounded px-2 py-1" onclick="deleteMeeting(<?= $m['id'] ?>)" title="ลบข้อมูล">
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

<!-- Modal สำหรับ เพิ่ม / แก้ไข ข้อมูล -->
<div class="modal fade" id="meetingModal" tabindex="-1" aria-labelledby="meetingModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 shadow border-0">
            <form id="meetingForm" enctype="multipart/form-data">
                <input type="hidden" name="action" id="action" value="save_meeting">
                <input type="hidden" name="meeting_id" id="meeting_id" value="">
                
                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title font-black text-dark-blue" id="meetingModalLabel">บันทึกรายงานการประชุม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- แถวที่ 1 -->
                        <div class="col-12 col-md-4">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">วันที่ประชุม</label>
                            <input type="date" class="form-control rounded-3 py-2 text-sm" name="meeting_date" id="meeting_date" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ภาคเรียน</label>
                            <select class="form-select rounded-3 py-2 text-sm" name="semester" id="semester" required>
                                <option value="1">ภาคเรียนที่ 1</option>
                                <option value="2">ภาคเรียนที่ 2</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ปีการศึกษา (พ.ศ.)</label>
                            <input type="number" class="form-control rounded-3 py-2 text-sm" name="academic_year" id="academic_year" value="2569" min="2560" required>
                        </div>

                        <!-- แถวที่ 2 -->
                        <div class="col-12 col-md-6">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ห้องเรียน</label>
                            <select class="form-select rounded-3 py-2 text-sm" name="classroom_id" id="classroom_id" required>
                                <option value="">-- เลือกห้องเรียน --</option>
                                <?php foreach ($classrooms as $c): ?>
                                    <option value="<?= $c['id'] ?>">ม.<?= esc($c['level'] . '/' . $c['room_name'] . ' - ครู' . $c['teacher_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">จำนวนนักเรียนทั้งหมดในห้อง (คน)</label>
                            <input type="number" class="form-control rounded-3 py-2 text-sm" name="total_students" id="total_students" placeholder="กรอกจำนวนนักเรียน" min="0" required>
                        </div>

                        <!-- แถวที่ 3 -->
                        <div class="col-12 col-md-4">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">จำนวนผู้ปกครองทั้งหมด (คน)</label>
                            <input type="number" class="form-control rounded-3 py-2 text-sm" name="total_parents" id="total_parents" placeholder="ผู้ปกครองทั้งหมด" min="0" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">จำนวนที่มาประชุม (คน)</label>
                            <input type="number" class="form-control rounded-3 py-2 text-sm" name="attend_count" id="attend_count" placeholder="ผู้ปกครองที่มา" min="0" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">จำนวนที่ขาดประชุม (คน)</label>
                            <input type="number" class="form-control rounded-3 py-2 text-sm" name="absent_count" id="absent_count" placeholder="ผู้ปกครองที่ขาด" min="0" readonly>
                        </div>

                        <!-- แถวที่ 4 -->
                        <div class="col-12">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">สรุปเนื้อหาการประชุม</label>
                            <textarea class="form-control rounded-3 text-sm" name="summary" id="summary" rows="3" placeholder="ระบุสรุปเนื้อหาสาระสำคัญในการประชุม..." required></textarea>
                        </div>

                        <!-- แถวที่ 5 -->
                        <div class="col-12 col-md-6">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ปัญหาหรืออุปสรรคที่พบ</label>
                            <textarea class="form-control rounded-3 text-sm" name="problems" id="problems" rows="2" placeholder="ระบุปัญหา อุปสรรค พฤติกรรม หรือข้อขัดข้องต่างๆ (หากมี)..."></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ข้อเสนอแนะ / แนวทางการแก้ไข</label>
                            <textarea class="form-control rounded-3 text-sm" name="suggestions" id="suggestions" rows="2" placeholder="ระบุข้อเสนอแนะในการแก้ไข ปรับปรุง หรือการสนับสนุนที่ต้องการ..."></textarea>
                        </div>

                        <!-- แถวที่ 6: อัปโหลดรูปภาพกิจกรรม -->
                        <div class="col-12 border-top pt-3 mt-3">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">แนบรูปภาพบรรยากาศการประชุม (แนบได้หลายรูปพร้อมกัน)</label>
                            <input type="file" class="form-control rounded-3 py-2 text-sm" name="images[]" id="images-input" multiple accept="image/*">
                            <small class="text-muted">ขนาดไฟล์สูงสุด 5MB ต่อไฟล์ รองรับ .jpg, .jpeg, .png</small>
                            
                            <!-- แสดงรูปภาพบรรยากาศเดิม (กรณีโหมดแก้ไข) -->
                            <div id="existing-images-container" class="mt-3 d-none">
                                <h6 class="text-xs font-bold text-dark mb-2">รูปกิจกรรมปัจจุบัน (คลิกเพื่อลบรูปที่ไม่ต้องการออก)</h6>
                                <div id="existing-images-grid" class="preview-container"></div>
                            </div>
                            
                            <!-- พรีวิวรูปภาพใหม่ที่กำลังจะอัปโหลด -->
                            <div id="new-images-container" class="mt-3 d-none">
                                <h6 class="text-xs font-bold text-dark mb-2">รูปภาพใหม่ที่รออัปโหลด</h6>
                                <div id="new-images-grid" class="preview-container"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4 font-bold text-sm" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 font-bold text-sm" id="btnSaveMeeting">
                        <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // โหลด DataTable
    const table = $('#meetingsTable').DataTable({
        order: [[1, 'desc']]
    });
    
    // คำนวณจำนวนผู้ปกครองขาดประชุมอัตโนมัติ
    const totalInput = document.getElementById('total_parents');
    const attendInput = document.getElementById('attend_count');
    const absentInput = document.getElementById('absent_count');
    
    function calcAbsent() {
        const total = parseInt(totalInput.value) || 0;
        const attend = parseInt(attendInput.value) || 0;
        absentInput.value = Math.max(0, total - attend);
    }
    
    totalInput.addEventListener('input', calcAbsent);
    attendInput.addEventListener('input', calcAbsent);

    // พรีวิวรูปภาพใหม่เมื่อเลือกไฟล์
    const imagesInput = document.getElementById('images-input');
    const newImagesContainer = document.getElementById('new-images-container');
    const newImagesGrid = document.getElementById('new-images-grid');

    imagesInput.addEventListener('change', function() {
        newImagesGrid.innerHTML = '';
        if (this.files && this.files.length > 0) {
            newImagesContainer.classList.remove('d-none');
            Array.from(this.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.innerHTML = `<img src="${e.target.result}"><div class="text-xs text-center text-white bg-dark bg-opacity-70 p-1 position-absolute bottom-0 w-100" style="font-size: 8px;">${file.name.substring(0, 10)}...</div>`;
                    newImagesGrid.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
        } else {
            newImagesContainer.classList.add('d-none');
        }
    });

    // บันทึกฟอร์มผ่าน AJAX
    const meetingForm = document.getElementById('meetingForm');
    meetingForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // ตรวจสอบความถูกต้องพื้นฐาน
        const totalParents = parseInt(totalInput.value) || 0;
        const attend = parseInt(attendInput.value) || 0;
        if (attend > totalParents) {
            showAlert('ข้อผิดพลาด', 'จำนวนผู้ปกครองที่เข้าร่วมประชุม ห้ามมากกว่าจำนวนผู้ปกครองทั้งหมด', 'error');
            return;
        }

        const formData = new FormData(this);
        
        // ปิดปุ่มชั่วคราว
        const btnSave = document.getElementById('btnSaveMeeting');
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
                $('#meetingModal').modal('hide');
                showAlert('สำเร็จ', 'บันทึกข้อมูลรายงานการประชุมเรียบร้อยแล้ว', 'success')
                .then(() => {
                    location.reload();
                });
            } else {
                showAlert('เกิดข้อผิดพลาด', data.message || 'บันทึกข้อมูลไม่สำเร็จ', 'error');
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

// เปิด Modal โหมดสร้างบันทึกใหม่
function openAddModal() {
    document.getElementById('meetingModalLabel').textContent = 'เพิ่มบันทึกรายงานการประชุม';
    document.getElementById('meetingForm').reset();
    document.getElementById('meeting_id').value = '';
    document.getElementById('action').value = 'save_meeting';
    
    // ซ่อนพรีวิวภาพเดิมและภาพใหม่
    document.getElementById('existing-images-container').classList.add('d-none');
    document.getElementById('existing-images-grid').innerHTML = '';
    document.getElementById('new-images-container').classList.add('d-none');
    document.getElementById('new-images-grid').innerHTML = '';
    
    // ตั้งค่าปีการศึกษาเริ่มต้นตามปีปัจจุบัน
    document.getElementById('academic_year').value = new Date().getFullYear() + 543;
    
    $('#meetingModal').modal('show');
}

// โหลดข้อมูลและเปิด Modal แก้ไข
function openEditModal(meetingId) {
    document.getElementById('meetingModalLabel').textContent = 'แก้ไขบันทึกรายงานการประชุม';
    document.getElementById('meetingForm').reset();
    document.getElementById('meeting_id').value = meetingId;
    document.getElementById('action').value = 'save_meeting';
    
    // เคลียร์ พรีวิว
    document.getElementById('existing-images-grid').innerHTML = '';
    document.getElementById('new-images-container').classList.add('d-none');
    document.getElementById('new-images-grid').innerHTML = '';

    // เรียกดึงข้อมูลประชุมจาก API
    fetch(`api.php?action=get_meeting&id=${meetingId}`)
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const m = data.data;
            document.getElementById('meeting_date').value = m.meeting_date;
            document.getElementById('semester').value = m.semester;
            document.getElementById('academic_year').value = m.academic_year;
            document.getElementById('classroom_id').value = m.classroom_id;
            document.getElementById('total_students').value = m.total_students;
            document.getElementById('total_parents').value = m.total_parents;
            document.getElementById('attend_count').value = m.attend_count;
            document.getElementById('absent_count').value = m.absent_count;
            document.getElementById('summary').value = m.summary;
            document.getElementById('problems').value = m.problems;
            document.getElementById('suggestions').value = m.suggestions;
            
            // โหลดรูปภาพประกอบเดิม
            const images = data.images || [];
            const grid = document.getElementById('existing-images-grid');
            if (images.length > 0) {
                document.getElementById('existing-images-container').classList.remove('d-none');
                images.forEach(img => {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.id = `existing-img-wrapper-${img.id}`;
                    item.innerHTML = `
                        <img src="${img.image_path}">
                        <button type="button" class="btn-remove" onclick="deleteMeetingImage(${img.id})" title="ลบภาพนี้">
                            <i class="bi bi-x"></i>
                        </button>
                    `;
                    grid.appendChild(item);
                });
            } else {
                document.getElementById('existing-images-container').classList.add('d-none');
            }
            
            $('#meetingModal').modal('show');
        } else {
            showAlert('ข้อผิดพลาด', 'ดึงข้อมูลไม่สำเร็จ: ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    });
}

// ฟังก์ชันลบรูปภาพเดี่ยวผ่าน AJAX
function deleteMeetingImage(imageId) {
    confirmDelete('ยืนยันการลบรูปภาพ?', 'รูปภาพจะถูกลบออกจากฐานข้อมูลและโฟลเดอร์ทันที!', function() {
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_image',
                image_id: imageId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const wrapper = document.getElementById(`existing-img-wrapper-${imageId}`);
                if (wrapper) {
                    wrapper.remove();
                }
                
                // เช็คว่าเหลือรูปเดิมหรือไม่ ถ้าไม่เหลือให้ซ่อนคอนเทนเนอร์
                const container = document.getElementById('existing-images-grid');
                if (container.children.length === 0) {
                    document.getElementById('existing-images-container').classList.add('d-none');
                }
                
                Swal.fire({
                    title: 'ลบสำเร็จ',
                    text: 'ลบรูปภาพเรียบร้อยแล้ว',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                showAlert('เกิดข้อผิดพลาด', data.message || 'ลบรูปไม่สำเร็จ', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('เกิดข้อผิดพลาด', 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้', 'error');
        });
    });
}

// ลบบันทึกรายงานการประชุมทั้งรายงาน
function deleteMeeting(meetingId) {
    confirmDelete('ยืนยันการลบรายงาน?', 'ข้อมูลบันทึกและสมาชิกเครือข่ายผู้ปกครองที่ผูกไว้ทั้งหมดจะถูกลบออก!', function() {
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_meeting',
                meeting_id: meetingId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('สำเร็จ', 'ลบรายงานการประชุมเรียบร้อยแล้ว', 'success')
                .then(() => {
                    location.reload();
                });
            } else {
                showAlert('เกิดข้อผิดพลาด', data.message || 'ลบรายงานไม่สำเร็จ', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('เกิดข้อผิดพลาด', 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้', 'error');
        });
    });
}
</script>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
