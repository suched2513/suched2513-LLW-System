<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = 'สรุปผลการดำเนินงาน';
$pageSubtitle = 'รายงานสรุปผล ประเมินความสำเร็จ และแนบภาพกิจกรรม';
$activeSystem = 'budget';

$disbursementId = (int)($_GET['id'] ?? 0);

try {
    $pdo = getPdo();
    
    // Fetch disbursement details to pre-fill
    $stmt = $pdo->prepare("
        SELECT d.*, a.activity_name, p.project_name 
        FROM sbms_disbursements d
        JOIN sbms_activities a ON d.activity_id = a.id
        JOIN sbms_projects p ON a.project_id = p.id
        WHERE d.id = ?
    ");
    $stmt->execute([$disbursementId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        die('ไม่พบข้อมูลการขออนุญาตที่อ้างถึง');
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    die('เกิดข้อผิดพลาดในการดึงข้อมูล');
}

require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="max-w-5xl mx-auto">
    <div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-white/50 overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-8 text-white">
            <h3 class="text-xl font-black flex items-center gap-3">
                <i class="bi bi-file-earmark-check"></i> แบบฟอร์มสรุปโครงการ/กิจกรรม
            </h3>
            <p class="text-emerald-100 text-sm mt-2 opacity-80">
                อ้างอิงกิจกรรม: <?= htmlspecialchars($request['activity_name']) ?> (<?= htmlspecialchars($request['project_name']) ?>)
            </p>
        </div>
        
        <form id="summaryForm" action="api/save_summary.php" method="POST" enctype="multipart/form-data" class="p-8 sm:p-12 space-y-10">
            <input type="hidden" name="disbursement_id" value="<?= $disbursementId ?>">
            <input type="hidden" name="project_id" value="<?= $request['project_id'] ?>">
            <input type="hidden" name="activity_id" value="<?= $request['activity_id'] ?>">

            <!-- Section 1: ข้อมูลเบื้องต้น -->
            <div class="space-y-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-black">1</span>
                    <h4 class="text-lg font-black text-slate-800">รายละเอียดสรุปโครงการ</h4>
                </div>
                
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ลักษณะของโครงการ</label>
                        <select name="project_type" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                            <option value="">-- กรุณาเลือกรายการ --</option>
                            <option value="โครงการใหม่">โครงการใหม่</option>
                            <option value="โครงการต่อเนื่อง">โครงการต่อเนื่อง</option>
                            <option value="โครงการพิเศษ / เฉพาะกิจ">โครงการพิเศษ / เฉพาะกิจ</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">วัตถุประสงค์ของโครงการ</label>
                        <textarea name="objectives" rows="3" required placeholder="ระบุวัตถุประสงค์ของโครงการ..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all"></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: การประเมินผล -->
            <div class="space-y-6 pt-6 border-t border-slate-100">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-black">2</span>
                    <h4 class="text-lg font-black text-slate-800">รายละเอียดการดำเนินงาน (ระดับความสำเร็จ)</h4>
                </div>
                
                <div class="grid grid-cols-1 gap-6">
                    <?php
                    $evalFields = [
                        'eval_objective' => '1. การดำเนินการบรรลุตามวัตถุประสงค์',
                        'eval_cooperation' => '2. ความร่วมมือของบุคลากรและผู้มีส่วนเกี่ยวข้อง',
                        'eval_interest' => '3. ความสนใจของผู้เข้าร่วมกิจกรรมของโครงการ',
                        'eval_benefit' => '4. ประโยชน์ที่ได้รับ',
                        'eval_success' => '5. ความสำเร็จของการดำเนินโครงการ'
                    ];
                    foreach ($evalFields as $name => $label):
                    ?>
                    <div>
                        <label class="text-sm font-bold text-slate-600 mb-2 block"><?= $label ?></label>
                        <select name="<?= $name ?>" required class="w-full bg-emerald-50/50 border border-emerald-100 rounded-2xl px-6 py-3 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                            <option value="">-- กรุณาเลือก ระดับความสำเร็จ --</option>
                            <option value="มากที่สุด">มากที่สุด</option>
                            <option value="มาก">มาก</option>
                            <option value="ปานกลาง">ปานกลาง</option>
                            <option value="น้อย">น้อย</option>
                            <option value="น้อยที่สุด">น้อยที่สุด</option>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Section 3: ปัญหาและข้อเสนอแนะ -->
            <div class="space-y-6 pt-6 border-t border-slate-100">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-black">3</span>
                    <h4 class="text-lg font-black text-slate-800">ปัญหา อุปสรรค และข้อเสนอแนะ</h4>
                </div>
                
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ปัญหาอุปสรรค</label>
                        <textarea name="problems" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all"></textarea>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ความคิดเห็น / ข้อเสนอแนะ</label>
                        <textarea name="suggestions" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all"></textarea>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">สรุปผลการประเมิน</label>
                        <select name="conclusion" required class="w-full bg-rose-50 border border-rose-100 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-rose-500 outline-none transition-all font-bold text-rose-700">
                            <option value="">-- กรุณาเลือกรายการ --</option>
                            <option value="เห็นสมควรดำเนินการต่อไป">เห็นสมควรดำเนินการต่อไป</option>
                            <option value="ไม่สมควรดำเนินการต่อไป">ไม่สมควรดำเนินการต่อไป</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 4: ภาพกิจกรรม -->
            <div class="space-y-6 pt-6 border-t border-slate-100">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-black">4</span>
                    <h4 class="text-lg font-black text-slate-800">แนบภาพกิจกรรม (สูงสุด 4 ภาพ)</h4>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php for($i=1; $i<=4; $i++): ?>
                    <div class="p-6 bg-slate-50 rounded-3xl border border-slate-200 border-dashed">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">ภาพกิจกรรมที่ <?= $i ?></label>
                        <input type="file" name="image<?= $i ?>" accept="image/*" class="text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-black file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="pt-10">
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-6 rounded-[2rem] font-black text-lg shadow-2xl shadow-emerald-200 hover:scale-[1.02] transition-all flex items-center justify-center gap-4">
                    <i class="bi bi-cloud-arrow-up-fill"></i> บันทึกสรุปโครงการและจบกิจกรรม
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
