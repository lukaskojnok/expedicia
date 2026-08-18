<?php
if ( session_status() == PHP_SESSION_NONE ) {
  session_start();
}

require_once("psw.php");

require_once("config.php");

require_once("db.c.php");

$Database = new Database();
$db = $Database->getConnection();

require_once("functions.php");

get_params();

require_once(BASE_ROOT . "/classes/MailsMy/MailsMy.c.php");

require_once(BASE_ROOT . "/classes/DatasDbMy/DatasDbMy.c.php");
$datasDbMy = new DatasDbMy( $db );