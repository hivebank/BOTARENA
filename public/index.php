<?php
require_once __DIR__ . '/../config/config.php';

session_start();

// Redirect to dashboard if logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

// Otherwise redirect to login
header('Location: /login.php');
exit;
