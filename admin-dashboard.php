<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/Reservation.php';
Auth::requireRole('Admin');
function adEscape(string|int|null $value): string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$service=new Reservation(Database::connect()); $keyword=trim((string)($_GET['search']??''));
$flash=$_SESSION['admin_flash']??null; unset($_SESSION['admin_flash']);
$allReservations=$keyword===''?$service->getReservations():$service->searchReservations($keyword);
$now=new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
$reservations=array_values(array_filter($allReservations,function(array $reservation)use($now):bool{
    $end=new DateTimeImmutable($reservation['reservation_date'].' '.$reservation['end_time'],new DateTimeZone('Asia/Manila'));
    return $end>$now;
}));
$completedCount=count(array_filter($service->getReservations(),function(array $reservation)use($now):bool{
    $end=new DateTimeImmutable($reservation['reservation_date'].' '.$reservation['end_time'],new DateTimeZone('Asia/Manila'));
    return $reservation['status']!=='Cancelled'&&$end<=$now;
}));
$pending=count(array_filter($reservations,fn($r)=>$r['status']==='Pending'));
$confirmed=count(array_filter($reservations,fn($r)=>$r['status']==='Confirmed'));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Dashboard | Laguerta Picklebook</title><link rel="stylesheet" href="assets/style.css"></head><body class="admin-role-body">
<aside class="admin-sidebar"><a class="brand admin-brand" href="admin-dashboard.php"><span>LP</span><span class="brand-copy">Laguerta Picklebook<small>ADMIN</small></span></a><nav><a class="active" href="admin-dashboard.php">Booking results</a><a href="personal-gcash-admin.php">Verify payments</a><a href="completed-bookings.php">Completed bookings</a><a href="monthly-bookings.php">Monthly bookings</a><a href="admin-crud.php">Full CRUD system</a><a href="logout.php">Sign out</a></nav></aside>
<main class="admin-main"><header class="admin-header"><div><p class="eyebrow">Operations overview</p><h1>Booking results</h1><p>Current and upcoming reservations. Finished sessions move automatically to Completed bookings.</p></div></header>
<?php if($flash):?><div class="notice"><?=adEscape($flash)?></div><?php endif;?>
<section class="admin-stats"><div><small>Active bookings</small><strong><?=count($reservations)?></strong></div><div><small>Pending</small><strong><?=$pending?></strong></div><div><small>Confirmed</small><strong><?=$confirmed?></strong></div><div><small>Completed bookings</small><strong><?=$completedCount?></strong></div></section>
<section class="panel admin-results"><div class="admin-table-head"><div><h2>Player reservations</h2><p>Current and upcoming schedules.</p></div><form method="get" class="search-form"><input type="search" name="search" value="<?=adEscape($keyword)?>" placeholder="Player name or schedule date"><button>Search</button><?php if($keyword!==''):?><a href="admin-dashboard.php">Clear</a><?php endif;?></form></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Player</th><th>Court</th><th>Schedule</th><th>Booking</th><th>Payment</th><th>Manage</th></tr></thead><tbody><?php if(!$reservations):?><tr><td colspan="7" class="empty">No current or upcoming reservations.</td></tr><?php endif;?><?php foreach($reservations as $r):?><tr><td>#<?=adEscape($r['reservation_id'])?></td><td><strong><?=adEscape($r['player_name'])?></strong><small>@<?=adEscape($r['username'])?></small></td><td><strong><?=adEscape($r['court_name'])?></strong><small><?=adEscape($r['location'])?></small></td><td><strong><?=date('M j, Y',strtotime($r['reservation_date']))?></strong><small><?=date('g:i A',strtotime($r['start_time']))?>–<?=date('g:i A',strtotime($r['end_time']))?></small></td><td><span class="status status-<?=strtolower(adEscape($r['status']))?>"><?=adEscape($r['status'])?></span></td><td><span class="payment-state"><?=adEscape($r['payment_status']??'Not submitted')?></span></td><td><a class="edit-button" href="index.php?admin_view=1&amp;edit=<?=adEscape($r['reservation_id'])?>">Manage</a></td></tr><?php endforeach;?></tbody></table></div></section></main></body></html>
