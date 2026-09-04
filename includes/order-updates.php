<section class="order-updates">
  <?php if (!empty($_SESSION["order_update_flash"])) { ?>
    <?php $update_flash = $_SESSION["order_update_flash"]; unset($_SESSION["order_update_flash"]); ?>
    <div class="stock-positions-message <?= !empty($update_flash["success"]) ? "stock-positions-message_success" : "stock-positions-message_error" ?>">
      <?= htmlspecialchars((string) ($update_flash["message"] ?? ""), ENT_QUOTES, "UTF-8") ?>
    </div>
  <?php } ?>

  <div class="order-update-actions">
    <h2>Spustiť aktualizáciu</h2>
    <p>Vyberte obdobie, od ktorého sa majú objednávky zo Shoptetu znovu načítať.</p>

    <div class="order-update-presets">
      <?php foreach ($order_update_presets as $preset_key => $preset) { ?>
        <form method="post" action="/scripts/update_invoices.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
          <input type="hidden" name="request_type" value="<?= htmlspecialchars($preset_key, ENT_QUOTES, "UTF-8") ?>">
          <input type="hidden" name="return_to" value="updates">
          <input type="hidden" name="typ" value="<?= htmlspecialchars($typ_kontroly, ENT_QUOTES, "UTF-8") ?>">
          <button type="submit" class="button button-light">
            <?= htmlspecialchars($preset["label"], ENT_QUOTES, "UTF-8") ?>
            <small>od <?= htmlspecialchars($preset["date"], ENT_QUOTES, "UTF-8") ?></small>
          </button>
        </form>
      <?php } ?>
    </div>

    <form method="post" action="/scripts/update_invoices.php" class="order-update-custom-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
      <input type="hidden" name="request_type" value="custom">
      <input type="hidden" name="return_to" value="updates">
      <input type="hidden" name="typ" value="<?= htmlspecialchars($typ_kontroly, ENT_QUOTES, "UTF-8") ?>">
      <label for="order-update-date">Vlastný dátum od</label>
      <input type="date" name="date_from" id="order-update-date" value="<?= htmlspecialchars(date("Y-m-d"), ENT_QUOTES, "UTF-8") ?>" max="<?= htmlspecialchars(date("Y-m-d"), ENT_QUOTES, "UTF-8") ?>" required>
      <button type="submit" class="button">Aktualizovať od zvoleného dátumu</button>
    </form>
  </div>

  <div class="table-box order-update-log-box">
    <table class="data-table order-update-log-table">
      <thead>
        <tr>
          <th>Spustené</th>
          <th>Spôsob</th>
          <th>Obdobie od</th>
          <th>Výsledok</th>
          <th>Nové</th>
          <th>Zmenené</th>
          <th>Bez zmeny</th>
          <th>Trvanie</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$order_update_logs) { ?>
          <tr><td colspan="8" class="data-table_empty">Zatiaľ nebola zaznamenaná žiadna aktualizácia.</td></tr>
        <?php } ?>

        <?php foreach ($order_update_logs as $update_log) { ?>
          <?php
          $update_started = strtotime($update_log["started_at"]);
          $update_finished = !empty($update_log["finished_at"]) ? strtotime($update_log["finished_at"]) : false;
          $update_duration = $update_finished && $update_started ? max(0, $update_finished - $update_started) . " s" : "—";
          $source_label = [
            "cron" => "Cron",
            "automatic" => "Automaticky",
            "manual" => "Manuálne"
          ][$update_log["source"]] ?? $update_log["source"];
          $source_class = $update_log["source"] === "cron"
            ? "status-active"
            : ($update_log["source"] === "automatic" ? "status-done" : "status-waiting");
          $status_label = ["running" => "Prebieha", "success" => "Úspešná", "error" => "Chyba"][$update_log["status"]] ?? $update_log["status"];
          ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars(date("d. m. Y H:i:s", $update_started), ENT_QUOTES, "UTF-8") ?></strong>
              <?php if (!empty($update_log["admin_name"])) { ?><div class="table-sub-text"><?= htmlspecialchars($update_log["admin_name"], ENT_QUOTES, "UTF-8") ?></div><?php } ?>
            </td>
            <td><span class="status <?= $source_class ?>"><?= htmlspecialchars($source_label, ENT_QUOTES, "UTF-8") ?></span></td>
            <td><?= htmlspecialchars(date("d. m. Y H:i:s", strtotime($update_log["update_from"])), ENT_QUOTES, "UTF-8") ?></td>
            <td>
              <span class="status status-<?= htmlspecialchars($update_log["status"], ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($status_label, ENT_QUOTES, "UTF-8") ?></span>
              <?php if (!empty($update_log["message"])) { ?><div class="order-update-message"><?= htmlspecialchars($update_log["message"], ENT_QUOTES, "UTF-8") ?></div><?php } ?>
            </td>
            <td><strong><?= (int) $update_log["new_orders"] ?></strong></td>
            <td><strong><?= (int) $update_log["changed_orders"] ?></strong></td>
            <td><?= (int) $update_log["unchanged_orders"] ?></td>
            <td><?= htmlspecialchars($update_duration, ENT_QUOTES, "UTF-8") ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</section>