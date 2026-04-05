# CLAUDE.md — LLW System Development Guide

## Project Overview

ระบบบริหารจัดการโรงเรียนละลมวิทยา (Lalom Wittaya School Management System)
PHP 7.4+ / MySQL / Tailwind CSS CDN / Bootstrap Icons

Portal รวม 4 โมดูล: เช็คชื่อนักเรียน, Chromebook, ลงเวลาบุคลากร, ขออนุญาตออกนอก

## Architecture

```
├── config/           → DB connections (database.php = หลัก, db_central.php, db_project.php)
├── api/              → REST JSON endpoints (auth, save_request, approve_action, get_*)
├── components/       → Shared UI (layout_start.php → header + sidebar, layout_end.php)
├── attendance_system/ → ระบบเช็คชื่อ (มี functions.php รวม helpers)
├── chromebook/       → ระบบ Chromebook
├── admin/            → WFH Admin dashboards
├── user/             → WFH Staff dashboards
├── includes/         → Helpers (telegram_bot.php)
└── .github/workflows/ → Auto deploy via FTP (lftp)
```

## Database

- ฐานข้อมูลเดียว: `llw_db` — ตาราง prefix: `llw_*`, `wfh_*`, `cb_*`, `att_*`
- ใช้ทั้ง MySQLi (WFH system) และ PDO (Chromebook, Attendance, API)
- Connection factories: `getWfhConn()` → MySQLi, `getPdo()` → PDO
- Charset: utf8mb4 เสมอ

## Page Template (ทุกหน้าต้องตามรูปแบบนี้)

```php
<?php
session_start();
require_once __DIR__ . '/../config.php'; // หรือ config/database.php

// 1. Auth guard
if (!isset($_SESSION['llw_role'])) { header('Location: /login.php'); exit(); }

// 2. Data fetching (prepared statements เท่านั้น)
$stmt = $conn->prepare("SELECT * FROM table WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

// 3. Set page variables for layout
$pageTitle = 'ชื่อหน้า';
$activeSystem = 'attendance'; // สำหรับ sidebar highlight

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- 4. Page content (Tailwind CSS) -->

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
```

---

## Coding Standards (บังคับ)

### Security — กฎเหล็ก ห้ามละเมิด

1. **SQL Injection**: ใช้ Prepared Statements เท่านั้น ห้าม interpolate ตัวแปรใน SQL โดยเด็ดขาด
   ```php
   // ถูก
   $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
   $stmt->execute([$id]);

   // ผิด — ห้ามทำแบบนี้เด็ดขาด
   $pdo->query("SELECT * FROM users WHERE id = $id");
   $conn->query("SELECT * FROM wfh_timelogs WHERE log_date='$today'");
   ```

2. **XSS Prevention**: ทุกครั้งที่แสดงข้อมูลจาก DB หรือ user input ต้อง escape
   ```php
   // ถูก
   <?= htmlspecialchars($user['firstname'], ENT_QUOTES, 'UTF-8') ?>

   // ผิด
   <?= $user['firstname'] ?>
   <?= $_SESSION['firstname'] ?>
   ```

3. **Auth Guard**: ทุกหน้าที่ต้อง login ต้องมี session check + role check ก่อน logic ใดๆ
   ```php
   session_start();
   if (!isset($_SESSION['llw_role'])) { header('Location: /login.php'); exit(); }
   // ตรวจ role เฉพาะ (ถ้าจำเป็น)
   if ($_SESSION['llw_role'] !== 'super_admin') { header('Location: /index.php'); exit(); }
   ```

4. **Password**: ใช้ `password_hash()` / `password_verify()` เท่านั้น ห้าม MD5, SHA1

5. **File Upload**: validate mime type + extension + ขนาด, ใช้ชื่อไฟล์ random, เก็บนอก webroot ถ้าเป็นไปได้

6. **API Endpoints**: ต้อง validate input ทุก field, ใช้ `json_decode(file_get_contents('php://input'), true)`, return JSON เสมอ

### PHP Standards

- ใช้ `<?php` เต็มรูปแบบ ไม่ใช้ short tag `<?`
- Short echo `<?= ?>` ใช้ได้เฉพาะใน template
- ใช้ strict comparison `===` แทน `==` เมื่อเป็นไปได้
- Error handling ใช้ try-catch สำหรับ DB operations
- Transactions สำหรับ multi-step writes: `$pdo->beginTransaction()` → `commit()` / `rollBack()`
- ตั้งชื่อไฟล์ lowercase + underscore: `manage_users.php`, `save_request.php`
- ตั้งชื่อตาราง DB: `{module}_{entity}` เช่น `att_students`, `cb_borrow_logs`

### API Pattern (สำหรับ /api/ endpoints)

```php
<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // ... logic with prepared statements

    $pdo->commit();
    echo json_encode(['status' => 'success', 'data' => $result]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
    error_log($e->getMessage()); // log จริง ไม่ expose ให้ user
}
```

---

## UI/Design System (Tailwind CSS)

### Dependencies (CDN — ใส่ใน layout_start.php)

```html
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
```

### Typography

- Font: **Prompt** (Thai-optimized) — `font-family: 'Prompt', sans-serif`
- Headings: `font-black` (900) หรือ `font-bold` (700)
- Body: `font-normal` (400)
- Labels/Badges: `text-[9px] font-black uppercase tracking-wider`

### Color Palette

| ใช้สำหรับ | Primary | Success | Warning | Danger | Info |
|---|---|---|---|---|---|
| Color | `blue-600` | `emerald-500` | `amber-500` | `rose-500` | `indigo-600` |
| Light BG | `blue-50` | `emerald-50` | `amber-50` | `rose-50` | `indigo-50` |
| Shadow | `shadow-blue-200` | `shadow-emerald-200` | `shadow-amber-200` | `shadow-rose-200` | `shadow-indigo-200` |

### Component Patterns

**Card**:
```html
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100">
  <h3 class="text-lg font-black text-slate-800">Title</h3>
  <p class="text-sm text-slate-500 mt-1">Description</p>
</div>
```

**Glass Card** (สำหรับ overlay, modal, login):
```html
<div class="bg-white/70 backdrop-blur-xl rounded-[32px] shadow-2xl border border-white/50 p-10">
```

**KPI Stat Card**:
```html
<div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl p-6 text-white shadow-xl shadow-blue-200">
  <p class="text-xs font-bold opacity-80 uppercase tracking-wider">Label</p>
  <p class="text-4xl font-black mt-2">123</p>
</div>
```

**Button — Primary**:
```html
<button class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] transition-all">
  <i class="bi bi-plus-lg mr-2"></i>Action
</button>
```

**Button — Danger**:
```html
<button class="bg-rose-500 text-white px-4 py-2 rounded-xl font-bold shadow-lg shadow-rose-200 hover:bg-rose-600 transition-all">
  <i class="bi bi-trash3 mr-1"></i>Delete
</button>
```

**Badge/Tag**:
```html
<span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold">Active</span>
```

**Table**:
```html
<div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-slate-100">
  <table class="w-full">
    <thead>
      <tr class="bg-slate-50 border-b border-slate-100">
        <th class="text-left text-xs font-bold text-slate-400 uppercase tracking-wider px-6 py-4">Column</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-50">
      <tr class="hover:bg-blue-50/50 transition">
        <td class="px-6 py-4 text-sm text-slate-700">Data</td>
      </tr>
    </tbody>
  </table>
</div>
```

**Form Input**:
```html
<div>
  <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Label</label>
  <input type="text" name="field" required
         class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
</div>
```

**Alert / Notification (SweetAlert2)**:
```html
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    icon: 'success',
    title: 'สำเร็จ',
    text: 'บันทึกข้อมูลเรียบร้อย',
    confirmButtonColor: '#2563eb'
});
</script>
```

### Layout Grid

```html
<!-- Responsive grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
  <!-- cards -->
</div>
```

### Design Principles

- **Border Radius**: ใช้ `rounded-2xl` (16px) เป็นหลัก, `rounded-[32px]` สำหรับ hero/glass
- **Shadows**: ใช้ shadow คู่สี เช่น `shadow-xl shadow-blue-100/50` — ไม่ใช้ shadow ดำ
- **Spacing**: ใช้ `gap-6` หรือ `gap-8` ระหว่าง sections, `p-6` ภายใน card
- **Transitions**: ทุก interactive element ต้องมี `transition-all` + hover effect
- **Mobile First**: เริ่มจาก mobile (`grid-cols-1`) แล้วขยาย (`md:`, `lg:`)
- **Icons**: Bootstrap Icons (`bi-*`) — ใส่ก่อนข้อความเสมอ เช่น `<i class="bi bi-plus-lg mr-2"></i>`

---

## Deployment

- **Auto Deploy**: push to `main` → GitHub Actions → lftp FTP upload
- **Config**: GitHub Secrets สร้าง config/database.php อัตโนมัติ (ไม่ hardcode credentials)
- **ไฟล์ที่ไม่ขึ้น production**: `test_*.php`, `fix_*.php`, `*.md`, `.git/`, `.github/`
- **ห้ามสร้างไฟล์ `setup.php` หรือ debug files** ที่ expose ข้อมูล DB บน production

## File Naming

- Pages: `lowercase_underscore.php` เช่น `manage_users.php`
- Components: `lowercase_underscore.php` ใน `/components/`
- API: verb_noun.php เช่น `save_request.php`, `get_teachers.php`
- Config: `db_{name}.php`

## Language

- UI ทั้งหมดเป็น **ภาษาไทย**
- Code comments เป็นไทยหรืออังกฤษก็ได้
- Variable names / function names เป็น **ภาษาอังกฤษ** เสมอ
