<?php
/**
 * pp5/import_data.php — ระบบนำเข้าและส่งออกข้อมูลด้วย Excel (.xlsx / .csv)
 *
 * คุณสมบัติตามข้อกำหนด:
 * 1. นำเข้านักเรียน, ครูผู้สอน, รายวิชา, และการจัดครูสอนจากไฟล์ Excel (.xlsx / .csv)
 * 2. มีไฟล์แม่แบบ (Template) ให้ดาวน์โหลดครบทั้ง 4 ประเภท
 * 3. แสดงตารางตรวจสอบความถูกต้องและระบุแถวที่ผิดพลาดพร้อมสาเหตุก่อนกดยืนยันนำเข้าจริง
 */

require_once __DIR__ . '/_bootstrap.php';
pp5_require_login();

use PP5\App\Policies\Pp5Policy;
use PP5\App\Services\ExcelImportExportService;

$policy = new Pp5Policy($pdo);
$policy->authorizeAdmin();

$excelService = new ExcelImportExportService($pdo);
$termCtx      = pp5_get_term_context($pdo);
$curYear      = (int)$termCtx['year'];
$curSem       = (int)$termCtx['sem'];

// ดาวน์โหลดไฟล์แม่แบบ CSV
if (isset($_GET['download_template'])) {
    $tplType = trim((string)$_GET['download_template']);
    $excelService->outputCsvTemplate($tplType);
}

$templates        = $excelService->getTemplateDefinitions();
$selectedType     = trim((string)($_POST['import_type'] ?? ($_GET['type'] ?? 'students')));
if (!isset($templates[$selectedType])) {
    $selectedType = 'students';
}

$msg              = '';
$msgType          = 'success';
$validationResult = null;

// ─── ประมวลผล POST: ขั้นตอนที่ 1 (ตรวจสอบไฟล์) และ ขั้นตอนที่ 2 (ยืนยันนำเข้าจริง) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $step = trim((string)($_POST['step'] ?? 'validate'));

    try {
        if ($step === 'validate') {
            $rawRows = [];
            // กรณีแปลงจาก .xlsx ผ่าน SheetJS ฝั่งเบราว์เซอร์ส่งมาเป็น JSON
            if (!empty($_POST['parsed_rows_json'])) {
                $decoded = json_decode((string)$_POST['parsed_rows_json'], true);
                if (is_array($decoded)) {
                    $rawRows = $decoded;
                }
            } elseif (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                $rawRows = $excelService->parseCsvFile($_FILES['import_file']['tmp_name']);
            }

            if (empty($rawRows)) {
                $msg     = 'ไม่พบข้อมูลในไฟล์ที่เลือก กรุณาตรวจสอบไฟล์ Excel หรือ CSV ของคุณ';
                $msgType = 'error';
            } else {
                $validationResult = $excelService->validateImportRows($selectedType, $rawRows, $curYear, $curSem);
                $_SESSION['pp5_pending_import'] = [
                    'type'       => $selectedType,
                    'year'       => $curYear,
                    'sem'        => $curSem,
                    'valid_rows' => $validationResult['valid_rows'],
                ];
            }
        } elseif ($step === 'commit') {
            $pending = $_SESSION['pp5_pending_import'] ?? null;
            if (!$pending || empty($pending['valid_rows']) || $pending['type'] !== $selectedType) {
                $msg     = 'ไม่พบข้อมูลที่รอการยืนยัน กรุณาอัปโหลดและตรวจสอบไฟล์ใหม่อีกครั้ง';
                $msgType = 'error';
            } else {
                $importedCount = $excelService->commitImportRows(
                    $pending['type'],
                    $pending['valid_rows'],
                    (int)$pending['year'],
                    (int)$pending['sem']
                );
                unset($_SESSION['pp5_pending_import']);
                $msg = "นำเข้าข้อมูล {$templates[$selectedType]['title']} สำเร็จจำนวน {$importedCount} รายการเรียบร้อยแล้ว!";
            }
        }
    } catch (Throwable $e) {
        error_log('[PP5 Excel Import] ' . $e->getMessage());
        $msg     = 'เกิดข้อผิดพลาดระหว่างประมวลผลข้อมูล กรุณาตรวจสอบไฟล์และลองใหม่อีกครั้ง';
        $msgType = 'error';
    }
}

$pageTitle    = 'นำเข้าและส่งออกข้อมูลด้วย Excel';
$pageSubtitle = 'ดาวน์โหลดไฟล์แม่แบบ · ตรวจสอบแถวที่ผิดพลาดก่อนยืนยัน · นำเข้านักเรียน ครู รายวิชา และการจัดครูสอน';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- SheetJS สำหรับอ่านและสร้างไฟล์ .xlsx บนเบราว์เซอร์โดยไม่ต้องพึ่ง Node.js บนโฮสต์ -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<?= pp5_render_top_bar($pdo, $termCtx, $policy, 'import') ?>

<?php if ($msg !== ''): ?>
    <div class="mb-6 p-4 rounded-2xl border flex items-center gap-3 shadow-sm <?= $msgType === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?>">
        <i class="bi <?= $msgType === 'error' ? 'bi-exclamation-octagon-fill text-rose-500' : 'bi-check-circle-fill text-emerald-500' ?> text-lg"></i>
        <span class="text-sm font-bold"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<!-- ─── ส่วนที่ 1: ดาวน์โหลดไฟล์แม่แบบ (Excel Templates) ทั้ง 4 หมวด ─── -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php foreach ($templates as $tKey => $tDef):
        $isActive = ($selectedType === $tKey);
    ?>
        <div class="bg-white rounded-2xl p-5 border transition-all <?= $isActive ? 'border-emerald-500 ring-2 ring-emerald-200 shadow-xl shadow-emerald-100/50' : 'border-slate-100 shadow-lg shadow-blue-100/30' ?>">
            <div class="flex items-center justify-between mb-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase <?= $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                    Template
                </span>
                <i class="bi bi-file-earmark-spreadsheet-fill text-emerald-600 text-lg"></i>
            </div>
            <h3 class="text-sm font-black text-slate-800"><?= htmlspecialchars($tDef['title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="text-[11px] text-slate-400 mt-1 line-clamp-2">
                คอลัมน์: <?= htmlspecialchars(implode(', ', $tDef['headers']), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button"
                        onclick='downloadXlsxTemplate(<?= json_encode($tDef, JSON_UNESCAPED_UNICODE) ?>)'
                        class="flex-1 bg-emerald-600 text-white px-3 py-2 rounded-xl text-xs font-bold hover:bg-emerald-700 transition-all flex items-center justify-center gap-1">
                    <i class="bi bi-download"></i> แม่แบบ .XLSX
                </button>
                <a href="/pp5/import_data.php?download_template=<?= urlencode($tKey) ?>"
                   class="px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition-all flex items-center justify-center gap-1">
                    .CSV
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ─── ส่วนที่ 2: ฟอร์มอัปโหลดไฟล์ Excel (.xlsx / .csv) เพื่อตรวจสอบข้อมูล ─── -->
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100 mb-6">
    <h3 class="text-base font-black text-slate-800 flex items-center gap-2 mb-4">
        <i class="bi bi-cloud-arrow-up-fill text-emerald-600"></i>
        อัปโหลดไฟล์ Excel (.xlsx หรือ .csv) เพื่อตรวจสอบความถูกต้องก่อนนำเข้า · ภาคเรียนที่ <?= $curSem ?>/<?= $curYear ?>
    </h3>

    <form method="POST" enctype="multipart/form-data" id="excelUploadForm" class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="validate">
        <input type="hidden" name="parsed_rows_json" id="parsed_rows_json" value="">

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">1. เลือกประเภทข้อมูลที่จะนำเข้า</label>
            <select name="import_type" id="import_type" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold text-slate-800 outline-none focus:ring-2 focus:ring-emerald-500">
                <?php foreach ($templates as $tKey => $tDef): ?>
                    <option value="<?= htmlspecialchars($tKey, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedType === $tKey ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tDef['title'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">2. เลือกไฟล์ Excel (.xlsx, .xls หรือ .csv)</label>
            <input type="file" name="import_file" id="import_file" accept=".xlsx,.xls,.csv" required
                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2.5 text-sm font-semibold text-slate-700">
        </div>

        <div>
            <button type="submit" id="btnValidate" class="w-full bg-blue-600 text-white px-6 py-3 rounded-2xl text-sm font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all flex items-center justify-center gap-2">
                <i class="bi bi-search"></i> ตรวจสอบข้อมูลในไฟล์ (Preview)
            </button>
        </div>
    </form>

    <!-- คำอธิบายคอลัมน์ของประเภทที่เลือก -->
    <div class="mt-4 p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
        <p class="text-xs font-black text-slate-700 mb-2">
            <i class="bi bi-info-circle-fill text-blue-600"></i> โครงสร้างคอลัมน์สำหรับ "<?= htmlspecialchars($templates[$selectedType]['title'], ENT_QUOTES, 'UTF-8') ?>":
        </p>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($templates[$selectedType]['headers'] as $idx => $colName): ?>
                <span class="px-3 py-1 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700">
                    คอลัมน์ <?= $idx + 1 ?>: <code class="text-indigo-600 font-black"><?= htmlspecialchars($colName, ENT_QUOTES, 'UTF-8') ?></code>
                    (<?= htmlspecialchars($templates[$selectedType]['labels'][$idx] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ─── ส่วนที่ 3: ผลการตรวจสอบข้อมูลก่อนยืนยัน (Validation Preview) ─── -->
<?php if ($validationResult !== null):
    $validRows = $validationResult['valid_rows'];
    $errorRows = $validationResult['error_rows'];
?>
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 overflow-hidden border border-slate-100 mb-6">
    <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h3 class="text-base font-black text-slate-800">ผลการตรวจสอบความถูกต้องของข้อมูลก่อนนำเข้า</h3>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-black">
                    <i class="bi bi-check-circle-fill"></i> ข้อมูลถูกต้องพร้อมนำเข้า: <?= count($validRows) ?> แถว
                </span>
                <span class="px-3 py-1 rounded-full <?= empty($errorRows) ? 'bg-slate-100 text-slate-500' : 'bg-rose-50 text-rose-700' ?> text-xs font-black">
                    <i class="bi bi-exclamation-triangle-fill"></i> แถวที่พบข้อผิดพลาด: <?= count($errorRows) ?> แถว
                </span>
            </div>
        </div>

        <?php if (!empty($validRows)): ?>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="step" value="commit">
                <input type="hidden" name="import_type" value="<?= htmlspecialchars($selectedType, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="bg-emerald-600 text-white px-6 py-3 rounded-2xl text-sm font-black shadow-lg shadow-emerald-200 hover:bg-emerald-700 hover:scale-[1.01] transition-all flex items-center gap-2">
                    <i class="bi bi-check2-circle text-lg"></i> ยืนยันการนำเข้าข้อมูลที่ถูกต้อง (<?= count($validRows) ?> แถว)
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- แสดงตารางแถวที่ผิดพลาด (ถ้ามี) -->
    <?php if (!empty($errorRows)): ?>
        <div class="p-6 bg-rose-50/50 border-b border-rose-100">
            <h4 class="text-sm font-black text-rose-700 flex items-center gap-2 mb-3">
                <i class="bi bi-x-octagon-fill"></i> รายการแถวที่พบข้อผิดพลาด (จะไม่ถูกนำเข้าจนกว่าจะแก้ไขให้ถูกต้อง)
            </h4>
            <div class="overflow-x-auto bg-white rounded-xl border border-rose-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="bg-rose-50 text-rose-800 border-b border-rose-200">
                        <tr>
                            <th class="px-4 py-2.5 font-black">แถวที่</th>
                            <th class="px-4 py-2.5 font-black">ข้อมูลในแถว</th>
                            <th class="px-4 py-2.5 font-black">สาเหตุที่ผิดพลาด</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-rose-100">
                        <?php foreach ($errorRows as $er): ?>
                            <tr>
                                <td class="px-4 py-2.5 font-black text-rose-700">แถว #<?= (int)$er['row_no'] ?></td>
                                <td class="px-4 py-2.5 font-mono text-slate-600"><?= htmlspecialchars(implode(' | ', $er['raw']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-2.5 font-bold text-rose-600">
                                    <?= htmlspecialchars(implode(', ', $er['errors']), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- แสดงตารางตัวอย่างแถวที่ถูกต้อง -->
    <?php if (!empty($validRows)): ?>
        <div class="p-6">
            <h4 class="text-sm font-black text-emerald-700 flex items-center gap-2 mb-3">
                <i class="bi bi-check-circle-fill"></i> ตัวอย่างข้อมูลที่ผ่านการตรวจสอบพร้อมนำเข้า (แสดงสูงสุด 50 แถวแรก)
            </h4>
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-2.5 font-black text-slate-500">แถวที่</th>
                            <?php foreach (array_keys($validRows[0]) as $k):
                                if ($k === 'row_no') continue;
                            ?>
                                <th class="px-4 py-2.5 font-black text-slate-500"><?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach (array_slice($validRows, 0, 50) as $vr): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-black text-emerald-700">#<?= (int)$vr['row_no'] ?></td>
                                <?php foreach ($vr as $k => $val):
                                    if ($k === 'row_no') continue;
                                ?>
                                    <td class="px-4 py-2 text-slate-700 font-semibold"><?= htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
// สร้างไฟล์แม่แบบ .xlsx ด้วย SheetJS ฝั่ง Client
function downloadXlsxTemplate(def) {
    if (typeof XLSX === 'undefined') {
        window.location.href = '/pp5/import_data.php?download_template=' + encodeURIComponent(def.filename.replace('template_pp5_', ''));
        return;
    }
    const aoa = [def.headers, ...def.samples];
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Template');
    XLSX.writeFile(wb, def.filename + '.xlsx');
}

// แปลงไฟล์ .xlsx/.xls อัตโนมัติด้วย SheetJS ก่อนส่งฟอร์มไปตรวจสอบที่ Server
document.getElementById('excelUploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('import_file');
    const jsonInput = document.getElementById('parsed_rows_json');
    if (!fileInput.files || !fileInput.files[0] || jsonInput.value !== '') {
        return;
    }

    const file = fileInput.files[0];
    const ext = file.name.split('.').pop().toLowerCase();
    if ((ext === 'xlsx' || ext === 'xls') && typeof XLSX !== 'undefined') {
        e.preventDefault();
        const reader = new FileReader();
        reader.onload = function(evt) {
            const data = new Uint8Array(evt.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });
            jsonInput.value = JSON.stringify(rows);
            document.getElementById('excelUploadForm').submit();
        };
        reader.readAsArrayBuffer(file);
    }
});
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
