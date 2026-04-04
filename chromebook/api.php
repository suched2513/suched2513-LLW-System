session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require 'config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input && isset($_POST['action'])) {
    $input = $_POST;
}

$action = $_GET['action'] ?? ($input['action'] ?? '');
$payload = $input['payload'] ?? $input ?? [];

function sendResponse($success, $data = [], $error = '') {
    echo json_encode(['success' => $success, 'data' => $data, 'error' => $error]);
    exit;
}

function verifyToken($payload, $pdo) {
    // If we have a valid session role that can manage Chromebooks
    if (isset($_SESSION['llw_role']) && in_array($_SESSION['llw_role'], ['super_admin', 'cb_admin'])) {
        return true;
    }
    // Backward compatibility for existing tokens if any
    if (isset($payload['token']) && $payload['token'] === 'mock-token-admin') {
        return true;
    }
    return false;
}

try {
    switch ($action) {
        case 'login':
            $adminId = $payload['adminId'] ?? '';
            $password = $payload['password'] ?? '';
            
            $stmt = $pdo->prepare("SELECT * FROM llw_users WHERE username = ? AND role IN ('super_admin','cb_admin') AND status='active' LIMIT 1");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                echo json_encode([
                    'success' => true, 
                    'token' => 'mock-token-admin',
                    'adminId' => $admin['admin_id']
                ]);
            } else {
                sendResponse(false, [], 'Admin ID หรือ Password ไม่ถูกต้อง');
            }
            break;

        case 'getData':
            $sheetName = $payload['sheetName'] ?? '';
            if ($sheetName === 'BorrowLog') {
                $stmt = $pdo->query("SELECT b.entry_id, b.borrower_type, b.borrower_id, b.class_name, b.chromebook_serial, b.chromebook_id, b.images, b.status, b.date_borrowed, (SELECT MAX(inspected_date) FROM cb_inspections i WHERE i.borrow_log_id = b.entry_id) as last_inspected FROM cb_borrow_logs b ORDER BY b.entry_id ASC");
                $rows = [];
                while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
                    $rows[] = $r;
                }
                sendResponse(true, $rows);
            } elseif ($sheetName === 'Teachers') {
                $stmt = $pdo->query("SELECT teacher_id, name FROM cb_teachers");
                sendResponse(true, $stmt->fetchAll(PDO::FETCH_NUM));
            } elseif ($sheetName === 'Students') {
                $stmt = $pdo->query("SELECT student_id, name, class_name FROM cb_students");
                sendResponse(true, $stmt->fetchAll(PDO::FETCH_NUM));
            } elseif ($sheetName === 'Chromebooks') {
                $stmt = $pdo->query("SELECT chromebook_id, model, serial_number FROM cb_chromebooks");
                sendResponse(true, $stmt->fetchAll(PDO::FETCH_NUM));
            } elseif ($sheetName === 'Inspections') {
                $stmt = $pdo->query("SELECT id, borrow_log_id, condition_status, inspected_date FROM cb_inspections");
                sendResponse(true, $stmt->fetchAll(PDO::FETCH_NUM));
            } else {
                sendResponse(false, [], "Sheet $sheetName not found");
            }
            break;

        case 'addBorrow':
            if (!verifyToken($payload, $pdo)) { echo json_encode(['needLogin' => true]); exit; }
            
            $bType = $payload['borrowerType'];
            $bId = $payload['borrowerId'];
            $cName = $payload['className'] ?? null;
            $cbId = $payload['chromebookId'];
            $blobs = $payload['imageBlobs'] ?? [];
            
            // Get CB Serial
            $stmt = $pdo->prepare("SELECT serial_number FROM cb_chromebooks WHERE chromebook_id = ?");
            $stmt->execute([$cbId]);
            $cb = $stmt->fetch();
            $serial = $cb['serial_number'] ?? '';
            
            // Handle images
            $savedImages = [];
            foreach ($blobs as $blob) {
                if (preg_match('/^data:image\/(\w+);base64,/', $blob, $type)) {
                    $blob = substr($blob, strpos($blob, ',') + 1);
                    $type = strtolower($type[1]);
                    if (!in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ])) continue;
                    $blob = base64_decode($blob);
                    $filename = uniqid() . '.' . $type;
                    file_put_contents(__DIR__ . '/uploads/' . $filename, $blob);
                    $savedImages[] = $filename;
                }
            }
            $imagesStr = implode(',', $savedImages);
            
            $stmt = $pdo->prepare("INSERT INTO cb_borrow_logs (borrower_type, borrower_id, class_name, chromebook_id, chromebook_serial, images, status, date_borrowed) VALUES (?, ?, ?, ?, ?, ?, 'Borrowed', NOW())");
            $stmt->execute([$bType, $bId, $cName, $cbId, $serial, $imagesStr]);
            sendResponse(true);
            break;

        case 'editBorrow':
            if (!verifyToken($payload, $pdo)) { echo json_encode(['needLogin' => true]); exit; }
            $entryId = $payload['entryId'];
            $blobs = $payload['newImageBlobs'] ?? [];
            
            $stmt = $pdo->prepare("SELECT images FROM cb_borrow_logs WHERE entry_id = ?");
            $stmt->execute([$entryId]);
            $row = $stmt->fetch();
            $existingImages = $row['images'] ? explode(',', $row['images']) : [];
            
            $savedImages = [];
            foreach ($blobs as $blob) {
                if (preg_match('/^data:image\/(\w+);base64,/', $blob, $type)) {
                    $blob = substr($blob, strpos($blob, ',') + 1);
                    $type = strtolower($type[1]);
                    if (!in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ])) continue;
                    $blob = base64_decode($blob);
                    $filename = uniqid() . '.' . $type;
                    file_put_contents(__DIR__ . '/uploads/' . $filename, $blob);
                    $savedImages[] = $filename;
                }
            }
            $allImagesStr = implode(',', array_merge($existingImages, $savedImages));
            
            $stmt = $pdo->prepare("UPDATE cb_borrow_logs SET images = ? WHERE entry_id = ?");
            $stmt->execute([$allImagesStr, $entryId]);
            sendResponse(true);
            break;

        case 'returnBorrow':
            if (!verifyToken($payload, $pdo)) { echo json_encode(['needLogin' => true]); exit; }
            $entryId = $payload['entryId'];
            $stmt = $pdo->prepare("UPDATE cb_borrow_logs SET status = 'Returned', date_returned = NOW() WHERE entry_id = ?");
            $stmt->execute([$entryId]);
            sendResponse(true);
            break;

        case 'addTeacher':
            if (!verifyToken($payload, $pdo)) { echo json_encode(['needLogin' => true]); exit; }
            $id = $payload['teacher_id'];
            $name = $payload['name'];
            $stmt = $pdo->prepare("INSERT INTO cb_teachers (teacher_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = ?");
            $stmt->execute([$id, $name, $name]);
            sendResponse(true);
            break;

        case 'addStudent':
            if (!verifyToken($payload, $pdo)) { echo json_encode(['needLogin' => true]); exit; }
            $id = $payload['student_id'];
            $name = $payload['name'];
            $className = $payload['class_name'];
            $stmt = $pdo->prepare("INSERT INTO cb_students (student_id, name, class_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = ?, class_name = ?");
            $stmt->execute([$id, $name, $className, $name, $className]);
            sendResponse(true);
            break;

        case 'addChromebook':
            if (!verifyToken($payload, $pdo)) { echo json_encode(['needLogin' => true]); exit; }
            $id = $payload['chromebook_id'];
            $model = $payload['model'];
            $serial = $payload['serial_number'];
            $stmt = $pdo->prepare("INSERT INTO cb_chromebooks (chromebook_id, model, serial_number) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE model = ?, serial_number = ?");
            $stmt->execute([$id, $model, $serial, $model, $serial]);
            sendResponse(true);
            break;

        case 'addInspection':
            if (!verifyToken($payload, $pdo)) { echo json_encode(['needLogin' => true]); exit; }
            $entryId = $payload['entryId'];
            $condition = $payload['condition'];
            $notes = $payload['notes'] ?? '';
            $blobs = $payload['imageBlobs'] ?? [];
            
            $savedImages = [];
            foreach ($blobs as $blob) {
                if (preg_match('/^data:image\/(\w+);base64,/', $blob, $type)) {
                    $blob = substr($blob, strpos($blob, ',') + 1);
                    $type = strtolower($type[1]);
                    if (!in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ])) continue;
                    $blob = base64_decode($blob);
                    $filename = uniqid() . '.' . $type;
                    file_put_contents(__DIR__ . '/uploads/' . $filename, $blob);
                    $savedImages[] = $filename;
                }
            }
            $imagesStr = implode(',', $savedImages);
            
            $stmt = $pdo->prepare("INSERT INTO cb_inspections (borrow_log_id, condition_status, notes, images) VALUES (?, ?, ?, ?)");
            $stmt->execute([$entryId, $condition, $notes, $imagesStr]);
            sendResponse(true);
            break;

        case 'deleteBorrow':
            if (!verifyToken($payload, $pdo)) { echo json_encode(['needLogin' => true]); exit; }
            $entryId = $payload['entryId'];
            
            $stmt = $pdo->prepare("SELECT images FROM cb_borrow_logs WHERE entry_id = ?");
            $stmt->execute([$entryId]);
            if ($row = $stmt->fetch()) {
                if ($row['images']) {
                    foreach (explode(',', $row['images']) as $img) {
                        $path = __DIR__ . '/uploads/' . $img;
                        if (file_exists($path)) unlink($path);
                    }
                }
            }
            $stmt = $pdo->prepare("DELETE FROM cb_borrow_logs WHERE entry_id = ?");
            $stmt->execute([$entryId]);
            sendResponse(true);
            break;

        default:
            sendResponse(false, [], "Unknown action $action");
    }
} catch (Exception $e) {
    sendResponse(false, [], $e->getMessage());
}
?>
