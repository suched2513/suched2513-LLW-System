<?php
/**
 * ajax/documents.php
 * Backend Logic for e-Document System
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/database.php';

// Auth Guard
if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$pdo = getPdo();

try {
    switch ($action) {
        case 'list':
            handleList($pdo);
            break;
        case 'save':
            handleSave($pdo);
            break;
        case 'get':
            handleGet($pdo);
            break;
        case 'delete':
            handleDelete($pdo);
            break;
        case 'delete_attachment':
            handleDeleteAttachment($pdo);
            break;
        case 'assign':
            handleAssign($pdo);
            break;
        case 'get_involved_users':
            handleGetInvolvedUsers($pdo);
            break;
        case 'get_users':
            handleGetUsers($pdo);
            break;
        case 'send_telegram':
            handleSendTelegram($pdo);
            break;
        default:
            throw new Exception('Action not found');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log($e->getMessage());
}

/**
 * List documents with pagination and filters
 */
function handleList($pdo) {
    $type = $_GET['type'] ?? 'incoming';
    $table = "edoc_{$type}"; // Prefix added in migration
    $search = $_GET['search'] ?? '';
    $year = $_GET['year'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $where = ["1=1"];
    $params = [];

    if ($search) {
        $where[] = "(doc_number LIKE ? OR subject LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($year) {
        $where[] = "year_be = ?";
        $params[] = $year;
    }

    $whereClause = implode(" AND ", $where);

    // Count total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Fetch data
    $stmt = $pdo->prepare("SELECT *, DATE_FORMAT(doc_date, '%d/%m/%Y') as doc_date_formatted 
                           FROM $table 
                           WHERE $whereClause 
                           ORDER BY doc_date DESC, id DESC 
                           LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get attachment counts
    foreach ($items as &$item) {
        $attStmt = $pdo->prepare("SELECT COUNT(*) FROM edoc_attachments WHERE ref_table = ? AND ref_id = ?");
        $attStmt->execute([$table, $item['id']]);
        $item['attachments_count'] = (int)$attStmt->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'data' => $items,
        'pagination' => [
            'total' => $total,
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'start' => $offset + 1,
            'end' => min($offset + $limit, $total)
        ]
    ]);
}

/**
 * Save (Create/Update) document
 */
function handleSave($pdo) {
    $id = $_POST['id'] ?? '';
    $type = $_POST['type'] ?? 'incoming';
    $table = "edoc_{$type}";
    
    $doc_number = $_POST['doc_number'] ?? '';
    $doc_date = $_POST['doc_date'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $status = $_POST['status'] ?? 'รออนุมัติ';
    $year_be = $_POST['year_be'] ?? (date('Y') + 543);
    $created_by = $_SESSION['user_id'] ?? 0;

    $pdo->beginTransaction();

    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE $table SET doc_number = ?, doc_date = ?, subject = ?, status = ?, year_be = ? WHERE id = ?");
        $stmt->execute([$doc_number, $doc_date, $subject, $status, $year_be, $id]);
    } else {
        // Create
        $stmt = $pdo->prepare("INSERT INTO $table (doc_number, doc_date, subject, status, year_be, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$doc_number, $doc_date, $subject, $status, $year_be, $created_by]);
        $id = $pdo->lastInsertId();
    }

    // Handle Attachments
    if (isset($_FILES['new_attachments'])) {
        $uploadDir = __DIR__ . '/../uploads/edoc/';
        foreach ($_FILES['new_attachments']['name'] as $key => $name) {
            if ($_FILES['new_attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['new_attachments']['tmp_name'][$key];
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $newName = uniqid('edoc_') . '.' . $ext;
                $filePath = 'uploads/edoc/' . $newName;

                if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                    $attStmt = $pdo->prepare("INSERT INTO edoc_attachments (ref_table, ref_id, file_path, file_name, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)");
                    $attStmt->execute([
                        $table,
                        $id,
                        $filePath,
                        $name,
                        $_FILES['new_attachments']['type'][$key],
                        $_FILES['new_attachments']['size'][$key]
                    ]);
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว', 'id' => $id]);
}

/**
 * Get single document details
 */
function handleGet($pdo) {
    $id = $_GET['id'] ?? '';
    $type = $_GET['type'] ?? 'incoming';
    $table = "edoc_{$type}";

    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) throw new Exception('Document not found');

    // Get attachments
    $attStmt = $pdo->prepare("SELECT * FROM edoc_attachments WHERE ref_table = ? AND ref_id = ? ORDER BY sort_order ASC, id ASC");
    $attStmt->execute([$table, $id]);
    $item['attachments'] = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $item]);
}

/**
 * Delete document
 */
function handleDelete($pdo) {
    // Only super_admin or edoc_admin can delete
    if (!in_array($_SESSION['llw_role'], ['super_admin', 'edoc_admin'])) {
        throw new Exception('คุณไม่มีสิทธิ์ลบเอกสาร');
    }

    $id = $_POST['id'] ?? '';
    $type = $_POST['type'] ?? 'incoming';
    $table = "edoc_{$type}";

    $pdo->beginTransaction();

    // Get and delete attachments files
    $attStmt = $pdo->prepare("SELECT file_path FROM edoc_attachments WHERE ref_table = ? AND ref_id = ?");
    $attStmt->execute([$table, $id]);
    $files = $attStmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($files as $file) {
        $fullPath = __DIR__ . '/../' . $file;
        if (file_exists($fullPath)) unlink($fullPath);
    }

    // Delete records
    $pdo->prepare("DELETE FROM edoc_attachments WHERE ref_table = ? AND ref_id = ?")->execute([$table, $id]);
    $pdo->prepare("DELETE FROM edoc_links WHERE ref_table = ? AND ref_id = ?")->execute([$table, $id]);
    $pdo->prepare("DELETE FROM edoc_involved_users WHERE ref_table = ? AND ref_id = ?")->execute([$table, $id]);
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
}

/**
 * Delete single attachment
 */
function handleDeleteAttachment($pdo) {
    $id = $_POST['attachment_id'] ?? '';
    
    $stmt = $pdo->prepare("SELECT file_path FROM edoc_attachments WHERE id = ?");
    $stmt->execute([$id]);
    $filePath = $stmt->fetchColumn();

    if ($filePath) {
        $fullPath = __DIR__ . '/../' . $filePath;
        if (file_exists($fullPath)) unlink($fullPath);
        
        $pdo->prepare("DELETE FROM edoc_attachments WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'ลบไฟล์แนบเรียบร้อยแล้ว']);
    } else {
        throw new Exception('ไม่พบไฟล์แนบ');
    }
}

/**
 * Assign users to document
 */
function handleAssign($pdo) {
    $ref_id = $_POST['ref_id'] ?? '';
    $ref_table = $_POST['ref_table'] ?? '';
    $user_ids = $_POST['user_ids'] ?? []; // Array of IDs

    if (empty($ref_id) || empty($ref_table)) throw new Exception('ข้อมูลไม่ครบถ้วน');

    $pdo->beginTransaction();
    
    // Clear old assignments (optional, or just add new)
    // For this implementation, we replace all assignments
    $pdo->prepare("DELETE FROM edoc_involved_users WHERE ref_table = ? AND ref_id = ?")->execute([$ref_table, $ref_id]);

    $stmt = $pdo->prepare("INSERT INTO edoc_involved_users (ref_table, ref_id, user_id, assigner_id) VALUES (?, ?, ?, ?)");
    foreach ($user_ids as $uid) {
        $stmt->execute([$ref_table, $ref_id, $uid, $_SESSION['user_id']]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'บันทึกการมอบหมายเรียบร้อยแล้ว']);
}

/**
 * Get users assigned to a doc
 */
function handleGetInvolvedUsers($pdo) {
    $ref_id = $_GET['ref_id'] ?? '';
    $ref_table = $_GET['ref_table'] ?? '';

    $stmt = $pdo->prepare("SELECT u.user_id, u.firstname, u.lastname, i.acknowledged_at 
                           FROM edoc_involved_users i 
                           JOIN llw_users u ON i.user_id = u.user_id 
                           WHERE i.ref_table = ? AND i.ref_id = ?");
    $stmt->execute([$ref_table, $ref_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $users]);
}

/**
 * Get all active users for Select2
 */
function handleGetUsers($pdo) {
    $stmt = $pdo->query("SELECT user_id as id, CONCAT(firstname, ' ', lastname) as text FROM llw_users WHERE status = 'active' ORDER BY firstname ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $users]);
}

/**
 * Send notification to Telegram
 */
function handleSendTelegram($pdo) {
    require_once __DIR__ . '/../includes/telegram_bot.php';
    
    $ref_id = $_POST['ref_id'] ?? '';
    $ref_table = $_POST['ref_table'] ?? '';
    $message_prefix = $_POST['message'] ?? 'แจ้งเตือนเอกสารใหม่: ';

    // Fetch document details
    $stmt = $pdo->prepare("SELECT subject, doc_number FROM $ref_table WHERE id = ?");
    $stmt->execute([$ref_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) throw new Exception('ไม่พบเอกสาร');

    $fullMessage = "<b>{$message_prefix}</b>\n";
    $fullMessage .= "เลขที่: {$doc['doc_number']}\n";
    $fullMessage .= "เรื่อง: {$doc['subject']}\n";
    $fullMessage .= "แจ้งโดย: " . ($_SESSION['firstname'] ?? 'ระบบ');

    // Get Telegram settings from DB (if available) or config
    // In LLW, it's usually in wfh_system_settings or config
    if (defined('TELEGRAM_TOKEN') && defined('TELEGRAM_CHAT_ID')) {
        $bot = new TelegramBot(TELEGRAM_TOKEN, TELEGRAM_CHAT_ID);
        $res = $bot->sendMessage($fullMessage);
        if ($res) {
            echo json_encode(['success' => true, 'message' => 'ส่งแจ้งเตือน Telegram เรียบร้อยแล้ว']);
        } else {
            throw new Exception('ไม่สามารถส่ง Telegram ได้');
        }
    } else {
        throw new Exception('ไม่ได้ตั้งค่า Telegram Token/ChatID');
    }
}
