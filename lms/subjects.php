<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);
$msg = '';

$_has_tid = (bool)$pdo->query("SHOW COLUMNS FROM `lms_subjects` LIKE 'teacher_id'")->fetch();

// POST: add / edit / set_classrooms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['subject_name'] ?? '');
        $code = trim($_POST['subject_code'] ?? '');
        if (!$name) { $msg = 'error:กรุณาระบุชื่อวิชา'; }
        else {
            if ($_has_tid) {
                $pdo->prepare("INSERT INTO lms_subjects (subject_name, subject_code, teacher_id) VALUES (?,?,?)")
                    ->execute([$name, $code ?: null, $is_admin ? null : $teacher_id]);
            } else {
                $pdo->prepare("INSERT INTO lms_subjects (subject_name, subject_code) VALUES (?,?)")->execute([$name, $code ?: null]);
            }
            $sid = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO lms_exam_settings (subject_id, pre_pass_score, post_pass_score, post_max_attempts) VALUES (?,6,6,3)")->execute([$sid]);
            $msg = 'success:เพิ่มวิชาสำเร็จ';
        }
        header('Location: subjects.php?msg=' . urlencode($msg)); exit();
    }

    if ($action === 'edit') {
        $id   = (int)$_POST['id'];
        $name = trim($_POST['subject_name'] ?? '');
        $code = trim($_POST['subject_code'] ?? '');
        $pdo->prepare("UPDATE lms_subjects SET subject_name=?, subject_code=? WHERE id=?")->execute([$name, $code ?: null, $id]);
        header('Location: subjects.php?msg=' . urlencode('success:แก้ไขสำเร็จ')); exit();
    }

    if ($action === 'set_classrooms') {
        $sid = (int)$_POST['subject_id'];
        $classrooms = array_filter(array_map('trim', $_POST['classrooms'] ?? []));
        $pdo->prepare("DELETE FROM lms_subject_classrooms WHERE subject_id=?")->execute([$sid]);
        $stmt = $pdo->prepare("INSERT IGNORE INTO lms_subject_classrooms (subject_id, classroom) VALUES (?,?)");
        foreach ($classrooms as $cl) { $stmt->execute([$sid, $cl]); }
        header('Location: subjects.php?msg=' . urlencode('success:บันทึกห้องเรียนสำเร็จ')); exit();
    }
}

// GET: delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $sid = (int)$_GET['id'];
    $units = $pdo->prepare("SELECT id FROM lms_units WHERE subject_id=?"); $units->execute([$sid]); $units = $units->fetchAll();
    foreach ($units as $u) {
        $topics = $pdo->prepare("SELECT id FROM lms_topics WHERE unit_id=?"); $topics->execute([$u['id']]); $topics = $topics->fetchAll();
        foreach ($topics as $t) {
            $files = $pdo->prepare("SELECT filename FROM lms_topic_files WHERE topic_id=?"); $files->execute([$t['id']]); $files = $files->fetchAll();
            foreach ($files as $f) { @unlink(__DIR__ . '/../uploads/lms/' . $f['filename']); }
            foreach (['lms_topic_files','lms_topic_links','lms_topic_youtube'] as $tbl)
                $pdo->prepare("DELETE FROM `{$tbl}` WHERE topic_id=?")->execute([$t['id']]);
        }
        $pdo->prepare("DELETE FROM lms_topics WHERE unit_id=?")->execute([$u['id']]);
        $pdo->prepare("DELETE FROM lms_unit_exercises WHERE unit_id=?")->execute([$u['id']]);
    }
    foreach (['lms_units','lms_pre_questions','lms_post_questions','lms_exam_settings',
              'lms_student_pre_exam','lms_student_post_exam','lms_student_exercises',
              'lms_subject_classrooms'] as $tbl)
        $pdo->prepare("DELETE FROM `{$tbl}` WHERE subject_id=?")->execute([$sid]);
    $pdo->prepare("DELETE FROM lms_subjects WHERE id=?")->execute([$sid]);
    header('Location: subjects.php?msg=' . urlencode('success:ลบวิชาสำเร็จ')); exit();
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

if ($_has_tid && !$is_admin) {
    $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE teacher_id=? ORDER BY created_at");
    $st->execute([$teacher_id]); $subjects = $st->fetchAll();
} else {
    $subjects = $pdo->query("SELECT * FROM lms_subjects ORDER BY created_at")->fetchAll();
}

$all_classrooms = $pdo->query("
    SELECT DISTINCT classroom FROM att_students
    WHERE status='active' AND student_id REGEXP '^[0-9]+$'
      AND student_id NOT IN (SELECT subject_code FROM att_subjects)
    ORDER BY classroom
")->fetchAll(PDO::FETCH_COLUMN);

// Per-subject stats
$subject_data = [];
foreach ($subjects as $s) {
    $sid = $s['id'];
    $sc = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=? ORDER BY classroom"); $sc->execute([$sid]); $classrooms = $sc->fetchAll(PDO::FETCH_COLUMN);
    $uc = $pdo->prepare("SELECT COUNT(*) FROM lms_units WHERE subject_id=?"); $uc->execute([$sid]); $unit_count = (int)$uc->fetchColumn();
    $pq = $pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions WHERE subject_id=?"); $pq->execute([$sid]); $pre_q = (int)$pq->fetchColumn();
    $poq = $pdo->prepare("SELECT COUNT(*) FROM lms_post_questions WHERE subject_id=?"); $poq->execute([$sid]); $post_q = (int)$poq->fetchColumn();
    $stud = $pdo->prepare("SELECT COUNT(DISTINCT student_uid) FROM lms_student_pre_exam WHERE subject_id=?"); $stud->execute([$sid]); $stud_count = (int)$stud->fetchColumn();
    $subject_data[$sid] = compact('classrooms','unit_count','pre_q','post_q','stud_count');
}

$pageTitle    = 'วิชา LMS';
$pageSubtitle = 'จัดการวิชาและกำหนดห้องเรียน';
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{
  const p='<?=addslashes($msg)?>'.split(':');
  Swal.fire({icon:p[0]==='success'?'success':'error',title:p[0]==='success'?'สำเร็จ':'ผิดพลาด',text:p[1],confirmButtonColor:'#7C3AED',timer:p[0]==='success'?2000:undefined,showConfirmButton:p[0]!=='success'});
});</script>
<?php endif; ?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
      <i class="fas fa-chalkboard text-white"></i>
    </div>
    <div>
      <h2 class="text-lg font-black text-slate-800">วิชา LMS</h2>
      <p class="text-xs text-slate-400">กำหนดวิชาและห้องเรียนที่เรียนวิชานั้น</p>
    </div>
  </div>
  <button onclick="openModal('addModal')" class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all flex items-center gap-2">
    <i class="fas fa-plus"></i> เพิ่มวิชา
  </button>
</div>

<?php if (empty($subjects)): ?>
<div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
  <i class="fas fa-chalkboard text-5xl mb-3 block opacity-30"></i>
  <p class="font-bold">ยังไม่มีวิชา — กดเพิ่มวิชาเพื่อเริ่มต้น</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
<?php foreach ($subjects as $s):
  $d = $subject_data[$s['id']];
?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-xl shadow-slate-100/50 overflow-hidden">
  <!-- Header -->
  <div class="px-5 py-4 flex items-start justify-between gap-3" style="background:linear-gradient(135deg,#7C3AED15,#4F46E510)">
    <div>
      <div class="flex items-center gap-2 flex-wrap">
        <span class="font-black text-slate-800 text-base"><?=htmlspecialchars($s['subject_name'],ENT_QUOTES,'UTF-8')?></span>
        <?php if ($s['subject_code']): ?>
        <span class="px-2 py-0.5 bg-violet-100 text-violet-700 text-xs font-bold rounded-full"><?=htmlspecialchars($s['subject_code'],ENT_QUOTES,'UTF-8')?></span>
        <?php endif; ?>
      </div>
      <div class="flex flex-wrap gap-1 mt-2">
        <?php if (empty($d['classrooms'])): ?>
        <span class="px-2 py-0.5 bg-slate-100 text-slate-400 text-xs rounded-full">ยังไม่ได้กำหนดห้อง</span>
        <?php else: ?>
        <?php foreach ($d['classrooms'] as $cl): ?>
        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-xs font-bold rounded-full"><?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?></span>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="flex gap-1.5 flex-shrink-0">
      <button onclick="openEdit(<?=htmlspecialchars(json_encode($s),ENT_QUOTES,'UTF-8')?>)" class="p-1.5 bg-amber-400 text-white rounded-lg hover:bg-amber-500 transition-all text-xs"><i class="fas fa-edit"></i></button>
      <button onclick="confirmDel(<?=$s['id']?>,'<?=htmlspecialchars($s['subject_name'],ENT_QUOTES,'UTF-8')?>')" class="p-1.5 bg-rose-500 text-white rounded-lg hover:bg-rose-600 transition-all text-xs"><i class="fas fa-trash"></i></button>
    </div>
  </div>
  <!-- Stats -->
  <div class="grid grid-cols-4 divide-x divide-slate-100 border-y border-slate-100">
    <div class="px-3 py-2.5 text-center">
      <div class="text-lg font-black text-violet-600"><?=$d['unit_count']?></div>
      <div class="text-[10px] text-slate-400">หน่วย</div>
    </div>
    <div class="px-3 py-2.5 text-center">
      <div class="text-lg font-black text-blue-500"><?=$d['pre_q']?></div>
      <div class="text-[10px] text-slate-400">ก่อนเรียน</div>
    </div>
    <div class="px-3 py-2.5 text-center">
      <div class="text-lg font-black text-rose-500"><?=$d['post_q']?></div>
      <div class="text-[10px] text-slate-400">หลังเรียน</div>
    </div>
    <div class="px-3 py-2.5 text-center">
      <div class="text-lg font-black text-emerald-600"><?=$d['stud_count']?></div>
      <div class="text-[10px] text-slate-400">นักเรียน</div>
    </div>
  </div>
  <!-- Actions -->
  <div class="px-4 py-3 flex flex-wrap gap-2">
    <button onclick="openClassrooms(<?=$s['id']?>,<?=htmlspecialchars(json_encode($d['classrooms']),ENT_QUOTES,'UTF-8')?>)"
      class="px-3 py-1.5 bg-indigo-50 text-indigo-700 text-xs font-bold rounded-lg hover:bg-indigo-100 transition-all">
      <i class="fas fa-school mr-1"></i>ห้องเรียน
    </button>
    <a href="units.php?subject_id=<?=$s['id']?>" class="px-3 py-1.5 bg-violet-50 text-violet-700 text-xs font-bold rounded-lg hover:bg-violet-100 transition-all">
      <i class="fas fa-book-open mr-1"></i>หน่วยการเรียน
    </a>
    <a href="pre_exam.php?subject_id=<?=$s['id']?>" class="px-3 py-1.5 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg hover:bg-blue-100 transition-all">
      <i class="fas fa-play-circle mr-1"></i>ก่อนเรียน
    </a>
    <a href="post_exam.php?subject_id=<?=$s['id']?>" class="px-3 py-1.5 bg-rose-50 text-rose-700 text-xs font-bold rounded-lg hover:bg-rose-100 transition-all">
      <i class="fas fa-flag-checkered mr-1"></i>หลังเรียน
    </a>
    <a href="exam_settings.php?subject_id=<?=$s['id']?>" class="px-3 py-1.5 bg-slate-50 text-slate-600 text-xs font-bold rounded-lg hover:bg-slate-100 transition-all">
      <i class="fas fa-cog mr-1"></i>ตั้งค่า
    </a>
    <a href="progress.php?subject_id=<?=$s['id']?>" class="px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-bold rounded-lg hover:bg-amber-100 transition-all">
      <i class="fas fa-chart-line mr-1"></i>ความคืบหน้า
    </a>
    <a href="exam_answers.php?subject_id=<?=$s['id']?>" class="px-3 py-1.5 bg-violet-50 text-violet-700 text-xs font-bold rounded-lg hover:bg-violet-100 transition-all">
      <i class="fas fa-pen-fancy mr-1"></i>ตรวจอัตนัย
    </a>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <h3 class="font-black text-slate-800"><i class="fas fa-plus-circle mr-2 text-violet-500"></i>เพิ่มวิชา</h3>
      <button onclick="closeModal('addModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="add">
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1">ชื่อวิชา <span class="text-rose-500">*</span></label>
        <input type="text" name="subject_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" placeholder="เช่น วิทยาการคำนวณ">
      </div>
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1">รหัสวิชา <span class="text-slate-300">(ไม่บังคับ)</span></label>
        <input type="text" name="subject_code" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" placeholder="เช่น ว21101">
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <h3 class="font-black text-slate-800"><i class="fas fa-edit mr-2 text-amber-500"></i>แก้ไขวิชา</h3>
      <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1">ชื่อวิชา</label>
        <input type="text" name="subject_name" id="edit_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
      </div>
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1">รหัสวิชา</label>
        <input type="text" name="subject_code" id="edit_code" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-amber-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-amber-200"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- Classrooms Modal -->
<div id="classModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <h3 class="font-black text-slate-800"><i class="fas fa-school mr-2 text-indigo-500"></i>กำหนดห้องเรียน</h3>
      <button onclick="closeModal('classModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" class="p-6">
      <input type="hidden" name="action" value="set_classrooms">
      <input type="hidden" name="subject_id" id="class_subject_id">
      <p class="text-xs text-slate-500 mb-4">เลือกห้องเรียนที่เรียนวิชานี้ (เลือกได้หลายห้อง)</p>
      <div class="grid grid-cols-3 gap-2 max-h-60 overflow-y-auto" id="classCheckboxes">
        <?php foreach ($all_classrooms as $cl): ?>
        <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 hover:bg-violet-50 hover:border-violet-200 cursor-pointer transition-all">
          <input type="checkbox" name="classrooms[]" value="<?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?>" class="cls-check accent-violet-600 w-4 h-4">
          <span class="text-xs font-bold text-slate-700"><?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="flex justify-end gap-3 mt-5">
        <button type="button" onclick="closeModal('classModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-200"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){const el=document.getElementById(id);el.classList.remove('hidden');el.classList.add('flex');}
function closeModal(id){const el=document.getElementById(id);el.classList.add('hidden');el.classList.remove('flex');}

function openEdit(s) {
  document.getElementById('edit_id').value   = s.id;
  document.getElementById('edit_name').value = s.subject_name;
  document.getElementById('edit_code').value = s.subject_code || '';
  openModal('editModal');
}

function openClassrooms(sid, assigned) {
  document.getElementById('class_subject_id').value = sid;
  document.querySelectorAll('.cls-check').forEach(cb => {
    cb.checked = assigned.includes(cb.value);
  });
  openModal('classModal');
}

function confirmDel(id, name) {
  Swal.fire({
    icon:'warning', title:'ลบวิชา "'+name+'"?',
    text:'จะลบข้อมูลทั้งหมดในวิชานี้ (หน่วย/ข้อสอบ/ผลสอบ) ไม่สามารถกู้คืนได้',
    showCancelButton:true, confirmButtonColor:'#ef4444',
    cancelButtonText:'ยกเลิก', confirmButtonText:'ลบ'
  }).then(r=>{ if(r.isConfirmed) location.href='subjects.php?action=delete&id='+id; });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
