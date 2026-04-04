<?php
require_once 'functions.php';
checkLogin();

// Auth check
if (!isset($_SESSION['llw_role']) || $_SESSION['llw_role'] !== 'super_admin') {
    header('Location: dashboard.php'); exit();
}

$pageTitle = 'จัดการระบบ (Admin)';
$pageSubtitle = 'จัดการรายชื่อครู รายวิชา และข้อมูลพื้นฐาน';

$msg = ''; $msgType = 'success';
$action = $_POST['action'] ?? '';

// --- CRUD: TEACHERS ---
if ($action === 'add_teacher') {
    $name = trim($_POST['name']); $username = trim($_POST['username']); $password = $_POST['password'];
    if (!$name || !$username || !$password) { $msg = 'กรุณากรอกข้อมูลให้ครบถ้วน'; $msgType = 'error'; } 
    else {
        $chk = $pdo->prepare("SELECT user_id FROM llw_users WHERE username=?");
        $chk->execute([$username]);
        if ($chk->fetch()) { $msg = "Username '$username' ถูกใช้งานแล้ว"; $msgType = 'error'; } 
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->beginTransaction();
            try {
                $u = $pdo->prepare("INSERT INTO llw_users (username,password,firstname,lastname,role,status) VALUES (?,?,?,?,'att_teacher','active')");
                $parts = explode(' ', $name, 2);
                $u->execute([$username, $hash, $parts[0], $parts[1] ?? '']);
                $uid = $pdo->lastInsertId();
                $t = $pdo->prepare("INSERT INTO att_teachers (name,username,password,llw_user_id) VALUES (?,?,?,?)");
                $t->execute([$name, $username, $hash, $uid]);
                $pdo->commit(); $msg = "เพิ่มครู '$name' สำเร็จ";
            } catch (Exception $e) { $pdo->rollBack(); $msg = 'เกิดข้อผิดพลาด: ' . $e->getMessage(); $msgType = 'error'; }
        }
    }
} elseif ($action === 'delete_teacher') {
    $tid = (int)$_POST['teacher_id'];
    if ($tid) {
        $link = $pdo->prepare("SELECT llw_user_id FROM att_teachers WHERE id=?");
        $link->execute([$tid]);
        $uid = $link->fetchColumn();
        $pdo->prepare("DELETE FROM att_subjects WHERE teacher_id=?")->execute([$tid]);
        $pdo->prepare("DELETE FROM att_teachers WHERE id=?")->execute([$tid]);
        if ($uid) $pdo->prepare("DELETE FROM llw_users WHERE user_id=?")->execute([$uid]);
        $msg = 'ลบข้อมูลสำเร็จ';
    }
}

// --- CRUD: SUBJECTS ---
if ($action === 'add_subject') {
    $tid = (int)$_POST['teacher_id']; $code = trim($_POST['subject_code']); $name = trim($_POST['subject_name']); $cls = trim($_POST['classroom']);
    if (!$tid || !$code || !$name || !$cls) { $msg = 'ข้อมูลไม่ครบ'; $msgType = 'error'; } 
    else {
        $pdo->prepare("INSERT INTO att_subjects (subject_code,subject_name,classroom,teacher_id) VALUES (?,?,?,?)")->execute([$code, $name, $cls, $tid]);
        $msg = "เพิ่มวิชาสำเร็จ";
    }
} elseif ($action === 'delete_subject') {
    $sid = (int)$_POST['subject_id'];
    if ($sid) { $pdo->prepare("DELETE FROM att_subjects WHERE id=?")->execute([$sid]); $msg = 'ลบวิชาสำเร็จ'; }
}

// Data Fetching
$teachers = $pdo->query("SELECT t.*, lu.username, lu.status as user_status, lu.last_login, COUNT(DISTINCT s.id) as s_count FROM att_teachers t LEFT JOIN llw_users lu ON lu.user_id = t.llw_user_id LEFT JOIN att_subjects s ON s.teacher_id = t.id GROUP BY t.id ORDER BY t.name")->fetchAll();
$subjects = $pdo->query("SELECT s.*, t.name as t_name FROM att_subjects s JOIN att_teachers t ON t.id = s.teacher_id ORDER BY s.subject_code")->fetchAll();
$classrooms = $pdo->query("SELECT DISTINCT classroom FROM att_students ORDER BY classroom")->fetchAll(PDO::FETCH_COLUMN);

require_once 'components/layout_start.php';
?>

<div class="flex flex-col gap-8">
    
    <!-- ── Tab Navigation ── -->
    <div class="flex p-1 bg-slate-200/50 rounded-2xl w-fit no-print">
        <button onclick="showTab('teachers')" id="tab-teachers" class="tab-btn active px-6 py-2.5 rounded-xl text-sm font-bold transition-all">
            <i class="bi bi-people-fill mr-2"></i> จัดการครู
        </button>
        <button onclick="showTab('subjects')" id="tab-subjects" class="tab-btn px-6 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-700 transition-all">
            <i class="bi bi-book-half mr-2"></i> จัดการวิชา
        </button>
    </div>

    <!-- ══ TEACHERS TAB ══ -->
    <div id="pane-teachers" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Form Add Teacher -->
        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 flex flex-col gap-6 h-fit">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl"><i class="bi bi-person-plus-fill"></i></div>
                <h3 class="font-bold text-slate-800">เพิ่มครูเข้าในระบบ</h3>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_teacher">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ชื่อ-สกุล</label>
                    <input type="text" name="name" required placeholder="นายสมชาย ใจดี" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Username</label>
                    <input type="text" name="username" required placeholder="teacher01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition mt-2">ยืนยันเพิ่มครู</button>
            </form>
        </div>

        <!-- Teacher List Table -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-50 flex items-center justify-between">
                <h3 class="font-bold text-slate-800">รายชื่อครูทั้งหมด (<?= count($teachers) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50 text-sm">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-6 py-4 text-left">ครูผู้สอน</th>
                            <th class="px-6 py-4 text-left">Username</th>
                            <th class="px-6 py-4 text-center">วิชา</th>
                            <th class="px-6 py-4 text-center">สถานะ</th>
                            <th class="px-6 py-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($teachers as $t): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs"><?= mb_substr($t['name'],0,1) ?></div>
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($t['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-blue-600"><?= $t['username'] ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 font-bold text-[10px]"><?= $t['s_count'] ?> วิชา</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold <?= $t['user_status']==='active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
                                    <?= strtoupper($t['user_status'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="deleteTeacher(<?= $t['id'] ?>, '<?= addslashes($t['name']) ?>')" class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition"><i class="bi bi-trash3-fill"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══ SUBJECTS TAB ══ -->
    <div id="pane-subjects" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add Subject Form -->
        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 flex flex-col gap-6 h-fit">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl"><i class="bi bi-journal-plus"></i></div>
                <h3 class="font-bold text-slate-800">เพิ่มรายวิชา</h3>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_subject">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ครูผู้สอน</label>
                    <select name="teacher_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                        <option value="">-- เลือกครู --</option>
                        <?php foreach($teachers as $t): ?> <option value="<?= $t['id'] ?>"><?= $t['name'] ?></option> <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                   <div>
                       <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">รหัสวิชา</label>
                       <input type="text" name="subject_code" required placeholder="ท31101" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-mono outline-none">
                   </div>
                   <div>
                       <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ห้อง/ชั้น</label>
                       <input type="text" name="classroom" required placeholder="ม.4/1" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs outline-none">
                   </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">ชื่อรายวิชา</label>
                    <input type="text" name="subject_name" required placeholder="ภาษาไทย" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold outline-none">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold shadow-xl shadow-blue-100 hover:bg-blue-700 transition mt-2">ยืนยันเพิ่มวิชา</button>
            </form>
        </div>

        <!-- Subject List Table -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
             <div class="px-8 py-5 border-b border-slate-50">
                <h3 class="font-bold text-slate-800">รายวิชาทั้งหมด (<?= count($subjects) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-50 text-sm">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        <tr>
                            <th class="px-6 py-4 text-left">รหัส / วิชา</th>
                            <th class="px-6 py-4 text-left">ครูผู้รับผิดชอบ</th>
                            <th class="px-6 py-4 text-center">ห้องเรียน</th>
                            <th class="px-6 py-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($subjects as $s): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="text-xs font-mono text-blue-600 font-bold"><?= $s['subject_code'] ?></span>
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($s['subject_name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-600"><?= $s['t_name'] ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-[10px]"><?= $s['classroom'] ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="deleteSubject(<?= $s['id'] ?>, '<?= addslashes($s['subject_name']) ?>')" class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition"><i class="bi bi-trash3"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tab) {
    ['teachers','subjects'].forEach(t => {
        document.getElementById('pane-'+t).classList.toggle('hidden', t!==tab);
        const btn = document.getElementById('tab-'+t);
        if(t===tab) {
            btn.classList.add('active','bg-white','text-blue-700','shadow-sm');
            btn.classList.remove('text-slate-500');
        } else {
            btn.classList.remove('active','bg-white','text-blue-700','shadow-sm');
            btn.classList.add('text-slate-500');
        }
    });
}

function deleteTeacher(id, name) {
    Swal.fire({
        title: 'ลบข้อมูลครู?', text: `ต้องการลบครู "${name}" หรือไม่? วิชาที่เกี่ยวข้องจะถูกลบด้วย`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#e11d48', confirmButtonText: 'ยืนยันลบข้อมูล', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if(r.isConfirmed){
            const f = document.createElement('form'); f.method='POST';
            f.innerHTML = `<input name="action" value="delete_teacher"><input name="teacher_id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }
    });
}

function deleteSubject(id, name) {
    Swal.fire({
        title: 'ลบวิชา?', text: `ต้องการลบวิชา "${name}" หรือไม่?`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#e11d48', confirmButtonText: 'ยืนยันลบ', cancelButtonText: 'ยกเลิก'
    }).then(r => {
        if(r.isConfirmed){
            const f = document.createElement('form'); f.method='POST';
            f.innerHTML = `<input name="action" value="delete_subject"><input name="subject_id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }
    });
}
</script>

<?php
if ($msg) echo "<script>Swal.fire({ icon: '$msgType', title: 'แจ้งเตือน', text: '$msg', timer: 2000, showConfirmButton: false });</script>";
require_once 'components/layout_end.php';
?>
