<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
if (!Auth::user()) { header('Location: login.php'); exit; }
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST')try{Auth::verifyCsrf($_POST['csrf_token']??null);$password=(string)($_POST['password']??'');if(strlen($password)<10)throw new RuntimeException('Use at least 10 characters.');$s=Database::connect()->prepare('UPDATE users SET password=:password,must_change_password=0 WHERE user_id=:id');$s->execute(['password'=>password_hash($password,PASSWORD_DEFAULT),'id'=>Auth::id()]);$_SESSION['auth_user']['must_change_password']=0;header('Location: '.(Auth::role()==='Admin'?'admin-dashboard.php':'user-dashboard.php'));exit;}catch(Throwable $e){$error=$e->getMessage();}
function cpEscape(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Change password</title><link rel="stylesheet" href="assets/style.css"></head><body class="auth-body"><main class="auth-shell"><section class="auth-card"><h1>Choose a new password</h1><p>This temporary password must be replaced before continuing.</p><?php if($error):?><div class="notice error"><?=cpEscape($error)?></div><?php endif;?><form method="post" class="reservation-form"><input type="hidden" name="csrf_token" value="<?=cpEscape(Auth::csrf())?>"><label>New password<input type="password" name="password" minlength="10" autocomplete="new-password" required></label><button class="primary-button">Save password</button></form></section></main></body></html>
