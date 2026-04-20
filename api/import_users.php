<?php
/**
 * api/import_users.php — นำเข้าผู้ใช้งานจากไฟล์ CSV
 * Roles: super_admin เท่านั้น
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/database.php';

// Auth guard: ต้องเป็น super_admin เท่านั้น
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

if (!isset($_FILES['csv_file'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์ที่อัปโหลด']);
    exit;
}

try {
    $pdo = getPdo();
    $filePath = $_FILES['csv_file']['tmp_name'];
    
    // อ่านข้อมูลทั้งหมดเพื่อตรวจสอบและแปลง Encoding
    $content = file_get_contents($filePath);
    
    // ตรวจจับ Encoding (เน้น UTF-8 และ Windows-874/TIS-620 สำหรับภาษาไทย)
    $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-874', 'TIS-620'], true);
    
    if ($encoding !== 'UTF-8') {
        // ถ้าไม่ใช่ UTF-8 ให้แปลงเป็น UTF-8 (ส่วนใหญ่ไฟล์จาก Excel จะเป็น Windows-874)
        $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'Windows-874');
    }

    // ลบ BOM (Byte Order Mark) ถ้าติดมาจาก Excel UTF-8
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    // สร้าง Stream ชั่วคราวจากข้อมูลที่แปลงแล้วเพื่อใช้กับ fgetcsv
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    $pdo->beginTransaction();

    $successCount = 0;
    $skipCount = 0;
    $errors = [];
    $line = 0;

    $allowedRoles = ['super_admin', 'wfh_admin', 'wfh_staff', 'cb_admin', 'att_teacher'];

    // เตรียม Statement สำหรับตรวจสอบ username ซ้ำ
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM llw_users WHERE username = ?");
    
    // เตรียม Statement สำหรับเพิ่มข้อมูล
    $insertStmt = $pdo->prepare("
        INSERT INTO llw_users (username, firstname, lastname, password, role, status) 
        VALUES (?, ?, ?, ?, ?, 'active')
    ");

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $line++;
        
        // ข้ามแถวที่ว่าง หรือมีข้อมูลไม่ครบ (อย่างน้อยต้องมี username, password, role)
        if (count($data) < 5 || empty(trim($data[0]))) {
            $skipCount++;
            continue;
        }

        $username  = trim($data[0]);
        $firstname = trim($data[1]);
        $lastname  = trim($data[2]);
        $password  = trim($data[3]);
        $role      = trim($data[4]);

        // ตรวจสอบ username ซ้ำ
        $checkStmt->execute([$username]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = "บรรทัดที่ $line: Username '$username' มีอยู่ในระบบแล้ว";
            $skipCount++;
            continue;
        }

        // ตรวจสอบ Role
        if (!in_array($role, $allowedRoles)) {
            $errors[] = "บรรทัดที่ $line: Role '$role' ไม่ถูกต้อง";
            $skipCount++;
            continue;
        }

        // ตรวจสอบความยาวรหัสผ่าน
        if (strlen($password) < 6) {
            $errors[] = "บรรทัดที่ $line: รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร (User: $username)";
            $skipCount++;
            continue;
        }

        // Hash รหัสผ่านและบันทึก
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $insertStmt->execute([$username, $firstname, $lastname, $hash, $role]);
        $successCount++;
    }

    fclose($handle);
    $pdo->commit();

    $msg = "นำเข้าสำเร็จ $successCount รายการ";
    if ($skipCount > 0) $msg .= " (ข้าม $skipCount รายการ)";
    
    echo json_encode([
        'status'  => 'success',
        'message' => $msg,
        'details' => [
            'success' => $successCount,
            'skipped' => $skipCount,
            'errors'  => $errors
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[API Import Users] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการประมวลผลไฟล์']);
}
