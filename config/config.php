<?php
$DOPRAVA_KODY = [
  "SHIPPING4" => ["name" => "Osobný odber", "api" => "", "class" => "shipping4"],
  "SHIPPING62" => ["name" => "Packeta Zásielkovňa", "api" => "packeta_zasielkovna", "class" => "shipping62"],
  "SHIPPING65" => ["name" => "DPD – odberné miesto", "api" => "dpd_odberne_miesto", "carrier" => "dpd", "product" => 17, "class" => "shipping65"],
  "SHIPPING68" => ["name" => "DPD – doručenie na adresu", "api" => "dpd_dorucenie_na_adresu", "carrier" => "dpd", "product" => 9, "class" => "shipping68"]
];
define("DOPRAVA_KODY", $DOPRAVA_KODY);


$GLOBALS["pageURL2"] = "expedicia.kojnok.sk";

if (substr($_SERVER["DOCUMENT_ROOT"], -1) == "/") {
  define("BASE_ROOT", $_SERVER["DOCUMENT_ROOT"]);
} else {
  define("BASE_ROOT", $_SERVER["DOCUMENT_ROOT"] . "/");
}

define("DATAS_ROOT_SHORT", "data/");
define("DATAS_ROOT", BASE_ROOT . DATAS_ROOT_SHORT);

// define("IP_ADDRESS", $_SERVER["HTTP_CF_CONNECTING_IP"]);
define("IP_ADDRESS", $_SERVER["REMOTE_ADDR"]);
// define("COUNTRY", $_SERVER["HTTP_CF_IPCOUNTRY"]);
// define("COUNTRY_NAME", $_SERVER["HTTP_GEOIP_COUNTRY_NAME"]);
define("COUNTRY_NAME", "");
define("COUNTRY_CODE", $_SERVER["GEOIP_COUNTRY_CODE"]);
define("HTTP_USER_AGENT", $_SERVER["HTTP_USER_AGENT"]);

define("WEB_URL", $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"]);
define("WEB_URL_ACTUAL", $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
define("WEB_URL_NAME", "expedicia.kojnok.sk");
define("WEB_EMAIL_1", "novinky@expedicia.kojnok.sk");
define("WEB_PHONE_1", "0000 111 222");

$page = ( isset( $_GET["page"] ) ? $_GET["page"] : "" ); $page = strip_tags($page);
$page2 = ( isset( $_GET["page2"] ) ? $_GET["page2"] : "" ); $page2 = strip_tags($page2);
$page3 = ( isset( $_GET["page3"] ) ? $_GET["page3"] : "" ); $page3 = strip_tags($page3);

include_once("consts.php");

/////////////////////////////////////////////////////////////////////////////////////////////////////////// LANGS

$jazyky_arr["sk"] = "Slovenčina";
// $jazyky_arr["en"] = "Angličtina";
// $jazyky_arr["pl"] = "Poľština";
// $jazyky_arr["hu"] = "Maďarčina";

define("JAZYKY_ARR", $jazyky_arr);

/////////////////////////////////////////////////////////////////////////////////////////////////////////// LANGS
define("PRIMARY_LANG", "sk");

function SET_LANG( $page_lang ) {
  global $page, $page2, $page3, $LANG;

  if ( in_array($page_lang, ["en", "hu", "pl", "de"]) ) {
    // include($_SERVER["DOCUMENT_ROOT"] . "langs/{$page_lang}.php");

    define("LANG", $page_lang);
    define("LANG_URL", "/{$page_lang}");

    if ( $page_lang == "en" ) {
      define("LANG_META", "en-EN");
    } elseif ( $page_lang == "hu" ) {
      define("LANG_META", "hu-HU");
    } elseif ( $page_lang == "pl" ) {
      define("LANG_META", "pl-PL");
    } elseif ( $page_lang == "de" ) {
      define("LANG_META", "de-DE");
    }

    $page = $page2;
    $page2 = $page3;

  } else {
    define("LANG", "sk"); define("LANG_META", "sk-SK"); define("LANG_URL", "");

    // include($_SERVER["DOCUMENT_ROOT"] . "langs/sk.php");
  }
}


// if ( empty($_SERVER['HTTP_X_REQUESTED_WITH']) && !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ) {
// if ( empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest' ) {
  SET_LANG( $page );
// }
/////////////////////////////////////////////////////////////////////////////////////////////////////////// LANGS
