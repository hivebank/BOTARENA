<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$userId = getUserFromToken();

if (!$userId) {
    errorResponse('Unauthorized', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Get user's bots
if ($method === 'GET' && !isset($_GET['action'])) {
    $bots = mongoQuery('bots', ['user_id' => $userId, 'active' => true]);

    $result = array_map(function($bot) {
        return [
            'id' => (string)$bot->_id,
            'name' => $bot->name,
            'symbol' => $bot->symbol,
            'strategy' => $bot->strategy,
            'capital' => $bot->capital,
            'leverage' => $bot->leverage,
            'status' => $bot->status,
            'total_pnl' => $bot->total_pnl ?? 0,
            'trade_count' => $bot->trade_count ?? 0,
            'win_count' => $bot->win_count ?? 0,
            'loss_count' => $bot->loss_count ?? 0,
            'created_at' => $bot->created_at ? $bot->created_at->toDateTime()->format('c') : null
        ];
    }, $bots);

    successResponse(['bots' => $result]);
}

// Get stats
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'stats') {
    $bots = mongoQuery('bots', ['user_id' => $userId, 'active' => true]);

    $totalBots = count($bots);
    $activeBots = count(array_filter($bots, fn($b) => $b->status === 'running'));
    $totalPnl = array_sum(array_map(fn($b) => $b->total_pnl ?? 0, $bots));
    $totalTrades = array_sum(array_map(fn($b) => $b->trade_count ?? 0, $bots));

    successResponse([
        'total_bots' => $totalBots,
        'active_bots' => $activeBots,
        'total_pnl' => $totalPnl,
        'total_trades' => $totalTrades
    ]);
}

// Create bot
elseif ($method === 'POST' && isset($input['action']) && $input['action'] === 'create') {
    $name = sanitizeInput($input['name'] ?? '');
    $symbol = sanitizeInput($input['symbol'] ?? '');
    $strategy = sanitizeInput($input['strategy'] ?? '');
    $capital = floatval($input['capital'] ?? 0);
    $leverage = intval($input['leverage'] ?? 1);

    if (empty($name) || empty($symbol) || empty($strategy) || $capital <= 0) {
        errorResponse('Missing or invalid parameters', 400);
    }

    $result = mongoInsert('bots', [
        'user_id' => $userId,
        'name' => $name,
        'symbol' => $symbol,
        'strategy' => $strategy,
        'capital' => $capital,
        'leverage' => $leverage,
        'status' => 'stopped',
        'total_pnl' => 0,
        'trade_count' => 0,
        'win_count' => 0,
        'loss_count' => 0,
        'active' => true
    ]);

    if (!$result['success']) {
        errorResponse('Failed to create bot', 500);
    }

    successResponse(['bot_id' => $result['id']], 'Bot created successfully');
}

// Update bot status
elseif ($method === 'PUT' && isset($input['action']) && $input['action'] === 'update_status') {
    $botId = $input['bot_id'] ?? '';
    $status = $input['status'] ?? '';

    if (empty($botId) || empty($status)) {
        errorResponse('Missing parameters', 400);
    }

    $result = mongoUpdate('bots', [
        '_id' => new MongoDB\BSON\ObjectId($botId),
        'user_id' => $userId
    ], ['status' => $status]);

    if (!$result['success'] || $result['modified'] === 0) {
        errorResponse('Failed to update bot', 500);
    }

    successResponse([], 'Bot status updated');
}

// Delete bot
elseif ($method === 'DELETE') {
    $botId = $input['bot_id'] ?? '';

    if (empty($botId)) {
        errorResponse('Bot ID required', 400);
    }

    $result = mongoUpdate('bots', [
        '_id' => new MongoDB\BSON\ObjectId($botId),
        'user_id' => $userId
    ], ['active' => false, 'status' => 'stopped']);

    if (!$result['success']) {
        errorResponse('Failed to delete bot', 500);
    }

    successResponse([], 'Bot deleted successfully');
}

else {
    errorResponse('Invalid action', 400);
}
