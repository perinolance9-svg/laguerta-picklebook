<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/Payment.php';
require_once __DIR__ . '/classes/PayMongoGateway.php';
Auth::requireRole('Player');

$reservationId = filter_var($_GET['reservation_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;

try {
    if ($reservationId < 1) throw new InvalidArgumentException('Invalid reservation ID.');
    $paymentService = new Payment(Database::connect());
    $booking = $paymentService->getPayableReservation($reservationId);
    if (!$booking) throw new RuntimeException('This reservation is unavailable or already paid.');
    if ((int) $booking['user_id'] !== Auth::id()) throw new RuntimeException('You can only pay for your own booking.');

    $gateway = new PayMongoGateway(AppConfig::payMongoSecretKey());
    $checkout = $gateway->createGcashCheckout($booking, AppConfig::bookingFeeCentavos(), AppConfig::baseUrl());
    $paymentService->savePendingCheckout($reservationId, AppConfig::bookingFeeCentavos(), $checkout);
    header('Location: ' . $checkout['checkout_url'], true, 303);
    exit;
} catch (Throwable $exception) {
    $_SESSION['flash'] = ['message' => $exception->getMessage(), 'type' => 'error'];
    header('Location: user-dashboard.php');
    exit;
}
