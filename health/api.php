<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}
if (!in_array($_SESSION['llw_role'], ['super_admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

$pdo    = getPdo();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'students_by_class':
            $classroom = trim($_GET['classroom'] ?? '');
            if (!$classroom) { echo json_encode([]); exit; }
            $st = $pdo->prepare("
                SELECT id, name, gender, birthdate
                FROM att_students
                WHERE classroom=? AND status='active'
                  AND student_id REGEXP '^[0-9]+$'
                  AND student_id NOT IN (SELECT subject_code FROM att_subjects)
                ORDER BY name
            ");
            $st->execute([$classroom]);
            echo json_encode($st->fetchAll());
            break;

        case 'evaluate':
            $sid    = (int)($_GET['student_id'] ?? 0);
            $weight = (float)($_GET['weight'] ?? 0);
            $height = (float)($_GET['height'] ?? 0);
            if (!$sid || !$weight || !$height) {
                echo json_encode(['bfa_status' => null, 'hfa_status' => null]);
                exit;
            }
            $st = $pdo->prepare("SELECT gender, birthdate FROM att_students WHERE id=?");
            $st->execute([$sid]);
            $stu = $st->fetch();
            if (!$stu || !$stu['birthdate']) {
                // No birthdate — still return simple BMI estimate without age
                $bmi = $weight / (($height / 100) ** 2);
                echo json_encode([
                    'bfa_status' => simpleBfaStatus($bmi, 0),
                    'hfa_status' => null,
                    'bmi'        => round($bmi, 2),
                    'age_months' => null,
                ]);
                exit;
            }
            $bmi        = $weight / (($height / 100) ** 2);
            $age_months = ageInMonths($stu['birthdate']);
            // att_students.gender uses Thai values: ชาย/หญิง
            $gender     = ($stu['gender'] === 'หญิง') ? 'female' : 'male';

            $std = $pdo->prepare("SELECT * FROM health_growth_standards WHERE gender=? AND age_month=?");
            $std->execute([$gender, $age_months]);
            $row = $std->fetch();

            $bfa_status = null;
            $hfa_status = null;

            if ($row) {
                $bfa_status = classifySD($bmi,    $row['bfa_neg3'], $row['bfa_neg2'], $row['bfa_pos1'], $row['bfa_pos2'], 'bfa');
                $hfa_status = classifySD($height, $row['hfa_neg3'], $row['hfa_neg2'], $row['hfa_pos1'], $row['hfa_pos2'], 'hfa');
            } else {
                // Fallback: simple WHO/DOH cutoffs for school-age
                $bfa_status = simpleBfaStatus($bmi, $age_months);
                $hfa_status = null;
            }

            echo json_encode([
                'bfa_status' => $bfa_status,
                'hfa_status' => $hfa_status,
                'bmi'        => round($bmi, 2),
                'age_months' => $age_months,
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}

// ── Helpers ────────────────────────────────────────────────────────────────

function ageInMonths(string $birthdate): int {
    try {
        $born = new DateTime($birthdate);
        $now  = new DateTime();
        $diff = $born->diff($now);
        return (int)($diff->y * 12 + $diff->m);
    } catch (Exception $e) {
        return 0;
    }
}

function classifySD(float $val, $n3, $n2, $p1, $p2, string $type): string {
    if ($n3 === null) return '—';
    if ($type === 'bfa') {
        if ($val < $n3) return 'ผอมมาก';
        if ($val < $n2) return 'ผอม';
        if ($val <= $p1) return 'สมส่วน';
        if ($val <= $p2) return 'น้ำหนักเกิน';
        return 'อ้วน';
    }
    // hfa
    if ($val < $n3) return 'เตี้ยมาก';
    if ($val < $n2) return 'เตี้ย';
    if ($val <= $p2) return 'ปกติ';
    return 'สูง';
}

function simpleBfaStatus(float $bmi, int $age_months): string {
    // Simple age-group cutoffs when no DOH table loaded
    $age_years = $age_months / 12;
    if ($age_years < 5) {
        if ($bmi < 14.0) return 'ผอมมาก';
        if ($bmi < 15.0) return 'ผอม';
        if ($bmi <= 18.0) return 'สมส่วน';
        if ($bmi <= 20.0) return 'น้ำหนักเกิน';
        return 'อ้วน';
    }
    // School-age approximate (WHO 2007 reference)
    if ($bmi < 14.5) return 'ผอมมาก';
    if ($bmi < 16.0) return 'ผอม';
    if ($bmi <= 22.0) return 'สมส่วน';
    if ($bmi <= 25.0) return 'น้ำหนักเกิน';
    return 'อ้วน';
}
