<?php
if (!isset($_SESSION['is_student']) || $_SESSION['is_student'] !== true) {
    header('Location: /student/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
