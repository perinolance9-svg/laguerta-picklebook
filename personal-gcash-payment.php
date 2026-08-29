<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/Payment.php';
Auth::requireRole('Player');

function pgEscape(string|int|float|null $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$reservationId = filter_var($_GET['reservation_id'] ?? $_POST['reservation_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$error = null; $submitted = false; $booking = null;
$service = new Payment(Database::connect());
try {
    if ($reservationId < 1) throw new InvalidArgumentException('Invalid reservation ID.');
    $booking = $service->getPayableReservation($reservationId);
    if (!$booking) throw new RuntimeException('This booking is unavailable, already paid, or awaiting verification.');
    if ((int) $booking['user_id'] !== Auth::id()) throw new RuntimeException('You can only pay for your own booking.');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) throw new RuntimeException('The form expired.');
        $receipt = $_FILES['receipt_image'] ?? null;
        if (!is_array($receipt) || ($receipt['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Select a GCash payment screenshot.');
        if ((int) $receipt['size'] > 5 * 1024 * 1024) throw new RuntimeException('The screenshot must be 5 MB or smaller.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $receipt['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) throw new RuntimeException('Upload a JPG, PNG, or WebP image.');
        $uploadDirectory = __DIR__ . '/uploads/payment-receipts';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) throw new RuntimeException('Receipt storage could not be created.');
        $filename = 'receipt-' . $reservationId . '-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        $relativePath = 'uploads/payment-receipts/' . $filename;
        if (!move_uploaded_file((string) $receipt['tmp_name'], __DIR__ . '/' . $relativePath)) throw new RuntimeException('The screenshot could not be saved.');
        try {
            $service->submitPersonalGcashReference($reservationId, (string) ($_POST['payer_name'] ?? ''), (string) ($_POST['payer_number'] ?? ''), (string) ($_POST['reference_number'] ?? ''), AppConfig::bookingFeeCentavos(), $relativePath);
        } catch (Throwable $exception) {
            @unlink(__DIR__ . '/' . $relativePath);
            throw $exception;
        }
        $submitted = true;
    }
} catch (Throwable $exception) { $error = $exception->getMessage(); }
$recipientName = AppConfig::personalGcashName();
$recipientNumber = AppConfig::personalGcashNumber();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>GCash Checkout | Laguerta Picklebook</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="checkout-body"><header class="checkout-nav"><a class="brand" href="user-dashboard.php"><span>LP</span><span class="brand-copy">Laguerta Picklebook<small>SECURE PAYMENT</small></span></a><a href="user-dashboard.php" class="checkout-close">Cancel payment</a></header>
<main class="checkout-shell">
<section class="checkout-progress"><div class="done"><b>1</b><span>Booking<small>Details saved</small></span></div><i></i><div class="active"><b>2</b><span>Payment<small>GCash transfer</small></span></div><i></i><div><b>3</b><span>Verification<small>Admin review</small></span></div></section>
<?php if ($submitted): ?><section class="checkout-success"><div class="success-check">✓</div><p class="eyebrow">Receipt received</p><h1>Payment sent for verification.</h1><p>Your booking remains pending while the administrator matches your screenshot and reference number with the GCash transaction.</p><div class="success-reference">Reservation <strong>#<?= pgEscape($reservationId) ?></strong><span>For Verification</span></div><a class="primary-button inline-button" href="user-dashboard.php">Return to player dashboard</a></section>
<?php else: ?><div class="checkout-grid"><section class="checkout-main-card"><div class="checkout-title"><div class="gcash-logo">G</div><div><p class="eyebrow">Personal GCash</p><h1>Complete your payment</h1></div></div>
<?php if ($recipientName === '' || $recipientNumber === ''): ?><div class="notice">Recipient QR details are not displayed yet, but you may still submit an existing GCash receipt for administrator verification.</div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= pgEscape($error) ?></div><?php endif; ?>
<div class="checkout-payee"><div class="checkout-qr"><?php if (is_file(__DIR__ . '/assets/gcash-qr.png')): ?><img src="assets/gcash-qr.png" alt="GCash payment QR code"><?php else: ?><div><strong>QR</strong><small>Add gcash-qr.png</small></div><?php endif; ?></div><div class="payee-details"><span>Scan using the GCash app</span><h2><?= pgEscape($recipientName ?: 'Account name unavailable') ?></h2><p><?= pgEscape($recipientNumber ?: 'GCash number unavailable') ?></p><div class="amount-display"><small>Exact amount to send</small><strong>₱<?= pgEscape(number_format(AppConfig::bookingFeeCentavos()/100, 2)) ?></strong></div></div></div>
<?php if ($booking): ?><form method="post" enctype="multipart/form-data" class="checkout-form"><input type="hidden" name="csrf_token" value="<?= pgEscape($_SESSION['csrf_token']) ?>"><input type="hidden" name="reservation_id" value="<?= pgEscape($reservationId) ?>"><div class="checkout-form-head"><h2>Submit payment proof</h2><span>All fields required</span></div><div class="checkout-fields checkout-fields-three"><label>GCash account name<input type="text" name="payer_name" maxlength="100" placeholder="Name shown on receipt" required></label><label>GCash number<input type="tel" name="payer_number" pattern="09[0-9]{9}" maxlength="11" placeholder="09XX XXX XXXX" required></label><label>Reference number<input type="text" name="reference_number" maxlength="50" placeholder="From GCash receipt" required></label></div><label class="receipt-label">GCash receipt screenshot<span class="checkout-upload"><span class="upload-icon">↑</span><span><strong>Choose receipt image</strong><small>Required · JPG, PNG or WebP · up to 5 MB</small></span><input type="file" name="receipt_image" accept="image/jpeg,image/png,image/webp" required></span></label><button class="checkout-submit" type="submit"><span>Upload receipt for verification</span><b>→</b></button><p class="checkout-security">The administrator verifies the account name, number, reference, and receipt before confirming your booking.</p></form><?php endif; ?></section>
<aside class="checkout-summary"><p class="eyebrow">Order summary</p><h2>Court reservation</h2><?php if ($booking): ?><div class="summary-id">Booking ID <strong>#<?= pgEscape($booking['reservation_id']) ?></strong></div><dl><div><dt>Player</dt><dd><?= pgEscape($booking['player_name']) ?></dd></div><div><dt>Court</dt><dd><?= pgEscape($booking['court_name']) ?></dd></div><div><dt>Date</dt><dd><?= pgEscape(date('M j, Y', strtotime($booking['reservation_date']))) ?></dd></div><div><dt>Time</dt><dd><?= pgEscape(date('g:i A', strtotime($booking['start_time']))) ?> – <?= pgEscape(date('g:i A', strtotime($booking['end_time']))) ?></dd></div></dl><div class="summary-total"><span>Total</span><strong>₱<?= pgEscape(number_format(AppConfig::bookingFeeCentavos()/100, 2)) ?></strong></div><?php endif; ?><div class="summary-help"><strong>Payment reminder</strong><p>Send the exact amount and make sure the screenshot clearly shows the reference number.</p></div></aside></div><?php endif; ?>
</main><script src="assets/auto-refresh.js"></script></body></html>
