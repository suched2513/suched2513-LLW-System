<?php
/**
 * config/academic.php — ศูนย์กลางการจัดการปีการศึกษาและภาคเรียน (Enterprise Academic Calendar Engine)
 *
 * รองรับทั้ง:
 *   1. การคำนวณอัตโนมัติตามช่วงวันที่
 *      - ภาคเรียนที่ 1 : 16 พ.ค. – 31 ต.ค.
 *      - ภาคเรียนที่ 2 : 1 พ.ย. – 31 มี.ค. ของปีถัดไป (และช่วง เม.ย.–15 พ.ค. ก่อนเปิดเทอมใหม่)
 *   2. การกำหนดวันเปิด–ปิดเทอม หรือบังคับสลับเทอมผ่านตาราง `llw_academic_terms` โดย Admin
 */

if (!defined('LLW_FORCE_ACADEMIC_YEAR')) define('LLW_FORCE_ACADEMIC_YEAR', null); // เช่น 2569
if (!defined('LLW_FORCE_SEMESTER'))      define('LLW_FORCE_SEMESTER', null);      // 1 หรือ 2

// วันเริ่มภาคเรียนมาตรฐาน (เดือน, วัน)
if (!defined('LLW_SEM1_START')) define('LLW_SEM1_START', [5, 16]);
if (!defined('LLW_SEM1_END'))   define('LLW_SEM1_END',   [10, 31]);
if (!defined('LLW_SEM2_START')) define('LLW_SEM2_START', [11, 1]);
if (!defined('LLW_SEM2_END'))   define('LLW_SEM2_END',   [3, 31]);

if (!function_exists('llw_ensure_semester_schema')) {
    /**
     * ตรวจสอบและอัปเกรดโครงสร้างตารางอัตโนมัติ (ปลอดภัย รันครั้งเดียวต่อ request)
     */
    function llw_ensure_semester_schema(?PDO $pdo = null): bool {
        static $checked = false;
        static $ready   = false;
        if ($checked) return $ready;
        $checked = true;

        try {
            if (!$pdo && function_exists('getPdo')) {
                $pdo = getPdo();
            }
            if (!$pdo) return false;

            $hasTable = (bool)$pdo->query("SHOW TABLES LIKE 'llw_academic_terms'")->fetchColumn();
            $hasCol   = (bool)$pdo->query("SHOW COLUMNS FROM `att_subjects` LIKE 'semester'")->fetchColumn();

            if (!$hasTable || !$hasCol) {
                $migFile = __DIR__ . '/../database/migrations/2026_10_20_233000_add_semester_support.php';
                if (file_exists($migFile)) {
                    $mig = require $migFile;
                    if (is_array($mig) && isset($mig['up'])) {
                        $mig['up']($pdo);
                    }
                }
            }
            $ready = true;
        } catch (\Throwable $e) {
            error_log('[LLW Academic] Schema check skipped: ' . $e->getMessage());
            $ready = false;
        }
        return $ready;
    }
}

if (!function_exists('llw_default_term_dates')) {
    /**
     * คืนค่าช่วงวันเริ่มต้น-สิ้นสุดมาตรฐานของปีการศึกษา (พ.ศ.) และภาคเรียน (1 หรือ 2)
     */
    function llw_default_term_dates(int $academicYear, int $semester): array {
        $ceYear = $academicYear - 543;
        if ($semester === 2) {
            return [
                'academic_year' => $academicYear,
                'semester'      => 2,
                'start_date'    => sprintf('%04d-%02d-%02d', $ceYear, LLW_SEM2_START[0], LLW_SEM2_START[1]),
                'end_date'      => sprintf('%04d-%02d-%02d', $ceYear + 1, LLW_SEM2_END[0], LLW_SEM2_END[1]),
            ];
        }
        return [
            'academic_year' => $academicYear,
            'semester'      => 1,
            'start_date'    => sprintf('%04d-%02d-%02d', $ceYear, LLW_SEM1_START[0], LLW_SEM1_START[1]),
            'end_date'      => sprintf('%04d-%02d-%02d', $ceYear, LLW_SEM1_END[0], LLW_SEM1_END[1]),
        ];
    }
}

if (!function_exists('llw_get_term_dates')) {
    /**
     * ดึงช่วงวันเปิด-ปิดภาคเรียนจากตาราง llw_academic_terms (ถ้าไม่มีใช้ค่ามาตรฐาน)
     */
    function llw_get_term_dates(?int $academicYear = null, ?int $semester = null, ?PDO $pdo = null): array {
        $academicYear = $academicYear ?: llw_current_academic_year();
        $semester     = in_array($semester, [1, 2], true) ? $semester : llw_current_semester();
        $defaults     = llw_default_term_dates($academicYear, $semester);

        try {
            if (!$pdo && function_exists('getPdo')) {
                $pdo = getPdo();
            }
            if ($pdo && llw_ensure_semester_schema($pdo)) {
                $stmt = $pdo->prepare("SELECT start_date, end_date, is_active FROM llw_academic_terms WHERE academic_year = ? AND semester = ? LIMIT 1");
                $stmt->execute([$academicYear, $semester]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $defaults['start_date'] = $row['start_date'];
                    $defaults['end_date']   = $row['end_date'];
                    $defaults['is_active']  = (int)$row['is_active'];
                }
            }
        } catch (\Throwable $e) {
            // ใช้ค่าเริ่มต้นเมื่อ DB ยังไม่พร้อม
        }

        $defaults['label'] = "ภาคเรียนที่ {$defaults['semester']}/{$defaults['academic_year']}";
        return $defaults;
    }
}

if (!function_exists('llw_active_term_from_db')) {
    /**
     * ตรวจสอบว่ามีเทอมที่ Admin กดบังคับเปิดใช้งาน (is_active=1) หรือตรงกับช่วงวันที่ใน DB หรือไม่
     */
    function llw_active_term_from_db(): ?array {
        static $cached = false;
        static $result = null;
        if ($cached) return $result;
        $cached = true;

        try {
            if (!function_exists('getPdo')) return null;
            $pdo = getPdo();
            if (!llw_ensure_semester_schema($pdo)) return null;

            // 1. ถ้ามีเทอมที่ Admin ล็อกให้เป็น Active (is_active = 1)
            $row = $pdo->query("SELECT academic_year, semester, start_date, end_date FROM llw_academic_terms WHERE is_active = 1 ORDER BY academic_year DESC, semester DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $result = [
                    'academic_year' => (int)$row['academic_year'],
                    'semester'      => (int)$row['semester'],
                    'start_date'    => $row['start_date'],
                    'end_date'      => $row['end_date'],
                    'forced'        => true,
                ];
                return $result;
            }

            // 2. ถ้าไม่ได้ล็อก ให้ดูว่าวันนี้ตกอยู่ในช่วง start_date..end_date ของเทอมไหนใน DB
            $today = date('Y-m-d');
            $stmt  = $pdo->prepare("SELECT academic_year, semester, start_date, end_date FROM llw_academic_terms WHERE ? BETWEEN start_date AND end_date ORDER BY academic_year DESC, semester DESC LIMIT 1");
            $stmt->execute([$today]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $result = [
                    'academic_year' => (int)$row['academic_year'],
                    'semester'      => (int)$row['semester'],
                    'start_date'    => $row['start_date'],
                    'end_date'      => $row['end_date'],
                    'forced'        => false,
                ];
                return $result;
            }
        } catch (\Throwable $e) {
            // fallback to date logic
        }
        return null;
    }
}

if (!function_exists('llw_current_semester')) {
    function llw_current_semester(?int $ts = null): int {
        if (LLW_FORCE_SEMESTER !== null) return (int)LLW_FORCE_SEMESTER;
        if ($ts === null) {
            $dbTerm = llw_active_term_from_db();
            if ($dbTerm) return $dbTerm['semester'];
        }
        $ts = $ts ?? time();
        $md = (int)date('n', $ts) * 100 + (int)date('j', $ts);
        $s1 = LLW_SEM1_START[0] * 100 + LLW_SEM1_START[1];
        $s2 = LLW_SEM2_START[0] * 100 + LLW_SEM2_START[1];
        return ($md >= $s1 && $md < $s2) ? 1 : 2;
    }
}

if (!function_exists('llw_current_academic_year')) {
    function llw_current_academic_year(?int $ts = null): int {
        if (LLW_FORCE_ACADEMIC_YEAR !== null) return (int)LLW_FORCE_ACADEMIC_YEAR;
        if ($ts === null) {
            $dbTerm = llw_active_term_from_db();
            if ($dbTerm) return $dbTerm['academic_year'];
        }
        $ts = $ts ?? time();
        $md = (int)date('n', $ts) * 100 + (int)date('j', $ts);
        $s1 = LLW_SEM1_START[0] * 100 + LLW_SEM1_START[1];
        $y  = (int)date('Y', $ts) + 543;
        return $md >= $s1 ? $y : $y - 1; // ม.ค.–15 พ.ค. ยังเป็นปีการศึกษาก่อนหน้า
    }
}

if (!function_exists('llw_resolve_month_range_dates')) {
    /**
     * แปลงตัวกรองเดือน (รวมถึงช่วงเดือนข้ามปี เช่น พ.ย. 11 ถึง มี.ค. 03 ของเทอม 2)
     * ให้เป็นช่วงวันที่จริง (start_date, end_date) เพื่อป้องกันบั๊ก DATE_FORMAT('%m') BETWEEN '11' AND '03'
     */
    function llw_resolve_month_range_dates(string $monthFrom, string $monthTo, ?int $academicYear = null): ?array {
        $mFrom = (int)$monthFrom;
        $mTo   = (int)$monthTo;
        if ($mFrom < 1 || $mFrom > 12 || $mTo < 1 || $mTo > 12) return null;

        $academicYear = $academicYear ?: llw_current_academic_year();
        $ceBase       = $academicYear - 543; // เช่น 2569 -> 2026

        // ในปีการศึกษาไทย เดือน พ.ค.(5)..ธ.ค.(12) อยู่ปี ค.ศ. ต้น, เดือน ม.ค.(1)..เม.ย.(4) อยู่ปี ค.ศ. ถัดไป
        $yearFrom = ($mFrom >= 5) ? $ceBase : ($ceBase + 1);
        $yearTo   = ($mTo   >= 5) ? $ceBase : ($ceBase + 1);

        // กรณีผู้ใช้เลือกย้อนกลับในปฏิทินเดียวกัน ให้สลับให้ถูกต้อง
        if ($yearFrom > $yearTo || ($yearFrom === $yearTo && $mFrom > $mTo)) {
            $yearTo = $yearFrom + 1;
        }

        $startDate = sprintf('%04d-%02d-01', $yearFrom, $mFrom);
        $lastDay   = (int)date('t', strtotime(sprintf('%04d-%02d-01', $yearTo, $mTo)));
        $endDate   = sprintf('%04d-%02d-%02d', $yearTo, $mTo, $lastDay);

        return ['start_date' => $startDate, 'end_date' => $endDate];
    }
}

if (!function_exists('llw_build_assembly_date_condition')) {
    /**
     * สร้าง SQL condition และ params สำหรับกรองวันที่ของระบบเข้าแถว (assembly)
     * รองรับทั้ง:
     *   - date_from & date_to (YYYY-MM-DD)
     *   - semester (1 หรือ 2) + year (พ.ศ.)
     *   - month_from & month_to (01..12 รองรับข้ามปี พ.ย. 11 -> มี.ค. 03)
     *   - month เดี่ยว (01..12)
     */
    function llw_build_assembly_date_condition(string $colName = 'date', ?array $src = null): array {
        $src       = $src ?? $_GET;
        $dateFrom  = trim($src['date_from']  ?? '');
        $dateTo    = trim($src['date_to']    ?? '');
        $sem       = (int)($src['semester']  ?? 0);
        $year      = (int)($src['year']      ?? llw_current_academic_year());
        $monthFrom = trim($src['month_from'] ?? '');
        $monthTo   = trim($src['month_to']   ?? '');
        $month     = trim($src['month']      ?? 'all');

        // 1. ถ้าระบุ date_from และ date_to มาโดยตรง
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            return [
                'sql'        => "AND $colName BETWEEN ? AND ?",
                'params'     => [$dateFrom, $dateTo],
                'start_date' => $dateFrom,
                'end_date'   => $dateTo,
                'label'      => "$dateFrom ถึง $dateTo",
            ];
        }

        // 2. ถ้าระบุ semester (1 หรือ 2)
        if (in_array($sem, [1, 2], true)) {
            $term = llw_get_term_dates($year, $sem);
            return [
                'sql'        => "AND $colName BETWEEN ? AND ?",
                'params'     => [$term['start_date'], $term['end_date']],
                'start_date' => $term['start_date'],
                'end_date'   => $term['end_date'],
                'label'      => "เทอม {$sem}/{$year}",
            ];
        }

        // 3. ถ้าระบุ month_from และ month_to (รองรับข้ามปี เช่น 11 ถึง 03)
        if ($monthFrom !== '' && $monthTo !== '') {
            $range = llw_resolve_month_range_dates($monthFrom, $monthTo, $year);
            if ($range) {
                return [
                    'sql'        => "AND $colName BETWEEN ? AND ?",
                    'params'     => [$range['start_date'], $range['end_date']],
                    'start_date' => $range['start_date'],
                    'end_date'   => $range['end_date'],
                    'label'      => "{$monthFrom}-{$monthTo}/{$year}",
                ];
            }
        }

        // 4. ถ้าระบุ month เดี่ยว
        if ($month !== '' && $month !== 'all') {
            $range = llw_resolve_month_range_dates($month, $month, $year);
            if ($range) {
                return [
                    'sql'        => "AND $colName BETWEEN ? AND ?",
                    'params'     => [$range['start_date'], $range['end_date']],
                    'start_date' => $range['start_date'],
                    'end_date'   => $range['end_date'],
                    'label'      => "เดือน {$month}/{$year}",
                ];
            }
        }

        return ['sql' => '', 'params' => [], 'start_date' => null, 'end_date' => null, 'label' => 'ทั้งหมด'];
    }
}

if (!function_exists('llw_render_term_preset_bar')) {
    /**
     * แสดงแถบปุ่มลัดเลือกช่วงเวลา (เทอม 1 / เทอม 2 / เดือนนี้ / สัปดาห์นี้) สำหรับหน้ารายงาน
     */
    function llw_render_term_preset_bar(string $startSelector = 'input[name="start_date"]', string $endSelector = 'input[name="end_date"]', string $curStart = '', string $curEnd = '', ?int $year = null): string {
        $year  = $year ?: llw_current_academic_year();
        $term1 = llw_get_term_dates($year, 1);
        $term2 = llw_get_term_dates($year, 2);
        $curSem = llw_current_semester();

        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');
        $dow        = (int)date('N');
        $weekStart  = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days'));
        $weekEnd    = date('Y-m-d', strtotime('+' . (7 - $dow) . ' days'));
        $today      = date('Y-m-d');

        $presets = [
            [
                'label' => "ภาคเรียนที่ 1/{$year}",
                'icon'  => 'bi-1-circle-fill',
                'start' => $term1['start_date'],
                'end'   => $term1['end_date'],
                'badge' => $curSem === 1 ? 'ปัจจุบัน' : '',
            ],
            [
                'label' => "ภาคเรียนที่ 2/{$year}",
                'icon'  => 'bi-2-circle-fill',
                'start' => $term2['start_date'],
                'end'   => $term2['end_date'],
                'badge' => $curSem === 2 ? 'ปัจจุบัน' : '',
            ],
            [
                'label' => 'เดือนนี้',
                'icon'  => 'bi-calendar-month',
                'start' => $monthStart,
                'end'   => $monthEnd,
                'badge' => '',
            ],
            [
                'label' => 'สัปดาห์นี้',
                'icon'  => 'bi-calendar-week',
                'start' => $weekStart,
                'end'   => $weekEnd,
                'badge' => '',
            ],
            [
                'label' => 'วันนี้',
                'icon'  => 'bi-calendar-check',
                'start' => $today,
                'end'   => $today,
                'badge' => '',
            ],
        ];

        $sSel = htmlspecialchars($startSelector, ENT_QUOTES, 'UTF-8');
        $eSel = htmlspecialchars($endSelector, ENT_QUOTES, 'UTF-8');

        $html = '<div class="flex flex-wrap items-center gap-2 mb-3 no-print">';
        $html .= '<span class="text-[11px] font-black text-slate-400 uppercase tracking-wider mr-1"><i class="bi bi-lightning-charge-fill text-amber-500"></i> ช่วงเวลาด่วน:</span>';
        foreach ($presets as $p) {
            $active = ($curStart === $p['start'] && $curEnd === $p['end']);
            $cls    = $active
                ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-200'
                : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200';
            $badgeHtml = $p['badge']
                ? '<span class="ml-1 px-1.5 py-0.2 rounded-md text-[9px] font-black ' . ($active ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-700') . '">' . $p['badge'] . '</span>'
                : '';
            $html .= sprintf(
                '<button type="button" onclick="llwApplyDatePreset(\'%s\',\'%s\',\'%s\',\'%s\',this)" class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all flex items-center gap-1.5 %s"><i class="bi %s"></i> %s%s</button>',
                $sSel, $eSel, $p['start'], $p['end'], $cls, $p['icon'], htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8'), $badgeHtml
            );
        }
        $html .= '</div>';
        $html .= '<script>
        if (typeof window.llwApplyDatePreset !== "function") {
            window.llwApplyDatePreset = function(sSel, eSel, start, end, btn) {
                var f = btn ? btn.closest("form") : document;
                var sEl = (f && f.querySelector(sSel)) || document.querySelector(sSel);
                var eEl = (f && f.querySelector(eSel)) || document.querySelector(eSel);
                if (sEl) sEl.value = start;
                if (eEl) eEl.value = end;
                if (f && f.tagName === "FORM") f.submit();
            };
        }
        </script>';
        return $html;
    }
}


