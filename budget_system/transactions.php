<?php
require_once 'config.php';
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$db = connectDB();
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// ตรวจสอบว่าโครงการมีอยู่จริง
try {
    $stmt = $db->prepare("
        SELECT p.*, 
            (SELECT SUM(amount) FROM budget_transactions 
             WHERE project_id = p.project_id AND transaction_type = 'expense') as used_budget
        FROM budget_projects p 
        WHERE project_id = ?
    ");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        $_SESSION['alert'] = ['type' => 'danger', 'message' => 'ไม่พบข้อมูลโครงการ'];
        header('Location: projects.php');
        exit();
    }
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    if ($_POST['transaction_type'] === 'expense') {
                        $stmt = $db->prepare("
                            SELECT 
                                p.total_budget,
                                COALESCE(SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
                                COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense
                            FROM budget_projects p
                            LEFT JOIN budget_transactions t ON p.project_id = t.project_id
                            WHERE p.project_id = :project_id
                            GROUP BY p.project_id
                        ");
                        $stmt->execute(['project_id' => $project_id]);
                        $budget_info = $stmt->fetch(PDO::FETCH_ASSOC);

                        $total_budget = $budget_info['total_budget'] + $budget_info['total_income'];
                        $remaining = $total_budget - $budget_info['total_expense'];

                        if ($_POST['amount'] > $remaining) {
                            throw new Exception("งบประมาณไม่เพียงพอ (คงเหลือ " . number_format($remaining, 2) . " บาท)");
                        }
                    }

                    $stmt = $db->prepare("
                        INSERT INTO budget_transactions (project_id, amount, transaction_type, description, 
                                                       transaction_date, created_by)
                        VALUES (:project_id, :amount, :transaction_type, :description, :transaction_date, :created_by)
                    ");
                    $stmt->execute([
                        'project_id' => $project_id,
                        'amount' => $_POST['amount'],
                        'transaction_type' => $_POST['transaction_type'],
                        'description' => $_POST['description'],
                        'transaction_date' => $_POST['transaction_date'],
                        'created_by' => $_SESSION['user_id']
                    ]);
                    $_SESSION['alert'] = [
                        'type' => 'success',
                        'message' => 'เพิ่ม' . ($_POST['transaction_type'] === 'income' ? 'รายรับ' : 'รายจ่าย') . 'สำเร็จ'
                    ];
                    break;

                case 'delete':
                    if (!canManageProject($project_id)) {
                        throw new Exception("คุณไม่มีสิทธิ์ลบรายการนี้");
                    }

                    $stmt = $db->prepare("DELETE FROM budget_transactions WHERE transaction_id = :transaction_id AND project_id = :project_id");
                    $stmt->execute([
                        'transaction_id' => $_POST['transaction_id'],
                        'project_id' => $project_id
                    ]);

                    $_SESSION['alert'] = ['type' => 'success', 'message' => 'ลบรายการสำเร็จ'];
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['alert'] = ['type' => 'danger', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    header("Location: transactions.php?project_id=$project_id");
    exit();
}

// ดึงข้อมูลรายการงบประมาณ
$stmt = $db->prepare("
    SELECT t.*, u.username
    FROM budget_transactions t
    JOIN llw_users u ON t.created_by = u.user_id
    WHERE t.project_id = ?
    ORDER BY t.transaction_date DESC, t.created_at DESC
");
$stmt->execute([$project_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงสรุปยอด
$stmt = $db->prepare("
    SELECT p.*,
           COALESCE(SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense,
           (SELECT COALESCE(SUM(amount), 0) FROM budget_transactions WHERE project_id = p.project_id AND transaction_type = 'income') as total_income
    FROM budget_projects p
    LEFT JOIN budget_transactions t ON p.project_id = t.project_id
    WHERE p.project_id = ?
    GROUP BY p.project_id
");
$stmt->execute([$project_id]);
$project_summary = $stmt->fetch(PDO::FETCH_ASSOC);

$total_budget_with_income = $project_summary['total_budget'] + $project_summary['total_income'];
$remaining_budget = $total_budget_with_income - $project_summary['total_expense'];
$usage_percentage = ($total_budget_with_income > 0) ? ($project_summary['total_expense'] / $total_budget_with_income) * 100 : 0;

$pageTitle = 'รายการงบประมาณ';
$pageSubtitle = h($project['project_name']);
$activeSystem = 'budget';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="space-y-6">
    <!-- Project Info Card -->
    <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <a href="projects.php" class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center hover:bg-slate-100 hover:text-slate-600 transition-all">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h3 class="text-xl font-black text-slate-800"><?php echo h($project['project_name']); ?></h3>
            </div>
            <p class="text-sm text-slate-400 ml-13"><?php echo h($project['description']); ?></p>
        </div>
        <div class="flex gap-2">
            <?php if (canEdit()): ?>
                <button data-bs-toggle="modal" data-bs-target="#addTransactionModal" 
                        class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white px-6 py-3 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-2">
                    <i class="bi bi-plus-lg"></i> เพิ่มรายการใหม่
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-3xl p-6 shadow-xl shadow-slate-100/50 border border-slate-100 flex flex-col justify-between overflow-hidden relative group">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">งบประมาณรวม</p>
                <p class="text-2xl font-black text-slate-800 italic">฿<?php echo number_format($total_budget_with_income, 2); ?></p>
            </div>
            <div class="mt-4 flex items-center gap-2 text-[10px] font-bold text-slate-400">
                <span>Start: ฿<?php echo number_format($project['total_budget'], 2); ?></span>
                <span class="text-emerald-500">+฿<?php echo number_format($project_summary['total_income'], 2); ?></span>
            </div>
            <i class="bi bi-bank absolute -right-2 -bottom-2 text-6xl opacity-5 group-hover:scale-110 transition-transform"></i>
        </div>

        <div class="bg-white rounded-3xl p-6 shadow-xl shadow-slate-100/50 border border-slate-100 flex flex-col justify-between overflow-hidden relative group">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">งบประมาณคงเหลือ</p>
                <p class="text-2xl font-black text-emerald-600 italic">฿<?php echo number_format($remaining_budget, 2); ?></p>
            </div>
            <div class="mt-4 flex items-center justify-between">
                <span class="text-[10px] font-black text-emerald-500"><?php echo number_format(100 - $usage_percentage, 1); ?>% Left</span>
                <div class="h-1.5 w-24 bg-slate-50 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500" style="width: <?php echo max(0, 100 - $usage_percentage); ?>%"></div>
                </div>
            </div>
            <i class="bi bi-piggy-bank absolute -right-2 -bottom-2 text-6xl opacity-5 group-hover:scale-110 transition-transform"></i>
        </div>

        <div class="bg-white rounded-3xl p-6 shadow-xl shadow-slate-100/50 border border-slate-100 flex flex-col justify-between overflow-hidden relative group">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ใช้ไปแล้ว</p>
                <p class="text-2xl font-black text-rose-500 italic">฿<?php echo number_format($project_summary['total_expense'], 2); ?></p>
            </div>
            <div class="mt-4 flex items-center justify-between">
                <span class="text-[10px] font-black text-rose-400"><?php echo number_format($usage_percentage, 1); ?>% Used</span>
                <div class="h-1.5 w-24 bg-slate-50 rounded-full overflow-hidden">
                    <div class="h-full bg-rose-500" style="width: <?php echo min(100, $usage_percentage); ?>%"></div>
                </div>
            </div>
            <i class="bi bi-cart-dash absolute -right-2 -bottom-2 text-6xl opacity-5 group-hover:scale-110 transition-transform"></i>
        </div>

        <div class="bg-white rounded-3xl p-6 shadow-xl shadow-slate-100/50 border border-slate-100 flex flex-col justify-between overflow-hidden relative group">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">รายรับเพิ่มเติม</p>
                <p class="text-2xl font-black text-indigo-600 italic">฿<?php echo number_format($project_summary['total_income'], 2); ?></p>
            </div>
            <div class="mt-4 flex items-center gap-2 text-[10px] font-bold text-slate-400">
                <i class="bi bi-collection"></i> <?php echo count($transactions); ?> รายการทั้งหมด
            </div>
            <i class="bi bi-box-arrow-in-down absolute -right-2 -bottom-2 text-6xl opacity-5 group-hover:scale-110 transition-transform"></i>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="bg-<?php echo $_SESSION['alert']['type'] === 'success' ? 'emerald' : 'rose'; ?>-50 border border-<?php echo $_SESSION['alert']['type'] === 'success' ? 'emerald' : 'rose'; ?>-100 text-<?php echo $_SESSION['alert']['type'] === 'success' ? 'emerald' : 'rose'; ?>-700 px-6 py-4 rounded-2xl flex items-center justify-between">
            <p class="text-sm font-bold"><i class="bi bi-info-circle-fill mr-2"></i> <?php echo $_SESSION['alert']['message']; ?></p>
            <button onclick="this.parentElement.remove()" class="text-lg opacity-50 hover:opacity-100">&times;</button>
        </div>
        <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>

    <!-- Transactions List -->
    <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-lg font-black text-slate-800">ประวัติรายการ</h3>
            <button class="text-xs font-black text-slate-400 uppercase tracking-widest hover:text-blue-600 transition-colors">เรียงตามวันที่ <i class="bi bi-sort-down"></i></button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50">
                    <tr>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">วันที่</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">รายละเอียด</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">ประเภท</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">จำนวนเงิน</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">ผู้บันทึก</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" class="px-8 py-20 text-center text-slate-300 font-bold">ไม่พบรายการธุรกรรม</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr class="hover:bg-slate-50/50 transition-all group">
                            <td class="px-8 py-5 text-sm font-medium text-slate-500">
                                <?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?>
                            </td>
                            <td class="px-8 py-5">
                                <p class="text-sm font-bold text-slate-700"><?php echo h($transaction['description']); ?></p>
                            </td>
                            <td class="px-8 py-5">
                                <?php if($transaction['transaction_type'] == 'income'): ?>
                                    <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[9px] font-black uppercase">Income</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full bg-rose-50 text-rose-600 text-[9px] font-black uppercase">Expense</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <p class="text-sm font-black italic <?php echo $transaction['transaction_type'] == 'income' ? 'text-emerald-600' : 'text-rose-500'; ?>">
                                    <?php echo $transaction['transaction_type'] == 'income' ? '+' : '-'; ?>
                                    <?php echo number_format($transaction['amount'], 2); ?>
                                </p>
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-lg bg-slate-100 flex items-center justify-center text-[10px] font-black text-slate-500 uppercase">
                                        <?php echo mb_substr($transaction['username'] ?? 'U', 0, 1); ?>
                                    </div>
                                    <span class="text-[11px] font-bold text-slate-500"><?php echo h($transaction['username'] ?? 'System'); ?></span>
                                </div>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <?php if (canManage()): ?>
                                    <button onclick="deleteTransaction(<?php echo $transaction['transaction_id']; ?>)" 
                                            class="p-2 text-slate-300 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition-all">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add Transaction -->
<div class="modal fade" id="addTransactionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-[2.5rem] overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-indigo-600 to-blue-700 px-10 py-8 text-white relative">
                <h5 class="text-2xl font-black mb-1">บันทึกรายการใหม่</h5>
                <p class="text-xs opacity-70 font-bold uppercase tracking-[0.2em]">Add New Transaction</p>
                <i class="bi bi-plus-circle absolute right-8 top-1/2 -translate-y-1/2 text-5xl opacity-20"></i>
            </div>
            <form method="POST" id="transactionForm" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add">
                <div class="p-10 space-y-6 bg-white">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">ประเภทรายการ</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="transaction_type" value="expense" checked class="peer hidden">
                                <div class="p-4 rounded-2xl border-2 border-slate-100 text-center peer-checked:border-rose-500 peer-checked:bg-rose-50/50 transition-all group-hover:bg-slate-50">
                                    <i class="bi bi-dash-circle-fill text-rose-500 text-xl block mb-1"></i>
                                    <span class="text-xs font-black text-slate-600 uppercase tracking-widest">รายจ่าย</span>
                                </div>
                            </label>
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="transaction_type" value="income" class="peer hidden">
                                <div class="p-4 rounded-2xl border-2 border-slate-100 text-center peer-checked:border-emerald-500 peer-checked:bg-emerald-50/50 transition-all group-hover:bg-slate-50">
                                    <i class="bi bi-plus-circle-fill text-emerald-500 text-xl block mb-1"></i>
                                    <span class="text-xs font-black text-slate-600 uppercase tracking-widest">รายรับ</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">จำนวนเงิน (บาท)</label>
                        <input type="number" name="amount" step="0.01" min="0.01" required 
                               class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-6 py-4 text-2xl font-black text-slate-800 focus:border-blue-500 focus:bg-white outline-none transition-all placeholder:text-slate-300" placeholder="0.00">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">รายละเอียดรายการ</label>
                        <textarea name="description" rows="3" required 
                                  class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 focus:border-blue-500 focus:bg-white outline-none transition-all placeholder:text-slate-300" placeholder="ระบุวัตถุประสงค์หรือรายละเอียดการใช้จ่าย..."></textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">วันที่ทำรายการ</label>
                        <input type="date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required 
                               class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-700 focus:border-blue-500 focus:bg-white outline-none transition-all">
                    </div>
                </div>
                <div class="p-10 bg-slate-50 flex gap-4">
                    <button type="button" data-bs-dismiss="modal" 
                            class="flex-1 px-6 py-4 rounded-2xl font-black text-slate-400 uppercase tracking-widest hover:bg-slate-200 transition-all">Cancel</button>
                    <button type="submit" 
                            class="flex-1 px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-2xl font-black shadow-xl shadow-blue-200 uppercase tracking-widest hover:scale-[1.02] active:scale-95 transition-all">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteTransaction(id) {
    Swal.fire({
        title: 'ลบรายการธุรกรรม?',
        text: "คุณต้องการลบรายการนี้ใช่หรือไม่? การดำเนินการนี้ไม่สามารถย้อนกลับได้",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            popup: 'rounded-[2rem]',
            confirmButton: 'rounded-xl px-6 py-3 font-black uppercase tracking-widest text-[10px]',
            cancelButton: 'rounded-xl px-6 py-3 font-black uppercase tracking-widest text-[10px]'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="transaction_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Date Validation
document.getElementById('transactionForm').addEventListener('submit', function (e) {
    const transactionDate = new Date(this.transaction_date.value);
    const startDate = new Date('<?php echo $project['start_date']; ?>');
    const endDate = new Date('<?php echo $project['end_date']; ?>');

    if (transactionDate < startDate || transactionDate > endDate) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'วันที่ไม่อยู่ในระยะเวลาโครงการ',
            text: 'กรุณาเลือกวันที่ระหว่าง <?php echo date('d/m/Y', strtotime($project['start_date'])); ?> ถึง <?php echo date('d/m/Y', strtotime($project['end_date'])); ?>',
            customClass: { popup: 'rounded-[2rem]' }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>