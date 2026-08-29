<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';

$connection = Database::connect();
$adminCount = (int) $connection->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn();
$error = null;

if ($adminCount === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Auth::verifyCsrf($_POST['csrf_token'] ?? null);
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $last = trim((string) ($_POST['last_name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($first === '' || $last === '' || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            throw new RuntimeException('Enter your name and a valid username.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
        if (strlen($password) < 12) throw new RuntimeException('Use a password with at least 12 characters.');
        if ($password !== $confirm) throw new RuntimeException('The passwords do not match.');

        $statement = $connection->prepare("INSERT INTO users(first_name,last_name,username,email,password,role,is_active,must_change_password) VALUES(:first,:last,:username,:email,:password,'Admin',1,0)");
        $statement->execute([
            'first' => $first,
            'last' => $last,
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        Auth::login($connection, $username, $password);
        header('Location: admin-dashboard.php');
        exit;
    } catch (PDOException $exception) {
        $error = $exception->getCode() === '23000' ? 'That username or email is already registered.' : 'Admin setup failed.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

function setupEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin setup | Laguerta Picklebook</title><link rel="stylesheet" href="assets/style.css?v=20260829-4"></head><body class="auth-body"><main class="auth-shell"><section class="auth-card"><a class="brand" href="index.php"><span>LP</span><span class="brand-copy">Laguerta Picklebook<small>SECURE FIRST-RUN SETUP</small></span></a><?php if($adminCount > 0):?><p class="eyebrow">Setup complete</p><h1>Admin exists</h1><p>The administrator account has already been created. This setup form is now locked.</p><a class="primary-button" href="login.php">Go to sign in</a><?php else:?><p class="eyebrow">First launch</p><h1>Create administrator</h1><p>Create the private account that will manage bookings and verify payments.</p><?php if($error):?><div class="notice error"><?=setupEscape($error)?></div><?php endif;?><form method="post" class="reservation-form"><input type="hidden" name="csrf_token" value="<?=setupEscape(Auth::csrf())?>"><div class="two-columns"><label>First name<input name="first_name" maxlength="50" required></label><label>Last name<input name="last_name" maxlength="50" required></label></div><label>Username<input name="username" maxlength="50" autocomplete="username" required></label><label>Email<input type="email" name="email" maxlength="100" autocomplete="email" required></label><label>Password<input type="password" name="password" minlength="12" autocomplete="new-password" required><small>Use at least 12 characters and keep it private.</small></label><label>Confirm password<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></label><button class="primary-button" type="submit">Create secure Admin account</button></form><?php endif;?></section></main></body></html>
