<?php
/**
 * edocument/index.php
 * e-Document System Dashboard
 */
session_start();
require_once __DIR__ . '/../config/database.php';
if (!isset($_SESSION['llw_role'])) { header('Location: /login.php'); exit; }

$pdo = getPdo();
$stats = [
    'incoming' => $pdo->query("SELECT COUNT(*) FROM edoc_incoming_documents")->fetchColumn(),
    'outgoing' => $pdo->query("SELECT COUNT(*) FROM edoc_outgoing_documents")->fetchColumn(),
    'orders'   => $pdo->query("SELECT COUNT(*) FROM edoc_orders")->fetchColumn(),
    'memos'    => $pdo->query("SELECT COUNT(*) FROM edoc_memos")->fetchColumn(),
];

$pageTitle = 'แดชบอร์ดระบบ e-สารบรรณ';
$activeSystem = 'edoc';
require_once __DIR__ . '/../components/layout_start.php';
?>

<div class="row g-4 mb-4">
    <!-- Stats Cards -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 text-white p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-80">หนังสือรับ</div>
                    <div class="text-4xl font-black mt-2"><?= $stats['incoming'] ?></div>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-2xl d-flex align-items-center justify-center text-xl">
                    <i class="bi bi-box-arrow-in-right"></i>
                </div>
            </div>
            <a href="incoming.php" class="mt-3 text-white text-xs font-bold text-decoration-none d-flex align-items-center">
                จัดการข้อมูล <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 text-white p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-80">หนังสือส่ง</div>
                    <div class="text-4xl font-black mt-2"><?= $stats['outgoing'] ?></div>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-2xl d-flex align-items-center justify-center text-xl">
                    <i class="bi bi-send"></i>
                </div>
            </div>
            <a href="outgoing.php" class="mt-3 text-white text-xs font-bold text-decoration-none d-flex align-items-center">
                จัดการข้อมูล <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-2xl bg-gradient-to-br from-rose-500 to-rose-600 text-white p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-80">คำสั่ง</div>
                    <div class="text-4xl font-black mt-2"><?= $stats['orders'] ?></div>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-2xl d-flex align-items-center justify-center text-xl">
                    <i class="bi bi-file-earmark-check"></i>
                </div>
            </div>
            <a href="orders.php" class="mt-3 text-white text-xs font-bold text-decoration-none d-flex align-items-center">
                จัดการข้อมูล <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-80">บันทึกข้อความ</div>
                    <div class="text-4xl font-black mt-2"><?= $stats['memos'] ?></div>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-2xl d-flex align-items-center justify-center text-xl">
                    <i class="bi bi-sticky"></i>
                </div>
            </div>
            <a href="memos.php" class="mt-3 text-white text-xs font-bold text-decoration-none d-flex align-items-center">
                จัดการข้อมูล <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm rounded-2xl p-6 bg-white">
            <h5 class="font-black text-slate-800 mb-4">ยินดีต้อนรับเข้าสู่ระบบ e-สารบรรณ</h5>
            <p class="text-slate-500 text-sm">ระบบจัดการเอกสารราชการอิเล็กทรอนิกส์ โรงเรียนละลมวิทยา คุณสามารถจัดการหนังสือรับ-ส่ง คำสั่ง และบันทึกข้อความได้จากเมนูด้านซ้าย</p>
            <div class="d-flex gap-2 mt-4">
                <a href="incoming.php" class="btn btn-primary rounded-pill px-4 font-bold shadow-sm">เริ่มใช้งาน</a>
                <button class="btn btn-light rounded-pill px-4 font-bold">คู่มือการใช้งาน</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
