<?php
/**
 * assembly/api/import_students.php
 * POST multipart — นำเข้านักเรียนจาก CSV
 * CSV columns: student_id, name, classroom, teacher_name
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if (!in_array($_SESSION['llw_role'], ['super_admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'เฉพาะ Super Admin เท่านั้น']);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเลือกไฟล์ CSV']);
    exit;
}

// validate extension
$ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไฟล์ต้องเป็น .csv เท่านั้น']);
    exit;
}

try {
    $pdo = getPdo();

    $file    = fopen($_FILES['csv_file']['tmp_name'], 'r');
    // ลบ BOM ถ้ามี
    $bom     = fread($file, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($file);

    $headers = fgetcsv($file); // skip header row
    if (!$headers) {
        echo json_encode(['status' => 'error', 'message' => 'ไฟล์ CSV ว่างเปล่า']); exit;
    }

    $inserted = 0; $updated = 0; $errors = [];

    $studentStmt = $pdo->prepare("
        INSERT INTO assembly_students (student_id, name, classroom)
        VALUES (:student_id, :name, :classroom)
        ON DUPLICATE KEY UPDATE name = VALUES(name), classroom = VALUES(classroom)
    ");

    $classroomStmt = $pdo->prepare("
        INSERT INTO assembly_classrooms (classroom, teacher_name)
        VALUES (:classroom, :teacher_name)
        ON DUPLICATE KEY UPDATE teacher_name = VALUES(teacher_name)
    ");

    $lineNum = 1;
    while (($row = fgetcsv($file)) !== false) {
        $lineNum++;
        if (count($row) < 3) {
            $errors[] = "บรรทัด $lineNum: คอลัมน์ไม่ครบ";
            continue;
        }

        $studentId   = trim($row[0]);
        $name        = trim($row[1]);
        $classroom   = trim($row[2]);
        $teacherName = trim($row[3] ?? '');

        if ($studentId === '' || $name === '' || $classroom === '') {
            $errors[] = "บรรทัด $lineNum: ข้อมูลไม่ครบ";
            continue;
        }

        // upsert student
        $studentStmt->execute([
            ':student_id' => $studentId,
            ':name'       => $name,
            ':classroom'  => $classroom,
        ]);

        // upsert classroom (with teacher)
        if ($teacherName !== '') {
            $classroomStmt->execute([
                ':classroom'   => $classroom,
                ':teacher_name'=> $teacherName,
            ]);
        } else {
            $pdo->prepare("INSERT IGNORE INTO assembly_classrooms (classroom) VALUES (?)")->execute([$classroom]);
        }

        $inserted++;
    }
    fclose($file);

    echo json_encode([
        'status'   => 'success',
        'imported' => $inserted,
        'errors'   => $errors,
        'message'  => "นำเข้าสำเร็จ $inserted รายการ" . (count($errors) > 0 ? ', มีข้อผิดพลาด ' . count($errors) . ' บรรทัด' : ''),
    ]);
} catch (Exception $e) {
    error_log('[Assembly] import_students: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
