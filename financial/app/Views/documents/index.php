<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$currentType = $currentType ?? 'all';
$requests = $requests ?? [];
$stats = $stats ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">
            <i class="bi bi-folder2-open text-primary me-2"></i>ทะเบียนเอกสารงบประมาณ (แบบ ผง 1/1 - ผง 1/2)
        </h4>
        <div class="text-muted small">ระบบติดตามและควบคุมเอกสารขออนุมัติและรายงานการใช้เงินงบประมาณ โรงเรียนละลมวิทยา</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= $baseUrl ?>/budgets/phong11" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> เขียนขออนุมัติ (แบบ ผง 1/1)
        </a>
        <a href="<?= $baseUrl ?>/budgets/phong12" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-check me-1"></i> เขียนรายงานผล (แบบ ผง 1/2)
        </a>
        <a href="<?= $baseUrl ?>/budgets" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pie-chart me-1"></i> หน้างบประมาณ
        </a>
    </div>
</div>

<!-- สถิติภาพรวม -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-gov p-3 shadow-sm border-start border-4 border-primary">
            <div class="text-muted small fw-semibold">คำขออนุมัติใช้เงิน (แบบ ผง 1/1)</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($stats['total_phong11'] ?? 0) ?> <small class="fs-6 text-muted">ฉบับ</small></div>
            <div class="small text-primary mt-1">วงเงินขอใช้รวม: <?= money_format_th($stats['amount_phong11'] ?? 0) ?> ฿</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-gov p-3 shadow-sm border-start border-4 border-success">
            <div class="text-muted small fw-semibold">รายงานการใช้เงินแล้ว (แบบ ผง 1/2)</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($stats['total_phong12'] ?? 0) ?> <small class="fs-6 text-muted">ฉบับ</small></div>
            <div class="small text-success mt-1">ยอดรายงานรวม: <?= money_format_th($stats['amount_phong12'] ?? 0) ?> ฿</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-gov p-3 shadow-sm border-start border-4 border-warning">
            <div class="text-muted small fw-semibold">อยู่ระหว่างดำเนินกิจกรรม (รอ ผง 1/2)</div>
            <div class="fs-4 fw-bold text-warning-emphasis mt-1"><?= number_format(($stats['total_phong11'] ?? 0) - ($stats['total_phong12'] ?? 0)) ?> <small class="fs-6 text-muted">ฉบับ</small></div>
            <div class="small text-muted mt-1">ผูกพันงบประมาณไว้ในระบบ</div>
        </div>
    </div>
</div>

<!-- Tabs & Filter -->
<div class="card card-gov shadow-sm">
    <div class="card-header bg-white p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link <?= $currentType === 'all' ? 'active' : '' ?>" href="<?= $baseUrl ?>/budgets/documents">
                        ทั้งหมด (<?= count($requests) ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentType === 'phong11' ? 'active' : '' ?>" href="<?= $baseUrl ?>/budgets/documents?type=phong11">
                        แบบ ผง 1/1 (ขออนุมัติ)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentType === 'phong12' ? 'active' : '' ?>" href="<?= $baseUrl ?>/budgets/documents?type=phong12">
                        แบบ ผง 1/2 (รายงานผล)
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 110px;">เลขที่บันทึก</th>
                    <th style="width: 100px;">วันที่</th>
                    <th style="width: 110px;">ประเภท</th>
                    <th>โครงการ / กิจกรรม</th>
                    <th style="width: 150px;">ผู้ขอใช้ / รายงาน</th>
                    <th style="width: 130px;" class="text-end">ยอดเงิน (บาท)</th>
                    <th style="width: 110px;" class="text-center">สถานะ</th>
                    <th style="width: 180px;" class="text-center">จัดการเอกสาร</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary"></i>
                            <div>ยังไม่มีประวัติการบันทึกเอกสาร แบบ ผง ในระบบ</div>
                            <div class="small mt-2">
                                <a href="<?= $baseUrl ?>/budgets/phong11" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i> เริ่มเขียนขออนุมัติใช้เงินงบประมาณ (แบบ ผง 1/1)
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><code><?= e($r['doc_no']) ?></code></td>
                            <td><small><?= e($r['doc_date']) ?></small></td>
                            <td>
                                <?php if ($r['doc_type'] === 'phong11'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <i class="bi bi-file-earmark-text me-1"></i>แบบ ผง 1/1
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-file-earmark-check me-1"></i>แบบ ผง 1/2
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($r['project_name']) ?></div>
                                <div class="text-primary small"><i class="bi bi-arrow-return-right me-1"></i><?= e($r['activity_name']) ?></div>
                                <div class="text-muted small"><?= e($r['department']) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($r['requester_name']) ?></div>
                                <div class="text-muted small"><?= e($r['requester_position']) ?></div>
                            </td>
                            <td class="text-end fw-bold text-dark"><?= money_format_th((float)$r['total_amount']) ?></td>
                            <td class="text-center">
                                <?php if ($r['status'] === 'reported' || $r['doc_type'] === 'phong12'): ?>
                                    <span class="badge bg-success">รายงานผลแล้ว</span>
                                <?php elseif ($r['status'] === 'disbursed'): ?>
                                    <span class="badge bg-secondary">เบิกจ่ายแล้ว</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">อนุมัติแล้ว/รอรายงาน</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= $baseUrl ?>/budgets/documents/print/<?= $r['id'] ?>" target="_blank" class="btn btn-outline-primary" title="สั่งพิมพ์เอกสาร">
                                        <i class="bi bi-printer"></i> พิมพ์
                                    </a>
                                    <?php if ($r['doc_type'] === 'phong11' && $r['status'] !== 'reported'): ?>
                                        <a href="<?= $baseUrl ?>/budgets/phong12?from_id=<?= $r['id'] ?>" class="btn btn-outline-success" title="จัดทำรายงานผลการใช้เงิน (แบบ ผง 1/2)">
                                            <i class="bi bi-file-earmark-check"></i> ทำ ผง 1/2
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>