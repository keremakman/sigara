<?php
$config = require __DIR__ . '/config.php';

date_default_timezone_set($config['timezone'] ?? 'UTC');

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['security']['session_name'] ?? 'sigara_sess');
    session_start();
}

function is_logged_in(): bool { return isset($_SESSION['user_key']); }
function require_login(): void { if (!is_logged_in()) { header('Location: index.php'); exit; } }
function current_user_key(): ?string { return $_SESSION['user_key'] ?? null; }
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function send_email(string $to, string $subject, string $body): bool {
    global $config;
    $smtp = $config['smtp'] ?? [];
    $headers = '';
    if (!empty($smtp['from_email'])) {
        $fromName = $smtp['from_name'] ?? 'Sigara Sayaç';
        $headers .= 'From: ' . $fromName . ' <' . $smtp['from_email'] . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }
    // Basit mail() denemesi; XAMPP'te ek yapılandırma gerekebilir
    return @mail($to, $subject, $body, $headers);
}

// Remember me helpers
function remember_cookie_name(): string {
    global $config;
    return $config['security']['remember_cookie_name'] ?? 'sigara_remember';
}
function sign_remember_payload(string $uk, int $exp): string {
    global $config;
    $secret = $config['security']['remember_me_secret'] ?? 'dev-secret';
    return hash_hmac('sha256', $uk . '|' . $exp, $secret);
}
function set_remember_cookie(string $uk, ?int $exp = null): void {
    global $config;
    $days = (int)($config['security']['remember_me_days'] ?? 30);
    if ($exp === null) { $exp = time() + $days * 86400; }
    $name = remember_cookie_name();
    $payload = $uk . '|' . $exp;
    $sig = sign_remember_payload($uk, $exp);
    $val = $payload . '|' . $sig;
    setcookie($name, $val, $exp, '/', '', false, true);
}
function clear_remember_cookie(): void {
    $name = remember_cookie_name();
    setcookie($name, '', time() - 3600, '/', '', false, true);
}
function try_auto_login_from_cookie(): void {
    if (is_logged_in()) { return; }
    $name = remember_cookie_name();
    if (empty($_COOKIE[$name])) { return; }
    $parts = explode('|', $_COOKIE[$name]);
    if (count($parts) !== 3) { return; }
    [$uk, $expStr, $sig] = $parts;
    $exp = (int)$expStr;
    if ($exp < time()) { clear_remember_cookie(); return; }
    $calc = sign_remember_payload($uk, $exp);
    if (!hash_equals($calc, $sig)) { return; }
    if ($uk !== 'user1' && $uk !== 'user2') { return; }
    $_SESSION['user_key'] = $uk;
    generate_csrf_token();
    set_remember_cookie($uk);
}

// Auto-login from cookie if present
if (!is_logged_in()) {
    try_auto_login_from_cookie();
}

