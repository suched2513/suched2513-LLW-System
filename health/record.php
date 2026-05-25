<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo     = getPdo();
$user_id = (int)$_SESSION['user_id'];
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rec     = null;

if ($edit_id) {
    $st = $pdo->prepare("SELECT hr.*, s.name student_name, s.classroom, s.gender, s.birthdate FROM health_records hr JOIN att_students s ON s.id=hr.student_id WHERE hr.id=?");
    $st->execute([$edit_id]);
    $rec = $st->fetch();
    if (!$rec) { header('Location: /health/dashboard.php'); exit(); }
}

// Classrooms
$classrooms = $pdo->query("
    SELECT DISTINCT classroom FROM att_students
    WHERE status='active' AND student_id REGEXP '^[0-9]+$'
      AND student_id NOT IN (SELECT subject_code FROM att_subjects)
    ORDER BY classroom
")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle    = $edit_id ? 'แก้ไขข้อมูลสุขภาพ' : 'บันทึกข้อมูลสุขภาพ';
$pageSubtitle = 'น้ำหนัก ส่วนสูง ภาวะโภชนาการ';
$activeSystem = 'health';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="max-w-2xl mx-auto">

<!-- Back + Title -->
<div class="flex items-center gap-3 mb-6">
  <a href="/health/dashboard.php" class="w-9 h-9 bg-slate-100 rounded-xl flex items-center justify-center hover:bg-slate-200 transition-all">
    <i class="fas fa-arrow-left text-slate-600 text-sm"></i>
  </a>
  <div>
    <h2 class="text-lg font-black text-slate-800"><?=$pageTitle?></h2>
    <p class="text-xs text-slate-400"><?=$pageSubtitle?></p>
  </div>
</div>

<!-- Form Card -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 p-6">

  <?php if ($rec): ?>
  <div class="mb-5 p-4 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center gap-3">
    <i class="fas fa-user-circle text-emerald-500 text-2xl"></i>
    <div>
      <p class="font-black text-slate-800 text-sm"><?=htmlspecialchars($rec['student_name'],ENT_QUOTES,'UTF-8')?></p>
      <p class="text-xs text-slate-400"><?=htmlspecialchars($rec['classroom'],ENT_QUOTES,'UTF-8')?></p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Student Selector (new record only) -->
  <?php if (!$rec): ?>
  <div class="mb-5">
    <label class="block text-xs font-black text-slate-600 mb-2 uppercase tracking-wider">ห้องเรียน</label>
    <select id="sel_classroom" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
      <option value="">— เลือกห้องเรียน —</option>
      <?php foreach ($classrooms as $cl): ?>
      <option><?=htmlspecialchars($cl,ENT_QUOTES,'UTF-8')?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-5" id="student_wrap" style="display:none">
    <label class="block text-xs font-black text-slate-600 mb-2 uppercase tracking-wider">นักเรียน</label>
    <select id="sel_student" name="student_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
      <option value="">— กำลังโหลด... —</option>
    </select>
    <p id="student_info" class="text-xs text-slate-400 mt-1 hidden"></p>
  </div>
  <?php else: ?>
  <input type="hidden" id="sel_student" value="<?=$rec['student_id']?>">
  <?php endif; ?>

  <form id="health_form" method="post" action="/health/save.php">
    <?php if ($edit_id): ?>
    <input type="hidden" name="id" value="<?=$edit_id?>">
    <?php endif; ?>
    <input type="hidden" name="student_id" id="hidden_student_id" value="<?=$rec['student_id']??''?>">

    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-xs font-black text-slate-600 mb-2 uppercase tracking-wider">น้ำหนัก (kg) <span class="text-rose-500">*</span></label>
        <input type="number" name="weight_kg" id="weight_kg" step="0.1" min="10" max="200" required
          value="<?=htmlspecialchars($rec['weight_kg']??'',ENT_QUOTES,'UTF-8')?>"
          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
      </div>
      <div>
        <label class="block text-xs font-black text-slate-600 mb-2 uppercase tracking-wider">ส่วนสูง (cm) <span class="text-rose-500">*</span></label>
        <input type="number" name="height_cm" id="height_cm" step="0.1" min="80" max="220" required
          value="<?=htmlspecialchars($rec['height_cm']??'',ENT_QUOTES,'UTF-8')?>"
          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-xs font-black text-slate-600 mb-2 uppercase tracking-wider">รอบเอว (cm)</label>
        <input type="number" name="waist_cm" id="waist_cm" step="0.1" min="30" max="200"
          value="<?=htmlspecialchars($rec['waist_cm']??'',ENT_QUOTES,'UTF-8')?>"
          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
      </div>
      <div>
        <label class="block text-xs font-black text-slate-600 mb-2 uppercase tracking-wider">BMI (คำนวณอัตโนมัติ)</label>
        <div id="bmi_display" class="w-full bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 text-sm font-black text-emerald-700">—</div>
      </div>
    </div>

    <!-- DOH status preview -->
    <div id="status_preview" class="hidden mb-4 p-4 bg-slate-50 border border-slate-200 rounded-xl">
      <p class="text-xs font-black text-slate-500 uppercase tracking-wider mb-2">ผลการประเมินตามเกณฑ์กรมอนามัย</p>
      <div class="grid grid-cols-2 gap-3 text-xs">
        <div><span class="text-slate-400">ภาวะโภชนาการ (BMI/อายุ):</span><br><span id="bfa_lbl" class="font-black text-slate-700">—</span></div>
        <div><span class="text-slate-400">ส่วนสูงตามวัย:</span><br><span id="hfa_lbl" class="font-black text-slate-700">—</span></div>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-xs font-black text-slate-600 mb-2 uppercase tracking-wider">วันที่วัด <span class="text-rose-500">*</span></label>
        <input type="date" name="record_date" required
          value="<?=htmlspecialchars($rec['record_date']??date('Y-m-d'),ENT_QUOTES,'UTF-8')?>"
          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
      </div>
      <div>
        <label class="block text-xs font-black text-slate-600 mb-2 uppercase tracking-wider">ภาคเรียน <span class="text-rose-500">*</span></label>
        <select name="semester" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
          <option value="1" <?=($rec['semester']??1)==1?'selected':''?>>ภาคเรียนที่ 1</option>
          <option value="2" <?=($rec['semester']??1)==2?'selected':''?>>ภาคเรียนที่ 2</option>
        </select>
      </div>
    </div>

    <div class="mb-4">
      <label class="block text-xs font-black text-slate-600 mb-2 uppercase tracking-wider">ปีการศึกษา <span class="text-rose-500">*</span></label>
      <input type="number" name="academic_year" required
        value="<?=htmlspecialchars($rec['academic_year']??2569,ENT_QUOTES,'UTF-8')?>"
        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
    </div>

    <div class="mb-6">
      <label class="block text-xs font-black text-slate-600 mb-2 uppercase tracking-wider">หมายเหตุ</label>
      <textarea name="note" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-400 outline-none resize-none"><?=htmlspecialchars($rec['note']??'',ENT_QUOTES,'UTF-8')?></textarea>
    </div>

    <div class="flex gap-3">
      <button type="submit" id="btn_save"
        class="flex-1 bg-emerald-600 text-white py-3 rounded-xl font-black text-sm shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all">
        <i class="fas fa-save mr-2"></i><?=$edit_id ? 'บันทึกการแก้ไข' : 'บันทึกข้อมูล'?>
      </button>
      <?php if ($edit_id): ?>
      <button type="button" onclick="confirmDelete(<?=$edit_id?>)"
        class="px-4 py-3 bg-rose-50 text-rose-600 rounded-xl font-black text-sm hover:bg-rose-100 transition-all">
        <i class="fas fa-trash"></i>
      </button>
      <?php endif; ?>
    </div>
  </form>
</div>
</div>

<script>
const studentData = {};  // cache: student_id → {birthdate, gender, name}

// Classroom → students loader
document.getElementById('sel_classroom')?.addEventListener('change', function() {
    const cl = this.value;
    if (!cl) return;
    fetch('/health/api.php?action=students_by_class&classroom=' + encodeURIComponent(cl))
        .then(r => r.json())
        .then(d => {
            const sel = document.getElementById('sel_student');
            sel.innerHTML = '<option value="">— เลือกนักเรียน —</option>';
            d.forEach(s => {
                studentData[s.id] = s;
                sel.innerHTML += `<option value="${s.id}">${s.name}</option>`;
            });
            document.getElementById('student_wrap').style.display = '';
            document.getElementById('hidden_student_id').value = '';
        });
});

document.getElementById('sel_student')?.addEventListener('change', function() {
    const id = this.value;
    document.getElementById('hidden_student_id').value = id;
    const s = studentData[id];
    if (s) {
        const info = document.getElementById('student_info');
        info.textContent = `เพศ: ${s.gender === 'male' ? 'ชาย' : 'หญิง'} · วันเกิด: ${s.birthdate || '—'}`;
        info.classList.remove('hidden');
    }
    calcBmi();
});

function calcBmi() {
    const w = parseFloat(document.getElementById('weight_kg').value);
    const h = parseFloat(document.getElementById('height_cm').value);
    if (!w || !h || h < 50) {
        document.getElementById('bmi_display').textContent = '—';
        document.getElementById('status_preview').classList.add('hidden');
        return;
    }
    const bmi = w / ((h/100) * (h/100));
    document.getElementById('bmi_display').textContent = bmi.toFixed(1);

    // Lookup DOH standards
    const sid = document.getElementById('hidden_student_id').value;
    const s = studentData[sid];
    if (!sid || !s?.birthdate) return;

    fetch(`/health/api.php?action=evaluate&student_id=${sid}&weight=${w}&height=${h}`)
        .then(r => r.json())
        .then(d => {
            if (d.bfa_status) {
                document.getElementById('bfa_lbl').textContent = d.bfa_status;
                document.getElementById('hfa_lbl').textContent = d.hfa_status || '—';
                document.getElementById('status_preview').classList.remove('hidden');
            }
        });
}

// For edit mode — trigger calc on load
<?php if ($rec && $rec['birthdate']): ?>
studentData[<?=$rec['student_id']?>] = {
    id: <?=$rec['student_id']?>,
    name: <?=json_encode($rec['student_name'])?>,
    gender: <?=json_encode($rec['gender'])?>,
    birthdate: <?=json_encode($rec['birthdate'])?>
};
<?php endif; ?>

['weight_kg','height_cm'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', calcBmi);
});
window.addEventListener('load', calcBmi);

// Form submit guard
document.getElementById('health_form').addEventListener('submit', function(e) {
    const sid = document.getElementById('hidden_student_id').value;
    if (!sid) {
        e.preventDefault();
        Swal.fire({ icon:'warning', title:'กรุณาเลือกนักเรียน', confirmButtonColor:'#10B981' });
        return;
    }
    document.getElementById('btn_save').disabled = true;
    document.getElementById('btn_save').textContent = 'กำลังบันทึก...';
});

function confirmDelete(id) {
    Swal.fire({
        title: 'ลบบันทึกนี้?', text: 'ไม่สามารถกู้คืนได้',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#F43F5E', cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบเลย', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if (r.isConfirmed) window.location = '/health/save.php?delete='+id;
    });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
