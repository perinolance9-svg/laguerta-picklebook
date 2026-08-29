<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/GoogleOAuth.php';
try {
    header('Location: ' . GoogleOAuth::authorizationUrl());
    exit;
} catch (Throwable $exception) {
    $_SESSION['google_login_error'] = $exception->getMessage();
    header('Location: login.php');
    exit;
}

