<?php
session_start();
require_once 'functions.php';
checkLogin();

// Only super_admin or school admins can do this
if ($_SESSION['llw_role'] !== 'super_admin') {
    die("คุณไม่มีสิทธิ์ใช้งานหน้านี้");
}

$pageTitle = 'ระบบจับคู่วิชาเลือกอัตโนมัติ';
$pageSubtitle = 'Mapping นักเรียนเข้าวิชาเลือกตามสายการเรียน';
$activeSystem = 'attendance';

require_once '../components/layout_start.php';
?>

<div class="glass-card rounded-[40px] p-10 shadow-2xl">
    <div class="flex items-center gap-6 mb-10">
        <div class="w-16 h-16 bg-gradient-to-br from-violet-600 to-indigo-600 rounded-[24px] flex items-center justify-center text-white text-3xl shadow-xl shadow-violet-200">
            <i class="bi bi-magic"></i>
        </div>
        <div>
            <h2 class="text-3xl font-black text-slate-800">ระบบ Auto-Mapping</h2>
            <p class="text-slate-500 font-bold">ระบบจะดึงนักเรียนเข้าวิชาเลือกอัตโนมัติหาก "ชื่อวิชา" ตรงกับ "สายการเรียน"</p>
        </div>
    </div>

    <div class="space-y-6">
        <?php
        try {
            $pdo->beginTransaction();
            
            // 1. Get all elective subjects
            $subjects = $pdo->query("SELECT id, subject_name FROM att_subjects WHERE is_elective = 1")->fetchAll();
            $total_mapped = 0;
            
            if (empty($subjects)) {
                echo '<div class="p-6 bg-amber-50 rounded-2xl border border-amber-200 text-amber-700 font-bold">ไม่พบวิชาที่ตั้งค่าเป็น "วิชาเลือก" ในระบบ กรุณาไปตั้งค่าในหน้า Admin ก่อนครับ</div>';
            } else {
                echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
                foreach ($subjects as $s) {
                    $sub_id = $s['id'];
                    $sub_name = $s['subject_name'];
                    
                    // Match students (exact or fuzzy)
                    $stmt = $pdo->prepare("
                        SELECT id FROM att_students 
                        WHERE status = 'active' 
                        AND (
                            major = :name1 
                            OR :name2 LIKE CONCAT('%', major, '%')
                        )
                        AND major IS NOT NULL AND major != ''
                    ");
                    $stmt->execute([':name1' => $sub_name, ':name2' => $sub_name]);
                    $std_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (count($std_ids) > 0) {
                        $ins = $pdo->prepare("INSERT IGNORE INTO att_subject_students (subject_id, student_id) VALUES (?, ?)");
                        $count = 0;
                        foreach ($std_ids as $std_id) {
                            if ($ins->execute([$sub_id, $std_id])) $count++;
                        }
                        $total_mapped += $count;
                        
                        echo '<div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-100 flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-black text-emerald-400 uppercase tracking-widest">วิชา: ' . htmlspecialchars($sub_name) . '</p>
                                    <p class="font-bold text-emerald-800">จับคู่สำเร็จ!</p>
                                </div>
                                <div class="text-right">
                                    <span class="text-2xl font-black text-emerald-600">+' . count($std_ids) . '</span>
                                    <p class="text-[10px] text-emerald-400 font-bold uppercase">นักเรียน</p>
                                </div>
                              </div>';
                    } else {
                        echo '<div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-between opacity-60">
                                <div>
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest">วิชา: ' . htmlspecialchars($sub_name) . '</p>
                                    <p class="font-bold text-slate-500">ไม่พบรายชื่อสายที่ตรงกัน</p>
                                </div>
                              </div>';
                    }
                }
                echo '</div>';
            }
            
            $pdo->commit();
            
            if ($total_mapped > 0) {
                echo '<div class="mt-10 p-8 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-[32px] text-white shadow-xl shadow-emerald-100">
                        <div class="flex items-center gap-6">
                            <i class="bi bi-check-circle-fill text-5xl"></i>
                            <div>
                                <h3 class="text-xl font-black">ดำเนินการเสร็จสมบูรณ์!</h3>
                                <p class="opacity-90">ระบบได้ดึงนักเรียนเข้าวิชาเลือกให้ทั้งหมด ' . $total_mapped . ' รายการเรียบร้อยครับ</p>
                            </div>
                        </div>
                      </div>';
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<div class="p-6 bg-rose-50 rounded-2xl border border-rose-200 text-rose-700">เกิดข้อผิดพลาด: ' . $e->getMessage() . '</div>';
        }
        ?>
        
        <div class="pt-10 flex justify-center">
            <a href="admin.php" class="bg-slate-900 text-white px-10 py-4 rounded-2xl font-black text-sm hover:scale-105 transition-all shadow-xl shadow-slate-200">
                <i class="bi bi-arrow-left me-2"></i> กลับหน้าจัดการระบบ
            </a>
        </div>
    </div>
</div>

<?php require_once '../components/layout_end.php'; ?>
