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
    $stmt = $pdo->query("SELECT * FROM sbms_projects WHERE status IN ('approved', 'in_progress')");
    $projects = $stmt->fetchAll();
    
    // Get Vendors
    $stmt = $pdo->query("SELECT * FROM sbms_vendors");
    $vendors = $stmt->fetchAll();
    
    // Get Procurement Logs (Mock or real if table exists)
    // For now, let's assume we use disbursements as a base or have a sbms_procurements table
    // I'll create a simple list view
    $procurements = []; // Empty for now or fetch from a dedicated table
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $procurements = [];
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
                <button class="bg-gold hover:bg-amber-600 text-navy px-8 py-3.5 rounded-2xl font-black shadow-xl shadow-gold/20 flex items-center gap-3 transition-all hover:scale-[1.05]">
                    <i class="bi bi-cart-plus-fill"></i> สร้างใบขอซื้อ (PR)
                </button>
            </div>
        </div>
    </div>

    <!-- Workflow Steps -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <?php
        $steps = [
            ['label' => 'ขอซื้อ/จ้าง (PR)', 'count' => 5, 'color' => 'blue', 'icon' => 'bi-file-earmark-plus'],
            ['label' => 'ใบสั่งซื้อ/จ้าง (PO)', 'count' => 2, 'color' => 'amber', 'icon' => 'bi-file-earmark-check'],
            ['label' => 'ตรวจรับพัสดุ', 'count' => 8, 'color' => 'emerald', 'icon' => 'bi-box-seam'],
            ['label' => 'เบิกจ่ายเงิน', 'count' => 12, 'color' => 'navy', 'icon' => 'bi-wallet2'],
        ];
        foreach ($steps as $s):
        ?>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-xl flex items-center justify-between group hover:border-gold transition-all">
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

    <!-- Procurement Table Placeholder -->
    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center">
            <h4 class="text-lg font-black text-navy italic">สถานะการจัดซื้อล่าสุด</h4>
            <div class="flex gap-2">
                <input type="text" placeholder="ค้นหาเลขที่เอกสาร..." class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-2 text-xs outline-none focus:ring-2 focus:ring-gold/50">
            </div>
        </div>
        <div class="px-8 py-20 text-center">
            <div class="w-20 h-20 bg-slate-50 text-slate-200 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="bi bi-clipboard-data text-4xl"></i>
            </div>
            <h5 class="text-slate-400 font-bold italic">อยู่ระหว่างการพัฒนาโมดูลจัดซื้อเชิงลึก</h5>
            <p class="text-[10px] text-slate-300 uppercase tracking-[0.2em] font-black mt-2">Module is coming soon in next update</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
