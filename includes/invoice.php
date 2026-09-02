<?php
$shoptet_private_api_token = SHOPTET_API;

function getShoptetProductData($code, $token) {
  $url = "https://api.myshoptet.com/api/products/code/" . rawurlencode($code) . "?include=images,perStockAmounts";

  $ch = curl_init($url);

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      "Shoptet-Private-API-Token: " . $token
    ],
    CURLOPT_TIMEOUT => 10
  ]);

  $response = json_decode(curl_exec($ch), true);

  $image = $response["data"]["images"][0]["seoName"] ?? "";

  $image_url = "";

  if ($image !== "") {
    $image_url = "https://cdn.myshoptet.com/usr/www.okfish.sk/user/shop/detail/" . $image;
  }

  $stock_data = null;

  foreach ($response["data"]["variants"] ?? [] as $variant) {
    if ((string) ($variant["code"] ?? "") !== (string) $code) {
      continue;
    }

    foreach ($variant["perStockAmounts"] ?? [] as $stock) {
      if ((int) ($stock["stockId"] ?? 0) === 1) {
        $stock_data = $stock;
        break 2;
      }
    }
  }

  return [
    "image" => $image_url,
    "stock_location" => $stock_data["location"] ?? "",
    "stock_amount" => $stock_data["amount"] ?? 0,
    "stock_claim" => $stock_data["claim"] ?? 0
  ];
}

$stock_positions_query = $db->prepare("SELECT pozicie FROM pozicie_sklad WHERE sklad = :sklad ORDER BY id ASC LIMIT 1");
$stock_positions_query->execute([":sklad" => 1]);
$stock_positions_text = (string) ($stock_positions_query->fetchColumn() ?: "");
$stock_position_order = [];

foreach (preg_split('/\R/u', $stock_positions_text) as $stock_position_index => $stock_position) {
  $stock_position_key = strtoupper(trim($stock_position));

  if ($stock_position_key !== "" && !array_key_exists($stock_position_key, $stock_position_order)) {
    $stock_position_order[$stock_position_key] = $stock_position_index;
  }
}

$product_api_cache = [];

foreach ($items as $item_index => &$item) {
  $product_code = trim((string) ($item["kod"] ?? ""));

  if (!array_key_exists($product_code, $product_api_cache)) {
    $product_api_cache[$product_code] = $product_code === ""
      ? ["image" => "", "stock_location" => "", "stock_amount" => 0, "stock_claim" => 0]
      : getShoptetProductData($product_code, $shoptet_private_api_token);
  }

  $item["_shoptet_data"] = $product_api_cache[$product_code];
  $stock_location_key = strtoupper(trim((string) ($item["_shoptet_data"]["stock_location"] ?? "")));
  $item["_stock_position_order"] = array_key_exists($stock_location_key, $stock_position_order)
    ? $stock_position_order[$stock_location_key]
    : PHP_INT_MAX;
  $item["_original_order"] = $item_index;
}
unset($item);

usort($items, function ($item_a, $item_b) {
  $position_comparison = $item_a["_stock_position_order"] <=> $item_b["_stock_position_order"];

  if ($position_comparison !== 0) {
    return $position_comparison;
  }

  return $item_a["_original_order"] <=> $item_b["_original_order"];
});
?>


<section class="invoice-summary">

  <div class="invoice-summary_item invoice-summary_cisloobjednavky">
    <span>Objednávka</span>
    <strong nofocus><?= htmlspecialchars($order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?></strong>
  </div>

  <!-- <div class="invoice-summary_item">
    <span>Faktúra</span>
    <strong><?= htmlspecialchars($order["cislo_faktury"] ?: "—", ENT_QUOTES, "UTF-8") ?></strong>
  </div> -->

  <div class="invoice-summary_item invoice-summary_customer">
    <span>Zákazník</span>
    <strong><?= htmlspecialchars($zakaznik_meno, ENT_QUOTES, "UTF-8") ?></strong>
    <small><?= htmlspecialchars($zakaznik_mesto, ENT_QUOTES, "UTF-8") ?></small>
  </div>

  <div class="invoice-summary_item invoice-summary_datum">
    <span>Dátum</span>
    <strong><?= htmlspecialchars($datum, ENT_QUOTES, "UTF-8") ?></strong>
  </div>

  <div class="invoice-summary_item invoice-summary_pocetpoloziek">
    <span>Položky</span>
    <strong><?= htmlspecialchars((string) $pocet_poloziek, ENT_QUOTES, "UTF-8") ?></strong>
  </div>

  <div class="invoice-summary_item invoice-summary_suma">
    <span>Suma</span>
    <strong><?= htmlspecialchars($suma_objednavky, ENT_QUOTES, "UTF-8") ?> <?= htmlspecialchars($mena, ENT_QUOTES, "UTF-8") ?></strong>

    <?php if ($je_dobierka) { ?>
      <small class="payment-info payment-info_cod">Platba: Dobierka</small>
    <?php } elseif ($je_neuhradene_bez_dobierky) { ?>
      <small class="payment-info payment-info_warning">Pozor: objednávka nie je uhradená</small>
    <?php } elseif (!empty($order["platba_nazov"])) { ?>
      <small class="payment-info">Platba: <?= htmlspecialchars($order["platba_nazov"], ENT_QUOTES, "UTF-8") ?></small>
    <?php } ?>
  </div>

  <div class="invoice-summary_item invoice-summary_vyskladnenie">
    <span>Vyskladnenie</span>
    <strong>
      <button
        type="button"
        class="status order-logs-open <?= htmlspecialchars($status_classes[$order["status_vyskladnenie"]] ?? "status-waiting", ENT_QUOTES, "UTF-8") ?>"
        data-order-id="<?= (int) $order["id"] ?>"
        data-order-number="<?= htmlspecialchars((string) $order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?>"
        title="Zobraziť históriu objednávky"
        nofocus
      >
        <?= htmlspecialchars($status_labels[$order["status_vyskladnenie"]] ?? $order["status_vyskladnenie"], ENT_QUOTES, "UTF-8") ?>
      </button>
    </strong>
  </div>

  <div class="invoice-summary_item invoice-summary_expedicia">
    <span>Expedícia</span>
    <strong>
      <button
        type="button"
        class="status order-logs-open <?= htmlspecialchars($status_classes[$order["status_expedicia"]] ?? "status-waiting", ENT_QUOTES, "UTF-8") ?>"
        data-order-id="<?= (int) $order["id"] ?>"
        data-order-number="<?= htmlspecialchars((string) $order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?>"
        title="Zobraziť históriu objednávky"
        nofocus
      >
        <?= htmlspecialchars($status_labels[$order["status_expedicia"]] ?? $order["status_expedicia"], ENT_QUOTES, "UTF-8") ?>
      </button>
    </strong>
  </div>

  <div class="invoice-summary_item invoice-summary_shipping <?= htmlspecialchars($shipping_class, ENT_QUOTES, "UTF-8") ?>">
    <span>Spôsob doručenia</span>
    <strong><?= htmlspecialchars($doprava_nazov, ENT_QUOTES, "UTF-8") ?></strong>

    <?php if (!empty($order["ulozne_miesto"])) { ?>
      <small><?= htmlspecialchars($order["ulozne_miesto"], ENT_QUOTES, "UTF-8") ?></small>
    <?php } elseif (!empty($order["doprava_kod"])) { ?>
      <small><?= htmlspecialchars($order["doprava_kod"], ENT_QUOTES, "UTF-8") ?></small>
    <?php } ?>
  </div>

  <div class="invoice-summary_item invoice-summary_actions" nofocus>
    <button
      type="button"
      class="invoice-actions_toggle"
      aria-expanded="false"
      aria-controls="invoice-actions-menu"
      aria-label="Otvoriť akcie objednávky"
    >⋮</button>
    <div class="invoice-actions_menu" id="invoice-actions-menu" hidden>
      <a href="/invoice?id=<?= (int) $order["id"] ?>&amp;typ=vyskladnenie&amp;quick=vyskladnenie">Rýchle vyskladnenie</a>
      <a href="/invoice?id=<?= (int) $order["id"] ?>&amp;typ=expedicia&amp;quick=expedicia">Rýchla expedícia</a>
      <button type="button" data-order-complete-without-expedition data-order-id="<?= (int) $order["id"] ?>" data-csrf-token="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">Vybaviť bez expedovania</button>
      <button
        type="button"
        class="invoice-action_delete"
        data-order-delete
        data-order-id="<?= (int) $order["id"] ?>"
        data-csrf-token="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>"
      >Vymazať objednávku</button>
    </div>
  </div>

</section>

<?php if ($typ_kontroly === "vyskladnenie") { ?>
  <div class="box-scan-status" id="box-scan-status" hidden>
    <div>
      <strong id="box-scan-status-code"></strong>
    </div>
    <button type="button" id="box-scan-status-remove" nofocus>Zrušiť box</button>
  </div>
<?php } ?>

<div class="table-box" id="invoice-items-box">

  <table class="data-table invoice-items-table">

    <tbody>

      <?php if (!empty($items)) { ?>

        <?php foreach ($items as $item) { ?>
          <?php
          $mnozstvo = (float) $item["mnozstvo"];

          if (floor($mnozstvo) === $mnozstvo) {
            $mnozstvo = (int) $mnozstvo;
          }

          $produkt_obrazok = "";
          $produkt_kod = trim((string) $item["kod"]);

          $produkt_shoptet_api_data = $item["_shoptet_data"] ?? [];
          $produkt_obrazok = $produkt_shoptet_api_data["image"] ?? "";
          $produkt_poloha = $produkt_shoptet_api_data["stock_location"] ?? "";
          ?>

          <tr row_item_id="<?= htmlspecialchars($item["kod"], ENT_QUOTES, "UTF-8"); ?>">
            <td class="invoice-item_image">
              <?php if ($produkt_obrazok !== "") { ?>
                <img
                  src="<?= htmlspecialchars($produkt_obrazok, ENT_QUOTES, "UTF-8"); ?>"
                  alt="<?= htmlspecialchars($item["nazov"], ENT_QUOTES, "UTF-8"); ?>"
                >
              <?php } ?>
            </td>

            <td
              class="invoice-item_code"
              item_id="<?= htmlspecialchars($item["kod"], ENT_QUOTES, "UTF-8"); ?>"
            >
              <strong><?= htmlspecialchars($item["kod"], ENT_QUOTES, "UTF-8"); ?></strong>
            </td>

            <td class="invoice-item_name">
              <strong><?= htmlspecialchars($item["nazov"], ENT_QUOTES, "UTF-8"); ?></strong>
              <?= !empty($item["variant_nazov"]) ? "<br>" . htmlspecialchars($item["variant_nazov"], ENT_QUOTES, "UTF-8") : ""; ?>
              <?php if ($produkt_poloha !== "") { ?>
                <small class="invoice-item_location">
                  <strong><?= htmlspecialchars($produkt_poloha, ENT_QUOTES, "UTF-8"); ?></strong>
                </small>
              <?php } ?>
            </td>

            <td class="invoice-item_amount">
              <?= (int) $item["mnozstvo"]; ?>
            </td>

            <td
              class="invoice-item_amount_control"
              item_id="<?= htmlspecialchars($item["kod"], ENT_QUOTES, "UTF-8"); ?>"
              item_amount="<?= (int) $item["mnozstvo"]; ?>"
            >
              <?= (int) $item["mnozstvo"]; ?>
            </td>
          </tr>

        <?php } ?>

      <?php } else { ?>

        <tr>
          <td colspan="5" class="data-table_empty">Objednávka neobsahuje žiadne produktové položky.</td>
        </tr>

      <?php } ?>

    </tbody>
  </table>

</div>

<div class="invoice-control-success" id="invoice-control-success" hidden>
  <?php if ($typ_kontroly === "vyskladnenie") { ?>
    <div class="box-assignment" id="box-assignment" data-order-id="<?= (int) $order["id"] ?>">
      <strong>Úspešne vyskladnené</strong>
      <span>Naskenujte expedičný box, do ktorého ste objednávku uložili.</span>
      <form class="box-assignment_form" id="box-assignment-form" nofocus>
        <input type="hidden" id="box-assignment-csrf" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
        <input type="text" id="box-assignment-code" class="box-assignment_input" placeholder="Kód expedičného boxu" autocomplete="off">
        <button type="submit" class="button">Uložiť do boxu</button>
      </form>
      <div class="box-assignment_message" id="box-assignment-message" aria-live="polite"></div>
      <a href="/?typ=vyskladnenie" class="shipment-back-button box-assignment_back">Späť na objednávky bez boxu</a>
    </div>
    <div class="shipment-completed" id="box-assignment-completed" hidden>
      <strong>Objednávka je pripravená na expedíciu</strong>
      <a href="/?typ=vyskladnenie" class="shipment-back-button">Späť na objednávky</a>
    </div>
  <?php } elseif (($shipping_data["api"] ?? "") === "osobne") { ?>
    <div class="shipment-completed">
      <strong>Kontrola expedície bola úspešne ukončená</strong>
      <a href="/?typ=expedicia" class="shipment-back-button">Späť na objednávky</a>
    </div>
  <?php } elseif (!empty($shipping_data["api"])) { ?>
    <form class="shipment-form" id="shipment-form" data-order-id="<?= (int) $order["id"] ?>" data-control-type="<?= htmlspecialchars($typ_kontroly, ENT_QUOTES, "UTF-8") ?>" nofocus>
      <div class="shipment-layout">
        <div class="shipment-panel shipment-panel_weights">
          <?php if ($je_dobierka) { ?>
            <div class="shipment-cod">
              <label for="shipment-cod-amount">Suma dobierky <strong>Povinný údaj</strong></label>
              <div class="shipment-cod_value">
                <input
                  type="number"
                  id="shipment-cod-amount"
                  min="0.01"
                  step="0.01"
                  value="<?= htmlspecialchars(number_format((float) $order["cena_na_uhradu"], 2, ".", ""), ENT_QUOTES, "UTF-8") ?>"
                  required
                >
                <span><?= htmlspecialchars($mena, ENT_QUOTES, "UTF-8") ?></span>
              </div>
            </div>
          <?php } ?>
          <div class="shipment-form_header">
            <strong>Hmotnosť balíkov</strong>
            <span>Hmotnosť zadávaj v kilogramoch.</span>
          </div>
          <div class="shipment-parcels" id="shipment-parcels">
            <div class="shipment-parcel">
              <span class="shipment-parcel_label">Balík 1</span>
              <input class="shipment-weight" type="text" inputmode="decimal" autocomplete="off" value="">
              <span class="shipment-parcel_unit">kg</span>
              <button type="button" class="shipment-parcel_remove" aria-label="Odstrániť balík">×</button>
            </div>
            <div class="shipment-parcel">
              <span class="shipment-parcel_label">Balík 2</span>
              <input class="shipment-weight" type="text" inputmode="decimal" autocomplete="off" value="">
              <span class="shipment-parcel_unit">kg</span>
              <button type="button" class="shipment-parcel_remove" aria-label="Odstrániť balík">×</button>
            </div>
            <div class="shipment-parcel">
              <span class="shipment-parcel_label">Balík 3</span>
              <input class="shipment-weight" type="text" inputmode="decimal" autocomplete="off" value="">
              <span class="shipment-parcel_unit">kg</span>
              <button type="button" class="shipment-parcel_remove" aria-label="Odstrániť balík">×</button>
            </div>
          </div>
          <button type="button" class="shipment-add-parcel" id="shipment-add-parcel">+ Ďalší balík</button>
          <div class="shipment-submit_actions">
            <button type="button" class="shipment-submit shipment-submit_without-print" data-print-label="0" disabled>Poslať bez vytlačenia</button>
            <button type="button" class="shipment-submit shipment-submit_with-print" data-print-label="1" disabled>Poslať a vytlačiť štítok</button>
          </div>
        </div>
        <div class="shipment-panel shipment-panel_keypad">
          <div class="shipment-keypad" id="shipment-keypad" aria-label="Numerická klávesnica">
            <button type="button" data-key="1">1</button><button type="button" data-key="2">2</button><button type="button" data-key="3">3</button>
            <button type="button" data-key="4">4</button><button type="button" data-key="5">5</button><button type="button" data-key="6">6</button>
            <button type="button" data-key="7">7</button><button type="button" data-key="8">8</button><button type="button" data-key="9">9</button>
            <button type="button" data-key=",">,</button><button type="button" data-key="0">0</button><button type="button" class="shipment-keypad_delete" data-key="delete">⌫</button>
          </div>
        </div>
      </div>
      <div class="shipment-message" id="shipment-message" aria-live="polite"></div>
      <pre class="shipment-error-json" id="shipment-error-json" hidden></pre>
    </form>
    <div class="shipment-completed" id="shipment-completed" hidden>
      <strong>Zásielka bola odoslaná dopravcovi</strong>
      <div class="shipment-completed_warning" id="shipment-completed-warning" hidden></div>
      <div class="shipment-completed_labels" id="shipment-completed-labels"></div>
      <a href="/?typ=<?= urlencode($typ_kontroly) ?>" class="shipment-back-button">Späť na objednávky</a>
    </div>
  <?php } else { ?>
    <div class="shipment-not-available">Pre tento druh dopravy nie je zatiaľ nastavené odosielanie dopravcovi.</div>
  <?php } ?>
</div>
