<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">บริหารจัดการงบประมาณและโครงการ</h4>
        <div class="text-muted small">จัดสรรงบประมาณ ควบคุมยอดผูกพัน และโอนเปลี่ยนแปลงงบประมาณประจำปี พ.ศ. <?= e($activeFiscalYear['year_be']) ?></div>
    </div>
    <div class="d-flex gap-2">
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

<!-- Budgets Table -->
<div class="card card-gov mb-4">
    <div class="card-header">
        <span><i class="bi bi-table me-2"></i> บัญชีคุมยอดงบประมาณแยกตามโครงการและหมวดรายจ่าย</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th>รหัสโครงการ</th>
                    <th>ชื่อโครงการ / กลุ่มสาระฯ</th>
                    <th>แหล่งเงิน</th>
                    <th>หมวดงบประมาณ</th>
                    <th class="text-end">งบสุทธิ (บาท)</th>
                    <th class="text-end">ยอดผูกพัน (บาท)</th>
                    <th class="text-end">เบิกจ่ายแล้ว (บาท)</th>
                    <th class="text-end">งบคงเหลือ (บาท)</th>
                    <th class="text-center" style="width: 140px;">สถานะการใช้</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($budgets)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">ยังไม่มีการจัดสรรงบประมาณในปี พ.ศ. <?= e($activeFiscalYear['year_be']) ?></td>
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
                                <div class="fw-bold"><?= e($b['project_name']) ?></div>
                                <small class="text-muted"><?= e($b['department'] ?? '-') ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= e($b['source_name']) ?></span></td>
                            <td><?= $categoriesTh[$b['budget_category']] ?? $b['budget_category'] ?></td>
                            <td class="text-end fw-semibold"><?= money_format_th($net) ?></td>
                            <td class="text-end text-warning-emphasis"><?= money_format_th($committed) ?></td>
                            <td class="text-end text-danger"><?= money_format_th($paid) ?></td>
                            <td class="text-end fw-bold text-success"><?= money_format_th($remaining) ?></td>
                            <td class="text-center">
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar <?= $percent >= 100 ? 'bg-danger' : ($percent >= 80 ? 'bg-warning' : 'bg-primary') ?>" 
                                         role="progressbar" style="width: <?= min(100, $percent) ?>%;" 
                                         aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <small class="text-muted" style="font-size: 0.75rem;"><?= round($percent, 1) ?>%</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Transfers History -->
<?php if (!empty($transfers)): ?>
    <div class="card card-gov">
        <div class="card-header">
            <span><i class="bi bi-clock-history me-2"></i> ประวัติการโอนเปลี่ยนแปลงงบประมาณ</span>
        </div>
        <div class="table-responsive">
            <table class="table table-gov table-hover mb-0">
                <thead>
                    <tr>
                        <th>เลขที่รายการ</th>
                        <th>วันที่โอน</th>
                        <th>จากโครงการ</th>
                        <th>ไปยังโครงการ</th>
                        <th class="text-end">จำนวนเงิน (บาท)</th>
                        <th>เหตุผลความจำเป็น</th>
                        <th>ผู้ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $t): ?>
                        <tr>
                            <td><code><?= e($t['transfer_no']) ?></code></td>
                            <td><?= thai_date($t['transfer_date'], false, true) ?></td>
                            <td><?= e($t['from_project']) ?></td>
                            <td><?= e($t['to_project']) ?></td>
                            <td class="text-end fw-bold text-primary"><?= money_format_th($t['amount']) ?></td>
                            <td><?= e($t['reason']) ?></td>
                            <td><?= e($t['creator_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: New Project -->
<div class="modal fade" id="newProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/budgets/projects">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-folder-plus me-2"></i> เพิ่มโครงการ / งานใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">รหัสโครงการ <span class="text-danger">*</span></label>
                        <input type="text" name="project_code" class="form-control" placeholder="เช่น P-6901" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อโครงการ <span class="text-danger">*</span></label>
                        <input type="text" name="project_name" class="form-control" placeholder="เช่น โครงการพัฒนาทักษะวิชาการและการจัดการเรียนรู้" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">กลุ่มสาระฯ / ฝ่ายงาน</label>
                        <input type="text" name="department" class="form-control" placeholder="เช่น กลุ่มสาระการเรียนรู้วิทยาศาสตร์และเทคโนโลยี">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ผู้รับผิดชอบโครงการ</label>
                        <input type="text" name="responsible_person" class="form-control" placeholder="ชื่อ-สกุล ครูผู้รับผิดชอบ">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">วงเงินตามแผน (บาท)</label>
                        <input type="number" step="0.01" name="planned_amount" class="form-control" value="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gov">บันทึกโครงการ</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Allocate Budget -->
<div class="modal fade" id="allocateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/budgets/allocate">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pie-chart-fill me-2"></i> จัดสรรวงเงินงบประมาณ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลือกโครงการ <span class="text-danger">*</span></label>
                        <select name="project_id" class="form-select" required>
                            <option value="">-- เลือกโครงการ --</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>"><?= e($proj['project_code']) ?> - <?= e($proj['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">แหล่งเงินงบประมาณ <span class="text-danger">*</span></label>
                        <select name="funding_source_id" class="form-select" required>
                            <?php foreach ($fundingSources as $fs): ?>
                                <option value="<?= $fs['id'] ?>"><?= e($fs['source_name']) ?></option>
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
                    <button type="submit" class="btn btn-primary-gov">บันทึกการจัดสรร</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Transfer Budget -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/budgets/transfer">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i> โอนเปลี่ยนแปลงงบประมาณ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">โอนออกจากงบประมาณต้นทาง <span class="text-danger">*</span></label>
                        <select name="from_budget_id" class="form-select" required>
                            <option value="">-- เลือกหมวดงบประมาณต้นทาง --</option>
                            <?php foreach ($budgets as $b): 
                                $rem = (float)$b['net_amount'] - ((float)$b['committed_amount'] + (float)$b['paid_amount']);
                                if ($rem > 0):
                            ?>
                                <option value="<?= $b['id'] ?>"><?= e($b['project_code']) ?> <?= e($b['project_name']) ?> (คงเหลือ: <?= money_format_th($rem) ?> บาท)</option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">โอนเข้าสู่งบประมาณปลายทาง <span class="text-danger">*</span></label>
                        <select name="to_budget_id" class="form-select" required>
                            <option value="">-- เลือกหมวดงบประมาณปลายทาง --</option>
                            <?php foreach ($budgets as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= e($b['project_code']) ?> <?= e($b['project_name']) ?> (<?= e($b['budget_category']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">จำนวนเงินที่ต้องการโอน (บาท) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เหตุผลความจำเป็นในการโอนเปลี่ยนแปลง <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="ระบุเหตุผลความจำเป็นตามระเบียบกระทรวงการคลัง"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-gold">ยืนยันการโอนงบประมาณ</button>
                </div>
            </div>
        </form>
    </div>
</div>
