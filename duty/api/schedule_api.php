<?php
/**
 * duty/api/schedule_api.php — AJAX API สำหรับระบบจัดตารางเวร
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin','wfh_admin'])) {
    http_response_code(401);
    echo json_encode(['status'=>'error','message'=>'กรุณาเข้าสู่ระบบ']);
    exit;
}

$pdo    = getPdo();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Auto-migrate: สร้างตาราง duty_groups system ถ้ายังไม่มี ──
try {
    $pdo->query("SELECT 1 FROM duty_groups LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS duty_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT '#6c757d',
        description TEXT, sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS duty_group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL, teacher_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_gm (group_id, teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS duty_day_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        duty_date DATE NOT NULL,
        shift ENUM('day','night') DEFAULT 'day',
        group_id INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_ddg (duty_date, shift)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
// ── Auto-migrate: group_id ใน duty_schedule ──
try {
    $c = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='duty_schedule' AND COLUMN_NAME='group_id'");
    if ((int)$c->fetchColumn() === 0)
        $pdo->exec("ALTER TABLE duty_schedule ADD COLUMN group_id INT NULL");
} catch (Exception $e) { /* ignore */ }

// CSRF สำหรับ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['status'=>'error','message'=>'CSRF invalid']);
        exit;
    }
}

try {
    switch ($action) {

        // ── GET: ดึง week data ──────────────────────────────────────────
        case 'get_week': {
            $weekStart = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
            $weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));

            $rows = $pdo->prepare("
                SELECT ddg.duty_date, ddg.shift, ddg.group_id,
                       g.name AS group_name, g.color AS group_color,
                       COUNT(m.id) AS member_count,
                       COALESCE(pc.cnt,0) AS point_count
                FROM duty_day_groups ddg
                LEFT JOIN duty_groups g ON g.id = ddg.group_id
                LEFT JOIN duty_group_members m ON m.group_id = g.id
                LEFT JOIN (
                    SELECT duty_date, shift, COUNT(*) AS cnt
                    FROM duty_schedule
                    WHERE duty_date BETWEEN ? AND ?
                    GROUP BY duty_date, shift
                ) pc ON pc.duty_date=ddg.duty_date AND pc.shift=ddg.shift
                WHERE ddg.duty_date BETWEEN ? AND ?
                GROUP BY ddg.id
            ");
            $rows->execute([$weekStart, $weekEnd, $weekStart, $weekEnd]);
            $data = [];
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $data[$r['duty_date']][$r['shift']] = $r;
            }
            echo json_encode(['status'=>'success','data'=>$data]);
            break;
        }

        // ── POST: assign group to day+shift ────────────────────────────
        case 'assign_group': {
            $date    = $_POST['duty_date'] ?? '';
            $shift   = in_array($_POST['shift']??'',['day','night']) ? $_POST['shift'] : 'day';
            $groupId = (int)($_POST['group_id']??0) ?: null;
            if (!$date) throw new Exception('ไม่ระบุวันที่');

            $pdo->prepare("
                INSERT INTO duty_day_groups (duty_date, shift, group_id) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE group_id=VALUES(group_id), updated_at=NOW()
            ")->execute([$date, $shift, $groupId]);

            // ดึง group info ส่งกลับ
            $g = null;
            if ($groupId) {
                $s = $pdo->prepare("SELECT g.*, COUNT(m.id) as member_count FROM duty_groups g LEFT JOIN duty_group_members m ON m.group_id=g.id WHERE g.id=? GROUP BY g.id");
                $s->execute([$groupId]);
                $g = $s->fetch(PDO::FETCH_ASSOC);
            }
            echo json_encode(['status'=>'success','group'=>$g]);
            break;
        }

        // ── POST: remove group from day+shift ──────────────────────────
        case 'remove_group': {
            $date  = $_POST['duty_date'] ?? '';
            $shift = in_array($_POST['shift']??'',['day','night']) ? $_POST['shift'] : 'day';
            $pdo->prepare("DELETE FROM duty_day_groups WHERE duty_date=? AND shift=?")->execute([$date,$shift]);
            $pdo->prepare("DELETE FROM duty_schedule WHERE duty_date=? AND shift=?")->execute([$date,$shift]);
            echo json_encode(['status'=>'success']);
            break;
        }

        // ── GET: รายละเอียดวัน+กะ (สมาชิกกลุ่ม + จุดที่จัดแล้ว) ──────
        case 'get_day_detail': {
            $date  = $_GET['duty_date'] ?? '';
            $shift = $_GET['shift'] ?? 'day';

            $ddg = $pdo->prepare("
                SELECT ddg.*, g.name AS group_name, g.color AS group_color
                FROM duty_day_groups ddg
                LEFT JOIN duty_groups g ON g.id=ddg.group_id
                WHERE ddg.duty_date=? AND ddg.shift=?
            ");
            $ddg->execute([$date,$shift]);
            $dayGroup = $ddg->fetch(PDO::FETCH_ASSOC);

            $members = [];
            if ($dayGroup && $dayGroup['group_id']) {
                $ms = $pdo->prepare("
                    SELECT dt.id, dt.prefix, dt.full_name,
                           ds.id AS schedule_id, ds.point_no, ds.role
                    FROM duty_group_members m
                    JOIN duty_teachers dt ON dt.id=m.teacher_id
                    LEFT JOIN duty_schedule ds ON ds.teacher_id=dt.id AND ds.duty_date=? AND ds.shift=?
                    WHERE m.group_id=?
                    ORDER BY ISNULL(ds.point_no), ds.point_no, dt.full_name
                ");
                $ms->execute([$date,$shift,$dayGroup['group_id']]);
                $members = $ms->fetchAll(PDO::FETCH_ASSOC);
            }

            // ดึงจำนวนจุดจาก settings (default 5)
            $maxPts = (int)($pdo->query("SELECT svalue FROM duty_settings WHERE skey='max_duty_points'")->fetchColumn() ?: 5);

            echo json_encode(['status'=>'success','day_group'=>$dayGroup,'members'=>$members,'max_points'=>$maxPts]);
            break;
        }

        // ── POST: บันทึกการจัดจุด ───────────────────────────────────────
        case 'save_points': {
            $date  = $_POST['duty_date'] ?? '';
            $shift = in_array($_POST['shift']??'',['day','night']) ? $_POST['shift'] : 'day';

            // รับ assignments เป็น JSON string
            $assignments = json_decode($_POST['assignments'] ?? '[]', true) ?: [];

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM duty_schedule WHERE duty_date=? AND shift=?")->execute([$date,$shift]);

            $stmt = $pdo->prepare("
                INSERT INTO duty_schedule (duty_date, shift, point_no, role, teacher_id, group_id)
                VALUES (?,?,?,?,?,?)
            ");

            // ดึง group_id ของวันนี้
            $gid = $pdo->prepare("SELECT group_id FROM duty_day_groups WHERE duty_date=? AND shift=?");
            $gid->execute([$date,$shift]);
            $groupId = $gid->fetchColumn() ?: null;

            foreach ($assignments as $a) {
                $tid    = (int)($a['teacher_id'] ?? 0);
                $ptNo   = (int)($a['point_no']   ?? 0);
                $role   = trim($a['role'] ?? '');
                if ($tid && $ptNo) {
                    $stmt->execute([$date,$shift,$ptNo,$role?:null,$tid,$groupId]);
                }
            }
            $pdo->commit();
            echo json_encode(['status'=>'success']);
            break;
        }

        // ── POST: auto-fill สัปดาห์ด้วย pattern ───────────────────────
        case 'auto_fill_week': {
            $weekStart  = $_POST['week_start'] ?? '';
            $dayPattern = json_decode($_POST['pattern'] ?? '[]', true) ?: [];
            // pattern = [{day_of_week: 1, shift: 'day', group_id: 2}, ...]
            // day_of_week: 1=จันทร์ ... 7=อาทิตย์

            if (!$weekStart || empty($dayPattern)) throw new Exception('ข้อมูลไม่ครบ');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO duty_day_groups (duty_date, shift, group_id) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE group_id=VALUES(group_id), updated_at=NOW()
            ");
            foreach ($dayPattern as $p) {
                $dow     = (int)($p['day_of_week'] ?? 1);
                $shift   = in_array($p['shift']??'',['day','night']) ? $p['shift'] : 'day';
                $groupId = (int)($p['group_id'] ?? 0) ?: null;
                $date    = date('Y-m-d', strtotime($weekStart . ' +' . ($dow-1) . ' days'));
                $stmt->execute([$date,$shift,$groupId]);
            }
            $pdo->commit();
            echo json_encode(['status'=>'success']);
            break;
        }

        // ── GET: ดึง teachers สำหรับ point+date ─────────────────────
        case 'get_point': {
            $date = $_GET['duty_date'] ?? '';
            $ptNo = (int)($_GET['point_no'] ?? 0);
            if (!$date || !$ptNo) throw new Exception('ข้อมูลไม่ครบ');

            $rows = $pdo->prepare("
                SELECT ds.teacher_id, ds.role, dt.prefix, dt.full_name
                FROM duty_schedule ds
                JOIN duty_teachers dt ON dt.id = ds.teacher_id
                WHERE ds.duty_date = ? AND ds.point_no = ? AND ds.shift = 'day'
                ORDER BY ds.id
            ");
            $rows->execute([$date, $ptNo]);
            echo json_encode(['status'=>'success','teachers'=>$rows->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        // ── POST: บันทึก teachers สำหรับ point+date (max 2) ──────────
        case 'save_point': {
            $date = $_POST['duty_date'] ?? '';
            $ptNo = (int)($_POST['point_no'] ?? 0);
            $tids = array_values(array_unique(
                array_filter(array_map('intval', (array)($_POST['teacher_ids'] ?? [])))
            ));
            if (!$date || !$ptNo) throw new Exception('ข้อมูลไม่ครบ');

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM duty_schedule WHERE duty_date=? AND point_no=? AND shift='day'")
                ->execute([$date, $ptNo]);
            $stmt = $pdo->prepare("INSERT INTO duty_schedule (duty_date, shift, point_no, teacher_id) VALUES (?,?,?,?)");
            foreach (array_slice($tids, 0, 2) as $tid) {
                $stmt->execute([$date, 'day', $ptNo, $tid]);
            }
            $pdo->commit();
            echo json_encode(['status'=>'success']);
            break;
        }

        // ── POST: ล้างตารางทั้งสัปดาห์ ────────────────────────────────
        case 'clear_week': {
            $weekStart = $_POST['week_start'] ?? '';
            if (!$weekStart) throw new Exception('ไม่ระบุวันที่');
            $weekEnd = date('Y-m-d', strtotime($weekStart . ' +4 days'));

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM duty_schedule WHERE duty_date BETWEEN ? AND ? AND shift='day'")
                ->execute([$weekStart, $weekEnd]);
            $pdo->prepare("DELETE FROM duty_day_groups WHERE duty_date BETWEEN ? AND ?")
                ->execute([$weekStart, $weekEnd]);
            $pdo->commit();
            echo json_encode(['status'=>'success']);
            break;
        }

        default:
            throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('schedule_api: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'เกิดข้อผิดพลาด']);
}
