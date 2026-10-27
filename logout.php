<?php
// logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cookie_params = session_get_cookie_params();
session_unset();
session_destroy();
if (ini_get('session.use_cookies')) {
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookie_params['path'],
        $cookie_params['domain'],
        $cookie_params['secure'],
        $cookie_params['httponly']
    );
}

$application_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
header("Location: {$application_path}/index.php");
exit;
?>
