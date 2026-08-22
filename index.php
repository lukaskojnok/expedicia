<?php

if ( isset($_GET["doo"]) AND $_GET["doo"] == 1 ):
 setcookie("doo", "1", time() + 2592000, "/");
 header("Location:/");
 die();
endif;
if ( isset($_GET["doo"]) AND $_GET["doo"] == "clear" ):
 setcookie("doo", "", time(), "/");
 header("Location:/");
 die();
endif;

// if ( $_SERVER["REMOTE_ADDR"] != "45.152.96.7" AND !isset($_COOKIE["doo"]) ) exit;

if ( $_SERVER["REMOTE_ADDR"] == "45.152.96.6" OR isset($_COOKIE["doo"]) ) {
  require_once("indexMAIN.php");
} else {
  require_once("indexMAIN.php");
}

