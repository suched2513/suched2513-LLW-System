<?php
/**
 * parent_meeting/reports.php - จัดการรายงานและการอนุมัติลงความเห็นสำหรับผู้บริหาร
 */
require_once __DIR__ . '/config.php';
checkRole(['executive', 'admin']); // เฉพาะผู้บริหารและแอดมิน

$pageTitle = 'รายงานทั้งหมด';
$pageSubtitle = 'รายงานการประชุมผู้ปกครองทั้งโรงเรียนและการประเมินของผู้บริหาร';
$activePage = 'reports';

$pdo = getPmPdo();

try {
    // ดึงปีการศึกษาและเทอมที่มีการส่งรายงานเข้ามา เพื่อเอามาทำตัวกรอง
    $filterStmt = $pdo->query("SELECT DISTINCT semester, academic_year FROM pm_meetings ORDER BY academic_year DESC, semester DESC");
    $filters = $filterStmt->fetchAll();
    
    // ตั้งค่าตัวเลือกตัวกรอง
    $selSemester = $_GET['semester'] ?? '';
    $selYear = $_GET['academic_year'] ?? '';
    $selLevel = $_GET['level'] ?? '';
    
    // สร้าง SQL Query แบบ Dynamic ตามตัวเลือก
    $query = "
        SELECT m.*, c.level, c.room_name, u.fullname as creator_name,
               (SELECT COUNT(*) FROM pm_comments WHERE meeting_id = m.id) as comment_count,
               COALESCE(
                   (SELECT GROUP_CONCAT(CONCAT(lu.firstname, ' ', lu.lastname)
                           ORDER BY la.role_type ASC, la.id ASC SEPARATOR ' และ ')
                    FROM llw_class_advisors la
                    LEFT JOIN llw_users lu ON la.user_id = lu.user_id
                    WHERE la.classroom = CONCAT(c.level, '/', c.room_name)),
                   c.teacher_name
               ) as teacher_name
        FROM pm_meetings m
        JOIN pm_classrooms c ON m.classroom_id = c.id
        JOIN pm_users u ON m.created_by = u.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($selSemester !== '') {
        $query .= " AND m.semester = ?";
        $params[] = $selSemester;
    }
    if ($selYear !== '') {
        $query .= " AND m.academic_year = ?";
        $params[] = $selYear;
    }
    if ($selLevel !== '') {
        $query .= " AND c.level = ?";
        $params[] = $selLevel;
    }
    
    $query .= " ORDER BY m.meeting_date DESC, c.level, c.room_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();

    // สถิติรวม
    $totalMeetings = count($reports);
    $totalParents  = array_sum(array_column($reports, 'total_parents'));
    $totalAttend   = array_sum(array_column($reports, 'attend_count'));
    $totalAbsent   = array_sum(array_column($reports, 'absent_count'));
    $attendRate    = $totalParents > 0 ? round($totalAttend / $totalParents * 100, 1) : 0;

    // ดึงรายชื่อผู้ขาดแยกตามห้อง (filter เดียวกัน)
    $absentQuery = "
        SELECT c.level, c.room_name,
               a.student_name, a.parent_name, a.phone,
               a.absent_reason, a.follow_up_status,
               COALESCE(
                   (SELECT GROUP_CONCAT(CONCAT(lu.firstname, ' ', lu.lastname)
                           ORDER BY la.role_type ASC, la.id ASC SEPARATOR ' และ ')
                    FROM llw_class_advisors la
                    LEFT JOIN llw_users lu ON la.user_id = lu.user_id
                    WHERE la.classroom = CONCAT(c.level, '/', c.room_name)),
                   c.teacher_name
               ) as teacher_name
        FROM pm_meeting_absents a
        JOIN pm_meetings m ON a.meeting_id = m.id
        JOIN pm_classrooms c ON m.classroom_id = c.id
        WHERE 1=1
    ";
    $absentParams = [];
    if ($selSemester !== '') { $absentQuery .= " AND m.semester = ?";      $absentParams[] = $selSemester; }
    if ($selYear     !== '') { $absentQuery .= " AND m.academic_year = ?"; $absentParams[] = $selYear; }
    if ($selLevel    !== '') { $absentQuery .= " AND c.level = ?";         $absentParams[] = $selLevel; }
    $absentQuery .= " ORDER BY c.level, c.room_name, a.student_name";

    $absentStmt = $pdo->prepare($absentQuery);
    $absentStmt->execute($absentParams);
    $allAbsents = $absentStmt->fetchAll();

    // จัดกลุ่มผู้ขาดตามห้องเรียน
    $absentsByClass = [];
    foreach ($allAbsents as $ab) {
        $key = $ab['level'] . '/' . $ab['room_name'];
        if (!isset($absentsByClass[$key])) {
            $absentsByClass[$key] = [
                'level'        => $ab['level'],
                'room_name'    => $ab['room_name'],
                'teacher_name' => $ab['teacher_name'],
                'absents'      => []
            ];
        }
        $absentsByClass[$key]['absents'][] = $ab;
    }

} catch (Exception $e) {
    error_log('[Parent Meeting] Reports Fetch Error: ' . $e->getMessage());
    $filters        = [];
    $reports        = [];
    $totalMeetings  = $totalParents = $totalAttend = $totalAbsent = 0;
    $attendRate     = 0;
    $absentsByClass = [];
}


require_once __DIR__ . '/components/layout_start.php';
?>

<!-- KPI Cards ภาพรวม -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-4" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
            <div class="text-white opacity-75 text-xs font-bold text-uppercase mb-1">ห้องที่ส่งรายงาน</div>
            <div class="text-white fw-black" style="font-size:2rem;"><?= $totalMeetings ?></div>
            <div class="text-white opacity-75 text-xs">ห้องเรียน</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-4" style="background:linear-gradient(135deg,#0ea5e9,#38bdf8);">
            <div class="text-white opacity-75 text-xs font-bold text-uppercase mb-1">ผู้ปกครองทั้งหมด</div>
            <div class="text-white fw-black" style="font-size:2rem;"><?= number_format($totalParents) ?></div>
            <div class="text-white opacity-75 text-xs">คน (ทุกห้อง)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-4" style="background:linear-gradient(135deg,#10b981,#34d399);">
            <div class="text-white opacity-75 text-xs font-bold text-uppercase mb-1">มาร่วมประชุม</div>
            <div class="text-white fw-black" style="font-size:2rem;"><?= number_format($totalAttend) ?></div>
            <div class="text-white opacity-75 text-xs">คน (<?= $attendRate ?>%)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-4" style="background:linear-gradient(135deg,#f43f5e,#fb7185);">
            <div class="text-white opacity-75 text-xs font-bold text-uppercase mb-1">ไม่ได้มาประชุม</div>
            <div class="text-white fw-black" style="font-size:2rem;"><?= number_format($totalAbsent) ?></div>
            <div class="text-white opacity-75 text-xs">คน (<?= $totalParents > 0 ? round($totalAbsent/$totalParents*100,1) : 0 ?>%)</div>
        </div>
    </div>
</div>

<!-- การ์ดตัวกรองค้นหา -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white">
        <h6 class="m-0 font-bold text-dark-blue"><i class="bi bi-search me-1"></i> ตัวกรองข้อมูลรายงาน</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-12 col-md-3">
                <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ระดับชั้นเรียน</label>
                <select class="form-select rounded-3 text-sm" name="level">
                    <option value="">-- แสดงทุกชั้น --</option>
                    <option value="ม.1" <?= $selLevel === 'ม.1' ? 'selected' : '' ?>>มัธยมศึกษาปีที่ 1</option>
                    <option value="ม.2" <?= $selLevel === 'ม.2' ? 'selected' : '' ?>>มัธยมศึกษาปีที่ 2</option>
                    <option value="ม.3" <?= $selLevel === 'ม.3' ? 'selected' : '' ?>>มัธยมศึกษาปีที่ 3</option>
                    <option value="ม.4" <?= $selLevel === 'ม.4' ? 'selected' : '' ?>>มัธยมศึกษาปีที่ 4</option>
                    <option value="ม.5" <?= $selLevel === 'ม.5' ? 'selected' : '' ?>>มัธยมศึกษาปีที่ 5</option>
                    <option value="ม.6" <?= $selLevel === 'ม.6' ? 'selected' : '' ?>>มัธยมศึกษาปีที่ 6</option>
                </select>
            </div>
            
            <div class="col-12 col-md-3">
                <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ปีการศึกษา</label>
                <select class="form-select rounded-3 text-sm" name="academic_year">
                    <option value="">-- ทุกปีการศึกษา --</option>
                    <?php 
                    $years = array_unique(array_column($filters, 'academic_year'));
                    foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= (string)$selYear === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ภาคเรียน</label>
                <select class="form-select rounded-3 text-sm" name="semester">
                    <option value="">-- ทุกภาคเรียน --</option>
                    <option value="1" <?= $selSemester === '1' ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                    <option value="2" <?= $selSemester === '2' ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                </select>
            </div>

            <div class="col-12 col-md-3 d-flex align-items-end">
                <div class="btn-group w-100 gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 text-sm font-bold w-100 py-2.5">
                        <i class="bi bi-filter"></i> กรองข้อมูล
                    </button>
                    <a href="reports.php" class="btn btn-outline-secondary rounded-3 text-sm font-bold w-100 py-2.5">
                        ล้างตัวกรอง
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($absentsByClass)): ?>
<!-- ภาพรวมผู้ขาดประชุมแยกห้อง -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
        <h6 class="m-0 font-bold" style="color:#be123c;">
            <i class="bi bi-person-x-fill me-2"></i>ภาพรวมผู้ปกครองที่ไม่ได้มาประชุมแยกห้อง
        </h6>
        <span class="badge bg-danger-subtle text-danger font-bold px-3 py-2 rounded-pill">
            รวม <?= number_format($totalAbsent) ?> คน จาก <?= count($absentsByClass) ?> ห้อง
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px;">
                <thead class="table-light">
                    <tr>
                        <th class="px-4" style="width:90px;">ห้อง</th>
                        <th style="width:200px;">ครูที่ปรึกษา</th>
                        <th style="width:80px;" class="text-center">ขาด</th>
                        <th>รายชื่อนักเรียน / ผู้ปกครองที่ขาด</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($absentsByClass as $cls):
                    $cnt = count($cls['absents']);
                    $colId = 'abs_' . preg_replace('/[^a-z0-9]/i','_', $cls['level'].$cls['room_name']);
                ?>
                    <tr class="align-middle">
                        <td class="px-4 fw-bold">
                            <span class="badge bg-danger text-white px-2 py-1 rounded">
                                <?= esc($cls['level'] . '/' . $cls['room_name']) ?>
                            </span>
                        </td>
                        <td class="text-muted" style="font-size:12px;"><?= esc(format_teacher_names($cls['teacher_name'])) ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1"
                                    data-bs-toggle="collapse" data-bs-target="#<?= $colId ?>">
                                <?= $cnt ?> คน
                            </button>
                        </td>
                        <td>
                            <div class="collapse" id="<?= $colId ?>">
                                <div class="py-2">
                                <?php foreach ($cls['absents'] as $i => $ab): ?>
                                    <div class="d-flex align-items-start gap-2 py-1 <?= $i > 0 ? 'border-top' : '' ?>">
                                        <span class="badge bg-light text-secondary border fw-bold" style="min-width:22px;font-size:10px;"><?= $i+1 ?></span>
                                        <div class="flex-grow-1">
                                            <span class="fw-bold text-dark"><?= esc($ab['student_name']) ?></span>
                                            <?php if ($ab['parent_name']): ?>
                                                <span class="text-muted ms-1">/ ผปก. <?= esc($ab['parent_name']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($ab['phone']): ?>
                                                <span class="badge bg-light text-secondary ms-1 border" style="font-size:11px;">
                                                    <i class="bi bi-telephone"></i> <?= esc($ab['phone']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($ab['absent_reason']): ?>
                                                <div class="text-xs text-muted mt-1">
                                                    <i class="bi bi-chat-left-text me-1"></i>สาเหตุ: <?= esc($ab['absent_reason']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <?php if ($ab['follow_up_status'] === 'followed_up'): ?>
                                                <span class="badge bg-success-subtle text-success rounded-pill px-2" style="font-size:10px;">
                                                    <i class="bi bi-check-circle-fill"></i> ติดตามแล้ว
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning rounded-pill px-2" style="font-size:10px;">
                                                    <i class="bi bi-clock"></i> ยังไม่ติดตาม
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="text-xs text-muted">
                                <i class="bi bi-caret-down"></i> คลิกที่จำนวนคนเพื่อดูรายชื่อ
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ตารางรายงานการประชุมทั้งหมด -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive p-3">
            <table id="reportsTable" class="table table-hover w-100 mb-0">
                <thead>
                    <tr>
                        <th width="8%">ลำดับ</th>
                        <th width="12%">ห้องเรียน</th>
                        <th width="12%">วันที่ประชุม</th>
                        <th width="12%">เทอม/ปีการศึกษา</th>
                        <th width="15%">ครูที่ปรึกษา</th>
                        <th width="12%">ผู้ปกครองเข้าร่วม</th>
                        <th width="12%">ความเห็นผู้บริหาร</th>
                        <th width="17%" class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($reports as $r): 
                        $rate = $r['total_parents'] > 0 ? round(($r['attend_count'] / $r['total_parents']) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="badge bg-primary text-white rounded px-2.5 py-1 font-bold">ม.<?= esc($r['level'] . '/' . $r['room_name']) ?></span></td>
                            <td><span class="font-bold text-dark"><?= th_date($r['meeting_date']) ?></span></td>
                            <td>เทอม <?= esc($r['semester'] . '/' . $r['academic_year']) ?></td>
                            <td><?= esc(format_teacher_names($r['teacher_name'])) ?></td>
                            <td>
                                <span class="font-bold text-success"><?= esc($r['attend_count']) ?></span> / <?= esc($r['total_parents']) ?> คน
                                <div class="text-xs text-muted">(คิดเป็น <?= $rate ?>%)</div>
                            </td>
                            <td>
                                <?php if ($r['comment_count'] > 0): ?>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 text-xs font-bold">
                                        <i class="bi bi-chat-left-text-fill me-1"></i> ให้ความเห็นแล้ว (<?= $r['comment_count'] ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-1 text-xs font-bold">
                                        <i class="bi bi-chat-left-text me-1"></i> รอผู้บริหารลงความเห็น
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group gap-1">
                                    <button class="btn btn-sm btn-primary rounded px-2 py-1 text-xs font-bold" onclick="viewReportDetails(<?= $r['id'] ?>)">
                                        <i class="bi bi-eye"></i> ตรวจรายงาน
                                    </button>
                                    <a href="<?= pm_url('print_report.php?id=' . $r['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded px-2 py-1" title="พิมพ์รายงาน / PDF">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal สำหรับตรวจและลงความเห็นรายละเอียดรายงานการประชุม -->
<div class="modal fade" id="reportDetailsModal" tabindex="-1" aria-labelledby="reportDetailsModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4">
                <h5 class="modal-title font-black text-dark-blue" id="reportDetailsModalLabel">การตรวจบันทึกรายงานการประชุม</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <!-- ฝั่งซ้าย: รายละเอียดรายงาน -->
                    <div class="col-12 col-lg-8">
                        <div class="card border-0 shadow-sm p-4 bg-white mb-4">
                            <h5 class="font-bold text-dark mb-3 border-bottom pb-2">
                                <i class="bi bi-file-earmark-text text-primary me-2"></i>รายละเอียดรายงานการประชุม
                            </h5>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-12 col-sm-4 bg-light p-2.5 rounded-3">
                                    <div class="text-xs text-muted font-bold">ห้องเรียน</div>
                                    <div class="font-bold text-dark" id="view_classroom">ม.1/1</div>
                                </div>
                                <div class="col-12 col-sm-4 bg-light p-2.5 rounded-3">
                                    <div class="text-xs text-muted font-bold">ครูที่ปรึกษา</div>
                                    <div class="font-bold text-dark" id="view_teacher">ครูสมชาย</div>
                                </div>
                                <div class="col-12 col-sm-4 bg-light p-2.5 rounded-3">
                                    <div class="text-xs text-muted font-bold">วันที่บันทึก</div>
                                    <div class="font-bold text-dark" id="view_date">1 ม.ค. 2569</div>
                                </div>
                                <div class="col-12 col-sm-4 bg-light p-2.5 rounded-3">
                                    <div class="text-xs text-muted font-bold">ภาคเรียน / ปีการศึกษา</div>
                                    <div class="font-bold text-dark" id="view_semester">เทอม 1/2569</div>
                                </div>
                                <div class="col-12 col-sm-4 bg-light p-2.5 rounded-3">
                                    <div class="text-xs text-muted font-bold">จำนวนนักเรียน / ผู้ปกครอง</div>
                                    <div class="font-bold text-dark" id="view_students">30 คน / 30 คน</div>
                                </div>
                                <div class="col-12 col-sm-4 bg-light p-2.5 rounded-3">
                                    <div class="text-xs text-muted font-bold">ผู้ปกครองที่เข้าประชุม (มา / ขาด)</div>
                                    <div class="font-bold text-dark" id="view_attendance">28 คน / 2 คน (93%)</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="font-bold text-dark"><i class="bi bi-check-circle-fill text-success me-1"></i> สรุปเนื้อหาการประชุม:</h6>
                                <p class="text-sm bg-light p-3 rounded-3 mb-0" style="white-space: pre-wrap;" id="view_summary">-</p>
                            </div>
                            <div class="mb-3">
                                <h6 class="font-bold text-dark"><i class="bi bi-exclamation-octagon-fill text-danger me-1"></i> ปัญหาอุปสรรคที่พบ:</h6>
                                <p class="text-sm bg-light p-3 rounded-3 mb-0" style="white-space: pre-wrap;" id="view_problems">-</p>
                            </div>
                            <div class="mb-0">
                                <h6 class="font-bold text-dark"><i class="bi bi-lightbulb-fill text-warning me-1"></i> ข้อเสนอแนะ / แนวทางการแก้ไข:</h6>
                                <p class="text-sm bg-light p-3 rounded-3 mb-0" style="white-space: pre-wrap;" id="view_suggestions">-</p>
                            </div>
                        </div>

                        <!-- การ์ดแสดงรูปภาพบรรยากาศ -->
                        <div class="card border-0 shadow-sm p-4 bg-white">
                            <h5 class="font-bold text-dark mb-3 border-bottom pb-2">
                                <i class="bi bi-images text-primary me-2"></i>รูปภาพบรรยากาศกิจกรรม
                            </h5>
                            <div id="view_images_grid" class="row g-3">
                                <!-- รูปภาพจะมาดึงและวาดผ่าน JS -->
                            </div>
                        </div>
                    </div>

                    <!-- ฝั่งขวา: การแสดงความคิดเห็น/ความเห็นผู้บริหาร -->
                    <div class="col-12 col-lg-4">
                        <div class="card border-0 shadow-sm p-4 bg-white h-100 d-flex flex-column">
                            <h5 class="font-bold text-dark mb-3 border-bottom pb-2">
                                <i class="bi bi-chat-left-quote text-primary me-2"></i>ความเห็นและข้อสั่งการผู้บริหาร
                            </h5>
                            
                            <!-- กล่องแสดงรายการประวัติความเห็น -->
                            <div class="flex-grow-1 overflow-auto mb-3" style="max-height: 300px;" id="comments_list">
                                <!-- ความเห็นจะถูกนำมาวาดด้วย JS -->
                            </div>
                            
                            <!-- ฟอร์มลงความคิดเห็นเพิ่มเติม -->
                            <form id="commentForm" class="border-top pt-3 mt-auto">
                                <input type="hidden" name="action" value="save_comment">
                                <input type="hidden" name="meeting_id" id="comment_meeting_id" value="">
                                
                                <div class="mb-3">
                                    <label class="form-label text-xs font-black uppercase text-muted tracking-wider">ลงความเห็น / สั่งการเพิ่มเติม</label>
                                    <textarea class="form-control rounded-3 text-sm" name="comment_text" id="comment_text" rows="3" placeholder="เขียนข้อเสนอแนะ การรับทราบ หรือข้อสั่งการของผู้บริหาร..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100 rounded-3 font-bold text-sm" id="btnSaveComment">
                                    <i class="bi bi-chat-dots me-1"></i> ยืนยันการลงความเห็น
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer border-top py-3 px-4">
                <a href="" id="btn_modal_print" target="_blank" class="btn btn-outline-secondary rounded-3 text-sm font-bold">
                    <i class="bi bi-printer me-1"></i> พิมพ์รายงานฉบับเต็ม (PDF)
                </a>
                <button type="button" class="btn btn-primary rounded-3 text-sm font-bold px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // โหลดตาราง DataTable
    $('#reportsTable').DataTable({
        order: [[2, 'desc']]
    });

    // การส่งความคิดเห็น (Comment) ผ่าน AJAX
    const commentForm = document.getElementById('commentForm');
    commentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const btnSave = document.getElementById('btnSaveComment');
        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';
        
        fetch('api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-chat-dots me-1"></i> ยืนยันการลงความเห็น';
            
            if (data.status === 'success') {
                document.getElementById('comment_text').value = '';
                
                // โหลดคอมเมนต์ใหม่โดยใช้ฟังก์ชันโหลดประวัติคอมเมนต์
                const meetingId = document.getElementById('comment_meeting_id').value;
                loadComments(meetingId);
                
                Swal.fire({
                    title: 'สำเร็จ',
                    text: 'ลงความเห็นเรียบร้อยแล้ว',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                showAlert('เกิดข้อผิดพลาด', data.message || 'บันทึกความเห็นไม่สำเร็จ', 'error');
            }
        })
        .catch(err => {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-chat-dots me-1"></i> ยืนยันการลงความเห็น';
            console.error(err);
            showAlert('ข้อผิดพลาด', 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้', 'error');
        });
    });
});

// ฟังก์ชันโหลดความเห็น
function loadComments(meetingId) {
    const list = document.getElementById('comments_list');
    list.innerHTML = '<div class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-1"></span> กำลังโหลดข้อมูล...</div>';
    
    fetch(`api.php?action=get_comments&meeting_id=${meetingId}`)
    .then(res => res.json())
    .then(data => {
        list.innerHTML = '';
        if (data.status === 'success') {
            const comments = data.data || [];
            if (comments.length === 0) {
                list.innerHTML = '<div class="text-center py-4 text-muted fs-7"><i class="bi bi-chat-quote d-block mb-1 fs-5"></i>ยังไม่มีข้อเสนอแนะหรือความเห็นผู้บริหาร</div>';
            } else {
                comments.forEach(c => {
                    const item = document.createElement('div');
                    item.className = 'bg-light rounded-3 p-3 mb-2 border';
                    item.innerHTML = `
                        <p class="text-sm mb-1 text-dark" style="white-space: pre-wrap;">${escapeHtml(c.comment_text)}</p>
                        <div class="d-flex align-items-center justify-content-between border-top pt-1 mt-1 text-xs text-muted">
                            <span><strong>${escapeHtml(c.commenter_name)}</strong></span>
                            <span>${c.created_at_formatted}</span>
                        </div>
                    `;
                    list.appendChild(item);
                });
            }
        }
    })
    .catch(err => {
        console.error(err);
        list.innerHTML = '<div class="text-center py-3 text-danger">โหลดคอมเมนต์ไม่สำเร็จ</div>';
    });
}

// ดูรายละเอียดรายงานการประชุมในโมดูลตรวจประเมิน
function viewReportDetails(meetingId) {
    // กำหนดค่าปุ่มพิมพ์รายงาน
    document.getElementById('btn_modal_print').href = `print_report.php?id=${meetingId}`;
    document.getElementById('comment_meeting_id').value = meetingId;
    
    // โหลดรายละเอียดผ่าน API
    fetch(`api.php?action=get_meeting&id=${meetingId}`)
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const m = data.data;
            document.getElementById('view_classroom').textContent = `ม.${m.level}/${m.room_name}`;
            document.getElementById('view_teacher').textContent = m.teacher_name_formatted || `ครู${m.teacher_name}`;
            document.getElementById('view_date').textContent = m.meeting_date_formatted;
            document.getElementById('view_semester').textContent = `เทอม ${m.semester}/${m.academic_year}`;
            document.getElementById('view_students').textContent = `${m.total_students} คน / ${m.total_parents} คน`;
            
            const rate = m.total_parents > 0 ? Math.round((m.attend_count / m.total_parents) * 100) : 0;
            document.getElementById('view_attendance').innerHTML = `<span class="text-success">${m.attend_count} คน</span> / <span class="text-danger">${m.absent_count} คน</span> (เข้าร่วมประชุม ${rate}%)`;
            
            document.getElementById('view_summary').textContent = m.summary || 'ไม่ได้ระบุเนื้อหา';
            document.getElementById('view_problems').textContent = m.problems || 'ไม่มีปัญหาอุปสรรค';
            document.getElementById('view_suggestions').textContent = m.suggestions || 'ไม่มีข้อเสนอแนะ';
            
            // วาดรูปบรรยากาศกิจกรรม
            const imgGrid = document.getElementById('view_images_grid');
            imgGrid.innerHTML = '';
            const images = data.images || [];
            if (images.length === 0) {
                imgGrid.innerHTML = '<div class="col-12 text-center py-4 text-muted fs-7">ไม่ได้แนบรูปกิจกรรมประกอบรายงาน</div>';
            } else {
                images.forEach(img => {
                    const col = document.createElement('div');
                    col.className = 'col-6 col-sm-4';
                    col.innerHTML = `
                        <a href="${img.image_path}" target="_blank" class="d-block rounded-3 overflow-hidden border" style="height: 120px;">
                            <img src="${img.image_path}" class="w-100 h-100 object-fit-cover hover-scale" style="transition: all 0.2s;" title="คลิกเพื่อดูรูปภาพขนาดใหญ่">
                        </a>
                    `;
                    imgGrid.appendChild(col);
                });
            }
            
            // โหลดประวัติความเห็น
            loadComments(meetingId);
            
            // แสดงผล Modal
            $('#reportDetailsModal').modal('show');
        } else {
            showAlert('ข้อผิดพลาด', 'ดึงข้อมูลรายงานไม่สำเร็จ: ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อระบบ API ได้', 'error');
    });
}

// ฟังก์ชันกรองการแปลง HTML
function escapeHtml(string) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(string).replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>

<style>
.hover-scale:hover {
    transform: scale(1.05);
}
</style>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
