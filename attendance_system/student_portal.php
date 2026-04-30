<?php
/**
 * student_portal.php — Public portal for students
 * A: ดูวิชาที่ลงทะเบียน + สถิติเข้าเรียน
 * C: ลงทะเบียนวิชาเลือกเองได้ (ถ้าตกหล่น)
 */
require_once __DIR__ . '/../config/database.php';
$pdo = getPdo();

$student_code  = trim($_GET['code'] ?? '');
$student_info  = null;
$subjects_summary = [];
$enrolled_electives  = [];   // วิชาเลือกที่ลงแล้ว
$available_electives = [];   // วิชาเลือกที่ยังไม่ได้ลง
$enroll_msg    = '';
$enroll_type   = 'success';
$error         = '';

// ── Handle Self-Enrollment POST ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['student_db_id'], $_POST['subject_id'])) {
    csrf_verify();
    if ($_POST['action'] === 'self_enroll') {
        $db_id  = (int)$_POST['student_db_id'];
        $sub_id = (int)$_POST['subject_id'];
        $student_code = trim($_POST['code'] ?? '');
        if ($db_id && $sub_id) {
            try {
                // ตรวจสอบว่า subject_id เป็นวิชาเลือกจริง
                $chk = $pdo->prepare("SELECT id FROM att_subjects WHERE id=? AND is_elective=1 LIMIT 1");
                $chk->execute([$sub_id]);
                if ($chk->fetch()) {
                    $ins = $pdo->prepare("INSERT IGNORE INTO att_subject_students (subject_id, student_id) VALUES (?,?)");
                    $ins->execute([$sub_id, $db_id]);
                    $enroll_msg = 'ลงทะเบียนสำเร็จแล้ว ✅';
                } else {
                    $enroll_msg = 'วิชานี้ไม่ใช่วิชาเลือก หรือไม่มีในระบบ'; $enroll_type = 'error';
                }
            } catch (Exception $e) {
                $enroll_msg = 'เกิดข้อผิดพลาด'; $enroll_type = 'error';
            }
        }
    } elseif ($_POST['action'] === 'self_unenroll') {
        $db_id  = (int)$_POST['student_db_id'];
        $sub_id = (int)$_POST['subject_id'];
        $student_code = trim($_POST['code'] ?? '');
        if ($db_id && $sub_id) {
            $pdo->prepare("DELETE FROM att_subject_students WHERE subject_id=? AND student_id=?")->execute([$sub_id, $db_id]);
            $enroll_msg = 'ยกเลิกการลงทะเบียนแล้ว';
        }
    }
}

// ── Fetch student data ────────────────────────────────────────
if ($student_code !== '') {
    $st = $pdo->prepare("SELECT * FROM att_students WHERE student_id = ? LIMIT 1");
    $st->execute([$student_code]);
    $student_info = $st->fetch();

    if ($student_info) {
        $sid = $student_info['id'];

        // สถิติการเข้าเรียนรายวิชา (เฉพาะมีข้อมูลการเช็คชื่อแล้ว)
        $stmt = $pdo->prepare("
            SELECT
                sj.id as subject_id, sj.subject_code, sj.subject_name, sj.classroom, sj.is_elective,
                COUNT(DISTINCT CONCAT(a.date,'_',a.period)) as my_sessions,
                (SELECT COUNT(DISTINCT CONCAT(date,'_',period)) FROM att_attendance WHERE subject_id=sj.id) as total_sessions,
                SUM(CASE WHEN a.status='มา'  THEN 1 ELSE 0 END) as cnt_come,
                SUM(CASE WHEN a.status='ขาด' THEN 1 ELSE 0 END) as cnt_absent,
                SUM(CASE WHEN a.status='ลา'  THEN 1 ELSE 0 END) as cnt_leave,
                SUM(CASE WHEN a.status='โดด' THEN 1 ELSE 0 END) as cnt_skip,
                SUM(CASE WHEN a.status='สาย' THEN 1 ELSE 0 END) as cnt_late
            FROM att_attendance a
            JOIN att_subjects sj ON sj.id = a.subject_id
            WHERE a.student_id = :sid
            GROUP BY sj.id ORDER BY sj.subject_code
        ");
        $stmt->execute(['sid' => $sid]);
        $subjects_summary = $stmt->fetchAll();

        // วิชาเลือกที่นักเรียนลงทะเบียนแล้ว
        try {
            $e1 = $pdo->prepare("
                SELECT sj.id, sj.subject_code, sj.subject_name, sj.classroom,
                       t.name as teacher_name
                FROM att_subject_students ss
                JOIN att_subjects sj ON sj.id = ss.subject_id
                JOIN att_teachers t ON t.id = sj.teacher_id
                WHERE ss.student_id = ?
                ORDER BY sj.subject_code
            ");
            $e1->execute([$sid]);
            $enrolled_electives = $e1->fetchAll();

            // วิชาเลือกที่ยังไม่ได้ลง (ทุกวิชาเลือกในระบบที่ยังไม่มีชื่อนักเรียนนี้)
            $enrolled_ids = array_column($enrolled_electives, 'id');
            $e2 = $pdo->prepare("
                SELECT sj.id, sj.subject_code, sj.subject_name, sj.classroom,
                       t.name as teacher_name,
                       (SELECT COUNT(*) FROM att_subject_students WHERE subject_id=sj.id) as enrolled_count
                FROM att_subjects sj
                JOIN att_teachers t ON t.id = sj.teacher_id
                WHERE sj.is_elective = 1
                ORDER BY sj.subject_code
            ");
            $e2->execute();
            $all_electives = $e2->fetchAll();
            $available_electives = array_filter($all_electives, fn($s) => !in_array($s['id'], $enrolled_ids));
        } catch (Exception $e) {
            // migration ยังไม่ run — ข้ามส่วนนี้
        }
    } else {
        $error = 'ไม่พบรหัสนักเรียน "' . htmlspecialchars($student_code) . '" กรุณาตรวจสอบอีกครั้ง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบเวลาเรียน — โรงเรียนละลมวิทยา</title>
    <meta name="description" content="นักเรียนสามารถตรวจสอบสถิติการเข้าเรียนและวิชาที่ลงทะเบียนได้ที่นี่">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { font-family: 'Prompt', sans-serif; }
        .glass { background: rgba(255,255,255,0.75); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.5); }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 40%, #f093fb 100%); }
        .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 800; }
        @keyframes fadeUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .fade-up { animation: fadeUp 0.5s ease forwards; }
        .progress-bar { height: 8px; border-radius: 99px; background: #e2e8f0; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 99px; transition: width 1s ease; }
        .tab-active { background:white; color:#7c3aed; box-shadow:0 2px 12px rgba(0,0,0,.08); }
    </style>
</head>
<body class="gradient-bg min-h-screen flex flex-col items-center justify-start py-10 px-4">

    <!-- Header -->
    <div class="text-center mb-8 fade-up" style="animation-delay:0s">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-3xl border border-white/30 text-white text-2xl font-black italic mb-4 shadow-2xl">LLW</div>
        <h1 class="text-3xl font-black text-white drop-shadow-lg">ตรวจสอบเวลาเรียน</h1>
        <p class="text-white/70 text-sm mt-2">โรงเรียนละลมวิทยา — ระบบสำหรับนักเรียน</p>
    </div>

    <!-- Search Card -->
    <div class="w-full max-w-md glass rounded-[2rem] shadow-2xl p-8 fade-up" style="animation-delay:0.1s">
        <h2 class="text-lg font-black text-slate-800 mb-1">กรอกรหัสนักเรียน</h2>
        <p class="text-xs text-slate-400 font-medium mb-6">ใส่รหัสประจำตัวนักเรียนของคุณ</p>
        <form method="GET" class="flex gap-3">
            <div class="relative flex-1">
                <i class="bi bi-person-badge absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                <input type="text" name="code" value="<?= htmlspecialchars($student_code) ?>"
                       placeholder="เช่น 4100, 4102..." autofocus
                       class="w-full pl-12 pr-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-sm font-bold focus:ring-2 focus:ring-indigo-400 outline-none transition-all">
            </div>
            <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3.5 rounded-2xl font-black shadow-lg shadow-indigo-200 hover:scale-[1.03] transition-all">
                <i class="bi bi-search"></i>
            </button>
        </form>

        <?php if ($error): ?>
        <div class="mt-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 text-sm font-bold flex items-center gap-3">
            <i class="bi bi-person-x-fill text-xl"></i> <?= $error ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($student_info): ?>

    <!-- Student Profile -->
    <div class="w-full max-w-2xl mt-6 fade-up" style="animation-delay:0.2s">
        <div class="glass rounded-[2rem] shadow-2xl p-6 flex items-center gap-5">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-2xl font-black shadow-lg">
                <?= mb_substr($student_info['name'], 0, 1) ?>
            </div>
            <div>
                <h2 class="text-xl font-black text-slate-800"><?= htmlspecialchars($student_info['name']) ?></h2>
                <p class="text-sm font-bold text-indigo-500 font-mono">รหัส: <?= $student_info['student_id'] ?></p>
                <span class="inline-block mt-1 px-3 py-0.5 bg-indigo-50 text-indigo-700 rounded-xl font-bold text-xs border border-indigo-100">ชั้น <?= htmlspecialchars($student_info['classroom']) ?></span>
            </div>
            <div class="ml-auto text-right hidden sm:flex gap-6">
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">วิชาที่เรียน</p>
                    <p class="text-3xl font-black text-slate-700"><?= count($subjects_summary) ?></p>
                </div>
                <?php if (!empty($enrolled_electives)): ?>
                <div>
                    <p class="text-[10px] text-violet-400 font-bold uppercase tracking-widest">วิชาเลือก</p>
                    <p class="text-3xl font-black text-violet-600"><?= count($enrolled_electives) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tab Menu -->
    <div class="w-full max-w-2xl mt-5 fade-up" style="animation-delay:0.25s">
        <div class="flex p-1.5 bg-white/20 rounded-2xl gap-1">
            <button onclick="showPortalTab('attendance')" id="ptab-attendance"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold text-white/80 transition-all tab-active">
                <i class="bi bi-calendar-check mr-1.5"></i> ประวัติเข้าเรียน
            </button>
            <button onclick="showPortalTab('elective')" id="ptab-elective"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold text-white/80 transition-all">
                <i class="bi bi-star-fill mr-1.5"></i> วิชาเลือก
                <?php if (!empty($enrolled_electives)): ?>
                <span class="ml-1 px-1.5 py-0.5 bg-violet-200 text-violet-700 text-[9px] font-black rounded-lg"><?= count($enrolled_electives) ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <!-- ══ ATTENDANCE TAB ══ -->
    <div id="ppane-attendance" class="w-full max-w-2xl mt-4 fade-up" style="animation-delay:0.3s">
        <?php
        $ms_subjects = array_filter($subjects_summary, fn($s) => $s['total_sessions'] > 0 && round(($s['cnt_come']/$s['total_sessions'])*100,1) < 80);
        ?>
        <?php if (!empty($ms_subjects)): ?>
        <div class="bg-rose-600 text-white rounded-2xl px-6 py-4 flex items-center gap-4 shadow-xl shadow-rose-200/50 mb-4">
            <i class="bi bi-exclamation-triangle-fill text-2xl"></i>
            <div>
                <p class="font-black text-base">⚠️ คุณอาจติด มส. ใน <?= count($ms_subjects) ?> วิชา</p>
                <p class="text-rose-200 text-xs mt-0.5">กรุณาติดต่อครูผู้สอนเพื่อแก้ไขก่อนสิ้นภาคเรียน</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($subjects_summary)): ?>
        <div class="grid gap-4">
            <?php foreach ($subjects_summary as $subj):
                $total = $subj['total_sessions'];
                $rate  = $total > 0 ? round(($subj['cnt_come'] / $total) * 100, 1) : 0;
                $is_ms = $total > 0 && $rate < 80;
                $rc    = $rate >= 80 ? 'emerald' : ($rate >= 60 ? 'amber' : 'rose');
            ?>
            <div class="glass rounded-2xl shadow-lg p-6 <?= $is_ms ? 'border-rose-300' : 'border-white/50' ?>">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black text-indigo-500 uppercase tracking-widest font-mono"><?= htmlspecialchars($subj['subject_code']) ?></span>
                            <?php if (!empty($subj['is_elective'])): ?>
                            <span class="px-2 py-0.5 bg-violet-100 text-violet-600 text-[9px] font-black rounded-lg">วิชาเลือก</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-black text-slate-800 text-base"><?= htmlspecialchars($subj['subject_name']) ?></h3>
                        <span class="text-xs text-slate-400 font-medium">ห้อง <?= $subj['classroom'] ?> | สอนไปแล้ว <?= $total ?> คาบ</span>
                    </div>
                    <?php if ($is_ms): ?>
                    <span class="status-badge bg-rose-600 text-white shadow-md flex-shrink-0"><i class="bi bi-x-circle-fill"></i> ติด มส.</span>
                    <?php else: ?>
                    <span class="status-badge bg-emerald-100 text-emerald-700 border border-emerald-200 flex-shrink-0"><i class="bi bi-check-circle-fill"></i> ผ่านเกณฑ์</span>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs text-slate-500 font-bold">% มาเรียน</span>
                        <span class="text-lg font-black text-<?= $rc ?>-600"><?= $rate ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill bg-<?= $rc ?>-500" style="width: <?= min($rate,100) ?>%"></div>
                    </div>
                    <?php if ($is_ms): ?>
                    <p class="text-[10px] text-rose-500 font-bold mt-1">ต้องการ <?= 80 - $rate ?>% เพิ่มเติม (ยังขาดอีก <?= max(0, ceil($total * 0.8) - $subj['cnt_come']) ?> คาบ)</p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-5 gap-2">
                    <?php
                    $stats = [
                        ['label'=>'มา',  'val'=>$subj['cnt_come'],   'color'=>'emerald'],
                        ['label'=>'ขาด', 'val'=>$subj['cnt_absent'], 'color'=>'rose'],
                        ['label'=>'ลา',  'val'=>$subj['cnt_leave'],  'color'=>'amber'],
                        ['label'=>'โดด', 'val'=>$subj['cnt_skip'],   'color'=>'violet'],
                        ['label'=>'สาย', 'val'=>$subj['cnt_late'],   'color'=>'orange'],
                    ];
                    foreach ($stats as $s): ?>
                    <div class="text-center bg-slate-50 rounded-xl py-2.5">
                        <p class="text-[9px] font-black text-<?= $s['color'] ?>-500 uppercase"><?= $s['label'] ?></p>
                        <p class="text-lg font-black text-slate-700"><?= $s['val'] ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="glass rounded-[2rem] shadow-xl p-8 text-center">
            <i class="bi bi-calendar2-x text-5xl text-slate-300 mb-4 block"></i>
            <p class="font-black text-slate-500">ยังไม่มีข้อมูลการเช็คชื่อ</p>
            <p class="text-xs text-slate-400 mt-1">ขอให้ครูผู้สอนเช็คชื่อในระบบก่อนครับ</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══ ELECTIVE TAB ══ -->
    <div id="ppane-elective" class="w-full max-w-2xl mt-4 hidden flex flex-col gap-5">

        <!-- วิชาเลือกที่ลงแล้ว -->
        <div class="glass rounded-[2rem] shadow-xl p-6">
            <h3 class="font-black text-slate-800 text-base mb-4 flex items-center gap-2">
                <div class="w-8 h-8 bg-violet-100 text-violet-600 rounded-xl flex items-center justify-center"><i class="bi bi-check2-circle"></i></div>
                วิชาเลือกที่ลงทะเบียนแล้ว
            </h3>
            <?php if (empty($enrolled_electives)): ?>
            <div class="text-center py-6 text-slate-400">
                <i class="bi bi-journal-x text-4xl block mb-2 opacity-40"></i>
                <p class="font-bold text-sm">ยังไม่ได้ลงทะเบียนวิชาเลือกใด</p>
            </div>
            <?php else: ?>
            <div class="grid gap-3">
                <?php foreach ($enrolled_electives as $ev): ?>
                <div class="flex items-center justify-between bg-violet-50 border border-violet-100 rounded-2xl px-5 py-4">
                    <div>
                        <span class="font-mono text-[10px] font-black text-violet-400"><?= htmlspecialchars($ev['subject_code']) ?></span>
                        <p class="font-black text-slate-800"><?= htmlspecialchars($ev['subject_name']) ?></p>
                        <p class="text-[11px] text-slate-400 font-bold"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($ev['teacher_name']) ?> &nbsp;·&nbsp; ห้อง <?= $ev['classroom'] ?></p>
                    </div>
                    <form method="POST" class="flex-shrink-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="self_unenroll">
                        <input type="hidden" name="student_db_id" value="<?= $student_info['id'] ?>">
                        <input type="hidden" name="subject_id" value="<?= $ev['id'] ?>">
                        <input type="hidden" name="code" value="<?= htmlspecialchars($student_code) ?>">
                        <button type="button" onclick="confirmUnenroll(this, '<?= addslashes($ev['subject_name']) ?>')"
                                class="text-[11px] font-bold text-rose-400 hover:text-rose-600 transition px-3 py-1.5 rounded-xl hover:bg-rose-50">
                            <i class="bi bi-x-lg"></i> ยกเลิก
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- วิชาเลือกที่ยังไม่ได้ลง -->
        <?php if (!empty($available_electives)): ?>
        <div class="glass rounded-[2rem] shadow-xl p-6">
            <h3 class="font-black text-slate-800 text-base mb-1 flex items-center gap-2">
                <div class="w-8 h-8 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center"><i class="bi bi-plus-circle-fill"></i></div>
                วิชาเลือกที่ยังไม่ได้ลง
            </h3>
            <p class="text-xs text-slate-400 font-bold mb-4">ถ้าตกหล่น หรือต้องการเพิ่ม กด "ลงทะเบียน" ได้เลย</p>
            <div class="grid gap-3">
                <?php foreach ($available_electives as $av): ?>
                <div class="flex items-center justify-between bg-amber-50 border border-amber-100 rounded-2xl px-5 py-4">
                    <div>
                        <span class="font-mono text-[10px] font-black text-amber-400"><?= htmlspecialchars($av['subject_code']) ?></span>
                        <p class="font-black text-slate-800"><?= htmlspecialchars($av['subject_name']) ?></p>
                        <p class="text-[11px] text-slate-400 font-bold">
                            <i class="bi bi-person-fill"></i> <?= htmlspecialchars($av['teacher_name']) ?>
                            &nbsp;·&nbsp; ห้อง <?= $av['classroom'] ?>
                            &nbsp;·&nbsp; <i class="bi bi-people-fill"></i> ลงทะเบียนแล้ว <?= $av['enrolled_count'] ?> คน
                        </p>
                    </div>
                    <form method="POST" class="flex-shrink-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="self_enroll">
                        <input type="hidden" name="student_db_id" value="<?= $student_info['id'] ?>">
                        <input type="hidden" name="subject_id" value="<?= $av['id'] ?>">
                        <input type="hidden" name="code" value="<?= htmlspecialchars($student_code) ?>">
                        <button type="submit"
                                class="text-[11px] font-black text-violet-600 bg-violet-100 hover:bg-violet-600 hover:text-white transition px-4 py-2 rounded-xl shadow-sm">
                            <i class="bi bi-plus-lg"></i> ลงทะเบียน
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif (empty($enrolled_electives)): ?>
        <div class="glass rounded-2xl p-5 text-center text-slate-400 text-sm font-bold">
            <i class="bi bi-star text-3xl block mb-2 opacity-30"></i>
            ยังไม่มีวิชาเลือกเปิดในระบบ
        </div>
        <?php endif; ?>

    </div>

    <?php endif; ?>

    <!-- Footer -->
    <div class="mt-10 mb-4 text-center">
        <p class="text-white/50 text-xs font-medium">LLW Platform · โรงเรียนละลมวิทยา</p>
        <a href="/login.php" class="text-white/40 text-[10px] hover:text-white/70 transition mt-1 block">ครูและบุคลากร → เข้าสู่ระบบหลัก</a>
    </div>

<script>
function showPortalTab(tab) {
    ['attendance', 'elective'].forEach(t => {
        const pane = document.getElementById('ppane-' + t);
        const btn  = document.getElementById('ptab-' + t);
        if (!pane || !btn) return;
        pane.classList.toggle('hidden', t !== tab);
        if (t === tab) {
            btn.classList.add('tab-active');
            btn.classList.remove('text-white/80');
        } else {
            btn.classList.remove('tab-active');
            btn.classList.add('text-white/80');
        }
    });
}

function confirmUnenroll(btn, name) {
    Swal.fire({
        title: 'ยกเลิกการลงทะเบียน?',
        text: `ต้องการยกเลิกวิชา "${name}" หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        confirmButtonText: 'ยืนยันยกเลิก',
        cancelButtonText: 'ไม่ใช่'
    }).then(r => {
        if (r.isConfirmed) btn.closest('form').submit();
    });
}

<?php if ($enroll_msg): ?>
document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
        icon: '<?= $enroll_type === 'error' ? 'error' : 'success' ?>',
        title: '<?= $enroll_type === 'error' ? 'เกิดข้อผิดพลาด' : 'สำเร็จ' ?>',
        text: '<?= addslashes($enroll_msg) ?>',
        timer: 2000, showConfirmButton: false
    }).then(() => showPortalTab('elective'));
    showPortalTab('elective');
});
<?php endif; ?>
</script>
</body>
</html>
