<?php
declare(strict_types=1);

final class GoogleOAuth
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';

    public static function configured(): bool
    {
        return AppConfig::googleClientId() !== '' && AppConfig::googleClientSecret() !== '' && AppConfig::googleRedirectUri() !== '';
    }

    public static function authorizationUrl(): string
    {
        if (!self::configured()) throw new RuntimeException('Google sign-in is not configured yet.');
        $state = bin2hex(random_bytes(24));
        $verifier = self::base64Url(random_bytes(48));
        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_verifier'] = $verifier;
        return self::AUTH_ENDPOINT . '?' . http_build_query([
            'client_id' => AppConfig::googleClientId(),
            'redirect_uri' => AppConfig::googleRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => self::base64Url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function complete(string $code, string $state): array
    {
        $expected = (string) ($_SESSION['google_oauth_state'] ?? '');
        $verifier = (string) ($_SESSION['google_oauth_verifier'] ?? '');
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_verifier']);
        if ($expected === '' || !hash_equals($expected, $state) || $verifier === '') throw new RuntimeException('Google sign-in session expired. Try again.');

        $tokens = self::request(self::TOKEN_ENDPOINT, [
            'code' => $code,
            'client_id' => AppConfig::googleClientId(),
            'client_secret' => AppConfig::googleClientSecret(),
            'redirect_uri' => AppConfig::googleRedirectUri(),
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]);
        $accessToken = (string) ($tokens['access_token'] ?? '');
        if ($accessToken === '') throw new RuntimeException('Google did not return an access token.');
        $profile = self::request(self::USERINFO_ENDPOINT, null, ['Authorization: Bearer ' . $accessToken]);
        if (empty($profile['sub']) || empty($profile['email']) || empty($profile['email_verified'])) throw new RuntimeException('Google could not verify this email account.');
        return $profile;
    }

    private static function request(string $url, ?array $post = null, array $headers = []): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_HTTPHEADER=>$headers]);
        if ($post !== null) curl_setopt_array($curl, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
        $body = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl);
        if ($body === false || $status < 200 || $status >= 300) throw new RuntimeException('Google sign-in request failed.' . ($error !== '' ? ' ' . $error : ''));
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        return is_array($data) ? $data : [];
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
