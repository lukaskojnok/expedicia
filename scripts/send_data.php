<?php
ini_set("display_errors", "0");

require_once __DIR__ . "/../config/common.php";
require_once __DIR__ . "/../config/controls_log.php";

header("Content-Type: application/json; charset=utf-8");

function send_data_response($success, $message, $http_code = 200, $data = []) {
  if (!$success && !array_key_exists("api_response", $data)) {
    $data["api_response"] = json_encode([
      "success" => false,
      "message" => $message,
      "source" => "expedicia"
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  http_response_code($http_code);
  echo json_encode(array_merge([
    "success" => $success,
    "message" => $message
  ], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function shipment_safe_filename_part($value, $fallback) {
  $value = preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim((string) $value));
  $value = trim((string) $value, "-_");

  return $value !== "" ? $value : $fallback;
}

function shipment_label_extension($mime_type, $fallback = "pdf") {
  $extensions = [
    "application/pdf" => "pdf",
    "application/zpl" => "zpl",
    "text/plain" => "zpl",
    "image/png" => "png"
  ];

  return $extensions[strtolower(trim((string) $mime_type))] ?? $fallback;
}

function shipment_download_label($url) {
  $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

  if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ["http", "https"], true)) {
    return ["success" => false, "message" => "Dopravca vrátil neplatnú URL štítka."];
  }

  $curl = curl_init($url);
  curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_MAXREDIRS => 3
  ]);

  $content = curl_exec($curl);
  $error = curl_error($curl);
  $http_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
  $mime_type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
  curl_close($curl);

  if ($error !== "" || $http_code < 200 || $http_code >= 300 || !is_string($content) || $content === "") {
    return ["success" => false, "message" => "Štítok sa nepodarilo stiahnuť z URL dopravcu."];
  }

  return [
    "success" => true,
    "content" => $content,
    "mime_type" => strtok($mime_type, ";") ?: "application/pdf"
  ];
}

function shipment_decode_label($label) {
  if (is_string($label)) {
    $label = ["content" => $label];
  }

  if (!is_array($label)) {
    return ["success" => false, "message" => "Dopravca vrátil neplatné dáta štítka."];
  }

  $content = $label["content"] ?? $label["data"] ?? $label["base64"] ?? $label["url"] ?? "";
  $mime_type = trim((string) ($label["mime_type"] ?? $label["mimeType"] ?? "application/pdf"));
  $extension = strtolower(trim((string) ($label["extension"] ?? "")));

  if (!is_string($content) || trim($content) === "") {
    return ["success" => false, "message" => "Dopravca nevrátil obsah štítka."];
  }

  if (filter_var(trim($content), FILTER_VALIDATE_URL)) {
    $download = shipment_download_label(trim($content));

    if (empty($download["success"])) {
      return $download;
    }

    $content = $download["content"];
    $mime_type = $download["mime_type"];
  } elseif (preg_match('/^data:([^;,]+);base64,(.+)$/s', trim($content), $matches)) {
    $mime_type = trim($matches[1]);
    $content = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
  } elseif (strncmp($content, "%PDF-", 5) !== 0 && strncmp($content, "^XA", 3) !== 0 && strncmp($content, "\x89PNG", 4) !== 0) {
    $decoded = base64_decode(preg_replace('/\s+/', '', $content), true);

    if ($decoded === false || $decoded === "") {
      return ["success" => false, "message" => "Štítok sa nepodarilo dekódovať."];
    }

    $content = $decoded;
  }

  if (!is_string($content) || $content === "") {
    return ["success" => false, "message" => "Štítok je prázdny."];
  }

  if (strncmp($content, "%PDF-", 5) === 0) {
    $mime_type = "application/pdf";
    $extension = "pdf";
  } elseif (strncmp($content, "^XA", 3) === 0) {
    $mime_type = "application/zpl";
    $extension = "zpl";
  } elseif (strncmp($content, "\x89PNG", 4) === 0) {
    $mime_type = "image/png";
    $extension = "png";
  }

  if (!in_array($extension, ["pdf", "zpl", "png"], true)) {
    $extension = shipment_label_extension($mime_type);
  }

  return [
    "success" => true,
    "content" => $content,
    "mime_type" => $mime_type,
    "extension" => $extension
  ];
}

function shipment_save_labels($labels, $order, $carrier_code) {
  if (empty($labels) || !is_array($labels)) {
    return [
      "files" => [],
      "error" => "Dopravca nevrátil žiadny štítok. Zásielka však bola vytvorená."
    ];
  }

  $directory = rtrim(DATAS_ROOT, "/\\") . DIRECTORY_SEPARATOR . "stitky";

  if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    return [
      "files" => [],
      "error" => "Priečinok /data/stitky sa nepodarilo vytvoriť. Zásielka však bola vytvorená."
    ];
  }

  if (!is_writable($directory)) {
    return [
      "files" => [],
      "error" => "Priečinok /data/stitky nie je zapisovateľný. Zásielka však bola vytvorená."
    ];
  }

  $order_number = shipment_safe_filename_part($order["cislo_objednavky"] ?? $order["id"] ?? "objednavka", "objednavka");
  $carrier = shipment_safe_filename_part($carrier_code, "dopravca");
  $timestamp = date("Ymd-His");
  $files = [];
  $errors = [];
  $label_count = count($labels);

  foreach (array_values($labels) as $index => $label) {
    $decoded = shipment_decode_label($label);

    if (empty($decoded["success"])) {
      $errors[] = $decoded["message"] ?? "Štítok sa nepodarilo spracovať.";
      continue;
    }

    $suffix = $label_count > 1 ? "-" . ($index + 1) : "";
    $filename = strtolower($carrier) . "-" . $order_number . "-" . $timestamp . $suffix . "." . $decoded["extension"];
    $absolute_path = $directory . DIRECTORY_SEPARATOR . $filename;

    if (file_put_contents($absolute_path, $decoded["content"], LOCK_EX) === false) {
      $errors[] = "Súbor {$filename} sa nepodarilo uložiť.";
      continue;
    }

    $files[] = [
      "name" => $filename,
      "path" => "data/stitky/" . $filename,
      "url" => "/data/stitky/" . rawurlencode($filename),
      "mime_type" => $decoded["mime_type"]
    ];
  }

  return [
    "files" => $files,
    "error" => !empty($errors) ? implode(" ", $errors) : ""
  ];
}

set_exception_handler(function($exception) {
  send_data_response(false, "Pri odosielaní zásielky nastala neočakávaná chyba.", 500, [
    "api_response" => json_encode([
      "success" => false,
      "type" => get_class($exception),
      "message" => $exception->getMessage(),
      "source" => "expedicia"
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
  ]);
});

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  send_data_response(false, "Neplatná požiadavka.", 405);
}

$order_id = (int) ($_POST["order_id"] ?? 0);
$typ_kontroly = $_POST["typ_kontroly"] ?? "expedicia";
$weights_input = $_POST["weights"] ?? [];
$print_label = filter_var($_POST["print_label"] ?? false, FILTER_VALIDATE_BOOLEAN);
$weights = [];

if (!in_array($typ_kontroly, ["vyskladnenie", "expedicia"], true)) {
  $typ_kontroly = "expedicia";
}

foreach ((array) $weights_input as $weight_input) {
  $weight = (float) str_replace(",", ".", (string) $weight_input);

  if ($weight > 0 && $weight <= 50) {
    $weights[] = $weight;
  }
}

if ($order_id <= 0 || empty($weights)) {
  send_data_response(false, "Zadaj aspoň jednu platnú hmotnosť balíka.", 422);
}

$admin_data = controls_get_admin_data($db);
$user_id = (int) ($admin_data["id"] ?? 0);

if ($user_id <= 0) {
  send_data_response(false, "Nepodarilo sa určiť prihláseného administrátora.", 401);
}

$query = $db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
$query->execute([":id" => $order_id]);
$order = $query->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  send_data_response(false, "Objednávka nebola nájdená.", 404);
}

if (($order["status_odoslanie_dopravcovi"] ?? "") === "success") {
  send_data_response(false, "Táto objednávka už bola odoslaná dopravcovi.", 409);
}

$shipping = DOPRAVA_KODY[$order["doprava_kod"]] ?? null;
$api = $shipping["api"] ?? "";

if ($api === "") {
  send_data_response(false, "Pre tento druh dopravy nie je nastavené odosielanie dopravcovi.", 422);
}

$api_file = __DIR__ . "/api/" . basename($api) . ".php";

if (!is_file($api_file)) {
  send_data_response(false, "API modul pre túto dopravu ešte nie je vytvorený.", 501);
}

$shipment_data = ["order" => $order, "shipping" => $shipping, "weights" => $weights];

try {
  require $api_file;
} catch (Throwable $exception) {
  $carrier_response = [
    "success" => false,
    "message" => "Pri spracovaní odpovede dopravcu nastala neočakávaná chyba.",
    "http_code" => 0,
    "response_code" => 500,
    "response" => json_encode([
      "success" => false,
      "exception" => $exception->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    "reference" => $order["cislo_objednavky"]
  ];
}

if (!isset($carrier_response) || !is_array($carrier_response)) {
  send_data_response(false, "Dopravca nevrátil platnú odpoveď.", 502);
}

$success = !empty($carrier_response["success"]);
$message = trim((string) ($carrier_response["message"] ?? "Zásielku sa nepodarilo odoslať."));
$http_code = (int) ($carrier_response["http_code"] ?? 0);
$response_code = (int) ($carrier_response["response_code"] ?? ($success ? 200 : 422));
$api_response = $carrier_response["response"] ?? "";
$carrier_name = $shipping["name"] ?? $order["doprava_nazov"];
$carrier_code = $shipping["carrier"] ?? $api;
$reference = $carrier_response["reference"] ?? $order["cislo_objednavky"];
$label_files = [];
$label_error = "";

if ($message === "") {
  $message = $success
    ? "Zásielka bola úspešne odoslaná dopravcovi."
    : "Zásielku sa nepodarilo odoslať.";
}

if (is_array($api_response) || is_object($api_response)) {
  $api_response = json_encode($api_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$api_response = (string) $api_response;
$reference = trim((string) $reference);

if (!$success) {
  if (trim($api_response) === "") {
    $api_response = json_encode([
      "success" => false,
      "message" => $message,
      "carrier" => $carrier_name
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } else {
    json_decode($api_response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      $api_response = json_encode([
        "success" => false,
        "message" => $message,
        "carrier" => $carrier_name,
        "carrier_response_raw" => $api_response
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
  }
}

if ($success) {
  $saved_labels = shipment_save_labels($carrier_response["labels"] ?? [], $order, $carrier_code);
  $label_files = $saved_labels["files"];
  $label_error = trim((string) $saved_labels["error"]);
}

$label_files_json = !empty($label_files)
  ? json_encode($label_files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
  : null;
$label_path = $label_files[0]["path"] ?? null;

$query = $db->prepare("
  UPDATE orders SET
    status_odoslanie_dopravcovi = :shipment_status,
    odoslane_dopravcovi_at = CASE
      WHEN :success = 1 THEN NOW()
      ELSE NULL
    END,
    dopravca_nazov = :carrier_name,
    dopravca_response = :api_response,
    dopravca_http_kod = :http_code,
    dopravca_reference = :reference,
    dopravca_label_path = :label_path,
    dopravca_label_subory = :label_files,
    status_expedicia = CASE
      WHEN :success_status = 1 THEN 'ukoncene'
      ELSE status_expedicia
    END,
    expedicia_user_id = :user_id,
    zmena = CASE
      WHEN :success_changed = 1 THEN 0
      ELSE zmena
    END
  WHERE id = :id
");
$query->execute([
  ":shipment_status" => $success ? "success" : "error",
  ":success" => $success ? 1 : 0,
  ":carrier_name" => $carrier_name,
  ":api_response" => $api_response,
  ":http_code" => $http_code,
  ":reference" => $reference,
  ":label_path" => $label_path,
  ":label_files" => $label_files_json,
  ":success_status" => $success ? 1 : 0,
  ":success_changed" => $success ? 1 : 0,
  ":user_id" => $user_id,
  ":id" => $order_id
]);

controls_add_log($db, $order_id, $user_id, $typ_kontroly, "carrier_sent", $success ? "success" : "error", [
  "finished" => $success,
  "carrier" => $carrier_name,
  "shipment_reference" => $reference,
  "api_http_code" => $http_code,
  "api_response" => $api_response,
  "label_files" => $label_files_json,
  "print_requested" => $print_label,
  "message" => $label_error !== "" ? $message . " " . $label_error : $message
]);

if (!$success) {
  send_data_response(false, $message, $response_code, [
    "api_response" => $api_response
  ]);
}

send_data_response(true, $message, 200, [
  "warning" => $label_error,
  "label_files" => $label_files,
  "label_url" => $label_files[0]["url"] ?? "",
  "print_url" => $print_label && !empty($label_files)
    ? "/scripts/print_label.php?order_id=" . $order_id
    : ""
]);
