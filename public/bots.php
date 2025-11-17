<?php
require_once __DIR__ . '/../config/config.php';

$userId = requireAuth();

// Get user's bots
$bots = mongoQuery('bots', ['user_id' => $userId]);

// Handle bot actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['bot_id'])) {
    $botId = $_POST['bot_id'];
    $action = $_POST['action'];

    $botData = mongoQuery('bots', ['_id' => new MongoDB\BSON\ObjectId($botId), 'user_id' => $userId]);

    if (!empty($botData)) {
        switch ($action) {
            case 'start':
                mongoUpdate('bots', ['_id' => new MongoDB\BSON\ObjectId($botId)], ['status' => 'running']);
                mongoInsert('logs', ['type' => 'bot_started', 'user_id' => $userId, 'bot_id' => $botId]);
                break;

            case 'stop':
                mongoUpdate('bots', ['_id' => new MongoDB\BSON\ObjectId($botId)], ['status' => 'stopped']);
                mongoInsert('logs', ['type' => 'bot_stopped', 'user_id' => $userId, 'bot_id' => $botId]);
                break;

            case 'pause':
                mongoUpdate('bots', ['_id' => new MongoDB\BSON\ObjectId($botId)], ['status' => 'paused']);
                mongoInsert('logs', ['type' => 'bot_paused', 'user_id' => $userId, 'bot_id' => $botId]);
                break;

            case 'delete':
                mongoUpdate('bots', ['_id' => new MongoDB\BSON\ObjectId($botId)], ['active' => false, 'status' => 'stopped']);
                mongoInsert('logs', ['type' => 'bot_deleted', 'user_id' => $userId, 'bot_id' => $botId]);
                break;
        }

        header('Location: /bots.php?updated=1');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bots - Hyperliquid Trading SaaS</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <nav class="container">
            <a href="/dashboard.php" class="logo">⚡ HyperTrade</a>
            <ul class="nav-links">
                <li><a href="/dashboard.php">Dashboard</a></li>
                <li><a href="/bots.php">My Bots</a></li>
                <li><a href="/create-bot.php">Create Bot</a></li>
                <li><a href="/pay.php">Upgrade</a></li>
                <?php if ($_SESSION['is_admin'] ?? false): ?>
                    <li><a href="/admin/">Admin</a></li>
                <?php endif; ?>
                <li><a href="/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="container dashboard">
        <div class="flex-between mb-3">
            <h1>🤖 My Trading Bots</h1>
            <a href="/create-bot.php" class="btn btn-primary">+ Create New Bot</a>
        </div>

        <?php if (isset($_GET['created'])): ?>
            <div class="alert alert-success">Bot created successfully!</div>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Bot updated successfully!</div>
        <?php endif; ?>

        <?php if (empty($bots)): ?>
            <div class="card">
                <div class="card-body text-center">
                    <h2>No bots yet</h2>
                    <p class="text-secondary">Create your first trading bot to get started!</p>
                    <a href="/create-bot.php" class="btn btn-primary mt-2">Create Bot</a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($bots as $bot): ?>
                    <?php if ($bot->active ?? true): ?>
                        <div class="card">
                            <div class="card-header flex-between">
                                <span>
                                    <span class="status-indicator <?php echo $bot->status; ?>"></span>
                                    <?php echo htmlspecialchars($bot->name); ?>
                                </span>
                                <span class="badge badge-<?php echo $bot->status === 'running' ? 'success' : ($bot->status === 'stopped' ? 'danger' : 'warning'); ?>">
                                    <?php echo ucfirst($bot->status); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong>Symbol:</strong> <?php echo htmlspecialchars($bot->symbol); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Strategy:</strong> <?php echo ucwords(str_replace('_', ' ', $bot->strategy)); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Capital:</strong> $<?php echo number_format($bot->capital, 2); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Leverage:</strong> <?php echo $bot->leverage; ?>x
                                </div>
                                <div class="mb-2">
                                    <strong>Mode:</strong> <?php echo ucfirst($bot->mode); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Total P&L:</strong>
                                    <span class="<?php echo ($bot->total_pnl ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        $<?php echo number_format($bot->total_pnl ?? 0, 2); ?>
                                    </span>
                                </div>
                                <div class="mb-2">
                                    <strong>Trades:</strong> <?php echo $bot->trade_count ?? 0; ?>
                                    (<?php echo $bot->win_count ?? 0; ?>W / <?php echo $bot->loss_count ?? 0; ?>L)
                                </div>

                                <div class="flex gap-1 mt-3">
                                    <?php if ($bot->status === 'stopped' || $bot->status === 'paused'): ?>
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="bot_id" value="<?php echo $bot->_id; ?>">
                                            <input type="hidden" name="action" value="start">
                                            <button type="submit" class="btn btn-success btn-sm" style="width: 100%;">▶ Start</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($bot->status === 'running'): ?>
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="bot_id" value="<?php echo $bot->_id; ?>">
                                            <input type="hidden" name="action" value="pause">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">⏸ Pause</button>
                                        </form>

                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="bot_id" value="<?php echo $bot->_id; ?>">
                                            <input type="hidden" name="action" value="stop">
                                            <button type="submit" class="btn btn-danger btn-sm" style="width: 100%;">⏹ Stop</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" style="flex: 1;" onsubmit="return confirm('Are you sure you want to delete this bot?');">
                                        <input type="hidden" name="bot_id" value="<?php echo $bot->_id; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-danger btn-sm" style="width: 100%;">🗑 Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh every 15 seconds
        setTimeout(function() {
            location.reload();
        }, 15000);
    </script>
</body>
</html>
