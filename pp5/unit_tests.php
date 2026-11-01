<?php
/**
 * pp5/unit_tests.php — หน้าแสดงผลและรัน Unit Tests ตรวจสอบกฎการคำนวณ ปพ.5 ทั้ง 9 ข้อ
 *
 * รองรับทั้งการรันผ่าน CLI (`php pp5/unit_tests.php`) และการเปิดผ่านเบราว์เซอร์ (`/pp5/unit_tests.php`)
 */

require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\Pp5UnitTestSuite;

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    pp5_require_login();
    $policy = new Pp5Policy($pdo);
    if (!$policy->isAdmin()) {
        header('Location: /pp5/index.php');
        exit();
    }
}

$suite  = new Pp5UnitTestSuite($pdo);
$report = $suite->runAll();

if ($isCli) {
    echo "=============================================================\n";
    echo " PP5 Online Unit Test Suite (GradeCalculator & AttendanceService)\n";
    echo "=============================================================\n";
    foreach ($report['results'] as $idx => $r) {
        $badge = $r['passed'] ? '[PASS]' : '[FAIL]';
        echo sprintf("%02d. %s %s — %s\n", $idx + 1, $badge, $r['group'], $r['title']);
        if (!$r['passed']) {
            echo "    Expected: {$r['expected']} | Actual: {$r['actual']}\n";
        }
    }
    echo "-------------------------------------------------------------\n";
    echo "Summary: {$report['passed']}/{$report['total']} passed, {$report['failed']} failed.\n";
    exit($report['all_ok'] ? 0 : 1);
}

$termCtx      = pp5_get_term_context($pdo);
$pageTitle    = 'Unit Tests — ตรวจสอบกฎการคำนวณ ปพ.5 ออนไลน์';
$pageSubtitle = 'ชุดทดสอบอัตโนมัติ (GradeCalculator, AttendanceService, CitizenIdCrypto) ครอบคลุมกฎการคำนวณทั้ง 9 ข้อ';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
echo pp5_render_top_bar($pdo, $termCtx, $policy, 'tests');
?>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-gradient-to-br <?= $report['all_ok'] ? 'from-emerald-500 to-teal-600 shadow-emerald-200/50' : 'from-rose-500 to-pink-600 shadow-rose-200/50' ?> rounded-2xl p-6 text-white shadow-xl">
        <p class="text-xs font-bold opacity-85 uppercase tracking-wider">สถานะการทดสอบทั้งหมด</p>
        <p class="text-3xl font-black mt-2 flex items-center gap-2">
            <i class="bi <?= $report['all_ok'] ? 'bi-patch-check-fill' : 'bi-exclamation-octagon-fill' ?>"></i>
            <?= $report['all_ok'] ? 'ผ่านครบทุกกรณี (PASS)' : 'พบข้อผิดพลาด (FAIL)' ?>
        </p>
        <p class="text-xs opacity-90 mt-1">ทดสอบจริงกับคลาส Service ในระบบ</p>
    </div>

    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-xl shadow-blue-100/40">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">กรณีทดสอบที่ผ่าน (Passed Assertions)</p>
        <p class="text-4xl font-black text-emerald-600 mt-2">
            <?= (int)$report['passed'] ?> <span class="text-base font-bold text-slate-400">/ <?= (int)$report['total'] ?></span>
        </p>
    </div>

    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-xl shadow-blue-100/40 flex flex-col justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">คลาสที่ถูกทดสอบ</p>
            <div class="flex flex-wrap gap-1.5 mt-2">
                <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold">GradeCalculator</span>
                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold">AttendanceService</span>
                <span class="px-2.5 py-1 rounded-lg bg-violet-50 text-violet-700 text-xs font-bold">CitizenIdCrypto</span>
            </div>
        </div>
        <a href="/pp5/unit_tests.php" class="mt-3 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold transition-all">
            <i class="bi bi-play-fill"></i> รันชุดทดสอบซ้ำอีกครั้ง
        </a>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 overflow-hidden">
    <div class="p-5 border-b border-slate-100">
        <h3 class="text-base font-black text-slate-800 flex items-center gap-2">
            <i class="bi bi-list-check text-violet-600"></i>
            รายละเอียดผลการรัน Unit Tests (<?= (int)$report['total'] ?> กรณีทดสอบ)
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-black text-slate-500 uppercase tracking-wider">
                    <th class="py-3.5 px-4 text-center w-14">#</th>
                    <th class="py-3.5 px-4">หมวดกฎการคำนวณ</th>
                    <th class="py-3.5 px-4">กรณีทดสอบ (Test Case)</th>
                    <th class="py-3.5 px-4">ค่าที่คาดหวัง (Expected)</th>
                    <th class="py-3.5 px-4">ผลลัพธ์จริง (Actual)</th>
                    <th class="py-3.5 px-4 text-center w-28">ผลลัพธ์</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
                <?php foreach ($report['results'] as $i => $r): ?>
                    <tr class="hover:bg-slate-50/80">
                        <td class="py-3.5 px-4 text-center font-black text-slate-400"><?= $i + 1 ?></td>
                        <td class="py-3.5 px-4 font-black text-indigo-700"><?= htmlspecialchars($r['group'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3.5 px-4 font-bold text-slate-800"><?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3.5 px-4 font-mono text-slate-600"><?= htmlspecialchars($r['expected'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3.5 px-4 font-mono font-bold <?= $r['passed'] ? 'text-emerald-700' : 'text-rose-600' ?>"><?= htmlspecialchars($r['actual'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3.5 px-4 text-center">
                            <?php if ($r['passed']): ?>
                                <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 font-black inline-flex items-center gap-1">
                                    <i class="bi bi-check-circle-fill"></i> PASS
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full bg-rose-100 text-rose-800 font-black inline-flex items-center gap-1">
                                    <i class="bi bi-x-circle-fill"></i> FAIL
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
