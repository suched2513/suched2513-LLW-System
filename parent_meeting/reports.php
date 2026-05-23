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
    
    // ดึงข้อมูลผู้ขาดประชุมของแต่ละห้อง
    $meetingIds = array_column($reports, 'id');
    $absentsByMeeting = [];
    if (!empty($meetingIds)) {
        $inClause = implode(',', array_fill(0, count($meetingIds), '?'));
        $absentStmt = $pdo->prepare("SELECT * FROM pm_meeting_absents WHERE meeting_id IN ($inClause) ORDER BY id ASC");
        $absentStmt->execute($meetingIds);
        $allAbsents = $absentStmt->fetchAll();
        
        foreach ($allAbsents as $abs) {
            $absentsByMeeting[$abs['meeting_id']][] = $abs;
        }
        // คำนวณและแก้ไขยอดผู้เข้าเรียน/ขาดเรียนใน $reports ให้ตรงกับประวัติจริงใน pm_meeting_absents เสมอ
        foreach ($reports as &$r) {
            $mId = $r['id'];
            $actualAbsent = isset($absentsByMeeting[$mId]) ? count($absentsByMeeting[$mId]) : 0;
            $r['absent_count'] = $actualAbsent;
            $r['attend_count'] = max(0, (int)$r['total_parents'] - $actualAbsent);
        }
        unset($r);
    }
    
} catch (Exception $e) {
    error_log('[Parent Meeting] Reports Fetch Error: ' . $e->getMessage());
    $filters = [];
    $reports = [];
    $absentsByMeeting = [];
}

require_once __DIR__ . '/components/layout_start.php';
?>

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

<!-- ตารางรายงานการประชุมทั้งหมด -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white p-0 border-bottom">
        <ul class="nav nav-tabs card-header-tabs m-0 border-0" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active px-4 py-3 border-0 rounded-0 font-bold" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-pane" type="button" role="tab" aria-controls="list-pane" aria-selected="true">
                    <i class="bi bi-file-earmark-text me-2 text-primary"></i>รายงานการประชุมรายห้อง
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link px-4 py-3 border-0 rounded-0 font-bold" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="false">
                    <i class="bi bi-people-fill me-2 text-danger"></i>ภาพรวมผู้ไม่มาประชุมผู้ปกครอง
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content" id="reportTabsContent">
            <!-- Tab 1: รายงานการประชุมรายห้อง -->
            <div class="tab-pane fade show active" id="list-pane" role="tabpanel" aria-labelledby="list-tab" tabindex="0">
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
            
            <!-- Tab 2: ภาพรวมผู้ไม่มาประชุมผู้ปกครอง -->
            <div class="tab-pane fade" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab" tabindex="0">
                <div class="p-4">
                    <?php
                    $totalClassroomsCount = count($reports);
                    $sumStudents = 0;
                    $sumAttend = 0;
                    $sumAbsent = 0;
                    foreach ($reports as $r) {
                        $sumStudents += (int)$r['total_parents'];
                        $sumAttend += (int)$r['attend_count'];
                        $sumAbsent += (int)$r['absent_count'];
                    }
                    $overallAttendRate = $sumStudents > 0 ? round(($sumAttend / $sumStudents) * 100, 1) : 0;
                    ?>
                    
                    <!-- แถบปุ่มพิมพ์รายงาน/PDF สรุปภาพรวม -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-light p-3 rounded-3 border border-slate-100">
                        <div>
                            <span class="text-xs text-muted font-bold uppercase tracking-wide"><i class="bi bi-printer me-1"></i> เมนูการพิมพ์และส่งออกเอกสาร</span>
                            <div class="text-sm font-bold text-dark-blue mt-0.5">พิมพ์ข้อมูลสรุปและรายชื่อตามตัวกรองปัจจุบัน</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="print_summary.php?type=absents&level=<?= urlencode($selLevel) ?>&academic_year=<?= urlencode($selYear) ?>&semester=<?= urlencode($selSemester) ?>" target="_blank" class="btn btn-outline-danger btn-sm font-bold rounded px-3 py-2">
                                <i class="bi bi-person-x-fill me-1"></i> พิมพ์รายชื่อผู้ขาดประชุม (ปิดประกาศ)
                            </a>
                            <a href="print_summary.php?type=summary&level=<?= urlencode($selLevel) ?>&academic_year=<?= urlencode($selYear) ?>&semester=<?= urlencode($selSemester) ?>" target="_blank" class="btn btn-primary btn-sm font-bold rounded px-3 py-2 text-white">
                                <i class="bi bi-file-earmark-pdf-fill me-1"></i> พิมพ์รายงานสรุปภาพรวม
                            </a>
                        </div>
                    </div>
                    
                    <!-- สถิติแบบการ์ดภาพรวม -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="bg-primary-subtle text-primary rounded-3 p-3 border border-primary-subtle d-flex align-items-center">
                                <div class="fs-2 me-3"><i class="bi bi-door-open-fill"></i></div>
                                <div>
                                    <div class="text-xs text-muted font-bold uppercase">ห้องเรียนที่รายงาน</div>
                                    <div class="fs-4 font-black"><?= $totalClassroomsCount ?> ห้อง</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="bg-info-subtle text-info-emphasis rounded-3 p-3 border border-info-subtle d-flex align-items-center">
                                <div class="fs-2 me-3"><i class="bi bi-people-fill"></i></div>
                                <div>
                                    <div class="text-xs text-muted font-bold uppercase">จำนวนนักเรียนทั้งหมด</div>
                                    <div class="fs-4 font-black"><?= $sumStudents ?> คน</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="bg-success-subtle text-success rounded-3 p-3 border border-success-subtle d-flex align-items-center">
                                <div class="fs-2 me-3"><i class="bi bi-check-circle-fill"></i></div>
                                <div>
                                    <div class="text-xs text-muted font-bold uppercase">ผู้ปกครองที่เข้าร่วม</div>
                                    <div class="fs-4 font-black text-success"><?= $sumAttend ?> คน <span class="fs-6 font-bold text-muted">(<?= $overallAttendRate ?>%)</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="bg-danger-subtle text-danger rounded-3 p-3 border border-danger-subtle d-flex align-items-center">
                                <div class="fs-2 me-3"><i class="bi bi-x-circle-fill"></i></div>
                                <div>
                                    <div class="text-xs text-muted font-bold uppercase">ผู้ปกครองไม่มาประชุม</div>
                                    <div class="fs-4 font-black text-danger"><?= $sumAbsent ?> คน</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table id="overviewTable" class="table table-hover w-100 mb-0">
                            <thead>
                                <tr>
                                    <th width="15%">ห้องเรียน</th>
                                    <th width="15%">จำนวนนักเรียน</th>
                                    <th width="15%">ไม่มาประชุม</th>
                                    <th width="55%">รายชื่อนักเรียน / ผู้ปกครองที่ไม่มาเข้าร่วมประชุม</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $r): 
                                    $mId = $r['id'];
                                    $classroomName = 'ม.' . esc($r['level'] . '/' . $r['room_name']);
                                    $classroomTotal = esc($r['total_parents']) . ' คน';
                                    $classroomAbsentCount = (int)$r['absent_count'];
                                    $absentsList = $absentsByMeeting[$mId] ?? [];
                                ?>
                                    <tr>
                                        <td><span class="badge bg-primary text-white rounded px-2.5 py-1 font-bold"><?= $classroomName ?></span></td>
                                        <td><span class="font-bold text-dark"><?= $classroomTotal ?></span></td>
                                        <td>
                                            <?php if ($classroomAbsentCount > 0): ?>
                                                <span class="badge bg-danger text-white rounded-pill px-2.5 py-1 font-bold"><?= $classroomAbsentCount ?> คน</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 font-bold">0 คน (ครบ 100%)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($absentsList)): ?>
                                                <div class="d-flex flex-column gap-1">
                                                    <?php foreach ($absentsList as $index => $abs): ?>
                                                        <div class="p-2 bg-light rounded text-xs border border-slate-100">
                                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                                <span class="badge bg-secondary text-white font-bold">คนที่ <?= ($index + 1) ?></span>
                                                                <span class="font-bold text-dark"><i class="bi bi-person-fill text-muted me-1"></i>นักเรียน:</span> <?= esc($abs['student_name']) ?>
                                                                <span class="font-bold text-dark"><i class="bi bi-person-heart text-muted me-1"></i>ผู้ปกครอง:</span> <?= esc($abs['parent_name']) ?> (<?= esc($abs['relationship']) ?>)
                                                                <?php if (!empty($abs['phone'])): ?>
                                                                    <span class="font-bold text-dark"><i class="bi bi-telephone-fill text-muted me-1"></i>โทร:</span> <a href="tel:<?= esc($abs['phone']) ?>" class="text-decoration-none"><?= esc($abs['phone']) ?></a>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (!empty($abs['absent_reason'])): ?>
                                                                <div class="mt-1 text-danger">
                                                                    <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>เหตุผล:</strong> <?= esc($abs['absent_reason']) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($abs['follow_up_status'])): ?>
                                                                <div class="mt-1 text-info-emphasis">
                                                                    <strong><i class="bi bi-chat-dots-fill me-1"></i>ผลการติดตาม:</strong> <?= esc($abs['follow_up_status']) ?>
                                                                    <?php if (!empty($abs['follow_up_date'])): ?>
                                                                        (วันที่: <?= th_date($abs['follow_up_date']) ?>)
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted text-xs"><i class="bi bi-check-circle-fill text-success me-1"></i>มาครบทุกคน</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
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

    // โหลดตารางสรุปผู้ไม่มาประชุม
    $('#overviewTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25
    });

    // ปรับความกว้างคอลัมน์ของตารางเมื่อสลับแท็บ
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tabEl => {
        tabEl.addEventListener('shown.bs.tab', function() {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });
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
.card-header-tabs .nav-link {
    color: var(--text-muted);
    border-bottom: 3px solid transparent !important;
    transition: all 0.2s;
}
.card-header-tabs .nav-link:hover {
    color: var(--primary-color);
    background-color: rgba(13, 110, 253, 0.05);
}
.card-header-tabs .nav-link.active {
    color: var(--primary-color) !important;
    background: transparent !important;
    border-bottom: 3px solid var(--primary-color) !important;
}
</style>

<?php require_once __DIR__ . '/components/layout_end.php'; ?>
