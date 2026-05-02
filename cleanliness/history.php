<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

try {
    $pdo = getPdo();
    
    // Simple filter
    $date = $_GET['date'] ?? null;
    $where = "";
    $params = [];
    if ($date) {
        $where = "WHERE s.score_date = ?";
        $params[] = $date;
    }

    $stmt = $pdo->prepare("SELECT s.*, a.name as area_name, u.firstname, u.lastname 
        FROM clean_scores s
        JOIN clean_areas a ON s.area_id = a.id
        JOIN llw_users u ON s.recorded_by_user_id = u.user_id
        $where
        ORDER BY s.score_date DESC, s.created_at DESC
        LIMIT 100");
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
    $records = [];
}

$pageTitle = 'ประวัติการประเมิน';
$pageSubtitle = 'รายการบันทึกคะแนนย้อนหลัง';
$activeSystem = 'cleanliness';

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-2xl shadow-slate-200/40 border border-white p-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
        <div>
            <h2 class="text-2xl font-black text-slate-800 flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center">
                    <i class="bi bi-clock-history"></i>
                </span>
                ประวัติการบันทึก
            </h2>
        </div>
        
        <form class="flex items-center gap-3 bg-slate-50 p-2 rounded-2xl border border-slate-100">
            <input type="date" name="date" value="<?= $date ?>" class="bg-transparent border-none outline-none text-sm font-bold text-slate-600 px-3">
            <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-xl font-bold text-xs shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">ค้นหา</button>
            <?php if ($date): ?>
                <a href="history.php" class="text-slate-400 hover:text-rose-500 transition-colors pr-2"><i class="bi bi-x-circle-fill"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50">
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest rounded-l-2xl">วันที่</th>
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">พื้นที่</th>
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">ห้องเรียน</th>
                    <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">คะแนน (100)</th>
                    <th class="px-6 py-4 text-left text-xs font-black text-slate-400 uppercase tracking-widest">ผู้บันทึก</th>
                    <th class="px-6 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest rounded-r-2xl">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($records as $row): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="px-6 py-5">
                            <span class="text-sm font-bold text-slate-600"><?= date('d/m/Y', strtotime($row['score_date'])) ?></span>
                            <p class="text-xs text-slate-300 font-medium"><?= date('H:i', strtotime($row['created_at'])) ?> น.</p>
                        </td>
                        <td class="px-6 py-5">
                            <span class="text-sm font-black text-slate-800"><?= htmlspecialchars($row['area_name']) ?></span>
                        </td>
                        <td class="px-6 py-5 text-sm font-bold text-emerald-600"><?= htmlspecialchars($row['class_name']) ?></td>
                        <td class="px-6 py-5 text-center">
                            <span class="px-4 py-1.5 rounded-full <?= $row['score'] >= 80 ? 'bg-emerald-50 text-emerald-600' : ($row['score'] >= 60 ? 'bg-amber-50 text-amber-600' : 'bg-rose-50 text-rose-500') ?> text-xs font-black">
                                <?= $row['score'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-5 text-sm text-slate-500 font-medium">
                            <?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <?php if (in_array($_SESSION['llw_role'], ['super_admin']) || $_SESSION['user_id'] == $row['recorded_by_user_id']): ?>
                                <button onclick="deleteRecord(<?= $row['id'] ?>)" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-400 hover:bg-rose-500 hover:text-white transition-all">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-20 text-center text-slate-400 font-medium italic">ไม่พบข้อมูลการบันทึก</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function deleteRecord(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "คุณจะไม่สามารถกู้คืนข้อมูลนี้ได้!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบข้อมูล',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            popup: 'rounded-[2rem]',
            confirmButton: 'rounded-xl font-bold px-6 py-3',
            cancelButton: 'rounded-xl font-bold px-6 py-3'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/delete_score.php?id=' + id)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'ลบสำเร็จ', showConfirmButton: false, timer: 1000 })
                        .then(() => location.reload());
                    } else {
                        Swal.fire('ผิดพลาด', data.message, 'error');
                    }
                });
        }
    });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
