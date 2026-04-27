<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
requireLogin();
$u = getCurrentUser();
$db = getDB();
if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];
    $s = $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $s->execute([$id, $u['id']]);
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php'));
    exit;
}
if (isset($_GET['read_all'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$u['id']]);
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php'));
    exit;
}
header('Content-Type: application/json');
$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 20");
$notifs->execute([$u['id']]);
echo json_encode(['count'=>$notifs->rowCount(),'items'=>$notifs->fetchAll()]);