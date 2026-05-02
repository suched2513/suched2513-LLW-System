<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    http_response_code(403); die('Forbidden');
}

$pdo = getPdo();
$results = [];

$migrationName = '2026_05_02_000007_create_student_leave_tables';
$migrationFile = __DIR__ . '/../database/migrations/' . $migrationName . '.php';

$steps = [
    'สร้างตาราง stl_* (ใบลานักเรียน)' => function(PDO $pdo) use ($migrationFile, $migrationName) {
        // Skip if already run
        try {
            $done = (int)$pdo->query("SELECT COUNT(*) FROM _migrations WHERE migration = '$migrationName'")->fetchColumn();
            if ($done > 0) return 'already_done';
        } catch (Exception $e) { /* _migrations table may not exist yet */ }

        $migration = require $migrationFile;
        $migration['up']($pdo);

        // Record in _migrations
        try {
            $batch = (int)$pdo->query("SELECT COALESCE(MAX(batch),0) FROM _migrations")->fetchColumn() + 1;
            $pdo->prepare("INSERT INTO _migrations (migration, batch) VALUES (?,?)")
                ->execute([$migrationName, $batch]);
        } catch (Exception $e) {
            error_log('[migration] record: ' . $e->getMessage());
        }
        return 'migrated';
    },
];

$allOk = true;
foreach ($steps as $label => $fn) {
    try {
        $note = $fn($pdo);
        $results[] = [
            'ok'    => true,
            'label' => $label,
            'note'  => $note === 'already_done' ? '(เคยรันแล้ว)' : '✓ สร้างตารางใหม่',
        ];
    } catch (Exception $e) {
        $results[] = ['ok' => false, 'label' => $label, 'err' => $e->getMessage()];
        $allOk = false;
        error_log('[migration] ' . $label . ': ' . $e->getMessage());
    }
}

if ($allOk) {
    @unlink(__FILE__);
}
?><!DOCTYPE html>
<html lang="th"><head><meta charset="UTF-8"><title>Migration — ใบลานักเรียน</title>
<style>
body { font-family: sans-serif; max-width: 640px; margin: 40px auto; padding: 20px; }
.ok  { color: #16a34a; }
.err { color: #dc2626; }
.box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 8px 0; }
.note { color: #64748b; font-size: .85em; margin-left: .5em; }
</style>
</head><body>
<h2>Migration Results — ระบบใบลานักเรียน</h2>
<?php foreach ($results as $r): ?>
<div class="box">
  <span class="<?= $r['ok'] ? 'ok' : 'err' ?>"><?= $r['ok'] ? '✅' : '❌' ?></span>
  <?= htmlspecialchars($r['label']) ?>
  <?php if (!empty($r['note'])): ?><span class="note"><?= htmlspecialchars($r['note']) ?></span><?php endif; ?>
  <?php if (!$r['ok']): ?><br><small style="color:#dc2626"><?= htmlspecialchars($r['err']) ?></small><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if ($allOk): ?>
<p class="ok"><strong>✅ เสร็จสมบูรณ์ — ไฟล์นี้ถูกลบแล้ว</strong></p>
<?php else: ?>
<p class="err"><strong>❌ มีข้อผิดพลาด กรุณาตรวจสอบ error log</strong></p>
<?php endif; ?>
<p>
  <a href="/student_leave/teacher.php">→ ไปที่ระบบใบลานักเรียน</a>
  &nbsp;|&nbsp;
  <a href="/index.php">← กลับหน้าหลัก</a>
</p>
</body></html>
