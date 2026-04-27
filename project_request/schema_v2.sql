-- Update Users Role ENUM (Applying to main llw_users table)
ALTER TABLE llw_users MODIFY COLUMN role ENUM('admin', 'teacher', 'director', 'budget_officer', 'super_admin', 'wfh_admin', 'wfh_staff', 'cb_admin', 'att_teacher') NOT NULL;

-- Add missing columns if they don't exist in central auth
-- ALTER TABLE llw_users ADD COLUMN department VARCHAR(100) AFTER lastname;

-- 8. Audit Logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action ENUM('create', 'update', 'submit', 'approve', 'reject', 'download', 'import', 'delete') NOT NULL,
    target_type VARCHAR(100) NOT NULL,
    target_id INT,
    old_value JSON,
    new_value JSON,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES llw_users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('pending_approval', 'budget_warning', 'project_overdue', 'approved', 'rejected') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    related_id INT,
    related_type VARCHAR(100),
    is_read TINYINT(1) DEFAULT 0,
    sent_email TINYINT(1) DEFAULT 0,
    sent_line TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES llw_users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Report Exports
CREATE TABLE IF NOT EXISTS report_exports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    report_type VARCHAR(100) NOT NULL,
    filters JSON,
    file_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES llw_users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Views for Reporting

-- View for Budget Usage Summary
CREATE OR REPLACE VIEW v_budget_usage AS
SELECT
    d.id AS department_id,
    d.name AS department_name,
    bp.fiscal_year,
    SUM(bp.budget_subsidy) AS alloc_subsidy,
    SUM(bp.budget_quality) AS alloc_quality,
    SUM(bp.budget_revenue) AS alloc_revenue,
    SUM(bp.budget_operation) AS alloc_operation,
    SUM(bp.budget_reserve) AS alloc_reserve,
    SUM(bp.budget_subsidy + bp.budget_quality + bp.budget_revenue + bp.budget_operation + bp.budget_reserve) AS alloc_total,
    COALESCE(SUM(spent.total_spent), 0) AS used_total
FROM departments d
JOIN budget_projects bp ON bp.department_id = d.id
LEFT JOIN (
    SELECT budget_project_id, SUM(amount_requested) AS total_spent
    FROM project_requests
    WHERE status = 'approved'
    GROUP BY budget_project_id
) spent ON spent.budget_project_id = bp.id
GROUP BY d.id, bp.fiscal_year;

-- View for Project Status Summary
CREATE OR REPLACE VIEW v_project_status_summary AS
SELECT
    d.name AS department_name,
    COUNT(bp.id) AS total_projects,
    SUM(CASE WHEN pr.id IS NULL THEN 1 ELSE 0 END) AS no_request,
    SUM(CASE WHEN pr.status='draft' THEN 1 ELSE 0 END) AS draft,
    SUM(CASE WHEN pr.status='submitted' THEN 1 ELSE 0 END) AS submitted,
    SUM(CASE WHEN pr.status='approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN pr.status='rejected' THEN 1 ELSE 0 END) AS rejected
FROM budget_projects bp
JOIN departments d ON bp.department_id = d.id
LEFT JOIN project_requests pr ON pr.budget_project_id = bp.id
GROUP BY d.id;
