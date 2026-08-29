<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/Payment.php';
Auth::requireRole('Player');

$reservationId = filter_var($_GET['reservation_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$status = $reservationId > 0 ? (new Payment(Database::connect()))->getReservationPaymentStatus($reservationId) : null;
if ($status && (int) $status['user_id'] !== Auth::id()) { http_response_code(403); exit('Access denied.'); }
$confirmed = ($status['payment_status'] ?? '') === 'Paid' && ($status['reservation_status'] ?? '') === 'Confirmed';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="5"><title>Payment Status | Laguerta Picklebook</title><link rel="stylesheet" href="assets/style.css"></head>
<body><main class="payment-shell"><section class="payment-card">
<div class="gcash-heading"><span>G</span><div><p class="eyebrow">PayMongo GCash</p><h1><?= $confirmed ? 'Booking confirmed' : 'Verifying payment' ?></h1></div></div>
<?php if ($confirmed): ?>
<div class="notice">Payment received. Reservation #<?= htmlspecialchars((string) $reservationId) ?> is confirmed.</div>
<?php else: ?>
<div class="notice">Your payment return was received. The booking remains pending until PayMongo's verified webhook confirms it. This page refreshes automatically.</div>
<?php endif; ?>
<a class="primary-button inline-button" href="user-dashboard.php">Return to reservations</a>
</section></main><script src="assets/auto-refresh.js"></script></body></html>
