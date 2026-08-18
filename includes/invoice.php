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

zasilkovnaDistributionPointBranchId
dpdServiceCode
dpdPsId