<?php
require_once __DIR__ . "/../config/common.php";

header("Content-Type: application/json; charset=utf-8");

function send_data_response($success, $message, $http_code = 200) {
  http_response_code($http_code);
  echo json_encode([
    "success" => $success,
    "message" => $message
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  send_data_response(false, "Neplatná požiadavka.", 405);
}

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$weights_input = $_POST["weights"] ?? [];
$weights = [];

if (!is_array($weights_input)) {
  $weights_input = [$weights_input];
}

foreach ($weights_input as $weight_input) {
  $weight = (float) str_replace(",", ".", (string) $weight_input);

  if ($weight > 0 && $weight <= 50) {
    $weights[] = $weight;
  }
}

if ($order_id <= 0 || empty($weights)) {
  send_data_response(false, "Zadaj aspoň jednu platnú hmotnosť balíka.", 422);
}

$query = $db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
$query->execute([":id" => $order_id]);
$order = $query->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  send_data_response(false, "Objednávka nebola nájdená.", 404);
}

if (($order["status_expedicia"] ?? "") === "ukoncene") {
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

$shipment_data = [
  "order" => $order,
  "shipping" => $shipping,
  "weights" => $weights
];

require $api_file;

if (!isset($carrier_response) || !is_array($carrier_response)) {
  send_data_response(false, "Dopravca nevrátil platnú odpoveď.", 502);
}

if (!empty($carrier_response["success"])) {
  $query = $db->prepare("UPDATE orders SET status_expedicia = 'ukoncene' WHERE id = :id");
  $query->execute([":id" => $order_id]);
}

send_data_response(
  !empty($carrier_response["success"]),
  $carrier_response["message"] ?? "Zásielku sa nepodarilo odoslať.",
  $carrier_response["http_code"] ?? (!empty($carrier_response["success"]) ? 200 : 422)
);
