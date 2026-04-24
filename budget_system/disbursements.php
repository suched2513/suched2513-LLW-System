<?php
require_once 'config.php';
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$db = connectDB();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_request') {
    try {
        $db->beginTransaction();

        $project_id = $_POST['project_id'];
        $activity_name = $_POST['activity_name'];
        $reason = $_POST['reason'];
        $fund_source_id = $_POST['fund_source_id'];
        $request_date = $_POST['request_date'];
        
        // Calculate Total Amount from items
        $total_amount = 0;
        $items = $_POST['items'] ?? [];
        foreach ($items as $item) {
            $total_amount += ($item['quantity'] * $item['price_per_unit']);
        }

        // 1. Insert Disbursement Header
        $stmt = $db->prepare("
            INSERT INTO budget_disbursements (project_id, activity_name, reason, fund_source_id, total_amount, request_date, requested_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $project_id, $activity_name, $reason, $fund_source_id, $total_amount, $request_date, $_SESSION['user_id']
        ]);
        $disbursement_id = $db->lastInsertId();

        // 2. Insert Items
        $stmtItem = $db->prepare("
            INSERT INTO budget_disbursement_items (disbursement_id, item_name, quantity, unit, price_per_unit, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $item) {
            $row_total = $item['quantity'] * $item['price_per_unit'];
            $stmtItem->execute([
                $disbursement_id, $item['name'], $item['quantity'], $item['unit'], $item['price_per_unit'], $row_total
            ]);
        }

        $db->commit();
        $_SESSION['alert'] = ['type' => 'success', 'message' => 'สร้างคำขออนุมัติเรียบร้อยแล้ว'];
        header("Location: view_request.php?id=$disbursement_id");
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['alert'] = ['type' => 'danger', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

// Fetch Projects for dropdown
$projects = $db->query("SELECT project_id, project_name, total_budget FROM budget_projects WHERE status = 'active' ORDER BY project_name")->fetchAll(PDO::FETCH_ASSOC);
$fund_sources = getFundSources();

$pageTitle = 'ยื่นขออนุมัติใช้เงิน';
$pageSubtitle = 'สร้างบันทึกข้อความขออนุมัติจัดซื้อ/จัดจ้าง';
$activeSystem = 'budget';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="max-w-5xl mx-auto">
    <form method="POST" class="space-y-6" id="disbursementForm">
        <input type="hidden" name="action" value="create_request">
        
        <!-- Header Info Card -->
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8 md:p-10">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-xl">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-slate-800">รายละเอียดคำขออนุมัติ</h3>
                    <p class="text-sm text-slate-400 font-bold uppercase tracking-widest">ข้อมูลเบื้องต้นของบันทึกข้อความ</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">เลือกโครงการ</label>
                        <select name="project_id" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                            <option value="">-- กรุณาเลือกโครงการ --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?php echo $p['project_id']; ?>"><?php echo h($p['project_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">ชื่อกิจกรรม</label>
                        <input type="text" name="activity_name" required placeholder="เช่น กิจกรรมปรับปรุงซ่อมถนน..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">แหล่งเงินทุน</label>
                            <select name="fund_source_id" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                                <?php foreach ($fund_sources as $fs): ?>
                                    <option value="<?php echo $fs['source_id']; ?>"><?php echo h($fs['source_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">วันที่ยื่นขอ</label>
                            <input type="date" name="request_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">เหตุผลที่ขอใช้</label>
                        <textarea name="reason" rows="2" placeholder="ระบุเหตุผลความจำเป็น..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Card -->
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
            <div class="p-8 md:p-10 border-b border-slate-50 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center text-xl">
                        <i class="bi bi-list-check"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-slate-800">รายการจัดซื้อ/จัดจ้าง</h3>
                        <p class="text-sm text-slate-400 font-bold uppercase tracking-widest">รายละเอียดสิ่งของและงบประมาณ</p>
                    </div>
                </div>
                <button type="button" onclick="addItem()" class="bg-indigo-50 text-indigo-600 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all flex items-center gap-2">
                    <i class="bi bi-plus-lg"></i> เพิ่มรายการ
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full" id="itemsTable">
                    <thead class="bg-slate-50/50 border-b border-slate-100">
                        <tr>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-16">ลำดับ</th>
                            <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">รายการ</th>
                            <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-32">จำนวน</th>
                            <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-32">หน่วย</th>
                            <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-40">ราคา/หน่วย</th>
                            <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-40 text-right">รวมเป็นเงิน</th>
                            <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-16 text-center">ลบ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <!-- Items will be added here -->
                    </tbody>
                    <tfoot class="bg-slate-50/30">
                        <tr>
                            <td colspan="5" class="px-8 py-6 text-right font-black text-slate-500 uppercase tracking-widest">รวมทั้งสิ้น</td>
                            <td class="px-4 py-6 text-right font-black text-indigo-600 text-lg italic" id="grandTotal">฿0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-4 pb-10">
            <a href="index.php" class="px-8 py-4 bg-white text-slate-400 rounded-2xl font-black uppercase tracking-widest hover:bg-slate-50 transition-all border border-slate-100">ยกเลิก</a>
            <button type="submit" class="px-10 py-4 bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-blue-200 hover:scale-[1.02] active:scale-95 transition-all">
                บันทึกและออกเอกสาร <i class="bi bi-arrow-right ml-2"></i>
            </button>
        </div>
    </form>
</div>

<script>
let itemCount = 0;

function addItem() {
    const tbody = document.querySelector('#itemsTable tbody');
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50/50 transition-all group';
    tr.innerHTML = `
        <td class="px-8 py-4 text-center font-bold text-slate-400">${itemCount + 1}</td>
        <td class="px-4 py-4">
            <input type="text" name="items[${itemCount}][name]" required class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
        </td>
        <td class="px-4 py-4">
            <input type="number" name="items[${itemCount}][quantity]" step="0.01" value="1" required onchange="calculateRow(this)" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-2 text-sm font-bold text-center outline-none focus:ring-2 focus:ring-indigo-500 transition-all qty-input">
        </td>
        <td class="px-4 py-4">
            <input type="text" name="items[${itemCount}][unit]" placeholder="หน่วย" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
        </td>
        <td class="px-4 py-4">
            <input type="number" name="items[${itemCount}][price_per_unit]" step="0.01" value="0.00" required onchange="calculateRow(this)" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-2 text-sm font-black text-right outline-none focus:ring-2 focus:ring-indigo-500 transition-all price-input">
        </td>
        <td class="px-4 py-4 text-right font-black text-slate-700 row-total">฿0.00</td>
        <td class="px-8 py-4 text-center">
            <button type="button" onclick="removeItem(this)" class="text-slate-300 hover:text-rose-500 transition-all">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    itemCount++;
    updateRowNumbers();
}

function removeItem(btn) {
    btn.closest('tr').remove();
    updateRowNumbers();
    calculateGrandTotal();
}

function updateRowNumbers() {
    const rows = document.querySelectorAll('#itemsTable tbody tr');
    rows.forEach((row, index) => {
        row.querySelector('td:first-child').textContent = index + 1;
    });
}

function calculateRow(input) {
    const row = input.closest('tr');
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const price = parseFloat(row.querySelector('.price-input').value) || 0;
    const total = qty * price;
    row.querySelector('.row-total').textContent = '฿' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    calculateGrandTotal();
}

function calculateGrandTotal() {
    const rows = document.querySelectorAll('#itemsTable tbody tr');
    let total = 0;
    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        total += (qty * price);
    });
    document.getElementById('grandTotal').textContent = '฿' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Add first item automatically
window.onload = function() {
    addItem();
};
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
