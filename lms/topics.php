<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: ' . $base_path . '/login.php'); exit(); }
if (!in_array($_SESSION['llw_role'], ['super_admin','att_teacher'])) { header('Location: ' . $base_path . '/login.php'); exit(); }

$pdo     = getPdo();
$unit_id = (int)($_GET['unit_id'] ?? 0);
if (!$unit_id) { header('Location: ' . $base_path . '/lms/units.php'); exit(); }

$unit = $pdo->prepare("SELECT * FROM lms_units WHERE id=? LIMIT 1");
$unit->execute([$unit_id]); $unit = $unit->fetch();
if (!$unit) { header('Location: ' . $base_path . '/lms/units.php'); exit(); }

// AJAX: get topic data for edit
if (isset($_GET['ajax']) && $_GET['ajax'] === 'topic_data') {
    $tid   = (int)($_GET['id'] ?? 0);
    $topic = $pdo->prepare("SELECT * FROM lms_topics WHERE id=?"); $topic->execute([$tid]); $topic=$topic->fetch();
    $links = $pdo->prepare("SELECT * FROM lms_topic_links WHERE topic_id=?"); $links->execute([$tid]); $links=$links->fetchAll();
    $yt    = $pdo->prepare("SELECT * FROM lms_topic_youtube WHERE topic_id=?"); $yt->execute([$tid]); $yt=$yt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode(['topic'=>$topic,'links'=>$links,'yt'=>$yt]); exit();
}

$msg = '';

function saveTopic(PDO $pdo, int $topic_id): void {
    foreach ($_POST['links'] ?? [] as $k => $url) {
        $url = trim($url); if (!$url) continue;
        $lbl = trim($_POST['link_labels'][$k] ?? '');
        $pdo->prepare("INSERT INTO lms_topic_links (topic_id, url, link_label) VALUES (?,?,?)")->execute([$topic_id, $url, $lbl]);
    }
    foreach ($_POST['yt_urls'] ?? [] as $k => $url) {
        $url = trim($url); if (!$url) continue;
        $lbl = trim($_POST['yt_labels'][$k] ?? '');
        $pdo->prepare("INSERT INTO lms_topic_youtube (topic_id, url, video_label) VALUES (?,?,?)")->execute([$topic_id, $url, $lbl]);
    }
    $upload_dir = __DIR__ . '/../uploads/lms/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    if (!empty($_FILES['topic_files']['name'][0])) {
        $cnt = count($_FILES['topic_files']['name']);
        for ($i = 0; $i < $cnt; $i++) {
            if ($_FILES['topic_files']['error'][$i] === UPLOAD_ERR_OK) {
                $orig  = basename($_FILES['topic_files']['name'][$i]);
                $fname = uniqid('lms_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
                move_uploaded_file($_FILES['topic_files']['tmp_name'][$i], $upload_dir . $fname);
                $pdo->prepare("INSERT INTO lms_topic_files (topic_id, filename, original_name) VALUES (?,?,?)")->execute([$topic_id, $fname, $orig]);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $order_no   = (int)($_POST['order_no'] ?? 1);
    $topic_name = trim($_POST['topic_name'] ?? '');
    $content    = trim($_POST['content'] ?? '');
    $id         = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'add') {
            if (!$topic_name) throw new Exception('กรุณาระบุชื่อเรื่อง');
            $pdo->prepare("INSERT INTO lms_topics (unit_id, order_no, topic_name, content) VALUES (?,?,?,?)")->execute([$unit_id, $order_no, $topic_name, $content]);
            saveTopic($pdo, (int)$pdo->lastInsertId());
            $msg = 'success:เพิ่มเรื่องสำเร็จ';
        } elseif ($action === 'edit') {
            if (!$topic_name) throw new Exception('กรุณาระบุชื่อเรื่อง');
            $pdo->prepare("UPDATE lms_topics SET order_no=?, topic_name=?, content=? WHERE id=?")->execute([$order_no, $topic_name, $content, $id]);
            $pdo->prepare("DELETE FROM lms_topic_links WHERE topic_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM lms_topic_youtube WHERE topic_id=?")->execute([$id]);
            saveTopic($pdo, $id);
            $msg = 'success:แก้ไขสำเร็จ';
        }
    } catch (Exception $e) {
        $msg = 'error:' . $e->getMessage();
    }
    header("Location: topics.php?unit_id={$unit_id}&msg=" . urlencode($msg)); exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_topic') {
    $pdo->prepare("DELETE FROM lms_topics WHERE id=? AND unit_id=?")->execute([(int)$_GET['topic_id'], $unit_id]);
    header("Location: topics.php?unit_id={$unit_id}&msg=" . urlencode('success:ลบเรื่องสำเร็จ')); exit();
}
if (isset($_GET['action']) && $_GET['action'] === 'delete_file') {
    $fid = (int)$_GET['file_id'];
    $fr  = $pdo->prepare("SELECT filename FROM lms_topic_files WHERE id=?"); $fr->execute([$fid]); $fr=$fr->fetch();
    if ($fr) { @unlink(__DIR__ . '/../uploads/lms/' . $fr['filename']); }
    $pdo->prepare("DELETE FROM lms_topic_files WHERE id=?")->execute([$fid]);
    header("Location: topics.php?unit_id={$unit_id}&msg=" . urlencode('success:ลบไฟล์สำเร็จ')); exit();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$topics = $pdo->prepare("SELECT * FROM lms_topics WHERE unit_id=? ORDER BY order_no"); $topics->execute([$unit_id]); $topics=$topics->fetchAll();
$next_order = empty($topics) ? 1 : max(array_column($topics, 'order_no')) + 1;

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
    <a href="<?=$base_path?>/lms/units.php" class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 mb-1">
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
  <button onclick="openModal('addModal')"
    class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all flex items-center gap-2">
    <i class="fas fa-plus"></i> เพิ่มเรื่อง
  </button>
</div>

<div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-xs text-slate-400 font-black uppercase tracking-wider">
      <tr>
        <th class="px-5 py-3 text-center w-16">ลำดับ</th>
        <th class="px-5 py-3 text-left">ชื่อเรื่อง</th>
        <th class="px-5 py-3 text-center w-40">สื่อ</th>
        <th class="px-5 py-3 text-center w-36">จัดการ</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-50">
      <?php if (empty($topics)): ?>
      <tr><td colspan="4" class="py-16 text-center text-slate-300"><i class="fas fa-file-alt text-4xl mb-3 block opacity-30"></i>ยังไม่มีเรื่องในหน่วยนี้</td></tr>
      <?php endif; ?>
      <?php foreach ($topics as $t):
        $s1=$pdo->prepare("SELECT COUNT(*) FROM lms_topic_links   WHERE topic_id=?"); $s1->execute([$t['id']]); $lc=(int)$s1->fetchColumn();
        $s2=$pdo->prepare("SELECT COUNT(*) FROM lms_topic_youtube WHERE topic_id=?"); $s2->execute([$t['id']]); $yc=(int)$s2->fetchColumn();
        $s3=$pdo->prepare("SELECT COUNT(*) FROM lms_topic_files   WHERE topic_id=?"); $s3->execute([$t['id']]); $fc=(int)$s3->fetchColumn();
      ?>
      <tr class="hover:bg-slate-50/50 transition-colors">
        <td class="px-5 py-4 text-center">
          <span class="px-2.5 py-1 bg-orange-50 text-orange-600 text-xs font-black rounded-full"><?=$t['order_no']?></span>
        </td>
        <td class="px-5 py-4">
          <div class="font-bold text-slate-800"><?=htmlspecialchars($t['topic_name'],ENT_QUOTES,'UTF-8')?></div>
          <?php if ($t['content']): ?>
          <div class="text-xs text-slate-400 mt-0.5 line-clamp-1"><?=htmlspecialchars(mb_substr($t['content'],0,80),ENT_QUOTES,'UTF-8')?></div>
          <?php endif; ?>
        </td>
        <td class="px-5 py-4 text-center">
          <div class="flex gap-1 justify-center flex-wrap">
            <?php if ($lc): ?><span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs font-bold rounded-full"><i class="fas fa-link mr-0.5"></i><?=$lc?></span><?php endif; ?>
            <?php if ($yc): ?>
            <button onclick="loadPreview(<?=$t['id']?>, <?=$unit_id?>)"
              class="px-2 py-0.5 bg-rose-50 text-rose-500 text-xs font-bold rounded-full hover:bg-rose-100 transition-all">
              <i class="fab fa-youtube mr-0.5"></i><?=$yc?> <i class="fas fa-play-circle ml-0.5"></i>
            </button>
            <?php endif; ?>
            <?php if ($fc): ?><span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-xs font-bold rounded-full"><i class="fas fa-file mr-0.5"></i><?=$fc?></span><?php endif; ?>
            <?php if (!$lc && !$yc && !$fc): ?><span class="text-slate-300 text-xs">—</span><?php endif; ?>
          </div>
        </td>
        <td class="px-5 py-4">
          <div class="flex gap-2 justify-center">
            <button onclick="loadPreview(<?=$t['id']?>, <?=$unit_id?>)"
              class="px-3 py-1.5 bg-violet-100 text-violet-700 text-xs font-bold rounded-lg hover:bg-violet-200 transition-all" title="ดูเนื้อหา">
              <i class="fas fa-eye"></i>
            </button>
            <button onclick="loadEditTopic(<?=$t['id']?>, <?=$unit_id?>)"
              class="px-3 py-1.5 bg-amber-400 text-white text-xs font-bold rounded-lg hover:bg-amber-500 transition-all">
              <i class="fas fa-edit"></i>
            </button>
            <button onclick="confirmDel('topics.php?unit_id=<?=$unit_id?>&action=delete_topic&topic_id=<?=$t['id']?>','ลบเรื่องนี้?')"
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

<?php foreach ($topics as $t):
  $files = $pdo->prepare("SELECT * FROM lms_topic_files WHERE topic_id=?"); $files->execute([$t['id']]); $files=$files->fetchAll();
  if (empty($files)) continue;
?>
<div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-4 mt-3" style="border-left:3px solid #AED581">
  <div class="font-bold text-slate-600 text-xs mb-2"><i class="fas fa-paperclip mr-1 text-green-500"></i><?=htmlspecialchars($t['topic_name'],ENT_QUOTES,'UTF-8')?> — ไฟล์แนบ</div>
  <div class="flex flex-wrap gap-2">
    <?php foreach ($files as $f): ?>
    <div class="flex items-center gap-2 bg-slate-50 rounded-lg px-3 py-2 border border-slate-100 text-xs text-slate-600">
      <i class="fas fa-file-alt text-slate-400"></i>
      <?=htmlspecialchars($f['original_name'],ENT_QUOTES,'UTF-8')?>
      <a href="topics.php?unit_id=<?=$unit_id?>&action=delete_file&file_id=<?=$f['id']?>" class="text-rose-400 hover:text-rose-600 ml-1"><i class="fas fa-times-circle"></i></a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- Add / Edit Modal Template -->
<?php foreach ([['add','เพิ่มเรื่อง','add'],['edit','แก้ไขเรื่อง','edit']] as [$mid, $mtitle, $maction]): ?>
<div id="<?=$mid?>Modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white z-10">
      <h3 class="font-black text-slate-800"><i class="fas fa-<?=$mid==='add'?'plus-circle text-orange-500':'edit text-amber-500'?> mr-2"></i><?=$mtitle?></h3>
      <button onclick="closeModal('<?=$mid?>Modal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-lg"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
      <input type="hidden" name="action" value="<?=$maction?>">
      <?php if ($mid==='edit'): ?><input type="hidden" name="id" id="e_tid"><?php endif; ?>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-black text-slate-500 mb-1">ลำดับ</label>
          <input type="number" name="order_no" <?=$mid==='edit'?'id="e_order"':'id="a_order"'?> class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" value="<?=$mid==='add'?$next_order:1?>" min="1">
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-black text-slate-500 mb-1">ชื่อเรื่อง <span class="text-rose-500">*</span></label>
          <input type="text" name="topic_name" <?=$mid==='edit'?'id="e_tname"':''?> class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none" required>
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-black text-slate-500 mb-1">เนื้อหาบทเรียน</label>
          <textarea name="content" <?=$mid==='edit'?'id="e_content"':''?> rows="5" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none" placeholder="เนื้อหา..."></textarea>
        </div>
      </div>
      <!-- Links -->
      <div class="rounded-xl p-3 bg-blue-50 border border-blue-100">
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs font-black text-blue-700"><i class="fas fa-link mr-1"></i>ลิงค์ URL</label>
          <button type="button" onclick="addLinkRow('<?=$mid?>_links')" class="px-3 py-1 bg-blue-500 text-white text-xs font-bold rounded-lg hover:bg-blue-600 transition-all"><i class="fas fa-plus"></i> เพิ่ม</button>
        </div>
        <div id="<?=$mid?>_links" class="space-y-2"></div>
      </div>
      <!-- YouTube -->
      <div class="rounded-xl p-3 bg-rose-50 border border-rose-100">
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs font-black text-rose-700"><i class="fab fa-youtube mr-1"></i>ลิงค์ YouTube</label>
          <button type="button" onclick="addYtRow('<?=$mid?>_yt')" class="px-3 py-1 bg-rose-500 text-white text-xs font-bold rounded-lg hover:bg-rose-600 transition-all"><i class="fas fa-plus"></i> เพิ่ม</button>
        </div>
        <div id="<?=$mid?>_yt" class="space-y-2"></div>
      </div>
      <!-- Files -->
      <div class="rounded-xl p-3 bg-emerald-50 border border-emerald-100">
        <label class="block text-xs font-black text-emerald-700 mb-2"><i class="fas fa-file-upload mr-1"></i>อัพโหลดไฟล์</label>
        <input type="file" name="topic_files[]" multiple class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs bg-white">
        <p class="text-xs text-slate-400 mt-1">เลือกได้หลายไฟล์พร้อมกัน</p>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModal('<?=$mid?>Modal')" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">ยกเลิก</button>
        <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all"><i class="fas fa-save mr-1"></i>บันทึก</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<script>
function openModal(id) { const el=document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id) { const el=document.getElementById(id); el.classList.add('hidden'); el.classList.remove('flex'); }

function addLinkRow(cid, url='', label='') {
  const c=document.getElementById(cid); const d=document.createElement('div'); d.className='grid grid-cols-2 gap-2';
  d.innerHTML=`<input type="text" name="link_labels[]" value="${label}" class="border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-violet-400 outline-none" placeholder="ชื่อลิงค์ (ถ้ามี)">
    <div class="flex gap-2"><input type="url" name="links[]" value="${url}" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-violet-400 outline-none" placeholder="https://...">
    <button type="button" onclick="this.closest('.grid').remove()" class="px-2.5 py-1.5 bg-rose-100 text-rose-500 text-xs rounded-lg"><i class="fas fa-times"></i></button></div>`;
  c.appendChild(d);
}
function addYtRow(cid, url='', label='') {
  const c=document.getElementById(cid); const d=document.createElement('div'); d.className='grid grid-cols-2 gap-2';
  d.innerHTML=`<input type="text" name="yt_labels[]" value="${label}" class="border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-violet-400 outline-none" placeholder="ชื่อวิดีโอ (ถ้ามี)">
    <div class="flex gap-2"><input type="url" name="yt_urls[]" value="${url}" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-violet-400 outline-none" placeholder="https://youtube.com/...">
    <button type="button" onclick="this.closest('.grid').remove()" class="px-2.5 py-1.5 bg-rose-100 text-rose-500 text-xs rounded-lg"><i class="fas fa-times"></i></button></div>`;
  c.appendChild(d);
}

async function loadEditTopic(tid, unitId) {
  const data = await fetch(`topics.php?unit_id=${unitId}&ajax=topic_data&id=${tid}`).then(r=>r.json());
  if (!data.topic) { Swal.fire({icon:'error',title:'ผิดพลาด',text:'ไม่พบข้อมูล',confirmButtonColor:'#7C3AED'}); return; }
  document.getElementById('e_tid').value   = data.topic.id;
  document.getElementById('e_order').value = data.topic.order_no;
  document.getElementById('e_tname').value = data.topic.topic_name;
  document.getElementById('e_content').value = data.topic.content || '';
  document.getElementById('edit_links').innerHTML='';
  document.getElementById('edit_yt').innerHTML='';
  data.links.forEach(l=>addLinkRow('edit_links',l.url,l.link_label||''));
  data.yt.forEach(y=>addYtRow('edit_yt',y.url,y.video_label||''));
  openModal('editModal');
}

function confirmDel(url, msg) {
  Swal.fire({icon:'warning',title:msg,showCancelButton:true,confirmButtonColor:'#ef4444',cancelButtonText:'ยกเลิก',confirmButtonText:'ลบ'})
    .then(r=>{if(r.isConfirmed)location.href=url;});
}

async function loadPreview(tid, unitId) {
  const data = await fetch(`topics.php?unit_id=${unitId}&ajax=topic_data&id=${tid}`).then(r=>r.json());
  if (!data.topic) return;
  const t = data.topic;

  let html = '';

  if (t.content) {
    html += `<div class="bg-slate-50 rounded-xl p-3 mb-4 text-sm text-slate-700 whitespace-pre-wrap text-left">${escText(t.content)}</div>`;
  }

  if (data.links && data.links.length > 0) {
    html += `<div class="mb-4 text-left"><p class="text-xs font-black text-blue-600 mb-2"><i class="fas fa-link mr-1"></i>ลิงก์</p><div class="space-y-1">`;
    data.links.forEach(l => {
      const label = l.link_label || l.url;
      html += `<a href="${escAttr(l.url)}" target="_blank" rel="noopener" class="flex items-center gap-2 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 text-xs text-blue-700 font-bold hover:bg-blue-100 transition-all"><i class="fas fa-external-link-alt"></i>${escText(label)}</a>`;
    });
    html += `</div></div>`;
  }

  if (data.yt && data.yt.length > 0) {
    html += `<div class="text-left"><p class="text-xs font-black text-rose-500 mb-2"><i class="fab fa-youtube mr-1"></i>YouTube</p><div class="space-y-3">`;
    data.yt.forEach(y => {
      const m = y.url.match(/(?:v=|youtu\.be\/)([A-Za-z0-9_\-]{11})/);
      const vid = m ? m[1] : '';
      if (vid) {
        const label = y.video_label ? `<p class="text-xs font-bold text-slate-600 mb-1">${escText(y.video_label)}</p>` : '';
        html += `<div>${label}<div class="relative rounded-xl overflow-hidden bg-black" style="padding-bottom:56.25%"><iframe class="absolute inset-0 w-full h-full" src="https://www.youtube.com/embed/${vid}?rel=0" frameborder="0" allowfullscreen></iframe></div></div>`;
      } else {
        html += `<a href="${escAttr(y.url)}" target="_blank" rel="noopener" class="flex items-center gap-2 bg-red-50 border border-red-100 rounded-lg px-3 py-2 text-xs text-red-600 font-bold"><i class="fab fa-youtube"></i>${escText(y.video_label||y.url)}</a>`;
      }
    });
    html += `</div></div>`;
  }

  if (!html) html = '<p class="text-slate-400 text-sm text-center py-4">ไม่มีเนื้อหา</p>';

  Swal.fire({
    title: `<span class="text-base font-black">${escText(t.topic_name)}</span>`,
    html: `<div class="overflow-y-auto max-h-[60vh]">${html}</div>`,
    confirmButtonColor: '#7C3AED',
    confirmButtonText: 'ปิด',
    width: '640px',
  });
}

function escText(str) {
  return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function escAttr(str) {
  return String(str||'').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
