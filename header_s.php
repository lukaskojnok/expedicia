<?php
$is_page_login = false;

if (!auth_is_logged_in()) {
  header("Location: /login.php");
  exit;
}

$ADMIN_DATA = auth_admin_by_id($db, $_SESSION["admin_id"]);

if (!$ADMIN_DATA || $ADMIN_DATA["login"] !== $_SESSION["admin_login"]) {
  auth_logout($db);
  header("Location: /login.php");
  exit;
}

$unique_code = (string) ($_SESSION["admin_unique_code"] ?? "");
if ($unique_code !== "") {
  $db->prepare("UPDATE admins_logs SET date_last_do = NOW() WHERE unique_code = :unique_code")->execute([":unique_code" => $unique_code]);
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
    controls_add_log($db, $order_id, $control_user_id, $typ_kontroly, "invoice_opened", "opened", [
      "message" => "Otvorený detail objednávky."
    ]);
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
  $query = $db->prepare("
    SELECT id, kod, order_id
    FROM expedicne_boxy
    WHERE order_id IS NULL OR order_id = :order_id
    ORDER BY kod ASC
  ");
  $query->execute([":order_id" => $order_id]);
  $available_expedicne_boxy = $query->fetchAll(PDO::FETCH_ASSOC);
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

} elseif ($page === "expedicne-boxy") {
  $query = $db->query("
    SELECT
      expedicne_boxy.id,
      expedicne_boxy.kod,
      expedicne_boxy.order_id,
      expedicne_boxy.obsadeny_at,
      orders.cislo_objednavky,
      orders.cislo_faktury,
      orders.dodacie_meno,
      orders.fakturacne_meno
    FROM expedicne_boxy
    LEFT JOIN orders ON orders.id = expedicne_boxy.order_id
    ORDER BY expedicne_boxy.kod ASC
  ");
  $expedicne_boxy = $query->fetchAll(PDO::FETCH_ASSOC);

  $page_title = "Expedičné boxy";
  $topbar_count_value = count($expedicne_boxy);
  $topbar_count_label = "boxov";
  $topbar_back_url = "/?typ=" . urlencode($typ_kontroly);

} elseif ($page === "order-updates") {
  $today = new DateTimeImmutable("today");
  $order_update_presets = [
    "today" => [
      "label" => "Od dnes",
      "date" => $today->format("d. m. Y")
    ],
    "yesterday" => [
      "label" => "Od včera",
      "date" => $today->modify("-1 day")->format("d. m. Y")
    ],
    "last_7_days" => [
      "label" => "Posledných 7 dní",
      "date" => $today->modify("-6 days")->format("d. m. Y")
    ]
  ];

  $query = $db->query("
    SELECT
      order_update_logs.*,
      admins.name AS admin_name
    FROM order_update_logs
    LEFT JOIN admins ON admins.id = order_update_logs.admin_id
    ORDER BY order_update_logs.started_at DESC, order_update_logs.id DESC
    LIMIT 200
  ");
  $order_update_logs = $query->fetchAll(PDO::FETCH_ASSOC);

  $page_title = "Aktualizácie objednávok";
  $topbar_count_value = count($order_update_logs);
  $topbar_count_label = "záznamov";
  $topbar_back_url = "/?typ=" . urlencode($typ_kontroly);

} elseif ($page === "pozicie-sklad") {
  $pozicie_sklad_error = "";
  $pozicie_sklad_saved = isset($_GET["saved"]) && $_GET["saved"] === "1";

  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!auth_csrf_is_valid($csrf_token)) {
      $pozicie_sklad_error = "Platnosť formulára vypršala. Obnovte stránku a skúste to znova.";
    } else {
      $pozicie_input = str_replace(["\r\n", "\r"], "\n", (string) ($_POST["pozicie"] ?? ""));
      $pozicie_riadky = preg_split('/\n/', $pozicie_input);
      $pozicie_riadky = array_values(array_filter(array_map("trim", $pozicie_riadky), function ($pozicia) {
        return $pozicia !== "";
      }));
      $pozicie_text = implode("\r\n", $pozicie_riadky);

      $query = $db->prepare("SELECT id FROM pozicie_sklad WHERE sklad = :sklad ORDER BY id ASC LIMIT 1");
      $query->execute([":sklad" => 1]);
      $pozicie_sklad_id = (int) $query->fetchColumn();

      if ($pozicie_sklad_id > 0) {
        $query = $db->prepare("UPDATE pozicie_sklad SET pozicie = :pozicie WHERE id = :id");
        $query->execute([
          ":pozicie" => $pozicie_text,
          ":id" => $pozicie_sklad_id
        ]);
      } else {
        $query = $db->prepare("INSERT INTO pozicie_sklad (sklad, pozicie) VALUES (:sklad, :pozicie)");
        $query->execute([
          ":sklad" => 1,
          ":pozicie" => $pozicie_text
        ]);
      }

      header("Location: /?page=pozicie-sklad&typ=" . urlencode($typ_kontroly) . "&saved=1");
      exit;
    }
  }

  $query = $db->prepare("SELECT pozicie FROM pozicie_sklad WHERE sklad = :sklad ORDER BY id ASC LIMIT 1");
  $query->execute([":sklad" => 1]);
  $pozicie_sklad_text = (string) ($query->fetchColumn() ?: "");
  $pozicie_sklad_text = str_replace(["\r\n", "\r"], "\n", $pozicie_sklad_text);
  $pozicie_sklad_count = count(array_filter(array_map("trim", preg_split('/\n/', $pozicie_sklad_text)), function ($pozicia) {
    return $pozicia !== "";
  }));

  $page_title = "Pozície skladu";
  $topbar_count_value = $pozicie_sklad_count;
  $topbar_count_label = "pozícií";
  $topbar_back_url = "/?typ=" . urlencode($typ_kontroly);

} else {
  if ($typ_kontroly === "vyskladnenie") {
    $orders_where_sql = "orders.status_vyskladnenie IN ('nove', 'v_procese')";
    $orders_status_order_sql = "
      CASE orders.status_vyskladnenie
        WHEN 'nove' THEN 1
        WHEN 'v_procese' THEN 2
        ELSE 3
      END
    ";
  } else {
    $orders_where_sql = "orders.status_vyskladnenie = 'ukoncene'";
    $orders_status_order_sql = "
      CASE orders.status_expedicia
        WHEN 'nove' THEN 1
        WHEN 'v_procese' THEN 2
        WHEN 'ukoncene' THEN 3
        ELSE 4
      END
    ";
  }

  $query = $db->query("
    SELECT
      orders.*,
      working_admin.name AS working_user_name,
      COALESCE(items_count.pocet_poloziek, 0) AS pocet_poloziek
    FROM orders
    LEFT JOIN admins AS working_admin
      ON working_admin.id = orders.{$user_column}
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
    WHERE {$orders_where_sql}
    ORDER BY
      {$orders_status_order_sql},
      orders.zmena DESC,
      orders.datum_objednavky ASC,
      orders.id ASC
  ");

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