<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($code) ?> - <?= e($title) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #F8FAFC;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 3rem 2rem;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            border: 1px solid #E2E8F0;
        }
        .error-code {
            font-size: 4.5rem;
            font-weight: 700;
            color: #0B2545;
            line-height: 1;
        }
    </style>
</head>
<body>

<div class="error-card">
    <div class="error-code mb-2"><?= e($code) ?></div>
    <h4 class="fw-bold mb-3" style="color: #0B2545;"><?= e($title) ?></h4>
    <?php if (!empty($message)): ?>
        <p class="text-muted mb-4"><?= e($message) ?></p>
    <?php endif; ?>
    <div class="d-flex justify-content-center gap-2">
        <a href="javascript:history.back()" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
        <a href="<?= $baseUrl ?>/dashboard" class="btn btn-primary" style="background-color: #0B2545; border-color: #0B2545;">
            <i class="bi bi-house me-1"></i> หน้าหลักแดชบอร์ด
        </a>
    </div>
</div>

</body>
</html>
