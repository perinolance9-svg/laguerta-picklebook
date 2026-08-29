<?php
declare(strict_types=1);

final class Auth
{
    public static function user(): ?array
    {
        return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user']) ? $_SESSION['auth_user'] : null;
    }

    public static function id(): int
    {
        return (int) (self::user()['user_id'] ?? 0);
    }

    public static function role(): string
    {
        return (string) (self::user()['role'] ?? '');
    }

    public static function login(PDO $connection, string $identity, string $password): bool
    {
        $statement = $connection->prepare(
            'SELECT user_id, first_name, last_name, username, email, password, role, is_active, must_change_password
             FROM users WHERE username = :username OR email = :email LIMIT 1'
        );
        $identity = trim($identity);
        $statement->execute(['username' => $identity, 'email' => $identity]);
        $user = $statement->fetch();
        if (!$user || !(bool) $user['is_active'] || !password_verify($password, (string) $user['password'])) return false;

        session_regenerate_id(true);
        unset($user['password'], $user['is_active']);
        $_SESSION['auth_user'] = $user;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return true;
    }

    public static function loginWithGoogle(PDO $connection, array $profile): void
    {
        $sub = trim((string) ($profile['sub'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        if ($sub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($profile['email_verified'])) throw new RuntimeException('Google account verification failed.');

        $statement = $connection->prepare('SELECT user_id,first_name,last_name,username,email,role,is_active,must_change_password FROM users WHERE google_sub=:sub OR email=:email LIMIT 1');
        $statement->execute(['sub'=>$sub,'email'=>$email]);
        $user = $statement->fetch();
        if ($user) {
            if (!(bool)$user['is_active']) throw new RuntimeException('This account is disabled.');
            $update = $connection->prepare('UPDATE users SET google_sub=:sub,avatar_url=:avatar WHERE user_id=:id');
            $update->execute(['sub'=>$sub,'avatar'=>(string)($profile['picture']??''),'id'=>$user['user_id']]);
            unset($user['is_active']);
            $user['must_change_password'] = 0;
        } else {
            $first = trim((string) ($profile['given_name'] ?? 'Google')) ?: 'Google';
            $last = trim((string) ($profile['family_name'] ?? 'Player')) ?: 'Player';
            $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr($email, '@', true) ?: 'player')) ?: 'player';
            $username = substr($base, 0, 38);
            for ($suffix=0;;$suffix++) {
                $candidate = $username . ($suffix ? '.' . $suffix : '');
                $check = $connection->prepare('SELECT COUNT(*) FROM users WHERE username=:username');
                $check->execute(['username'=>$candidate]);
                if ((int)$check->fetchColumn() === 0) { $username=$candidate; break; }
            }
            $insert = $connection->prepare("INSERT INTO users(first_name,last_name,username,email,password,role,is_active,must_change_password,google_sub,avatar_url) VALUES(:first,:last,:username,:email,:password,'Player',1,0,:sub,:avatar)");
            $insert->execute(['first'=>$first,'last'=>$last,'username'=>$username,'email'=>$email,'password'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'sub'=>$sub,'avatar'=>(string)($profile['picture']??'')]);
            $user=['user_id'=>(int)$connection->lastInsertId(),'first_name'=>$first,'last_name'=>$last,'username'=>$username,'email'=>$email,'role'=>'Player','must_change_password'=>0];
        }
        self::establish($user);
    }

    private static function establish(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['auth_user'] = $user;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::user()) {
            $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? 'user-dashboard.php';
            header('Location: login.php');
            exit;
        }
        if ((bool) (self::user()['must_change_password'] ?? false)) {
            header('Location: change-password.php');
            exit;
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        if (self::role() !== $role) {
            $destination = self::role() === 'Admin'
                ? 'admin-dashboard.php'
                : 'user-dashboard.php';
            header('Location: ' . $destination);
            exit;
        }
    }

    public static function csrf(): string
    {
        if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return (string) $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): void
    {
        if (!hash_equals(self::csrf(), (string) $token)) throw new RuntimeException('The form expired. Reload and try again.');
    }
}
