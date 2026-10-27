<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use PDO;

/**
 * Class AuditLogController
 * ดูประวัติการใช้งานระบบ (Audit Trail) และส่งออกไฟล์ CSV
 */
class AuditLogController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('audit_logs', 'view');
        $pdo = Database::getConnection();

        $module = trim((string)$this->request->query('module', ''));
        $action = trim((string)$this->request->query('action', ''));
        $dateFrom = trim((string)$this->request->query('date_from', ''));
        $dateTo = trim((string)$this->request->query('date_to', ''));
        $userId = (int)$this->request->query('user_id', 0);

        $sql = "
            SELECT a.*, u.username, u.full_name
            FROM fin_audit_logs a
            LEFT JOIN fin_users u ON a.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($module !== '') {
            $sql .= " AND a.module = ?";
            $params[] = $module;
        }
        if ($action !== '') {
            $sql .= " AND a.action = ?";
            $params[] = $action;
        }
        if ($userId > 0) {
            $sql .= " AND a.user_id = ?";
            $params[] = $userId;
        }
        if ($dateFrom !== '') {
            $sql .= " AND a.created_at >= ?";
            $params[] = "{$dateFrom} 00:00:00";
        }
        if ($dateTo !== '') {
            $sql .= " AND a.created_at <= ?";
            $params[] = "{$dateTo} 23:59:59";
        }

        $sql .= " ORDER BY a.id DESC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Get filter dropdown options
        $modules = [];
        try {
            $modules = $pdo->query("SELECT DISTINCT module FROM fin_audit_logs WHERE module IS NOT NULL AND module != '' ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {}

        $actions = [];
        try {
            $actions = $pdo->query("SELECT DISTINCT action FROM fin_audit_logs WHERE action IS NOT NULL AND action != '' ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {}

        $users = [];
        try {
            $users = $pdo->query("SELECT id, username, full_name FROM fin_users ORDER BY full_name ASC")->fetchAll();
        } catch (\Throwable $e) {}

        $this->render('audit_logs/index', [
            'pageTitle' => 'ประวัติการใช้งานระบบ (Audit Trail)',
            'logs'      => $logs,
            'modules'   => $modules,
            'actions'   => $actions,
            'users'     => $users,
            'filter'    => [
                'module'    => $module,
                'action'    => $action,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'user_id'   => $userId,
            ]
        ]);
    }

    public function exportCsv(): void
    {
        $this->checkPermission('audit_logs', 'export');
        $pdo = Database::getConnection();

        $module = trim((string)$this->request->query('module', ''));
        $action = trim((string)$this->request->query('action', ''));
        $dateFrom = trim((string)$this->request->query('date_from', ''));
        $dateTo = trim((string)$this->request->query('date_to', ''));
        $userId = (int)$this->request->query('user_id', 0);

        $sql = "
            SELECT a.id, a.created_at, u.username, u.full_name, a.module, a.action, a.table_name, a.record_id, a.notes, a.ip_address, a.status
            FROM fin_audit_logs a
            LEFT JOIN fin_users u ON a.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($module !== '') {
            $sql .= " AND a.module = ?";
            $params[] = $module;
        }
        if ($action !== '') {
            $sql .= " AND a.action = ?";
            $params[] = $action;
        }
        if ($userId > 0) {
            $sql .= " AND a.user_id = ?";
            $params[] = $userId;
        }
        if ($dateFrom !== '') {
            $sql .= " AND a.created_at >= ?";
            $params[] = "{$dateFrom} 00:00:00";
        }
        if ($dateTo !== '') {
            $sql .= " AND a.created_at <= ?";
            $params[] = "{$dateTo} 23:59:59";
        }

        $sql .= " ORDER BY a.id DESC LIMIT 1000";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        $csvData = [
            ['รหัส', 'วัน-เวลา', 'ชื่อผู้ใช้', 'ชื่อ-นามสกุล', 'โมดูล', 'การกระทำ', 'ตาราง', 'รหัสแถวข้อมูล', 'หมายเหตุ', 'IP Address', 'สถานะ']
        ];

        foreach ($logs as $l) {
            $csvData[] = [
                $l['id'],
                thai_date($l['created_at'], true),
                $l['username'] ?? 'System/Guest',
                $l['full_name'] ?? '-',
                $l['module'],
                $l['action'],
                $l['table_name'] ?? '-',
                $l['record_id'] ?? '-',
                $l['notes'] ?? '-',
                $l['ip_address'],
                $l['status']
            ];
        }

        Response::csv($csvData, 'audit_logs_' . date('Ymd_His') . '.csv');
    }
}
