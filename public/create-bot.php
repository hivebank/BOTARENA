<?php
require_once __DIR__ . '/../config/config.php';

$userId = requireAuth();

// Get user data
$users = mongoQuery('users', ['_id' => new MongoDB\BSON\ObjectId($userId)]);
$user = !empty($users) ? $users[0] : null;

if (!$user) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

// Load settings
$settings = json_decode(file_get_contents(__DIR__ . '/../config/settings.json'), true);
$tier = $settings['pricing'][$user->tier . '_tier'] ?? $settings['pricing']['free_tier'];

// Get current bot count
$botCount = count(mongoQuery('bots', ['user_id' => $userId]));

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $symbol = sanitizeInput($_POST['symbol'] ?? '');
    $strategy = sanitizeInput($_POST['strategy'] ?? '');
    $capital = floatval($_POST['capital'] ?? 0);
    $leverage = intval($_POST['leverage'] ?? 1);
    $stopLoss = floatval($_POST['stop_loss'] ?? 2);
    $takeProfit = floatval($_POST['take_profit'] ?? 5);
    $mode = sanitizeInput($_POST['mode'] ?? 'paper');

    if (empty($name) || empty($symbol) || empty($strategy)) {
        $error = 'Please fill in all required fields';
    } elseif ($botCount >= $tier['max_bots']) {
        $error = "You've reached your bot limit ({$tier['max_bots']}). Upgrade to create more bots.";
    } elseif ($capital <= 0 || $capital > $tier['max_capital_usd']) {
        $error = "Capital must be between $1 and $" . number_format($tier['max_capital_usd']);
    } elseif ($leverage < 1 || $leverage > $settings['trading']['max_leverage']) {
        $error = "Leverage must be between 1 and {$settings['trading']['max_leverage']}";
    } else {
        // Verify strategy is available
        if (!isset($settings['strategies'][$strategy]) || !$settings['strategies'][$strategy]['enabled']) {
            $error = 'Invalid or disabled strategy';
        } elseif ($mode === 'live' && !in_array('live_trading', $tier['features'])) {
            $error = 'Live trading is not available in your tier. Upgrade to access this feature.';
        } else {
            // Create bot
            $result = mongoInsert('bots', [
                'user_id' => $userId,
                'name' => $name,
                'symbol' => $symbol,
                'strategy' => $strategy,
                'capital' => $capital,
                'leverage' => $leverage,
                'stop_loss_percent' => $stopLoss,
                'take_profit_percent' => $takeProfit,
                'mode' => $mode,
                'status' => 'stopped',
                'total_pnl' => 0,
                'trade_count' => 0,
                'win_count' => 0,
                'loss_count' => 0,
                'current_position' => null,
                'settings' => array_merge(
                    $settings['strategies'][$strategy]['parameters'],
                    [
                        'max_position_size' => $capital * $leverage,
                        'max_daily_loss' => $capital * ($settings['risk']['max_drawdown_percent'] / 100)
                    ]
                ),
                'active' => true
            ]);

            if ($result['success']) {
                // Log bot creation
                mongoInsert('logs', [
                    'type' => 'bot_created',
                    'user_id' => $userId,
                    'bot_id' => $result['id'],
                    'bot_name' => $name
                ]);

                // Update user bot count
                mongoUpdate('users', ['_id' => new MongoDB\BSON\ObjectId($userId)], [
                    'bots_count' => $botCount + 1
                ]);

                $success = 'Bot created successfully!';
                header('Location: /bots.php?created=1');
                exit;
            } else {
                $error = 'Failed to create bot. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Bot - Hyperliquid Trading SaaS</title>
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
        <h1>🤖 Create New Trading Bot</h1>
        <p class="text-secondary mb-3">Bots: <?php echo $botCount; ?> / <?php echo $tier['max_bots']; ?></p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="name">Bot Name *</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            required
                            placeholder="My Awesome Bot"
                            value="<?php echo htmlspecialchars($name ?? ''); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="symbol">Trading Symbol *</label>
                        <select id="symbol" name="symbol" required>
                            <option value="">Select Symbol</option>
                            <?php foreach ($settings['trading']['allowed_symbols'] as $sym): ?>
                                <option value="<?php echo $sym; ?>" <?php echo ($symbol ?? '') === $sym ? 'selected' : ''; ?>>
                                    <?php echo $sym; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="strategy">Strategy *</label>
                        <select id="strategy" name="strategy" required>
                            <option value="">Select Strategy</option>
                            <?php foreach ($settings['strategies'] as $stratName => $stratConfig): ?>
                                <?php if ($stratConfig['enabled'] && in_array($stratName, $tier['features'] ?: ['basic_strategies'])): ?>
                                    <option value="<?php echo $stratName; ?>" <?php echo ($strategy ?? '') === $stratName ? 'selected' : ''; ?>>
                                        <?php echo ucwords(str_replace('_', ' ', $stratName)); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-secondary">Available strategies depend on your tier</small>
                    </div>

                    <div class="form-group">
                        <label for="capital">Capital (USD) *</label>
                        <input
                            type="number"
                            id="capital"
                            name="capital"
                            required
                            min="1"
                            max="<?php echo $tier['max_capital_usd']; ?>"
                            step="0.01"
                            placeholder="1000.00"
                            value="<?php echo htmlspecialchars($capital ?? ''); ?>"
                        >
                        <small class="text-secondary">Max: $<?php echo number_format($tier['max_capital_usd']); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="leverage">Leverage</label>
                        <input
                            type="number"
                            id="leverage"
                            name="leverage"
                            min="1"
                            max="<?php echo $settings['trading']['max_leverage']; ?>"
                            value="<?php echo htmlspecialchars($leverage ?? 1); ?>"
                        >
                        <small class="text-secondary">1x to <?php echo $settings['trading']['max_leverage']; ?>x</small>
                    </div>

                    <div class="form-group">
                        <label for="stop_loss">Stop Loss (%)</label>
                        <input
                            type="number"
                            id="stop_loss"
                            name="stop_loss"
                            min="0.1"
                            max="10"
                            step="0.1"
                            value="<?php echo htmlspecialchars($stopLoss ?? 2); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="take_profit">Take Profit (%)</label>
                        <input
                            type="number"
                            id="take_profit"
                            name="take_profit"
                            min="0.1"
                            max="50"
                            step="0.1"
                            value="<?php echo htmlspecialchars($takeProfit ?? 5); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="mode">Trading Mode</label>
                        <select id="mode" name="mode">
                            <option value="paper">Paper Trading (Simulated)</option>
                            <?php if (in_array('live_trading', $tier['features'])): ?>
                                <option value="live">Live Trading (Real Money)</option>
                            <?php else: ?>
                                <option value="live" disabled>Live Trading (Upgrade Required)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="alert alert-warning mt-3">
                    <strong>⚠️ Important:</strong> Make sure you understand the risks involved in trading.
                    Always start with paper trading to test your strategy before going live.
                </div>

                <button type="submit" class="btn btn-primary mt-3">Create Bot</button>
                <a href="/bots.php" class="btn btn-secondary mt-3">Cancel</a>
            </form>
        </div>

        <!-- Strategy Info -->
        <div class="card mt-3">
            <div class="card-header">Available Strategies</div>
            <div class="card-body">
                <div class="grid grid-2">
                    <?php foreach ($settings['strategies'] as $stratName => $stratConfig): ?>
                        <?php if ($stratConfig['enabled']): ?>
                            <div>
                                <strong><?php echo ucwords(str_replace('_', ' ', $stratName)); ?></strong>
                                <p class="text-secondary" style="font-size: 0.875rem;">
                                    <?php
                                    $descriptions = [
                                        'momentum' => 'Follows price trends and momentum indicators',
                                        'mean_reversion' => 'Trades based on price returning to average',
                                        'grid_trading' => 'Places buy/sell orders at regular intervals',
                                        'ai_optimizer' => 'AI-powered predictions using LSTM models',
                                        'breakout' => 'Trades on price breakouts with volume confirmation'
                                    ];
                                    echo $descriptions[$stratName] ?? 'Advanced trading strategy';
                                    ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
