<?php
require_once __DIR__ . '/../config/config.php';

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $username = sanitizeInput($_POST['username'] ?? '');

    if (empty($email) || empty($password) || empty($confirmPassword) || empty($username)) {
        $error = 'Please fill in all fields';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        // Check if email already exists
        $existingUsers = mongoQuery('users', ['email' => $email]);

        if (!empty($existingUsers)) {
            $error = 'Email already registered';
        } else {
            // Check if username already exists
            $existingUsername = mongoQuery('users', ['username' => $username]);

            if (!empty($existingUsername)) {
                $error = 'Username already taken';
            } else {
                // Create new user
                $result = mongoInsert('users', [
                    'email' => $email,
                    'username' => $username,
                    'password' => hashPassword($password),
                    'is_admin' => false,
                    'tier' => 'free',
                    'bots_count' => 0,
                    'total_pnl' => 0,
                    'login_attempts' => 0,
                    'active' => true,
                    'api_key' => bin2hex(random_bytes(32)),
                    'settings' => [
                        'notifications_enabled' => true,
                        'email_alerts' => true
                    ]
                ]);

                if ($result['success']) {
                    // Log registration
                    mongoInsert('logs', [
                        'type' => 'registration',
                        'user_id' => $result['id'],
                        'email' => $email,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);

                    header('Location: /login.php?registered=1');
                    exit;
                } else {
                    $error = 'Registration failed. Please try again.';
                }
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
    <title>Register - Hyperliquid Trading SaaS</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>🚀 Register</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        value="<?php echo htmlspecialchars($username ?? ''); ?>"
                        autocomplete="username"
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        value="<?php echo htmlspecialchars($email ?? ''); ?>"
                        autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password (min 8 characters)</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="btn btn-primary">Register</button>
            </form>

            <p class="text-center mt-3">
                Already have an account? <a href="/login.php">Login here</a>
            </p>
        </div>
    </div>

    <script>
        // Client-side password validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;

            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>
