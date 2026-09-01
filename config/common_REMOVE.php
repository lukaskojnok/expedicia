<?php
if (session_status() === PHP_SESSION_NONE) {
  ini_set("session.use_strict_mode", "1");
  ini_set("session.cookie_httponly", "1");
  ini_set("session.cookie_samesite", "Lax");
  if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ini_set("session.cookie_secure", "1");
  session_start();
}

require_once("psw.php");

require_once("config.php");

require_once("db.c.php");

$Database = new Database();
$db = $Database->getConnection();

require_once(__DIR__ . "/auth.php");

require_once("functions.php");

get_params();

require_once(BASE_ROOT . "/classes/MailsMy/MailsMy.c.php");

require_once(BASE_ROOT . "/classes/DatasDbMy/DatasDbMy.c.php");
$datasDbMy = new DatasDbMy( $db );
