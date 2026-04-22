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
            <button onclick="printReport()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 transition-all shadow-lg shadow-indigo-100">
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

    <!-- Detailed Analysis -->
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-xl no-print">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h3 class="text-lg font-black text-slate-800">วิเคราะห์งบประมาณรายโครงการ</h3>
                <p class="text-xs text-slate-400 mt-1 uppercase tracking-widest font-bold">Project-based Budget Analysis</p>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">รหัสโครงการ</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ชื่อโครงการ</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">งบที่ได้รับ</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">เบิกจ่ายจริง</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">คงเหลือ</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">ร้อยละ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php 
                    $stmt = $pdo->prepare("SELECT * FROM sbms_projects WHERE fiscal_year_id = ? ORDER BY project_code");
                    $stmt->execute([$filterYear]);
                    $projects = $stmt->fetchAll();
                    
                    if (empty($projects)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-20 text-center text-slate-400 font-bold italic">ไม่พบข้อมูลโครงการในปีงบประมาณนี้</td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($projects as $p): 
                        $remain = $p['approved_amount'] - $p['used_amount'];
                        $pct = $p['approved_amount'] > 0 ? round(($p['used_amount'] / $p['approved_amount']) * 100, 1) : 0;
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 text-xs font-black text-indigo-600"><?= $p['project_code'] ?></td>
                        <td class="px-6 py-4 text-xs font-bold text-slate-700"><?= htmlspecialchars($p['project_name']) ?></td>
                        <td class="px-6 py-4 text-right text-xs font-black text-slate-800">฿<?= number_format($p['approved_amount'], 2) ?></td>
                        <td class="px-6 py-4 text-right text-xs font-black text-rose-500">฿<?= number_format($p['used_amount'], 2) ?></td>
                        <td class="px-6 py-4 text-right text-xs font-black text-emerald-600">฿<?= number_format($remain, 2) ?></td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-[10px] font-black px-2 py-1 bg-slate-100 rounded-lg"><?= $pct ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Print-Only Report Template -->
<div id="printReport" class="hidden print:block bg-white p-10 text-slate-900 font-serif">
    <div class="text-center mb-10">
        <h1 class="text-2xl font-black mb-2">รายงานสรุปการใช้จ่ายงบประมาณ</h1>
        <p class="text-lg">โรงเรียนละลมวิทยา ปีงบประมาณ พ.ศ. <?= $years[array_search($filterYear, array_column($years, 'id'))]['year_name'] ?></p>
        <div class="mt-4 border-b-2 border-slate-900 w-24 mx-auto"></div>
    </div>

    <h2 class="text-lg font-black mb-4">1. สรุปภาพรวมรายหมวดงบประมาณ</h2>
    <table class="w-full border-collapse border border-slate-900 mb-8 text-sm">
        <thead>
            <tr class="bg-slate-100">
                <th class="border border-slate-900 p-2 text-left">หมวดงบประมาณ</th>
                <th class="border border-slate-900 p-2 text-right">งบประมาณที่ได้รับ</th>
                <th class="border border-slate-900 p-2 text-right">เบิกจ่ายสะสม</th>
                <th class="border border-slate-900 p-2 text-right">คงเหลือ</th>
                <th class="border border-slate-900 p-2 text-center">ร้อยละการเบิกจ่าย</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($summary as $s): 
                $t = $types[$s['budget_type']] ?? ['label' => 'อื่นๆ'];
                $pct = $s['total'] > 0 ? round(($s['used'] / $s['total']) * 100, 1) : 0;
            ?>
            <tr>
                <td class="border border-slate-900 p-2"><?= $t['label'] ?></td>
                <td class="border border-slate-900 p-2 text-right">฿<?= number_format($s['total'], 2) ?></td>
                <td class="border border-slate-900 p-2 text-right">฿<?= number_format($s['used'], 2) ?></td>
                <td class="border border-slate-900 p-2 text-right">฿<?= number_format($s['total'] - $s['used'], 2) ?></td>
                <td class="border border-slate-900 p-2 text-center"><?= $pct ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2 class="text-lg font-black mb-4">2. รายละเอียดรายโครงการ</h2>
    <table class="w-full border-collapse border border-slate-900 text-xs">
        <thead>
            <tr class="bg-slate-100">
                <th class="border border-slate-900 p-2">รหัส</th>
                <th class="border border-slate-900 p-2 text-left">ชื่อโครงการ</th>
                <th class="border border-slate-900 p-2 text-right">งบประมาณ</th>
                <th class="border border-slate-900 p-2 text-right">เบิกจ่าย</th>
                <th class="border border-slate-900 p-2 text-right">คงเหลือ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $p): ?>
            <tr>
                <td class="border border-slate-900 p-2 text-center"><?= $p['project_code'] ?></td>
                <td class="border border-slate-900 p-2"><?= htmlspecialchars($p['project_name']) ?></td>
                <td class="border border-slate-900 p-2 text-right">฿<?= number_format($p['approved_amount'], 2) ?></td>
                <td class="border border-slate-900 p-2 text-right">฿<?= number_format($p['used_amount'], 2) ?></td>
                <td class="border border-slate-900 p-2 text-right">฿<?= number_format($p['approved_amount'] - $p['used_amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="mt-20 flex justify-end">
        <div class="text-center w-64">
            <p class="mb-10">(ลงชื่อ)......................................................</p>
            <p class="font-black">เจ้าหน้าที่งบประมาณ</p>
            <p>วันที่ <?= date('d / m / Y') ?></p>
        </div>
    </div>
</div>

<style>
    @media print {
        body * { visibility: hidden; }
        #printReport, #printReport * { visibility: visible; }
        #printReport { position: absolute; left: 0; top: 0; width: 100%; display: block !important; }
        .no-print { display: none !important; }
    }
</style>

<script>
    function printReport() {
        window.print();
    }
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
