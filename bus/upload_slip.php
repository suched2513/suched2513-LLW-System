<?php
session_start();
require_once __DIR__ . '/config.php';
busRequireStudent();

$pdo      = getPdo();
$busId    = (int)$_SESSION['bus_student_id'];
$name     = $_SESSION['bus_student_name'];
$class    = $_SESSION['bus_student_class'];
$semester = busGetSemester();

$msg = '';

// Fetch active registration with balance
$reg = null; $balance = 0.0;
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.status, rt.price, rt.route_name, rt.route_code
        FROM bus_registrations r
        JOIN bus_routes rt ON rt.id = r.route_id
        WHERE r.student_id = ? AND r.semester = ? AND r.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$busId, $semester]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($reg) {
        $paid    = busGetPaid($pdo, $reg['id']);
        $balance = max(0, (float)$reg['price'] - $paid);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Redirect if nothing to pay
if (!$reg || $balance <= 0.01) {
    header('Location: /bus/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $amount       = (float)($_POST['amount'] ?? 0);
    $transferDate = trim($_POST['transfer_date'] ?? '');
    $note         = trim($_POST['note'] ?? '');
    $file         = $_FILES['slip_image'] ?? null;

    if ($amount <= 0 || $amount > $balance + 0.01) {
        $msg = 'จำนวนเงินไม่ถูกต้อง (ต้องไม่เกิน ' . number_format($balance, 0) . ' บาท)';
    } elseif (empty($transferDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $transferDate)) {
        $msg = 'กรุณาระบุวันที่โอนเงิน';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $errCodes = [
            UPLOAD_ERR_INI_SIZE   => 'ไฟล์ใหญ่เกินกำหนด',
            UPLOAD_ERR_FORM_SIZE  => 'ไฟล์ใหญ่เกินกำหนด',
            UPLOAD_ERR_NO_FILE    => 'กรุณาแนบรูปสลิปการโอนเงิน',
        ];
        $msg = $errCodes[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'กรุณาแนบรูปสลิปการโอนเงิน';
    } else {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo        = new finfo(FILEINFO_MIME_TYPE);
        $mime         = $finfo->file($file['tmp_name']);
        $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extMap       = ['jpg' => true, 'jpeg' => true, 'png' => true, 'gif' => true, 'webp' => true];

        if (!in_array($mime, $allowedMimes, true) || !isset($extMap[$ext])) {
            $msg = 'ไฟล์ต้องเป็นรูปภาพ (JPG, PNG, GIF, WebP) เท่านั้น';
        } elseif ($file['size'] > 8 * 1024 * 1024) {
            $msg = 'ขนาดไฟล์ต้องไม่เกิน 8 MB';
        } else {
            $uploadDir = __DIR__ . '/uploads/slips/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
                file_put_contents($uploadDir . '.htaccess', "Options -Indexes\n<FilesMatch \"\\.php$\">\n    Require all denied\n</FilesMatch>\n");
            }
            $filename = 'slip_' . $busId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                try {
                    $ins = $pdo->prepare("INSERT INTO bus_payment_slips (registration_id, amount, slip_image, transfer_date, note) VALUES (?,?,?,?,?)");
                    $ins->execute([$reg['id'], $amount, $filename, $transferDate, $note ?: null]);
                    header('Location: /bus/dashboard.php?msg=' . urlencode('ส่งสลิปเรียบร้อยแล้ว รอเจ้าหน้าที่ตรวจสอบ') . '&t=ok');
                    exit();
                } catch (Exception $e) {
                    error_log($e->getMessage());
                    @unlink($uploadDir . $filename);
                    $msg = 'เกิดข้อผิดพลาดในการบันทึก กรุณาลองใหม่';
                }
            } else {
                $msg = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
            }
        }
    }
}

header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>แนบสลิปโอนเงิน | รถรับส่ง LLW</title>
<meta name="theme-color" content="#f97316">
<meta name="apple-mobile-web-app-capable" content="yes">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>body { font-family:'Prompt',sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen" style="padding-bottom:env(safe-area-inset-bottom)">

<!-- Header -->
<header class="bg-gradient-to-r from-orange-500 to-amber-500 text-white sticky top-0 z-50 shadow-md" style="padding-top:env(safe-area-inset-top)">
    <div class="max-w-lg mx-auto flex items-center gap-3 px-4 py-3">
        <a href="/bus/dashboard.php"
           class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center border border-white/20 active:bg-white/30 flex-shrink-0">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <div class="font-black text-sm leading-tight">แนบสลิปโอนเงิน</div>
            <div class="text-orange-100 text-[9px] font-bold">ค่าบริการรถรับส่ง</div>
        </div>
    </div>
</header>

<div class="max-w-lg mx-auto px-4 pt-5 pb-10 space-y-4">

    <!-- Route Info Card -->
    <div class="bg-gradient-to-br from-orange-500 to-amber-400 rounded-3xl p-5 text-white shadow-xl shadow-orange-200/60 relative overflow-hidden">
        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/10 rounded-full pointer-events-none"></div>
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center border border-white/30 flex-shrink-0">
                <i class="bi bi-camera-fill text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-black text-base leading-tight truncate"><?= htmlspecialchars($reg['route_name'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-orange-100 text-xs mt-0.5">สาย <?= htmlspecialchars($reg['route_code'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-white font-black text-sm mt-1">ค้างชำระ <?= number_format($balance, 0) ?> บาท</p>
            </div>
        </div>
        <div class="mt-3 pt-3 border-t border-white/20 flex items-center gap-2 text-orange-100 text-xs">
            <i class="bi bi-person-fill"></i>
            <span><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="opacity-60">·</span>
            <span><?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-50">
            <h2 class="font-black text-slate-700">กรอกข้อมูลสลิป</h2>
            <p class="text-[10px] text-slate-400 mt-0.5">แนบรูปสลิปและกรอกข้อมูลการโอนเงิน</p>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-5 space-y-5">
            <?= csrf_field() ?>

            <!-- Amount -->
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">
                    จำนวนเงินที่โอน (บาท) <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input type="number" name="amount" required
                           min="1" max="<?= number_format($balance, 2, '.', '') ?>" step="1"
                           value="<?= number_format($balance, 0, '.', '') ?>"
                           inputmode="numeric"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 pr-12 py-4 text-xl font-black text-slate-700
                                  text-center focus:ring-2 focus:ring-orange-400 focus:border-orange-400 outline-none transition-all">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-bold pointer-events-none">฿</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-1.5 text-center">ยอดค้างชำระ <?= number_format($balance, 0) ?> บาท</p>
            </div>

            <!-- Transfer Date -->
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">
                    วันที่โอนเงิน <span class="text-rose-500">*</span>
                </label>
                <input type="date" name="transfer_date" required
                       value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm font-bold text-slate-700
                              focus:ring-2 focus:ring-orange-400 focus:border-orange-400 outline-none transition-all">
            </div>

            <!-- Slip Image Upload -->
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">
                    รูปสลิปการโอนเงิน <span class="text-rose-500">*</span>
                </label>
                <label for="slipImageInput" id="imageDropZone"
                       class="flex flex-col items-center justify-center gap-3 w-full min-h-[140px] border-2 border-dashed border-slate-200 rounded-2xl cursor-pointer
                              bg-slate-50 hover:bg-orange-50 hover:border-orange-300 active:bg-orange-50 transition-all p-4">
                    <i class="bi bi-camera-fill text-4xl text-slate-300" id="dropIcon"></i>
                    <div class="text-center">
                        <p class="text-sm font-black text-slate-500" id="dropText">แตะเพื่อเลือกรูปภาพ</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">JPG, PNG, WebP · ไม่เกิน 8 MB</p>
                    </div>
                    <input type="file" name="slip_image" id="slipImageInput" required accept="image/*"
                           capture="environment" class="hidden" onchange="previewImage(this)">
                </label>
                <div id="imagePreview" class="hidden mt-3 relative">
                    <img id="previewImg" src="" alt="preview"
                         class="w-full rounded-2xl object-contain max-h-72 border border-slate-200 bg-slate-50">
                    <button type="button" onclick="clearImage()"
                            class="absolute top-2 right-2 w-8 h-8 bg-rose-500 text-white rounded-xl flex items-center justify-center shadow-lg active:bg-rose-600">
                        <i class="bi bi-x-lg text-xs font-bold"></i>
                    </button>
                </div>
            </div>

            <!-- Note -->
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">หมายเหตุ (ไม่บังคับ)</label>
                <textarea name="note" rows="2" maxlength="500"
                          placeholder="เช่น ชำระบางส่วน, ชำระครั้งที่ 1..."
                          class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm resize-none
                                 focus:ring-2 focus:ring-orange-400 focus:border-orange-400 outline-none transition-all"></textarea>
            </div>

            <!-- Submit -->
            <button type="submit"
                    class="w-full py-4 bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-2xl font-black text-sm
                           shadow-xl shadow-orange-200/60 active:scale-95 transition-transform flex items-center justify-center gap-2">
                <i class="bi bi-send-fill text-base"></i> ส่งสลิปโอนเงิน
            </button>
        </form>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-100 rounded-2xl px-4 py-3.5 flex items-start gap-3">
        <i class="bi bi-info-circle-fill text-blue-400 mt-0.5 flex-shrink-0"></i>
        <div class="text-xs text-blue-700 space-y-1">
            <p class="font-black">ขั้นตอนหลังจากส่งสลิป</p>
            <p>1. เจ้าหน้าที่การเงินจะตรวจสอบสลิปของคุณ</p>
            <p>2. เมื่ออนุมัติแล้ว ยอดชำระจะอัปเดตโดยอัตโนมัติ</p>
            <p>3. หากมีปัญหา เจ้าหน้าที่จะแจ้งเหตุผลกลับมา</p>
        </div>
    </div>

</div>

<?php if ($msg): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'error',
        title: 'ผิดพลาด',
        text: <?= json_encode($msg) ?>,
        confirmButtonColor: '#f97316',
        customClass: { popup: 'rounded-[2rem]', confirmButton: 'rounded-xl' }
    });
});
</script>
<?php endif; ?>

<script>
function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('imagePreview').classList.remove('hidden');
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('imageDropZone').classList.add('hidden');
    };
    reader.readAsDataURL(input.files[0]);
}
function clearImage() {
    document.getElementById('slipImageInput').value = '';
    document.getElementById('imagePreview').classList.add('hidden');
    document.getElementById('imageDropZone').classList.remove('hidden');
}
</script>
</body>
</html>
