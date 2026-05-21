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
                FROM meetings m
                JOIN classrooms c ON m.classroom_id = c.id
                JOIN users u ON m.created_by = u.id
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
            $imgStmt = $pdo->prepare("SELECT id, image_path FROM meeting_images WHERE meeting_id = ?");
            $imgStmt->execute([$meetingId]);
            $images = $imgStmt->fetchAll();

            // ปรับแต่ง path ให้พร้อมใช้งานใน frontend
            foreach ($images as &$img) {
                // หากเป็น path สัมพัทธ์ ให้เติม url helper
                $img['image_path'] = pm_url($img['image_path']);
            }

            echo json_encode([
                'status' => 'success',
                'data' => $meeting,
                'images' => $images
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

            if (empty($meetingDate) || empty($semester) || $academicYear <= 0 || $classroomId <= 0 || $totalParents < 0 || $attendCount < 0) {
                throw new Exception('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
            }

            if ($attendCount > $totalParents) {
                throw new Exception('จำนวนผู้เข้าร่วมประชุม ห้ามมากกว่าจำนวนผู้ปกครองทั้งหมด');
            }

            // คำนวณจำนวนผู้ขาดที่ถูกต้อง
            $absentCount = max(0, $totalParents - $attendCount);

            $pdo->beginTransaction();

            if ($meetingId > 0) {
                // โหมดแก้ไข
                // ถ้าสิทธิ์เป็นครู ตรวจสอบความสิทธิ์การแก้ไข (เป็นผู้บันทึก)
                if ($role === 'teacher') {
                    $checkStmt = $pdo->prepare("SELECT created_by FROM meetings WHERE id = ?");
                    $checkStmt->execute([$meetingId]);
                    $owner = $checkStmt->fetchColumn();
                    if ($owner != $userId) {
                        throw new Exception('คุณไม่มีสิทธิ์แก้ไขรายงานการประชุมห้องเรียนนี้');
                    }
                }

                $stmt = $pdo->prepare("
                    UPDATE meetings 
                    SET meeting_date = ?, semester = ?, academic_year = ?, classroom_id = ?, 
                        total_students = ?, total_parents = ?, attend_count = ?, absent_count = ?, 
                        summary = ?, problems = ?, suggestions = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $meetingDate, $semester, $academicYear, $classroomId,
                    $totalStudents, $totalParents, $attendCount, $absentCount,
                    $summary, $problems, $suggestions, $meetingId
                ]);
            } else {
                // โหมดเพิ่มใหม่
                $stmt = $pdo->prepare("
                    INSERT INTO meetings (meeting_date, semester, academic_year, classroom_id, total_students, total_parents, attend_count, absent_count, summary, problems, suggestions, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $meetingDate, $semester, $academicYear, $classroomId,
                    $totalStudents, $totalParents, $attendCount, $absentCount,
                    $summary, $problems, $suggestions, $userId
                ]);
                $meetingId = $pdo->lastInsertId();
            }

            // อัปโหลดไฟล์รูปภาพกิจกรรม (ถ้ามี)
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $files = $_FILES['images'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $uploadDir = __DIR__ . '/uploads/';

                // ตรวจสอบและสร้างโฟลเดอร์ถ้าไม่มี
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
                    
                    // เช็คขนาดไฟล์ (ไม่เกิน 5MB)
                    if ($fileSize > 5 * 1024 * 1024) {
                        throw new Exception("ขนาดไฟล์รูปภาพเกิน 5MB: $fileName");
                    }

                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions)) {
                        throw new Exception("ประเภทไฟล์ไม่รองรับ (.jpg, .jpeg, .png, .webp เท่านั้น): $fileName");
                    }

                    // สุ่มชื่อไฟล์ใหม่
                    $newFileName = uniqid('meet_') . '_' . time() . '_' . $i . '.' . $ext;
                    $destPath = $uploadDir . $newFileName;

                    if (move_uploaded_file($tmpName, $destPath)) {
                        $imgStmt = $pdo->prepare("INSERT INTO meeting_images (meeting_id, image_path) VALUES (?, ?)");
                        $imgStmt->execute([$meetingId, 'uploads/' . $newFileName]);
                    } else {
                        throw new Exception("อัปโหลดไฟล์ไม่สำเร็จ: $fileName");
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลรายงานการประชุมเรียบร้อยแล้ว']);
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
                    FROM meeting_images mi
                    JOIN meetings m ON mi.meeting_id = m.id
                    WHERE mi.id = ?
                ");
                $checkStmt->execute([$imageId]);
                $owner = $checkStmt->fetchColumn();
                if ($owner != $userId) {
                    throw new Exception('คุณไม่มีสิทธิ์ลบรูปภาพกิจกรรมของรายงานฉบับนี้');
                }
            }

            // ดึงพาธรูปภาพมาเพื่อทำการลบจากเซิร์ฟเวอร์
            $stmt = $pdo->prepare("SELECT image_path FROM meeting_images WHERE id = ?");
            $stmt->execute([$imageId]);
            $imgPath = $stmt->fetchColumn();

            if ($imgPath) {
                $fullPath = __DIR__ . '/' . $imgPath;
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            // ลบจากฐานข้อมูล
            $deleteStmt = $pdo->prepare("DELETE FROM meeting_images WHERE id = ?");
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
                $checkStmt = $pdo->prepare("SELECT created_by FROM meetings WHERE id = ?");
                $checkStmt->execute([$meetingId]);
                $owner = $checkStmt->fetchColumn();
                if ($owner != $userId) {
                    throw new Exception('คุณไม่มีสิทธิ์ลบรายงานการประชุมห้องเรียนนี้');
                }
            }

            $pdo->beginTransaction();

            // ดึงและลบรูปกิจกรรมทั้งหมดจากเซิร์ฟเวอร์
            $imgStmt = $pdo->prepare("SELECT image_path FROM meeting_images WHERE meeting_id = ?");
            $imgStmt->execute([$meetingId]);
            $images = $imgStmt->fetchAll();
            foreach ($images as $img) {
                $fullPath = __DIR__ . '/' . $img['image_path'];
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            // ดึงและลบรูปเครือข่ายผู้ปกครองทั้งหมดของรายงานนี้จากเซิร์ฟเวอร์
            $netStmt = $pdo->prepare("SELECT image_path FROM network_parents WHERE meeting_id = ?");
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
            $deleteStmt = $pdo->prepare("DELETE FROM meetings WHERE id = ?");
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

            $stmt = $pdo->prepare("SELECT * FROM network_parents WHERE id = ?");
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
                $checkStmt = $pdo->prepare("SELECT created_by FROM meetings WHERE id = ?");
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
                    $checkPosStmt = $pdo->prepare("SELECT id FROM network_parents WHERE meeting_id = ? AND position_name = ?");
                    $checkPosStmt->execute([$meetingId, $positionName]);
                    $existingId = $checkPosStmt->fetchColumn();
                    
                    if ($existingId) {
                        // หากมีอยู่แล้ว ให้เปลี่ยนโหมดเป็น Update อัตโนมัติ เพื่อความลื่นไหล
                        $networkId = (int)$existingId;
                    }
                } else {
                    // ตำแหน่ง กรรมการ มีได้สูงสุด 2 คน
                    $checkPosStmt = $pdo->prepare("SELECT COUNT(*) FROM network_parents WHERE meeting_id = ? AND position_name = 'กรรมการ'");
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
                $oldImgStmt = $pdo->prepare("SELECT image_path FROM network_parents WHERE id = ?");
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
                    UPDATE network_parents 
                    SET parent_name = ?, student_name = ?, student_class = ?, phone = ?, address = ?, image_path = ?
                    WHERE id = ?
                ");
                $stmt->execute([$parentName, $studentName, $studentClass, $phone, $address, $imagePath, $networkId]);
            } else {
                // เพิ่มข้อมูลใหม่
                $stmt = $pdo->prepare("
                    INSERT INTO network_parents (meeting_id, position_name, parent_name, student_name, student_class, phone, address, image_path)
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
                    FROM network_parents np
                    JOIN meetings m ON np.meeting_id = m.id
                    WHERE np.id = ?
                ");
                $checkStmt->execute([$networkId]);
                $owner = $checkStmt->fetchColumn();
                if ($owner != $userId) {
                    throw new Exception('คุณไม่มีสิทธิ์ลบข้อมูลเครือข่ายของชั้นเรียนนี้');
                }
            }

            $stmt = $pdo->prepare("SELECT image_path FROM network_parents WHERE id = ?");
            $stmt->execute([$networkId]);
            $imgPath = $stmt->fetchColumn();

            if ($imgPath) {
                $fullPath = __DIR__ . '/' . $imgPath;
                if (file_exists($fullPath) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            $deleteStmt = $pdo->prepare("DELETE FROM network_parents WHERE id = ?");
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
                FROM comments c
                JOIN users u ON c.commented_by = u.id
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

            $stmt = $pdo->prepare("INSERT INTO comments (meeting_id, comment_text, commented_by) VALUES (?, ?, ?)");
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

            $stmt = $pdo->prepare("SELECT id, fullname, username, role FROM users WHERE id = ?");
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
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $chk->execute([$username, $tgtUserId]);
                if ($chk->fetch()) {
                    throw new Exception('ชื่อผู้ใช้งาน (Username) นี้มีผู้ใช้รายอื่นในระบบแล้ว');
                }

                if (!empty($password)) {
                    // อัปเดตพร้อมรหัสผ่านใหม่
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET fullname = ?, username = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$fullname, $username, $userRole, $hash, $tgtUserId]);
                } else {
                    // อัปเดตโดยไม่เปลี่ยนรหัสผ่าน
                    $stmt = $pdo->prepare("UPDATE users SET fullname = ?, username = ?, role = ? WHERE id = ?");
                    $stmt->execute([$fullname, $username, $userRole, $tgtUserId]);
                }
            } else {
                // เพิ่มผู้ใช้ใหม่
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $chk->execute([$username]);
                if ($chk->fetch()) {
                    throw new Exception('ชื่อผู้ใช้งาน (Username) นี้มีผู้ใช้ในระบบแล้ว');
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (fullname, username, password, role) VALUES (?, ?, ?, ?)");
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

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
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

            $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
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
                $stmt = $pdo->prepare("UPDATE classrooms SET level = ?, room_name = ?, teacher_name = ? WHERE id = ?");
                $stmt->execute([$level, $roomName, $teacherName, $classroomId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO classrooms (level, room_name, teacher_name) VALUES (?, ?, ?)");
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

            $stmt = $pdo->prepare("DELETE FROM classrooms WHERE id = ?");
            $stmt->execute([$classroomId]);

            echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลห้องเรียนเรียบร้อยแล้ว']);
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
