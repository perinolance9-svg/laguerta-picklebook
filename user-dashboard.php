<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/Reservation.php';
Auth::requireRole('Player');
function udEscape(string|int|null $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$error=null; $connection=Database::connect(); $service=new Reservation($connection);
$userId=Auth::id();
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!hash_equals($_SESSION['csrf_token'],(string)($_POST['csrf_token']??''))) throw new RuntimeException('The form expired.');
        $newId=$service->addReservation([
            'reservation_date'=>trim((string)($_POST['reservation_date']??'')),
            'start_time'=>trim((string)($_POST['start_time']??'')),
            'end_time'=>trim((string)($_POST['end_time']??'')),
            'status'=>'Pending','user_id'=>$userId,
            'court_id'=>filter_var($_POST['court_id']??null,FILTER_VALIDATE_INT)?:0,
        ]);
        header('Location: personal-gcash-payment.php?reservation_id='.$newId); exit;
    }
} catch(Throwable $exception){$error=$exception->getMessage();}
$courts=$connection->query("SELECT court_id,court_name,location FROM courts WHERE status='Available' ORDER BY court_name")->fetchAll();
$selectedUser=['full_name'=>(string)(Auth::user()['first_name'].' '.Auth::user()['last_name'])];
$now=new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
$scheduleDate=trim((string)($_GET['schedule_date']??$now->format('Y-m-d')));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$scheduleDate))$scheduleDate=$now->format('Y-m-d');
$reservations=array_values(array_filter($service->getReservations(),function(array $reservation)use($now):bool{
    $end=new DateTimeImmutable($reservation['reservation_date'].' '.$reservation['end_time'],new DateTimeZone('Asia/Manila'));
    return $reservation['status']!=='Cancelled'&&$end>$now;
}));
$dailyStatement=$connection->prepare("SELECT r.reservation_id,r.user_id,r.start_time,r.end_time,r.status,c.court_name,c.location FROM reservations r INNER JOIN courts c ON c.court_id=r.court_id WHERE r.reservation_date=:schedule_date AND r.status<>'Cancelled' ORDER BY r.start_time,c.court_name");
$dailyStatement->execute(['schedule_date'=>$scheduleDate]);
$dailyBookings=$dailyStatement->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#286b08"><link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="assets/app-icon.svg" type="image/svg+xml"><title>Player Dashboard | Laguerta Picklebook</title><link rel="stylesheet" href="assets/style.css"></head><body class="role-body">
<header class="role-nav"><a class="brand" href="user-dashboard.php"><span>LP</span><span class="brand-copy">Laguerta Picklebook<small>PLAYER DASHBOARD</small></span></a><a class="admin-back-button" href="logout.php">Sign out</a></header>
<main class="role-shell"><section class="player-welcome"><div><p class="eyebrow">Player space</p><h1>Welcome, <?=udEscape((string)Auth::user()['first_name'])?></h1><p>Reserve a court, continue to personal GCash payment, and track your booking status.</p></div><div class="secure-profile"><small>Signed in as</small><strong>@<?=udEscape((string)Auth::user()['username'])?></strong></div></section>
<section class="live-courts"><div class="live-courts-head"><div><p class="eyebrow">Live availability</p><h2>Courts right now</h2></div><span><i></i> Updates automatically</span></div><div class="court-status-grid" id="court-status-grid"><p class="court-loading">Checking court availability…</p></div></section>
<section class="daily-schedule"><div class="daily-schedule-head"><div><p class="eyebrow">Daily schedule</p><h2>All bookings for <?=udEscape(date('M j, Y',strtotime($scheduleDate)))?></h2><p>Booked court times are visible to every player. Player names remain private.</p></div><form method="get" class="daily-date-filter"><label>Choose date<input type="date" name="schedule_date" value="<?=udEscape($scheduleDate)?>" onchange="this.form.submit()"></label></form></div><div class="daily-booking-grid"><?php if(!$dailyBookings):?><p class="empty daily-empty">No courts are booked on this date.</p><?php endif;?><?php foreach($dailyBookings as $daily):?><article class="daily-booking-card"><div><small><?=udEscape($daily['location'])?></small><h3><?=udEscape($daily['court_name'])?></h3></div><strong><?=date('g:i A',strtotime($daily['start_time']))?>–<?=date('g:i A',strtotime($daily['end_time']))?></strong><span class="status <?=((int)$daily['user_id']===$userId)?'status-confirmed':'status-pending'?>"><?=((int)$daily['user_id']===$userId)?'Your booking':'Reserved'?></span></article><?php endforeach;?></div></section>
<?php if($error):?><div class="notice error"><?=udEscape($error)?></div><?php endif;?>
<?php if($userId>0):?><section class="player-grid"><article class="panel role-book-card"><p class="eyebrow">New reservation</p><h2>Book your court</h2><form method="post" class="reservation-form"><input type="hidden" name="csrf_token" value="<?=udEscape($_SESSION['csrf_token'])?>"><input type="hidden" name="user_id" value="<?=udEscape($userId)?>"><label>Court<select name="court_id" required><option value="">Choose an available court</option><?php foreach($courts as $c):?><option value="<?=udEscape($c['court_id'])?>"><?=udEscape($c['court_name'].' · '.$c['location'])?></option><?php endforeach;?></select></label><label>Date<input type="date" name="reservation_date" value="<?=date('Y-m-d')?>" min="<?=date('Y-m-d')?>" required></label><div class="two-columns"><label>Start<input type="time" name="start_time" value="09:00" required></label><label>End<input type="time" name="end_time" value="10:00" required></label></div><div class="payment-note"><strong>Booking rules</strong>Open 4:00 AM–2:00 AM · maximum 5 active bookings per player.</div><div class="payment-note"><strong>₱<?=number_format(AppConfig::bookingFeeCentavos()/100,2)?> via personal GCash</strong>Payment is verified before confirmation.</div><button class="primary-button" type="submit">Book and continue to payment</button></form></article>
<article class="panel role-reservations"><div class="role-section-head"><div><p class="eyebrow">Reserved schedules</p><h2>All reservations</h2></div><span><?=count($reservations)?> bookings</span></div><div class="booking-cards"><?php if(!$reservations):?><p class="empty">There are no current or upcoming reservations.</p><?php endif;?><?php foreach($reservations as $r):?><div class="booking-card"><div class="booking-date"><strong><?=date('d',strtotime($r['reservation_date']))?></strong><span><?=date('M',strtotime($r['reservation_date']))?></span></div><div><strong><?=udEscape($r['court_name'])?></strong><small><?=udEscape($r['location'])?> · <?=date('g:i A',strtotime($r['start_time']))?>–<?=date('g:i A',strtotime($r['end_time']))?></small></div><div class="booking-state"><span class="status status-<?=strtolower(udEscape($r['status']))?>"><?=udEscape($r['status'])?></span><?php if((int)$r['user_id']===$userId):?><small><?=udEscape($r['payment_status']??'Not paid')?></small><?php endif;?><?php if((int)$r['user_id']===$userId&&$r['status']==='Pending'&&($r['payment_status']??'')!=='For Verification'):?><a class="pay-button" href="personal-gcash-payment.php?reservation_id=<?=udEscape($r['reservation_id'])?>">Pay now</a><?php endif;?></div></div><?php endforeach;?></div></article></section><?php endif;?></main>
<script>
function durationLabel(seconds){if(seconds===null||seconds===undefined)return '';const h=Math.floor(seconds/3600),m=Math.ceil((seconds%3600)/60);return h>0?`${h} hr ${m} min`:`${m} min`;}
function safe(value){return String(value).replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));}
let liveCourts=[];
let refreshingCourts=false;
function renderCourts(){const grid=document.getElementById('court-status-grid');grid.innerHTML=liveCourts.map(c=>{const occupied=c.availability==='occupied';const reserved=c.availability==='reserved';const vacant=c.availability==='vacant';let timer='';if(occupied)timer=`<strong>${durationLabel(c.remaining_seconds)} remaining</strong>`;else if(reserved)timer=`<strong>Starts in ${durationLabel(c.remaining_seconds)}</strong>`;else if(vacant)timer='<strong>Vacant now</strong>';return `<article class="court-live-card court-${safe(c.availability)}"><div class="court-live-icon">${vacant?'✓':occupied?'◷':reserved?'⌛':'!'}</div><div><small>${safe(c.location)}</small><h3>${safe(c.court_name)}</h3><span class="court-live-state">${safe(c.availability)}</span></div><div class="court-live-time">${timer}<small>${safe(c.message)}</small></div></article>`}).join('');}
async function refreshCourts(){if(refreshingCourts)return;refreshingCourts=true;const grid=document.getElementById('court-status-grid');try{const response=await fetch(`court-status.php?t=${Date.now()}`,{cache:'no-store'});if(!response.ok)throw new Error();const data=await response.json();liveCourts=data.courts;renderCourts();}catch(e){if(!liveCourts.length)grid.innerHTML='<p class="notice error">Live court status is temporarily unavailable.</p>';}finally{refreshingCourts=false;}}
function tickCourts(){let expired=false;liveCourts.forEach(c=>{if(Number.isFinite(c.remaining_seconds)&&c.remaining_seconds>0){c.remaining_seconds-=1;if(c.remaining_seconds<=0)expired=true;}});if(liveCourts.length)renderCourts();if(expired)refreshCourts();}
refreshCourts();setInterval(tickCourts,1000);setInterval(refreshCourts,5000);document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')refreshCourts();});
</script><script>if('serviceWorker'in navigator)navigator.serviceWorker.register('service-worker.js');</script></body></html>
