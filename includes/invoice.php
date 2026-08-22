<?php
$shoptet_private_api_token = "";

function getShoptetProductImageUrl($code, $token) {
  $url = "https://api.myshoptet.com/api/products/code/" . rawurlencode($code) . "?include=images";

  $ch = curl_init($url);

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      "Shoptet-Private-API-Token: " . $token
    ],
    CURLOPT_TIMEOUT => 10
  ]);

  $response = json_decode(curl_exec($ch), true);

  curl_close($ch);

  $image = $response["data"]["images"][0]["seoName"] ?? "";

  if ($image === "") {
    return null;
  }

  if (empty($image)) {
    return null;
  } else
  return "https://cdn.myshoptet.com/usr/www.okfish.sk/user/shop/detail/" . $image;
}
?>


<section class="invoice-summary">

  <div class="invoice-summary_item">
    <span>Objednávka</span>
    <strong nofocus><?= htmlspecialchars($order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?></strong>
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

    <?php if ($je_dobierka) { ?>
      <small class="payment-info payment-info_cod">Platba: Dobierka</small>
    <?php } elseif ($je_neuhradene_bez_dobierky) { ?>
      <small class="payment-info payment-info_warning">Pozor: objednávka nie je uhradená</small>
    <?php } elseif (!empty($order["platba_nazov"])) { ?>
      <small class="payment-info">Platba: <?= htmlspecialchars($order["platba_nazov"], ENT_QUOTES, "UTF-8") ?></small>
    <?php } ?>
  </div>

  <div class="invoice-summary_item">
    <span>Stav</span>
    <strong>
      <button
        type="button"
        class="status order-logs-open <?= htmlspecialchars($status_class, ENT_QUOTES, "UTF-8") ?>"
        data-order-id="<?= (int) $order["id"] ?>"
        data-order-number="<?= htmlspecialchars((string) $order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?>"
        title="Zobraziť históriu objednávky"
        nofocus
      >
        <?= htmlspecialchars($status_label, ENT_QUOTES, "UTF-8") ?>
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

</section>

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

          // $produkt_obrazok = getShoptetProductImageUrl($produkt_kod, $shoptet_private_api_token);
          ?>

          <tr row_item_id="<?= htmlspecialchars($item["kod"], ENT_QUOTES, "UTF-8"); ?>">
            <td class="invoice-item_image">
              <?php if (!empty($item["image"])) { ?>
                <img
                  src="<?= htmlspecialchars($item["image"], ENT_QUOTES, "UTF-8"); ?>"
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
            </td>

            <td>
              <?= !empty($item["variant"]) ? htmlspecialchars($item["variant"], ENT_QUOTES, "UTF-8") : "—"; ?>
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
  <?php if (!empty(DOPRAVA_KODY[$order["doprava_kod"]]["api"])) { ?>
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
      <a href="/?typ=<?= urlencode($typ_kontroly) ?>" class="shipment-back-button">Späť na objednávky / faktúry</a>
    </div>
  <?php } else { ?>
    <div class="shipment-not-available">Pre tento druh dopravy nie je zatiaľ nastavené odosielanie dopravcovi.</div>
  <?php } ?>
</div>
