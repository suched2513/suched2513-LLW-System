<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();
$msg = '';

// CSV Export / Template
if (isset($_GET['export']) || isset($_GET['template'])) {
    $fn = isset($_GET['export']) ? 'lms_pre_exam_' . date('YmdHis') . '.csv' : 'lms_pre_exam_template.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['คำถาม','ตัวเลือก1','ตัวเลือก2','ตัวเลือก3','ตัวเลือก4','เฉลย(1-4)']);
    if (isset($_GET['export'])) {
        foreach ($pdo->query("SELECT question_text,choice1,choice2,choice3,choice4,correct_answer FROM lms_pre_questions ORDER BY id")->fetchAll() as $r)
            fputcsv($out, $r);
    } else {
        fputcsv($out, ['1+1 เท่ากับเท่าไหร่?','1','2','3','4','2']);
    }
    fclose($out); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'import' && !empty($_FILES['csv_file']['name'])) {
        $h = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $bom = fread($h, 3); if ($bom !== "\xEF\xBB\xBF") rewind($h);
        fgetcsv($h); $cnt = 0;
        while (($row = fgetcsv($h)) !== false) {
            if (count($row) >= 6 && trim($row[0]) && (int)$row[5] >= 1 && (int)$row[5] <= 4) {
                $pdo->prepare("INSERT INTO lms_pre_questions (question_text,choice1,choice2,choice3,choice4,correct_answer) VALUES (?,?,?,?,?,?)")
                    ->execute([trim($row[0]),trim($row[1]),trim($row[2]),trim($row[3]),trim($row[4]),(int)$row[5]]);
                $cnt++;
            }
        }
        fclose($h);
        header('Location: pre_exam.php?msg=' . urlencode("success:นำเข้าสำเร็จ $cnt ข้อ")); exit();
    }

    $qtext  = trim($_POST['question_text'] ?? '');
    $c      = [1=>trim($_POST['choice1']??''),2=>trim($_POST['choice2']??''),3=>trim($_POST['choice3']??''),4=>trim($_POST['choice4']??'')];
    $ans    = (int)($_POST['correct_answer'] ?? 1);
    $id     = (int)($_POST['id'] ?? 0);

    $qimg = $_POST['old_qimg'] ?? null;
    if (!empty($_FILES['question_img']['name'])) {
        $ext = pathinfo($_FILES['question_img']['name'], PATHINFO_EXTENSION);
        $qimg = uniqid('pq_') . '.' . $ext;
        $dir = __DIR__ . '/../uploads/lms/questions/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        move_uploaded_file($_FILES['question_img']['tmp_name'], $dir . $qimg);
    }

    try {
        if (!$qtext || !$c[1] || !$c[2] || !$c[3] || !$c[4]) throw new Exception('กรุณากรอกข้อมูลให้ครบ');
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO lms_pre_questions (question_text,question_img,choice1,choice2,choice3,choice4,correct_answer) VALUES (?,?,?,?,?,?,?)")
                ->execute([$qtext,$qimg,$c[1],$c[2],$c[3],$c[4],$ans]);
            $msg = 'success:เพิ่มข้อสอบสำเร็จ';
        } elseif ($action === 'edit') {
            $pdo->prepare("UPDATE lms_pre_questions SET question_text=?,question_img=?,choice1=?,choice2=?,choice3=?,choice4=?,correct_answer=? WHERE id=?")
                ->execute([$qtext,$qimg,$c[1],$c[2],$c[3],$c[4],$ans,$id]);
            $msg = 'success:แก้ไขสำเร็จ';
        }
    } catch (Exception $e) { $msg = 'error:'.$e->getMessage(); }
    header('Location: pre_exam.php?msg=' . urlencode($msg)); exit();
}
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $pdo->prepare("DELETE FROM lms_pre_questions WHERE id=?")->execute([(int)$_GET['id']]);
    header('Location: pre_exam.php?msg=' . urlencode('success:ลบสำเร็จ')); exit();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$questions = $pdo->query("SELECT * FROM lms_pre_questions ORDER BY id")->fetchAll();
$total = count($questions);

$pageTitle    = 'ข้อสอบก่อนเรียน';
$pageSubtitle = "ทั้งหมด {$total} ข้อ";
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
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-blue-500 to-blue-700">
      <i class="fas fa-clipboard-list text-white"></i>
    </div>
    <div>
      <h2 class="text-lg font-black text-slate-800">ข้อสอบก่อนเรียน</h2>
      <p class="text-xs text-slate-400"><?=$total?> ข้อ · นักเรียนต้องผ่านก่อนเรียนได้</p>
    </div>
  </div>
  <div class="flex gap-2 flex-wrap">
    <a href="pre_exam.php?template=1" class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all">
      <i class="fas fa-download mr-1"></i> Template CSV
    </a>
    <button onclick="openModal('importModal')" class="px-3 py-2 bg-emerald-500 text-white text-xs font-bold rounded-xl hover:bg-emerald-600 transition-all shadow-lg shadow-emerald-200">
      <i class="fas fa-file-import mr-1"></i> Import CSV
    </button>
    <a href="pre_exam.php?export=1" class="px-3 py-2 bg-slate-500 text-white text-xs font-bold rounded-xl hover:bg-slate-600 transition-all">
      <i class="fas fa-file-export mr-1"></i> Export
    </a>
    <button onclick="openModal('addModal')" class="px-3 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">
      <i class="fas fa-plus mr-1"></i> เพิ่มข้อสอบ
    </button>
  </div>
</div>

<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
      <tr>
        <th class="px-5 py-3 w-12 text-center">#</th>
        <th class="px-5 py-3 text-left">คำถาม</th>
        <th class="px-5 py-3 text-center w-24">เฉลย</th>
        <th class="px-5 py-3 text-center w-28">จัดการ</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-50">
      <?php if (empty($questions)): ?>
      <tr><td colspan="4" class="py-16 text-center text-slate-300"><i class="fas fa-clipboard text-4xl mb-3 block opacity-30"></i>ยังไม่มีข้อสอบ</td></tr>
      <?php endif; ?>
      <?php foreach ($questions as $i => $q): ?>
      <tr class="hover:bg-slate-50/50 transition-colors">
        <td class="px-5 py-3 text-center text-slate-400"><?=$i+1?></td>
        <td class="px-5 py-3">
          <div class="font-medium text-slate-700 text-xs"><?=htmlspecialchars(mb_substr($q['question_text'],0,100),ENT_QUOTES,'UTF-8')?></div>
          <div class="flex flex-wrap gap-1 mt-1">
            <?php for ($n=1;$n<=4;$n++): ?>
            <span class="px-2 py-0.5 <?=$q['correct_answer']==$n?'bg-emerald-100 text-emerald-700 font-black':'bg-slate-100 text-slate-400'?> text-xs rounded-full">
              <?=$n?>. <?=htmlspecialchars(mb_substr($q["choice{$n}"],0,20),ENT_QUOTES,'UTF-8')?>
            </span>
            <?php endfor; ?>
          </div>
        </td>
        <td class="px-5 py-3 text-center">
          <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-black rounded-full">ข้อ <?=$q['correct_answer']?></span>
        </td>
        <td class="px-5 py-3">
          <div class="flex gap-2 justify-center">
            <button onclick="openEdit(<?=htmlspecialchars(json_encode($q),ENT_QUOTES,'UTF-8')?>)"
              class="px-2.5 py-1.5 bg-amber-400 text-white text-xs font-bold rounded-lg hover:bg-amber-500 transition-all">
              <i class="fas fa-edit"></i>
            </button>
            <button onclick="confirmDel('pre_exam.php?action=delete&id=<?=$q['id']?>')"
              class="px-2.5 py-1.5 bg-rose-500 text-white text-xs font-bold rounded-lg hover:bg-rose-600 transition-all">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Add Modal -->
<?php foreach ([['add','เพิ่มข้อสอบก่อนเรียน'],['edit','แก้ไขข้อสอบ']] as [$mid,$mtitle]): ?>
<div id="<?=$mid?>Modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white z-10">
      <h3 class="font-black text-slate-800"><i class="fas fa-clipboard-list mr-2 text-blue-500"></i><?=$mtitle?></h3>
      <button onclick="closeModal('<?=$mid?>Modal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
      <input type="hidden" name="action" value="<?=$mid?>">
      <?php if ($mid==='edit'): ?>
      <input type="hidden" name="id" id="eq_id">
      <input type="hidden" name="old_qimg" id="eq_qimg">
      <?php endif; ?>
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1">คำถาม <span class="text-rose-500">*</span></label>
        <textarea name="question_text" id="<?=$mid?>_qtext" rows="3" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 outline-none resize-none" placeholder="พิมพ์คำถาม..."></textarea>
      </div>
      <?php for ($n=1;$n<=4;$n++): ?>
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1">ตัวเลือกที่ <?=$n?> <span class="text-rose-500">*</span></label>
        <input type="text" name="choice<?=$n?>" id="<?=$mid?>_c<?=$n?>" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
      </div>
      <?php endfor; ?>
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1">เฉลย <span class="text-rose-500">*</span></label>
        <select name="correct_answer" id="<?=$mid?>_ans" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
          <?php for($n=1;$n<=4;$n++): ?><option value="<?=$n?>">ข้อ <?=$n?></option><?php endfor; ?>
        </select>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('<?=$mid?>Modal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <h3 class="font-black text-slate-800"><i class="fas fa-file-import mr-2 text-emerald-500"></i>Import CSV</h3>
      <button onclick="closeModal('importModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-6">
      <input type="hidden" name="action" value="import">
      <input type="file" name="csv_file" accept=".csv" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm mb-4">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('importModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-200 hover:bg-emerald-600 transition-all"><i class="fas fa-upload mr-1"></i>นำเข้า</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) { const el=document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id) { const el=document.getElementById(id); el.classList.add('hidden'); el.classList.remove('flex'); }
function openEdit(q) {
  document.getElementById('eq_id').value = q.id;
  document.getElementById('eq_qimg').value = q.question_img||'';
  document.getElementById('edit_qtext').value = q.question_text;
  for(let n=1;n<=4;n++) document.getElementById('edit_c'+n).value = q['choice'+n]||'';
  document.getElementById('edit_ans').value = q.correct_answer;
  openModal('editModal');
}
function confirmDel(url) {
  Swal.fire({icon:'warning',title:'ลบข้อสอบนี้?',showCancelButton:true,confirmButtonColor:'#ef4444',cancelButtonText:'ยกเลิก',confirmButtonText:'ลบ'})
    .then(r=>{if(r.isConfirmed)location.href=url;});
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
