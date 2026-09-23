<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role']) || !in_array($_SESSION['llw_role'], ['super_admin', 'cb_admin'])) {
    header('Location: ' . $base_path . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit();
}

$pdo = getPdo();

// Check table exists
$tableExists = (bool)$pdo->query("SHOW TABLES LIKE 'cb_repairs'")->fetch();

$repairs = [];
$stats   = ['total' => 0, 'รับแจ้ง' => 0, 'ส่งซ่อม' => 0, 'ซ่อมเสร็จ' => 0, 'รับกลับ' => 0];

if ($tableExists) {
    $hasImgCol = !empty($pdo->query("SHOW COLUMNS FROM cb_repairs LIKE 'images'")->fetchAll());
    $imgSql    = $hasImgCol ? 'r.images,' : "'' AS images,";
    $stmt = $pdo->query("
        SELECT r.id, r.borrow_log_id, r.chromebook_id, r.chromebook_serial,
               r.description, {$imgSql} r.status, r.repair_notes, r.reported_by,
               r.created_at, r.updated_at,
               b.borrower_type, b.borrower_id, b.class_name,
               COALESCE(t.name, s.name, b.borrower_id) AS borrower_name
        FROM cb_repairs r
        LEFT JOIN cb_borrow_logs b ON b.entry_id = r.borrow_log_id
        LEFT JOIN cb_teachers t ON b.borrower_type='Teacher' AND t.teacher_id = b.borrower_id
        LEFT JOIN cb_students s ON b.borrower_type='Student' AND s.student_id = b.borrower_id
        ORDER BY FIELD(r.status,'รับแจ้ง','ส่งซ่อม','ซ่อมเสร็จ','รับกลับ'), r.created_at DESC
    ");
    $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['total'] = count($repairs);
    foreach ($repairs as $rep) {
        $stats[$rep['status']] = ($stats[$rep['status']] ?? 0) + 1;
    }
}

$pageTitle    = 'ติดตามการซ่อม';
$pageSubtitle = 'สถานะการแจ้งซ่อม Chromebook ทั้งหมด';
$activeSystem = 'chromebook';
require_once __DIR__ . '/../components/layout_start.php';
?>

<style>
.modal-bg { position:fixed; inset:0; background:rgba(15,23,42,0.5); backdrop-filter:blur(12px); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; pointer-events:none; transition:all .3s ease; }
.modal-bg.active { opacity:1; pointer-events:auto; }
.modal-box { background:#fff; border-radius:2rem; padding:2rem; width:90%; transform:scale(0.92) translateY(16px); transition:all .3s cubic-bezier(.34,1.56,.64,1); max-height:90vh; overflow-y:auto; box-shadow:0 25px 60px -12px rgba(0,0,0,0.25); }
.modal-bg.active .modal-box { transform:scale(1) translateY(0); }
</style>

<?php if (!$tableExists): ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 flex gap-4 items-start">
    <i class="bi bi-exclamation-triangle-fill text-amber-500 text-2xl mt-0.5"></i>
    <div>
        <p class="font-black text-amber-800">ยังไม่ได้รัน Migration</p>
        <p class="text-sm text-amber-700 mt-1">ตาราง <code class="font-mono bg-amber-100 px-1 rounded">cb_repairs</code> ยังไม่มีในฐานข้อมูล กรุณารัน <code class="font-mono bg-amber-100 px-1 rounded">php database/migrate.php</code></p>
    </div>
</div>
<?php else: ?>

<div class="flex flex-col gap-6">

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <?php
        $kpiCards = [
            ['label'=>'รายการซ่อมทั้งหมด', 'val'=>$stats['total'],    'icon'=>'bi-clipboard2-data-fill',  'grad'=>'from-slate-600 to-slate-800',   'shadow'=>'shadow-slate-300/50'],
            ['label'=>'รับแจ้ง (รอดำเนินการ)', 'val'=>$stats['รับแจ้ง'],  'icon'=>'bi-bell-fill',             'grad'=>'from-amber-400 to-orange-500', 'shadow'=>'shadow-amber-200/50'],
            ['label'=>'ส่งซ่อมแล้ว',       'val'=>$stats['ส่งซ่อม'],  'icon'=>'bi-box-seam-fill',          'grad'=>'from-blue-500 to-indigo-600',   'shadow'=>'shadow-blue-200/50'],
            ['label'=>'ซ่อมเสร็จ (รอรับ)',  'val'=>$stats['ซ่อมเสร็จ'], 'icon'=>'bi-patch-check-fill',       'grad'=>'from-emerald-500 to-teal-500', 'shadow'=>'shadow-emerald-200/50'],
            ['label'=>'รับกลับแล้ว',        'val'=>$stats['รับกลับ'],  'icon'=>'bi-check-circle-fill',      'grad'=>'from-cyan-500 to-blue-600',    'shadow'=>'shadow-cyan-200/50'],
        ];
        foreach ($kpiCards as $c): ?>
        <div class="relative overflow-hidden rounded-2xl p-6 text-white shadow-xl <?= $c['shadow'] ?> bg-gradient-to-br <?= $c['grad'] ?>">
            <p class="text-xs font-black uppercase tracking-widest opacity-80 mb-1"><?= $c['label'] ?></p>
            <p class="text-4xl font-black tracking-tight"><?= $c['val'] ?></p>
            <i class="bi <?= $c['icon'] ?> absolute right-2 bottom-0 text-[4.5rem] opacity-10"></i>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter + Table -->
    <div class="bg-white rounded-2xl shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
        <!-- Toolbar -->
        <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap gap-3 items-center justify-between">
            <div class="flex gap-1.5 flex-wrap" id="filter-tabs">
                <?php
                $filterOpts = ['all'=>'ทั้งหมด','รับแจ้ง'=>'🔔 รับแจ้ง','ส่งซ่อม'=>'📦 ส่งซ่อม','ซ่อมเสร็จ'=>'✅ ซ่อมเสร็จ','รับกลับ'=>'🏫 รับกลับ'];
                foreach ($filterOpts as $k => $label): ?>
                <button onclick="filterRepairs('<?= $k ?>')" data-filter="<?= $k ?>"
                    class="filter-btn px-3 py-1.5 rounded-xl text-sm font-black transition-all bg-slate-100 text-slate-500 hover:bg-slate-200">
                    <?= $label ?>
                </button>
                <?php endforeach; ?>
            </div>
            <a href="index.php" class="bg-gradient-to-r from-cyan-500 to-blue-600 text-white px-4 py-2 rounded-xl text-xs font-black hover:opacity-90 transition flex items-center gap-1.5 shadow-sm shadow-cyan-200">
                <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
            </a>
        </div>
        <!-- Table -->
        <div class="overflow-x-auto" id="tbl-wrap">
            <table class="min-w-full w-full text-sm" id="repair-table">
                <thead class="bg-slate-50 text-xs font-black text-slate-400 uppercase tracking-widest">
                    <tr>
                        <th class="px-5 py-3 text-left">#</th>
                        <th class="px-5 py-3 text-left">สถานะ</th>
                        <th class="px-5 py-3 text-left">Chromebook</th>
                        <th class="px-5 py-3 text-left">ผู้ยืม</th>
                        <th class="px-5 py-3 text-left">รายละเอียด</th>
                        <th class="px-5 py-3 text-center">รูป</th>
                        <th class="px-5 py-3 text-left">หมายเหตุ</th>
                        <th class="px-5 py-3 text-left">วันที่แจ้ง</th>
                        <th class="px-4 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($repairs)): ?>
                    <tr><td colspan="9" class="text-center py-20 text-slate-400 font-bold">ยังไม่มีรายการซ่อม</td></tr>
                    <?php else: ?>
                    <?php foreach ($repairs as $rep):
                        $statusColors = [
                            'รับแจ้ง'   => 'bg-amber-100 text-amber-700',
                            'ส่งซ่อม'   => 'bg-blue-100 text-blue-700',
                            'ซ่อมเสร็จ' => 'bg-emerald-100 text-emerald-700',
                            'รับกลับ'   => 'bg-slate-100 text-slate-500',
                        ];
                        $color = $statusColors[$rep['status']] ?? 'bg-slate-100 text-slate-500';
                        $statusFlow = ['รับแจ้ง', 'ส่งซ่อม', 'ซ่อมเสร็จ', 'รับกลับ'];
                        $curIdx = array_search($rep['status'], $statusFlow);
                        $nextStatus = ($curIdx !== false && $curIdx < count($statusFlow)-1) ? $statusFlow[$curIdx+1] : null;
                        $isDone = $rep['status'] === 'รับกลับ';
                    ?>
                    <tr class="hover:bg-slate-50/50 transition<?= $isDone ? ' opacity-50' : '' ?>" data-status="<?= htmlspecialchars($rep['status'], ENT_QUOTES, 'UTF-8') ?>">
                        <td class="px-5 py-3 text-xs text-slate-400 font-mono font-bold">#<?= $rep['id'] ?></td>
                        <td class="px-5 py-3">
                            <span class="px-2.5 py-1 rounded-full text-xs font-black <?= $color ?>"><?= htmlspecialchars($rep['status'], ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td class="px-5 py-3">
                            <p class="font-mono font-black text-xs text-cyan-600"><?= htmlspecialchars($rep['chromebook_id'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs text-slate-300 font-bold"><?= htmlspecialchars($rep['chromebook_serial'], ENT_QUOTES, 'UTF-8') ?></p>
                        </td>
                        <td class="px-5 py-3">
                            <p class="text-sm font-bold text-slate-700"><?= htmlspecialchars($rep['borrower_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if ($rep['class_name']): ?>
                            <p class="text-xs text-slate-400 font-bold"><?= htmlspecialchars($rep['class_name'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-500 font-bold max-w-[180px]">
                            <span title="<?= htmlspecialchars($rep['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(mb_strimwidth($rep['description'] ?? '—', 0, 55, '…'), ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <?php
                            $imgs = array_filter(explode(',', $rep['images'] ?? ''));
                            if ($imgs): ?>
                            <div class="flex -space-x-2 justify-center">
                                <?php foreach (array_slice($imgs, 0, 3) as $img): ?>
                                <img src="uploads/<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
                                     class="w-9 h-9 rounded-xl ring-2 ring-white object-cover cursor-pointer hover:z-10 hover:scale-110 transition"
                                     onclick="viewImg('uploads/<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>')">
                                <?php endforeach; ?>
                                <?php if (count($imgs) > 3): ?>
                                <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-400 text-xs font-black flex items-center justify-center ring-2 ring-white">+<?= count($imgs)-3 ?></div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-slate-300 text-xs font-bold">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-400 font-bold max-w-[150px]">
                            <span title="<?= htmlspecialchars($rep['repair_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(mb_strimwidth($rep['repair_notes'] ?? '—', 0, 45, '…'), ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-400 font-bold whitespace-nowrap"><?= (new DateTime($rep['created_at']))->format('d/m/y H:i') ?></td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <?php if ($nextStatus): ?>
                                <button onclick="advanceRepair(<?= $rep['id'] ?>, '<?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8') ?>')"
                                    class="px-2.5 py-1.5 text-xs font-black bg-cyan-50 text-cyan-700 hover:bg-cyan-100 rounded-xl transition flex items-center gap-1">
                                    <i class="bi bi-arrow-right-circle"></i> <?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8') ?>
                                </button>
                                <?php endif; ?>
                                <a href="print_slip.php?id=<?= $rep['id'] ?>" target="_blank"
                                    class="p-2 text-blue-400 hover:bg-blue-50 rounded-xl transition" title="พิมพ์ใบแจ้งซ่อม">
                                    <i class="bi bi-printer-fill"></i>
                                </a>
                                <a href="print_liability.php?id=<?= $rep['id'] ?>" target="_blank"
                                    class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition" title="พิมพ์ใบรับรองความรับผิดชอบ">
                                    <i class="bi bi-file-earmark-person-fill"></i>
                                </a>
                                <button onclick="openEdit(<?= $rep['id'] ?>, <?= htmlspecialchars(json_encode($rep['status']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($rep['repair_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)"
                                    class="p-2 text-slate-400 hover:bg-slate-100 rounded-xl transition" title="แก้ไข">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button onclick="doDelete(<?= $rep['id'] ?>)" class="p-2 text-rose-400 hover:bg-rose-50 rounded-xl transition" title="ลบ">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Scroll Navigation Bar ── -->
        <div id="scroll-nav" class="px-4 py-2 bg-slate-50 border-t border-slate-100 flex items-center gap-3">
            <button id="btn-scroll-left"
                onclick="document.getElementById('tbl-wrap').scrollBy({left:-300,behavior:'smooth'})"
                class="flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-xs font-black rounded-xl shadow-sm shadow-cyan-200/50 hover:opacity-90 transition select-none">
                <i class="bi bi-chevron-double-left"></i> เลื่อนซ้าย
            </button>

            <!-- Progress track -->
            <div class="flex-1 relative h-2 bg-slate-200 rounded-full overflow-hidden cursor-pointer" id="scroll-track"
                 onclick="scrollToClick(event)">
                <div id="scroll-thumb"
                     class="absolute top-0 left-0 h-2 bg-gradient-to-r from-cyan-400 to-blue-500 rounded-full transition-all duration-150"
                     style="width:100%"></div>
            </div>

            <button id="btn-scroll-right"
                onclick="document.getElementById('tbl-wrap').scrollBy({left:300,behavior:'smooth'})"
                class="flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-xs font-black rounded-xl shadow-sm shadow-cyan-200/50 hover:opacity-90 transition select-none">
                เลื่อนขวา <i class="bi bi-chevron-double-right"></i>
            </button>
        </div>

    </div>
</div>

<!-- Image Viewer -->
<div id="modal-img" class="modal-bg z-[1300]" onclick="closeModal('modal-img')">
  <div class="relative max-w-4xl mx-4" onclick="event.stopPropagation()">
    <img id="modal-img-src" src="" class="max-w-full max-h-[90vh] object-contain rounded-2xl shadow-2xl">
  </div>
</div>

<!-- Edit Modal -->
<div id="modal-edit" class="modal-bg">
  <div class="modal-box max-w-md">
    <div class="flex items-center justify-between mb-5">
        <h3 class="font-black text-slate-800 text-lg"><i class="bi bi-tools text-orange-500 mr-2"></i>แก้ไขรายการซ่อม</h3>
        <button onclick="closeModal('modal-edit')" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition"><i class="bi bi-x-lg text-sm"></i></button>
    </div>
    <form id="edit-form" class="space-y-4">
        <input type="hidden" id="edit-id">
        <div>
            <label class="block text-xs font-black text-slate-400 uppercase tracking-wider mb-2">สถานะ</label>
            <select id="edit-status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-orange-400">
                <option value="รับแจ้ง">🔔 รับแจ้ง</option>
                <option value="ส่งซ่อม">📦 ส่งซ่อม</option>
                <option value="ซ่อมเสร็จ">✅ ซ่อมเสร็จ</option>
                <option value="รับกลับ">🏫 รับกลับ</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-black text-slate-400 uppercase tracking-wider mb-2">หมายเหตุการซ่อม</label>
            <textarea id="edit-notes" rows="3" placeholder="บันทึกรายละเอียดการซ่อม..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 focus:ring-orange-400"></textarea>
        </div>
        <button type="submit" class="w-full bg-gradient-to-r from-orange-500 to-red-600 text-white py-3 rounded-xl font-black hover:opacity-90 transition">บันทึก</button>
    </form>
  </div>
</div>

<script>
function viewImg(url) { document.getElementById('modal-img-src').src = url; openModal('modal-img'); }

function filterRepairs(status) {
    document.querySelectorAll('.filter-btn').forEach(b => {
        const active = b.dataset.filter === status;
        b.className = `filter-btn px-3 py-1.5 rounded-xl text-sm font-black transition-all ${active ? 'bg-gradient-to-r from-orange-500 to-red-600 text-white shadow-md scale-105' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'}`;
    });
    document.querySelectorAll('#repair-table tbody tr[data-status]').forEach(tr => {
        tr.style.display = (status === 'all' || tr.dataset.status === status) ? '' : 'none';
    });
}

async function apiCall(action, payload={}) {
    const r = await fetch('api.php?action=' + action, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action, payload})
    });
    return r.json();
}

async function advanceRepair(id, nextStatus) {
    const { isConfirmed, value } = await Swal.fire({
        title: `เปลี่ยนสถานะเป็น "${nextStatus}"?`,
        input: 'textarea',
        inputLabel: 'หมายเหตุ (ถ้ามี)',
        inputPlaceholder: 'เช่น ส่งร้านซ่อมแล้ว, ได้รับเครื่องคืนแล้ว...',
        showCancelButton: true,
        confirmButtonColor: '#0891b2',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    });
    if (!isConfirmed) return;
    const res = await apiCall('updateRepairStatus', {repairId: id, status: nextStatus, notes: value||''});
    if (res.success) {
        Swal.fire({icon:'success', title:'อัปเดตสถานะแล้ว', timer:1400, showConfirmButton:false})
            .then(() => location.reload());
    } else Swal.fire('ผิดพลาด', res.error, 'error');
}

function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function openEdit(id, status, notes) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-status').value = status;
    document.getElementById('edit-notes').value = notes;
    openModal('modal-edit');
}

document.getElementById('edit-form').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type=submit]');
    btn.disabled = true; btn.textContent = 'กำลังบันทึก...';
    const res = await apiCall('updateRepairStatus', {
        repairId: document.getElementById('edit-id').value,
        status:   document.getElementById('edit-status').value,
        notes:    document.getElementById('edit-notes').value
    });
    if (res.success) {
        Swal.fire({icon:'success', title:'บันทึกแล้ว', timer:1200, showConfirmButton:false})
            .then(() => location.reload());
    } else { Swal.fire('ผิดพลาด', res.error, 'error'); btn.disabled = false; btn.textContent = 'บันทึก'; }
});

async function doDelete(id) {
    const r = await Swal.fire({title:'ลบรายการซ่อมนี้?', icon:'warning', showCancelButton:true, confirmButtonColor:'#e11d48', confirmButtonText:'ลบ', cancelButtonText:'ยกเลิก'});
    if (!r.isConfirmed) return;
    const res = await apiCall('deleteRepair', {repairId: id});
    if (res.success) {
        Swal.fire({icon:'success', title:'ลบแล้ว', timer:1200, showConfirmButton:false})
            .then(() => location.reload());
    } else Swal.fire('ผิดพลาด', res.error, 'error');
}

// Activate "all" filter on load
document.addEventListener('DOMContentLoaded', () => {
    filterRepairs('all');
    initScrollNav();
});

// ── Scroll Navigation Bar ─────────────────────────────────────
function initScrollNav() {
    const wrap  = document.getElementById('tbl-wrap');
    const thumb = document.getElementById('scroll-thumb');
    const track = document.getElementById('scroll-track');
    const btnL  = document.getElementById('btn-scroll-left');
    const btnR  = document.getElementById('btn-scroll-right');
    if (!wrap || !thumb) return;

    function updateThumb() {
        const ratio = wrap.scrollWidth > wrap.clientWidth
            ? wrap.scrollLeft / (wrap.scrollWidth - wrap.clientWidth)
            : 0;
        const thumbW = Math.max(16, (wrap.clientWidth / wrap.scrollWidth) * 100);
        thumb.style.width = thumbW + '%';
        thumb.style.left  = (ratio * (100 - thumbW)) + '%';

        // dim buttons at edges
        if (btnL) btnL.style.opacity = wrap.scrollLeft < 5 ? '0.4' : '1';
        if (btnR) btnR.style.opacity = wrap.scrollLeft >= wrap.scrollWidth - wrap.clientWidth - 5 ? '0.4' : '1';
    }

    // คลิกบน track เพื่อ jump
    window.scrollToClick = function(e) {
        const rect  = track.getBoundingClientRect();
        const ratio = (e.clientX - rect.left) / rect.width;
        wrap.scrollLeft = ratio * (wrap.scrollWidth - wrap.clientWidth);
    };

    // กดค้างปุ่มเพื่อเลื่อนต่อเนื่อง
    let holdTimer;
    function holdScroll(dir) {
        clearInterval(holdTimer);
        holdTimer = setInterval(() => { wrap.scrollLeft += dir * 20; }, 16);
    }
    function stopHold() { clearInterval(holdTimer); }

    if (btnL) {
        btnL.addEventListener('mousedown', () => holdScroll(-1));
        btnL.addEventListener('touchstart', (e) => { e.preventDefault(); holdScroll(-1); }, {passive:false});
    }
    if (btnR) {
        btnR.addEventListener('mousedown', () => holdScroll(1));
        btnR.addEventListener('touchstart', (e) => { e.preventDefault(); holdScroll(1); }, {passive:false});
    }
    document.addEventListener('mouseup', stopHold);
    document.addEventListener('touchend', stopHold);

    wrap.addEventListener('scroll', updateThumb);
    window.addEventListener('resize', updateThumb);
    updateThumb();
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
