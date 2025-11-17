<?php
/**
 * Main Configuration File
 * All application settings and constants
 */

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Database Configuration
define('MONGO_URI', getenv('MONGO_URI') ?: 'mongodb://localhost:27017');
define('MONGO_DB', 'hyperliquid_saas');

// JWT Configuration
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'CHANGE_THIS_IN_PRODUCTION_' . bin2hex(random_bytes(32)));
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 86400 * 7); // 7 days

// Session Configuration
define('SESSION_LIFETIME', 86400); // 24 hours
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params(SESSION_LIFETIME);

// Paths
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('API_PATH', BASE_PATH . '/api');
define('PYTHON_PATH', BASE_PATH . '/python');

// API Configuration
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 100); // requests per minute

// Payment Configuration
define('SOL_WALLET', getenv('SOL_WALLET') ?: 'YOUR_SOL_WALLET_ADDRESS');
define('ETH_WALLET', getenv('ETH_WALLET') ?: 'YOUR_ETH_WALLET_ADDRESS');
define('SOL_RPC', getenv('SOL_RPC') ?: 'https://api.mainnet-beta.solana.com');
define('ETH_RPC', getenv('ETH_RPC') ?: 'https://eth.llamarpc.com');

// Pricing (in USD)
define('PRICE_PER_BOT_MONTH', 49.99);
define('PRICE_PER_BOT_YEAR', 499.99);

// Bot Configuration
define('MAX_BOTS_PER_USER', 10);
define('MAX_BOTS_FREE_TIER', 1);

// Hyperliquid API
define('HYPERLIQUID_API_URL', 'https://api.hyperliquid.xyz');
define('HYPERLIQUID_WS_URL', 'wss://api.hyperliquid.xyz/ws');

// Security
define('BCRYPT_COST', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes

// Logging
define('LOG_PATH', BASE_PATH . '/logs');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// CORS Settings
define('CORS_ORIGINS', ['http://localhost', 'https://yourdomain.com']);

// Admin Credentials (Change in production!)
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@localhost');
define('ADMIN_DEFAULT_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');

/**
 * MongoDB Connection Helper
 */
function getMongoClient() {
    static $client = null;

    if ($client === null) {
        try {
            $client = new MongoDB\Driver\Manager(MONGO_URI);
        } catch (Exception $e) {
            error_log('MongoDB Connection Error: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }

    return $client;
}

/**
 * Get MongoDB Collection
 */
function getCollection($collectionName) {
    $manager = getMongoClient();
    $namespace = MONGO_DB . '.' . $collectionName;
    return ['manager' => $manager, 'namespace' => $namespace];
}

/**
 * Execute MongoDB Query
 */
function mongoQuery($collection, $filter = [], $options = []) {
    $coll = getCollection($collection);
    $query = new MongoDB\Driver\Query($filter, $options);

    try {
        $cursor = $coll['manager']->executeQuery($coll['namespace'], $query);
        return iterator_to_array($cursor);
    } catch (Exception $e) {
        error_log('MongoDB Query Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Insert MongoDB Document
 */
function mongoInsert($collection, $document) {
    $coll = getCollection($collection);
    $bulk = new MongoDB\Driver\BulkWrite;

    if (!isset($document['created_at'])) {
        $document['created_at'] = new MongoDB\BSON\UTCDateTime();
    }
    $document['updated_at'] = new MongoDB\BSON\UTCDateTime();

    $id = $bulk->insert($document);

    try {
        $result = $coll['manager']->executeBulkWrite($coll['namespace'], $bulk);
        return ['success' => true, 'id' => (string)$id];
    } catch (Exception $e) {
        error_log('MongoDB Insert Error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update MongoDB Document
 */
function mongoUpdate($collection, $filter, $update, $options = []) {
    $coll = getCollection($collection);
    $bulk = new MongoDB\Driver\BulkWrite;

    if (!isset($update['$set'])) {
        $update = ['$set' => $update];
    }
    $update['$set']['updated_at'] = new MongoDB\BSON\UTCDateTime();

    $bulk->update($filter, $update, $options);

    try {
        $result = $coll['manager']->executeBulkWrite($coll['namespace'], $bulk);
        return ['success' => true, 'modified' => $result->getModifiedCount()];
    } catch (Exception $e) {
        error_log('MongoDB Update Error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete MongoDB Document
 */
function mongoDelete($collection, $filter, $options = ['limit' => 1]) {
    $coll = getCollection($collection);
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->delete($filter, $options);

    try {
        $result = $coll['manager']->executeBulkWrite($coll['namespace'], $bulk);
        return ['success' => true, 'deleted' => $result->getDeletedCount()];
    } catch (Exception $e) {
        error_log('MongoDB Delete Error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * JWT Helper Functions
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function createJWT($payload) {
    $header = ['typ' => 'JWT', 'alg' => JWT_ALGORITHM];

    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRY;

    $segments = [];
    $segments[] = base64UrlEncode(json_encode($header));
    $segments[] = base64UrlEncode(json_encode($payload));

    $signing_input = implode('.', $segments);
    $signature = hash_hmac('sha256', $signing_input, JWT_SECRET, true);
    $segments[] = base64UrlEncode($signature);

    return implode('.', $segments);
}

function verifyJWT($jwt) {
    $tokens = explode('.', $jwt);

    if (count($tokens) !== 3) {
        return false;
    }

    list($header64, $payload64, $signature64) = $tokens;

    $signature = base64UrlDecode($signature64);
    $signing_input = $header64 . '.' . $payload64;

    $valid = hash_equals(
        $signature,
        hash_hmac('sha256', $signing_input, JWT_SECRET, true)
    );

    if (!$valid) {
        return false;
    }

    $payload = json_decode(base64UrlDecode($payload64), true);

    if (!isset($payload['exp']) || $payload['exp'] < time()) {
        return false;
    }

    return $payload;
}

/**
 * Security Helpers
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Response Helpers
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function errorResponse($message, $status = 400) {
    jsonResponse(['error' => $message, 'success' => false], $status);
}

function successResponse($data = [], $message = 'Success') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

/**
 * Authentication Helpers
 */
function requireAuth() {
    session_start();

    if (!isset($_SESSION['user_id'])) {
        if (php_sapi_name() === 'cli') {
            return false;
        }
        header('Location: /login.php');
        exit;
    }

    return $_SESSION['user_id'];
}

function requireAdmin() {
    session_start();

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        if (php_sapi_name() === 'cli') {
            return false;
        }
        header('Location: /login.php');
        exit;
    }

    return $_SESSION['user_id'];
}

function getAuthToken() {
    $headers = getallheaders();

    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return $matches[1];
        }
    }

    return null;
}

function getUserFromToken() {
    $token = getAuthToken();

    if (!$token) {
        return null;
    }

    $payload = verifyJWT($token);

    if (!$payload || !isset($payload['user_id'])) {
        return null;
    }

    return $payload['user_id'];
}

/**
 * Initialize logs directory
 */
if (!file_exists(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

/**
 * Logging Function
 */
function logMessage($level, $message, $context = []) {
    $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];

    if ($levels[$level] < $levels[LOG_LEVEL]) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logLine = "[$timestamp] [$level] $message $contextStr\n";

    $logFile = LOG_PATH . '/app_' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $logLine, FILE_APPEND);
}
