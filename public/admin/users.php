<?php
require_once __DIR__ . '/../../config/config.php';

requireAdmin();

$users = mongoQuery('users', []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin</title>
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
        <h1>👥 User Management</h1>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Tier</th>
                        <th>Bots</th>
                        <th>Total P&L</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user->username); ?></td>
                            <td><?php echo htmlspecialchars($user->email); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo strtoupper($user->tier ?? 'free'); ?>
                                </span>
                            </td>
                            <td><?php echo $user->bots_count ?? 0; ?></td>
                            <td class="<?php echo ($user->total_pnl ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                $<?php echo number_format($user->total_pnl ?? 0, 2); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo ($user->active ?? true) ? 'success' : 'danger'; ?>">
                                    <?php echo ($user->active ?? true) ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo $user->created_at ? $user->created_at->toDateTime()->format('M d, Y') : 'N/A'; ?></td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="viewUser('<?php echo $user->_id; ?>')">View</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function viewUser(userId) {
            // In production, this would open a detailed user view
            alert('User ID: ' + userId);
        }
    </script>
</body>
</html>
