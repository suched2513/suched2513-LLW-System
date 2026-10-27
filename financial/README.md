# ระบบบริหารการเงินและบัญชีสถานศึกษา (School Financial & Accounting Management System)
### โรงเรียนละลมวิทยา | Lalompittayakhom School

เว็บแอปพลิเคชันบริหารการเงิน บัญชีคู่ งบประมาณ จัดซื้อจัดจ้าง พัสดุครุภัณฑ์ และการกระทบยอดธนาคาร พัฒนาด้วยสถาปัตยกรรม Clean MVC (PHP 8.1+ & MySQL / MariaDB + Bootstrap 5) สำหรับสถานศึกษาภาครัฐ

---

## 1. คุณสมบัติหลักของระบบ (Core Features)

1. **แดชบอร์ดผู้บริหารและการเงิน (Executive & Finance Dashboard)**
   - สรุปงบประมาณที่ได้รับ, ยอดผูกพัน (Commitment), ยอดเบิกจ่ายจริง, และงบคงเหลือสุทธิ
   - แยกความหมายชัดเจนระหว่าง **งบคงเหลือ**, **เงินสดในมือ**, และ **ยอดเงินฝากธนาคาร**
   - กราฟรายรับ-รายจ่ายรายเดือน และโครงการที่ใช้งบประมาณใกล้เต็มเกณฑ์เตือน (Threshold)
2. **ระบบควบคุมงบประมาณอัตโนมัติ (Automated Real-time Budget Control)**
   - คำนวณเปอร์เซ็นต์การใช้วงเงินตามสูตร:
     $$\text{Percent} = \frac{\text{ยอดจ่ายสะสม} + \text{ยอดผูกพัน} + \text{รายการใหม่}}{\text{งบประมาณสุทธิ}} \times 100$$
   - เตือนเมื่อแตะ $80\%$ และบล็อกเด็ดขาดเมื่อถึง $100\%$
   - ล็อกแถวข้อมูล (`SELECT ... FOR UPDATE`) ป้องกันการแย่งยื่นคำขอพร้อมกัน
3. **ระบบรับเงิน (Receipt Management)**
   - รองรับเงินอุดหนุนรายหัว, เงินรายได้สถานศึกษา, เงินบริจาค, ค่าบำรุงการศึกษา และเงินอื่น ๆ
   - ออกใบเสร็จรับเงิน (REC-YYYY-XXXX), บันทึกเงินสด/เงินโอน, จัดทำใบนำฝากธนาคาร โดยไม่ลงรายรับซ้ำ
   - ยกเลิกใบเสร็จพร้อมระบุเหตุผลและบันทึก Audit Trail
4. **ระบบเบิกจ่ายเงิน (Disbursement Workflow)**
   - ขั้นตอน: **ร่าง $\rightarrow$ เสนอ $\rightarrow$ ตรวจสอบ $\rightarrow$ อนุมัติ $\rightarrow$ จ่ายเงิน $\rightarrow$ บันทึกบัญชีอัตโนมัติ**
   - ระบบใบสำคัญจ่าย (Payment Voucher - PV), หักภาษี ณ ที่จ่าย (1%, 3%), ป้องกันการจ่ายซ้ำ
   - การแยกหน้าที่ความรับผิดชอบ (Separation of Duties): ผู้ขอเบิกไม่อนุมัติรายการตนเอง
5. **ระบบเงินยืมราชการและสัญญายืมเงิน (Advance & Clearing Management)**
   - สัญญายืมเงินราชการ (แบบ 8500), วันครบกำหนดชำระ (30 วัน), การติดตามหนี้เงินยืม
   - ใบส่งใช้เงินยืม: คำนวณเงินเหลือส่งคืน หรือเบิกจ่ายส่วนต่างเพิ่มได้อย่างแม่นยำ
6. **ระบบบัญชีคู่ภาครัฐ (Double-Entry Ledger)**
   - ผังบัญชี 5 หมวด (สินทรัพย์, หนี้สิน, ส่วนของทุน, รายได้, ค่าใช้จ่าย)
   - สมุดรายวันทั่วไป (General Journal), บัญชีแยกประเภท (General Ledger), งบทดลอง ($\text{Debit} = \text{Credit}$)
   - บันทึกการปิดบัญชีสิ้นงวด/สิ้นปีงบประมาณ
7. **บัญชีธนาคารและการกระทบยอด (Bank Accounts & Reconciliation)**
   - รองรับบัญชีธนาคารของโรงเรียน (กระแสรายวัน, ออมทรัพย์, ฝากประจำ)
   - ทะเบียนคุมสมุดเงินฝากธนาคาร และงบพิสูจน์ยอดเงินฝากธนาคาร (Bank Reconciliation Statement)
   - สูตรกระทบยอด: $\text{Adjusted Bank Balance} = \text{ยอดตามสเตตเมนต์} + \text{เงินฝากระหว่างทาง} - \text{เช็คค้างจ่าย}$
8. **ห้องเรียนพิเศษและค่าบำรุงการศึกษา (Special Programs & Tuition Fees)**
   - รองรับ EP, MEP, IEP, IP, Gifted, SMP, วิทย์-คณิตพิเศษ และประเภทที่กำหนดเอง
   - จัดการรายการเรียกเก็บ, ส่วนลด, การยกเว้น/ผ่อนผัน และติดตามยอดค้างชำระ
9. **จัดซื้อจัดจ้าง พัสดุ และครุภัณฑ์ (Procurement & Inventory / Assets)**
   - แผนจัดซื้อจัดจ้าง, ใบขอซื้อขอจ้าง (PR), ใบสั่งซื้อสั่งจ้าง (PO), รายงานการตรวจรับพัสดุ (Inspection)
   - ทะเบียนคุมวัสดุสิ้นเปลือง (Stock Cards) และประวัติการเบิกจ่าย
   - ทะเบียนคุมครุภัณฑ์พร้อมการคำนวณค่าเสื่อมราคาแบบเส้นตรง (Straight-Line Depreciation) โดยหักมูลค่าซาก 1.00 บาท
10. **เอกสารและรายงานทางการเงินและบัญชีสถานศึกษา 28 รายงาน**
    - ระบบรายงานมาตรฐานราชการครบถ้วนตามแบบ บก., กค., สพฐ.
    - รองรับการส่งออกไฟล์และแสดงผลแบบพิมพ์
11. **ความปลอดภัยและประวัติการใช้งาน (Strict RBAC & Audit Trail)**
    - แยกบทบาท 8 บทบาทด้วย Server-side RBAC
    - ระบบป้องกัน CSRF, SQL Injection (PDO Prepared Statements 100%), XSS Escaping
    - บันทึกประวัติการกระทำ (Audit Trail) ละเอียดถึงระดับ Before/After Data และ IP Address

---

## 2. บทบาทผู้ใช้งานและรหัสผ่านเริ่มต้น (Default Roles & Credentials)

ระบบได้เตรียมผู้ใช้งานจำลองสำหรับทดสอบระบบตามมาตรฐานการแบ่งแยกหน้าที่ (Separation of Duties):

| ชื่อผู้ใช้ (Username) | บทบาท (Role) | สิทธิ์หน้าที่หลัก | รหัสผ่านเริ่มต้น |
|---|---|---|---|
| `admin` | ผู้ดูแลระบบ (Administrator) | จัดการผู้ใช้, ตั้งค่าระบบ, ดู Audit Logs (ไม่มีสิทธิ์อนุมัติการเงิน) | `password123` |
| `director` | ผู้อนุมัติ (Approver / ผอ.รร.) | อนุมัติเบิกจ่าย, อนุมัติสัญญายืมเงิน, อนุมัติจัดซื้อจัดจ้าง | `password123` |
| `finance` | เจ้าหน้าที่การเงิน (Finance Officer) | ออกใบเสร็จ, บันทึกจ่ายเงิน, จัดการเงินยืม, จัดการธนาคาร | `password123` |
| `accountant` | เจ้าหน้าที่บัญชี (Accountant) | ตรวจสอบเอกสาร, บันทึกสมุดรายวัน, ผังบัญชี, งบทดลอง | `password123` |
| `procurement` | เจ้าหน้าที่พัสดุ (Procurement Officer)| จัดซื้อจัดจ้าง, คุมสต็อกวัสดุ, ทะเบียนครุภัณฑ์ | `password123` |
| `auditor` | ผู้ตรวจสอบ (Auditor) | ตรวจสอบความถูกต้องของคำขอและรายงานทางการเงิน | `password123` |
| `executive` | ผู้บริหาร (Executive) | ดูแดชบอร์ดภาพรวม, งบประมาณ และรายงานทางการเงิน | `password123` |
| `requester` | ผู้ขอเบิก (Requester / ครู) | ยื่นคำขอเบิกเงิน, ขอยืมเงิน, ส่งใช้เงินยืม (ไม่อนุมัติเอง) | `password123` |

---

## 3. ดัชนีรายงานทางการเงินและบัญชีสถานศึกษา 28 รายงาน (Report Catalog)

ระบบประกอบด้วยรายงานราชการมาตรฐานครบถ้วน 28 ฉบับ:

| รหัสรายงาน | ชื่อรายงาน (Report Title) | หมวดหมู่ |
|---|---|---|
| `REC-01` | รายงานเงินคงเหลือประจำวัน (บก.111) | การรับเงิน |
| `REC-02` | รายงานการรับเงินประจำวัน | การรับเงิน |
| `REC-03` | รายงานสรุปการรับเงินประจำเดือน | การรับเงิน |
| `REC-CERT` | ทะเบียนคุมใบเสร็จรับเงิน | การรับเงิน |
| `PAY-01` | ทะเบียนคุมการเบิกจ่ายเงินงบประมาณ | การจ่ายเงิน |
| `PAY-02` | ทะเบียนคุมการจ่ายเงินนอกงบประมาณ | การจ่ายเงิน |
| `PAY-03` | รายงานสรุปการจ่ายเงินประจำเดือน | การจ่ายเงิน |
| `PAY-WHT` | รายงานภาษีหัก ณ ที่จ่าย (ภ.ง.ด. 1, 3, 53) | การจ่ายเงิน |
| `ADV-01` | ทะเบียนคุมสัญญายืมเงินราชการ (แบบ 8500) | เงินยืม |
| `ADV-02` | รายงานลูกหนี้เงินยืมค้างชำระ | เงินยืม |
| `ADV-03` | รายงานสรุปการส่งใช้ใบสำคัญและล้างเงินยืม | เงินยืม |
| `ACC-01` | สมุดรายวันทั่วไป (General Journal) | บัญชี |
| `ACC-02` | บัญชีแยกประเภททั่วไป (General Ledger) | บัญชี |
| `TB-01` | งบทดลองประจำงวด (Trial Balance) | บัญชี |
| `BGT-01` | รายงานสถานะงบประมาณรายโครงการและกิจกรรม | งบประมาณ |
| `BGT-02` | รายงานการโอนเปลี่ยนแปลงงบประมาณ | งบประมาณ |
| `BNK-REC` | งบพิสูจน์ยอดเงินฝากธนาคาร (Bank Reconciliation) | ธนาคาร |
| `PRC-01` | รายงานผลการจัดซื้อจัดจ้างประจำเดือน (สขร. 1) | พัสดุและจัดซื้อ |
| `PRC-02` | ทะเบียนคุมใบสั่งซื้อ/สั่งจ้าง (PO Log) | พัสดุและจัดซื้อ |
| `PRC-03` | รายงานการตรวจรับพัสดุและตรวจรับงานจ้าง | พัสดุและจัดซื้อ |
| `AST-01` | ทะเบียนคุมทรัพย์สินและครุภัณฑ์ (แบบ พณ.1) | ครุภัณฑ์ |
| `AST-02` | รายงานการคำนวณค่าเสื่อมราคาประจำปี | ครุภัณฑ์ |
| `INV-01` | บัญชีคุมวัสดุสิ้นเปลือง (Stock Card) | พัสดุ |
| `INV-02` | รายงานการตรวจสอบพัสดุประจำปี | พัสดุ |
| `TRV-01` | รายงานการเดินทางไปราชการ (แบบ 8708) | เดินทางราชการ |
| `TRV-02` | ทะเบียนคุมการเบิกจ่ายค่าเบี้ยเลี้ยงและที่พัก | เดินทางราชการ |
| `TUI-01` | รายงานการจัดเก็บเงินบำรุงการศึกษาและยอดค้างชำระ | ค่าบำรุงการศึกษา |
| `FIN-STAT` | งบแสดงฐานะการเงินและผลการดำเนินงานทางการเงิน | งบการเงิน |

---

## 4. ความต้องการของระบบ (System Requirements)

- **PHP**: เวอร์ชัน `8.1` ขึ้นไป (รองรับ PHP 8.1, 8.2, 8.3)
- **PHP Extensions**:
  - `pdo` และ `pdo_mysql`
  - `mbstring` (ประมวลผล UTF-8 ภาษาไทย)
  - `openssl` (การเข้ารหัสความปลอดภัย)
  - `json`
  - `gd` (สำหรับปรับแต่งภาพลายเซ็นและตราประทับ)
  - `fileinfo` (ตรวจสอบ MIME Type ของไฟล์อัปโหลด)
- **Database**: MySQL `5.7+` หรือ `8.0+` / MariaDB `10.3+` (รองรับ InnoDB และ UTF8MB4)
- **Web Server**: Apache (`mod_rewrite` เปิดใช้งาน) หรือ Nginx

---

## 5. โครงสร้างระบบ (Architecture & Directory Structure)

```
financial/
├── app/
│   ├── Controllers/               # Controller จัดการคำขอ HTTP ทั้ง 13 โมดูล
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── BudgetController.php
│   │   ├── ReceiptController.php
│   │   ├── DisbursementController.php
│   │   ├── AdvanceController.php
│   │   ├── AccountController.php
│   │   ├── BankController.php
│   │   ├── SpecialProgramController.php
│   │   ├── ProcurementController.php
│   │   ├── InventoryController.php
│   │   ├── ReportController.php
│   │   ├── FiscalYearController.php
│   │   ├── SettingController.php
│   │   ├── UserController.php
│   │   └── AuditLogController.php
│   ├── Core/                      # กลไก MVC Core
│   │   ├── Controller.php
│   │   ├── Database.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Router.php
│   │   └── View.php
│   ├── Helpers/                   # ฟังก์ชันช่วยเหลือและคำนวณภาษาไทย
│   │   └── functions.php
│   ├── Middleware/                # ตรวจสอบสิทธิ์และความปลอดภัย
│   │   ├── AuthMiddleware.php
│   │   ├── CsrfMiddleware.php
│   │   └── RbacMiddleware.php
│   ├── Services/                  # Business Logic ชั้นสูง
│   │   ├── AccountingService.php
│   │   ├── AdvanceService.php
│   │   ├── AssetService.php
│   │   ├── AuditTrailService.php
│   │   ├── AuthService.php
│   │   ├── BahtTextService.php
│   │   ├── BankService.php
│   │   ├── BudgetService.php
│   │   ├── DisbursementService.php
│   │   ├── ProcurementService.php
│   │   ├── ReceiptService.php
│   │   ├── ReportService.php
│   │   ├── SettingService.php
│   │   └── SpecialProgramService.php
│   └── Views/                     # UI Views (Bootstrap 5 Government Theme)
├── config/                        # แฟ้มตั้งค่า (app.php, database.php, permissions.php)
├── database/                      # Schema, Migrations, Seeders
│   ├── schema.sql
│   ├── migrate.php
│   └── seed.php
├── public/                        # จุดเข้าถึงเว็บและ Assets
│   ├── .htaccess
│   └── index.php
├── storage/                       # แฟ้มจัดเก็บเอกสารและลายเซ็นนอก Public
├── tests/                         # ชุดการทดสอบครอบคลุมทุกโมดูล
│   ├── BahtTextTest.php
│   ├── Phase2CoreTest.php
│   ├── Phase3FinanceTest.php
│   ├── Phase4BankAndProgramsTest.php
│   ├── Phase5ProcurementAndAssetTest.php
│   └── run_all_tests.php
├── composer.json
├── .env.example
└── README.md
```

---

## 6. ขั้นตอนการติดตั้งบนโฮสติ้งหรือเซิร์ฟเวอร์ (Installation & Setup)

### 6.1 คัดลอกและตั้งค่า Environment (.env)
```bash
cp .env.example .env
```
เปิดไฟล์ `.env` แล้วกำหนดข้อมูลการเชื่อมต่อฐานข้อมูล MySQL และชื่อสถานศึกษา:
```env
APP_NAME="ระบบบริหารการเงินและบัญชีสถานศึกษา"
APP_ENV=production
APP_URL=https://llw.krusuched.com/financial

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=llw_financial
DB_USERNAME=root
DB_PASSWORD=your_password
DB_CHARSET=utf8mb4

SCHOOL_NAME="โรงเรียนละลมวิทยา"
```

### 6.2 ติดตั้ง Composer Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 6.3 สร้างฐานข้อมูลและสร้างตาราง (Migration & Seed)
```bash
php database/migrate.php
php database/seed.php
```

### 6.4 การตั้งค่า Web Server (Apache)
ไฟล์ `public/.htaccess` ถูกกำหนดค่าพร้อมทำงานเพื่อส่งต่อคำขอทั้งหมดเข้าสู่ `public/index.php`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

---

## 7. การรันชุดทดสอบระบบ (Running Automated Test Suite)

ระบบมีชุดทดสอบอัตโนมัติครบถ้วน สามารถรันได้ด้วยคำสั่งเดียว:
```bash
php tests/run_all_tests.php
```

ผลการทดสอบครอบคลุม:
- การแปลงจำนวนเงินเป็นตัวอักษรภาษาไทย (`BahtText`) และขอบเขตหลักสตางค์/หลักล้าน
- ระบบ Core MVC, Router, การตรวจสอบ CSRF, การคำนวณปีงบประมาณไทย
- การควบคุมงบประมาณ, การบล็อกงบเกิน, การส่งใช้เงินยืม, ความสมดุลของสมุดรายวันทั่วไป (Debit = Credit)
- การกระทบยอดเงินฝากธนาคาร (Bank Reconciliation) และการคำนวณค่าบำรุงการศึกษา
- การคำนวณค่าเสื่อมราคาครุภัณฑ์แบบเส้นตรงตามเกณฑ์กรมบัญชีกลาง และความถูกต้องของรายงาน 28 รายงาน

---

## 8. การส่งมอบงาน (Completion Status)

- [x] **ระยะที่ 1**: วิเคราะห์ความต้องการ สถาปัตยกรรมระบบ ER Diagram โครงสร้างไฟล์ และ Workflow
- [x] **ระยะที่ 2**: ระบบติดตั้ง ฐานข้อมูล ผู้ใช้ สิทธิ์เข้าถึง (RBAC) ปีงบประมาณ และ Audit Trail
- [x] **ระยะที่ 3**: ควบคุมงบประมาณ ระบบรับเงิน ระบบเบิกจ่าย ระบบเงินยืม และระบบบัญชีคู่
- [x] **ระยะที่ 4**: ทะเบียนบัญชีธนาคาร กระทบยอดเงินฝาก และระบบห้องเรียนพิเศษ/ค่าบำรุงการศึกษา
- [x] **ระยะที่ 5**: ระบบจัดซื้อจัดจ้าง พัสดุ ครุภัณฑ์ (ค่าเสื่อมราคา) และดัชนีรายงาน 28 ฉบับ
- [x] **ระยะที่ 6**: การทดสอบความถูกต้อง ความปลอดภัย และเอกสารคู่มือระบบฉบับสมบูรณ์
