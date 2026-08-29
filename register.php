<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
if (Auth::user()) { header('Location: user-dashboard.php'); exit; }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::verifyCsrf($_POST['csrf_token'] ?? null);
        $first = trim((string) ($_POST['first_name'] ?? '')); $last = trim((string) ($_POST['last_name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? '')); $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($first === '' || $last === '' || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) throw new RuntimeException('Enter your name and a valid username.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
        if (strlen($password) < 10) throw new RuntimeException('Use a password with at least 10 characters.');
        $connection = Database::connect();
        $statement = $connection->prepare("INSERT INTO users(first_name,last_name,username,email,password,role,is_active,must_change_password) VALUES(:first,:last,:username,:email,:password,'Player',1,0)");
        $statement->execute(['first'=>$first,'last'=>$last,'username'=>$username,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT)]);
        if (!Auth::login($connection, $username, $password)) {
            throw new RuntimeException('Your account was created, but automatic sign-in failed. Please sign in from the login page.');
        }
        header('Location: user-dashboard.php'); exit;
    } catch (PDOException $exception) { $error = $exception->getCode() === '23000' ? 'This username or email already has an account. Please sign in instead.' : 'Account creation failed.'; }
      catch (Throwable $exception) { $error = $exception->getMessage(); }
}
function registerEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create account | Laguerta Picklebook</title><link rel="stylesheet" href="assets/style.css?v=20260829-4"></head><body class="auth-body"><main class="auth-shell"><section class="auth-card"><a class="brand" href="login.php"><span>LP</span><span class="brand-copy">Laguerta Picklebook<small>PLAYER REGISTRATION</small></span></a><p class="eyebrow">New player</p><h1>Create account</h1><p>Create your account once. You can use the same username or email to sign in again anytime.</p><?php if($error):?><div class="notice error"><?=registerEscape($error)?></div><?php endif;?><form method="post" class="reservation-form"><input type="hidden" name="csrf_token" value="<?=registerEscape(Auth::csrf())?>"><div class="two-columns"><label>First name<input name="first_name" maxlength="50" required></label><label>Last name<input name="last_name" maxlength="50" required></label></div><label>Username<input name="username" maxlength="50" autocomplete="username" required></label><label>Email<input type="email" name="email" maxlength="100" autocomplete="email" required></label><label>Password<input type="password" name="password" minlength="10" autocomplete="new-password" required><small>Use at least 10 characters.</small></label><button class="primary-button" type="submit">Create account and continue</button></form><p class="auth-link">Already have an account? <a href="login.php">Sign in</a></p></section></main></body></html>
