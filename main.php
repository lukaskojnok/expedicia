<?php
// Možnosti:
// expedicia
// vyskladnenie

$typ_kontroly = "expedicia";

// Statusy, ktoré sa majú zobrazovať.

$zobrazit_statusy = [
  "nove",
  "v_procese",
  "ukoncene"
];

$allowed_types = [
  "expedicia",
  "vyskladnenie"
];

$allowed_statuses = [
  "nove",
  "v_procese",
  "ukoncene"
];

if (!in_array($typ_kontroly, $allowed_types, true)) {
  $typ_kontroly = "expedicia";
}

$zobrazit_statusy = array_values(
  array_intersect($zobrazit_statusy, $allowed_statuses)
);

if (empty($zobrazit_statusy)) {
  $zobrazit_statusy = $allowed_statuses;
}

if ($typ_kontroly === "vyskladnenie") {
  $status_column = "status_vyskladnenie";
  $user_column = "vyskladnenie_user_id";
  $page_title = "Faktúry na vyskladnenie";
  $status_title = "Stav vyskladnenia";
} else {
  $status_column = "status_expedicia";
  $user_column = "expedicia_user_id";
  $page_title = "Faktúry na expedíciu";
  $status_title = "Stav expedície";
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

$prihlaseny_meno = $_SESSION["admin_name"] ?? "Lukáš";
?>

<header class="topbar">
  <div class="topbar_inner">

    <div class="topbar_left">
      <a href="/" class="topbar_logo">Expedícia</a>

      <div class="topbar_page">
        <h1><?= htmlspecialchars($page_title, ENT_QUOTES, "UTF-8") ?></h1>

        <span class="topbar_count">
          <strong><?= $pocet_faktur ?></strong>
          faktúr
        </span>
      </div>
    </div>

    <nav class="topbar_menu">
      <span>Prihlásený: <strong><?= htmlspecialchars($prihlaseny_meno, ENT_QUOTES, "UTF-8") ?></strong></span>
      <a href="/logout.php" class="topbar_logout">Odhlásiť sa</a>
    </nav>

  </div>
</header>

<main class="content-main">

  <?php require __DIR__ . "/includes/invoices.php"; ?>

</main>