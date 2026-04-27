# Project Request System (SBMS 2569)
ระบบขอดำเนินโครงการ - โรงเรียนละลมวิทยา

ระบบสำหรับบริหารจัดการงบประมาณและขอดำเนินโครงการผ่านเว็บแอปพลิเคชัน รองรับการนำเข้าข้อมูลจาก Excel, การขอใช้เงินผ่าน Wizard 4 ขั้นตอน และระบบอนุมัติออนไลน์

## โครงสร้างโฟลเดอร์
- `admin/` - ส่วนงานแอดมิน (นำเข้างบประมาณ, จัดการผู้ใช้)
- `teacher/` - ส่วนงานครู (ขอดำเนินโครงการ, ติดตามสถานะ)
- `director/` - ส่วนงานผู้อำนวยการ (อนุมัติ/ไม่อนุมัติ)
- `config/` - การตั้งค่าฐานข้อมูลและสิทธิ์
- `api/` - ระบบบันทึกข้อมูลเบื้องหลัง
- `documents/` - ระบบสร้างไฟล์ Word (ต้องใช้ PhpWord)

## วิธีการติดตั้งบน Shared Hosting / cPanel
1. **สร้างฐานข้อมูล**:
   - สร้างฐานข้อมูล MySQL ผ่าน cPanel
   - นำเข้าไฟล์ `schema.sql` และ `seed.sql` เข้าไปในฐานข้อมูล
2. **ตั้งค่าการเชื่อมต่อ**:
   - แก้ไขไฟล์ `config/db.php` เพื่อระบุ DB_HOST, DB_NAME, DB_USER, DB_PASS ให้ตรงกับที่สร้างไว้
3. **ติดตั้ง Libraries (Composer)**:
   - หาก Hosting รองรับ Terminal ให้รันคำสั่ง `composer install` ในโฟลเดอร์ `project_request`
   - หากไม่รองรับ ให้รันบนเครื่อง Local แล้วอัปโหลดโฟลเดอร์ `vendor/` ขึ้นไปทั้งหมด
4. **ตั้งค่า Permissions**:
   - ตรวจสอบให้แน่ใจว่าโฟลเดอร์ `uploads/temp/` สามารถเขียนไฟล์ได้ (Permission 755 หรือ 777)
5. **เข้าสู่ระบบ**:
   - URL: `your-domain.com/project_request/`
   - User: `admin` / Pass: `password`
   - User: `director` / Pass: `password`
   - User: `teacher1` / Pass: `password`

## หมายเหตุการใช้งาน
- การนำเข้างบประมาณ รองรับไฟล์ .csv และ .xlsx (หากติดตั้ง PhpSpreadsheet)
- การสร้างเอกสาร Word จำเป็นต้องมีไฟล์เทมเพลตใน `documents/templates/` (เช่น memo.docx, committee.docx)
- ระบบใช้ Tailwind CSS ผ่าน CDN ไม่ต้องติดตั้ง Node.js บน Server
