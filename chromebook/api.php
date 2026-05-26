<?php
/**
 * chromebook/api.php — Chromebook REST API (JSON)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

// Auth guard
if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'cb_admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action  = $_GET['action'] ?? ($input['action'] ?? '');
$payload = $input['payload'] ?? $input ?? [];

function ok($data = [])  { echo json_encode(['success' => true,  'data' => $data]); exit; }
function err($msg)        { echo json_encode(['success' => false, 'error' => $msg]); exit; }

// ── Image save helper ──────────────────────────────────────────
function saveBlobs(array $blobs): array {
    $saved = [];
    foreach ($blobs as $blob) {
        if (!preg_match('/^data:image\/(\w+);base64,/', $blob, $m)) continue;
        $ext  = strtolower($m[1]);
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;
        $data = base64_decode(substr($blob, strpos($blob, ',') + 1));
        $filename = uniqid('cb_', true) . '.' . $ext;
        file_put_contents(__DIR__ . '/uploads/' . $filename, $data);
        $saved[] = $filename;
    }
    return $saved;
}

try {
    $pdo = getPdo();

    switch ($action) {

        // ── READ ──────────────────────────────────────────────
        case 'getData':
            $sheet = $payload['sheetName'] ?? '';
            if ($sheet === 'BorrowLog') {
                $stmt = $pdo->query("
                    SELECT b.entry_id, b.borrower_type, b.borrower_id, b.class_name,
                           b.chromebook_id, b.chromebook_serial, b.images, b.status,
                           b.date_borrowed,
                           (SELECT MAX(inspected_date) FROM cb_inspections i WHERE i.borrow_log_id = b.entry_id) as last_inspected
                    FROM cb_borrow_logs b ORDER BY b.entry_id DESC
                ");
                ok($stmt->fetchAll(PDO::FETCH_NUM));
            } elseif ($sheet === 'Teachers') {
                ok($pdo->query("SELECT teacher_id, name FROM cb_teachers ORDER BY name")->fetchAll(PDO::FETCH_NUM));
            } elseif ($sheet === 'Students') {
                // ดึงจากฐานข้อมูลกลาง (att_students) ปี 2569
                ok($pdo->query("SELECT student_id, name, classroom as class_name FROM att_students WHERE academic_year = 2569 AND (status = 'active' OR status IS NULL) ORDER BY classroom, name")->fetchAll(PDO::FETCH_NUM));
            } elseif ($sheet === 'Chromebooks') {
                ok($pdo->query("SELECT chromebook_id, model, serial_number FROM cb_chromebooks ORDER BY chromebook_id")->fetchAll(PDO::FETCH_NUM));
            } elseif ($sheet === 'Inspections') {
                ok($pdo->query("SELECT id, borrow_log_id, condition_status, inspected_date FROM cb_inspections")->fetchAll(PDO::FETCH_NUM));
            } else {
                err("Unknown sheet: $sheet");
            }
            break;

        // ── BORROW ───────────────────────────────────────────
        case 'addBorrow':
            $bType = $payload['borrowerType'] ?? '';
            $bId   = $payload['borrowerId']   ?? '';
            $cName = $payload['className']     ?? null;
            $cbId  = $payload['chromebookId'] ?? '';
            if (!$bType || !$bId || !$cbId) err('ข้อมูลไม่ครบ');

            // Normalize student ID if borrower is a student
            if ($bType === 'Student' && preg_match('/^\d+$/', $bId)) {
                $bId = str_pad($bId, 5, '0', STR_PAD_LEFT);
            }

            $cb = $pdo->prepare("SELECT serial_number FROM cb_chromebooks WHERE chromebook_id = ?");
            $cb->execute([$cbId]); $row = $cb->fetch();
            $serial = $row['serial_number'] ?? '';

            $imgs = implode(',', saveBlobs($payload['imageBlobs'] ?? []));
            $pdo->prepare("INSERT INTO cb_borrow_logs (borrower_type, borrower_id, class_name, chromebook_id, chromebook_serial, images, status, date_borrowed) VALUES (?,?,?,?,?,?,'Borrowed',NOW())")
                ->execute([$bType, $bId, $cName, $cbId, $serial, $imgs]);
            ok();
            break;

        case 'returnBorrow':
            $id = (int)($payload['entryId'] ?? 0);
            $pdo->prepare("UPDATE cb_borrow_logs SET status='Returned', date_returned=NOW() WHERE entry_id=?")->execute([$id]);
            ok();
            break;

        case 'editBorrow':
            $id = (int)($payload['entryId'] ?? 0);
            $stmt = $pdo->prepare("SELECT images FROM cb_borrow_logs WHERE entry_id=?");
            $stmt->execute([$id]); $row = $stmt->fetch();
            $existing = $row && $row['images'] ? explode(',', $row['images']) : [];
            $new = saveBlobs($payload['newImageBlobs'] ?? []);
            $all = implode(',', array_merge($existing, $new));
            $pdo->prepare("UPDATE cb_borrow_logs SET images=? WHERE entry_id=?")->execute([$all, $id]);
            ok();
            break;

        case 'deleteBorrow':
            $id = (int)($payload['entryId'] ?? 0);
            $stmt = $pdo->prepare("SELECT images FROM cb_borrow_logs WHERE entry_id=?");
            $stmt->execute([$id]); $row = $stmt->fetch();
            if ($row && $row['images']) {
                foreach (explode(',', $row['images']) as $img) {
                    $p = __DIR__ . '/uploads/' . $img;
                    if (file_exists($p)) unlink($p);
                }
            }
            $pdo->prepare("DELETE FROM cb_inspections WHERE borrow_log_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM cb_borrow_logs WHERE entry_id=?")->execute([$id]);
            ok();
            break;

        // ── INSPECTION ───────────────────────────────────────
        case 'addInspection':
            $id   = (int)($payload['entryId']   ?? 0);
            $cond = $payload['condition'] ?? 'Normal';
            $note = $payload['notes']     ?? '';
            $imgs = implode(',', saveBlobs($payload['imageBlobs'] ?? []));
            $pdo->prepare("INSERT INTO cb_inspections (borrow_log_id, condition_status, notes, images, inspected_date) VALUES (?,?,?,?,NOW())")
                ->execute([$id, $cond, $note, $imgs]);
            ok();
            break;

        // ── MASTER DATA: Teachers ────────────────────────────
        case 'addTeacher':
            $id   = trim($payload['teacher_id'] ?? '');
            $name = trim($payload['name']        ?? '');
            if (!$id || !$name) err('ข้อมูลไม่ครบ');
            $pdo->prepare("INSERT INTO cb_teachers (teacher_id, name) VALUES (?,?) ON DUPLICATE KEY UPDATE name=?")->execute([$id, $name, $name]);
            ok();
            break;

        case 'deleteTeacher':
            $id = trim($payload['teacher_id'] ?? '');
            $pdo->prepare("DELETE FROM cb_teachers WHERE teacher_id=?")->execute([$id]);
            ok();
            break;

        // ── MASTER DATA: Students ────────────────────────────
        case 'addStudent':
            $id    = trim($payload['student_id'] ?? '');
            if (preg_match('/^\d+$/', $id)) $id = str_pad($id, 5, '0', STR_PAD_LEFT);
            
            $name  = trim($payload['name']        ?? '');
            $cls   = trim($payload['class_name']  ?? '');
            if (!$id || !$name || !$cls) err('ข้อมูลไม่ครบ');
            $pdo->prepare("INSERT INTO cb_students (student_id, name, class_name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=?, class_name=?")->execute([$id, $name, $cls, $name, $cls]);
            ok();
            break;

        case 'deleteStudent':
            $id = trim($payload['student_id'] ?? '');
            $pdo->prepare("DELETE FROM cb_students WHERE student_id=?")->execute([$id]);
            ok();
            break;

        // ── MASTER DATA: Chromebooks ─────────────────────────
        case 'addChromebook':
            $id     = trim($payload['chromebook_id']  ?? '');
            $model  = trim($payload['model']           ?? '');
            $serial = trim($payload['serial_number']   ?? '');
            if (!$id || !$model || !$serial) err('ข้อมูลไม่ครบ');
            $pdo->prepare("INSERT INTO cb_chromebooks (chromebook_id, model, serial_number) VALUES (?,?,?) ON DUPLICATE KEY UPDATE model=?, serial_number=?")->execute([$id, $model, $serial, $model, $serial]);
            ok();
            break;

        case 'deleteChromebook':
            $id = trim($payload['chromebook_id'] ?? '');
            $pdo->prepare("DELETE FROM cb_chromebooks WHERE chromebook_id=?")->execute([$id]);
            ok();
            break;

        // ── REASSIGN BORROWER ────────────────────────────────
        case 'reassignBorrow':
            $entryId  = (int)($payload['entryId']     ?? 0);
            $newType  = trim($payload['borrowerType'] ?? '');
            $newBorrowerId = trim($payload['borrowerId'] ?? '');
            $newName  = trim($payload['name']         ?? '');
            $newClass = trim($payload['className']    ?? '');
            if (!$entryId || !$newType || !$newName || !$newBorrowerId) err('ข้อมูลไม่ครบ');
            if (!in_array($newType, ['Teacher', 'Student'])) err('ประเภทไม่ถูกต้อง');

            // Normalize student ID if borrower is a student
            if ($newType === 'Student' && preg_match('/^\d+$/', $newBorrowerId)) {
                $newBorrowerId = str_pad($newBorrowerId, 5, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("SELECT chromebook_id FROM cb_borrow_logs WHERE entry_id=?");
            $stmt->execute([$entryId]);
            $log = $stmt->fetch();
            if (!$log) err('ไม่พบรายการ');

            $chromebookId = $log['chromebook_id'];

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE cb_borrow_logs SET borrower_id=?, borrower_type=?, class_name=? WHERE entry_id=?")
                ->execute([$newBorrowerId, $newType, $newClass ?: null, $entryId]);

            if ($newType === 'Student') {
                $pdo->prepare("INSERT INTO cb_students (student_id, name, class_name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), class_name=VALUES(class_name)")
                    ->execute([$newBorrowerId, $newName, $newClass]);
            } else {
                $pdo->prepare("INSERT INTO cb_teachers (teacher_id, name) VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)")
                    ->execute([$newBorrowerId, $newName]);
            }

            $pdo->prepare("UPDATE cb_chromebooks SET borrower_type=?, borrower_name=? WHERE chromebook_id=?")
                ->execute([$newType, $newName, $chromebookId]);
            $pdo->commit();
            ok();
            break;

        // ── REPAIRS ──────────────────────────────────────────
        case 'getRepairs':
            $hasImgCol = !empty($pdo->query("SHOW COLUMNS FROM cb_repairs LIKE 'images'")->fetchAll());
            $imgSql    = $hasImgCol ? 'r.images,' : "'' AS images,";
            $stmt = $pdo->query("
                SELECT r.id, r.borrow_log_id, r.chromebook_id, r.chromebook_serial,
                       r.description, {$imgSql} r.status, r.repair_notes, r.reported_by,
                       r.created_at, r.updated_at,
                       b.borrower_type, b.borrower_id, b.class_name
                FROM cb_repairs r
                LEFT JOIN cb_borrow_logs b ON b.entry_id = r.borrow_log_id
                ORDER BY FIELD(r.status,'รับแจ้ง','ส่งซ่อม','ซ่อมเสร็จ','รับกลับ'), r.created_at DESC
            ");
            ok($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'addRepair':
            $borrowLogId = (int)($payload['borrowLogId'] ?? 0);
            $desc        = trim($payload['description']  ?? '');
            if (!$borrowLogId) err('ข้อมูลไม่ครบ');

            $stmt = $pdo->prepare("SELECT chromebook_id, chromebook_serial FROM cb_borrow_logs WHERE entry_id=?");
            $stmt->execute([$borrowLogId]);
            $log = $stmt->fetch();
            if (!$log) err('ไม่พบรายการยืม');

            $reporter  = $_SESSION['fullname'] ?? $_SESSION['username'] ?? '';
            $hasImgCol = !empty($pdo->query("SHOW COLUMNS FROM cb_repairs LIKE 'images'")->fetchAll());
            if ($hasImgCol) {
                $imgs = implode(',', saveBlobs($payload['imageBlobs'] ?? []));
                $pdo->prepare("INSERT INTO cb_repairs (borrow_log_id, chromebook_id, chromebook_serial, description, images, reported_by) VALUES (?,?,?,?,?,?)")
                    ->execute([$borrowLogId, $log['chromebook_id'], $log['chromebook_serial'], $desc, $imgs, $reporter]);
            } else {
                $pdo->prepare("INSERT INTO cb_repairs (borrow_log_id, chromebook_id, chromebook_serial, description, reported_by) VALUES (?,?,?,?,?)")
                    ->execute([$borrowLogId, $log['chromebook_id'], $log['chromebook_serial'], $desc, $reporter]);
            }
            ok(['repair_id' => (int)$pdo->lastInsertId()]);
            break;

        case 'updateRepairStatus':
            $id     = (int)($payload['repairId'] ?? 0);
            $status = $payload['status']      ?? '';
            $notes  = trim($payload['notes']  ?? '');
            $allowed = ['รับแจ้ง','ส่งซ่อม','ซ่อมเสร็จ','รับกลับ'];
            if (!$id || !in_array($status, $allowed)) err('ข้อมูลไม่ถูกต้อง');
            $pdo->prepare("UPDATE cb_repairs SET status=?, repair_notes=? WHERE id=?")
                ->execute([$status, $notes, $id]);
            ok();
            break;

        case 'deleteRepair':
            $id = (int)($payload['repairId'] ?? 0);
            if (!$id) err('ข้อมูลไม่ครบ');
            $pdo->prepare("DELETE FROM cb_repairs WHERE id=?")->execute([$id]);
            ok();
            break;

        // ── CSV IMPORT ────────────────────────────────────────
        case 'importCSV':
            $type = $payload['type'] ?? '';
            $rows = $payload['rows'] ?? [];
            if (empty($rows)) err('ไม่มีข้อมูล');

            $pdo->beginTransaction();
            $inserted = 0; $skipped = 0;
            try {
                foreach ($rows as $row) {
                    if ($type === 'teacher') {
                        $id   = trim($row[0] ?? '');
                        $name = trim($row[1] ?? '');
                        if (!$id || !$name) { $skipped++; continue; }
                        $pdo->prepare("INSERT INTO cb_teachers (teacher_id, name) VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)")
                            ->execute([$id, $name]);
                        $inserted++;
                    } elseif ($type === 'student') {
                        $id  = trim($row[0] ?? '');
                        if (preg_match('/^\d+$/', $id)) $id = str_pad($id, 5, '0', STR_PAD_LEFT);
                        
                        $name = trim($row[1] ?? '');
                        $cls = trim($row[2] ?? '');
                        if (!$id || !$name || !$cls) { $skipped++; continue; }
                        $pdo->prepare("INSERT INTO cb_students (student_id, name, class_name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), class_name=VALUES(class_name)")
                            ->execute([$id, $name, $cls]);
                        $inserted++;
                    } elseif ($type === 'chromebook') {
                        $id     = trim($row[0] ?? '');
                        $model  = trim($row[1] ?? '');
                        $serial = trim($row[2] ?? '');
                        if (!$id || !$model || !$serial) { $skipped++; continue; }
                        $pdo->prepare("INSERT INTO cb_chromebooks (chromebook_id, model, serial_number) VALUES (?,?,?) ON DUPLICATE KEY UPDATE model=VALUES(model), serial_number=VALUES(serial_number)")
                            ->execute([$id, $model, $serial]);
                        $inserted++;
                    } else {
                        $pdo->rollBack(); err("ประเภทไม่ถูกต้อง: $type");
                    }
                }
                $pdo->commit();
                ok(['inserted' => $inserted, 'skipped' => $skipped]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        default:
            err("Unknown action: $action");
    }
} catch (Exception $e) {
    error_log('CB API Error: ' . $e->getMessage());
    http_response_code(500);
    err('เกิดข้อผิดพลาดในระบบ');
}
