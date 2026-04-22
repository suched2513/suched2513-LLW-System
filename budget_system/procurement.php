<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'ระบบจัดซื้อจัดจ้าง';
$pageSubtitle = 'บริหารจัดการพัสดุและการจ้างงานตามโครงการ';
$activeSystem = 'budget';

try {
    $pdo = getPdo();
    
    // Get Projects for selection
    $stmt = $pdo->query("SELECT * FROM sbms_projects WHERE status IN ('approved', 'in_progress') ORDER BY project_name");
    $projects = $stmt->fetchAll();
    
    // Get Procurement Status Counts
    $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM sbms_procurements GROUP BY status");
    $countsRaw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $counts = [
        'pr' => ($countsRaw['pr_pending'] ?? 0) + ($countsRaw['pr_approved'] ?? 0),
        'po' => $countsRaw['po_issued'] ?? 0,
        'received' => $countsRaw['received'] ?? 0,
        'paid' => 0 // Link to disbursements later
    ];
    
    // Get Procurements
    $stmt = $pdo->query("
        SELECT pr.*, p.project_name 
        FROM sbms_procurements pr
        LEFT JOIN sbms_projects p ON pr.project_id = p.id
        ORDER BY pr.created_at DESC
    ");
    $procurements = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $procurements = [];
    $projects = [];
    $counts = ['pr' => 0, 'po' => 0, 'received' => 0, 'paid' => 0];
}

require_once __DIR__ . '/../components/layout_start.php';
?>

<style>
    .bg-navy { background-color: #0B1C3E; }
    .text-gold { color: #F59E0B; }
    .bg-gold { background-color: #F59E0B; }
</style>

<div class="space-y-6">
    <!-- Action Header -->
    <div class="bg-navy p-8 rounded-[2.5rem] shadow-2xl relative overflow-hidden group">
        <div class="absolute -right-10 -top-10 w-48 h-48 bg-gold/10 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-700"></div>
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-6">
            <div>
                <h3 class="text-2xl font-black text-gold tracking-tight italic">Procurement Service</h3>
                <p class="text-[10px] text-white/60 mt-1 font-black uppercase tracking-[0.3em]">งานจัดซื้อจัดจ้างตามระเบียบพัสดุ 2560</p>
            </div>
            <div class="flex gap-4">
                <button onclick="openModal('addPRModal')" class="bg-gold hover:bg-amber-600 text-navy px-8 py-3.5 rounded-2xl font-black shadow-xl shadow-gold/20 flex items-center gap-3 transition-all hover:scale-[1.05]">
                    <i class="bi bi-cart-plus-fill"></i> สร้างใบขอซื้อ (PR)
                </button>
            </div>
        </div>
    </div>

    <!-- Workflow Steps -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <?php
        $steps = [
            ['label' => 'ขอซื้อ/จ้าง (PR)', 'count' => $counts['pr'], 'color' => 'blue', 'icon' => 'bi-file-earmark-plus'],
            ['label' => 'ใบสั่งซื้อ/จ้าง (PO)', 'count' => $counts['po'], 'color' => 'amber', 'icon' => 'bi-file-earmark-check'],
            ['label' => 'ตรวจรับพัสดุ', 'count' => $counts['received'], 'color' => 'emerald', 'icon' => 'bi-box-seam'],
            ['label' => 'เบิกจ่ายเงิน', 'count' => $counts['paid'], 'color' => 'navy', 'icon' => 'bi-wallet2'],
        ];
        foreach ($steps as $s):
        ?>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-xl flex items-center justify-between group hover:border-gold transition-all cursor-pointer">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-slate-50 text-slate-400 rounded-2xl flex items-center justify-center text-xl group-hover:bg-navy group-hover:text-gold transition-all">
                    <i class="bi <?= $s['icon'] ?>"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?= $s['label'] ?></p>
                    <p class="text-lg font-black text-navy"><?= $s['count'] ?> <span class="text-xs text-slate-300">รายการ</span></p>
                </div>
            </div>
            <i class="bi bi-chevron-right text-slate-200 group-hover:text-gold transition-colors"></i>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Procurement Table -->
    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center">
            <h4 class="text-lg font-black text-navy italic">สถานะการจัดซื้อล่าสุด</h4>
            <div class="flex gap-2">
                <input type="text" placeholder="ค้นหาเลขที่เอกสาร..." class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-2 text-xs outline-none focus:ring-2 focus:ring-gold/50">
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">เลขที่ PR/PO</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">รายการ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">โครงการ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">งบประมาณ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">สถานะ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($procurements)): ?>
                    <tr>
                        <td colspan="6" class="px-8 py-20 text-center text-slate-400 font-bold italic">
                            ยังไม่มีข้อมูลการจัดซื้อจัดจ้างในระบบ
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($procurements as $pr): 
                        $statusStyles = [
                            'pr_pending'  => 'bg-amber-50 text-amber-600',
                            'pr_approved' => 'bg-blue-50 text-blue-600',
                            'po_issued'   => 'bg-indigo-50 text-indigo-600',
                            'received'    => 'bg-emerald-50 text-emerald-600',
                            'cancelled'   => 'bg-rose-50 text-rose-600',
                        ];
                        $style = $statusStyles[$pr['status']] ?? 'bg-slate-100 text-slate-500';
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="px-8 py-5">
                            <p class="text-[11px] font-black text-navy"><?= $pr['pr_no'] ?></p>
                            <p class="text-[9px] text-slate-400 font-bold"><?= $pr['po_no'] ?: 'รอดำเนินการ PO' ?></p>
                        </td>
                        <td class="px-8 py-5 text-xs font-bold text-slate-600"><?= htmlspecialchars($pr['title']) ?></td>
                        <td class="px-8 py-5 text-[11px] font-bold text-slate-400"><?= htmlspecialchars($pr['project_name'] ?: 'การจัดซื้อทั่วไป') ?></td>
                        <td class="px-8 py-5 text-right font-black text-navy text-sm">฿<?= number_format($pr['estimated_amount'], 2) ?></td>
                        <td class="px-8 py-5 text-center">
                            <span class="px-3 py-1 rounded-full <?= $style ?> text-[9px] font-black uppercase tracking-widest"><?= str_replace('_', ' ', $pr['status']) ?></span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <button class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 hover:bg-navy hover:text-gold transition-all"><i class="bi bi-eye-fill"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add PR -->
<div id="addPRModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('addPRModal')"></div>
    <div class="bg-white rounded-[2.5rem] w-full max-w-xl relative z-10 shadow-2xl overflow-hidden">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-navy text-white">
            <h3 class="text-xl font-black italic">สร้างใบขอซื้อ/จ้าง (PR)</h3>
            <button onclick="closeModal('addPRModal')" class="text-white/60 hover:text-white transition-colors"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="api/save_procurement.php" method="POST" class="p-8 space-y-5">
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">โครงการที่เกี่ยวข้อง</label>
                <select name="project_id" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-gold/50">
                    <option value="">-- ไม่ระบุโครงการ (จัดซื้อทั่วไป) --</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ชื่อรายการจัดซื้อ/จ้าง</label>
                <input type="text" name="title" required placeholder="เช่น จัดซื้อวัสดุคอมพิวเตอร์..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-gold/50">
            </div>
            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">งบประมาณโดยประมาณ (฿)</label>
                <input type="number" step="0.01" name="estimated_amount" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-black outline-none focus:ring-2 focus:ring-gold/50">
            </div>
            <div class="pt-4">
                <button type="submit" class="w-full bg-gold hover:bg-amber-600 text-navy py-4 rounded-2xl font-black shadow-xl shadow-gold/20 transition-all">
                    บันทึกและส่งขออนุมัติ PR
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: 'บันทึกใบขอซื้อ (PR) เรียบร้อยแล้ว', timer: 2000, showConfirmButton: false, confirmButtonColor: '#F59E0B' });
    }
    if (urlParams.has('error')) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: urlParams.get('error'), confirmButtonColor: '#0B1C3E' });
    }
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
