# ระบบขอดำเนินโครงการ โรงเรียนละลมวิทยา

## ข้อมูล
- โรงเรียนละลมวิทยา อำเภอภูสิงห์ จังหวัดศรีสะเกษ
- PHP + MySQL | Bootstrap 5 | Chart.js

## การติดตั้ง

### 1. ฐานข้อมูล
```sql
CREATE DATABASE lalomwittaya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Import schema.sql แล้วตาม seed.sql
```

### 2. แก้ไข config/db.php
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'lalomwittaya');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

### 3. Upload ไฟล์ทั้งหมดขึ้น cPanel
วาง folder ทั้งหมดใน public_html/ หรือ subdomain

### 4. (Optional) ติดตั้ง Composer dependencies
```bash
composer install
```
เพื่อใช้ PhpWord (เอกสาร .docx) และ TCPDF (export PDF)

## บัญชีทดสอบ

| Username | Password | สิทธิ์ |
|---|---|---|
| admin | password123 | ผู้ดูแลระบบ |
| director | password123 | ผู้อำนวยการ |
| budget1 | password123 | เจ้าหน้าที่งบประมาณ |
| teacher1-5 | password123 | ครู |

## โครงสร้างหน้า

- `/login.php` - เข้าสู่ระบบ
- `/admin/dashboard.php` - Dashboard Admin
- `/dashboard/director.php` - Dashboard ผู้อำนวยการ (กราฟ)
- `/dashboard/budget_officer.php` - Dashboard ฝ่ายงบประมาณ
- `/teacher/my_projects.php` - โครงการของครู
- `/teacher/request_form.php` - Wizard ขอดำเนินโครงการ
- `/director/pending.php` - อนุมัติ/ปฏิเสธ
- `/documents/gen_memo.php` - บันทึกขออนุมัติใช้เงิน
- `/documents/gen_committee.php` - บันทึกขอแต่งตั้งกรรมการ
- `/documents/gen_delivery.php` - ใบส่งมอบงาน
- `/reports/budget_overview.php` - รายงานงบประมาณ
- `/reports/project_progress.php` - ความคืบหน้า
- `/reports/project_overdue.php` - โครงการค้าง

## หมายเหตุ
- เอกสารใช้ HTML print เป็น fallback (ไม่ต้องติดตั้ง Composer)
- หากติดตั้ง composer install จะสร้างไฟล์ .docx ได้
- Password ทดสอบใช้ `password_hash('password123', PASSWORD_DEFAULT)`
