<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin', 'wfh_staff', 'att_teacher'])) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$pdo     = getPdo();
$isAdmin = in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin']);
$userId  = (int)$_SESSION['user_id'];
$dbError = false;

// Auto-create table if not exists (first-run convenience)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sar_reports (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id   INT NOT NULL,
            teacher_name VARCHAR(200) NOT NULL DEFAULT '',
            year         VARCHAR(10)  NOT NULL DEFAULT '',
            semester     TINYINT      NOT NULL DEFAULT 1,
            form_data    LONGTEXT     NOT NULL DEFAULT '{}',
            status       ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_teacher (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    error_log('SAR table create: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId) {
        try {
            $isAdmin
                ? $pdo->prepare("DELETE FROM sar_reports WHERE id = ?")->execute([$delId])
                : $pdo->prepare("DELETE FROM sar_reports WHERE id = ? AND teacher_id = ?")->execute([$delId, $userId]);
        } catch (Exception $e) { error_log($e->getMessage()); }
    }
    header('Location: index.php'); exit();
}

$reports = [];
try {
    if ($isAdmin) {
        $stmt = $pdo->query(
            "SELECT s.*, u.firstname, u.lastname FROM sar_reports s
             LEFT JOIN llw_users u ON u.user_id = s.teacher_id
             ORDER BY s.created_at DESC"
        );
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare(
            "SELECT s.*, u.firstname, u.lastname FROM sar_reports s
             LEFT JOIN llw_users u ON u.user_id = s.teacher_id
             WHERE s.teacher_id = ?
             ORDER BY s.created_at DESC"
        );
        $stmt->execute([$userId]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    $dbError = true;
}

$pageTitle    = 'รายงาน SAR';
$pageSubtitle = 'รายงานการประเมินตนเอง (Self Assessment Report)';
$activeSystem = 'sar';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-2xl font-black text-slate-800">รายงานการประเมินตนเอง (SAR)</h2>
        <p class="text-sm text-slate-500 mt-1">Self Assessment Report — โรงเรียนละลมวิทยา</p>
    </div>
    <a href="fill.php"
       class="bg-blue-600 text-white px-5 py-2.5 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] transition-all flex items-center gap-2">
        <i class="bi bi-plus-lg"></i> สร้าง SAR ใหม่
    </a>
</div>

<?php if (empty($reports)): ?>
<div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-16 text-center">
    <i class="bi bi-file-text" style="font-size:3rem;color:#cbd5e1;display:block;margin-bottom:1rem;"></i>
    <p class="font-bold text-slate-400 text-lg">ยังไม่มีรายงาน SAR</p>
    <p class="text-sm text-slate-400 mt-1">คลิก "สร้าง SAR ใหม่" เพื่อเริ่มต้น</p>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-slate-50">
                <th class="text-xs font-bold text-slate-400 uppercase tracking-wider px-6 py-4 text-left">ผู้รายงาน</th>
                <th class="text-xs font-bold text-slate-400 uppercase tracking-wider px-4 py-4 text-left">ปีการศึกษา</th>
                <th class="text-xs font-bold text-slate-400 uppercase tracking-wider px-4 py-4 text-center">ภาคเรียน</th>
                <th class="text-xs font-bold text-slate-400 uppercase tracking-wider px-4 py-4 text-center">สถานะ</th>
                <th class="text-xs font-bold text-slate-400 uppercase tracking-wider px-4 py-4 text-left">อัปเดต</th>
                <th class="px-4 py-4"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($reports as $r):
                $displayName = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
                if (!$displayName) $displayName = $r['teacher_name'];
                $ts = strtotime($r['updated_at'] ?: $r['created_at']);
                $dateThai = date('d/m/', $ts) . (date('Y', $ts) + 543);
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></p>
                </td>
                <td class="px-4 py-4 text-sm font-bold text-slate-700">
                    <?= htmlspecialchars($r['year'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td class="px-4 py-4 text-sm text-slate-600 text-center">
                    ภาคเรียนที่ <?= (int)$r['semester'] ?>
                </td>
                <td class="px-4 py-4 text-center">
                    <?php if ($r['status'] === 'submitted'): ?>
                    <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold">
                        <i class="bi bi-check-circle-fill me-1"></i>ส่งแล้ว
                    </span>
                    <?php else: ?>
                    <span class="px-3 py-1 rounded-full bg-amber-50 text-amber-600 text-xs font-bold">
                        <i class="bi bi-pencil-fill me-1"></i>ร่าง
                    </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-4 text-xs text-slate-400"><?= $dateThai ?></td>
                <td class="px-4 py-4">
                    <div class="flex items-center gap-2 justify-end">
                        <a href="fill.php?id=<?= $r['id'] ?>"
                           class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-xl text-xs font-bold hover:bg-blue-100 transition-all flex items-center gap-1">
                            <i class="bi bi-pencil-fill"></i> แก้ไข
                        </a>
                        <a href="fill.php?id=<?= $r['id'] ?>&print=1" target="_blank"
                           class="px-3 py-1.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition-all flex items-center gap-1">
                            <i class="bi bi-printer-fill"></i> พิมพ์
                        </a>
                        <form method="POST" class="inline" onsubmit="return confirm('ลบรายงานนี้ใช่ไหม?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit"
                                    class="px-3 py-1.5 bg-rose-50 text-rose-500 rounded-xl text-xs font-bold hover:bg-rose-100 transition-all">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
