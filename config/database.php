<?php
declare(strict_types=1);

function appSetting(string $key, string $default = ''): string
{
    $environment = getenv($key);
    if ($environment !== false && trim((string) $environment) !== '') return trim((string) $environment);
    static $private = null;
    if ($private === null) $private = is_file(__DIR__ . '/private.php') ? (array) require __DIR__ . '/private.php' : [];
    return array_key_exists($key, $private) ? (string) $private[$key] : $default;
}

final class Database
{
    public static function connect(): PDO
    {
        $host = appSetting('DB_HOST', appSetting('MYSQLHOST', '127.0.0.1'));
        $port = appSetting('DB_PORT', appSetting('MYSQLPORT', '3306'));
        $name = appSetting('DB_NAME', appSetting('MYSQLDATABASE', 'pickle_book'));
        $user = appSetting('DB_USER', appSetting('MYSQLUSER', 'root'));
        $pass = appSetting('DB_PASS', appSetting('MYSQLPASSWORD'));
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        try {
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            throw new RuntimeException('Database connection failed. Check the setup in config/database.php.');
        }
    }
}

final class AppConfig
{
    public static function payMongoSecretKey(): string { return appSetting('PAYMONGO_SECRET_KEY'); }
    public static function payMongoWebhookSecret(): string { return appSetting('PAYMONGO_WEBHOOK_SECRET'); }
    public static function payMongoMode(): string { return appSetting('PAYMONGO_MODE') === 'live' ? 'live' : 'test'; }
    public static function baseUrl(): string { return rtrim(appSetting('APP_BASE_URL'), '/'); }
    public static function bookingFeeCentavos(): int { return 15000; }

    public static function personalGcashName(): string { return appSetting('PERSONAL_GCASH_NAME'); }
    public static function personalGcashNumber(): string { return appSetting('PERSONAL_GCASH_NUMBER'); }
    public static function googleClientId(): string { return appSetting('GOOGLE_CLIENT_ID'); }
    public static function googleClientSecret(): string { return appSetting('GOOGLE_CLIENT_SECRET'); }
    public static function googleRedirectUri(): string
    {
        $configured = appSetting('GOOGLE_REDIRECT_URI');
        return $configured !== '' ? $configured : self::baseUrl() . '/google-callback.php';
    }
    public static function personalGcashAdminPin(): string
    {
        return appSetting('PERSONAL_GCASH_ADMIN_PIN');
    }
}
