<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$preBudget = $preBudget ?? null;
$fromPhong11 = $fromPhong11 ?? null;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">
            <i class="bi bi-file-earmark-check text-success me-2"></i>จัดทำเอกสารรายงานการใช้เงินงบประมาณ (แบบ ผง 1/2)
        </h4>
        <div class="text-muted small">แบบบันทึกข้อความรายงานการใช้เงินงบประมาณตามแผนปฏิบัติราชการ โรงเรียนละลมวิทยา</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/budgets/documents" class="btn btn-outline-secondary">
            <i class="bi bi-folder2-open me-1"></i> ทะเบียนเอกสาร ผง.
        </a>
        <a href="<?= $baseUrl ?>/budgets" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> หน้างบประมาณ
        </a>
    </div>
</div>

<?php if ($fromPhong11): ?>
    <div class="alert alert-info border-info-subtle d-flex align-items-center mb-4">
        <i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i>
        <div>
            <div class="fw-bold">เชื่อมโยงจากบันทึกขออนุมัติใช้เงิน (แบบ ผง 1/1) เลขที่ <?= e($fromPhong11['doc_no']) ?></div>
            <div class="small">ระบบได้ดึงข้อมูลโครงการ กิจกรรม ฝ่ายงาน ผู้ขอใช้ และรายการค่าใช้จ่ายที่ขออนุมัติไว้ <?= money_format_th($fromPhong11['total_amount']) ?> บาท มาให้อัตโนมัติแล้ว</div>
        </div>
    </div>
<?php endif; ?>

<form method="POST" action="<?= $baseUrl ?>/budgets/phong12/submit" target="_blank" id="phong12Form">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="related_request_id" value="<?= $fromPhong11['id'] ?? '' ?>">
    <input type="hidden" name="total_amount" id="totalAmountInput" value="<?= $fromPhong11['total_amount'] ?? '0' ?>">

    <div class="row g-4">
        <!-- 1. ข้อมูลหัวบันทึกข้อความ -->
        <div class="col-lg-8">
            <div class="card card-gov mb-4 shadow-sm">
                <div class="card-header bg-success-subtle text-success fw-bold">
                    <i class="bi bi-building me-2"></i>1. ข้อมูลส่วนราชการและรายงานผลตามแผนปฏิบัติการ
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">เลือกกิจกรรมตามแผนปฏิบัติการ <span class="text-danger">*</span></label>
                            <select name="budget_id" id="budgetSelect" class="form-select select2" required onchange="onActivityChange(this)">
                                <option value="">-- เลือกโครงการและกิจกรรม --</option>
                                <?php foreach ($allBudgets as $b): ?>
                                    <option value="<?= $b['id'] ?>" 
                                            data-project="<?= e($b['project_name']) ?>"
                                            data-activity="<?= e($b['activity_name'] ?: $b['project_name']) ?>"
                                            data-dept="<?= e($b['department']) ?>"
                                            data-responsible="<?= e($b['responsible_person']) ?>"
                                            data-initial="<?= (float)$b['net_amount'] ?>"
                                            data-spent="<?= (float)$b['paid_amount'] + (float)$b['committed_amount'] ?>"
                                            data-remaining="<?= (float)$b['net_amount'] - (float)$b['paid_amount'] - (float)$b['committed_amount'] ?>"
                                            <?= (($preBudget && $preBudget['id'] == $b['id']) || ($fromPhong11 && $fromPhong11['budget_id'] == $b['id'])) ? 'selected' : '' ?>>
                                        <?= e($b['project_code']) ?> - <?= e($b['project_name']) ?>: <?= e($b['activity_name'] ?: '') ?> (คงเหลือ: <?= money_format_th((float)$b['net_amount'] - (float)$b['paid_amount'] - (float)$b['committed_amount']) ?> ฿)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">เลขที่บันทึกข้อความ (ที่) <span class="text-danger">*</span></label>
                            <input type="text" name="doc_no" class="form-control" value="<?= e($fromPhong11['doc_no'] ?? '21/2569') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">วันที่รายงาน</label>
                            <input type="text" name="doc_date" class="form-control" value="<?= thai_date(date('Y-m-d'), false, true) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อส่วนราชการ</label>
                            <input type="text" name="school_name" class="form-control" value="โรงเรียนละลมวิทยา">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">สังกัด</label>
                            <input type="text" name="affiliation" class="form-control" value="สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ปีงบประมาณ พ.ศ.</label>
                            <input type="text" name="fiscal_year_be" class="form-control" value="2570">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ฝ่ายงาน</label>
                            <input type="text" name="department" id="department" class="form-control" value="<?= e($fromPhong11['department'] ?? 'ฝ่ายบริหารงานทั่วไป') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">กลุ่มงาน / งานที่รับผิดชอบ</label>
                            <input type="text" name="work_section" id="workSection" class="form-control" value="<?= e($fromPhong11['work_section'] ?? 'งานอาคารสถานที่และสภาพแวดล้อม') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อโครงการ</label>
                            <input type="text" name="project_name" id="projectName" class="form-control" value="<?= e($fromPhong11['project_name'] ?? '') ?>" readonly>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">ชื่อกิจกรรมตามแผน</label>
                            <input type="text" name="activity_name" id="activityName" class="form-control" value="<?= e($fromPhong11['activity_name'] ?? '') ?>" readonly>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">วัตถุประสงค์ที่ดำเนินการแล้วเสร็จ <span class="text-danger">*</span></label>
                            <textarea name="objective" id="objective" class="form-control" rows="2" required><?= e($fromPhong11['objective'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. รายละเอียดค่าใช้จ่ายแนบท้าย -->
            <div class="card card-gov mb-4 shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark"><i class="bi bi-list-check me-2"></i>2. รายละเอียดค่าใช้จ่ายตามที่จ่ายจริง (แนบท้าย แบบ ผง 1/2)</span>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="addItemRow()">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่มรายการ
                    </button>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-bordered align-middle" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>รายการค่าใช้จ่าย / พัสดุที่จ่ายจริง <span class="text-danger">*</span></th>
                                <th style="width: 100px;">จำนวน</th>
                                <th style="width: 100px;">หน่วยนับ</th>
                                <th style="width: 140px;">ราคา/หน่วย</th>
                                <th style="width: 150px;" class="text-end">จำนวนเงิน (บาท)</th>
                                <th style="width: 50px;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <?php 
                            $initItems = [];
                            if ($fromPhong11 && !empty($fromPhong11['items_json'])) {
                                $initItems = json_decode($fromPhong11['items_json'], true) ?: [];
                            }
                            if (empty($initItems)) {
                                $initItems = [['item_name' => '', 'quantity' => 1, 'unit' => 'ชุด', 'unit_price' => 0, 'amount' => 0]];
                            }
                            foreach ($initItems as $idx => $it):
                            ?>
                                <tr>
                                    <td class="text-center text-muted row-idx"><?= $idx + 1 ?></td>
                                    <td>
                                        <input type="text" name="item_name[]" class="form-control form-control-sm" placeholder="ระบุรายการ..." value="<?= e($it['item_name'] ?? '') ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0.01" name="quantity[]" class="form-control form-control-sm text-center qty-input" value="<?= (float)($it['quantity'] ?? 1) ?>" oninput="calcTotal()" required>
                                    </td>
                                    <td>
                                        <input type="text" name="unit[]" class="form-control form-control-sm text-center" value="<?= e($it['unit'] ?? 'ชุด') ?>" placeholder="หน่วย">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="unit_price[]" class="form-control form-control-sm text-end price-input" value="<?= (float)($it['unit_price'] ?? 0) ?>" oninput="calcTotal()" required>
                                    </td>
                                    <td class="text-end fw-bold row-amount"><?= number_format((float)($it['amount'] ?? 0), 2) ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm text-danger p-0" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end fw-bold">ยอดเงินที่ขอรายงานและเบิกจ่ายจริงทั้งสิ้น</td>
                                <td class="text-end fw-bold text-success fs-5" id="grandTotal"><?= money_format_th((float)($fromPhong11['total_amount'] ?? 0)) ?> ฿</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. แผงคุมยอดงบและผู้ลงนาม -->
        <div class="col-lg-4">
            <div class="card card-gov mb-4 shadow-sm border-top border-4 border-success">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-wallet2 text-success me-2"></i>งบประมาณกิจกรรม (ตรวจสอบอัตโนมัติ)
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">งบประมาณที่ได้รับ:</span>
                        <span class="fw-bold" id="cardAllocated">0.00 ฿</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">เบิกจ่ายไปแล้ว:</span>
                        <span class="text-danger" id="cardSpent">0.00 ฿</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 pb-2 border-bottom">
                        <span class="text-muted">งบคงเหลือ:</span>
                        <span class="fw-bold" id="cardRemaining">0.00 ฿</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-semibold">ยอดรายงานครั้งนี้:</span>
                        <span class="fw-bold text-danger fs-5" id="cardRequesting"><?= money_format_th((float)($fromPhong11['total_amount'] ?? 0)) ?> ฿</span>
                    </div>
                </div>
            </div>

            <!-- ข้อมูลผู้ขอใช้และผู้ลงนาม -->
            <div class="card card-gov mb-4 shadow-sm">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-pen me-2"></i>ข้อมูลผู้รายงานและผู้ลงนาม
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">ผู้รายงาน / ผู้ขอใช้เงิน</label>
                        <input type="text" name="requester_name" id="requesterName" class="form-control form-control-sm" value="<?= e($fromPhong11['requester_name'] ?? ($currentUser['full_name'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">ตำแหน่งผู้รายงาน</label>
                        <input type="text" name="requester_position" id="requesterPosition" class="form-control form-control-sm" value="<?= e($fromPhong11['requester_position'] ?? ($currentUser['position'] ?? 'ครู')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">หัวหน้าฝ่ายงบประมาณ / งานแผนงาน</label>
                        <input type="text" name="plan_head_name" class="form-control form-control-sm" value="นางชลลัดดา มากนวล">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">หัวหน้าเจ้าหน้าที่พัสดุ</label>
                        <input type="text" name="procurement_head_name" class="form-control form-control-sm" value="นางบานเย็น ภูรักษา">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">หัวหน้างานการเงิน</label>
                        <input type="text" name="finance_head_name" class="form-control form-control-sm" value="นางรัตนา หงษ์โสภา">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">ผู้อำนวยการโรงเรียนละลมวิทยา</label>
                        <input type="text" name="director_name" class="form-control form-control-sm" value="นายสถาน ปรางมาศ">
                    </div>
                </div>
            </div>

            <!-- ปุ่มดำเนินการ 2 ทางเลือก -->
            <div class="d-grid gap-2">
                <button type="submit" name="action_type" value="save_and_print" class="btn btn-success py-2 fw-bold">
                    <i class="bi bi-save2 me-2"></i> บันทึกรายงานและพิมพ์เอกสาร (แนะนำ)
                </button>
                <button type="submit" name="action_type" value="preview" class="btn btn-outline-secondary py-2">
                    <i class="bi bi-printer me-2"></i> พิมพ์ตัวอย่าง (ไม่บันทึก)
                </button>
            </div>
        </div>
    </div>
</form>

<script>
function onActivityChange(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return;

    document.getElementById('projectName').value = opt.getAttribute('data-project') || '';
    document.getElementById('activityName').value = opt.getAttribute('data-activity') || '';
    document.getElementById('department').value = opt.getAttribute('data-dept') || '';
    if (!document.getElementById('requesterName').value) {
        document.getElementById('requesterName').value = opt.getAttribute('data-responsible') || '';
    }

    const initial = parseFloat(opt.getAttribute('data-initial') || 0);
    const spent = parseFloat(opt.getAttribute('data-spent') || 0);
    const rem = parseFloat(opt.getAttribute('data-remaining') || 0);

    document.getElementById('cardAllocated').textContent = initial.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ฿';
    document.getElementById('cardSpent').textContent = spent.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ฿';
    document.getElementById('cardRemaining').textContent = rem.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ฿';

    calcTotal();
}

function addItemRow() {
    const tbody = document.getElementById('itemsBody');
    const idx = tbody.rows.length + 1;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="text-center text-muted row-idx">${idx}</td>
        <td>
            <input type="text" name="item_name[]" class="form-control form-control-sm" placeholder="ระบุรายการ..." required>
        </td>
        <td>
            <input type="number" step="0.01" min="0.01" name="quantity[]" class="form-control form-control-sm text-center qty-input" value="1" oninput="calcTotal()" required>
        </td>
        <td>
            <input type="text" name="unit[]" class="form-control form-control-sm text-center" value="ชุด" placeholder="หน่วย">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="unit_price[]" class="form-control form-control-sm text-end price-input" placeholder="0.00" oninput="calcTotal()" required>
        </td>
        <td class="text-end fw-bold row-amount">0.00</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm text-danger p-0" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removeRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.rows.length <= 1) {
        alert('ต้องมีรายการอย่างน้อย 1 แถว');
        return;
    }
    btn.closest('tr').remove();
    Array.from(tbody.rows).forEach((row, i) => {
        row.querySelector('.row-idx').textContent = i + 1;
    });
    calcTotal();
}

function calcTotal() {
    let grand = 0;
    const tbody = document.getElementById('itemsBody');
    Array.from(tbody.rows).forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const amt = qty * price;
        row.querySelector('.row-amount').textContent = amt.toLocaleString('en-US', {minimumFractionDigits: 2});
        grand += amt;
    });

    document.getElementById('grandTotal').textContent = grand.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ฿';
    document.getElementById('cardRequesting').textContent = grand.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ฿';
    document.getElementById('totalAmountInput').value = grand;
}

window.addEventListener('DOMContentLoaded', () => {
    const bSelect = document.getElementById('budgetSelect');
    if (bSelect && bSelect.selectedIndex > 0) {
        onActivityChange(bSelect);
    }
    calcTotal();
});
</script>