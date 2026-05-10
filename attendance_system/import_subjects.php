<?php
/**
 * attendance_system/import_subjects.php — นำเข้ารายวิชาผ่าน CSV
 */
require_once 'functions.php';
checkAdmin();

$pageTitle    = 'นำเข้ารายวิชา CSV';
$pageSubtitle = 'อัปโหลดไฟล์ CSV เพื่อเพิ่มหรืออัปเดตรายวิชาในเทอมนี้';
$activeSystem = 'attendance';

$msg = ''; $msgType = ''; $preview = []; $skipped = []; $importCount = 0;

// ── ดึงครูทั้งหมดสำหรับ lookup username → teacher_id ──
$allTeachers = $pdo->query("
    SELECT at.id, at.name, at.username,
           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(lu.firstname,''),' ',COALESCE(lu.lastname,''))),''), at.name) AS display_name
    FROM att_teachers at
    LEFT JOIN llw_users lu ON lu.user_id = at.llw_user_id
    ORDER BY display_name
")->fetchAll(PDO::FETCH_ASSOC);

// map username → id  and  name → id
$teacherByUsername = [];
$teacherByName     = [];
foreach ($allTeachers as $t) {
    if ($t['username']) $teacherByUsername[trim($t['username'])] = (int)$t['id'];
    $teacherByName[trim($t['name'])]         = (int)$t['id'];
    $teacherByName[trim($t['display_name'])] = (int)$t['id'];
}

// ── ดึงรายวิชาปัจจุบัน (subject_code + classroom เป็น key) ──
$existingSubjects = [];
foreach ($pdo->query("SELECT subject_code, classroom FROM att_subjects")->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $existingSubjects[strtolower(trim($s['subject_code'])) . '|' . trim($s['classroom'])] = true;
}

// ── Helper: แปลง username/ชื่อ → teacher_id ──
function resolveTeacher(string $raw, array $byUser, array $byName): ?int {
    $raw = trim($raw);
    if ($raw === '') return null;
    if (isset($byUser[$raw]))  return $byUser[$raw];
    if (isset($byName[$raw]))  return $byName[$raw];
    // ลองไม่สนใจ case
    $lower = mb_strtolower($raw);
    foreach ($byUser as $k => $v) { if (mb_strtolower($k) === $lower) return $v; }
    foreach ($byName as $k => $v) { if (mb_strtolower($k) === $lower) return $v; }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';

    // ── Fix classroom: แทนที่ชื่อห้องแบบกลุ่ม ──
    if ($do === 'fix_classroom') {
        $from = trim($_POST['cls_from'] ?? '');
        $to   = trim($_POST['cls_to']   ?? '');
        if (!$from || !$to) {
            $msg = 'กรุณากรอกชื่อห้องทั้งสองช่อง'; $msgType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE att_subjects SET classroom=? WHERE classroom=?");
                $stmt->execute([$to, $from]);
                $affected = $stmt->rowCount();
                $msg = "อัปเดตห้องเรียน จาก \"{$from}\" → \"{$to}\" สำเร็จ {$affected} รายวิชา";
                $msgType = $affected > 0 ? 'success' : 'warning';
                // refresh classrooms list
                $classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_subjects ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);
                $existingSubjects = [];
                foreach ($pdo->query("SELECT subject_code, classroom FROM att_subjects")->fetchAll(PDO::FETCH_ASSOC) as $s) {
                    $existingSubjects[strtolower(trim($s['subject_code'])) . '|' . trim($s['classroom'])] = true;
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $msg = 'เกิดข้อผิดพลาด'; $msgType = 'error';
            }
        }
    }

    // ── Preview ──
    if ($do === 'preview' && isset($_FILES['csvfile'])) {
        $file = $_FILES['csvfile'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์'; $msgType = 'error';
        } else {
            $content = file_get_contents($file['tmp_name']);
            // strip BOM
            $content = ltrim($content, "\xEF\xBB\xBF");
            // convert TIS-620 → UTF-8 if needed
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = @iconv('TIS-620', 'UTF-8//IGNORE', $content) ?: $content;
            }
            // normalize ALL line endings (\r\n Windows, \r Mac old, \n Unix)
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            // detect delimiter: semicolon only if no comma in file
            $delim = (strpos($content, ';') !== false && strpos($content, ',') === false) ? ';' : ',';
            $lines = explode("\n", $content);

            $skipped = []; // เก็บแถวที่ข้ามไปพร้อมเหตุผล
            $first   = true;
            foreach ($lines as $idx => $line) {
                $lineNum = $idx + 1;
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delim);

                // skip header row (first non-empty line)
                if ($first) {
                    $first = false;
                    // ตรวจว่า header row จริงๆ ไม่ใช่ข้อมูล (ถ้า col 0 เป็น subject_code ให้ข้ามจริง)
                    $h0 = strtolower(trim($row[0] ?? ''));
                    if (in_array($h0, ['subject_code','รหัสวิชา','code','รหัส'])) continue;
                    // ถ้าไม่ใช่ header ให้ process row นี้ต่อไปเลย
                }

                if (count($row) < 2) {
                    $skipped[] = ['row' => $lineNum, 'reason' => 'คอลัมน์ไม่ครบ (พบ '.count($row).' คอลัมน์)'];
                    continue;
                }

                $code        = trim($row[0] ?? '');
                $name        = trim($row[1] ?? '');
                $cls         = trim($row[2] ?? '');
                $rawTeacher  = trim($row[3] ?? '');
                $rawElective = strtolower(trim($row[4] ?? ''));
                $isElective  = in_array($rawElective, ['1','yes','true','elective','วิชาเลือก']) ? 1 : 0;

                if (!$code) { $skipped[] = ['row' => $lineNum, 'reason' => 'รหัสวิชาว่าง']; continue; }
                if (!$name) { $skipped[] = ['row' => $lineNum, 'reason' => 'ชื่อวิชาว่าง'];  continue; }

                $tid = resolveTeacher($rawTeacher, $teacherByUsername, $teacherByName);
                $teacherDisplay = '';
                if ($tid) {
                    foreach ($allTeachers as $t) {
                        if ((int)$t['id'] === $tid) { $teacherDisplay = $t['display_name']; break; }
                    }
                }

                $key     = strtolower(trim($code)) . '|' . trim($cls);
                $isExist = isset($existingSubjects[$key]);

                $preview[] = [
                    'row'             => $lineNum,
                    'subject_code'    => $code,
                    'subject_name'    => $name,
                    'classroom'       => $cls,
                    'teacher_raw'     => $rawTeacher,
                    'teacher_id'      => $tid,
                    'teacher_display' => $teacherDisplay,
                    'is_existing'     => $isExist,
                    'teacher_found'   => ($rawTeacher === '' || $tid !== null),
                    'is_elective'     => $isElective,
                ];
            }
            if (empty($preview)) {
                $msg = 'ไม่พบข้อมูลในไฟล์ (ตรวจสอบ header row และรูปแบบ CSV)'; $msgType = 'error';
            }
        }
    }

    // ── Import ──
    if ($do === 'import' && !empty($_POST['json_data'])) {
        $data = json_decode($_POST['json_data'], true);
        if (!$data) {
            $msg = 'ข้อมูลไม่ถูกต้อง กรุณาอัปโหลดไฟล์ใหม่'; $msgType = 'error';
        } else {
            // ── ตรวจ column teacher_id ว่า NULL ได้หรือไม่ ──
            $colInfo = $pdo->query("SHOW COLUMNS FROM att_subjects LIKE 'teacher_id'")->fetch(PDO::FETCH_ASSOC);
            $tidNullable = ($colInfo && strtolower($colInfo['Null'] ?? '') === 'yes');
            $tidFallback = $tidNullable ? null : 0; // ใช้ค่า default ที่ปลอดภัย

            $stmtChk       = $pdo->prepare("SELECT id FROM att_subjects WHERE LOWER(TRIM(subject_code))=LOWER(TRIM(?)) AND TRIM(classroom)=TRIM(?) LIMIT 1");
            $stmtUpdFull   = $pdo->prepare("UPDATE att_subjects SET subject_name=?, teacher_id=?, is_elective=? WHERE LOWER(TRIM(subject_code))=LOWER(TRIM(?)) AND TRIM(classroom)=TRIM(?)");
            $stmtUpdName   = $pdo->prepare("UPDATE att_subjects SET subject_name=?, is_elective=? WHERE LOWER(TRIM(subject_code))=LOWER(TRIM(?)) AND TRIM(classroom)=TRIM(?)");
            $stmtIns       = $pdo->prepare("INSERT INTO att_subjects (subject_code, subject_name, classroom, teacher_id, is_elective) VALUES (?, ?, ?, ?, ?)");

            $errors = [];
            foreach ($data as $r) {
                $code       = trim($r['subject_code'] ?? '');
                $name       = trim($r['subject_name'] ?? '');
                $cls        = trim($r['classroom']    ?? '');
                $tid        = isset($r['teacher_id']) && $r['teacher_id'] > 0 ? (int)$r['teacher_id'] : null;
                $isElective = isset($r['is_elective']) ? (int)$r['is_elective'] : 0;
                if (!$code || !$name) continue;

                try {
                    $stmtChk->execute([$code, $cls]);
                    $existId = $stmtChk->fetchColumn();
                    if ($existId) {
                        if ($tid !== null) {
                            $stmtUpdFull->execute([$name, $tid, $isElective, $code, $cls]);
                        } else {
                            $stmtUpdName->execute([$name, $isElective, $code, $cls]);
                        }
                    } else {
                        $insertTid = $tid !== null ? $tid : $tidFallback;
                        $stmtIns->execute([$code, $name, $cls, $insertTid, $isElective]);
                    }
                    $importCount++;
                } catch (Exception $e) {
                    error_log("import_subjects row [{$code}|{$cls}]: " . $e->getMessage());
                    $errors[] = $code . ($cls ? " ({$cls})" : '');
                }
            }

            if ($importCount > 0) {
                $msg = "นำเข้า/อัปเดตรายวิชาสำเร็จ {$importCount} รายการ";
                if ($errors) $msg .= " (ข้ามไป " . count($errors) . " รายการ: " . implode(', ', array_slice($errors, 0, 5)) . ")";
                $msgType = $errors ? 'warning' : 'success';
            } elseif ($errors) {
                $msg = 'นำเข้าไม่สำเร็จ (' . implode(', ', array_slice($errors, 0, 3)) . ')';
                $msgType = 'error';
            } else {
                $msg = 'ไม่มีข้อมูลที่ต้องนำเข้า'; $msgType = 'warning';
            }
        }
    }
}

// ── ดึงรายวิชาปัจจุบันสำหรับตาราง ──
$filterCls = $_GET['cls'] ?? '';
$whereQ = $filterCls
    ? " WHERE s.classroom = " . $pdo->quote($filterCls)
    : '';
$subjects = $pdo->query("
    SELECT s.*, at.name AS teacher_name,
           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(lu.firstname,''),' ',COALESCE(lu.lastname,''))),''), at.name) AS teacher_display
    FROM att_subjects s
    LEFT JOIN att_teachers at ON at.id = s.teacher_id
    LEFT JOIN llw_users lu ON lu.user_id = at.llw_user_id
    $whereQ
    ORDER BY s.classroom, s.subject_code
")->fetchAll(PDO::FETCH_ASSOC);

$classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_subjects ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/components/layout_start.php';
?>

<style>
.glass-card { background: rgba(255,255,255,.7); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,.5); }
.drop-zone  { border: 2px dashed #cbd5e1; transition: all .3s; }
.drop-zone:hover, .drop-zone.active { border-color: #4f46e5; background: #f5f3ff; }
</style>

<div class="space-y-10 mb-20">

    <!-- Top Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- Left: Import -->
        <div class="lg:col-span-8 flex flex-col gap-6">
            <div class="glass-card rounded-[40px] p-8 shadow-xl shadow-indigo-100/30">
                <div class="flex items-center gap-4 mb-8">
                    <div class="w-14 h-14 bg-gradient-to-br from-indigo-600 to-blue-600 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-indigo-200/50">
                        <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-slate-800">นำเข้ารายวิชาแบบกลุ่ม</h2>
                        <p class="text-sm text-slate-500">อัปโหลดไฟล์ CSV เพื่อเพิ่มหรืออัปเดตรายวิชาในเทอมนี้</p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="importForm" class="space-y-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="preview">
                    <div id="dropZone" class="drop-zone rounded-[32px] p-10 flex flex-col items-center justify-center cursor-pointer group">
                        <i class="bi bi-file-earmark-spreadsheet text-5xl text-slate-300 group-hover:text-indigo-500 mb-4 transition-colors"></i>
                        <p class="text-slate-600 font-bold">ลากไฟล์ CSV มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</p>
                        <p id="fileLabel" class="text-xs text-slate-400 uppercase tracking-widest mt-2">รองรับ .CSV (UTF-8 หรือ TIS-620)</p>
                        <input type="file" name="csvfile" id="fileInput" accept=".csv" class="hidden">
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="bg-slate-900 text-white rounded-2xl px-8 py-4 font-black text-sm hover:bg-black hover:-translate-y-1 transition-all shadow-xl shadow-slate-200">
                            <i class="bi bi-search me-2"></i> ตรวจสอบข้อมูล
                        </button>
                    </div>
                </form>
            </div>

            <!-- Preview -->
            <?php
            $warnCount = count(array_filter($preview, fn($p) => !$p['teacher_found']));
            $newCount  = count(array_filter($preview, fn($p) => !$p['is_existing']));
            $updCount  = count(array_filter($preview, fn($p) =>  $p['is_existing']));
            if (!empty($preview) || !empty($skipped)):
            ?>
            <div class="glass-card rounded-[40px] overflow-hidden shadow-2xl border-2 border-indigo-500/20">
                <div class="px-8 py-6 bg-gradient-to-r from-indigo-600 to-blue-600 text-white flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-xl">
                            <i class="bi bi-ui-checks"></i>
                        </div>
                        <div>
                            <h3 class="font-black text-lg">ตรวจสอบข้อมูล <?= count($preview) ?> รายวิชา</h3>
                            <p class="text-xs opacity-80">
                                <span class="font-black text-emerald-200">เพิ่มใหม่ <?= $newCount ?></span>
                                · <span class="font-black text-amber-200">อัปเดต <?= $updCount ?></span>
                                <?php if ($warnCount): ?> · <span class="font-black text-rose-200">ไม่พบครู <?= $warnCount ?></span><?php endif; ?>
                                <?php if (!empty($skipped)): ?> · <span class="font-black text-rose-300">ข้ามไป <?= count($skipped) ?> แถว</span><?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="import">
                        <input type="hidden" name="json_data"
                               value='<?= htmlspecialchars(json_encode($preview, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
                        <button type="submit"
                                class="bg-white text-indigo-600 px-8 py-3 rounded-2xl font-black text-sm hover:scale-105 active:scale-95 transition-all shadow-xl">
                            <i class="bi bi-check2-circle me-2"></i> ยืนยันนำเข้า
                        </button>
                    </form>
                </div>
                <div class="max-h-[500px] overflow-y-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 sticky top-0 z-10">
                            <tr class="text-left">
                                <th class="px-5 py-4 text-xs font-black text-slate-400 uppercase tracking-widest">รหัสวิชา</th>
                                <th class="px-5 py-4 text-xs font-black text-slate-400 uppercase tracking-widest">ชื่อวิชา</th>
                                <th class="px-5 py-4 text-xs font-black text-slate-400 uppercase tracking-widest">ห้อง</th>
                                <th class="px-5 py-4 text-xs font-black text-slate-400 uppercase tracking-widest">ครูผู้สอน</th>
                                <th class="px-5 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">ประเภท</th>
                                <th class="px-5 py-4 text-center text-xs font-black text-slate-400 uppercase tracking-widest">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($preview as $p): ?>
                        <tr class="hover:bg-indigo-50/40 transition-colors <?= !$p['teacher_found'] ? 'bg-rose-50/40' : ($p['is_existing'] ? 'bg-amber-50/30' : '') ?>">
                            <td class="px-5 py-3 font-mono font-black text-indigo-600 text-xs"><?= htmlspecialchars($p['subject_code']) ?></td>
                            <td class="px-5 py-3 font-bold text-slate-700"><?= htmlspecialchars($p['subject_name']) ?></td>
                            <td class="px-5 py-3">
                                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs font-black"><?= htmlspecialchars($p['classroom']) ?></span>
                            </td>
                            <td class="px-5 py-3 text-sm">
                                <?php if ($p['teacher_id']): ?>
                                    <span class="text-emerald-600 font-bold text-xs"><i class="bi bi-person-check-fill me-1"></i><?= htmlspecialchars($p['teacher_display']) ?></span>
                                <?php elseif ($p['teacher_raw']): ?>
                                    <span class="text-rose-500 font-bold text-xs"><i class="bi bi-person-x-fill me-1"></i><?= htmlspecialchars($p['teacher_raw']) ?> (ไม่พบ)</span>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <?php if (!empty($p['is_elective'])): ?>
                                    <span class="px-2 py-0.5 rounded-lg bg-violet-100 text-violet-700 text-xs font-black">วิชาเลือก</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-500 text-xs font-bold">บังคับ</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <?php if ($p['is_existing']): ?>
                                    <span class="px-2 py-0.5 rounded-lg bg-amber-100 text-amber-700 text-xs font-black">อัปเดต</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-black">เพิ่มใหม่</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($skipped)): ?>
            <div class="glass-card rounded-[32px] p-6 shadow-lg border border-rose-200/60">
                <h4 class="font-black text-rose-600 mb-3 text-sm flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    แถวที่ข้ามไป <?= count($skipped) ?> แถว
                </h4>
                <div class="space-y-1 max-h-40 overflow-y-auto">
                <?php foreach ($skipped as $sk): ?>
                <div class="text-xs flex items-center gap-2 text-slate-600 py-1 border-b border-slate-50">
                    <span class="font-mono text-rose-500 font-black w-16 shrink-0">แถว <?= (int)$sk['row'] ?></span>
                    <span><?= htmlspecialchars($sk['reason']) ?></span>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Right: Help + Format -->
        <div class="lg:col-span-4 flex flex-col gap-6">

            <!-- Format Guide -->
            <div class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-[40px] p-8 text-white shadow-xl shadow-indigo-200 relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 text-9xl text-white/10 rotate-12"><i class="bi bi-table"></i></div>
                <div class="relative z-10">
                    <h3 class="font-black text-lg mb-4">รูปแบบไฟล์ CSV</h3>
                    <ul class="text-xs space-y-3 font-medium opacity-90">
                        <li class="flex gap-2"><i class="bi bi-1-circle-fill text-indigo-200"></i> คอลัมน์ 1: <strong>รหัสวิชา</strong> เช่น ว21101</li>
                        <li class="flex gap-2"><i class="bi bi-2-circle-fill text-indigo-200"></i> คอลัมน์ 2: <strong>ชื่อวิชา</strong> เช่น วิทยาศาสตร์</li>
                        <li class="flex gap-2"><i class="bi bi-3-circle-fill text-indigo-200"></i> คอลัมน์ 3: <strong>ห้องเรียน</strong> เช่น ม.1/1</li>
                        <li class="flex gap-2"><i class="bi bi-4-circle-fill text-amber-300"></i> คอลัมน์ 4: <strong>Username ครู</strong> (ไม่บังคับ)</li>
                    </ul>
                    <div class="mt-4 p-3 bg-white/10 rounded-xl text-xs font-mono">
                        <div class="opacity-70 mb-1">ตัวอย่าง:</div>
                        <div>ว21101,วิทยาศาสตร์,ม.1/1,teacher1</div>
                        <div>ท21101,ภาษาไทย,ม.1/2,teacher2</div>
                    </div>
                    <div class="mt-4 p-3 bg-white/10 rounded-xl text-xs">
                        <i class="bi bi-info-circle me-1"></i>
                        ถ้ารหัสวิชา + ห้องซ้ำ ระบบจะ <strong>อัปเดต</strong> ชื่อและครูให้อัตโนมัติ
                    </div>
                    <!-- Download Template -->
                    <?php
                    $csvTemplate = "\xEF\xBB\xBF" . "subject_code,subject_name,classroom,teacher_username,is_elective\r\n";
                    $csvTemplate .= "ว21101,วิทยาศาสตร์,ม.1/1,,0\r\n";
                    $csvTemplate .= "ท21101,ภาษาไทย,ม.1/2,,0\r\n";
                    $csvTemplate .= "ว30223,ยากับชีวิตประจำวัน,ม.6(ว.ส),,1\r\n";
                    $csvB64 = base64_encode($csvTemplate);
                    ?>
                    <a href="data:text/csv;charset=utf-8;base64,<?= $csvB64 ?>"
                       download="subjects_template.csv"
                       class="mt-5 inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all">
                        <i class="bi bi-download"></i> โหลดไฟล์ตัวอย่าง
                    </a>
                </div>
            </div>

            <!-- Fix Classroom Card -->
            <?php
            $subjectClassrooms = $pdo->query("SELECT DISTINCT classroom FROM att_subjects ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);
            $studentClassrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);
            $mismatchCls = array_diff($subjectClassrooms, $studentClassrooms);
            ?>
            <div class="glass-card rounded-[40px] p-8 shadow-xl shadow-slate-200/50 border <?= !empty($mismatchCls) ? 'border-rose-200' : 'border-slate-100' ?>">
                <h3 class="font-black text-slate-800 mb-1 flex items-center gap-2">
                    <i class="bi bi-pencil-square text-amber-500"></i> แก้ชื่อห้องในวิชา
                </h3>
                <p class="text-xs text-slate-400 mb-4">เปลี่ยนชื่อห้องทั้งหมดในวิชา ให้ตรงกับห้องของนักเรียน</p>

                <?php if (!empty($mismatchCls)): ?>
                <div class="bg-rose-50 border border-rose-200 rounded-2xl px-4 py-3 mb-4">
                    <p class="text-xs font-black text-rose-600 mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>ห้องในวิชาที่ไม่มีนักเรียน:</p>
                    <?php foreach ($mismatchCls as $mc): ?>
                    <button type="button" onclick="fillClsFrom('<?= htmlspecialchars($mc, ENT_QUOTES) ?>')"
                            class="inline-block mt-1 mr-1 px-2 py-0.5 bg-rose-100 text-rose-700 text-xs font-mono font-black rounded-lg hover:bg-rose-200 cursor-pointer transition-colors">
                        <?= htmlspecialchars($mc) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="fix_classroom">
                    <div>
                        <label class="text-xs font-black text-slate-500 uppercase tracking-wider block mb-1">จากห้อง</label>
                        <input id="cls_from" name="cls_from" type="text" placeholder="เช่น ท.2/1"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-amber-400 outline-none">
                    </div>
                    <div>
                        <label class="text-xs font-black text-slate-500 uppercase tracking-wider block mb-1">เป็นห้อง</label>
                        <input id="cls_to" name="cls_to" type="text" placeholder="เช่น ม.2/1"
                               list="student-cls-list"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-emerald-400 outline-none">
                        <datalist id="student-cls-list">
                        <?php foreach ($studentClassrooms as $sc): ?>
                        <option value="<?= htmlspecialchars($sc) ?>">
                        <?php endforeach; ?>
                        </datalist>
                    </div>
                    <button type="submit"
                            class="w-full bg-amber-500 text-white py-2.5 rounded-xl font-black text-sm hover:bg-amber-600 transition-all shadow-lg shadow-amber-100">
                        <i class="bi bi-arrow-left-right me-2"></i> เปลี่ยนชื่อห้อง
                    </button>
                </form>
            </div>

            <!-- Teacher list quick ref -->
            <div class="glass-card rounded-[40px] p-8 shadow-xl shadow-slate-200/50">
                <h3 class="font-black text-slate-800 mb-4 flex items-center gap-2">
                    <i class="bi bi-person-lines-fill text-indigo-500"></i> Username ครูในระบบ
                </h3>
                <div class="max-h-48 overflow-y-auto space-y-1">
                <?php foreach ($allTeachers as $t): ?>
                <div class="flex justify-between items-center text-xs py-1 border-b border-slate-50">
                    <span class="font-bold text-slate-600"><?= htmlspecialchars($t['display_name']) ?></span>
                    <span class="font-mono text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-lg"><?= htmlspecialchars($t['username'] ?? '-') ?></span>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Subject List Table -->
    <div class="glass-card rounded-[40px] overflow-hidden shadow-xl shadow-slate-200/50">
        <div class="px-8 py-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
            <h3 class="text-xl font-black text-slate-800 flex items-center gap-3">
                <i class="bi bi-journal-bookmark-fill text-indigo-500"></i> รายวิชาทั้งหมด
                <span class="text-sm font-bold text-slate-400">(<?= count($subjects) ?> รายวิชา)</span>
            </h3>
            <form method="GET" class="flex gap-2">
                <select name="cls" class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500/20">
                    <option value="">— ทุกห้อง —</option>
                    <?php foreach ($classrooms as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $filterCls === $c ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="bg-slate-900 text-white px-5 py-2 rounded-2xl text-xs font-black hover:bg-black transition-all">กรอง</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50/50">
                    <tr class="text-left">
                        <th class="px-6 py-4 text-xs font-black text-slate-400 uppercase tracking-widest">#</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-400 uppercase tracking-widest">รหัสวิชา</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-400 uppercase tracking-widest">ชื่อวิชา</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-400 uppercase tracking-widest">ห้องเรียน</th>
                        <th class="px-6 py-4 text-xs font-black text-slate-400 uppercase tracking-widest">ครูผู้สอน</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($subjects as $i => $s): ?>
                <tr class="hover:bg-indigo-50/30 transition-colors">
                    <td class="px-6 py-4 text-xs text-slate-400 font-bold"><?= $i + 1 ?></td>
                    <td class="px-6 py-4 font-mono font-black text-indigo-600 text-xs"><?= htmlspecialchars($s['subject_code']) ?></td>
                    <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($s['subject_name']) ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs font-black"><?= htmlspecialchars($s['classroom']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <?php if ($s['teacher_display']): ?>
                        <span class="text-slate-700 font-bold text-xs"><?= htmlspecialchars($s['teacher_display']) ?></span>
                        <?php else: ?>
                        <span class="text-rose-400 text-xs font-bold"><i class="bi bi-exclamation-circle me-1"></i>ยังไม่ระบุครู</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($subjects)): ?>
                <tr><td colspan="5" class="px-6 py-20 text-center">
                    <i class="bi bi-journal-x text-5xl text-slate-200 mb-4 block"></i>
                    <p class="text-slate-400 font-bold">ยังไม่มีรายวิชา — อัปโหลดไฟล์ CSV ด้านบนได้เลย</p>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const dropZone  = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileLabel = document.getElementById('fileLabel');

dropZone.onclick = () => fileInput.click();
fileInput.onchange = () => {
    if (fileInput.files.length) {
        fileLabel.textContent = '📄 ' + fileInput.files[0].name;
        document.getElementById('importForm').submit();
    }
};
dropZone.ondragover = e => { e.preventDefault(); dropZone.classList.add('active'); };
dropZone.ondragleave = () => dropZone.classList.remove('active');
dropZone.ondrop = e => {
    e.preventDefault(); dropZone.classList.remove('active');
    fileInput.files = e.dataTransfer.files;
    document.getElementById('importForm').submit();
};

function fillClsFrom(cls) {
    const fromEl = document.getElementById('cls_from');
    if (fromEl) { fromEl.value = cls; fromEl.closest('form').querySelector('#cls_to').focus(); }
}
</script>

<?php
if ($msg) {
    $safeMsg = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    echo "<script>Swal.fire({icon:'$msgType',title:'แจ้งเตือน',text:'$safeMsg',timer:4000,showConfirmButton:false,customClass:{popup:'rounded-[32px]'}});</script>";
}
require_once 'components/layout_end.php';
?>
