<?php
/**
 * components/back_button.php — Shared "back" button for all modules (LMS first)
 *
 * Usage (after layout_start.php):
 *   <?= lms_back_button('/lms/dashboard.php', 'กลับหน้า LMS') ?>
 *   <?= lms_back_button('/lms_system/student_quizzes.php', 'กลับ', 'ออกจากข้อสอบ? คำตอบที่ยังไม่ส่งจะหายไป') ?>
 *
 * - $parentUrl : fixed parent page (hierarchical back, never loops after form POST)
 * - $confirm   : optional message; asks via SweetAlert2 before leaving
 */
if (!function_exists('lms_back_button')) {
    function lms_back_button(string $parentUrl, string $label = 'ย้อนกลับ', ?string $confirm = null): string
    {
        // Only allow same-site relative URLs (must start with a single "/")
        if ($parentUrl === '' || $parentUrl[0] !== '/' || strpos($parentUrl, '//') === 0) {
            $parentUrl = '/index.php';
        }
        $href      = htmlspecialchars($parentUrl, ENT_QUOTES, 'UTF-8');
        $labelHtml = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $classes   = 'inline-flex items-center gap-2 px-4 py-2.5 min-h-[44px] rounded-2xl bg-white border border-slate-200 '
                   . 'text-slate-600 text-sm font-bold shadow-sm hover:bg-slate-50 hover:text-slate-800 hover:shadow-md '
                   . 'transition-all no-print';

        $onclick = '';
        if ($confirm !== null && $confirm !== '') {
            $msg = htmlspecialchars(json_encode($confirm, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars(json_encode($parentUrl, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
            $onclick = ' onclick="event.preventDefault();Swal.fire({title:' . $msg . ',icon:\'warning\',showCancelButton:true,'
                     . 'confirmButtonColor:\'#2563eb\',confirmButtonText:\'ออกจากหน้านี้\',cancelButtonText:\'อยู่ต่อ\'})'
                     . '.then(function(r){if(r.isConfirmed){window.onbeforeunload=null;window.location.href=' . $url . ';}});"';
        }

        return '<div class="mb-4"><a href="' . $href . '" class="' . $classes . '"' . $onclick . '>'
             . '<i class="bi bi-arrow-left"></i> ' . $labelHtml . '</a></div>';
    }
}
