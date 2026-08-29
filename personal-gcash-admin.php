<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/Payment.php';
Auth::requireRole('Admin');
function adminEscape(string|int|float|null $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$error = null; $service = new Payment(Database::connect());
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) throw new RuntimeException('The form expired.');
        $service->confirmPersonalGcashPayment((int) ($_POST['payment_id'] ?? 0));
        $_SESSION['admin_flash'] = 'Payment verified and booking confirmed.';
        header('Location: admin-dashboard.php'); exit;
    }
} catch (Throwable $exception) { $error = $exception->getMessage(); }
$flash = $_SESSION['admin_flash'] ?? null; unset($_SESSION['admin_flash']);
$payments = $service->getPersonalGcashSubmissions();
$date = (string) ($_GET['date'] ?? '');
if ($date !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])-([0-2]\d|3[01])$/', $date)) $date = '';
if ($date !== '') $payments = array_values(array_filter($payments, static fn(array $payment): bool => (string) $payment['reservation_date'] === $date));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verify GCash Payments</title><link rel="stylesheet" href="assets/style.css"></head>
<body><main class="shell"><div class="panel manual-admin">
<a class="admin-back-button" href="admin-dashboard.php">← Back to Admin Dashboard</a>
<p class="eyebrow">Manual verification</p><h1>GCash receipt verification</h1>
<p>Open the uploaded receipt and match the sender name, number, and amount in your GCash app before confirming. New submissions appear automatically every 5 seconds.</p>
<form method="get" class="month-filter"><label>Exact date (optional)<input type="date" name="date" value="<?=adminEscape($date)?>" onchange="this.form.submit()"></label></form>
<?php if($flash):?><div class="notice"><?=adminEscape($flash)?></div><?php endif;?><?php if($error):?><div class="notice error"><?=adminEscape($error)?></div><?php endif;?>
<div class="table-wrap"><table><thead><tr><th>Booking</th><th>Player</th><th>GCash sender</th><th>Receipt proof</th><th>Schedule</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if(!$payments):?><tr><td colspan="8" class="empty">No GCash payments found for the selected month or date.</td></tr><?php endif;?>
<?php foreach($payments as $p):?><tr>
<td>#<?=adminEscape($p['reservation_id'])?><small><?=adminEscape($p['court_name'])?></small></td><td><?=adminEscape($p['player_name'])?></td>
<td><strong><?=adminEscape($p['gcash_account_name']?:'Not supplied')?></strong><small><?=adminEscape($p['gcash_number'])?></small></td>
<td><?php if($p['receipt_image']):?><a class="receipt-link" href="receipt.php?payment_id=<?=adminEscape($p['payment_id'])?>" target="_blank" rel="noopener"><img src="receipt.php?payment_id=<?=adminEscape($p['payment_id'])?>" alt="GCash payment receipt"><span>See receipt</span></a><?php else:?>No image<?php endif;?></td>
<td><strong><?=date('M j, Y',strtotime($p['reservation_date']))?></strong><small><?=date('g:i A',strtotime($p['start_time']))?>–<?=date('g:i A',strtotime($p['end_time']))?></small></td>
<td>₱<?=adminEscape(number_format((float)$p['amount'],2))?></td><td><span class="payment-state"><?=adminEscape($p['payment_status'])?></span></td>
<td><?php if($p['payment_status']!=='Paid'):?><form method="post" class="admin-confirm"><input type="hidden" name="csrf_token" value="<?=adminEscape(Auth::csrf())?>"><input type="hidden" name="payment_id" value="<?=adminEscape($p['payment_id'])?>"><button class="verify-gcash-button" type="submit">Verify GCash</button></form><?php else:?><span class="verified-label">✓ Verified</span><?php endif;?></td>
</tr><?php endforeach;?></tbody></table></div></div></main>
<script src="assets/auto-refresh.js"></script></body></html>
