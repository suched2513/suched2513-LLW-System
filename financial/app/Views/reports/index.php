<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #0B2545;">ศูนย์เอกสารและรายงานทางการเงินและบัญชีสถานศึกษา</h4>
        <div class="text-muted small">ดัชนีแม่แบบเอกสารราชการและ 28 รายงานมาตรฐานตามระเบียบกระทรวงการคลังและ สพฐ.</div>
    </div>
</div>

<!-- 28 Reports Catalog Grid -->
<div class="row g-3">
    <?php 
    $catColors = [
        'รับเงิน'   => 'border-start border-4 border-success',
        'เบิกจ่าย'  => 'border-start border-4 border-primary',
        'เงินยืม'   => 'border-start border-4 border-warning',
        'บัญชี'    => 'border-start border-4 border-info',
        'งบประมาณ' => 'border-start border-4 border-secondary',
        'ธนาคาร'   => 'border-start border-4 border-dark',
        'พัสดุ'    => 'border-start border-4 border-danger',
        'เดินทาง'   => 'border-start border-4 border-primary-subtle',
        'ค่าบำรุง'  => 'border-start border-4 border-success-subtle',
        'งบการเงิน' => 'border-start border-4 border-gold'
    ];
    foreach ($catalog as $rpt): 
        $border = $catColors[$rpt['category']] ?? 'border-start border-4 border-secondary';
    ?>
        <div class="col-md-6 col-xl-4">
            <div class="card card-gov h-100 <?= $border ?>">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-light text-dark border fw-bold"><code><?= e($rpt['code']) ?></code></span>
                            <span class="badge bg-secondary-subtle text-secondary"><?= e($rpt['category']) ?></span>
                        </div>
                        <h6 class="fw-bold mb-1 text-navy"><?= e($rpt['name']) ?></h6>
                        <p class="text-muted small mb-3"><?= e($rpt['desc']) ?></p>
                    </div>

                    <div class="d-flex justify-content-end">
                        <a href="<?= $baseUrl ?>/reports/show/<?= $rpt['code'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-file-earmark-text me-1"></i> เปิดรายงาน
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
