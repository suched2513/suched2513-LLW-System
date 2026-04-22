<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'การเบิกจ่ายงบประมาณ';
$pageSubtitle = 'บันทึกประวัติการใช้จ่ายและเบิกจ่ายเงินงบประมาณ';
$activeSystem = 'budget';

try {
    $pdo = getPdo();
    
    // Get Disbursements with Project info
    $stmt = $pdo->query("
        SELECT d.*, p.project_name 
        FROM sbms_disbursements d
        LEFT JOIN sbms_projects p ON d.project_id = p.id
        ORDER BY d.created_at DESC
    ");
    $disbursements = $stmt->fetchAll();
    
    // Get Approved Projects for the form
    $stmt = $pdo->query("SELECT * FROM sbms_projects WHERE status IN ('approved', 'in_progress')");
    $projects = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $disbursements = [];
}

require_once __DIR__ . '/../components/layout_start.php';
?>

<style>
    .bg-navy { background-color: #0B1C3E; }
    .text-gold { color: #F59E0B; }
    .bg-gold { background-color: #F59E0B; }
    .border-gold { border-color: #F59E0B; }
</style>

<div class="space-y-6">
    <!-- Action Header: Navy/Gold -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-navy p-8 rounded-[2.5rem] shadow-2xl relative overflow-hidden group">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-gold/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
        <div class="relative z-10">
            <h3 class="text-xl font-black text-gold tracking-tight">การเบิกจ่ายงบประมาณ</h3>
            <p class="text-[10px] text-white/60 mt-1 font-black uppercase tracking-[0.2em]">บันทึกประวัติการใช้จ่ายและเบิกจ่ายเงิน</p>
        </div>
        <button onclick="openModal('addDisbursementModal')" class="bg-gold hover:bg-amber-600 text-navy px-8 py-3.5 rounded-2xl font-black shadow-xl shadow-gold/20 flex items-center gap-3 transition-all hover:scale-[1.05] relative z-10">
            <i class="bi bi-receipt-cutoff"></i> บันทึกเบิกจ่ายใหม่
        </button>
    </div>

    <!-- Disbursement Table -->
    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">เลขที่เอกสาร</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">ประเภท/โครงการ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">จำนวนเงิน</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">วิธีชำระ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">สถานะ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($disbursements)): ?>
                    <tr>
                        <td colspan="6" class="px-8 py-20 text-center text-slate-400 font-bold italic">
                            ยังไม่มีข้อมูลการเบิกจ่ายในระบบ
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($disbursements as $d): 
                        $statusStyles = [
                            'draft'     => 'bg-slate-100 text-slate-500',
                            'pending'   => 'bg-amber-50 text-amber-600',
                            'approved'  => 'bg-emerald-50 text-emerald-600',
                            'paid'      => 'bg-navy text-gold',
                            'cancelled' => 'bg-rose-50 text-rose-600',
                        ];
                        $statusStyle = $statusStyles[$d['status']] ?? 'bg-slate-100 text-slate-500';
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="px-8 py-5">
                            <p class="text-[11px] font-black text-navy tracking-tight"><?= $d['doc_no'] ?: '---' ?></p>
                            <p class="text-[9px] text-slate-400 font-bold"><?= date('d/m/Y', strtotime($d['created_at'])) ?></p>
                        </td>
                        <td class="px-8 py-5">
                            <p class="text-[10px] font-black text-gold uppercase tracking-widest mb-1"><?= $d['disbursement_type'] ?></p>
                            <p class="text-[12px] text-slate-600 font-bold truncate max-w-[250px]"><?= htmlspecialchars($d['project_name'] ?: 'การเบิกจ่ายทั่วไป') ?></p>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <p class="text-sm font-black text-navy tracking-tight">฿<?= number_format($d['amount'], 2) ?></p>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span class="px-2.5 py-1 bg-slate-50 border border-slate-100 rounded-lg text-[9px] font-black text-slate-400 uppercase tracking-widest">
                                <?= $d['payment_method'] ?>
                            </span>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span class="px-3 py-1 rounded-full <?= $statusStyle ?> text-[9px] font-black uppercase tracking-widest">
                                <?= $d['status'] ?>
                            </span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex justify-end gap-2">
                                <button class="w-9 h-9 rounded-xl bg-slate-50 text-slate-300 hover:bg-gold hover:text-navy transition-all">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button class="w-9 h-9 rounded-xl bg-slate-50 text-slate-300 hover:bg-navy hover:text-gold transition-all">
                                    <i class="bi bi-printer-fill"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Disbursement -->
<div id="addDisbursementModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('addDisbursementModal')"></div>
    <div class="bg-white rounded-[2.5rem] w-full max-w-2xl relative z-10 shadow-2xl overflow-hidden">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-xl font-black text-slate-800">บันทึกเบิกจ่ายใหม่</h3>
            <button onclick="closeModal('addDisbursementModal')" class="text-slate-400 hover:text-slate-600"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="api/save_disbursement.php" method="POST" class="p-8 grid grid-cols-2 gap-6">
            <div class="col-span-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">เลือกโครงการที่เบิกจ่าย</label>
                <select name="project_id" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="">-- ไม่ระบุโครงการ (เบิกจ่ายทั่วไป) --</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?> (คงเหลือ ฿<?= number_format($p['approved_amount'] - $p['used_amount'], 2) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ประเภทการเบิก</label>
                <select name="disbursement_type" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="project">ตามโครงการ</option>
                    <option value="utility">ค่าสาธารณูปโภค</option>
                    <option value="training">ค่าวิทยากร/อบรม</option>
                    <option value="travel">ค่าเดินทาง</option>
                    <option value="salary">ค่าตอบแทน/เงินเดือน</option>
                    <option value="other">อื่นๆ</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">จำนวนเงินเบิก (฿)</label>
                <input type="number" step="0.01" name="amount" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-black focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">วิธีชำระเงิน</label>
                <select name="payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="transfer">โอนเงินเข้าบัญชี</option>
                    <option value="cash">เงินสด</option>
                    <option value="cheque">เช็คขีดคร่อม</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">เลขที่เอกสารอ้างอิง</label>
                <input type="text" name="doc_no" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div class="col-span-2 pt-4">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-4 rounded-2xl font-black shadow-xl shadow-indigo-200 transition-all">
                    ยืนยันการบันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
