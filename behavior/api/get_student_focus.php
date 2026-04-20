<?php
/**
 * API: Get student focus data (info + scores + history)
 * GET ?sid=xxxxx
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    // Allow public access for student_view (check referer or mode param)
    $mode = $_GET['mode'] ?? 'teacher';
    if ($mode !== 'student') {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit;
    }
}

$sid = trim($_GET['sid'] ?? '');
if ($sid === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุรหัสนักเรียน']);
    exit;
}

// Normalize: pad to 5 digits
if (preg_match('/^\d+$/', $sid)) {
    $sid = str_pad($sid, 5, '0', STR_PAD_LEFT);
}

try {
    $pdo = getPdo();

    // Get student info from Master (att_students) with Meta (beh_students)
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.name, s.classroom, b.homeroom, b.img_url
        FROM att_students s
        LEFT JOIN beh_students b ON s.student_id = b.student_id
        WHERE s.student_id = ?
    ");
    $stmt->execute([$sid]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode(['status' => 'error', 'st' => null, 'message' => 'ไม่พบรหัสนักเรียนนี้ในฐานข้อมูลกลาง']);
        exit;
    }

    $st = [
        'studentId' => $student['student_id'],
        'name'      => $student['name'],
        'classText' => $student['classroom'],
        'homeroom'  => $student['homeroom'] ?? '',
        'img'       => $student['img_url'] ?? '',
    ];

    // Calculate scores
    $stmtGood = $pdo->prepare("SELECT COALESCE(SUM(score), 0) AS total FROM beh_records WHERE student_id = ? AND type = 'ความดี'");
    $stmtGood->execute([$sid]);
    $goodScore = (int)$stmtGood->fetchColumn();

    $stmtBad = $pdo->prepare("SELECT COALESCE(SUM(score), 0) AS total FROM beh_records WHERE student_id = ? AND type = 'ความผิด'");
    $stmtBad->execute([$sid]);
    $badScore = (int)$stmtBad->fetchColumn();

    $scores = [
        'good' => $goodScore,
        'bad'  => $badScore,
    ];

    // Build history HTML
    $stmtHist = $pdo->prepare("
        SELECT * FROM beh_records 
        WHERE student_id = ? 
        ORDER BY record_date DESC, created_at DESC 
        LIMIT 100
    ");
    $stmtHist->execute([$sid]);
    $records = $stmtHist->fetchAll();

    $html = '';
    if (empty($records)) {
        $html = '<div class="text-center py-12 text-slate-400 opacity-50">
            <i class="bi bi-inbox text-5xl block mb-3"></i>
            <p>ยังไม่มีการบันทึก</p>
        </div>';
    } else {
        foreach ($records as $r) {
            $isGood = $r['type'] === 'ความดี';
            $dateFormatted = date('d/m/', strtotime($r['record_date'])) . (date('Y', strtotime($r['record_date'])) + 543);
            $colorClass = $isGood ? 'emerald' : 'rose';
            $icon = $isGood ? 'bi-emoji-smile-fill' : 'bi-emoji-frown-fill';
            $sign = $isGood ? '+' : '-';
            $escapedActivity = htmlspecialchars($r['activity'] ?? '', ENT_QUOTES, 'UTF-8');
            $escapedTeacher = htmlspecialchars($r['teacher_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $recordId = (int)$r['id'];

            $imgTag = '';
            if (!empty($r['image_path'])) {
                $imgUrl = htmlspecialchars($r['image_path'], ENT_QUOTES, 'UTF-8');
                $imgTag = "<div class='mt-2'><img src='{$imgUrl}' class='rounded-xl max-h-20 border border-slate-200' alt='evidence' onclick=\"window.open('{$imgUrl}', '_blank')\" class='cursor-pointer'></div>";
            }

            $statusBadge = '';
            if ($r['status'] === 'pending') {
                $statusBadge = '<span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 text-[8px] font-black uppercase tracking-tighter border border-amber-100 ml-2">รอยืนยัน</span>';
            } elseif ($r['status'] === 'rejected') {
                $statusBadge = '<span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-600 text-[8px] font-black uppercase tracking-tighter border border-rose-100 ml-2">ปฏิเสธ</span>';
            }

            $html .= "
            <div class='bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-3 hover:shadow-md transition-all'>
                <div class='flex items-start justify-between gap-3'>
                    <div class='flex items-start gap-3'>
                        <div class='w-10 h-10 bg-{$colorClass}-50 rounded-xl flex items-center justify-center flex-shrink-0'>
                            <i class='bi {$icon} text-{$colorClass}-500 text-lg'></i>
                        </div>
                        <div>
                            <div class='text-xs text-slate-400 mb-1'>{$dateFormatted} · {$escapedTeacher} {$statusBadge}</div>
                            <p class='text-sm font-bold text-slate-700'>{$escapedActivity}</p>
                            {$imgTag}
                        </div>
                    </div>
                    <div class='text-right flex-shrink-0'>
                        <span class='text-lg font-black text-{$colorClass}-600'>{$sign}{$r['score']}</span>
                        <br>
                        <button onclick=\"deleteActivity({$recordId})\" class='text-xs text-rose-400 hover:text-rose-600 transition-colors mt-1'>
                            <i class='bi bi-trash'></i> ลบ
                        </button>
                    </div>
                </div>
            </div>";
        }
    }

    echo json_encode([
        'status' => 'success',
        'st'     => $st,
        'scores' => $scores,
        'html'   => $html,
    ]);

} catch (Exception $e) {
    error_log('[behavior] get_student_focus error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
}
