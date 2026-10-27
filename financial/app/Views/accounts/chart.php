<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ผังบัญชีมาตรฐานสถานศึกษา (Chart of Accounts)</h4>
        <div class="text-muted small">ระบบบัญชี 5 หมวดมาตรฐานตามหลักการบัญชีภาครัฐ</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/accounts" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i> สมุดรายวันทั่วไป
        </a>
        <button type="button" class="btn btn-primary-gov btn-sm" data-bs-toggle="modal" data-bs-target="#newAccountModal">
            <i class="bi bi-plus-circle me-1"></i> เพิ่มรหัสบัญชีใหม่
        </button>
    </div>
</div>

<!-- Category Tabs -->
<ul class="nav nav-pills mb-3 gap-1">
    <li class="nav-item">
        <a class="nav-link <?= empty($category) ? 'active' : '' ?>" href="<?= $baseUrl ?>/accounts/chart">ทุกหมวดบัญชี</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $category === 'asset' ? 'active' : '' ?>" href="<?= $baseUrl ?>/accounts/chart?category=asset">หมวด 1: สินทรัพย์ (Assets)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $category === 'liability' ? 'active' : '' ?>" href="<?= $baseUrl ?>/accounts/chart?category=liability">หมวด 2: หนี้สิน (Liabilities)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $category === 'equity' ? 'active' : '' ?>" href="<?= $baseUrl ?>/accounts/chart?category=equity">หมวด 3: ส่วนของทุน (Equity)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $category === 'revenue' ? 'active' : '' ?>" href="<?= $baseUrl ?>/accounts/chart?category=revenue">หมวด 4: รายได้ (Revenue)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $category === 'expense' ? 'active' : '' ?>" href="<?= $baseUrl ?>/accounts/chart?category=expense">หมวด 5: ค่าใช้จ่าย (Expenses)</a>
    </li>
</ul>

<!-- Accounts Table -->
<div class="card card-gov">
    <div class="card-header">
        <span><i class="bi bi-diagram-3 me-2"></i> รายการผังบัญชี (<?= count($accounts) ?> บัญชี)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-gov table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 140px;">รหัสบัญชี</th>
                    <th>ชื่อบัญชี</th>
                    <th>หมวดบัญชี</th>
                    <th>ยอดดุลปกติ</th>
                    <th>สถานะ</th>
                    <th class="text-end">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($accounts)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">ไม่พบข้อมูลผังบัญชี</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $catNames = [
                        'asset'     => ['สินทรัพย์', 'bg-primary-subtle text-primary border border-primary-subtle'],
                        'liability' => ['หนี้สิน', 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
                        'equity'    => ['ส่วนของทุน', 'bg-info-subtle text-info-emphasis border border-info-subtle'],
                        'revenue'   => ['รายได้', 'bg-success-subtle text-success border border-success-subtle'],
                        'expense'   => ['ค่าใช้จ่าย', 'bg-danger-subtle text-danger border border-danger-subtle'],
                    ];
                    foreach ($accounts as $a): 
                        [$cName, $cBadge] = $catNames[$a['account_category']] ?? [$a['account_category'], 'bg-secondary'];
                    ?>
                        <tr>
                            <td class="fw-bold"><code><?= e($a['account_code']) ?></code></td>
                            <td>
                                <a href="<?= $baseUrl ?>/accounts/ledger/<?= $a['id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= e($a['account_name']) ?>
                                </a>
                            </td>
                            <td><span class="badge <?= $cBadge ?>"><?= $cName ?></span></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= $a['normal_balance'] === 'debit' ? 'เดบิต (Dr.)' : 'เครดิต (Cr.)' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success">ใช้งานปกติ</span>
                            </td>
                            <td class="text-end">
                                <a href="<?= $baseUrl ?>/accounts/ledger/<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="ดูสมุดแยกประเภท">
                                    <i class="bi bi-journal-album me-1"></i> แยกประเภท
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Account -->
<div class="modal fade" id="newAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?= $baseUrl ?>/accounts/chart">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> เพิ่มรหัสบัญชีใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">รหัสบัญชี <span class="text-danger">*</span></label>
                        <input type="text" name="account_code" class="form-control" placeholder="เช่น 1114, 5140" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อบัญชี <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control" placeholder="เช่น เงินฝากประจำ, ค่าจ้างเหมาบริการ" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">หมวดบัญชี <span class="text-danger">*</span></label>
                        <select name="account_category" class="form-select" required>
                            <option value="asset">สินทรัพย์ (หมวด 1)</option>
                            <option value="liability">หนี้สิน (หมวด 2)</option>
                            <option value="equity">ส่วนของทุน / รายได้สะสม (หมวด 3)</option>
                            <option value="revenue">รายได้ (หมวด 4)</option>
                            <option value="expense">ค่าใช้จ่าย (หมวด 5)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ยอดดุลปกติ <span class="text-danger">*</span></label>
                        <select name="normal_balance" class="form-select" required>
                            <option value="debit">เดบิต (Debit)</option>
                            <option value="credit">เครดิต (Credit)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gov">บันทึกรหัสบัญชี</button>
                </div>
            </div>
        </form>
    </div>
</div>
