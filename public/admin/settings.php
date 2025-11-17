<?php
require_once __DIR__ . '/../../config/config.php';

requireAdmin();

$settings = json_decode(file_get_contents(__DIR__ . '/../../config/settings.json'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settingsPath = __DIR__ . '/../../config/settings.json';
    file_put_contents($settingsPath, json_encode($_POST['settings'], JSON_PRETTY_PRINT));

    mongoInsert('logs', [
        'type' => 'settings_updated',
        'user_id' => $_SESSION['user_id'],
        'changes' => $_POST['settings']
    ]);

    header('Location: /admin/settings.php?updated=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin</title>
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
        <h1>⚙️ System Settings</h1>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Settings updated successfully!</div>
        <?php endif; ?>

        <form method="POST">
            <div class="card">
                <div class="card-header">Trading Settings</div>
                <div class="card-body">
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label>Max Leverage</label>
                            <input type="number" name="settings[trading][max_leverage]" value="<?php echo $settings['trading']['max_leverage']; ?>" min="1" max="100">
                        </div>
                        <div class="form-group">
                            <label>Max Position Size (USD)</label>
                            <input type="number" name="settings[trading][max_position_size_usd]" value="<?php echo $settings['trading']['max_position_size_usd']; ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Risk Settings</div>
                <div class="card-body">
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label>Max Drawdown (%)</label>
                            <input type="number" name="settings[risk][max_drawdown_percent]" value="<?php echo $settings['risk']['max_drawdown_percent']; ?>" step="0.1">
                        </div>
                        <div class="form-group">
                            <label>Stop Loss (%)</label>
                            <input type="number" name="settings[risk][stop_loss_percent]" value="<?php echo $settings['risk']['stop_loss_percent']; ?>" step="0.1">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Current Settings (JSON)</div>
                <div class="card-body">
                    <pre><?php echo json_encode($settings, JSON_PRETTY_PRINT); ?></pre>
                </div>
            </div>

            <button type="submit" name="update_settings" class="btn btn-primary mt-3">Save Settings</button>
        </form>
    </div>
</body>
</html>
