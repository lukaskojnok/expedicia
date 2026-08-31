<?php
$logs_action_labels = [
  "invoice_opened" => "Otvoril detail objednávky",
  "work_claimed" => "Začal pracovať na objednávke",
  "work_taken_over" => "Prevzal rozpracovanú objednávku",
  "control_completed" => "Dokončil kontrolu položiek",
  "quick_control_completed" => "Dokončil rýchlu kontrolu",
  "carrier_sent" => "Odoslal zásielku dopravcovi",
  "box_assigned" => "Uložil objednávku do expedičného boxu"
];
?>

<section class="logs-page" nofocus>
  <div class="logs-filters">
    <label class="logs-filter logs-filter_search" for="logs-search">
      <span>Objednávka alebo meno</span>
      <input type="search" id="logs-search" placeholder="Číslo objednávky alebo meno zákazníka" autocomplete="off">
    </label>
    <label class="logs-filter" for="logs-admin">
      <span>Používateľ</span>
      <select id="logs-admin">
        <option value="">Všetci používatelia</option>
        <?php foreach ($logs_admins as $logs_admin_id => $logs_admin_name) { ?>
          <option value="<?= (int) $logs_admin_id ?>"><?= htmlspecialchars($logs_admin_name, ENT_QUOTES, "UTF-8") ?></option>
        <?php } ?>
      </select>
    </label>
    <label class="logs-filter" for="logs-type">
      <span>Činnosť</span>
      <select id="logs-type">
        <option value="">Vyskladnenie aj expedícia</option>
        <option value="vyskladnenie">Vyskladnenie</option>
        <option value="expedicia">Expedícia</option>
      </select>
    </label>
    <div class="logs-filter-result">
      Zobrazené: <strong id="logs-visible-count"><?= count($logs) ?></strong>
    </div>
  </div>

  <div class="logs-list" id="logs-list">
    <?php if (empty($logs)) { ?>
      <div class="logs-empty">Za posledné 2 dni nie sú uložené žiadne logy.</div>
    <?php } ?>

    <?php foreach ($logs as $log) { ?>
      <?php
      $action = (string) ($log["action"] ?? "");
      $action_label = $logs_action_labels[$action] ?? ucfirst(str_replace("_", " ", $action ?: "Udalosť"));
      $control_type = (string) ($log["typ_kontroly"] ?? "");
      $control_label = $control_type === "vyskladnenie" ? "Vyskladnenie" : "Expedícia";
      $status = (string) ($log["status"] ?? "");
      $created_at = strtotime((string) ($log["created_at"] ?? ""));
      $created_label = $created_at ? date("d. m. Y, H:i:s", $created_at) : "—";
      $order_number = trim((string) ($log["cislo_objednavky"] ?? ""));
      $invoice_number = trim((string) ($log["cislo_faktury"] ?? ""));
      $customer_name = trim((string) ($log["customer_name"] ?? "—"));
      $user_name = trim((string) ($log["user_name"] ?? "—"));
      $message = trim((string) ($log["message"] ?? ""));
      $search_text = strtolower(implode(" ", [$order_number, $invoice_number, $customer_name, $user_name, $message]));
      $is_error = $status === "error";
      ?>
      <article
        class="logs-item <?= $is_error ? "logs-item_error" : "" ?>"
        data-admin-id="<?= (int) ($log["user_id"] ?? 0) ?>"
        data-control-type="<?= htmlspecialchars($control_type, ENT_QUOTES, "UTF-8") ?>"
        data-search="<?= htmlspecialchars($search_text, ENT_QUOTES, "UTF-8") ?>"
      >
        <time datetime="<?= htmlspecialchars((string) ($log["created_at"] ?? ""), ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($created_label, ENT_QUOTES, "UTF-8") ?></time>
        <div class="logs-item_main">
          <div class="logs-item_title">
            <strong><?= htmlspecialchars($user_name, ENT_QUOTES, "UTF-8") ?></strong>
            <span><?= htmlspecialchars($action_label, ENT_QUOTES, "UTF-8") ?></span>
          </div>
          <?php if ($message !== "") { ?>
            <p><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
          <?php } ?>
        </div>
        <div class="logs-item_order">
          <?php if ((int) ($log["order_id"] ?? 0) > 0) { ?>
            <a href="/invoice?id=<?= (int) $log["order_id"] ?>&amp;typ=<?= urlencode($control_type) ?>">Objednávka <?= htmlspecialchars($order_number ?: "#" . $log["order_id"], ENT_QUOTES, "UTF-8") ?></a>
          <?php } else { ?>
            <strong>Objednávka —</strong>
          <?php } ?>
          <span><?= htmlspecialchars($customer_name, ENT_QUOTES, "UTF-8") ?></span>
          <?php if ($invoice_number !== "") { ?><small>Faktúra <?= htmlspecialchars($invoice_number, ENT_QUOTES, "UTF-8") ?></small><?php } ?>
        </div>
        <span class="logs-item_type logs-item_type-<?= htmlspecialchars($control_type, ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($control_label, ENT_QUOTES, "UTF-8") ?></span>
      </article>
    <?php } ?>

    <div class="logs-empty" id="logs-filter-empty" hidden>Pre zvolené filtre sa nenašli žiadne logy.</div>
  </div>
</section>

<script>
$(function() {
  const $items = $("#logs-list .logs-item");
  const $empty = $("#logs-filter-empty");
  const $count = $("#logs-visible-count");

  function normalize(value) {
    return String(value || "").toLocaleLowerCase("sk").trim();
  }

  function filterLogs() {
    const search = normalize($("#logs-search").val());
    const adminId = $("#logs-admin").val();
    const controlType = $("#logs-type").val();
    let visibleCount = 0;

    $items.each(function() {
      const $item = $(this);
      const matchesSearch = search === "" || normalize($item.attr("data-search")).includes(search);
      const matchesAdmin = adminId === "" || $item.attr("data-admin-id") === adminId;
      const matchesType = controlType === "" || $item.attr("data-control-type") === controlType;
      const visible = matchesSearch && matchesAdmin && matchesType;

      $item.prop("hidden", !visible);
      if (visible) visibleCount++;
    });

    $count.text(visibleCount);
    $empty.prop("hidden", visibleCount !== 0 || $items.length === 0);
  }

  $("#logs-search").on("input", filterLogs);
  $("#logs-admin, #logs-type").on("change", filterLogs);
});
</script>
