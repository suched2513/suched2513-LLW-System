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
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content rounded-4 shadow border-0">
            <form id="meetingForm" enctype="multipart/form-data">
                <input type="hidden" name="action" id="action" value="save_meeting">
                <input type="hidden" name="meeting_id" id="meeting_id" value="">
                
                <!-- Hidden inputs for JSON data -->
                <input type="hidden" name="attendants_data" id="attendants_data">
                <input type="hidden" name="absents_data" id="absents_data">
                <input type="hidden" name="relations_data" id="relations_data">
                <input type="hidden" name="groups_data" id="groups_data">
                <input type="hidden" name="letters_data" id="letters_data">

                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title font-black text-dark-blue" id="meetingModalLabel">บันทึกรายงานการประชุม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <!-- Bootstrap Nav Tabs -->
                    <ul class="nav nav-tabs nav-justified mb-4" id="meetingTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active font-bold text-sm" id="tab1-tab" data-bs-toggle="tab" data-bs-target="#tab1" type="button" role="tab">ลลว.๐๑ (บันทึก/สรุป)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link font-bold text-sm" id="tab2-tab" data-bs-toggle="tab" data-bs-target="#tab2" type="button" role="tab">ลลว.๐๒ (ผู้เข้าร่วม)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link font-bold text-sm" id="tab3-tab" data-bs-toggle="tab" data-bs-target="#tab3" type="button" role="tab">ลลว.๐๓ (ผู้ขาดประชุม)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link font-bold text-sm" id="tab4-tab" data-bs-toggle="tab" data-bs-target="#tab4" type="button" role="tab">ลลว.๐๔ (ประสานสัมพันธ์)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link font-bold text-sm" id="tab5-tab" data-bs-toggle="tab" data-bs-target="#tab5" type="button" role="tab">ลลว.๐๕ (Meet & Greet)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link font-bold text-sm" id="tab6-tab" data-bs-toggle="tab" data-bs-target="#tab6" type="button" role="tab">ลลว.๐๖ (ความในใจลูก)</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="meetingTabContent">
                        <!-- Tab 1: ลลว.๐๑ -->
                        <div class="tab-pane fade show active" id="tab1" role="tabpanel">
                            <div class="row g-3">
                                <!-- แถวที่ 1 -->
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">วันที่ประชุม</label>
                                    <input type="date" class="form-control rounded-3 py-2 text-sm" name="meeting_date" id="meeting_date" required>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">ภาคเรียน</label>
                                    <select class="form-select rounded-3 py-2 text-sm" name="semester" id="semester" required>
                                        <option value="1">ภาคเรียนที่ 1</option>
                                        <option value="2">ภาคเรียนที่ 2</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">ปีการศึกษา (พ.ศ.)</label>
                                    <input type="number" class="form-control rounded-3 py-2 text-sm" name="academic_year" id="academic_year" min="2560" required>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">ห้องเรียน</label>
                                    <select class="form-select rounded-3 py-2 text-sm" name="classroom_id" id="classroom_id" required>
                                        <option value="">-- เลือกห้องเรียน --</option>
                                        <?php foreach ($classrooms as $c): ?>
                                            <option value="<?= $c['id'] ?>">ม.<?= esc($c['level'] . '/' . $c['room_name'] . ' - ครู' . $c['teacher_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-link btn-sm p-0 mt-1 font-bold text-xs text-decoration-none text-primary" id="btnSyncStudents" onclick="triggerManualStudentSync()" style="display:none;">
                                        <i class="bi bi-cloud-download me-1"></i>ดึงรายชื่อนักเรียนจากข้อมูลกลาง
                                    </button>
                                </div>

                                <!-- แถวที่ 2: สั่งการและหนังสือราชการ -->
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">เลขที่คำสั่งแต่งตั้ง</label>
                                    <input type="text" class="form-control rounded-3 py-2 text-sm" name="command_no" id="command_no" placeholder="เช่น ๑๒๓/๒๕๖๙">
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">ลงวันที่คำสั่ง</label>
                                    <input type="date" class="form-control rounded-3 py-2 text-sm" name="command_date" id="command_date">
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">เลขที่บันทึกรายงาน</label>
                                    <input type="text" class="form-control rounded-3 py-2 text-sm" name="doc_no" id="doc_no" placeholder="เช่น พิเศษ/๐๑/๒๕๖๙">
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">ลงวันที่ส่งรายงาน</label>
                                    <input type="date" class="form-control rounded-3 py-2 text-sm" name="doc_date" id="doc_date">
                                </div>

                                <!-- แถวที่ 3: สถิติ -->
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">จำนวนนักเรียนทั้งหมด (คน)</label>
                                    <input type="number" class="form-control rounded-3 py-2 text-sm" name="total_students" id="total_students" min="0" required>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">จำนวนผู้ปกครองทั้งหมด (คน)</label>
                                    <input type="number" class="form-control rounded-3 py-2 text-sm" name="total_parents" id="total_parents" min="0" required>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">จำนวนที่มาประชุม (คน)</label>
                                    <input type="number" class="form-control rounded-3 py-2 text-sm" name="attend_count" id="attend_count" min="0" required>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">จำนวนที่ขาดประชุม (คน)</label>
                                    <input type="number" class="form-control rounded-3 py-2 text-sm" name="absent_count" id="absent_count" readonly>
                                </div>

                                <!-- วาระการประชุม -->
                                <div class="col-12">
                                    <h6 class="font-bold text-dark border-bottom pb-1 mb-2 mt-2"><i class="bi bi-list-task me-1"></i>วาระการประชุมสำคัญ</h6>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label text-xs font-bold text-muted">วาระที่ 1</label>
                                    <textarea class="form-control rounded-3 text-sm" name="agenda_1" id="agenda_1" rows="2" placeholder="เช่น การทำความเข้าใจระเบียบโรงเรียน..."></textarea>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label text-xs font-bold text-muted">วาระที่ 2</label>
                                    <textarea class="form-control rounded-3 text-sm" name="agenda_2" id="agenda_2" rows="2" placeholder="เช่น ชี้แจงเรื่องผลการเรียนนักเรียน..."></textarea>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label text-xs font-bold text-muted">วาระที่ 3</label>
                                    <textarea class="form-control rounded-3 text-sm" name="agenda_3" id="agenda_3" rows="2" placeholder="เช่น การเลือกคณะกรรมการเครือข่าย..."></textarea>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label text-xs font-bold text-muted">มติที่ประชุม</label>
                                    <textarea class="form-control rounded-3 text-sm" name="consensus" id="consensus" rows="2" placeholder="เช่น เห็นชอบร่วมกันในแนวทางการแก้ไขพฤติกรรม..."></textarea>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label text-xs font-bold text-muted">สรุปเนื้อหาการประชุมภาพรวม</label>
                                    <textarea class="form-control rounded-3 text-sm" name="summary" id="summary" rows="2" placeholder="ระบุสรุปเนื้อหาสาระสำคัญในการประชุม..." required></textarea>
                                </div>

                                <!-- บรรยากาศและการสนับสนุน -->
                                <div class="col-12">
                                    <h6 class="font-bold text-dark border-bottom pb-1 mb-2 mt-2"><i class="bi bi-chat-heart me-1"></i>บรรยากาศการประชุมและข้อสังเกต</h6>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">ความร่วมมือเสนอความคิดเห็น</label>
                                    <textarea class="form-control rounded-3 text-sm" name="cooperation_rating" id="cooperation_rating" rows="2" placeholder="เช่น ผู้ปกครองมีความร่วมมือเป็นอย่างดี..."></textarea>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">ข้อคิดเห็นที่เป็นประโยชน์</label>
                                    <textarea class="form-control rounded-3 text-sm" name="useful_suggestions" id="useful_suggestions" rows="2" placeholder="เช่น เสนอให้จัดกิจกรรมติวเข้มหลังเลิกเรียน..."></textarea>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">การสนับสนุนจากผู้ปกครอง</label>
                                    <textarea class="form-control rounded-3 text-sm" name="support_received" id="support_received" rows="2" placeholder="เช่น ยินดีช่วยเหลือปรับปรุงห้องเรียน..."></textarea>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label text-xs font-bold text-muted">ข้อสังเกตอื่นๆ</label>
                                    <textarea class="form-control rounded-3 text-sm" name="other_observations" id="other_observations" rows="2" placeholder="เช่น ผู้ปกครองอยากให้เน้นย้ำเรื่องยาเสพติด..."></textarea>
                                </div>

                                <!-- ปัญหา / อุปสรรค / ข้อเสนอแนะ -->
                                <div class="col-12 col-md-6 border-top pt-2">
                                    <label class="form-label text-xs font-bold text-muted">ปัญหาหรืออุปสรรคที่พบ</label>
                                    <textarea class="form-control rounded-3 text-sm" name="problems" id="problems" rows="2" placeholder="ระบุปัญหา อุปสรรค พฤติกรรม หรือข้อขัดข้องต่างๆ (หากมี)..."></textarea>
                                </div>
                                <div class="col-12 col-md-6 border-top pt-2">
                                    <label class="form-label text-xs font-bold text-muted">ข้อเสนอแนะ / แนวทางการแก้ไข</label>
                                    <textarea class="form-control rounded-3 text-sm" name="suggestions" id="suggestions" rows="2" placeholder="ระบุข้อเสนอแนะในการแก้ไข ปรับปรุง หรือการสนับสนุนที่ต้องการ..."></textarea>
                                </div>

                                <!-- อัปโหลดรูปภาพกิจกรรม -->
                                <div class="col-12 border-top pt-3 mt-3">
                                    <label class="form-label text-xs font-bold text-muted">แนบรูปภาพบรรยากาศการประชุม (แนบได้หลายรูปพร้อมกัน)</label>
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

                        <!-- Tab 2: ลลว.๐๒ (ผู้เข้าร่วม) -->
                        <div class="tab-pane fade" id="tab2" role="tabpanel">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="m-0 font-bold text-dark"><i class="bi bi-people me-1"></i>ใบลงชื่อผู้เข้าร่วมประชุม (ลลว.๐๒)</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary font-bold" onclick="addAttendantRow()">
                                    <i class="bi bi-plus-circle me-1"></i> เพิ่มแถวผู้เข้าร่วม
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ชื่อ-นามสกุลนักเรียน</th>
                                            <th>ชื่อผู้ปกครอง (ตัวบรรจง)</th>
                                            <th>เบอร์โทรศัพท์มือถือ</th>
                                            <th>ความสัมพันธ์กับนักเรียน</th>
                                            <th width="80" class="text-center">ลบ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendantsTableBody">
                                        <!-- Dynamic Rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab 3: ลลว.๐๓ (ผู้ขาดประชุม) -->
                        <div class="tab-pane fade" id="tab3" role="tabpanel">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="m-0 font-bold text-dark"><i class="bi bi-person-x me-1"></i>ใบลงชื่อติดตามผู้ไม่เข้าร่วมประชุม (ลลว.๐๓)</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary font-bold" onclick="addAbsentRow()">
                                    <i class="bi bi-plus-circle me-1"></i> เพิ่มแถวผู้ขาดประชุม
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ชื่อ-นามสกุลนักเรียน</th>
                                            <th>ชื่อผู้ปกครอง</th>
                                            <th>เบอร์โทรศัพท์</th>
                                            <th>ความสัมพันธ์</th>
                                            <th>สาเหตุที่ขาดประชุม</th>
                                            <th>สถานะการติดตาม</th>
                                            <th width="140">วันที่ติดตาม</th>
                                            <th width="80" class="text-center">ลบ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="absentsTableBody">
                                        <!-- Dynamic Rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab 4: ลลว.๐๔ (ประสานสัมพันธ์) -->
                        <div class="tab-pane fade" id="tab4" role="tabpanel">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="m-0 font-bold text-dark"><i class="bi bi-clipboard-check me-1"></i>แบบบันทึกประสานสัมพันธ์ผู้ปกครองกับครูที่ปรึกษา (ลลว.๐๔)</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary font-bold" onclick="addNewRelationRow()">
                                    <i class="bi bi-plus-circle me-1"></i> เพิ่มนักเรียนประเมิน ลลว.๐๔
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="8%">ลำดับ</th>
                                            <th>ชื่อนักเรียน</th>
                                            <th>ชั้น/เลขที่</th>
                                            <th>ผู้ปกครอง (เกี่ยวข้อง)</th>
                                            <th>พฤติกรรมสรุป</th>
                                            <th width="20%" class="text-center">การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="relationsTableBody">
                                        <!-- Dynamic Rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab 5: ลลว.๐๕ (Meet & Greet) -->
                        <div class="tab-pane fade" id="tab5" role="tabpanel">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="m-0 font-bold text-dark"><i class="bi bi-chat-square-quote me-1"></i>บันทึกกิจกรรมกลุ่มย่อย Meet and Greet (ลลว.๐๕)</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary font-bold" onclick="addGroupRow()">
                                    <i class="bi bi-plus-circle me-1"></i> เพิ่มกลุ่มย่อย
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>หัวข้อกลุ่มย่อย</th>
                                            <th>ชื่อผู้ปกครองในกลุ่ม (คั่นด้วยจุลภาค ,)</th>
                                            <th>ประเด็นที่พูดคุยสรุป</th>
                                            <th>แนวทางแก้ไขของกลุ่ม</th>
                                            <th>สิ่งที่ต้องการให้โรงเรียนช่วย</th>
                                            <th width="80" class="text-center">ลบ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="groupsTableBody">
                                        <!-- Dynamic Rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab 6: ลลว.๐๖ (ความในใจของลูก) -->
                        <div class="tab-pane fade" id="tab6" role="tabpanel">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="m-0 font-bold text-dark"><i class="bi bi-envelope-heart me-1"></i>บันทึกความในใจของลูกที่อยากบอกผู้ปกครอง (ลลว.๐๖)</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary font-bold" onclick="addNewLetterRow()">
                                    <i class="bi bi-plus-circle me-1"></i> เพิ่มบันทึกจดหมาย ลลว.๐๖
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="8%">ลำดับ</th>
                                            <th>ชื่อนักเรียน</th>
                                            <th>ชั้น/เลขที่</th>
                                            <th>จดหมายเขียนถึง</th>
                                            <th>สถานะการกรอก</th>
                                            <th width="20%" class="text-center">การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lettersTableBody">
                                        <!-- Dynamic Rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4 font-bold text-sm" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 font-bold text-sm" id="btnSaveMeeting">
                        <i class="bi bi-save me-1"></i> บันทึกข้อมูลทั้งหมด
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     SUB-MODAL FOR ลลว.๐๔ (ประสานสัมพันธ์)
     ========================================== -->
<div class="modal fade" id="relationSubModal" tabindex="-1" aria-labelledby="relationSubModalLabel" aria-hidden="true" data-bs-backdrop="static" style="z-index: 20050 !important;">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content rounded-4 shadow border-0">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title font-bold" id="relationSubModalLabel"><i class="bi bi-person-check me-2"></i>รายละเอียดแบบบันทึกประสานสัมพันธ์ ลลว.๐๔</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <!-- ข้อมูลนักเรียน/ผู้ปกครองพื้นฐาน -->
                    <div class="col-12 col-md-4">
                        <label class="form-label text-xs font-bold text-muted">ชื่อ-นามสกุลนักเรียน</label>
                        <input type="text" class="form-control rounded-3 py-2 text-sm" id="rel_student_name" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label text-xs font-bold text-muted">ชั้นมัธยมศึกษาปีที่ (เช่น ๑/๒)</label>
                        <input type="text" class="form-control rounded-3 py-2 text-sm" id="rel_classroom_no" placeholder="เช่น ๑/๒" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label text-xs font-bold text-muted">เลขที่</label>
                        <input type="number" class="form-control rounded-3 py-2 text-sm" id="rel_student_no" min="1" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-xs font-bold text-muted">ชื่อ-นามสกุลผู้ปกครอง</label>
                        <input type="text" class="form-control rounded-3 py-2 text-sm" id="rel_parent_name" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-xs font-bold text-muted">ความสัมพันธ์กับนักเรียน</label>
                        <input type="text" class="form-control rounded-3 py-2 text-sm" id="rel_relationship" placeholder="เช่น บิดา, มารดา, ยาย" required>
                    </div>

                    <!-- 1. พฤติกรรมด้านการเรียน -->
                    <div class="col-12">
                        <h6 class="font-bold text-dark border-bottom pb-1 mb-2 mt-2"><i class="bi bi-journal-x me-1"></i>๑. พฤติกรรมด้านการเรียน</h6>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label text-xs font-bold text-muted">ติด 0 (วิชา)</label>
                        <input type="number" class="form-control rounded-3 py-2 text-sm" id="rel_grade_zero" min="0" value="0">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label text-xs font-bold text-muted">ติด ร (วิชา)</label>
                        <input type="number" class="form-control rounded-3 py-2 text-sm" id="rel_grade_r" min="0" value="0">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label text-xs font-bold text-muted">ติด มส (วิชา)</label>
                        <input type="number" class="form-control rounded-3 py-2 text-sm" id="rel_grade_ms" min="0" value="0">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label text-xs font-bold text-muted">ติด มผ (วิชา)</label>
                        <input type="number" class="form-control rounded-3 py-2 text-sm" id="rel_grade_mp" min="0" value="0">
                    </div>

                    <!-- 2. คะแนนพฤติกรรมที่หัก -->
                    <div class="col-12">
                        <h6 class="font-bold text-dark border-bottom pb-1 mb-2 mt-2"><i class="bi bi-exclamation-triangle me-1"></i>๒. คะแนนความประพฤติที่โดนตัด</h6>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label text-xs font-bold text-muted">คะแนนพฤติกรรมที่ถูกตัด</label>
                        <input type="number" class="form-control rounded-3 py-2 text-sm" id="rel_behavior_score" min="0" value="0">
                    </div>

                    <!-- 3. พฤติกรรมควรยกย่องชมเชย -->
                    <div class="col-12">
                        <h6 class="font-bold text-dark border-bottom pb-1 mb-2 mt-2"><i class="bi bi-star-fill text-warning me-1"></i>๓. พฤติกรรมที่ควรยกย่องชมเชย</h6>
                    </div>
                    
                    <div class="col-12 col-md-6 border-end">
                        <span class="badge bg-primary mb-2">สำหรับครูที่ปรึกษา</span>
                        <div class="praise-teacher-checklist" style="font-size: 13px;">
                            <?php 
                            $praises = [
                                'มีน้ำใจต่อครู เพื่อนและบุคคลอื่น',
                                'มีความซื่อสัตย์สุจริต',
                                'ความรับผิดชอบ',
                                'มีความกระตือรือร้น',
                                'ตั้งใจเรียน ไม่หนีเรียน หรือเข้าห้องเรียนช้า',
                                'ช่วยดูแลรักษาความสะอาดภายในโรงเรียน',
                                'แต่งกายและทรงผมถูกระเบียบ',
                                'เข้าร่วมกิจกรรมต่าง ๆ ที่โรงเรียนจัดให้ ทั้งภายในและภายนอกโรงเรียน',
                                'ตรงต่อเวลา มาโรงเรียนทันเข้าแถว และร่วมกิจกรรมหน้าเสาธง',
                                'บำเพ็ญตนเป็นประโยชน์ต่อสังคมและสาธารณประโยชน์ (จิตอาสา)'
                            ];
                            foreach ($praises as $index => $item):
                            ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input chk-praise-teacher" type="checkbox" value="<?= $item ?>" id="praise_t_<?= $index ?>">
                                    <label class="form-check-label" for="praise_t_<?= $index ?>"><?= $item ?></label>
                                </div>
                            <?php endforeach; ?>
                            <div class="mt-2">
                                <label class="form-label text-xs font-bold text-muted">พฤติกรรมอื่นๆ ของครู</label>
                                <input type="text" class="form-control form-control-sm rounded-2" id="rel_praise_teacher_other" placeholder="ระบุพฤติกรรมชื่นชมอื่นๆ...">
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <span class="badge bg-success mb-2">สำหรับผู้ปกครอง</span>
                        <div class="praise-parent-checklist" style="font-size: 13px;">
                            <?php foreach ($praises as $index => $item): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input chk-praise-parent" type="checkbox" value="<?= $item ?>" id="praise_p_<?= $index ?>">
                                    <label class="form-check-label" for="praise_p_<?= $index ?>"><?= $item ?></label>
                                </div>
                            <?php endforeach; ?>
                            <div class="mt-2">
                                <label class="form-label text-xs font-bold text-muted">พฤติกรรมอื่นๆ ของผู้ปกครอง</label>
                                <input type="text" class="form-control form-control-sm rounded-2" id="rel_praise_parent_other" placeholder="ระบุพฤติกรรมชื่นชมอื่นๆ...">
                            </div>
                        </div>
                    </div>

                    <!-- 4. พฤติกรรมที่ต้องปรับปรุง -->
                    <div class="col-12">
                        <h6 class="font-bold text-dark border-bottom pb-1 mb-2 mt-2"><i class="bi bi-slash-circle text-danger me-1"></i>๔. พฤติกรรมของนักเรียนที่ต้องปรับปรุง แก้ไข</h6>
                    </div>
                    
                    <div class="col-12 col-md-6 border-end">
                        <span class="badge bg-primary mb-2">สำหรับครูที่ปรึกษา</span>
                        <div class="improve-teacher-checklist" style="font-size: 13px;">
                            <?php 
                            $improves = [
                                'มาสาย ไม่ทันเข้าแถว หรือหลบหนีการเข้าแถว',
                                'เข้าห้องเรียนช้า',
                                'หนีเรียนตลอดทั้งวัน หรือหนีเรียนบางคาบเรียน',
                                'แต่งกายหรือทรงผมผิดระเบียบ',
                                'สวมใส่เครื่องประดับหรือของมีค่า',
                                'ใช้โทรศัพท์มือถือระหว่างเรียน',
                                'สูบบุหรี่ทั้งภายในและภายนอกโรงเรียน',
                                'ประพฤติตนในทำนองชู้สาว',
                                'แสดงกิริยาวาจาที่ไม่สุภาพต่อครูและบุคคลอื่น'
                            ];
                            foreach ($improves as $index => $item):
                            ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input chk-improve-teacher" type="checkbox" value="<?= $item ?>" id="improve_t_<?= $index ?>">
                                    <label class="form-check-label" for="improve_t_<?= $index ?>"><?= $item ?></label>
                                </div>
                            <?php endforeach; ?>
                            <div class="mt-2">
                                <label class="form-label text-xs font-bold text-muted">พฤติกรรมที่ต้องปรับปรุงอื่นๆ ของครู</label>
                                <input type="text" class="form-control form-control-sm rounded-2" id="rel_improve_teacher_other" placeholder="ระบุพฤติกรรมอื่นๆ...">
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <span class="badge bg-success mb-2">สำหรับผู้ปกครอง</span>
                        <div class="improve-parent-checklist" style="font-size: 13px;">
                            <?php foreach ($improves as $index => $item): ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input chk-improve-parent" type="checkbox" value="<?= $item ?>" id="improve_p_<?= $index ?>">
                                    <label class="form-check-label" for="improve_p_<?= $index ?>"><?= $item ?></label>
                                </div>
                            <?php endforeach; ?>
                            <div class="mt-2">
                                <label class="form-label text-xs font-bold text-muted">พฤติกรรมที่ต้องปรับปรุงอื่นๆ ของผู้ปกครอง</label>
                                <input type="text" class="form-control form-control-sm rounded-2" id="rel_improve_parent_other" placeholder="ระบุพฤติกรรมอื่นๆ...">
                            </div>
                        </div>
                    </div>

                    <!-- วิธีการแก้ไขพฤติกรรม -->
                    <div class="col-12">
                        <h6 class="font-bold text-dark border-bottom pb-1 mb-2 mt-2"><i class="bi bi-chat-left-dots me-1"></i>วิธีการบันทึกและแนวทางแก้ไขเพิ่มเติม</h6>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-xs font-bold text-muted">วิธีการ/การแก้ไขพฤติกรรมที่ไม่พึงประสงค์ของครูที่ปรึกษาที่ผ่านมา</label>
                        <textarea class="form-control rounded-3 text-sm" id="rel_teacher_remedy" rows="2" placeholder="ระบุวิธีแก้ไขเพิ่มเติมของครู..."></textarea>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-xs font-bold text-muted">แนวทางการแก้ไขและกวดขันพฤติกรรมของผู้ปกครองที่บ้าน</label>
                        <textarea class="form-control rounded-3 text-sm" id="rel_parent_remedy" rows="2" placeholder="ระบุแนวทางร่วมมือของผู้ปกครอง..."></textarea>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-xs font-bold text-muted">ความต้องการเพิ่มเติมของผู้ปกครองที่ต้องการให้โรงเรียนช่วยเหลือ</label>
                        <textarea class="form-control rounded-3 text-sm" id="rel_parent_support" rows="2" placeholder="ระบุความช่วยเหลือที่ต้องการจากโรงเรียน..."></textarea>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-xs font-bold text-muted">ความรู้สึก/ความเห็นของผู้ปกครองที่ได้เข้าร่วมกิจกรรมครั้งนี้</label>
                        <textarea class="form-control rounded-3 text-sm" id="rel_parent_impression" rows="2" placeholder="ระบุความเห็นต่อการจัดประชุมครั้งนี้..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-xs font-bold text-muted">คำขอบคุณ/ความรู้สึก/ความคิดเห็นเพิ่มเติมของผู้ปกครองที่มีต่อครูที่ปรึกษา</label>
                        <textarea class="form-control rounded-3 text-sm" id="rel_parent_feedback" rows="2" placeholder="ความรู้สึกจากผู้ปกครอง..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 px-4">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4 font-bold text-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary rounded-3 px-4 font-bold text-sm" onclick="saveRelationSubModal()">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     SUB-MODAL FOR ลลว.๐๖ (ความในใจของลูก)
     ========================================== -->
<div class="modal fade" id="letterSubModal" tabindex="-1" aria-labelledby="letterSubModalLabel" aria-hidden="true" data-bs-backdrop="static" style="z-index: 20050 !important;">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content rounded-4 shadow border-0">
            <div class="modal-header bg-success text-white py-3 px-4">
                <h5 class="modal-title font-bold" id="letterSubModalLabel"><i class="bi bi-heart-fill text-danger me-2"></i>รายละเอียดบันทึกความในใจของลูก ลลว.๐๖</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label text-xs font-bold text-muted">ชื่อ-นามสกุลนักเรียน</label>
                        <input type="text" class="form-control rounded-3 py-2 text-sm" id="let_student_name" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label text-xs font-bold text-muted">ชั้นมัธยมศึกษาปีที่ (เช่น ๑/๒)</label>
                        <input type="text" class="form-control rounded-3 py-2 text-sm" id="let_classroom_no" placeholder="เช่น ๑/๒" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label text-xs font-bold text-muted">เลขที่</label>
                        <input type="number" class="form-control rounded-3 py-2 text-sm" id="let_student_no" min="1" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-xs font-bold text-muted">๑. ความในใจนี้อยากบอกกับ (เช่น คุณพ่อ, คุณแม่, ผู้ปกครอง)</label>
                        <input type="text" class="form-control rounded-3 py-2 text-sm" id="let_to_whom" placeholder="เช่น คุณแม่" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-xs font-bold text-muted">๒. สิ่งที่นักเรียนประทับใจที่มีต่อผู้ปกครอง</label>
                        <textarea class="form-control rounded-3 text-sm" id="let_impressed" rows="2" placeholder="เช่น ดูแลใส่ใจ ให้คำแนะนำดีๆ เสมอ..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-xs font-bold text-muted">๓. ความในใจที่นักเรียนอยากบอกให้ผู้ปกครองรู้</label>
                        <textarea class="form-control rounded-3 text-sm" id="let_inner_feelings" rows="2" placeholder="ระบุสิ่งที่เก็บไว้ในใจ..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-xs font-bold text-muted">๔. สิ่งที่นักเรียนภาคภูมิใจในตนเอง</label>
                        <textarea class="form-control rounded-3 text-sm" id="let_proud" rows="2" placeholder="เช่น ตั้งใจเรียน สอบผ่านทุกวิชา บำเพ็ญประโยชน์..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-xs font-bold text-muted">๕. สิ่งที่นักเรียนจะปรับปรุงแก้ไขตนเองให้ดียิ่งขึ้น</label>
                        <textarea class="form-control rounded-3 text-sm" id="let_improvement" rows="2" placeholder="เช่น ลดการเล่นเกม นอนให้ตรงเวลาขึ้น..."></textarea>
                    </div>
                    <div class="col-12 border-top pt-2">
                        <label class="form-label text-xs font-bold text-muted text-success font-black">๖. ความรู้สึกและความคิดเห็นตอบกลับของผู้ปกครองที่มีต่อบุตรหลาน</label>
                        <textarea class="form-control rounded-3 text-sm border-success border-1" id="let_parent_response" rows="2" placeholder="สิ่งที่ผู้ปกครองเขียนกลับถึงนักเรียน..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 px-4">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4 font-bold text-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-success rounded-3 px-4 font-bold text-sm text-white" onclick="saveLetterSubModal()">ตกลง</button>
            </div>
        </div>
    </div>
</div>

<script>
// Global Arrays for Sub-form data (ลลว.๐๔ และ ลลว.๐๖)
let currentRelations = [];
let currentLetters = [];

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

    // ซิงค์รายชื่อนักเรียนเมื่อเลือกห้องเรียน
    const classroomSelect = document.getElementById('classroom_id');
    const btnSyncStudents = document.getElementById('btnSyncStudents');

    classroomSelect.addEventListener('change', function() {
        if (this.value) {
            btnSyncStudents.style.display = 'inline-block';
            // ถามผู้ใช้เพื่อดึงรายชื่อเฉพาะตอนเพิ่มใหม่ (meeting_id ว่าง)
            const meetingId = document.getElementById('meeting_id').value;
            if (!meetingId) {
                confirmLoadStudents(this.value);
            }
        } else {
            btnSyncStudents.style.display = 'none';
        }
    });

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

    // บันทึกฟอร์มหลักผ่าน AJAX
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

        // Serialize inline tables (ลลว.๐๒, ลลว.๐๓, ลลว.๐๕)
        // 1. ลลว.๐๒ (ผู้เข้าร่วม)
        const attendants = [];
        document.querySelectorAll('#attendantsTableBody tr').forEach(row => {
            const student_name = row.querySelector('.student_name').value.trim();
            if (student_name) {
                attendants.push({
                    student_name: student_name,
                    parent_name: row.querySelector('.parent_name').value.trim(),
                    phone: row.querySelector('.phone').value.trim(),
                    relationship: row.querySelector('.relationship').value.trim()
                });
            }
        });
        document.getElementById('attendants_data').value = JSON.stringify(attendants);

        // 2. ลลว.๐๓ (ผู้ขาดประชุม)
        const absents = [];
        document.querySelectorAll('#absentsTableBody tr').forEach(row => {
            const student_name = row.querySelector('.student_name').value.trim();
            if (student_name) {
                absents.push({
                    student_name: student_name,
                    parent_name: row.querySelector('.parent_name').value.trim(),
                    phone: row.querySelector('.phone').value.trim(),
                    relationship: row.querySelector('.relationship').value.trim(),
                    absent_reason: row.querySelector('.absent_reason').value.trim(),
                    follow_up_status: row.querySelector('.follow_up_status').value.trim(),
                    follow_up_date: row.querySelector('.follow_up_date').value
                });
            }
        });
        document.getElementById('absents_data').value = JSON.stringify(absents);

        // 3. ลลว.๐๕ (Meet & Greet)
        const groups = [];
        document.querySelectorAll('#groupsTableBody tr').forEach(row => {
            const group_topic = row.querySelector('.group_topic').value.trim();
            if (group_topic) {
                const attendants_text = row.querySelector('.attendants_text').value;
                const attendants_json = attendants_text.split(',').map(s => s.trim()).filter(s => s.length > 0);
                groups.push({
                    group_topic: group_topic,
                    attendants_json: attendants_json,
                    discussion_summary: row.querySelector('.discussion_summary').value.trim(),
                    discussion_resolution: row.querySelector('.discussion_resolution').value.trim(),
                    school_support_request: row.querySelector('.school_support_request').value.trim()
                });
            }
        });
        document.getElementById('groups_data').value = JSON.stringify(groups);

        // Serialize global array tables (ลลว.๐๔ และ ลลว.๐๖)
        document.getElementById('relations_data').value = JSON.stringify(currentRelations);
        document.getElementById('letters_data').value = JSON.stringify(currentLetters);

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
            btnSave.innerHTML = '<i class="bi bi-save me-1"></i> บันทึกข้อมูลทั้งหมด';
            
            if (data.status === 'success') {
                $('#meetingModal').modal('hide');
                showAlert('สำเร็จ', 'บันทึกข้อมูลรายงานการประชุมและแบบฟอร์ม ลลว.๐๑ - ลลว.๐๖ เรียบร้อยแล้ว', 'success')
                .then(() => {
                    location.reload();
                });
            } else {
                showAlert('เกิดข้อผิดพลาด', data.message || 'บันทึกข้อมูลไม่สำเร็จ', 'error');
            }
        })
        .catch(err => {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-save me-1"></i> บันทึกข้อมูลทั้งหมด';
            console.error(err);
            showAlert('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        });
    });
});

// ------------------------------------------
// STUDENT SYNC FUNCTIONS FROM CENTRAL LLW
// ------------------------------------------
function triggerManualStudentSync() {
    const classroomId = document.getElementById('classroom_id').value;
    if (!classroomId) {
        showAlert('คำเตือน', 'กรุณาเลือกห้องเรียนก่อนดึงข้อมูล', 'warning');
        return;
    }
    confirmLoadStudents(classroomId);
}

function confirmLoadStudents(classroomId) {
    if (!classroomId) return;
    
    Swal.fire({
        title: 'ดึงรายชื่อนักเรียน?',
        text: 'ต้องการดึงรายชื่อนักเรียนของห้องนี้จากระบบกลางเพื่อลงข้อมูล ลลว.๐๒, ลลว.๐๔ และ ลลว.๐๖ อัตโนมัติหรือไม่? (ข้อมูลเดิมในตารางเหล่านี้จะถูกล้าง)',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'ใช่, ดึงข้อมูล',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            loadStudentsFromCentral(classroomId);
        }
    });
}

function loadStudentsFromCentral(classroomId) {
    Swal.fire({
        title: 'กำลังดึงข้อมูลนักเรียน...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(`api.php?action=get_llw_students&classroom_id=${classroomId}`)
    .then(res => res.json())
    .then(res => {
        Swal.close();
        if (res.status === 'success') {
            const students = res.data;
            if (students.length === 0) {
                showAlert('ไม่พบข้อมูล', 'ไม่พบรายชื่อนักเรียนในห้องเรียนนี้จากระบบกลาง', 'warning');
                return;
            }
            
            // 1. อัปเดตจำนวนและสถิติ
            document.getElementById('total_students').value = students.length;
            document.getElementById('total_parents').value = students.length;
            document.getElementById('attend_count').value = students.length;
            document.getElementById('absent_count').value = 0;
            
            // 2. ล้างและเติม ลลว.๐๒ (ผู้มาประชุม)
            const attendantsBody = document.getElementById('attendantsTableBody');
            attendantsBody.innerHTML = '';
            students.forEach(s => {
                addAttendantRow({
                    student_name: s.name,
                    parent_name: '',
                    phone: '',
                    relationship: 'ผู้ปกครอง'
                });
            });
            
            // 3. เคลียร์ ลลว.๐๓ (ผู้ขาดประชุม) - ค่าเริ่มต้นไม่มีใครขาด
            document.getElementById('absentsTableBody').innerHTML = '';
            
            // 4. ล้างและเติม ลลว.๐๔ (ประสานสัมพันธ์)
            currentRelations = students.map((s, idx) => ({
                student_name: s.name,
                classroom_no: s.classroom ? s.classroom.replace(/^ม\./, '') : '',
                student_no: idx + 1,
                parent_name: '',
                relationship: 'ผู้ปกครอง',
                grade_zero_count: 0,
                grade_r_count: 0,
                grade_ms_count: 0,
                grade_mp_count: 0,
                behavior_score_deducted: 0,
                praise_teacher_json: [],
                praise_teacher_other: '',
                praise_parent_json: [],
                praise_parent_other: '',
                improve_teacher_json: [],
                improve_teacher_other: '',
                improve_parent_json: [],
                improve_parent_other: '',
                teacher_remedy: '',
                parent_remedy: '',
                parent_support_request: '',
                parent_meeting_impression: '',
                parent_teacher_feedback: ''
            }));
            renderRelationsTable();
            
            // 5. ล้างและเติม ลลว.๐๖ (ความในใจของลูก)
            currentLetters = students.map((s, idx) => ({
                student_name: s.name,
                classroom_no: s.classroom ? s.classroom.replace(/^ม\./, '') : '',
                student_no: idx + 1,
                letter_to_whom: 'ผู้ปกครอง',
                impressed_story: '',
                inner_feelings: '',
                proud_story: '',
                improvement_plan: '',
                parent_response: ''
            }));
            renderLettersTable();
            
            showAlert('สำเร็จ', `ดึงข้อมูลนักเรียนและเตรียมเอกสาร ลลว.๐๒, ลลว.๐๔, ลลว.๐๖ จำนวน ${students.length} คน เรียบร้อยแล้ว`, 'success');
        } else {
            showAlert('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถดึงข้อมูลได้', 'error');
        }
    })
    .catch(err => {
        Swal.close();
        console.error(err);
        showAlert('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อระบบได้', 'error');
    });
}

// HTML Escaper helper
function escapeHtml(string) {
    if (!string) return '';
    return String(string)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ------------------------------------------
// DYNAMIC ROW ADDERS (ลลว.๐๒)
// ------------------------------------------
function addAttendantRow(data = {}) {
    const tbody = document.getElementById('attendantsTableBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" class="form-control form-control-sm student_name" value="${data.student_name || ''}" placeholder="ชื่อนักเรียน" required></td>
        <td><input type="text" class="form-control form-control-sm parent_name" value="${data.parent_name || ''}" placeholder="ชื่อผู้ปกครอง" required></td>
        <td><input type="text" class="form-control form-control-sm phone" value="${data.phone || ''}" placeholder="เบอร์โทร"></td>
        <td><input type="text" class="form-control form-control-sm relationship" value="${data.relationship || ''}" placeholder="เช่น บิดา, มารดา"></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove();"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
}

// ------------------------------------------
// DYNAMIC ROW ADDERS (ลลว.๐๓)
// ------------------------------------------
function addAbsentRow(data = {}) {
    const tbody = document.getElementById('absentsTableBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" class="form-control form-control-sm student_name" value="${data.student_name || ''}" placeholder="ชื่อนักเรียน" required></td>
        <td><input type="text" class="form-control form-control-sm parent_name" value="${data.parent_name || ''}" placeholder="ชื่อผู้ปกครอง" required></td>
        <td><input type="text" class="form-control form-control-sm phone" value="${data.phone || ''}" placeholder="เบอร์โทร"></td>
        <td><input type="text" class="form-control form-control-sm relationship" value="${data.relationship || ''}" placeholder="ความสัมพันธ์"></td>
        <td><input type="text" class="form-control form-control-sm absent_reason" value="${data.absent_reason || ''}" placeholder="สาเหตุที่ไม่มา"></td>
        <td><input type="text" class="form-control form-control-sm follow_up_status" value="${data.follow_up_status || ''}" placeholder="สถานะการตาม"></td>
        <td><input type="date" class="form-control form-control-sm follow_up_date" value="${data.follow_up_date || ''}"></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove();"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
}

// ------------------------------------------
// DYNAMIC ROW ADDERS (ลลว.๐๕)
// ------------------------------------------
function addGroupRow(data = {}) {
    const tbody = document.getElementById('groupsTableBody');
    const tr = document.createElement('tr');
    
    // Convert array to comma string for easy display
    let attendantsText = '';
    if (data.attendants_json) {
        if (Array.isArray(data.attendants_json)) {
            attendantsText = data.attendants_json.join(', ');
        } else {
            try {
                const arr = JSON.parse(data.attendants_json);
                attendantsText = arr.join(', ');
            } catch(e) {
                attendantsText = data.attendants_json;
            }
        }
    }
    
    tr.innerHTML = `
        <td><input type="text" class="form-control form-control-sm group_topic" value="${data.group_topic || ''}" placeholder="เช่น ด้านเรียน, การเงิน" required></td>
        <td><input type="text" class="form-control form-control-sm attendants_text" value="${attendantsText}" placeholder="คั่นด้วยลูกน้ำ เช่น นายเอ, นายบี"></td>
        <td><textarea class="form-control form-control-sm discussion_summary" rows="1" placeholder="ประเด็นคุย...">${data.discussion_summary || ''}</textarea></td>
        <td><textarea class="form-control form-control-sm discussion_resolution" rows="1" placeholder="แนวทาง...">${data.discussion_resolution || ''}</textarea></td>
        <td><textarea class="form-control form-control-sm school_support_request" rows="1" placeholder="เสนอแนะ...">${data.school_support_request || ''}</textarea></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove();"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
}

// ------------------------------------------
// COMPLEX TABULAR HANDLERS (ลลว.๐๔)
// ------------------------------------------
function renderRelationsTable() {
    const tbody = document.getElementById('relationsTableBody');
    tbody.innerHTML = '';
    currentRelations.forEach((rel, index) => {
        const totalFails = parseInt(rel.grade_zero_count || 0) + parseInt(rel.grade_r_count || 0) + parseInt(rel.grade_ms_count || 0) + parseInt(rel.grade_mp_count || 0);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="text-center">${index + 1}</td>
            <td><strong>${escapeHtml(rel.student_name)}</strong></td>
            <td>ม.${escapeHtml(rel.classroom_no || '')} / เลขที่ ${rel.student_no || ''}</td>
            <td>${escapeHtml(rel.parent_name || '')} (${escapeHtml(rel.relationship || '')})</td>
            <td>
                <span class="badge bg-danger">ติด 0/ร/มส/มผ: ${totalFails}</span>
                <span class="badge bg-warning text-dark">ถูกหัก ${rel.behavior_score_deducted || 0} คะแนน</span>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openRelationSubModal(${index})"><i class="bi bi-pencil-square"></i> กรอกข้อมูล</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRelationRow(${index})"><i class="bi bi-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function addNewRelationRow() {
    currentRelations.push({
        student_name: '',
        classroom_no: '',
        student_no: 1,
        parent_name: '',
        relationship: '',
        grade_zero_count: 0,
        grade_r_count: 0,
        grade_ms_count: 0,
        grade_mp_count: 0,
        behavior_score_deducted: 0,
        praise_teacher_json: [],
        praise_teacher_other: '',
        praise_parent_json: [],
        praise_parent_other: '',
        improve_teacher_json: [],
        improve_teacher_other: '',
        improve_parent_json: [],
        improve_parent_other: '',
        teacher_remedy: '',
        parent_remedy: '',
        parent_support_request: '',
        parent_meeting_impression: '',
        parent_teacher_feedback: ''
    });
    renderRelationsTable();
    openRelationSubModal(currentRelations.length - 1);
}

let editingRelationIndex = -1;
function openRelationSubModal(index) {
    editingRelationIndex = index;
    const rel = currentRelations[index];
    
    document.getElementById('rel_student_name').value = rel.student_name || '';
    document.getElementById('rel_classroom_no').value = rel.classroom_no || '';
    document.getElementById('rel_student_no').value = rel.student_no || 1;
    document.getElementById('rel_parent_name').value = rel.parent_name || '';
    document.getElementById('rel_relationship').value = rel.relationship || '';
    document.getElementById('rel_grade_zero').value = rel.grade_zero_count || 0;
    document.getElementById('rel_grade_r').value = rel.grade_r_count || 0;
    document.getElementById('rel_grade_ms').value = rel.grade_ms_count || 0;
    document.getElementById('rel_grade_mp').value = rel.grade_mp_count || 0;
    document.getElementById('rel_behavior_score').value = rel.behavior_score_deducted || 0;
    
    document.getElementById('rel_praise_teacher_other').value = rel.praise_teacher_other || '';
    document.getElementById('rel_praise_parent_other').value = rel.praise_parent_other || '';
    document.getElementById('rel_improve_teacher_other').value = rel.improve_teacher_other || '';
    document.getElementById('rel_improve_parent_other').value = rel.improve_parent_other || '';
    
    document.getElementById('rel_teacher_remedy').value = rel.teacher_remedy || '';
    document.getElementById('rel_parent_remedy').value = rel.parent_remedy || '';
    document.getElementById('rel_parent_support').value = rel.parent_support_request || '';
    document.getElementById('rel_parent_impression').value = rel.parent_meeting_impression || '';
    document.getElementById('rel_parent_feedback').value = rel.parent_teacher_feedback || '';
    
    // Parse arrays
    let pt = rel.praise_teacher_json || []; if (!Array.isArray(pt)) pt = JSON.parse(pt || '[]');
    let pp = rel.praise_parent_json || []; if (!Array.isArray(pp)) pp = JSON.parse(pp || '[]');
    let it = rel.improve_teacher_json || []; if (!Array.isArray(it)) it = JSON.parse(it || '[]');
    let ip = rel.improve_parent_json || []; if (!Array.isArray(ip)) ip = JSON.parse(ip || '[]');
    
    document.querySelectorAll('.chk-praise-teacher').forEach(chk => { chk.checked = pt.includes(chk.value); });
    document.querySelectorAll('.chk-praise-parent').forEach(chk => { chk.checked = pp.includes(chk.value); });
    document.querySelectorAll('.chk-improve-teacher').forEach(chk => { chk.checked = it.includes(chk.value); });
    document.querySelectorAll('.chk-improve-parent').forEach(chk => { chk.checked = ip.includes(chk.value); });
    
    $('#relationSubModal').modal('show');
}

function saveRelationSubModal() {
    if (editingRelationIndex === -1) return;
    
    const rel = currentRelations[editingRelationIndex];
    
    rel.student_name = document.getElementById('rel_student_name').value.trim();
    rel.classroom_no = document.getElementById('rel_classroom_no').value.trim();
    rel.student_no = parseInt(document.getElementById('rel_student_no').value) || 1;
    rel.parent_name = document.getElementById('rel_parent_name').value.trim();
    rel.relationship = document.getElementById('rel_relationship').value.trim();
    rel.grade_zero_count = parseInt(document.getElementById('rel_grade_zero').value) || 0;
    rel.grade_r_count = parseInt(document.getElementById('rel_grade_r').value) || 0;
    rel.grade_ms_count = parseInt(document.getElementById('rel_grade_ms').value) || 0;
    rel.grade_mp_count = parseInt(document.getElementById('rel_grade_mp').value) || 0;
    rel.behavior_score_deducted = parseInt(document.getElementById('rel_behavior_score').value) || 0;
    
    rel.praise_teacher_other = document.getElementById('rel_praise_teacher_other').value.trim();
    rel.praise_parent_other = document.getElementById('rel_praise_parent_other').value.trim();
    rel.improve_teacher_other = document.getElementById('rel_improve_teacher_other').value.trim();
    rel.improve_parent_other = document.getElementById('rel_improve_parent_other').value.trim();
    
    rel.teacher_remedy = document.getElementById('rel_teacher_remedy').value.trim();
    rel.parent_remedy = document.getElementById('rel_parent_remedy').value.trim();
    rel.parent_support_request = document.getElementById('rel_parent_support').value.trim();
    rel.parent_meeting_impression = document.getElementById('rel_parent_impression').value.trim();
    rel.parent_teacher_feedback = document.getElementById('rel_parent_feedback').value.trim();
    
    // Get checkbox values
    rel.praise_teacher_json = [];
    document.querySelectorAll('.chk-praise-teacher:checked').forEach(chk => rel.praise_teacher_json.push(chk.value));
    
    rel.praise_parent_json = [];
    document.querySelectorAll('.chk-praise-parent:checked').forEach(chk => rel.praise_parent_json.push(chk.value));
    
    rel.improve_teacher_json = [];
    document.querySelectorAll('.chk-improve-teacher:checked').forEach(chk => rel.improve_teacher_json.push(chk.value));
    
    rel.improve_parent_json = [];
    document.querySelectorAll('.chk-improve-parent:checked').forEach(chk => rel.improve_parent_json.push(chk.value));
    
    $('#relationSubModal').modal('hide');
    renderRelationsTable();
}

function deleteRelationRow(index) {
    currentRelations.splice(index, 1);
    renderRelationsTable();
}

// ------------------------------------------
// COMPLEX TABULAR HANDLERS (ลลว.๐๖)
// ------------------------------------------
function renderLettersTable() {
    const tbody = document.getElementById('lettersTableBody');
    tbody.innerHTML = '';
    currentLetters.forEach((letVal, index) => {
        const tr = document.createElement('tr');
        const hasFeelings = letVal.inner_feelings && letVal.inner_feelings.trim().length > 0;
        tr.innerHTML = `
            <td class="text-center">${index + 1}</td>
            <td><strong>${escapeHtml(letVal.student_name)}</strong></td>
            <td>ม.${escapeHtml(letVal.classroom_no || '')} / เลขที่ ${letVal.student_no || ''}</td>
            <td>เขียนถึง: ${escapeHtml(letVal.letter_to_whom || 'ยังไม่ได้ระบุ')}</td>
            <td>
                <span class="badge ${hasFeelings ? 'bg-success' : 'bg-secondary'}">${hasFeelings ? 'บันทึกความรู้สึกแล้ว' : 'ยังไม่ได้บันทึก'}</span>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openLetterSubModal(${index})"><i class="bi bi-pencil-square"></i> กรอกจดหมาย</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteLetterRow(${index})"><i class="bi bi-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function addNewLetterRow() {
    currentLetters.push({
        student_name: '',
        classroom_no: '',
        student_no: 1,
        letter_to_whom: '',
        impressed_story: '',
        inner_feelings: '',
        proud_story: '',
        improvement_plan: '',
        parent_response: ''
    });
    renderLettersTable();
    openLetterSubModal(currentLetters.length - 1);
}

let editingLetterIndex = -1;
function openLetterSubModal(index) {
    editingLetterIndex = index;
    const letVal = currentLetters[index];
    
    document.getElementById('let_student_name').value = letVal.student_name || '';
    document.getElementById('let_classroom_no').value = letVal.classroom_no || '';
    document.getElementById('let_student_no').value = letVal.student_no || 1;
    document.getElementById('let_to_whom').value = letVal.letter_to_whom || '';
    document.getElementById('let_impressed').value = letVal.impressed_story || '';
    document.getElementById('let_inner_feelings').value = letVal.inner_feelings || '';
    document.getElementById('let_proud').value = letVal.proud_story || '';
    document.getElementById('let_improvement').value = letVal.improvement_plan || '';
    document.getElementById('let_parent_response').value = letVal.parent_response || '';
    
    $('#letterSubModal').modal('show');
}

function saveLetterSubModal() {
    if (editingLetterIndex === -1) return;
    
    const letVal = currentLetters[editingLetterIndex];
    
    letVal.student_name = document.getElementById('let_student_name').value.trim();
    letVal.classroom_no = document.getElementById('let_classroom_no').value.trim();
    letVal.student_no = parseInt(document.getElementById('let_student_no').value) || 1;
    letVal.letter_to_whom = document.getElementById('let_to_whom').value.trim();
    letVal.impressed_story = document.getElementById('let_impressed').value.trim();
    letVal.inner_feelings = document.getElementById('let_inner_feelings').value.trim();
    letVal.proud_story = document.getElementById('let_proud').value.trim();
    letVal.improvement_plan = document.getElementById('let_improvement').value.trim();
    letVal.parent_response = document.getElementById('let_parent_response').value.trim();
    
    $('#letterSubModal').modal('hide');
    renderLettersTable();
}

function deleteLetterRow(index) {
    currentLetters.splice(index, 1);
    renderLettersTable();
}

// ------------------------------------------
// MODAL OPENERS
// ------------------------------------------
function openAddModal() {
    document.getElementById('meetingModalLabel').textContent = 'เพิ่มบันทึกรายงานการประชุม (ลลว.๐๑ - ลลว.๐๖)';
    document.getElementById('meetingForm').reset();
    document.getElementById('meeting_id').value = '';
    document.getElementById('action').value = 'save_meeting';
    
    // Clear inline tables
    document.getElementById('attendantsTableBody').innerHTML = '';
    document.getElementById('absentsTableBody').innerHTML = '';
    document.getElementById('groupsTableBody').innerHTML = '';

    // Hide sync button
    document.getElementById('btnSyncStudents').style.display = 'none';

    // Clear arrays
    currentRelations = [];
    renderRelationsTable();
    currentLetters = [];
    renderLettersTable();

    // ซ่อนพรีวิวภาพเดิมและภาพใหม่
    document.getElementById('existing-images-container').classList.add('d-none');
    document.getElementById('existing-images-grid').innerHTML = '';
    document.getElementById('new-images-container').classList.add('d-none');
    document.getElementById('new-images-grid').innerHTML = '';
    
    // ตั้งค่าปีการศึกษาเริ่มต้นตามปีปัจจุบัน
    document.getElementById('academic_year').value = new Date().getFullYear() + 543;
    
    // เปิดแท็บแรก
    const firstTabEl = document.querySelector('#meetingTab button[data-bs-target="#tab1"]');
    const firstTab = new bootstrap.Tab(firstTabEl);
    firstTab.show();

    $('#meetingModal').modal('show');
}

function openEditModal(meetingId) {
    document.getElementById('meetingModalLabel').textContent = 'แก้ไขบันทึกรายงานการประชุม (ลลว.๐๑ - ลลว.๐๖)';
    document.getElementById('meetingForm').reset();
    document.getElementById('meeting_id').value = meetingId;
    document.getElementById('action').value = 'save_meeting';
    
    // เคลียร์พรีวิวและตาราง
    document.getElementById('existing-images-grid').innerHTML = '';
    document.getElementById('new-images-container').classList.add('d-none');
    document.getElementById('new-images-grid').innerHTML = '';
    document.getElementById('attendantsTableBody').innerHTML = '';
    document.getElementById('absentsTableBody').innerHTML = '';
    document.getElementById('groupsTableBody').innerHTML = '';
    
    // Hide sync button initially
    document.getElementById('btnSyncStudents').style.display = 'none';

    currentRelations = [];
    renderRelationsTable();
    currentLetters = [];
    renderLettersTable();

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
            
            // Show sync button since classroom is loaded
            if (m.classroom_id) {
                document.getElementById('btnSyncStudents').style.display = 'inline-block';
            }
            
            document.getElementById('total_students').value = m.total_students;
            document.getElementById('total_parents').value = m.total_parents;
            document.getElementById('attend_count').value = m.attend_count;
            document.getElementById('absent_count').value = m.absent_count;
            document.getElementById('summary').value = m.summary || '';
            document.getElementById('problems').value = m.problems || '';
            document.getElementById('suggestions').value = m.suggestions || '';
            
            // ข้อมูลเสริม ลลว.๐๑
            document.getElementById('doc_no').value = m.doc_no || '';
            document.getElementById('doc_date').value = m.doc_date || '';
            document.getElementById('command_no').value = m.command_no || '';
            document.getElementById('command_date').value = m.command_date || '';
            document.getElementById('agenda_1').value = m.agenda_1 || '';
            document.getElementById('agenda_2').value = m.agenda_2 || '';
            document.getElementById('agenda_3').value = m.agenda_3 || '';
            document.getElementById('consensus').value = m.consensus || '';
            document.getElementById('cooperation_rating').value = m.cooperation_rating || '';
            document.getElementById('useful_suggestions').value = m.useful_suggestions || '';
            document.getElementById('support_received').value = m.support_received || '';
            document.getElementById('other_observations').value = m.other_observations || '';

            // โหลดตารางย่อย ลลว.๐๒
            (data.attendants || []).forEach(att => addAttendantRow(att));
            
            // โหลดตารางย่อย ลลว.๐๓
            (data.absents || []).forEach(abs => addAbsentRow(abs));

            // โหลดตารางย่อย ลลว.๐๕
            (data.groups || []).forEach(grp => addGroupRow(grp));

            // โหลดตารางย่อย ลลว.๐๔
            currentRelations = data.relations || [];
            renderRelationsTable();

            // โหลดตารางย่อย ลลว.๐๖
            currentLetters = data.letters || [];
            renderLettersTable();

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
            
            // เปิดแท็บแรก
            const firstTabEl = document.querySelector('#meetingTab button[data-bs-target="#tab1"]');
            const firstTab = new bootstrap.Tab(firstTabEl);
            firstTab.show();

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
                if (wrapper) wrapper.remove();
                
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
