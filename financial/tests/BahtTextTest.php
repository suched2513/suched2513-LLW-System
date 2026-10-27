<?php

require_once __DIR__ . '/../app/Services/BahtTextService.php';

use App\Services\BahtTextService;

$cases = [
    '0.00'         => 'ศูนย์บาทถ้วน',
    '0.50'         => 'ศูนย์บาทห้าสิบสตางค์',
    '1.00'         => 'หนึ่งบาทถ้วน',
    '11.00'        => 'สิบเอ็ดบาทถ้วน',
    '21.00'        => 'ยี่สิบเอ็ดบาทถ้วน',
    '101.00'       => 'หนึ่งร้อยเอ็ดบาทถ้วน',
    '1000.25'      => 'หนึ่งพันบาทยี่สิบห้าสตางค์',
    '1000001.00'   => 'หนึ่งล้านเอ็ดบาทถ้วน',
];

$allPass = true;
foreach ($cases as $val => $exp) {
    $res = BahtTextService::toBahtText($val);
    $ok = ($res === $exp);
    if (!$ok) $allPass = false;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . "$val => $res (expected: $exp)" . PHP_EOL;
}

if ($allPass) {
    echo "=== ALL BAHTTEXT TESTS PASSED ===" . PHP_EOL;
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===" . PHP_EOL;
    exit(1);
}
