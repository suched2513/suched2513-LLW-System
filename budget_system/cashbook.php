<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'ทะเบียนคุมเงินนอก/ใน';
$pageSubtitle = 'บันทึกการรับ-จ่ายเงินและตรวจสอบยอดเงินคงเหลือรายวัน';
$activeSystem = 'budget';

try {
    $pdo = getPdo();
    
    // Get all transactions (Disbursements as base + potential income)
    $stmt = $pdo->query("
        SELECT d.*, p.project_name 
        FROM sbms_disbursements d
        LEFT JOIN sbms_projects p ON d.project_id = p.id
        ORDER BY d.created_at DESC
    ");
    $transactions = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $transactions = [];
}

require_once __DIR__ . '/../components/layout_start.php';
?>

<style>
    .bg-navy { background-color: #0B1C3E; }
    .text-gold { color: #F59E0B; }
    .bg-gold { background-color: #F59E0B; }
</style>

<div class="space-y-6">
    <!-- Header Summary -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-navy p-8 rounded-[2.5rem] shadow-2xl text-white flex justify-between items-center relative overflow-hidden">
             <div class="absolute -right-4 -bottom-4 text-gold/10 text-8xl font-black italic">BOOK</div>
             <div>
                 <p class="text-[10px] font-black text-gold uppercase tracking-[0.2em]">ยอดเงินสดคงเหลือ</p>
                 <h3 class="text-3xl font-black mt-2">฿450,000.00</h3>
             </div>
             <i class="bi bi-safe2 text-4xl text-gold opacity-80"></i>
        </div>
        <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100 flex justify-between items-center">
             <div>
                 <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">รับเงินวันนี้</p>
                 <h3 class="text-3xl font-black text-emerald-500 mt-2">฿0.00</h3>
             </div>
             <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center">
                 <i class="bi bi-arrow-down-left text-2xl"></i>
             </div>
        </div>
        <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100 flex justify-between items-center">
             <div>
                 <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">จ่ายเงินวันนี้</p>
                 <h3 class="text-3xl font-black text-rose-500 mt-2">฿1,500.00</h3>
             </div>
             <div class="w-12 h-12 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center">
                 <i class="bi bi-arrow-up-right text-2xl"></i>
             </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100">
        <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div>
                <h4 class="text-lg font-black text-navy">ทะเบียนเงินสด (Cash Ledger)</h4>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">รายการเคลื่อนไหวทางการเงินย้อนหลัง</p>
            </div>
            <div class="flex gap-2">
                 <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm hover:bg-slate-50 transition-all">
                     <i class="bi bi-printer-fill mr-2"></i> พิมพ์ทะเบียน
                 </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-100">
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">วันที่</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">รายละเอียดรายการ</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">รายรับ (In)</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">รายจ่าย (Out)</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">คงเหลือ (Balance)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="5" class="px-8 py-20 text-center text-slate-300 font-black italic uppercase tracking-widest">
                            No Transaction Data
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php 
                    $balance = 450000; // Mock starting balance
                    foreach ($transactions as $t): 
                        $isExpense = true; // For now all are disbursements
                        if ($isExpense) $balance -= $t['amount'];
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-5 text-[11px] font-bold text-slate-500 italic"><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
                        <td class="px-8 py-5">
                            <p class="text-xs font-black text-navy"><?= htmlspecialchars($t['project_name'] ?: 'ถอนเงินสด/เช็คจ่าย') ?></p>
                            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Doc: <?= $t['doc_no'] ?: '---' ?></p>
                        </td>
                        <td class="px-8 py-5 text-right text-xs font-black text-emerald-500">---</td>
                        <td class="px-8 py-5 text-right text-xs font-black text-rose-500">฿<?= number_format($t['amount'], 2) ?></td>
                        <td class="px-8 py-5 text-right text-sm font-black text-navy tracking-tight">฿<?= number_format($balance + $t['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_start.php'; ?>
