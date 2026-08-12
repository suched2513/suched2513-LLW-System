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
$view_trash = isset($_GET['view']) && $_GET['view'] === 'trash';

function saveExRows(PDO $pdo, int $unit_id, array $titles, array $descs, array $scores, array $dues, array $remedials = []): void {
    foreach ($titles as $i => $title) {
        $title = trim($title);
        if (!$title) continue;
        $desc      = trim($descs[$i]  ?? '') ?: null;
        $score     = ($scores[$i] ?? '') !== '' ? max(1, (int)$scores[$i]) : null;
        $due       = !empty($dues[$i]) ? $dues[$i] : null;
        $remedial  = !empty($remedials[$i]) ? 1 : 0;
        $pdo->prepare("INSERT INTO lms_unit_exercises (unit_id, exercise_title, description, max_score, due_date, is_remedial) VALUES (?,?,?,?,?,?)")
            ->execute([$unit_id, $title, $desc, $score, $due, $remedial]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $order_no  = (int)($_POST['order_no'] ?? 1);
    $unit_name = trim($_POST['unit_name'] ?? '');
    $id        = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'add') {
            if (!$unit_name) throw new Exception('กรุณาระบุชื่อหน่วย');
            $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
            $pdo->prepare("INSERT INTO lms_units (subject_id, order_no, unit_number, unit_name, status) VALUES (?,?,?,?,?)")
                ->execute([$subject_id, $order_no, $order_no, $unit_name, $status]);
            $new_id = (int)$pdo->lastInsertId();
            lms_log_activity($pdo, 'create', 'lms_unit', $new_id, null, ['unit_name' => $unit_name, 'status' => $status]);
            saveExRows($pdo, $new_id,
                $_POST['exercises']    ?? [],
                $_POST['descriptions'] ?? [],
                $_POST['max_scores']   ?? [],
                $_POST['due_dates']    ?? [],
                $_POST['remedials']    ?? []
            );
            $msg = 'success:เพิ่มหน่วยการเรียนรู้สำเร็จ';
        } elseif ($action === 'edit') {
            if (!$unit_name) throw new Exception('กรุณาระบุชื่อหน่วย');
            if (!lms_get_owned_unit($pdo, $id, $is_admin, $teacher_id)) throw new Exception('ไม่มีสิทธิ์แก้ไขหน่วยนี้');
            $pdo->prepare("UPDATE lms_units SET order_no=?, unit_number=?, unit_name=? WHERE id=?")->execute([$order_no, $order_no, $unit_name, $id]);
            $existing_ids = [];
            foreach ($_POST['exercises'] ?? [] as $ex_id => $title) {
                $ex_id = (int)$ex_id; $title = trim($title);
                if ($title && $ex_id > 0) {
                    $existing_ids[] = $ex_id;
                    $desc     = trim($_POST['descriptions'][$ex_id]  ?? '') ?: null;
                    $score    = ($_POST['max_scores'][$ex_id] ?? '') !== '' ? max(1, (int)$_POST['max_scores'][$ex_id]) : null;
                    $due      = !empty($_POST['due_dates'][$ex_id]) ? $_POST['due_dates'][$ex_id] : null;
                    $remedial = !empty($_POST['remedials'][$ex_id]) ? 1 : 0;
                    $pdo->prepare("UPDATE lms_unit_exercises SET exercise_title=?, description=?, max_score=?, due_date=?, is_remedial=? WHERE id=? AND unit_id=?")
                        ->execute([$title, $desc, $score, $due, $remedial, $ex_id, $id]);
                }
            }
            if (!empty($existing_ids)) {
                $pl = implode(',', array_fill(0, count($existing_ids), '?'));
                $pdo->prepare("DELETE FROM lms_unit_exercises WHERE unit_id=? AND id NOT IN ($pl)")->execute(array_merge([$id], $existing_ids));
            } else {
                $pdo->prepare("DELETE FROM lms_unit_exercises WHERE unit_id=?")->execute([$id]);
            }
            saveExRows($pdo, $id,
                $_POST['new_exercises']    ?? [],
                $_POST['new_descriptions'] ?? [],
                $_POST['new_max_scores']   ?? [],
                $_POST['new_due_dates']    ?? [],
                $_POST['new_remedials']    ?? []
            );
            $msg = 'success:แก้ไขสำเร็จ';
        }
    } catch (Exception $e) {
        $msg = 'error:' . $e->getMessage();
    }
    header('Location: units.php?subject_id=' . $subject_id . '&msg=' . urlencode($msg)); exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id'];
    if (lms_get_owned_unit($pdo, $id, $is_admin, $teacher_id)) {
        $pdo->prepare("UPDATE lms_units SET deleted_at=NOW() WHERE id=? AND subject_id=?")->execute([$id, $subject_id]);
        lms_log_activity($pdo, 'soft_delete', 'lms_unit', $id);
    }
    header('Location: units.php?subject_id=' . $subject_id . '&msg=' . urlencode('success:ลบหน่วยการเรียนรู้สำเร็จ')); exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'restore') {
    $id = (int)$_GET['id'];
    if (lms_get_owned_unit($pdo, $id, $is_admin, $teacher_id)) {
        $pdo->prepare("UPDATE lms_units SET deleted_at=NULL WHERE id=? AND subject_id=?")->execute([$id, $subject_id]);
        lms_log_activity($pdo, 'restore', 'lms_unit', $id);
    }
    header('Location: units.php?subject_id=' . $subject_id . '&view=trash&msg=' . urlencode('success:กู้คืนหน่วยการเรียนรู้สำเร็จ')); exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'toggle_publish') {
    $id = (int)$_GET['id'];
    if (lms_get_owned_unit($pdo, $id, $is_admin, $teacher_id)) {
        $cur = $pdo->prepare("SELECT status FROM lms_units WHERE id=?"); $cur->execute([$id]); $cur = $cur->fetchColumn();
        $new_status = $cur === 'published' ? 'draft' : 'published';
        $pdo->prepare("UPDATE lms_units SET status=? WHERE id=?")->execute([$new_status, $id]);
        lms_log_activity($pdo, $new_status === 'published' ? 'publish' : 'unpublish', 'lms_unit', $id);
    }
    header('Location: units.php?subject_id=' . $subject_id . '&msg=' . urlencode('success:อัปเดตสถานะสำเร็จ')); exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'exercises') {
    header('Content-Type: application/json');
    $uid  = (int)($_GET['unit_id'] ?? 0);
    $rows = $pdo->prepare("SELECT id, exercise_title, description, max_score, due_date, is_remedial FROM lms_unit_exercises WHERE unit_id=? AND deleted_at IS NULL ORDER BY id");
    $rows->execute([$uid]);
    echo json_encode($rows->fetchAll()); exit();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$trash_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM lms_units WHERE subject_id=? AND deleted_at IS NOT NULL");
$trash_count_stmt->execute([$subject_id]);
$trash_count = (int)$trash_count_stmt->fetchColumn();

$units_stmt = $pdo->prepare("
    SELECT u.*, COUNT(DISTINCT t.id) topic_count
    FROM lms_units u
    LEFT JOIN lms_topics t ON t.unit_id = u.id AND t.deleted_at IS NULL
    WHERE u.subject_id=? AND u.deleted_at IS " . ($view_trash ? "NOT NULL" : "NULL") . "
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
      <p class="text-xs text-slate-400"><?=$view_trash?'กำลังดูถังขยะ':'หน่วยการเรียนรู้และแบบฝึกหัด'?></p>
    </div>
  </div>
  <div class="flex gap-2">
    <a href="units.php?subject_id=<?=$subject_id?><?=$view_trash?'':'&view=trash'?>"
      class="px-4 py-2 <?=$view_trash?'bg-violet-600 text-white':'bg-slate-100 text-slate-600'?> text-xs font-bold rounded-xl hover:opacity-90 transition-all flex items-center gap-2">
      <i class="fas fa-<?=$view_trash?'arrow-left':'trash-restore'?>"></i> <?=$view_trash?'กลับหน้าหน่วยการเรียนรู้':'ถังขยะ ('.$trash_count.')'?>
    </a>
    <?php if (!$view_trash): ?>
    <button onclick="openModal('addModal')"
      class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all flex items-center gap-2">
      <i class="fas fa-plus"></i> เพิ่มหน่วย
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
      <tr>
        <th class="px-5 py-3 text-center w-16">ลำดับ</th>
        <th class="px-5 py-3 text-left">ชื่อหน่วยการเรียนรู้</th>
        <th class="px-5 py-3 text-center w-24">สถานะ</th>
        <th class="px-5 py-3 text-center w-28">จำนวนเรื่อง</th>
        <th class="px-5 py-3 text-center" style="min-width:22rem">จัดการ</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-50">
      <?php if (empty($units)): ?>
      <tr><td colspan="5" class="py-16 text-center text-slate-300"><i class="fas fa-book text-4xl mb-3 block opacity-30"></i><?=$view_trash?'ถังขยะว่าง':'ยังไม่มีหน่วยการเรียนรู้'?></td></tr>
      <?php endif; ?>
      <?php foreach ($units as $u):
        $exs = $pdo->prepare("SELECT id, exercise_title, max_score, due_date, is_remedial FROM lms_unit_exercises WHERE unit_id=? AND deleted_at IS NULL ORDER BY id");
        $exs->execute([$u['id']]); $exs = $exs->fetchAll();
        $is_published = ($u['status'] ?? 'published') === 'published';
        $pqc = $pdo->prepare("SELECT COUNT(*) FROM lms_pre_questions WHERE unit_id=?"); $pqc->execute([$u['id']]); $pre_q_count = (int)$pqc->fetchColumn();
        $poqc = $pdo->prepare("SELECT COUNT(*) FROM lms_post_questions WHERE unit_id=?"); $poqc->execute([$u['id']]); $post_q_count = (int)$poqc->fetchColumn();
      ?>
      <tr class="hover:bg-slate-50/50 transition-colors">
        <td class="px-5 py-4 text-center">
          <span class="px-2.5 py-1 bg-orange-50 text-orange-600 text-xs font-black rounded-full"><?=$u['order_no']?></span>
        </td>
        <td class="px-5 py-4">
          <div class="font-bold text-slate-800"><?=htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8')?></div>
          <div class="flex flex-wrap gap-1 mt-1.5">
            <?php foreach ($exs as $ex): ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 <?=$ex['is_remedial']?'bg-orange-50 text-orange-600':'bg-emerald-50 text-emerald-600'?> text-xs font-bold rounded-full">
              <i class="fas fa-<?=$ex['is_remedial']?'life-ring':'tasks'?> text-[10px]"></i><?=htmlspecialchars($ex['exercise_title'],ENT_QUOTES,'UTF-8')?>
              <?php if ($ex['is_remedial']): ?><span class="bg-orange-100 text-orange-700 px-1.5 rounded-full">ซ่อมเสริม</span><?php endif; ?>
              <?php if ($ex['max_score']): ?><span class="bg-amber-100 text-amber-600 px-1.5 rounded-full"><?=$ex['max_score']?>pts</span><?php endif; ?>
              <?php if ($ex['due_date']): ?><span class="bg-rose-50 text-rose-400 px-1 rounded-full" title="<?=htmlspecialchars(date('d/m/Y H:i',strtotime($ex['due_date'])),ENT_QUOTES,'UTF-8')?>"><i class="fas fa-clock text-[9px]"></i></span><?php endif; ?>
            </span>
            <?php endforeach; ?>
          </div>
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
          <span class="px-2.5 py-1 bg-blue-50 text-blue-600 text-xs font-bold rounded-full"><?=$u['topic_count']?> เรื่อง</span>
        </td>
        <td class="px-5 py-4">
          <div class="flex gap-2 justify-center flex-wrap">
            <?php if ($view_trash): ?>
            <button onclick="confirmDel('units.php?action=restore&id=<?=$u['id']?>&subject_id=<?=$subject_id?>','กู้คืนหน่วยนี้?','กู้คืน','#7C3AED')"
              class="px-3 py-1.5 bg-violet-600 text-white text-xs font-bold rounded-lg hover:bg-violet-700 transition-all">
              <i class="fas fa-trash-restore mr-1"></i>กู้คืน
            </button>
            <?php else: ?>
            <a href="<?=$base_path?>/lms/topics.php?unit_id=<?=$u['id']?>&subject_id=<?=$subject_id?>"
               class="px-3 py-1.5 bg-teal-500 text-white text-xs font-bold rounded-lg hover:bg-teal-600 transition-all">
              <i class="fas fa-folder-open"></i> เรื่อง
            </a>
            <a href="<?=$base_path?>/lms/pre_exam.php?unit_id=<?=$u['id']?>"
               class="px-3 py-1.5 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg hover:bg-blue-100 transition-all" title="ข้อสอบก่อนเรียนของหน่วยนี้">
              <i class="fas fa-clipboard-list"></i> ก่อนเรียน<?=$pre_q_count?' ('.$pre_q_count.')':''?>
            </a>
            <a href="<?=$base_path?>/lms/post_exam.php?unit_id=<?=$u['id']?>"
               class="px-3 py-1.5 bg-rose-50 text-rose-700 text-xs font-bold rounded-lg hover:bg-rose-100 transition-all" title="ข้อสอบหลังเรียนของหน่วยนี้">
              <i class="fas fa-clipboard-check"></i> หลังเรียน<?=$post_q_count?' ('.$post_q_count.')':''?>
            </a>
            <a href="<?=$base_path?>/lms/exam_settings.php?unit_id=<?=$u['id']?>"
               class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-lg hover:bg-slate-200 transition-all" title="ตั้งค่าเกณฑ์ผ่าน/จำนวนครั้ง">
              <i class="fas fa-cog"></i>
            </a>
            <button onclick="location.href='units.php?action=toggle_publish&id=<?=$u['id']?>&subject_id=<?=$subject_id?>'"
              class="px-3 py-1.5 <?=$is_published?'bg-slate-400 hover:bg-slate-500':'bg-emerald-500 hover:bg-emerald-600'?> text-white text-xs font-bold rounded-lg transition-all"
              title="<?=$is_published?'ยกเลิกการเผยแพร่':'เผยแพร่'?>">
              <i class="fas fa-<?=$is_published?'eye-slash':'upload'?>"></i>
            </button>
            <button onclick="openEditModal(<?=$u['id']?>, <?=$u['order_no']?>, '<?=addslashes(htmlspecialchars($u['unit_name'],ENT_QUOTES,'UTF-8'))?>')"
              class="px-3 py-1.5 bg-amber-400 text-white text-xs font-bold rounded-lg hover:bg-amber-500 transition-all">
              <i class="fas fa-edit"></i>
            </button>
            <button onclick="confirmDel('units.php?action=delete&id=<?=$u['id']?>&subject_id=<?=$subject_id?>','ลบหน่วยนี้?')"
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
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 flex-shrink-0">
      <h3 class="font-black text-slate-800"><i class="fas fa-plus-circle mr-2 text-orange-500"></i>เพิ่มหน่วยการเรียนรู้</h3>
      <button onclick="closeModal('addModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4 overflow-y-auto flex-1">
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
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs font-black text-slate-500"><i class="fas fa-tasks mr-1 text-emerald-500"></i>แบบฝึกหัด / ชิ้นงาน</label>
          <button type="button" onclick="addExRow('add_exs')" class="px-3 py-1 bg-emerald-500 text-white text-xs font-bold rounded-lg hover:bg-emerald-600 transition-all">
            <i class="fas fa-plus"></i> เพิ่ม
          </button>
        </div>
        <div id="add_exs" class="space-y-2"></div>
      </div>
      <div class="flex justify-end gap-3 pt-2 flex-shrink-0">
        <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 flex-shrink-0">
      <h3 class="font-black text-slate-800"><i class="fas fa-edit mr-2 text-amber-500"></i>แก้ไขหน่วยการเรียนรู้</h3>
      <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4 overflow-y-auto flex-1">
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
          <label class="text-xs font-black text-slate-500"><i class="fas fa-tasks mr-1 text-emerald-500"></i>แบบฝึกหัด / ชิ้นงาน</label>
          <button type="button" onclick="addNewExRow('edit_exs')" class="px-3 py-1 bg-emerald-500 text-white text-xs font-bold rounded-lg hover:bg-emerald-600 transition-all">
            <i class="fas fa-plus"></i> เพิ่ม
          </button>
        </div>
        <div id="edit_exs" class="space-y-2"></div>
      </div>
      <div class="flex justify-end gap-3 pt-2 flex-shrink-0">
        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-amber-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-amber-200 hover:bg-amber-600 transition-all"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<script>
let exC  = 0; // counter for add modal new rows
let eExC = 0; // counter for edit modal new rows

function escH(s) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(s || ''));
  return d.innerHTML;
}

function buildExRow(idx, isNew, title, desc, score, due, remedial) {
  const p       = isNew ? 'new_' : '';
  const hasEx   = !!(desc || score || due || remedial);
  const dueDisp = due ? due.replace('T',' ').substring(0,16) : '';
  return `<div class="exercise-row rounded-xl border border-slate-200 overflow-hidden">
    <div class="flex items-center gap-2 px-3 py-2.5 bg-slate-50">
      <input type="text" name="${p}exercises[${idx}]" value="${escH(title)}"
        class="flex-1 text-xs border border-slate-200 rounded-lg px-3 py-1.5 bg-white focus:ring-2 focus:ring-violet-400 outline-none"
        placeholder="ชื่อชิ้นงาน / แบบฝึกหัด..." required>
      <button type="button" onclick="toggleExExtra(this)" title="ตั้งค่าเพิ่มเติม"
        class="w-7 h-7 flex-shrink-0 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:text-violet-600 bg-white hover:bg-violet-50 transition-all">
        <i class="fas fa-cog text-xs"></i>
      </button>
      <button type="button" onclick="this.closest('.exercise-row').remove()"
        class="w-7 h-7 flex-shrink-0 flex items-center justify-center rounded-lg bg-rose-50 text-rose-400 hover:bg-rose-100 transition-all">
        <i class="fas fa-times text-xs"></i>
      </button>
    </div>
    <div class="ex-extra ${hasEx ? '' : 'hidden'} px-3 pb-3 border-t border-slate-100 space-y-2 bg-white">
      <div class="mt-2">
        <label class="text-[10px] font-black text-slate-400 mb-1 block"><i class="fas fa-align-left mr-1"></i>คำอธิบาย / สิ่งที่ต้องทำ</label>
        <textarea name="${p}descriptions[${idx}]" rows="2"
          class="w-full text-xs border border-slate-200 rounded-xl px-3 py-2 resize-none focus:ring-2 focus:ring-violet-400 outline-none"
          placeholder="เช่น ให้นักเรียนถ่ายรูปชิ้นงานแล้วส่ง...">${escH(desc)}</textarea>
      </div>
      <div class="flex gap-2">
        <div class="flex-1">
          <label class="text-[10px] font-black text-slate-400 mb-1 block"><i class="fas fa-star mr-1 text-amber-400"></i>คะแนนเต็ม</label>
          <input type="number" name="${p}max_scores[${idx}]" value="${escH(String(score||''))}"
            min="1" max="1000" placeholder="ไม่กำหนด"
            class="w-full text-xs border border-slate-200 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-violet-400 outline-none">
        </div>
        <div class="flex-1">
          <label class="text-[10px] font-black text-slate-400 mb-1 block"><i class="fas fa-clock mr-1 text-rose-400"></i>หมดเขตส่ง</label>
          <input type="datetime-local" name="${p}due_dates[${idx}]" value="${escH(due||'')}"
            class="w-full text-xs border border-slate-200 rounded-xl px-3 py-1.5 focus:ring-2 focus:ring-violet-400 outline-none">
        </div>
      </div>
      <label class="flex items-center gap-2 cursor-pointer pt-1">
        <input type="checkbox" name="${p}remedials[${idx}]" value="1" class="accent-orange-500" ${remedial ? 'checked' : ''}>
        <span class="text-xs font-bold text-orange-600"><i class="fas fa-life-ring mr-1"></i>แบบฝึกซ่อมเสริม (แนะนำเมื่อสอบหลังเรียนไม่ผ่าน)</span>
      </label>
    </div>
  </div>`;
}

function toggleExExtra(btn) {
  btn.closest('.exercise-row').querySelector('.ex-extra').classList.toggle('hidden');
}

function addExRow(cid) {
  document.getElementById(cid).insertAdjacentHTML('beforeend', buildExRow(exC++, false, '', '', '', '', false));
}

function addNewExRow(cid) {
  document.getElementById(cid).insertAdjacentHTML('beforeend', buildExRow(eExC++, true, '', '', '', '', false));
}

function openModal(id) { const el=document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id) { const el=document.getElementById(id); el.classList.add('hidden'); el.classList.remove('flex'); }

async function openEditModal(id, order, name) {
  document.getElementById('e_uid').value   = id;
  document.getElementById('e_order').value = order;
  document.getElementById('e_uname').value = name;
  const c = document.getElementById('edit_exs');
  c.innerHTML = '<p class="text-xs text-slate-400 p-2 text-center"><i class="fas fa-spinner fa-spin mr-1"></i>กำลังโหลด...</p>';
  openModal('editModal');
  eExC = 0;
  const data = await fetch(`units.php?ajax=exercises&unit_id=${id}&subject_id=<?=$subject_id?>`).then(r => r.json());
  c.innerHTML = '';
  data.forEach(ex => {
    const dueVal = ex.due_date ? ex.due_date.replace(' ', 'T').substring(0, 16) : '';
    const tmp = document.createElement('div');
    tmp.innerHTML = buildExRow(ex.id, false, ex.exercise_title, ex.description || '', ex.max_score || '', dueVal, ex.is_remedial);
    c.appendChild(tmp.firstElementChild);
  });
}

function confirmDel(url, msg, okText, okColor) {
  Swal.fire({
    icon:'warning', title: msg,
    text: okText ? '' : 'ย้ายไปถังขยะ กู้คืนได้ภายหลัง',
    showCancelButton:true,
    confirmButtonColor: okColor || '#ef4444',
    cancelButtonText:'ยกเลิก',
    confirmButtonText: okText || 'ลบ',
  }).then(r => { if (r.isConfirmed) location.href = url; });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
