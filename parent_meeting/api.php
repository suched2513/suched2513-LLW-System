<?php
/**
 * parent_meeting/api.php - Unified API Endpoint for AJAX actions
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

// Auth Guard - ต้องเข้าสู่ระบบก่อนใช้ API
if (!isset($_SESSION['pm_user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$role = $_SESSION['pm_role'];
$userId = $_SESSION['pm_user_id'];
$pdo = getPmPdo();

// รับข้อมูลจากการยิงแบบ JSON payload
$input = json_decode(file_get_contents('php://input'), true);

// ตรวจสอบหา Action
$action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่ระบุการดำเนินการ (Action)']);
    exit;
}

try {
    switch ($action) {
        // ==========================================
        // MEETINGS ACTIONS
        // ==========================================
        case 'get_meeting':
            $meetingId = (int)($_GET['id'] ?? 0);
            if ($meetingId <= 0) {
                throw new Exception('ไม่ระบุ ID รายงานการประชุม');
            }

            // ดึงข้อมูลการประชุม
            $stmt = $pdo->prepare("
                SELECT m.*, c.level, c.room_name, c.teacher_name, u.fullname as creator_name
                FROM pm_meetings m
                JOIN pm_classrooms c ON m.classroom_id = c.id
                JOIN pm_users u ON m.created_by = u.id
                WHERE m.id = ?
            ");
            $stmt->execute([$meetingId]);
            $meeting = $stmt->fetch();

            if (!$meeting) {
                throw new Exception('ไม่พบข้อมูลรายงานการประชุม');
            }

            // เพิ่มข้อมูลฟอร์แมตวันที่แบบไทย
            $meeting['meeting_date_formatted'] = th_date($meeting['meeting_date']);

            // ดึงรูปภาพบรรยากาศการประชุม
            $imgStmt = $pdo->prepare("SELECT id, image_path FROM pm_meeting_images WHERE meeting_id = ?");
            $imgStmt->execute([$meetingId]);
            $images = $imgStmt->fetchAll();

            // ปรับแต่ง path ให้พร้อมใช้งานใน frontend
            foreach ($images as &$img) {
                $img['image_path'] = pm_url($img['image_path']);
            }

            // ดึงผู้เข้าร่วมประชุม (ลลว.๐๒)
            $attStmt = $pdo->prepare("SELECT * FROM pm_meeting_attendants WHERE meeting_id = ? ORDER BY id ASC");
            $attStmt->execute([$meetingId]);
            $attendants = $attStmt->fetchAll();

            // ดึงผู้ขาดประชุม (ลลว.๐๓)
            $absStmt = $pdo->prepare("SELECT * FROM pm_meeting_absents WHERE meeting_id = ? ORDER BY id ASC");
            $absStmt->execute([$meetingId]);
            $absents = $absStmt->fetchAll();

            // ดึงข้อมูลประสานสัมพันธ์ (ลลว.๐๔)
            $relStmt = $pdo->prepare("SELECT * FROM pm_student_relations WHERE meeting_id = ? ORDER BY id ASC");
            $relStmt->execute([$meetingId]);
            $relations = $relStmt->fetchAll();
            foreach ($relations as &$rel) {
                $rel['praise_teacher_json'] = json_decode($rel['praise_teacher_json'] ?? '[]', true);
                $rel['praise_parent_json'] = json_decode($rel['praise_parent_json'] ?? '[]', true);
                $rel['improve_teacher_json'] = json_decode($rel['improve_teacher_json'] ?? '[]', true);
                $rel['improve_parent_json'] = json_decode($rel['improve_parent_json'] ?? '[]', true);
            }

            // ดึงข้อมูลกลุ่ม (ลลว.๐๕)
            $grpStmt = $pdo->prepare("SELECT * FROM pm_meet_greet_groups WHERE meeting_id = ? ORDER BY id ASC");
            $grpStmt->execute([$meetingId]);
            $groups = $grpStmt->fetchAll();
            foreach ($groups as &$grp) {
                $grp['attendants_json'] = json_decode($grp['attendants_json'] ?? '[]', true);
            }

            // ดึงความในใจของลูก (ลลว.๐๖)
            $letStmt = $pdo->prepare("SELECT * FROM pm_student_letters WHERE meeting_id = ? ORDER BY id ASC");
            $letStmt->execute([$meetingId]);
            $letters = $letStmt->fetchAll();

            echo json_encode([
                'status' => 'success',
                'data' => $meeting,
                'images' => $images,
                'attendants' => $attendants,
                'absents' => $absents,
                'relations' => $relations,
                'groups' => $groups,
                'letters' => $letters
            ]);
            break;

        case 'save_meeting':
            // สิทธิ์: เฉพาะครูและแอดมินเท่านั้น
            if (!in_array($role, ['teacher', 'admin'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์บันทึกรายงานการประชุม']);
                exit;
            }

            $meetingId = (int)($_POST['meeting_id'] ?? 0);
            $meetingDate = $_POST['meeting_date'] ?? '';
            $semester = $_POST['semester'] ?? '';
            $academicYear = (int)($_POST['academic_year'] ?? 0);
            $classroomId = (int)($_POST['classroom_id'] ?? 0);
            $totalStudents = (int)($_POST['total_students'] ?? 0);
            $totalParents = (int)($_POST['total_parents'] ?? 0);
            $attendCount = (int)($_POST['attend_count'] ?? 0);
            $absentCount = (int)($_POST['absent_count'] ?? 0);
            $summary = $_POST['summary'] ?? '';
            $problems = $_POST['problems'] ?? '';
            $suggestions = $_POST['suggestions'] ?? '';

            // คอลัมน์เพิ่มเติมของ ลลว.๐๑
            $docNo = $_POST['doc_no'] ?? '';
            $docDate = !empty($_POST['doc_date']) ? $_POST['doc_date'] : null;
            $commandNo = $_POST['command_no'] ?? '';
            $commandDate = !empty($_POST['command_date']) ? $_POST['command_date'] : null;
            $agenda1 = $_POST['agenda_1'] ?? '';
            $agenda2 = $_POST['agenda_2'] ?? '';
            $agenda3 = $_POST['agenda_3'] ?? '';
            $consensus = $_POST['consensus'] ?? '';
            $cooperationRating = $_POST['cooperation_rating'] ?? '';
            $usefulSuggestions = $_POST['useful_suggestions'] ?? '';
            $supportReceived = $_POST['support_received'] ?? '';
            $otherObservations = $_POST['other_observations'] ?? '';

            // ตรวจสอบพารามิเตอร์บันทึกเฉพาะแท็บ (tab1 - tab6 หรือ all)
            $saveOnly = $_POST['save_only'] ?? 'all';

            if ($saveOnly === 'all' || $saveOnly === 'tab1') {
                if (empty($meetingDate) || empty($semester) || $academicYear <= 0 || $classroomId <= 0 || $totalParents < 0 || $attendCount < 0) {
                    throw new Exception('กรุณากรอกข้อมูลที่จำเป็นใน ลลว.๐๑ ให้ครบถ้วน');
                }

                if ($attendCount > $totalParents) {
                    throw new Exception('จำนวนผู้เข้าร่วมประชุม ห้ามมากกว่าจำนวนผู้ปกครองทั้งหมด');
                }

                // คำนวณจำนวนผู้ขาดที่ถูกต้อง
                $absentCount = max(0, $totalParents - $attendCount);
            } else {
                // หากจะบันทึกแท็บย่อยอื่นๆ ต้องมี meeting_id หลักก่อน
                if ($meetingId <= 0) {
                    throw new Exception('ไม่พบรหัสรายงานการประชุมหลัก กรุณาบันทึก ลลว.๐๑ ก่อน');
                }
            }

            $pdo->beginTransaction();

            // ตรวจสอบสิทธิ์ความเป็นเจ้าของรายงานการประชุม (สำหรับครู)
            if ($meetingId > 0) {
                if ($role === 'teacher') {
                    $checkStmt = $pdo->prepare("SELECT created_by FROM pm_meetings WHERE id = ?");
                    $checkStmt->execute([$meetingId]);
                    $owner = $checkStmt->fetchColumn();
                    if ($owner != $userId) {
                        throw new Exception('คุณไม่มีสิทธิ์แก้ไขรายงานการประชุมห้องเรียนนี้');
                    }
                }
            }

            // 1. บันทึก/แก้ไขข้อมูล ลลว.๐๑ (ตาราง pm_meetingsหลัก)
            if ($saveOnly === 'all' || $saveOnly === 'tab1') {
                if ($meetingId > 0) {
                    // โหมดแก้ไข
                    $stmt = $pdo->prepare("
                        UPDATE pm_meetings 
                        SET meeting_date = ?, semester = ?, academic_year = ?, classroom_id = ?, 
                            total_students = ?, total_parents = ?, attend_count = ?, absent_count = ?, 
                            summary = ?, problems = ?, suggestions = ?,
                            doc_no = ?, doc_date = ?, command_no = ?, command_date = ?,
                            agenda_1 = ?, agenda_2 = ?, agenda_3 = ?, consensus = ?,
                            cooperation_rating = ?, useful_suggestions = ?, support_received = ?, other_observations = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $meetingDate, $semester, $academicYear, $classroomId,
                        $totalStudents, $totalParents, $attendCount, $absentCount,
                        $summary, $problems, $suggestions,
                        $docNo, $docDate, $commandNo, $commandDate,
                        $agenda1, $agenda2, $agenda3, $consensus,
                        $cooperationRating, $usefulSuggestions, $supportReceived, $otherObservations,
                        $meetingId
                    ]);
                } else {
                    // โหมดเพิ่มใหม่
                    $stmt = $pdo->prepare("
                        INSERT INTO pm_meetings (
                            meeting_date, semester, academic_year, classroom_id, total_students, total_parents, attend_count, absent_count, 
                            summary, problems, suggestions, created_by,
                            doc_no, doc_date, command_no, command_date,
                            agenda_1, agenda_2, agenda_3, consensus,
                            cooperation_rating, useful_suggestions, support_received, other_observations
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $meetingDate, $semester, $academicYear, $classroomId, $totalStudents, $totalParents, $attendCount, $absentCount,
                        $summary, $problems, $suggestions, $userId,
                        $docNo, $docDate, $commandNo, $commandDate,
                        $agenda1, $agenda2, $agenda3, $consensus,
                        $cooperationRating, $usefulSuggestions, $supportReceived, $otherObservations
                    ]);
                    $meetingId = $pdo->lastInsertId();
                }

                // อัปโหลดไฟล์รูปภาพกิจกรรม (ถ้ามี) - อยู่ในหน้า ลลว.๐๑
                if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    $files = $_FILES['images'];
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                    $uploadDir = __DIR__ . '/uploads/';

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                            continue;
                        }

                        $fileName = $files['name'][$i];
                        $fileSize = $files['size'][$i];
                        $tmpName = $files['tmp_name'][$i];
                        
                        if ($fileSize > 5 * 1024 * 1024) {
                            throw new Exception("ขนาดไฟล์รูปภาพเกิน 5MB: $fileName");
                        }

                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowedExtensions)) {
                            throw new Exception("ประเภทไฟล์ไม่รองรับ (.jpg, .jpeg, .png, .webp เท่านั้น): $fileName");
                        }

                        $newFileName = uniqid('meet_') . '_' . time() . '_' . $i . '.' . $ext;
                        $destPath = $uploadDir . $newFileName;

                        if (move_uploaded_file($tmpName, $destPath)) {
                            $imgStmt = $pdo->prepare("INSERT INTO pm_meeting_images (meeting_id, image_path) VALUES (?, ?)");
                            $imgStmt->execute([$meetingId, 'uploads/' . $newFileName]);
                        } else {
                            throw new Exception("อัปโหลดไฟล์ไม่สำเร็จ: $fileName");
                        }
                    }
                }
            }

            // 2. ลลว.๐๒ (ผู้เข้าร่วม)
            if ($saveOnly === 'all' || $saveOnly === 'tab2') {
                $attendants = json_decode($_POST['attendants_data'] ?? '[]', true);
                $pdo->prepare("DELETE FROM pm_meeting_attendants WHERE meeting_id = ?")->execute([$meetingId]);
                $insAtt = $pdo->prepare("INSERT INTO pm_meeting_attendants (meeting_id, student_name, parent_name, phone, relationship) VALUES (?, ?, ?, ?, ?)");
                foreach ($attendants as $att) {
                    if (!empty(trim($att['student_name']))) {
                        $insAtt->execute([
                            $meetingId,
                            trim($att['student_name']),
                            trim($att['parent_name'] ?? ''),
                            trim($att['phone'] ?? ''),
                            trim($att['relationship'] ?? '')
                        ]);
                    }
                }
            }

            // 3. ลลว.๐๓ (ผู้ขาดประชุม)
            if ($saveOnly === 'all' || $saveOnly === 'tab3') {
                $absents = json_decode($_POST['absents_data'] ?? '[]', true);
                $pdo->prepare("DELETE FROM pm_meeting_absents WHERE meeting_id = ?")->execute([$meetingId]);
                $insAbs = $pdo->prepare("INSERT INTO pm_meeting_absents (meeting_id, student_name, parent_name, phone, relationship, absent_reason, follow_up_status, follow_up_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($absents as $abs) {
                    if (!empty(trim($abs['student_name']))) {
                        $insAbs->execute([
                            $meetingId,
                            trim($abs['student_name']),
                            trim($abs['parent_name'] ?? ''),
                            trim($abs['phone'] ?? ''),
                            trim($abs['relationship'] ?? ''),
                            trim($abs['absent_reason'] ?? ''),
                            trim($abs['follow_up_status'] ?? ''),
                            !empty($abs['follow_up_date']) ? $abs['follow_up_date'] : null
                        ]);
                    }
                }
            }

            // 4. ลลว.๐๔ (ประสานสัมพันธ์)
            if ($saveOnly === 'all' || $saveOnly === 'tab4') {
                $relations = json_decode($_POST['relations_data'] ?? '[]', true);
                $pdo->prepare("DELETE FROM pm_student_relations WHERE meeting_id = ?")->execute([$meetingId]);
                $insRel = $pdo->prepare("
                    INSERT INTO pm_student_relations (
                        meeting_id, student_name, classroom_no, student_no, parent_name, relationship,
                        grade_zero_count, grade_r_count, grade_ms_count, grade_mp_count, behavior_score_deducted,
                        praise_teacher_json, praise_teacher_other, praise_parent_json, praise_parent_other,
                        improve_teacher_json, improve_teacher_other, improve_parent_json, improve_parent_other,
                        teacher_remedy, parent_remedy, parent_support_request, parent_meeting_impression, parent_teacher_feedback
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($relations as $rel) {
                    if (!empty(trim($rel['student_name']))) {
                        $insRel->execute([
                            $meetingId,
                            trim($rel['student_name']),
                            trim($rel['classroom_no'] ?? ''),
                            (int)($rel['student_no'] ?? 0),
                            trim($rel['parent_name'] ?? ''),
                            trim($rel['relationship'] ?? ''),
                            (int)($rel['grade_zero_count'] ?? 0),
                            (int)($rel['grade_r_count'] ?? 0),
                            (int)($rel['grade_ms_count'] ?? 0),
                            (int)($rel['grade_mp_count'] ?? 0),
                            (int)($rel['behavior_score_deducted'] ?? 0),
                            json_encode($rel['praise_teacher_json'] ?? []),
                            trim($rel['praise_teacher_other'] ?? ''),
                            json_encode($rel['praise_parent_json'] ?? []),
                            trim($rel['praise_parent_other'] ?? ''),
                            json_encode($rel['improve_teacher_json'] ?? []),
                            trim($rel['improve_teacher_other'] ?? ''),
                            json_encode($rel['improve_parent_json'] ?? []),
                            trim($rel['improve_parent_other'] ?? ''),
                            trim($rel['teacher_remedy'] ?? ''),
                            trim($rel['parent_remedy'] ?? ''),
                            trim($rel['parent_support_request'] ?? ''),
                            trim($rel['parent_meeting_impression'] ?? ''),
                            trim($rel['parent_teacher_feedback'] ?? '')
                        ]);
                    }
                }
            }

            // 5. ลลว.๐๕ (Meet & Greet)
            if ($saveOnly === 'all' || $saveOnly === 'tab5') {
                $groups = json_decode($_POST['groups_data'] ?? '[]', true);
                $pdo->prepare("DELETE FROM pm_meet_greet_groups WHERE meeting_id = ?")->execute([$meetingId]);
                $insGrp = $pdo->prepare("INSERT INTO pm_meet_greet_groups (meeting_id, group_topic, attendants_json, discussion_summary, discussion_resolution, school_support_request) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($groups as $grp) {
                    if (!empty(trim($grp['group_topic']))) {
                        $insGrp->execute([
                            $meetingId,
                            trim($grp['group_topic']),
                            json_encode($grp['attendants_json'] ?? []),
                            trim($grp['discussion_summary'] ?? ''),
                            trim($grp['discussion_resolution'] ?? ''),
                            trim($grp['school_support_request'] ?? '')
                        ]);
                    }
                }
            }

            // 6. ลลว.๐๖ (ความในใจลูก)
            if ($saveOnly === 'all' || $saveOnly === 'tab6') {
                $letters = json_decode($_POST['letters_data'] ?? '[]', true);
                $pdo->prepare("DELETE FROM pm_student_letters WHERE meeting_id = ?")->execute([$meetingId]);
                $insLet = $pdo->prepare("INSERT INTO pm_student_letters (meeting_id, student_name, classroom_no, student_no, letter_to_whom, impressed_story, inner_feelings, proud_story, improvement_plan, parent_response) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($letters as $let) {
                    if (!empty(trim($let['student_name']))) {
                        $insLet->execute([
                            $meetingId,
                            trim($let['student_name']),
                            trim($let['classroom_no'] ?? ''),
                            (int)($let['student_no'] ?? 0),
                            trim($let['letter_to_whom'] ?? ''),
                            trim($let['impressed_story'] ?? ''),
                            trim($let['inner_feelings'] ?? ''),
                            trim($let['proud_story'] ?? ''),
                            trim($let['improvement_plan'] ?? ''),
                            trim($let['parent_response'] ?? '')
                        ]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode([
                'status' => 'success',
                'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว',
                'meeting_id' => $meetingId
            ]);
            break;

        case 'delete_image':
            // สิทธิ์: เฉพาะครูและแอดมินเท่านั้น
            if (!in_array($role, ['teacher', 'admin'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ลบรูปกิจกรรม']);
                exit;
            }

            $imageId = (int)($input['image_id'] ?? 0);
            if ($imageId <= 0) {
                throw new Exception('ไม่ระบุ ID รูปภาพ');
            }

            // ตรวจสอบสิทธิ์เจ้าของรูปภาพ (สำหรับสิทธิ์ครู)
            if ($role === 'teacher') {
                $checkStmt = $pdo->prepare("
                    SELECT m.created_by 
                    FROM pm_meeting_images mi
                    JOIN pm_meetings m ON mi.meeting_id = m.id
                    WHERE mi.id = ?
                ");
                $checkStmt->execute([$imageId]);
                $owner = $checkStmt->fetchColumn();
                if ($owner != $userId) {
                    throw new Exception('คุณไม่มีสิทธิ์ลบรูปภาพกิจกรรมของรายงานฉบับนี้');
                }
            }

            // ดึงพาธรูปภาพมาเพื่อทำการลบจากเซิร์ฟเวอร์
            $stmt = $pdo->prepare("SELECT image_path FROM pm_meeting_images WHERE id = ?");
            $stmt->execute([$imageId]);
            $imgPath = $stmt->fetchColumn();

            if ($imgPath) {
                $fullPath = __DIR__ . '/' . $imgPath;
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            // ลบจากฐานข้อมูล
            $deleteStmt = $pdo->prepare("DELETE FROM pm_meeting_images WHERE id = ?");
            $deleteStmt->execute([$imageId]);

            echo json_encode(['status' => 'success', 'message' => 'ลบรูปภาพเรียบร้อยแล้ว']);
            break;

        case 'delete_meeting':
            // สิทธิ์: เฉพาะครูและแอดมินเท่านั้น
            if (!in_array($role, ['teacher', 'admin'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ลบรายงานการประชุม']);
                exit;
            }

            $meetingId = (int)($input['meeting_id'] ?? 0);
            if ($meetingId <= 0) {
                throw new Exception('ไม่ระบุ ID รายงานการประชุม');
            }

            // ตรวจสอบสิทธิ์เจ้าของรายงาน (สำหรับสิทธิ์ครู)
            if ($role === 'teacher') {
                $checkStmt = $pdo->prepare("SELECT created_by FROM pm_meetings WHERE id = ?");
                $checkStmt->execute([$meetingId]);
                $owner = $checkStmt->fetchColumn();
                if ($owner != $userId) {
                    throw new Exception('คุณไม่มีสิทธิ์ลบรายงานการประชุมห้องเรียนนี้');
                }
            }

            $pdo->beginTransaction();

            // ดึงและลบรูปกิจกรรมทั้งหมดจากเซิร์ฟเวอร์
            $imgStmt = $pdo->prepare("SELECT image_path FROM pm_meeting_images WHERE meeting_id = ?");
            $imgStmt->execute([$meetingId]);
            $images = $imgStmt->fetchAll();
            foreach ($images as $img) {
                $fullPath = __DIR__ . '/' . $img['image_path'];
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            // ดึงและลบรูปเครือข่ายผู้ปกครองทั้งหมดของรายงานนี้จากเซิร์ฟเวอร์
            $netStmt = $pdo->prepare("SELECT image_path FROM pm_network_parents WHERE meeting_id = ?");
            $netStmt->execute([$meetingId]);
            $networkImages = $netStmt->fetchAll();
            foreach ($networkImages as $nimg) {
                if ($nimg['image_path']) {
                    $fullPath = __DIR__ . '/' . $nimg['image_path'];
                    if (file_exists($fullPath) && is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }
            }

            // ลบรายงานการประชุม (Cascade ลบตารางอื่นอัตโนมัติ)
            $deleteStmt = $pdo->prepare("DELETE FROM pm_meetings WHERE id = ?");
            $deleteStmt->execute([$meetingId]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'ลบรายงานการประชุมและรูปภาพแนบทั้งหมดเรียบร้อยแล้ว']);
            break;

        // ==========================================
        // PARENT NETWORK ACTIONS
        // ==========================================
        case 'get_network':
            $networkId = (int)($_GET['id'] ?? 0);
            if ($networkId <= 0) {
                throw new Exception('ไม่ระบุ ID สมาชิกเครือข่ายผู้ปกครอง');
            }

            $stmt = $pdo->prepare("SELECT * FROM pm_network_parents WHERE id = ?");
            $stmt->execute([$networkId]);
            $network = $stmt->fetch();

            if (!$network) {
                throw new Exception('ไม่พบข้อมูลสมาชิกเครือข่ายผู้ปกครอง');
            }

            // แปลงพาธรูปให้ถูกต้องเพื่อการแสดงผลพรีวิว
            if ($network['image_path']) {
                $network['image_path'] = pm_url($network['image_path']);
            }

            echo json_encode([
                'status' => 'success',
                'data' => $network
            ]);
            break;

        case 'save_network':
            // สิทธิ์: เฉพาะครูและแอดมินเท่านั้น
            if (!in_array($role, ['teacher', 'admin'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์บันทึกข้อมูลเครือข่ายผู้ปกครอง']);
                exit;
            }

            $networkId = (int)($_POST['network_id'] ?? 0);
            $meetingId = (int)($_POST['meeting_id'] ?? 0);
            $positionName = $_POST['position_name'] ?? '';
            $parentName = $_POST['parent_name'] ?? '';
            $studentName = $_POST['student_name'] ?? '';
            $studentClass = $_POST['student_class'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $address = $_POST['address'] ?? '';

            if ($meetingId <= 0 || empty($positionName) || empty($parentName) || empty($studentName) || empty($studentClass) || empty($phone) || empty($address)) {
                throw new Exception('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
            }

            // ตรวจสอบสิทธิ์ความเป็นเจ้าของ meeting ของห้องเรียนนั้น (สำหรับครู)
            if ($role === 'teacher') {
                $checkStmt = $pdo->prepare("SELECT created_by FROM pm_meetings WHERE id = ?");
                $checkStmt->execute([$meetingId]);
                $owner = $checkStmt->fetchColumn();
                if ($owner != $userId) {
                    throw new Exception('คุณไม่มีสิทธิ์จัดการข้อมูลในรายงานการประชุมฉบับนี้');
                }
            }

            $pdo->beginTransaction();

            // เช็คว่าสำหรับโหมดเพิ่มข้อมูลใหม่ (networkId = 0)
            if ($networkId == 0) {
                // ตรวจสอบโควตาสำหรับตำแหน่งที่ไม่ใช่ กรรมการ (ประธาน, รองประธาน, เลขานุการ มีได้แค่ 1 ต่อ 1 รายงาน)
                if ($positionName !== 'กรรมการ') {
                    $checkPosStmt = $pdo->prepare("SELECT id FROM pm_network_parents WHERE meeting_id = ? AND position_name = ?");
                    $checkPosStmt->execute([$meetingId, $positionName]);
                    $existingId = $checkPosStmt->fetchColumn();
                    
                    if ($existingId) {
                        // หากมีอยู่แล้ว ให้เปลี่ยนโหมดเป็น Update อัตโนมัติ เพื่อความลื่นไหล
                        $networkId = (int)$existingId;
                    }
                } else {
                    // ตำแหน่ง กรรมการ มีได้สูงสุด 2 คน
                    $checkPosStmt = $pdo->prepare("SELECT COUNT(*) FROM pm_network_parents WHERE meeting_id = ? AND position_name = 'กรรมการ'");
                    $checkPosStmt->execute([$meetingId]);
                    $countKom = (int)$checkPosStmt->fetchColumn();
                    if ($countKom >= 2) {
                        throw new Exception('ตำแหน่งกรรมการเครือข่ายผู้ปกครองในห้องเรียนนี้เต็มแล้ว (มีได้สูงสุด 2 ท่าน)');
                    }
                }
            }

            $imagePath = null;
            if ($networkId > 0) {
                // ดึงภาพเดิมไว้เผื่อกรณีไม่ได้แก้ไขรูปภาพ
                $oldImgStmt = $pdo->prepare("SELECT image_path FROM pm_network_parents WHERE id = ?");
                $oldImgStmt->execute([$networkId]);
                $imagePath = $oldImgStmt->fetchColumn();
            }

            // ประมวลผลรูปภาพ (ถ้ามีการอัปโหลดรูปภาพใหม่)
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $uploadDir = __DIR__ . '/uploads/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName = $file['name'];
                $fileSize = $file['size'];
                $tmpName = $file['tmp_name'];

                // เช็คขนาด (ไม่เกิน 2MB)
                if ($fileSize > 2 * 1024 * 1024) {
                    throw new Exception("ขนาดไฟล์รูปภาพห้ามเกิน 2MB: $fileName");
                }

                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions)) {
                    throw new Exception("ประเภทไฟล์ไม่รองรับ (.jpg, .jpeg, .png, .webp เท่านั้น): $fileName");
                }

                // สุ่มชื่อไฟล์ใหม่
                $newFileName = uniqid('parent_') . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($tmpName, $destPath)) {
                    // หากมีรูปเดิมอยู่ให้ลบรูปเดิมออกด้วย
                    if ($imagePath) {
                        $oldFullPath = __DIR__ . '/' . $imagePath;
                        if (file_exists($oldFullPath) && is_file($oldFullPath)) {
                            @unlink($oldFullPath);
                        }
                    }
                    $imagePath = 'uploads/' . $newFileName;
                } else {
                    throw new Exception("การอัปโหลดไฟล์รูปภาพล้มเหลว");
                }
            }

            if ($networkId > 0) {
                // อัปเดตข้อมูล
                $stmt = $pdo->prepare("
                    UPDATE pm_network_parents 
                    SET parent_name = ?, student_name = ?, student_class = ?, phone = ?, address = ?, image_path = ?
                    WHERE id = ?
                ");
                $stmt->execute([$parentName, $studentName, $studentClass, $phone, $address, $imagePath, $networkId]);
            } else {
                // เพิ่มข้อมูลใหม่
                $stmt = $pdo->prepare("
                    INSERT INTO pm_network_parents (meeting_id, position_name, parent_name, student_name, student_class, phone, address, image_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$meetingId, $positionName, $parentName, $studentName, $studentClass, $phone, $address, $imagePath]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลสมาชิกเครือข่ายผู้ปกครองสำเร็จ']);
            break;

        case 'delete_network':
            // สิทธิ์: เฉพาะครูและแอดมินเท่านั้น
            if (!in_array($role, ['teacher', 'admin'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ลบข้อมูลเครือข่ายผู้ปกครอง']);
                exit;
            }

            $networkId = (int)($input['network_id'] ?? 0);
            if ($networkId <= 0) {
                throw new Exception('ไม่ระบุ ID สมาชิกเครือข่ายผู้ปกครอง');
            }

            // ตรวจสอบสิทธิ์เจ้าของ meeting (สำหรับสิทธิ์ครู)
            if ($role === 'teacher') {
                $checkStmt = $pdo->prepare("
                    SELECT m.created_by 
                    FROM pm_network_parents np
                    JOIN pm_meetings m ON np.meeting_id = m.id
                    WHERE np.id = ?
                ");
                $checkStmt->execute([$networkId]);
                $owner = $checkStmt->fetchColumn();
                if ($owner != $userId) {
                    throw new Exception('คุณไม่มีสิทธิ์ลบข้อมูลเครือข่ายของชั้นเรียนนี้');
                }
            }

            $stmt = $pdo->prepare("SELECT image_path FROM pm_network_parents WHERE id = ?");
            $stmt->execute([$networkId]);
            $imgPath = $stmt->fetchColumn();

            if ($imgPath) {
                $fullPath = __DIR__ . '/' . $imgPath;
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            $deleteStmt = $pdo->prepare("DELETE FROM pm_network_parents WHERE id = ?");
            $deleteStmt->execute([$networkId]);

            echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลสมาชิกเครือข่ายผู้ปกครองเรียบร้อยแล้ว']);
            break;

        // ==========================================
        // EXECUTIVE COMMENTS ACTIONS
        // ==========================================
        case 'get_comments':
            $meetingId = (int)($_GET['meeting_id'] ?? 0);
            if ($meetingId <= 0) {
                throw new Exception('ไม่ระบุ ID รายงานการประชุม');
            }

            $stmt = $pdo->prepare("
                SELECT c.*, u.fullname as commenter_name 
                FROM pm_comments c
                JOIN pm_users u ON c.commented_by = u.id
                WHERE c.meeting_id = ?
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$meetingId]);
            $comments = $stmt->fetchAll();

            foreach ($comments as &$c) {
                $c['created_at_formatted'] = th_date($c['created_at']) . ' ' . date('H:i', strtotime($c['created_at'])) . ' น.';
            }

            echo json_encode([
                'status' => 'success',
                'data' => $comments
            ]);
            break;

        case 'save_comment':
            // สิทธิ์: ผู้บริหารและแอดมินเท่านั้น
            if (!in_array($role, ['executive', 'admin'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ลงความเห็นประเมินรายงาน']);
                exit;
            }

            $meetingId = (int)($_POST['meeting_id'] ?? $input['meeting_id'] ?? 0);
            $commentText = $_POST['comment_text'] ?? $input['comment_text'] ?? '';

            if ($meetingId <= 0 || empty(trim($commentText))) {
                throw new Exception('กรุณากรอกข้อเสนอแนะความเห็นผู้บริหาร');
            }

            $stmt = $pdo->prepare("INSERT INTO pm_comments (meeting_id, comment_text, commented_by) VALUES (?, ?, ?)");
            $stmt->execute([$meetingId, trim($commentText), $userId]);

            echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อเสนอแนะความเห็นเรียบร้อยแล้ว']);
            break;

        // ==========================================
        // USER MANAGEMENT ACTIONS (ADMIN ONLY)
        // ==========================================
        case 'get_user':
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'เฉพาะแอดมินเท่านั้นที่เข้าถึงได้']);
                exit;
            }

            $tgtUserId = (int)($_GET['id'] ?? 0);
            if ($tgtUserId <= 0) {
                throw new Exception('ไม่ระบุ ID ผู้ใช้');
            }

            $stmt = $pdo->prepare("SELECT id, fullname, username, role FROM pm_users WHERE id = ?");
            $stmt->execute([$tgtUserId]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                throw new Exception('ไม่พบข้อมูลผู้ใช้งาน');
            }

            echo json_encode([
                'status' => 'success',
                'data' => $targetUser
            ]);
            break;

        case 'save_user':
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'เฉพาะแอดมินเท่านั้นที่เข้าถึงได้']);
                exit;
            }

            $tgtUserId = (int)($_POST['user_id'] ?? $input['user_id'] ?? 0);
            $fullname = trim($_POST['fullname'] ?? $input['fullname'] ?? '');
            $username = trim($_POST['username'] ?? $input['username'] ?? '');
            $password = $_POST['password'] ?? $input['password'] ?? '';
            $userRole = $_POST['role'] ?? $input['role'] ?? '';

            if (empty($fullname) || empty($username) || empty($userRole)) {
                throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
            }

            if ($tgtUserId == 0 && empty($password)) {
                throw new Exception('กรุณากำหนดรหัสผ่านสำหรับผู้ใช้งานใหม่');
            }

            $pdo->beginTransaction();

            if ($tgtUserId > 0) {
                // ตรวจสอบชื่อผู้ซ้ำ (ยกเว้นไอดีตนเอง)
                $chk = $pdo->prepare("SELECT id FROM pm_users WHERE username = ? AND id != ?");
                $chk->execute([$username, $tgtUserId]);
                if ($chk->fetch()) {
                    throw new Exception('ชื่อผู้ใช้งาน (Username) นี้มีผู้ใช้รายอื่นในระบบแล้ว');
                }

                if (!empty($password)) {
                    // อัปเดตพร้อมรหัสผ่านใหม่
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE pm_users SET fullname = ?, username = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$fullname, $username, $userRole, $hash, $tgtUserId]);
                } else {
                    // อัปเดตโดยไม่เปลี่ยนรหัสผ่าน
                    $stmt = $pdo->prepare("UPDATE pm_users SET fullname = ?, username = ?, role = ? WHERE id = ?");
                    $stmt->execute([$fullname, $username, $userRole, $tgtUserId]);
                }
            } else {
                // เพิ่มผู้ใช้ใหม่
                $chk = $pdo->prepare("SELECT id FROM pm_users WHERE username = ?");
                $chk->execute([$username]);
                if ($chk->fetch()) {
                    throw new Exception('ชื่อผู้ใช้งาน (Username) นี้มีผู้ใช้ในระบบแล้ว');
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO pm_users (fullname, username, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$fullname, $username, $hash, $userRole]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลผู้ใช้งานเรียบร้อยแล้ว']);
            break;

        case 'delete_user':
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'เฉพาะแอดมินเท่านั้นที่เข้าถึงได้']);
                exit;
            }

            $tgtUserId = (int)($input['user_id'] ?? 0);
            if ($tgtUserId <= 0) {
                throw new Exception('ไม่ระบุ ID ผู้ใช้งาน');
            }

            // ห้ามลบตัวเอง
            if ($tgtUserId == $userId) {
                throw new Exception('คุณไม่สามารถลบบัญชีของตัวเองที่กำลังใช้งานได้');
            }

            $stmt = $pdo->prepare("DELETE FROM pm_users WHERE id = ?");
            $stmt->execute([$tgtUserId]);

            echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลผู้ใช้งานเรียบร้อยแล้ว']);
            break;

        // ==========================================
        // CLASSROOM SETTINGS ACTIONS (ADMIN ONLY)
        // ==========================================
        case 'get_classroom':
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'เฉพาะแอดมินเท่านั้นที่เข้าถึงได้']);
                exit;
            }

            $classroomId = (int)($_GET['id'] ?? 0);
            if ($classroomId <= 0) {
                throw new Exception('ไม่ระบุ ID ห้องเรียน');
            }

            $stmt = $pdo->prepare("SELECT * FROM pm_classrooms WHERE id = ?");
            $stmt->execute([$classroomId]);
            $classroom = $stmt->fetch();

            if (!$classroom) {
                throw new Exception('ไม่พบข้อมูลห้องเรียน');
            }

            echo json_encode([
                'status' => 'success',
                'data' => $classroom
            ]);
            break;

        case 'save_classroom':
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'เฉพาะแอดมินเท่านั้นที่เข้าถึงได้']);
                exit;
            }

            $classroomId = (int)($_POST['classroom_id'] ?? $input['classroom_id'] ?? 0);
            $level = trim($_POST['level'] ?? $input['level'] ?? '');
            $roomName = trim($_POST['room_name'] ?? $input['room_name'] ?? '');
            $teacherName = trim($_POST['teacher_name'] ?? $input['teacher_name'] ?? '');

            if (empty($level) || empty($roomName) || empty($teacherName)) {
                throw new Exception('กรุณากรอกข้อมูลระดับชั้น ห้องเรียน และครูที่ปรึกษาให้ครบถ้วน');
            }

            if ($classroomId > 0) {
                $stmt = $pdo->prepare("UPDATE pm_classrooms SET level = ?, room_name = ?, teacher_name = ? WHERE id = ?");
                $stmt->execute([$level, $roomName, $teacherName, $classroomId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO pm_classrooms (level, room_name, teacher_name) VALUES (?, ?, ?)");
                $stmt->execute([$level, $roomName, $teacherName]);
            }

            echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลห้องเรียนเรียบร้อยแล้ว']);
            break;

        case 'delete_classroom':
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'เฉพาะแอดมินเท่านั้นที่เข้าถึงได้']);
                exit;
            }

            $classroomId = (int)($input['classroom_id'] ?? 0);
            if ($classroomId <= 0) {
                throw new Exception('ไม่ระบุ ID ห้องเรียน');
            }

            $stmt = $pdo->prepare("DELETE FROM pm_classrooms WHERE id = ?");
            $stmt->execute([$classroomId]);

            echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลห้องเรียนเรียบร้อยแล้ว']);
            break;

        // ==========================================
        // CENTRAL LLW DATA SYNC ACTIONS (ADMIN ONLY)
        // ==========================================

        case 'get_llw_teachers':
            // ดึงรายชื่อครูที่ปรึกษาจากตาราง att_teachers ของระบบกลาง
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'เฉพาะแอดมินเท่านั้นที่เข้าถึงได้']);
                exit;
            }

            $llwPdo = getLlwPdo();
            if (!$llwPdo) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลกลางได้']);
                break;
            }

            try {
                // ตรวจสอบว่าตาราง att_teachers มีอยู่ก่อน
                $llwPdo->query("SELECT 1 FROM att_teachers LIMIT 1");
                $stmt = $llwPdo->query("SELECT id, name, username FROM att_teachers ORDER BY name ASC");
                $teachers = $stmt->fetchAll();
                echo json_encode(['status' => 'success', 'data' => $teachers, 'count' => count($teachers)]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่พบตาราง att_teachers ในฐานข้อมูลกลาง: ' . $e->getMessage()]);
            }
            break;

        case 'get_llw_students':
            // ดึงรายชื่อนักเรียนตามห้องเรียนจาก att_students
            if (!in_array($role, ['admin', 'teacher'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้']);
                exit;
            }

            $classroom = trim($_GET['classroom'] ?? '');
            $classroomId = (int)($_GET['classroom_id'] ?? 0);

            // หากระบุ classroom_id ให้ดึงชื่อห้องเรียนจาก pm_classrooms
            if ($classroomId > 0) {
                $cStmt = $pdo->prepare("SELECT level, room_name FROM pm_classrooms WHERE id = ?");
                $cStmt->execute([$classroomId]);
                $clsData = $cStmt->fetch();
                if ($clsData) {
                    // กำหนดรูปแบบห้องเรียน เช่น "ม.1/1" หรือ "ม.2/3"
                    $classroom = $clsData['level'] . '/' . $clsData['room_name'];
                }
            }

            $llwPdo = getLlwPdo();
            if (!$llwPdo) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลกลางได้']);
                break;
            }

            try {
                $llwPdo->query("SELECT 1 FROM att_students LIMIT 1");
                if (!empty($classroom)) {
                    $stmt = $llwPdo->prepare("SELECT id, student_id, name, classroom FROM att_students WHERE classroom = ? ORDER BY name ASC");
                    $stmt->execute([$classroom]);
                } else {
                    $stmt = $llwPdo->query("SELECT id, student_id, name, classroom FROM att_students ORDER BY classroom, name ASC");
                }
                $students = $stmt->fetchAll();
                echo json_encode(['status' => 'success', 'data' => $students, 'count' => count($students)]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่พบตาราง att_students: ' . $e->getMessage()]);
            }
            break;

        case 'get_llw_classrooms':
            // ดึงห้องเรียนทั้งหมดที่มีอยู่ใน att_subjects (ข้อมูลจากระบบเช็คชื่อ) 
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'เฉพาะแอดมินเท่านั้นที่เข้าถึงได้']);
                exit;
            }

            $llwPdo = getLlwPdo();
            if (!$llwPdo) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลกลางได้']);
                break;
            }

            $result = [];
            try {
                // ดึงรายชื่อครูที่ปรึกษาพร้อมห้องเรียนจาก att_teachers
                $llwPdo->query("SELECT 1 FROM att_teachers LIMIT 1");
                $teacherStmt = $llwPdo->query("SELECT DISTINCT t.id as teacher_id, t.name as teacher_name FROM att_teachers t ORDER BY t.name ASC");
                $teachers = $teacherStmt->fetchAll();

                // ดึงห้องเรียนพร้อมครูที่ปรึกษา (ถ้ามี) จาก att_students + llw_class_advisors + llw_users
                $llwPdo->query("SELECT 1 FROM att_students LIMIT 1");
                $classStmt = $llwPdo->query("
                    SELECT DISTINCT s.classroom, CONCAT(u.firstname, ' ', u.lastname) as advisor_name
                    FROM att_students s
                    LEFT JOIN llw_class_advisors la ON s.classroom = la.classroom AND la.role_type = 'primary'
                    LEFT JOIN llw_users u ON la.user_id = u.user_id
                    WHERE s.classroom IS NOT NULL AND s.classroom != ''
                    ORDER BY s.classroom ASC
                ");
                $classrooms_raw = $classStmt->fetchAll();

                // แปลงรูปแบบ classroom (เช่น "ม.1/1") เป็น level + room และเก็บชื่อครูที่ปรึกษา
                $classroomList = [];
                foreach ($classrooms_raw as $row) {
                    $cls = $row['classroom'];
                    $advisor = trim($row['advisor_name'] ?? '');
                    if (empty($advisor)) {
                        $advisor = 'ครูที่ปรึกษา';
                    }
                    if (preg_match('/^ม\.(\d+)[\/-](\d+)$/', $cls, $m)) {
                        $classroomList[] = [
                            'raw' => $cls,
                            'level' => 'ม.' . $m[1],
                            'room' => $m[2],
                            'teacher_name' => $advisor
                        ];
                    } elseif (preg_match('/^(\d)[\/-](\d+)$/', $cls, $m)) {
                        $classroomList[] = [
                            'raw' => $cls,
                            'level' => 'ม.' . $m[1],
                            'room' => $m[2],
                            'teacher_name' => $advisor
                        ];
                    } else {
                        $classroomList[] = [
                            'raw' => $cls,
                            'level' => $cls,
                            'room' => '',
                            'teacher_name' => $advisor
                        ];
                    }
                }

                $result = [
                    'teachers' => $teachers,
                    'classrooms' => $classroomList,
                ];
            } catch (Exception $e) {
                // ถ้า att_teachers หรือ att_students ไม่มี ให้ส่งข้อมูลเปล่า
                $result = ['teachers' => [], 'classrooms' => []];
            }

            echo json_encode(['status' => 'success', 'data' => $result]);
            break;

        case 'sync_classrooms_from_llw':
            // Sync ห้องเรียนจากข้อมูลกลาง LLW เข้า pm_classrooms
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'เฉพาะแอดมินเท่านั้นที่เข้าถึงได้']);
                exit;
            }

            $syncData = $input['classrooms'] ?? [];
            if (empty($syncData)) {
                throw new Exception('ไม่มีข้อมูลห้องเรียนที่จะนำเข้า');
            }

            $inserted = 0;
            $updated = 0;
            $insStmt = $pdo->prepare("INSERT INTO pm_classrooms (level, room_name, teacher_name) VALUES (?, ?, ?)");
            $checkStmt = $pdo->prepare("SELECT id, teacher_name FROM pm_classrooms WHERE level = ? AND room_name = ?");
            $updStmt = $pdo->prepare("UPDATE pm_classrooms SET teacher_name = ? WHERE id = ?");

            foreach ($syncData as $row) {
                $level = trim($row['level'] ?? '');
                $room = trim($row['room'] ?? '');
                $teacher = trim($row['teacher_name'] ?? 'ครูที่ปรึกษา');

                if (empty($level) || empty($room)) continue;

                // ตรวจสอบว่ามีห้องนี้อยู่แล้วหรือไม่
                $checkStmt->execute([$level, $room]);
                $existing = $checkStmt->fetch();
                if ($existing) {
                    if ($existing['teacher_name'] !== $teacher) {
                        $updStmt->execute([$teacher, $existing['id']]);
                        $updated++;
                    }
                    continue;
                }

                $insStmt->execute([$level, $room, $teacher]);
                $inserted++;
            }

            echo json_encode([
                'status' => 'success',
                'message' => "นำเข้าห้องเรียนสำเร็จ: เพิ่มใหม่ {$inserted} ห้อง, อัปเดตที่ปรึกษา {$updated} ห้อง",
                'inserted' => $inserted,
                'updated' => $updated,
            ]);
            break;

        default:
            throw new Exception('การทำงาน (Action) ไม่ถูกต้อง');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    error_log('[Parent Meeting API Exception] ' . $e->getMessage());
}
