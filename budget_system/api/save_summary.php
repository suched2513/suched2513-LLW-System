<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['llw_role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = $_POST;
$files = $_FILES;

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // Handle Image Uploads
    $uploadDir = __DIR__ . '/../../uploads/sbms/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $imagePaths = [null, null, null, null];
    for ($i = 1; $i <= 4; $i++) {
        $fileKey = 'image' . $i;
        if (isset($files[$fileKey]) && $files[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($files[$fileKey]['name'], PATHINFO_EXTENSION);
            $fileName = 'summary_' . $input['disbursement_id'] . '_' . $i . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($files[$fileKey]['tmp_name'], $targetPath)) {
                $imagePaths[$i-1] = 'uploads/sbms/' . $fileName;
            }
        }
    }

    // Insert into sbms_summaries
    $stmt = $pdo->prepare("
        INSERT INTO sbms_summaries 
        (disbursement_id, project_id, activity_id, project_type, objectives, 
         eval_objective, eval_cooperation, eval_interest, eval_benefit, eval_success, 
         problems, suggestions, conclusion, image1_path, image2_path, image3_path, image4_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $input['disbursement_id'],
        $input['project_id'],
        $input['activity_id'],
        $input['project_type'],
        $input['objectives'],
        $input['eval_objective'],
        $input['eval_cooperation'],
        $input['eval_interest'],
        $input['eval_benefit'],
        $input['eval_success'],
        $input['problems'],
        $input['suggestions'],
        $input['conclusion'],
        $imagePaths[0],
        $imagePaths[1],
        $imagePaths[2],
        $imagePaths[3]
    ]);

    // Update Disbursement Status
    $stmt = $pdo->prepare("UPDATE sbms_disbursements SET status = 'completed' WHERE id = ?");
    $stmt->execute([$input['disbursement_id']]);

    $pdo->commit();
    
    header('Location: ../index.php?summary_success=1');
    exit;

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    header('Location: ../summary_form.php?id=' . $input['disbursement_id'] . '&error=' . urlencode($e->getMessage()));
    exit;
}
