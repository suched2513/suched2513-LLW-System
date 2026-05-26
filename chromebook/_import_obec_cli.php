<?php
// CLI-only import script — do not deploy
if (php_sapi_name() !== 'cli') exit('CLI only');

require_once __DIR__ . '/../config/database.php';
$pdo = getPdo();

$csvPath = 'C:/Users/Advices/Downloads/lalomwittaya_devices.csv';
$content = file_get_contents($csvPath);

// Strip BOM
if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);
elseif (substr($content, 0, 2) === "\xFF\xFE") $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');

$lines = preg_split('/\r\n|\r|\n/', $content);
$lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));
array_shift($lines); // skip header

$stmtDev = $pdo->prepare("
    INSERT INTO cb_chromebooks
        (chromebook_id, model, brand, serial_number, mac_address, imei,
         keyboard_box, keyboard_sn, sim_type, mobile_no, borrower_type, borrower_name)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        model=VALUES(model), brand=VALUES(brand), serial_number=VALUES(serial_number),
        mac_address=VALUES(mac_address), imei=VALUES(imei), keyboard_box=VALUES(keyboard_box),
        keyboard_sn=VALUES(keyboard_sn), sim_type=VALUES(sim_type), mobile_no=VALUES(mobile_no),
        borrower_type=VALUES(borrower_type), borrower_name=VALUES(borrower_name)
");
$stmtTeacher = $pdo->prepare("
    INSERT INTO cb_teachers (teacher_id, name) VALUES (?,?)
    ON DUPLICATE KEY UPDATE name=VALUES(name)
");
$stmtStudent = $pdo->prepare("
    INSERT INTO cb_students (student_id, name, class_name) VALUES (?,?,?)
    ON DUPLICATE KEY UPDATE name=VALUES(name)
");
$stmtLog = $pdo->prepare("
    INSERT IGNORE INTO cb_borrow_logs
        (borrower_type, borrower_id, class_name, chromebook_id, chromebook_serial, status, date_borrowed)
    VALUES (?,?,?,?,?,'Borrowed',NOW())
");

$dev = $tch = $stu = $log = $skip = 0;
$pdo->beginTransaction();

foreach ($lines as $line) {
    $row = str_getcsv($line);
    $type   = trim($row[0] ?? '');
    $box    = trim($row[1] ?? '');
    $serial = trim($row[2] ?? '');
    $mac    = trim($row[3] ?? '');
    $imei   = trim($row[4] ?? '') ?: null;
    $kbbox  = trim($row[5] ?? '');
    $kbsn   = trim($row[6] ?? '');
    $sim    = trim($row[7] ?? '');
    $mobile = trim($row[8] ?? '');
    $brand  = trim($row[9] ?? '');
    $model  = trim($row[10] ?? '');
    $fn     = trim($row[11] ?? '');
    $ln     = trim($row[12] ?? '');
    $name   = trim($fn . ' ' . $ln);

    if (!$box || !$serial) { $skip++; continue; }

    $bType = ($type === 'ครู') ? 'Teacher' : 'Student';

    $stmtDev->execute([$box, $brand . ' ' . $model, $brand, $serial, $mac, $imei,
                       $kbbox, $kbsn, $sim, $mobile, $bType, $name]);
    $dev++;

    if ($bType === 'Teacher') {
        $stmtTeacher->execute([$box, $name]);
        $tch++;
    } else {
        $stmtStudent->execute([$box, $name, '']);
        $stu++;
    }

    $stmtLog->execute([$bType, $box, '', $box, $serial]);
    $log++;
}

$pdo->commit();

echo "✓ อุปกรณ์: $dev\n";
echo "✓ ครู: $tch\n";
echo "✓ นักเรียน: $stu\n";
echo "✓ บันทึกการรับ: $log\n";
if ($skip) echo "⚠ ข้ามไป: $skip แถว\n";
echo "เสร็จสิ้น\n";
