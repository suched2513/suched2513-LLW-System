<?php
require_once 'config.php';

// Emergency Installer: Permissions temporarily removed to bypass session issues
// PLEASE DELETE THIS FILE AFTER SUCCESSFUL INSTALLATION

$db = connectDB();

echo "<!DOCTYPE html><html><head><title>Budget System Phase 2 Installer</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css' rel='stylesheet'></head>";
echo "<body class='bg-slate-50 p-10 font-sans'>";
echo "<div class='max-w-2xl mx-auto bg-white rounded-3xl shadow-xl p-8 border border-slate-100'>";
echo "<h1 class='text-2xl font-black text-slate-800 mb-6'>Budget System Phase 2 Installer</h1>";
echo "<div class='space-y-4'>";

try {
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. Fund Sources
    echo "<div class='p-4 rounded-2xl bg-slate-50 border border-slate-100'>";
    echo "<p class='text-xs font-black text-slate-400 uppercase tracking-widest mb-2'>Step 1: Creating budget_fund_sources</p>";
    $db->exec("CREATE TABLE IF NOT EXISTS `budget_fund_sources` (
        `source_id` int(11) NOT NULL AUTO_INCREMENT,
        `source_name` varchar(100) NOT NULL,
        `description` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`source_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("INSERT IGNORE INTO `budget_fund_sources` (`source_id`, `source_name`) VALUES 
        (1, 'เงินอุดหนุน'), (2, 'พัฒนาคุณภาพผู้เรียน'), (3, 'เงินรายได้สถานศึกษา'), (4, 'เงินสำรองจ่าย'), (5, 'เงินอื่นๆ')");
    echo "<p class='text-emerald-600 font-bold'>✓ Success</p></div>";

    // 2. Update Projects
    echo "<div class='p-4 rounded-2xl bg-slate-50 border border-slate-100'>";
    echo "<p class='text-xs font-black text-slate-400 uppercase tracking-widest mb-2'>Step 2: Updating budget_projects</p>";
    $cols = $db->query("DESCRIBE `budget_projects`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('department_id', $cols)) {
        $db->exec("ALTER TABLE `budget_projects` ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `project_name` ");
    }
    if (!in_array('fiscal_year', $cols)) {
        $db->exec("ALTER TABLE `budget_projects` ADD COLUMN `fiscal_year` varchar(4) DEFAULT NULL AFTER `department_id` ");
    }
    echo "<p class='text-emerald-600 font-bold'>✓ Success</p></div>";

    // 3. Disbursements
    echo "<div class='p-4 rounded-2xl bg-slate-50 border border-slate-100'>";
    echo "<p class='text-xs font-black text-slate-400 uppercase tracking-widest mb-2'>Step 3: Creating budget_disbursements</p>";
    $db->exec("CREATE TABLE IF NOT EXISTS `budget_disbursements` (
        `disbursement_id` int(11) NOT NULL AUTO_INCREMENT,
        `project_id` int(11) NOT NULL,
        `doc_no` varchar(50) DEFAULT NULL,
        `activity_name` varchar(255) NOT NULL,
        `reason` text DEFAULT NULL,
        `fund_source_id` int(11) DEFAULT NULL,
        `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
        `status` enum('pending','approved','rejected') DEFAULT 'pending',
        `request_date` date NOT NULL,
        `requested_by` int(11) NOT NULL,
        `approved_by` int(11) DEFAULT NULL,
        `approved_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`disbursement_id`),
        FOREIGN KEY (`project_id`) REFERENCES `budget_projects` (`project_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='text-emerald-600 font-bold'>✓ Success</p></div>";

    // 4. Items
    echo "<div class='p-4 rounded-2xl bg-slate-50 border border-slate-100'>";
    echo "<p class='text-xs font-black text-slate-400 uppercase tracking-widest mb-2'>Step 4: Creating budget_disbursement_items</p>";
    $db->exec("CREATE TABLE IF NOT EXISTS `budget_disbursement_items` (
        `item_id` int(11) NOT NULL AUTO_INCREMENT,
        `disbursement_id` int(11) NOT NULL,
        `item_name` varchar(255) NOT NULL,
        `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
        `unit` varchar(50) DEFAULT NULL,
        `price_per_unit` decimal(15,2) NOT NULL DEFAULT 0.00,
        `total_price` decimal(15,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (`item_id`),
        FOREIGN KEY (`disbursement_id`) REFERENCES `budget_disbursements` (`disbursement_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='text-emerald-600 font-bold'>✓ Success</p></div>";

    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "<div class='mt-8 p-6 bg-indigo-600 rounded-2xl text-white text-center'>";
    echo "<p class='font-black text-lg'>อัปเกรดเป็น Phase 2 เรียบร้อย!</p>";
    echo "<p class='text-sm opacity-80 mb-4'>ระบบรองรับการขออนุมัติและคัดแยกแหล่งเงินแล้วครับ</p>";
    echo "<a href='index.php' class='inline-block bg-white text-indigo-600 px-6 py-2 rounded-xl font-bold hover:bg-slate-50 transition-all'>ไปที่หน้าหลัก</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='p-6 bg-rose-50 border border-rose-100 rounded-2xl text-rose-600'>";
    echo "<p class='font-black mb-2 uppercase tracking-widest text-xs'>Error Details:</p>";
    echo "<pre class='text-xs overflow-auto'>" . $e->getMessage() . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";
