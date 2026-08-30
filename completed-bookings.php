<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
Auth::requireRole('Admin');
function cbEscape(string|int|float|null $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$timezone = new DateTimeZone('Asia/Manila');
$now = new DateTimeImmutable('now', $timezone);
$month = (string)($_GET['month'] ?? $_POST['month'] ?? $now->format('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = $now->format('Y-m');
$monthStart = new DateTimeImmutable($month . '-01', $timezone);
$monthEnd = $monthStart->modify('first day of next month');
$connection = Database::connect();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::verifyCsrf($_POST['csrf_token'] ?? null);
        $reservationId = filter_var($_POST['reservation_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        if ($reservationId < 1) throw new RuntimeException('Invalid booking.');
        $delete = $connection->prepare("DELETE FROM reservations WHERE reservation_id=:id AND (reservation_date<:today OR (reservation_date=:today2 AND end_time<=:time))");
        $delete->execute(['id'=>$reservationId,'today'=>$now->format('Y-m-d'),'today2'=>$now->format('Y-m-d'),'time'=>$now->format('H:i:s')]);
        if ($delete->rowCount() !== 1) throw new RuntimeException('Only completed bookings can be deleted here.');
        $_SESSION['completed_flash'] = 'Completed booking deleted permanently.';
        header('Location: completed-bookings.php?month=' . rawurlencode($month)); exit;
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
}
$flash = $_SESSION['completed_flash'] ?? null; unset($_SESSION['completed_flash']);
$statement = $connection->prepare("SELECT r.reservation_id,r.reservation_date,r.start_time,r.end_time,r.status,CONCAT(u.first_name,' ',u.last_name) player_name,u.username,c.court_name,c.location,p.amount,p.payment_status FROM reservations r INNER JOIN users u ON u.user_id=r.user_id INNER JOIN courts c ON c.court_id=r.court_id LEFT JOIN payments p ON p.reservation_id=r.reservation_id WHERE r.status<>'Cancelled' AND r.reservation_date>=:month_start AND r.reservation_date<:month_end AND (r.reservation_date<:today OR (r.reservation_date=:today2 AND r.end_time<=:time)) ORDER BY r.reservation_date DESC,r.end_time DESC");
$statement->execute(['month_start'=>$monthStart->format('Y-m-d'),'month_end'=>$monthEnd->format('Y-m-d'),'today'=>$now->format('Y-m-d'),'today2'=>$now->format('Y-m-d'),'time'=>$now->format('H:i:s')]);
$completed = $statement->fetchAll();
$totalCollected = array_sum(array_map(fn(array $booking): float => $booking['payment_status']==='Paid' ? (float)($booking['amount']??0) : 0, $completed));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Completed Bookings | Laguerta Picklebook</title><link rel="stylesheet" href="assets/style.css"></head><body class="admin-role-body">
<aside class="admin-sidebar"><a class="brand admin-brand" href="admin-dashboard.php"><span>LP</span><span class="brand-copy">Laguerta Picklebook<small>ADMIN</small></span></a><nav><a href="admin-dashboard.php">Booking results</a><a href="personal-gcash-admin.php">Verify payments</a><a class="active" href="completed-bookings.php">Completed bookings</a><a href="monthly-bookings.php">Monthly bookings</a><a href="admin-crud.php">Full CRUD system</a><a href="logout.php">Sign out</a></nav></aside>
<main class="admin-main"><header class="admin-header"><div><p class="eyebrow">Booking history</p><h1>Completed bookings</h1><p>Bookings appear here automatically after their reserved end time.</p></div><form method="get" class="month-filter"><label>Select month<input type="month" name="month" value="<?=cbEscape($month)?>" onchange="this.form.submit()"></label></form></header>
<?php if($flash):?><div class="notice"><?=cbEscape($flash)?></div><?php endif;?><?php if($error):?><div class="notice error"><?=cbEscape($error)?></div><?php endif;?>
<section class="admin-stats"><div><small>Finished bookings</small><strong><?=count($completed)?></strong></div><div><small>Total collected</small><strong>₱<?=cbEscape(number_format($totalCollected,2))?></strong></div></section>
<section class="panel admin-results"><div class="admin-table-head"><div><h2>Finished court sessions</h2><p>Includes the booking price and payment result.</p></div></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Player</th><th>Court</th><th>Schedule</th><th>Booking</th><th>Payment</th><th>Price</th><th>Action</th></tr></thead><tbody>
<?php if(!$completed):?><tr><td colspan="8" class="empty">No completed bookings for this month.</td></tr><?php endif;?>
<?php foreach($completed as $booking):?><tr><td>#<?=cbEscape($booking['reservation_id'])?></td><td><strong><?=cbEscape($booking['player_name'])?></strong><small>@<?=cbEscape($booking['username'])?></small></td><td><strong><?=cbEscape($booking['court_name'])?></strong><small><?=cbEscape($booking['location'])?></small></td><td><strong><?=date('M j, Y',strtotime($booking['reservation_date']))?></strong><small><?=date('g:i A',strtotime($booking['start_time']))?>–<?=date('g:i A',strtotime($booking['end_time']))?></small></td><td><span class="status status-completed">Completed</span></td><td><span class="payment-state"><?=cbEscape($booking['payment_status']??'Not submitted')?></span></td><td><strong>₱<?=cbEscape(number_format((float)($booking['amount']??0),2))?></strong></td><td><form method="post" onsubmit="return confirm('Permanently delete this completed booking?');"><input type="hidden" name="csrf_token" value="<?=cbEscape(Auth::csrf())?>"><input type="hidden" name="reservation_id" value="<?=cbEscape($booking['reservation_id'])?>"><input type="hidden" name="month" value="<?=cbEscape($month)?>"><button class="delete-button" type="submit">Delete booking</button></form></td></tr><?php endforeach;?>
</tbody></table></div></section></main></body></html>
