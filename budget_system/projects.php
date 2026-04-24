<?php
require_once 'config.php';
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$db = connectDB();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $stmt = $db->prepare("
                        INSERT INTO budget_projects (project_name, description, total_budget, 
                                           start_date, end_date, status, created_by)
                        VALUES (:project_name, :description, :total_budget, 
                                :start_date, :end_date, :status, :created_by)
                    ");
                    $stmt->execute([
                        'project_name' => $_POST['project_name'],
                        'description' => $_POST['description'],
                        'total_budget' => $_POST['total_budget'],
                        'start_date' => $_POST['start_date'],
                        'end_date' => $_POST['end_date'],
                        'status' => $_POST['status'],
                        'created_by' => $_SESSION['user_id']
                    ]);
                    $_SESSION['alert'] = ['type' => 'success', 'message' => 'เพิ่มโครงการสำเร็จ'];
                    break;

                case 'edit':
                    if (!canManageProject($_POST['project_id'])) {
                        throw new Exception('คุณไม่มีสิทธิ์แก้ไขโครงการนี้');
                    }

                    $stmt = $db->prepare("
                        UPDATE budget_projects 
                        SET project_name = :project_name,
                            description = :description,
                            total_budget = :total_budget,
                            start_date = :start_date,
                            end_date = :end_date,
                            status = :status
                        WHERE project_id = :project_id
                    ");
                    $stmt->execute([
                        'project_id' => $_POST['project_id'],
                        'project_name' => $_POST['project_name'],
                        'description' => $_POST['description'],
                        'total_budget' => $_POST['total_budget'],
                        'start_date' => $_POST['start_date'],
                        'end_date' => $_POST['end_date'],
                        'status' => $_POST['status']
                    ]);
                    $_SESSION['alert'] = ['type' => 'success', 'message' => 'แก้ไขโครงการสำเร็จ'];
                    break;

                case 'delete':
                    if (!canManageProject($_POST['project_id'])) {
                        throw new Exception('คุณไม่มีสิทธิ์ลบโครงการนี้');
                    }

                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count 
                        FROM budget_transactions 
                        WHERE project_id = :project_id
                    ");
                    $stmt->execute(['project_id' => $_POST['project_id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result['count'] > 0) {
                        throw new Exception('ไม่สามารถลบโครงการที่มีการใช้งบประมาณแล้ว');
                    }

                    $stmt = $db->prepare("DELETE FROM budget_projects WHERE project_id = :project_id");
                    $stmt->execute(['project_id' => $_POST['project_id']]);
                    $_SESSION['alert'] = ['type' => 'success', 'message' => 'ลบโครงการสำเร็จ'];
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['alert'] = ['type' => 'danger', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    header('Location: projects.php');
    exit();
}

// Get current filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query with filters
$query = "
    SELECT p.*, 
           COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' 
                            THEN t.amount ELSE 0 END), 0) as used_budget,
           COUNT(DISTINCT t.transaction_id) as transaction_count
    FROM budget_projects p
    LEFT JOIN budget_transactions t ON p.project_id = t.project_id
    WHERE 1=1
";

$params = [];
if ($status_filter !== 'all') {
    $query .= " AND p.status = :status";
    $params['status'] = $status_filter;
}
if ($search) {
    $query .= " AND (p.project_name LIKE :search OR p.description LIKE :search)";
    $params['search'] = "%$search%";
}

$query .= " GROUP BY p.project_id ORDER BY p.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$budget_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'จัดการโครงการ';
$pageSubtitle = 'รายการโครงการและสถานะงบประมาณ';
$activeSystem = 'budget';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-col md:flex-row gap-4 flex-1">
            <form method="GET" class="flex flex-1 gap-2">
                <div class="relative flex-1">
                    <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" placeholder="ค้นหาชื่อโครงการ..." value="<?php echo h($search); ?>" 
                           class="w-full bg-white border border-slate-200 rounded-2xl pl-12 pr-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
                <select name="status" onchange="this.form.submit()" 
                        class="bg-white border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>ทุกสถานะ</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>ยกเลิก</option>
                </select>
                <?php if ($search || $status_filter !== 'all'): ?>
                    <a href="projects.php" class="p-3 bg-slate-100 text-slate-500 rounded-2xl hover:bg-slate-200 transition-all">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <?php if (canManage()): ?>
            <button data-bs-toggle="modal" data-bs-target="#addProjectModal" 
                    class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white px-6 py-3 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-2">
                <i class="bi bi-plus-lg"></i> เพิ่มโครงการใหม่
            </button>
        <?php endif; ?>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="bg-<?php echo $_SESSION['alert']['type'] === 'success' ? 'emerald' : 'rose'; ?>-50 border border-<?php echo $_SESSION['alert']['type'] === 'success' ? 'emerald' : 'rose'; ?>-100 text-<?php echo $_SESSION['alert']['type'] === 'success' ? 'emerald' : 'rose'; ?>-700 px-6 py-4 rounded-2xl flex items-center justify-between">
            <p class="text-sm font-bold"><i class="bi bi-info-circle-fill mr-2"></i> <?php echo $_SESSION['alert']['message']; ?></p>
            <button onclick="this.parentElement.remove()" class="text-lg opacity-50 hover:opacity-100">&times;</button>
        </div>
        <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>

    <!-- Projects Grid/Table -->
    <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 border-b border-slate-100">
                    <tr>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">โครงการ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">งบประมาณ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">ความคืบหน้า</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">สถานะ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($budget_projects)): ?>
                        <tr>
                            <td colspan="5" class="px-8 py-20 text-center">
                                <div class="flex flex-col items-center gap-4 text-slate-300">
                                    <i class="bi bi-folder2-open text-6xl"></i>
                                    <p class="font-bold">ไม่พบข้อมูลโครงการ</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($budget_projects as $project): ?>
                        <?php
                        $percentage = ($project['total_budget'] > 0) ? ($project['used_budget'] / $project['total_budget']) * 100 : 0;
                        $status_class = [
                            'active'    => 'bg-blue-50 text-blue-600',
                            'completed' => 'bg-emerald-50 text-emerald-600',
                            'cancelled' => 'bg-slate-50 text-slate-400'
                        ][$project['status']] ?? 'bg-slate-50 text-slate-400';
                        $status_label = [
                            'active'    => 'กำลังดำเนินการ',
                            'completed' => 'เสร็จสิ้น',
                            'cancelled' => 'ยกเลิก'
                        ][$project['status']] ?? 'ไม่ระบุ';
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-all group">
                            <td class="px-8 py-6">
                                <p class="text-sm font-black text-slate-800"><?php echo h($project['project_name']); ?></p>
                                <p class="text-xs text-slate-400 mt-1 line-clamp-1"><?php echo h($project['description']); ?></p>
                                <div class="flex items-center gap-2 mt-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo date('d/m/Y', strtotime($project['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($project['end_date'])); ?>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <p class="text-sm font-black text-slate-700 italic">฿<?php echo number_format($project['total_budget'], 2); ?></p>
                                <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase">ใช้ไป ฿<?php echo number_format($project['used_budget'], 2); ?></p>
                            </td>
                            <td class="px-8 py-6 min-w-[200px]">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-black text-slate-500"><?php echo number_format($percentage, 1); ?>%</span>
                                    <span class="text-[10px] font-black text-slate-300">Used</span>
                                </div>
                                <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full" 
                                         style="width: <?php echo min($percentage, 100); ?>%"></div>
                                </div>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?php echo $status_class; ?>">
                                    <?php echo $status_label; ?>
                                </span>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="transactions.php?project_id=<?php echo $project['project_id']; ?>" 
                                       class="p-2 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-600 hover:text-white transition-all" title="รายการงบประมาณ">
                                        <i class="bi bi-list-columns-reverse"></i>
                                    </a>
                                    <?php if (canManageProject($project['project_id'])): ?>
                                        <button onclick='editProject(<?php echo json_encode($project); ?>)'
                                                class="p-2 bg-amber-50 text-amber-600 rounded-xl hover:bg-amber-600 hover:text-white transition-all" title="แก้ไข">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <?php if ($project['transaction_count'] == 0): ?>
                                            <button onclick="deleteProject(<?php echo $project['project_id']; ?>, '<?php echo h($project['project_name']); ?>')"
                                                    class="p-2 bg-rose-50 text-rose-600 rounded-xl hover:bg-rose-600 hover:text-white transition-all" title="ลบ">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-[2rem] overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-8 py-6 text-white">
                <h5 class="text-xl font-black mb-1">เพิ่มโครงการใหม่</h5>
                <p class="text-xs opacity-70 font-bold uppercase tracking-widest">โครงการงบประมาณประจำปี</p>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add">
                <div class="p-8 space-y-4">
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">ชื่อโครงการ</label>
                        <input type="text" name="project_name" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">รายละเอียด</label>
                        <textarea name="description" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">งบประมาณ (บาท)</label>
                        <input type="number" name="total_budget" step="0.01" min="0" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-black focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">วันที่เริ่ม</label>
                            <input type="date" name="start_date" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">วันที่สิ้นสุด</label>
                            <input type="date" name="end_date" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">สถานะ</label>
                        <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                            <option value="active">กำลังดำเนินการ</option>
                            <option value="completed">เสร็จสิ้น</option>
                            <option value="cancelled">ยกเลิก</option>
                        </select>
                    </div>
                </div>
                <div class="p-8 bg-slate-50 flex gap-3">
                    <button type="button" data-bs-dismiss="modal" class="flex-1 px-6 py-3 rounded-2xl font-bold text-slate-500 hover:bg-slate-200 transition-all">ยกเลิก</button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editProjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-[2rem] overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-8 py-6 text-white">
                <h5 class="text-xl font-black mb-1">แก้ไขโครงการ</h5>
                <p class="text-xs opacity-70 font-bold uppercase tracking-widest">ปรับปรุงข้อมูลโครงการ</p>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="project_id" id="edit_project_id">
                <div class="p-8 space-y-4">
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">ชื่อโครงการ</label>
                        <input type="text" name="project_name" id="edit_project_name" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">รายละเอียด</label>
                        <textarea name="description" id="edit_description" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">งบประมาณ (บาท)</label>
                        <input type="number" name="total_budget" id="edit_total_budget" step="0.01" min="0" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-black focus:ring-2 focus:ring-amber-500 outline-none transition-all">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">วันที่เริ่ม</label>
                            <input type="date" name="start_date" id="edit_start_date" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">วันที่สิ้นสุด</label>
                            <input type="date" name="end_date" id="edit_end_date" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">สถานะ</label>
                        <select name="status" id="edit_status" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all">
                            <option value="active">กำลังดำเนินการ</option>
                            <option value="completed">เสร็จสิ้น</option>
                            <option value="cancelled">ยกเลิก</option>
                        </select>
                    </div>
                </div>
                <div class="p-8 bg-slate-50 flex gap-3">
                    <button type="button" data-bs-dismiss="modal" class="flex-1 px-6 py-3 rounded-2xl font-bold text-slate-500 hover:bg-slate-200 transition-all">ยกเลิก</button>
                    <button type="submit" class="flex-1 px-6 py-3 bg-amber-500 text-white rounded-2xl font-bold shadow-lg shadow-amber-200 hover:bg-amber-600 transition-all">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editProject(project) {
    document.getElementById('edit_project_id').value = project.project_id;
    document.getElementById('edit_project_name').value = project.project_name;
    document.getElementById('edit_description').value = project.description;
    document.getElementById('edit_total_budget').value = project.total_budget;
    document.getElementById('edit_start_date').value = project.start_date;
    document.getElementById('edit_end_date').value = project.end_date;
    document.getElementById('edit_status').value = project.status;
    new bootstrap.Modal(document.getElementById('editProjectModal')).show();
}

function deleteProject(id, name) {
    Swal.fire({
        title: 'ยืนยันการลบโครงการ?',
        html: `คุณต้องการลบโครงการ <strong>${name}</strong> ใช่หรือไม่?<br>การดำเนินการนี้ไม่สามารถย้อนกลับได้!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ใช่, ลบโครงการ!',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            popup: 'rounded-[2rem]',
            confirmButton: 'rounded-xl px-6 py-3 font-bold',
            cancelButton: 'rounded-xl px-6 py-3 font-bold'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="project_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>