<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'รายงานงบประมาณ';
$pageSubtitle = 'สรุปภาพรวมและรายละเอียดการเงินประจำปีงบประมาณ';
$activeSystem = 'budget';

try {
    $pdo = getPdo();
    
    // Get Fiscal Years for filter
    $stmt = $pdo->query("SELECT * FROM sbms_fiscal_years ORDER BY year_name DESC");
    $years = $stmt->fetchAll();
    
    $filterYear = $_GET['year_id'] ?? ($years[0]['id'] ?? null);
    
    // Get Summary by Budget Type
    $summary = [];
    if ($filterYear) {
        $stmt = $pdo->prepare("
            SELECT budget_type, SUM(total_amount) as total, SUM(used_amount) as used 
            FROM sbms_budgets 
            WHERE fiscal_year_id = ? 
            GROUP BY budget_type
        ");
        $stmt->execute([$filterYear]);
        $summary = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $summary = [];
}

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="space-y-6">
    <!-- Filter Section -->
    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-xl flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                <i class="bi bi-filter-square-fill"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">เลือกรายงานประจำปี</p>
                <select onchange="window.location.href='?year_id='+this.value" class="bg-transparent border-none text-sm font-black text-slate-800 outline-none cursor-pointer">
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $filterYear == $y['id'] ? 'selected' : '' ?>>ปี พ.ศ. <?= $y['year_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="ml-auto flex gap-2">
            <button class="bg-slate-50 hover:bg-slate-100 text-slate-600 px-5 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 transition-all">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </button>
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 transition-all shadow-lg shadow-indigo-100">
                <i class="bi bi-printer"></i> พิมพ์รายงาน PDF
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php 
        $types = [
            'government' => ['label' => 'งบอุดหนุนรัฐบาล', 'color' => 'blue', 'icon' => 'bi-bank'],
            'subsidy'    => ['label' => 'งบเรียนฟรี 15 ปี', 'color' => 'emerald', 'icon' => 'bi-book'],
            'revenue'    => ['label' => 'เงินระดมทรัพยากร', 'color' => 'amber', 'icon' => 'bi-wallet2'],
        ];
        foreach ($summary as $s): 
            $t = $types[$s['budget_type']] ?? ['label' => 'อื่นๆ', 'color' => 'slate', 'icon' => 'bi-plus-circle'];
            $percent = $s['total'] > 0 ? round(($s['used'] / $s['total']) * 100, 1) : 0;
        ?>
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl relative overflow-hidden group">
            <div class="absolute -right-8 -bottom-8 text-<?= $t['color'] ?>-50 opacity-[0.05] text-[10rem] group-hover:scale-110 transition-transform">
                <i class="bi <?= $t['icon'] ?>"></i>
            </div>
            <div class="relative z-10">
                <div class="w-12 h-12 bg-<?= $t['color'] ?>-50 text-<?= $t['color'] ?>-500 rounded-2xl flex items-center justify-center mb-6">
                    <i class="bi <?= $t['icon'] ?> text-2xl"></i>
                </div>
                <h4 class="text-sm font-black text-slate-800"><?= $t['label'] ?></h4>
                <div class="mt-4 space-y-1">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">ใช้ไปแล้ว / ทั้งหมด</p>
                    <p class="text-xl font-black text-slate-800">
                        ฿<?= number_format($s['used'], 2) ?> / <span class="text-slate-400 font-bold">฿<?= number_format($s['total'], 2) ?></span>
                    </p>
                </div>
                <div class="mt-6">
                    <div class="w-full h-2 bg-slate-50 rounded-full overflow-hidden">
                        <div class="h-full bg-<?= $t['color'] ?>-500 rounded-full" style="width: <?= $percent ?>%"></div>
                    </div>
                    <p class="text-right text-[10px] font-black text-<?= $t['color'] ?>-600 mt-2 uppercase"><?= $percent ?>% EXPENDED</p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Detailed Analysis Placeholder -->
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h3 class="text-lg font-black text-slate-800">วิเคราะห์งบประมาณรายโครงการ</h3>
                <p class="text-xs text-slate-400 mt-1 uppercase tracking-widest font-bold">Project-based Budget Analysis</p>
            </div>
        </div>
        
        <div class="h-64 flex items-center justify-center bg-slate-50/50 rounded-3xl border border-dashed border-slate-200">
            <div class="text-center">
                <i class="bi bi-graph-up-arrow text-4xl text-slate-300"></i>
                <p class="text-sm text-slate-400 mt-2 font-bold italic">กรุณาเลือกปีงบประมาณเพื่อดูรายละเอียด...</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
