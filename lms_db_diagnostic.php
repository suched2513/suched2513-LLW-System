<?php
/**
 * lms_db_diagnostic.php — Diagnoses database status and queries for LMS
 */
header('Content-Type: text/plain; charset=utf-8');
session_start();

// Mock LLW Role and teacher_id if not logged in to make this diagnostics runnable
if (!isset($_SESSION['llw_role'])) {
    $_SESSION['llw_role'] = 'super_admin';
}

require_once __DIR__ . '/config.php';

try {
    echo "=== DATABASE INFORMTION ===\n";
    $pdo = getPdo();
    echo "Successfully connected to DB via getPdo().\n\n";

    echo "=== CHECKING TABLES ===\n";
    $tables = ['llw_users', 'att_subjects', 'att_teachers', 'att_students', 'lms_units', 'lms_quizzes', 'lms_questions', 'lms_choices', 'lms_quiz_attempts', 'lms_quiz_answers'];
    foreach ($tables as $tbl) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$tbl`");
            $count = $stmt->fetchColumn();
            echo "✓ Table `$tbl` exists. Count: $count\n";
        } catch (Exception $e) {
            echo "❌ Table `$tbl` ERROR: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== DIAGNOSING MANAGE_UNITS QUERIES ===\n";

    echo "Query 1: Fetch subjects (role = {$_SESSION['llw_role']})\n";
    try {
        if ($_SESSION['llw_role'] === 'super_admin') {
            $stmt = $pdo->prepare("
                SELECT s.*, t.name as teacher_name 
                FROM att_subjects s 
                LEFT JOIN att_teachers t ON s.teacher_id = t.id 
                ORDER BY s.subject_code ASC, s.classroom ASC
            ");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT s.*, t.name as teacher_name 
                FROM att_subjects s 
                LEFT JOIN att_teachers t ON s.teacher_id = t.id 
                WHERE s.teacher_id = ? 
                ORDER BY s.subject_code ASC, s.classroom ASC
            ");
            $stmt->execute([$_SESSION['teacher_id'] ?? 0]);
        }
        $subjects = $stmt->fetchAll();
        echo "✓ Query 1 successful. Fetched " . count($subjects) . " subjects.\n";
        
        if (!empty($subjects)) {
            $subjectIds = array_column($subjects, 'id');
            $inQuery = implode(',', array_fill(0, count($subjectIds), '?'));
            
            echo "Query 2: Fetch units and quizzes using IN ($inQuery)\n";
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       q_pre.id as pre_quiz_id, q_pre.is_active as pre_quiz_active, q_pre.time_limit as pre_time_limit, q_pre.title as pre_quiz_title,
                       q_post.id as post_quiz_id, q_post.is_active as post_quiz_active, q_post.time_limit as post_time_limit, q_post.title as post_quiz_title,
                       (SELECT COUNT(*) FROM lms_questions WHERE quiz_id = q_pre.id) as pre_question_count,
                       (SELECT COUNT(*) FROM lms_questions WHERE quiz_id = q_post.id) as post_question_count
                FROM lms_units u
                LEFT JOIN lms_quizzes q_pre ON q_pre.unit_id = u.id AND q_pre.quiz_type = 'pre'
                LEFT JOIN lms_quizzes q_post ON q_post.unit_id = u.id AND q_post.quiz_type = 'post'
                WHERE u.subject_id IN ($inQuery)
                ORDER BY u.unit_number ASC
            ");
            $stmt->execute($subjectIds);
            $unitsList = $stmt->fetchAll();
            echo "✓ Query 2 successful. Fetched " . count($unitsList) . " units.\n";

            echo "Query 3: Fetch attempts count\n";
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM lms_quiz_attempts a
                JOIN lms_quizzes q ON a.quiz_id = q.id
                JOIN lms_units u ON q.unit_id = u.id
                WHERE u.subject_id IN ($inQuery)
            ");
            $stmt->execute($subjectIds);
            $statsTotalAttempts = (int)$stmt->fetchColumn();
            echo "✓ Query 3 successful. Total attempts: $statsTotalAttempts\n";
        } else {
            echo "→ No subjects, skipping queries 2 & 3.\n";
        }
    } catch (Exception $e) {
        echo "❌ Query execution error: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "❌ Global Database Error: " . $e->getMessage() . "\n";
}
