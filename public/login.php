<?php
require_once __DIR__ . '/../config/config.php';

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } else {
        // Find user in database
        $users = mongoQuery('users', ['email' => $email]);

        if (empty($users)) {
            $error = 'Invalid credentials';
        } else {
            $user = $users[0];

            // Check login attempts
            $attempts = $user->login_attempts ?? 0;
            $lastAttempt = $user->last_login_attempt ?? null;

            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockoutTime = $lastAttempt ? $lastAttempt->toDateTime()->getTimestamp() + LOGIN_TIMEOUT : 0;
                if (time() < $lockoutTime) {
                    $remainingTime = ceil(($lockoutTime - time()) / 60);
                    $error = "Too many failed attempts. Try again in {$remainingTime} minutes.";
                } else {
                    // Reset attempts
                    mongoUpdate('users', ['_id' => $user->_id], ['login_attempts' => 0]);
                    $attempts = 0;
                }
            }

            if (empty($error)) {
                if (verifyPassword($password, $user->password)) {
                    // Successful login
                    $_SESSION['user_id'] = (string)$user->_id;
                    $_SESSION['email'] = $user->email;
                    $_SESSION['is_admin'] = $user->is_admin ?? false;

                    // Reset login attempts
                    mongoUpdate('users', ['_id' => $user->_id], [
                        'login_attempts' => 0,
                        'last_login' => new MongoDB\BSON\UTCDateTime()
                    ]);

                    // Log login
                    mongoInsert('logs', [
                        'type' => 'login',
                        'user_id' => (string)$user->_id,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);

                    header('Location: /dashboard.php');
                    exit;
                } else {
                    // Failed login
                    mongoUpdate('users', ['_id' => $user->_id], [
                        'login_attempts' => $attempts + 1,
                        'last_login_attempt' => new MongoDB\BSON\UTCDateTime()
                    ]);

                    $error = 'Invalid credentials';
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
    <title>Login - Hyperliquid Trading SaaS</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>🚀 Login</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">Registration successful! Please login.</div>
            <?php endif; ?>

            <form method="POST" action="">
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
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit" class="btn btn-primary">Login</button>
            </form>

            <p class="text-center mt-3">
                Don't have an account? <a href="/register.php">Register here</a>
            </p>
        </div>
    </div>
</body>
</html>
