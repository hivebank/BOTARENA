<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Login
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'login') {
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        errorResponse('Email and password required', 400);
    }

    $users = mongoQuery('users', ['email' => $email]);

    if (empty($users)) {
        errorResponse('Invalid credentials', 401);
    }

    $user = $users[0];

    if (!verifyPassword($password, $user->password)) {
        errorResponse('Invalid credentials', 401);
    }

    // Create JWT
    $token = createJWT([
        'user_id' => (string)$user->_id,
        'email' => $user->email,
        'is_admin' => $user->is_admin ?? false
    ]);

    // Update last login
    mongoUpdate('users', ['_id' => $user->_id], ['last_login' => new MongoDB\BSON\UTCDateTime()]);

    successResponse([
        'token' => $token,
        'user' => [
            'id' => (string)$user->_id,
            'email' => $user->email,
            'username' => $user->username,
            'tier' => $user->tier ?? 'free',
            'is_admin' => $user->is_admin ?? false
        ]
    ], 'Login successful');
}

// Register
elseif ($method === 'POST' && isset($input['action']) && $input['action'] === 'register') {
    $email = sanitizeInput($input['email'] ?? '');
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($username) || empty($password)) {
        errorResponse('All fields required', 400);
    }

    if (!validateEmail($email)) {
        errorResponse('Invalid email format', 400);
    }

    if (strlen($password) < 8) {
        errorResponse('Password must be at least 8 characters', 400);
    }

    // Check if email exists
    $existingUsers = mongoQuery('users', ['email' => $email]);

    if (!empty($existingUsers)) {
        errorResponse('Email already registered', 409);
    }

    // Create user
    $result = mongoInsert('users', [
        'email' => $email,
        'username' => $username,
        'password' => hashPassword($password),
        'is_admin' => false,
        'tier' => 'free',
        'bots_count' => 0,
        'total_pnl' => 0,
        'active' => true,
        'api_key' => bin2hex(random_bytes(32))
    ]);

    if (!$result['success']) {
        errorResponse('Registration failed', 500);
    }

    successResponse(['user_id' => $result['id']], 'Registration successful');
}

// Verify token
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'verify') {
    $userId = getUserFromToken();

    if (!$userId) {
        errorResponse('Invalid or expired token', 401);
    }

    $users = mongoQuery('users', ['_id' => new MongoDB\BSON\ObjectId($userId)]);

    if (empty($users)) {
        errorResponse('User not found', 404);
    }

    $user = $users[0];

    successResponse([
        'user' => [
            'id' => (string)$user->_id,
            'email' => $user->email,
            'username' => $user->username,
            'tier' => $user->tier ?? 'free',
            'is_admin' => $user->is_admin ?? false
        ]
    ]);
}

else {
    errorResponse('Invalid action', 400);
}
