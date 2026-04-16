<?php
session_start();
require_once __DIR__ . '/../config.php';

// Auth guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit();
}

$pdo = getPdo();
$groupId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

// If no ID provided, show list of groups instead
if (!$groupId) {
    $stmt = $pdo->prepare("
        SELECT g.*, m.role as my_role
        FROM plc_groups g
        JOIN plc_members m ON g.id = m.group_id
        WHERE m.user_id = ?
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([$userId]);
    $myGroups = $stmt->fetchAll();

    $pageTitle = 'รายงานสรุป PLC';
    $pageSubtitle = 'กรุณาเลือกกลุ่มที่ต้องการพิมพ์รายงาน';
    $activeSystem = 'plc';
    require_once __DIR__ . '/../components/layout_start.php';
    ?>
    <div class="space-y-8 animate-in fade-in duration-700">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <div class="w-16 h-16 bg-violet-100 text-violet-600 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-6 shadow-xl shadow-violet-100">
                <i class="bi bi-file-earmark-bar-graph"></i>
            </div>
            <h2 class="text-3xl font-black text-slate-800 tracking-tight">พิมพ์รายงานสรุป</h2>
            <p class="text-slate-400 mt-2 font-bold uppercase tracking-widest text-[10px]">โปรดเลือกหนึ่งในระบบกลุ่ม PLC ของคุณเพื่อดำเนินการต่อ</p>
        </div>

        <?php if (empty($myGroups)): ?>
        <div class="bg-white rounded-[2.5rem] p-16 text-center border-2 border-dashed border-slate-100 max-w-xl mx-auto">
            <p class="text-slate-400 text-sm font-bold">คุณยังไม่มีกลุ่ม PLC สำหรับออกรายงาน</p>
            <a href="dashboard.php" class="mt-6 inline-flex items-center gap-2 bg-violet-600 text-white px-8 py-3 rounded-2xl font-bold hover:bg-violet-700 transition-all">
                กลับไปยัง Dashboard
            </a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($myGroups as $group): ?>
            <a href="?id=<?= $group['id'] ?>" target="_blank" class="group bg-white rounded-[2rem] p-8 shadow-xl shadow-slate-100/50 border border-slate-100 hover:border-violet-500 hover:scale-[1.02] transition-all">
                <div class="flex items-center justify-between mb-6">
                    <div class="w-12 h-12 bg-violet-50 text-violet-600 rounded-2xl flex items-center justify-center text-xl group-hover:rotate-12 transition-transform">
                        <i class="bi bi-printer"></i>
                    </div>
                </div>
                <h4 class="text-lg font-black text-slate-800"><?= htmlspecialchars($group['group_name']) ?></h4>
                <p class="text-slate-400 text-xs mt-1 uppercase font-bold tracking-widest italic">
                    ปีการศึกษา <?= htmlspecialchars($group['academic_year']) ?> / <?= htmlspecialchars($group['semester']) ?>
                </p>
                <div class="mt-6 flex items-center gap-2 text-violet-600 font-black text-[10px] uppercase tracking-widest">
                    <span>เปิดแบบพิมพ์</span>
                    <i class="bi bi-arrow-right"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
    require_once __DIR__ . '/../components/layout_end.php';
    exit();
}

// Fetch group details if ID exists
$stmt = $pdo->prepare("SELECT * FROM plc_groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

if (!$group) {
    die("ไม่พบข้อมูลกลุ่ม");
}

// Fetch members
$stmt = $pdo->prepare("
    SELECT m.*, u.firstname, u.lastname
    FROM plc_members m
    JOIN llw_users u ON m.user_id = u.user_id
    WHERE m.group_id = ?
");
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();

// Fetch logs
$stmt = $pdo->prepare("
    SELECT l.*, u.firstname, u.lastname
    FROM plc_logs l
    JOIN llw_users u ON l.user_id = u.user_id
    WHERE l.group_id = ?
    ORDER BY l.log_date ASC, l.created_at ASC
");
$stmt->execute([$groupId]);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>PLC_Report_<?= $groupId ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 40px; }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #000; padding-bottom: 20px; }
        .logo { width: 80px; height: 80px; margin-bottom: 10px; }
        h1 { margin: 0; font-size: 24px; }
        h2 { margin: 5px 0; font-size: 18px; }
        
        .section { margin-bottom: 30px; }
        .section-title { font-weight: bold; font-size: 18px; border-left: 5px solid #7c3aed; padding-left: 10px; margin-bottom: 15px; color: #5b21b6; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; font-size: 14px; }
        th { background-color: #f8fafc; font-weight: bold; }
        
        .pdca-step { margin-bottom: 40px; page-break-inside: avoid; }
        .pdca-badge { display: inline-block; padding: 2px 10px; border-radius: 4px; color: white; font-weight: bold; font-size: 12px; margin-bottom: 10px; }
        .bg-plan { background-color: #3b82f6; }
        .bg-do { background-color: #10b981; }
        .bg-check { background-color: #f59e0b; }
        .bg-act { background-color: #f43f5e; }
        
        .log-card { border: 1px solid #eee; padding: 20px; border-radius: 10px; margin-bottom: 15px; }
        .log-meta { font-size: 12px; color: #666; margin-bottom: 10px; }
        .log-content { white-space: pre-line; font-size: 14px; }
        .reflection-box { margin-top: 15px; padding: 15px; background: #fdf4ff; border-radius: 8px; font-style: italic; color: #701a75; }

        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .page-break { page-break-after: always; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #7c3aed; color: white; border: none; border-radius: 8px; cursor: pointer; font-family: 'Prompt'; font-weight: bold;">พิมพ์รายงาน</button>
    </div>

    <div class="header">
        <h1>รายงานสรุปกิจกรรมชุมชนแห่งการเรียนรู้ทางวิชาชีพ (PLC)</h1>
        <h2>โรงเรียนละลมวิทยา</h2>
        <p>ปีการศึกษา <?= htmlspecialchars($group['academic_year']) ?> ภาคเรียนที่ <?= htmlspecialchars($group['semester']) ?></p>
    </div>

    <div class="section">
        <div class="section-title">ข้อมูลพื้นฐานของกลุ่ม</div>
        <table>
            <tr>
                <th width="30%">ชื่อกลุ่ม</th>
                <td><?= htmlspecialchars($group['group_name']) ?></td>
            </tr>
            <tr>
                <th>กลุ่มเป้าหมาย/รายวิชา</th>
                <td><?= htmlspecialchars($group['target_group']) ?: '-' ?></td>
            </tr>
            <tr>
                <th>สถานะกลุ่ม</th>
                <td><?= htmlspecialchars($group['status']) ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">สมาชิกในกลุ่ม</div>
        <table>
            <thead>
                <tr>
                    <th>ชื่อ-นามสกุล</th>
                    <th>บทบาทในกลุ่ม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['firstname']) ?> <?= htmlspecialchars($m['lastname']) ?></td>
                    <td><?= ucwords(str_replace('_', ' ', $m['role'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <div class="section-title">บันทึกกิจกรรมตามกระบวนการ PDCA</div>
        
        <?php 
        $phases = ['Plan', 'Do', 'Check', 'Act'];
        foreach ($phases as $phase):
            $phaseLogs = array_filter($logs, function($l) use ($phase) { return $l['phase'] === $phase; });
        ?>
        <div class="pdca-step">
            <div class="pdca-badge bg-<?= strtolower($phase) ?>"><?= strtoupper($phase) ?> Phase</div>
            
            <?php if (empty($phaseLogs)): ?>
                <p style="color: #999; font-style: italic; font-size: 14px;">--- ไม่มีการบันทึกกิจกรรมในขั้นตอนนี้ ---</p>
            <?php else: ?>
                <?php foreach ($phaseLogs as $log): ?>
                <div class="log-card">
                    <div class="log-meta">
                        บันทึกเมื่อ: <?= date('d/m/Y', strtotime($log['log_date'])) ?> | 
                        โดย: <?= htmlspecialchars($log['firstname']) ?> <?= htmlspecialchars($log['lastname']) ?>
                    </div>
                    <div style="font-weight: bold; font-size: 16px; margin-bottom: 10px;"><?= htmlspecialchars($log['topic']) ?></div>
                    <div class="log-content"><?= htmlspecialchars($log['details']) ?></div>
                    
                    <?php if ($log['reflection']): ?>
                    <div class="reflection-box">
                        <strong>การสะท้อนผล:</strong><br>
                        <?= htmlspecialchars($log['reflection']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top: 80px;">
        <table style="border: none;">
            <tr>
                <td style="border: none; text-align: center; width: 50%;">
                    ลงชื่อ..........................................................<br>
                    (..........................................................)<br>
                    ครูต้นแบบ (Model Teacher)
                </td>
                <td style="border: none; text-align: center; width: 50%;">
                    ลงชื่อ..........................................................<br>
                    (..........................................................)<br>
                    ครูพี่เลี้ยง (Mentor) / ผู้เชี่ยวชาญ
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
