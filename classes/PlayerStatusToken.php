<?php
declare(strict_types=1);

final class PlayerStatusToken
{
    public static function issue(int $userId, int $lifetimeSeconds = 43200): string
    {
        if ($userId < 1) throw new InvalidArgumentException('Invalid player.');
        $expires = time() + $lifetimeSeconds;
        $payload = $userId . '.' . $expires;
        return $payload . '.' . hash_hmac('sha256', $payload, AppConfig::playerStatusSecret());
    }

    public static function verify(string $token): int
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) return 0;
        [$userId, $expires, $signature] = $parts;
        if ((int)$userId < 1 || (int)$expires < time()) return 0;
        $payload = $userId . '.' . $expires;
        $expected = hash_hmac('sha256', $payload, AppConfig::playerStatusSecret());
        return hash_equals($expected, $signature) ? (int)$userId : 0;
    }
}
