<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
checkRole(['director', 'budget_officer', 'admin']);

$pageTitle = 'เมนูรายงานและสถิติ';
$pageSubtitle = 'เลือกประเภทรายงานที่คุณต้องการตรวจสอบหรือส่งออกข้อมูล';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    <!-- Financial Reports -->
    <div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100 group hover:border-blue-200 transition-all">
        <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
            <i class="bi bi-graph-up-arrow"></i>
        </div>
        <h4 class="text-xl font-black text-slate-800 mb-2">รายงานการเงิน</h4>
        <p class="text-sm text-slate-400 font-medium mb-6">สรุปยอดงบประมาณจัดสรร การเบิกจ่าย และยอดคงเหลือรายฝ่าย</p>
        <ul class="space-y-3">
            <li><a href="budget_overview.php" class="flex items-center justify-between text-xs font-black text-slate-600 hover:text-blue-600 uppercase tracking-widest"><span>ภาพรวมการใช้จ่าย</span> <i class="bi bi-arrow-right"></i></a></li>
            <li><a href="budget_by_dept.php" class="flex items-center justify-between text-xs font-black text-slate-600 hover:text-blue-600 uppercase tracking-widest"><span>แยกตามฝ่าย/กลุ่มงาน</span> <i class="bi bi-arrow-right"></i></a></li>
            <li><a href="annual_summary.php" class="flex items-center justify-between text-xs font-black text-slate-600 hover:text-blue-600 uppercase tracking-widest"><span>สรุปประจำปีงบประมาณ</span> <i class="bi bi-arrow-right"></i></a></li>
        </ul>
    </div>

    <!-- Project Reports -->
    <div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100 group hover:border-indigo-200 transition-all">
        <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
            <i class="bi bi-folder-check"></i>
        </div>
        <h4 class="text-xl font-black text-slate-800 mb-2">รายงานโครงการ</h4>
        <p class="text-sm text-slate-400 font-medium mb-6">ติดตามความคืบหน้าของโครงการและการดำเนินการตามแผนงาน</p>
        <ul class="space-y-3">
            <li><a href="project_progress.php" class="flex items-center justify-between text-xs font-black text-slate-600 hover:text-indigo-600 uppercase tracking-widest"><span>สถานะโครงการรายฝ่าย</span> <i class="bi bi-arrow-right"></i></a></li>
            <li><a href="project_overdue.php" class="flex items-center justify-between text-xs font-black text-slate-600 hover:text-indigo-600 uppercase tracking-widest"><span>โครงการค้างดำเนินการ</span> <i class="bi bi-arrow-right"></i></a></li>
            <li><a href="procurement_type.php" class="flex items-center justify-between text-xs font-black text-slate-600 hover:text-indigo-600 uppercase tracking-widest"><span>สัดส่วนจัดซื้อ/จัดจ้าง</span> <i class="bi bi-arrow-right"></i></a></li>
        </ul>
    </div>

    <!-- System Admin Reports -->
    <div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-slate-200/50 border border-slate-100 group hover:border-rose-200 transition-all">
        <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
            <i class="bi bi-shield-check"></i>
        </div>
        <h4 class="text-xl font-black text-slate-800 mb-2">ตรวจสอบระบบ</h4>
        <p class="text-sm text-slate-400 font-medium mb-6">เฉพาะผู้ดูแลระบบ เพื่อตรวจสอบความโปร่งใสและการใช้งาน</p>
        <ul class="space-y-3">
            <li><a href="audit_log.php" class="flex items-center justify-between text-xs font-black text-slate-600 hover:text-rose-600 uppercase tracking-widest"><span>Audit Logs</span> <i class="bi bi-arrow-right"></i></a></li>
            <li><a href="#" class="flex items-center justify-between text-xs font-black text-slate-400 uppercase tracking-widest cursor-not-allowed"><span>สถิติการใช้งานระบบ</span> <span class="bg-slate-100 px-2 py-0.5 rounded text-[9px]">Coming Soon</span></a></li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
