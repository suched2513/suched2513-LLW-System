<?php
/**
 * learning_media/index.php — คลังสื่อการเรียนรู้โรงเรียนละลมวิทยา
 */
session_start();
require_once __DIR__ . '/../config.php';

$isLoggedIn = isset($_SESSION['llw_role']);
$userRole   = $_SESSION['llw_role'] ?? 'guest';
$userName   = $_SESSION['fullname'] ?? ($_SESSION['teacher_name'] ?? 'ครูผู้สอน');
$userId     = $_SESSION['user_id'] ?? 0;
$canManage  = in_array($userRole, ['super_admin', 'att_teacher', 'wfh_admin', 'wfh_staff', 'club_admin'], true);

// Layout settings
$pageTitle    = 'คลังสื่อการเรียนรู้';
$pageSubtitle = 'ศูนย์รวมสื่อ นวัตกรรม และแหล่งเรียนรู้ออนไลน์ โรงเรียนละลมวิทยา';
$activeSystem = 'learning_media';

if ($isLoggedIn) {
    require_once __DIR__ . '/../components/layout_start.php';
} else {
    // Standalone header for public / student access
    require_once __DIR__ . '/../components/header.php';
}
?>

<style>
    :root {
        --media-primary: #0284c7;
        --media-gradient: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);
        --card-shadow: 0 10px 25px -5px rgba(2, 132, 199, 0.08), 0 8px 10px -6px rgba(2, 132, 199, 0.05);
        --card-hover-shadow: 0 20px 30px -10px rgba(2, 132, 199, 0.20), 0 10px 15px -5px rgba(2, 132, 199, 0.10);
    }

    .media-card {
        border-radius: 1.5rem;
        background: #ffffff;
        border: 1px solid #f1f5f9;
        box-shadow: var(--card-shadow);
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
    }
    .media-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--card-hover-shadow);
    }
    .cover-img-wrap {
        position: relative;
        width: 100%;
        height: 190px;
        overflow: hidden;
        background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);
    }
    .cover-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s ease, filter 0.3s ease;
    }
    .media-card:hover .cover-img {
        transform: scale(1.06);
    }
    .media-grade-badge {
        position: absolute;
        top: 14px;
        left: 14px;
        background: linear-gradient(135deg, #f97316 0%, #ef4444 100%);
        color: #fff;
        padding: 0.28rem 0.85rem;
        border-radius: 9999px;
        font-size: 0.78rem;
        font-weight: 800;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.35);
        letter-spacing: 0.02em;
    }
    .media-type-badge {
        position: absolute;
        top: 14px;
        right: 14px;
        backdrop-filter: blur(8px);
        background: rgba(255, 255, 255, 0.9);
        color: #0369a1;
        padding: 0.28rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 800;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .media-chip-subject {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        background: #f0f9ff;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }
    .open-link-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff !important;
        border: none;
        border-radius: 1rem;
        padding: 0.5rem 1.15rem;
        font-weight: 700;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        transition: all 0.2s ease;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        text-decoration: none !important;
    }
    .open-link-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(16, 185, 129, 0.45);
        filter: brightness(1.06);
    }
    .view-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.8rem;
        color: #64748b;
        background-color: #f1f5f9;
        padding: 0.3rem 0.65rem;
        border-radius: 9999px;
        font-weight: 700;
    }
    .rating-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.8rem;
        color: #b45309;
        background-color: #fefce8;
        border: 1px solid #fef08a;
        padding: 0.3rem 0.65rem;
        border-radius: 9999px;
        font-weight: 800;
        cursor: pointer;
    }
    .rating-badge:hover {
        background-color: #fef08a;
    }
    .add-floating-btn {
        position: fixed;
        right: 2rem;
        bottom: 2rem;
        z-index: 1000;
        background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
        border: none;
        color: #fff;
        font-weight: 800;
        font-size: 1rem;
        border-radius: 9999px;
        padding: 0.85rem 1.75rem;
        box-shadow: 0 8px 25px rgba(234, 88, 12, 0.45);
        transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }
    .add-floating-btn:hover {
        transform: translateY(-4px) scale(1.04);
        box-shadow: 0 12px 30px rgba(234, 88, 12, 0.6);
        color: #fff;
    }
    .subj-filter-btn, .type-filter-btn {
        font-weight: 700;
        border-radius: 9999px;
        padding: 0.4rem 1.05rem;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        font-size: 0.82rem;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .subj-filter-btn:hover, .type-filter-btn:hover {
        border-color: #38bdf8;
        color: #0284c7;
        background: #f0f9ff;
    }
    .subj-filter-btn.active, .type-filter-btn.active {
        background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);
        color: #ffffff !important;
        border-color: transparent !important;
        box-shadow: 0 4px 15px rgba(2, 132, 199, 0.35);
    }

    /* Star Rating widget */
    .star-rating {
        display: inline-flex;
        flex-direction: row-reverse;
        justify-content: center;
        gap: 0.35rem;
    }
    .star-rating input {
        display: none;
    }
    .star-rating label {
        font-size: 2.2rem;
        color: #cbd5e1;
        cursor: pointer;
        transition: color 0.15s ease, transform 0.15s ease;
    }
    .star-rating label:hover,
    .star-rating label:hover ~ label,
    .star-rating input:checked ~ label {
        color: #f59e0b;
        transform: scale(1.1);
    }
</style>

<?php if (!$isLoggedIn): ?>
<!-- Public Top Nav for Guests / Students -->
<nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm mb-4">
    <div class="container px-3 py-1">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-slate-800" href="/index.php">
            <div style="width:34px;height:34px;background:linear-gradient(135deg,#0284c7,#2563eb);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;">
                <i class="fas fa-book-open"></i>
            </div>
            <span style="font-size:1.15rem;font-weight:800;">คลังสื่อการเรียนรู้ <span class="text-primary">ละลมวิทยา</span></span>
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <a href="/index.php" class="btn btn-light rounded-pill px-3 py-1 text-xs fw-bold border">
                <i class="fas fa-home me-1"></i> หน้าหลัก
            </a>
            <a href="/login.php?redirect=/learning_media/index.php" class="btn btn-primary rounded-pill px-3 py-1 text-xs fw-bold shadow-sm" style="background:#2563eb;">
                <i class="fas fa-sign-in-alt me-1"></i> เข้าสู่ระบบ (ครู)
            </a>
        </div>
    </div>
</nav>
<div class="container pb-5">
<?php endif; ?>

<!-- ── Filter & Search Section ── -->
<div class="bg-white rounded-3xl p-5 mb-5 shadow-sm border border-slate-100">
    <div class="row g-3 align-items-center justify-content-between">
        <!-- Search Input -->
        <div class="col-12 col-md-5 col-lg-4">
            <div class="input-group">
                <span class="input-group-text bg-slate-50 border-slate-200 text-slate-400 rounded-start-2xl">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="searchInput" class="form-control bg-slate-50 border-slate-200 text-sm font-semibold rounded-end-2xl py-2"
                       placeholder="ค้นหาชื่อสื่อ, ผู้พัฒนา หรือระดับชั้น..." oninput="onSearchChange(this.value)">
            </div>
        </div>

        <!-- Grade Selector -->
        <div class="col-12 col-md-4 col-lg-3">
            <div class="d-flex align-items-center gap-2">
                <label for="gradeFilter" class="text-xs font-black text-slate-500 uppercase tracking-wider mb-0 text-nowrap">
                    <i class="fas fa-school text-primary me-1"></i>ระดับชั้น:
                </label>
                <select id="gradeFilter" class="form-select bg-slate-50 border-slate-200 rounded-2xl text-xs font-bold py-2" onchange="onGradeChange(this.value)">
                    <option value="">ทุกระดับชั้น</option>
                    <option value="ม.1">ม.1</option>
                    <option value="ม.2">ม.2</option>
                    <option value="ม.3">ม.3</option>
                    <option value="ม.4">ม.4</option>
                    <option value="ม.5">ม.5</option>
                    <option value="ม.6">ม.6</option>
                    <option value="ม.1-3">ม.1-3 (ม.ต้น)</option>
                    <option value="ม.4-6">ม.4-6 (ม.ปลาย)</option>
                </select>
            </div>
        </div>

        <!-- Stats Counter -->
        <div class="col-12 col-md-3 text-md-end">
            <span id="totalMediaCount" class="badge rounded-pill px-3 py-2 text-xs font-bold"
                  style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;">
                กำลังโหลด...
            </span>
        </div>
    </div>

    <!-- Media Type Filter Pills -->
    <div class="mt-4 pt-3 border-top border-slate-100">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="text-xs font-black text-slate-400 uppercase tracking-wider">
                <i class="fas fa-filter text-sky-500 me-1"></i> รูปแบบสื่อ:
            </span>
        </div>
        <div id="typeFilter" class="d-flex flex-wrap gap-2">
            <button type="button" class="type-filter-btn active" onclick="selectType('')">
                <i class="fas fa-border-all me-1"></i> ทุกรูปแบบ
            </button>
            <button type="button" class="type-filter-btn" onclick="selectType('slide')">
                <i class="fas fa-file-powerpoint text-indigo-500 me-1"></i> สไลด์/เอกสาร
            </button>
            <button type="button" class="type-filter-btn" onclick="selectType('video')">
                <i class="fas fa-play-circle text-rose-500 me-1"></i> วิดีโอคลิป
            </button>
            <button type="button" class="type-filter-btn" onclick="selectType('worksheet')">
                <i class="fas fa-file-alt text-emerald-500 me-1"></i> ใบงาน/แบบฝึก
            </button>
            <button type="button" class="type-filter-btn" onclick="selectType('game')">
                <i class="fas fa-gamepad text-amber-500 me-1"></i> สื่อเกม/Interactive
            </button>
            <button type="button" class="type-filter-btn" onclick="selectType('website')">
                <i class="fas fa-globe text-sky-500 me-1"></i> เว็บไซต์/บทเรียน
            </button>
        </div>
    </div>

    <!-- Subject Category Tabs -->
    <div class="mt-3 pt-3 border-top border-slate-100">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="text-xs font-black text-slate-400 uppercase tracking-wider">
                <i class="fas fa-book-reader text-primary me-1"></i> กลุ่มสาระการเรียนรู้:
            </span>
        </div>
        <div id="subjectFilter" class="d-flex flex-wrap gap-2">
            <!-- Dynamic Subject Pills -->
        </div>
    </div>
</div>

<!-- ── Media Card Grid ── -->
<div id="mediaList" class="row g-4 min-h-[300px]">
    <!-- Loaded dynamically via API -->
</div>

<!-- ── Floating "+ เพิ่มสื่อ" Button (for logged-in teachers/admins) ── -->
<?php if ($canManage): ?>
<button type="button" class="add-floating-btn" onclick="openAddModal()">
    <i class="fas fa-plus"></i>
    <span>เพิ่มสื่อใหม่</span>
</button>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     MODAL: เพิ่มรายการสื่อใหม่
     ══════════════════════════════════════════════════════ -->
<div class="modal fade" id="addMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3xl shadow-2xl overflow-hidden">
            <form id="addMediaForm" onsubmit="submitAddMedia(event)">
                <div class="modal-header text-white px-6 py-4" style="background:linear-gradient(135deg,#0284c7,#2563eb);">
                    <h5 class="modal-title font-black d-flex align-items-center gap-2">
                        <i class="fas fa-plus-circle"></i> เพิ่มสื่อการเรียนรู้ใหม่
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-6 row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">วันที่เผยแพร่</label>
                        <input type="date" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5"
                               name="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">ชื่อผู้ออกแบบ / ผู้จัดทำ</label>
                        <input type="text" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5"
                               name="author" value="<?= htmlspecialchars($userName) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">ระดับชั้น</label>
                        <select class="form-select rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5" name="grade" required>
                            <option value="">— เลือกระดับชั้น —</option>
                            <option value="ม.1">ม.1</option>
                            <option value="ม.2">ม.2</option>
                            <option value="ม.3">ม.3</option>
                            <option value="ม.4">ม.4</option>
                            <option value="ม.5">ม.5</option>
                            <option value="ม.6">ม.6</option>
                            <option value="ม.1-3">ม.1-3 (ม.ต้น)</option>
                            <option value="ม.4-6">ม.4-6 (ม.ปลาย)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">กลุ่มสาระการเรียนรู้</label>
                        <select class="form-select rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5" name="subject" required>
                            <option value="">— เลือกกลุ่มสาระ —</option>
                            <option value="ภาษาไทย">ภาษาไทย</option>
                            <option value="คณิตศาสตร์">คณิตศาสตร์</option>
                            <option value="วิทยาศาสตร์และเทคโนโลยี">วิทยาศาสตร์และเทคโนโลยี</option>
                            <option value="สังคมศึกษา">สังคมศึกษา ศาสนา และวัฒนธรรม</option>
                            <option value="สุขศึกษา">สุขศึกษาและพลศึกษา</option>
                            <option value="ศิลปะ">ศิลปะ</option>
                            <option value="การงานอาชีพ">การงานอาชีพ</option>
                            <option value="ภาษาต่างประเทศ">ภาษาต่างประเทศ</option>
                            <option value="กิจกรรมพัฒนาผู้เรียน">กิจกรรมพัฒนาผู้เรียน</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">รูปแบบสื่อ</label>
                        <select class="form-select rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5" name="mediaType" required>
                            <option value="slide">สไลด์นำเสนอ / เอกสาร (Canva, PPT, PDF)</option>
                            <option value="video">วิดีโอคลิป (YouTube, MP4)</option>
                            <option value="worksheet">ใบงาน / แบบฝึกทักษะ</option>
                            <option value="game">สื่อเกมการศึกษา (Wordwall, Quizizz)</option>
                            <option value="website">เว็บไซต์ / บทเรียนออนไลน์ (Sites, LMS)</option>
                            <option value="other">สื่อและนวัตกรรมอื่นๆ</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">ชื่อสื่อการเรียนรู้</label>
                        <input type="text" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5"
                               name="mediaName" placeholder="เช่น สื่ออินโฟกราฟิก เรื่อง ระบบสุริยะ" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">ลิงก์สื่อ (Link URL)</label>
                        <input type="url" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5"
                               name="mediaLink" placeholder="https://..." required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">
                            วัตถุประสงค์การใช้สื่อ (สำหรับรายงาน/ว.PA)
                        </label>
                        <textarea class="form-control rounded-2xl bg-slate-50 border-slate-200 text-xs p-2.5"
                                  name="objective" rows="2" placeholder="เพื่อพัฒนาผลสัมฤทธิ์ทางการเรียน เรื่อง..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">
                            ผลการนำไปใช้ / สรุปผลการเรียนรู้
                        </label>
                        <textarea class="form-control rounded-2xl bg-slate-50 border-slate-200 text-xs p-2.5"
                                  name="usageResults" rows="2" placeholder="ผู้เรียนมีความเข้าใจและผลการทดสอบผ่านเกณฑ์ร้อยละ..."></textarea>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">
                            รูปภาพหน้าปก (ไฟล์รูป JPG, PNG, WEBP ขนาดไม่เกิน 3MB)
                        </label>
                        <input type="file" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-xs p-2.5"
                               name="cover" accept="image/*" onchange="previewUploadCover(this, '#addCoverPreview')">
                        <div id="addCoverPreview" class="mt-2 text-center" style="display:none;">
                            <img src="" style="max-height:140px;border-radius:12px;border:1px solid #e2e8f0;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer px-6 py-3 bg-slate-50 border-top">
                    <button type="button" class="btn btn-light rounded-xl font-bold text-xs px-4 py-2" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-xl font-black text-xs px-6 py-2.5 shadow-md" style="background:#2563eb;">
                        <i class="fas fa-cloud-upload-alt me-1"></i> บันทึกสื่อการเรียนรู้
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: แก้ไขข้อมูลสื่อ
     ══════════════════════════════════════════════════════ -->
<div class="modal fade" id="editMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3xl shadow-2xl overflow-hidden">
            <form id="editMediaForm" onsubmit="submitEditMedia(event)">
                <input type="hidden" name="id" id="editMediaId">
                <div class="modal-header text-dark px-6 py-4" style="background:#fef3c7;">
                    <h5 class="modal-title font-black text-amber-900 d-flex align-items-center gap-2">
                        <i class="fas fa-edit text-amber-600"></i> แก้ไขข้อมูลสื่อการเรียนรู้
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-6 row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">วันที่</label>
                        <input type="date" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5"
                               name="date" id="editMediaDate" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">ชื่อผู้ออกแบบ</label>
                        <input type="text" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5"
                               name="author" id="editMediaAuthor" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">ระดับชั้น</label>
                        <select class="form-select rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5" name="grade" id="editMediaGrade" required>
                            <option value="">— เลือกระดับชั้น —</option>
                            <option value="ม.1">ม.1</option>
                            <option value="ม.2">ม.2</option>
                            <option value="ม.3">ม.3</option>
                            <option value="ม.4">ม.4</option>
                            <option value="ม.5">ม.5</option>
                            <option value="ม.6">ม.6</option>
                            <option value="ม.1-3">ม.1-3 (ม.ต้น)</option>
                            <option value="ม.4-6">ม.4-6 (ม.ปลาย)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">กลุ่มสาระการเรียนรู้</label>
                        <select class="form-select rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5" name="subject" id="editMediaSubject" required>
                            <option value="">— เลือกกลุ่มสาระ —</option>
                            <option value="ภาษาไทย">ภาษาไทย</option>
                            <option value="คณิตศาสตร์">คณิตศาสตร์</option>
                            <option value="วิทยาศาสตร์และเทคโนโลยี">วิทยาศาสตร์และเทคโนโลยี</option>
                            <option value="สังคมศึกษา">สังคมศึกษา ศาสนา และวัฒนธรรม</option>
                            <option value="สุขศึกษา">สุขศึกษาและพลศึกษา</option>
                            <option value="ศิลปะ">ศิลปะ</option>
                            <option value="การงานอาชีพ">การงานอาชีพ</option>
                            <option value="ภาษาต่างประเทศ">ภาษาต่างประเทศ</option>
                            <option value="กิจกรรมพัฒนาผู้เรียน">กิจกรรมพัฒนาผู้เรียน</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">รูปแบบสื่อ</label>
                        <select class="form-select rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5" name="mediaType" id="editMediaType" required>
                            <option value="slide">สไลด์นำเสนอ / เอกสาร</option>
                            <option value="video">วิดีโอคลิป</option>
                            <option value="worksheet">ใบงาน / แบบฝึกทักษะ</option>
                            <option value="game">สื่อเกมการศึกษา</option>
                            <option value="website">เว็บไซต์ / บทเรียนออนไลน์</option>
                            <option value="other">สื่ออื่นๆ</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">ชื่อสื่อการเรียนรู้</label>
                        <input type="text" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5"
                               name="mediaName" id="editMediaName" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">Link สื่อ</label>
                        <input type="url" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2.5"
                               name="mediaLink" id="editMediaLink" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">วัตถุประสงค์การใช้สื่อ</label>
                        <textarea class="form-control rounded-2xl bg-slate-50 border-slate-200 text-xs p-2.5"
                                  name="objective" id="editMediaObjective" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">ผลการนำไปใช้ / สรุปผล</label>
                        <textarea class="form-control rounded-2xl bg-slate-50 border-slate-200 text-xs p-2.5"
                                  name="usageResults" id="editMediaUsageResults" rows="2"></textarea>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">
                            เปลี่ยนรูปภาพหน้าปกใหม่ (เว้นว่างไว้หากไม่ต้องการเปลี่ยน)
                        </label>
                        <input type="file" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-xs p-2.5"
                               name="cover" accept="image/*" onchange="previewUploadCover(this, '#editCoverPreview')">
                        <input type="hidden" name="existingCover" id="editMediaExistingCover">
                        <div id="editCoverPreview" class="mt-2 text-center">
                            <img id="editCoverPreviewImg" src="" style="max-height:140px;border-radius:12px;border:1px solid #e2e8f0;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer px-6 py-3 bg-slate-50 border-top">
                    <button type="button" class="btn btn-light rounded-xl font-bold text-xs px-4 py-2" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning rounded-xl font-black text-xs px-6 py-2.5 shadow-md">
                        <i class="fas fa-save me-1"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: ประเมินความพึงพอใจสื่อ (1-5 ดาว)
     ══════════════════════════════════════════════════════ -->
<div class="modal fade" id="rateMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-3xl shadow-2xl overflow-hidden">
            <form id="rateMediaForm" onsubmit="submitRateMedia(event)">
                <input type="hidden" name="media_id" id="rateMediaId">
                <div class="modal-header text-white px-5 py-4" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <h5 class="modal-title font-black d-flex align-items-center gap-2">
                        <i class="fas fa-star"></i> ประเมินความพึงพอใจสื่อการเรียนรู้
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-6 text-center">
                    <h6 id="rateMediaTitle" class="font-bold text-slate-800 mb-2 leading-snug">ชื่อสื่อ...</h6>
                    <p class="text-xs text-slate-400 mb-4">ให้คะแนนความพึงพอใจเพื่อช่วยพัฒนาคุณภาพสื่อการสอน</p>

                    <!-- 5 Stars Input -->
                    <div class="star-rating mb-4">
                        <input type="radio" id="star5" name="rating" value="5" checked><label for="star5" title="5 ดาว (ดีเยี่ยม)">★</label>
                        <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 ดาว (ดีมาก)">★</label>
                        <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 ดาว (ปานกลาง)">★</label>
                        <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 ดาว (พอใช้)">★</label>
                        <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 ดาว (ควรปรับปรุง)">★</label>
                    </div>

                    <div class="text-start mb-3">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">ชื่อผู้ประเมิน</label>
                        <input type="text" class="form-control rounded-2xl bg-slate-50 border-slate-200 text-sm font-semibold py-2"
                               name="name" id="rateEvaluatorName" value="<?= htmlspecialchars($userName) ?>" placeholder="ชื่อ-สกุล หรือ นักเรียน ม.x">
                    </div>

                    <div class="text-start mb-2">
                        <label class="form-label text-xs font-black text-slate-500 uppercase tracking-wider">ข้อเสนอแนะ / ความคิดเห็นเพิ่มเติม</label>
                        <textarea class="form-control rounded-2xl bg-slate-50 border-slate-200 text-xs p-3"
                                  name="feedback" rows="3" placeholder="สื่อเข้าใจง่าย สวยงาม น่าสนใจ หรือสิ่งที่อยากให้เพิ่มเติม..."></textarea>
                    </div>
                </div>
                <div class="modal-footer px-5 py-3 bg-slate-50 border-top justify-content-between">
                    <button type="button" class="btn btn-light rounded-xl font-bold text-xs px-4 py-2" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" class="btn btn-warning rounded-xl font-black text-xs px-5 py-2.5 shadow-md" style="background:#f59e0b;color:#fff;">
                        <i class="fas fa-check-circle me-1"></i> ส่งผลการประเมิน
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: แสดง QR Code และแชร์ลิงก์
     ══════════════════════════════════════════════════════ -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-3xl shadow-2xl overflow-hidden">
            <div class="modal-header text-white px-5 py-3.5" style="background:linear-gradient(135deg,#0284c7,#2563eb);">
                <h5 class="modal-title font-black text-sm d-flex align-items-center gap-2">
                    <i class="fas fa-qrcode"></i> QR Code สื่อการเรียนรู้
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-6 text-center">
                <h6 id="qrModalTitle" class="font-bold text-slate-800 mb-1 leading-snug">ชื่อสื่อ...</h6>
                <p id="qrModalAuthor" class="text-xs text-slate-500 mb-4">ผู้จัดทำ: ...</p>

                <div class="bg-white p-3 rounded-2xl border shadow-inner inline-block mb-4">
                    <img id="qrModalImg" src="" alt="QR Code" style="width:200px;height:200px;display:block;">
                </div>

                <div class="input-group mb-3">
                    <input type="text" id="qrModalLinkInput" class="form-control text-xs font-semibold bg-slate-50" readonly>
                    <button class="btn btn-primary text-xs font-bold px-3" type="button" onclick="copyQrLink()">
                        <i class="fas fa-copy me-1"></i> คัดลอก
                    </button>
                </div>
                <p class="text-[11px] text-slate-400 mb-0">นักเรียนสามารถใช้กล้องมือถือสแกนเพื่อเปิดใช้งานสื่อได้ทันที</p>
            </div>
            <div class="modal-footer px-5 py-3 bg-slate-50 border-top justify-content-between">
                <button type="button" class="btn btn-light rounded-xl font-bold text-xs px-4 py-2" data-bs-dismiss="modal">ปิด</button>
                <a id="qrDownloadBtn" href="#" download="qrcode.png" target="_blank" class="btn btn-outline-primary rounded-xl font-bold text-xs px-4 py-2">
                    <i class="fas fa-download me-1"></i> ดาวน์โหลด QR Code
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!$isLoggedIn): ?>
</div> <!-- end container for public view -->
<?php else: ?>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
<?php endif; ?>

<script>
// ── State variables ──
let allMediaList = [];
let activeSubject = "";
let activeGrade = "";
let activeMediaType = "";
let searchQuery = "";
const currentUserId = <?= (int)$userId ?>;
const currentUserRole = '<?= htmlspecialchars($userRole) ?>';
const currentUserName = '<?= htmlspecialchars($userName) ?>';
const canManage = <?= $canManage ? 'true' : 'false' ?>;

// ── Media Type Metadata ──
const MEDIA_TYPES = {
    slide:     { label: 'สไลด์/เอกสาร', icon: 'fa-file-powerpoint', color: 'indigo' },
    video:     { label: 'วิดีโอคลิป',   icon: 'fa-play-circle',       color: 'rose' },
    worksheet: { label: 'ใบงาน/แบบฝึก', icon: 'fa-file-alt',          color: 'emerald' },
    game:      { label: 'เกมการศึกษา',  icon: 'fa-gamepad',           color: 'amber' },
    website:   { label: 'เว็บไซต์/LMS',  icon: 'fa-globe',             color: 'sky' },
    other:     { label: 'สื่ออื่นๆ',     icon: 'fa-shapes',            color: 'slate' }
};

// ── Fetch Media List from API ──
async function loadMediaList() {
    try {
        const res = await fetch('/learning_media/api.php?action=list');
        const json = await res.json();
        if (json.status !== 'success') {
            document.getElementById('mediaList').innerHTML = `
                <div class="col-12 text-center py-5">
                    <p class="text-rose-500 font-bold">เกิดข้อผิดพลาดในการโหลดข้อมูล: ${json.message || 'Error'}</p>
                </div>`;
            return;
        }

        allMediaList = json.data || [];
        renderSubjectFilter(json.subjectsCount || {});
        filterAndRender();
    } catch (err) {
        console.error('loadMediaList error:', err);
    }
}

// ── Render Subject Filter Pills ──
function renderSubjectFilter(subjectCounts) {
    const subjects = [
        'ภาษาไทย', 'คณิตศาสตร์', 'วิทยาศาสตร์และเทคโนโลยี',
        'สังคมศึกษา', 'สุขศึกษา', 'ศิลปะ', 'การงานอาชีพ', 'ภาษาต่างประเทศ', 'กิจกรรมพัฒนาผู้เรียน'
    ];

    let total = allMediaList.length;
    let html = `
        <button type="button" class="subj-filter-btn ${activeSubject === '' ? 'active' : ''}" onclick="selectSubject('')">
            <i class="fas fa-layer-group me-1"></i> ทั้งหมด
            <span class="badge rounded-pill bg-slate-100 text-slate-700 ms-1 px-2 py-0.5" style="font-size:0.7rem;">${total}</span>
        </button>
    `;

    subjects.forEach(subj => {
        const count = subjectCounts[subj] || allMediaList.filter(m => m.subject === subj).length;
        if (count > 0 || canManage) {
            html += `
                <button type="button" class="subj-filter-btn ${activeSubject === subj ? 'active' : ''}" onclick="selectSubject('${subj}')">
                    ${subj}
                    <span class="badge rounded-pill ${activeSubject === subj ? 'bg-white text-primary' : 'bg-slate-100 text-slate-700'} ms-1 px-2 py-0.5" style="font-size:0.7rem;">${count}</span>
                </button>
            `;
        }
    });

    document.getElementById('subjectFilter').innerHTML = html;
}

function selectSubject(subj) {
    activeSubject = subj;
    document.querySelectorAll('.subj-filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.textContent.trim().startsWith(subj || 'ทั้งหมด'));
    });
    filterAndRender();
}

function selectType(type) {
    activeMediaType = type;
    document.querySelectorAll('.type-filter-btn').forEach(btn => {
        if (!type) {
            btn.classList.toggle('active', btn.textContent.includes('ทุกรูปแบบ'));
        } else {
            const meta = MEDIA_TYPES[type];
            btn.classList.toggle('active', meta && btn.textContent.includes(meta.label));
        }
    });
    filterAndRender();
}

function onGradeChange(val) {
    activeGrade = val;
    filterAndRender();
}

function onSearchChange(val) {
    searchQuery = val.trim().toLowerCase();
    filterAndRender();
}

// ── Filter and Render Cards ──
function filterAndRender() {
    let filtered = allMediaList;

    if (activeGrade) {
        filtered = filtered.filter(item => item.grade === activeGrade);
    }
    if (activeSubject) {
        filtered = filtered.filter(item => item.subject === activeSubject);
    }
    if (activeMediaType) {
        filtered = filtered.filter(item => item.mediaType === activeMediaType);
    }
    if (searchQuery) {
        filtered = filtered.filter(item =>
            (item.mediaName && item.mediaName.toLowerCase().includes(searchQuery)) ||
            (item.author && item.author.toLowerCase().includes(searchQuery)) ||
            (item.grade && item.grade.toLowerCase().includes(searchQuery)) ||
            (item.subject && item.subject.toLowerCase().includes(searchQuery)) ||
            (item.objective && item.objective.toLowerCase().includes(searchQuery))
        );
    }

    document.getElementById('totalMediaCount').innerHTML = `พบ <strong>${filtered.length}</strong> รายการ`;

    const el = document.getElementById('mediaList');
    if (!el) return;

    if (filtered.length === 0) {
        el.innerHTML = `
            <div class="col-12 py-5 text-center">
                <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-3xl mx-auto flex items-center justify-center text-2xl mb-3 shadow-inner">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h5 class="font-bold text-slate-700">ไม่พบสื่อการเรียนรู้ตามเงื่อนไข</h5>
                <p class="text-xs text-slate-400 mt-1">ลองเปลี่ยนคำค้นหา หรือเลือกระดับชั้น/กลุ่มสาระ/รูปแบบอื่น</p>
            </div>
        `;
        return;
    }

    el.innerHTML = filtered.map(item => {
        const isOwner = canManage && (currentUserRole === 'super_admin' || item.userId === currentUserId || item.author === currentUserName);
        const encodedLink = encodeURIComponent(item.mediaLink);
        const typeMeta = MEDIA_TYPES[item.mediaType] || MEDIA_TYPES.other;

        const ratingText = item.ratingCount > 0
            ? `⭐ ${item.ratingAvg.toFixed(1)} (${item.ratingCount})`
            : `⭐ ประเมิน`;

        return `
            <div class="col-12 col-md-6 col-lg-4">
                <div class="media-card h-100">
                    <div class="cover-img-wrap">
                        <img src="${item.cover}" class="cover-img" alt="${escapeHtml(item.mediaName)}"
                             onerror="this.src='https://cdn-icons-png.flaticon.com/512/847/847969.png'">
                        <span class="media-grade-badge">${item.grade}</span>
                        <span class="media-type-badge">
                            <i class="fas ${typeMeta.icon}"></i> ${typeMeta.label}
                        </span>
                    </div>
                    <div class="p-4 d-flex flex-column flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="media-chip-subject">
                                <i class="fas fa-tag"></i> ${item.subject}
                            </span>
                            <span class="rating-badge" title="คลิกเพื่อประเมินความพึงพอใจ" onclick="openRateModal(${item.id})">
                                ${ratingText}
                            </span>
                        </div>
                        <h5 class="text-slate-800 font-bold mb-2 leading-snug" style="min-height:2.6em;font-size:1.02rem;" title="${escapeHtml(item.mediaName)}">
                            ${escapeHtml(item.mediaName)}
                        </h5>
                        <div class="text-xs text-slate-500 mb-1 d-flex align-items-center gap-1.5">
                            <i class="fas fa-user-edit text-slate-400"></i>
                            <span class="truncate"><strong>ผู้จัดทำ:</strong> ${escapeHtml(item.author)}</span>
                        </div>
                        <div class="text-xs text-slate-400 mb-3 d-flex align-items-center gap-1.5">
                            <i class="far fa-calendar-alt"></i>
                            <span>${item.date}</span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mt-auto pt-3 border-top border-slate-100">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="open-link-btn" onclick="handleOpenMedia(${item.id}, '${encodedLink}')">
                                    <i class="fas fa-external-link-alt"></i> เปิดดูสื่อ
                                </button>
                                <button type="button" class="btn btn-sm btn-light rounded-2xl px-2.5 py-1.5 text-xs text-slate-600 border" title="แสดง QR Code" onclick="openQrModal(${item.id})">
                                    <i class="fas fa-qrcode text-sky-600"></i>
                                </button>
                            </div>
                            <span class="view-badge" title="จำนวนครั้งที่เข้าชม">
                                <i class="fas fa-eye text-primary"></i> <span id="view-count-${item.id}">${item.views.toLocaleString()}</span>
                            </span>
                        </div>

                        ${isOwner ? `
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mt-3 pt-2 border-top border-slate-100">
                            <div class="d-flex gap-1">
                                <a href="/learning_media/print_memo.php?id=${item.id}" target="_blank" class="btn btn-sm btn-light rounded-pill px-2.5 py-1 text-[11px] font-bold text-slate-600 hover:text-sky-600" title="พิมพ์บันทึกข้อความขออนุมัติ/รายงาน">
                                    <i class="fas fa-print text-sky-600 me-0.5"></i> บันทึก
                                </a>
                                <a href="/learning_media/print_summary.php?id=${item.id}" target="_blank" class="btn btn-sm btn-light rounded-pill px-2.5 py-1 text-[11px] font-bold text-slate-600 hover:text-emerald-600" title="พิมพ์เอกสารสรุปผล ว.PA / SAR">
                                    <i class="fas fa-file-invoice text-emerald-600 me-0.5"></i> ว.PA
                                </a>
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-light rounded-pill px-2.5 py-1 text-[11px] font-bold text-slate-600 hover:text-primary" onclick="openEditModal(${item.id})">
                                    <i class="fas fa-edit text-primary me-0.5"></i> แก้ไข
                                </button>
                                <button type="button" class="btn btn-sm btn-light rounded-pill px-2.5 py-1 text-[11px] font-bold text-rose-500 hover:bg-rose-50" onclick="confirmDeleteMedia(${item.id}, '${escapeHtml(item.mediaName)}')">
                                    <i class="fas fa-trash-alt me-0.5"></i> ลบ
                                </button>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// ── Open Media & Increment View Count ──
function handleOpenMedia(id, encodedLink) {
    // Increment view asynchronously
    fetch('/learning_media/api.php?action=view', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    }).then(r => r.json()).then(res => {
        if (res.status === 'success' && res.views) {
            const el = document.getElementById('view-count-' + id);
            if (el) el.textContent = res.views.toLocaleString();
            const item = allMediaList.find(m => m.id === id);
            if (item) item.views = res.views;
        }
    }).catch(() => {});

    // Open media in new tab
    const url = decodeURIComponent(encodedLink);
    window.open(url, '_blank');
}

// ── Rate Media Modal ──
function openRateModal(id) {
    const item = allMediaList.find(m => m.id === id);
    if (!item) return;

    document.getElementById('rateMediaId').value = item.id;
    document.getElementById('rateMediaTitle').textContent = item.mediaName;
    document.getElementById('star5').checked = true;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('rateMediaModal')).show();
}

async function submitRateMedia(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    try {
        const res = await fetch('/learning_media/api.php?action=rate', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.status === 'success') {
            Swal.fire({ icon: 'success', title: 'ขอบคุณครับ!', text: json.message, timer: 1500, showConfirmButton: false });
            bootstrap.Modal.getInstance(document.getElementById('rateMediaModal')).hide();
            await loadMediaList();
        } else {
            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: json.msg || json.message });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: err.message });
    }
}

// ── QR Code Modal ──
function openQrModal(id) {
    const item = allMediaList.find(m => m.id === id);
    if (!item) return;

    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(item.mediaLink);

    document.getElementById('qrModalTitle').textContent = item.mediaName;
    document.getElementById('qrModalAuthor').textContent = 'ผู้จัดทำ: ' + item.author + ' (' + item.grade + ')';
    document.getElementById('qrModalImg').src = qrUrl;
    document.getElementById('qrModalLinkInput').value = item.mediaLink;
    document.getElementById('qrDownloadBtn').href = qrUrl;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('qrModal')).show();
}

function copyQrLink() {
    const input = document.getElementById('qrModalLinkInput');
    input.select();
    navigator.clipboard.writeText(input.value);
    Swal.fire({ icon: 'success', title: 'คัดลอกลิงก์แล้ว', timer: 1000, showConfirmButton: false });
}

// ── Add Media Modal Actions ──
function openAddModal() {
    const modalEl = document.getElementById('addMediaModal');
    if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

function previewUploadCover(input, previewContainerSelector) {
    const file = input.files[0];
    const previewContainer = document.querySelector(previewContainerSelector);
    if (!previewContainer) return;

    if (file) {
        if (file.size > 3 * 1024 * 1024) {
            Swal.fire({ icon: 'warning', title: 'ไฟล์ใหญ่เกินไป', text: 'กรุณาเลือกไฟล์ภาพขนาดไม่เกิน 3MB' });
            input.value = '';
            previewContainer.style.display = 'none';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            const img = previewContainer.querySelector('img');
            if (img) img.src = e.target.result;
            previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        previewContainer.style.display = 'none';
    }
}

async function submitAddMedia(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    Swal.fire({
        title: 'กำลังบันทึกข้อมูล...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const res = await fetch('/learning_media/api.php?action=save', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();
        Swal.close();

        if (json.status === 'success') {
            Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', text: json.message, timer: 1500, showConfirmButton: false });
            form.reset();
            document.getElementById('addCoverPreview').style.display = 'none';
            bootstrap.Modal.getInstance(document.getElementById('addMediaModal')).hide();
            await loadMediaList();
        } else {
            Swal.fire({ icon: 'error', title: 'ไม่สามารถบันทึกได้', text: json.msg || json.message });
        }
    } catch (err) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: err.message });
    }
}

// ── Edit Media Modal Actions ──
function openEditModal(id) {
    const item = allMediaList.find(m => m.id === id);
    if (!item) return;

    document.getElementById('editMediaId').value = item.id;
    document.getElementById('editMediaDate').value = item.rawDate || '';
    document.getElementById('editMediaAuthor').value = item.author || '';
    document.getElementById('editMediaGrade').value = item.grade || '';
    document.getElementById('editMediaSubject').value = item.subject || '';
    document.getElementById('editMediaType').value = item.mediaType || 'slide';
    document.getElementById('editMediaName').value = item.mediaName || '';
    document.getElementById('editMediaLink').value = item.mediaLink || '';
    document.getElementById('editMediaObjective').value = item.objective || '';
    document.getElementById('editMediaUsageResults').value = item.usageResults || '';
    document.getElementById('editMediaExistingCover').value = item.cover || '';

    const preview = document.getElementById('editCoverPreview');
    const previewImg = document.getElementById('editCoverPreviewImg');
    if (item.cover && item.cover !== 'https://cdn-icons-png.flaticon.com/512/847/847969.png') {
        previewImg.src = item.cover;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('editMediaModal')).show();
}

async function submitEditMedia(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    Swal.fire({
        title: 'กำลังบันทึกการแก้ไข...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const res = await fetch('/learning_media/api.php?action=update', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();
        Swal.close();

        if (json.status === 'success') {
            Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', text: json.message, timer: 1500, showConfirmButton: false });
            bootstrap.Modal.getInstance(document.getElementById('editMediaModal')).hide();
            await loadMediaList();
        } else {
            Swal.fire({ icon: 'error', title: 'ไม่สามารถบันทึกได้', text: json.msg || json.message });
        }
    } catch (err) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: err.message });
    }
}

async function confirmDeleteMedia(id, name) {
    const confirm = await Swal.fire({
        title: 'ยืนยันการลบ?',
        html: `คุณต้องการลบสื่อ <b>"${escapeHtml(name)}"</b> ใช่หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบสื่อนี้',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8'
    });

    if (!confirm.isConfirmed) return;

    Swal.fire({ title: 'กำลังลบ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const res = await fetch('/learning_media/api.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id)
        });
        const json = await res.json();
        Swal.close();

        if (json.status === 'success') {
            Swal.fire({ icon: 'success', title: 'ลบสำเร็จ!', text: json.message, timer: 1500, showConfirmButton: false });
            await loadMediaList();
        } else {
            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: json.msg || json.message });
        }
    } catch (err) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: err.message });
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', loadMediaList);
</script>
