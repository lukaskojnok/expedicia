<?php
require_once __DIR__ . "/../config/common.php";
require_once __DIR__ . "/../config/controls_log.php";

header("Content-Type: application/json; charset=utf-8");

function send_data_response($success, $message, $http_code = 200) {
  http_response_code($http_code);
  echo json_encode(["success" => $success, "message" => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  send_data_response(false, "Neplatná požiadavka.", 405);
}

$order_id = (int) ($_POST["order_id"] ?? 0);
$typ_kontroly = $_POST["typ_kontroly"] ?? "expedicia";
$weights_input = $_POST["weights"] ?? [];
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
require $api_file;

if (!isset($carrier_response) || !is_array($carrier_response)) {
  send_data_response(false, "Dopravca nevrátil platnú odpoveď.", 502);
}

$success = !empty($carrier_response["success"]);
$message = trim((string) ($carrier_response["message"] ?? "Zásielku sa nepodarilo odoslať."));
$http_code = (int) ($carrier_response["http_code"] ?? 0);
$response_code = (int) ($carrier_response["response_code"] ?? ($success ? 200 : 422));
$api_response = $carrier_response["response"] ?? "";
$carrier_name = $shipping["name"] ?? $order["doprava_nazov"];
$reference = $carrier_response["reference"] ?? $order["cislo_objednavky"];

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
  "message" => $message
]);

send_data_response($success, $message, $response_code);