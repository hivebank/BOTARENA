<?php
/**
 * Health Check Endpoint for Railway
 * Returns 200 OK if system is healthy
 */

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => time(),
    'checks' => []
];

$allHealthy = true;

// Check MongoDB connection
try {
    require_once __DIR__ . '/../config/config.php';

    $manager = getMongoClient();
    $command = new MongoDB\Driver\Command(['ping' => 1]);
    $manager->executeCommand('admin', $command);

    $health['checks']['mongodb'] = 'ok';
} catch (Exception $e) {
    $health['checks']['mongodb'] = 'error: ' . $e->getMessage();
    $allHealthy = false;
}

// Check logs directory is writable
if (is_writable(LOG_PATH)) {
    $health['checks']['logs_writable'] = 'ok';
} else {
    $health['checks']['logs_writable'] = 'error';
    $allHealthy = false;
}

// Check PHP version
$health['checks']['php_version'] = PHP_VERSION;

// Check if MongoDB extension is loaded
if (extension_loaded('mongodb')) {
    $health['checks']['mongodb_extension'] = 'ok';
} else {
    $health['checks']['mongodb_extension'] = 'missing';
    $allHealthy = false;
}

// Environment
$health['environment'] = getenv('RAILWAY_ENVIRONMENT') ?: 'unknown';

// Overall status
if (!$allHealthy) {
    $health['status'] = 'unhealthy';
    http_response_code(503);
} else {
    http_response_code(200);
}

echo json_encode($health, JSON_PRETTY_PRINT);
