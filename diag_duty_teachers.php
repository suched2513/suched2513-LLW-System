<?php
// diag_duty_teachers.php — ตรวจนับครู (ลบหลังใช้)
require_once __DIR__ . '/config/database.php';
$pdo = getPdo();
header('Content-Type: text/plain; charset=utf-8');

// att_teachers
$total_att = $pdo->query("SELECT COUNT(*) FROM att_teachers")->fetchColumn();
$empty_att  = $pdo->query("SELECT COUNT(*) FROM att_teachers WHERE name IS NULL OR TRIM(name)=''")->fetchColumn();
echo "=== att_teachers ===\n";
echo "Total          : $total_att\n";
echo "name ว่าง      : $empty_att\n\n";

// duty_teachers
$total_duty  = $pdo->query("SELECT COUNT(*) FROM duty_teachers")->fetchColumn();
$empty_duty  = $pdo->query("SELECT COUNT(*) FROM duty_teachers WHERE full_name IS NULL OR TRIM(full_name)=''")->fetchColumn();
echo "=== duty_teachers ===\n";
echo "Total          : $total_duty\n";
echo "full_name ว่าง : $empty_duty\n\n";

// att_teachers ที่ name ว่าง
echo "=== att_teachers ที่ name ว่าง ===\n";
$rows = $pdo->query("SELECT id, name, username, llw_user_id FROM att_teachers WHERE name IS NULL OR TRIM(name)='' ORDER BY id");
foreach ($rows as $r) {
    echo "  id={$r['id']} username={$r['username']} llw_user_id={$r['llw_user_id']}\n";
}

echo "\n=== att_teachers ทั้งหมด (id + name) ===\n";
$rows = $pdo->query("SELECT at.id, at.name, at.username,
    COALESCE(
        NULLIF(TRIM(CONCAT(COALESCE(lu.firstname,''),' ',COALESCE(lu.lastname,''))), ''),
        NULLIF(TRIM(at.name), ''),
        NULLIF(TRIM(at.username), ''),
        CONCAT('ครู#', at.id)
    ) AS resolved_name
FROM att_teachers at
LEFT JOIN llw_users lu ON lu.user_id = at.llw_user_id
ORDER BY at.id");
foreach ($rows as $r) {
    echo "  id={$r['id']} name=[{$r['name']}] resolved=[{$r['resolved_name']}] username=[{$r['username']}]\n";
}

echo "\n=== duty_teachers ที่ full_name ว่าง ===\n";
$rows = $pdo->query("SELECT id, full_name FROM duty_teachers WHERE full_name IS NULL OR TRIM(full_name)=''");
foreach ($rows as $r) {
    echo "  id={$r['id']} full_name=[{$r['full_name']}]\n";
}
