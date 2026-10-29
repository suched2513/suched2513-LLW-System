<?php
/**
 * room_booking/admin/rooms.php
 * หน้าจัดการห้องประชุม (เพิ่ม แก้ไข ปิด/เปิดใช้งานแบบ Soft Delete)
 */

declare(strict_types=1);

$pageTitle = 'จัดการห้องประชุม';
require_once __DIR__ . '/../includes/functions.php';

requireRoomAdmin();

$pdo = getRoomBookingPdo();

// ประมวลผลการเพิ่ม / แก้ไข / ปิดใช้งาน
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $roomCode   = trim((string)$_POST['room_code']);
        $roomName   = trim((string)$_POST['room_name']);
        $building   = trim((string)$_POST['building']);
        $floor      = trim((string)$_POST['floor']);
        $capacity   = (int)$_POST['capacity'];
        $facilities = trim((string)$_POST['facilities']);
        $colorCode  = trim((string)$_POST['color_code']);

        if (!$roomCode || !$roomName) {
            $err = 'กรุณาระบุรหัสห้องและชื่อห้องประชุม';
        } else {
            if ($action === 'create') {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO rb_rooms (room_code, room_name, building, floor, capacity, facilities, color_code, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$roomCode, $roomName, $building, $floor, $capacity, $facilities, $colorCode]);
                    logRoomBookingAudit($pdo, 'create_room', 'rb_rooms', (int)$pdo->lastInsertId(), "สร้างห้องประชุมใหม่: {$roomName}");
                    $msg = 'เพิ่มห้องประชุมใหม่เรียบร้อยแล้ว';
                } catch (\Throwable $e) {
                    $err = 'เกิดข้อผิดพลาด: รหัสห้องนี้อาจมีอยู่แล้วในระบบ';
                }
            } else {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("
                    UPDATE rb_rooms SET
                        room_code = ?, room_name = ?, building = ?, floor = ?,
                        capacity = ?, facilities = ?, color_code = ?
                    WHERE id = ?
                ");
                $stmt->execute([$roomCode, $roomName, $building, $floor, $capacity, $facilities, $colorCode, $id]);
                logRoomBookingAudit($pdo, 'edit_room', 'rb_rooms', $id, "แก้ไขข้อมูลห้องประชุม: {$roomName}");
                $msg = 'บันทึกการแก้ไขเรียบร้อยแล้ว';
            }
        }
    } elseif ($action === 'toggle_active') {
        $id = (int)$_POST['id'];
        $active = (int)$_POST['is_active'] === 1 ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE rb_rooms SET is_active = ? WHERE id = ?");
        $stmt->execute([$active, $id]);
        $stateText = $active === 1 ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
        logRoomBookingAudit($pdo, 'toggle_room', 'rb_rooms', $id, "เปลี่ยนสถานะห้องเป็น: {$stateText}");
        $msg = "{$stateText}ห้องประชุมเรียบร้อยแล้ว";
    }
}

require_once __DIR__ . '/../includes/header.php';

// ดึงรายการห้องทั้งหมด (รวมทั้งที่เปิดและปิดใช้งาน)
$rooms = $pdo->query("SELECT * FROM rb_rooms ORDER BY is_active DESC, id ASC")->fetchAll();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="index.php" class="hover:text-blue-600">แผงควบคุม</a>
                <span>/</span>
                <span class="text-slate-800 font-medium">ห้องประชุม</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">จัดการข้อมูลห้องประชุมและห้องพิเศษ</h1>
        </div>
        <button onclick="openCreateModal()" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition">
            <i class="bi bi-plus-lg"></i> เพิ่มห้องประชุมใหม่
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 px-4 py-3 rounded-2xl text-xs font-semibold">
            ✓ <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="bg-rose-50 text-rose-800 border border-rose-200 px-4 py-3 rounded-2xl text-xs font-semibold">
            ✕ <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>

    <!-- Rooms Grid Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($rooms as $r): ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between transition hover:shadow-md <?= $r['is_active'] ? '' : 'opacity-60 bg-slate-50' ?>">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold text-white shadow-sm" style="background-color: <?= htmlspecialchars($r['color_code']) ?>;">
                            <?= htmlspecialchars($r['room_code']) ?>
                        </span>
                        <?php if ($r['is_active']): ?>
                            <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                ✓ พร้อมใช้งาน
                            </span>
                        <?php else: ?>
                            <span class="text-[11px] font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200">
                                ✕ ปิดใช้งานชั่วคราว
                            </span>
                        <?php endif; ?>
                    </div>

                    <h3 class="text-base font-bold text-slate-900 leading-snug"><?= htmlspecialchars($r['room_name']) ?></h3>
                    
                    <div class="mt-2 text-xs text-slate-500 space-y-1">
                        <div><i class="bi bi-geo-alt text-blue-600 mr-1"></i><?= htmlspecialchars($r['building'] ?: '-') ?> <?= htmlspecialchars($r['floor'] ?: '') ?></div>
                        <div><i class="bi bi-people text-blue-600 mr-1"></i>ความจุ: <strong class="text-slate-800"><?= $r['capacity'] ?></strong> คน</div>
                    </div>

                    <?php if ($r['facilities']): ?>
                        <div class="mt-3 pt-3 border-t border-slate-100 text-[11px] text-slate-600">
                            <strong>สิ่งอำนวยความสะดวก:</strong><br>
                            <span class="line-clamp-2 mt-0.5 text-slate-500"><?= htmlspecialchars($r['facilities']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                    <button onclick='openEditModal(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)' class="px-3 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-medium transition">
                        <i class="bi bi-pencil mr-1"></i> แก้ไข
                    </button>

                    <form method="POST" onsubmit="return confirm('ยืนยันเปลี่ยนสถานะการใช้งานห้องนี้?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="is_active" value="<?= $r['is_active'] ?>">
                        <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-semibold transition <?= $r['is_active'] ? 'text-rose-600 hover:bg-rose-50 border border-rose-200' : 'text-emerald-700 hover:bg-emerald-50 border border-emerald-200' ?>">
                            <?= $r['is_active'] ? 'ปิดใช้งาน (Soft Delete)' : 'เปิดใช้งาน' ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Create / Edit Room -->
<div id="roomModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 id="modalTitle" class="text-lg font-bold text-slate-900">เพิ่มห้องประชุมใหม่</h3>
            <button onclick="closeRoomModal()" class="text-slate-400 hover:text-slate-600">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>

        <form method="POST" class="py-4 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="roomId" value="">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">รหัสห้อง <span class="text-rose-500">*</span></label>
                    <input type="text" name="room_code" id="room_code" placeholder="เช่น R06" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">สีประจำห้องบนปฏิทิน</label>
                    <input type="color" name="color_code" id="color_code" value="#3b82f6" class="w-full h-9 p-1 rounded-xl border border-slate-200 bg-slate-50 cursor-pointer">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">ชื่อห้องประชุม <span class="text-rose-500">*</span></label>
                <input type="text" name="room_name" id="room_name" placeholder="เช่น ห้องประชุมเกียรติยศ" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">อาคาร</label>
                    <input type="text" name="building" id="building" placeholder="เช่น อาคาร 1" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">ชั้น</label>
                    <input type="text" name="floor" id="floor" placeholder="เช่น ชั้น 2" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">ความจุ (คน) <span class="text-rose-500">*</span></label>
                    <input type="number" name="capacity" id="capacity" value="30" min="1" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">สิ่งอำนวยความสะดวกและอุปกรณ์</label>
                <textarea name="facilities" id="facilities" rows="2" placeholder="เช่น โปรเจกเตอร์, จอ LED, เครื่องปรับอากาศ, ไมค์ลอย 2 ตัว" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="closeRoomModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-xs font-medium">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'เพิ่มห้องประชุมใหม่';
    document.getElementById('formAction').value = 'create';
    document.getElementById('roomId').value = '';
    document.getElementById('room_code').value = '';
    document.getElementById('room_name').value = '';
    document.getElementById('building').value = '';
    document.getElementById('floor').value = '';
    document.getElementById('capacity').value = '30';
    document.getElementById('facilities').value = '';
    document.getElementById('color_code').value = '#3b82f6';
    document.getElementById('roomModal').classList.remove('hidden');
}

function openEditModal(room) {
    document.getElementById('modalTitle').textContent = 'แก้ไขข้อมูลห้องประชุม';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('roomId').value = room.id;
    document.getElementById('room_code').value = room.room_code;
    document.getElementById('room_name').value = room.room_name;
    document.getElementById('building').value = room.building || '';
    document.getElementById('floor').value = room.floor || '';
    document.getElementById('capacity').value = room.capacity || 30;
    document.getElementById('facilities').value = room.facilities || '';
    document.getElementById('color_code').value = room.color_code || '#3b82f6';
    document.getElementById('roomModal').classList.remove('hidden');
}

function closeRoomModal() {
    document.getElementById('roomModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
