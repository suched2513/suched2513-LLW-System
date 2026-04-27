<?php
/**
 * director/approve.php — Approve/Reject request logic
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

checkRole('director');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $status = $_POST['status'];
    $note = $_POST['note'] ?? '';

    try {
        $pdo = getPdo();
        $stmt = $pdo->prepare("UPDATE project_requests SET status = ?, director_note = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $note, $id]);
        
        header('Location: ' . BASE_URL . '/director/pending.php?success=1');
        exit();
    } catch (Exception $e) {
        error_log($e->getMessage());
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}
