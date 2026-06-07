<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) {
    header('Location: ' . $base_path . '/login.php'); exit();
}
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin', 'wfh_staff', 'att_teacher'])) {
    header('Location: ' . $base_path . '/login.php'); exit();
}

$pdo       = getPdo();
$sarId     = (int)($_GET['id'] ?? 0);
$autoPrint = isset($_GET['print']);
$isAdmin   = in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin']);
$userId    = (int)$_SESSION['user_id'];
$formData  = [];

// Ensure table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sar_reports (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id   INT NOT NULL,
            teacher_name VARCHAR(200) NOT NULL DEFAULT '',
            year         VARCHAR(10)  NOT NULL DEFAULT '',
            semester     TINYINT      NOT NULL DEFAULT 1,
            form_data    LONGTEXT     NOT NULL DEFAULT '{}',
            status       ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_teacher (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) { error_log('SAR table: ' . $e->getMessage()); }

if ($sarId) {
    try {
        $stmt = $isAdmin
            ? $pdo->prepare("SELECT * FROM sar_reports WHERE id = ?")
            : $pdo->prepare("SELECT * FROM sar_reports WHERE id = ? AND teacher_id = ?");
        $isAdmin ? $stmt->execute([$sarId]) : $stmt->execute([$sarId, $userId]);
        $sarRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sarRow) { header('Location: index.php'); exit(); }
        $formData = json_decode($sarRow['form_data'], true) ?: [];
    } catch (Exception $e) {
        error_log($e->getMessage());
        header('Location: index.php'); exit();
    }
} else {
    $fullname = $_SESSION['fullname'] ?? '';
    $parts = explode(' ', $fullname, 2);
    $formData['fname'] = $parts[0] ?? '';
    $formData['lname'] = $parts[1] ?? '';
}

$formDataJson = json_encode($formData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$basePath = $base_path;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>SAR รายบุคคล — โรงเรียนละลมวิทยา</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&family=Noto+Serif+Thai:wght@400;600&display=swap" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{
  --blue:#1a56a0;--blue-light:#e8f0fa;--blue-mid:#3b7dd8;
  --text:#1a1a2e;--muted:#6b7280;--border:#d1d9e6;
  --bg:#f5f7fb;--card:#ffffff;--radius:10px;
  --shadow:0 1px 4px rgba(26,86,160,.08);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--text);font-size:15px;line-height:1.7}
a{color:var(--blue)}
.site-header{background:var(--blue);color:#fff}
.header-inner{max-width:960px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.header-icon{width:42px;height:42px;background:rgba(255,255,255,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0}
.header-text h1{font-family:'Noto Serif Thai',serif;font-size:17px;font-weight:600;line-height:1.3}
.header-text p{font-size:11px;opacity:.8;margin-top:1px}
.header-actions{margin-left:auto;display:flex;gap:6px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 15px;border-radius:6px;font-size:13px;font-family:'Sarabun',sans-serif;cursor:pointer;border:none;font-weight:500;transition:all .15s;text-decoration:none}
.btn-white{background:#fff;color:var(--blue)}.btn-white:hover{background:#e8f0fa}
.btn-green{background:#059669;color:#fff}.btn-green:hover{background:#047857}
.btn-outline{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.3)}.btn-outline:hover{background:rgba(255,255,255,.2)}
.btn:disabled{opacity:.6;cursor:not-allowed}
.progress-strip{background:rgba(0,0,0,.15);height:3px}
.progress-fill{height:3px;background:#fff;transition:width .4s;width:0%}
.page{max-width:960px;margin:0 auto;padding:20px 16px}
.tab-bar{display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:20px;overflow-x:auto}
.tab{padding:10px 18px;font-size:14px;font-family:'Sarabun',sans-serif;cursor:pointer;background:none;border:none;color:var(--muted);border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap;font-weight:400;transition:all .15s}
.tab.active{color:var(--blue);border-bottom-color:var(--blue);font-weight:600}
.tab:hover:not(.active){color:var(--text)}
.pane{display:none}.pane.active{display:block}
.section{margin-bottom:18px}
.sec-title{font-family:'Noto Serif Thai',serif;font-size:15px;font-weight:600;color:var(--blue);padding:9px 13px;background:var(--blue-light);border-radius:var(--radius) var(--radius) 0 0;border-left:4px solid var(--blue)}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow)}
.card-body{padding:14px}
.grid{display:grid;gap:10px;margin-bottom:10px}
.grid-2{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
.grid-3{grid-template-columns:repeat(auto-fit,minmax(140px,1fr))}
.field{display:flex;flex-direction:column;gap:4px}
label.lbl{font-size:12px;color:var(--muted);font-weight:500;letter-spacing:.02em}
input[type=text],input[type=number],input[type=date],select,textarea{
  width:100%;padding:7px 9px;border:1px solid var(--border);border-radius:6px;
  font-family:'Sarabun',sans-serif;font-size:14px;color:var(--text);background:#fff;transition:border .15s
}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--blue-mid);box-shadow:0 0 0 3px rgba(59,125,216,.12)}
textarea{resize:vertical;min-height:68px}
.inline-pair{display:flex;gap:7px}.inline-pair input,.inline-pair select{flex:1}
.tbl-wrap{overflow-x:auto;margin-bottom:8px}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:480px}
thead th{background:var(--blue-light);color:var(--blue);font-weight:600;padding:7px 9px;text-align:left;border:1px solid var(--border);font-size:12px}
tbody td{padding:5px 7px;border:1px solid var(--border);vertical-align:middle}
tbody tr:hover{background:#fafbff}
td input,td select{border:none;background:transparent;width:100%;font-size:13px;font-family:'Sarabun',sans-serif;color:var(--text);padding:2px 3px;min-width:60px}
td input:focus,td select:focus{background:#f0f5ff;border-radius:4px;outline:none}
.del-btn{background:none;border:none;cursor:pointer;color:#dc2626;font-size:15px;padding:2px 5px;border-radius:4px;opacity:.55}
.del-btn:hover{opacity:1;background:#fef2f2}
.add-row-btn{display:inline-flex;align-items:center;gap:5px;color:var(--blue);background:none;border:1px dashed var(--blue-mid);border-radius:6px;padding:5px 13px;font-size:13px;cursor:pointer;font-family:'Sarabun',sans-serif;margin-top:5px;transition:all .15s}
.add-row-btn:hover{background:var(--blue-light)}
.check-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-top:4px}
.check-item{display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer}
.check-item input{width:15px;height:15px;accent-color:var(--blue);cursor:pointer;flex-shrink:0}
.rating-section{display:flex;flex-direction:column;gap:6px}
.rating-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:7px 0;border-bottom:1px solid var(--border)}
.rating-row:last-child{border-bottom:none}
.rating-label{flex:1;font-size:13px;min-width:160px}
.rating-btns{display:flex;gap:3px}
.rb{width:36px;height:28px;border:1px solid var(--border);border-radius:6px;background:#fff;cursor:pointer;font-size:13px;font-weight:500;color:var(--muted);font-family:'Sarabun',sans-serif;transition:all .15s}
.rb:hover{border-color:var(--blue-mid);color:var(--blue)}
.rb.on{background:var(--blue);color:#fff;border-color:var(--blue)}
.std-tbl{width:100%;border-collapse:collapse;font-size:13px}
.std-tbl th{background:var(--blue-light);padding:7px 9px;border:1px solid var(--border);font-size:12px;font-weight:600;color:var(--blue);text-align:center}
.std-tbl th:first-child{text-align:left}
.std-tbl td{padding:6px 9px;border:1px solid var(--border);text-align:center;vertical-align:middle}
.std-tbl td:first-child{text-align:left;font-size:12px;line-height:1.5}
.std-tbl input[type=radio]{width:15px;height:15px;accent-color:var(--blue);cursor:pointer}
.std-tbl tr:hover{background:#f9fbff}
.std-result{text-align:right;font-size:13px;font-weight:600;color:var(--blue);margin-top:7px;padding:7px 11px;background:var(--blue-light);border-radius:6px}
.sum-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.sum-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:13px;text-align:center;box-shadow:var(--shadow)}
.sum-label{font-size:12px;color:var(--muted);margin-bottom:3px}
.sum-val{font-size:30px;font-weight:600;color:var(--blue);line-height:1}
.sum-sub{font-size:11px;color:var(--muted);margin-top:3px}
.overall-box{background:var(--blue);color:#fff;border-radius:var(--radius);padding:18px;text-align:center;margin-bottom:18px}
.overall-box .big{font-family:'Noto Serif Thai',serif;font-size:34px;font-weight:600}
.overall-box .sub{font-size:13px;opacity:.85;margin-top:3px}
.pbar{height:7px;background:rgba(255,255,255,.2);border-radius:4px;margin-top:10px;overflow:hidden}
.pbar-fill{height:100%;background:#fff;border-radius:4px;transition:width .5s}
.sig-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;text-align:center}
.sig-line{border-bottom:1px solid var(--border);min-height:38px;margin-bottom:5px}
.sig-name{font-size:13px;font-weight:500}
.sig-pos{font-size:12px;color:var(--muted)}
.ev-btn{background:none;border:1px solid var(--border);border-radius:5px;cursor:pointer;padding:2px 6px;font-size:14px;color:var(--blue);line-height:1;transition:all .15s;flex-shrink:0}
.ev-btn:hover{background:var(--blue-light);border-color:var(--blue-mid)}
.ev-label{font-size:11px;max-width:76px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)}
.ev-label a{color:var(--blue);text-decoration:none}.ev-label a:hover{text-decoration:underline}
.legend{font-size:12px;color:var(--muted);margin-bottom:9px;background:var(--blue-light);padding:7px 11px;border-radius:6px}
@media print{
  .no-print{display:none!important}
  .pane{display:block!important}
  body{background:#fff}
  .card{box-shadow:none;border:1px solid #ccc;break-inside:avoid}
  .section{break-inside:avoid}
  .page{padding:0}
}
@media(max-width:600px){
  .header-actions{margin-left:0;width:100%}
  .sig-grid{grid-template-columns:1fr}
  .check-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <div class="header-icon">📋</div>
    <div class="header-text">
      <h1>รายงานการประเมินตนเอง (SAR)</h1>
      <p>Self Assessment Report — โรงเรียนละลมวิทยา สพม.ศรีสะเกษ ยโสธร</p>
    </div>
    <div class="header-actions no-print">
      <a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/sar/index.php" class="btn btn-outline">← กลับ</a>
      <button class="btn btn-outline" id="btn-draft" onclick="saveDraft(false)">💾 บันทึกร่าง</button>
      <button class="btn btn-green"   id="btn-submit" onclick="saveDraft(true)">✅ ส่งรายงาน</button>
      <button class="btn btn-white"   onclick="window.print()">🖨️ พิมพ์</button>
    </div>
  </div>
  <div class="progress-strip"><div class="progress-fill" id="prog"></div></div>
</header>

<div class="page">
  <div class="tab-bar no-print">
    <button class="tab active" onclick="tab(0)">ส่วนที่ 1 ข้อมูลส่วนตัว</button>
    <button class="tab" onclick="tab(1)">ส่วนที่ 2 มาตรฐานการศึกษา</button>
    <button class="tab" onclick="tab(2)">สรุปผลการประเมิน</button>
  </div>

  <!-- ══ PANE 0 — ข้อมูลส่วนตัว ══ -->
  <div id="p0" class="pane active">

    <div class="section card">
      <div class="sec-title">1.1 ข้อมูลทั่วไป</div>
      <div class="card-body">
        <div class="grid grid-2">
          <div class="field"><label class="lbl">ชื่อ</label><input type="text" id="fname" placeholder="ชื่อ" oninput="updateSig();trackProgress()"/></div>
          <div class="field"><label class="lbl">สกุล</label><input type="text" id="lname" placeholder="นามสกุล" oninput="updateSig();trackProgress()"/></div>
          <div class="field"><label class="lbl">ตำแหน่ง</label><input type="text" id="pos" placeholder="ครู" oninput="updateSig();trackProgress()"/></div>
          <div class="field"><label class="lbl">วิทยฐานะ</label><input type="text" id="vithaya" placeholder="ครูชำนาญการ" oninput="updateSig()"/></div>
        </div>
        <div class="grid grid-2">
          <div class="field"><label class="lbl">ปริญญาตรี วิชาเอก</label><input type="text" id="d1" placeholder="ภาษาไทย"/></div>
          <div class="field"><label class="lbl">จากสถาบัน</label><input type="text" id="i1" placeholder="ชื่อสถาบัน"/></div>
          <div class="field"><label class="lbl">ปริญญาโท วิชาเอก</label><input type="text" id="d2" placeholder="บริหารการศึกษา"/></div>
          <div class="field"><label class="lbl">จากสถาบัน</label><input type="text" id="i2" placeholder="ชื่อสถาบัน"/></div>
        </div>
        <div class="grid grid-3">
          <div class="field"><label class="lbl">วันเกิด</label><input type="date" id="bday"/></div>
          <div class="field"><label class="lbl">วันบรรจุ</label><input type="date" id="sdate"/></div>
          <div class="field"><label class="lbl">อายุราชการ (ปี)</label><input type="number" id="awork" min="0" placeholder="13"/></div>
          <div class="field"><label class="lbl">เงินเดือน (บาท)</label><input type="number" id="salary" placeholder="42240"/></div>
          <div class="field"><label class="lbl">เงินวิทยฐานะ (บาท)</label><input type="number" id="vsal" placeholder="3500"/></div>
          <div class="field"><label class="lbl">จำนวนวันลา</label><input type="number" id="leave" min="0" value="0"/></div>
        </div>
        <div class="grid grid-2">
          <div class="field"><label class="lbl">กลุ่มสาระที่สอน</label><input type="text" id="subj" placeholder="ภาษาไทย" oninput="trackProgress()"/></div>
          <div class="field"><label class="lbl">ภาคเรียน / ปีการศึกษา</label>
            <div class="inline-pair">
              <select id="sem" onchange="updateActLabel()">
                <option value="1">ภาคเรียนที่ 1</option>
                <option value="2" selected>ภาคเรียนที่ 2</option>
              </select>
              <input type="text" id="syear" placeholder="2567" style="max-width:88px" oninput="updateActLabel()"/>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="section card">
      <div class="sec-title">1.2 ข้อมูลการปฏิบัติหน้าที่</div>
      <div class="card-body">
        <p style="font-size:13px;font-weight:600;color:var(--blue);margin-bottom:7px">ตารางสอน</p>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>ที่</th><th>รหัสวิชา</th><th>ชื่อวิชา</th><th>ชั้น</th><th>จำนวนห้อง</th><th>ชม./สัปดาห์</th><th></th></tr></thead>
            <tbody id="teach-body"></tbody>
          </table>
        </div>
        <button class="add-row-btn" onclick="addTeach()">+ เพิ่มรายวิชา</button>

        <p style="font-size:13px;font-weight:600;color:var(--blue);margin:14px 0 7px">ครูที่ปรึกษา</p>
        <div class="grid grid-3">
          <div class="field"><label class="lbl">ชั้น</label><input type="text" id="ac" placeholder="ม.1"/></div>
          <div class="field"><label class="lbl">ห้อง</label><input type="text" id="ar" placeholder="2"/></div>
          <div class="field"><label class="lbl">จำนวนนักเรียน</label><input type="number" id="at" placeholder="30"/></div>
          <div class="field"><label class="lbl">นักเรียนชาย</label><input type="number" id="am" placeholder="12"/></div>
          <div class="field"><label class="lbl">นักเรียนหญิง</label><input type="number" id="af" placeholder="18"/></div>
        </div>

        <p style="font-size:13px;font-weight:600;color:var(--blue);margin:14px 0 7px">
          1.2.2 กิจกรรมพัฒนาผู้เรียน
          <span id="act-sem-label" style="font-weight:400;color:var(--muted)"></span>
        </p>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:36px">ที่</th>
                <th>กิจกรรมพัฒนาผู้เรียน และชุมนุม</th>
                <th style="width:80px">ชั้น / ห้อง</th>
                <th style="width:80px">จำนวนนักเรียน</th>
                <th style="width:70px">ผ่าน (คน)</th>
                <th style="width:70px">ไม่ผ่าน (คน)</th>
                <th style="width:28px"></th>
              </tr>
            </thead>
            <tbody id="act-body"></tbody>
          </table>
        </div>
        <button class="add-row-btn" onclick="addActivity()">+ เพิ่มกิจกรรม</button>
      </div>
    </div>

    <div class="section card">
      <div class="sec-title">1.3 การจัดกิจกรรมการเรียนการสอน</div>
      <div class="card-body">
        <div class="grid grid-2">
          <div class="field"><label class="lbl">สื่อ / นวัตกรรม</label><textarea id="innov" placeholder="เช่น tatuga class, QUIZIZZ..."></textarea></div>
          <div class="field"><label class="lbl">วิจัยในชั้นเรียน</label><textarea id="research" placeholder="ชื่อเรื่องวิจัย..."></textarea></div>
        </div>
        <p style="font-size:13px;font-weight:600;color:var(--blue);margin:10px 0 7px">รูปแบบการสอนที่ใช้</p>
        <div class="check-grid">
          <label class="check-item"><input type="checkbox" id="m1"/> การอธิบาย</label>
          <label class="check-item"><input type="checkbox" id="m2"/> การสืบสวนสอบสวน</label>
          <label class="check-item"><input type="checkbox" id="m3"/> การสาธิต / ทดลอง</label>
          <label class="check-item"><input type="checkbox" id="m4"/> กลุ่มสืบค้นความรู้</label>
          <label class="check-item"><input type="checkbox" id="m5"/> การใช้เกมประกอบ</label>
          <label class="check-item"><input type="checkbox" id="m6"/> กลุ่มสัมพันธ์</label>
          <label class="check-item"><input type="checkbox" id="m7"/> สถานการณ์จำลอง</label>
          <label class="check-item"><input type="checkbox" id="m8"/> การเรียนรู้แบบร่วมมือ</label>
          <label class="check-item"><input type="checkbox" id="m9"/> บทบาทสมมุติ</label>
          <label class="check-item"><input type="checkbox" id="m10"/> การศึกษาค้นคว้าด้วยตนเอง</label>
          <label class="check-item"><input type="checkbox" id="m11"/> คอมพิวเตอร์ช่วยสอน</label>
          <label class="check-item"><input type="checkbox" id="m12"/> การทัศนศึกษานอกสถานที่</label>
        </div>
      </div>
    </div>

    <div class="section card">
      <div class="sec-title">1.4 การพัฒนาตนเอง</div>
      <div class="card-body">
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>วัน/เดือน/ปี</th>
                <th>เรื่อง / การอบรม</th>
                <th>หน่วยงานที่จัด</th>
                <th style="width:72px">ชม.</th>
                <th>หลักฐาน</th>
                <th style="width:80px">ไฟล์</th>
                <th style="width:24px"></th>
              </tr>
            </thead>
            <tbody id="dev-body"></tbody>
          </table>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;flex-wrap:wrap;gap:8px">
          <button class="add-row-btn" onclick="addDev()">+ เพิ่มรายการ</button>
          <div id="dev-summary" style="font-size:12px;color:var(--muted);background:var(--blue-light);padding:5px 12px;border-radius:6px"></div>
        </div>
      </div>
    </div>

    <div class="section card">
      <div class="sec-title">1.5 รางวัล / เกียรติคุณ</div>
      <div class="card-body">
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>วัน/เดือน/ปี</th><th>รางวัล / เกียรติคุณ</th><th>หน่วยงานที่มอบ</th><th>หลักฐาน</th><th></th></tr></thead>
            <tbody id="award-body"></tbody>
          </table>
        </div>
        <button class="add-row-btn" onclick="addAward()">+ เพิ่มรางวัล</button>
      </div>
    </div>

    <div class="section card">
      <div class="sec-title">การประเมินแผนการจัดการเรียนรู้</div>
      <div class="card-body">
        <div class="legend">เกณฑ์: 4 = ดีมาก &nbsp;|&nbsp; 3 = ดี &nbsp;|&nbsp; 2 = ปานกลาง &nbsp;|&nbsp; 1 = ปรับปรุง</div>
        <div class="rating-section">
          <div class="rating-row"><span class="rating-label">1. การวิเคราะห์มาตรฐานและตัวชี้วัด</span>
            <div class="rating-btns" data-g="pl1">
              <button class="rb" onclick="setR(this,'pl1',1)">1</button><button class="rb" onclick="setR(this,'pl1',2)">2</button>
              <button class="rb" onclick="setR(this,'pl1',3)">3</button><button class="rb on" onclick="setR(this,'pl1',4)">4</button>
            </div>
          </div>
          <div class="rating-row"><span class="rating-label">2. การออกแบบกิจกรรมการเรียนรู้</span>
            <div class="rating-btns" data-g="pl2">
              <button class="rb" onclick="setR(this,'pl2',1)">1</button><button class="rb" onclick="setR(this,'pl2',2)">2</button>
              <button class="rb" onclick="setR(this,'pl2',3)">3</button><button class="rb on" onclick="setR(this,'pl2',4)">4</button>
            </div>
          </div>
          <div class="rating-row"><span class="rating-label">3. การออกแบบปฏิสัมพันธ์</span>
            <div class="rating-btns" data-g="pl3">
              <button class="rb" onclick="setR(this,'pl3',1)">1</button><button class="rb" onclick="setR(this,'pl3',2)">2</button>
              <button class="rb" onclick="setR(this,'pl3',3)">3</button><button class="rb on" onclick="setR(this,'pl3',4)">4</button>
            </div>
          </div>
          <div class="rating-row"><span class="rating-label">4. การออกแบบประเมินผล</span>
            <div class="rating-btns" data-g="pl4">
              <button class="rb" onclick="setR(this,'pl4',1)">1</button><button class="rb" onclick="setR(this,'pl4',2)">2</button>
              <button class="rb" onclick="setR(this,'pl4',3)">3</button><button class="rb on" onclick="setR(this,'pl4',4)">4</button>
            </div>
          </div>
          <div class="rating-row"><span class="rating-label">5. การใช้สื่ออุปกรณ์การเรียนรู้</span>
            <div class="rating-btns" data-g="pl5">
              <button class="rb" onclick="setR(this,'pl5',1)">1</button><button class="rb" onclick="setR(this,'pl5',2)">2</button>
              <button class="rb" onclick="setR(this,'pl5',3)">3</button><button class="rb on" onclick="setR(this,'pl5',4)">4</button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /p0 -->

  <!-- ══ PANE 1 — มาตรฐาน ══ -->
  <div id="p1" class="pane">
    <div class="section card">
      <div class="sec-title">มาตรฐานที่ 1 — คุณภาพของผู้เรียน</div>
      <div class="card-body">
        <div class="legend">ระดับ: 5=ยอดเยี่ยม (90-100%) &nbsp;|&nbsp; 4=ดีเลิศ (80-89%) &nbsp;|&nbsp; 3=ดี (70-79%) &nbsp;|&nbsp; 2=ปานกลาง &nbsp;|&nbsp; 1=กำลังพัฒนา</div>
        <div class="tbl-wrap">
          <table class="std-tbl">
            <thead><tr><th>ตัวบ่งชี้</th><th>5</th><th>4</th><th>3</th><th>2</th><th>1</th></tr></thead>
            <tbody id="s1-body"></tbody>
          </table>
        </div>
        <div class="std-result">สรุประดับมาตรฐานที่ 1: <span id="s1-res">—</span></div>
      </div>
    </div>

    <div class="section card">
      <div class="sec-title">มาตรฐานที่ 2 — กระบวนการบริหารและการจัดการ</div>
      <div class="card-body">
        <div class="tbl-wrap">
          <table class="std-tbl">
            <thead><tr><th>ตัวบ่งชี้</th><th>5</th><th>4</th><th>3</th><th>2</th><th>1</th></tr></thead>
            <tbody id="s2-body"></tbody>
          </table>
        </div>
        <div class="std-result">สรุประดับมาตรฐานที่ 2: <span id="s2-res">—</span></div>
      </div>
    </div>

    <div class="section card">
      <div class="sec-title">มาตรฐานที่ 3 — กระบวนการจัดการเรียนการสอนที่เน้นผู้เรียนเป็นสำคัญ</div>
      <div class="card-body">
        <div class="tbl-wrap">
          <table class="std-tbl">
            <thead><tr><th>ตัวบ่งชี้</th><th>5</th><th>4</th><th>3</th><th>2</th><th>1</th></tr></thead>
            <tbody id="s3-body"></tbody>
          </table>
        </div>
        <div class="std-result">สรุประดับมาตรฐานที่ 3: <span id="s3-res">—</span></div>
      </div>
    </div>
  </div><!-- /p1 -->

  <!-- ══ PANE 2 — สรุป ══ -->
  <div id="p2" class="pane">
    <div class="overall-box">
      <div style="font-size:13px;opacity:.8;margin-bottom:3px">ผลการประเมินภาพรวม</div>
      <div class="big" id="ov-score">—</div>
      <div class="sub" id="ov-label">กรุณากรอกข้อมูลให้ครบก่อน</div>
      <div class="pbar"><div class="pbar-fill" id="ov-bar" style="width:0%"></div></div>
    </div>

    <div class="sum-grid">
      <div class="sum-card"><div class="sum-label">แผนการสอน</div><div class="sum-val" id="sv-plan">—</div><div class="sum-sub">จาก 4</div></div>
      <div class="sum-card"><div class="sum-label">มาตรฐานที่ 1</div><div class="sum-val" id="sv-s1">—</div><div class="sum-sub">จาก 5</div></div>
      <div class="sum-card"><div class="sum-label">มาตรฐานที่ 2</div><div class="sum-val" id="sv-s2">—</div><div class="sum-sub">จาก 5</div></div>
      <div class="sum-card"><div class="sum-label">มาตรฐานที่ 3</div><div class="sum-val" id="sv-s3">—</div><div class="sum-sub">จาก 5</div></div>
    </div>

    <div class="section card">
      <div class="sec-title">การรับรองรายงานการประเมินตนเอง</div>
      <div class="card-body">
        <div class="sig-grid">
          <div>
            <div class="sig-line"></div>
            <div class="sig-name">ผู้รายงาน</div>
            <div class="sig-pos" id="sig-name">( ........................ )</div>
            <div class="sig-pos" id="sig-pos2">ครู</div>
          </div>
          <div>
            <div class="sig-line"></div>
            <div class="sig-name">ผู้รับรองรายงาน</div>
            <div class="sig-pos">(นางสุภาพร ชิตภักดิ์)</div>
            <div class="sig-pos">หัวหน้าฝ่ายบริหารงานบุคคล</div>
          </div>
          <div>
            <div class="sig-line"></div>
            <div class="sig-name">ผู้รับรองรายงาน</div>
            <div class="sig-pos">(นายสถาน ปรางมาศ)</div>
            <div class="sig-pos">ผู้อำนวยการโรงเรียนละลมวิทยา</div>
          </div>
        </div>
      </div>
    </div>
  </div><!-- /p2 -->

</div><!-- /page -->

<script>
const SAR_ID    = <?= $sarId ?>;
const BASE_PATH = <?= json_encode($basePath) ?>;
let currentId   = SAR_ID;

// ── Standard indicators ──
const std1 = [
  "ผู้เรียนมีความสามารถในการอ่าน เขียน สื่อสาร และคิดคำนวณ",
  "ผู้เรียนมีความสามารถในการคิดวิเคราะห์ คิดอย่างมีวิจารณญาณ",
  "ผู้เรียนมีความสามารถในการสร้างนวัตกรรม",
  "ผู้เรียนมีความสามารถในการใช้เทคโนโลยีสารสนเทศและการสื่อสาร",
  "ผู้เรียนมีผลสัมฤทธิ์ทางการเรียนตามหลักสูตรสถานศึกษา",
  "ผู้เรียนมีความรู้ ทักษะพื้นฐาน และเจตคติที่ดีต่องานอาชีพ",
  "การมีคุณลักษณะและค่านิยมที่ดีตามที่สถานศึกษากำหนด",
  "ความภูมิใจในท้องถิ่นและความเป็นไทย",
  "การยอมรับที่จะอยู่ร่วมกันบนความแตกต่างและหลากหลาย",
  "สุขภาวะทางร่างกายและจิตสังคม"
];
const std2 = [
  "มีเป้าหมาย วิสัยทัศน์ และพันธกิจที่ชัดเจน",
  "การวางแผนพัฒนาคุณภาพการจัดการศึกษาอย่างเป็นระบบ",
  "การวางแผนและดำเนินงานพัฒนาวิชาการที่เน้นคุณภาพผู้เรียน",
  "พัฒนาครูและบุคลากรให้มีความเชี่ยวชาญทางวิชาชีพ (PLC, อบรม, นิเทศ)",
  "จัดสภาพแวดล้อมทางกายภาพและสังคมที่เอื้อต่อการเรียนรู้",
  "จัดระบบเทคโนโลยีสารสนเทศเพื่อสนับสนุนการบริหารจัดการ"
];
const std3 = [
  "ครูจัดการเรียนรู้ด้วยกระบวนการ Active Learning ทักษะศตวรรษที่ 21",
  "ครูจัดการเรียนรู้โดยใช้สื่อเทคโนโลยี สื่อ DLIT และแหล่งเรียนรู้",
  "มีการบริหารจัดการชั้นเรียนเชิงบวก",
  "ตรวจสอบและประเมินผู้เรียนอย่างเป็นระบบ นำผลมาพัฒนา (วิจัย)",
  "มีการแลกเปลี่ยนเรียนรู้ สะท้อนกลับ AAR/PLC เพื่อพัฒนาการสอน"
];

function buildStd(items, tbodyId, resId, sumId) {
  const tb = document.getElementById(tbodyId);
  items.forEach((item, i) => {
    const tr = document.createElement('tr');
    let html = `<td>${item}</td>`;
    for (let v = 5; v >= 1; v--)
      html += `<td><input type="radio" name="${tbodyId}_${i}" value="${v}" onchange="calcStd('${tbodyId}','${resId}','${sumId}')"/></td>`;
    tr.innerHTML = html;
    tb.appendChild(tr);
  });
}
buildStd(std1, 's1-body', 's1-res', 'sv-s1');
buildStd(std2, 's2-body', 's2-res', 'sv-s2');
buildStd(std3, 's3-body', 's3-res', 'sv-s3');

const lbl = {5:'ยอดเยี่ยม',4:'ดีเลิศ',3:'ดี',2:'ปานกลาง',1:'กำลังพัฒนา'};

function calcStd(tbodyId, resId, sumId) {
  const checked = [...document.querySelectorAll(`[name^="${tbodyId}_"]:checked`)];
  if (!checked.length) return;
  const avg = Math.round(checked.reduce((s, r) => s + parseInt(r.value), 0) / checked.length);
  document.getElementById(resId).textContent = `ระดับ ${avg} — ${lbl[avg]}`;
  if (sumId) document.getElementById(sumId).textContent = avg;
  updateOverall();
}

function getAvg(tbodyId) {
  const checked = [...document.querySelectorAll(`[name^="${tbodyId}_"]:checked`)];
  if (!checked.length) return null;
  return Math.round(checked.reduce((s, r) => s + parseInt(r.value), 0) / checked.length);
}

const planVals = {pl1:4,pl2:4,pl3:4,pl4:4,pl5:4};
function setR(btn, g, v) {
  planVals[g] = v;
  btn.parentElement.querySelectorAll('.rb').forEach(b => b.classList.toggle('on', parseInt(b.textContent) === v));
  updateOverall();
}

function updateOverall() {
  const planAvg = Math.round(Object.values(planVals).reduce((a, b) => a + b, 0) / 5);
  document.getElementById('sv-plan').textContent = planAvg;
  const s1 = getAvg('s1-body'), s2 = getAvg('s2-body'), s3 = getAvg('s3-body');
  if (s1) document.getElementById('sv-s1').textContent = s1;
  if (s2) document.getElementById('sv-s2').textContent = s2;
  if (s3) document.getElementById('sv-s3').textContent = s3;
  const vals = [s1, s2, s3].filter(v => v !== null);
  if (!vals.length) return;
  const ov = Math.round(vals.reduce((a, b) => a + b, 0) / vals.length);
  document.getElementById('ov-score').textContent = `ระดับ ${ov}`;
  document.getElementById('ov-label').textContent = lbl[ov] || '';
  document.getElementById('ov-bar').style.width = `${ov * 20}%`;
}

function tab(i) {
  document.querySelectorAll('.tab').forEach((t, j) => t.classList.toggle('active', i === j));
  document.querySelectorAll('.pane').forEach((p, j) => p.classList.toggle('active', i === j));
  updateOverall();
}

function delRow(btn) { btn.closest('tr').remove(); }

function addTeach() {
  const tb = document.getElementById('teach-body');
  const n  = tb.rows.length + 1;
  tb.insertAdjacentHTML('beforeend',
    `<tr><td>${n}</td><td><input type="text"/></td><td><input type="text"/></td>` +
    `<td><input type="text"/></td><td><input type="number" min="0" value="1"/></td>` +
    `<td><input type="number" min="0" value="0"/></td>` +
    `<td><button class="del-btn" onclick="delRow(this)">✕</button></td></tr>`);
}
function addDev() {
  document.getElementById('dev-body').insertAdjacentHTML('beforeend',
    `<tr>
      <td><input type="date"/></td>
      <td><input type="text" placeholder="เรื่อง / การอบรม"/></td>
      <td><input type="text" placeholder="หน่วยงานที่จัด"/></td>
      <td><input type="number" min="0" value="0" class="dev-hours" style="text-align:center" oninput="updateDevSummary()"/></td>
      <td><input type="text" placeholder="ชื่อหลักฐาน"/></td>
      <td><div style="display:flex;align-items:center;gap:3px">
        <input type="file" class="ev-input" style="display:none" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="uploadEvidence(this)"/>
        <input type="hidden" class="ev-path" value=""/>
        <input type="hidden" class="ev-name-val" value=""/>
        <button type="button" class="ev-btn" onclick="this.parentElement.querySelector('.ev-input').click()">📎</button>
        <span class="ev-label"></span>
      </div></td>
      <td><button class="del-btn" onclick="delRowDev(this)">✕</button></td>
    </tr>`);
  updateDevSummary();
}
function delRowDev(btn) { btn.closest('tr').remove(); updateDevSummary(); }
function updateDevSummary() {
  const rows = document.querySelectorAll('#dev-body tr');
  let total = 0;
  rows.forEach(tr => { const h = tr.querySelector('.dev-hours'); if (h) total += parseFloat(h.value) || 0; });
  const el = document.getElementById('dev-summary');
  if (el) el.textContent = rows.length ? `อบรม ${rows.length} ครั้ง | รวม ${total} ชั่วโมง` : '';
}
async function uploadEvidence(input) {
  if (!input.files || !input.files[0]) return;
  const td  = input.closest('td');
  const btn = td.querySelector('.ev-btn');
  const lbl = td.querySelector('.ev-label');
  const evp = td.querySelector('.ev-path');
  const evn = td.querySelector('.ev-name-val');
  if (btn) btn.textContent = '⏳';
  try {
    const fd = new FormData();
    fd.append('file', input.files[0]);
    const resp = await fetch(BASE_PATH + '/sar/api/upload_evidence.php', {method:'POST', body:fd});
    const json = await resp.json();
    if (json.status === 'success') {
      evp.value = json.path;
      evn.value = json.filename;
      const sn = json.filename.length > 14 ? json.filename.substring(0, 14) + '…' : json.filename;
      lbl.innerHTML = `<a href="${BASE_PATH}/sar/api/serve_evidence.php?f=${encodeURIComponent(json.path)}" target="_blank" title="${esc(json.filename)}">${esc(sn)}</a>`;
    } else {
      Swal.fire({icon:'error', title:'อัปโหลดไม่สำเร็จ', text: json.message || 'ลองใหม่'});
    }
  } catch(e) {
    Swal.fire({icon:'error', title:'เกิดข้อผิดพลาด', text:'ไม่สามารถอัปโหลดได้'});
  } finally {
    if (btn) btn.textContent = '📎';
    input.value = '';
  }
}
function addAward() {
  document.getElementById('award-body').insertAdjacentHTML('beforeend',
    `<tr><td><input type="date"/></td><td><input type="text"/></td>` +
    `<td><input type="text"/></td><td><input type="text"/></td>` +
    `<td><button class="del-btn" onclick="delRow(this)">✕</button></td></tr>`);
}

function addActivity() {
  const tb = document.getElementById('act-body');
  const n  = tb.rows.length + 1;
  tb.insertAdjacentHTML('beforeend',
    `<tr><td>${n}</td><td><input type="text"/></td>` +
    `<td><input type="text"/></td><td><input type="number" min="0" value="0"/></td>` +
    `<td><input type="number" min="0" value="0"/></td>` +
    `<td><input type="number" min="0" value="0"/></td>` +
    `<td><button class="del-btn" onclick="delRow(this)">✕</button></td></tr>`);
}

function updateActLabel() {
  const sem  = document.getElementById('sem')?.value || '';
  const year = document.getElementById('syear')?.value || '';
  const el   = document.getElementById('act-sem-label');
  if (el) el.textContent = (sem || year) ? `— ภาคเรียนที่ ${sem} ปีการศึกษา ${year}` : '';
}

function updateSig() {
  const fn = document.getElementById('fname').value;
  const ln = document.getElementById('lname').value;
  const vt = document.getElementById('vithaya').value;
  document.getElementById('sig-name').textContent = `( ${fn||'...'} ${ln||''} )`;
  if (vt) document.getElementById('sig-pos2').textContent = vt;
}

function trackProgress() {
  const filled = ['fname','lname','pos','subj'].filter(id => document.getElementById(id)?.value.trim()).length;
  document.getElementById('prog').style.width = `${filled * 25}%`;
  updateActLabel();
}

// ── Collect all form data ──
function collectFormData() {
  const d = {};
  ['fname','lname','pos','vithaya','d1','i1','d2','i2','bday','sdate','awork',
   'salary','vsal','leave','subj','syear','innov','research','ac','ar','at','am','af'
  ].forEach(id => { const el = document.getElementById(id); if (el) d[id] = el.value; });
  d.sem = document.getElementById('sem')?.value || '2';

  d.methods = [];
  for (let i = 1; i <= 12; i++) {
    const cb = document.getElementById(`m${i}`);
    if (cb?.checked) d.methods.push(`m${i}`);
  }

  d.teach_rows = [];
  document.querySelectorAll('#teach-body tr').forEach(tr => {
    const cells = tr.querySelectorAll('td');
    if (cells.length >= 6) {
      d.teach_rows.push({
        code:  cells[1].querySelector('input')?.value || '',
        name:  cells[2].querySelector('input')?.value || '',
        cls:   cells[3].querySelector('input')?.value || '',
        rooms: cells[4].querySelector('input')?.value || '',
        hours: cells[5].querySelector('input')?.value || ''
      });
    }
  });

  d.dev_rows = [];
  document.querySelectorAll('#dev-body tr').forEach(tr => {
    const cells = tr.querySelectorAll('td');
    if (cells.length < 7) return;
    d.dev_rows.push({
      date:      cells[0].querySelector('input')?.value || '',
      title:     cells[1].querySelector('input')?.value || '',
      org:       cells[2].querySelector('input')?.value || '',
      hours:     cells[3].querySelector('input')?.value || '0',
      evidence:  cells[4].querySelector('input')?.value || '',
      file_path: cells[5].querySelector('.ev-path')?.value || '',
      file_name: cells[5].querySelector('.ev-name-val')?.value || ''
    });
  });

  d.award_rows = [];
  document.querySelectorAll('#award-body tr').forEach(tr => {
    const ins = tr.querySelectorAll('input');
    if (ins.length >= 4)
      d.award_rows.push({date: ins[0].value, title: ins[1].value, org: ins[2].value, evidence: ins[3].value});
  });

  d.activity_rows = [];
  document.querySelectorAll('#act-body tr').forEach(tr => {
    const cells = tr.querySelectorAll('td');
    if (cells.length >= 6) {
      d.activity_rows.push({
        name:    cells[1].querySelector('input')?.value || '',
        cls:     cells[2].querySelector('input')?.value || '',
        total:   cells[3].querySelector('input')?.value || '',
        pass:    cells[4].querySelector('input')?.value || '',
        fail:    cells[5].querySelector('input')?.value || ''
      });
    }
  });

  d.plan_ratings = {...planVals};

  ['s1','s2','s3'].forEach(s => {
    const rows = document.getElementById(`${s}-body`)?.rows.length || 0;
    d[s] = [];
    for (let i = 0; i < rows; i++) {
      const checked = document.querySelector(`[name="${s}-body_${i}"]:checked`);
      d[s].push(checked ? parseInt(checked.value) : null);
    }
  });

  return d;
}

// ── Load form data from object ──
function esc(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function loadFormData(data) {
  if (!data || typeof data !== 'object') return;
  ['fname','lname','pos','vithaya','d1','i1','d2','i2','bday','sdate','awork',
   'salary','vsal','leave','subj','syear','innov','research','ac','ar','at','am','af'
  ].forEach(id => {
    const el = document.getElementById(id);
    if (el && data[id] !== undefined && data[id] !== null) el.value = data[id];
  });
  if (data.sem) document.getElementById('sem').value = data.sem;

  if (Array.isArray(data.methods))
    data.methods.forEach(id => { const cb = document.getElementById(id); if (cb) cb.checked = true; });

  const tb = document.getElementById('teach-body');
  if (Array.isArray(data.teach_rows) && data.teach_rows.length) {
    tb.innerHTML = '';
    data.teach_rows.forEach((row, i) => {
      tb.insertAdjacentHTML('beforeend',
        `<tr><td>${i+1}</td>` +
        `<td><input type="text" value="${esc(row.code)}"/></td>` +
        `<td><input type="text" value="${esc(row.name)}"/></td>` +
        `<td><input type="text" value="${esc(row.cls)}"/></td>` +
        `<td><input type="number" min="0" value="${esc(row.rooms)}"/></td>` +
        `<td><input type="number" min="0" value="${esc(row.hours)}"/></td>` +
        `<td><button class="del-btn" onclick="delRow(this)">✕</button></td></tr>`);
    });
  }
  if (!tb.rows.length) addTeach();

  const db = document.getElementById('dev-body');
  if (Array.isArray(data.dev_rows) && data.dev_rows.length) {
    db.innerHTML = '';
    data.dev_rows.forEach(row => {
      const fp = row.file_path || '';
      const fn = row.file_name || '';
      const sn = fn.length > 14 ? fn.substring(0, 14) + '…' : fn;
      const fileLabel = fp
        ? `<a href="${BASE_PATH}/sar/api/serve_evidence.php?f=${encodeURIComponent(fp)}" target="_blank" title="${esc(fn)}">${esc(sn)}</a>`
        : '';
      db.insertAdjacentHTML('beforeend',
        `<tr>
          <td><input type="date" value="${esc(row.date)}"/></td>
          <td><input type="text" value="${esc(row.title)}"/></td>
          <td><input type="text" value="${esc(row.org)}"/></td>
          <td><input type="number" min="0" value="${esc(row.hours||'0')}" class="dev-hours" style="text-align:center" oninput="updateDevSummary()"/></td>
          <td><input type="text" value="${esc(row.evidence)}"/></td>
          <td><div style="display:flex;align-items:center;gap:3px">
            <input type="file" class="ev-input" style="display:none" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="uploadEvidence(this)"/>
            <input type="hidden" class="ev-path" value="${esc(fp)}"/>
            <input type="hidden" class="ev-name-val" value="${esc(fn)}"/>
            <button type="button" class="ev-btn" onclick="this.parentElement.querySelector('.ev-input').click()">📎</button>
            <span class="ev-label">${fileLabel}</span>
          </div></td>
          <td><button class="del-btn" onclick="delRowDev(this)">✕</button></td>
        </tr>`);
    });
  }
  if (!db.rows.length) addDev();
  updateDevSummary();

  const ab = document.getElementById('award-body');
  if (Array.isArray(data.award_rows) && data.award_rows.length) {
    ab.innerHTML = '';
    data.award_rows.forEach(row => {
      ab.insertAdjacentHTML('beforeend',
        `<tr><td><input type="date" value="${esc(row.date)}"/></td>` +
        `<td><input type="text" value="${esc(row.title)}"/></td>` +
        `<td><input type="text" value="${esc(row.org)}"/></td>` +
        `<td><input type="text" value="${esc(row.evidence)}"/></td>` +
        `<td><button class="del-btn" onclick="delRow(this)">✕</button></td></tr>`);
    });
  }
  if (!ab.rows.length) addAward();

  const actb = document.getElementById('act-body');
  if (Array.isArray(data.activity_rows) && data.activity_rows.length) {
    actb.innerHTML = '';
    data.activity_rows.forEach((row, i) => {
      actb.insertAdjacentHTML('beforeend',
        `<tr><td>${i+1}</td>` +
        `<td><input type="text" value="${esc(row.name)}"/></td>` +
        `<td><input type="text" value="${esc(row.cls)}"/></td>` +
        `<td><input type="number" min="0" value="${esc(row.total)}"/></td>` +
        `<td><input type="number" min="0" value="${esc(row.pass)}"/></td>` +
        `<td><input type="number" min="0" value="${esc(row.fail)}"/></td>` +
        `<td><button class="del-btn" onclick="delRow(this)">✕</button></td></tr>`);
    });
  }
  if (!actb.rows.length) addActivity();

  if (data.plan_ratings && typeof data.plan_ratings === 'object') {
    Object.entries(data.plan_ratings).forEach(([g, v]) => {
      planVals[g] = parseInt(v) || 4;
      document.querySelectorAll(`[data-g="${g}"] .rb`).forEach(b =>
        b.classList.toggle('on', parseInt(b.textContent) === planVals[g]));
    });
  }

  ['s1','s2','s3'].forEach(s => {
    if (!Array.isArray(data[s])) return;
    data[s].forEach((val, i) => {
      if (val !== null && val !== undefined) {
        const radio = document.querySelector(`[name="${s}-body_${i}"][value="${val}"]`);
        if (radio) radio.checked = true;
      }
    });
    calcStd(`${s}-body`, `${s}-res`, `sv-${s}`);
  });

  updateSig();
  trackProgress();
  updateOverall();
}

// ── Save to DB ──
async function saveDraft(submit = false) {
  const btnId = submit ? 'btn-submit' : 'btn-draft';
  const btn   = document.getElementById(btnId);
  if (btn) { btn.disabled = true; btn.textContent = '⏳ กำลังบันทึก...'; }
  try {
    const resp = await fetch(BASE_PATH + '/sar/api/save.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({id: currentId, submit: submit, data: collectFormData()})
    });
    const json = await resp.json();
    if (json.status === 'success') {
      if (!currentId && json.id) {
        currentId = json.id;
        history.replaceState(null, '', '?id=' + json.id);
      }
      Swal.fire({
        icon: 'success',
        title: submit ? 'ส่งรายงานแล้ว!' : 'บันทึกแล้ว',
        text:  submit ? 'รายงาน SAR ถูกส่งเรียบร้อย' : 'บันทึกร่างเรียบร้อย',
        timer: 1800, showConfirmButton: false
      });
    } else {
      Swal.fire({icon:'error', title:'ผิดพลาด', text: json.message || 'ไม่สามารถบันทึกได้'});
    }
  } catch (e) {
    Swal.fire({icon:'error', title:'เกิดข้อผิดพลาด', text:'ไม่สามารถเชื่อมต่อ server ได้'});
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = submit ? '✅ ส่งรายงาน' : '💾 บันทึกร่าง';
    }
  }
}

// ── Init ──
loadFormData(<?= $formDataJson ?>);
updateOverall();

<?php if ($autoPrint): ?>
document.querySelectorAll('.pane').forEach(p => { p.style.display = 'block'; });
document.querySelectorAll('.no-print').forEach(el => { el.style.display = 'none'; });
window.addEventListener('load', () => setTimeout(() => window.print(), 600));
<?php endif; ?>
</script>
</body>
</html>
