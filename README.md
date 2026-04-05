# LLW System — ระบบบริหารจัดการโรงเรียนละลมวิทยา

> Lalom Wittaya School Management Platform — Unified portal for attendance, devices, staff, and leave management.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 7.4+ (Pure, no framework) |
| Database | MySQL / MariaDB (utf8mb4) |
| Frontend | Tailwind CSS (CDN), Bootstrap Icons, SweetAlert2, Chart.js |
| Font | Google Fonts — Prompt (Thai-optimized) |
| Deploy | GitHub Actions → FTP (lftp) auto on push to `main` |
| Auth | Session-based, role-based access control |

## 4 Modules

| Module | Description | Entry |
|---|---|---|
| Attendance | เช็คชื่อนักเรียนรายวิชา/คาบ มา-ขาด-ลา-สาย | `/attendance_system/dashboard.php` |
| Chromebook | ยืม-คืนอุปกรณ์ดิจิทัล ตรวจสภาพ | `/chromebook/index.php` |
| WFH | ลงเวลาเข้า-ออกงาน GPS + ภาพถ่าย | `/user/dashboard.php` |
| Leave | ขออนุญาตออกนอกบริเวณ + แจ้ง Telegram | `/leave_system.php` |

## User Roles

| Role | Access |
|---|---|
| `super_admin` | ทุกระบบ + Admin Panel + จัดการ users |
| `wfh_admin` | WFH Admin, อนุมัติ Leave, รายงาน |
| `wfh_staff` | ลงเวลา, ขอ Leave |
| `cb_admin` | Chromebook ยืม-คืน |
| `att_teacher` | เช็คชื่อนักเรียน, รายงาน |

## Quick Start (Local Development)

### 1. Clone
```bash
git clone https://github.com/suched2513/suched2513-LLW-System.git
cd suched2513-LLW-System
```

### 2. Database Setup
สร้าง database `llw_db` บน MySQL แล้วรัน migration:
```bash
php database/migrate.php --seed
```
จะสร้างตารางทั้งหมด + admin user:
- **Username:** `admin_llw`
- **Password:** `123456`

### 3. Web Server
ชี้ Apache/Nginx document root มาที่ root ของ project แล้วเปิด:
```
http://localhost/login.php
```

### 4. Config
ไฟล์ `config/database.php` ตั้งค่า default สำหรับ local:
```php
DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASS = ''
DB_NAME = 'llw_db'
```
ไม่ต้องแก้อะไรถ้าใช้ XAMPP/Laragon default

## Project Structure

```
├── index.php                    Landing page (standalone)
├── login.php                    Unified login
├── central_dashboard.php        Super Admin panel
│
├── config/
│   └── database.php             DB credentials + getWfhConn() / getPdo()
│
├── api/                         REST JSON endpoints
│   ├── save_request.php         Save leave request
│   ├── approve_action.php       Approve/reject (admin only)
│   ├── get_requests.php         Get leave list (role-filtered)
│   └── get_teachers.php         Get teacher list
│
├── components/                  Shared layout
│   ├── header.php               <head> + CDN links
│   ├── sidebar.php              Navigation + sub-menus
│   ├── layout_start.php         Top bar + breadcrumb + content wrapper
│   └── layout_end.php           Closing tags
│
├── attendance_system/           Attendance module
│   ├── dashboard.php
│   ├── attendance.php           Check-in form
│   ├── report.php               Reports
│   ├── admin.php                Manage students/subjects (super_admin)
│   ├── functions.php            Helper functions
│   └── import_students.php      CSV import
│
├── chromebook/                  Chromebook module
│   ├── index.php                Dashboard
│   ├── dashboard.php            Borrow/return
│   └── api.php                  AJAX endpoints
│
├── admin/                       WFH Admin
│   ├── dashboard.php            Overview + today's logs
│   ├── manage_users.php         CRUD users
│   ├── reports.php              Monthly reports
│   └── settings.php             System config
│
├── user/                        WFH Staff
│   ├── dashboard.php            Clock in/out (GPS + photo)
│   └── log_action.php           Save check-in/out API
│
├── database/                    Migration system
│   ├── migrate.php              CLI runner
│   ├── migrations/              Schema migrations (up/down)
│   └── seeds/                   Initial data (admin user, settings)
│
├── includes/
│   └── telegram_bot.php         Telegram notification class
│
└── .github/workflows/
    ├── deploy.yml               Auto deploy on push to main
    └── deploy-manual.yml        Manual deploy with options
```

## Database

Single database `llw_db` with table prefixes:

| Prefix | Module | Tables |
|---|---|---|
| `llw_` | Auth | `llw_users` |
| `wfh_` | WFH | `wfh_users`, `wfh_departments`, `wfh_timelogs`, `wfh_system_settings` |
| `cb_` | Chromebook | `cb_chromebooks`, `cb_teachers`, `cb_students`, `cb_borrow_logs`, `cb_inspections` |
| `att_` | Attendance | `att_teachers`, `att_students`, `att_subjects`, `att_attendance` |
| — | Leave | `leave_requests`, `leave_request_details` |

## Migration System

```bash
php database/migrate.php                # Run pending migrations
php database/migrate.php --seed         # Migrate + seed data
php database/migrate.php --status       # Show status
php database/migrate.php --rollback     # Rollback last migration
php database/migrate.php --rollback=3   # Rollback last 3
php database/migrate.php --fresh        # Drop all + re-migrate + seed
php database/migrate.php --make=name    # Create new migration file
php database/migrate.php --seed-only    # Run seeds only
```

### Create new migration
```bash
php database/migrate.php --make=add_email_to_users
# Creates: database/migrations/2026_04_05_143000_add_email_to_users.php
```

### Migration file format
```php
<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE llw_users ADD COLUMN email VARCHAR(255) NULL AFTER lastname");
    },
    'down' => function (PDO $pdo) {
        $pdo->exec("ALTER TABLE llw_users DROP COLUMN email");
    },
];
```

## Deployment

### Auto Deploy
```bash
git push origin main
# GitHub Actions จะ deploy อัตโนมัติผ่าน FTP
```

### GitHub Secrets Required
| Secret | Description |
|---|---|
| `FTP_SERVER` | FTP hostname |
| `FTP_USERNAME` | FTP user |
| `FTP_PASSWORD` | FTP password |
| `FTP_SERVER_DIR` | Deploy path (e.g. `/public_html/`) |
| `DB_HOST` | MySQL host |
| `DB_USER` | MySQL username |
| `DB_PASS` | MySQL password |
| `DB_NAME` | Database name |
| `DB_CENTRAL_NAME` | Central DB name |
| `DB_PROJECT_NAME` | Project DB name |
| `TELEGRAM_TOKEN` | Bot token |
| `TELEGRAM_BOSS1_CHAT_ID` | Chat ID |

### How it works
1. Push to `main` triggers GitHub Actions
2. Actions generates `config/*.php` from Secrets (production credentials)
3. Removes debug/test files (`test_*.php`, `fix_*.php`, etc.)
4. Uploads via `lftp` (FTP passive mode, incremental sync)

Local config stays unchanged — production config is generated at deploy time.

## Security

- **Auth**: `$_SESSION['llw_role']` is the single source of truth for authorization
- **SQL**: Prepared statements only — no string interpolation in queries
- **XSS**: `htmlspecialchars()` on all output from DB/user input
- **Passwords**: `password_hash()` / `password_verify()` (bcrypt)
- **API**: Returns proper HTTP status codes (401/403/500) + JSON
- **Config**: Credentials in GitHub Secrets, never in Git history
- **Debug files**: Automatically removed during deployment

## Development Guide

See [CLAUDE.md](CLAUDE.md) for comprehensive development standards including:
- Coding standards & security rules
- UI/Design system (Tailwind component patterns)
- Auth guard patterns with code examples
- Complete database schema
- API endpoint patterns
- Forbidden patterns & naming conventions

---

**Lalom Wittaya School** | Powered by Advanced School Intelligence
