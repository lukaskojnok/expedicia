<?php
ini_set("display_errors", "0");

require_once __DIR__ . "/../config/common.php";
require_once __DIR__ . "/../config/controls_log.php";

header("Content-Type: application/json; charset=utf-8");

function order_logs_response($success, $data, $http_code = 200) {
  http_response_code($http_code);
  echo json_encode(array_merge(["success" => $success], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

set_exception_handler(function($exception) {
  order_logs_response(false, [
    "message" => "Históriu objednávky sa nepodarilo načítať.",
    "error" => $exception->getMessage()
  ], 500);
});

$admin_data = controls_get_admin_data($db);

if (empty($admin_data["id"])) {
  order_logs_response(false, ["message" => "Prihlásenie vypršalo. Obnov stránku a prihlás sa znova."], 401);
}

$order_id = (int) ($_GET["order_id"] ?? 0);

if ($order_id <= 0) {
  order_logs_response(false, ["message" => "Chýba ID objednávky."], 422);
}

$query = $db->prepare("
  SELECT
    id,
    cislo_objednavky,
    cislo_faktury,
    dopravca_label_subory
  FROM orders
  WHERE id = :id
  LIMIT 1
");
$query->execute([":id" => $order_id]);
$order = $query->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  order_logs_response(false, ["message" => "Objednávka nebola nájdená."], 404);
}

$order_label_files = json_decode((string) ($order["dopravca_label_subory"] ?? ""), true);
$order["label_files"] = is_array($order_label_files) ? $order_label_files : [];
unset($order["dopravca_label_subory"]);

$query = $db->prepare("
  SELECT
    controls_logs.id,
    controls_logs.typ_kontroly,
    controls_logs.action,
    controls_logs.status,
    controls_logs.otvorene_at,
    controls_logs.ukoncene_at,
    controls_logs.created_at,
    controls_logs.carrier,
    controls_logs.shipment_reference,
    controls_logs.api_http_code,
    controls_logs.api_response,
    controls_logs.label_files,
    controls_logs.print_requested,
    controls_logs.message,
    COALESCE(NULLIF(admins.name, ''), admins.login, CONCAT('User ID: ', controls_logs.user_id)) AS user_name
  FROM controls_logs
  LEFT JOIN admins
    ON admins.id = controls_logs.user_id
  WHERE controls_logs.order_id = :order_id
  ORDER BY controls_logs.created_at DESC, controls_logs.id DESC
");
$query->execute([":order_id" => $order_id]);
$logs = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($logs as &$log) {
  $label_files = json_decode((string) ($log["label_files"] ?? ""), true);
  $log["label_files"] = is_array($label_files) ? $label_files : [];
  $log["print_requested"] = !empty($log["print_requested"]);
}
unset($log);

order_logs_response(true, [
  "order" => $order,
  "logs" => $logs
]);