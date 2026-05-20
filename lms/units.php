<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo = getPdo();
$msg = '';

$subject_id = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
if (!$subject_id) { header('Location: subjects.php'); exit(); }
$subject_stmt = $pdo->prepare("SELECT * FROM lms_subjects WHERE id=?"); $subject_stmt->execute([$subject_id]); $subject = $subject_stmt->fetch();
if (!$subject) { header('Location: subjects.php'); exit(); }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $order_no  = (int)($_POST['order_no'] ?? 1);
    $unit_name = trim($_POST['unit_name'] ?? '');
    $id        = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'add') {
            if (!$unit_name) throw new Exception('กรุณาระบุชื่อหน่วย');
            $pdo->prepare("INSERT INTO lms_units (subject_id, order_no, unit_name) VALUES (?,?,?)")
                ->execute([$subject_id, $order_no, $unit_name]);
            $new_id = $pdo->lastInsertId();
            foreach ($_POST['exercises'] ?? [] as $ex) {
                $ex = trim($ex);
                if ($ex) $pdo->prepare("INSERT INTO lms_unit_exercises (unit_id, exercise_title) VALUES (?,?)")->execute([$new_id, $ex]);
            }
            $msg = 'success:เพิ่มหน่วยการเรียนรู้สำเร็จ';
        } elseif ($action === 'edit') {
            if (!$unit_name) throw new Exception('กรุณาระบุชื่อหน่วย');
            $pdo->prepare("UPDATE lms_units SET order_no=?, unit_name=? WHERE id=?")->execute([$order_no, $unit_name, $id]);
            // Update existing exercises
            $existing_ids = [];
            foreach ($_POST['exercises'] ?? [] as $ex_id => $title) {
                $ex_id = (int)$ex_id; $title = trim($title);
                if ($title && $ex_id > 0) {
                    $existing_ids[] = $ex_id;
                    $pdo->prepare("UPDATE lms_unit_exercises SET exercise_title=? WHERE id=? AND unit_id=?")->execute([$title, $ex_id, $id]);
                }
            }
            if (!empty($existing_ids)) {
                $pl = implode(',', array_fill(0, count($existing_ids), '?'));
                $pdo->prepare("DELETE FROM lms_unit_exercises WHERE unit_id=? AND id NOT IN ($pl)")->execute(array_merge([$id], $existing_ids));
            } else {
                $pdo->prepare("DELETE FROM lms_unit_exercises WHERE unit_id=?")->execute([$id]);
            }
            foreach ($_POST['new_exercises'] ?? [] as $title) {
                $title = trim($title);
                if ($title) $pdo->prepare("INSERT INTO lms_unit_exercises (unit_id, exercise_title) VALUES (?,?)")->execute([$id, $title]);
            }
            $msg = 'success:แก้ไขสำเร็จ';
        }
    } catch (Exception $e) {
        $msg = 'error:' . $e->getMessage();
    }
    header('Location: units.php?subject_id=' . $subject_id . '&msg=' . urlencode($msg)); exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM lms_units WHERE id=? AND subject_id=?")->execute([$id, $subject_id]);
    header('Location: units.php?subject_id=' . $subject_id . '&msg=' . urlencode('success:ลบหน่วยการเรียนรู้สำเร็จ')); exit();
}
if (isset($_GET['ajax']) && $_GET['ajax'] === 'exercises') {
    header('Content-Type: application/json');
    $uid  = (int)($_GET['unit_id'] ?? 0);
    $rows = $pdo->prepare("SELECT * FROM lms_unit_exercises WHERE unit_id=? ORDER BY id");
    $rows->execute([$uid]);
    echo json_encode($rows->fetchAll()); exit();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$units_stmt = $pdo->prepare("
    SELECT u.*, COUNT(DISTINCT t.id) topic_count
    FROM lms_units u
    LEFT JOIN lms_topics t ON t.unit_id = u.id
    WHERE u.subject_id=?
    GROUP BY u.id ORDER BY u.order_no
");
$units_stmt->execute([$subject_id]);
$units = $units_stmt->fetchAll();

$pageTitle    = 'หน่วยการเรียนรู้';
$pageSubtitle = htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8');
$activeSystem = 'lms';
require_once __DIR__ . '/../components/layout_start.php';
?>

<?php if ($msg): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const p = '<?=addslashes($msg)?>'.split(':');
    Swal.fire({ icon: p[0]==='success'?'success':'error', title: p[0]==='success'?'สำเร็จ':'ผิดพลาด', text: p[1], confirmButtonColor:'#7C3AED', timer: p[0]==='success'?2000:undefined, showConfirmButton: p[0]!=='success' });
});
</script>
<?php endif; ?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background:linear-gradient(135deg,#F57C00,#FFCA28)">
      <i class="fas fa-book-open text-white"></i>
    </div>
    <div>
      <a href="<?=$base_path?>/lms/subjects.php" class="text-xs text-violet-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i>วิชาทั้งหมด</a>
      <h2 class="text-lg font-black text-slate-800"><?=htmlspecialchars($subject['subject_name'],ENT_QUOTES,'UTF-8')?></h2>
      <p class="text-xs text-slate-400">หน่วยการเรียนรู้และแบบฝึกหัด</p>
    </div>
  </div>
  <button onclick="openModal('addModal')"
    class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all flex items-center gap-2">
    <i class="fas fa-plus"></i> เพิ่มหน่วย
  </button>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
      <tr>
        <th class="px-5 py-3 text-center w-16">ลำดับ</th>
        <th class="px-5 py-3 text-left">ชื่อหน่วยการเรียนรู้</th>
        <th class="px-5 py-3 text-center w-28">จำนวนเรื่อง</th>
        <th class="px-5 py-3 text-center w-44">จัดการ</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-50">
      <?php if (empty($units)): ?>
      <tr><td colspan="4" class="py-16 text-center text-slate-300"><i class="fas fa-book text-4xl mb-3 block opacity-30"></i>ยังไม่มีหน่วยการเรียนรู้</td></tr>
      <?php endif; ?>
      <?php foreach ($units as $u):
        $exs = $pdo->prepare("SELECT * FROM lms_unit_exercises WHERE unit_id=? ORDER BY id");
        $exs->execute([$u['id']]); $exs = $exs->fetchAll();
      ?>
      <tr class="hover:bg-slate-50/50 transition-colors">
        <td class="px-5 py-4 text-center">
          <span class="px-2.5 py-1 bg-orange-50 text-orange-600 text-xs font-black rounded-full"><?=$u['order_no']?></span>
        </td>
        <td class="px-5 py-4">
          <div class="font-bold text-slate-800"><?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?></div>
          <div class="flex flex-wrap gap-1 mt-1.5">
            <?php foreach ($exs as $ex): ?>
            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-xs font-bold rounded-full"><i class="fas fa-tasks mr-1"></i><?=htmlspecialchars($ex['exercise_title'],ENT_QUOTES,'UTF-8')?></span>
            <?php endforeach; ?>
          </div>
        </td>
        <td class="px-5 py-4 text-center">
          <span class="px-2.5 py-1 bg-blue-50 text-blue-600 text-xs font-bold rounded-full"><?=$u['topic_count']?> เรื่อง</span>
        </td>
        <td class="px-5 py-4">
          <div class="flex gap-2 justify-center">
            <a href="<?=$base_path?>/lms/topics.php?unit_id=<?=$u['id']?>&subject_id=<?=$subject_id?>"
               class="px-3 py-1.5 bg-teal-500 text-white text-xs font-bold rounded-lg hover:bg-teal-600 transition-all">
              <i class="fas fa-folder-open"></i> เรื่อง
            </a>
            <button onclick="openEditModal(<?=$u['id']?>, <?=$u['order_no']?>, '<?=addslashes(htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8'))?>')"
              class="px-3 py-1.5 bg-amber-400 text-white text-xs font-bold rounded-lg hover:bg-amber-500 transition-all">
              <i class="fas fa-edit"></i>
            </button>
            <button onclick="confirmDel('units.php?action=delete&id=<?=$u['id']?>&subject_id=<?=$subject_id?>','ลบหน่วยนี้?')"
              class="px-3 py-1.5 bg-rose-500 text-white text-xs font-bold rounded-lg hover:bg-rose-600 transition-all">
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
<div id="addModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <h3 class="font-black text-slate-800"><i class="fas fa-plus-circle mr-2 text-orange-500"></i>เพิ่มหน่วยการเรียนรู้</h3>
      <button onclick="closeModal('addModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="subject_id" value="<?=$subject_id?>">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-black text-slate-500 mb-1">ลำดับ</label>
          <input type="number" name="order_no" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" value="1" min="1" required>
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-black text-slate-500 mb-1">ชื่อหน่วยการเรียนรู้ <span class="text-rose-500">*</span></label>
          <input type="text" name="unit_name" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" placeholder="เช่น หน่วยที่ 1: พื้นฐานคอมพิวเตอร์" required>
        </div>
      </div>
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs font-black text-slate-500">แบบฝึกหัด</label>
          <button type="button" onclick="addExRow('add_exs')" class="px-3 py-1 bg-teal-500 text-white text-xs font-bold rounded-lg hover:bg-teal-600 transition-all">
            <i class="fas fa-plus"></i> เพิ่ม
          </button>
        </div>
        <div id="add_exs" class="space-y-2"></div>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <h3 class="font-black text-slate-800"><i class="fas fa-edit mr-2 text-amber-500"></i>แก้ไขหน่วยการเรียนรู้</h3>
      <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e_uid">
      <input type="hidden" name="subject_id" value="<?=$subject_id?>">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-black text-slate-500 mb-1">ลำดับ</label>
          <input type="number" name="order_no" id="e_order" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" min="1" required>
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-black text-slate-500 mb-1">ชื่อหน่วยการเรียนรู้</label>
          <input type="text" name="unit_name" id="e_uname" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" required>
        </div>
      </div>
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs font-black text-slate-500">แบบฝึกหัด</label>
          <button type="button" onclick="addNewExRow('edit_exs')" class="px-3 py-1 bg-teal-500 text-white text-xs font-bold rounded-lg hover:bg-teal-600 transition-all">
            <i class="fas fa-plus"></i> เพิ่ม
          </button>
        </div>
        <div id="edit_exs" class="space-y-2"></div>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-amber-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-amber-200 hover:bg-amber-600 transition-all"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) { const el = document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id) { const el = document.getElementById(id); el.classList.add('hidden'); el.classList.remove('flex'); }

function addExRow(cid) {
  const c = document.getElementById(cid);
  const d = document.createElement('div'); d.className='flex gap-2';
  d.innerHTML = `<input type="text" name="exercises[]" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-violet-400 outline-none" placeholder="ชื่อแบบฝึกหัด...">
    <button type="button" onclick="this.parentElement.remove()" class="px-2.5 py-1.5 bg-rose-100 text-rose-500 text-xs rounded-lg hover:bg-rose-200 transition-all"><i class="fas fa-times"></i></button>`;
  c.appendChild(d);
}
function addNewExRow(cid) {
  const c = document.getElementById(cid);
  const d = document.createElement('div'); d.className='flex gap-2';
  d.innerHTML = `<input type="text" name="new_exercises[]" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-violet-400 outline-none" placeholder="ชื่อแบบฝึกหัดใหม่...">
    <button type="button" onclick="this.parentElement.remove()" class="px-2.5 py-1.5 bg-rose-100 text-rose-500 text-xs rounded-lg hover:bg-rose-200 transition-all"><i class="fas fa-times"></i></button>`;
  c.appendChild(d);
}

async function openEditModal(id, order, name) {
  document.getElementById('e_uid').value = id;
  document.getElementById('e_order').value = order;
  document.getElementById('e_uname').value = name;
  const c = document.getElementById('edit_exs');
  c.innerHTML = '<p class="text-xs text-slate-400 p-2">กำลังโหลด...</p>';
  openModal('editModal');
  const data = await fetch('units.php?ajax=exercises&unit_id=' + id + '&subject_id=<?=$subject_id?>').then(r => r.json());
  c.innerHTML = '';
  data.forEach(ex => {
    const d = document.createElement('div'); d.className='flex gap-2';
    d.innerHTML = `<input type="text" name="exercises[${ex.id}]" value="${ex.exercise_title.replace(/"/g,'&quot;')}" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-violet-400 outline-none">
      <button type="button" onclick="this.parentElement.remove()" class="px-2.5 py-1.5 bg-rose-100 text-rose-500 text-xs rounded-lg hover:bg-rose-200 transition-all"><i class="fas fa-times"></i></button>`;
    c.appendChild(d);
  });
}

function confirmDel(url, msg) {
  Swal.fire({ icon:'warning', title: msg, text:'ข้อมูลที่เกี่ยวข้องจะถูกลบด้วย', showCancelButton:true, confirmButtonColor:'#ef4444', cancelButtonText:'ยกเลิก', confirmButtonText:'ลบ' })
    .then(r => { if (r.isConfirmed) location.href = url; });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
