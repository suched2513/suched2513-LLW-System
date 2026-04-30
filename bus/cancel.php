<?php
session_start();
require_once __DIR__ . '/config.php';
busRequireStudent();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bus/dashboard.php');
    exit();
}

csrf_verify();

$pdo      = getPdo();
$busId    = (int)$_SESSION['bus_student_id'];
$semester = busGetSemester();
$nid      = preg_replace('/\D/', '', $_POST['national_id'] ?? '');
$reason   = trim($_POST['reason'] ?? '');

if (strlen($nid) !== 13) {
    header('Location: /bus/dashboard.php?msg=' . urlencode('กรุณากรอกเลขบัตรประชาชน 13 หลักให้ถูกต้อง') . '&t=err');
    exit();
}

if ($reason === '') {
    header('Location: /bus/dashboard.php?msg=' . urlencode('กรุณาระบุเหตุผลในการยกเลิก') . '&t=err');
    exit();
}

try {
    // Verify national ID
    $stuStmt = $pdo->prepare("SELECT national_id_hash FROM bus_students WHERE id = ? AND is_active = 1");
    $stuStmt->execute([$busId]);
    $student = $stuStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student || !password_verify($nid, $student['national_id_hash'])) {
        header('Location: /bus/dashboard.php?msg=' . urlencode('เลขบัตรประชาชนไม่ถูกต้อง') . '&t=err');
        exit();
    }

    // Get active registration for this semester
    $regStmt = $pdo->prepare("SELECT id FROM bus_registrations WHERE student_id = ? AND semester = ? AND status = 'active'");
    $regStmt->execute([$busId, $semester]);
    $reg = $regStmt->fetch(PDO::FETCH_ASSOC);

    if (!$reg) {
        header('Location: /bus/dashboard.php?msg=' . urlencode('ไม่พบการลงทะเบียนที่สามารถยกเลิกได้') . '&t=err');
        exit();
    }

    // Check no pending cancel request already exists
    $existStmt = $pdo->prepare("SELECT id FROM bus_cancel_requests WHERE registration_id = ? AND status = 'pending'");
    $existStmt->execute([$reg['id']]);
    if ($existStmt->fetch()) {
        header('Location: /bus/dashboard.php?msg=' . urlencode('คำขอยกเลิกของคุณกำลังรอการพิจารณาอยู่แล้ว') . '&t=err');
        exit();
    }

    $pdo->beginTransaction();

    $insStmt = $pdo->prepare("INSERT INTO bus_cancel_requests (registration_id, reason, status) VALUES (?, ?, 'pending')");
    $insStmt->execute([$reg['id'], $reason]);

    $updStmt = $pdo->prepare("UPDATE bus_registrations SET status = 'pending_cancel' WHERE id = ?");
    $updStmt->execute([$reg['id']]);

    $pdo->commit();

    header('Location: /bus/dashboard.php?msg=' . urlencode('ส่งคำขอยกเลิกเรียบร้อยแล้ว กรุณารอการอนุมัติจากเจ้าหน้าที่') . '&t=ok');
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    header('Location: /bus/dashboard.php?msg=' . urlencode('เกิดข้อผิดพลาด กรุณาลองใหม่') . '&t=err');
    exit();
}
