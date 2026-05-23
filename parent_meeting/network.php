<?php
/**
 * parent_meeting/network.php - จัดการเครือข่ายผู้ปกครองประจำห้อง
 */
require_once __DIR__ . '/config.php';
checkRole(['teacher', 'admin']);

$pageTitle = 'เครือข่ายผู้ปกครอง';
$pageSubtitle = 'จัดการทำเนียบผู้แทนเครือข่ายผู้ปกครองประจำห้องเรียน';
$activePage = 'network';

$pdo = getPmPdo();

try {
    // ดึงห้องเรียนที่เรามีสิทธิ์เข้าถึง (ถ้าครูเห็นเฉพาะของห้องที่เคยส่งรายงานประชุม, ถ้าแอดมินเห็นทั้งหมด)
    if ($_SESSION['pm_role'] === 'admin') {
        $meetingsStmt = $pdo->query("
            SELECT m.id as meeting_id, m.semester, m.academic_year, c.level, c.room_name
            FROM pm_meetings m
            JOIN pm_classrooms c ON m.classroom_id = c.id
            ORDER BY m.academic_year DESC, m.semester DESC, c.level, c.room_name
        ");
    } else {
        $meetingsStmt = $pdo->prepare("
            SELECT m.id as meeting_id, m.semester, m.academic_year, c.level, c.room_name
            FROM pm_meetings m
            JOIN pm_classrooms c ON m.classroom_id = c.id
            WHERE m.created_by = ? OR CONCAT(c.level, '/', c.room_name) IN (
                SELECT classroom FROM llw_class_advisors WHERE user_id = ?
            )
            ORDER BY m.academic_year DESC, m.semester DESC, c.level, c.room_name
        ");
        $meetingsStmt->execute([$_SESSION['pm_user_id'], $_SESSION['user_id'] ?? 0]);
    }
    $myMeetings = $meetingsStmt->fetchAll();
    
    // ดึงรหัส Meeting ID ปัจจุบันที่กำลังแสดงผล (ถ้าไม่มี ให้เอาอันแรก)
    $activeMeetingId = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;
    if ($activeMeetingId === 0 && !empty($myMeetings)) {
        $activeMeetingId = $myMeetings[0]['meeting_id'];
    }
    
    // ดึงเครือข่ายผู้ปกครองทั้งหมดของ Meeting ID นี้
    $networkList = [];
    if ($activeMeetingId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM pm_network_parents WHERE meeting_id = ?");
        $stmt->execute([$activeMeetingId]);
        $rawNetwork = $stmt->fetchAll();
        
        // จัดกลุ่มข้อมูลตามตำแหน่งเพื่อความสะดวกในการแสดงผล
        // ตำแหน่งประกอบด้วย: ประธาน, รองประธาน, กรรมการ (สองคน), เลขานุการ
        // เพื่อรองรับ "กรรมการ" 2 คน เราจะจัดตำแหน่ง 'กรรมการ' เป็น Array
        foreach ($rawNetwork as $p) {
            if ($p['position_name'] === 'กรรมการ') {
                $networkList['กรรมการ'][] = $p;
            } else {
                $networkList[$p['position_name']] = $p;
            }
        }
    }
    
} catch (Exception $e) {
    error_log('[Parent Meeting] Network Fetch Error: ' . $e->getMessage());
    $myMeetings = [];
    $activeMeetingId = 0;
    $networkList = [];
}

require_once __DIR__ . '/components/layout_start.php';
?>

<!-- ส่วนกรองห้องเรียน -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body py-3 px-4 bg-white d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center">
            <i class="bi bi-funnel text-primary me-2 fs-5"></i>
            <h6 class="m-0 font-bold">เลือกห้องเรียน / รอบการประชุม:</h6>
        </div>
        <div class="flex-grow-1" style="max-width: 400px;">
            <select class="form-select text-sm rounded-3" onchange="changeClassroom(this.value)">
                <?php if (empty($myMeetings)): ?>
                    <option value="">-- กรุณาเพิ่มบันทึกรายงานการประชุมในระบบก่อน --</option>
                <?php else: ?>
                    <?php foreach ($myMeetings as $m): ?>
                        <option value="<?= $m['meeting_id'] ?>" <?= $activeMeetingId === $m['meeting_id'] ? 'selected' : '' ?>>
                            ชั้น ม.<?= esc($m['level'] . '/' . $m['room_name']) ?> (เทอม <?= esc($m['semester'] . '/' . $m['academic_year']) ?>)
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div>
            <?php if ($activeMeetingId > 0): ?>
                <a href="<?= pm_url('print_report.php?id=' . $activeMeetingId) ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-3">
                    <i class="bi bi-printer me-1"></i> พิมพ์โครงสร้างเครือข่าย
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($activeMeetingId === 0): ?>
    <!-- กรณีไม่มีข้อมูลรายงานประชุมเลย -->
    <div class="card border-0 shadow-sm py-5 text-center text-muted">
        <div class="card-body">
            <i class="bi bi-journal-x fs-1 d-block mb-3 text-warning"></i>
            <h5 class="font-bold">ไม่พบข้อมูลการประชุม</h5>
            <p class="text-sm mb-4">ครูที่ปรึกษาจำเป็นต้องบันทึกรายงานการประชุมผู้ปกครองในเมนู "บันทึกการประชุม" ก่อน จึงจะเพิ่มเครือข่ายผู้ปกครองของห้องตนเองได้</p>
            <a href="<?= pm_url('meetings.php') ?>" class="btn btn-primary rounded-3 font-bold px-4 py-2">
                <i class="bi bi-plus-circle me-1"></i> ไปเพิ่มบันทึกการประชุม
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- แสดงผลผังบอร์ดเครือข่ายผู้ปกครอง 5 ตำแหน่ง -->
    <div class="row g-4 justify-content-center">
        <!-- 1. ประธาน (กึ่งกลางแถวบนสุด) -->
        <div class="col-12 text-center">
            <div class="d-inline-block text-start" style="width: 100%; max-width: 320px;">
                <?php displayNetworkCard('ประธาน', $networkList['ประธาน'] ?? null, $activeMeetingId); ?>
            </div>
        </div>

        <!-- 2. รองประธาน (แถวสอง) -->
        <div class="col-12 text-center mt-3">
            <div class="d-inline-block text-start" style="width: 100%; max-width: 320px;">
                <?php displayNetworkCard('รองประธาน', $networkList['รองประธาน'] ?? null, $activeMeetingId); ?>
            </div>
        </div>

        <!-- 3. กรรมการ และ เลขานุการ (แถวสาม) -->
        <div class="col-12">
            <div class="row g-4 justify-content-center">
                <!-- กรรมการคนที่ 1 -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <?php 
                    $kom1 = isset($networkList['กรรมการ'][0]) ? $networkList['กรรมการ'][0] : null;
                    displayNetworkCard('กรรมการ', $kom1, $activeMeetingId, 0); 
                    ?>
                </div>
                <!-- กรรมการคนที่ 2 -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <?php 
                    $kom2 = isset($networkList['กรรมการ'][1]) ? $networkList['กรรมการ'][1] : null;
                    displayNetworkCard('กรรมการ', $kom2, $activeMeetingId, 1); 
                    ?>
                </div>
                <!-- เลขานุการ -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <?php displayNetworkCard('เลขานุการ', $networkList['เลขานุการ'] ?? null, $activeMeetingId); ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal สำหรับกรอกข้อมูลเครือข่าย -->
<div class="modal fade" id="networkModal" tabindex="-1" aria-labelledby="networkModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form id="networkForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_network">
                <input type="hidden" name="meeting_id" value="<?= $activeMeetingId ?>">
                <input type="hidden" name="position_name" id="net_position_name" value="">
                <input type="hidden" name="index" id="net_index" value=""> <!-- สำหรับแยกกรรมการคนที่ 0 หรือ 1 -->
                <input type="hidden" name="network_id" id="network_id" value="">
                
                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title font-black text-dark-blue" id="networkModalLabel">จัดการข้อมูลเครือข่าย</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <span class="badge bg-warning text-dark font-bold px-3 py-1.5 fs-7 mb-2" id="modalPositionBadge">ตำแหน่ง</span>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ชื่อ-นามสกุลผู้ปกครอง</label>
                            <input type="text" class="form-control rounded-3 text-sm py-2" name="parent_name" id="net_parent_name" placeholder="นาย/นาง/นางสาว..." required>
                        </div>
                        
                        <div class="col-12 col-sm-6">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">เป็นผู้ปกครองของนักเรียนชื่อ</label>
                            <input type="text" class="form-control rounded-3 text-sm py-2" name="student_name" id="net_student_name" placeholder="ชื่อ-นามสกุลนักเรียน" required>
                        </div>
                        
                        <div class="col-12 col-sm-6">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ชั้นเรียนนักเรียน</label>
                            <input type="text" class="form-control rounded-3 text-sm py-2" name="student_class" id="net_student_class" placeholder="เช่น ม.1/1" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">เบอร์โทรศัพท์ติดต่อ</label>
                            <input type="tel" class="form-control rounded-3 text-sm py-2" name="phone" id="net_phone" placeholder="เช่น 0812345678" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ที่อยู่ติดต่อ</label>
                            <textarea class="form-control rounded-3 text-sm" name="address" id="net_address" rows="2" placeholder="บ้านเลขที่ หมู่ที่ ตำบล..." required></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-xs font-black uppercase text-muted tracking-wider">รูปภาพผู้ปกครอง</label>
                            <input type="file" class="form-control rounded-3 text-sm" name="image" id="net_image" accept="image/*">
                            <small class="text-muted">ขนาดแนะนำ สัดส่วนจัตุรัส 300x300 px, ไม่เกิน 2MB</small>
                            <div class="mt-2 text-center d-none" id="avatarPreviewContainer">
                                <img src="" id="avatarPreview" class="rounded-circle border" style="width: 80px; height: 80px; object-fit: cover;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-3 text-sm font-bold" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-3 text-sm font-bold" id="btnSaveNetwork">
                        <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ฟังก์ชันเปลี่ยนห้องเรียน
function changeClassroom(meetingId) {
    if (meetingId) {
        window.location.href = `network.php?meeting_id=${meetingId}`;
    }
}

// เปิด Modal ในการกรอกข้อมูล
function openNetworkModal(position, netId = '', index = '') {
    document.getElementById('networkForm').reset();
    document.getElementById('network_id').value = netId;
    document.getElementById('net_position_name').value = position;
    document.getElementById('net_index').value = index;
    
    document.getElementById('modalPositionBadge').textContent = 'ตำแหน่ง: ' + position + (index !== '' ? ' (คนที่ ' + (parseInt(index) + 1) + ')' : '');
    
    const previewContainer = document.getElementById('avatarPreviewContainer');
    const previewImg = document.getElementById('avatarPreview');
    previewContainer.classList.add('d-none');
    previewImg.src = '';
    
    if (netId !== '') {
        // หากมี ID แสดงว่าเป็นโหมดแก้ไข ให้ดึงข้อมูลมาแสดงผล
        fetch(`api.php?action=get_network&id=${netId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const n = data.data;
                document.getElementById('net_parent_name').value = n.parent_name;
                document.getElementById('net_student_name').value = n.student_name;
                document.getElementById('net_student_class').value = n.student_class;
                document.getElementById('net_phone').value = n.phone;
                document.getElementById('net_address').value = n.address;
                
                if (n.image_path) {
                    previewContainer.classList.remove('d-none');
                    previewImg.src = n.image_path;
                }
                
                $('#networkModal').modal('show');
            } else {
                showAlert('เกิดข้อผิดพลาด', data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        });
    } else {
        // โหมดเพิ่มข้อมูลใหม่
        // ดึงชื่อห้องของที่ประชุมมาช่วยกรอกในช่อง "ชั้นเรียนนักเรียน"
        const selectBox = document.querySelector('select.form-select');
        if (selectBox && selectBox.selectedOptions[0]) {
            const text = selectBox.selectedOptions[0].text;
            const match = text.match(/ม\.\d+\/\d+/);
            if (match) {
                document.getElementById('net_student_class').value = match[0];
            }
        }
        $('#networkModal').modal('show');
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // ตรวจสอบการเลือกรูปในฟอร์มเพื่อพรีวิว
    const imageInput = document.getElementById('net_image');
    imageInput.addEventListener('change', function() {
        const previewContainer = document.getElementById('avatarPreviewContainer');
        const previewImg = document.getElementById('avatarPreview');
        
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewContainer.classList.remove('d-none');
                previewImg.src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // ส่งข้อมูลบันทึกเครือข่ายผู้ปกครอง
    const networkForm = document.getElementById('networkForm');
    networkForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const btnSave = document.getElementById('btnSaveNetwork');
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
                $('#networkModal').modal('hide');
                showAlert('สำเร็จ', 'บันทึกข้อมูลกรรมการเครือข่ายเรียบร้อยแล้ว', 'success')
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

// ฟังก์ชันลบข้อมูลกรรมการเครือข่ายคนนั้นๆ
function deleteNetwork(netId) {
    confirmDelete('ยืนยันลบข้อมูล?', 'ประวัติการเป็นตัวแทนกรรมการเครือข่ายผู้ปกครองคนนี้จะถูกลบออกจากระบบ!', function() {
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_network',
                network_id: netId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('สำเร็จ', 'ลบข้อมูลเรียบร้อยแล้ว', 'success')
                .then(() => {
                    location.reload();
                });
            } else {
                showAlert('เกิดข้อผิดพลาด', data.message || 'ลบไม่สำเร็จ', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
        });
    });
}
</script>

<?php 
require_once __DIR__ . '/components/layout_end.php'; 

/**
 * ฟังก์ชันช่วยวาดการ์ดของเครือข่ายผู้ปกครอง
 */
function displayNetworkCard($position, $data, $meetingId, $index = '') {
    ?>
    <div class="card border-0 shadow-sm text-center h-100 bg-white">
        <div class="card-header bg-light py-2.5 font-bold border-bottom">
            <i class="bi bi-award-fill text-warning me-1"></i>
            <?= esc($position) ?><?= $index !== '' ? ' (คนที่ ' . ($index + 1) . ')' : '' ?>
        </div>
        <div class="card-body p-4 d-flex flex-column align-items-center">
            <!-- อวตาร -->
            <div class="mb-3">
                <?php if ($data && $data['image_path']): ?>
                    <img src="<?= esc(pm_url($data['image_path'])) ?>" class="rounded-circle border" style="width: 100px; height: 100px; object-fit: cover; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
                <?php else: ?>
                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-muted" style="width: 100px; height: 100px; font-size: 3rem;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($data): ?>
                <!-- แสดงข้อมูลที่มี -->
                <h6 class="font-black text-dark mb-1"><?= esc($data['parent_name']) ?></h6>
                <div class="text-xs text-muted font-bold mb-2">ผู้ปกครองของ: <?= esc($data['student_name']) ?> (<?= esc($data['student_class']) ?>)</div>
                <div class="text-xs text-dark-blue mb-1"><i class="bi bi-telephone-fill me-1 text-primary"></i> <?= esc($data['phone']) ?></div>
                <p class="text-xs text-muted text-start mt-2 border-top pt-2 w-100" style="min-height: 48px; max-height: 48px; overflow: hidden;" title="<?= esc($data['address']) ?>">
                    <i class="bi bi-geo-alt-fill text-secondary"></i> <?= esc($data['address']) ?>
                </p>
                
                <div class="mt-auto pt-3 border-top w-100 d-flex justify-content-center gap-2">
                    <button class="btn btn-xs btn-outline-info rounded px-2.5 py-1 text-xs" onclick="openNetworkModal('<?= $position ?>', <?= $data['id'] ?>, '<?= $index ?>')">
                        <i class="bi bi-pencil-square"></i> แก้ไข
                    </button>
                    <button class="btn btn-xs btn-outline-danger rounded px-2.5 py-1 text-xs" onclick="deleteNetwork(<?= $data['id'] ?>)">
                        <i class="bi bi-trash"></i> ลบ
                    </button>
                </div>
            <?php else: ?>
                <!-- แสดงปุ่มเพิ่มข้อมูล -->
                <div class="my-auto py-4 text-center">
                    <p class="text-muted text-xs mb-3">ยังไม่มีข้อมูลในตำแหน่งนี้</p>
                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 text-xs font-bold" onclick="openNetworkModal('<?= $position ?>', '', '<?= $index ?>')">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มข้อมูล
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
