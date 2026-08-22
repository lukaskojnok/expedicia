<?php
if (isset($_GET["logout"]) AND $_GET["logout"] == 1) {
  $query = $GLOBALS["db"]->prepare("DELETE FROM admins_logs WHERE unique_code=:unique_code")->execute(["unique_code" => $_COOKIE["loginADMIN_unique_code"]]);

  setcookie("loginADMIN", "", time(), "/");
  setcookie("loginADMIN_unique_code", "", time(), "/");
  header("Location: login.php");
  exit;
  $is_page_login = true;
}

if (!isset($_COOKIE["loginADMIN"]) or !isset($_COOKIE["loginADMIN_unique_code"])) {
  header("Location: login.php");
  exit;
  $is_page_login = true;
} else {
  setcookie("loginADMIN", "$_COOKIE[loginADMIN]", time() + 60 * 60 * 3, "/");
}

//////////////// LOGIN admin
function unique_code_admin_login( $login, $ip, $user_agent, $session_id ) {
  //return hash('sha512', $login . $ip . $user_agent . $session_id );
  return hash('sha512', $login . $ip . $user_agent);
}

if ( !empty($_COOKIE["loginADMIN"]) ) {
  $login = htmlspecialchars($_COOKIE["loginADMIN"]);
  $unique_code = htmlspecialchars($_COOKIE["loginADMIN_unique_code"]);

  $query = $GLOBALS["db"]->prepare( "SELECT * FROM admins_logs WHERE unique_code=:unique_code" );
  $query->execute( ["unique_code" => $unique_code] );
  $uq_control = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];

  $uq_control_code = unique_code_admin_login( $uq_control["login"], $uq_control["ip"], $uq_control["user_agent"], $uq_control["session_id"] );

  if ( $uq_control["unique_code"] == $uq_control_code ) {
    $GLOBALS["db"]->prepare( "UPDATE admins_logs SET date_last_do=NOW() WHERE unique_code=:unique_code" )->execute( ["unique_code" => $uq_control_code] );

    $query = $GLOBALS["db"]->prepare( "SELECT id, login, email, name, permissions FROM admins WHERE login=:login" );
    $query->execute( ["login" => $login] );
    $ADMIN_DATA = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];
  } else {
    $query = $GLOBALS["db"]->prepare( "DELETE FROM admins_logs WHERE unique_code=:unique_code" )->execute( [ "unique_code" => $_COOKIE["loginADMIN_unique_code"] ] );

    setcookie("loginADMIN", "", time(), "/");
    setcookie("loginADMIN_unique_code", "", time(), "/");
    header("Location:?");
    exit;
  }
}
//////////////// LOGIN admin

$login = (string) trim("lukaskojnok");
$heslo = (string) trim("Heslo10");

// echo hash('sha512', $login.$heslo.HAS_ADMIN);

$query = $GLOBALS["db"]->prepare( "SELECT * FROM admins WHERE login=:login AND active='1'" );
$query->execute( ["login" => $login] );
$admin = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];

$hesloP = "";
if ($admin["login"]) {
  $hesloP = hash('sha512', $admin["login"].$heslo.HAS_ADMIN);
}

if ($login == $admin["login"] AND $hesloP == $admin["password"] AND $login AND $heslo) {
  $ADMIN_DATA = $admin;

  setcookie ("loginADMIN", "$login", time() + 60*60*24, "/");

  $unique_code = unique_code_admin_login( $admin["login"], $_SERVER["REMOTE_ADDR"], $_SERVER["HTTP_USER_AGENT"], session_id() );
  setcookie ("loginADMIN_unique_code", "$unique_code", time() + 60*60*24, "/");

  $GLOBALS["db"]->prepare( "UPDATE admins SET date_login_last=NOW() WHERE login=:login" )->execute( ["login" => $login] );

  $GLOBALS["db"]->prepare( "DELETE FROM admins_logs WHERE unique_code=:unique_code" )->execute( ["unique_code" => $unique_code] );

  $query = $GLOBALS["db"]->prepare( "INSERT INTO admins_logs SET login=:login, session_id=:session_id, user_agent=:user_agent, ip=:ip, unique_code=:unique_code, date_login=NOW(), date_last_do=NOW()" );
  $query->execute([
    "login" => $login,
    "session_id" => session_id(),
    "user_agent" => $_SERVER["HTTP_USER_AGENT"],
    "ip" => $_SERVER["REMOTE_ADDR"],
    "unique_code" => $unique_code
  ]);
}  


///////////////////////////////////////////////////////////////////////////

require_once __DIR__ . "/config/controls_log.php";

$meta["title_primary"] = "EXPEDÍCIA";
$meta["h1"] = "";
$meta["title"] = "";
$meta["description"] = "";
$meta["image"] = "";
$meta["follow"] = true;
$meta["languages"] = [];

$allowed_types = [
  "expedicia",
  "vyskladnenie"
];

$allowed_statuses = [
  "nove",
  "v_procese",
  "ukoncene"
];

$status_labels = [
  "nove" => "Nové",
  "v_procese" => "V procese",
  "ukoncene" => "Ukončené"
];

$status_classes = [
  "nove" => "status-waiting",
  "v_procese" => "status-active",
  "ukoncene" => "status-done"
];

function order_is_cod($order) {
  $payment_code = strtoupper(trim((string) ($order["platba_kod"] ?? "")));
  $payment_name = strtolower(trim((string) ($order["platba_nazov"] ?? "")));

  return $payment_code === "BILLING3" || strpos($payment_name, "dobier") !== false;
}

$typ_kontroly = isset($_GET["typ"]) ? $_GET["typ"] : "vyskladnenie";

if (!in_array($typ_kontroly, $allowed_types, true)) {
  $typ_kontroly = "expedicia";
}

if ($typ_kontroly === "vyskladnenie") {
  $status_column = "status_vyskladnenie";
  $user_column = "vyskladnenie_user_id";
  $status_title = "Stav vyskladnenia";
} else {
  $status_column = "status_expedicia";
  $user_column = "expedicia_user_id";
  $status_title = "Stav expedície";
}

$prihlaseny_meno = $_SESSION["admin_name"] ?? "Lukáš";
$topbar_count_value = "";
$topbar_count_label = "";
$topbar_back_url = "";

if ($page === "invoice") {
  $order_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

  if ($order_id <= 0) {
    http_response_code(404);
    exit("Objednávka nebola nájdená.");
  }

  $query = $db->prepare("
    SELECT *
    FROM orders
    WHERE id = :id
    LIMIT 1
  ");

  $query->execute([
    ":id" => $order_id
  ]);

  $order = $query->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    http_response_code(404);
    exit("Objednávka nebola nájdená.");
  }

  $admin_data = controls_get_admin_data($db);
  $control_user_id = (int) ($admin_data["id"] ?? 0);

  if ($control_user_id > 0) {
    $query = $db->prepare("
      UPDATE orders
      SET
        {$status_column} = CASE
          WHEN {$status_column} = 'nove' THEN 'v_procese'
          ELSE {$status_column}
        END,
        {$user_column} = :user_id
      WHERE id = :id
    ");
    $query->execute([":user_id" => $control_user_id, ":id" => $order_id]);

    controls_add_log($db, $order_id, $control_user_id, $typ_kontroly, "invoice_opened", "opened", [
      "message" => "Otvorený detail objednávky."
    ]);

    $order[$status_column] = $order[$status_column] === "nove" ? "v_procese" : $order[$status_column];
    $order[$user_column] = $control_user_id;
  }

  $query = $db->prepare("
    SELECT *
    FROM orders_items
    WHERE order_id = :order_id
      AND type = 'product'
    ORDER BY id ASC
  ");

  $query->execute([
    ":order_id" => $order_id
  ]);

  $items = $query->fetchAll(PDO::FETCH_ASSOC);
  $pocet_poloziek = 0;

  foreach ($items as $item) {
    $pocet_poloziek += (float) $item["mnozstvo"];
  }

  if (floor($pocet_poloziek) === $pocet_poloziek) {
    $pocet_poloziek = (int) $pocet_poloziek;
  }

  $status = $order[$status_column] ?? "nove";
  $status_label = $status_labels[$status] ?? $status;
  $status_class = $status_classes[$status] ?? "status-waiting";

  $zakaznik_meno = $order["fakturacne_meno"] ?: $order["dodacie_meno"] ?: "—";
  $zakaznik_mesto = $order["dodacie_mesto"] ?: $order["fakturacne_mesto"] ?: "—";
  $shipping_data = DOPRAVA_KODY[$order["doprava_kod"]] ?? null;
  $shipping_class = $shipping_data["class"] ?? "shipping-unknown";
  $doprava_nazov = ($shipping_data["name"] ?? "") ?: ($order["doprava_nazov"] ?: "Neuvedená doprava");
  $mena = $order["mena"] ?: "EUR";
  $suma_objednavky = number_format((float) $order["cena_na_uhradu"], 2, ",", " ");
  $je_dobierka = order_is_cod($order);
  $je_neuhradene_bez_dobierky = !$je_dobierka && empty($order["uhradene"]);
  $datum = "—";

  if (!empty($order["datum_objednavky"])) {
    $datum_timestamp = strtotime($order["datum_objednavky"]);

    if ($datum_timestamp !== false) {
      $datum = date("d. m. Y, H:i", $datum_timestamp);
    }
  }

  $page_title = $typ_kontroly === "vyskladnenie"
    ? "Vyskladnenie objednávky"
    : "Expedícia objednávky";

  $topbar_count_value = $order["cislo_objednavky"];
  $topbar_count_label = "objednávka";
  $topbar_back_url = "/?typ=" . urlencode($typ_kontroly);
  
} else {
  $zobrazit_statusy = [
    "nove",
    "v_procese",
    "ukoncene"
  ];

  $zobrazit_statusy = array_values(
    array_intersect($zobrazit_statusy, $allowed_statuses)
  );

  if (empty($zobrazit_statusy)) {
    $zobrazit_statusy = $allowed_statuses;
  }

  $status_placeholders = [];
  $status_parameters = [];

  foreach ($zobrazit_statusy as $status_key => $status_value) {
    $placeholder = ":status_{$status_key}";

    $status_placeholders[] = $placeholder;
    $status_parameters[$placeholder] = $status_value;
  }

  $status_placeholders_sql = implode(", ", $status_placeholders);

  $query = $db->prepare("
    SELECT
      orders.*,
      COALESCE(items_count.pocet_poloziek, 0) AS pocet_poloziek
    FROM orders
    LEFT JOIN (
      SELECT
        order_id,
        SUM(
          CASE
            WHEN type = 'product' THEN mnozstvo
            ELSE 0
          END
        ) AS pocet_poloziek
      FROM orders_items
      GROUP BY order_id
    ) AS items_count
      ON items_count.order_id = orders.id
    WHERE orders.{$status_column} IN ({$status_placeholders_sql})
    ORDER BY
      orders.zmena DESC,
      CASE orders.{$status_column}
        WHEN 'nove' THEN 1
        WHEN 'v_procese' THEN 2
        WHEN 'ukoncene' THEN 3
        ELSE 4
      END,
      orders.datum_objednavky DESC,
      orders.id DESC
  ");

  $query->execute($status_parameters);

  $results = $query->fetchAll(PDO::FETCH_ASSOC);
  $pocet_faktur = count($results);

  $page_title = $typ_kontroly === "vyskladnenie"
    ? "Faktúry na vyskladnenie"
    : "Faktúry na expedíciu";

  $topbar_count_value = $pocet_faktur;
  $topbar_count_label = "faktúr";
}

$meta["title"] = $page_title;
?>
