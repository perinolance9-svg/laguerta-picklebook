<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/GoogleOAuth.php';
if (Auth::user()) { header('Location: ' . (Auth::role() === 'Admin' ? 'admin-dashboard.php' : 'user-dashboard.php')); exit; }
$error = $_SESSION['google_login_error'] ?? null; unset($_SESSION['google_login_error']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::verifyCsrf($_POST['csrf_token'] ?? null);
        if (!Auth::login(Database::connect(), (string) ($_POST['identity'] ?? ''), (string) ($_POST['password'] ?? ''))) throw new RuntimeException('Incorrect username/email or password.');
        unset($_SESSION['after_login']);
        $target = Auth::role() === 'Admin' ? 'admin-dashboard.php' : 'user-dashboard.php';
        header('Location: ' . $target); exit;
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
}
function loginEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#153e13"><title>Sign in | Laguerta Picklebook</title><link rel="stylesheet" href="assets/style.css?v=20260829-4"></head><body class="auth-body"><main class="auth-shell"><section class="auth-card simple-login"><a class="brand" href="login.php"><span>LP</span><span class="brand-copy">Laguerta Picklebook<small>COURT RESERVATIONS</small></span></a><p class="eyebrow">Welcome</p><h1>Sign in</h1><p>Enter your account details to continue.</p><?php if($error):?><div class="notice error"><?=loginEscape($error)?></div><?php endif;?><?php if(GoogleOAuth::configured()):?><a class="google-button" href="google-login.php"><span class="google-mark">G</span><strong>Continue with Google</strong><i>→</i></a><div class="signin-divider"><span>or sign in with your account</span></div><?php endif;?><form method="post" class="reservation-form signin-form"><input type="hidden" name="csrf_token" value="<?=loginEscape(Auth::csrf())?>"><label>Username or email<input name="identity" autocomplete="username" placeholder="Enter username or email" required></label><label>Password<input type="password" name="password" autocomplete="current-password" placeholder="Enter password" required></label><button class="primary-button" type="submit">Sign in <span>→</span></button></form><p class="auth-link">New player? <a href="register.php">Create an account</a></p></section></main></body></html>
