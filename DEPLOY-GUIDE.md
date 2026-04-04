# คู่มือตั้งค่า Auto Deploy — LLW System

## ภาพรวม

ระบบ deploy อัตโนมัติผ่าน GitHub Actions + FTP

```
git push origin main  →  GitHub Actions  →  FTP Upload  →  Production Server
```

- **Auto Deploy**: ทุกครั้งที่ push เข้า `main` จะ deploy ขึ้น production อัตโนมัติ
- **Manual Deploy**: กดปุ่ม deploy ได้เองผ่าน GitHub Actions
- **Config อัตโนมัติ**: ไฟล์ config ถูกสร้างจาก Secrets (ไม่เก็บ credentials ใน code)

---

## ขั้นตอนการตั้งค่า

### 1. ตั้งค่า GitHub Secrets

ไปที่ GitHub repo → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

#### FTP Credentials (สำหรับ upload ไฟล์ขึ้น hosting)

| Secret Name    | คำอธิบาย              | ตัวอย่าง                   |
|----------------|----------------------|---------------------------|
| `FTP_SERVER`   | FTP server hostname  | `ftp.yourhosting.com`     |
| `FTP_USERNAME` | FTP username         | `user@yourdomain.com`     |
| `FTP_PASSWORD` | FTP password         | `your_ftp_password`       |
| `FTP_SERVER_DIR` | Path บน server    | `/public_html/llw/`       |

> **หมายเหตุ**: `FTP_SERVER_DIR` ต้องลงท้ายด้วย `/` เสมอ
> ถ้า deploy ไปที่ root ของ domain ให้ใส่ `/public_html/`

#### Database Credentials (สำหรับเชื่อมต่อ MySQL บน hosting)

| Secret Name       | คำอธิบาย               | ตัวอย่าง              |
|-------------------|------------------------|----------------------|
| `DB_HOST`         | MySQL host             | `localhost`          |
| `DB_USER`         | MySQL username         | `llw_dbuser`         |
| `DB_PASS`         | MySQL password         | `your_db_password`   |
| `DB_NAME`         | Main database          | `llw_db`             |
| `DB_CENTRAL_NAME` | Central school DB      | `school_central_db`  |
| `DB_PROJECT_NAME` | Exit permit DB         | `exit_permit_db`     |

#### Telegram (สำหรับแจ้งเตือน)

| Secret Name               | คำอธิบาย           | ตัวอย่าง           |
|---------------------------|--------------------|--------------------|
| `TELEGRAM_TOKEN`          | Bot Token          | `123456:ABC-DEF...`|
| `TELEGRAM_BOSS1_CHAT_ID`  | Chat ID ของ ผอ.   | `987654321`        |

---

### 2. ตั้งค่า Hosting (ทำครั้งเดียว)

1. **สร้าง FTP Account** บน hosting panel (cPanel/DirectAdmin)
   - ตั้ง username/password แล้วนำไปใส่ใน GitHub Secrets
   - ให้สิทธิ์เขียนทับเต็มที่ (Full write access)

2. **สร้าง MySQL Database** บน hosting panel
   - สร้าง database 3 ตัว: `llw_db`, `school_central_db`, `exit_permit_db`
   - สร้าง user และให้สิทธิ์ ALL PRIVILEGES กับทั้ง 3 databases
   - นำ credentials ไปใส่ใน GitHub Secrets

3. **Import SQL** (ครั้งแรก)
   - นำไฟล์ SQL ของแต่ละ database ไป import ผ่าน phpMyAdmin

---

### 3. วิธี Deploy

#### Auto Deploy (แนะนำ)
```bash
# แก้โค้ด → commit → push = deploy อัตโนมัติ
git add .
git commit -m "fix: แก้ไขหน้า dashboard"
git push origin main
# → GitHub Actions จะ deploy ให้อัตโนมัติ
```

#### Manual Deploy
1. เปิด GitHub repo → tab **Actions**
2. เลือก **"Manual Deploy"** ทางซ้าย
3. กด **"Run workflow"**
4. เลือก options:
   - **environment**: production
   - **clean_slate**: false (ปกติ) / true (ลบไฟล์เก่าทั้งหมดก่อน deploy)
   - **skip_config**: false (ปกติ) / true (ใช้ config จาก repo โดยตรง)
5. กด **"Run workflow"**

---

## วิธีดูผลลัพธ์

1. ไปที่ GitHub repo → tab **Actions**
2. จะเห็น workflow run ล่าสุด
3. กดเข้าไปดู log ของแต่ละ step
4. สีเขียว = สำเร็จ, สีแดง = มีปัญหา

---

## การทำงานของระบบ

### Config Generation Flow
```
┌──────────────┐     ┌───────────────────┐     ┌──────────────────┐
│  GitHub       │     │  GitHub Actions    │     │  Production      │
│  Secrets      │ ──► │  สร้าง config.php  │ ──► │  FTP Upload      │
│  (encrypted)  │     │  จาก secrets       │     │  (ไฟล์ config    │
│               │     │                    │     │   มี credentials │
│               │     │                    │     │   จริง)           │
└──────────────┘     └───────────────────┘     └──────────────────┘
```

### สิ่งที่ถูกทำอัตโนมัติ
1. สร้าง `config/database.php` ด้วย DB credentials จาก secrets
2. สร้าง `config/db_central.php` ด้วย DB credentials จาก secrets
3. สร้าง `config/db_project.php` ด้วย DB + Telegram credentials จาก secrets
4. ลบไฟล์ `test_*.php`, `fix_*.php`, `md.txt` ก่อน upload
5. FTP sync (upload เฉพาะไฟล์ที่เปลี่ยน = เร็ว)

### ไฟล์ที่ไม่ถูก upload ขึ้น production
- `.git/`, `.github/` — ไฟล์ Git
- `test_*.php`, `fix_*.php` — ไฟล์ debug/test
- `*.md`, `md.txt` — เอกสาร
- `.env` — environment variables

---

## FAQ

**Q: Local dev ต้องเปลี่ยนอะไรไหม?**
A: ไม่ต้อง ใช้ config เดิม (localhost, root, no password) ได้เลย

**Q: ถ้า push แล้ว deploy ไม่สำเร็จ?**
A: ดู log ใน GitHub Actions → แก้ไข → push ใหม่ หรือกด Re-run

**Q: ถ้าต้องการเปลี่ยน DB password บน hosting?**
A: แก้ secret `DB_PASS` ใน GitHub → กด Manual Deploy ใหม่

**Q: ถ้าเพิ่ม folder uploads/ บน server แล้ว deploy จะลบไหม?**
A: ไม่ลบ เพราะ `dangerous-clean-slate: false` จะไม่ลบไฟล์ที่ไม่ได้อยู่ใน repo
