<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check admin auth
session_start();

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    errorResponse('Unauthorized - Admin access required', 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Kill all bots
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'kill_all_bots') {
    $result = mongoUpdate('bots', [], ['status' => 'stopped'], ['multi' => true]);

    mongoInsert('logs', [
        'type' => 'admin_kill_all_bots',
        'user_id' => $_SESSION['user_id'],
        'affected_count' => $result['modified']
    ]);

    successResponse(['stopped_count' => $result['modified']], 'All bots stopped');
}

// Toggle maintenance mode
elseif ($method === 'POST' && isset($input['action']) && $input['action'] === 'toggle_maintenance') {
    $settingsPath = __DIR__ . '/../config/settings.json';
    $settings = json_decode(file_get_contents($settingsPath), true);

    $settings['app']['maintenance_mode'] = !$settings['app']['maintenance_mode'];

    file_put_contents($settingsPath, json_encode($settings, JSON_PRETTY_PRINT));

    mongoInsert('logs', [
        'type' => 'admin_toggle_maintenance',
        'user_id' => $_SESSION['user_id'],
        'maintenance_mode' => $settings['app']['maintenance_mode']
    ]);

    successResponse([
        'maintenance_mode' => $settings['app']['maintenance_mode']
    ], 'Maintenance mode toggled');
}

// Stop specific bot
elseif ($method === 'POST' && isset($input['action']) && $input['action'] === 'stop_bot') {
    $botId = $input['bot_id'] ?? '';

    if (empty($botId)) {
        errorResponse('Bot ID required', 400);
    }

    $result = mongoUpdate('bots', ['_id' => new MongoDB\BSON\ObjectId($botId)], ['status' => 'stopped']);

    if (!$result['success']) {
        errorResponse('Failed to stop bot', 500);
    }

    mongoInsert('logs', [
        'type' => 'admin_stop_bot',
        'user_id' => $_SESSION['user_id'],
        'bot_id' => $botId
    ]);

    successResponse([], 'Bot stopped');
}

// Get system stats
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'stats') {
    $stats = [
        'total_users' => count(mongoQuery('users', [])),
        'total_bots' => count(mongoQuery('bots', [])),
        'active_bots' => count(mongoQuery('bots', ['status' => 'running'])),
        'total_trades' => count(mongoQuery('trades', [])),
        'total_payments' => count(mongoQuery('payments', ['status' => 'confirmed']))
    ];

    successResponse($stats);
}

else {
    errorResponse('Invalid action', 400);
}
