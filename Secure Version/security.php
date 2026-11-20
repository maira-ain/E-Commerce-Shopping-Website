<?php

declare(strict_types=1);

function apply_security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf_token(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function require_admin(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
        header('Location: login.php');
        exit();
    }
}

function is_valid_username(string $username): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_]{3,20}$/', $username);
}

function is_valid_password(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }

    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasLower = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);

    return (bool) ($hasUpper && $hasLower && $hasNumber);
}

function positive_int(mixed $value, int $default = 0): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $number === false ? $default : $number;
}
