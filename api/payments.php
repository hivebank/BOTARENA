<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$userId = getUserFromToken();

if (!$userId) {
    errorResponse('Unauthorized', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Record payment
if ($method === 'POST' && isset($input['action']) && $input['action'] === 'record') {
    $txHash = sanitizeInput($input['tx_hash'] ?? '');
    $wallet = sanitizeInput($input['wallet'] ?? '');
    $amount = floatval($input['amount'] ?? 0);
    $crypto = sanitizeInput($input['crypto'] ?? '');
    $tier = sanitizeInput($input['tier'] ?? '');
    $billing = sanitizeInput($input['billing'] ?? 'month');

    if (empty($txHash) || empty($wallet) || $amount <= 0 || empty($crypto) || empty($tier)) {
        errorResponse('Missing or invalid parameters', 400);
    }

    // Check if transaction already exists
    $existing = mongoQuery('payments', ['tx_hash' => $txHash]);

    if (!empty($existing)) {
        errorResponse('Transaction already recorded', 409);
    }

    // Insert payment record
    $result = mongoInsert('payments', [
        'user_id' => $userId,
        'tx_hash' => $txHash,
        'wallet_address' => $wallet,
        'amount_usd' => $amount,
        'crypto' => $crypto,
        'tier' => $tier,
        'billing_period' => $billing,
        'status' => 'pending',
        'confirmations' => 0
    ]);

    if (!$result['success']) {
        errorResponse('Failed to record payment', 500);
    }

    // In production, verify transaction on-chain here
    // For now, auto-confirm after a delay
    $paymentId = $result['id'];

    // Simulate confirmation (in production, use webhook/polling)
    mongoUpdate('payments', ['_id' => new MongoDB\BSON\ObjectId($paymentId)], [
        'status' => 'confirmed',
        'confirmations' => 1
    ]);

    // Upgrade user tier
    mongoUpdate('users', ['_id' => new MongoDB\BSON\ObjectId($userId)], [
        'tier' => $tier,
        'subscription_expires' => new MongoDB\BSON\UTCDateTime(
            strtotime($billing === 'month' ? '+1 month' : '+1 year') * 1000
        )
    ]);

    // Log payment
    mongoInsert('logs', [
        'type' => 'payment_received',
        'user_id' => $userId,
        'payment_id' => $paymentId,
        'amount' => $amount,
        'tier' => $tier
    ]);

    successResponse([
        'payment_id' => $paymentId,
        'status' => 'confirmed'
    ], 'Payment recorded and confirmed');
}

// Get payment history
elseif ($method === 'GET') {
    $payments = mongoQuery('payments', ['user_id' => $userId], ['sort' => ['created_at' => -1]]);

    $result = array_map(function($payment) {
        return [
            'id' => (string)$payment->_id,
            'tx_hash' => $payment->tx_hash,
            'amount_usd' => $payment->amount_usd,
            'crypto' => $payment->crypto,
            'tier' => $payment->tier,
            'status' => $payment->status,
            'created_at' => $payment->created_at ? $payment->created_at->toDateTime()->format('c') : null
        ];
    }, $payments);

    successResponse(['payments' => $result]);
}

else {
    errorResponse('Invalid action', 400);
}
