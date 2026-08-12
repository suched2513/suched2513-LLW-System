<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
require_once __DIR__ . '/_helpers.php';

$pdo        = getPdo();
$is_admin   = $_SESSION['llw_role'] === 'super_admin';
$teacher_id = (int)($_SESSION['teacher_id'] ?? 0);

$topic_id = (int)($_GET['topic_id'] ?? $_POST['topic_id'] ?? 0);
$topic    = lms_get_owned_topic($pdo, $topic_id, $is_admin, $teacher_id);
if (!$topic) { header('Location: ' . $base_path . '/lms/units.php'); exit(); }

$unit_stmt = $pdo->prepare("SELECT * FROM lms_units WHERE id=?");
$unit_stmt->execute([$topic['unit_id']]); $unit = $unit_stmt->fetch();

$block_types = lms_block_types();
$msg = '';

function lms_block_upload_config(string $type): ?array {
    $cfg = [
        'image' => ['mime' => ['image/jpeg','image/png','image/gif','image/webp'], 'ext' => ['jpg','jpeg','png','gif','webp'], 'max' => 5 * 1024 * 1024],
        'audio' => ['mime' => ['audio/mpeg','audio/mp4','audio/ogg','audio/wav','audio/x-wav'], 'ext' => ['mp3','m4a','ogg','wav'], 'max' => 20 * 1024 * 1024],
        'pdf'   => ['mime' => ['application/pdf'], 'ext' => ['pdf'], 'max' => 20 * 1024 * 1024],
        'file'  => [
            'mime' => ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                       'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                       'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
                       'application/zip','image/jpeg','image/png'],
            'ext'  => ['pdf','doc','docx','xls','xlsx','ppt','pptx','zip','jpg','jpeg','png'],
            'max'  => 20 * 1024 * 1024,
        ],
    ];
    return $cfg[$type] ?? null;
}

function lms_handle_block_upload(string $type, array $file): ?array {
    $cfg = lms_block_upload_config($type);
    if (!$cfg || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $cfg['ext'], true)) return null;
    if ($file['size'] > $cfg['max']) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $cfg['mime'], true)) return null;

    $upload_dir = __DIR__ . '/../uploads/lms/blocks/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        file_put_contents($upload_dir . '.htaccess',
            "Options -Indexes -ExecCGI\n<FilesMatch \"\\.(php|phtml|phar|php3|php4|php5|php7)$\">\n    Require all denied\n</FilesMatch>\n");
    }
    $fname = 'blk_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) return null;
    return ['path' => 'uploads/lms/blocks/' . $fname, 'original_name' => basename($file['name'])];
}

function lms_get_block_for_topic(PDO $pdo, int $block_id, int $topic_id): ?array {
    $st = $pdo->prepare("SELECT * FROM lms_topic_blocks WHERE id=? AND topic_id=?");
    $st->execute([$block_id, $topic_id]);
    $row = $st->fetch();
    return $row ?: null;
}

// ── AJAX: block data for edit modal ──────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'block_data') {
    header('Content-Type: application/json');
    $block = lms_get_block_for_topic($pdo, (int)($_GET['id'] ?? 0), $topic_id);
    echo json_encode($block ?: null); exit();
}

// ── AJAX: rendered preview (exactly what students will see) ─────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'preview') {
    header('Content-Type: application/json');
    $blocks = $pdo->prepare("SELECT * FROM lms_topic_blocks WHERE topic_id=? ORDER BY order_no");
    $blocks->execute([$topic_id]);
    echo json_encode(['html' => lms_render_topic_blocks($blocks->fetchAll())]); exit();
}

// ── POST: add / update block ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_block') {
            $type = $_POST['block_type'] ?? '';
            if (!isset($block_types[$type])) throw new Exception('ประเภทบล็อกไม่ถูกต้อง');

            $title = trim($_POST['title'] ?? '') ?: null;
            $body  = trim($_POST['body'] ?? '') ?: null;
            $url   = trim($_POST['media_url'] ?? '');
            if ($url && !filter_var($url, FILTER_VALIDATE_URL)) throw new Exception('ลิงก์ไม่ถูกต้อง');
            $media_url = $url ?: null;
            $media_path = null; $original_name = null;

            if (lms_block_upload_config($type) && !empty($_FILES['block_file']['name'])) {
                $up = lms_handle_block_upload($type, $_FILES['block_file']);
                if (!$up) throw new Exception('ไฟล์ไม่ถูกต้อง (ตรวจสอบชนิดและขนาดไฟล์)');
                $media_path = $up['path'];
                $original_name = $up['original_name'];
            } elseif (in_array($type, ['image','audio','pdf','file'], true)) {
                throw new Exception('กรุณาอัปโหลดไฟล์');
            }

            if ($type === 'heading' && !$title) throw new Exception('กรุณาระบุข้อความหัวข้อ');
            if (in_array($type, ['text','callout_info','callout_example','callout_warning','callout_hint','summary'], true) && !$body) {
                throw new Exception('กรุณาระบุเนื้อหา');
            }
            if ($type === 'question' && (!$title || !$body)) throw new Exception('กรุณาระบุคำถามและเฉลย');
            if (in_array($type, ['video','link'], true) && !$media_url) throw new Exception('กรุณาระบุลิงก์');

            $ord = $pdo->prepare("SELECT COALESCE(MAX(order_no),0)+1 FROM lms_topic_blocks WHERE topic_id=?");
            $ord->execute([$topic_id]); $order_no = (int)$ord->fetchColumn();

            $pdo->prepare("INSERT INTO lms_topic_blocks (topic_id, order_no, block_type, title, body, media_path, media_url, original_name) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$topic_id, $order_no, $type, $title, $body, $media_path, $media_url, $original_name]);
            lms_log_activity($pdo, 'create', 'lms_topic_block', (int)$pdo->lastInsertId(), null, ['block_type' => $type]);
            $msg = 'success:เพิ่มบล็อกสำเร็จ';

        } elseif ($action === 'update_block') {
            $id = (int)($_POST['id'] ?? 0);
            $block = lms_get_block_for_topic($pdo, $id, $topic_id);
            if (!$block) throw new Exception('ไม่พบบล็อกนี้');
            $type = $block['block_type'];

            $title = trim($_POST['title'] ?? '') ?: null;
            $body  = trim($_POST['body'] ?? '') ?: null;
            $url   = trim($_POST['media_url'] ?? '');
            if ($url && !filter_var($url, FILTER_VALIDATE_URL)) throw new Exception('ลิงก์ไม่ถูกต้อง');
            $media_url  = $url ?: null;
            $media_path = $block['media_path'];
            $original_name = $block['original_name'];

            if (lms_block_upload_config($type) && !empty($_FILES['block_file']['name'])) {
                $up = lms_handle_block_upload($type, $_FILES['block_file']);
                if (!$up) throw new Exception('ไฟล์ไม่ถูกต้อง (ตรวจสอบชนิดและขนาดไฟล์)');
                if ($block['media_path']) {
                    $old = __DIR__ . '/../' . $block['media_path'];
                    if (file_exists($old)) @unlink($old);
                }
                $media_path = $up['path'];
                $original_name = $up['original_name'];
            }

            $pdo->prepare("UPDATE lms_topic_blocks SET title=?, body=?, media_url=?, media_path=?, original_name=? WHERE id=?")
                ->execute([$title, $body, $media_url, $media_path, $original_name, $id]);
            lms_log_activity($pdo, 'update', 'lms_topic_block', $id);
            $msg = 'success:แก้ไขบล็อกสำเร็จ';
        }
    } catch (Exception $e) {
        $msg = 'error:' . $e->getMessage();
    }
    header('Location: topic_builder.php?topic_id=' . $topic_id . '&msg=' . urlencode($msg)); exit();
}

// ── GET: delete / move ────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete_block') {
    $block = lms_get_block_for_topic($pdo, (int)$_GET['id'], $topic_id);
    if ($block) {
        if ($block['media_path']) {
            $fp = __DIR__ . '/../' . $block['media_path'];
            if (file_exists($fp)) @unlink($fp);
        }
        $pdo->prepare("DELETE FROM lms_topic_blocks WHERE id=?")->execute([$block['id']]);
        lms_log_activity($pdo, 'delete', 'lms_topic_block', $block['id']);
    }
    header('Location: topic_builder.php?topic_id=' . $topic_id . '&msg=' . urlencode('success:ลบบล็อกสำเร็จ')); exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'move_block') {
    $block = lms_get_block_for_topic($pdo, (int)$_GET['id'], $topic_id);
    $dir   = $_GET['dir'] ?? '';
    if ($block && in_array($dir, ['up','down'], true)) {
        $cmp = $dir === 'up' ? '<' : '>';
        $ord = $dir === 'up' ? 'DESC' : 'ASC';
        $nb  = $pdo->prepare("SELECT * FROM lms_topic_blocks WHERE topic_id=? AND order_no {$cmp} ? ORDER BY order_no {$ord} LIMIT 1");
        $nb->execute([$topic_id, $block['order_no']]); $neighbor = $nb->fetch();
        if ($neighbor) {
            $pdo->prepare("UPDATE lms_topic_blocks SET order_no=? WHERE id=?")->execute([$neighbor['order_no'], $block['id']]);
            $pdo->prepare("UPDATE lms_topic_blocks SET order_no=? WHERE id=?")->execute([$block['order_no'], $neighbor['id']]);
        }
    }
    header('Location: topic_builder.php?topic_id=' . $topic_id . '&msg=' . urlencode('success:จัดลำดับสำเร็จ')); exit();
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

$blocks_stmt = $pdo->prepare("SELECT * FROM lms_topic_blocks WHERE topic_id=? ORDER BY order_no");
$blocks_stmt->execute([$topic_id]);
$blocks = $blocks_stmt->fetchAll();

$pageTitle    = 'ตัวสร้างเนื้อหาบทเรียน';
$pageSubtitle = htmlspecialchars($topic['topic_name'], ENT_QUOTES, 'UTF-8');
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
    <a href="<?=$base_path?>/lms/topics.php?unit_id=<?=$topic['unit_id']?>" class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 mb-1">
      <i class="fas fa-arrow-left"></i> กลับหน้าเรื่อง — <?=htmlspecialchars($unit['unit_name'] ?? '', ENT_QUOTES, 'UTF-8')?>
    </a>
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
        <i class="fas fa-layer-group text-white"></i>
      </div>
      <div>
        <h2 class="text-lg font-black text-slate-800"><?=htmlspecialchars($topic['topic_name'], ENT_QUOTES, 'UTF-8')?></h2>
        <p class="text-xs text-slate-400"><?=count($blocks)?> บล็อกเนื้อหา</p>
      </div>
    </div>
  </div>
  <div class="flex gap-2">
    <button onclick="openPreview()" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all flex items-center gap-2">
      <i class="fas fa-eye"></i> ดูตัวอย่าง
    </button>
    <button onclick="openAddModal()" class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all flex items-center gap-2">
      <i class="fas fa-plus"></i> เพิ่มบล็อก
    </button>
  </div>
</div>

<!-- Block list -->
<div class="space-y-3">
  <?php if (empty($blocks)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 p-16 text-center text-slate-300 shadow-sm">
    <i class="fas fa-layer-group text-5xl mb-3 block opacity-30"></i>
    <p class="font-bold">ยังไม่มีบล็อกเนื้อหา — กด "เพิ่มบล็อก" เพื่อเริ่มต้น</p>
  </div>
  <?php else: foreach ($blocks as $i => $b):
    $bt = $block_types[$b['block_type']] ?? ['label' => $b['block_type'], 'icon' => 'bi-square'];
  ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-start gap-3">
    <div class="flex flex-col items-center gap-1 flex-shrink-0 pt-1">
      <button onclick="location.href='topic_builder.php?topic_id=<?=$topic_id?>&action=move_block&id=<?=$b['id']?>&dir=up'"
        class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-all" title="เลื่อนขึ้น">
        <i class="fas fa-chevron-up text-xs"></i>
      </button>
      <span class="text-xs font-black text-slate-400"><?=$i + 1?></span>
      <button onclick="location.href='topic_builder.php?topic_id=<?=$topic_id?>&action=move_block&id=<?=$b['id']?>&dir=down'"
        class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-all" title="เลื่อนลง">
        <i class="fas fa-chevron-down text-xs"></i>
      </button>
    </div>
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2 mb-1">
        <span class="px-2.5 py-1 bg-violet-50 text-violet-700 text-xs font-black rounded-full"><i class="<?=htmlspecialchars($bt['icon'],ENT_QUOTES,'UTF-8')?> mr-1"></i><?=htmlspecialchars($bt['label'],ENT_QUOTES,'UTF-8')?></span>
      </div>
      <?php if ($b['title']): ?><p class="text-sm font-bold text-slate-800 truncate"><?=htmlspecialchars($b['title'],ENT_QUOTES,'UTF-8')?></p><?php endif; ?>
      <?php if ($b['body']): ?><p class="text-xs text-slate-400 mt-0.5 line-clamp-2"><?=htmlspecialchars(mb_substr($b['body'],0,140),ENT_QUOTES,'UTF-8')?></p><?php endif; ?>
      <?php if ($b['media_url']): ?><p class="text-xs text-blue-500 mt-0.5 truncate"><i class="fas fa-link mr-1"></i><?=htmlspecialchars($b['media_url'],ENT_QUOTES,'UTF-8')?></p><?php endif; ?>
      <?php if ($b['original_name']): ?><p class="text-xs text-slate-400 mt-0.5 truncate"><i class="fas fa-paperclip mr-1"></i><?=htmlspecialchars($b['original_name'],ENT_QUOTES,'UTF-8')?></p><?php endif; ?>
    </div>
    <div class="flex gap-2 flex-shrink-0">
      <button onclick="openEditModal(<?=$b['id']?>)" class="px-3 py-1.5 bg-amber-400 text-white text-xs font-bold rounded-lg hover:bg-amber-500 transition-all"><i class="fas fa-edit"></i></button>
      <button onclick="confirmDel('topic_builder.php?topic_id=<?=$topic_id?>&action=delete_block&id=<?=$b['id']?>','ลบบล็อกนี้?')" class="px-3 py-1.5 bg-rose-500 text-white text-xs font-bold rounded-lg hover:bg-rose-600 transition-all"><i class="fas fa-trash"></i></button>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>

<!-- Add Block Modal -->
<div id="addModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white z-10">
      <h3 class="font-black text-slate-800"><i class="fas fa-plus-circle mr-2 text-violet-500"></i>เพิ่มบล็อกเนื้อหา</h3>
      <button onclick="closeModal('addModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
      <input type="hidden" name="action" value="add_block">
      <input type="hidden" name="topic_id" value="<?=$topic_id?>">
      <div>
        <label class="block text-xs font-black text-slate-500 mb-1">ประเภทบล็อก <span class="text-rose-500">*</span></label>
        <select name="block_type" id="add_type" onchange="renderFields('add')" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" required>
          <option value="">— เลือกประเภท —</option>
          <?php foreach ($block_types as $key => $bt): ?>
          <option value="<?=$key?>"><?=htmlspecialchars($bt['label'],ENT_QUOTES,'UTF-8')?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="add_fields"></div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Block Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white z-10">
      <h3 class="font-black text-slate-800"><i class="fas fa-edit mr-2 text-amber-500"></i>แก้ไขบล็อกเนื้อหา</h3>
      <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
      <input type="hidden" name="action" value="update_block">
      <input type="hidden" name="topic_id" value="<?=$topic_id?>">
      <input type="hidden" name="id" id="edit_id">
      <input type="hidden" id="edit_type">
      <div id="edit_fields"></div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-amber-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-amber-200 hover:bg-amber-600 transition-all"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[85vh] overflow-hidden flex flex-col">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 flex-shrink-0">
      <h3 class="font-black text-slate-800"><i class="fas fa-eye mr-2 text-violet-500"></i>ตัวอย่างที่นักเรียนจะเห็น</h3>
      <button onclick="closeModal('previewModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <div id="previewBody" class="p-6 overflow-y-auto"></div>
  </div>
</div>

<script>
const BLOCK_TYPES_NEED_FILE = ['image','audio','pdf','file'];
const BLOCK_TYPES_NEED_URL  = ['video','link'];

function fieldsHtml(prefix, type, vals) {
  vals = vals || {};
  const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  let h = '';
  if (type === 'heading') {
    h += `<div><label class="block text-xs font-black text-slate-500 mb-1">ข้อความหัวข้อ <span class="text-rose-500">*</span></label>
      <input type="text" name="title" value="${esc(vals.title)}" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" required></div>`;
  } else if (['text','callout_info','callout_example','callout_warning','callout_hint','summary'].includes(type)) {
    if (type !== 'text' && type !== 'summary') {
      h += `<div><label class="block text-xs font-black text-slate-500 mb-1">หัวข้อกล่อง (ไม่บังคับ)</label>
        <input type="text" name="title" value="${esc(vals.title)}" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none"></div>`;
    }
    h += `<div><label class="block text-xs font-black text-slate-500 mb-1">เนื้อหา <span class="text-rose-500">*</span></label>
      <textarea name="body" rows="4" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none" required>${esc(vals.body)}</textarea></div>`;
  } else if (type === 'question') {
    h += `<div><label class="block text-xs font-black text-slate-500 mb-1">คำถาม <span class="text-rose-500">*</span></label>
      <input type="text" name="title" value="${esc(vals.title)}" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" required></div>
      <div><label class="block text-xs font-black text-slate-500 mb-1">เฉลย / คำอธิบาย (กดดูได้) <span class="text-rose-500">*</span></label>
      <textarea name="body" rows="3" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none" required>${esc(vals.body)}</textarea></div>`;
  } else if (type === 'video' || type === 'link') {
    h += `<div><label class="block text-xs font-black text-slate-500 mb-1">ชื่อ/ป้ายกำกับ (ไม่บังคับ)</label>
      <input type="text" name="title" value="${esc(vals.title)}" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none"></div>
      <div><label class="block text-xs font-black text-slate-500 mb-1">${type==='video'?'ลิงก์ YouTube':'ลิงก์'} <span class="text-rose-500">*</span></label>
      <input type="url" name="media_url" value="${esc(vals.media_url)}" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" placeholder="https://..." required></div>`;
  } else if (BLOCK_TYPES_NEED_FILE.includes(type)) {
    const labelMap = {image:'คำบรรยายภาพ', audio:'ชื่อไฟล์เสียง', pdf:'ชื่อเอกสาร', file:'คำบรรยายไฟล์'};
    h += `<div><label class="block text-xs font-black text-slate-500 mb-1">${labelMap[type]} (ไม่บังคับ)</label>
      <input type="text" name="title" value="${esc(vals.title)}" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none"></div>`;
    if (vals.original_name) {
      h += `<p class="text-xs text-slate-400"><i class="fas fa-paperclip mr-1"></i>ไฟล์ปัจจุบัน: ${esc(vals.original_name)}</p>`;
    }
    h += `<div><label class="block text-xs font-black text-slate-500 mb-1">อัปโหลดไฟล์ ${vals.original_name?'(อัปโหลดใหม่เพื่อแทนที่)':'<span class=\"text-rose-500\">*</span>'}</label>
      <input type="file" name="block_file" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs bg-white" ${vals.original_name?'':'required'}></div>`;
  }
  return h;
}

function renderFields(prefix) {
  const type = document.getElementById(prefix+'_type').value;
  document.getElementById(prefix+'_fields').innerHTML = fieldsHtml(prefix, type, {});
}

function openModal(id) { const el=document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id) { const el=document.getElementById(id); el.classList.add('hidden'); el.classList.remove('flex'); }

function openAddModal() {
  document.getElementById('add_type').value = '';
  document.getElementById('add_fields').innerHTML = '';
  openModal('addModal');
}

async function openEditModal(id) {
  const b = await fetch(`topic_builder.php?topic_id=<?=$topic_id?>&ajax=block_data&id=${id}`).then(r=>r.json());
  if (!b) { Swal.fire({icon:'error',title:'ไม่พบข้อมูล',confirmButtonColor:'#7C3AED'}); return; }
  document.getElementById('edit_id').value = b.id;
  document.getElementById('edit_type').value = b.block_type;
  document.getElementById('edit_fields').innerHTML = fieldsHtml('edit', b.block_type, b);
  openModal('editModal');
}

async function openPreview() {
  document.getElementById('previewBody').innerHTML = '<p class="text-center text-slate-400 text-sm py-8"><i class="fas fa-spinner fa-spin mr-1"></i>กำลังโหลด...</p>';
  openModal('previewModal');
  const data = await fetch(`topic_builder.php?topic_id=<?=$topic_id?>&ajax=preview`).then(r=>r.json());
  document.getElementById('previewBody').innerHTML = data.html;
}

function confirmDel(url, msg) {
  Swal.fire({icon:'warning',title:msg,text:'ลบแล้วไม่สามารถกู้คืนได้',showCancelButton:true,confirmButtonColor:'#ef4444',cancelButtonText:'ยกเลิก',confirmButtonText:'ลบ'})
    .then(r=>{if(r.isConfirmed)location.href=url;});
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
