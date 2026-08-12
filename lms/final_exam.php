<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
require_once __DIR__ . '/_helpers.php';

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);
$msg = '';

$subject_id = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
if (!$subject_id) { header('Location: subjects.php'); exit(); }
$subject = lms_get_owned_subject($pdo, $subject_id, $is_admin, $teacher_id);
if (!$subject) { header('Location: subjects.php'); exit(); }

$question_types = lms_question_types();

if (isset($_GET['export']) || isset($_GET['template'])) {
    $fn = isset($_GET['export']) ? 'lms_final_exam_'.$subject_id.'_'.date('YmdHis').'.csv' : 'lms_final_exam_template.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fn.'"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['คำถาม','ตัวเลือก1','ตัวเลือก2','ตัวเลือก3','ตัวเลือก4','เฉลย(1-5)','ตัวเลือก5(ไม่บังคับ)']);
    if (isset($_GET['export'])) {
        $st = $pdo->prepare("SELECT question_text,choice1,choice2,choice3,choice4,correct_answer,choice5 FROM lms_final_questions WHERE subject_id=? AND question_type='choice' ORDER BY id");
        $st->execute([$subject_id]);
        foreach ($st->fetchAll() as $r) fputcsv($out, $r);
    } else {
        fputcsv($out, ['1+1 เท่ากับเท่าไหร่?','1','2','3','4','2','']);
    }
    fclose($out); exit();
}

function lms_parse_lines(string $raw): array {
    $lines = array_map('trim', explode("\n", str_replace("\r", '', $raw)));
    return array_values(array_filter($lines, fn($l) => $l !== ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'import' && !empty($_FILES['csv_file']['name'])) {
        $h = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $bom = fread($h, 3); if ($bom !== "\xEF\xBB\xBF") rewind($h);
        fgetcsv($h); $cnt = 0;
        while (($row = fgetcsv($h)) !== false) {
            $choice5 = trim($row[6] ?? '');
            $ansNum  = (int)($row[5] ?? 0);
            if (count($row) >= 6 && trim($row[0]) && $ansNum >= 1 && $ansNum <= 5 && !($ansNum === 5 && $choice5 === '')) {
                $pdo->prepare("INSERT INTO lms_final_questions (subject_id,question_text,choice1,choice2,choice3,choice4,choice5,correct_answer) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$subject_id,trim($row[0]),trim($row[1]),trim($row[2]),trim($row[3]),trim($row[4]),$choice5,$ansNum]);
                $cnt++;
            }
        }
        fclose($h);
        header('Location: final_exam.php?subject_id='.$subject_id.'&msg='.urlencode("success:นำเข้าสำเร็จ $cnt ข้อ")); exit();
    }

    $qtext = trim($_POST['question_text'] ?? '');
    $qtype = array_key_exists($_POST['question_type'] ?? '', $question_types) ? $_POST['question_type'] : 'choice';
    $id    = (int)($_POST['id'] ?? 0);

    $qimg = $_POST['old_qimg'] ?? null;
    if (!empty($_FILES['question_img']['name'])) {
        $ext = pathinfo($_FILES['question_img']['name'], PATHINFO_EXTENSION);
        $qimg = uniqid('fq_').'.'.$ext;
        $dir = __DIR__.'/../uploads/lms/questions/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        move_uploaded_file($_FILES['question_img']['tmp_name'], $dir.$qimg);
    }

    // Per-type field extraction
    $c = [1=>'',2=>'',3=>'',4=>'',5=>''];
    $ans = null; $options_json = null; $correct_json = null;

    try {
        if (!$qtext) throw new Exception('กรุณาระบุคำถาม');

        if (in_array($qtype, ['choice','multi_choice'], true)) {
            $c = [1=>trim($_POST['choice1']??''),2=>trim($_POST['choice2']??''),3=>trim($_POST['choice3']??''),4=>trim($_POST['choice4']??''),5=>trim($_POST['choice5']??'')];
            if (!$c[1] || !$c[2] || !$c[3] || !$c[4]) throw new Exception('กรุณากรอกตัวเลือกให้ครบ 4 ข้อ');
            if ($qtype === 'choice') {
                $ans = (int)($_POST['correct_answer'] ?? 1);
            } else {
                $sel = array_map('intval', $_POST['correct_multi'] ?? []);
                if (empty($sel)) throw new Exception('กรุณาเลือกเฉลยอย่างน้อย 1 ข้อ');
                $correct_json = json_encode(array_values($sel));
            }
        } elseif ($qtype === 'true_false') {
            $ans = (int)($_POST['tf_answer'] ?? 1);
        } elseif ($qtype === 'fill_blank') {
            $accepted = lms_parse_lines($_POST['fill_answers'] ?? '');
            if (empty($accepted)) throw new Exception('กรุณาระบุคำตอบที่ยอมรับอย่างน้อย 1 คำตอบ');
            $correct_json = json_encode($accepted, JSON_UNESCAPED_UNICODE);
        } elseif ($qtype === 'matching') {
            $left  = lms_parse_lines($_POST['matching_left'] ?? '');
            $right = lms_parse_lines($_POST['matching_right'] ?? '');
            if (empty($left) || count($left) !== count($right)) throw new Exception('รายการซ้ายและขวาต้องมีจำนวนเท่ากันและไม่ว่าง');
            $options_json = json_encode(['left'=>$left,'right'=>$right], JSON_UNESCAPED_UNICODE);
        } elseif ($qtype === 'ordering') {
            $items = lms_parse_lines($_POST['ordering_items'] ?? '');
            if (count($items) < 2) throw new Exception('กรุณาระบุรายการอย่างน้อย 2 รายการ (เรียงตามลำดับที่ถูกต้อง)');
            $options_json = json_encode($items, JSON_UNESCAPED_UNICODE);
        }
        // text / upload: no extra fields needed

        if ($action === 'add') {
            $pdo->prepare("INSERT INTO lms_final_questions (subject_id,question_text,question_type,question_img,choice1,choice2,choice3,choice4,choice5,correct_answer,options_json,correct_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$subject_id,$qtext,$qtype,$qimg,$c[1],$c[2],$c[3],$c[4],$c[5],$ans,$options_json,$correct_json]);
            $msg = 'success:เพิ่มข้อสอบสำเร็จ';
        } elseif ($action === 'edit') {
            $pdo->prepare("UPDATE lms_final_questions SET question_text=?,question_type=?,question_img=?,choice1=?,choice2=?,choice3=?,choice4=?,choice5=?,correct_answer=?,options_json=?,correct_json=? WHERE id=? AND subject_id=?")
                ->execute([$qtext,$qtype,$qimg,$c[1],$c[2],$c[3],$c[4],$c[5],$ans,$options_json,$correct_json,$id,$subject_id]);
            $msg = 'success:แก้ไขสำเร็จ';
        }
    } catch (Exception $e) { $msg = 'error:'.$e->getMessage(); }
    header('Location: final_exam.php?subject_id='.$subject_id.'&msg='.urlencode($msg)); exit();
}
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $pdo->prepare("DELETE FROM lms_final_questions WHERE id=? AND subject_id=?")->execute([(int)$_GET['id'],$subject_id]);
    header('Location: final_exam.php?subject_id='.$subject_id.'&msg='.urlencode('success:ลบสำเร็จ')); exit();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$st = $pdo->prepare("SELECT * FROM lms_final_questions WHERE subject_id=? ORDER BY id");
$st->execute([$subject_id]);
$questions = $st->fetchAll();
$total = count($questions);

$pageTitle    = 'ข้อสอบปลายภาค';
$pageSubtitle = htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8');
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{
  const p='<?=addslashes($msg)?>'.split(':');
  Swal.fire({icon:p[0]==='success'?'success':'error',title:p[0]==='success'?'สำเร็จ':'ผิดพลาด',text:p[1],confirmButtonColor:'#D97706',timer:p[0]==='success'?2000:undefined,showConfirmButton:p[0]!=='success'});
});</script>
<?php endif; ?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg bg-gradient-to-br from-amber-500 to-amber-700">
      <i class="fas fa-clipboard-list text-white"></i>
    </div>
    <div>
      <a href="<?=$base_path?>/lms/subject_dashboard.php?subject_id=<?=$subject_id?>&tab=exam" class="text-xs text-violet-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i>ข้อสอบ</a>
      <h2 class="text-lg font-black text-slate-800">ข้อสอบปลายภาค</h2>
      <p class="text-xs text-slate-400"><?=$total?> ข้อ · <?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?></p>
    </div>
  </div>
  <div class="flex gap-2 flex-wrap">
    <a href="final_exam.php?subject_id=<?=$subject_id?>&template=1" class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all">
      <i class="fas fa-download mr-1"></i> Template CSV
    </a>
    <button onclick="openModal('importModal')" class="px-3 py-2 bg-emerald-500 text-white text-xs font-bold rounded-xl hover:bg-emerald-600 transition-all shadow-lg shadow-emerald-200">
      <i class="fas fa-file-import mr-1"></i> Import CSV
    </button>
    <a href="final_exam.php?subject_id=<?=$subject_id?>&export=1" class="px-3 py-2 bg-slate-500 text-white text-xs font-bold rounded-xl hover:bg-slate-600 transition-all">
      <i class="fas fa-file-export mr-1"></i> Export
    </a>
    <button onclick="openAddModal()" class="px-3 py-2 bg-amber-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-amber-200 hover:bg-amber-700 transition-all">
      <i class="fas fa-plus mr-1"></i> เพิ่มข้อสอบ
    </button>
  </div>
</div>
<p class="text-xs text-slate-400 mb-4"><i class="fas fa-info-circle mr-1"></i>Import/Export CSV รองรับเฉพาะข้อสอบประเภท "ปรนัยหนึ่งคำตอบ" — ประเภทอื่นให้เพิ่มผ่านฟอร์มโดยตรง</p>

<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
      <tr>
        <th class="px-5 py-3 w-12 text-center">#</th>
        <th class="px-5 py-3 text-left">คำถาม</th>
        <th class="px-5 py-3 text-center w-32">ประเภท</th>
        <th class="px-5 py-3 text-center w-28">จัดการ</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-50">
      <?php if (empty($questions)): ?>
      <tr><td colspan="4" class="py-16 text-center text-slate-300"><i class="fas fa-clipboard text-4xl mb-3 block opacity-30"></i>ยังไม่มีข้อสอบ</td></tr>
      <?php endif; ?>
      <?php foreach ($questions as $i => $q):
        $qtype = $q['question_type'] ?? 'choice';
        $meta  = $question_types[$qtype] ?? ['label'=>$qtype,'icon'=>'bi-question'];
      ?>
      <tr class="hover:bg-slate-50/50 transition-colors">
        <td class="px-5 py-3 text-center text-slate-400"><?=$i+1?></td>
        <td class="px-5 py-3">
          <div class="font-medium text-slate-700 text-xs"><?=htmlspecialchars(mb_substr($q['question_text'],0,100),ENT_QUOTES,'UTF-8')?></div>
          <?php if ($qtype === 'choice'): ?>
          <div class="flex flex-wrap gap-1 mt-1">
            <?php for ($n=1;$n<=5;$n++): if ($n===5 && empty($q['choice5'])) continue; ?>
            <span class="px-2 py-0.5 <?=$q['correct_answer']==$n?'bg-emerald-100 text-emerald-700 font-black':'bg-slate-100 text-slate-400'?> text-xs rounded-full">
              <?=$n?>. <?=htmlspecialchars(mb_substr($q["choice{$n}"],0,20),ENT_QUOTES,'UTF-8')?>
            </span>
            <?php endfor; ?>
          </div>
          <?php elseif ($qtype === 'true_false'): ?>
          <div class="mt-1 text-xs text-emerald-600 font-bold">เฉลย: <?=$q['correct_answer']==1?'ถูก':'ผิด'?></div>
          <?php endif; ?>
        </td>
        <td class="px-5 py-3 text-center">
          <span class="px-2.5 py-1 bg-amber-50 text-amber-600 text-xs font-black rounded-full"><i class="<?=htmlspecialchars($meta['icon'],ENT_QUOTES,'UTF-8')?> mr-1"></i><?=htmlspecialchars($meta['label'],ENT_QUOTES,'UTF-8')?></span>
        </td>
        <td class="px-5 py-3">
          <div class="flex gap-2 justify-center">
            <button onclick="openEdit(<?=htmlspecialchars(json_encode($q),ENT_QUOTES,'UTF-8')?>)"
              class="px-2.5 py-1.5 bg-amber-400 text-white text-xs font-bold rounded-lg hover:bg-amber-500 transition-all"><i class="fas fa-edit"></i></button>
            <button onclick="confirmDel('final_exam.php?action=delete&id=<?=$q['id']?>&subject_id=<?=$subject_id?>')"
              class="px-2.5 py-1.5 bg-rose-500 text-white text-xs font-bold rounded-lg hover:bg-rose-600 transition-all"><i class="fas fa-trash"></i></button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php foreach ([['add','เพิ่มข้อสอบปลายภาค'],['edit','แก้ไขข้อสอบ']] as [$mid,$mtitle]): ?>
<div id="<?=$mid?>Modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white z-10">
      <h3 class="font-black text-slate-800"><i class="fas fa-clipboard-list mr-2 text-amber-500"></i><?=$mtitle?></h3>
      <button onclick="closeModal('<?=$mid?>Modal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
      <input type="hidden" name="action" value="<?=$mid?>">
      <input type="hidden" name="subject_id" value="<?=$subject_id?>">
      <?php if ($mid==='edit'): ?>
      <input type="hidden" name="id" id="eq_id">
      <input type="hidden" name="old_qimg" id="eq_qimg">
      <?php endif; ?>
      <div>
        <label class="block text-xs font-black text-slate-500 mb-2">ประเภทคำถาม</label>
        <select name="question_type" id="<?=$mid?>_qtype" onchange="renderQFields('<?=$mid?>')" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none" <?=$mid==='edit'?'disabled':''?>>
          <?php foreach ($question_types as $key => $meta): ?>
          <option value="<?=$key?>"><?=htmlspecialchars($meta['label'],ENT_QUOTES,'UTF-8')?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($mid === 'edit'): ?>
        <input type="hidden" name="question_type" id="<?=$mid?>_qtype_hidden">
        <p class="text-[10px] text-slate-400 mt-1">เปลี่ยนประเภทคำถามหลังสร้างแล้วไม่ได้ — ลบแล้วสร้างใหม่หากต้องการเปลี่ยน</p>
        <?php endif; ?>
      </div>
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1">คำถาม <span class="text-rose-500">*</span></label>
        <textarea name="question_text" id="<?=$mid?>_qtext" rows="3" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none" placeholder="พิมพ์คำถาม..."></textarea>
      </div>
      <div id="<?=$mid?>_fields"></div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('<?=$mid?>Modal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-amber-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-amber-200 hover:bg-amber-700 transition-all"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<div id="importModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <h3 class="font-black text-slate-800"><i class="fas fa-file-import mr-2 text-emerald-500"></i>Import CSV</h3>
      <button onclick="closeModal('importModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-6">
      <input type="hidden" name="action" value="import">
      <input type="hidden" name="subject_id" value="<?=$subject_id?>">
      <input type="file" name="csv_file" accept=".csv" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm mb-4">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('importModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-200 hover:bg-emerald-600 transition-all"><i class="fas fa-upload mr-1"></i>นำเข้า</button>
      </div>
    </form>
  </div>
</div>

<script>
<?=lms_exam_admin_js_helpers()?>

function openAddModal() {
  document.getElementById('add_qtype').value = 'choice';
  document.getElementById('add_qtext').value = '';
  renderQFields('add');
  openModal('addModal');
}

function openEdit(q){
  document.getElementById('eq_id').value=q.id;
  document.getElementById('eq_qimg').value=q.question_img||'';
  document.getElementById('edit_qtext').value=q.question_text;
  const qtype = q.question_type || 'choice';
  document.getElementById('edit_qtype').value = qtype;
  document.getElementById('edit_qtype_hidden').value = qtype;
  renderQFields('edit', q);
  openModal('editModal');
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
