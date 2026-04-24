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

// Handle Workflow Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $db->beginTransaction();
        $action = $_POST['action'];
        $now = date('Y-m-d H:i:s');
        $user_id = $_SESSION['user_id'];

        switch ($action) {
            case 'sign_project':
                $stmt = $db->prepare("UPDATE budget_disbursements SET current_step = 'pending_plan', project_head_id = ?, project_head_signed_at = ? WHERE disbursement_id = ?");
                $stmt->execute([$user_id, $now, $id]);
                break;
                
            case 'sign_plan':
                $stmt = $db->prepare("UPDATE budget_disbursements SET current_step = 'pending_procurement', plan_head_id = ?, plan_head_signed_at = ?, plan_budget_total = ?, plan_budget_used = ?, plan_budget_remain = ?, plan_is_in_plan = ? WHERE disbursement_id = ?");
                $stmt->execute([$user_id, $now, $_POST['plan_budget_total'], $_POST['plan_budget_used'], $_POST['plan_budget_remain'], $_POST['plan_is_in_plan'], $id]);
                break;
                
            case 'sign_procurement':
                $stmt = $db->prepare("UPDATE budget_disbursements SET current_step = 'pending_finance', procurement_head_id = ?, procurement_head_signed_at = ?, procurement_result = ? WHERE disbursement_id = ?");
                $stmt->execute([$user_id, $now, $_POST['procurement_result'], $id]);
                break;
                
            case 'sign_finance':
                $stmt = $db->prepare("UPDATE budget_disbursements SET current_step = 'pending_deputy', finance_head_id = ?, finance_head_signed_at = ? WHERE disbursement_id = ?");
                $stmt->execute([$user_id, $now, $id]);
                break;
                
            case 'sign_deputy':
                $stmt = $db->prepare("UPDATE budget_disbursements SET current_step = 'pending_director', deputy_id = ?, deputy_signed_at = ?, deputy_comment = ?, deputy_result = 'approved' WHERE disbursement_id = ?");
                $stmt->execute([$user_id, $now, $_POST['deputy_comment'], $id]);
                break;
                
            case 'sign_director':
                $stmt = $db->prepare("UPDATE budget_disbursements SET status = 'approved', current_step = 'completed', director_id = ?, director_signed_at = ?, director_result = 'approved' WHERE disbursement_id = ?");
                $stmt->execute([$user_id, $now, $id]);
                
                // Final approval: Record transaction (Expense)
                $stmtTrans = $db->prepare("INSERT INTO budget_transactions (project_id, amount, transaction_type, description, transaction_date, created_by) VALUES (?, ?, 'expense', ?, CURDATE(), ?)");
                $desc = "เบิกจ่ายตามคำขอ #" . $id . " (" . $req['activity_name'] . ")";
                $stmtTrans->execute([$req['project_id'], $req['total_amount'], $desc, $user_id]);
                break;
        }
        
        $db->commit();
        $_SESSION['alert'] = ['type' => 'success', 'message' => 'บันทึกการอนุมัติเรียบร้อยแล้ว'];
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

<div class="max-w-6xl mx-auto space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Request Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8">
                <div class="flex justify-between items-start mb-8">
                    <div>
                        <h3 class="text-xl font-black text-slate-800">รายละเอียดคำขอเบิกจ่าย</h3>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">คำขอเลขที่: #<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?></p>
                    </div>
                    <div class="text-right">
                        <span class="px-4 py-2 rounded-full font-bold text-xs <?php echo getStatusBadgeClass($req['status']); ?>">
                            <?php echo getStatusDisplay($req['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">โครงการ</p>
                            <p class="text-sm font-bold text-slate-700"><?php echo h($req['project_name']); ?></p>
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">กิจกรรม</p>
                            <p class="text-sm font-bold text-slate-700"><?php echo h($req['activity_name']); ?></p>
                        </div>
                    </div>

                    <div class="overflow-hidden border border-slate-100 rounded-2xl">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">รายการพัสดุ/จ้าง</th>
                                    <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">จำนวน</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">รวมเงิน</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="px-6 py-4 text-sm font-bold text-slate-700"><?php echo h($item['item_name']); ?></td>
                                    <td class="px-4 py-4 text-sm text-slate-500 text-center"><?php echo number_format($item['quantity'], 2); ?> <?php echo h($item['unit']); ?></td>
                                    <td class="px-6 py-4 text-sm font-black text-slate-800 text-right">฿<?php echo number_format($item['total_price'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-blue-50/30">
                                <tr>
                                    <td colspan="2" class="px-6 py-4 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">รวมยอดเงินทั้งสิ้น</td>
                                    <td class="px-6 py-4 text-right text-blue-600 text-xl font-black">฿<?php echo number_format($req['total_amount'], 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">เหตุผลความจำเป็น</p>
                        <p class="text-sm text-slate-600 leading-relaxed"><?php echo nl2br(h($req['reason'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Workflow Timeline -->
            <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8">
                <h3 class="text-lg font-black text-slate-800 mb-8 flex items-center gap-3">
                    <i class="bi bi-diagram-3-fill text-indigo-600"></i> บันทึกการอนุมัติ (Workflow)
                </h3>
                <div class="relative space-y-8">
                    <?php 
                    $steps = [
                        ['id' => 'pending_project', 'label' => 'ผู้ขอใช้/ผู้รับผิดชอบโครงการ', 'signed_at' => $req['project_head_signed_at'], 'signer_id' => $req['project_head_id']],
                        ['id' => 'pending_plan', 'label' => 'หัวหน้างานแผนงาน', 'signed_at' => $req['plan_head_signed_at'], 'signer_id' => $req['plan_head_id']],
                        ['id' => 'pending_procurement', 'label' => 'หัวหน้างานพัสดุ', 'signed_at' => $req['procurement_head_signed_at'], 'signer_id' => $req['procurement_head_id']],
                        ['id' => 'pending_finance', 'label' => 'หัวหน้างานการเงิน', 'signed_at' => $req['finance_head_signed_at'], 'signer_id' => $req['finance_head_id']],
                        ['id' => 'pending_deputy', 'label' => 'รองผู้อำนวยการโรงเรียน', 'signed_at' => $req['deputy_signed_at'], 'signer_id' => $req['deputy_id']],
                        ['id' => 'pending_director', 'label' => 'ผู้อำนวยการโรงเรียน', 'signed_at' => $req['director_signed_at'], 'signer_id' => $req['director_id']],
                    ];

                    foreach ($steps as $idx => $s): 
                        $is_done = !empty($s['signed_at']);
                        $is_current = ($req['current_step'] === $s['id'] && $req['status'] === 'pending');
                    ?>
                    <div class="flex gap-6 relative">
                        <?php if ($idx < count($steps) - 1): ?>
                        <div class="absolute left-4 top-10 w-0.5 h-8 bg-slate-100"></div>
                        <?php endif; ?>
                        
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-black z-10 
                            <?php echo $is_done ? 'bg-emerald-500 text-white' : ($is_current ? 'bg-blue-600 text-white ring-4 ring-blue-100' : 'bg-slate-100 text-slate-300'); ?>">
                            <?php if ($is_done): ?><i class="bi bi-check-lg"></i><?php else: ?><?php echo $idx + 1; ?><?php endif; ?>
                        </div>
                        
                        <div class="flex-1">
                            <div class="flex justify-between items-center">
                                <h4 class="font-bold <?php echo $is_done ? 'text-slate-800' : ($is_current ? 'text-blue-600' : 'text-slate-400'); ?>">
                                    <?php echo $s['label']; ?>
                                </h4>
                                <?php if ($is_done): ?>
                                    <span class="text-[10px] font-black text-emerald-500 uppercase bg-emerald-50 px-2 py-1 rounded-md">
                                        <i class="bi bi-clock-fill"></i> <?php echo date('d/m/Y H:i', strtotime($s['signed_at'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($is_done): ?>
                                <p class="text-[11px] text-slate-500 mt-1 italic">อนุมัติโดยระบบอิเล็กทรอนิกส์</p>
                            <?php elseif ($is_current): ?>
                                <p class="text-[11px] text-blue-500 font-bold mt-1 uppercase tracking-wider animate-pulse">กำลังรอการพิจารณา...</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Approval Action Sidebar -->
        <div class="space-y-6">
            <?php if ($req['status'] === 'pending'): ?>
            <div class="bg-white rounded-[2rem] shadow-xl shadow-blue-100/50 border-2 border-blue-600 p-8 sticky top-6">
                <h3 class="text-sm font-black text-slate-800 mb-6 uppercase tracking-widest flex items-center gap-2">
                    <i class="bi bi-shield-check text-blue-600"></i> ส่วนการดำเนินการ
                </h3>

                <form action="" method="POST" class="space-y-6">
                    <?php if ($req['current_step'] === 'pending_project'): ?>
                        <div class="p-4 bg-blue-50 rounded-2xl text-blue-700 text-xs font-bold leading-relaxed">
                            กรุณาตรวจสอบความถูกต้องของรายการและเหตุผลความจำเป็นก่อนส่งงานให้ฝ่ายแผนงาน
                        </div>
                        <input type="hidden" name="action" value="sign_project">
                        <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black uppercase tracking-widest shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">ยืนยันข้อมูลคำขอ</button>

                    <?php elseif ($req['current_step'] === 'pending_plan'): ?>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">ความเห็นแผนงาน</label>
                                <select name="plan_is_in_plan" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold">
                                    <option value="1">อยู่ในแผนงาน</option>
                                    <option value="0">ไม่อยู่ในแผนงาน</option>
                                </select>
                            </div>
                            <input type="hidden" name="plan_budget_total" value="<?php echo $req['total_budget']; ?>">
                            <input type="hidden" name="plan_budget_used" value="0">
                            <input type="hidden" name="plan_budget_remain" value="<?php echo $req['total_budget']; ?>">
                            <input type="hidden" name="action" value="sign_plan">
                            <button type="submit" class="w-full bg-indigo-600 text-white py-4 rounded-2xl font-black uppercase tracking-widest shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all">บันทึกงานแผนงาน</button>
                        </div>

                    <?php elseif ($req['current_step'] === 'pending_procurement'): ?>
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">ผลการตรวจสอบพัสดุ</label>
                            <select name="procurement_result" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold">
                                <option value="can_buy">จัดซื้อ/จัดจ้างได้</option>
                                <option value="cannot_buy">ไม่สามารถจัดซื้อ/จัดจ้างได้</option>
                            </select>
                            <input type="hidden" name="action" value="sign_procurement">
                            <button type="submit" class="w-full bg-emerald-600 text-white py-4 rounded-2xl font-black uppercase tracking-widest shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all">บันทึกงานพัสดุ</button>
                        </div>

                    <?php elseif ($req['current_step'] === 'pending_finance'): ?>
                        <p class="text-xs text-slate-500 font-bold text-center">ฝ่ายการเงินตรวจสอบแหล่งเงินทุน</p>
                        <input type="hidden" name="action" value="sign_finance">
                        <button type="submit" class="w-full bg-rose-600 text-white py-4 rounded-2xl font-black uppercase tracking-widest shadow-lg shadow-rose-200 hover:bg-rose-700 transition-all">ยืนยันงานการเงิน</button>

                    <?php elseif ($req['current_step'] === 'pending_deputy'): ?>
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">ความเห็นรองผู้อำนวยการ</label>
                            <textarea name="deputy_comment" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold h-24" placeholder="ระบุความเห็น..."></textarea>
                            <input type="hidden" name="action" value="sign_deputy">
                            <button type="submit" class="w-full bg-orange-500 text-white py-4 rounded-2xl font-black uppercase tracking-widest shadow-lg shadow-orange-200 hover:bg-orange-600 transition-all">บันทึกความเห็นรอง ผอ.</button>
                        </div>

                    <?php elseif ($req['current_step'] === 'pending_director'): ?>
                        <p class="text-sm font-bold text-slate-700 text-center mb-4">ผู้อำนวยการพิจารณาอนุมัติ</p>
                        <input type="hidden" name="action" value="sign_director">
                        <button type="submit" class="w-full bg-blue-700 text-white py-5 rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-blue-200 hover:bg-blue-800 hover:scale-[1.02] transition-all">อนุมัติในหลักการ</button>
                    <?php endif; ?>
                </form>

                <p class="text-[9px] text-slate-400 mt-6 text-center leading-relaxed">
                    * การกดยืนยันมีผลทางกฎหมายเทียบเท่าการลงลายมือชื่อ<br>ตาม พ.ร.บ. ว่าด้วยธุรกรรมทางอิเล็กทรอนิกส์
                </p>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100/50 border border-slate-100 p-8">
                <h3 class="text-sm font-black text-slate-800 mb-6 uppercase tracking-widest">เครื่องมือเอกสาร</h3>
                <a href="print_request.php?id=<?php echo $id; ?>" target="_blank" class="flex items-center justify-center gap-3 w-full bg-slate-800 text-white py-4 rounded-2xl font-black uppercase tracking-widest hover:bg-slate-900 transition-all shadow-lg shadow-slate-200">
                    <i class="bi bi-printer-fill"></i> พิมพ์บันทึกข้อความ
                </a>
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
