<?php
if (!defined("AUTH_SESSION_LIFETIME")) {
  define("AUTH_SESSION_LIFETIME", 8 * 60 * 60);
}

if (session_status() === PHP_SESSION_NONE) {
  $session_directory = dirname(__DIR__) . "/data/sessions";

  if (!is_dir($session_directory)) {
    @mkdir($session_directory, 0775, true);
  }

  if (is_dir($session_directory) && is_writable($session_directory)) {
    session_save_path($session_directory);
  }

  ini_set("session.use_strict_mode", "1");
  ini_set("session.use_only_cookies", "1");
  ini_set("session.gc_maxlifetime", (string) AUTH_SESSION_LIFETIME);
  ini_set("session.gc_probability", "1");
  ini_set("session.gc_divisor", "100");
  ini_set("session.cookie_httponly", "1");
  ini_set("session.cookie_samesite", "Lax");
  $session_secure = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

  session_set_cookie_params([
    "lifetime" => AUTH_SESSION_LIFETIME,
    "path" => "/",
    "secure" => $session_secure,
    "httponly" => true,
    "samesite" => "Lax"
  ]);

  session_start();
}

require_once("psw.php");

require_once("config.php");

require_once("db.c.php");

$Database = new Database();
$db = $Database->getConnection();

require_once(__DIR__ . "/auth.php");

auth_refresh_session($db);

require_once("functions.php");

get_params();

require_once(BASE_ROOT . "/classes/MailsMy/MailsMy.c.php");

require_once(BASE_ROOT . "/classes/DatasDbMy/DatasDbMy.c.php");
$datasDbMy = new DatasDbMy( $db );
