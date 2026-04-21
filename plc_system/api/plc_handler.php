<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input && $_POST) $input = $_POST; // Fallback for standard form posts

try {
    $pdo = getPdo();
    $userId = $_SESSION['user_id'];
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'create_group':
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO plc_groups (group_name, academic_year, semester, target_group, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $input['group_name'] ?? '',
                $input['academic_year'] ?? '',
                $input['semester'] ?? '',
                $input['target_group'] ?? '',
                $userId
            ]);
            $groupId = $pdo->lastInsertId();

            // Add creator as Model Teacher by default
            $stmt = $pdo->prepare("
                INSERT INTO plc_members (group_id, user_id, role)
                VALUES (?, ?, 'model_teacher')
            ");
            $stmt->execute([$groupId, $userId]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Group created successfully', 'data' => ['id' => $groupId]]);
            break;

        case 'add_log':
            // Check if user is member of the group
            $stmt = $pdo->prepare("SELECT id FROM plc_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$input['group_id'], $userId]);
            if (!$stmt->fetch()) {
                throw new Exception("คุณไม่มีสิทธิ์ในกลุ่มนี้");
            }

            // Handle File Uploads
            $evidencePaths = [];
            if (!empty($_FILES['evidence_files']['name'][0])) {
                $targetDir = "../../uploads/plc/";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

                $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'xlsx', 'pptx'];
                
                foreach ($_FILES['evidence_files']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['evidence_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $originalName = $_FILES['evidence_files']['name'][$key];
                        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                        
                        if (in_array($ext, $allowedExts)) {
                            $newName = uniqid('plc_', true) . '.' . $ext;
                            $targetFile = $targetDir . $newName;
                            
                            if (move_uploaded_file($tmpName, $targetFile)) {
                                $evidencePaths[] = "uploads/plc/" . $newName;
                            }
                        }
                    }
                }
            }

            $evidenceStr = implode(',', $evidencePaths);

            $stmt = $pdo->prepare("
                INSERT INTO plc_logs (group_id, user_id, phase, topic, details, reflection, evidence_path, log_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $input['group_id'],
                $userId,
                $input['phase'],
                $input['topic'],
                $input['details'] ?? '',
                $input['reflection'] ?? '',
                $evidenceStr,
                $input['log_date'] ?? date('Y-m-d')
            ]);

            echo json_encode(['status' => 'success', 'message' => 'เพิ่มบันทึกสำเร็จ' . (!empty($evidencePaths) ? ' (พร้อมแนบไฟล์ ' . count($evidencePaths) . ' รายการ)' : '')]);
            break;

        case 'add_member':
            // Only model_teacher or super_admin can add members
            $stmt = $pdo->prepare("SELECT role FROM plc_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$input['group_id'], $userId]);
            $myRole = $stmt->fetchColumn();
            if ($myRole !== 'model_teacher' && $_SESSION['llw_role'] !== 'super_admin') {
                throw new Exception("คุณไม่มีสิทธิ์เพิ่มสมาชิก");
            }

            // Validate target user exists
            $stmt = $pdo->prepare("SELECT user_id, firstname, lastname FROM llw_users WHERE user_id = ? AND status = 'active'");
            $stmt->execute([$input['target_user_id']]);
            $targetUser = $stmt->fetch();
            if (!$targetUser) throw new Exception("ไม่พบผู้ใช้งานที่ระบุ");

            // Check not already member
            $stmt = $pdo->prepare("SELECT id FROM plc_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$input['group_id'], $input['target_user_id']]);
            if ($stmt->fetch()) throw new Exception("{$targetUser['firstname']} {$targetUser['lastname']} เป็นสมาชิกของกลุ่มนี้แล้ว");

            $memberRole = in_array($input['member_role'] ?? '', ['model_teacher','mentor','expert','member']) ? $input['member_role'] : 'member';

            $stmt = $pdo->prepare("INSERT INTO plc_members (group_id, user_id, role) VALUES (?, ?, ?)");
            $stmt->execute([$input['group_id'], $input['target_user_id'], $memberRole]);

            echo json_encode(['status' => 'success', 'message' => "เพิ่ม {$targetUser['firstname']} {$targetUser['lastname']} เป็นสมาชิกสำเร็จ"]);
            break;

        case 'remove_member':
            // Only model_teacher or super_admin can remove members
            $stmt = $pdo->prepare("SELECT role FROM plc_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$input['group_id'], $userId]);
            $myRole = $stmt->fetchColumn();
            if ($myRole !== 'model_teacher' && $_SESSION['llw_role'] !== 'super_admin') {
                throw new Exception("คุณไม่มีสิทธิ์ลบสมาชิก");
            }
            // Cannot remove model_teacher (creator)
            $stmt = $pdo->prepare("SELECT role FROM plc_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$input['group_id'], $input['target_user_id']]);
            $targetRole = $stmt->fetchColumn();
            if ($targetRole === 'model_teacher') throw new Exception("ไม่สามารถลบ Model Teacher ออกได้");

            $stmt = $pdo->prepare("DELETE FROM plc_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$input['group_id'], $input['target_user_id']]);
            echo json_encode(['status' => 'success', 'message' => 'ลบสมาชิกออกจากกลุ่มสำเร็จ']);
            break;

        case 'delete_log':
            // Only log owner, model_teacher of the group, or super_admin
            $stmt = $pdo->prepare("SELECT l.user_id, m.role FROM plc_logs l LEFT JOIN plc_members m ON m.group_id = l.group_id AND m.user_id = ? WHERE l.id = ?");
            $stmt->execute([$userId, $input['log_id']]);
            $logInfo = $stmt->fetch();
            if (!$logInfo) throw new Exception("ไม่พบบันทึกกิจกรรม");

            $canDelete = ($logInfo['user_id'] == $userId)
                      || ($logInfo['role'] === 'model_teacher')
                      || ($_SESSION['llw_role'] === 'super_admin');
            if (!$canDelete) throw new Exception("คุณไม่มีสิทธิ์ลบบันทึกนี้");

            $stmt = $pdo->prepare("DELETE FROM plc_logs WHERE id = ?");
            $stmt->execute([$input['log_id']]);
            echo json_encode(['status' => 'success', 'message' => 'ลบบันทึกกิจกรรมสำเร็จ']);
            break;

        case 'update_group_status':
            // Only model_teacher or super_admin
            $stmt = $pdo->prepare("SELECT role FROM plc_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$input['group_id'], $userId]);
            $myRole = $stmt->fetchColumn();
            if ($myRole !== 'model_teacher' && $_SESSION['llw_role'] !== 'super_admin') {
                throw new Exception("คุณไม่มีสิทธิ์เปลี่ยนสถานะกลุ่ม");
            }
            $allowedStatuses = ['active', 'completed', 'archived'];
            if (!in_array($input['status'], $allowedStatuses)) throw new Exception("สถานะไม่ถูกต้อง");

            $stmt = $pdo->prepare("UPDATE plc_groups SET status = ? WHERE id = ?");
            $stmt->execute([$input['status'], $input['group_id']]);
            echo json_encode(['status' => 'success', 'message' => 'อัปเดตสถานะกลุ่มสำเร็จ']);
            break;

        case 'get_users':
            // Return list of users for member search (super_admin + model_teacher)
            $search = '%' . ($input['q'] ?? '') . '%';
            $stmt = $pdo->prepare("SELECT user_id, firstname, lastname, role FROM llw_users WHERE status = 'active' AND (firstname LIKE ? OR lastname LIKE ? OR username LIKE ?) LIMIT 20");
            $stmt->execute([$search, $search, $search]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $users]);
            break;

        case 'delete_group':
            // super_admin only
            if ($_SESSION['llw_role'] !== 'super_admin') {
                throw new Exception("เฉพาะ Super Admin เท่านั้นที่ลบกลุ่มได้");
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM plc_logs WHERE group_id = ?");
            $stmt->execute([$input['group_id']]);
            $stmt = $pdo->prepare("DELETE FROM plc_members WHERE group_id = ?");
            $stmt->execute([$input['group_id']]);
            $stmt = $pdo->prepare("DELETE FROM plc_groups WHERE id = ?");
            $stmt->execute([$input['group_id']]);
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'ลบกลุ่มสำเร็จ']);
            break;

        default:
            throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[LLW] plc_handler error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
