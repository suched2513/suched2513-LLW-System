<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$preBudget = $preBudget ?? null;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">
            <i class="bi bi-file-earmark-text text-primary me-2"></i>จัดทำเอกสารขออนุมัติใช้เงินงบประมาณ (แบบ ผง 1/1)
        </h4>
        <div class="text-muted small">แบบบันทึกข้อความขออนุมัติใช้เงินงบประมาณตามแผนปฏิบัติราชการ โรงเรียนละลมวิทยา</div>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/budgets" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> กลับหน้างบประมาณ
        </a>
    </div>
</div>

<form method="POST" action="<?= $baseUrl ?>/budgets/phong11/preview" target="_blank" id="phong11Form">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <div class="row g-4">
        <!-- 1. ข้อมูลหัวบันทึกข้อความ -->
        <div class="col-lg-8">
            <div class="card card-gov mb-4 shadow-sm">
                <div class="card-header bg-primary-subtle text-primary fw-bold">
                    <i class="bi bi-building me-2"></i>1. ข้อมูลส่วนราชการและโครงการตามแผนปฏิบัติการ
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">เลือกกิจกรรมตามแผนปฏิบัติการ (148 กิจกรรม) <span class="text-danger">*</span></label>
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
                                            <?= ($preBudget && $preBudget['id'] == $b['id']) ? 'selected' : '' ?>>
                                        <?= e($b['project_code']) ?> - <?= e($b['project_name']) ?>: <?= e($b['activity_name'] ?: '') ?> (คงเหลือ: <?= money_format_th((float)$b['net_amount'] - (float)$b['paid_amount'] - (float)$b['committed_amount']) ?> ฿)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">เลขที่บันทึกข้อความ (ที่)</label>
                            <input type="text" name="doc_no" class="form-control" value="221/2569" placeholder="เช่น 221/2569">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">วันที่ลงบันทึกข้อความ</label>
                            <input type="text" name="doc_date" class="form-control" value="<?= thai_date(date('Y-m-d'), false, true) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อโครงการหลัก</label>
                            <input type="text" name="project_name" id="projectName" class="form-control bg-light" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อกิจกรรมย่อย</label>
                            <input type="text" name="activity_name" id="activityName" class="form-control bg-light" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ฝ่ายงาน</label>
                            <input type="text" name="department" id="department" class="form-control bg-light">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">งานที่รับผิดชอบ</label>
                            <input type="text" name="work_section" id="workSection" class="form-control" placeholder="เช่น งานอาคารสถานที่และสภาพแวดล้อม">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">วัตถุประสงค์ในการขอใช้เงิน <span class="text-danger">*</span></label>
                            <textarea name="objective" id="objective" class="form-control" rows="2" placeholder="เช่น เพื่อจัดซื้อวัสดุสำหรับกิจกรรม หรือ จ้างเหมาบริการ..." required></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">เสนอผู้ตรวจรับพัสดุ</label>
                            <input type="text" name="inspector_name" class="form-control" placeholder="ชื่อ-สกุล ผู้ตรวจรับพัสดุ">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. รายการค่าใช้จ่ายแนบท้าย -->
            <div class="card card-gov mb-4 shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-list-ol me-2"></i>2. รายการค่าใช้จ่ายแนบท้ายบันทึก</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItemRow()">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่มรายการ
                    </button>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-bordered align-middle" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>รายการค่าใช้จ่าย / พัสดุ <span class="text-danger">*</span></th>
                                <th style="width: 100px;">จำนวน</th>
                                <th style="width: 100px;">หน่วยนับ</th>
                                <th style="width: 140px;">ราคา/หน่วย</th>
                                <th style="width: 150px;" class="text-end">จำนวนเงิน (บาท)</th>
                                <th style="width: 50px;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr>
                                <td class="text-center text-muted row-idx">1</td>
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
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end fw-bold">รวมขอใช้เงินครั้งนี้ทั้งสิ้น</td>
                                <td class="text-end fw-bold text-success fs-5" id="grandTotal">0.00 ฿</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. แผงคุมยอดงบและผู้ลงนาม -->
        <div class="col-lg-4">
            <!-- ยอดงบประมาณ Real-time Card -->
            <div class="card card-gov mb-4 shadow-sm border-start border-4 border-success">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-wallet2 me-2"></i>งบประมาณกิจกรรม (ตรวจสอบอัตโนมัติ)
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">งบประมาณที่ได้รับ:</span>
                        <span class="fw-bold" id="cardAllocated">0.00 ฿</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">ใช้ไปแล้ว:</span>
                        <span class="text-warning-emphasis" id="cardSpent">0.00 ฿</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">คงเหลือก่อนขอใช้:</span>
                        <span class="fw-bold" id="cardRemaining">0.00 ฿</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">ขอใช้ครั้งนี้:</span>
                        <span class="fw-bold text-danger" id="cardRequesting">0.00 ฿</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">คงเหลือสุทธิ:</span>
                        <span class="fw-bold text-success fs-5" id="cardNetRemaining">0.00 ฿</span>
                    </div>
                </div>
            </div>

            <!-- ผู้ลงนาม 4 ด่าน -->
            <div class="card card-gov mb-4 shadow-sm">
                <div class="card-header bg-light fw-bold">
                    <i class="bi bi-pen me-2"></i>ข้อมูลผู้ขอใช้และผู้ลงนาม
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">ผู้ขอใช้ (ครูผู้รับผิดชอบ)</label>
                        <input type="text" name="requester_name" id="requesterName" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">ตำแหน่งผู้ขอใช้</label>
                        <input type="text" name="requester_position" class="form-control form-control-sm" value="ครู">
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

            <!-- ปุ่มดำเนินการ -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary py-2 fw-bold">
                    <i class="bi bi-printer-fill me-2"></i> สร้างและพิมพ์เอกสาร แบบ ผง 1/1
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
    document.getElementById('requesterName').value = opt.getAttribute('data-responsible') || '';

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
    // renumber
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

    const bSelect = document.getElementById('budgetSelect');
    if (bSelect && bSelect.selectedIndex > 0) {
        const rem = parseFloat(bSelect.options[bSelect.selectedIndex].getAttribute('data-remaining') || 0);
        const netRem = rem - grand;
        const cardNet = document.getElementById('cardNetRemaining');
        cardNet.textContent = netRem.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ฿';
        if (netRem < 0) {
            cardNet.classList.remove('text-success');
            cardNet.classList.add('text-danger');
        } else {
            cardNet.classList.remove('text-danger');
            cardNet.classList.add('text-success');
        }
    }
}

// Auto init on load
window.addEventListener('DOMContentLoaded', () => {
    const bSelect = document.getElementById('budgetSelect');
    if (bSelect && bSelect.selectedIndex > 0) {
        onActivityChange(bSelect);
    }
});
</script>
