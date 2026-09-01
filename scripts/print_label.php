<?php
require_once __DIR__ . "/../config/common.php";
require_once __DIR__ . "/../config/controls_log.php";
require_once __DIR__ . "/pdf_labels.php";

$admin_data = controls_get_admin_data($db);

if (empty($admin_data["id"])) {
  http_response_code(401);
  exit("Prihlásenie vypršalo.");
}

$order_id = (int) ($_GET["order_id"] ?? 0);
$query = $db->prepare("
  SELECT
    id,
    cislo_objednavky,
    dopravca_nazov,
    dopravca_label_path,
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
  if (!is_array($file) || shipment_pdf_label_absolute_path($file) === "") {
    continue;
  }

  $filename = basename((string) ($file["name"] ?? $file["path"]));
  $label_files[] = [
    "name" => $filename,
    "path" => "data/stitky/" . $filename,
    "url" => "/data/stitky/" . rawurlencode($filename),
    "mime_type" => "application/pdf"
  ];
}

$print_file = [];
$stored_print_path = trim((string) ($order["dopravca_label_path"] ?? ""));
$stored_print_name = basename($stored_print_path);
$original_label_names = array_column($label_files, "name");
$stored_path_is_single_original = count($label_files) > 1
  && in_array($stored_print_name, $original_label_names, true);

if ($stored_print_path !== "" && !$stored_path_is_single_original) {
  $stored_print_file = [
    "name" => $stored_print_name,
    "path" => $stored_print_path,
    "mime_type" => "application/pdf"
  ];

  if (shipment_pdf_label_absolute_path($stored_print_file) !== "") {
    $filename = $stored_print_name;
    $print_file = [
      "name" => $filename,
      "path" => "data/stitky/" . $filename,
      "url" => "/data/stitky/" . rawurlencode($filename),
      "mime_type" => "application/pdf"
    ];
  }
}

if (empty($print_file) && !empty($label_files)) {
  $prepared_print = shipment_prepare_print_pdf(
    $label_files,
    $order,
    $order["dopravca_nazov"] ?? "dopravca"
  );

  if (!empty($prepared_print["success"])) {
    $print_file = $prepared_print["file"];

    $update = $db->prepare("
      UPDATE orders
      SET dopravca_label_path = :label_path
      WHERE id = :id
    ");
    $update->execute([
      ":label_path" => $print_file["path"],
      ":id" => $order_id
    ]);
  } else {
    http_response_code(500);
    exit(htmlspecialchars((string) ($prepared_print["error"] ?? "PDF štítky sa nepodarilo pripraviť."), ENT_QUOTES, "UTF-8"));
  }
}

if (empty($print_file)) {
  http_response_code(404);
  exit("K tejto objednávke sa nenašiel PDF štítok na tlač.");
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
      .print-label_document { width: 100%; height: calc(100% - 72px); padding: 16px; }
      .print-label_document iframe { width: 100%; height: 100%; background: #fff; border: 1px solid #dce1e8; }
      @media print { .print-label_top { display: none; } .print-label_document { height: 100%; padding: 0; } }
    </style>
  </head>
  <body>
    <div class="print-label_top">
      <strong>Štítky objednávky <?= htmlspecialchars($order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?></strong>
      <div class="print-label_actions">
        <a href="<?= htmlspecialchars($print_file["url"], ENT_QUOTES, "UTF-8") ?>" download>Stiahnuť PDF</a>
        <button type="button" id="print-label-button">Vytlačiť</button>
      </div>
    </div>
    <div class="print-label_document">
      <iframe
        src="<?= htmlspecialchars($print_file["url"], ENT_QUOTES, "UTF-8") ?>"
        title="Štítky objednávky <?= htmlspecialchars($order["cislo_objednavky"], ENT_QUOTES, "UTF-8") ?>"
        id="print-label-frame"
      ></iframe>
    </div>
    <script>
      const printFrame = document.getElementById("print-label-frame");
      let automaticPrintStarted = false;

      function printLabels() {
        try {
          printFrame.contentWindow.focus();
          printFrame.contentWindow.print();
        } catch (error) {
          window.focus();
          window.print();
        }
      }

      document.getElementById("print-label-button").addEventListener("click", printLabels);

      printFrame.addEventListener("load", function() {
        if (automaticPrintStarted) {
          return;
        }

        automaticPrintStarted = true;
        setTimeout(printLabels, 500);
      });
    </script>
  </body>
</html>