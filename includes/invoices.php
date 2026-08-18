<div class="table-box">

  <table class="data-table">
    <thead>
      <tr>
        <th>Číslo faktúry</th>
        <th>Objednávka</th>
        <th>E-shop</th>
        <th>Zákazník</th>
        <th>Doprava</th>
        <th>Dátum</th>
        <th>Položky</th>
        <th>Suma</th>
        <th><?= htmlspecialchars($status_title, ENT_QUOTES, "UTF-8") ?></th>
        <th>Pracuje</th>
        <th></th>
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
              $datum = date("d. m. Y, H:i", $datum_timestamp);
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

          $doprava_nazov = $result["doprava_nazov"] ?: "Neuvedená doprava";
          $foxdeli_pick_up_place = $result["foxdeli_pick_up_place"];

          $suma_objednavky = number_format(
            (float) $result["cena_na_uhradu"],
            2,
            ",",
            " "
          );

          $mena = $result["mena"] ?: "EUR";
          ?>

          <?php
          $invoice_url = "/invoice?id=" . (int) $result["id"] . "&typ=" . urlencode($typ_kontroly);
          ?>

          <tr
            class="invoice-row <?= htmlspecialchars($row_class, ENT_QUOTES, "UTF-8") ?>"
            data-url="<?= htmlspecialchars($invoice_url, ENT_QUOTES, "UTF-8") ?>"
          >

            <td>
              <div class="order-number">
                <strong>
                  <?= htmlspecialchars($result["cislo_faktury"] ?: "—", ENT_QUOTES, "UTF-8") ?>
                </strong>
              </div>
            </td>

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

            <td class="data-table_shipping">
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
            </td>

            <td>
              <?= htmlspecialchars($datum, ENT_QUOTES, "UTF-8") ?>
            </td>

            <td>
              <?= htmlspecialchars((string) $pocet_poloziek, ENT_QUOTES, "UTF-8") ?>
            </td>

            <td class="data-table_price">
              <?= htmlspecialchars($suma_objednavky, ENT_QUOTES, "UTF-8") ?>
              <?= htmlspecialchars($mena, ENT_QUOTES, "UTF-8") ?>
            </td>

            <td>
              <span class="status <?= htmlspecialchars($status_class, ENT_QUOTES, "UTF-8") ?>">
                <?= htmlspecialchars($status_label, ENT_QUOTES, "UTF-8") ?>
              </span>

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
              <?php if ($status === "v_procese" && !empty($user_id)) { ?>
                <span class="working-user">
                  User ID: <?= (int) $user_id ?>
                </span>
              <?php } else { ?>
                <span class="table-empty">—</span>
              <?php } ?>
            </td>

            <td class="data-table_action">
              <a
                href="<?= htmlspecialchars($invoice_url, ENT_QUOTES, "UTF-8") ?>"
                class="<?= htmlspecialchars($button_class, ENT_QUOTES, "UTF-8") ?>"
              >
                <?= htmlspecialchars($button_text, ENT_QUOTES, "UTF-8") ?>
              </a>
            </td>

          </tr>

        <?php } ?>

      <?php } else { ?>

        <tr>
          <td colspan="11" class="data-table_empty">
            Nenašli sa žiadne faktúry.
          </td>
        </tr>

      <?php } ?>

    </tbody>
  </table>

</div>