<?php
require_once __DIR__ . '/../../config/config.php';

requireAdmin();

// Get stats
$totalUsers = count(mongoQuery('users', []));
$totalBots = count(mongoQuery('bots', []));
$activeBots = count(mongoQuery('bots', ['status' => 'running']));
$totalTrades = count(mongoQuery('trades', []));

// Get recent activity
$recentLogs = mongoQuery('logs', [], ['sort' => ['created_at' => -1], 'limit' => 20]);

// Calculate total revenue (from payments)
$payments = mongoQuery('payments', ['status' => 'confirmed']);
$totalRevenue = array_sum(array_map(fn($p) => $p->amount ?? 0, $payments));

// Load settings
$settings = json_decode(file_get_contents(__DIR__ . '/../../config/settings.json'), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
        <h1>🔧 Admin Dashboard</h1>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalBots; ?></div>
                <div class="stat-label">Total Bots</div>
            </div>
            <div class="stat-card positive">
                <div class="stat-value"><?php echo $activeBots; ?></div>
                <div class="stat-label">Active Bots</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($totalRevenue, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <div class="grid grid-2 mt-4">
            <!-- System Status -->
            <div class="card">
                <div class="card-header">System Status</div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong>Maintenance Mode:</strong>
                        <span class="badge badge-<?php echo $settings['app']['maintenance_mode'] ? 'warning' : 'success'; ?>">
                            <?php echo $settings['app']['maintenance_mode'] ? 'ON' : 'OFF'; ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <strong>Environment:</strong> <?php echo $settings['app']['environment']; ?>
                    </div>
                    <div class="mb-2">
                        <strong>Version:</strong> <?php echo $settings['app']['version']; ?>
                    </div>
                    <div class="mb-2">
                        <strong>Total Trades:</strong> <?php echo $totalTrades; ?>
                    </div>

                    <div class="flex gap-2 mt-3">
                        <button class="btn btn-danger btn-sm" onclick="killAllBots()">
                            🛑 Kill All Bots
                        </button>
                        <button class="btn btn-warning btn-sm" onclick="toggleMaintenance()">
                            🔧 Toggle Maintenance
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">Recent Activity</div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($recentLogs as $log): ?>
                        <div class="mb-2" style="border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                            <div>
                                <span class="badge badge-info"><?php echo htmlspecialchars($log->type ?? 'N/A'); ?></span>
                                <?php if (isset($log->user_id)): ?>
                                    <span class="text-secondary">User: <?php echo substr($log->user_id, 0, 8); ?>...</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-secondary" style="font-size: 0.75rem;">
                                <?php echo $log->created_at ? $log->created_at->toDateTime()->format('Y-m-d H:i:s') : 'N/A'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function killAllBots() {
            if (!confirm('Are you sure you want to stop ALL bots? This cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('/api/admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'kill_all_bots' })
                });

                const data = await response.json();

                if (data.success) {
                    alert('All bots stopped successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function toggleMaintenance() {
            try {
                const response = await fetch('/api/admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle_maintenance' })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Maintenance mode toggled');
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
