<?php
$order_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$typ_kontroly = isset($_GET["typ"]) ? $_GET["typ"] : "expedicia";

$allowed_types = [
  "expedicia",
  "vyskladnenie"
];

if (!in_array($typ_kontroly, $allowed_types, true)) {
  $typ_kontroly = "expedicia";
}

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

$status_column = $typ_kontroly === "vyskladnenie"
  ? "status_vyskladnenie"
  : "status_expedicia";

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

$status = $order[$status_column] ?? "nove";
$status_label = $status_labels[$status] ?? $status;
$status_class = $status_classes[$status] ?? "status-waiting";

$zakaznik_meno = $order["fakturacne_meno"] ?: $order["dodacie_meno"] ?: "—";
$zakaznik_mesto = $order["dodacie_mesto"] ?: $order["fakturacne_mesto"] ?: "—";
$doprava_nazov = $order["doprava_nazov"] ?: "Neuvedená doprava";
$mena = $order["mena"] ?: "EUR";
$suma_objednavky = number_format((float) $order["cena_na_uhradu"], 2, ",", " ");

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

$prihlaseny_meno = $_SESSION["admin_name"] ?? "Lukáš";
?>

<header class="topbar">
  <div class="topbar_inner">

    <div class="topbar_left">
      <a href="/" class="topbar_logo">Expedícia</a>

      <div class="topbar_page">
        <h1><?= htmlspecialchars($page_title, ENT_QUOTES, "UTF-8") ?></h1>

        <span class="topbar_count">
          <strong><?= htmlspecialchars($order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?></strong>
        </span>
      </div>
    </div>

    <nav class="topbar_menu">
      <span>Prihlásený: <strong><?= htmlspecialchars($prihlaseny_meno, ENT_QUOTES, "UTF-8") ?></strong></span>
      <a href="/" class="topbar_logout">Späť na zoznam</a>
    </nav>

  </div>
</header>

<main class="content-main">

  <section class="invoice-summary">

    <div class="invoice-summary_item">
      <span>Objednávka</span>
      <strong><?= htmlspecialchars($order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?></strong>
    </div>

    <div class="invoice-summary_item">
      <span>Faktúra</span>
      <strong><?= htmlspecialchars($order["cislo_faktury"] ?: "—", ENT_QUOTES, "UTF-8") ?></strong>
    </div>

    <div class="invoice-summary_item invoice-summary_customer">
      <span>Zákazník</span>
      <strong><?= htmlspecialchars($zakaznik_meno, ENT_QUOTES, "UTF-8") ?></strong>
      <small><?= htmlspecialchars($zakaznik_mesto, ENT_QUOTES, "UTF-8") ?></small>
    </div>

    <div class="invoice-summary_item">
      <span>Dátum</span>
      <strong><?= htmlspecialchars($datum, ENT_QUOTES, "UTF-8") ?></strong>
    </div>

    <div class="invoice-summary_item">
      <span>Položky</span>
      <strong><?= htmlspecialchars((string) $pocet_poloziek, ENT_QUOTES, "UTF-8") ?></strong>
    </div>

    <div class="invoice-summary_item">
      <span>Suma</span>
      <strong><?= htmlspecialchars($suma_objednavky, ENT_QUOTES, "UTF-8") ?> <?= htmlspecialchars($mena, ENT_QUOTES, "UTF-8") ?></strong>
    </div>

    <div class="invoice-summary_item">
      <span>Stav</span>
      <strong><span class="status <?= htmlspecialchars($status_class, ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($status_label, ENT_QUOTES, "UTF-8") ?></span></strong>
    </div>

  </section>

  <section class="shipping-highlight">
    <div>
      <span>Spôsob doručenia</span>
      <strong><?= htmlspecialchars($doprava_nazov, ENT_QUOTES, "UTF-8") ?></strong>

      <?php if (!empty($order["doprava_kod"])) { ?>
        <small><?= htmlspecialchars($order["doprava_kod"], ENT_QUOTES, "UTF-8") ?></small>
      <?php } ?>
    </div>

    <?php if (!empty($order["ulozne_miesto"])) { ?>
      <div class="shipping-location">
        <span>Úložné miesto</span>
        <strong><?= htmlspecialchars($order["ulozne_miesto"], ENT_QUOTES, "UTF-8") ?></strong>
      </div>
    <?php } ?>
  </section>

  <div class="table-box">

    <table class="data-table invoice-items-table">
      <thead>
        <tr>
          <th>Kód</th>
          <th>EAN</th>
          <th>Produkt</th>
          <th>Variant</th>
          <th>Množstvo</th>
          <th>Jednotková cena</th>
          <th>Spolu</th>
        </tr>
      </thead>

      <tbody>

        <?php if (!empty($items)) { ?>

          <?php foreach ($items as $item) { ?>
            <?php
            $mnozstvo = (float) $item["mnozstvo"];

            if (floor($mnozstvo) === $mnozstvo) {
              $mnozstvo = (int) $mnozstvo;
            }

            $jednotkova_cena = number_format((float) $item["jednotkova_cena_s_dph"], 2, ",", " ");
            $celkova_cena = number_format((float) $item["celkova_cena_s_dph"], 2, ",", " ");
            ?>

            <tr>
              <td><strong><?= htmlspecialchars($item["kod"] ?: "—", ENT_QUOTES, "UTF-8") ?></strong></td>
              <td><?= htmlspecialchars($item["ean"] ?: "—", ENT_QUOTES, "UTF-8") ?></td>
              <td class="invoice-item_name"><strong><?= htmlspecialchars($item["nazov"], ENT_QUOTES, "UTF-8") ?></strong></td>
              <td><?= htmlspecialchars($item["variant_nazov"] ?: "—", ENT_QUOTES, "UTF-8") ?></td>
              <td class="invoice-item_amount"><?= htmlspecialchars((string) $mnozstvo, ENT_QUOTES, "UTF-8") ?> <?= htmlspecialchars($item["jednotka"] ?: "ks", ENT_QUOTES, "UTF-8") ?></td>
              <td class="data-table_price"><?= htmlspecialchars($jednotkova_cena, ENT_QUOTES, "UTF-8") ?> <?= htmlspecialchars($mena, ENT_QUOTES, "UTF-8") ?></td>
              <td class="data-table_price"><?= htmlspecialchars($celkova_cena, ENT_QUOTES, "UTF-8") ?> <?= htmlspecialchars($mena, ENT_QUOTES, "UTF-8") ?></td>
            </tr>

          <?php } ?>

        <?php } else { ?>

          <tr>
            <td colspan="7" class="data-table_empty">Objednávka neobsahuje žiadne produktové položky.</td>
          </tr>

        <?php } ?>

      </tbody>
    </table>

  </div>

</main>