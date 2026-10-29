<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$selectedDept = $selectedDept ?? '';
$deptSummaries = $deptSummaries ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">บริหารจัดการงบประมาณและโครงการ</h4>
        <div class="text-muted small">จัดสรรงบประมาณ ควบคุมยอดผูกพัน และโอนเปลี่ยนแปลงงบประมาณประจำปี พ.ศ. <?= e($activeFiscalYear['year_be']) ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#importPlanModal">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> นำเข้าแผนปี 2570 (Google Sheets)
        </button>
        <a href="<?= $baseUrl ?>/funding-sources" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-tags me-1"></i> ประเภทการเงิน
        </a>
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newProjectModal">
            <i class="bi bi-folder-plus me-1"></i> เพิ่มโครงการใหม่
        </button>
        <button type="button" class="btn btn-primary-gov btn-sm" data-bs-toggle="modal" data-bs-target="#allocateModal">
            <i class="bi bi-pie-chart-fill me-1"></i> จัดสรรงบประมาณ
        </button>
        <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#transferModal">
            <i class="bi bi-arrow-left-right me-1"></i> โอนเปลี่ยนแปลงงบ
        </button>
    </div>
</div>

<!-- Department Filter Tabs -->
<?php if (!empty($deptSummaries)): ?>
    <div class="card card-gov mb-3 p-2 bg-light">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="text-muted small fw-semibold ms-2"><i class="bi bi-funnel me-1"></i>กรองตามฝ่ายงาน:</span>
            <a href="<?= $baseUrl ?>/budgets" class="btn btn-sm <?= empty($selectedDept) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                ทุกฝ่ายงาน (<?= count($budgets) ?>)
            </a>
            <?php foreach ($deptSummaries as $ds): ?>
                <?php if (empty($ds['department'])) continue; ?>
                <a href="<?= $baseUrl ?>/budgets?dept=<?= urlencode($ds['department']) ?>" 
                   class="btn btn-sm <?= ($selectedDept === $ds['department']) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= e($ds['department']) ?> 
                    <span class="badge <?= ($selectedDept === $ds['department']) ? 'bg-light text-primary' : 'bg-secondary' ?> ms-1">
                        <?= $ds['project_count'] ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Budgets Table -->
<div class="card card-gov mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i> บัญชีคุมยอดงบประมาณแยกตามโครงการ กิจกรรม และหมวดรายจ่าย</span>
        <span class="badge bg-light text-dark border"><?= count($budgets) ?> รายการ</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 130px;">รหัสโครงการ/กิจกรรม</th>
                    <th>ชื่อโครงการ / กิจกรรม</th>
                    <th style="width: 170px;">แหล่งเงิน</th>
                    <th style="width: 110px;">หมวดงบ</th>
                    <th class="text-end" style="width: 120px;">งบสุทธิ (บาท)</th>
                    <th class="text-end" style="width: 110px;">ยอดผูกพัน</th>
                    <th class="text-end" style="width: 110px;">เบิกจ่ายแล้ว</th>
                    <th class="text-end" style="width: 120px;">งบคงเหลือ</th>
                    <th class="text-center" style="width: 110px;">สถานะการใช้</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($budgets)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="bi bi-folder-x fs-1 text-secondary d-block mb-2"></i>
                            <div>ยังไม่มีการจัดสรรงบประมาณในปี พ.ศ. <?= e($activeFiscalYear['year_be']) ?></div>
                            <div class="small mt-2">
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#importPlanModal">
                                    <i class="bi bi-cloud-arrow-down me-1"></i> คลิกที่นี่เพื่อนำเข้าแผนงาน 148 กิจกรรมจาก Google Sheets
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $categoriesTh = [
                        'personnel' => 'งบบุคลากร',
                        'operating' => 'งบดำเนินงาน',
                        'investment'=> 'งบลงทุน',
                        'subsidy'   => 'งบเงินอุดหนุน',
                        'other'     => 'งบรายจ่ายอื่น'
                    ];
                    foreach ($budgets as $b): 
                        $net = (float)$b['net_amount'];
                        $committed = (float)$b['committed_amount'];
                        $paid = (float)$b['paid_amount'];
                        $remaining = $net - ($committed + $paid);
                        $percent = $net > 0 ? (($committed + $paid) / $net) * 100 : 0.0;
                    ?>
                        <tr>
                            <td><code><?= e($b['project_code']) ?></code></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($b['project_name']) ?></div>
                                <?php if (!empty($b['activity_name']) && $b['activity_name'] !== $b['project_name']): ?>
                                    <div class="text-primary small">
                                        <i class="bi bi-arrow-return-right me-1"></i><?= e($b['activity_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-muted small">
                                    <span class="badge bg-secondary-subtle text-secondary me-1"><?= e($b['department'] ?? '-') ?></span>
                                    <?php if (!empty($b['responsible_person'])): ?>
                                        <span><i class="bi bi-person me-1"></i><?= e($b['responsible_person']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= e($b['source_name']) ?></span></td>
                            <td><small><?= $categoriesTh[$b['budget_category']] ?? $b['budget_category'] ?></small></td>
                            <td class="text-end fw-semibold"><?= money_format_th($net) ?></td>
                            <td class="text-end text-warning-emphasis"><?= money_format_th($committed) ?></td>
                            <td class="text-end text-danger"><?= money_format_th($paid) ?></td>
                            <td class="text-end fw-bold text-success"><?= money_format_th($remaining) ?></td>
                            <td class="text-center">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?= $percent >= 100 ? 'bg-danger' : ($percent >= 80 ? 'bg-warning' : 'bg-primary') ?>" 
                                         role="progressbar" style="width: <?= min(100, $percent) ?>%;" 
                                         aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <small class="text-muted" style="font-size: 0.72rem;"><?= round($percent, 1) ?>%</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Import School Plan -->
<div class="modal fade" id="importPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $baseUrl ?>/budgets/import-plan">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <div class="modal-header bg-success-subtle">
                    <h5 class="modal-title fw-bold text-success">
                        <i class="bi bi-cloud-arrow-down-fill me-2"></i>นำเข้าแผนปฏิบัติการและงบประมาณ ประจำปี พ.ศ. 2570
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info border-info-subtle mb-3">
                        <div class="fw-bold"><i class="bi bi-info-circle-fill me-1"></i> แหล่งข้อมูล: แผนงบประมาณ ประจำปีงบประมาณ พ.ศ. 2570.xlsx</div>
                        <div class="small mt-1">ระบบจะนำเข้าโครงการและกิจกรรมทั้งหมดครบทั้ง 4 ฝ่ายงาน พร้อมจัดสรรวงเงินงบประมาณตามประเภทแหล่งเงินที่ได้รับอนุมัติโดยอัตโนมัติ</div>
                    </div>

                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ฝ่ายงาน</th>
                                <th class="text-center">จำนวนกิจกรรม</th>
                                <th class="text-end">งบอุดหนุน (SUB-01)</th>
                                <th class="text-end">งบพัฒนาผู้เรียน (SUB-02)</th>
                                <th class="text-end">เงินรายได้ (REV-01)</th>
                                <th class="text-end">รวมอนุมัติ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>ฝ่ายบริหารงานวิชาการ</td>
                                <td class="text-center">48</td>
                                <td class="text-end">538,200.00 ฿</td>
                                <td class="text-end">881,480.00 ฿</td>
                                <td class="text-end">-</td>
                                <td class="text-end fw-semibold">1,419,680.00 ฿</td>
                            </tr>
                            <tr>
                                <td>ฝ่ายบริหารงานทั่วไป</td>
                                <td class="text-center">69</td>
                                <td class="text-end">683,000.00 ฿</td>
                                <td class="text-end">-</td>
                                <td class="text-end">676,000.00 ฿</td>
                                <td class="text-end fw-semibold">1,359,000.00 ฿</td>
                            </tr>
                            <tr>
                                <td>ฝ่ายบริหารงานบุคคล</td>
                                <td class="text-center">26</td>
                                <td class="text-end">426,500.00 ฿</td>
                                <td class="text-end">-</td>
                                <td class="text-end">-</td>
                                <td class="text-end fw-semibold">426,500.00 ฿</td>
                            </tr>
                            <tr>
                                <td>ฝ่ายแผนงานงบประมาณ</td>
                                <td class="text-center">5</td>
                                <td class="text-end">95,000.00 ฿</td>
                                <td class="text-end">-</td>
                                <td class="text-end">-</td>
                                <td class="text-end fw-semibold">95,000.00 ฿</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-success fw-bold">
                            <tr>
                                <td>รวมทั้งสิ้น</td>
                                <td class="text-center">148 กิจกรรม</td>
                                <td class="text-end">1,742,700.00 ฿</td>
                                <td class="text-end">881,480.00 ฿</td>
                                <td class="text-end">676,000.00 ฿</td>
                                <td class="text-end text-success">3,300,180.00 ฿</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> ยืนยันการนำเข้าข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: New Project -->
<div class="modal fade" id="newProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $baseUrl ?>/budgets/projects">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">เพิ่มโครงการใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">รหัสโครงการ <span class="text-danger">*</span></label>
                        <input type="text" name="project_code" class="form-control" placeholder="เช่น PRJ-2570-01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อโครงการ <span class="text-danger">*</span></label>
                        <input type="text" name="project_name" class="form-control" placeholder="เช่น โครงการพัฒนาห้องเรียนพิเศษ..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">กลุ่มสาระฯ / ฝ่ายงาน</label>
                        <select name="department" class="form-select">
                            <option value="">-- เลือกฝ่ายงาน --</option>
                            <option value="ฝ่ายบริหารงานวิชาการ">ฝ่ายบริหารงานวิชาการ</option>
                            <option value="ฝ่ายบริหารงานทั่วไป">ฝ่ายบริหารงานทั่วไป</option>
                            <option value="ฝ่ายบริหารงานบุคคล">ฝ่ายบริหารงานบุคคล</option>
                            <option value="ฝ่ายบริหารงานงบประมาณ นโยบายและแผนงาน">ฝ่ายบริหารงานงบประมาณ นโยบายและแผนงาน</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ผู้รับผิดชอบโครงการ</label>
                        <input type="text" name="responsible_person" class="form-control" placeholder="ชื่อ-นามสกุล ครูผู้รับผิดชอบ">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">วงเงินแผนงบประมาณ (บาท)</label>
                        <input type="number" step="0.01" min="0" name="planned_amount" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกโครงการ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Allocate Budget -->
<div class="modal fade" id="allocateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $baseUrl ?>/budgets/allocate">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">จัดสรรวงเงินงบประมาณ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลือกโครงการ/กิจกรรม <span class="text-danger">*</span></label>
                        <select name="project_id" class="form-select" required>
                            <option value="">-- เลือกโครงการ/กิจกรรม --</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>">
                                    <?= e($proj['project_code']) ?> - <?= e($proj['project_name']) ?>
                                    <?= !empty($proj['activity_name']) && $proj['activity_name'] !== $proj['project_name'] ? ' (' . e($proj['activity_name']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ประเภทการเงิน / แหล่งเงิน <span class="text-danger">*</span></label>
                        <select name="funding_source_id" class="form-select" required>
                            <?php foreach ($fundingSources as $fs): ?>
                                <option value="<?= $fs['id'] ?>"><?= e($fs['source_name']) ?> (<?= e($fs['source_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">หมวดงบประมาณ <span class="text-danger">*</span></label>
                        <select name="budget_category" class="form-select" required>
                            <option value="operating">งบดำเนินงาน (ค่าตอบแทน ใช้สอยและวัสดุ)</option>
                            <option value="subsidy">งบเงินอุดหนุน</option>
                            <option value="investment">งบลงทุน (ครุภัณฑ์ ที่ดินและสิ่งก่อสร้าง)</option>
                            <option value="personnel">งบบุคลากร</option>
                            <option value="other">งบรายจ่ายอื่น</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">จำนวนเงินจัดสรร (บาท) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="initial_amount" class="form-control" required placeholder="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">จัดสรรงบประมาณ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Transfer Budget -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $baseUrl ?>/budgets/transfer">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">โอนเปลี่ยนแปลงงบประมาณ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">จากงบประมาณโครงการ <span class="text-danger">*</span></label>
                        <select name="from_budget_id" class="form-select" required>
                            <option value="">-- เลือกงบต้นทาง --</option>
                            <?php foreach ($budgets as $b): ?>
                                <option value="<?= $b['id'] ?>">
                                    <?= e($b['project_code']) ?> - <?= e($b['project_name']) ?> (คงเหลือ: <?= money_format_th((float)$b['net_amount'] - (float)$b['committed_amount'] - (float)$b['paid_amount']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ไปยังงบประมาณโครงการ <span class="text-danger">*</span></label>
                        <select name="to_budget_id" class="form-select" required>
                            <option value="">-- เลือกงบปลายทาง --</option>
                            <?php foreach ($budgets as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= e($b['project_code']) ?> - <?= e($b['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">จำนวนเงินที่โอน (บาท) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เหตุผลความจำเป็นในการโอน <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required placeholder="ระบุเหตุผล..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-gold">ยืนยันการโอนเงิน</button>
                </div>
            </form>
        </div>
    </div>
</div>
