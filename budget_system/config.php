<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Database connection (Legacy wrapper for compatibility)
function connectDB()
{
    return getPdo();
}

// Check if user is logged in (Using LLW session)
function isLoggedIn()
{
    return isset($_SESSION['llw_role']);
}

// Check if user is admin (Super Admin in LLW)
function isAdmin()
{
    return isset($_SESSION['llw_role']) && $_SESSION['llw_role'] === 'super_admin';
}

// Security function to prevent XSS
function h($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Check if user can view data (All staff/admin)
function canView()
{
    return isset($_SESSION['llw_role']);
}

// Check if user can edit data (Super Admin or specific roles if needed)
function canEdit()
{
    return isAdmin();
}

// Check if user can manage projects (Super Admin)
function canManage()
{
    return isAdmin();
}

// Get Role Display in Thai
function getRoleDisplay($role)
{
    switch ($role) {
        case 'super_admin':
            return 'ผู้ดูแลระบบสูงสุด';
        case 'wfh_admin':
            return 'ผู้บริหาร/แอดมิน';
        default:
            return 'เจ้าหน้าที่';
    }
}

// Check if user can manage specific project
function canManageProject($project_id) {
    if (isAdmin()) return true;
    if (!isLoggedIn()) return false;
    
    try {
        $db = connectDB();
        // Updated to budget_projects and using session user_id
        $stmt = $db->prepare("
            SELECT p.created_by
            FROM budget_projects p
            WHERE p.project_id = ? AND p.created_by = ?
        ");
        $stmt->execute([$project_id, $_SESSION['user_id']]);
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        return false;
    }
}

// Check if user is project owner
function isProjectOwner($project_id) {
    if (!isLoggedIn()) return false;
    
    try {
        $db = connectDB();
        $stmt = $db->prepare("SELECT created_by FROM budget_projects WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $project && $project['created_by'] == $_SESSION['user_id'];
    } catch(PDOException $e) {
        return false;
    }
}

// Get Fund Sources
function getFundSources() {
    try {
        $db = connectDB();
        return $db->query("SELECT * FROM budget_fund_sources ORDER BY source_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

// Get Departments
function getDepartments() {
    try {
        $db = connectDB();
        return $db->query("SELECT * FROM wfh_departments ORDER BY dept_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return [];
    }
}

// Get Status Badge Class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'bg-amber-50 text-amber-600 border-amber-100';
        case 'approved': return 'bg-emerald-50 text-emerald-600 border-emerald-100';
        case 'rejected': return 'bg-rose-50 text-rose-600 border-rose-100';
        default: return 'bg-slate-50 text-slate-600 border-slate-100';
    }
}

// Get Status Display in Thai
function getStatusDisplay($status) {
    switch ($status) {
        case 'pending': return 'รออนุมัติ';
        case 'approved': return 'อนุมัติแล้ว';
        case 'rejected': return 'ไม่อนุมัติ';
        default: return $status;
    }
}
