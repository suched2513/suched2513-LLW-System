<?php
/**
 * database/seed.php
 * ตัวติดตั้งข้อมูลเริ่มต้นระบบบริหารการเงินและบัญชีสถานศึกษา โรงเรียนละลมวิทยา
 */

require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;

try {
    echo "--- เริ่มต้นการนำเข้าข้อมูลตั้งต้น (Seeding Initial Data) ---" . PHP_EOL;
    $pdo = isset($pdo) && ($pdo instanceof PDO) ? $pdo : Database::getConnection();
    $permissionsConfig = require __DIR__ . '/../config/permissions.php';

    // 1. Roles (บทบาท)
    $stmtRole = $pdo->prepare("INSERT INTO fin_roles (role_code, role_name, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE role_name=VALUES(role_name), description=VALUES(description)");
    foreach ($permissionsConfig['roles'] as $code => $name) {
        $stmtRole->execute([$code, $name, "บทบาทมาตรฐานระบบ: $name"]);
    }
    echo "  - ติดตั้งบทบาทผู้ใช้งาน (8 บทบาท) เรียบร้อย" . PHP_EOL;

    // 2. Permissions (สิทธิ์)
    $stmtPerm = $pdo->prepare("INSERT INTO fin_permissions (module, action, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE description=VALUES(description)");
    foreach ($permissionsConfig['modules'] as $module => $modDesc) {
        foreach ($permissionsConfig['actions'] as $action => $actDesc) {
            $stmtPerm->execute([$module, $action, "$actDesc ใน $modDesc"]);
        }
    }
    echo "  - ติดตั้งรายการสิทธิ์ (104 สิทธิ์) เรียบร้อย" . PHP_EOL;

    // 3. Role Permissions Mapping
    $roleMap = $pdo->query("SELECT role_code, id FROM fin_roles")->fetchAll(PDO::FETCH_KEY_PAIR);
    $permMap = $pdo->query("SELECT CONCAT(module, ':', action) as perm_key, id FROM fin_permissions")->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmtRolePerm = $pdo->prepare("INSERT IGNORE INTO fin_role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($permissionsConfig['role_permissions'] as $roleCode => $moduleActions) {
        if (!isset($roleMap[$roleCode])) continue;
        $roleId = $roleMap[$roleCode];
        foreach ($moduleActions as $mod => $actions) {
            foreach ($actions as $act) {
                $key = "$mod:$act";
                if (isset($permMap[$key])) {
                    $stmtRolePerm->execute([$roleId, $permMap[$key]]);
                }
            }
        }
    }
    echo "  - จับคู่สิทธิ์ตามบทบาท (Role-Permission Matrix) เรียบร้อย" . PHP_EOL;

    // 4. Default Users (ผู้ใช้งานตั้งต้น)
    $defaultPasswordHash = password_hash('Password123!', PASSWORD_BCRYPT);
    $defaultUsers = [
        ['admin', $defaultPasswordHash, 'ผู้ดูแลระบบกลาง', 'admin@llw.ac.th', 'admin'],
        ['finance', $defaultPasswordHash, 'นางกนกพร การเงินดี (เจ้าหน้าที่การเงิน)', 'finance@llw.ac.th', 'finance_officer'],
        ['accountant', $defaultPasswordHash, 'นายบัญชา ลงบัญชีแม่น (เจ้าหน้าที่บัญชี)', 'accountant@llw.ac.th', 'accountant'],
        ['inventory', $defaultPasswordHash, 'นายพัสกร ดูแลพัสดุ (เจ้าหน้าที่พัสดุ)', 'inventory@llw.ac.th', 'inventory_officer'],
        ['teacher1', $defaultPasswordHash, 'ครูสุเชษฐ์ ใจดี (ผู้ขอเบิก/ครูผู้สอน)', 'teacher1@llw.ac.th', 'requester'],
        ['auditor', $defaultPasswordHash, 'นางสมพร ตรวจสอบละเอียด (ผู้ตรวจสอบภายใน)', 'auditor@llw.ac.th', 'auditor'],
        ['director', $defaultPasswordHash, 'ผู้อำนวยการโรงเรียนละลมวิทยา (ผู้อนุมัติ)', 'director@llw.ac.th', 'approver'],
        ['executive', $defaultPasswordHash, 'รองผู้อำนวยการกลุ่มบริหารงบประมาณ (ผู้บริหาร)', 'executive@llw.ac.th', 'executive'],
    ];

    $stmtUser = $pdo->prepare("INSERT INTO fin_users (username, password_hash, full_name, email, is_active) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), email=VALUES(email)");
    $stmtUserRole = $pdo->prepare("INSERT IGNORE INTO fin_user_roles (user_id, role_id) VALUES (?, ?)");

    foreach ($defaultUsers as $u) {
        $stmtUser->execute([$u[0], $u[1], $u[2], $u[3]]);
        $userId = $pdo->query("SELECT id FROM fin_users WHERE username = " . $pdo->quote($u[0]))->fetchColumn();
        if ($userId && isset($roleMap[$u[4]])) {
            $stmtUserRole->execute([$userId, $roleMap[$u[4]]]);
        }
    }
    echo "  - ติดตั้งผู้ใช้งานเริ่มต้น (8 บัญชี รหัสผ่านเริ่มต้น: Password123!) เรียบร้อย" . PHP_EOL;

    // 5. Fiscal Years & Accounting Periods (ปีงบประมาณไทย 2569 และ 2570)
    $fiscalYears = [
        [2569, '2025-10-01', '2026-09-30', 0, 0],
        [2570, '2026-10-01', '2027-09-30', 1, 0], // ปีปัจจุบัน
    ];
    $stmtFy = $pdo->prepare("INSERT INTO fin_fiscal_years (year_be, start_date, end_date, is_current, is_closed) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_current=VALUES(is_current)");
    $stmtPeriod = $pdo->prepare("INSERT INTO fin_accounting_periods (fiscal_year_id, period_no, month_no, year_ce, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, 'open') ON DUPLICATE KEY UPDATE status=VALUES(status)");

    foreach ($fiscalYears as $fy) {
        $stmtFy->execute($fy);
        $fyId = $pdo->query("SELECT id FROM fin_fiscal_years WHERE year_be = {$fy[0]}")->fetchColumn();
        $startCeYear = $fy[0] - 543 - 1; // เช่น 2570 => ค.ศ. 2026
        for ($p = 1; $p <= 12; $p++) {
            // p=1 => Oct (10), p=2 => Nov (11), ..., p=12 => Sep (09)
            $m = ($p <= 3) ? ($p + 9) : ($p - 3);
            $y = ($p <= 3) ? $startCeYear : ($startCeYear + 1);
            $pStart = sprintf('%04d-%02d-01', $y, $m);
            $pEnd   = date('Y-m-t', strtotime($pStart));
            $stmtPeriod->execute([$fyId, $p, $m, $y, $pStart, $pEnd]);
        }
    }
    echo "  - ติดตั้งปีงบประมาณไทย 2569 และ 2570 พร้อม 24 งวดบัญชี เรียบร้อย" . PHP_EOL;

    // 6. Chart of Accounts (ผังบัญชีมาตรฐาน 5 หมวด)
    $accounts = [
        // สินทรัพย์ (1)
        ['110101', 'เงินสดในมือ', 'asset', 'debit', null],
        ['110102', 'เงินฝากธนาคารกรุงไทย (กระแสรายวัน/ออมทรัพย์)', 'asset', 'debit', null],
        ['110103', 'เงินฝากธนาคารออมสิน', 'asset', 'debit', null],
        ['110104', 'เงินฝากธนาคารเพื่อการเกษตรฯ (ธ.ก.ส.)', 'asset', 'debit', null],
        ['110201', 'ลูกหนี้เงินยืมราชการ', 'asset', 'debit', null],
        ['110301', 'วัสดุคงเหลือ', 'asset', 'debit', null],
        ['120101', 'ครุภัณฑ์การศึกษาและสำนักงาน', 'asset', 'debit', null],
        ['120102', 'ค่าเสื่อมราคาสะสมครุภัณฑ์', 'asset', 'credit', null],
        // หนี้สิน (2)
        ['210101', 'เจ้าหนี้การค้าและผู้ขาย', 'liability', 'credit', null],
        ['210201', 'ภาษีหัก ณ ที่จ่ายค้างจ่าย', 'liability', 'credit', null],
        ['210301', 'เงินประกันสัญญาค้างจ่าย', 'liability', 'credit', null],
        // ทุน (3)
        ['310101', 'เงินสะสม/ทุนสถานศึกษา', 'equity', 'credit', null],
        ['310201', 'รายได้สูงกว่า (ต่ำกว่า) ค่าใช้จ่ายสะสม', 'equity', 'credit', null],
        // รายได้ (4)
        ['410101', 'รายได้เงินอุดหนุนรายหัวจากรัฐ', 'revenue', 'credit', null],
        ['410102', 'รายได้เงินรายได้สถานศึกษา', 'revenue', 'credit', null],
        ['410103', 'รายได้เงินบริจาค', 'revenue', 'credit', null],
        ['410104', 'รายได้ค่าบำรุงการศึกษาห้องเรียนพิเศษ', 'revenue', 'credit', null],
        ['410105', 'รายได้ดอกเบี้ยรับ', 'revenue', 'credit', null],
        // ค่าใช้จ่าย (5)
        ['510101', 'ค่าตอบแทนบุคลากรและวิทยากร', 'expense', 'debit', null],
        ['510201', 'ค่าใช้สอยในการดำเนินงาน', 'expense', 'debit', null],
        ['510301', 'ค่าวัสดุและอุปกรณ์การศึกษา', 'expense', 'debit', null],
        ['510401', 'ค่าสาธารณูปโภค', 'expense', 'debit', null],
        ['510501', 'ค่าใช้จ่ายตามโครงการพัฒนาผู้เรียน', 'expense', 'debit', null],
        ['510601', 'ค่าธรรมเนียมธนาคาร', 'expense', 'debit', null],
    ];

    $stmtAcc = $pdo->prepare("INSERT INTO fin_chart_of_accounts (account_code, account_name, account_category, normal_balance, is_active) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE account_name=VALUES(account_name), account_category=VALUES(account_category), normal_balance=VALUES(normal_balance)");
    foreach ($accounts as $acc) {
        $stmtAcc->execute([$acc[0], $acc[1], $acc[2], $acc[3]]);
    }
    echo "  - ติดตั้งผังบัญชีมาตรฐานภาครัฐ 5 หมวด (24 บัญชี) เรียบร้อย" . PHP_EOL;

    // 7. Funding Sources (แหล่งเงิน)
    $fundingSources = [
        ['SUB-01', 'เงินอุดหนุนรายหัวนักเรียน', 'subsidy'],
        ['REV-01', 'เงินรายได้สถานศึกษา', 'revenue'],
        ['DON-01', 'เงินบริจาคเพื่อการศึกษา', 'donation'],
        ['FEE-01', 'ค่าบำรุงการศึกษาห้องเรียนพิเศษ', 'tuition_fee'],
        ['OTH-01', 'เงินสนับสนุนอื่นๆ', 'other'],
    ];
    $stmtFs = $pdo->prepare("INSERT INTO fin_funding_sources (source_code, source_name, source_type, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE source_name=VALUES(source_name), source_type=VALUES(source_type)");
    foreach ($fundingSources as $fs) {
        $stmtFs->execute($fs);
    }
    echo "  - ติดตั้งแหล่งเงินตั้งต้น (5 แหล่งเงิน) เรียบร้อย" . PHP_EOL;

    // 8. Special Programs (ห้องเรียนพิเศษ)
    $specialPrograms = [
        ['EP', 'English Program (EP)', 'โครงการจัดการเรียนการสอนตามหลักสูตรกระทรวงศึกษาธิการเป็นภาษาอังกฤษ'],
        ['MEP', 'Mini English Program (MEP)', 'โครงการจัดการเรียนการสอนภาษาอังกฤษแบบเข้ม'],
        ['IEP', 'Intensive English Program (IEP)', 'โครงการห้องเรียนเน้นภาษาอังกฤษพิเศษ'],
        ['IP', 'International Program (IP)', 'โครงการห้องเรียนนานาชาติ'],
        ['Gifted', 'Gifted Program', 'โครงการส่งเสริมความเป็นเลิศด้านวิชาการ'],
        ['SMP', 'Science-Math Program (SMP)', 'โครงการห้องเรียนพิเศษวิทยาศาสตร์-คณิตศาสตร์'],
    ];
    $stmtSp = $pdo->prepare("INSERT INTO fin_special_programs (program_code, program_name, description, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE program_name=VALUES(program_name), description=VALUES(description)");
    foreach ($specialPrograms as $sp) {
        $stmtSp->execute($sp);
    }
    echo "  - ติดตั้งห้องเรียนพิเศษมาตรฐาน (6 ประเภท) เรียบร้อย" . PHP_EOL;

    // 9. Document Templates (แม่แบบเอกสาร 28 รายการ)
    $templates = [
        ['REC-01', 'ใบเสร็จรับเงิน', 'ใบเสร็จรับเงิน'],
        ['REP-01', 'รายงานรับเงินประจำวัน', 'รายงานประจำวัน'],
        ['REP-02', 'รายงานรับเงินประจำเดือน', 'รายงานประจำเดือน'],
        ['DEP-01', 'ใบนำฝากเงินธนาคาร', 'ใบนำฝาก'],
        ['BAL-01', 'รายงานเงินคงเหลือประจำวัน', 'เงินคงเหลือประจำวัน'],
        ['REQ-01', 'บันทึกขออนุมัติเบิกจ่าย', 'บันทึกข้อความ'],
        ['VOU-01', 'ใบสำคัญจ่าย', 'ใบสำคัญจ่าย'],
        ['REC-CERT', 'ใบรับรองแทนใบเสร็จรับเงิน', 'บก.111'],
        ['ADV-01', 'สัญญายืมเงิน', '8500'],
        ['ADV-02', 'ใบส่งใช้เงินยืม', 'ใบส่งใช้เงินยืม'],
        ['ADV-03', 'ทะเบียนคุมเงินยืมราชการ', 'ทะเบียนคุมเงินยืม'],
        ['REP-03', 'รายงานจ่ายเงินประจำเดือน', 'รายงานจ่ายประจำเดือน'],
        ['JRN-01', 'สมุดรายวันทั่วไป', 'สมุดรายวันทั่วไป'],
        ['GL-01', 'บัญชีแยกประเภททั่วไป', 'บัญชีแยกประเภท'],
        ['TB-01', 'งบทดลอง', 'งบทดลอง'],
        ['BDG-01', 'ทะเบียนคุมงบประมาณ', 'ทะเบียนคุมงบประมาณ'],
        ['BDG-02', 'รายงานการใช้งบประมาณรายโครงการ', 'รายงานงบโครงการ'],
        ['REC-BANK', 'รายงานการกระทบยอดเงินฝากธนาคาร', 'กระทบยอดธนาคาร'],
        ['PRC-01', 'แผนจัดซื้อจัดจ้างประจำปี', 'แผนจัดซื้อจัดจ้าง'],
        ['PR-01', 'ใบขอซื้อหรือขอจ้าง', 'ขอซื้อขอจ้าง'],
        ['PRC-02', 'ตารางเปรียบเทียบราคา', 'เปรียบเทียบราคา'],
        ['PO-01', 'ใบสั่งซื้อหรือใบสั่งจ้าง', 'สั่งซื้อสั่งจ้าง'],
        ['INSP-01', 'รายงานตรวจรับพัสดุ', 'รายงานตรวจรับ'],
        ['AST-01', 'ทะเบียนคุมครุภัณฑ์', 'ทะเบียนครุภัณฑ์'],
        ['INV-01', 'รายงานวัสดุคงเหลือประจำงวด', 'รายงานวัสดุคงเหลือ'],
        ['TRV-01', 'แบบขอเบิกค่าใช้จ่ายในการเดินทางไปราชการ', '8708'],
        ['TRV-ADV', 'สัญญายืมเงินเพื่อการเดินทางไปราชการ', '8500'],
        ['REC-CERT2', 'ใบรับรองการจ่ายเงินกรณีไม่มีใบเสร็จรับเงิน', 'บก.111'],
    ];
    $stmtTpl = $pdo->prepare("INSERT INTO fin_document_templates (template_code, template_name, form_code, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE template_name=VALUES(template_name), form_code=VALUES(form_code)");
    foreach ($templates as $tpl) {
        $stmtTpl->execute($tpl);
    }
    echo "  - ติดตั้งแม่แบบเอกสารและรายงานราชการ (28 รายการ) เรียบร้อย" . PHP_EOL;

    // 10. Bank Accounts (บัญชีธนาคารโรงเรียนละลมวิทยา)
    $bankAccounts = [
        ['ธนาคารกรุงไทย', 'สาขาขุขันธ์', '328-0-12345-6', 'โรงเรียนละลมวิทยา (เงินรายได้สถานศึกษา)', 'savings', 250000.00, 2],
        ['ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร (ธ.ก.ส.)', 'สาขาละลม', '020-1-98765-4', 'โรงเรียนละลมวิทยา (เงินอุดหนุน)', 'savings', 500000.00, 1],
        ['ธนาคารกรุงไทย', 'สาขาขุขันธ์', '328-6-54321-0', 'โรงเรียนละลมวิทยา (กระแสรายวัน)', 'current', 100000.00, 1],
    ];
    $stmtBank = $pdo->prepare("INSERT INTO fin_bank_accounts (bank_name, branch_name, account_no, account_name, account_type, current_balance, funding_source_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE account_name=VALUES(account_name), current_balance=VALUES(current_balance)");
    foreach ($bankAccounts as $ba) {
        $stmtBank->execute($ba);
    }
    echo "  - ติดตั้งบัญชีเงินฝากธนาคารเริ่มต้น เรียบร้อย" . PHP_EOL;

    // 11. System Settings (ข้อมูลหน่วยงานและการตั้งค่านโยบาย)
    $settings = [
        'school_name'            => 'โรงเรียนละลมวิทยา',
        'school_affiliation'     => 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร',
        'school_address'         => 'ตำบลละลม อำเภอไพรบึง จังหวัดศรีสะเกษ',
        'school_phone'           => '045-XXXXXX',
        'school_tax_id'          => '0994000XXXXXX',
        'school_logo_path'       => '',
        'school_garuda_path'     => '',
        'budget_warning_percent' => '80.00',
        'budget_block_percent'   => '100.00',
        'allow_budget_override'  => '0',
    ];
    $stmtSet = $pdo->prepare("INSERT INTO fin_system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    foreach ($settings as $k => $v) {
        $stmtSet->execute([$k, $v]);
    }
    echo "  - ติดตั้งข้อมูลองค์กรโรงเรียนละลมวิทยาและการตั้งค่าระบบ เรียบร้อย" . PHP_EOL;

    echo "=== ดำเนินการนำเข้าข้อมูลตั้งต้น (SEEDING) สำเร็จสมบูรณ์ 100% ===" . PHP_EOL;

} catch (Throwable $e) {
    echo "❌ เกิดข้อผิดพลาดในการ Seed ข้อมูล: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
