<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/GoogleOAuth.php';

try {
    if (isset($_GET['error'])) throw new RuntimeException('Google sign-in was cancelled.');
    $code = (string) ($_GET['code'] ?? ''); $state = (string) ($_GET['state'] ?? '');
    if ($code === '' || $state === '') throw new RuntimeException('Google did not return a valid sign-in response.');
    $profile = GoogleOAuth::complete($code, $state);
    Auth::loginWithGoogle(Database::connect(), $profile);
    header('Location: ' . (Auth::role() === 'Admin' ? 'admin-dashboard.php' : 'user-dashboard.php'));
    exit;
} catch (Throwable $exception) {
    $_SESSION['google_login_error'] = $exception->getMessage();
    header('Location: login.php');
    exit;
}
