<?php
require_once __DIR__ . "/../config/common.php";
require_once __DIR__ . "/../config/controls_log.php";

$admin_data = controls_get_admin_data($db);

if (empty($admin_data["id"])) {
  http_response_code(401);
  exit("Prihlásenie vypršalo.");
}

$order_id = (int) ($_GET["order_id"] ?? 0);
$query = $db->prepare("
  SELECT
    cislo_objednavky,
    dopravca_label_subory
  FROM orders
  WHERE id = :id
  LIMIT 1
");
$query->execute([":id" => $order_id]);
$order = $query->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  http_response_code(404);
  exit("Objednávka nebola nájdená.");
}

$stored_files = json_decode((string) $order["dopravca_label_subory"], true);
$label_files = [];

foreach ((array) $stored_files as $file) {
  $filename = basename((string) ($file["name"] ?? $file["path"] ?? ""));
  $absolute_path = rtrim(DATAS_ROOT, "/\\") . DIRECTORY_SEPARATOR . "stitky" . DIRECTORY_SEPARATOR . $filename;

  if ($filename === "" || !is_file($absolute_path)) {
    continue;
  }

  $label_files[] = [
    "name" => $filename,
    "url" => "/data/stitky/" . rawurlencode($filename),
    "mime_type" => (string) ($file["mime_type"] ?? "application/pdf")
  ];
}

if (empty($label_files)) {
  http_response_code(404);
  exit("K tejto objednávke sa nenašiel uložený štítok.");
}
?>
<!DOCTYPE html>
<html lang="sk">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Štítky objednávky <?= htmlspecialchars($order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?></title>
    <style>
      * { box-sizing: border-box; }
      html, body { width: 100%; height: 100%; margin: 0; font-family: Arial, sans-serif; color: #172033; background: #eef1f5; }
      .print-label_top { display: flex; align-items: center; justify-content: space-between; gap: 20px; min-height: 72px; padding: 12px 20px; background: #172033; }
      .print-label_top strong { font-size: 17px; color: #fff; }
      .print-label_actions { display: flex; gap: 10px; }
      .print-label_actions button, .print-label_actions a { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px; font-size: 14px; font-weight: 700; color: #fff; background: #246bfe; border: 0; text-decoration: none; cursor: pointer; }
      .print-label_list { display: grid; grid-template-columns: repeat(<?= count($label_files) ?>, minmax(0, 1fr)); gap: 16px; width: 100%; height: calc(100% - 72px); padding: 16px; }
      .print-label_item { display: flex; flex-direction: column; min-width: 0; min-height: 0; background: #fff; border: 1px solid #dce1e8; }
      .print-label_item iframe { width: 100%; height: 100%; border: 0; }
      @media print { .print-label_top { display: none; } .print-label_list { height: 100%; padding: 0; } }
    </style>
  </head>
  <body>
    <div class="print-label_top">
      <strong>Štítky objednávky <?= htmlspecialchars($order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?></strong>
      <div class="print-label_actions">
        <?php foreach ($label_files as $index => $file) { ?>
          <a href="<?= htmlspecialchars($file["url"], ENT_QUOTES, "UTF-8") ?>" download>Stiahnuť<?= count($label_files) > 1 ? " " . ($index + 1) : "" ?></a>
        <?php } ?>
        <button type="button" id="print-label-button">Vytlačiť</button>
      </div>
    </div>
    <div class="print-label_list">
      <?php foreach ($label_files as $index => $file) { ?>
        <div class="print-label_item">
          <iframe
            src="<?= htmlspecialchars($file["url"], ENT_QUOTES, "UTF-8") ?>"
            title="Štítok <?= $index + 1 ?>"
            data-label-frame
          ></iframe>
        </div>
      <?php } ?>
    </div>
    <script>
      const frames = Array.from(document.querySelectorAll("[data-label-frame]"));
      let automaticPrintStarted = false;

      function printLabels() {
        if (frames.length === 1) {
          try {
            frames[0].contentWindow.focus();
            frames[0].contentWindow.print();
            return;
          } catch (error) {
          }
        }

        window.focus();
        window.print();
      }

      document.getElementById("print-label-button").addEventListener("click", printLabels);

      frames[0].addEventListener("load", function() {
        if (automaticPrintStarted) {
          return;
        }

        automaticPrintStarted = true;
        setTimeout(printLabels, 300);
      });
    </script>
  </body>
</html>
