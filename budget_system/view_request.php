<?php
require_once 'config.php';
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$id = $_GET['id'] ?? 0;
$db = connectDB();

// Fetch Disbursement Data
$stmt = $db->prepare("
    SELECT d.*, p.project_name, p.fiscal_year, fs.source_name, u.firstname, u.lastname, dept.dept_name
    FROM budget_disbursements d
    JOIN budget_projects p ON d.project_id = p.project_id
    LEFT JOIN wfh_departments dept ON p.department_id = dept.dept_id
    LEFT JOIN budget_fund_sources fs ON d.fund_source_id = fs.source_id
    LEFT JOIN llw_users u ON d.requested_by = u.user_id
    WHERE d.disbursement_id = ?
");
$stmt->execute([$id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    die("ไม่พบข้อมูลคำขอ");
}

// Fetch Items
$stmtItems = $db->prepare("SELECT * FROM budget_disbursement_items WHERE disbursement_id = ? ORDER BY item_id ASC");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Handle Approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    if (!isAdmin()) die("Unauthorized");
    
    try {
        $db->beginTransaction();
        
        // 1. Update Disbursement Status
        $stmt = $db->prepare("UPDATE budget_disbursements SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE disbursement_id = ?");
        $stmt->execute([$_SESSION['user_id'], $id]);
        
        // 2. Create Transaction (Expense)
        $stmtTrans = $db->prepare("
            INSERT INTO budget_transactions (project_id, amount, transaction_type, description, transaction_date, created_by)
            VALUES (?, ?, 'expense', ?, CURDATE(), ?)
        ");
        $desc = "เบิกจ่ายตามคำขอ #" . $id . " (" . $req['activity_name'] . ")";
        $stmtTrans->execute([$req['project_id'], $req['total_amount'], $desc, $_SESSION['user_id']]);
        
        $db->commit();
        $_SESSION['alert'] = ['type' => 'success', 'message' => 'อนุมัติการเบิกจ่ายและหักงบประมาณเรียบร้อยแล้ว'];
        header("Location: view_request.php?id=$id");
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['alert'] = ['type' => 'danger', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

$pageTitle = 'รายละเอียดคำขออนุมัติ';
$pageSubtitle = 'ตรวจสอบสถานะและสั่งพิมพ์เอกสาร';
$activeSystem = 'budget';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Status Header -->
    <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-6 text-center md:text-left">
            <div class="w-16 h-16 rounded-3xl <?php echo getStatusBadgeClass($req['status']); ?> flex items-center justify-center text-3xl border-2">
                <i class="bi <?php echo $req['status'] === 'approved' ? 'bi-patch-check-fill' : ($req['status'] === 'rejected' ? 'bi-x-circle-fill' : 'bi-hourglass-split'); ?>"></i>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">สถานะปัจจุบัน</p>
                <h2 class="text-2xl font-black text-slate-800 uppercase"><?php echo getStatusDisplay($req['status']); ?></h2>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="print_request.php?id=<?php echo $id; ?>" target="_blank" class="px-8 py-4 bg-blue-600 text-white rounded-2xl font-black uppercase tracking-widest shadow-lg shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] transition-all flex items-center gap-2">
                <i class="bi bi-printer-fill"></i> พิมพ์บันทึกข้อความ
            </a>
            <?php if (isAdmin() && $req['status'] === 'pending'): ?>
                <button onclick="approveRequest(<?php echo $id; ?>)" class="px-8 py-4 bg-emerald-500 text-white rounded-2xl font-black uppercase tracking-widest shadow-lg shadow-emerald-200 hover:bg-emerald-600 hover:scale-[1.02] transition-all">อนุมัติ</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Main Details -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8">
                <h3 class="text-lg font-black text-slate-800 mb-6 flex items-center gap-3">
                    <i class="bi bi-info-circle text-blue-600"></i> รายละเอียดการขอใช้เงิน
                </h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">โครงการ</p>
                            <p class="text-sm font-bold text-slate-700"><?php echo h($req['project_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">กิจกรรม</p>
                            <p class="text-sm font-bold text-slate-700"><?php echo h($req['activity_name']); ?></p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">เหตุผลความจำเป็น</p>
                        <p class="text-sm text-slate-600 mt-1"><?php echo nl2br(h($req['reason'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-50">
                    <h3 class="text-lg font-black text-slate-800">รายการพัสดุ/จ้าง</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50/50">
                            <tr>
                                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">รายการ</th>
                                <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">จำนวน</th>
                                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">ราคา</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="px-8 py-4 text-sm font-bold text-slate-700"><?php echo h($item['item_name']); ?></td>
                                    <td class="px-4 py-4 text-sm text-slate-500 text-center"><?php echo number_format($item['quantity'], 2); ?> <?php echo h($item['unit']); ?></td>
                                    <td class="px-8 py-4 text-sm font-black text-slate-800 text-right">฿<?php echo number_format($item['total_price'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-slate-50/50 font-black">
                            <tr>
                                <td colspan="2" class="px-8 py-5 text-right text-slate-500 uppercase text-[10px]">รวมทั้งสิ้น</td>
                                <td class="px-8 py-5 text-right text-indigo-600 text-lg italic">฿<?php echo number_format($req['total_amount'], 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Meta Info Sidebar -->
        <div class="space-y-6">
            <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8">
                <h3 class="text-sm font-black text-slate-800 mb-6 uppercase tracking-widest">ข้อมูลการทำรายการ</h3>
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 bg-slate-100 text-slate-400 rounded-xl flex items-center justify-center text-sm"><i class="bi bi-person"></i></div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">ผู้ขอใช้</p>
                            <p class="text-xs font-bold text-slate-700"><?php echo h($req['firstname'] . ' ' . $req['lastname']); ?></p>
                            <p class="text-[9px] text-slate-400 font-bold"><?php echo h($req['dept_name']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 bg-slate-100 text-slate-400 rounded-xl flex items-center justify-center text-sm"><i class="bi bi-calendar-event"></i></div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">วันที่ยื่นขอ</p>
                            <p class="text-xs font-bold text-slate-700"><?php echo date('d M Y', strtotime($req['request_date'])); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-8 h-8 bg-slate-100 text-slate-400 rounded-xl flex items-center justify-center text-sm"><i class="bi bi-database"></i></div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">แหล่งเงินทุน</p>
                            <p class="text-xs font-bold text-blue-600"><?php echo h($req['source_name']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Helpful Tips -->
            <div class="bg-indigo-600 rounded-[2rem] p-8 text-white">
                <i class="bi bi-lightbulb text-3xl opacity-50 mb-4 block"></i>
                <p class="text-sm font-bold leading-relaxed">เมื่อ ผอ. เซ็นชื่อในบันทึกข้อความแล้ว กรุณามากดปุ่ม "อนุมัติ" ในระบบเพื่อปรับปรุงสถานะงบประมาณให้ถูกต้องครับ</p>
            </div>
        </div>
    </div>
</div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="bg-<?php echo $_SESSION['alert']['type'] === 'success' ? 'emerald' : 'rose'; ?>-50 border border-<?php echo $_SESSION['alert']['type'] === 'success' ? 'emerald' : 'rose'; ?>-100 text-<?php echo $_SESSION['alert']['type'] === 'success' ? 'emerald' : 'rose'; ?>-700 px-6 py-4 rounded-2xl flex items-center justify-between mb-6">
            <p class="text-sm font-bold"><i class="bi bi-info-circle-fill mr-2"></i> <?php echo $_SESSION['alert']['message']; ?></p>
            <button onclick="this.parentElement.remove()" class="text-lg opacity-50 hover:opacity-100">&times;</button>
        </div>
        <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>

<script>
function approveRequest(id) {
    Swal.fire({
        title: 'ยืนยันการอนุมัติ?',
        text: "สถานะจะเปลี่ยนเป็นอนุมัติและหักงบประมาณโครงการทันที",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ยืนยันอนุมัติ',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-[2rem]' }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="approve">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
