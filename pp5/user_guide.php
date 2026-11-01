<?php
/**
 * pp5/user_guide.php — คู่มือการใช้งานระบบ ปพ.5 ออนไลน์ โรงเรียนละลมวิทยา
 * แนะนำขั้นตอนการทำงานสำหรับครูผู้สอน ครูที่ปรึกษา และงานทะเบียน 7 ขั้นตอน (1-2-3...)
 */

require_once __DIR__ . '/_bootstrap.php';
pp5_require_login();

use PP5\App\Policies\Pp5Policy;

$policy  = new Pp5Policy($pdo);
$termCtx = pp5_get_term_context($pdo);

$pageTitle    = 'คู่มือการใช้งานระบบ ปพ.5 ออนไลน์';
$pageSubtitle = 'คู่มือแนะนำขั้นตอนการทำงานสำหรับครูผู้สอน ตั้งแต่เริ่มต้นจนถึงพิมพ์เล่ม ปพ.5 ฉบับสมบูรณ์';
$activeSystem = 'pp5';

require_once __DIR__ . '/../components/layout_start.php';
echo pp5_render_top_bar($pdo, $termCtx, $policy, 'dashboard');
?>

<div class="max-w-6xl mx-auto space-y-8 pb-12">

    <!-- ─── Hero Banner ─── -->
    <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-indigo-700 via-blue-700 to-indigo-900 p-8 sm:p-10 text-white shadow-2xl shadow-indigo-200/50">
        <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/15 backdrop-blur-md text-amber-300 border border-white/20 text-xs font-black uppercase tracking-wider mb-4">
                <i class="bi bi-patch-check-fill"></i> ระบบ ปพ.5 ออนไลน์ · โรงเรียนละลมวิทยา
            </div>
            <h1 class="text-2xl sm:text-4xl font-black tracking-tight text-white mb-3">
                เริ่มต้นใช้งาน ปพ.5 ง่ายๆ ใน 7 ขั้นตอน
            </h1>
            <p class="text-sm sm:text-base text-indigo-100 max-w-3xl leading-relaxed">
                คู่มือแนะนำขั้นตอนการบันทึกผลการพัฒนาคุณภาพผู้เรียน ตั้งแต่ตั้งค่าโครงสร้าง 100 คะแนน เช็กเวลาเรียน กรอกคะแนนแบบ Excel ตัดเกรดอัตโนมัติ จนถึงส่งตรวจและพิมพ์สมุด ปพ.5 ครบ 5 ส่วนตามระเบียบกระทรวงศึกษาธิการ
            </p>
            <div class="flex flex-wrap items-center gap-3 mt-6">
                <a href="/pp5/index.php" class="px-5 py-2.5 rounded-2xl bg-white text-indigo-700 hover:bg-indigo-50 font-black text-xs sm:text-sm shadow-lg transition-all flex items-center gap-2">
                    <i class="bi bi-speedometer2"></i> ไปที่แดชบอร์ดรายวิชา
                </a>
                <a href="#step-1" class="px-5 py-2.5 rounded-2xl bg-indigo-600/50 hover:bg-indigo-600 border border-white/30 text-white font-bold text-xs sm:text-sm backdrop-blur-md transition-all flex items-center gap-2">
                    <i class="bi bi-arrow-down-circle"></i> ดูขั้นตอนที่ 1
                </a>
            </div>
        </div>
    </div>

    <!-- ─── Quick Workflow Roadmap ─── -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-blue-100/50 border border-slate-100">
        <h3 class="text-base sm:text-lg font-black text-slate-800 mb-6 flex items-center gap-2">
            <i class="bi bi-diagram-3-fill text-indigo-600"></i> แผนภาพเส้นทางการทำงานของครูผู้สอน (Workflow Roadmap)
        </h3>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Box 1 -->
            <a href="#step-1" class="group p-4 rounded-2xl bg-indigo-50/60 border border-indigo-100 hover:border-indigo-300 hover:bg-indigo-50 transition-all flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-black text-xs flex items-center justify-center mb-3 shadow-md shadow-indigo-200">1</span>
                    <h4 class="font-black text-slate-800 text-sm group-hover:text-indigo-600 transition-colors">1. ตั้งค่ารายวิชา & ชิ้นงาน</h4>
                    <p class="text-xs text-slate-500 mt-1">สัดส่วนคะแนน 100%, ตัวชี้วัด K-P-A และคำอธิบายรายวิชา</p>
                </div>
                <div class="mt-4 flex items-center text-[11px] font-bold text-indigo-600">
                    อ่านเพิ่มเติม <i class="bi bi-chevron-right ml-1"></i>
                </div>
            </a>

            <!-- Box 2 -->
            <a href="#step-2" class="group p-4 rounded-2xl bg-emerald-50/60 border border-emerald-100 hover:border-emerald-300 hover:bg-emerald-50 transition-all flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white font-black text-xs flex items-center justify-center mb-3 shadow-md shadow-emerald-200">2</span>
                    <h4 class="font-black text-slate-800 text-sm group-hover:text-emerald-600 transition-colors">2. บันทึกเวลาเรียน</h4>
                    <p class="text-xs text-slate-500 mt-1">เช็กชื่อรายคาบ, คำนวณ % เข้าเรียน, เตือน "มส" ต่ำกว่า 80%</p>
                </div>
                <div class="mt-4 flex items-center text-[11px] font-bold text-emerald-600">
                    อ่านเพิ่มเติม <i class="bi bi-chevron-right ml-1"></i>
                </div>
            </a>

            <!-- Box 3 -->
            <a href="#step-3" class="group p-4 rounded-2xl bg-blue-50/60 border border-blue-100 hover:border-blue-300 hover:bg-blue-50 transition-all flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-xl bg-blue-600 text-white font-black text-xs flex items-center justify-center mb-3 shadow-md shadow-blue-200">3</span>
                    <h4 class="font-black text-slate-800 text-sm group-hover:text-blue-600 transition-colors">3. กรอกคะแนน & ตัดเกรด</h4>
                    <p class="text-xs text-slate-500 mt-1">ตาราง Excel Grid, วางจาก Excel (Ctrl+V), ตัดเกรด 0–4 ทันที</p>
                </div>
                <div class="mt-4 flex items-center text-[11px] font-bold text-blue-600">
                    อ่านเพิ่มเติม <i class="bi bi-chevron-right ml-1"></i>
                </div>
            </a>

            <!-- Box 4 -->
            <a href="#step-4" class="group p-4 rounded-2xl bg-purple-50/60 border border-purple-100 hover:border-purple-300 hover:bg-purple-50 transition-all flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-xl bg-purple-600 text-white font-black text-xs flex items-center justify-center mb-3 shadow-md shadow-purple-200">4</span>
                    <h4 class="font-black text-slate-800 text-sm group-hover:text-purple-600 transition-colors">4. คุณลักษณะ & อ่านคิด</h4>
                    <p class="text-xs text-slate-500 mt-1">ประเมิน 3 ด้าน (0–3), ปุ่มลัด 3 ทั้งหมด, สรุปฐานนิยม Mode</p>
                </div>
                <div class="mt-4 flex items-center text-[11px] font-bold text-purple-600">
                    อ่านเพิ่มเติม <i class="bi bi-chevron-right ml-1"></i>
                </div>
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
            <!-- Box 5 -->
            <a href="#step-5" class="group p-4 rounded-2xl bg-rose-50/60 border border-rose-100 hover:border-rose-300 hover:bg-rose-50 transition-all flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-xl bg-rose-600 text-white font-black text-xs flex items-center justify-center mb-3 shadow-md shadow-rose-200">5</span>
                    <h4 class="font-black text-slate-800 text-sm group-hover:text-rose-600 transition-colors">5. สรุปผล & ส่งตรวจเล่ม</h4>
                    <p class="text-xs text-slate-500 mt-1">Checklist ความพร้อม 5 ส่วน, ส่งตรวจผ่านระบบออนไลน์</p>
                </div>
                <div class="mt-4 flex items-center text-[11px] font-bold text-rose-600">
                    อ่านเพิ่มเติม <i class="bi bi-chevron-right ml-1"></i>
                </div>
            </a>

            <!-- Box 6 -->
            <a href="#step-6" class="group p-4 rounded-2xl bg-amber-50/60 border border-amber-100 hover:border-amber-300 hover:bg-amber-50 transition-all flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-xl bg-amber-500 text-white font-black text-xs flex items-center justify-center mb-3 shadow-md shadow-amber-200">6</span>
                    <h4 class="font-black text-slate-800 text-sm group-hover:text-amber-600 transition-colors">6. สอบแก้ตัว (เกรด 0/ร)</h4>
                    <p class="text-xs text-slate-500 mt-1">บันทึกวันที่สอบแก้ตัว และปรับเกรดเป็น 1 ตามระเบียบ</p>
                </div>
                <div class="mt-4 flex items-center text-[11px] font-bold text-amber-600">
                    อ่านเพิ่มเติม <i class="bi bi-chevron-right ml-1"></i>
                </div>
            </a>

            <!-- Box 7 -->
            <a href="#step-7" class="group p-4 rounded-2xl bg-slate-800 text-white border border-slate-700 hover:bg-slate-900 transition-all flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-xl bg-amber-400 text-slate-900 font-black text-xs flex items-center justify-center mb-3">7</span>
                    <h4 class="font-black text-white text-sm">7. พิมพ์เล่ม ปพ.5 A4</h4>
                    <p class="text-xs text-slate-300 mt-1">พิมพ์ครบ 5 ส่วน หรือพิมพ์แยกชีตส่งวิชาการได้ทันที</p>
                </div>
                <div class="mt-4 flex items-center text-[11px] font-bold text-amber-300">
                    อ่านเพิ่มเติม <i class="bi bi-chevron-right ml-1"></i>
                </div>
            </a>
        </div>
    </div>

    <!-- ─── Detailed Steps Section ─── -->
    <div class="space-y-6">

        <!-- STEP 1 -->
        <div id="step-1" class="scroll-mt-6 bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-blue-100/50 border border-slate-100 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-indigo-600 text-white font-black text-base flex items-center justify-center shadow-lg shadow-indigo-200">1</span>
                <div>
                    <h3 class="text-lg font-black text-slate-800">ขั้นตอนที่ 1 : ตรวจสอบรายวิชา & ตั้งค่าโครงสร้างคะแนน 100 คะแนน</h3>
                    <p class="text-xs text-slate-400">เมนู "1. ตั้งค่ารายวิชา & ชิ้นงาน" ในหน้ารายวิชา ปพ.5</p>
                </div>
            </div>

            <div class="text-xs sm:text-sm text-slate-600 space-y-3 leading-relaxed">
                <p>
                    เมื่อเข้าสู่ระบบ ปพ.5 ออนไลน์ จะพบการ์ดรายวิชาที่ตนเองสอนประจำภาคเรียน ให้คลิกปุ่ม <strong>"1. ตั้งค่ารายวิชา & ชิ้นงาน"</strong> เพื่อเตรียมความพร้อมของวิชา:
                </p>
                <ul class="list-disc list-inside space-y-1.5 pl-2">
                    <li><strong>ตั้งสัดส่วนคะแนน (Ratio):</strong> เช่น <code>70 : 30</code> หรือ <code>80 : 20</code> (คะแนนเก็บ : สอบปลายภาค รวมกันต้องได้ 100 คะแนน)</li>
                    <li><strong>กำหนดรายการคะแนน / ชิ้นงาน:</strong> เพิ่มหัวข้อคะแนนตาม 4 ช่วงการวัดผล (ก่อนกลางภาค, สอบกลางภาค, หลังกลางภาค, สอบปลายภาค)</li>
                    <li><strong>ตรวจสอบคะแนนเต็มรวม:</strong> แถบแจ้งเตือนด้านบนจะเปลี่ยนเป็นสีเขียวทันทีเมื่อคะแนนเต็มรวมของทุกชิ้นงานเท่ากับ <strong>100 คะแนนพอดี</strong></li>
                    <li><strong>แผนการวัดผล K-P-A:</strong> กรอกคะแนน K (ความรู้), P (ทักษะ), A (เจตคติ) ประจำแต่ละตัวชี้วัด</li>
                    <li><strong>คำอธิบายรายวิชา:</strong> พิมพ์หรือคัดลอกข้อความคำอธิบายรายวิชาตามหลักสูตรลงในช่องข้อความ แล้วกดบันทึก</li>
                </ul>
            </div>

            <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-200 text-xs text-indigo-900 flex items-start gap-3">
                <i class="bi bi-lightbulb-fill text-indigo-600 text-lg shrink-0 mt-0.5"></i>
                <div>
                    <strong>เคล็ดลับประหยัดเวลา (คัดลอกโครงสร้างข้ามห้อง):</strong><br>
                    หากอาจารย์สอนวิชาเดียวกันหลายห้อง (เช่น ม.2/1, ม.2/2, ม.2/3) ให้ตั้งค่าในห้องแรกให้สมบูรณ์ก่อน จากนั้นในห้องถัดไป ให้ไปที่กล่อง <strong>"ตัวช่วยสร้าง & คัดลอกโครงสร้าง"</strong> แล้วเลือกห้องต้นทางเพื่อคัดลอกรายการคะแนนและสัดส่วนมาได้ทันทีใน 1 วินาที!
                </div>
            </div>
        </div>

        <!-- STEP 2 -->
        <div id="step-2" class="scroll-mt-6 bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-blue-100/50 border border-slate-100 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-emerald-600 text-white font-black text-base flex items-center justify-center shadow-lg shadow-emerald-200">2</span>
                <div>
                    <h3 class="text-lg font-black text-slate-800">ขั้นตอนที่ 2 : บันทึกเวลาเรียน (เช็กชื่อรายคาบ)</h3>
                    <p class="text-xs text-slate-400">เมนู "2. บันทึกเวลาเรียน (เช็กชื่อ)" ในหน้ารายวิชา ปพ.5</p>
                </div>
            </div>

            <div class="text-xs sm:text-sm text-slate-600 space-y-3 leading-relaxed">
                <p>
                    ระบบ ปพ.5 บันทึกเวลาเรียนตามคาบเรียนจริง และเชื่อมโยงกับการคำนวณสิทธิ์สอบอัตโนมัติ:
                </p>
                <ul class="list-disc list-inside space-y-1.5 pl-2">
                    <li>สามารถเพิ่มคาบเรียนและคลิกบันทึกสถานะ: <strong>มา, ขาด, สาย, ลา, กิจกรรม</strong></li>
                    <li>มีปุ่มด่วน <strong>"มาเรียนทั้งหมด"</strong> เพื่อช่วยให้บันทึกได้สะดวกรวดเร็วในคลิกเดียว</li>
                    <li>ระบบคำนวณร้อยละเวลาเรียนสะสมของนักเรียนแต่ละคนแบบ Real-time</li>
                </ul>
            </div>

            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-900 flex items-start gap-3">
                <i class="bi bi-shield-fill-exclamation text-rose-600 text-lg shrink-0 mt-0.5"></i>
                <div>
                    <strong>เกณฑ์เวลาเรียนตามหลักสูตรแกนกลางฯ 2551 (กฎ 80%):</strong><br>
                    นักเรียนต้องมีเวลาเรียนไม่น้อยกว่า <strong>ร้อยละ 80</strong> ของเวลาเรียนทั้งหมด หากเวลาเรียนต่ำกว่า 80% ระบบจะแสดงเครื่องหมายเตือนสีแดง และในตารางตัดเกรดจะขึ้นสถานะ <strong>"มส" (หมดสิทธิ์สอบ)</strong> อัตโนมัติ
                </div>
            </div>
        </div>

        <!-- STEP 3 -->
        <div id="step-3" class="scroll-mt-6 bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-blue-100/50 border border-slate-100 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-blue-600 text-white font-black text-base flex items-center justify-center shadow-lg shadow-blue-200">3</span>
                <div>
                    <h3 class="text-lg font-black text-slate-800">ขั้นตอนที่ 3 : กรอกคะแนน & ตัดเกรดอัตโนมัติ (Excel Grid)</h3>
                    <p class="text-xs text-slate-400">เมนู "3. กรอกคะแนน & ตัดเกรด (Excel Grid)" ในหน้ารายวิชา ปพ.5</p>
                </div>
            </div>

            <div class="text-xs sm:text-sm text-slate-600 space-y-3 leading-relaxed">
                <p>
                    ตารางกรอกคะแนนออกแบบมาให้ใช้งานเหมือน Excel ทำงานบนเบราว์เซอร์ได้อย่างลื่นไหลและบันทึกอัตโนมัติ (Autosave):
                </p>
                <ul class="list-disc list-inside space-y-1.5 pl-2">
                    <li><strong>กรอกคะแนนทีละช่อง:</strong> พิมพ์ตัวเลขคะแนนแล้วกดปุ่มลูกศร ↓ หรือ Enter เพื่อเลื่อนไปนักเรียนคนถัดไป</li>
                    <li><strong>ปุ่มกรอกคะแนนเต็มทั้งคอลัมน์:</strong> คลิกปุ่มตัวเลขคะแนนเต็มที่หัวตาราง ระบบจะใส่คะแนนเต็มให้นักเรียนทั้งห้องทันที</li>
                    <li><strong>ตัดเกรดอัตโนมัติ:</strong> คำนวณคะแนนรวมและตัดเกรด 8 ระดับ (0, 1, 1.5, 2, 2.5, 3, 3.5, 4) ทันทีตามเกณฑ์มาตรฐาน สพฐ.</li>
                    <li>แสดงสถิติการกระจายตัวของเกรด (Distribution) และกราฟของห้องเรียนแบบสดๆ</li>
                </ul>
            </div>

            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 flex items-start gap-3">
                <i class="bi bi-file-earmark-excel-fill text-emerald-600 text-lg shrink-0 mt-0.5"></i>
                <div>
                    <strong>ฟังก์ชันเด็ด: คัดลอกและวางจาก Excel / Google Sheets (Ctrl+C → Ctrl+V):</strong><br>
                    หากอาจารย์บันทึกคะแนนไว้ในไฟล์ Excel หรือ Google Sheets อยู่แล้ว สามารถลากคลุมแถบคะแนนใน Excel กด <code>Ctrl+C</code> แล้วกลับมาคลิกที่ช่องคะแนนของคนแรกในระบบ ปพ.5 จากนั้นกด <code>Ctrl+V</code> เพื่อวางข้อมูลลงตารางได้ทั้งคอลัมน์ทันที!
                </div>
            </div>
        </div>

        <!-- STEP 4 -->
        <div id="step-4" class="scroll-mt-6 bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-blue-100/50 border border-slate-100 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-purple-600 text-white font-black text-base flex items-center justify-center shadow-lg shadow-purple-200">4</span>
                <div>
                    <h3 class="text-lg font-black text-slate-800">ขั้นตอนที่ 4 : ประเมินคุณลักษณะอันพึงประสงค์ อ่านคิดเขียน และสมรรถนะ</h3>
                    <p class="text-xs text-slate-400">เมนู "4. คุณลักษณะ / อ่านคิดเขียน / สมรรถนะ" ในหน้ารายวิชา ปพ.5</p>
                </div>
            </div>

            <div class="text-xs sm:text-sm text-slate-600 space-y-3 leading-relaxed">
                <p>
                    ประเมินผลการเรียนรู้รอบด้านครบทั้ง 3 ด้าน ตามหลักสูตรแกนกลางฯ 2551 (ระดับคุณภาพ 0–3):
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 my-2">
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <strong class="text-purple-700">1. คุณลักษณะฯ (8 ข้อ)</strong>
                        <p class="text-[11px] text-slate-500 mt-1">รักชาติ ซื่อสัตย์ มีวินัย ใฝ่เรียนรู้ อยู่อย่างพอเพียง มุ่งมั่น รักความเป็นไทย จิตสาธารณะ</p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <strong class="text-blue-700">2. อ่าน คิดวิเคราะห์ เขียน (5 ข้อ)</strong>
                        <p class="text-[11px] text-slate-500 mt-1">อ่านหาข้อมูล จับประเด็น วิเคราะห์สังเคราะห์ ประเมินคุณค่า และเขียนสื่อสารสร้างสรรค์</p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <strong class="text-teal-700">3. สมรรถนะสำคัญ (5 ด้าน)</strong>
                        <p class="text-[11px] text-slate-500 mt-1">การสื่อสาร การคิด การแก้ปัญหา การใช้ทักษะชีวิต และการใช้เทคโนโลยี</p>
                    </div>
                </div>
                <ul class="list-disc list-inside space-y-1.5 pl-2">
                    <li>ระดับคะแนน: <strong>3 = ดีเยี่ยม, 2 = ดี, 1 = ผ่าน, 0 = ไม่ผ่าน</strong></li>
                    <li>มีปุ่มลัด <strong>"3 (ดีเยี่ยม) ทั้งหมด"</strong> และปุ่มกรอกทั้งคอลัมน์ ช่วยให้กรอกผลประเมินได้รวดเร็ว</li>
                    <li>ระบบสรุปผลรวมรายคนตามฐานนิยม (Mode) ให้โดยอัตโนมัติ</li>
                </ul>
            </div>
        </div>

        <!-- STEP 5 -->
        <div id="step-5" class="scroll-mt-6 bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-blue-100/50 border border-slate-100 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-rose-600 text-white font-black text-base flex items-center justify-center shadow-lg shadow-rose-200">5</span>
                <div>
                    <h3 class="text-lg font-black text-slate-800">ขั้นตอนที่ 5 : สรุปผล & ส่งตรวจเล่ม ปพ.5 ออนไลน์</h3>
                    <p class="text-xs text-slate-400">เมนู "5. สรุปผล & พิมพ์เล่ม ปพ.5 (A4)" ในหน้ารายวิชา ปพ.5</p>
                </div>
            </div>

            <div class="text-xs sm:text-sm text-slate-600 space-y-3 leading-relaxed">
                <p>
                    เมื่อกรอกข้อมูลครบถ้วนแล้ว ให้เข้ามาที่หน้านี้เพื่อตรวจสอบความพร้อมก่อนส่งตรวจ:
                </p>
                <ul class="list-disc list-inside space-y-1.5 pl-2">
                    <li><strong>Checklist ตรวจความพร้อม 5 ส่วน:</strong> ระบบจะแสดงเครื่องหมายติ๊กถูกสีเขียวครบ 5 ข้อเมื่อข้อมูลสมบูรณ์</li>
                    <li><strong>ตรวจสอบตารางสรุปเกรด:</strong> ดูจำนวนนักเรียนและร้อยละของแต่ละระดับเกรด (0 - 4, ร, มส)</li>
                    <li><strong>กดปุ่ม "ส่งตรวจเล่ม ปพ.5":</strong> ยืนยันการส่งเล่มเข้าสู่ขั้นตอนการตรวจอนุมัติ</li>
                </ul>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-700">
                <strong>ลำดับขั้นตอนการอนุมัติ (Approval Workflow):</strong><br>
                ครูผู้สอนส่งตรวจ ➔ หัวหน้ากลุ่มสาระฯ ตรวจสอบ ➔ รองฯ ฝ่ายวิชาการ ตรวจสอบ ➔ ผู้อำนวยการ อนุมัติสมบูรณ์<br>
                <em>*หมายเหตุ: เมื่อกดส่งตรวจแล้ว เล่มจะถูกล็อกไม่ให้แก้ไขคะแนน เพื่อความถูกต้องของข้อมูล (หากต้องการแก้ไข สามารถแจ้งงานทะเบียนเพื่อขอปลดล็อกได้)</em>
            </div>
        </div>

        <!-- STEP 6 -->
        <div id="step-6" class="scroll-mt-6 bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-blue-100/50 border border-slate-100 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-amber-500 text-white font-black text-base flex items-center justify-center shadow-lg shadow-amber-200">6</span>
                <div>
                    <h3 class="text-lg font-black text-slate-800">ขั้นตอนที่ 6 : การบันทึกผลสอบแก้ตัว (กรณีนักเรียนติด 0 หรือ ร)</h3>
                    <p class="text-xs text-slate-400">อยู่ในหน้า "5. สรุปผล & พิมพ์เล่ม ปพ.5" (จะแสดงเมื่อมีนักเรียนได้เกรด 0 หรือ ร)</p>
                </div>
            </div>

            <div class="text-xs sm:text-sm text-slate-600 space-y-3 leading-relaxed">
                <p>
                    หากในรายวิชามีนักเรียนที่ได้ผลการเรียน <strong>0 หรือ ร</strong> ระบบจะแสดงตารางรายชื่อนักเรียนกลุ่มนี้ขึ้นมาโดยเฉพาะ:
                </p>
                <ul class="list-disc list-inside space-y-1.5 pl-2">
                    <li>เมื่อนักเรียนดำเนินการสอบแก้ตัวหรือส่งงานครบถ้วนแล้ว</li>
                    <li>ครูผู้สอนสามารถระบุ <strong>วันที่สอบแก้ตัว</strong> และเลือกผลการสอบแก้ตัวเป็น <strong>เกรด 1</strong> ตามระเบียบการวัดผล</li>
                    <li>กดปุ่ม <strong>"บันทึกผลการสอบแก้ตัว"</strong> ข้อมูลจะถูกอัปเดตและพิมพ์ลงในหน้าแบบบันทึกผลการสอบแก้ตัวของเล่ม ปพ.5 โดยอัตโนมัติ</li>
                </ul>
            </div>
        </div>

        <!-- STEP 7 -->
        <div id="step-7" class="scroll-mt-6 bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-blue-100/50 border border-slate-100 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-slate-900 text-amber-400 font-black text-base flex items-center justify-center shadow-lg shadow-slate-300">7</span>
                <div>
                    <h3 class="text-lg font-black text-slate-800">ขั้นตอนที่ 7 : การสั่งพิมพ์เล่ม ปพ.5 ขนาด A4 ส่งงานวิชาการ</h3>
                    <p class="text-xs text-slate-400">ปุ่ม "พิมพ์ ปพ.5 (ครบ 5 ส่วน)" สีดำ ที่แถบเครื่องมือด้านบน</p>
                </div>
            </div>

            <div class="text-xs sm:text-sm text-slate-600 space-y-3 leading-relaxed">
                <p>
                    เอกสาร ปพ.5 ออนไลน์ได้รับการจัดหน้าตามมาตรฐานแบบพิมพ์กระทรวงศึกษาธิการ และตรงตาม Google Sheets ต้นแบบของโรงเรียนละลมวิทยา 100%:
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 my-2">
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <strong class="text-slate-800">🖨️ พิมพ์ครบทั้ง 5 ส่วน (เล่มสมบูรณ์):</strong>
                        <p class="text-xs text-slate-500 mt-1">กดปุ่มสีดำ <strong>"พิมพ์ ปพ.5 (ครบ 5 ส่วน)"</strong> จะเปิดหน้าต่างพร้อมพิมพ์ ปก, เวลาเรียน, คะแนน, คุณลักษณะ และอ่านคิดเขียน</p>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <strong class="text-slate-800">📄 พิมพ์แยกเฉพาะส่วน:</strong>
                        <p class="text-xs text-slate-500 mt-1">มีปุ่มพิมพ์ด่วนเฉพาะชีต เช่น <strong>ชีต ปก</strong>, <strong>ชีต คำอธิบาย</strong> หรือ <strong>ชีต ตัวชี้วัด (แผน K-P-A)</strong> เพื่อนำไปเย็บเล่มตามที่วิชาการกำหนด</p>
                    </div>
                </div>
                <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-800">
                    <strong>คำแนะนำในการตั้งค่าการพิมพ์ของเบราว์เซอร์:</strong><br>
                    - กระดาษ: <strong>A4</strong><br>
                    - ระยะขอบ (Margins): <strong>Default (ค่าเริ่มต้น)</strong> หรือ <strong>Minimum (น้อยที่สุด)</strong><br>
                    - ตัวเลือกกราฟิกพื้นหลัง: ติ๊กถูกที่ <strong>"Background graphics"</strong> เพื่อให้แถบสีและหัวตารางแสดงอย่างสมบูรณ์
                </div>
            </div>
        </div>

    </div>

    <!-- ─── FAQ Section ─── -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl shadow-blue-100/50 border border-slate-100 space-y-6">
        <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
            <i class="bi bi-question-circle-fill text-indigo-600"></i> คำถามที่พบบ่อย (FAQ)
        </h3>

        <div class="space-y-3 text-xs sm:text-sm">
            <!-- Q1 -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <h4 class="font-black text-slate-800 flex items-center gap-2">
                    <span class="text-indigo-600">Q:</span> ในแดชบอร์ดมีรายวิชาหรือห้องเรียนที่ผมไม่ได้สอน หรือชื่อครูไม่ตรง ต้องแก้ไขตรงไหน?
                </h4>
                <p class="text-slate-600 mt-2 leading-relaxed">
                    <strong>A:</strong> หากอาจารย์มีสิทธิ์ Admin สามารถกดปุ่ม <strong>"⚙️ จัดการวิชาสอน"</strong> ที่มุมขวาบนของหน้ารายวิชาเพื่อเข้าไปเปลี่ยนชื่อครูผู้สอนหรือลบวิชาที่จัดห้องผิดได้ทันที หรือแจ้งฝ่ายทะเบียนและวัดผลให้เข้าไปที่เมนู <strong>"จัดการข้อมูลหลัก ปพ.5" ➔ แท็บ "6. จัดครูสอน & ปลดล็อก ปพ.5"</strong> เพื่อแก้ไขหรือลบรายวิชานั้นออกได้ครับ
                </p>
            </div>

            <!-- Q2 -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <h4 class="font-black text-slate-800 flex items-center gap-2">
                    <span class="text-indigo-600">Q:</span> ส่งตรวจเล่ม ปพ.5 ไปแล้ว แต่ต้องการกลับมาแก้คะแนน ต้องทำอย่างไร?
                </h4>
                <p class="text-slate-600 mt-2 leading-relaxed">
                    <strong>A:</strong> เล่มที่ส่งตรวจแล้วจะเข้าสู่สถานะล็อก ให้ติดต่อหัวหน้ากลุ่มสาระฯ หรือฝ่ายทะเบียนวัดผล โดยผู้ดูแลสามารถกดปุ่ม <strong>"🔓 ปลดล็อก"</strong> ในหน้าสรุปรายวิชาหรือในหน้าจัดการวิชาเปิดสอน พร้อมระบุเหตุผล จากนั้นเล่มจะกลับมาเป็นสถานะ Draft ให้ครูผู้สอนแก้ไขคะแนนได้ตามปกติ
                </p>
            </div>

            <!-- Q3 -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <h4 class="font-black text-slate-800 flex items-center gap-2">
                    <span class="text-indigo-600">Q:</span> ต้องการดาวน์โหลดเล่ม ปพ.5 ทุกวิชาที่สอนเก็บไว้ในเครื่องทีเดียว ทำได้ไหม?
                </h4>
                <p class="text-slate-600 mt-2 leading-relaxed">
                    <strong>A:</strong> ทำได้ครับ! สามารถไปที่เมนูด้านซ้าย <strong>"ดาวน์โหลดเล่ม ปพ.5 (.ZIP)"</strong> ระบบจะรวบรวมเล่มรายงาน ปพ.5 ครบ 5 ส่วนของทุกห้องที่อาจารย์สอน บีบอัดเป็นไฟล์ <code>.zip</code> ให้ดาวน์โหลดไปเก็บไว้ในเครื่องคอมพิวเตอร์ได้ในคลิกเดียว
                </p>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
