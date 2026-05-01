<?php
/**
 * bus/config.php — Bus System Shared Helpers
 */
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

function busGetSemester(): string {
    $m = (int)date('n');
    $y = (int)date('Y') + 543;
    if ($m >= 5 && $m <= 10) return $y . '-1';
    if ($m >= 11) return $y . '-2';
    return ($y - 1) . '-2'; // Jan–Apr still in previous year's semester 2
}

function busSemesterLabel(string $sem): string {
    [$y, $n] = explode('-', $sem . '-1');
    return 'ภาคเรียนที่ ' . $n . ' ปีการศึกษา ' . $y;
}

function busMaskNid(string $nid): string {
    $d = preg_replace('/\D/', '', $nid);
    if (strlen($d) !== 13) return 'x-xxxx-xxxxx-xx-x';
    return $d[0] . '-' . substr($d, 1, 4) . '-xxxxx-xx-' . $d[12];
}

function busRequireStudent(): void {
    if (!isset($_SESSION['bus_student_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header('Location: /bus/index.php?redirect=' . $redirect);
        exit();
    }
}

function busRequireStaff(array $roles = ['bus_admin', 'bus_finance', 'super_admin', 'wfh_admin']): void {
    if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], $roles, true)) {
        header('Location: /login.php');
        exit();
    }
}

function busCanAdmin(): bool {
    return isset($_SESSION['llw_role']) && in_array($_SESSION['llw_role'], ['bus_admin', 'super_admin', 'wfh_admin'], true);
}

function busCanFinance(): bool {
    return isset($_SESSION['llw_role']) && in_array($_SESSION['llw_role'], ['bus_admin', 'bus_finance', 'super_admin', 'wfh_admin'], true);
}

// Return student's total paid for a registration
function busGetPaid(PDO $pdo, int $regId): float {
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM bus_payments WHERE registration_id = ?");
    $s->execute([$regId]);
    return (float)$s->fetchColumn();
}
