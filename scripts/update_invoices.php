<?php
// Cron cez CLI: php scripts/update_invoices.php
// Cron cez URL: /scripts/update_invoices.php?cron=1&cron_key=HODNOTA_Z_UPDATE_ORDERS_CRON_KEY

// php /cesta/k/webu/scripts/update_invoices.php

ini_set("display_errors", 0);
ini_set("display_startup_errors", 0);
error_reporting(E_ALL);
date_default_timezone_set("Europe/Bratislava");

$is_cli = PHP_SAPI === "cli";

if ($is_cli) {
  $_SERVER["DOCUMENT_ROOT"] = dirname(__DIR__);
  $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
  $_SERVER["HTTP_USER_AGENT"] = "Expedicia cron";
  $_SERVER["REQUEST_SCHEME"] = "http";
  $_SERVER["HTTP_HOST"] = "localhost";
  $_SERVER["REQUEST_URI"] = "/scripts/update_invoices.php";
  $_SERVER["GEOIP_COUNTRY_CODE"] = "";
}

require_once __DIR__ . "/../config/common.php";

function update_fail_response(string $message, int $http_code = 400): void {
  if (PHP_SAPI !== "cli") {
    http_response_code($http_code);
  }

  echo PHP_SAPI === "cli"
    ? $message . PHP_EOL
    : htmlspecialchars($message, ENT_QUOTES, "UTF-8");
  exit(1);
}

function update_parse_date(string $value, bool $date_only = false): ?DateTimeImmutable {
  $format = $date_only ? "!Y-m-d" : "!Y-m-d H:i:s";
  $date = DateTimeImmutable::createFromFormat($format, $value);
  $errors = DateTimeImmutable::getLastErrors();

  if ($date === false || ($errors !== false && ($errors["warning_count"] > 0 || $errors["error_count"] > 0))) {
    return null;
  }

  return $date;
}

function update_finish_log(PDO $db, int $log_id, string $status, int $new_orders, int $changed_orders, int $unchanged_orders, ?string $message = null): void {
  $query = $db->prepare("
    UPDATE order_update_logs SET
      status = :status,
      finished_at = NOW(),
      new_orders = :new_orders,
      changed_orders = :changed_orders,
      unchanged_orders = :unchanged_orders,
      message = :message
    WHERE id = :id
    LIMIT 1
  ");
  $query->execute([
    ":status" => $status,
    ":new_orders" => $new_orders,
    ":changed_orders" => $changed_orders,
    ":unchanged_orders" => $unchanged_orders,
    ":message" => $message,
    ":id" => $log_id
  ]);
}

function update_background_response(array $data, int $http_code = 200): void {
  http_response_code($http_code);
  header("Content-Type: application/json; charset=UTF-8");
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit($http_code >= 400 ? 1 : 0);
}

function update_release_auto_lock(PDO $db, bool &$lock_acquired): void {
  if (!$lock_acquired) {
    return;
  }

  $db->query("SELECT RELEASE_LOCK('expedicia_auto_order_update')");
  $lock_acquired = false;
}

$cron_requested = !$is_cli && isset($_GET["cron"]) && $_GET["cron"] === "1";
$cron_key = (string) ($_GET["cron_key"] ?? "");
$configured_cron_key = defined("UPDATE_ORDERS_CRON_KEY") ? (string) UPDATE_ORDERS_CRON_KEY : "";
$is_http_cron = $cron_requested
  && $configured_cron_key !== ""
  && $cron_key !== ""
  && hash_equals($configured_cron_key, $cron_key);

if ($cron_requested && !$is_http_cron) {
  update_fail_response("Cron kľúč nie je nastavený alebo nie je platný.", 403);
}

$is_cron = $is_cli || $is_http_cron;

if (!$is_cron) {
  if (!auth_is_logged_in()) {
    header("Location: /login.php");
    exit;
  }

  if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    update_fail_response("Aktualizáciu je možné spustiť iba odoslaním formulára.", 405);
  }

  if (!auth_csrf_is_valid($_POST["csrf_token"] ?? "")) {
    update_fail_response("Platnosť formulára vypršala. Obnovte stránku a skúste to znova.", 403);
  }
}

$zdroj = "shoptet";
$zdroj_eshop = "okfish.sk";
$shoptet_orders_hash = SHOPTET_HASH_ORDERS;
$request_data = $is_cron ? $_GET : $_POST;
$is_background = !$is_cron && ($request_data["auto_check"] ?? "") === "1";
$source = $is_cron ? "cron" : ($is_background ? "automatic" : "manual");
$request_type = (string) ($request_data["request_type"] ?? "quick");
$allowed_request_types = ["quick", "today", "yesterday", "last_7_days", "custom"];

if (!in_array($request_type, $allowed_request_types, true)) {
  update_fail_response("Neznámy typ aktualizácie.");
}

$auto_lock_acquired = false;

if ($is_background) {
  $auto_lock_acquired = (int) $db->query("SELECT GET_LOCK('expedicia_auto_order_update', 0)")->fetchColumn() === 1;

  if (!$auto_lock_acquired) {
    update_background_response([
      "success" => true,
      "updated" => false,
      "message" => "Iná automatická aktualizácia už prebieha."
    ]);
  }

  $query = $db->query("
    SELECT COUNT(*)
    FROM order_update_logs
    WHERE status IN ('running', 'success')
      AND started_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
  ");

  if ((int) $query->fetchColumn() > 0) {
    update_release_auto_lock($db, $auto_lock_acquired);
    update_background_response([
      "success" => true,
      "updated" => false,
      "message" => "Objednávky boli aktualizované pred menej ako 5 minútami."
    ]);
  }
}

$today = new DateTimeImmutable("today");

if ($request_type === "quick") {
  $query = $db->query("
    SELECT started_at
    FROM order_update_logs
    WHERE status = 'success'
    ORDER BY started_at DESC, id DESC
    LIMIT 1
  ");
  $last_successful_update = $query->fetchColumn();
  $date = $last_successful_update
    ? update_parse_date((string) $last_successful_update)
    : $today;
} elseif ($request_type === "today") {
  $date = $today;
} elseif ($request_type === "yesterday") {
  $date = $today->modify("-1 day");
} elseif ($request_type === "last_7_days") {
  $date = $today->modify("-6 days");
} else {
  $date = update_parse_date((string) ($request_data["date_from"] ?? ""), true);
}

if (!$date || $date > new DateTimeImmutable()) {
  update_fail_response("Zadajte platný dátum, ktorý nie je v budúcnosti.");
}

$update_time_from = $date->format("Y-m-d H:i:s");
$update_time_from_url = rawurlencode($update_time_from);
$url = "https://www.okfish.sk/export/ordersFeed.xml?patternId=53&partnerId=4&hash={$shoptet_orders_hash}&updateTimeFrom={$update_time_from_url}";
$admin_id = $is_cron ? null : (int) ($_SESSION["admin_id"] ?? 0);
$return_to = (!$is_cron && ($request_data["return_to"] ?? "") === "updates") ? "updates" : "home";

try {
  $query = $db->prepare("
    INSERT INTO order_update_logs SET
      source = :source,
      request_type = :request_type,
      status = 'running',
      admin_id = :admin_id,
      update_from = :update_from,
      started_at = NOW()
  ");
  $query->execute([
    ":source" => $source,
    ":request_type" => $request_type,
    ":admin_id" => $admin_id ?: null,
    ":update_from" => $update_time_from
  ]);
  $update_log_id = (int) $db->lastInsertId();
} catch (Throwable $e) {
  update_release_auto_lock($db, $auto_lock_acquired);

  if ($is_background) {
    update_background_response([
      "success" => false,
      "updated" => false,
      "message" => "Najprv upravte databázový stĺpec source v tabuľke order_update_logs. " . $e->getMessage()
    ], 500);
  }

  update_fail_response("Najprv vytvorte databázovú tabuľku order_update_logs. " . $e->getMessage(), 500);
}

function xml_text($value): string {
  return trim((string) $value);
}

function xml_nullable($value): ?string {
  $value = trim((string) $value);

  return $value !== "" ? $value : null;
}

function xml_decimal($value, int $decimal_places = 2): string {
  $value = trim((string) $value);
  $value = str_replace(["\xc2\xa0", " "], "", $value);
  $value = str_replace(",", ".", $value);

  if ($value === "" || !is_numeric($value)) {
    $value = 0;
  }

  return number_format((float) $value, $decimal_places, ".", "");
}

try {
  $pocet_novych = 0;
  $pocet_zmenenych = 0;
  $pocet_bez_zmeny = 0;
  $ch = curl_init($url);

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => [
      "Accept: application/xml"
    ]
  ]);

  $xml_content = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_error = curl_error($ch);

  curl_close($ch);

  if ($xml_content === false || $http_code !== 200) {
    throw new RuntimeException("Feed sa nepodarilo načítať. HTTP: {$http_code}. Chyba: {$curl_error}");
  }

  libxml_use_internal_errors(true);

  $xml = simplexml_load_string($xml_content);

  if ($xml === false) {
    $errors = libxml_get_errors();
    $error_messages = [];

    foreach ($errors as $error) {
      $error_messages[] = trim($error->message);
    }

    libxml_clear_errors();

    throw new RuntimeException("XML sa nepodarilo spracovať: " . implode(" | ", $error_messages));
  }

  $query_item_insert = $db->prepare("
  INSERT INTO orders_items SET
    order_id = :order_id,
    type = :type,
    nazov = :nazov,
    kod = :kod,
    ean = :ean,
    plu = :plu,
    variant_nazov = :variant_nazov,
    vyrobca = :vyrobca,
    dodavatel = :dodavatel,
    mnozstvo = :mnozstvo,
    jednotka = :jednotka,
    vaha_kg = :vaha_kg,
    dlzka_cm = :dlzka_cm,
    sirka_cm = :sirka_cm,
    vyska_cm = :vyska_cm,
    stav_polozky = :stav_polozky,
    zlava_percent = :zlava_percent,
    jednotkova_cena_s_dph = :jednotkova_cena_s_dph,
    jednotkova_cena_bez_dph = :jednotkova_cena_bez_dph,
    jednotkova_dph = :jednotkova_dph,
    sadzba_dph = :sadzba_dph,
    celkova_cena_s_dph = :celkova_cena_s_dph,
    celkova_cena_bez_dph = :celkova_cena_bez_dph,
    celkova_dph = :celkova_dph
");

  $db->beginTransaction();

  foreach ($xml->ORDER as $order) {
    $systemove_id = xml_text($order->ORDER_ID);
    $cislo_objednavky = xml_text($order->CODE);
    $data_hash = hash("sha256", $order->asXML());

    if ($systemove_id === "" || $cislo_objednavky === "") {
      continue;
    }

    $billing_address = $order->CUSTOMER->BILLING_ADDRESS;
    $shipping_address = $order->CUSTOMER->SHIPPING_ADDRESS;
    $foxdeli = isset($order->FOXDELI) ? $order->FOXDELI : null;

    $items = [];

    $doprava_typ = null;
    $doprava_nazov = null;
    $doprava_kod = null;
    $platba_typ = null;
    $platba_nazov = null;
    $platba_kod = null;

    foreach ($order->ORDER_ITEMS->ITEM as $item) {
      $item_type = xml_text($item->TYPE);
      $item_nazov = xml_text($item->NAME);
      $item_kod = xml_nullable($item->CODE);

      $items[] = [
        "type" => $item_type,
        "nazov" => $item_nazov,
        "kod" => $item_kod,
        "ean" => xml_nullable($item->EAN),
        "plu" => xml_nullable($item->PLU),
        "variant_nazov" => xml_nullable($item->VARIANT_NAME),
        "vyrobca" => xml_nullable($item->MANUFACTURER),
        "dodavatel" => xml_nullable($item->SUPPLIER),
        "mnozstvo" => xml_decimal($item->AMOUNT, 3),
        "jednotka" => xml_nullable($item->UNIT),
        "vaha_kg" => xml_decimal($item->WEIGHT, 3),
        "dlzka_cm" => null,
        "sirka_cm" => null,
        "vyska_cm" => null,
        "stav_polozky" => xml_nullable($item->STATUS),
        "zlava_percent" => xml_decimal($item->DISCOUNT, 2),
        "jednotkova_cena_s_dph" => xml_decimal($item->UNIT_PRICE->WITH_VAT, 2),
        "jednotkova_cena_bez_dph" => xml_decimal($item->UNIT_PRICE->WITHOUT_VAT, 2),
        "jednotkova_dph" => xml_decimal($item->UNIT_PRICE->VAT, 2),
        "sadzba_dph" => xml_decimal($item->UNIT_PRICE->VAT_RATE, 2),
        "celkova_cena_s_dph" => xml_decimal($item->TOTAL_PRICE->WITH_VAT, 2),
        "celkova_cena_bez_dph" => xml_decimal($item->TOTAL_PRICE->WITHOUT_VAT, 2),
        "celkova_dph" => xml_decimal($item->TOTAL_PRICE->VAT, 2)
      ];

      if ($item_type === "shipping") {
        $doprava_typ = $item_type;
        $doprava_nazov = $item_nazov;
        $doprava_kod = $item_kod;
      }

      if ($item_type === "billing") {
        $platba_typ = $item_type;
        $platba_nazov = $item_nazov;
        $platba_kod = $item_kod;
      }
    }

    $query = $db->prepare("
      SELECT
        id,
        data_hash
      FROM orders
      WHERE zdroj = :zdroj
        AND zdroj_eshop = :zdroj_eshop
        AND systemove_id = :systemove_id
      LIMIT 1
    ");

    $query->execute([
      ":zdroj" => $zdroj,
      ":zdroj_eshop" => $zdroj_eshop,
      ":systemove_id" => $systemove_id
    ]);

    $existing_order = $query->fetch(PDO::FETCH_ASSOC);

    $order_values = [
      ":systemove_id" => $systemove_id,
      ":cislo_objednavky" => $cislo_objednavky,
      ":datum_objednavky" => xml_nullable($order->DATE),
      ":stav_objednavky" => xml_nullable($order->STATUS),

      ":email" => xml_nullable($order->CUSTOMER->EMAIL),
      ":telefon" => xml_nullable($order->CUSTOMER->PHONE),

      ":fakturacne_meno" => xml_nullable($billing_address->NAME),
      ":fakturacna_firma" => xml_nullable($billing_address->COMPANY),
      ":fakturacna_ulica" => xml_nullable($billing_address->STREET),
      ":fakturacne_cislo_domu" => xml_nullable($billing_address->HOUSENUMBER),
      ":fakturacne_mesto" => xml_nullable($billing_address->CITY),
      ":fakturacne_psc" => xml_nullable($billing_address->ZIP),
      ":fakturacna_krajina" => xml_nullable($billing_address->COUNTRY),
      ":ico" => xml_nullable($billing_address->COMPANY_ID),
      ":dic" => xml_nullable($billing_address->VAT_ID),
      ":ic_dph" => null,

      ":dodacie_meno" => xml_nullable($shipping_address->NAME),
      ":dodacia_firma" => xml_nullable($shipping_address->COMPANY),
      ":dodacia_ulica" => xml_nullable($shipping_address->STREET),
      ":dodacie_cislo_domu" => xml_nullable($shipping_address->HOUSENUMBER),
      ":dodacie_mesto" => xml_nullable($shipping_address->CITY),
      ":dodacie_psc" => xml_nullable($shipping_address->ZIP),
      ":dodacia_krajina" => xml_nullable($shipping_address->COUNTRY),

      ":mena" => xml_text($order->CURRENCY->CODE) ?: "EUR",
      ":kurz" => xml_decimal($order->CURRENCY->EXCHANGE_RATE, 6),
      ":cena_s_dph" => xml_decimal($order->TOTAL_PRICE->WITH_VAT, 2),
      ":cena_bez_dph" => xml_decimal($order->TOTAL_PRICE->WITHOUT_VAT, 2),
      ":dph" => xml_decimal($order->TOTAL_PRICE->VAT, 2),
      ":zaokruhlenie" => xml_decimal($order->TOTAL_PRICE->ROUNDING, 2),
      ":cena_na_uhradu" => xml_decimal($order->TOTAL_PRICE->PRICE_TO_PAY, 2),
      ":uhradene" => (int) xml_text($order->TOTAL_PRICE->PAID),
      ":uhradena_suma" => xml_decimal($order->TOTAL_PRICE->AMOUNT_PAID, 2),

      ":platba_typ" => $platba_typ,
      ":platba_nazov" => $platba_nazov,
      ":platba_kod" => $platba_kod,

      ":doprava_typ" => $doprava_typ,
      ":doprava_nazov" => $doprava_nazov,
      ":doprava_kod" => $doprava_kod,

      ":cislo_balika" => xml_nullable($order->PACKAGE_NUMBER),
      ":vaha_kg" => xml_decimal($order->WEIGHT, 3),
      ":parcelShopId" => $foxdeli !== null ? xml_nullable($foxdeli->PICK_UP_PLACE) : null,

      ":foxdeli_shipping_code" => $foxdeli !== null ? xml_nullable($foxdeli->SHIPPING_CODE) : null,
      ":foxdeli_shipping_type" => $foxdeli !== null ? xml_nullable($foxdeli->SHIPPING_TYPE) : null,
      ":foxdeli_delivery_price_to_pay" => $foxdeli !== null ? xml_decimal($foxdeli->DELIVERY_PRICE_TO_PAY, 2) : null,
      ":foxdeli_pick_up_place" => $foxdeli !== null ? xml_nullable($foxdeli->PICK_UP_PLACE) : null,
      ":foxdeli_currency_code" => $foxdeli !== null ? xml_nullable($foxdeli->CURRENCY_CODE) : null,
      ":foxdeli_variable_symbol" => $foxdeli !== null ? xml_nullable($foxdeli->VARIABLE_SYMBOL) : null,

      ":poznamka_zakaznika" => xml_nullable($order->REMARK),
      ":poznamka_obchodu" => xml_nullable($order->SHOP_REMARK),

      ":data_hash" => $data_hash
    ];

    $ulozit_polozky = false;

    if (!$existing_order) {
      $query = $db->prepare("
        INSERT INTO orders SET
          zdroj = :zdroj,
          zdroj_eshop = :zdroj_eshop,
          systemove_id = :systemove_id,
          cislo_objednavky = :cislo_objednavky,
          cislo_faktury = NULL,
          datum_objednavky = :datum_objednavky,
          stav_objednavky = :stav_objednavky,

          status_vyskladnenie = 'nove',
          status_expedicia = 'nove',

          email = :email,
          telefon = :telefon,

          fakturacne_meno = :fakturacne_meno,
          fakturacna_firma = :fakturacna_firma,
          fakturacna_ulica = :fakturacna_ulica,
          fakturacne_cislo_domu = :fakturacne_cislo_domu,
          fakturacne_mesto = :fakturacne_mesto,
          fakturacne_psc = :fakturacne_psc,
          fakturacna_krajina = :fakturacna_krajina,
          ico = :ico,
          dic = :dic,
          ic_dph = :ic_dph,

          dodacie_meno = :dodacie_meno,
          dodacia_firma = :dodacia_firma,
          dodacia_ulica = :dodacia_ulica,
          dodacie_cislo_domu = :dodacie_cislo_domu,
          dodacie_mesto = :dodacie_mesto,
          dodacie_psc = :dodacie_psc,
          dodacia_krajina = :dodacia_krajina,

          mena = :mena,
          kurz = :kurz,
          cena_s_dph = :cena_s_dph,
          cena_bez_dph = :cena_bez_dph,
          dph = :dph,
          zaokruhlenie = :zaokruhlenie,
          cena_na_uhradu = :cena_na_uhradu,
          uhradene = :uhradene,
          uhradena_suma = :uhradena_suma,

          platba_typ = :platba_typ,
          platba_nazov = :platba_nazov,
          platba_kod = :platba_kod,

          doprava_typ = :doprava_typ,
          doprava_nazov = :doprava_nazov,
          doprava_kod = :doprava_kod,

          cislo_balika = :cislo_balika,
          vaha_kg = :vaha_kg,
          parcelShopId = :parcelShopId,

          foxdeli_shipping_code = :foxdeli_shipping_code,
          foxdeli_shipping_type = :foxdeli_shipping_type,
          foxdeli_delivery_price_to_pay = :foxdeli_delivery_price_to_pay,
          foxdeli_pick_up_place = :foxdeli_pick_up_place,
          foxdeli_currency_code = :foxdeli_currency_code,
          foxdeli_variable_symbol = :foxdeli_variable_symbol,

          poznamka_zakaznika = :poznamka_zakaznika,
          poznamka_obchodu = :poznamka_obchodu,

          data_hash = :data_hash,
          zmena = 0,
          zmena_at = NULL,
          zmena_poznamka = NULL
      ");

      $query->execute(array_merge([
        ":zdroj" => $zdroj,
        ":zdroj_eshop" => $zdroj_eshop
      ], $order_values));

      $LAST_ID_ROW = $db->lastInsertId();
      $order_id = (int) $LAST_ID_ROW;

      $ulozit_polozky = true;
      $pocet_novych++;
    } elseif ($existing_order["data_hash"] !== $data_hash) {
      $order_id = (int) $existing_order["id"];

      $query = $db->prepare("
        UPDATE orders SET
          cislo_objednavky = :cislo_objednavky,
          datum_objednavky = :datum_objednavky,
          stav_objednavky = :stav_objednavky,

          email = :email,
          telefon = :telefon,

          fakturacne_meno = :fakturacne_meno,
          fakturacna_firma = :fakturacna_firma,
          fakturacna_ulica = :fakturacna_ulica,
          fakturacne_cislo_domu = :fakturacne_cislo_domu,
          fakturacne_mesto = :fakturacne_mesto,
          fakturacne_psc = :fakturacne_psc,
          fakturacna_krajina = :fakturacna_krajina,
          ico = :ico,
          dic = :dic,
          ic_dph = :ic_dph,

          dodacie_meno = :dodacie_meno,
          dodacia_firma = :dodacia_firma,
          dodacia_ulica = :dodacia_ulica,
          dodacie_cislo_domu = :dodacie_cislo_domu,
          dodacie_mesto = :dodacie_mesto,
          dodacie_psc = :dodacie_psc,
          dodacia_krajina = :dodacia_krajina,

          mena = :mena,
          kurz = :kurz,
          cena_s_dph = :cena_s_dph,
          cena_bez_dph = :cena_bez_dph,
          dph = :dph,
          zaokruhlenie = :zaokruhlenie,
          cena_na_uhradu = :cena_na_uhradu,
          uhradene = :uhradene,
          uhradena_suma = :uhradena_suma,

          platba_typ = :platba_typ,
          platba_nazov = :platba_nazov,
          platba_kod = :platba_kod,

          doprava_typ = :doprava_typ,
          doprava_nazov = :doprava_nazov,
          doprava_kod = :doprava_kod,

          cislo_balika = :cislo_balika,
          vaha_kg = :vaha_kg,
          parcelShopId = :parcelShopId,

          foxdeli_shipping_code = :foxdeli_shipping_code,
          foxdeli_shipping_type = :foxdeli_shipping_type,
          foxdeli_delivery_price_to_pay = :foxdeli_delivery_price_to_pay,
          foxdeli_pick_up_place = :foxdeli_pick_up_place,
          foxdeli_currency_code = :foxdeli_currency_code,
          foxdeli_variable_symbol = :foxdeli_variable_symbol,

          poznamka_zakaznika = :poznamka_zakaznika,
          poznamka_obchodu = :poznamka_obchodu,

          data_hash = :data_hash,
          zmena = 1,
          zmena_at = NOW(),
          zmena_poznamka = 'Objednávka bola zmenená v zdrojovom e-shope.'
        WHERE id = :id
        LIMIT 1
      ");

      $update_order_values = $order_values;

      unset($update_order_values[":systemove_id"]);

      $query->execute(array_merge($update_order_values, [
        ":id" => $order_id
      ]));

      $query = $db->prepare("
        DELETE FROM orders_items
        WHERE order_id = :order_id
      ");

      $query->execute([
        ":order_id" => $order_id
      ]);

      $ulozit_polozky = true;
      $pocet_zmenenych++;
    } else {
      $pocet_bez_zmeny++;
    }

    if ($ulozit_polozky) {
      foreach ($items as $item) {
        $query_item_insert->execute([
          ":order_id" => $order_id,
          ":type" => $item["type"],
          ":nazov" => $item["nazov"],
          ":kod" => $item["kod"],
          ":ean" => $item["ean"],
          ":plu" => $item["plu"],
          ":variant_nazov" => $item["variant_nazov"],
          ":vyrobca" => $item["vyrobca"],
          ":dodavatel" => $item["dodavatel"],
          ":mnozstvo" => $item["mnozstvo"],
          ":jednotka" => $item["jednotka"],
          ":vaha_kg" => $item["vaha_kg"],
          ":dlzka_cm" => $item["dlzka_cm"],
          ":sirka_cm" => $item["sirka_cm"],
          ":vyska_cm" => $item["vyska_cm"],
          ":stav_polozky" => $item["stav_polozky"],
          ":zlava_percent" => $item["zlava_percent"],
          ":jednotkova_cena_s_dph" => $item["jednotkova_cena_s_dph"],
          ":jednotkova_cena_bez_dph" => $item["jednotkova_cena_bez_dph"],
          ":jednotkova_dph" => $item["jednotkova_dph"],
          ":sadzba_dph" => $item["sadzba_dph"],
          ":celkova_cena_s_dph" => $item["celkova_cena_s_dph"],
          ":celkova_cena_bez_dph" => $item["celkova_cena_bez_dph"],
          ":celkova_dph" => $item["celkova_dph"]
        ]);
      }
    }
  }

  $db->commit();
  $success_prefix = $is_background ? "Automatická aktualizácia dokončená." : "Aktualizácia dokončená.";
  $success_message = "{$success_prefix} Nové: {$pocet_novych}, zmenené: {$pocet_zmenenych}, bez zmeny: {$pocet_bez_zmeny}.";
  update_finish_log($db, $update_log_id, "success", $pocet_novych, $pocet_zmenenych, $pocet_bez_zmeny, $success_message);

  if ($is_background) {
    update_release_auto_lock($db, $auto_lock_acquired);
    update_background_response([
      "success" => true,
      "updated" => true,
      "new_orders" => $pocet_novych,
      "changed_orders" => $pocet_zmenenych,
      "unchanged_orders" => $pocet_bez_zmeny,
      "message" => $success_message
    ]);
  }

  if ($is_cron) {
    echo $success_message . PHP_EOL;
    exit(0);
  }

  $_SESSION["order_update_flash"] = [
    "success" => true,
    "message" => $success_message
  ];
  header("Location: " . ($return_to === "updates" ? "/?page=order-updates" : "/"));
  exit;
} catch (Throwable $e) {
  if ($db->inTransaction()) {
    $db->rollBack();
  }

  $error_message = "Chyba aktualizácie: " . $e->getMessage();

  try {
    update_finish_log($db, $update_log_id, "error", $pocet_novych ?? 0, $pocet_zmenenych ?? 0, $pocet_bez_zmeny ?? 0, $error_message);
  } catch (Throwable $log_error) {
    $error_message .= " Log chyby sa nepodarilo uložiť: " . $log_error->getMessage();
  }

  if ($is_background) {
    update_release_auto_lock($db, $auto_lock_acquired);
    update_background_response([
      "success" => false,
      "updated" => false,
      "message" => $error_message
    ], 500);
  }

  if ($is_cron) {
    update_fail_response($error_message, 500);
  }

  $_SESSION["order_update_flash"] = [
    "success" => false,
    "message" => $error_message
  ];
  header("Location: /?page=order-updates");
  exit;
}
