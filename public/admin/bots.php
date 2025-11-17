<?php
require_once __DIR__ . '/../../config/config.php';

requireAdmin();

$bots = mongoQuery('bots', [], ['sort' => ['created_at' => -1]]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bots - Admin</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <nav class="container">
            <a href="/admin/" class="logo">⚡ HyperTrade Admin</a>
            <ul class="nav-links">
                <li><a href="/admin/">Dashboard</a></li>
                <li><a href="/admin/users.php">Users</a></li>
                <li><a href="/admin/bots.php">Bots</a></li>
                <li><a href="/admin/settings.php">Settings</a></li>
                <li><a href="/dashboard.php">User View</a></li>
                <li><a href="/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="container dashboard">
        <h1>🤖 Bot Management</h1>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>User ID</th>
                        <th>Symbol</th>
                        <th>Strategy</th>
                        <th>Capital</th>
                        <th>Status</th>
                        <th>P&L</th>
                        <th>Trades</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bots as $bot): ?>
                        <?php if ($bot->active ?? true): ?>
                            <tr>
                                <td>
                                    <span class="status-indicator <?php echo $bot->status; ?>"></span>
                                    <?php echo htmlspecialchars($bot->name); ?>
                                </td>
                                <td><?php echo substr($bot->user_id, 0, 8); ?>...</td>
                                <td><?php echo htmlspecialchars($bot->symbol); ?></td>
                                <td><?php echo htmlspecialchars($bot->strategy); ?></td>
                                <td>$<?php echo number_format($bot->capital, 2); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $bot->status === 'running' ? 'success' : ($bot->status === 'stopped' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($bot->status); ?>
                                    </span>
                                </td>
                                <td class="<?php echo ($bot->total_pnl ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    $<?php echo number_format($bot->total_pnl ?? 0, 2); ?>
                                </td>
                                <td><?php echo $bot->trade_count ?? 0; ?></td>
                                <td>
                                    <button class="btn btn-danger btn-sm" onclick="stopBot('<?php echo $bot->_id; ?>')">Stop</button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        async function stopBot(botId) {
            if (!confirm('Stop this bot?')) return;

            try {
                const response = await fetch('/api/admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'stop_bot', bot_id: botId })
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>
