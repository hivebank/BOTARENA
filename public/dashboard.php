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

// Get user's bots
$bots = mongoQuery('bots', ['user_id' => $userId]);

// Calculate stats
$totalBots = count($bots);
$activeBots = count(array_filter($bots, fn($bot) => $bot->status === 'running'));
$totalPnl = 0;
$totalTrades = 0;

foreach ($bots as $bot) {
    $totalPnl += $bot->total_pnl ?? 0;
    $totalTrades += $bot->trade_count ?? 0;
}

// Get recent trades
$trades = mongoQuery('trades', ['user_id' => $userId], ['sort' => ['created_at' => -1], 'limit' => 10]);

// Load settings
$settings = json_decode(file_get_contents(__DIR__ . '/../config/settings.json'), true);
$tier = $settings['pricing'][$user->tier . '_tier'] ?? $settings['pricing']['free_tier'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hyperliquid Trading SaaS</title>
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
        <h1>Welcome back, <?php echo htmlspecialchars($user->username); ?>! 👋</h1>
        <p class="text-secondary mb-3">Current Tier: <span class="badge badge-info"><?php echo strtoupper($user->tier); ?></span></p>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalBots; ?> / <?php echo $tier['max_bots']; ?></div>
                <div class="stat-label">Active Bots</div>
            </div>

            <div class="stat-card <?php echo $totalPnl >= 0 ? 'positive' : 'negative'; ?>">
                <div class="stat-value">$<?php echo number_format($totalPnl, 2); ?></div>
                <div class="stat-label">Total P&L</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo $activeBots; ?></div>
                <div class="stat-label">Running Now</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo $totalTrades; ?></div>
                <div class="stat-label">Total Trades</div>
            </div>
        </div>

        <div class="grid grid-2">
            <!-- Bots Overview -->
            <div class="card">
                <div class="card-header">Your Bots</div>
                <div class="card-body">
                    <?php if (empty($bots)): ?>
                        <p class="text-secondary">No bots created yet.</p>
                        <a href="/create-bot.php" class="btn btn-primary mt-2">Create Your First Bot</a>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>P&L</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($bots, 0, 5) as $bot): ?>
                                    <tr>
                                        <td>
                                            <span class="status-indicator <?php echo $bot->status; ?>"></span>
                                            <?php echo htmlspecialchars($bot->name); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $bot->status === 'running' ? 'success' : ($bot->status === 'stopped' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($bot->status); ?>
                                            </span>
                                        </td>
                                        <td class="<?php echo ($bot->total_pnl ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            $<?php echo number_format($bot->total_pnl ?? 0, 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <a href="/bots.php" class="btn btn-secondary btn-sm mt-2">View All Bots</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Trades -->
            <div class="card">
                <div class="card-header">Recent Trades</div>
                <div class="card-body">
                    <?php if (empty($trades)): ?>
                        <p class="text-secondary">No trades yet.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Symbol</th>
                                    <th>Side</th>
                                    <th>P&L</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trades as $trade): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($trade->symbol ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $trade->side === 'buy' ? 'success' : 'danger'; ?>">
                                                <?php echo strtoupper($trade->side ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td class="<?php echo ($trade->pnl ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            $<?php echo number_format($trade->pnl ?? 0, 2); ?>
                                        </td>
                                        <td><?php echo $trade->created_at ? $trade->created_at->toDateTime()->format('M d, H:i') : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Account Limits -->
        <div class="card mt-3">
            <div class="card-header">Account Limits (<?php echo strtoupper($user->tier); ?> Tier)</div>
            <div class="card-body">
                <div class="grid grid-3">
                    <div>
                        <strong>Max Bots:</strong> <?php echo $tier['max_bots']; ?>
                    </div>
                    <div>
                        <strong>Max Capital:</strong> $<?php echo number_format($tier['max_capital_usd']); ?>
                    </div>
                    <div>
                        <strong>Features:</strong> <?php echo count($tier['features']); ?> enabled
                    </div>
                </div>
                <?php if ($user->tier === 'free'): ?>
                    <a href="/pay.php" class="btn btn-primary mt-2">Upgrade for More Features</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);

        // Real-time updates via API polling
        async function updateStats() {
            try {
                const response = await fetch('/api/bots.php?action=stats');
                const data = await response.json();

                if (data.success) {
                    // Update stats without page reload
                    document.querySelector('.stats-grid').innerHTML = `
                        <div class="stat-card">
                            <div class="stat-value">${data.total_bots} / <?php echo $tier['max_bots']; ?></div>
                            <div class="stat-label">Active Bots</div>
                        </div>
                        <div class="stat-card ${data.total_pnl >= 0 ? 'positive' : 'negative'}">
                            <div class="stat-value">$${data.total_pnl.toFixed(2)}</div>
                            <div class="stat-label">Total P&L</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${data.active_bots}</div>
                            <div class="stat-label">Running Now</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${data.total_trades}</div>
                            <div class="stat-label">Total Trades</div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Failed to update stats:', error);
            }
        }

        // Update every 10 seconds
        setInterval(updateStats, 10000);
    </script>
</body>
</html>
