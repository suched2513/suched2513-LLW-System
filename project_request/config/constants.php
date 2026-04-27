<?php
/**
 * System Constants
 */

define('SCHOOL_NAME', 'โรงเรียนละลมวิทยา');
define('FISCAL_YEAR', '2569');
define('APP_NAME', 'ระบบขอดำเนินโครงการ');
define('BASE_URL', '/project_request'); // Adjust based on your folder structure

// Request Status Labels
define('STATUS_LABELS', [
    'draft'     => 'ฉบับร่าง',
    'submitted' => 'รออนุมัติ',
    'approved'  => 'อนุมัติแล้ว',
    'rejected'  => 'ไม่อนุมัติ'
]);

// Request Status Colors (Tailwind Classes)
define('STATUS_COLORS', [
    'draft'     => 'bg-slate-100 text-slate-600',
    'submitted' => 'bg-amber-50 text-amber-600',
    'approved'  => 'bg-emerald-50 text-emerald-600',
    'rejected'  => 'bg-rose-50 text-rose-600'
]);

// Budget Types
define('BUDGET_TYPES', [
    'subsidy'   => 'งบเงินอุดหนุน',
    'quality'   => 'งบพัฒนาคุณภาพผู้เรียน',
    'revenue'   => 'เงินรายได้สถานศึกษา',
    'operation' => 'งบงานประจำ',
    'reserve'   => 'เงินสำรองจ่าย'
]);
