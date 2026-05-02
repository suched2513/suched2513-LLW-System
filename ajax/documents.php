<?php
/**
 * ajax/documents.php
 * Backend Logic for e-Document System
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// Whitelist: type → actual table name
const TABLE_MAP = [
    'incoming' => 'edoc_incoming',
    'outgoing' => 'edoc_outgoing',
    'order'    => 'edoc_orders',
    'memo'     => 'edoc_memos',
];

function resolveTable(string $type): string {
    if (!array_key_exists($type, TABLE_MAP)) {
        throw new InvalidArgumentException('ประเภทเอกสารไม่ถูกต้อง');
    }
    return TABLE_MAP[$type];
}

function resolveTableByName(string $tableName): string {
    if (!in_array($tableName, TABLE_MAP, true)) {
        throw new InvalidArgumentException('ชื่อตารางไม่ถูกต้อง');
    }
    return $tableName;
}

$action = $_REQUEST['action'] ?? '';
$pdo = getPdo();

try {
    switch ($action) {
        case 'list':             handleList($pdo);             break;
        case 'save':             handleSave($pdo);             break;
        case 'get':              handleGet($pdo);              break;
        case 'delete':           handleDelete($pdo);           break;
        case 'delete_attachment':handleDeleteAttachment($pdo); break;
        case 'assign':           handleAssign($pdo);           break;
        case 'get_involved_users':handleGetInvolvedUsers($pdo);break;
        case 'get_users':        handleGetUsers($pdo);         break;
        case 'send_telegram':    handleSendTelegram($pdo);     break;
        default:                 throw new Exception('Action not found');
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log('[edoc] ' . $e->getMessage());
}

function handleList(PDO $pdo): void {
    $table  = resolveTable($_GET['type'] ?? 'incoming');
    $search = $_GET['search'] ?? '';
    $year   = $_GET['year']   ?? '';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 10;
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[]  = '(doc_number LIKE ? OR subject LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($year !== '') {
        $where[]  = 'year_be = ?';
        $params[] = (int)$year;
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT *, DATE_FORMAT(doc_date, '%d/%m/%Y') AS doc_date_formatted
                           FROM $table
                           WHERE $whereClause
                           ORDER BY doc_date DESC, id DESC
                           LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $attStmt = $pdo->prepare('SELECT COUNT(*) FROM edoc_attachments WHERE ref_table = ? AND ref_id = ?');
        $attStmt->execute([$table, $item['id']]);
        $item['attachments_count'] = (int)$attStmt->fetchColumn();
    }
    unset($item);

    echo json_encode([
        'success' => true,
        'data'    => $items,
        'pagination' => [
            'total'        => $total,
            'current_page' => $page,
            'total_pages'  => (int)ceil($total / $limit),
            'start'        => $total === 0 ? 0 : $offset + 1,
            'end'          => min($offset + $limit, $total),
        ],
    ]);
}

function handleSave(PDO $pdo): void {
    $table      = resolveTable($_POST['type'] ?? 'incoming');
    $id         = (int)($_POST['id'] ?? 0);
    $doc_number = trim($_POST['doc_number'] ?? '');
    $doc_date   = $_POST['doc_date'] ?? '';
    $subject    = trim($_POST['subject'] ?? '');
    $status     = $_POST['status'] ?? 'รออนุมัติ';
    $year_be    = (int)($_POST['year_be'] ?? (date('Y') + 543));
    $created_by = (int)($_SESSION['user_id'] ?? 0);

    $allowedStatus = ['รออนุมัติ', 'ประกาศใช้แล้ว', 'ยกเลิก'];
    if (!in_array($status, $allowedStatus, true)) $status = 'รออนุมัติ';

    $pdo->beginTransaction();

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE $table SET doc_number=?, doc_date=?, subject=?, status=?, year_be=? WHERE id=?");
        $stmt->execute([$doc_number, $doc_date, $subject, $status, $year_be, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO $table (doc_number, doc_date, subject, status, year_be, created_by) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$doc_number, $doc_date, $subject, $status, $year_be, $created_by]);
        $id = (int)$pdo->lastInsertId();
    }

    if (!empty($_FILES['new_attachments']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/edoc/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif'];
        foreach ($_FILES['new_attachments']['name'] as $key => $name) {
            if ($_FILES['new_attachments']['error'][$key] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) continue;
            $newName  = uniqid('edoc_') . '.' . $ext;
            $filePath = 'uploads/edoc/' . $newName;
            if (move_uploaded_file($_FILES['new_attachments']['tmp_name'][$key], $uploadDir . $newName)) {
                $attStmt = $pdo->prepare('INSERT INTO edoc_attachments (ref_table, ref_id, file_path, file_name, file_type, file_size) VALUES (?,?,?,?,?,?)');
                $attStmt->execute([
                    $table, $id, $filePath, $name,
                    $_FILES['new_attachments']['type'][$key],
                    $_FILES['new_attachments']['size'][$key],
                ]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว', 'id' => $id]);
}

function handleGet(PDO $pdo): void {
    $table = resolveTable($_GET['type'] ?? 'incoming');
    $id    = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) throw new Exception('ไม่พบเอกสาร');

    $attStmt = $pdo->prepare('SELECT * FROM edoc_attachments WHERE ref_table = ? AND ref_id = ? ORDER BY sort_order ASC, id ASC');
    $attStmt->execute([$table, $id]);
    $item['attachments'] = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $item]);
}

function handleDelete(PDO $pdo): void {
    if (!in_array($_SESSION['llw_role'], ['super_admin', 'edoc_admin'], true)) {
        throw new Exception('คุณไม่มีสิทธิ์ลบเอกสาร');
    }

    $table = resolveTable($_POST['type'] ?? 'incoming');
    $id    = (int)($_POST['id'] ?? 0);

    $pdo->beginTransaction();

    $attStmt = $pdo->prepare('SELECT file_path FROM edoc_attachments WHERE ref_table = ? AND ref_id = ?');
    $attStmt->execute([$table, $id]);
    foreach ($attStmt->fetchAll(PDO::FETCH_COLUMN) as $file) {
        $full = __DIR__ . '/../' . $file;
        if (file_exists($full)) unlink($full);
    }

    $pdo->prepare('DELETE FROM edoc_attachments    WHERE ref_table = ? AND ref_id = ?')->execute([$table, $id]);
    $pdo->prepare('DELETE FROM edoc_links          WHERE ref_table = ? AND ref_id = ?')->execute([$table, $id]);
    $pdo->prepare('DELETE FROM edoc_involved_users WHERE ref_table = ? AND ref_id = ?')->execute([$table, $id]);
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
}

function handleDeleteAttachment(PDO $pdo): void {
    $id = (int)($_POST['attachment_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT file_path FROM edoc_attachments WHERE id = ?');
    $stmt->execute([$id]);
    $filePath = $stmt->fetchColumn();

    if (!$filePath) throw new Exception('ไม่พบไฟล์แนบ');

    $full = __DIR__ . '/../' . $filePath;
    if (file_exists($full)) unlink($full);

    $pdo->prepare('DELETE FROM edoc_attachments WHERE id = ?')->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'ลบไฟล์แนบเรียบร้อยแล้ว']);
}

function handleAssign(PDO $pdo): void {
    $ref_id    = (int)($_POST['ref_id']    ?? 0);
    $ref_table = resolveTableByName($_POST['ref_table'] ?? '');
    $user_ids  = array_map('intval', (array)($_POST['user_ids'] ?? []));

    if ($ref_id <= 0) throw new Exception('ข้อมูลไม่ครบถ้วน');

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM edoc_involved_users WHERE ref_table = ? AND ref_id = ?')->execute([$ref_table, $ref_id]);
    $stmt = $pdo->prepare('INSERT INTO edoc_involved_users (ref_table, ref_id, user_id, assigner_id) VALUES (?,?,?,?)');
    foreach ($user_ids as $uid) {
        $stmt->execute([$ref_table, $ref_id, $uid, (int)($_SESSION['user_id'] ?? 0)]);
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'บันทึกการมอบหมายเรียบร้อยแล้ว']);
}

function handleGetInvolvedUsers(PDO $pdo): void {
    $ref_id    = (int)($_GET['ref_id'] ?? 0);
    $ref_table = resolveTableByName($_GET['ref_table'] ?? '');

    $stmt = $pdo->prepare('SELECT u.user_id, u.firstname, u.lastname, i.acknowledged_at
                           FROM edoc_involved_users i
                           JOIN llw_users u ON i.user_id = u.user_id
                           WHERE i.ref_table = ? AND i.ref_id = ?');
    $stmt->execute([$ref_table, $ref_id]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function handleGetUsers(PDO $pdo): void {
    $stmt = $pdo->query("SELECT user_id AS id, CONCAT(firstname, ' ', lastname) AS text
                         FROM llw_users WHERE status = 'active' ORDER BY firstname ASC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function handleSendTelegram(PDO $pdo): void {
    require_once __DIR__ . '/../includes/telegram_bot.php';

    $ref_id         = (int)($_POST['ref_id'] ?? 0);
    $ref_table      = resolveTableByName($_POST['ref_table'] ?? '');
    $message_prefix = htmlspecialchars($_POST['message'] ?? 'แจ้งเตือนเอกสารใหม่: ', ENT_QUOTES, 'UTF-8');

    $stmt = $pdo->prepare("SELECT subject, doc_number FROM $ref_table WHERE id = ?");
    $stmt->execute([$ref_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) throw new Exception('ไม่พบเอกสาร');

    $text  = "<b>{$message_prefix}</b>\n";
    $text .= 'เลขที่: ' . htmlspecialchars($doc['doc_number'], ENT_QUOTES, 'UTF-8') . "\n";
    $text .= 'เรื่อง: ' . htmlspecialchars($doc['subject'], ENT_QUOTES, 'UTF-8') . "\n";
    $text .= 'แจ้งโดย: ' . htmlspecialchars($_SESSION['firstname'] ?? 'ระบบ', ENT_QUOTES, 'UTF-8');

    if (!defined('TELEGRAM_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
        throw new Exception('ไม่ได้ตั้งค่า Telegram Token/ChatID');
    }

    $bot = new TelegramBot(TELEGRAM_TOKEN, TELEGRAM_CHAT_ID);
    if (!$bot->sendMessage($text)) {
        throw new Exception('ไม่สามารถส่ง Telegram ได้');
    }

    echo json_encode(['success' => true, 'message' => 'ส่งแจ้งเตือน Telegram เรียบร้อยแล้ว']);
}
