<?php
require_once 'config.php';

// Emergency Installer: Permissions temporarily removed to bypass session issues
// PLEASE DELETE THIS FILE AFTER SUCCESSFUL INSTALLATION

$db = connectDB();

echo "<!DOCTYPE html><html><head><title>Budget System DB Installer</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css' rel='stylesheet'></head>";
echo "<body class='bg-slate-50 p-10 font-sans'>";
echo "<div class='max-w-2xl mx-auto bg-white rounded-3xl shadow-xl p-8 border border-slate-100'>";
echo "<h1 class='text-2xl font-black text-slate-800 mb-6'>Budget System Database Installer</h1>";
echo "<div class='space-y-4'>";

try {
    // 1. Create budget_projects
    echo "<div class='p-4 rounded-2xl bg-slate-50 border border-slate-100'>";
    echo "<p class='text-xs font-black text-slate-400 uppercase tracking-widest mb-2'>Step 1: Creating budget_projects table</p>";
    $db->exec("CREATE TABLE IF NOT EXISTS budget_projects (
        project_id INT AUTO_INCREMENT PRIMARY KEY,
        project_name VARCHAR(255) NOT NULL,
        description TEXT,
        total_budget DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
        start_date DATE,
        end_date DATE,
        status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='text-emerald-600 font-bold'>✓ Success</p></div>";

    // 2. Create budget_transactions
    echo "<div class='p-4 rounded-2xl bg-slate-50 border border-slate-100'>";
    echo "<p class='text-xs font-black text-slate-400 uppercase tracking-widest mb-2'>Step 2: Creating budget_transactions table</p>";
    $db->exec("CREATE TABLE IF NOT EXISTS budget_transactions (
        transaction_id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        transaction_type ENUM('income', 'expense') NOT NULL,
        description TEXT,
        transaction_date DATE NOT NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES budget_projects(project_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='text-emerald-600 font-bold'>✓ Success</p></div>";

    // 3. Seed Sample Data if empty
    $count = $db->query("SELECT COUNT(*) FROM budget_projects")->fetchColumn();
    if ($count == 0) {
        echo "<div class='p-4 rounded-2xl bg-blue-50 border border-blue-100'>";
        echo "<p class='text-xs font-black text-blue-400 uppercase tracking-widest mb-2'>Step 3: Seeding sample data</p>";
        $adminId = $_SESSION['user_id'] ?? 1;
        $db->exec("INSERT INTO budget_projects (project_name, description, total_budget, start_date, end_date, created_by) VALUES 
            ('โครงการพัฒนาการเรียนการสอน', 'งบประมาณเพื่อพัฒนาสื่อและอุปกรณ์การเรียน', 50000.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), $adminId)");
        echo "<p class='text-blue-600 font-bold'>✓ Sample Data Created</p></div>";
    }

    echo "<div class='mt-8 p-6 bg-emerald-500 rounded-2xl text-white text-center'>";
    echo "<p class='font-black text-lg'>การติดตั้งเสร็จสมบูรณ์!</p>";
    echo "<p class='text-sm opacity-80 mb-4'>คุณสามารถเข้าใช้งานระบบงบประมาณได้แล้วครับ</p>";
    echo "<a href='index.php' class='inline-block bg-white text-emerald-600 px-6 py-2 rounded-xl font-bold hover:bg-slate-50 transition-all'>ไปที่หน้าหลักระบบงบประมาณ</a>";
    echo "</div>";

    echo "<p class='text-center mt-6 text-slate-300 text-[10px] font-bold uppercase tracking-widest'>⚠️ เพื่อความปลอดภัย กรุณาลบไฟล์ install_db.php ออกหลังจากติดตั้งเสร็จ</p>";

} catch (Exception $e) {
    echo "<div class='p-6 bg-rose-50 border border-rose-100 rounded-2xl text-rose-600'>";
    echo "<p class='font-black mb-2 uppercase tracking-widest text-xs'>Error Details:</p>";
    echo "<pre class='text-xs overflow-auto'>" . $e->getMessage() . "</pre>";
    echo "</div>";
    echo "<p class='text-center mt-4 text-slate-400'>กรุณาตรวจสอบว่าฐานข้อมูล 'krusuche_llw' มีอยู่จริงและมีสิทธิ์เขียนข้อมูล</p>";
}

echo "</div></body></html>";
