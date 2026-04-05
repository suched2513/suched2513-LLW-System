# CLAUDE.md — LLW System Complete Development Guide

> คู่มือนี้ออกแบบมาให้ AI หรือนักพัฒนาคนใดก็ตามอ่านแล้วเข้าใจระบบทั้งหมด สามารถเขียนโค้ดต่อได้ทันที

## Project Overview

**LLW System** = ระบบบริหารจัดการโรงเรียนละลมวิทยา (Lalom Wittaya School Management System)

| Item | Value |
|---|---|
| Stack | PHP 7.4+ / MySQL / Tailwind CSS (CDN) / Bootstrap Icons |
| Production URL | `https://llw.krusuched.com/` |
| Database | MySQL single DB: `llw_db` (production: `krusuche_llw`) |
| Deploy | Auto via GitHub Actions → FTP (lftp) on push to `main` |
| Auth | Session-based, unified via `$_SESSION['llw_role']` |
| UI Language | ภาษาไทย |
| Code Language | English (variables, functions) |

### 4 โมดูลหลัก

| # | โมดูล | Prefix | สี | Entry Point |
|---|---|---|---|---|
| 1 | เช็คชื่อนักเรียน (Attendance) | `att_` | indigo | `attendance_system/dashboard.php` |
| 2 | จัดการ Chromebook | `cb_` | cyan | `chromebook/index.php` |
| 3 | ลงเวลาบุคลากร (WFH) | `wfh_` | emerald | `index_wfh.php` → `user/dashboard.php` |
| 4 | ขออนุญาตออกนอก (Leave) | — | rose | `leave_system.php` |

---

## Architecture & File Map

```
LLW-System/
├── index.php                  → Landing page (standalone, ไม่ใช้ layout)
├── login.php                  → Unified login (form + processor)
├── auth.php                   → Login processor (POST only, redirect by role)
├── logout.php                 → Destroy session → redirect index.php
├── central_dashboard.php      → Super Admin panel (CRUD users, KPIs)
├── index_wfh.php              → WFH entry (redirect by role)
├── leave_system.php           → Leave request UI
├── config.php                 → Loader: require config/database.php + getWfhConn()
│
├── config/
│   ├── database.php           → DB_HOST/USER/PASS/NAME defines + getWfhConn() + getPdo()
│   ├── db_central.php         → $pdo_central (school_central_db) — legacy
│   └── db_project.php         → $pdo_project (exit_permit_db) + TELEGRAM defines — legacy
│
├── api/                       → REST JSON endpoints
│   ├── auth.php               → Teacher login (Leave system)
│   ├── save_request.php       → Save leave request (transaction)
│   ├── approve_action.php     → Approve/reject leave (role: super_admin/wfh_admin)
│   ├── get_requests.php       → Get leave requests (role-filtered)
│   └── get_teachers.php       → Get teacher list (auth required)
│
├── components/                → Shared layout (used by all modules except index.php)
│   ├── header.php             → <head> CDNs, fonts, base CSS, <body> open
│   ├── sidebar.php            → Navigation + sub-menus + profile + logout
│   ├── layout_start.php       → Includes header+sidebar, top bar, breadcrumb, content open
│   └── layout_end.php         → Closing tags </div></main></body></html>
│
├── attendance_system/         → Attendance module
│   ├── dashboard.php          → Teacher dashboard (stats, subjects)
│   ├── attendance.php         → Check-in form
│   ├── report.php             → Reports by date range
│   ├── report_student.php     → Per-student report
│   ├── report_subject.php     → Per-subject report
│   ├── admin.php              → Manage students/subjects (super_admin only)
│   ├── import_students.php    → CSV import
│   ├── functions.php          → Helper functions (checkLogin, getSubjects, etc.)
│   ├── db.php                 → DB connection (require config/database.php)
│   ├── index.php              → Legacy login (unused in unified system)
│   └── logout.php             → Legacy logout
│
├── chromebook/                → Chromebook module
│   ├── index.php              → Main dashboard (role: super_admin/cb_admin)
│   ├── dashboard.php          → Borrow/return UI
│   ├── api.php                → AJAX API (switch-case actions)
│   └── config.php             → DB connection wrapper
│
├── admin/                     → WFH Admin pages
│   ├── dashboard.php          → Admin overview (role: super_admin/wfh_admin)
│   ├── manage_users.php       → CRUD WFH users
│   ├── reports.php            → Monthly reports
│   └── settings.php           → System settings (late time, geofence, telegram)
│
├── user/                      → WFH Staff pages
│   ├── dashboard.php          → Clock in/out with GPS + photo
│   └── log_action.php         → API: save check-in/out (JSON)
│
├── includes/
│   └── telegram_bot.php       → TelegramBot class (sendMessage)
│
└── .github/workflows/
    ├── deploy.yml             → Auto deploy on push to main
    └── deploy-manual.yml      → Manual deploy with options
```

---

## Authentication & Authorization

### Session Variables (set by login.php / auth.php)

```php
$_SESSION['user_id']    // int — llw_users.user_id
$_SESSION['username']   // string
$_SESSION['firstname']  // string
$_SESSION['fullname']   // string — "firstname lastname"
$_SESSION['llw_role']   // string — THE authoritative role check
$_SESSION['role']       // string — legacy compat: 'admin' or 'user'
$_SESSION['teacher_id'] // int — att_teachers.id (if att_teacher/super_admin)
$_SESSION['teacher_name'] // string
```

### Role Matrix

| Role | ค่า `llw_role` | เข้าถึงได้ |
|---|---|---|
| Super Admin | `super_admin` | ทุกหน้า + central_dashboard + attendance admin |
| WFH Admin | `wfh_admin` | admin/*, leave approve, portal |
| WFH Staff | `wfh_staff` | user/*, leave request, portal |
| CB Admin | `cb_admin` | chromebook/*, portal |
| Teacher | `att_teacher` | attendance_system/*, leave request, portal |

### Auth Guard Rules (บังคับทุกไฟล์)

```php
// ── หน้าทั่วไป (ต้อง login) ──
session_start();
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php'); exit();
}

// ── หน้า admin (ต้องมี role เฉพาะ) ──
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    header('Location: /login.php'); exit();
}

// ── API endpoint (return JSON) ──
if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// ── API ที่ต้อง role เฉพาะ ──
if (!in_array($_SESSION['llw_role'], ['super_admin', 'wfh_admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}
```

**สำคัญ**:
- ใช้ `$_SESSION['llw_role']` เป็น **มาตรฐานเดียว** — ห้ามใช้ `$_SESSION['role']` หรือ `$_SESSION['user_id']` เป็น auth check
- API ต้อง return `http_response_code(401)` หรือ `403` + JSON — ห้าม redirect
- หน้า page ต้อง `header('Location: /login.php'); exit();` — ห้ามลืม `exit()`

---

## Database Schema

### ตาราง `llw_users` (Central Auth)
```sql
user_id     INT AUTO_INCREMENT PRIMARY KEY
username    VARCHAR(100) UNIQUE NOT NULL
password    VARCHAR(255) NOT NULL          -- password_hash() เท่านั้น
firstname   VARCHAR(100)
lastname    VARCHAR(100)
role        ENUM('super_admin','wfh_admin','wfh_staff','cb_admin','att_teacher')
status      ENUM('active','inactive')
last_login  DATETIME NULL
created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
```

### ตาราง WFH Module (`wfh_*`)
```
wfh_users          — user_id, username, password, firstname, lastname, position, dept_id, role
wfh_departments    — dept_id, dept_name
wfh_timelogs       — log_id, user_id, log_date, check_in_time, check_out_time,
                     check_in_status('ปกติ'/'มาสาย'), check_in_lat, check_in_lng,
                     check_in_photo, check_out_lat, check_out_lng, check_out_photo
wfh_system_settings — setting_id, regular_time_in, late_time, school_lat, school_lng,
                     geofence_radius, telegram_token, admin_chat_id
```

### ตาราง Chromebook Module (`cb_*`)
```
cb_chromebooks     — chromebook_id, model, serial_number
cb_teachers        — teacher_id, name
cb_students        — student_id, name, class_name
cb_borrow_logs     — entry_id, borrower_type, borrower_id, class_name, chromebook_id,
                     chromebook_serial, images, status('Borrowed'/'Returned'),
                     date_borrowed, date_returned
cb_inspections     — id, borrow_log_id, condition_status, notes, images, inspected_date
```

### ตาราง Attendance Module (`att_*`)
```
att_teachers       — id, name, username, password, llw_user_id
att_students       — id, student_id(VARCHAR), name, classroom
att_subjects       — id, subject_code, subject_name, classroom, teacher_id
att_attendance     — id, date, period(1-8), subject_id, teacher_id, student_id,
                     status('มา'/'ขาด'/'ลา'/'โดด'/'สาย'), time_in, note, created_at
```

---

## URL Routing

**สำคัญ**: Production deploy อยู่ที่ root ของ subdomain `llw.krusuched.com`

```
https://llw.krusuched.com/                          → index.php
https://llw.krusuched.com/login.php                 → login.php
https://llw.krusuched.com/attendance_system/dashboard.php
https://llw.krusuched.com/chromebook/index.php
```

**ห้ามใช้ base path prefix** เช่น `/llw/` ใน links — URL ทุกตัวเริ่มจาก `/` โดยตรง

ใน `components/sidebar.php`:
```php
$base_path = '';  // ← ว่างเปล่า ห้ามใส่ /llw
```

---

## Layout System

### หน้าที่ใช้ Shared Layout (sidebar + header)
ทุกหน้าใน admin/, user/, attendance_system/, chromebook/, leave_system.php:
```php
$pageTitle = 'ชื่อหน้า';
$pageSubtitle = 'คำอธิบายย่อย';
$activeSystem = 'attendance'; // ค่า: portal, attendance, chromebook, wfh, leave
require_once __DIR__ . '/../components/layout_start.php';
// ... page content ...
require_once __DIR__ . '/../components/layout_end.php';
```

### หน้าที่ไม่ใช้ Shared Layout (standalone)
- `index.php` — Landing page (มี nav + footer ของตัวเอง)
- `login.php` — Login page (standalone UI)

### Sidebar Sub-menus
Sidebar แสดง sub-menu อัตโนมัติตาม `$activeSystem`:
- **attendance**: Dashboard, เช็คชื่อ, รายงาน, จัดการข้อมูล
- **chromebook**: Dashboard, ยืม-คืน
- **wfh**: Dashboard (admin), ลงเวลา, รายงาน, จัดการบุคลากร, ตั้งค่า (ซ่อนตาม role)
- **leave**: รายการคำขอ

---

## Coding Standards (บังคับ)

### Security — กฎเหล็ก ห้ามละเมิด

1. **SQL Injection**: ใช้ Prepared Statements เท่านั้น ห้าม interpolate ตัวแปรใน SQL
   ```php
   // ถูก
   $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
   $stmt->execute([$id]);

   // ผิด — ห้ามทำเด็ดขาด
   $pdo->query("SELECT * FROM users WHERE id = $id");
   $conn->query("SELECT * FROM wfh_timelogs WHERE log_date='$today'");
   ```

2. **XSS Prevention**: ทุก output จาก DB/user input ต้อง escape
   ```php
   // ถูก
   <?= htmlspecialchars($user['firstname'], ENT_QUOTES, 'UTF-8') ?>

   // ผิด
   <?= $user['firstname'] ?>
   ```

3. **Auth Guard**: ดูหัวข้อ "Auth Guard Rules" ด้านบน — ห้ามขาด

4. **Password**: `password_hash()` / `password_verify()` เท่านั้น — ห้าม MD5, SHA1

5. **File Upload**: validate mime type + extension + ขนาด, ชื่อไฟล์ random

6. **API Response**: ห้าม expose error details จาก Exception — log แล้ว return generic message
   ```php
   } catch (Exception $e) {
       error_log($e->getMessage());
       echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
   }
   ```

### Forbidden Patterns (ห้ามทำ)

- ห้ามสร้างไฟล์ `setup.php`, `debug_*.php`, `test_*.php`, `fix_*.php`, `reset_*.php`, `temp_*.php`, `check_*.php` — จะถูกลบตอน deploy
- ห้ามใช้ `$_SESSION['role']` หรือ `$_SESSION['user_id']` เป็น auth check — ใช้ `$_SESSION['llw_role']` เท่านั้น
- ห้าม hardcode DB credentials — ใช้ defines จาก `config/database.php`
- ห้ามใช้ `Access-Control-Allow-Origin: *` — จำกัด origin ให้เฉพาะที่จำเป็น
- ห้ามใช้ `echo $e->getMessage()` ใน production response — ใช้ `error_log()` แทน
- ห้ามใส่ base path `/llw/` ใน URL links — ใช้ `/` เริ่มต้นตรงๆ

### PHP Standards

- `<?php` เต็มรูปแบบ ไม่ใช้ short tag `<?`
- Short echo `<?= ?>` ใช้ได้เฉพาะใน HTML template
- Strict comparison `===` แทน `==` เสมอ
- `try-catch` สำหรับ DB operations ทุกครั้ง
- Transactions สำหรับ multi-step writes: `beginTransaction()` → `commit()` / `rollBack()`
- ตั้งชื่อไฟล์: `lowercase_underscore.php`
- ตั้งชื่อตาราง: `{module}_{entity}` เช่น `att_students`, `cb_borrow_logs`

### API Endpoint Pattern

```php
<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/database.php';

// Auth guard (ใช้ llw_role)
if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// Role guard (ถ้าจำเป็น)
// if (!in_array($_SESSION['llw_role'], ['super_admin'])) { ... }

$input = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = getPdo();
    $pdo->beginTransaction();
    // ... prepared statements only ...
    $pdo->commit();
    echo json_encode(['status' => 'success', 'data' => $result]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
    error_log($e->getMessage());
}
```

### Page Template

```php
<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) { header('Location: /login.php'); exit(); }
// Role guard (ถ้าจำเป็น)
// if (!in_array($_SESSION['llw_role'], ['super_admin','wfh_admin'])) { header('Location: /login.php'); exit(); }

// Data fetching (prepared statements)
$stmt = $conn->prepare("SELECT * FROM table WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

// Layout variables
$pageTitle = 'ชื่อหน้า';
$pageSubtitle = 'คำอธิบาย';
$activeSystem = 'wfh'; // portal | attendance | chromebook | wfh | leave

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Page content (Tailwind CSS) -->

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
```

---

## UI/Design System (Tailwind CSS)

### CDN Dependencies (อยู่ใน components/header.php)

```html
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
```

### Typography

- Font: **Prompt** — `font-family: 'Prompt', sans-serif`
- Headings: `font-black` (900)
- Body: `font-normal` (400)
- Labels: `text-[9px] sm:text-[10px] font-black uppercase tracking-wider`

### Color Palette

| ใช้สำหรับ | Primary | Success | Warning | Danger | Info |
|---|---|---|---|---|---|
| Color | `blue-600` | `emerald-500` | `amber-500` | `rose-500` | `indigo-600` |
| Gradient | `from-blue-600 to-indigo-600` | `from-emerald-500 to-teal-500` | `from-amber-500 to-orange-500` | `from-rose-500 to-pink-500` | `from-indigo-500 to-purple-500` |
| Light BG | `blue-50` | `emerald-50` | `amber-50` | `rose-50` | `indigo-50` |
| Shadow | `shadow-blue-200/50` | `shadow-emerald-200/50` | `shadow-amber-200/50` | `shadow-rose-200/50` | `shadow-indigo-200/50` |

### Module Color Map

| Module | Active Sidebar | Card Accent | Gradient |
|---|---|---|---|
| Attendance | `indigo-600` | `blue` | `from-indigo-600 to-blue-600` |
| Chromebook | `cyan-600` | `indigo` | `from-cyan-600 to-blue-600` |
| WFH | `emerald-600` | `emerald` | `from-emerald-600 to-teal-600` |
| Leave | `rose-600` | `rose` | `from-rose-600 to-pink-600` |
| Portal | `blue-600` | `blue` | `from-blue-600 to-indigo-600` |

### Component Patterns

**Card**:
```html
<div class="bg-white rounded-2xl shadow-xl shadow-blue-100/50 p-6 border border-slate-100">
  <h3 class="text-lg font-black text-slate-800">Title</h3>
  <p class="text-sm text-slate-500 mt-1">Description</p>
</div>
```

**Glass Card** (overlay, modal, login):
```html
<div class="bg-white/70 backdrop-blur-xl rounded-[32px] shadow-2xl border border-white/50 p-10">
```

**KPI Stat Card** (gradient):
```html
<div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl p-6 text-white shadow-xl shadow-blue-200/50">
  <p class="text-xs font-bold opacity-80 uppercase tracking-wider">Label</p>
  <p class="text-4xl font-black mt-2">123</p>
</div>
```

**Button Primary**: `bg-blue-600 text-white px-6 py-3 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 hover:scale-[1.02] transition-all`

**Button Danger**: `bg-rose-500 text-white px-4 py-2 rounded-xl font-bold shadow-lg shadow-rose-200 hover:bg-rose-600 transition-all`

**Badge**: `px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold`

**Table**: `bg-white rounded-2xl shadow-lg overflow-hidden border border-slate-100` + thead `bg-slate-50` + th `text-xs font-bold text-slate-400 uppercase tracking-wider px-6 py-4`

**Form Input**: `w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all`

### Design Principles

- **Rounded**: `rounded-2xl` ปกติ, `rounded-[2.5rem]` สำหรับ card ใหญ่, `rounded-[32px]` สำหรับ glass
- **Shadows**: ใช้ shadow คู่สี เช่น `shadow-xl shadow-blue-100/50` — ไม่ใช้ shadow ดำ
- **Spacing**: `gap-6` ระหว่าง sections, `p-6` ภายใน card
- **Transitions**: ทุก interactive element ต้องมี `transition-all` + hover effect
- **Mobile First**: เริ่มจาก `grid-cols-1` แล้วขยาย `sm:grid-cols-2`, `lg:grid-cols-4`
- **Responsive text**: ใช้ `text-sm sm:text-base lg:text-lg` ปรับตามจอ
- **Icons**: Bootstrap Icons (`bi-*`) — ใส่ก่อนข้อความเสมอ
- **SweetAlert2**: ใช้สำหรับ alert/confirm ทั้งหมด, `confirmButtonColor: '#2563eb'`

---

## Deployment

### Auto Deploy (push to main)
```
git push origin main → GitHub Actions → generate config from Secrets → lftp FTP upload
```

### GitHub Secrets Required
```
FTP_SERVER, FTP_USERNAME, FTP_PASSWORD, FTP_SERVER_DIR
DB_HOST, DB_USER, DB_PASS, DB_NAME
DB_CENTRAL_NAME, DB_PROJECT_NAME
TELEGRAM_TOKEN, TELEGRAM_BOSS1_CHAT_ID
```

### Files excluded from production
- `test_*.php`, `fix_*.php`, `debug_*.php`, `reset_*.php`, `temp_*.php`, `check_*.php`
- `*.md`, `.git/`, `.github/`, `.gitignore`

### Config generation
GitHub Actions สร้าง `config/database.php`, `config/db_central.php`, `config/db_project.php` จาก Secrets ทับไฟล์เดิมก่อน upload — local dev ใช้ config เดิม (localhost/root) ไม่ต้องเปลี่ยน

---

## Quick Reference

### สร้างหน้าใหม่ใน module (checklist)
1. สร้างไฟล์ `.php` ใน folder ของ module
2. เพิ่ม `session_start()` + auth guard ตาม role
3. ตั้ง `$pageTitle`, `$pageSubtitle`, `$activeSystem`
4. `require_once` layout_start.php / layout_end.php
5. ใช้ prepared statements เท่านั้น
6. `htmlspecialchars()` ทุก output
7. เพิ่ม link ใน `$subMenus` ที่ `components/sidebar.php` (ถ้าต้องการ)

### สร้าง API endpoint ใหม่ (checklist)
1. สร้างไฟล์ใน `api/` folder
2. เพิ่ม `header('Content-Type: application/json')` + `session_start()`
3. Auth guard ด้วย `$_SESSION['llw_role']` + role check ถ้าจำเป็น
4. Validate input ทุก field
5. ใช้ `try-catch` + `beginTransaction()` + prepared statements
6. Return JSON: `['status' => 'success/error', 'data' => ..., 'message' => ...]`
7. `error_log()` สำหรับ exceptions — ห้าม expose ให้ client

### สร้างตารางใหม่ (checklist)
1. ตั้งชื่อ: `{module}_{entity}` เช่น `att_grades`
2. ใช้ `INT AUTO_INCREMENT PRIMARY KEY`
3. Charset: `utf8mb4_unicode_ci`
4. เพิ่ม `created_at DATETIME DEFAULT CURRENT_TIMESTAMP`
5. อย่าลืม import SQL บน production ผ่าน phpMyAdmin
