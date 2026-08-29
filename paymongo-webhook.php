<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Payment.php';
require_once __DIR__ . '/classes/PayMongoGateway.php';

header('Content-Type: application/json');
$rawBody = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? $_SERVER['HTTP_X_PAYMONGO_SIGNATURE'] ?? '';

if (!PayMongoGateway::verifyWebhook($rawBody, $signature, AppConfig::payMongoWebhookSecret(), AppConfig::payMongoMode())) {
    http_response_code(401);
    echo json_encode(['received' => false]);
    exit;
}

try {
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    $event = $payload['data'] ?? [];
    $attributes = $event['attributes'] ?? [];
    $eventType = $attributes['type'] ?? $event['type'] ?? '';
    if ($eventType !== 'checkout_session.payment.paid') {
        http_response_code(200);
        echo json_encode(['received' => true, 'ignored' => true]);
        exit;
    }

    $session = $attributes['data'] ?? $event['data'] ?? [];
    $sessionId = (string) ($session['id'] ?? '');
    $sessionAttributes = $session['attributes'] ?? [];
    $payments = $sessionAttributes['payments'] ?? [];
    $payment = is_array($payments) && isset($payments[0]) ? $payments[0] : [];
    $paymentAttributes = $payment['attributes'] ?? [];
    $amount = (int) ($paymentAttributes['amount'] ?? 0);
    $gatewayPaymentId = (string) ($payment['id'] ?? '');
    $eventId = (string) ($event['id'] ?? hash('sha256', $rawBody));
    if ($sessionId === '' || $gatewayPaymentId === '' || $amount < 1) {
        throw new RuntimeException('Incomplete paid checkout event.');
    }

    (new Payment(Database::connect()))->markCheckoutPaid($sessionId, $gatewayPaymentId, $amount, $eventId);
    http_response_code(200);
    echo json_encode(['received' => true]);
} catch (Throwable $exception) {
    error_log('PayMongo webhook error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['received' => false]);
}
