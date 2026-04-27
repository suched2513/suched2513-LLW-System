<?php
session_start();
$pageTitle = 'ขอดำเนินโครงการ';
$pageSubtitle = 'กรอกรายละเอียดคำขอใช้เงินงบประมาณ';
require_once __DIR__ . '/../components/layout_start.php';

$user = currentUser();
$pdo = getPdo();

// Fetch projects for Step 1
$stmt = $pdo->prepare("SELECT * FROM budget_projects WHERE owner_name = ? AND is_active = 1");
$stmt->execute([$user['full_name']]);
$projects = $stmt->fetchAll();

// Pre-select project if ID is in URL
$preSelectedId = $_GET['project_id'] ?? '';
?>

<!-- Stepper Navigation -->
<div class="max-w-4xl mx-auto mb-10">
    <div class="flex items-center justify-between px-4 relative">
        <div class="absolute top-1/2 left-0 w-full h-1 bg-slate-100 -z-10 -translate-y-1/2"></div>
        <?php for($i=1;$i<=4;$i++): ?>
        <div class="step-indicator flex flex-col items-center gap-2" data-step="<?= $i ?>">
            <div class="w-10 h-10 rounded-full flex items-center justify-center font-black text-sm <?= $i==1 ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-400' ?> transition-all">
                <?= $i ?>
            </div>
            <span class="text-[9px] font-black uppercase tracking-widest <?= $i==1 ? 'text-blue-600' : 'text-slate-400' ?>">Step <?= $i ?></span>
        </div>
        <?php endfor; ?>
    </div>
</div>

<form id="wizardForm" action="<?= BASE_URL ?>/api/save_request.php" method="POST" class="max-w-4xl mx-auto">
    <!-- Step 1: Project & Info -->
    <div class="wizard-step bg-white rounded-[2.5rem] p-8 sm:p-10 shadow-xl shadow-slate-200/50 border border-slate-100 animate-fade-in-up" data-step="1">
        <h4 class="text-xl font-black text-slate-800 mb-8 flex items-center gap-3">
            <i class="bi bi-info-circle-fill text-blue-600"></i>
            ข้อมูลโครงการ
        </h4>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="md:col-span-2">
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">เลือกโครงการ / กิจกรรม</label>
                <select name="budget_project_id" id="project_id" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all" required onchange="updateBudgetInfo(this)">
                    <option value="">-- กรุณาเลือกโครงการ --</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>" 
                            data-limit="<?= $p['budget_subsidy'] + $p['budget_quality'] + $p['budget_revenue'] + $p['budget_operation'] + $p['budget_reserve'] ?>"
                            <?= $preSelectedId == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['activity']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">วันที่ขอดำเนินการ</label>
                <input type="date" name="request_date" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all" required>
            </div>

            <div>
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">ประเภทการดำเนินการ</label>
                <div class="flex gap-4">
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="proc_type" value="buy" class="hidden peer" checked>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-center peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 transition-all">
                            <i class="bi bi-cart-fill text-xl block mb-1"></i>
                            <span class="text-xs font-black uppercase">จัดซื้อ</span>
                        </div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="proc_type" value="hire" class="hidden peer">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-center peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 transition-all">
                            <i class="bi bi-tools text-xl block mb-1"></i>
                            <span class="text-xs font-black uppercase">จัดจ้าง</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="md:col-span-2">
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">เหตุผลความจำเป็น</label>
                <textarea name="reason" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold focus:ring-4 focus:ring-blue-100 outline-none transition-all" rows="3" placeholder="ระบุเหตุผลที่ต้องการขอใช้งบประมาณ..."></textarea>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="button" onclick="goToStep(2)" class="bg-blue-600 text-white px-10 py-4 rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:bg-blue-700 transition-all">
                ถัดไป: รายการของใช้เงิน
            </button>
        </div>
    </div>

    <!-- Step 2: Items Table -->
    <div class="wizard-step bg-white rounded-[2.5rem] p-8 sm:p-10 shadow-xl shadow-slate-200/50 border border-slate-100 animate-fade-in-up hidden" data-step="2">
        <h4 class="text-xl font-black text-slate-800 mb-2 flex items-center gap-3">
            <i class="bi bi-list-task text-blue-600"></i>
            รายการขอใช้เงิน
        </h4>
        <p class="text-xs text-slate-400 font-bold mb-8 uppercase tracking-widest">งบประมาณที่ได้รับจัดสรร: <span id="budget_limit_display" class="text-blue-600">0.00</span> บาท</p>
        <input type="hidden" id="budget_limit" value="0">

        <div class="overflow-x-auto mb-8">
            <table class="w-full border-separate border-spacing-y-2">
                <thead>
                    <tr class="text-left">
                        <th class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2">รายการ</th>
                        <th class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2 text-center w-24">จำนวน</th>
                        <th class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2 text-center w-24">หน่วย</th>
                        <th class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2 text-right w-32">ราคา/หน่วย</th>
                        <th class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2 text-right w-32">รวม</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <!-- Rows injected by JS -->
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right py-6 font-black text-slate-800">ยอดรวมทั้งสิ้น</td>
                        <td class="text-right py-6">
                            <span id="total_requested_display" class="text-xl font-black text-blue-600 underline">0.00</span>
                            <input type="hidden" name="amount_requested" id="total_requested" value="0">
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div id="budget_warning" class="hidden mb-8 p-4 bg-rose-50 border border-rose-100 text-rose-500 rounded-2xl font-bold text-xs flex items-center gap-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
            คำเตือน: ยอดรวมเกินกว่างบประมาณที่ได้รับจัดสรร!
        </div>

        <div class="flex justify-between items-center">
            <button type="button" onclick="addItem()" class="text-blue-600 font-black text-sm flex items-center gap-2 hover:underline">
                <i class="bi bi-plus-circle-fill"></i>
                เพิ่มรายการใหม่
            </button>
            <div class="flex gap-4">
                <button type="button" onclick="goToStep(1)" class="bg-slate-100 text-slate-600 px-8 py-4 rounded-2xl font-black text-sm transition-all">กลับ</button>
                <button type="button" onclick="goToStep(3)" id="btnNext2" class="bg-blue-600 text-white px-10 py-4 rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:bg-blue-700 transition-all">
                    ถัดไป: คณะกรรมการ
                </button>
            </div>
        </div>
    </div>

    <!-- Step 3: Committee -->
    <div class="wizard-step bg-white rounded-[2.5rem] p-8 sm:p-10 shadow-xl shadow-slate-200/50 border border-slate-100 animate-fade-in-up hidden" data-step="3">
        <h4 class="text-xl font-black text-slate-800 mb-8 flex items-center gap-3">
            <i class="bi bi-people-fill text-blue-600"></i>
            คณะกรรมการพิจารณาผล / ตรวจรับ
        </h4>

        <div class="overflow-x-auto mb-8">
            <table class="w-full border-separate border-spacing-y-2">
                <thead>
                    <tr class="text-left">
                        <th class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2">ชื่อ-นามสกุล</th>
                        <th class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2">ตำแหน่ง</th>
                        <th class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2">บทบาท</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody id="commBody">
                    <!-- Rows injected by JS -->
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-center">
            <button type="button" onclick="addComm()" class="text-blue-600 font-black text-sm flex items-center gap-2 hover:underline">
                <i class="bi bi-plus-circle-fill"></i>
                เพิ่มกรรมการ
            </button>
            <div class="flex gap-4">
                <button type="button" onclick="goToStep(2)" class="bg-slate-100 text-slate-600 px-8 py-4 rounded-2xl font-black text-sm transition-all">กลับ</button>
                <button type="button" onclick="goToStep(4)" class="bg-blue-600 text-white px-10 py-4 rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:bg-blue-700 transition-all">
                    ถัดไป: ตรวจสอบข้อมูล
                </button>
            </div>
        </div>
    </div>

    <!-- Step 4: Summary -->
    <div class="wizard-step bg-white rounded-[2.5rem] p-8 sm:p-10 shadow-xl shadow-slate-200/50 border border-slate-100 animate-fade-in-up hidden" data-step="4">
        <h4 class="text-xl font-black text-slate-800 mb-8 flex items-center gap-3">
            <i class="bi bi-clipboard-check-fill text-blue-600"></i>
            สรุปข้อมูลคำขอ
        </h4>

        <div id="summaryView" class="mb-10 bg-slate-50 rounded-[2rem] p-8">
            <!-- Rendered by JS -->
        </div>

        <div class="flex justify-between items-center">
            <button type="button" onclick="goToStep(3)" class="bg-slate-100 text-slate-600 px-8 py-4 rounded-2xl font-black text-sm transition-all">กลับไปแก้ไข</button>
            <div class="flex gap-4">
                <button type="submit" name="status" value="draft" class="bg-white border-2 border-blue-600 text-blue-600 px-8 py-4 rounded-2xl font-black text-sm hover:bg-blue-50 transition-all">
                    บันทึกร่าง
                </button>
                <button type="submit" name="status" value="submitted" class="bg-blue-600 text-white px-10 py-4 rounded-2xl font-black text-sm shadow-xl shadow-blue-200 hover:bg-blue-700 transition-all">
                    ส่งคำขออนุมัติ
                </button>
            </div>
        </div>
    </div>
</form>

<script src="<?= BASE_URL ?>/assets/js/wizard.js"></script>
<script>
function updateBudgetInfo(select) {
    const option = select.options[select.selectedIndex];
    const limit = option.dataset.limit || 0;
    document.getElementById('budget_limit').value = limit;
    document.getElementById('budget_limit_display').innerText = parseFloat(limit).toLocaleString();
    if (window.calcTotal) window.calcTotal();
}

// Trigger initial load
updateBudgetInfo(document.getElementById('project_id'));
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
