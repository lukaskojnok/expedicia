<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("config/common.php");

// require_once("crons/crons.php");

require_once("header_s.php");

require_once("header_html.php");

if ($is_page_login) {
  include("login.php");
} else {
  include("main.php");
}

require_once("header_footer.php");