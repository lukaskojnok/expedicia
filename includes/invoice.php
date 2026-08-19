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

  <div class="invoice-summary_item invoice-summary_shipping">
    <span>Spôsob doručenia</span>
    <strong><?= htmlspecialchars($doprava_nazov, ENT_QUOTES, "UTF-8") ?></strong>

    <?php if (!empty($order["ulozne_miesto"])) { ?>
      <small><?= htmlspecialchars($order["ulozne_miesto"], ENT_QUOTES, "UTF-8") ?></small>
    <?php } elseif (!empty($order["doprava_kod"])) { ?>
      <small><?= htmlspecialchars($order["doprava_kod"], ENT_QUOTES, "UTF-8") ?></small>
    <?php } ?>
  </div>

</section>

<div class="table-box">

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

          <tr>

            <td class="invoice-item_image">
              <?php if ($produkt_obrazok !== NULL) { ?>
                <img src="<?= htmlspecialchars($produkt_obrazok, ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($item["nazov"], ENT_QUOTES, "UTF-8") ?>">
              <?php } else { ?>
                <span>Bez obrázka</span>
              <?php } ?>

            </td>

            <td class="invoice-item_code">
              <strong><?= htmlspecialchars($item["kod"] ?: "—", ENT_QUOTES, "UTF-8") ?></strong>
            </td>

            <td class="invoice-item_name">
              <strong><?= htmlspecialchars($item["nazov"], ENT_QUOTES, "UTF-8") ?></strong>
            </td>

            <td><?= htmlspecialchars($item["variant_nazov"] ?: "—", ENT_QUOTES, "UTF-8") ?></td>

            <td class="invoice-item_amount">
              <?= htmlspecialchars((string) $mnozstvo, ENT_QUOTES, "UTF-8") ?>
            </td>

            <td class="invoice-item_amount_control" item_id="<?= $item["kod"]; ?>" item_amount="<?= $mnozstvo; ?>">
              <?= htmlspecialchars((string) $mnozstvo, ENT_QUOTES, "UTF-8") ?>
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



