<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
function mbEscape(string|int|float|null $value): string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$timezone=new DateTimeZone('Asia/Manila');
$now=new DateTimeImmutable('now',$timezone);
$month=trim((string)($_GET['month']??$now->format('Y-m')));
if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))$month=$now->format('Y-m');
$first=DateTimeImmutable::createFromFormat('!Y-m',$month,$timezone);
$next=$first->modify('+1 month');
$statement=Database::connect()->prepare(
    "SELECT r.reservation_id,r.reservation_date,r.start_time,r.end_time,r.status,
            CONCAT(u.first_name,' ',u.last_name) player_name,u.username,c.court_name,c.location,
            p.amount,p.payment_status
     FROM reservations r
     INNER JOIN users u ON u.user_id=r.user_id
     INNER JOIN courts c ON c.court_id=r.court_id
     LEFT JOIN payments p ON p.reservation_id=r.reservation_id
     WHERE r.reservation_date>=:first_date AND r.reservation_date<:next_date
     ORDER BY r.reservation_date,r.start_time"
);
$statement->execute(['first_date'=>$first->format('Y-m-d'),'next_date'=>$next->format('Y-m-d')]);
$bookings=$statement->fetchAll();
$confirmed=count(array_filter($bookings,fn($booking)=>$booking['status']==='Confirmed'));
$reserved=count(array_filter($bookings,function($booking)use($now,$timezone){$start=new DateTimeImmutable($booking['reservation_date'].' '.$booking['start_time'],$timezone);return $booking['status']!=='Cancelled'&&$start>$now;}));
$completed=count(array_filter($bookings,function($booking)use($now,$timezone){$end=new DateTimeImmutable($booking['reservation_date'].' '.$booking['end_time'],$timezone);return $booking['status']!=='Cancelled'&&$end<=$now;}));
$totalCollected=array_sum(array_map(fn($booking)=>$booking['payment_status']==='Paid'?(float)($booking['amount']??0):0,$bookings));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Monthly Bookings | Laguerta Picklebook</title><link rel="stylesheet" href="assets/style.css"></head><body class="admin-role-body">
<aside class="admin-sidebar"><a class="brand admin-brand" href="admin-dashboard.php"><span>LP</span><span class="brand-copy">Laguerta Picklebook<small>ADMIN</small></span></a><nav><a href="admin-dashboard.php">Booking results</a><a href="personal-gcash-admin.php">Verify payments</a><a href="completed-bookings.php">Completed bookings</a><a class="active" href="monthly-bookings.php">Monthly bookings</a><a href="index.php?admin_view=1">Full CRUD system</a><a href="user-dashboard.php?admin_view=1">Player dashboard</a></nav></aside>
<main class="admin-main"><header class="admin-header"><div><p class="eyebrow">Monthly report</p><h1><?=mbEscape($first->format('F Y'))?> bookings</h1><p>View every reservation and payment recorded for the selected month.</p></div><form method="get" class="month-filter"><label>Select month<input type="month" name="month" value="<?=mbEscape($month)?>" onchange="this.form.submit()"></label></form></header>
<section class="admin-stats"><div><small>Total bookings</small><strong><?=count($bookings)?></strong></div><div><small>Confirmed</small><strong><?=$confirmed?></strong></div><div><small>Reserved</small><strong><?=$reserved?></strong></div><div><small>Completed</small><strong><?=$completed?></strong></div></section>
<section class="panel admin-results"><div class="admin-table-head"><div><h2>All bookings for <?=mbEscape($first->format('F'))?></h2><p>Includes upcoming, completed, pending, confirmed, and cancelled records.</p></div></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Player</th><th>Court</th><th>Schedule</th><th>Booking</th><th>Payment</th><th>Price</th></tr></thead><tbody>
<?php if(!$bookings):?><tr><td colspan="7" class="empty">No bookings were recorded for this month.</td></tr><?php endif;?>
<?php foreach($bookings as $booking):?><tr><td>#<?=mbEscape($booking['reservation_id'])?></td><td><strong><?=mbEscape($booking['player_name'])?></strong><small>@<?=mbEscape($booking['username'])?></small></td><td><strong><?=mbEscape($booking['court_name'])?></strong><small><?=mbEscape($booking['location'])?></small></td><td><strong><?=date('M j, Y',strtotime($booking['reservation_date']))?></strong><small><?=date('g:i A',strtotime($booking['start_time']))?>–<?=date('g:i A',strtotime($booking['end_time']))?></small></td><td><span class="status status-<?=strtolower(mbEscape($booking['status']))?>"><?=mbEscape($booking['status'])?></span></td><td><span class="payment-state"><?=mbEscape($booking['payment_status']??'Not submitted')?></span></td><td><strong>₱<?=mbEscape(number_format((float)($booking['amount']??0),2))?></strong></td></tr><?php endforeach;?>
</tbody></table></div></section></main><script src="assets/auto-refresh.js"></script></body></html>
