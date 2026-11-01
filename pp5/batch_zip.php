<?php
/**
 * pp5/batch_zip.php — ดาวน์โหลดเล่มรายงาน ปพ.5 หลายรายวิชาพร้อมกันเป็นไฟล์ .zip (Batch ZIP Export)
 */

require_once __DIR__ . '/_bootstrap.php';

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\MasterDataService;
use PP5\App\Services\Pp5ReportService;

pp5_require_login();

$policy        = new Pp5Policy($pdo);
$masterService = new MasterDataService($pdo);
$reportService = new Pp5ReportService($pdo);
$termCtx       = pp5_get_term_context($pdo);
$curYear       = (int)$termCtx['year'];
$curSem        = (int)$termCtx['sem'];

// ดึงรายวิชาที่ผู้ใช้นี้มีสิทธิ์ดูตาม Policy
$allCourses = $masterService->getCourses($curYear, $curSem);
$visibleCourses = array_values(array_filter($allCourses, fn($c) => $policy->canViewCourse($c)));

$errorMsg = '';

// เมื่อกดปุ่มดาวน์โหลดไฟล์ ZIP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'download_zip') {
    $selectedIds = $_POST['course_ids'] ?? [];
    if (!is_array($selectedIds) || empty($selectedIds)) {
        $errorMsg = 'กรุณาเลือกรายวิชาอย่างน้อย 1 รายวิชาเพื่อสร้างไฟล์ .zip';
    } else {
        // กรองเฉพาะวิชาที่ผู้ใช้นี้มีสิทธิ์ดูจริงตาม Policy
        $allowedMap = [];
        foreach ($visibleCourses as $vc) {
            $allowedMap[(int)$vc['id']] = true;
        }
        $validIds = [];
        foreach ($selectedIds as $rawId) {
            $cid = (int)$rawId;
            if (isset($allowedMap[$cid])) {
                $validIds[] = $cid;
            }
        }

        if (empty($validIds)) {
            $errorMsg = 'คุณไม่มีสิทธิ์ดาวน์โหลดรายวิชาที่เลือก';
        } else {
            $zipPath = $reportService->createBatchZipArchive($validIds);
            if (!$zipPath || !file_exists($zipPath)) {
                $errorMsg = 'ไม่สามารถสร้างไฟล์ .zip ได้ กรุณาตรวจสอบส่วนขยาย ZipArchive บนเซิร์ฟเวอร์';
            } else {
                $downloadName = "PP5_Batch_Sem{$curSem}_{$curYear}_" . date('Ymd_His') . ".zip";
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $downloadName . '"');
                header('Content-Length: ' . filesize($zipPath));
                readfile($zipPath);
                @unlink($zipPath);
                exit;
            }
        }
    }
}

$pageTitle    = 'ดาวน์โหลดเล่ม ปพ.5 แบบกลุ่ม (.ZIP)';
$pageSubtitle = 'เลือกรายวิชาหลายเล่ม (ตามห้องเรียน กลุ่มสาระ หรือวิชาที่สอน) เพื่อรวบรวมเป็นไฟล์ .zip ในคลิกเดียว';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
echo pp5_render_top_bar($pdo, $termCtx, $policy, 'batch_zip');
?>

<?php if ($errorMsg !== ''): ?>
    <div class="mb-6 rounded-2xl p-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm font-bold flex items-center gap-3">
        <i class="bi bi-exclamation-octagon-fill text-xl"></i>
        <span><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 border border-slate-100 overflow-hidden">
    <form method="POST">
        <input type="hidden" name="form_action" value="download_zip">

        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                    <i class="bi bi-file-earmark-zip-fill text-indigo-600"></i>
                    เลือกรายวิชา ปพ.5 ที่ต้องการรวมเป็นไฟล์ .ZIP (ภาคเรียนที่ <?= $curSem ?>/<?= $curYear ?>)
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    แสดงรายวิชาที่คุณมีสิทธิ์เข้าถึงทั้งหมด <?= count($visibleCourses) ?> รายวิชา • แต่ละไฟล์ภายใน ZIP ประกอบด้วยเล่ม ปพ.5 ครบทั้ง 5 ส่วน
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="toggleAllZipCheckboxes(true)" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-all">
                    เลือกทั้งหมด
                </button>
                <button type="button" onclick="toggleAllZipCheckboxes(false)" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-all">
                    ยกเลิกทั้งหมด
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black shadow-lg shadow-indigo-200 transition-all flex items-center gap-2">
                    <i class="bi bi-cloud-arrow-down-fill text-sm"></i> ดาวน์โหลดเล่มที่เลือกเป็น .ZIP
                </button>
            </div>
        </div>

        <?php if (empty($visibleCourses)): ?>
            <div class="p-10 text-center text-xs text-slate-400">
                ไม่พบรายวิชา ปพ.5 ในภาคเรียนนี้
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs sm:text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-black text-slate-500 uppercase">
                            <th class="py-3.5 px-4 text-center w-12">เลือก</th>
                            <th class="py-3.5 px-4">รหัสวิชา / ชื่อรายวิชา</th>
                            <th class="py-3.5 px-4 text-center">ห้องเรียน</th>
                            <th class="py-3.5 px-4">กลุ่มสาระการเรียนรู้</th>
                            <th class="py-3.5 px-4">ครูผู้สอน</th>
                            <th class="py-3.5 px-4 text-center">สถานะ</th>
                            <th class="py-3.5 px-4 text-right">ดูตัวอย่างรายเล่ม</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($visibleCourses as $cr): ?>
                            <tr class="hover:bg-indigo-50/30 transition-all">
                                <td class="py-3 px-4 text-center">
                                    <input type="checkbox" name="course_ids[]" value="<?= (int)$cr['id'] ?>" checked
                                           class="pp5-zip-chk w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-black text-xs mr-1.5">
                                        <?= htmlspecialchars($cr['subject_code'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <strong class="text-slate-800"><?= htmlspecialchars($cr['subject_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-800 font-black text-xs">
                                        <?= htmlspecialchars($cr['classroom_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs font-semibold text-slate-600">
                                    <?= htmlspecialchars($cr['department_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-4 text-xs font-bold text-slate-700">
                                    <?= htmlspecialchars($cr['teacher_name'] ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-4 text-center text-xs font-bold text-slate-600">
                                    <?= htmlspecialchars($cr['status'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a href="/pp5/course_pdf.php?course_id=<?= (int)$cr['id'] ?>" target="_blank"
                                       class="px-3 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold inline-flex items-center gap-1">
                                        <i class="bi bi-printer-fill"></i> เปิดพิมพ์ A4
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
function toggleAllZipCheckboxes(checked) {
    document.querySelectorAll('.pp5-zip-chk').forEach(cb => {
        cb.checked = checked;
    });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
