<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/auth.php';
auditLog('logout');
session_destroy();
header('Location: /login.php');
exit;
