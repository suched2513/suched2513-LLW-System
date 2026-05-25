<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo     = getPdo();
$user_id = (int)$_SESSION['user_id'];

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM health_records WHERE id=?")->execute([$id]);
    header('Location: /health/students.php'); exit();
}

// POST save / update
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /health/record.php'); exit(); }

$id          = (int)($_POST['id'] ?? 0);
$student_id  = (int)($_POST['student_id'] ?? 0);
$weight      = (float)($_POST['weight_kg'] ?? 0);
$height      = (float)($_POST['height_cm'] ?? 0);
$waist       = strlen(trim($_POST['waist_cm'] ?? '')) ? (float)$_POST['waist_cm'] : null;
$record_date = trim($_POST['record_date'] ?? '');
$semester    = (int)($_POST['semester'] ?? 1);
$acad_year   = (int)($_POST['academic_year'] ?? 2569);
$note        = trim($_POST['note'] ?? '');

// Validation
if (!$student_id || $weight < 10 || $height < 80 || !$record_date) {
    header('Location: /health/record.php' . ($id ? "?id=$id" : '') . '&err=invalid'); exit();
}
if (!in_array($semester, [1, 2])) $semester = 1;

// Fetch student DOH data
$stu = $pdo->prepare("SELECT gender, birthdate FROM att_students WHERE id=?");
$stu->execute([$student_id]);
$stu = $stu->fetch();

// Compute statuses
$bfa_status = null;
$hfa_status = null;

if ($stu && $stu['birthdate']) {
    $born      = new DateTime($stu['birthdate']);
    $measure   = new DateTime($record_date);
    $diff      = $born->diff($measure);
    $age_m     = (int)($diff->y * 12 + $diff->m);
    $gender    = $stu['gender'] === 'female' ? 'female' : 'male';
    $bmi       = $weight / (($height / 100) ** 2);

    $std = $pdo->prepare("SELECT * FROM health_growth_standards WHERE gender=? AND age_month=?");
    $std->execute([$gender, $age_m]);
    $row = $std->fetch();

    if ($row) {
        $bfa_status = classifySD($bmi,    $row['bfa_neg3'], $row['bfa_neg2'], $row['bfa_neg1'], $row['bfa_median'], $row['bfa_pos1'], $row['bfa_pos2'], 'bfa');
        $hfa_status = classifySD($height, $row['hfa_neg3'], $row['hfa_neg2'], $row['hfa_neg1'], $row['hfa_median'], $row['hfa_pos1'], $row['hfa_pos2'], 'hfa');
    } else {
        $bfa_status = simpleBfaStatus($bmi, $age_m);
    }
}

try {
    $pdo->beginTransaction();

    if ($id) {
        $st = $pdo->prepare("
            UPDATE health_records
            SET student_id=?, weight_kg=?, height_cm=?, waist_cm=?, record_date=?,
                semester=?, academic_year=?, bfa_status=?, hfa_status=?, note=?, recorded_by=?
            WHERE id=?
        ");
        $st->execute([$student_id, $weight, $height, $waist, $record_date, $semester, $acad_year, $bfa_status, $hfa_status, $note, $user_id, $id]);
    } else {
        $st = $pdo->prepare("
            INSERT INTO health_records
                (student_id, weight_kg, height_cm, waist_cm, record_date, semester, academic_year, bfa_status, hfa_status, note, recorded_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $st->execute([$student_id, $weight, $height, $waist, $record_date, $semester, $acad_year, $bfa_status, $hfa_status, $note, $user_id]);
        $id = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
    header('Location: /health/students.php?year=' . $acad_year . '&semester=' . $semester . '&saved=1'); exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    header('Location: /health/record.php' . ($id ? "?id=$id" : '') . '&err=db'); exit();
}

// ── Helpers ────────────────────────────────────────────────────────────────

function classifySD(float $val, $n3, $n2, $n1, $med, $p1, $p2, string $type): string {
    if ($n3 === null || $med === null) return '—';
    if ($type === 'bfa') {
        if ($val < $n3) return 'ผอมมาก';
        if ($val < $n2) return 'ผอม';
        if ($val <= $p1) return 'สมส่วน';
        if ($val <= $p2) return 'น้ำหนักเกิน';
        return 'อ้วน';
    }
    if ($val < $n3) return 'เตี้ยมาก';
    if ($val < $n2) return 'เตี้ย';
    if ($val <= $p2) return 'ปกติ';
    return 'สูง';
}

function simpleBfaStatus(float $bmi, int $age_months): string {
    $age_years = $age_months / 12;
    if ($age_years < 5) {
        if ($bmi < 14.0) return 'ผอมมาก';
        if ($bmi < 15.0) return 'ผอม';
        if ($bmi <= 18.0) return 'สมส่วน';
        if ($bmi <= 20.0) return 'น้ำหนักเกิน';
        return 'อ้วน';
    }
    if ($bmi < 14.5) return 'ผอมมาก';
    if ($bmi < 16.0) return 'ผอม';
    if ($bmi <= 22.0) return 'สมส่วน';
    if ($bmi <= 25.0) return 'น้ำหนักเกิน';
    return 'อ้วน';
}
