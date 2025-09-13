<?php
$config = require __DIR__ . '/config.php';

date_default_timezone_set(isset($config['timezone']) ? $config['timezone'] : 'UTC');

if (session_status() === PHP_SESSION_NONE) {
    session_name(isset($config['security']['session_name']) ? $config['security']['session_name'] : 'sigara_sess');
    session_start();
}

function is_logged_in() { return isset($_SESSION['user_key']); }
function require_login() { if (!is_logged_in()) { header('Location: index.php'); exit; } }
function current_user_key() { return isset($_SESSION['user_key']) ? $_SESSION['user_key'] : null; }
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function send_email($to, $subject, $body) {
    global $config;
    $smtp = isset($config['smtp']) ? $config['smtp'] : array();
    $headers = '';
    if (!empty($smtp['from_email'])) {
        $fromName = isset($smtp['from_name']) ? $smtp['from_name'] : 'Sigara Sayaç';
        $headers .= 'From: ' . $fromName . ' <' . $smtp['from_email'] . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }
    // Basit mail() denemesi; XAMPP'te ek yapılandırma gerekebilir
    return @mail($to, $subject, $body, $headers);
}

// Remember me helpers
function remember_cookie_name() {
    global $config;
    return isset($config['security']['remember_cookie_name']) ? $config['security']['remember_cookie_name'] : 'sigara_remember';
}
function sign_remember_payload($uk, $exp) {
    global $config;
    $secret = isset($config['security']['remember_me_secret']) ? $config['security']['remember_me_secret'] : 'dev-secret';
    return hash_hmac('sha256', $uk . '|' . $exp, $secret);
}
function set_remember_cookie($uk, $exp = null) {
    global $config;
    $days = (int)(isset($config['security']['remember_me_days']) ? $config['security']['remember_me_days'] : 30);
    if ($exp === null) { $exp = time() + $days * 86400; }
    $name = remember_cookie_name();
    $payload = $uk . '|' . $exp;
    $sig = sign_remember_payload($uk, $exp);
    $val = $payload . '|' . $sig;
    setcookie($name, $val, $exp, '/', '', false, true);
}
function clear_remember_cookie() {
    $name = remember_cookie_name();
    setcookie($name, '', time() - 3600, '/', '', false, true);
}
function try_auto_login_from_cookie() {
    if (is_logged_in()) { return; }
    $name = remember_cookie_name();
    if (empty($_COOKIE[$name])) { return; }
    $parts = explode('|', $_COOKIE[$name]);
    if (count($parts) !== 3) { return; }
    $uk = $parts[0];
    $expStr = $parts[1];
    $sig = $parts[2];
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

