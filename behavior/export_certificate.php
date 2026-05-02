<?php
/**
 * behavior/export_certificate.php — Elegant Behavior Achievement Certificate
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['llw_role'])) { 
    echo "Unauthorized"; exit; 
}

$sid = $_GET['student_id'] ?? '';
if (!$sid) { echo "Missing student id"; exit; }

$pdo = getPdo();

// 1. Fetch Student Info
$stmt = $pdo->prepare("SELECT * FROM beh_students WHERE student_id = ? LIMIT 1");
$stmt->execute([$sid]);
$student = $stmt->fetch();

if (!$student) { echo "Student not found"; exit; }

// 2. Calculate Stats
$stmtS = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'ความดี' THEN score ELSE 0 END) as good,
        SUM(CASE WHEN type = 'ความผิด' THEN score ELSE 0 END) as bad
    FROM beh_records WHERE student_id = ?
");
$stmtS->execute([$sid]);
$scores = $stmtS->fetch();

$netScore = 100 + ($scores['good'] ?: 0) - ($scores['bad'] ?: 0);

// Only allow certificate if score > 150 (Achievement threshold)
if ($netScore < 150) {
    // echo "คะแนนยังไม่ถึงเกณฑ์รับเกียรติบัตร (ต้องมีคะแนนสะสม 150 คะแนนขึ้นไป)";
    // exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เกียรติบัตร - <?= htmlspecialchars($student['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; margin: 0; padding: 0; }
            .cert-container { border: 20px solid #e2e8f0 !important; box-shadow: none !important; }
        }
        body { font-family: 'Prompt', sans-serif; }
        .cert-container {
            width: 1122px; /* A4 Landscape Width */
            height: 794px; /* A4 Landscape Height */
            margin: 20px auto;
            position: relative;
            background: white;
            border: 30px solid #f8fafc;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .cert-border {
            position: absolute;
            inset: 20px;
            border: 4px solid #f1f5f9;
            pointer-events: none;
        }
        .cert-decoration {
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, #6366f1 0%, transparent 70%);
            opacity: 0.1;
            filter: blur(50px);
        }
        .cert-top-left { top: -100px; left: -100px; }
        .cert-bottom-right { bottom: -100px; right: -100px; }
    </style>
</head>
<body class="bg-slate-100 flex flex-col items-center py-10">

    <div class="no-print mb-8">
        <button onclick="window.print()" class="bg-indigo-600 text-white px-10 py-4 rounded-2xl font-black text-sm uppercase tracking-widest shadow-xl shadow-indigo-200 hover:scale-[1.02] transition-all">
            <i class="bi bi-printer"></i> Print Certificate (A4 Landscape)
        </button>
    </div>

    <div class="cert-container relative p-20 flex flex-col items-center">
        <div class="cert-border"></div>
        <div class="cert-decoration cert-top-left"></div>
        <div class="cert-decoration cert-bottom-right"></div>

        <div class="relative z-10 flex flex-col items-center text-center w-full">
            <!-- School Logo/Icon Placeholder -->
            <div class="w-24 h-24 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-3xl flex items-center justify-center text-white text-5xl shadow-xl mb-10">
                ⭐
            </div>

            <h1 class="text-5xl font-black text-slate-800 tracking-tighter uppercase mb-2">Certificate of Achievement</h1>
            <p class="text-lg font-bold text-indigo-500 uppercase tracking-[0.3em]">เกียรติบัตรประกาศเกียรติคุณ</p>

            <div class="w-1/2 h-0.5 bg-slate-100 my-10"></div>

            <p class="text-xl text-slate-500 font-medium mb-6">ขอมอบเกียรติบัตรฉบับนี้เพื่อแสดงว่า</p>
            
            <h2 class="text-6xl font-black text-slate-900 mb-6"><?= htmlspecialchars($student['name']) ?></h2>
            
            <p class="text-xl text-slate-600 font-bold mb-10">นักเรียนระดับชั้น <?= htmlspecialchars($student['level']) ?>/<?= htmlspecialchars($student['room']) ?></p>

            <div class="max-w-2xl text-center">
                <p class="text-lg text-slate-500 leading-relaxed font-medium">
                    เป็นผู้ที่มีผลการประเมินพฤติกรรมยอดเยี่ยม และได้รับการบันทึกคะแนนความดีในทางสร้างสรรค์
                    ประจำปีการศึกษา <?= date('Y') + 543 ?> 
                    ด้วยคะแนนพฤติกรรมสะสมสุทธิ <span class="text-indigo-600 font-black text-2xl"><?= $netScore ?></span> คะแนน
                </p>
                <p class="text-slate-400 mt-4 font-bold uppercase tracking-widest text-xs italic">
                    Exemplary Student Behavior for Academic Excellence
                </p>
            </div>

            <div class="mt-20 w-full flex justify-between items-end px-10">
                <div class="text-center w-64">
                    <div class="h-0.5 w-full bg-slate-200 mb-3"></div>
                    <p class="text-sm font-black text-slate-800 tracking-wider">ฝ่ายกิจการนักเรียน</p>
                    <p class="text-xs font-bold text-slate-400 uppercase mt-1">Student Affairs Division</p>
                </div>

                <div class="flex flex-col items-center">
                    <div class="w-20 h-20 border-4 border-indigo-50 rounded-full flex items-center justify-center text-indigo-200 text-3xl mb-4">
                        ⚖️
                    </div>
                </div>

                <div class="text-center w-64">
                    <div class="h-0.5 w-full bg-slate-200 mb-3"></div>
                    <p class="text-sm font-black text-slate-800 tracking-wider">โรงเรียนละลมวิทยา</p>
                    <p class="text-xs font-bold text-slate-400 uppercase mt-1">Lalom Wittaya School</p>
                </div>
            </div>
            
            <p class="mt-12 text-xs text-slate-300 font-bold uppercase tracking-[0.5em]">Lalom Wittaya School Management System - Behavioral Records Module</p>
        </div>
    </div>

    <!-- Bootstrap Icons (for icon use if needed) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>
