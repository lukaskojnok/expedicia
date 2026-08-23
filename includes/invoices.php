<div class="table-box">

  <table class="data-table invoices-table" data-workers-control-type="<?= htmlspecialchars($typ_kontroly, ENT_QUOTES, "UTF-8") ?>">
    <thead>
      <tr>
        <!-- <th>Číslo faktúry</th> -->
        <th>Objednávka</th>
        <th>E-shop</th>
        <th>Zákazník</th>
        <th>Doprava</th>
        <th>Dátum</th>
        <th>Položky</th>
        <th>Suma</th>
        <th>Vyskladnenie</th>
        <th>Expedícia</th>
        <th>Pracuje</th>
        <!-- <th></th> -->
      </tr>
    </thead>

    <tbody>

      <?php if (!empty($results)) { ?>

        <?php foreach ($results as $result) { ?>
          <?php
          $status = $result[$status_column] ?? "nove";
          $user_id = $result[$user_column] ?? null;

          $status_label = $status_labels[$status] ?? $status;
          $status_class = $status_classes[$status] ?? "status-waiting";
          $status_vyskladnenie = $result["status_vyskladnenie"] ?? "nove";
          $status_expedicia = $result["status_expedicia"] ?? "nove";
          $working_user_name = trim((string) ($result["working_user_name"] ?? ""));

          $row_classes = [];

          if (!empty($result["zmena"])) {
            $row_classes[] = "row-changed";
          }

          if ($status === "ukoncene") {
            $row_classes[] = "row-completed";
          }

          $row_class = implode(" ", $row_classes);

          $datum = "—";

          if (!empty($result["datum_objednavky"])) {
            $datum_timestamp = strtotime($result["datum_objednavky"]);

            if ($datum_timestamp !== false) {
              $datum = date("d.m. H:i", $datum_timestamp);
            }
          }

          $pocet_poloziek = (float) $result["pocet_poloziek"];

          if (floor($pocet_poloziek) === $pocet_poloziek) {
            $pocet_poloziek = (int) $pocet_poloziek;
          }

          if ($status === "nove") {
            $button_text = "Otvoriť";
            $button_class = "button";
          } elseif ($status === "v_procese") {
            $button_text = "Detail";
            $button_class = "button";
          } else {
            $button_text = "Detail";
            $button_class = "button button-light";
          }

          $zdroj_eshop = $result["zdroj_eshop"] ?: "—";
          $zdroj = $result["zdroj"] ?: null;

          $zakaznik_meno = $result["fakturacne_meno"] ?: $result["dodacie_meno"] ?: "—";
          $zakaznik_mesto = $result["dodacie_mesto"] ?: $result["fakturacne_mesto"] ?: "—";

          $shipping_data = DOPRAVA_KODY[$result["doprava_kod"]] ?? null;
          $shipping_class = $shipping_data["class"] ?? "shipping-unknown";
          $doprava_nazov = ($shipping_data["name"] ?? "") ?: ($result["doprava_nazov"] ?: "Neuvedená doprava");
          $foxdeli_pick_up_place = $result["foxdeli_pick_up_place"];

          $suma_objednavky = number_format(
            (float) $result["cena_na_uhradu"],
            2,
            ",",
            " "
          );

          $mena = $result["mena"] ?: "EUR";
          $je_dobierka = order_is_cod($result);
          $je_neuhradene_bez_dobierky = !$je_dobierka && empty($result["uhradene"]);
          ?>

          <?php
          $invoice_url = "/invoice?id=" . (int) $result["id"] . "&typ=" . urlencode($typ_kontroly);
          ?>
          <tr
            class="invoice-row <?= htmlspecialchars($row_class, ENT_QUOTES, "UTF-8") ?>"
            data-url="<?= htmlspecialchars($invoice_url, ENT_QUOTES, "UTF-8") ?>"
            data-invoice-number="<?= htmlspecialchars((string) $result["cislo_faktury"], ENT_QUOTES, "UTF-8") ?>"
            data-order-number="<?= htmlspecialchars((string) $result["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?>"
          >

            <!-- <td>
              <div class="order-number">
                <strong nofocus>
                  <?= htmlspecialchars($result["cislo_faktury"] ?: "—", ENT_QUOTES, "UTF-8") ?>
                </strong>
              </div>
            </td> -->

            <td>
              <strong>
                <?= htmlspecialchars($result["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?>
              </strong>
            </td>

            <td class="data-table_source">
              <div class="table-main-text">
                <?= htmlspecialchars($zdroj_eshop, ENT_QUOTES, "UTF-8") ?>
              </div>

              <?php if (!empty($zdroj)) { ?>
                <div class="table-sub-text">
                  <?= htmlspecialchars($zdroj, ENT_QUOTES, "UTF-8") ?>
                </div>
              <?php } ?>
            </td>

            <td>
              <div class="table-main-text">
                <?= htmlspecialchars($zakaznik_meno, ENT_QUOTES, "UTF-8") ?>
              </div>

              <div class="table-sub-text">
                <?= htmlspecialchars($zakaznik_mesto, ENT_QUOTES, "UTF-8") ?>
              </div>
            </td>

            <td class="data-table_shipping <?= htmlspecialchars($shipping_class, ENT_QUOTES, "UTF-8") ?>">
              <div class="table-main-text">
                <?= htmlspecialchars($doprava_nazov, ENT_QUOTES, "UTF-8") ?>
              </div>

              <?php if (!empty($result["doprava_kod"])) { ?>
                <div class="table-sub-text">
                  <?= htmlspecialchars($result["doprava_kod"], ENT_QUOTES, "UTF-8") ?>
                </div>
              <?php } ?>

              <?php if (!empty($foxdeli_pick_up_place)) { ?>
                <div class="table-sub-text">
                  <?= htmlspecialchars($foxdeli_pick_up_place, ENT_QUOTES, "UTF-8") ?>
                </div>
              <?php } ?>

              <?php if (!isset(DOPRAVA_KODY[$result["doprava_kod"]])) { ?>
                <div class="shipping-warning">
                  Tento druh dopravy nie je zaznamenaný v systéme. Kontaktujte administrátora.
                </div>
              <?php } ?>
            </td>

            <td align="center">
              <?= htmlspecialchars($datum, ENT_QUOTES, "UTF-8") ?>
            </td>

            <td align="center">
              <?= htmlspecialchars((string) $pocet_poloziek, ENT_QUOTES, "UTF-8") ?>
            </td>

            <td class="data-table_price">
              <?= htmlspecialchars($suma_objednavky, ENT_QUOTES, "UTF-8") ?>
              <?= htmlspecialchars($mena, ENT_QUOTES, "UTF-8") ?>

              <?php if ($je_dobierka) { ?>
                <div class="payment-info payment-info_cod">Platba: Dobierka</div>
              <?php } elseif ($je_neuhradene_bez_dobierky) { ?>
                <div class="payment-info payment-info_warning">Pozor: objednávka nie je uhradená</div>
              <?php } elseif (!empty($result["platba_nazov"])) { ?>
                <div class="payment-info">Platba: <?= htmlspecialchars($result["platba_nazov"], ENT_QUOTES, "UTF-8") ?></div>
              <?php } ?>
            </td>

            <td>
              <button
                type="button"
                class="status order-logs-open <?= htmlspecialchars($status_classes[$status_vyskladnenie] ?? "status-waiting", ENT_QUOTES, "UTF-8") ?>"
                data-order-id="<?= (int) $result["id"] ?>"
                data-order-number="<?= htmlspecialchars((string) $result["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?>"
                title="Zobraziť históriu objednávky"
                nofocus
              >
                <?= htmlspecialchars($status_labels[$status_vyskladnenie] ?? $status_vyskladnenie, ENT_QUOTES, "UTF-8") ?>
              </button>

              <?php if (!empty($result["zmena"])) { ?>
                <span
                  class="status status-changed"
                  title="<?= htmlspecialchars($result["zmena_poznamka"] ?: "Objednávka bola zmenená.", ENT_QUOTES, "UTF-8") ?>"
                >
                  Zmenené
                </span>
              <?php } ?>
            </td>

            <td>
              <button
                type="button"
                class="status order-logs-open <?= htmlspecialchars($status_classes[$status_expedicia] ?? "status-waiting", ENT_QUOTES, "UTF-8") ?>"
                data-order-id="<?= (int) $result["id"] ?>"
                data-order-number="<?= htmlspecialchars((string) $result["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?>"
                title="Zobraziť históriu objednávky"
                nofocus
              >
                <?= htmlspecialchars($status_labels[$status_expedicia] ?? $status_expedicia, ENT_QUOTES, "UTF-8") ?>
              </button>
            </td>

            <td class="order-worker" data-order-worker-id="<?= (int) $result["id"] ?>">
              <?php if ($working_user_name !== "" && $status === "v_procese") { ?>
                <span class="working-user">
                  <?= $typ_kontroly === "vyskladnenie" ? "Vyskladňuje" : "Expeduje" ?>:
                  <strong><?= htmlspecialchars($working_user_name, ENT_QUOTES, "UTF-8") ?></strong>
                </span>
              <?php } elseif ($working_user_name !== "" && $status === "ukoncene") { ?>
                <span class="working-user working-user_done">
                  <?= $typ_kontroly === "vyskladnenie" ? "Vyskladnil" : "Expedoval" ?>:
                  <strong><?= htmlspecialchars($working_user_name, ENT_QUOTES, "UTF-8") ?></strong>
                </span>
              <?php } else { ?>
                <span class="table-empty">—</span>
              <?php } ?>
            </td>

            <!-- <td class="data-table_action">
              <a
                href="<?= htmlspecialchars($invoice_url, ENT_QUOTES, "UTF-8") ?>"
                class="<?= htmlspecialchars($button_class, ENT_QUOTES, "UTF-8") ?>"
              >
                <?= htmlspecialchars($button_text, ENT_QUOTES, "UTF-8") ?>
              </a>
            </td> -->

          </tr>

        <?php } ?>

      <?php } else { ?>

        <tr>
          <td colspan="10" class="data-table_empty">
            Nenašli sa žiadne objednávky.
          </td>
        </tr>

      <?php } ?>

    </tbody>
  </table>

</div>
