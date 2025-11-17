<?php
require_once __DIR__ . '/../config/config.php';

session_start();

// Log logout event
if (isset($_SESSION['user_id'])) {
    mongoInsert('logs', [
        'type' => 'logout',
        'user_id' => $_SESSION['user_id'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: /login.php');
exit;
