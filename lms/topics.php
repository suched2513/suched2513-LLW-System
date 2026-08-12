<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
require_once __DIR__ . '/_helpers.php';

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);
$unit_id = (int)($_GET['unit_id'] ?? 0);
if (!$unit_id) { header('Location: ' . $base_path . '/lms/units.php'); exit(); }

$unit = lms_get_owned_unit($pdo, $unit_id, $is_admin, $teacher_id);
if (!$unit) { header('Location: ' . $base_path . '/lms/units.php'); exit(); }
$view_trash = isset($_GET['view']) && $_GET['view'] === 'trash';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $order_no   = (int)($_POST['order_no'] ?? 1);
    $topic_name = trim($_POST['topic_name'] ?? '');
    $id         = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'add') {
            if (!$topic_name) throw new Exception('กรุณาระบุชื่อเรื่อง');
            $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
            $pdo->prepare("INSERT INTO lms_topics (unit_id, order_no, topic_name, status) VALUES (?,?,?,?)")->execute([$unit_id, $order_no, $topic_name, $status]);
            $new_id = (int)$pdo->lastInsertId();
            lms_log_activity($pdo, 'create', 'lms_topic', $new_id, null, ['topic_name' => $topic_name, 'status' => $status]);
            header("Location: topic_builder.php?topic_id={$new_id}"); exit();
        } elseif ($action === 'edit') {
            if (!$topic_name) throw new Exception('กรุณาระบุชื่อเรื่อง');
            if (!lms_get_owned_topic($pdo, $id, $is_admin, $teacher_id)) throw new Exception('ไม่มีสิทธิ์แก้ไขเรื่องนี้');
            $pdo->prepare("UPDATE lms_topics SET order_no=?, topic_name=? WHERE id=?")->execute([$order_no, $topic_name, $id]);
            $msg = 'success:แก้ไขสำเร็จ';
        }
    } catch (Exception $e) {
        $msg = 'error:' . $e->getMessage();
    }
    header("Location: topics.php?unit_id={$unit_id}&msg=" . urlencode($msg)); exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_topic') {
    $tid = (int)$_GET['topic_id'];
    if (lms_get_owned_topic($pdo, $tid, $is_admin, $teacher_id)) {
        $pdo->prepare("UPDATE lms_topics SET deleted_at=NOW() WHERE id=? AND unit_id=?")->execute([$tid, $unit_id]);
        lms_log_activity($pdo, 'soft_delete', 'lms_topic', $tid);
    }
    header("Location: topics.php?unit_id={$unit_id}&msg=" . urlencode('success:ลบเรื่องสำเร็จ')); exit();
}
if (isset($_GET['action']) && $_GET['action'] === 'restore_topic') {
    $tid = (int)$_GET['topic_id'];
    if (lms_get_owned_topic($pdo, $tid, $is_admin, $teacher_id)) {
        $pdo->prepare("UPDATE lms_topics SET deleted_at=NULL WHERE id=? AND unit_id=?")->execute([$tid, $unit_id]);
        lms_log_activity($pdo, 'restore', 'lms_topic', $tid);
    }
    header("Location: topics.php?unit_id={$unit_id}&view=trash&msg=" . urlencode('success:กู้คืนเรื่องสำเร็จ')); exit();
}
if (isset($_GET['action']) && $_GET['action'] === 'toggle_publish') {
    $tid = (int)$_GET['topic_id'];
    if (lms_get_owned_topic($pdo, $tid, $is_admin, $teacher_id)) {
        $cur = $pdo->prepare("SELECT status FROM lms_topics WHERE id=?"); $cur->execute([$tid]); $cur = $cur->fetchColumn();
        $new_status = $cur === 'published' ? 'draft' : 'published';
        $pdo->prepare("UPDATE lms_topics SET status=? WHERE id=?")->execute([$new_status, $tid]);
        lms_log_activity($pdo, $new_status === 'published' ? 'publish' : 'unpublish', 'lms_topic', $tid);
    }
    header("Location: topics.php?unit_id={$unit_id}&msg=" . urlencode('success:อัปเดตสถานะสำเร็จ')); exit();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$topics = $pdo->prepare("SELECT * FROM lms_topics WHERE unit_id=? AND deleted_at IS " . ($view_trash ? "NOT NULL" : "NULL") . " ORDER BY order_no");
$topics->execute([$unit_id]); $topics=$topics->fetchAll();
$next_order = empty($topics) ? 1 : max(array_column($topics, 'order_no')) + 1;
$trash_topic_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_topics WHERE unit_id=? AND deleted_at IS NOT NULL");
$trash_topic_count_stmt->execute([$unit_id]);
$trash_topic_count = (int)$trash_topic_count_stmt->fetchColumn();

$pageTitle    = 'จัดการเรื่อง';
$pageSubtitle = htmlspecialchars($unit['unit_name'],ENT_QUOTES,'UTF-8');
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
  <div>
    <a href="<?=$base_path?>/lms/units.php?subject_id=<?=$unit['subject_id']?>" class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 mb-1">
      <i class="fas fa-arrow-left"></i> กลับหน่วยการเรียนรู้
    </a>
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background:linear-gradient(135deg,#F57C00,#FFCA28)">
        <i class="fas fa-book-reader text-white"></i>
      </div>
      <div>
        <h2 class="text-lg font-black text-slate-800"><?=htmlspecialchars($unit['unit_name'],ENT_QUOTES,'UTF-8')?></h2>
        <p class="text-xs text-slate-400">จัดการเรื่องในหน่วยนี้</p>
      </div>
    </div>
  </div>
  <div class="flex gap-2">
    <a href="topics.php?unit_id=<?=$unit_id?><?=$view_trash?'':'&view=trash'?>"
      class="px-4 py-2 <?=$view_trash?'bg-violet-600 text-white':'bg-slate-100 text-slate-600'?> text-xs font-bold rounded-xl hover:opacity-90 transition-all flex items-center gap-2">
      <i class="fas fa-<?=$view_trash?'arrow-left':'trash-restore'?>"></i> <?=$view_trash?'กลับหน้าเรื่อง':'ถังขยะ ('.$trash_topic_count.')'?>
    </a>
    <?php if (!$view_trash): ?>
    <button onclick="openModal('addModal')"
      class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all flex items-center gap-2">
      <i class="fas fa-plus"></i> เพิ่มเรื่อง
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
      <tr>
        <th class="px-5 py-3 text-center w-16">ลำดับ</th>
        <th class="px-5 py-3 text-left">ชื่อเรื่อง</th>
        <th class="px-5 py-3 text-center w-24">สถานะ</th>
        <th class="px-5 py-3 text-center w-28">เนื้อหา</th>
        <th class="px-5 py-3 text-center w-56">จัดการ</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-50">
      <?php if (empty($topics)): ?>
      <tr><td colspan="5" class="py-16 text-center text-slate-300"><i class="fas fa-file-alt text-4xl mb-3 block opacity-30"></i><?=$view_trash?'ถังขยะว่าง':'ยังไม่มีเรื่องในหน่วยนี้'?></td></tr>
      <?php endif; ?>
      <?php foreach ($topics as $t):
        $bc = $pdo->prepare("SELECT COUNT(*) FROM lms_topic_blocks WHERE topic_id=?"); $bc->execute([$t['id']]); $block_count = (int)$bc->fetchColumn();
        $is_published = ($t['status'] ?? 'published') === 'published';
      ?>
      <tr class="hover:bg-slate-50/50 transition-colors">
        <td class="px-5 py-4 text-center">
          <span class="px-2.5 py-1 bg-orange-50 text-orange-600 text-xs font-black rounded-full"><?=$t['order_no']?></span>
        </td>
        <td class="px-5 py-4">
          <div class="font-bold text-slate-800"><?=htmlspecialchars($t['topic_name'],ENT_QUOTES,'UTF-8')?></div>
        </td>
        <td class="px-5 py-4 text-center">
          <?php if ($view_trash): ?>
          <span class="px-2.5 py-1 bg-slate-100 text-slate-400 text-xs font-black rounded-full"><i class="fas fa-trash mr-1"></i>ลบแล้ว</span>
          <?php elseif ($is_published): ?>
          <span class="px-2.5 py-1 bg-emerald-50 text-emerald-600 text-xs font-black rounded-full"><i class="fas fa-check-circle mr-1"></i>เผยแพร่</span>
          <?php else: ?>
          <span class="px-2.5 py-1 bg-amber-50 text-amber-600 text-xs font-black rounded-full"><i class="fas fa-pencil-ruler mr-1"></i>ร่าง</span>
          <?php endif; ?>
        </td>
        <td class="px-5 py-4 text-center">
          <span class="px-2.5 py-1 <?=$block_count?'bg-violet-50 text-violet-700':'bg-slate-100 text-slate-400'?> text-xs font-bold rounded-full"><?=$block_count?> บล็อก</span>
        </td>
        <td class="px-5 py-4">
          <div class="flex gap-2 justify-center">
            <?php if ($view_trash): ?>
            <button onclick="confirmDel('topics.php?unit_id=<?=$unit_id?>&action=restore_topic&topic_id=<?=$t['id']?>','กู้คืนเรื่องนี้?','กู้คืน','#7C3AED')"
              class="px-3 py-1.5 bg-violet-600 text-white text-xs font-bold rounded-lg hover:bg-violet-700 transition-all">
              <i class="fas fa-trash-restore mr-1"></i>กู้คืน
            </button>
            <?php else: ?>
            <a href="topic_builder.php?topic_id=<?=$t['id']?>" title="เนื้อหา (Lesson Builder)"
              class="px-3 py-1.5 bg-violet-600 text-white text-xs font-bold rounded-lg hover:bg-violet-700 transition-all">
              <i class="fas fa-layer-group mr-1"></i>เนื้อหา
            </a>
            <button onclick="loadPreview(<?=$t['id']?>, '<?=addslashes(htmlspecialchars($t['topic_name'],ENT_QUOTES,'UTF-8'))?>')"
              class="px-3 py-1.5 bg-violet-100 text-violet-700 text-xs font-bold rounded-lg hover:bg-violet-200 transition-all" title="ดูตัวอย่าง">
              <i class="fas fa-eye"></i>
            </button>
            <button onclick="location.href='topics.php?unit_id=<?=$unit_id?>&action=toggle_publish&topic_id=<?=$t['id']?>'"
              class="px-3 py-1.5 <?=$is_published?'bg-slate-400 hover:bg-slate-500':'bg-emerald-500 hover:bg-emerald-600'?> text-white text-xs font-bold rounded-lg transition-all"
              title="<?=$is_published?'ยกเลิกการเผยแพร่':'เผยแพร่'?>">
              <i class="fas fa-<?=$is_published?'eye-slash':'upload'?>"></i>
            </button>
            <button onclick="openEditModal(<?=$t['id']?>, <?=$t['order_no']?>, '<?=addslashes(htmlspecialchars($t['topic_name'],ENT_QUOTES,'UTF-8'))?>')"
              class="px-3 py-1.5 bg-amber-400 text-white text-xs font-bold rounded-lg hover:bg-amber-500 transition-all" title="เปลี่ยนชื่อ/ลำดับ">
              <i class="fas fa-edit"></i>
            </button>
            <button onclick="confirmDel('topics.php?unit_id=<?=$unit_id?>&action=delete_topic&topic_id=<?=$t['id']?>','ลบเรื่องนี้?')"
              class="px-3 py-1.5 bg-rose-500 text-white text-xs font-bold rounded-lg hover:bg-rose-600 transition-all">
              <i class="fas fa-trash"></i>
            </button>
            <?php endif; ?>
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
      <h3 class="font-black text-slate-800"><i class="fas fa-plus-circle mr-2 text-orange-500"></i>เพิ่มเรื่อง</h3>
      <button onclick="closeModal('addModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="add">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-black text-slate-500 mb-1">ลำดับ</label>
          <input type="number" name="order_no" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" value="<?=$next_order?>" min="1">
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-black text-slate-500 mb-1">ชื่อเรื่อง <span class="text-rose-500">*</span></label>
          <input type="text" name="topic_name" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" required>
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-black text-slate-500 mb-2">สถานะ</label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="status" value="published" class="accent-emerald-600" checked>
              <span class="text-sm font-bold text-emerald-700">เผยแพร่ทันที</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="status" value="draft" class="accent-amber-600">
              <span class="text-sm font-bold text-amber-700">บันทึกเป็นร่าง</span>
            </label>
          </div>
        </div>
      </div>
      <p class="text-xs text-slate-400">หลังบันทึกจะพาไปหน้าตัวสร้างเนื้อหา (Lesson Builder) เพื่อเพิ่มบล็อกเนื้อหาต่อทันที</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all"><i class="fas fa-save mr-1"></i>บันทึก &amp; ไปต่อ</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal (rename / reorder only — content is managed in Lesson Builder) -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <h3 class="font-black text-slate-800"><i class="fas fa-edit mr-2 text-amber-500"></i>แก้ไขเรื่อง</h3>
      <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e_tid">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-black text-slate-500 mb-1">ลำดับ</label>
          <input type="number" name="order_no" id="e_order" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" min="1">
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-black text-slate-500 mb-1">ชื่อเรื่อง</label>
          <input type="text" name="topic_name" id="e_tname" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" required>
        </div>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-amber-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-amber-200 hover:bg-amber-600 transition-all"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) { const el=document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id) { const el=document.getElementById(id); el.classList.add('hidden'); el.classList.remove('flex'); }

function openEditModal(id, order, name) {
  document.getElementById('e_tid').value   = id;
  document.getElementById('e_order').value = order;
  document.getElementById('e_tname').value = name;
  openModal('editModal');
}

function confirmDel(url, msg, okText, okColor) {
  Swal.fire({
    icon:'warning', title:msg,
    showCancelButton:true,
    confirmButtonColor: okColor || '#ef4444',
    cancelButtonText:'ยกเลิก',
    confirmButtonText: okText || 'ลบ',
  }).then(r=>{if(r.isConfirmed)location.href=url;});
}

async function loadPreview(tid, name) {
  Swal.fire({ title: 'กำลังโหลด...', didOpen: () => Swal.showLoading() });
  const data = await fetch(`topic_builder.php?topic_id=${tid}&ajax=preview`).then(r=>r.json());
  Swal.fire({
    title: `<span class="text-base font-black">${name}</span>`,
    html: `<div class="overflow-y-auto max-h-[60vh] text-left">${data.html}</div>`,
    confirmButtonColor: '#7C3AED',
    confirmButtonText: 'ปิด',
    width: '640px',
  });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
