<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

$action = $_GET['action'] ?? '';
$input  = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? $action;
}

$sid = trim($_GET['sid'] ?? ($input['sid'] ?? ''));
if (!$sid) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุรหัสนักเรียน']);
    exit;
}
if (preg_match('/^\d+$/', $sid)) {
    $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
}

try {
    $pdo = getPdo();

    // Resolve bus_students.id from student_id VARCHAR
    $sStmt = $pdo->prepare("SELECT id, fullname FROM bus_students WHERE student_id = ? AND is_active = 1 LIMIT 1");
    $sStmt->execute([$sid]);
    $student = $sStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['status' => 'success', 'data' => null]);
        exit;
    }

    $busStudentId = (int)$student['id'];

    if ($action === 'get_info') {
        // Current registration + route info
        $rStmt = $pdo->prepare("
            SELECT r.id AS reg_id, r.status AS reg_status, r.semester,
                   rt.route_name, rt.price
            FROM bus_registrations r
            LEFT JOIN bus_routes rt ON rt.id = r.route_id
            WHERE r.student_id = ?
            ORDER BY r.id DESC
            LIMIT 1
        ");
        $rStmt->execute([$busStudentId]);
        $reg = $rStmt->fetch(PDO::FETCH_ASSOC);

        $data = [
            'route_name' => $reg['route_name'] ?? null,
            'reg_status' => $reg['reg_status'] ?? null,
            'semester'   => $reg['semester']   ?? null,
            'price'      => (float)($reg['price'] ?? 0),
            'payments'   => [],
            'total_paid' => 0,
            'balance'    => (float)($reg['price'] ?? 0),
            'has_pending_cancel' => false,
        ];

        if ($reg) {
            $regId = (int)$reg['reg_id'];

            // Payments via registration_id
            $pStmt = $pdo->prepare("
                SELECT amount, paid_at AS payment_date, note
                FROM bus_payments
                WHERE registration_id = ?
                ORDER BY paid_at DESC
            ");
            $pStmt->execute([$regId]);
            $data['payments'] = $pStmt->fetchAll(PDO::FETCH_ASSOC);

            $totalPaid = array_sum(array_column($data['payments'], 'amount'));
            $data['total_paid'] = $totalPaid;
            $data['balance']    = max(0, $data['price'] - $totalPaid);

            // Pending cancel
            $cStmt = $pdo->prepare("
                SELECT COUNT(*) FROM bus_cancel_requests
                WHERE registration_id = ? AND status = 'pending'
            ");
            $cStmt->execute([$regId]);
            $data['has_pending_cancel'] = (int)$cStmt->fetchColumn() > 0;
        }

        echo json_encode(['status' => 'success', 'data' => $data]);

    } elseif ($action === 'request_cancel') {
        $citizenId = preg_replace('/\D/', '', $input['citizen_id'] ?? '');
        $reason    = trim($input['reason'] ?? '');

        if (strlen($citizenId) !== 13) {
            echo json_encode(['status' => 'error', 'message' => 'เลขบัตรประชาชนต้องมี 13 หลัก']);
            exit;
        }

        // Re-verify national ID
        $vStmt = $pdo->prepare("SELECT national_id_hash FROM bus_students WHERE id = ?");
        $vStmt->execute([$busStudentId]);
        $hash = $vStmt->fetchColumn();

        if (!$hash || !password_verify($citizenId, $hash)) {
            echo json_encode(['status' => 'error', 'message' => 'เลขบัตรประชาชนไม่ถูกต้อง']);
            exit;
        }

        // Get active registration
        $rStmt = $pdo->prepare("SELECT id FROM bus_registrations WHERE student_id = ? AND status = 'active' LIMIT 1");
        $rStmt->execute([$busStudentId]);
        $regId = (int)$rStmt->fetchColumn();

        if (!$regId) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบการลงทะเบียนที่ใช้งานอยู่']);
            exit;
        }

        // Check no pending request already
        $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM bus_cancel_requests WHERE registration_id = ? AND status = 'pending'");
        $chkStmt->execute([$regId]);
        if ((int)$chkStmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'มีคำขอยกเลิกรอดำเนินการอยู่แล้ว']);
            exit;
        }

        $pdo->beginTransaction();
        $insStmt = $pdo->prepare("INSERT INTO bus_cancel_requests (registration_id, reason, status) VALUES (?, ?, 'pending')");
        $insStmt->execute([$regId, $reason]);
        $updStmt = $pdo->prepare("UPDATE bus_registrations SET status = 'pending_cancel' WHERE id = ?");
        $updStmt->execute([$regId]);
        $pdo->commit();

        echo json_encode(['status' => 'success', 'message' => 'ส่งคำขอยกเลิกสำเร็จ รอการตรวจสอบจากเจ้าหน้าที่']);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'action ไม่ถูกต้อง']);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[bus/student_actions] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
