<?php
/**
 * learning_media/print_memo.php — แบบบันทึกข้อความราชการ ขออนุมัติและรายงานผลการใช้สื่อการเรียนรู้
 * 2-Page Official Thai Government Memo Format with Digital Signature
 */
session_start();
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ไม่พบรหัสสื่อการเรียนรู้');
}

$pdo = getPdo();
$stmt = $pdo->prepare("SELECT * FROM learning_media WHERE id = ?");
$stmt->execute([$id]);
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$media) {
    die('ไม่พบข้อมูลสื่อการเรียนรู้');
}

// Convert date to Thai Buddhist Date
function thaiDateFull($dateStr) {
    if (!$dateStr) return '';
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $ts = strtotime($dateStr);
    $d = (int)date('d', $ts);
    $m = $months[(int)date('m', $ts)];
    $y = (int)date('Y', $ts) + 543;
    return "{$d} {$m} {$y}";
}

$mediaTypesThai = [
    'slide'     => 'สไลด์นำเสนอ / เอกสารประกอบการสอน (Canva, PPT, PDF)',
    'video'     => 'วิดีโอคลิป / สื่อมัลติมีเดีย (YouTube, MP4)',
    'worksheet' => 'ใบงาน / ใบกิจกรรม / แบบฝึกทักษะ',
    'game'      => 'สื่อเกมการศึกษา / Interactive (Wordwall, Quizizz, Kahoot)',
    'website'   => 'เว็บไซต์ / บทเรียนออนไลน์ (Google Sites, LMS)',
    'other'     => 'สื่อและนวัตกรรมการเรียนรู้อื่นๆ',
];
$typeName = $mediaTypesThai[$media['media_type']] ?? 'สื่อการเรียนรู้ออนไลน์';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกข้อความรายงานการใช้สื่อ - <?= htmlspecialchars($media['media_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
            font-size: 15pt;
            line-height: 1.55;
            color: #000;
            background: #e2e8f0;
            margin: 0;
            padding: 60px 0 40px 0;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 22mm 20mm 20mm 25mm;
            margin: 0 auto 25px auto;
            background: #fff;
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
            position: relative;
        }
        .header-krut {
            text-align: left;
            position: relative;
            margin-bottom: 12px;
        }
        .krut-img {
            width: 60px;
            height: auto;
        }
        .memo-title {
            text-align: center;
            font-size: 24pt;
            font-weight: bold;
            margin-top: -38px;
            margin-bottom: 20px;
        }
        .memo-meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .memo-meta td {
            padding: 4px 0;
            vertical-align: top;
        }
        .meta-label {
            font-weight: bold;
            font-size: 16pt;
        }
        p {
            margin: 0 0 10px 0;
            text-align: justify;
        }
        .indent {
            text-indent: 2.5cm;
        }
        .info-box {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px 16px;
            margin: 14px 0 16px;
            background: #f8fafc;
        }
        .memo-page-number {
            text-align: center;
            font-size: 15pt;
            font-weight: bold;
            margin-bottom: 25px;
        }
        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 24px;
            page-break-inside: avoid;
        }
        .sign-box {
            text-align: center;
            line-height: 1.6;
        }
        .approval-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .approval-table td {
            border: 1px solid #94a3b8;
            padding: 12px 14px;
            vertical-align: top;
            font-size: 13.5pt;
            line-height: 1.5;
        }
        .sig-display-box {
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: -10px;
        }
        .sig-display-box img {
            max-height: 50px;
            max-width: 180px;
            object-fit: contain;
        }
        .btn-sig {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 3px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-sig-clear {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fca5a5;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .no-print-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #0f172a;
            color: #fff;
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        }

        /* ── Signature Modal ── */
        .sig-modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        .sig-modal-dialog {
            background: #fff;
            border-radius: 16px;
            width: 480px;
            max-width: 95vw;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .sig-modal-header {
            background: linear-gradient(135deg, #0284c7, #2563eb);
            color: #fff;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 800;
            font-size: 14px;
        }
        .sig-canvas-wrap {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
            display: inline-block;
            cursor: crosshair;
            margin: 10px 0;
            touch-action: none;
        }

        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .page {
                width: 100%;
                min-height: 275mm;
                box-shadow: none;
                margin: 0;
                padding: 15mm 15mm 15mm 20mm;
                page-break-after: always;
                break-after: page;
            }
            .page:last-child {
                page-break-after: auto;
                break-after: auto;
            }
            .no-print-bar, .no-print, .sig-modal-backdrop { display: none !important; }
            @page { size: A4 portrait; margin: 5mm; }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div style="font-size:14px;font-weight:bold;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-file-alt text-sky-400"></i>
        <span>บันทึกข้อความขออนุมัติและรายงานการใช้สื่อการเรียนรู้ — <span style="color:#38bdf8;">๒ หน้า (A4)</span></span>
    </div>
    <div style="display:flex;gap:10px;">
        <button onclick="window.print()" style="background:#0284c7;color:#fff;border:none;padding:6px 18px;border-radius:8px;font-weight:bold;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-print"></i> สั่งพิมพ์เอกสาร
        </button>
        <button onclick="window.close()" style="background:#475569;color:#fff;border:none;padding:6px 14px;border-radius:8px;font-weight:bold;cursor:pointer;font-size:13px;">
            ปิดหน้าต่าง
        </button>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MEMO PAGE 1: ตราครุฑ, ส่วนราชการ, ข้อความเกริ่น, และรายละเอียดสื่อ
     ══════════════════════════════════════════════════════ -->
<div class="page" id="memoPage1">
    <!-- ตราครุฑ และหัวเรื่องบันทึกข้อความ -->
    <div style="position: relative; margin-bottom: 18px; min-height: 60px;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/82/Garuda_Emblem_of_Thailand.svg/180px-Garuda_Emblem_of_Thailand.svg.png" 
             alt="ตราครุฑ" class="krut-img" style="position: absolute; left: 0; top: 0;" onerror="this.style.display='none'">
        <div style="text-align: center; font-size: 26pt; font-weight: bold; padding-top: 4px;">
            บันทึกข้อความ
        </div>
    </div>

    <table class="memo-meta">
        <tr>
            <td style="width: 58%; vertical-align: top; line-height: 1.45;">
                <span class="meta-label">ส่วนราชการ</span> โรงเรียนละลมวิทยา<br>
                สพม.ศรีสะเกษ ยโสธร
            </td>
            <td style="width: 42%; vertical-align: top; line-height: 1.45;">
                <span class="meta-label">กลุ่มสาระฯ</span> <?= htmlspecialchars($media['subject']) ?>
            </td>
        </tr>
        <tr>
            <td style="vertical-align: top; padding-top: 6px;">
                <span class="meta-label">ที่</span> ศธ ....................................
            </td>
            <td style="vertical-align: top; padding-top: 6px;">
                <span class="meta-label">วันที่</span> <?= thaiDateFull($media['media_date']) ?>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="vertical-align: top; padding-top: 8px;">
                <span class="meta-label">เรื่อง</span> ขอส่งแบบรายงานและสรุปผลการนำสื่อนวัตกรรมการจัดการเรียนรู้ไปใช้ในการจัดการเรียนการสอน
            </td>
        </tr>
        <tr>
            <td colspan="2" style="vertical-align: top; padding-top: 8px;">
                <span class="meta-label">เรียน</span> ผู้อำนวยการโรงเรียนละลมวิทยา
            </td>
        </tr>
    </table>

    <p class="indent">
        ด้วย ข้าพเจ้า <strong><?= htmlspecialchars($media['author']) ?></strong> ตำแหน่ง ครู กลุ่มสาระการเรียนรู้<?= htmlspecialchars($media['subject']) ?> 
        ได้รับมอบหมายให้ปฏิบัติหน้าที่การจัดการเรียนการสอนในระดับชั้น <strong><?= htmlspecialchars($media['grade']) ?></strong> 
        เพื่อเป็นการพัฒนาคุณภาพผู้เรียนตามมาตรฐานและตัวชี้วัดของหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน และส่งเสริมการจัดการเรียนรู้เชิงรุก (Active Learning)
    </p>

    <p class="indent">
        ในการนี้ ข้าพเจ้าได้ดำเนินการจัดทำ/ผลิต และนำสื่อนวัตกรรมทางการศึกษาไปใช้ในการจัดกิจกรรมการเรียนรู้ เรื่อง 
        <strong>"<?= htmlspecialchars($media['media_name']) ?>"</strong> ประเภท <strong><?= htmlspecialchars($typeName) ?></strong> 
        โดยได้เผยแพร่ผ่านระบบคลังสื่อการเรียนรู้ออนไลน์ของโรงเรียนละลมวิทยา ซึ่งมีรายละเอียดผลการดำเนินงานดังนี้
    </p>

    <div class="info-box" style="font-size:14pt;">
        <div><strong>๑. วัตถุประสงค์:</strong> <?= htmlspecialchars($media['objective'] ?: 'เพื่อใช้เป็นสื่อประกอบการจัดกิจกรรมการเรียนรู้ และพัฒนาทักษะของผู้เรียน') ?></div>
        <div><strong>๒. ผลการนำไปใช้:</strong> <?= htmlspecialchars($media['usage_results'] ?: 'ผู้เรียนให้ความสนใจ มีส่วนร่วมในกิจกรรมการเรียนรู้ และมีความเข้าใจในบทเรียนเพิ่มขึ้น') ?></div>
        <div><strong>๓. สถิติการเข้าถึงและประเมินผล:</strong> 
            ยอดเข้าชมรวม <strong><?= number_format($media['views']) ?></strong> ครั้ง, 
            คะแนนความพึงพอใจเฉลี่ย <strong><?= number_format($media['rating_avg'], 2) ?></strong> / 5.00 
            (จากการประเมิน <strong><?= number_format($media['rating_count']) ?></strong> คน)
        </div>
        <div><strong>๔. ลิงก์เข้าถึงสื่อ:</strong> <span style="font-size:11pt;color:#1d4ed8;word-break:break-all;"><?= htmlspecialchars($media['media_link']) ?></span></div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MEMO PAGE 2: คำลงท้าย (จึงเรียนมาเพื่อทราบ...), ลายมือชื่อ, และตารางความเห็น/อนุมัติ
     ══════════════════════════════════════════════════════ -->
<div class="page" id="memoPage2">
    <!-- เลขหน้าตรงกลางตามระเบียบสารบรรณ -->
    <div class="memo-page-number">- ๒ -</div>

    <!-- จึงเรียนมาเพื่อโปรดทราบ... (ปัดมาหน้าที่ 2 ตามที่ต้องการ) -->
    <p class="indent" style="margin-top: 15px;">
        จึงเรียนมาเพื่อโปรดทราบและพิจารณาอนุมัติให้ใช้เป็นหลักฐานประกอบการประเมินการปฏิบัติงานต่อไป
    </p>

    <!-- ส่วนลงนาม -->
    <div class="sign-grid">
        <div></div>
        <div class="sign-box">
            <div id="memo-sig-display" class="sig-display-box"></div>
            <div style="white-space: nowrap;">ลงชื่อ..........................................................</div>
            <div style="font-weight: bold; margin-top: 2px; white-space: nowrap;">( <?= htmlspecialchars($media['author']) ?> )</div>
            <div style="margin-top: 2px;">ตำแหน่ง ครู โรงเรียนละลมวิทยา</div>
            <div style="margin-top: 2px;">ครูผู้จัดทำและรายงาน</div>
            <div class="no-print" style="margin-top: 8px;">
                <button type="button" class="btn-sig" onclick="openMemoSigModal()">
                    <i class="fas fa-signature me-1"></i> เซ็นชื่อดิจิทัล
                </button>
                <button type="button" class="btn-sig-clear" id="memo-btn-clear" style="display:none;" onclick="clearMemoSig()">
                    <i class="fas fa-eraser me-1"></i> ลบ
                </button>
            </div>
        </div>
    </div>

    <!-- ตารางความเห็นและคำสั่งอนุมัติ -->
    <table class="approval-table">
        <tr>
            <td style="width:50%;">
                <strong>ความเห็นของหัวหน้ากลุ่มสาระการเรียนรู้</strong><br>
                ( &nbsp; ) ทราบ และเห็นควรอนุมัติ<br>
                ( &nbsp; ) อื่นๆ ................................................................<br><br>
                <div style="white-space: nowrap;">ลงชื่อ..........................................................</div>
                <div style="white-space: nowrap; margin-top: 2px;">(&nbsp;..........................................................&nbsp;)</div>
                <div style="margin-top: 2px;">วันที่ ......./......./.......</div>
            </td>
            <td style="width:50%;">
                <strong>คำสั่ง / ข้อสั่งการผู้อำนวยการโรงเรียน</strong><br>
                ( &nbsp; ) ทราบ และอนุมัติ<br>
                ( &nbsp; ) อื่นๆ ................................................................<br><br>
                <div style="white-space: nowrap;">ลงชื่อ..........................................................</div>
                <div style="white-space: nowrap; margin-top: 2px;">(&nbsp;..........................................................&nbsp;)</div>
                <div style="margin-top: 2px;">ผู้อำนวยการโรงเรียนละลมวิทยา</div>
                <div style="margin-top: 2px;">วันที่ ......./......./.......</div>
            </td>
        </tr>
    </table>
</div>

<!-- Modal เซ็นชื่อ -->
<div class="sig-modal-backdrop" id="memoSigModal">
    <div class="sig-modal-dialog">
        <div class="sig-modal-header">
            <span><i class="fas fa-pen-nib me-2"></i> วาดลายเซ็นดิจิทัล</span>
            <button type="button" onclick="closeMemoSigModal()" style="background:transparent;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
        </div>
        <div style="padding:16px 20px;text-align:center;">
            <p style="font-size:13px;color:#64748b;margin:0 0 10px 0;">ใช้เมาส์ นิ้ว หรือปากกาเซ็นชื่อ:</p>
            <div class="sig-canvas-wrap">
                <canvas id="memoCanvas" width="420" height="180"></canvas>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <button type="button" onclick="clearMemoCanvas()" style="background:#f1f5f9;border:1px solid #cbd5e1;padding:4px 12px;border-radius:6px;font-size:12px;cursor:pointer;font-weight:700;">
                    <i class="fas fa-undo me-1"></i> ล้าง
                </button>
                <div style="display:flex;gap:6px;">
                    <button type="button" onclick="closeMemoSigModal()" style="background:#e2e8f0;border:none;padding:6px 14px;border-radius:6px;font-size:12px;cursor:pointer;">ยกเลิก</button>
                    <button type="button" onclick="applyMemoSig()" style="background:#0284c7;color:#fff;border:none;padding:6px 16px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">นำไปใช้</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const mCanvas = document.getElementById('memoCanvas');
const mCtx = mCanvas.getContext('2d');
let mDrawing = false, mLastX = 0, mLastY = 0;

function initMemoCanvas() {
    mCtx.strokeStyle = '#0f2b5c';
    mCtx.lineWidth = 2.5;
    mCtx.lineCap = 'round';
    mCtx.lineJoin = 'round';

    mCanvas.addEventListener('mousedown', (e) => {
        mDrawing = true;
        const rect = mCanvas.getBoundingClientRect();
        mLastX = e.clientX - rect.left;
        mLastY = e.clientY - rect.top;
    });
    mCanvas.addEventListener('mousemove', (e) => {
        if (!mDrawing) return;
        const rect = mCanvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        mCtx.beginPath(); mCtx.moveTo(mLastX, mLastY); mCtx.lineTo(x, y); mCtx.stroke();
        mLastX = x; mLastY = y;
    });
    window.addEventListener('mouseup', () => { mDrawing = false; });

    mCanvas.addEventListener('touchstart', (e) => {
        e.preventDefault(); mDrawing = true;
        const rect = mCanvas.getBoundingClientRect();
        const touch = e.touches[0];
        mLastX = touch.clientX - rect.left; mLastY = touch.clientY - rect.top;
    }, { passive: false });
    mCanvas.addEventListener('touchmove', (e) => {
        e.preventDefault(); if (!mDrawing) return;
        const rect = mCanvas.getBoundingClientRect();
        const touch = e.touches[0];
        const x = touch.clientX - rect.left; const y = touch.clientY - rect.top;
        mCtx.beginPath(); mCtx.moveTo(mLastX, mLastY); mCtx.lineTo(x, y); mCtx.stroke();
        mLastX = x; mLastY = y;
    }, { passive: false });
    mCanvas.addEventListener('touchend', (e) => { e.preventDefault(); mDrawing = false; });
}

function clearMemoCanvas() { mCtx.clearRect(0, 0, mCanvas.width, mCanvas.height); }
function openMemoSigModal() { clearMemoCanvas(); document.getElementById('memoSigModal').style.display = 'flex'; }
function closeMemoSigModal() { document.getElementById('memoSigModal').style.display = 'none'; }

function applyMemoSig() {
    const dataUrl = mCanvas.toDataURL('image/png');
    document.getElementById('memo-sig-display').innerHTML = `<img src="${dataUrl}" alt="ลายเซ็น">`;
    document.getElementById('memo-btn-clear').style.display = 'inline-block';
    try { localStorage.setItem('llw_memo_sig_<?= $id ?>', dataUrl); } catch(e){}
    closeMemoSigModal();
}

function clearMemoSig() {
    document.getElementById('memo-sig-display').innerHTML = '';
    document.getElementById('memo-btn-clear').style.display = 'none';
    try { localStorage.removeItem('llw_memo_sig_<?= $id ?>'); } catch(e){}
}

document.addEventListener('DOMContentLoaded', () => {
    initMemoCanvas();
    const saved = localStorage.getItem('llw_memo_sig_<?= $id ?>');
    if (saved) {
        document.getElementById('memo-sig-display').innerHTML = `<img src="${saved}" alt="ลายเซ็น">`;
        document.getElementById('memo-btn-clear').style.display = 'inline-block';
    }
});
</script>

</body>
</html>
