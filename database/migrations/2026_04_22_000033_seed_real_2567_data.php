<?php
return [
    'up' => function ($pdo) {
        // 1. Add Fiscal Year 2567
        $pdo->prepare("INSERT IGNORE INTO sbms_fiscal_years (year_name, start_date, end_date, status) VALUES (?, ?, ?, ?)")
            ->execute(['2567', '2023-10-01', '2024-09-30', 'closed']);
        $yearId = $pdo->query("SELECT id FROM sbms_fiscal_years WHERE year_name = '2567'")->fetchColumn();

        // 2. Add Budget Plans for 2567
        $budgets = [
            ['government', 'งบประมาณแผ่นดิน (พัฒนาคุณภาพ)', 200000, 120000],
            ['subsidy', 'เงินอุดหนุนรายหัวนักเรียน', 200000, 130000],
            ['revenue', 'เงินรายได้สถานศึกษา', 500000, 270000], // Adjusting total to match 900k sum (200+200+500)
        ];

        foreach ($budgets as $b) {
            $pdo->prepare("INSERT INTO sbms_budgets (fiscal_year_id, budget_type, plan_name, total_amount, used_amount) VALUES (?, ?, ?, ?, ?)")
                ->execute([$yearId, $b[0], $b[1], $b[2], $b[3]]);
        }

        // 3. Add Real Projects from Spreadsheet
        $projects = [
            ['PRJ-2567-001', 'พัฒนาห้องเรียน ICT', 'government', 100000, 85000, 'in_progress'],
            ['PRJ-2567-002', 'ปรับปรุงสนามกีฬา', 'subsidy', 120000, 120000, 'completed'],
            ['PRJ-2567-005', 'ศึกษาดูงานส่วนราชการ', 'revenue', 150000, 0, 'approved'],
        ];

        foreach ($projects as $p) {
            $budgetId = $pdo->query("SELECT id FROM sbms_budgets WHERE budget_type = '{$p[2]}' AND fiscal_year_id = $yearId")->fetchColumn();
            $pdo->prepare("
                INSERT INTO sbms_projects (project_code, project_name, fiscal_year_id, budget_id, requested_amount, approved_amount, used_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")
            ->execute([$p[0], $p[1], $yearId, $budgetId, $p[3], $p[3], $p[4], $p[5]]);
        }
    },
    'down' => function ($pdo) {
        $pdo->exec("DELETE FROM sbms_projects WHERE project_code LIKE 'PRJ-2567-%'");
        $pdo->exec("DELETE FROM sbms_budgets WHERE fiscal_year_id IN (SELECT id FROM sbms_fiscal_years WHERE year_name = '2567')");
        $pdo->exec("DELETE FROM sbms_fiscal_years WHERE year_name = '2567'");
    }
];
