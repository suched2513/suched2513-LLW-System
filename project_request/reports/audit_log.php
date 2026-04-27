<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

$pageTitle = 'Audit Logs';
$pageSubtitle = 'ประวัติการทำรายการและการเปลี่ยนแปลงข้อมูลในระบบ';
require_once __DIR__ . '/../components/layout_start.php';

$pdo = getPdo();
$user_id = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';

// Fetch Logs
$sql = "SELECT l.*, u.full_name as user_name FROM audit_logs l JOIN users u ON l.user_id = u.id";
$params = [];

if ($user_id || $action) {
    $sql .= " WHERE 1=1";
    if ($user_id) { $sql .= " AND l.user_id = ?"; $params[] = $user_id; }
    if ($action) { $sql .= " AND l.action = ?"; $params[] = $action; }
}

$sql .= " ORDER BY l.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch Users for Filter
$users = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();
?>

<div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
    <!-- Filters -->
    <form class="flex flex-wrap items-end gap-4 mb-10">
        <div class="w-48">
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">ผู้ใช้งาน</label>
            <select name="user_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold outline-none">
                <option value="">ทั้งหมด</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $user_id == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-48">
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">การกระทำ</label>
            <select name="action" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold outline-none">
                <option value="">ทั้งหมด</option>
                <option value="create" <?= $action == 'create' ? 'selected' : '' ?>>Create</option>
                <option value="update" <?= $action == 'update' ? 'selected' : '' ?>>Update</option>
                <option value="submit" <?= $action == 'submit' ? 'selected' : '' ?>>Submit</option>
                <option value="approve" <?= $action == 'approve' ? 'selected' : '' ?>>Approve</option>
                <option value="reject" <?= $action == 'reject' ? 'selected' : '' ?>>Reject</option>
            </select>
        </div>
        <button type="submit" class="bg-slate-800 text-white px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-slate-900 transition-all">กรองข้อมูล</button>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left border-b border-slate-50">
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">วัน-เวลา</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">ผู้ใช้งาน</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">การกระทำ</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">เป้าหมาย</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">รายละเอียด</th>
                    <th class="pb-4 text-[11px] font-black text-slate-400 uppercase tracking-widest px-4">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($logs as $log): ?>
                <tr class="text-xs">
                    <td class="py-4 px-4 font-bold text-slate-500"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                    <td class="py-4 px-4 font-black text-slate-700"><?= htmlspecialchars($log['user_name']) ?></td>
                    <td class="py-4 px-4">
                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-slate-100 text-slate-600">
                            <?= $log['action'] ?>
                        </span>
                    </td>
                    <td class="py-4 px-4 font-medium text-slate-500"><?= $log['target_type'] ?> (ID: <?= $log['target_id'] ?>)</td>
                    <td class="py-4 px-4">
                        <div class="max-w-xs truncate text-slate-400" title='<?= htmlspecialchars($log['new_value']) ?>'>
                            <?= htmlspecialchars($log['new_value']) ?>
                        </div>
                    </td>
                    <td class="py-4 px-4 font-mono text-slate-400"><?= $log['ip_address'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
